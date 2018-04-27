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
 * The mod_tarefalv submission viewed event.
 *
 * @package    mod_tarefalv
 * @copyright  2014 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_tarefalv\event;

defined('MOODLE_INTERNAL') || die();

/**
 * The mod_tarefalv submission viewed event class.
 *
 * @property-read array $other {
 *      Extra information about the event.
 *
 *      - int tarefalvid: the id of the assignment.
 * }
 *
 * @package    mod_tarefalv
 * @since      Moodle 2.7
 * @copyright  2014 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class submission_viewed extends base {
    /**
     * Create instance of event.
     *
     * @param \tarefalv $tarefalv
     * @param \stdClass $submission
     * @return submission_viewed
     */
    public static function create_from_submission(\tarefalv $tarefalv, \stdClass $submission) {
        $data = array(
            'objectid' => $submission->id,
            'relateduserid' => $submission->userid,
            'context' => $tarefalv->get_context(),
            'other' => array(
                'tarefalvid' => $tarefalv->get_instance()->id,
            ),
        );
        /** @var submission_viewed $event */
        $event = self::create($data);
        $event->set_tarefalv($tarefalv);
        $event->add_record_snapshot('tarefalv_submission', $submission);
        return $event;
    }

    /**
     * Init method.
     */
    protected function init() {
        $this->data['objecttable'] = 'tarefalv_submission';
        $this->data['crud'] = 'r';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
    }

    /**
     * Returns localised general event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventsubmissionviewed', 'mod_tarefalv');
    }

    /**
     * Returns description of what happened.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '$this->userid' viewed the submission for the user with id '$this->relateduserid' for the " .
            "assignment with course module id '$this->contextinstanceid'.";
    }

    /**
     * Return legacy data for add_to_log().
     *
     * @return array
     */
    protected function get_legacy_logdata() {
        $logmessage = get_string('viewsubmissionforuser', 'tarefalv', $this->relateduserid);
        $this->set_legacy_logdata('view submission', $logmessage);
        return parent::get_legacy_logdata();
    }

    /**
     * Custom validation.
     *
     * @throws \coding_exception
     */
    protected function validate_data() {
        parent::validate_data();

        if (!isset($this->relateduserid)) {
            throw new \coding_exception('The \'relateduserid\' must be set.');
        }

        if (!isset($this->other['tarefalvid'])) {
            throw new \coding_exception('The \'tarefalvid\' value must be set in other.');
        }
    }

    public static function get_objectid_mapping() {
        return array('db' => 'tarefalv_submission', 'restore' => 'submission');
    }

    public static function get_other_mapping() {
        $othermapped = array();
        $othermapped['tarefalvid'] = array('db' => 'tarefalv', 'restore' => 'tarefalv');

        return $othermapped;
    }
}
