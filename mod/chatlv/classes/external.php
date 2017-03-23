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
 * Chatlv external API
 *
 * @package    mod_chatlv
 * @category   external
 * @copyright  2015 Juan Leyva <juan@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.0
 */

defined('MOODLE_INTERNAL') || die;

require_once($CFG->libdir . '/externallib.php');
require_once($CFG->dirroot . '/mod/chatlv/lib.php');

/**
 * Chatlv external functions
 *
 * @package    mod_chatlv
 * @category   external
 * @copyright  2015 Juan Leyva <juan@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.0
 */
class mod_chatlv_external extends external_api {

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 3.0
     */
    public static function login_user_parameters() {
        return new external_function_parameters(
            array(
                'chatlvid' => new external_value(PARAM_INT, 'chatlv instance id'),
                'groupid' => new external_value(PARAM_INT, 'group id, 0 means that the function will determine the user group',
                                                VALUE_DEFAULT, 0),
            )
        );
    }

    /**
     * Log the current user into a chatlv room in the given chatlv.
     *
     * @param int $chatlvid the chatlv instance id
     * @param int $groupid the user group id
     * @return array of warnings and the chatlv unique session id
     * @since Moodle 3.0
     * @throws moodle_exception
     */
    public static function login_user($chatlvid, $groupid = 0) {
        global $DB;

        $params = self::validate_parameters(self::login_user_parameters(),
                                            array(
                                                'chatlvid' => $chatlvid,
                                                'groupid' => $groupid
                                            ));
        $warnings = array();

        // Request and permission validation.
        $chatlv = $DB->get_record('chatlv', array('id' => $params['chatlvid']), '*', MUST_EXIST);
        list($course, $cm) = get_course_and_cm_from_instance($chatlv, 'chatlv');

        $context = context_module::instance($cm->id);
        self::validate_context($context);

        require_capability('mod/chatlv:chatlv', $context);

        if (!empty($params['groupid'])) {
            $groupid = $params['groupid'];
            // Determine is the group is visible to user.
            if (!groups_group_visible($groupid, $course, $cm)) {
                throw new moodle_exception('notingroup');
            }
        } else {
            // Check to see if groups are being used here.
            if ($groupmode = groups_get_activity_groupmode($cm)) {
                $groupid = groups_get_activity_group($cm);
                // Determine is the group is visible to user (this is particullary for the group 0).
                if (!groups_group_visible($groupid, $course, $cm)) {
                    throw new moodle_exception('notingroup');
                }
            } else {
                $groupid = 0;
            }
        }

        // Get the unique chatlv session id.
        // Since we are going to use the chatlv via Web Service requests we set the ajax version (since it's the most similar).
        if (!$chatlvsid = chatlv_login_user($chatlv->id, 'ajax', $groupid, $course)) {
            throw moodle_exception('cantlogin', 'chatlv');
        }

        $result = array();
        $result['chatlvsid'] = $chatlvsid;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     * @since Moodle 3.0
     */
    public static function login_user_returns() {
        return new external_single_structure(
            array(
                'chatlvsid' => new external_value(PARAM_ALPHANUM, 'unique chatlv session id'),
                'warnings' => new external_warnings()
            )
        );
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 3.0
     */
    public static function get_chatlv_users_parameters() {
        return new external_function_parameters(
            array(
                'chatlvsid' => new external_value(PARAM_ALPHANUM, 'chatlv session id (obtained via mod_chatlv_login_user)')
            )
        );
    }

    /**
     * Get the list of users in the given chatlv session.
     *
     * @param int $chatlvsid the chatlv session id
     * @return array of warnings and the user lists
     * @since Moodle 3.0
     * @throws moodle_exception
     */
    public static function get_chatlv_users($chatlvsid) {
        global $DB, $PAGE;

        $params = self::validate_parameters(self::get_chatlv_users_parameters(),
                                            array(
                                                'chatlvsid' => $chatlvsid
                                            ));
        $warnings = array();

        // Request and permission validation.
        if (!$chatlvuser = $DB->get_record('chatlv_users', array('sid' => $params['chatlvsid']))) {
            throw new moodle_exception('notlogged', 'chatlv');
        }
        $chatlv = $DB->get_record('chatlv', array('id' => $chatlvuser->chatlvid), '*', MUST_EXIST);
        list($course, $cm) = get_course_and_cm_from_instance($chatlv, 'chatlv');

        $context = context_module::instance($cm->id);
        self::validate_context($context);

        require_capability('mod/chatlv:chatlv', $context);

        // First, delete old users from the chatlvs.
        chatlv_delete_old_users();

        $users = chatlv_get_users($chatlvuser->chatlvid, $chatlvuser->groupid, $cm->groupingid);
        $returnedusers = array();

        foreach ($users as $user) {

            $userpicture = new user_picture($user);
            $userpicture->size = 1; // Size f1.
            $profileimageurl = $userpicture->get_url($PAGE)->out(false);

            $returnedusers[] = array(
                'id' => $user->id,
                'fullname' => fullname($user),
                'profileimageurl' => $profileimageurl
            );
        }

        $result = array();
        $result['users'] = $returnedusers;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     * @since Moodle 3.0
     */
    public static function get_chatlv_users_returns() {
        return new external_single_structure(
            array(
                'users' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'id' => new external_value(PARAM_INT, 'user id'),
                            'fullname' => new external_value(PARAM_NOTAGS, 'user full name'),
                            'profileimageurl' => new external_value(PARAM_URL, 'user picture URL'),
                        )
                    ),
                    'list of users'
                ),
                'warnings' => new external_warnings()
            )
        );
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 3.0
     */
    public static function send_chatlv_message_parameters() {
        return new external_function_parameters(
            array(
                'chatlvsid' => new external_value(PARAM_ALPHANUM, 'chatlv session id (obtained via mod_chatlv_login_user)'),
                'messagetext' => new external_value(PARAM_RAW, 'the message text'),
                'beepid' => new external_value(PARAM_RAW, 'the beep id', VALUE_DEFAULT, ''),

            )
        );
    }

    /**
     * Send a message on the given chatlv session.
     *
     * @param int $chatlvsid the chatlv session id
     * @param string $messagetext the message text
     * @param string $beepid the beep message id
     * @return array of warnings and the new message id (0 if the message was empty)
     * @since Moodle 3.0
     * @throws moodle_exception
     */
    public static function send_chatlv_message($chatlvsid, $messagetext, $beepid = '') {
        global $DB;

        $params = self::validate_parameters(self::send_chatlv_message_parameters(),
                                            array(
                                                'chatlvsid' => $chatlvsid,
                                                'messagetext' => $messagetext,
                                                'beepid' => $beepid
                                            ));
        $warnings = array();

        // Request and permission validation.
        if (!$chatlvuser = $DB->get_record('chatlv_users', array('sid' => $params['chatlvsid']))) {
            throw new moodle_exception('notlogged', 'chatlv');
        }
        $chatlv = $DB->get_record('chatlv', array('id' => $chatlvuser->chatlvid), '*', MUST_EXIST);
        list($course, $cm) = get_course_and_cm_from_instance($chatlv, 'chatlv');

        $context = context_module::instance($cm->id);
        self::validate_context($context);

        require_capability('mod/chatlv:chatlv', $context);

        $chatlvmessage = clean_text($params['messagetext'], FORMAT_MOODLE);

        if (!empty($params['beepid'])) {
            $chatlvmessage = 'beep ' . $params['beepid'];
        }

        if (!empty($chatlvmessage)) {
            // Send the message.
            $messageid = chatlv_send_chatlvmessage($chatlvuser, $chatlvmessage, 0, $cm);
            // Update ping time.
            $chatlvuser->lastmessageping = time() - 2;
            $DB->update_record('chatlv_users', $chatlvuser);
        } else {
            $messageid = 0;
        }

        $result = array();
        $result['messageid'] = $messageid;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     * @since Moodle 3.0
     */
    public static function send_chatlv_message_returns() {
        return new external_single_structure(
            array(
                'messageid' => new external_value(PARAM_INT, 'message sent id'),
                'warnings' => new external_warnings()
            )
        );
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 3.0
     */
    public static function get_chatlv_latest_messages_parameters() {
        return new external_function_parameters(
            array(
                'chatlvsid' => new external_value(PARAM_ALPHANUM, 'chatlv session id (obtained via mod_chatlv_login_user)'),
                'chatlvlasttime' => new external_value(PARAM_INT, 'last time messages were retrieved (epoch time)', VALUE_DEFAULT, 0)
            )
        );
    }

    /**
     * Get the latest messages from the given chatlv session.
     *
     * @param int $chatlvsid the chatlv session id
     * @param int $chatlvlasttime last time messages were retrieved (epoch time)
     * @return array of warnings and the new message id (0 if the message was empty)
     * @since Moodle 3.0
     * @throws moodle_exception
     */
    public static function get_chatlv_latest_messages($chatlvsid, $chatlvlasttime = 0) {
        global $DB, $CFG;

        $params = self::validate_parameters(self::get_chatlv_latest_messages_parameters(),
                                            array(
                                                'chatlvsid' => $chatlvsid,
                                                'chatlvlasttime' => $chatlvlasttime
                                            ));
        $warnings = array();

        // Request and permission validation.
        if (!$chatlvuser = $DB->get_record('chatlv_users', array('sid' => $params['chatlvsid']))) {
            throw new moodle_exception('notlogged', 'chatlv');
        }
        $chatlv = $DB->get_record('chatlv', array('id' => $chatlvuser->chatlvid), '*', MUST_EXIST);
        list($course, $cm) = get_course_and_cm_from_instance($chatlv, 'chatlv');

        $context = context_module::instance($cm->id);
        self::validate_context($context);

        require_capability('mod/chatlv:chatlv', $context);

        $chatlvlasttime = $params['chatlvlasttime'];
        if ((time() - $chatlvlasttime) > $CFG->chatlv_old_ping) {
            chatlv_delete_old_users();
        }

        // Set default chatlv last time (to not retrieve all the conversations).
        if ($chatlvlasttime == 0) {
            $chatlvlasttime = time() - $CFG->chatlv_old_ping;
        }

        if ($latestmessage = chatlv_get_latest_message($chatlvuser->chatlvid, $chatlvuser->groupid)) {
            $chatlvnewlasttime = $latestmessage->timestamp;
        } else {
            $chatlvnewlasttime = 0;
        }

        $messages = chatlv_get_latest_messages($chatlvuser, $chatlvlasttime);
        $returnedmessages = array();

        foreach ($messages as $message) {

            // FORMAT_MOODLE is mandatory in the chatlv plugin.
            list($messageformatted, $format) = external_format_text($message->message, FORMAT_MOODLE, $context->id, 'mod_chatlv',
                                                                    '', 0);

            $returnedmessages[] = array(
                'id' => $message->id,
                'userid' => $message->userid,
                'system' => (bool) $message->system,
                'message' => $messageformatted,
                'timestamp' => $message->timestamp,
            );
        }

        // Update our status since we are active in the chatlv.
        $DB->set_field('chatlv_users', 'lastping', time(), array('id' => $chatlvuser->id));

        $result = array();
        $result['messages'] = $returnedmessages;
        $result['chatlvnewlasttime'] = $chatlvnewlasttime;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     * @since Moodle 3.0
     */
    public static function get_chatlv_latest_messages_returns() {
        return new external_single_structure(
            array(
                'messages' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'id' => new external_value(PARAM_INT, 'message id'),
                            'userid' => new external_value(PARAM_INT, 'user id'),
                            'system' => new external_value(PARAM_BOOL, 'true if is a system message (like user joined)'),
                            'message' => new external_value(PARAM_RAW, 'message text'),
                            'timestamp' => new external_value(PARAM_INT, 'timestamp for the message'),
                        )
                    ),
                    'list of users'
                ),
                'chatlvnewlasttime' => new external_value(PARAM_INT, 'new last time'),
                'warnings' => new external_warnings()
            )
        );
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 3.0
     */
    public static function view_chatlv_parameters() {
        return new external_function_parameters(
            array(
                'chatlvid' => new external_value(PARAM_INT, 'chatlv instance id')
            )
        );
    }

    /**
     * Trigger the course module viewed event and update the module completion status.
     *
     * @param int $chatlvid the chatlv instance id
     * @return array of warnings and status result
     * @since Moodle 3.0
     * @throws moodle_exception
     */
    public static function view_chatlv($chatlvid) {
        global $DB, $CFG;

        $params = self::validate_parameters(self::view_chatlv_parameters(),
                                            array(
                                                'chatlvid' => $chatlvid
                                            ));
        $warnings = array();

        // Request and permission validation.
        $chatlv = $DB->get_record('chatlv', array('id' => $params['chatlvid']), '*', MUST_EXIST);
        list($course, $cm) = get_course_and_cm_from_instance($chatlv, 'chatlv');

        $context = context_module::instance($cm->id);
        self::validate_context($context);

        require_capability('mod/chatlv:chatlv', $context);

        // Call the url/lib API.
        chatlv_view($chatlv, $course, $cm, $context);

        $result = array();
        $result['status'] = true;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     * @since Moodle 3.0
     */
    public static function view_chatlv_returns() {
        return new external_single_structure(
            array(
                'status' => new external_value(PARAM_BOOL, 'status: true if success'),
                'warnings' => new external_warnings()
            )
        );
    }


    /**
     * Describes the parameters for get_chatlvs_by_courses.
     *
     * @return external_external_function_parameters
     * @since Moodle 3.0
     */
    public static function get_chatlvs_by_courses_parameters() {
        return new external_function_parameters (
            array(
                'courseids' => new external_multiple_structure(
                    new external_value(PARAM_INT, 'course id'), 'Array of course ids', VALUE_DEFAULT, array()
                ),
            )
        );
    }

    /**
     * Returns a list of chatlvs in a provided list of courses,
     * if no list is provided all chatlvs that the user can view will be returned.
     *
     * @param array $courseids the course ids
     * @return array of chatlvs details
     * @since Moodle 3.0
     */
    public static function get_chatlvs_by_courses($courseids = array()) {
        global $CFG;

        $returnedchatlvs = array();
        $warnings = array();

        $params = self::validate_parameters(self::get_chatlvs_by_courses_parameters(), array('courseids' => $courseids));

        $courses = array();
        if (empty($params['courseids'])) {
            $courses = enrol_get_my_courses();
            $params['courseids'] = array_keys($courses);
        }

        // Ensure there are courseids to loop through.
        if (!empty($params['courseids'])) {

            list($courses, $warnings) = external_util::validate_courses($params['courseids'], $courses);

            // Get the chatlvs in this course, this function checks users visibility permissions.
            // We can avoid then additional validate_context calls.
            $chatlvs = get_all_instances_in_courses("chatlv", $courses);
            foreach ($chatlvs as $chatlv) {
                $chatlvcontext = context_module::instance($chatlv->coursemodule);
                // Entry to return.
                $chatlvdetails = array();
                // First, we return information that any user can see in the web interface.
                $chatlvdetails['id'] = $chatlv->id;
                $chatlvdetails['coursemodule']      = $chatlv->coursemodule;
                $chatlvdetails['course']            = $chatlv->course;
                $chatlvdetails['name']              = external_format_string($chatlv->name, $chatlvcontext->id);
                // Format intro.
                list($chatlvdetails['intro'], $chatlvdetails['introformat']) =
                    external_format_text($chatlv->intro, $chatlv->introformat, $chatlvcontext->id, 'mod_chatlv', 'intro', null);

                if (has_capability('mod/chatlv:chatlv', $chatlvcontext)) {
                    $chatlvdetails['chatlvmethod']    = $CFG->chatlv_method;
                    $chatlvdetails['keepdays']      = $chatlv->keepdays;
                    $chatlvdetails['studentlogs']   = $chatlv->studentlogs;
                    $chatlvdetails['chatlvtime']      = $chatlv->chatlvtime;
                    $chatlvdetails['schedule']      = $chatlv->schedule;
                }

                if (has_capability('moodle/course:manageactivities', $chatlvcontext)) {
                    $chatlvdetails['timemodified']  = $chatlv->timemodified;
                    $chatlvdetails['section']       = $chatlv->section;
                    $chatlvdetails['visible']       = $chatlv->visible;
                    $chatlvdetails['groupmode']     = $chatlv->groupmode;
                    $chatlvdetails['groupingid']    = $chatlv->groupingid;
                }
                $returnedchatlvs[] = $chatlvdetails;
            }
        }
        $result = array();
        $result['chatlvs'] = $returnedchatlvs;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Describes the get_chatlvs_by_courses return value.
     *
     * @return external_single_structure
     * @since Moodle 3.0
     */
    public static function get_chatlvs_by_courses_returns() {
        return new external_single_structure(
            array(
                'chatlvs' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'id' => new external_value(PARAM_INT, 'Chatlv id'),
                            'coursemodule' => new external_value(PARAM_INT, 'Course module id'),
                            'course' => new external_value(PARAM_INT, 'Course id'),
                            'name' => new external_value(PARAM_RAW, 'Chatlv name'),
                            'intro' => new external_value(PARAM_RAW, 'The Chatlv intro'),
                            'introformat' => new external_format_value('intro'),
                            'chatlvmethod' => new external_value(PARAM_ALPHA, 'chatlv method (sockets, daemon)', VALUE_OPTIONAL),
                            'keepdays' => new external_value(PARAM_INT, 'keep days', VALUE_OPTIONAL),
                            'studentlogs' => new external_value(PARAM_INT, 'student logs visible to everyone', VALUE_OPTIONAL),
                            'chatlvtime' => new external_value(PARAM_INT, 'chatlv time', VALUE_OPTIONAL),
                            'schedule' => new external_value(PARAM_INT, 'schedule type', VALUE_OPTIONAL),
                            'timemodified' => new external_value(PARAM_INT, 'time of last modification', VALUE_OPTIONAL),
                            'section' => new external_value(PARAM_INT, 'course section id', VALUE_OPTIONAL),
                            'visible' => new external_value(PARAM_BOOL, 'visible', VALUE_OPTIONAL),
                            'groupmode' => new external_value(PARAM_INT, 'group mode', VALUE_OPTIONAL),
                            'groupingid' => new external_value(PARAM_INT, 'group id', VALUE_OPTIONAL),
                        ), 'Chats'
                    )
                ),
                'warnings' => new external_warnings(),
            )
        );
    }
}
