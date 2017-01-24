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
 * @package    mod_chatlv
 * @subpackage backup-moodle2
 * @copyright 2010 onwards Eloy Lafuente (stronk7) {@link http://stronk7.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Define all the restore steps that will be used by the restore_chatlv_activity_task
 */

/**
 * Structure step to restore one chatlv activity
 */
class restore_chatlv_activity_structure_step extends restore_activity_structure_step {

    protected function define_structure() {

        $paths = array();
        $userinfo = $this->get_setting_value('userinfo');

        $paths[] = new restore_path_element('chatlv', '/activity/chatlv');
        if ($userinfo) {
            $paths[] = new restore_path_element('chatlv_message', '/activity/chatlv/messages/message');
        }

        // Return the paths wrapped into standard activity structure.
        return $this->prepare_activity_structure($paths);
    }

    protected function process_chatlv($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->course = $this->get_courseid();

        $data->chatlvtime = $this->apply_date_offset($data->chatlvtime);
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        // Insert the chatlv record.
        $newitemid = $DB->insert_record('chatlv', $data);
        // Immediately after inserting "activity" record, call this.
        $this->apply_activity_instance($newitemid);
    }

    protected function process_chatlv_message($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->chatlvid = $this->get_new_parentid('chatlv');
        $data->userid = $this->get_mappingid('user', $data->userid);
        $data->groupid = $this->get_mappingid('group', $data->groupid);
        $data->message = $data->message_text;
        $data->timestamp = $this->apply_date_offset($data->timestamp);

        $newitemid = $DB->insert_record('chatlv_messages', $data);
        $this->set_mapping('chatlv_message', $oldid, $newitemid); // Because of decode.
    }

    protected function after_execute() {
        // Add chatlv related files, no need to match by itemname (just internally handled context).
        $this->add_related_files('mod_chatlv', 'intro', null);
    }
}
