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
 * The mod_tarefalv submission unlocked event.
 *
 * @package    mod_tarefalv
 * @copyright  2013 Frédéric Massart
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_tarefalv\event;

defined('MOODLE_INTERNAL') || die();

/**
 * The mod_tarefalv submission unlocked event class.
 *
 * @package    mod_tarefalv
 * @since      Moodle 2.6
 * @copyright  2013 Frédéric Massart
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class submission_unlocked extends base {
    /**
     * Flag for prevention of direct create() call.
     * @var bool
     */
    protected static $preventcreatecall = true;

    /**
     * Create instance of event.
     *
     * @since Moodle 2.7
     *
     * @param \tarefalv $tarefalv
     * @param \stdClass $user
     * @return submission_unlocked
     */
    public static function create_from_user(\tarefalv $tarefalv, \stdClass $user) {
        $data = array(
            'context' => $tarefalv->get_context(),
            'objectid' => $tarefalv->get_instance()->id,
            'relateduserid' => $user->id,
        );
        self::$preventcreatecall = false;
        /** @var submission_unlocked $event */
        $event = self::create($data);
        self::$preventcreatecall = true;
        $event->set_tarefalv($tarefalv);
        $event->add_record_snapshot('user', $user);
        return $event;
    }

    /**
     * Returns description of what happened.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '$this->userid' locked the submission for the user with id '$this->relateduserid' " .
            "for the assignment with course module id '$this->contextinstanceid'.";
    }

    /**
     * Return localised event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventsubmissionunlocked', 'mod_tarefalv');
    }

    /**
     * Init method.
     *
     * @return void
     */
    protected function init() {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_TEACHING;
        $this->data['objecttable'] = 'tarefalv';
    }

    /**
     * Return legacy data for add_to_log().
     *
     * @return array
     */
    protected function get_legacy_logdata() {
        $user = $this->get_record_snapshot('user', $this->relateduserid);
        $logmessage = get_string('unlocksubmissionforstudent', 'tarefalv', array('id' => $user->id, 'fullname' => fullname($user)));
        $this->set_legacy_logdata('unlock submission', $logmessage);
        return parent::get_legacy_logdata();
    }

    /**
     * Custom validation.
     *
     * @throws \coding_exception
     */
    protected function validate_data() {
        if (self::$preventcreatecall) {
            throw new \coding_exception('cannot call submission_unlocked::create() directly, use submission_unlocked::create_from_user() instead.');
        }

        parent::validate_data();

        if (!isset($this->relateduserid)) {
            throw new \coding_exception('The \'relateduserid\' must be set.');
        }
    }

    public static function get_objectid_mapping() {
        return array('db' => 'tarefalv', 'restore' => 'tarefalv');
    }
}
