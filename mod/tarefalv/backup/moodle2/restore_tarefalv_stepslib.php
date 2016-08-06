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
 * @package moodlecore
 * @subpackage backup-moodle2
 * @copyright 2010 onwards Eloy Lafuente (stronk7) {@link http://stronk7.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Define all the restore steps that will be used by the restore_tarefalv_activity_task
 */

/**
 * Structure step to restore one tarefalv activity
 */
class restore_tarefalv_activity_structure_step extends restore_activity_structure_step {

    protected function define_structure() {

        $paths = array();
        $userinfo = $this->get_setting_value('userinfo');

        $tarefalv = new restore_path_element('tarefalv', '/activity/tarefalv');
        $paths[] = $tarefalv;

        // Apply for 'tarefalv' subplugins optional paths at tarefalv level
        $this->add_subplugin_structure('tarefalv', $tarefalv);

        if ($userinfo) {
            $submission = new restore_path_element('tarefalv_submission', '/activity/tarefalv/submissions/submission');
            $paths[] = $submission;
            // Apply for 'tarefalv' subplugins optional stuff at submission level
            $this->add_subplugin_structure('tarefalv', $submission);
        }

        // Return the paths wrapped into standard activity structure
        return $this->prepare_activity_structure($paths);
    }

    protected function process_tarefalv($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->course = $this->get_courseid();

        $data->timedue = $this->apply_date_offset($data->timedue);
        $data->timeavailable = $this->apply_date_offset($data->timeavailable);
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        if ($data->grade < 0) { // scale found, get mapping
            $data->grade = -($this->get_mappingid('scale', abs($data->grade)));
        }

        // insert the tarefalv record
        $newitemid = $DB->insert_record('tarefalv', $data);
        // immediately after inserting "activity" record, call this
        $this->apply_activity_instance($newitemid);
    }

    protected function process_tarefalv_submission($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->tarefalv = $this->get_new_parentid('tarefalv');
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);
        $data->timemarked = $this->apply_date_offset($data->timemarked);

        $data->userid = $this->get_mappingid('user', $data->userid);
        $data->teacher = $this->get_mappingid('user', $data->teacher);

        $newitemid = $DB->insert_record('tarefalv_submissions', $data);
        $this->set_mapping('tarefalv_submission', $oldid, $newitemid, true); // Going to have files
        $this->set_mapping(restore_gradingform_plugin::itemid_mapping('submission'), $oldid, $newitemid);
    }

    protected function after_execute() {
        // Add tarefalv related files, no need to match by itemname (just internally handled context)
        $this->add_related_files('mod_tarefalv', 'intro', null);
        // Add tarefalv submission files, matching by tarefalv_submission itemname
        $this->add_related_files('mod_tarefalv', 'submission', 'tarefalv_submission');
        $this->add_related_files('mod_tarefalv', 'response', 'tarefalv_submission');
    }
}
