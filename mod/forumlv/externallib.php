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
 * External forumlv API
 *
 * @package    mod_forumlv
 * @copyright  2012 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once("$CFG->libdir/externallib.php");

class mod_forumlv_external extends external_api {

    /**
     * Describes the parameters for get_forumlv.
     *
     * @return external_external_function_parameters
     * @since Moodle 2.5
     */
    public static function get_forumlvs_by_courses_parameters() {
        return new external_function_parameters (
            array(
                'courseids' => new external_multiple_structure(new external_value(PARAM_INT, 'course ID',
                        '', VALUE_REQUIRED, '', NULL_NOT_ALLOWED), 'Array of Course IDs', VALUE_DEFAULT, array()),
            )
        );
    }

    /**
     * Returns a list of forumlvs in a provided list of courses,
     * if no list is provided all forumlvs that the user can view
     * will be returned.
     *
     * @param array $courseids the course ids
     * @return array the forumlv details
     * @since Moodle 2.5
     */
    public static function get_forumlvs_by_courses($courseids = array()) {
        global $CFG, $DB, $USER;

        require_once($CFG->dirroot . "/mod/forumlv/lib.php");

        $params = self::validate_parameters(self::get_forumlvs_by_courses_parameters(), array('courseids' => $courseids));

        if (empty($params['courseids'])) {
            // Get all the courses the user can view.
            $courseids = array_keys(enrol_get_my_courses());
        } else {
            $courseids = $params['courseids'];
        }

        // Array to store the forumlvs to return.
        $arrforumlvs = array();

        // Ensure there are courseids to loop through.
        if (!empty($courseids)) {
            // Go through the courseids and return the forumlvs.
            foreach ($courseids as $cid) {
                // Get the course context.
                $context = context_course::instance($cid);
                // Check the user can function in this context.
                self::validate_context($context);
                // Get the forumlvs in this course.
                if ($forumlvs = $DB->get_records('forumlv', array('course' => $cid))) {
                    // Get the modinfo for the course.
                    $modinfo = get_fast_modinfo($cid);
                    // Get the forumlv instances.
                    $forumlvinstances = $modinfo->get_instances_of('forumlv');
                    // Loop through the forumlvs returned by modinfo.
                    foreach ($forumlvinstances as $forumlvid => $cm) {
                        // If it is not visible or present in the forumlvs get_records call, continue.
                        if (!$cm->uservisible || !isset($forumlvs[$forumlvid])) {
                            continue;
                        }
                        // Set the forumlv object.
                        $forumlv = $forumlvs[$forumlvid];
                        // Get the module context.
                        $context = context_module::instance($cm->id);
                        // Check they have the view forumlv capability.
                        require_capability('mod/forumlv:viewdiscussion', $context);
                        // Format the intro before being returning using the format setting.
                        list($forumlv->intro, $forumlv->introformat) = external_format_text($forumlv->intro, $forumlv->introformat,
                            $context->id, 'mod_forumlv', 'intro', 0);
                        // Add the course module id to the object, this information is useful.
                        $forumlv->cmid = $cm->id;
                        // Add the forumlv to the array to return.
                        $arrforumlvs[$forumlv->id] = (array) $forumlv;
                    }
                }
            }
        }

        return $arrforumlvs;
    }

    /**
     * Describes the get_forumlv return value.
     *
     * @return external_single_structure
     * @since Moodle 2.5
     */
     public static function get_forumlvs_by_courses_returns() {
        return new external_multiple_structure(
            new external_single_structure(
                array(
                    'id' => new external_value(PARAM_INT, 'Forum id'),
                    'course' => new external_value(PARAM_TEXT, 'Course id'),
                    'type' => new external_value(PARAM_TEXT, 'The forumlv type'),
                    'name' => new external_value(PARAM_TEXT, 'Forum name'),
                    'intro' => new external_value(PARAM_RAW, 'The forumlv intro'),
                    'introformat' => new external_format_value('intro'),
                    'assessed' => new external_value(PARAM_INT, 'Aggregate type'),
                    'assesstimestart' => new external_value(PARAM_INT, 'Assess start time'),
                    'assesstimefinish' => new external_value(PARAM_INT, 'Assess finish time'),
                    'scale' => new external_value(PARAM_INT, 'Scale'),
                    'maxbytes' => new external_value(PARAM_INT, 'Maximum attachment size'),
                    'maxattachments' => new external_value(PARAM_INT, 'Maximum number of attachments'),
                    'forcesubscribe' => new external_value(PARAM_INT, 'Force users to subscribe'),
                    'trackingtype' => new external_value(PARAM_INT, 'Subscription mode'),
                    'rsstype' => new external_value(PARAM_INT, 'RSS feed for this activity'),
                    'rssarticles' => new external_value(PARAM_INT, 'Number of RSS recent articles'),
                    'timemodified' => new external_value(PARAM_INT, 'Time modified'),
                    'warnafter' => new external_value(PARAM_INT, 'Post threshold for warning'),
                    'blockafter' => new external_value(PARAM_INT, 'Post threshold for blocking'),
                    'blockperiod' => new external_value(PARAM_INT, 'Time period for blocking'),
                    'completiondiscussions' => new external_value(PARAM_INT, 'Student must create discussions'),
                    'completionreplies' => new external_value(PARAM_INT, 'Student must post replies'),
                    'completionposts' => new external_value(PARAM_INT, 'Student must post discussions or replies'),
                    'cmid' => new external_value(PARAM_INT, 'Course module id')
                ), 'forumlv'
            )
        );
    }

    /**
     * Describes the parameters for get_forumlv_discussions.
     *
     * @return external_external_function_parameters
     * @since Moodle 2.5
     */
    public static function get_forumlv_discussions_parameters() {
        return new external_function_parameters (
            array(
                'forumlvids' => new external_multiple_structure(new external_value(PARAM_INT, 'forumlv ID',
                        '', VALUE_REQUIRED, '', NULL_NOT_ALLOWED), 'Array of Forum IDs', VALUE_REQUIRED),
            )
        );
    }

    /**
     * Returns a list of forumlv discussions as well as a summary of the discussion
     * in a provided list of forumlvs.
     *
     * @param array $forumlvids the forumlv ids
     * @return array the forumlv discussion details
     * @since Moodle 2.5
     */
    public static function get_forumlv_discussions($forumlvids) {
        global $CFG, $DB, $USER;

        require_once($CFG->dirroot . "/mod/forumlv/lib.php");

        // Validate the parameter.
        $params = self::validate_parameters(self::get_forumlv_discussions_parameters(), array('forumlvids' => $forumlvids));
        $forumlvids = $params['forumlvids'];

        // Array to store the forumlv discussions to return.
        $arrdiscussions = array();
        // Keep track of the course ids we have performed a require_course_login check on to avoid repeating.
        $arrcourseschecked = array();
        // Store the modinfo for the forumlvs in an individual courses.
        $arrcoursesforumlvinfo = array();
        // Keep track of the users we have looked up in the DB.
        $arrusers = array();

        // Loop through them.
        foreach ($forumlvids as $id) {
            // Get the forumlv object.
            $forumlv = $DB->get_record('forumlv', array('id' => $id), '*', MUST_EXIST);
            // Check that that user can view this course if check not performed yet.
            if (!in_array($forumlv->course, $arrcourseschecked)) {
                // Check the user can function in this context.
                self::validate_context(context_course::instance($forumlv->course));
                // Add to the array.
                $arrcourseschecked[] = $forumlv->course;
            }
            // Get the modinfo for the course if we haven't already.
            if (!isset($arrcoursesforumlvinfo[$forumlv->course])) {
                $modinfo = get_fast_modinfo($forumlv->course);
                $arrcoursesforumlvinfo[$forumlv->course] = $modinfo->get_instances_of('forumlv');
            }
            // Check if this forumlv does not exist in the modinfo array, should always be false unless DB is borked.
            if (empty($arrcoursesforumlvinfo[$forumlv->course][$forumlv->id])) {
                throw new moodle_exception('invalidmodule', 'error');
            }
            // We now have the course module.
            $cm = $arrcoursesforumlvinfo[$forumlv->course][$forumlv->id];
            // If the forumlv is not visible throw an exception.
            if (!$cm->uservisible) {
                throw new moodle_exception('nopermissiontoshow', 'error');
            }
            // Get the module context.
            $modcontext = context_module::instance($cm->id);
            // Check they have the view forumlv capability.
            require_capability('mod/forumlv:viewdiscussion', $modcontext);
            // Check if they can view full names.
            $canviewfullname = has_capability('moodle/site:viewfullnames', $modcontext);
            // Get the unreads array, this takes a forumlv id and returns data for all discussions.
            $unreads = array();
            if ($cantrack = forumlv_tp_can_track_forumlvs($forumlv)) {
                if ($forumlvtracked = forumlv_tp_is_tracked($forumlv)) {
                    $unreads = forumlv_get_discussions_unread($cm);
                }
            }
            // The forumlv function returns the replies for all the discussions in a given forumlv.
            $replies = forumlv_count_discussion_replies($id);
            // Get the discussions for this forumlv.
            if ($discussions = $DB->get_records('forumlv_discussions', array('forumlv' => $id))) {
                foreach ($discussions as $discussion) {
                    // If the forumlv is of type qanda and the user has not posted in the discussion
                    // we need to ensure that they have the required capability.
                    if ($forumlv->type == 'qanda' && !forumlv_user_has_posted($discussion->forumlv, $discussion->id, $USER->id)) {
                        require_capability('mod/forumlv:viewqandawithoutposting', $modcontext);
                    }
                    // If we don't have the users details then perform DB call.
                    if (empty($arrusers[$discussion->userid])) {
                        $arrusers[$discussion->userid] = $DB->get_record('user', array('id' => $discussion->userid),
                            'firstname, lastname, email, picture, imagealt', MUST_EXIST);
                    }
                    // Get the subject.
                    $subject = $DB->get_field('forumlv_posts', 'subject', array('id' => $discussion->firstpost), MUST_EXIST);
                    // Create object to return.
                    $return = new stdClass();
                    $return->id = (int) $discussion->id;
                    $return->course = $discussion->course;
                    $return->forumlv = $discussion->forumlv;
                    $return->name = $discussion->name;
                    $return->userid = $discussion->userid;
                    $return->groupid = $discussion->groupid;
                    $return->assessed = $discussion->assessed;
                    $return->timemodified = (int) $discussion->timemodified;
                    $return->usermodified = $discussion->usermodified;
                    $return->timestart = $discussion->timestart;
                    $return->timeend = $discussion->timeend;
                    $return->firstpost = (int) $discussion->firstpost;
                    $return->firstuserfullname = fullname($arrusers[$discussion->userid], $canviewfullname);
                    $return->firstuserimagealt = $arrusers[$discussion->userid]->imagealt;
                    $return->firstuserpicture = $arrusers[$discussion->userid]->picture;
                    $return->firstuseremail = $arrusers[$discussion->userid]->email;
                    $return->subject = $subject;
                    $return->numunread = '';
                    if ($cantrack && $forumlvtracked) {
                        if (isset($unreads[$discussion->id])) {
                            $return->numunread = (int) $unreads[$discussion->id];
                        }
                    }
                    // Check if there are any replies to this discussion.
                    if (!empty($replies[$discussion->id])) {
                         $return->numreplies = (int) $replies[$discussion->id]->replies;
                         $return->lastpost = (int) $replies[$discussion->id]->lastpostid;
                     } else { // No replies, so the last post will be the first post.
                        $return->numreplies = 0;
                        $return->lastpost = (int) $discussion->firstpost;
                     }
                    // Get the last post as well as the user who made it.
                    $lastpost = $DB->get_record('forumlv_posts', array('id' => $return->lastpost), '*', MUST_EXIST);
                    if (empty($arrusers[$lastpost->userid])) {
                        $arrusers[$lastpost->userid] = $DB->get_record('user', array('id' => $lastpost->userid),
                            'firstname, lastname, email, picture, imagealt', MUST_EXIST);
                    }
                    $return->lastuserid = $lastpost->userid;
                    $return->lastuserfullname = fullname($arrusers[$lastpost->userid], $canviewfullname);
                    $return->lastuserimagealt = $arrusers[$lastpost->userid]->imagealt;
                    $return->lastuserpicture = $arrusers[$lastpost->userid]->picture;
                    $return->lastuseremail = $arrusers[$lastpost->userid]->email;
                    // Add the discussion statistics to the array to return.
                    $arrdiscussions[$return->id] = (array) $return;
                }
            }
        }

        return $arrdiscussions;
    }

    /**
     * Describes the get_forumlv_discussions return value.
     *
     * @return external_single_structure
     * @since Moodle 2.5
     */
     public static function get_forumlv_discussions_returns() {
        return new external_multiple_structure(
            new external_single_structure(
                array(
                    'id' => new external_value(PARAM_INT, 'Forum id'),
                    'course' => new external_value(PARAM_INT, 'Course id'),
                    'forumlv' => new external_value(PARAM_INT, 'The forumlv id'),
                    'name' => new external_value(PARAM_TEXT, 'Discussion name'),
                    'userid' => new external_value(PARAM_INT, 'User id'),
                    'groupid' => new external_value(PARAM_INT, 'Group id'),
                    'assessed' => new external_value(PARAM_INT, 'Is this assessed?'),
                    'timemodified' => new external_value(PARAM_INT, 'Time modified'),
                    'usermodified' => new external_value(PARAM_INT, 'The id of the user who last modified'),
                    'timestart' => new external_value(PARAM_INT, 'Time discussion can start'),
                    'timeend' => new external_value(PARAM_INT, 'Time discussion ends'),
                    'firstpost' => new external_value(PARAM_INT, 'The first post in the discussion'),
                    'firstuserfullname' => new external_value(PARAM_TEXT, 'The discussion creators fullname'),
                    'firstuserimagealt' => new external_value(PARAM_TEXT, 'The discussion creators image alt'),
                    'firstuserpicture' => new external_value(PARAM_INT, 'The discussion creators profile picture'),
                    'firstuseremail' => new external_value(PARAM_TEXT, 'The discussion creators email'),
                    'subject' => new external_value(PARAM_TEXT, 'The discussion subject'),
                    'numreplies' => new external_value(PARAM_TEXT, 'The number of replies in the discussion'),
                    'numunread' => new external_value(PARAM_TEXT, 'The number of unread posts, blank if this value is
                        not available due to forumlv settings.'),
                    'lastpost' => new external_value(PARAM_INT, 'The id of the last post in the discussion'),
                    'lastuserid' => new external_value(PARAM_INT, 'The id of the user who made the last post'),
                    'lastuserfullname' => new external_value(PARAM_TEXT, 'The last person to posts fullname'),
                    'lastuserimagealt' => new external_value(PARAM_TEXT, 'The last person to posts image alt'),
                    'lastuserpicture' => new external_value(PARAM_INT, 'The last person to posts profile picture'),
                    'lastuseremail' => new external_value(PARAM_TEXT, 'The last person to posts email'),
                ), 'discussion'
            )
        );
    }
}
