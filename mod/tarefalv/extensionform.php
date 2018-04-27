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
 * This file contains the forms to create and edit an instance of this module
 *
 * @package   mod_tarefalv
 * @copyright 2012 NetSpot {@link http://www.netspot.com.au}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die('Direct access to this script is forbidden.');


require_once($CFG->libdir.'/formslib.php');
require_once($CFG->dirroot . '/mod/tarefalv/locallib.php');

/**
 * Assignment extension dates form
 *
 * @package   mod_tarefalv
 * @copyright 2012 NetSpot {@link http://www.netspot.com.au}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_tarefalv_extension_form extends moodleform {
    /** @var array $instance - The data passed to this form */
    private $instance;

    /**
     * Define the form - called by parent constructor
     */
    public function definition() {
        global $DB;

        $mform = $this->_form;
        $params = $this->_customdata;

        // Instance variable is used by the form validation function.
        $instance = $params['instance'];
        $this->instance = $instance;

        // Get the assignment class.
        $tarefalv = $params['tarefalv'];
        $userlist = $params['userlist'];
        $usercount = 0;
        $usershtml = '';

        $extrauserfields = get_extra_user_fields($tarefalv->get_context());
        foreach ($userlist as $userid) {
            if ($usercount >= 5) {
                $usershtml .= get_string('moreusers', 'tarefalv', count($userlist) - 5);
                break;
            }
            $user = $DB->get_record('user', array('id' => $userid), '*', MUST_EXIST);

            $usershtml .= $tarefalv->get_renderer()->render(new tarefalv_user_summary($user,
                                                                    $tarefalv->get_course()->id,
                                                                    has_capability('moodle/site:viewfullnames',
                                                                    $tarefalv->get_course_context()),
                                                                    $tarefalv->is_blind_marking(),
                                                                    $tarefalv->get_uniqueid_for_user($user->id),
                                                                    $extrauserfields,
                                                                    !$tarefalv->is_active_user($userid)));
                $usercount += 1;
        }

        $userscount = count($userlist);

        $listusersmessage = get_string('grantextensionforusers', 'tarefalv', $userscount);
        $mform->addElement('header', 'general', $listusersmessage);
        $mform->addElement('static', 'userslist', get_string('selectedusers', 'tarefalv'), $usershtml);

        if ($instance->allowsubmissionsfromdate) {
            $mform->addElement('static', 'allowsubmissionsfromdate', get_string('allowsubmissionsfromdate', 'tarefalv'),
                               userdate($instance->allowsubmissionsfromdate));
        }

        $finaldate = 0;
        if ($instance->duedate) {
            $mform->addElement('static', 'duedate', get_string('duedate', 'tarefalv'), userdate($instance->duedate));
            $finaldate = $instance->duedate;
        }
        if ($instance->cutoffdate) {
            $mform->addElement('static', 'cutoffdate', get_string('cutoffdate', 'tarefalv'), userdate($instance->cutoffdate));
            $finaldate = $instance->cutoffdate;
        }
        $mform->addElement('date_time_selector', 'extensionduedate',
                           get_string('extensionduedate', 'tarefalv'), array('optional'=>true));
        $mform->setDefault('extensionduedate', $finaldate);

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'userid');
        $mform->setType('userid', PARAM_INT);
        $mform->addElement('hidden', 'selectedusers');
        $mform->setType('selectedusers', PARAM_SEQUENCE);
        $mform->addElement('hidden', 'action', 'saveextension');
        $mform->setType('action', PARAM_ALPHA);

        $this->add_action_buttons(true, get_string('savechanges', 'tarefalv'));
    }

    /**
     * Perform validation on the extension form
     * @param array $data
     * @param array $files
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if ($this->instance->duedate && $data['extensionduedate']) {
            if ($this->instance->duedate > $data['extensionduedate']) {
                $errors['extensionduedate'] = get_string('extensionnotafterduedate', 'tarefalv');
            }
        }
        if ($this->instance->allowsubmissionsfromdate && $data['extensionduedate']) {
            if ($this->instance->allowsubmissionsfromdate > $data['extensionduedate']) {
                $errors['extensionduedate'] = get_string('extensionnotafterfromdate', 'tarefalv');
            }
        }

        return $errors;
    }
}
