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
 * The mod_tarefalv assessable submitted event.
 *
 * @package    mod_tarefalv
 * @copyright  2013 Frédéric Massart
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_tarefalv\event;

defined('MOODLE_INTERNAL') || die();

/**
 * The mod_tarefalv assessable submitted event class.
 *
 * @property-read array $other {
 *      Extra information about event.
 *
 *      - bool submission_editable: is submission editable.
 * }
 *
 * @package    mod_tarefalv
 * @since      Moodle 2.6
 * @copyright  2013 Frédéric Massart
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class assessable_submitted extends base {
    /**
     * Create instance of event.
     *
     * @since Moodle 2.7
     *
     * @param \tarefalv $tarefalv
     * @param \stdClass $submission
     * @param bool $editable
     * @return assessable_submitted
     */
    public static function create_from_submission(\tarefalv $tarefalv, \stdClass $submission, $editable) {
        global $USER;

        $data = array(
            'context' => $tarefalv->get_context(),
            'objectid' => $submission->id,
            'other' => array(
                'submission_editable' => $editable,
            ),
        );
        if (!empty($submission->userid) && ($submission->userid != $USER->id)) {
            $data['relateduserid'] = $submission->userid;
        }
        /** @var assessable_submitted $event */
        $event = self::create($data);
        $event->set_tarefalv($tarefalv);
        $event->add_record_snapshot('tarefalv_submission', $submission);
        return $event;
    }

    /**
     * Returns description of what happened.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '$this->userid' has submitted the submission with id '$this->objectid' " .
            "for the assignment with course module id '$this->contextinstanceid'.";
    }

    /**
     * Legacy event data if get_legacy_eventname() is not empty.
     *
     * @return \stdClass
     */
    protected function get_legacy_eventdata() {
        $eventdata = new \stdClass();
        $eventdata->modulename = 'tarefalv';
        $eventdata->cmid = $this->contextinstanceid;
        $eventdata->itemid = $this->objectid;
        $eventdata->courseid = $this->courseid;
        $eventdata->userid = $this->userid;
        $eventdata->params = array('submission_editable' => $this->other['submission_editable']);
        return $eventdata;
    }

    /**
     * Return the legacy event name.
     *
     * @return string
     */
    public static function get_legacy_eventname() {
        return 'assessable_submitted';
    }

    /**
     * Return localised event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventassessablesubmitted', 'mod_tarefalv');
    }

    /**
     * Init method.
     *
     * @return void
     */
    protected function init() {
        $this->data['objecttable'] = 'tarefalv_submission';
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
    }

    /**
     * Return legacy data for add_to_log().
     *
     * @return array
     */
    protected function get_legacy_logdata() {
        $submission = $this->get_record_snapshot('tarefalv_submission', $this->objectid);
        $this->set_legacy_logdata('submit for grading', $this->tarefalv->format_submission_for_log($submission));
        return parent::get_legacy_logdata();
    }

    /**
     * Custom validation.
     *
     * @throws \coding_exception
     * @return void
     */
    protected function validate_data() {
        parent::validate_data();

        if (!isset($this->other['submission_editable'])) {
            throw new \coding_exception('The \'submission_editable\' value must be set in other.');
        }
    }

    public static function get_objectid_mapping() {
        return array('db' => 'tarefalv_submission', 'restore' => 'submission');
    }

    public static function get_other_mapping() {
        // Nothing to map.
        return false;
    }
}
