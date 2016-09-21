<?php

// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * @package    mod_wikilv
 * @subpackage backup-moodle2
 * @copyright 2010 onwards Eloy Lafuente (stronk7) {@link http://stronk7.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Define all the restore steps that will be used by the restore_wikilv_activity_task
 */

/**
 * Structure step to restore one wikilv activity
 */
class restore_wikilv_activity_structure_step extends restore_activity_structure_step {

    protected function define_structure() {

        $paths = array();
        $userinfo = $this->get_setting_value('userinfo');

        $paths[] = new restore_path_element('wikilv', '/activity/wikilv');
        if ($userinfo) {
            $paths[] = new restore_path_element('wikilv_subwikilv', '/activity/wikilv/subwikilvs/subwikilv');
            $paths[] = new restore_path_element('wikilv_page', '/activity/wikilv/subwikilvs/subwikilv/pages/page');
            $paths[] = new restore_path_element('wikilv_version', '/activity/wikilv/subwikilvs/subwikilv/pages/page/versions/version');
            $paths[] = new restore_path_element('wikilv_tag', '/activity/wikilv/subwikilvs/subwikilv/pages/page/tags/tag');
            $paths[] = new restore_path_element('wikilv_synonym', '/activity/wikilv/subwikilvs/subwikilv/synonyms/synonym');
            $paths[] = new restore_path_element('wikilv_link', '/activity/wikilv/subwikilvs/subwikilv/links/link');
        }

        // Return the paths wrapped into standard activity structure
        return $this->prepare_activity_structure($paths);
    }

    protected function process_wikilv($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->course = $this->get_courseid();

        $data->editbegin = $this->apply_date_offset($data->editbegin);
        $data->editend = $this->apply_date_offset($data->editend);
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        // insert the wikilv record
        $newitemid = $DB->insert_record('wikilv', $data);
        // immediately after inserting "activity" record, call this
        $this->apply_activity_instance($newitemid);
    }

    protected function process_wikilv_subwikilv($data) {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;
        $data->wikilvid = $this->get_new_parentid('wikilv');

        // If the groupid is not equal to zero, get the mapping for the group.
        if ((int) $data->groupid !== 0) {
            $data->groupid = $this->get_mappingid('group', $data->groupid);
        }

        // If the userid is not equal to zero, get the mapping for the user.
        if ((int) $data->userid !== 0) {
            $data->userid = $this->get_mappingid('user', $data->userid);
        }

        // If these values are not equal to false then a mapping was successfully made.
        if ($data->groupid !== false && $data->userid !== false) {
            $newitemid = $DB->insert_record('wikilv_subwikilvs', $data);
        } else {
            $newitemid = false;
        }

        $this->set_mapping('wikilv_subwikilv', $oldid, $newitemid, true);
    }

    protected function process_wikilv_page($data) {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;
        $data->subwikilvid = $this->get_new_parentid('wikilv_subwikilv');
        $data->userid = $this->get_mappingid('user', $data->userid);
        $data->timemodified = $this->apply_date_offset($data->timemodified);
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timerendered = $this->apply_date_offset($data->timerendered);

        // Check that we were able to get a parentid for this page.
        if ($data->subwikilvid !== false) {
            $newitemid = $DB->insert_record('wikilv_pages', $data);
        } else {
            $newitemid = false;
        }

        $this->set_mapping('wikilv_page', $oldid, $newitemid, true);
    }

    protected function process_wikilv_version($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->pageid = $this->get_new_parentid('wikilv_page');
        $data->userid = $this->get_mappingid('user', $data->userid);
        $data->timecreated = $this->apply_date_offset($data->timecreated);

        $newitemid = $DB->insert_record('wikilv_versions', $data);
        $this->set_mapping('wikilv_version', $oldid, $newitemid);
    }
    protected function process_wikilv_synonym($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->subwikilvid = $this->get_new_parentid('wikilv_subwikilv');
        $data->pageid = $this->get_mappingid('wikilv_page', $data->pageid);

        $newitemid = $DB->insert_record('wikilv_synonyms', $data);
        // No need to save this mapping as far as nothing depend on it
        // (child paths, file areas nor links decoder)
    }
    protected function process_wikilv_link($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->subwikilvid = $this->get_new_parentid('wikilv_subwikilv');
        $data->frompageid = $this->get_mappingid('wikilv_page', $data->frompageid);
        $data->topageid = $this->get_mappingid('wikilv_page', $data->topageid);

        $newitemid = $DB->insert_record('wikilv_links', $data);
        // No need to save this mapping as far as nothing depend on it
        // (child paths, file areas nor links decoder)
    }

    protected function process_wikilv_tag($data) {
        global $CFG, $DB;

        $data = (object)$data;
        $oldid = $data->id;

        if (!core_tag_tag::is_enabled('mod_wikilv', 'wikilv_pages')) { // Tags disabled in server, nothing to process.
            return;
        }

        $tag = $data->rawname;
        $itemid = $this->get_new_parentid('wikilv_page');
        $wikilvid = $this->get_new_parentid('wikilv');

        $context = context_module::instance($this->task->get_moduleid());
        core_tag_tag::add_item_tag('mod_wikilv', 'wikilv_pages', $itemid, $context, $tag);
    }

    protected function after_execute() {
        // Add wikilv related files, no need to match by itemname (just internally handled context)
        $this->add_related_files('mod_wikilv', 'intro', null);
        $this->add_related_files('mod_wikilv', 'attachments', 'wikilv_subwikilv');
    }
}
