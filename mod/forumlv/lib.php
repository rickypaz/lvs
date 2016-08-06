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
 * @package    mod
 * @subpackage forumlv
 * @copyright  1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/** Include required files */
require_once ($CFG -> libdir . '/filelib.php');
require_once ($CFG -> libdir . '/eventslib.php');
require_once ($CFG -> dirroot . '/user/selector/lib.php');
require_once ($CFG -> dirroot . '/mod/forumlv/post_form.php');

/* @lvs dependências dos lvs  */
use uab\ifce\lvs\business\Item;
use uab\ifce\lvs\avaliacao\NotasLvFactory;
use uab\ifce\lvs\moodle2\business\Forumlv;
use uab\ifce\lvs\moodle2\business\Moodle2CursoLv;

require_once ($CFG -> dirroot . '/blocks/lvs/biblioteca/lib.php');
// @lvs inclusão do loader dos lvs

/// CONSTANTS ///////////////////////////////////////////////////////////

define('FORUMLV_MODE_FLATOLDEST', 1);
define('FORUMLV_MODE_FLATNEWEST', -1);
define('FORUMLV_MODE_THREADED', 2);
define('FORUMLV_MODE_NESTED', 3);

define('FORUMLV_CHOOSESUBSCRIBE', 0);
define('FORUMLV_FORCESUBSCRIBE', 1);
define('FORUMLV_INITIALSUBSCRIBE', 2);
define('FORUMLV_DISALLOWSUBSCRIBE', 3);

define('FORUMLV_TRACKING_OFF', 0);
define('FORUMLV_TRACKING_OPTIONAL', 1);
define('FORUMLV_TRACKING_ON', 2);

define('FORUMLV_MAILED_PENDING', 0);
define('FORUMLV_MAILED_SUCCESS', 1);
define('FORUMLV_MAILED_ERROR', 2);

if (!defined('FORUMLV_CRON_USER_CACHE')) {
    /** Defines how many full user records are cached in forumlv cron. */
    define('FORUMLV_CRON_USER_CACHE', 5000);
}

/// STANDARD FUNCTIONS ///////////////////////////////////////////////////////////

/**
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will create a new instance and return the id number
 * of the new instance.
 *
 * @param stdClass $forumlv add forumlv instance
 * @param mod_forumlv_mod_form $mform
 * @return int intance id
 */
function forumlv_add_instance($forumlv, $mform = null) {
    global $CFG, $DB;

    $forumlv -> timemodified = time();

    if (empty($forumlv -> assessed)) {
        $forumlv -> assessed = 0;
    }

    if (empty($forumlv -> ratingtime) or empty($forumlv -> assessed)) {
        $forumlv -> assesstimestart = 0;
        $forumlv -> assesstimefinish = 0;
    }

    //@lvs se o checkbox estiver desmarcado setar para 0
    $forumlv->exibir = (isset($forumlv->exibir)) ? 1 : 0;

    
    $forumlv -> id = $DB -> insert_record('forumlv', $forumlv);
    $modcontext = context_module::instance($forumlv -> coursemodule);

    if ($forumlv -> type == 'single') {// Create related discussion.
        $discussion = new stdClass();
        $discussion -> course = $forumlv -> course;
        $discussion -> forumlv = $forumlv -> id;
        $discussion -> name = $forumlv -> name;
        $discussion -> assessed = $forumlv -> assessed;
        $discussion -> message = $forumlv -> intro;
        $discussion -> messageformat = $forumlv -> introformat;
        $discussion -> messagetrust = trusttext_trusted(context_course::instance($forumlv -> course));
        $discussion -> mailnow = false;
        $discussion -> groupid = -1;

        $message = '';

        $discussion -> id = forumlv_add_discussion($discussion, null, $message);

        if ($mform and $draftid = file_get_submitted_draft_itemid('introeditor')) {
            // Ugly hack - we need to copy the files somehow.
            $discussion = $DB -> get_record('forumlv_discussions', array('id' => $discussion -> id), '*', MUST_EXIST);
            $post = $DB -> get_record('forumlv_posts', array('id' => $discussion -> firstpost), '*', MUST_EXIST);

            $options = array('subdirs' => true);
            // Use the same options as intro field!
            $post -> message = file_save_draft_area_files($draftid, $modcontext -> id, 'mod_forumlv', 'post', $post -> id, $options, $post -> message);
            $DB -> set_field('forumlv_posts', 'message', $post -> message, array('id' => $post -> id));
        }
    }

    if ($forumlv -> forcesubscribe == FORUMLV_INITIALSUBSCRIBE) {
        $users = forumlv_get_potential_subscribers($modcontext, 0, 'u.id, u.email');
        foreach ($users as $user) {
            forumlv_subscribe($user -> id, $forumlv -> id);
        }
    }

    forumlv_grade_item_update($forumlv);

    return $forumlv -> id;
}

/**
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will update an existing instance with new data.
 *
 * @global object
 * @param object $forumlv forumlv instance (with magic quotes)
 * @return bool success
 */
function forumlv_update_instance($forumlv, $mform) {
    global $DB, $OUTPUT, $USER;

    $forumlv -> timemodified = time();
    $forumlv -> id = $forumlv -> instance;

    if (empty($forumlv -> assessed)) {
        $forumlv -> assessed = 0;
    }

    if (empty($forumlv -> ratingtime) or empty($forumlv -> assessed)) {
        $forumlv -> assesstimestart = 0;
        $forumlv -> assesstimefinish = 0;
    }

    $oldforumlv = $DB -> get_record('forumlv', array('id' => $forumlv -> id));

    // MDL-3942 - if the aggregation type or scale (i.e. max grade) changes then recalculate the grades for the entire forumlv
    // if  scale changes - do we need to recheck the ratings, if ratings higher than scale how do we want to respond?
    // for count and sum aggregation types the grade we check to make sure they do not exceed the scale (i.e. max score) when calculating the grade
    if (($oldforumlv -> assessed <> $forumlv -> assessed) or ($oldforumlv -> scale <> $forumlv -> scale)) {
        forumlv_update_grades($forumlv);
        // recalculate grades for the forumlv
    }

    if ($forumlv -> type == 'single') {// Update related discussion and post.
        $discussions = $DB -> get_records('forumlv_discussions', array('forumlv' => $forumlv -> id), 'timemodified ASC');
        if (!empty($discussions)) {
            if (count($discussions) > 1) {
                echo $OUTPUT -> notification(get_string('warnformorepost', 'forumlv'));
            }
            $discussion = array_pop($discussions);
        } else {
            // try to recover by creating initial discussion - MDL-16262
            $discussion = new stdClass();
            $discussion -> course = $forumlv -> course;
            $discussion -> forumlv = $forumlv -> id;
            $discussion -> name = $forumlv -> name;
            $discussion -> assessed = $forumlv -> assessed;
            $discussion -> message = $forumlv -> intro;
            $discussion -> messageformat = $forumlv -> introformat;
            $discussion -> messagetrust = true;
            $discussion -> mailnow = false;
            $discussion -> groupid = -1;

            $message = '';

            forumlv_add_discussion($discussion, null, $message);

            if (!$discussion = $DB -> get_record('forumlv_discussions', array('forumlv' => $forumlv -> id))) {
                print_error('cannotadd', 'forumlv');
            }
        }
        if (!$post = $DB -> get_record('forumlv_posts', array('id' => $discussion -> firstpost))) {
            print_error('cannotfindfirstpost', 'forumlv');
        }

        $cm = get_coursemodule_from_instance('forumlv', $forumlv -> id);
        $modcontext = context_module::instance($cm -> id, MUST_EXIST);

        $post = $DB -> get_record('forumlv_posts', array('id' => $discussion -> firstpost), '*', MUST_EXIST);
        $post -> subject = $forumlv -> name;
        $post -> message = $forumlv -> intro;
        $post -> messageformat = $forumlv -> introformat;
        $post -> messagetrust = trusttext_trusted($modcontext);
        $post -> modified = $forumlv -> timemodified;
        $post -> userid = $USER -> id;
        // MDL-18599, so that current teacher can take ownership of activities.

        if ($mform and $draftid = file_get_submitted_draft_itemid('introeditor')) {
            // Ugly hack - we need to copy the files somehow.
            $options = array('subdirs' => true);
            // Use the same options as intro field!
            $post -> message = file_save_draft_area_files($draftid, $modcontext -> id, 'mod_forumlv', 'post', $post -> id, $options, $post -> message);
        }

        $DB -> update_record('forumlv_posts', $post);
        $discussion -> name = $forumlv -> name;
        $DB -> update_record('forumlv_discussions', $discussion);
    }

    //@lvs se o checkbox estiver desmarcado setar para 0
    $forumlv->exibir = (isset($forumlv->exibir)) ? 1 : 0;
    
    $DB -> update_record('forumlv', $forumlv);

    $modcontext = context_module::instance($forumlv -> coursemodule);
    if (($forumlv -> forcesubscribe == FORUMLV_INITIALSUBSCRIBE) && ($oldforumlv -> forcesubscribe <> $forumlv -> forcesubscribe)) {
        $users = forumlv_get_potential_subscribers($modcontext, 0, 'u.id, u.email', '');
        foreach ($users as $user) {
            forumlv_subscribe($user -> id, $forumlv -> id);
        }
    }

    forumlv_grade_item_update($forumlv);

    // @lvs atualizando notas dos alunos no curso somente se modificar alguma configuração lv (etapa ou numero de mensagens)
    if ($forumlv -> fator_multiplicativo != $oldforumlv -> fator_multiplicativo || $forumlv -> etapa != $oldforumlv -> etapa) {
        $cursolv = new Moodle2CursoLv($forumlv -> course);
        $forumlv = new Forumlv($forumlv -> id);

        $estudantes = $cursolv -> getEstudantes();
        $estudantes = array_keys($estudantes);

        $forumlv -> avaliarDesempenhoGeral($estudantes);
        $cursolv -> atualizarCurso($estudantes);
    }
    /* fim dos lvs */

    return true;
}

/**
 * Given an ID of an instance of this module,
 * this function will permanently delete the instance
 * and any data that depends on it.
 *
 * @global object
 * @param int $id forumlv instance id
 * @return bool success
 */
function forumlv_delete_instance($id) {
    global $DB;

    if (!$forumlv = $DB -> get_record('forumlv', array('id' => $id))) {
        return false;
    }
    if (!$cm = get_coursemodule_from_instance('forumlv', $forumlv -> id)) {
        return false;
    }
    if (!$course = $DB -> get_record('course', array('id' => $cm -> course))) {
        return false;
    }

    $context = context_module::instance($cm -> id);

    // now get rid of all files
    $fs = get_file_storage();
    $fs -> delete_area_files($context -> id);

    $result = true;

    if ($discussions = $DB -> get_records('forumlv_discussions', array('forumlv' => $forumlv -> id))) {
        foreach ($discussions as $discussion) {
            if (!forumlv_delete_discussion($discussion, true, $course, $cm, $forumlv)) {
                $result = false;
            }
        }
    }

    if (!$DB -> delete_records('forumlv_subscriptions', array('forumlv' => $forumlv -> id))) {
        $result = false;
    }

    forumlv_tp_delete_read_records(-1, -1, -1, $forumlv -> id);

    if (!$DB -> delete_records('forumlv', array('id' => $forumlv -> id))) {
        $result = false;
    }

    forumlv_grade_item_delete($forumlv);

    return $result;
}

/**
 * Indicates API features that the forumlv supports.
 *
 * @uses FEATURE_GROUPS
 * @uses FEATURE_GROUPINGS
 * @uses FEATURE_GROUPMEMBERSONLY
 * @uses FEATURE_MOD_INTRO
 * @uses FEATURE_COMPLETION_TRACKS_VIEWS
 * @uses FEATURE_COMPLETION_HAS_RULES
 * @uses FEATURE_GRADE_HAS_GRADE
 * @uses FEATURE_GRADE_OUTCOMES
 * @param string $feature
 * @return mixed True if yes (some features may use other values)
 */
function forumlv_supports($feature) {
    switch($feature) {
        case FEATURE_GROUPS :
            return true;
        case FEATURE_GROUPINGS :
            return true;
        case FEATURE_GROUPMEMBERSONLY :
            return true;
        case FEATURE_MOD_INTRO :
            return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS :
            return true;
        case FEATURE_COMPLETION_HAS_RULES :
            return true;
        case FEATURE_GRADE_HAS_GRADE :
            return true;
        case FEATURE_GRADE_OUTCOMES :
            return true;
        case FEATURE_RATE :
            return true;
        case FEATURE_BACKUP_MOODLE2 :
            return true;
        case FEATURE_SHOW_DESCRIPTION :
            return true;
        case FEATURE_PLAGIARISM :
            return true;

        default :
            return null;
    }
}

/**
 * Obtains the automatic completion state for this forumlv based on any conditions
 * in forumlv settings.
 *
 * @global object
 * @global object
 * @param object $course Course
 * @param object $cm Course-module
 * @param int $userid User ID
 * @param bool $type Type of comparison (or/and; can be used as return value if no conditions)
 * @return bool True if completed, false if not. (If no conditions, then return
 *   value depends on comparison type)
 */
function forumlv_get_completion_state($course, $cm, $userid, $type) {
    global $CFG, $DB;

    // Get forumlv details
    if (!($forumlv = $DB -> get_record('forumlv', array('id' => $cm -> instance)))) {
        throw new Exception("Can't find forumlv {$cm->instance}");
    }

    $result = $type;
    // Default return value

    $postcountparams = array('userid' => $userid, 'forumlvid' => $forumlv -> id);
    $postcountsql = "
SELECT
    COUNT(1)
FROM
    {forumlv_posts} fp
    INNER JOIN {forumlv_discussions} fd ON fp.discussion=fd.id
WHERE
    fp.userid=:userid AND fd.forumlv=:forumlvid";

    if ($forumlv -> completiondiscussions) {
        $value = $forumlv -> completiondiscussions <= $DB -> count_records('forumlv_discussions', array('forumlv' => $forumlv -> id, 'userid' => $userid));
        if ($type == COMPLETION_AND) {
            $result = $result && $value;
        } else {
            $result = $result || $value;
        }
    }
    if ($forumlv -> completionreplies) {
        $value = $forumlv -> completionreplies <= $DB -> get_field_sql($postcountsql . ' AND fp.parent<>0', $postcountparams);
        if ($type == COMPLETION_AND) {
            $result = $result && $value;
        } else {
            $result = $result || $value;
        }
    }
    if ($forumlv -> completionposts) {
        $value = $forumlv -> completionposts <= $DB -> get_field_sql($postcountsql, $postcountparams);
        if ($type == COMPLETION_AND) {
            $result = $result && $value;
        } else {
            $result = $result || $value;
        }
    }

    return $result;
}

/**
 * Create a message-id string to use in the custom headers of forumlv notification emails
 *
 * message-id is used by email clients to identify emails and to nest conversations
 *
 * @param int $postid The ID of the forumlv post we are notifying the user about
 * @param int $usertoid The ID of the user being notified
 * @param string $hostname The server's hostname
 * @return string A unique message-id
 */
function forumlv_get_email_message_id($postid, $usertoid, $hostname) {
    return '<' . hash('sha256', $postid . 'to' . $usertoid) . '@' . $hostname . '>';
}

/**
 * Removes properties from user record that are not necessary
 * for sending post notifications.
 * @param stdClass $user
 * @return void, $user parameter is modified
 */
function forumlv_cron_minimise_user_record(stdClass $user) {

    // We store large amount of users in one huge array,
    // make sure we do not store info there we do not actually need
    // in mail generation code or messaging.

    unset($user -> institution);
    unset($user -> department);
    unset($user -> address);
    unset($user -> city);
    unset($user -> url);
    unset($user -> currentlogin);
    unset($user -> description);
    unset($user -> descriptionformat);
}

/**
 * Function to be run periodically according to the moodle cron
 * Finds all posts that have yet to be mailed out, and mails them
 * out to all subscribers
 *
 * @global object
 * @global object
 * @global object
 * @uses CONTEXT_MODULE
 * @uses CONTEXT_COURSE
 * @uses SITEID
 * @uses FORMAT_PLAIN
 * @return void
 */
function forumlv_cron() {
    global $CFG, $USER, $DB;

    $site = get_site();

    // All users that are subscribed to any post that needs sending,
    // please increase $CFG->extramemorylimit on large sites that
    // send notifications to a large number of users.
    $users = array();
    $userscount = 0;
    // Cached user counter - count($users) in PHP is horribly slow!!!

    // status arrays
    $mailcount = array();
    $errorcount = array();

    // caches
    $discussions = array();
    $forumlvs = array();
    $courses = array();
    $coursemodules = array();
    $subscribedusers = array();

    // Posts older than 2 days will not be mailed.  This is to avoid the problem where
    // cron has not been running for a long time, and then suddenly people are flooded
    // with mail from the past few weeks or months
    $timenow = time();
    $endtime = $timenow - $CFG -> maxeditingtime;
    $starttime = $endtime - 48 * 3600;
    // Two days earlier

    if ($posts = forumlv_get_unmailed_posts($starttime, $endtime, $timenow)) {
        // Mark them all now as being mailed.  It's unlikely but possible there
        // might be an error later so that a post is NOT actually mailed out,
        // but since mail isn't crucial, we can accept this risk.  Doing it now
        // prevents the risk of duplicated mails, which is a worse problem.

        if (!forumlv_mark_old_posts_as_mailed($endtime)) {
            mtrace('Errors occurred while trying to mark some posts as being mailed.');
            return false;
            // Don't continue trying to mail them, in case we are in a cron loop
        }

        // checking post validity, and adding users to loop through later
        foreach ($posts as $pid => $post) {

            $discussionid = $post -> discussion;
            if (!isset($discussions[$discussionid])) {
                if ($discussion = $DB -> get_record('forumlv_discussions', array('id' => $post -> discussion))) {
                    $discussions[$discussionid] = $discussion;
                } else {
                    mtrace('Could not find discussion ' . $discussionid);
                    unset($posts[$pid]);
                    continue;
                }
            }
            $forumlvid = $discussions[$discussionid] -> forumlv;
            if (!isset($forumlvs[$forumlvid])) {
                if ($forumlv = $DB -> get_record('forumlv', array('id' => $forumlvid))) {
                    $forumlvs[$forumlvid] = $forumlv;
                } else {
                    mtrace('Could not find forumlv ' . $forumlvid);
                    unset($posts[$pid]);
                    continue;
                }
            }
            $courseid = $forumlvs[$forumlvid] -> course;
            if (!isset($courses[$courseid])) {
                if ($course = $DB -> get_record('course', array('id' => $courseid))) {
                    $courses[$courseid] = $course;
                } else {
                    mtrace('Could not find course ' . $courseid);
                    unset($posts[$pid]);
                    continue;
                }
            }
            if (!isset($coursemodules[$forumlvid])) {
                if ($cm = get_coursemodule_from_instance('forumlv', $forumlvid, $courseid)) {
                    $coursemodules[$forumlvid] = $cm;
                } else {
                    mtrace('Could not find course module for forumlv ' . $forumlvid);
                    unset($posts[$pid]);
                    continue;
                }
            }

            // caching subscribed users of each forumlv
            if (!isset($subscribedusers[$forumlvid])) {
                $modcontext = context_module::instance($coursemodules[$forumlvid] -> id);
                if ($subusers = forumlv_subscribed_users($courses[$courseid], $forumlvs[$forumlvid], 0, $modcontext, "u.*")) {
                    foreach ($subusers as $postuser) {
                        // this user is subscribed to this forumlv
                        $subscribedusers[$forumlvid][$postuser -> id] = $postuser -> id;
                        $userscount++;
                        if ($userscount > FORUMLV_CRON_USER_CACHE) {
                            // Store minimal user info.
                            $minuser = new stdClass();
                            $minuser -> id = $postuser -> id;
                            $users[$postuser -> id] = $minuser;
                        } else {
                            // Cache full user record.
                            forumlv_cron_minimise_user_record($postuser);
                            $users[$postuser -> id] = $postuser;
                        }
                    }
                    // Release memory.
                    unset($subusers);
                    unset($postuser);
                }
            }

            $mailcount[$pid] = 0;
            $errorcount[$pid] = 0;
        }
    }

    if ($users && $posts) {

        $urlinfo = parse_url($CFG -> wwwroot);
        $hostname = $urlinfo['host'];

        foreach ($users as $userto) {

            @set_time_limit(120);
            // terminate if processing of any account takes longer than 2 minutes

            mtrace('Processing user ' . $userto -> id);

            // Init user caches - we keep the cache for one cycle only,
            // otherwise it could consume too much memory.
            if (isset($userto -> username)) {
                $userto = clone($userto);
            } else {
                $userto = $DB -> get_record('user', array('id' => $userto -> id));
                forumlv_cron_minimise_user_record($userto);
            }
            $userto -> viewfullnames = array();
            $userto -> canpost = array();
            $userto -> markposts = array();

            // set this so that the capabilities are cached, and environment matches receiving user
            cron_setup_user($userto);

            // reset the caches
            foreach ($coursemodules as $forumlvid => $unused) {
                $coursemodules[$forumlvid] -> cache = new stdClass();
                $coursemodules[$forumlvid] -> cache -> caps = array();
                unset($coursemodules[$forumlvid] -> uservisible);
            }

            foreach ($posts as $pid => $post) {

                // Set up the environment for the post, discussion, forumlv, course
                $discussion = $discussions[$post -> discussion];
                $forumlv = $forumlvs[$discussion -> forumlv];
                $course = $courses[$forumlv -> course];
                $cm = &$coursemodules[$forumlv -> id];

                // Do some checks  to see if we can bail out now
                // Only active enrolled users are in the list of subscribers
                if (!isset($subscribedusers[$forumlv -> id][$userto -> id])) {
                    continue;
                    // user does not subscribe to this forumlv
                }

                // Don't send email if the forumlv is Q&A and the user has not posted
                // Initial topics are still mailed
                if ($forumlv -> type == 'qanda' && !forumlv_get_user_posted_time($discussion -> id, $userto -> id) && $pid != $discussion -> firstpost) {
                    mtrace('Did not email ' . $userto -> id . ' because user has not posted in discussion');
                    continue;
                }

                // Get info about the sending user
                if (array_key_exists($post -> userid, $users)) {// we might know him/her already
                    $userfrom = $users[$post -> userid];
                    if (!isset($userfrom -> idnumber)) {
                        // Minimalised user info, fetch full record.
                        $userfrom = $DB -> get_record('user', array('id' => $userfrom -> id));
                        forumlv_cron_minimise_user_record($userfrom);
                    }

                } else if ($userfrom = $DB -> get_record('user', array('id' => $post -> userid))) {
                    forumlv_cron_minimise_user_record($userfrom);
                    // Fetch only once if possible, we can add it to user list, it will be skipped anyway.
                    if ($userscount <= FORUMLV_CRON_USER_CACHE) {
                        $userscount++;
                        $users[$userfrom -> id] = $userfrom;
                    }

                } else {
                    mtrace('Could not find user ' . $post -> userid);
                    continue;
                }

                //if we want to check that userto and userfrom are not the same person this is probably the spot to do it

                // setup global $COURSE properly - needed for roles and languages
                cron_setup_user($userto, $course);

                // Fill caches
                if (!isset($userto -> viewfullnames[$forumlv -> id])) {
                    $modcontext = context_module::instance($cm -> id);
                    $userto -> viewfullnames[$forumlv -> id] = has_capability('moodle/site:viewfullnames', $modcontext);
                }
                if (!isset($userto -> canpost[$discussion -> id])) {
                    $modcontext = context_module::instance($cm -> id);
                    $userto -> canpost[$discussion -> id] = forumlv_user_can_post($forumlv, $discussion, $userto, $cm, $course, $modcontext);
                }
                if (!isset($userfrom -> groups[$forumlv -> id])) {
                    if (!isset($userfrom -> groups)) {
                        $userfrom -> groups = array();
                        if (isset($users[$userfrom -> id])) {
                            $users[$userfrom -> id] -> groups = array();
                        }
                    }
                    $userfrom -> groups[$forumlv -> id] = groups_get_all_groups($course -> id, $userfrom -> id, $cm -> groupingid);
                    if (isset($users[$userfrom -> id])) {
                        $users[$userfrom -> id] -> groups[$forumlv -> id] = $userfrom -> groups[$forumlv -> id];
                    }
                }

                // Make sure groups allow this user to see this email
                if ($discussion -> groupid > 0 and $groupmode = groups_get_activity_groupmode($cm, $course)) {// Groups are being used
                    if (!groups_group_exists($discussion -> groupid)) {// Can't find group
                        continue;
                        // Be safe and don't send it to anyone
                    }

                    if (!groups_is_member($discussion -> groupid) and !has_capability('moodle/site:accessallgroups', $modcontext)) {
                        // do not send posts from other groups when in SEPARATEGROUPS or VISIBLEGROUPS
                        continue;
                    }
                }

                // Make sure we're allowed to see it...
                if (!forumlv_user_can_see_post($forumlv, $discussion, $post, NULL, $cm)) {
                    mtrace('user ' . $userto -> id . ' can not see ' . $post -> id);
                    continue;
                }

                // OK so we need to send the email.

                // Does the user want this post in a digest?  If so postpone it for now.
                if ($userto -> maildigest > 0) {
                    // This user wants the mails to be in digest form
                    $queue = new stdClass();
                    $queue -> userid = $userto -> id;
                    $queue -> discussionid = $discussion -> id;
                    $queue -> postid = $post -> id;
                    $queue -> timemodified = $post -> created;
                    $DB -> insert_record('forumlv_queue', $queue);
                    continue;
                }

                // Prepare to actually send the post now, and build up the content

                $cleanforumlvname = str_replace('"', "'", strip_tags(format_string($forumlv -> name)));

                $userfrom -> customheaders = array(// Headers to make emails easier to track
                'Precedence: Bulk', 'List-Id: "' . $cleanforumlvname . '" <moodleforumlv' . $forumlv -> id . '@' . $hostname . '>', 'List-Help: ' . $CFG -> wwwroot . '/mod/forumlv/view.php?f=' . $forumlv -> id, 'Message-ID: ' . forumlv_get_email_message_id($post -> id, $userto -> id, $hostname), 'X-Course-Id: ' . $course -> id, 'X-Course-Name: ' . format_string($course -> fullname, true));

                if ($post -> parent) {// This post is a reply, so add headers for threading (see MDL-22551)
                    $userfrom -> customheaders[] = 'In-Reply-To: ' . forumlv_get_email_message_id($post -> parent, $userto -> id, $hostname);
                    $userfrom -> customheaders[] = 'References: ' . forumlv_get_email_message_id($post -> parent, $userto -> id, $hostname);
                }

                $shortname = format_string($course -> shortname, true, array('context' => context_course::instance($course -> id)));

                $postsubject = html_to_text("$shortname: " . format_string($post -> subject, true));
                $posttext = forumlv_make_mail_text($course, $cm, $forumlv, $discussion, $post, $userfrom, $userto);
                $posthtml = forumlv_make_mail_html($course, $cm, $forumlv, $discussion, $post, $userfrom, $userto);

                // Send the post now!

                mtrace('Sending ', '');

                $eventdata = new stdClass();
                $eventdata -> component = 'mod_forumlv';
                $eventdata -> name = 'posts';
                $eventdata -> userfrom = $userfrom;
                $eventdata -> userto = $userto;
                $eventdata -> subject = $postsubject;
                $eventdata -> fullmessage = $posttext;
                $eventdata -> fullmessageformat = FORMAT_PLAIN;
                $eventdata -> fullmessagehtml = $posthtml;
                $eventdata -> notification = 1;

                // If forumlv_replytouser is not set then send mail using the noreplyaddress.
                if (empty($CFG -> forumlv_replytouser)) {
                    // Clone userfrom as it is referenced by $users.
                    $cloneduserfrom = clone($userfrom);
                    $cloneduserfrom -> email = $CFG -> noreplyaddress;
                    $eventdata -> userfrom = $cloneduserfrom;
                }

                $smallmessagestrings = new stdClass();
                $smallmessagestrings -> user = fullname($userfrom);
                $smallmessagestrings -> forumlvname = "$shortname: " . format_string($forumlv -> name, true) . ": " . $discussion -> name;
                $smallmessagestrings -> message = $post -> message;
                //make sure strings are in message recipients language
                $eventdata -> smallmessage = get_string_manager() -> get_string('smallmessage', 'forumlv', $smallmessagestrings, $userto -> lang);

                $eventdata -> contexturl = "{$CFG->wwwroot}/mod/forumlv/discuss.php?d={$discussion->id}#p{$post->id}";
                $eventdata -> contexturlname = $discussion -> name;

                $mailresult = message_send($eventdata);
                if (!$mailresult) {
                    mtrace("Error: mod/forumlv/lib.php forumlv_cron(): Could not send out mail for id $post->id to user $userto->id" . " ($userto->email) .. not trying again.");
                    add_to_log($course -> id, 'forumlv', 'mail error', "discuss.php?d=$discussion->id#p$post->id", substr(format_string($post -> subject, true), 0, 30), $cm -> id, $userto -> id);
                    $errorcount[$post -> id]++;
                } else {
                    $mailcount[$post -> id]++;

                    // Mark post as read if forumlv_usermarksread is set off
                    if (!$CFG -> forumlv_usermarksread) {
                        $userto -> markposts[$post -> id] = $post -> id;
                    }
                }

                mtrace('post ' . $post -> id . ': ' . $post -> subject);
            }

            // mark processed posts as read
            forumlv_tp_mark_posts_read($userto, $userto -> markposts);
            unset($userto);
        }
    }

    if ($posts) {
        foreach ($posts as $post) {
            mtrace($mailcount[$post -> id] . " users were sent post $post->id, '$post->subject'");
            if ($errorcount[$post -> id]) {
                $DB -> set_field('forumlv_posts', 'mailed', FORUMLV_MAILED_ERROR, array('id' => $post -> id));
            }
        }
    }

    // release some memory
    unset($subscribedusers);
    unset($mailcount);
    unset($errorcount);

    cron_setup_user();

    $sitetimezone = $CFG -> timezone;

    // Now see if there are any digest mails waiting to be sent, and if we should send them

    mtrace('Starting digest processing...');

    @set_time_limit(300);
    // terminate if not able to fetch all digests in 5 minutes

    if (!isset($CFG -> digestmailtimelast)) {// To catch the first time
        set_config('digestmailtimelast', 0);
    }

    $timenow = time();
    $digesttime = usergetmidnight($timenow, $sitetimezone) + ($CFG -> digestmailtime * 3600);

    // Delete any really old ones (normally there shouldn't be any)
    $weekago = $timenow - (7 * 24 * 3600);
    $DB -> delete_records_select('forumlv_queue', "timemodified < ?", array($weekago));
    mtrace('Cleaned old digest records');

    if ($CFG -> digestmailtimelast < $digesttime and $timenow > $digesttime) {

        mtrace('Sending forumlv digests: ' . userdate($timenow, '', $sitetimezone));

        $digestposts_rs = $DB -> get_recordset_select('forumlv_queue', "timemodified < ?", array($digesttime));

        if ($digestposts_rs -> valid()) {

            // We have work to do
            $usermailcount = 0;

            //caches - reuse the those filled before too
            $discussionposts = array();
            $userdiscussions = array();

            foreach ($digestposts_rs as $digestpost) {
                if (!isset($posts[$digestpost -> postid])) {
                    if ($post = $DB -> get_record('forumlv_posts', array('id' => $digestpost -> postid))) {
                        $posts[$digestpost -> postid] = $post;
                    } else {
                        continue;
                    }
                }
                $discussionid = $digestpost -> discussionid;
                if (!isset($discussions[$discussionid])) {
                    if ($discussion = $DB -> get_record('forumlv_discussions', array('id' => $discussionid))) {
                        $discussions[$discussionid] = $discussion;
                    } else {
                        continue;
                    }
                }
                $forumlvid = $discussions[$discussionid] -> forumlv;
                if (!isset($forumlvs[$forumlvid])) {
                    if ($forumlv = $DB -> get_record('forumlv', array('id' => $forumlvid))) {
                        $forumlvs[$forumlvid] = $forumlv;
                    } else {
                        continue;
                    }
                }

                $courseid = $forumlvs[$forumlvid] -> course;
                if (!isset($courses[$courseid])) {
                    if ($course = $DB -> get_record('course', array('id' => $courseid))) {
                        $courses[$courseid] = $course;
                    } else {
                        continue;
                    }
                }

                if (!isset($coursemodules[$forumlvid])) {
                    if ($cm = get_coursemodule_from_instance('forumlv', $forumlvid, $courseid)) {
                        $coursemodules[$forumlvid] = $cm;
                    } else {
                        continue;
                    }
                }
                $userdiscussions[$digestpost -> userid][$digestpost -> discussionid] = $digestpost -> discussionid;
                $discussionposts[$digestpost -> discussionid][$digestpost -> postid] = $digestpost -> postid;
            }
            $digestposts_rs -> close();
            /// Finished iteration, let's close the resultset

            // Data collected, start sending out emails to each user
            foreach ($userdiscussions as $userid => $thesediscussions) {

                @set_time_limit(120);
                // terminate if processing of any account takes longer than 2 minutes

                cron_setup_user();

                mtrace(get_string('processingdigest', 'forumlv', $userid), '... ');

                // First of all delete all the queue entries for this user
                $DB -> delete_records_select('forumlv_queue', "userid = ? AND timemodified < ?", array($userid, $digesttime));

                // Init user caches - we keep the cache for one cycle only,
                // otherwise it would unnecessarily consume memory.
                if (array_key_exists($userid, $users) and isset($users[$userid] -> username)) {
                    $userto = clone($users[$userid]);
                } else {
                    $userto = $DB -> get_record('user', array('id' => $userid));
                    forumlv_cron_minimise_user_record($userto);
                }
                $userto -> viewfullnames = array();
                $userto -> canpost = array();
                $userto -> markposts = array();

                // Override the language and timezone of the "current" user, so that
                // mail is customised for the receiver.
                cron_setup_user($userto);

                $postsubject = get_string('digestmailsubject', 'forumlv', format_string($site -> shortname, true));

                $headerdata = new stdClass();
                $headerdata -> sitename = format_string($site -> fullname, true);
                $headerdata -> userprefs = $CFG -> wwwroot . '/user/edit.php?id=' . $userid . '&amp;course=' . $site -> id;

                $posttext = get_string('digestmailheader', 'forumlv', $headerdata) . "\n\n";
                $headerdata -> userprefs = '<a target="_blank" href="' . $headerdata -> userprefs . '">' . get_string('digestmailprefs', 'forumlv') . '</a>';

                $posthtml = "<head>";
                /*                foreach ($CFG->stylesheets as $stylesheet) {
                 //TODO: MDL-21120
                 $posthtml .= '<link rel="stylesheet" type="text/css" href="'.$stylesheet.'" />'."\n";
                 }*/
                $posthtml .= "</head>\n<body id=\"email\">\n";
                $posthtml .= '<p>' . get_string('digestmailheader', 'forumlv', $headerdata) . '</p><br /><hr size="1" noshade="noshade" />';

                foreach ($thesediscussions as $discussionid) {

                    @set_time_limit(120);
                    // to be reset for each post

                    $discussion = $discussions[$discussionid];
                    $forumlv = $forumlvs[$discussion -> forumlv];
                    $course = $courses[$forumlv -> course];
                    $cm = $coursemodules[$forumlv -> id];

                    //override language
                    cron_setup_user($userto, $course);

                    // Fill caches
                    if (!isset($userto -> viewfullnames[$forumlv -> id])) {
                        $modcontext = context_module::instance($cm -> id);
                        $userto -> viewfullnames[$forumlv -> id] = has_capability('moodle/site:viewfullnames', $modcontext);
                    }
                    if (!isset($userto -> canpost[$discussion -> id])) {
                        $modcontext = context_module::instance($cm -> id);
                        $userto -> canpost[$discussion -> id] = forumlv_user_can_post($forumlv, $discussion, $userto, $cm, $course, $modcontext);
                    }

                    $strforumlvs = get_string('forumlvs', 'forumlv');
                    $canunsubscribe = !forumlv_is_forcesubscribed($forumlv);
                    $canreply = $userto -> canpost[$discussion -> id];
                    $shortname = format_string($course -> shortname, true, array('context' => context_course::instance($course -> id)));

                    $posttext .= "\n \n";
                    $posttext .= '=====================================================================';
                    $posttext .= "\n \n";
                    $posttext .= "$shortname -> $strforumlvs -> " . format_string($forumlv -> name, true);
                    if ($discussion -> name != $forumlv -> name) {
                        $posttext .= " -> " . format_string($discussion -> name, true);
                    }
                    $posttext .= "\n";

                    $posthtml .= "<p><font face=\"sans-serif\">" . "<a target=\"_blank\" href=\"$CFG->wwwroot/course/view.php?id=$course->id\">$shortname</a> -> " . "<a target=\"_blank\" href=\"$CFG->wwwroot/mod/forumlv/index.php?id=$course->id\">$strforumlvs</a> -> " . "<a target=\"_blank\" href=\"$CFG->wwwroot/mod/forumlv/view.php?f=$forumlv->id\">" . format_string($forumlv -> name, true) . "</a>";
                    if ($discussion -> name == $forumlv -> name) {
                        $posthtml .= "</font></p>";
                    } else {
                        $posthtml .= " -> <a target=\"_blank\" href=\"$CFG->wwwroot/mod/forumlv/discuss.php?d=$discussion->id\">" . format_string($discussion -> name, true) . "</a></font></p>";
                    }
                    $posthtml .= '<p>';

                    $postsarray = $discussionposts[$discussionid];
                    sort($postsarray);

                    foreach ($postsarray as $postid) {
                        $post = $posts[$postid];

                        if (array_key_exists($post -> userid, $users)) {// we might know him/her already
                            $userfrom = $users[$post -> userid];
                            if (!isset($userfrom -> idnumber)) {
                                $userfrom = $DB -> get_record('user', array('id' => $userfrom -> id));
                                forumlv_cron_minimise_user_record($userfrom);
                            }

                        } else if ($userfrom = $DB -> get_record('user', array('id' => $post -> userid))) {
                            forumlv_cron_minimise_user_record($userfrom);
                            if ($userscount <= FORUMLV_CRON_USER_CACHE) {
                                $userscount++;
                                $users[$userfrom -> id] = $userfrom;
                            }

                        } else {
                            mtrace('Could not find user ' . $post -> userid);
                            continue;
                        }

                        if (!isset($userfrom -> groups[$forumlv -> id])) {
                            if (!isset($userfrom -> groups)) {
                                $userfrom -> groups = array();
                                if (isset($users[$userfrom -> id])) {
                                    $users[$userfrom -> id] -> groups = array();
                                }
                            }
                            $userfrom -> groups[$forumlv -> id] = groups_get_all_groups($course -> id, $userfrom -> id, $cm -> groupingid);
                            if (isset($users[$userfrom -> id])) {
                                $users[$userfrom -> id] -> groups[$forumlv -> id] = $userfrom -> groups[$forumlv -> id];
                            }
                        }

                        $userfrom -> customheaders = array("Precedence: Bulk");

                        if ($userto -> maildigest == 2) {
                            // Subjects only
                            $by = new stdClass();
                            $by -> name = fullname($userfrom);
                            $by -> date = userdate($post -> modified);
                            $posttext .= "\n" . format_string($post -> subject, true) . ' ' . get_string("bynameondate", "forumlv", $by);
                            $posttext .= "\n---------------------------------------------------------------------";

                            $by -> name = "<a target=\"_blank\" href=\"$CFG->wwwroot/user/view.php?id=$userfrom->id&amp;course=$course->id\">$by->name</a>";
                            $posthtml .= '<div><a target="_blank" href="' . $CFG -> wwwroot . '/mod/forumlv/discuss.php?d=' . $discussion -> id . '#p' . $post -> id . '">' . format_string($post -> subject, true) . '</a> ' . get_string("bynameondate", "forumlv", $by) . '</div>';

                        } else {
                            // The full treatment
                            $posttext .= forumlv_make_mail_text($course, $cm, $forumlv, $discussion, $post, $userfrom, $userto, true);
                            $posthtml .= forumlv_make_mail_post($course, $cm, $forumlv, $discussion, $post, $userfrom, $userto, false, $canreply, true, false);

                            // Create an array of postid's for this user to mark as read.
                            if (!$CFG -> forumlv_usermarksread) {
                                $userto -> markposts[$post -> id] = $post -> id;
                            }
                        }
                    }
                    if ($canunsubscribe) {
                        $posthtml .= "\n<div class='mdl-right'><font size=\"1\"><a href=\"$CFG->wwwroot/mod/forumlv/subscribe.php?id=$forumlv->id\">" . get_string("unsubscribe", "forumlv") . "</a></font></div>";
                    } else {
                        $posthtml .= "\n<div class='mdl-right'><font size=\"1\">" . get_string("everyoneissubscribed", "forumlv") . "</font></div>";
                    }
                    $posthtml .= '<hr size="1" noshade="noshade" /></p>';
                }
                $posthtml .= '</body>';

                if (empty($userto -> mailformat) || $userto -> mailformat != 1) {
                    // This user DOESN'T want to receive HTML
                    $posthtml = '';
                }

                $attachment = $attachname = '';
                // Directly email forumlv digests rather than sending them via messaging, use the
                // site shortname as 'from name', the noreply address will be used by email_to_user.
                $mailresult = email_to_user($userto, $site -> shortname, $postsubject, $posttext, $posthtml, $attachment, $attachname);

                if (!$mailresult) {
                    mtrace("ERROR!");
                    echo "Error: mod/forumlv/cron.php: Could not send out digest mail to user $userto->id ($userto->email)... not trying again.\n";
                    add_to_log($course -> id, 'forumlv', 'mail digest error', '', '', $cm -> id, $userto -> id);
                } else {
                    mtrace("success.");
                    $usermailcount++;

                    // Mark post as read if forumlv_usermarksread is set off
                    forumlv_tp_mark_posts_read($userto, $userto -> markposts);
                }
            }
        }
        /// We have finishied all digest emails, update $CFG->digestmailtimelast
        set_config('digestmailtimelast', $timenow);
    }

    cron_setup_user();

    if (!empty($usermailcount)) {
        mtrace(get_string('digestsentusers', 'forumlv', $usermailcount));
    }

    if (!empty($CFG -> forumlv_lastreadclean)) {
        $timenow = time();
        if ($CFG -> forumlv_lastreadclean + (24 * 3600) < $timenow) {
            set_config('forumlv_lastreadclean', $timenow);
            mtrace('Removing old forumlv read tracking info...');
            forumlv_tp_clean_read_records();
        }
    } else {
        set_config('forumlv_lastreadclean', time());
    }

    return true;
}

/**
 * Builds and returns the body of the email notification in plain text.
 *
 * @global object
 * @global object
 * @uses CONTEXT_MODULE
 * @param object $course
 * @param object $cm
 * @param object $forumlv
 * @param object $discussion
 * @param object $post
 * @param object $userfrom
 * @param object $userto
 * @param boolean $bare
 * @return string The email body in plain text format.
 */
function forumlv_make_mail_text($course, $cm, $forumlv, $discussion, $post, $userfrom, $userto, $bare = false) {
    global $CFG, $USER;

    $modcontext = context_module::instance($cm -> id);

    if (!isset($userto -> viewfullnames[$forumlv -> id])) {
        $viewfullnames = has_capability('moodle/site:viewfullnames', $modcontext, $userto -> id);
    } else {
        $viewfullnames = $userto -> viewfullnames[$forumlv -> id];
    }

    if (!isset($userto -> canpost[$discussion -> id])) {
        $canreply = forumlv_user_can_post($forumlv, $discussion, $userto, $cm, $course, $modcontext);
    } else {
        $canreply = $userto -> canpost[$discussion -> id];
    }

    $by = New stdClass;
    $by -> name = fullname($userfrom, $viewfullnames);
    $by -> date = userdate($post -> modified, "", $userto -> timezone);

    $strbynameondate = get_string('bynameondate', 'forumlv', $by);

    $strforumlvs = get_string('forumlvs', 'forumlv');

    $canunsubscribe = !forumlv_is_forcesubscribed($forumlv);

    $posttext = '';

    if (!$bare) {
        $shortname = format_string($course -> shortname, true, array('context' => context_course::instance($course -> id)));
        $posttext = "$shortname -> $strforumlvs -> " . format_string($forumlv -> name, true);

        if ($discussion -> name != $forumlv -> name) {
            $posttext .= " -> " . format_string($discussion -> name, true);
        }
    }

    // add absolute file links
    $post -> message = file_rewrite_pluginfile_urls($post -> message, 'pluginfile.php', $modcontext -> id, 'mod_forumlv', 'post', $post -> id);

    $posttext .= "\n---------------------------------------------------------------------\n";
    $posttext .= format_string($post -> subject, true);
    if ($bare) {
        $posttext .= " ($CFG->wwwroot/mod/forumlv/discuss.php?d=$discussion->id#p$post->id)";
    }
    $posttext .= "\n" . $strbynameondate . "\n";
    $posttext .= "---------------------------------------------------------------------\n";
    $posttext .= format_text_email($post -> message, $post -> messageformat);
    $posttext .= "\n\n";
    $posttext .= forumlv_print_attachments($post, $cm, "text");

    if (!$bare && $canreply) {
        $posttext .= "---------------------------------------------------------------------\n";
        $posttext .= get_string("postmailinfo", "forumlv", $shortname) . "\n";
        $posttext .= "$CFG->wwwroot/mod/forumlv/post.php?reply=$post->id\n";
    }
    if (!$bare && $canunsubscribe) {
        $posttext .= "\n---------------------------------------------------------------------\n";
        $posttext .= get_string("unsubscribe", "forumlv");
        $posttext .= ": $CFG->wwwroot/mod/forumlv/subscribe.php?id=$forumlv->id\n";
    }

    return $posttext;
}

/**
 * Builds and returns the body of the email notification in html format.
 *
 * @global object
 * @param object $course
 * @param object $cm
 * @param object $forumlv
 * @param object $discussion
 * @param object $post
 * @param object $userfrom
 * @param object $userto
 * @return string The email text in HTML format
 */
function forumlv_make_mail_html($course, $cm, $forumlv, $discussion, $post, $userfrom, $userto) {
    global $CFG;

    if ($userto -> mailformat != 1) {// Needs to be HTML
        return '';
    }

    if (!isset($userto -> canpost[$discussion -> id])) {
        $canreply = forumlv_user_can_post($forumlv, $discussion, $userto, $cm, $course);
    } else {
        $canreply = $userto -> canpost[$discussion -> id];
    }

    $strforumlvs = get_string('forumlvs', 'forumlv');
    $canunsubscribe = !forumlv_is_forcesubscribed($forumlv);
    $shortname = format_string($course -> shortname, true, array('context' => context_course::instance($course -> id)));

    $posthtml = '<head>';
    /*    foreach ($CFG->stylesheets as $stylesheet) {
     //TODO: MDL-21120
     $posthtml .= '<link rel="stylesheet" type="text/css" href="'.$stylesheet.'" />'."\n";
     }*/
    $posthtml .= '</head>';
    $posthtml .= "\n<body id=\"email\">\n\n";

    $posthtml .= '<div class="navbar">' . '<a target="_blank" href="' . $CFG -> wwwroot . '/course/view.php?id=' . $course -> id . '">' . $shortname . '</a> &raquo; ' . '<a target="_blank" href="' . $CFG -> wwwroot . '/mod/forumlv/index.php?id=' . $course -> id . '">' . $strforumlvs . '</a> &raquo; ' . '<a target="_blank" href="' . $CFG -> wwwroot . '/mod/forumlv/view.php?f=' . $forumlv -> id . '">' . format_string($forumlv -> name, true) . '</a>';
    if ($discussion -> name == $forumlv -> name) {
        $posthtml .= '</div>';
    } else {
        $posthtml .= ' &raquo; <a target="_blank" href="' . $CFG -> wwwroot . '/mod/forumlv/discuss.php?d=' . $discussion -> id . '">' . format_string($discussion -> name, true) . '</a></div>';
    }
    $posthtml .= forumlv_make_mail_post($course, $cm, $forumlv, $discussion, $post, $userfrom, $userto, false, $canreply, true, false);

    if ($canunsubscribe) {
        $posthtml .= '<hr /><div class="mdl-align unsubscribelink">
                      <a href="' . $CFG -> wwwroot . '/mod/forumlv/subscribe.php?id=' . $forumlv -> id . '">' . get_string('unsubscribe', 'forumlv') . '</a>&nbsp;
                      <a href="' . $CFG -> wwwroot . '/mod/forumlv/unsubscribeall.php">' . get_string('unsubscribeall', 'forumlv') . '</a></div>';
    }

    $posthtml .= '</body>';

    return $posthtml;
}

/**
 *
 * @param object $course
 * @param object $user
 * @param object $mod TODO this is not used in this function, refactor
 * @param object $forumlv
 * @return object A standard object with 2 variables: info (number of posts for this user) and time (last modified)
 */
function forumlv_user_outline($course, $user, $mod, $forumlv) {
    global $CFG;
    require_once ("$CFG->libdir/gradelib.php");
    $grades = grade_get_grades($course -> id, 'mod', 'forumlv', $forumlv -> id, $user -> id);
    if (empty($grades -> items[0] -> grades)) {
        $grade = false;
    } else {
        $grade = reset($grades -> items[0] -> grades);
    }

    $count = forumlv_count_user_posts($forumlv -> id, $user -> id);

    if ($count && $count -> postcount > 0) {
        $result = new stdClass();
        $result -> info = get_string("numposts", "forumlv", $count -> postcount);
        $result -> time = $count -> lastpost;
        if ($grade) {
            $result -> info .= ', ' . get_string('grade') . ': ' . $grade -> str_long_grade;
        }
        return $result;
    } else if ($grade) {
        $result = new stdClass();
        $result -> info = get_string('grade') . ': ' . $grade -> str_long_grade;

        //datesubmitted == time created. dategraded == time modified or time overridden
        //if grade was last modified by the user themselves use date graded. Otherwise use date submitted
        //TODO: move this copied & pasted code somewhere in the grades API. See MDL-26704
        if ($grade -> usermodified == $user -> id || empty($grade -> datesubmitted)) {
            $result -> time = $grade -> dategraded;
        } else {
            $result -> time = $grade -> datesubmitted;
        }

        return $result;
    }
    return NULL;
}

/**
 * @global object
 * @global object
 * @param object $coure
 * @param object $user
 * @param object $mod
 * @param object $forumlv
 */
function forumlv_user_complete($course, $user, $mod, $forumlv) {
    global $CFG, $USER, $OUTPUT;
    require_once ("$CFG->libdir/gradelib.php");

    $grades = grade_get_grades($course -> id, 'mod', 'forumlv', $forumlv -> id, $user -> id);
    if (!empty($grades -> items[0] -> grades)) {
        $grade = reset($grades -> items[0] -> grades);
        echo $OUTPUT -> container(get_string('grade') . ': ' . $grade -> str_long_grade);
        if ($grade -> str_feedback) {
            echo $OUTPUT -> container(get_string('feedback') . ': ' . $grade -> str_feedback);
        }
    }

    if ($posts = forumlv_get_user_posts($forumlv -> id, $user -> id)) {

        if (!$cm = get_coursemodule_from_instance('forumlv', $forumlv -> id, $course -> id)) {
            print_error('invalidcoursemodule');
        }
        $discussions = forumlv_get_user_involved_discussions($forumlv -> id, $user -> id);

        foreach ($posts as $post) {
            if (!isset($discussions[$post -> discussion])) {
                continue;
            }
            $discussion = $discussions[$post -> discussion];

            forumlv_print_post($post, $discussion, $forumlv, $cm, $course, false, false, false);
        }
    } else {
        echo "<p>" . get_string("noposts", "forumlv") . "</p>";
    }
}

/**
 * @global object
 * @global object
 * @global object
 * @param array $courses
 * @param array $htmlarray
 */
function forumlv_print_overview($courses, &$htmlarray) {
    global $USER, $CFG, $DB, $SESSION;

    if (empty($courses) || !is_array($courses) || count($courses) == 0) {
        return array();
    }

    if (!$forumlvs = get_all_instances_in_courses('forumlv', $courses)) {
        return;
    }

    // Courses to search for new posts
    $coursessqls = array();
    $params = array();
    foreach ($courses as $course) {

        // If the user has never entered into the course all posts are pending
        if ($course -> lastaccess == 0) {
            $coursessqls[] = '(f.course = ?)';
            $params[] = $course -> id;

            // Only posts created after the course last access
        } else {
            $coursessqls[] = '(f.course = ? AND p.created > ?)';
            $params[] = $course -> id;
            $params[] = $course -> lastaccess;
        }
    }
    $params[] = $USER -> id;
    $coursessql = implode(' OR ', $coursessqls);

    $sql = "SELECT f.id, COUNT(*) as count " . 'FROM {forumlv} f ' . 'JOIN {forumlv_discussions} d ON d.forumlv  = f.id ' . 'JOIN {forumlv_posts} p ON p.discussion = d.id ' . "WHERE ($coursessql) " . 'AND p.userid != ? ' . 'GROUP BY f.id';

    if (!$new = $DB -> get_records_sql($sql, $params)) {
        $new = array();
        // avoid warnings
    }

    // also get all forumlv tracking stuff ONCE.
    $trackingforumlvs = array();
    foreach ($forumlvs as $forumlv) {
        if (forumlv_tp_can_track_forumlvs($forumlv)) {
            $trackingforumlvs[$forumlv -> id] = $forumlv;
        }
    }

    if (count($trackingforumlvs) > 0) {
        $cutoffdate = isset($CFG -> forumlv_oldpostdays) ? (time() - ($CFG -> forumlv_oldpostdays * 24 * 60 * 60)) : 0;
        $sql = 'SELECT d.forumlv,d.course,COUNT(p.id) AS count ' . ' FROM {forumlv_posts} p ' . ' JOIN {forumlv_discussions} d ON p.discussion = d.id ' . ' LEFT JOIN {forumlv_read} r ON r.postid = p.id AND r.userid = ? WHERE (';
        $params = array($USER -> id);

        foreach ($trackingforumlvs as $track) {
            $sql .= '(d.forumlv = ? AND (d.groupid = -1 OR d.groupid = 0 OR d.groupid = ?)) OR ';
            $params[] = $track -> id;
            if (isset($SESSION -> currentgroup[$track -> course])) {
                $groupid = $SESSION -> currentgroup[$track -> course];
            } else {
                // get first groupid
                $groupids = groups_get_all_groups($track -> course, $USER -> id);
                if ($groupids) {
                    reset($groupids);
                    $groupid = key($groupids);
                    $SESSION -> currentgroup[$track -> course] = $groupid;
                } else {
                    $groupid = 0;
                }
                unset($groupids);
            }
            $params[] = $groupid;
        }
        $sql = substr($sql, 0, -3);
        // take off the last OR
        $sql .= ') AND p.modified >= ? AND r.id is NULL GROUP BY d.forumlv,d.course';
        $params[] = $cutoffdate;

        if (!$unread = $DB -> get_records_sql($sql, $params)) {
            $unread = array();
        }
    } else {
        $unread = array();
    }

    if (empty($unread) and empty($new)) {
        return;
    }

    $strforumlv = get_string('modulename', 'forumlv');

    foreach ($forumlvs as $forumlv) {
        $str = '';
        $count = 0;
        $thisunread = 0;
        $showunread = false;
        // either we have something from logs, or trackposts, or nothing.
        if (array_key_exists($forumlv -> id, $new) && !empty($new[$forumlv -> id])) {
            $count = $new[$forumlv -> id] -> count;
        }
        if (array_key_exists($forumlv -> id, $unread)) {
            $thisunread = $unread[$forumlv -> id] -> count;
            $showunread = true;
        }
        if ($count > 0 || $thisunread > 0) {
            $str .= '<div class="overview forumlv"><div class="name">' . $strforumlv . ': <a title="' . $strforumlv . '" href="' . $CFG -> wwwroot . '/mod/forumlv/view.php?f=' . $forumlv -> id . '">' . $forumlv -> name . '</a></div>';
            $str .= '<div class="info"><span class="postsincelogin">';
            $str .= get_string('overviewnumpostssince', 'forumlv', $count) . "</span>";
            if (!empty($showunread)) {
                $str .= '<div class="unreadposts">' . get_string('overviewnumunread', 'forumlv', $thisunread) . '</div>';
            }
            $str .= '</div></div>';
        }
        if (!empty($str)) {
            if (!array_key_exists($forumlv -> course, $htmlarray)) {
                $htmlarray[$forumlv -> course] = array();
            }
            if (!array_key_exists('forumlv', $htmlarray[$forumlv -> course])) {
                $htmlarray[$forumlv -> course]['forumlv'] = '';
                // initialize, avoid warnings
            }
            $htmlarray[$forumlv -> course]['forumlv'] .= $str;
        }
    }
}

/**
 * Given a course and a date, prints a summary of all the new
 * messages posted in the course since that date
 *
 * @global object
 * @global object
 * @global object
 * @uses CONTEXT_MODULE
 * @uses VISIBLEGROUPS
 * @param object $course
 * @param bool $viewfullnames capability
 * @param int $timestart
 * @return bool success
 */
function forumlv_print_recent_activity($course, $viewfullnames, $timestart) {
    global $CFG, $USER, $DB, $OUTPUT;

    // do not use log table if possible, it may be huge and is expensive to join with other tables

    if (!$posts = $DB -> get_records_sql("SELECT p.*, f.type AS forumlvtype, d.forumlv, d.groupid,
                                              d.timestart, d.timeend, d.userid AS duserid,
                                              u.firstname, u.lastname, u.email, u.picture
                                         FROM {forumlv_posts} p
                                              JOIN {forumlv_discussions} d ON d.id = p.discussion
                                              JOIN {forumlv} f             ON f.id = d.forumlv
                                              JOIN {user} u              ON u.id = p.userid
                                        WHERE p.created > ? AND f.course = ?
                                     ORDER BY p.id ASC", array($timestart, $course -> id))) {// order by initial posting date
        return false;
    }

    $modinfo = get_fast_modinfo($course);

    $groupmodes = array();
    $cms = array();

    $strftimerecent = get_string('strftimerecent');

    $printposts = array();
    foreach ($posts as $post) {
        if (!isset($modinfo -> instances['forumlv'][$post -> forumlv])) {
            // not visible
            continue;
        }
        $cm = $modinfo -> instances['forumlv'][$post -> forumlv];
        if (!$cm -> uservisible) {
            continue;
        }
        $context = context_module::instance($cm -> id);

        if (!has_capability('mod/forumlv:viewdiscussion', $context)) {
            continue;
        }

        if (!empty($CFG -> forumlv_enabletimedposts) and $USER -> id != $post -> duserid and (($post -> timestart > 0 and $post -> timestart > time()) or ($post -> timeend > 0 and $post -> timeend < time()))) {
            if (!has_capability('mod/forumlv:viewhiddentimedposts', $context)) {
                continue;
            }
        }

        $groupmode = groups_get_activity_groupmode($cm, $course);

        if ($groupmode) {
            if ($post -> groupid == -1 or $groupmode == VISIBLEGROUPS or has_capability('moodle/site:accessallgroups', $context)) {
                // oki (Open discussions have groupid -1)
            } else {
                // separate mode
                if (isguestuser()) {
                    // shortcut
                    continue;
                }

                if (is_null($modinfo -> groups)) {
                    $modinfo -> groups = groups_get_user_groups($course -> id);
                    // load all my groups and cache it in modinfo
                }

                if (!array_key_exists($post -> groupid, $modinfo -> groups[0])) {
                    continue;
                }
            }
        }

        $printposts[] = $post;
    }
    unset($posts);

    if (!$printposts) {
        return false;
    }

    echo $OUTPUT -> heading(get_string('newforumlvposts', 'forumlv') . ':', 3);
    echo "\n<ul class='unlist'>\n";

    foreach ($printposts as $post) {
        $subjectclass = empty($post -> parent) ? ' bold' : '';

        echo '<li><div class="head">' . '<div class="date">' . userdate($post -> modified, $strftimerecent) . '</div>' . '<div class="name">' . fullname($post, $viewfullnames) . '</div>' . '</div>';
        echo '<div class="info' . $subjectclass . '">';
        if (empty($post -> parent)) {
            echo '"<a href="' . $CFG -> wwwroot . '/mod/forumlv/discuss.php?d=' . $post -> discussion . '">';
        } else {
            echo '"<a href="' . $CFG -> wwwroot . '/mod/forumlv/discuss.php?d=' . $post -> discussion . '&amp;parent=' . $post -> parent . '#p' . $post -> id . '">';
        }
        $post -> subject = break_up_long_words(format_string($post -> subject, true));
        echo $post -> subject;
        echo "</a>\"</div></li>\n";
    }

    echo "</ul>\n";

    return true;
}

/**
 * Return grade for given user or all users.
 *
 * @global object
 * @global object
 * @param object $forumlv
 * @param int $userid optional user id, 0 means all users
 * @return array array of grades, false if none
 */
function forumlv_get_user_grades($forumlv, $userid = 0) {
    global $CFG;

    require_once ($CFG -> dirroot . '/rating/lib.php');

    $ratingoptions = new stdClass;
    $ratingoptions -> component = 'mod_forumlv';
    $ratingoptions -> ratingarea = 'post';

    //need these to work backwards to get a context id. Is there a better way to get contextid from a module instance?
    $ratingoptions -> modulename = 'forumlv';
    $ratingoptions -> moduleid = $forumlv -> id;
    $ratingoptions -> userid = $userid;
    $ratingoptions -> aggregationmethod = $forumlv -> assessed;
    $ratingoptions -> scaleid = $forumlv -> scale;
    $ratingoptions -> itemtable = 'forumlv_posts';
    $ratingoptions -> itemtableusercolumn = 'userid';

    $rm = new rating_manager();
    return $rm -> get_user_grades($ratingoptions);
}

/**
 * Update activity grades
 *
 * @category grade
 * @param object $forumlv
 * @param int $userid specific user only, 0 means all
 * @param boolean $nullifnone return null if grade does not exist
 * @return void
 */
function forumlv_update_grades($forumlv, $userid = 0, $nullifnone = true) {
    global $CFG, $DB;
    require_once ($CFG -> libdir . '/gradelib.php');

    if (!$forumlv -> assessed) {
        forumlv_grade_item_update($forumlv);

    } else if ($grades = forumlv_get_user_grades($forumlv, $userid)) {
        forumlv_grade_item_update($forumlv, $grades);

    } else if ($userid and $nullifnone) {
        $grade = new stdClass();
        $grade -> userid = $userid;
        $grade -> rawgrade = NULL;
        forumlv_grade_item_update($forumlv, $grade);

    } else {
        forumlv_grade_item_update($forumlv);
    }
}

/**
 * Update all grades in gradebook.
 * @global object
 */
function forumlv_upgrade_grades() {
    global $DB;

    $sql = "SELECT COUNT('x')
              FROM {forumlv} f, {course_modules} cm, {modules} m
             WHERE m.name='forumlv' AND m.id=cm.module AND cm.instance=f.id";
    $count = $DB -> count_records_sql($sql);

    $sql = "SELECT f.*, cm.idnumber AS cmidnumber, f.course AS courseid
              FROM {forumlv} f, {course_modules} cm, {modules} m
             WHERE m.name='forumlv' AND m.id=cm.module AND cm.instance=f.id";
    $rs = $DB -> get_recordset_sql($sql);
    if ($rs -> valid()) {
        $pbar = new progress_bar('forumlvupgradegrades', 500, true);
        $i = 0;
        foreach ($rs as $forumlv) {
            $i++;
            upgrade_set_timeout(60 * 5);
            // set up timeout, may also abort execution
            forumlv_update_grades($forumlv, 0, false);
            $pbar -> update($i, $count, "Updating Forum grades ($i/$count).");
        }
    }
    $rs -> close();
}

/**
 * Create/update grade item for given forumlv
 *
 * @category grade
 * @uses GRADE_TYPE_NONE
 * @uses GRADE_TYPE_VALUE
 * @uses GRADE_TYPE_SCALE
 * @param stdClass $forumlv Forum object with extra cmidnumber
 * @param mixed $grades Optional array/object of grade(s); 'reset' means reset grades in gradebook
 * @return int 0 if ok
 */
function forumlv_grade_item_update($forumlv, $grades = NULL) {
    global $CFG;
    if (!function_exists('grade_update')) {//workaround for buggy PHP versions
        require_once ($CFG -> libdir . '/gradelib.php');
    }

    $params = array('itemname' => $forumlv -> name, 'idnumber' => $forumlv -> cmidnumber);

    if (!$forumlv -> assessed or $forumlv -> scale == 0) {
        $params['gradetype'] = GRADE_TYPE_NONE;

    } else if ($forumlv -> scale > 0) {
        $params['gradetype'] = GRADE_TYPE_VALUE;
        $params['grademax'] = $forumlv -> scale;
        $params['grademin'] = 0;

    } else if ($forumlv -> scale < 0) {
        $params['gradetype'] = GRADE_TYPE_SCALE;
        $params['scaleid'] = -$forumlv -> scale;
    }

    if ($grades === 'reset') {
        $params['reset'] = true;
        $grades = NULL;
    }

    return grade_update('mod/forumlv', $forumlv -> course, 'mod', 'forumlv', $forumlv -> id, 0, $grades, $params);
}

/**
 * Delete grade item for given forumlv
 *
 * @category grade
 * @param stdClass $forumlv Forum object
 * @return grade_item
 */
function forumlv_grade_item_delete($forumlv) {
    global $CFG;
    require_once ($CFG -> libdir . '/gradelib.php');

    return grade_update('mod/forumlv', $forumlv -> course, 'mod', 'forumlv', $forumlv -> id, 0, NULL, array('deleted' => 1));
}

/**
 * This function returns if a scale is being used by one forumlv
 *
 * @global object
 * @param int $forumlvid
 * @param int $scaleid negative number
 * @return bool
 */
function forumlv_scale_used($forumlvid, $scaleid) {
    global $DB;
    $return = false;

    $rec = $DB -> get_record("forumlv", array("id" => "$forumlvid", "scale" => "-$scaleid"));

    if (!empty($rec) && !empty($scaleid)) {
        $return = true;
    }

    return $return;
}

/**
 * Checks if scale is being used by any instance of forumlv
 *
 * This is used to find out if scale used anywhere
 *
 * @global object
 * @param $scaleid int
 * @return boolean True if the scale is used by any forumlv
 */
function forumlv_scale_used_anywhere($scaleid) {
    global $DB;
    if ($scaleid and $DB -> record_exists('forumlv', array('scale' => -$scaleid))) {
        return true;
    } else {
        return false;
    }
}

// SQL FUNCTIONS ///////////////////////////////////////////////////////////

/**
 * Gets a post with all info ready for forumlv_print_post
 * Most of these joins are just to get the forumlv id
 *
 * @global object
 * @global object
 * @param int $postid
 * @return mixed array of posts or false
 */
function forumlv_get_post_full($postid) {
    global $CFG, $DB;

    return $DB -> get_record_sql("SELECT p.*, d.forumlv, u.firstname, u.lastname, u.email, u.picture, u.imagealt
                             FROM {forumlv_posts} p
                                  JOIN {forumlv_discussions} d ON p.discussion = d.id
                                  LEFT JOIN {user} u ON p.userid = u.id
                            WHERE p.id = ?", array($postid));
}

/**
 * Gets posts with all info ready for forumlv_print_post
 * We pass forumlvid in because we always know it so no need to make a
 * complicated join to find it out.
 *
 * @global object
 * @global object
 * @return mixed array of posts or false
 */
function forumlv_get_discussion_posts($discussion, $sort, $forumlvid) {
    global $CFG, $DB;

    return $DB -> get_records_sql("SELECT p.*, $forumlvid AS forumlv, u.firstname, u.lastname, u.email, u.picture, u.imagealt
                              FROM {forumlv_posts} p
                         LEFT JOIN {user} u ON p.userid = u.id
                             WHERE p.discussion = ?
                               AND p.parent > 0 $sort", array($discussion));
}

/**
 * Gets all posts in discussion including top parent.
 *
 * @global object
 * @global object
 * @global object
 * @param int $discussionid
 * @param string $sort
 * @param bool $tracking does user track the forumlv?
 * @return array of posts
 */
function forumlv_get_all_discussion_posts($discussionid, $sort, $tracking = false) {
    global $CFG, $DB, $USER;

    $tr_sel = "";
    $tr_join = "";
    $params = array();

    if ($tracking) {
        $now = time();
        $cutoffdate = $now - ($CFG -> forumlv_oldpostdays * 24 * 3600);
        $tr_sel = ", fr.id AS postread";
        $tr_join = "LEFT JOIN {forumlv_read} fr ON (fr.postid = p.id AND fr.userid = ?)";
        $params[] = $USER -> id;
    }

    $params[] = $discussionid;
    if (!$posts = $DB -> get_records_sql("SELECT p.*, u.firstname, u.lastname, u.email, u.picture, u.imagealt $tr_sel
                                     FROM {forumlv_posts} p
                                          LEFT JOIN {user} u ON p.userid = u.id
                                          $tr_join
                                    WHERE p.discussion = ?
                                 ORDER BY $sort", $params)) {
        return array();
    }

    foreach ($posts as $pid => $p) {
        if ($tracking) {
            if (forumlv_tp_is_post_old($p)) {
                $posts[$pid] -> postread = true;
            }
        }
        if (!$p -> parent) {
            continue;
        }
        if (!isset($posts[$p -> parent])) {
            continue;
            // parent does not exist??
        }
        if (!isset($posts[$p -> parent] -> children)) {
            $posts[$p -> parent] -> children = array();
        }
        $posts[$p -> parent] -> children[$pid] = &$posts[$pid];
    }

    return $posts;
}

/**
 * Gets posts with all info ready for forumlv_print_post
 * We pass forumlvid in because we always know it so no need to make a
 * complicated join to find it out.
 *
 * @global object
 * @global object
 * @param int $parent
 * @param int $forumlvid
 * @return array
 */
function forumlv_get_child_posts($parent, $forumlvid) {
    global $CFG, $DB;

    return $DB -> get_records_sql("SELECT p.*, $forumlvid AS forumlv, u.firstname, u.lastname, u.email, u.picture, u.imagealt
                              FROM {forumlv_posts} p
                         LEFT JOIN {user} u ON p.userid = u.id
                             WHERE p.parent = ?
                          ORDER BY p.created ASC", array($parent));
}

/**
 * An array of forumlv objects that the user is allowed to read/search through.
 *
 * @global object
 * @global object
 * @global object
 * @param int $userid
 * @param int $courseid if 0, we look for forumlvs throughout the whole site.
 * @return array of forumlv objects, or false if no matches
 *         Forum objects have the following attributes:
 *         id, type, course, cmid, cmvisible, cmgroupmode, accessallgroups,
 *         viewhiddentimedposts
 */
function forumlv_get_readable_forumlvs($userid, $courseid = 0) {

    global $CFG, $DB, $USER;
    require_once ($CFG -> dirroot . '/course/lib.php');

    if (!$forumlvmod = $DB -> get_record('modules', array('name' => 'forumlv'))) {
        print_error('notinstalled', 'forumlv');
    }

    if ($courseid) {
        $courses = $DB -> get_records('course', array('id' => $courseid));
    } else {
        // If no course is specified, then the user can see SITE + his courses.
        $courses1 = $DB -> get_records('course', array('id' => SITEID));
        $courses2 = enrol_get_users_courses($userid, true, array('modinfo'));
        $courses = array_merge($courses1, $courses2);
    }
    if (!$courses) {
        return array();
    }

    $readableforumlvs = array();

    foreach ($courses as $course) {

        $modinfo = get_fast_modinfo($course);
        if (is_null($modinfo -> groups)) {
            $modinfo -> groups = groups_get_user_groups($course -> id, $userid);
        }

        if (empty($modinfo -> instances['forumlv'])) {
            // hmm, no forumlvs?
            continue;
        }

        $courseforumlvs = $DB -> get_records('forumlv', array('course' => $course -> id));

        foreach ($modinfo->instances['forumlv'] as $forumlvid => $cm) {
            if (!$cm -> uservisible or !isset($courseforumlvs[$forumlvid])) {
                continue;
            }
            $context = context_module::instance($cm -> id);
            $forumlv = $courseforumlvs[$forumlvid];
            $forumlv -> context = $context;
            $forumlv -> cm = $cm;

            if (!has_capability('mod/forumlv:viewdiscussion', $context)) {
                continue;
            }

            /// group access
            if (groups_get_activity_groupmode($cm, $course) == SEPARATEGROUPS and !has_capability('moodle/site:accessallgroups', $context)) {
                if (is_null($modinfo -> groups)) {
                    $modinfo -> groups = groups_get_user_groups($course -> id, $USER -> id);
                }
                if (isset($modinfo -> groups[$cm -> groupingid])) {
                    $forumlv -> onlygroups = $modinfo -> groups[$cm -> groupingid];
                    $forumlv -> onlygroups[] = -1;
                } else {
                    $forumlv -> onlygroups = array(-1);
                }
            }

            /// hidden timed discussions
            $forumlv -> viewhiddentimedposts = true;
            if (!empty($CFG -> forumlv_enabletimedposts)) {
                if (!has_capability('mod/forumlv:viewhiddentimedposts', $context)) {
                    $forumlv -> viewhiddentimedposts = false;
                }
            }

            /// qanda access
            if ($forumlv -> type == 'qanda' && !has_capability('mod/forumlv:viewqandawithoutposting', $context)) {

                // We need to check whether the user has posted in the qanda forumlv.
                $forumlv -> onlydiscussions = array();
                // Holds discussion ids for the discussions
                // the user is allowed to see in this forumlv.
                if ($discussionspostedin = forumlv_discussions_user_has_posted_in($forumlv -> id, $USER -> id)) {
                    foreach ($discussionspostedin as $d) {
                        $forumlv -> onlydiscussions[] = $d -> id;
                    }
                }
            }

            $readableforumlvs[$forumlv -> id] = $forumlv;
        }

        unset($modinfo);

    }// End foreach $courses

    return $readableforumlvs;
}

/**
 * Returns a list of posts found using an array of search terms.
 *
 * @global object
 * @global object
 * @global object
 * @param array $searchterms array of search terms, e.g. word +word -word
 * @param int $courseid if 0, we search through the whole site
 * @param int $limitfrom
 * @param int $limitnum
 * @param int &$totalcount
 * @param string $extrasql
 * @return array|bool Array of posts found or false
 */
function forumlv_search_posts($searchterms, $courseid = 0, $limitfrom = 0, $limitnum = 50, &$totalcount, $extrasql = '') {
    global $CFG, $DB, $USER;
    require_once ($CFG -> libdir . '/searchlib.php');

    $forumlvs = forumlv_get_readable_forumlvs($USER -> id, $courseid);

    if (count($forumlvs) == 0) {
        $totalcount = 0;
        return false;
    }

    $now = round(time(), -2);
    // db friendly

    $fullaccess = array();
    $where = array();
    $params = array();

    foreach ($forumlvs as $forumlvid => $forumlv) {
        $select = array();

        if (!$forumlv -> viewhiddentimedposts) {
            $select[] = "(d.userid = :userid{$forumlvid} OR (d.timestart < :timestart{$forumlvid} AND (d.timeend = 0 OR d.timeend > :timeend{$forumlvid})))";
            $params = array_merge($params, array('userid' . $forumlvid => $USER -> id, 'timestart' . $forumlvid => $now, 'timeend' . $forumlvid => $now));
        }

        $cm = $forumlv -> cm;
        $context = $forumlv -> context;

        if ($forumlv -> type == 'qanda' && !has_capability('mod/forumlv:viewqandawithoutposting', $context)) {
            if (!empty($forumlv -> onlydiscussions)) {
                list($discussionid_sql, $discussionid_params) = $DB -> get_in_or_equal($forumlv -> onlydiscussions, SQL_PARAMS_NAMED, 'qanda' . $forumlvid . '_');
                $params = array_merge($params, $discussionid_params);
                $select[] = "(d.id $discussionid_sql OR p.parent = 0)";
            } else {
                $select[] = "p.parent = 0";
            }
        }

        if (!empty($forumlv -> onlygroups)) {
            list($groupid_sql, $groupid_params) = $DB -> get_in_or_equal($forumlv -> onlygroups, SQL_PARAMS_NAMED, 'grps' . $forumlvid . '_');
            $params = array_merge($params, $groupid_params);
            $select[] = "d.groupid $groupid_sql";
        }

        if ($select) {
            $selects = implode(" AND ", $select);
            $where[] = "(d.forumlv = :forumlv{$forumlvid} AND $selects)";
            $params['forumlv' . $forumlvid] = $forumlvid;
        } else {
            $fullaccess[] = $forumlvid;
        }
    }

    if ($fullaccess) {
        list($fullid_sql, $fullid_params) = $DB -> get_in_or_equal($fullaccess, SQL_PARAMS_NAMED, 'fula');
        $params = array_merge($params, $fullid_params);
        $where[] = "(d.forumlv $fullid_sql)";
    }

    $selectdiscussion = "(" . implode(" OR ", $where) . ")";

    $messagesearch = '';
    $searchstring = '';

    // Need to concat these back together for parser to work.
    foreach ($searchterms as $searchterm) {
        if ($searchstring != '') {
            $searchstring .= ' ';
        }
        $searchstring .= $searchterm;
    }

    // We need to allow quoted strings for the search. The quotes *should* be stripped
    // by the parser, but this should be examined carefully for security implications.
    $searchstring = str_replace("\\\"", "\"", $searchstring);
    $parser = new search_parser();
    $lexer = new search_lexer($parser);

    if ($lexer -> parse($searchstring)) {
        $parsearray = $parser -> get_parsed_array();
        // Experimental feature under 1.8! MDL-8830
        // Use alternative text searches if defined
        // This feature only works under mysql until properly implemented for other DBs
        // Requires manual creation of text index for forumlv_posts before enabling it:
        // CREATE FULLTEXT INDEX foru_post_tix ON [prefix]forumlv_posts (subject, message)
        // Experimental feature under 1.8! MDL-8830
        if (!empty($CFG -> forumlv_usetextsearches)) {
            list($messagesearch, $msparams) = search_generate_text_SQL($parsearray, 'p.message', 'p.subject', 'p.userid', 'u.id', 'u.firstname', 'u.lastname', 'p.modified', 'd.forumlv');
        } else {
            list($messagesearch, $msparams) = search_generate_SQL($parsearray, 'p.message', 'p.subject', 'p.userid', 'u.id', 'u.firstname', 'u.lastname', 'p.modified', 'd.forumlv');
        }
        $params = array_merge($params, $msparams);
    }

    $fromsql = "{forumlv_posts} p,
                  {forumlv_discussions} d,
                  {user} u";

    $selectsql = " $messagesearch
               AND p.discussion = d.id
               AND p.userid = u.id
               AND $selectdiscussion
                   $extrasql";

    $countsql = "SELECT COUNT(*)
                   FROM $fromsql
                  WHERE $selectsql";

    $searchsql = "SELECT p.*,
                         d.forumlv,
                         u.firstname,
                         u.lastname,
                         u.email,
                         u.picture,
                         u.imagealt
                    FROM $fromsql
                   WHERE $selectsql
                ORDER BY p.modified DESC";

    $totalcount = $DB -> count_records_sql($countsql, $params);

    return $DB -> get_records_sql($searchsql, $params, $limitfrom, $limitnum);
}

/**
 * Returns a list of ratings for a particular post - sorted.
 *
 * TODO: Check if this function is actually used anywhere.
 * Up until the fix for MDL-27471 this function wasn't even returning.
 *
 * @param stdClass $context
 * @param int $postid
 * @param string $sort
 * @return array Array of ratings or false
 */
function forumlv_get_ratings($context, $postid, $sort = "u.firstname ASC") {
    $options = new stdClass;
    $options -> context = $context;
    $options -> component = 'mod_forumlv';
    $options -> ratingarea = 'post';
    $options -> itemid = $postid;
    $options -> sort = "ORDER BY $sort";

    $rm = new rating_manager();
    return $rm -> get_all_ratings_for_item($options);
}

/**
 * Returns a list of all new posts that have not been mailed yet
 *
 * @param int $starttime posts created after this time
 * @param int $endtime posts created before this
 * @param int $now used for timed discussions only
 * @return array
 */
function forumlv_get_unmailed_posts($starttime, $endtime, $now = null) {
    global $CFG, $DB;

    $params = array();
    $params['mailed'] = FORUMLV_MAILED_PENDING;
    $params['ptimestart'] = $starttime;
    $params['ptimeend'] = $endtime;
    $params['mailnow'] = 1;

    if (!empty($CFG -> forumlv_enabletimedposts)) {
        if (empty($now)) {
            $now = time();
        }
        $timedsql = "AND (d.timestart < :dtimestart AND (d.timeend = 0 OR d.timeend > :dtimeend))";
        $params['dtimestart'] = $now;
        $params['dtimeend'] = $now;
    } else {
        $timedsql = "";
    }

    return $DB -> get_records_sql("SELECT p.*, d.course, d.forumlv
                                 FROM {forumlv_posts} p
                                 JOIN {forumlv_discussions} d ON d.id = p.discussion
                                 WHERE p.mailed = :mailed
                                 AND p.created >= :ptimestart
                                 AND (p.created < :ptimeend OR p.mailnow = :mailnow)
                                 $timedsql
                                 ORDER BY p.modified ASC", $params);
}

/**
 * Marks posts before a certain time as being mailed already
 *
 * @global object
 * @global object
 * @param int $endtime
 * @param int $now Defaults to time()
 * @return bool
 */
function forumlv_mark_old_posts_as_mailed($endtime, $now = null) {
    global $CFG, $DB;

    if (empty($now)) {
        $now = time();
    }

    $params = array();
    $params['mailedsuccess'] = FORUMLV_MAILED_SUCCESS;
    $params['now'] = $now;
    $params['endtime'] = $endtime;
    $params['mailnow'] = 1;
    $params['mailedpending'] = FORUMLV_MAILED_PENDING;

    if (empty($CFG -> forumlv_enabletimedposts)) {
        return $DB -> execute("UPDATE {forumlv_posts}
                             SET mailed = :mailedsuccess
                             WHERE (created < :endtime OR mailnow = :mailnow)
                             AND mailed = :mailedpending", $params);
    } else {
        return $DB -> execute("UPDATE {forumlv_posts}
                             SET mailed = :mailedsuccess
                             WHERE discussion NOT IN (SELECT d.id
                                                      FROM {forumlv_discussions} d
                                                      WHERE d.timestart > :now)
                             AND (created < :endtime OR mailnow = :mailnow)
                             AND mailed = :mailedpending", $params);
    }
}

/**
 * Get all the posts for a user in a forumlv suitable for forumlv_print_post
 *
 * @global object
 * @global object
 * @uses CONTEXT_MODULE
 * @return array
 */
function forumlv_get_user_posts($forumlvid, $userid) {
    global $CFG, $DB;

    $timedsql = "";
    $params = array($forumlvid, $userid);

    if (!empty($CFG -> forumlv_enabletimedposts)) {
        $cm = get_coursemodule_from_instance('forumlv', $forumlvid);
        if (!has_capability('mod/forumlv:viewhiddentimedposts', context_module::instance($cm -> id))) {
            $now = time();
            $timedsql = "AND (d.timestart < ? AND (d.timeend = 0 OR d.timeend > ?))";
            $params[] = $now;
            $params[] = $now;
        }
    }

    return $DB -> get_records_sql("SELECT p.*, d.forumlv, u.firstname, u.lastname, u.email, u.picture, u.imagealt
                              FROM {forumlv} f
                                   JOIN {forumlv_discussions} d ON d.forumlv = f.id
                                   JOIN {forumlv_posts} p       ON p.discussion = d.id
                                   JOIN {user} u              ON u.id = p.userid
                             WHERE f.id = ?
                                   AND p.userid = ?
                                   $timedsql
                          ORDER BY p.modified ASC", $params);
}

/**
 * Get all the discussions user participated in
 *
 * @global object
 * @global object
 * @uses CONTEXT_MODULE
 * @param int $forumlvid
 * @param int $userid
 * @return array Array or false
 */
function forumlv_get_user_involved_discussions($forumlvid, $userid) {
    global $CFG, $DB;

    $timedsql = "";
    $params = array($forumlvid, $userid);
    if (!empty($CFG -> forumlv_enabletimedposts)) {
        $cm = get_coursemodule_from_instance('forumlv', $forumlvid);
        if (!has_capability('mod/forumlv:viewhiddentimedposts', context_module::instance($cm -> id))) {
            $now = time();
            $timedsql = "AND (d.timestart < ? AND (d.timeend = 0 OR d.timeend > ?))";
            $params[] = $now;
            $params[] = $now;
        }
    }

    return $DB -> get_records_sql("SELECT DISTINCT d.*
                              FROM {forumlv} f
                                   JOIN {forumlv_discussions} d ON d.forumlv = f.id
                                   JOIN {forumlv_posts} p       ON p.discussion = d.id
                             WHERE f.id = ?
                                   AND p.userid = ?
                                   $timedsql", $params);
}

/**
 * Get all the posts for a user in a forumlv suitable for forumlv_print_post
 *
 * @global object
 * @global object
 * @param int $forumlvid
 * @param int $userid
 * @return array of counts or false
 */
function forumlv_count_user_posts($forumlvid, $userid) {
    global $CFG, $DB;

    $timedsql = "";
    $params = array($forumlvid, $userid);
    if (!empty($CFG -> forumlv_enabletimedposts)) {
        $cm = get_coursemodule_from_instance('forumlv', $forumlvid);
        if (!has_capability('mod/forumlv:viewhiddentimedposts', context_module::instance($cm -> id))) {
            $now = time();
            $timedsql = "AND (d.timestart < ? AND (d.timeend = 0 OR d.timeend > ?))";
            $params[] = $now;
            $params[] = $now;
        }
    }

    return $DB -> get_record_sql("SELECT COUNT(p.id) AS postcount, MAX(p.modified) AS lastpost
                             FROM {forumlv} f
                                  JOIN {forumlv_discussions} d ON d.forumlv = f.id
                                  JOIN {forumlv_posts} p       ON p.discussion = d.id
                                  JOIN {user} u              ON u.id = p.userid
                            WHERE f.id = ?
                                  AND p.userid = ?
                                  $timedsql", $params);
}

/**
 * Given a log entry, return the forumlv post details for it.
 *
 * @global object
 * @global object
 * @param object $log
 * @return array|null
 */
function forumlv_get_post_from_log($log) {
    global $CFG, $DB;

    if ($log -> action == "add post") {

        return $DB -> get_record_sql("SELECT p.*, f.type AS forumlvtype, d.forumlv, d.groupid,
                                           u.firstname, u.lastname, u.email, u.picture
                                 FROM {forumlv_discussions} d,
                                      {forumlv_posts} p,
                                      {forumlv} f,
                                      {user} u
                                WHERE p.id = ?
                                  AND d.id = p.discussion
                                  AND p.userid = u.id
                                  AND u.deleted <> '1'
                                  AND f.id = d.forumlv", array($log -> info));

    } else if ($log -> action == "add discussion") {

        return $DB -> get_record_sql("SELECT p.*, f.type AS forumlvtype, d.forumlv, d.groupid,
                                           u.firstname, u.lastname, u.email, u.picture
                                 FROM {forumlv_discussions} d,
                                      {forumlv_posts} p,
                                      {forumlv} f,
                                      {user} u
                                WHERE d.id = ?
                                  AND d.firstpost = p.id
                                  AND p.userid = u.id
                                  AND u.deleted <> '1'
                                  AND f.id = d.forumlv", array($log -> info));
    }
    return NULL;
}

/**
 * Given a discussion id, return the first post from the discussion
 *
 * @global object
 * @global object
 * @param int $dicsussionid
 * @return array
 */
function forumlv_get_firstpost_from_discussion($discussionid) {
    global $CFG, $DB;

    return $DB -> get_record_sql("SELECT p.*
                             FROM {forumlv_discussions} d,
                                  {forumlv_posts} p
                            WHERE d.id = ?
                              AND d.firstpost = p.id ", array($discussionid));
}

/**
 * Returns an array of counts of replies to each discussion
 *
 * @global object
 * @global object
 * @param int $forumlvid
 * @param string $forumlvsort
 * @param int $limit
 * @param int $page
 * @param int $perpage
 * @return array
 */
function forumlv_count_discussion_replies($forumlvid, $forumlvsort = "", $limit = -1, $page = -1, $perpage = 0) {
    global $CFG, $DB;

    if ($limit > 0) {
        $limitfrom = 0;
        $limitnum = $limit;
    } else if ($page != -1) {
        $limitfrom = $page * $perpage;
        $limitnum = $perpage;
    } else {
        $limitfrom = 0;
        $limitnum = 0;
    }

    if ($forumlvsort == "") {
        $orderby = "";
        $groupby = "";

    } else {
        $orderby = "ORDER BY $forumlvsort";
        $groupby = ", " . strtolower($forumlvsort);
        $groupby = str_replace('desc', '', $groupby);
        $groupby = str_replace('asc', '', $groupby);
    }

    if (($limitfrom == 0 and $limitnum == 0) or $forumlvsort == "") {
        $sql = "SELECT p.discussion, COUNT(p.id) AS replies, MAX(p.id) AS lastpostid
                  FROM {forumlv_posts} p
                       JOIN {forumlv_discussions} d ON p.discussion = d.id
                 WHERE p.parent > 0 AND d.forumlv = ?
              GROUP BY p.discussion";
        return $DB -> get_records_sql($sql, array($forumlvid));

    } else {
        $sql = "SELECT p.discussion, (COUNT(p.id) - 1) AS replies, MAX(p.id) AS lastpostid
                  FROM {forumlv_posts} p
                       JOIN {forumlv_discussions} d ON p.discussion = d.id
                 WHERE d.forumlv = ?
              GROUP BY p.discussion $groupby
              $orderby";
        return $DB -> get_records_sql("SELECT * FROM ($sql) sq", array($forumlvid), $limitfrom, $limitnum);
    }
}

/**
 * @global object
 * @global object
 * @global object
 * @staticvar array $cache
 * @param object $forumlv
 * @param object $cm
 * @param object $course
 * @return mixed
 */
function forumlv_count_discussions($forumlv, $cm, $course) {
    global $CFG, $DB, $USER;

    static $cache = array();

    $now = round(time(), -2);
    // db cache friendliness

    $params = array($course -> id);

    if (!isset($cache[$course -> id])) {
        if (!empty($CFG -> forumlv_enabletimedposts)) {
            $timedsql = "AND d.timestart < ? AND (d.timeend = 0 OR d.timeend > ?)";
            $params[] = $now;
            $params[] = $now;
        } else {
            $timedsql = "";
        }

        $sql = "SELECT f.id, COUNT(d.id) as dcount
                  FROM {forumlv} f
                       JOIN {forumlv_discussions} d ON d.forumlv = f.id
                 WHERE f.course = ?
                       $timedsql
              GROUP BY f.id";

        if ($counts = $DB -> get_records_sql($sql, $params)) {
            foreach ($counts as $count) {
                $counts[$count -> id] = $count -> dcount;
            }
            $cache[$course -> id] = $counts;
        } else {
            $cache[$course -> id] = array();
        }
    }

    if (empty($cache[$course -> id][$forumlv -> id])) {
        return 0;
    }

    $groupmode = groups_get_activity_groupmode($cm, $course);

    if ($groupmode != SEPARATEGROUPS) {
        return $cache[$course -> id][$forumlv -> id];
    }

    if (has_capability('moodle/site:accessallgroups', context_module::instance($cm -> id))) {
        return $cache[$course -> id][$forumlv -> id];
    }

    require_once ($CFG -> dirroot . '/course/lib.php');

    $modinfo = get_fast_modinfo($course);
    if (is_null($modinfo -> groups)) {
        $modinfo -> groups = groups_get_user_groups($course -> id, $USER -> id);
    }

    if (array_key_exists($cm -> groupingid, $modinfo -> groups)) {
        $mygroups = $modinfo -> groups[$cm -> groupingid];
    } else {
        $mygroups = false;
        // Will be set below
    }

    // add all groups posts
    if (empty($mygroups)) {
        $mygroups = array(-1 => -1);
    } else {
        $mygroups[-1] = -1;
    }

    list($mygroups_sql, $params) = $DB -> get_in_or_equal($mygroups);
    $params[] = $forumlv -> id;

    if (!empty($CFG -> forumlv_enabletimedposts)) {
        $timedsql = "AND d.timestart < $now AND (d.timeend = 0 OR d.timeend > $now)";
        $params[] = $now;
        $params[] = $now;
    } else {
        $timedsql = "";
    }

    $sql = "SELECT COUNT(d.id)
              FROM {forumlv_discussions} d
             WHERE d.groupid $mygroups_sql AND d.forumlv = ?
                   $timedsql";

    return $DB -> get_field_sql($sql, $params);
}

/**
 * How many posts by other users are unrated by a given user in the given discussion?
 *
 * TODO: Is this function still used anywhere?
 *
 * @param int $discussionid
 * @param int $userid
 * @return mixed
 */
function forumlv_count_unrated_posts($discussionid, $userid) {
    global $CFG, $DB;

    $sql = "SELECT COUNT(*) as num
              FROM {forumlv_posts}
             WHERE parent > 0
               AND discussion = :discussionid
               AND userid <> :userid";
    $params = array('discussionid' => $discussionid, 'userid' => $userid);
    $posts = $DB -> get_record_sql($sql, $params);
    if ($posts) {
        $sql = "SELECT count(*) as num
                  FROM {forumlv_posts} p,
                       {rating} r
                 WHERE p.discussion = :discussionid AND
                       p.id = r.itemid AND
                       r.userid = userid AND
                       r.component = 'mod_forumlv' AND
                       r.ratingarea = 'post'";
        $rated = $DB -> get_record_sql($sql, $params);
        if ($rated) {
            if ($posts -> num > $rated -> num) {
                return $posts -> num - $rated -> num;
            } else {
                return 0;
                // Just in case there was a counting error
            }
        } else {
            return $posts -> num;
        }
    } else {
        return 0;
    }
}

/**
 * Get all discussions in a forumlv
 *
 * @global object
 * @global object
 * @global object
 * @uses CONTEXT_MODULE
 * @uses VISIBLEGROUPS
 * @param object $cm
 * @param string $forumlvsort
 * @param bool $fullpost
 * @param int $unused
 * @param int $limit
 * @param bool $userlastmodified
 * @param int $page
 * @param int $perpage
 * @return array
 */
function forumlv_get_discussions($cm, $forumlvsort = "d.timemodified DESC", $fullpost = true, $unused = -1, $limit = -1, $userlastmodified = false, $page = -1, $perpage = 0) {
    global $CFG, $DB, $USER;

    $timelimit = '';

    $now = round(time(), -2);
    $params = array($cm -> instance);

    $modcontext = context_module::instance($cm -> id);

    if (!has_capability('mod/forumlv:viewdiscussion', $modcontext)) {/// User must have perms to view discussions
        return array();
    }

    if (!empty($CFG -> forumlv_enabletimedposts)) {/// Users must fulfill timed posts

        if (!has_capability('mod/forumlv:viewhiddentimedposts', $modcontext)) {
            $timelimit = " AND ((d.timestart <= ? AND (d.timeend = 0 OR d.timeend > ?))";
            $params[] = $now;
            $params[] = $now;
            if (isloggedin()) {
                $timelimit .= " OR d.userid = ?";
                $params[] = $USER -> id;
            }
            $timelimit .= ")";
        }
    }

    if ($limit > 0) {
        $limitfrom = 0;
        $limitnum = $limit;
    } else if ($page != -1) {
        $limitfrom = $page * $perpage;
        $limitnum = $perpage;
    } else {
        $limitfrom = 0;
        $limitnum = 0;
    }

    $groupmode = groups_get_activity_groupmode($cm);
    $currentgroup = groups_get_activity_group($cm);

    if ($groupmode) {
        if (empty($modcontext)) {
            $modcontext = context_module::instance($cm -> id);
        }

        if ($groupmode == VISIBLEGROUPS or has_capability('moodle/site:accessallgroups', $modcontext)) {
            if ($currentgroup) {
                $groupselect = "AND (d.groupid = ? OR d.groupid = -1)";
                $params[] = $currentgroup;
            } else {
                $groupselect = "";
            }

        } else {
            //seprate groups without access all
            if ($currentgroup) {
                $groupselect = "AND (d.groupid = ? OR d.groupid = -1)";
                $params[] = $currentgroup;
            } else {
                $groupselect = "AND d.groupid = -1";
            }
        }
    } else {
        $groupselect = "";
    }

    if (empty($forumlvsort)) {
        $forumlvsort = "d.timemodified DESC";
    }
    if (empty($fullpost)) {
        $postdata = "p.id,p.subject,p.modified,p.discussion,p.userid";
    } else {
        $postdata = "p.*";
    }

    if (empty($userlastmodified)) {// We don't need to know this
        $umfields = "";
        $umtable = "";
    } else {
        $umfields = ", um.firstname AS umfirstname, um.lastname AS umlastname";
        $umtable = " LEFT JOIN {user} um ON (d.usermodified = um.id)";
    }

    $sql = "SELECT $postdata, d.name, d.timemodified, d.usermodified, d.groupid, d.timestart, d.timeend,
                   u.firstname, u.lastname, u.email, u.picture, u.imagealt $umfields
              FROM {forumlv_discussions} d
                   JOIN {forumlv_posts} p ON p.discussion = d.id
                   JOIN {user} u ON p.userid = u.id
                   $umtable
             WHERE d.forumlv = ? AND p.parent = 0
                   $timelimit $groupselect
          ORDER BY $forumlvsort";
    return $DB -> get_records_sql($sql, $params, $limitfrom, $limitnum);
}

/**
 *
 * @global object
 * @global object
 * @global object
 * @uses CONTEXT_MODULE
 * @uses VISIBLEGROUPS
 * @param object $cm
 * @return array
 */
function forumlv_get_discussions_unread($cm) {
    global $CFG, $DB, $USER;

    $now = round(time(), -2);
    $cutoffdate = $now - ($CFG -> forumlv_oldpostdays * 24 * 60 * 60);

    $params = array();
    $groupmode = groups_get_activity_groupmode($cm);
    $currentgroup = groups_get_activity_group($cm);

    if ($groupmode) {
        $modcontext = context_module::instance($cm -> id);

        if ($groupmode == VISIBLEGROUPS or has_capability('moodle/site:accessallgroups', $modcontext)) {
            if ($currentgroup) {
                $groupselect = "AND (d.groupid = :currentgroup OR d.groupid = -1)";
                $params['currentgroup'] = $currentgroup;
            } else {
                $groupselect = "";
            }

        } else {
            //separate groups without access all
            if ($currentgroup) {
                $groupselect = "AND (d.groupid = :currentgroup OR d.groupid = -1)";
                $params['currentgroup'] = $currentgroup;
            } else {
                $groupselect = "AND d.groupid = -1";
            }
        }
    } else {
        $groupselect = "";
    }

    if (!empty($CFG -> forumlv_enabletimedposts)) {
        $timedsql = "AND d.timestart < :now1 AND (d.timeend = 0 OR d.timeend > :now2)";
        $params['now1'] = $now;
        $params['now2'] = $now;
    } else {
        $timedsql = "";
    }

    $sql = "SELECT d.id, COUNT(p.id) AS unread
              FROM {forumlv_discussions} d
                   JOIN {forumlv_posts} p     ON p.discussion = d.id
                   LEFT JOIN {forumlv_read} r ON (r.postid = p.id AND r.userid = $USER->id)
             WHERE d.forumlv = {$cm->instance}
                   AND p.modified >= :cutoffdate AND r.id is NULL
                   $groupselect
                   $timedsql
          GROUP BY d.id";
    $params['cutoffdate'] = $cutoffdate;

    if ($unreads = $DB -> get_records_sql($sql, $params)) {
        foreach ($unreads as $unread) {
            $unreads[$unread -> id] = $unread -> unread;
        }
        return $unreads;
    } else {
        return array();
    }
}

/**
 * @global object
 * @global object
 * @global object
 * @uses CONEXT_MODULE
 * @uses VISIBLEGROUPS
 * @param object $cm
 * @return array
 */
function forumlv_get_discussions_count($cm) {
    global $CFG, $DB, $USER;

    $now = round(time(), -2);
    $params = array($cm -> instance);
    $groupmode = groups_get_activity_groupmode($cm);
    $currentgroup = groups_get_activity_group($cm);

    if ($groupmode) {
        $modcontext = context_module::instance($cm -> id);

        if ($groupmode == VISIBLEGROUPS or has_capability('moodle/site:accessallgroups', $modcontext)) {
            if ($currentgroup) {
                $groupselect = "AND (d.groupid = ? OR d.groupid = -1)";
                $params[] = $currentgroup;
            } else {
                $groupselect = "";
            }

        } else {
            //seprate groups without access all
            if ($currentgroup) {
                $groupselect = "AND (d.groupid = ? OR d.groupid = -1)";
                $params[] = $currentgroup;
            } else {
                $groupselect = "AND d.groupid = -1";
            }
        }
    } else {
        $groupselect = "";
    }

    $cutoffdate = $now - ($CFG -> forumlv_oldpostdays * 24 * 60 * 60);

    $timelimit = "";

    if (!empty($CFG -> forumlv_enabletimedposts)) {

        $modcontext = context_module::instance($cm -> id);

        if (!has_capability('mod/forumlv:viewhiddentimedposts', $modcontext)) {
            $timelimit = " AND ((d.timestart <= ? AND (d.timeend = 0 OR d.timeend > ?))";
            $params[] = $now;
            $params[] = $now;
            if (isloggedin()) {
                $timelimit .= " OR d.userid = ?";
                $params[] = $USER -> id;
            }
            $timelimit .= ")";
        }
    }

    $sql = "SELECT COUNT(d.id)
              FROM {forumlv_discussions} d
                   JOIN {forumlv_posts} p ON p.discussion = d.id
             WHERE d.forumlv = ? AND p.parent = 0
                   $groupselect $timelimit";

    return $DB -> get_field_sql($sql, $params);
}

/**
 * Get all discussions started by a particular user in a course (or group)
 * This function no longer used ...
 *
 * @todo Remove this function if no longer used
 * @global object
 * @global object
 * @param int $courseid
 * @param int $userid
 * @param int $groupid
 * @return array
 */
function forumlv_get_user_discussions($courseid, $userid, $groupid = 0) {
    global $CFG, $DB;
    $params = array($courseid, $userid);
    if ($groupid) {
        $groupselect = " AND d.groupid = ? ";
        $params[] = $groupid;
    } else {
        $groupselect = "";
    }

    return $DB -> get_records_sql("SELECT p.*, d.groupid, u.firstname, u.lastname, u.email, u.picture, u.imagealt,
                                   f.type as forumlvtype, f.name as forumlvname, f.id as forumlvid
                              FROM {forumlv_discussions} d,
                                   {forumlv_posts} p,
                                   {user} u,
                                   {forumlv} f
                             WHERE d.course = ?
                               AND p.discussion = d.id
                               AND p.parent = 0
                               AND p.userid = u.id
                               AND u.id = ?
                               AND d.forumlv = f.id $groupselect
                          ORDER BY p.created DESC", $params);
}

/**
 * Get the list of potential subscribers to a forumlv.
 *
 * @param object $forumlvcontext the forumlv context.
 * @param integer $groupid the id of a group, or 0 for all groups.
 * @param string $fields the list of fields to return for each user. As for get_users_by_capability.
 * @param string $sort sort order. As for get_users_by_capability.
 * @return array list of users.
 */
function forumlv_get_potential_subscribers($forumlvcontext, $groupid, $fields, $sort = '') {
    global $DB;

    // only active enrolled users or everybody on the frontpage
    list($esql, $params) = get_enrolled_sql($forumlvcontext, 'mod/forumlv:allowforcesubscribe', $groupid, true);
    if (!$sort) {
        list($sort, $sortparams) = users_order_by_sql('u');
        $params = array_merge($params, $sortparams);
    }

    $sql = "SELECT $fields
              FROM {user} u
              JOIN ($esql) je ON je.id = u.id
          ORDER BY $sort";

    return $DB -> get_records_sql($sql, $params);
}

/**
 * Returns list of user objects that are subscribed to this forumlv
 *
 * @global object
 * @global object
 * @param object $course the course
 * @param forumlv $forumlv the forumlv
 * @param integer $groupid group id, or 0 for all.
 * @param object $context the forumlv context, to save re-fetching it where possible.
 * @param string $fields requested user fields (with "u." table prefix)
 * @return array list of users.
 */
function forumlv_subscribed_users($course, $forumlv, $groupid = 0, $context = null, $fields = null) {
    global $CFG, $DB;

    if (empty($fields)) {
        $fields = "u.id,
                  u.username,
                  u.firstname,
                  u.lastname,
                  u.maildisplay,
                  u.mailformat,
                  u.maildigest,
                  u.imagealt,
                  u.email,
                  u.emailstop,
                  u.city,
                  u.country,
                  u.lastaccess,
                  u.lastlogin,
                  u.picture,
                  u.timezone,
                  u.theme,
                  u.lang,
                  u.trackforum,
                  u.mnethostid";
    }

    if (empty($context)) {
        $cm = get_coursemodule_from_instance('forumlv', $forumlv -> id, $course -> id);
        $context = context_module::instance($cm -> id);
    }

    if (forumlv_is_forcesubscribed($forumlv)) {
        $results = forumlv_get_potential_subscribers($context, $groupid, $fields, "u.email ASC");

    } else {
        // only active enrolled users or everybody on the frontpage
        list($esql, $params) = get_enrolled_sql($context, '', $groupid, true);
        $params['forumlvid'] = $forumlv -> id;
        $results = $DB -> get_records_sql("SELECT $fields
                                           FROM {user} u
                                           JOIN ($esql) je ON je.id = u.id
                                           JOIN {forumlv_subscriptions} s ON s.userid = u.id
                                          WHERE s.forumlv = :forumlvid
                                       ORDER BY u.email ASC", $params);
    }

    // Guest user should never be subscribed to a forumlv.
    unset($results[$CFG -> siteguest]);

    return $results;
}

// OTHER FUNCTIONS ///////////////////////////////////////////////////////////

/**
 * @global object
 * @global object
 * @param int $courseid
 * @param string $type
 */
function forumlv_get_course_forumlv($courseid, $type) {
    // How to set up special 1-per-course forumlvs
    global $CFG, $DB, $OUTPUT, $USER;

    if ($forumlvs = $DB -> get_records_select("forumlv", "course = ? AND type = ?", array($courseid, $type), "id ASC")) {
        // There should always only be ONE, but with the right combination of
        // errors there might be more.  In this case, just return the oldest one (lowest ID).
        foreach ($forumlvs as $forumlv) {
            return $forumlv;
            // ie the first one
        }
    }

    // Doesn't exist, so create one now.
    $forumlv = new stdClass();
    $forumlv -> course = $courseid;
    $forumlv -> type = "$type";
    if (!empty($USER -> htmleditor)) {
        $forumlv -> introformat = $USER -> htmleditor;
    }
    switch ($forumlv->type) {
        case "news" :
            $forumlv -> name = get_string("namenews", "forumlv");
            $forumlv -> intro = get_string("intronews", "forumlv");
            $forumlv -> forcesubscribe = FORUMLV_FORCESUBSCRIBE;
            $forumlv -> assessed = 0;
            if ($courseid == SITEID) {
                $forumlv -> name = get_string("sitenews");
                $forumlv -> forcesubscribe = 0;
            }
            break;
        case "social" :
            $forumlv -> name = get_string("namesocial", "forumlv");
            $forumlv -> intro = get_string("introsocial", "forumlv");
            $forumlv -> assessed = 0;
            $forumlv -> forcesubscribe = 0;
            break;
        case "blog" :
            $forumlv -> name = get_string('blogforumlv', 'forumlv');
            $forumlv -> intro = get_string('introblog', 'forumlv');
            $forumlv -> assessed = 0;
            $forumlv -> forcesubscribe = 0;
            break;
        default :
            echo $OUTPUT -> notification("That forumlv type doesn't exist!");
            return false;
            break;
    }

    $forumlv -> timemodified = time();
    $forumlv -> id = $DB -> insert_record("forumlv", $forumlv);

    if (!$module = $DB -> get_record("modules", array("name" => "forumlv"))) {
        echo $OUTPUT -> notification("Could not find forumlv module!!");
        return false;
    }
    $mod = new stdClass();
    $mod -> course = $courseid;
    $mod -> module = $module -> id;
    $mod -> instance = $forumlv -> id;
    $mod -> section = 0;
    include_once ("$CFG->dirroot/course/lib.php");
    if (!$mod -> coursemodule = add_course_module($mod)) {
        echo $OUTPUT -> notification("Could not add a new course module to the course '" . $courseid . "'");
        return false;
    }
    $sectionid = course_add_cm_to_section($courseid, $mod -> coursemodule, 0);
    return $DB -> get_record("forumlv", array("id" => "$forumlv->id"));
}

/**
 * Given the data about a posting, builds up the HTML to display it and
 * returns the HTML in a string.  This is designed for sending via HTML email.
 *
 * @global object
 * @param object $course
 * @param object $cm
 * @param object $forumlv
 * @param object $discussion
 * @param object $post
 * @param object $userform
 * @param object $userto
 * @param bool $ownpost
 * @param bool $reply
 * @param bool $link
 * @param bool $rate
 * @param string $footer
 * @return string
 */
function forumlv_make_mail_post($course, $cm, $forumlv, $discussion, $post, $userfrom, $userto, $ownpost = false, $reply = false, $link = false, $rate = false, $footer = "") {

    global $CFG, $OUTPUT;

    $modcontext = context_module::instance($cm -> id);

    if (!isset($userto -> viewfullnames[$forumlv -> id])) {
        $viewfullnames = has_capability('moodle/site:viewfullnames', $modcontext, $userto -> id);
    } else {
        $viewfullnames = $userto -> viewfullnames[$forumlv -> id];
    }

    // add absolute file links
    $post -> message = file_rewrite_pluginfile_urls($post -> message, 'pluginfile.php', $modcontext -> id, 'mod_forumlv', 'post', $post -> id);

    // format the post body
    $options = new stdClass();
    $options -> para = true;
    $formattedtext = format_text($post -> message, $post -> messageformat, $options, $course -> id);

    $output = '<table border="0" cellpadding="3" cellspacing="0" class="forumlvpost">';

    $output .= '<tr class="header"><td width="35" valign="top" class="picture left">';
    $output .= $OUTPUT -> user_picture($userfrom, array('courseid' => $course -> id));
    $output .= '</td>';

    if ($post -> parent) {
        $output .= '<td class="topic">';
    } else {
        $output .= '<td class="topic starter">';
    }
    $output .= '<div class="subject">' . format_string($post -> subject) . '</div>';

    $fullname = fullname($userfrom, $viewfullnames);
    $by = new stdClass();
    $by -> name = '<a href="' . $CFG -> wwwroot . '/user/view.php?id=' . $userfrom -> id . '&amp;course=' . $course -> id . '">' . $fullname . '</a>';
    $by -> date = userdate($post -> modified, '', $userto -> timezone);
    $output .= '<div class="author">' . get_string('bynameondate', 'forumlv', $by) . '</div>';

    $output .= '</td></tr>';

    $output .= '<tr><td class="left side" valign="top">';

    if (isset($userfrom -> groups)) {
        $groups = $userfrom -> groups[$forumlv -> id];
    } else {
        $groups = groups_get_all_groups($course -> id, $userfrom -> id, $cm -> groupingid);
    }

    if ($groups) {
        $output .= print_group_picture($groups, $course -> id, false, true, true);
    } else {
        $output .= '&nbsp;';
    }

    $output .= '</td><td class="content">';

    $attachments = forumlv_print_attachments($post, $cm, 'html');
    if ($attachments !== '') {
        $output .= '<div class="attachments">';
        $output .= $attachments;
        $output .= '</div>';
    }

    $output .= $formattedtext;

    // Commands
    $commands = array();

    if ($post -> parent) {
        $commands[] = '<a target="_blank" href="' . $CFG -> wwwroot . '/mod/forumlv/discuss.php?d=' . $post -> discussion . '&amp;parent=' . $post -> parent . '">' . get_string('parent', 'forumlv') . '</a>';
    }

    if ($reply) {
        $commands[] = '<a target="_blank" href="' . $CFG -> wwwroot . '/mod/forumlv/post.php?reply=' . $post -> id . '">' . get_string('reply', 'forumlv') . '</a>';
    }

    $output .= '<div class="commands">';
    $output .= implode(' | ', $commands);
    $output .= '</div>';

    // Context link to post if required
    if ($link) {
        $output .= '<div class="link">';
        $output .= '<a target="_blank" href="' . $CFG -> wwwroot . '/mod/forumlv/discuss.php?d=' . $post -> discussion . '#p' . $post -> id . '">' . get_string('postincontext', 'forumlv') . '</a>';
        $output .= '</div>';
    }

    if ($footer) {
        $output .= '<div class="footer">' . $footer . '</div>';
    }
    $output .= '</td></tr></table>' . "\n\n";

    return $output;
}

/**
 * Print a forumlv post
 *
 * @global object
 * @global object
 * @uses FORUMLV_MODE_THREADED
 * @uses PORTFOLIO_FORMAT_PLAINHTML
 * @uses PORTFOLIO_FORMAT_FILE
 * @uses PORTFOLIO_FORMAT_RICHHTML
 * @uses PORTFOLIO_ADD_TEXT_LINK
 * @uses CONTEXT_MODULE
 * @param object $post The post to print.
 * @param object $discussion
 * @param object $forumlv
 * @param object $cm
 * @param object $course
 * @param boolean $ownpost Whether this post belongs to the current user.
 * @param boolean $reply Whether to print a 'reply' link at the bottom of the message.
 * @param boolean $link Just print a shortened version of the post as a link to the full post.
 * @param string $footer Extra stuff to print after the message.
 * @param string $highlight Space-separated list of terms to highlight.
 * @param int $post_read true, false or -99. If we already know whether this user
 *          has read this post, pass that in, otherwise, pass in -99, and this
 *          function will work it out.
 * @param boolean $dummyifcantsee When forumlv_user_can_see_post says that
 *          the current user can't see this post, if this argument is true
 *          (the default) then print a dummy 'you can't see this post' post.
 *          If false, don't output anything at all.
 * @param bool|null $istracked
 * @return void
 */
function forumlv_print_post($post, $discussion, $forumlv, &$cm, $course, $ownpost = false, $reply = false, $link = false, $footer = "", $highlight = "", $postisread = null, $dummyifcantsee = true, $istracked = null, $return = false) {
    global $USER, $CFG, $OUTPUT;

    require_once ($CFG -> libdir . '/filelib.php');

    // String cache
    static $str;

    $modcontext = context_module::instance($cm -> id);

    $post -> course = $course -> id;
    $post -> forumlv = $forumlv -> id;
    $post -> message = file_rewrite_pluginfile_urls($post -> message, 'pluginfile.php', $modcontext -> id, 'mod_forumlv', 'post', $post -> id);
    if (!empty($CFG -> enableplagiarism)) {
        require_once ($CFG -> libdir . '/plagiarismlib.php');
        $post -> message .= plagiarism_get_links(array('userid' => $post -> userid, 'content' => $post -> message, 'cmid' => $cm -> id, 'course' => $post -> course, 'forumlv' => $post -> forumlv));
    }

    // caching
    if (!isset($cm -> cache)) {
        $cm -> cache = new stdClass;
    }

    if (!isset($cm -> cache -> caps)) {
        $cm -> cache -> caps = array();
        $cm -> cache -> caps['mod/forumlv:viewdiscussion'] = has_capability('mod/forumlv:viewdiscussion', $modcontext);
        $cm -> cache -> caps['moodle/site:viewfullnames'] = has_capability('moodle/site:viewfullnames', $modcontext);
        $cm -> cache -> caps['mod/forumlv:editanypost'] = has_capability('mod/forumlv:editanypost', $modcontext);
        $cm -> cache -> caps['mod/forumlv:splitdiscussions'] = has_capability('mod/forumlv:splitdiscussions', $modcontext);
        $cm -> cache -> caps['mod/forumlv:deleteownpost'] = has_capability('mod/forumlv:deleteownpost', $modcontext);
        $cm -> cache -> caps['mod/forumlv:deleteanypost'] = has_capability('mod/forumlv:deleteanypost', $modcontext);
        $cm -> cache -> caps['mod/forumlv:viewanyrating'] = has_capability('mod/forumlv:viewanyrating', $modcontext);
        $cm -> cache -> caps['mod/forumlv:exportpost'] = has_capability('mod/forumlv:exportpost', $modcontext);
        $cm -> cache -> caps['mod/forumlv:exportownpost'] = has_capability('mod/forumlv:exportownpost', $modcontext);
    }

    if (!isset($cm -> uservisible)) {
        $cm -> uservisible = coursemodule_visible_for_user($cm);
    }

    if ($istracked && is_null($postisread)) {
        $postisread = forumlv_tp_is_post_read($USER -> id, $post);
    }

    if (!forumlv_user_can_see_post($forumlv, $discussion, $post, NULL, $cm)) {
        $output = '';
        if (!$dummyifcantsee) {
            if ($return) {
                return $output;
            }
            echo $output;
            return;
        }
        $output .= html_writer::tag('a', '', array('id' => 'p' . $post -> id));
        $output .= html_writer::start_tag('div', array('class' => 'forumlvpost clearfix'));
        $output .= html_writer::start_tag('div', array('class' => 'row header'));
        $output .= html_writer::tag('div', '', array('class' => 'left picture'));
        // Picture
        if ($post -> parent) {
            $output .= html_writer::start_tag('div', array('class' => 'topic'));
        } else {
            $output .= html_writer::start_tag('div', array('class' => 'topic starter'));
        }
        $output .= html_writer::tag('div', get_string('forumlvsubjecthidden', 'forumlv'), array('class' => 'subject'));
        // Subject
        $output .= html_writer::tag('div', get_string('forumlvauthorhidden', 'forumlv'), array('class' => 'author'));
        // author
        $output .= html_writer::end_tag('div');
        $output .= html_writer::end_tag('div');
        // row
        $output .= html_writer::start_tag('div', array('class' => 'row'));
        $output .= html_writer::tag('div', '&nbsp;', array('class' => 'left side'));
        // Groups
        $output .= html_writer::tag('div', get_string('forumlvbodyhidden', 'forumlv'), array('class' => 'content'));
        // Content
        $output .= html_writer::end_tag('div');
        // row
        $output .= html_writer::end_tag('div');
        // forumlvpost

        if ($return) {
            return $output;
        }
        echo $output;
        return;
    }

    if (empty($str)) {
        $str = new stdClass;
        $str -> edit = get_string('edit', 'forumlv');
        $str -> delete = get_string('delete', 'forumlv');
        $str -> reply = get_string('reply', 'forumlv');
        $str -> parent = get_string('parent', 'forumlv');
        $str -> pruneheading = get_string('pruneheading', 'forumlv');
        $str -> prune = get_string('prune', 'forumlv');
        $str -> displaymode = get_user_preferences('forumlv_displaymode', $CFG -> forumlv_displaymode);
        $str -> markread = get_string('markread', 'forumlv');
        $str -> markunread = get_string('markunread', 'forumlv');
    }

    $discussionlink = new moodle_url('/mod/forumlv/discuss.php', array('d' => $post -> discussion));

    // Build an object that represents the posting user
    $postuser = new stdClass;
    $postuser -> id = $post -> userid;
    $postuser -> firstname = $post -> firstname;
    $postuser -> lastname = $post -> lastname;
    $postuser -> imagealt = $post -> imagealt;
    $postuser -> picture = $post -> picture;
    $postuser -> email = $post -> email;
    // Some handy things for later on
    $postuser -> fullname = fullname($postuser, $cm -> cache -> caps['moodle/site:viewfullnames']);
    $postuser -> profilelink = new moodle_url('/user/view.php', array('id' => $post -> userid, 'course' => $course -> id));

    // Prepare the groups the posting user belongs to
    if (isset($cm -> cache -> usersgroups)) {
        $groups = array();
        if (isset($cm -> cache -> usersgroups[$post -> userid])) {
            foreach ($cm->cache->usersgroups[$post->userid] as $gid) {
                $groups[$gid] = $cm -> cache -> groups[$gid];
            }
        }
    } else {
        $groups = groups_get_all_groups($course -> id, $post -> userid, $cm -> groupingid);
    }

    // Prepare the attachements for the post, files then images
    list($attachments, $attachedimages) = forumlv_print_attachments($post, $cm, 'separateimages');

    // Determine if we need to shorten this post
    $shortenpost = ($link && (strlen(strip_tags($post -> message)) > $CFG -> forumlv_longpost));

    // Prepare an array of commands
    $commands = array();

    // SPECIAL CASE: The front page can display a news item post to non-logged in users.
    // Don't display the mark read / unread controls in this case.
    if ($istracked && $CFG -> forumlv_usermarksread && isloggedin()) {
        $url = new moodle_url($discussionlink, array('postid' => $post -> id, 'mark' => 'unread'));
        $text = $str -> markunread;
        if (!$postisread) {
            $url -> param('mark', 'read');
            $text = $str -> markread;
        }
        if ($str -> displaymode == FORUMLV_MODE_THREADED) {
            $url -> param('parent', $post -> parent);
        } else {
            $url -> set_anchor('p' . $post -> id);
        }
        $commands[] = array('url' => $url, 'text' => $text);
    }

    // Zoom in to the parent specifically
    if ($post -> parent) {
        $url = new moodle_url($discussionlink);
        if ($str -> displaymode == FORUMLV_MODE_THREADED) {
            $url -> param('parent', $post -> parent);
        } else {
            $url -> set_anchor('p' . $post -> parent);
        }
        $commands[] = array('url' => $url, 'text' => $str -> parent);
    }

    // Hack for allow to edit news posts those are not displayed yet until they are displayed
    $age = time() - $post -> created;
    if (!$post -> parent && $forumlv -> type == 'news' && $discussion -> timestart > time()) {
        $age = 0;
    }

    if ($forumlv -> type == 'single' and $discussion -> firstpost == $post -> id) {
        if (has_capability('moodle/course:manageactivities', $modcontext)) {
            // The first post in single simple is the forumlv description.
            $commands[] = array('url' => new moodle_url('/course/modedit.php', array('update' => $cm -> id, 'sesskey' => sesskey(), 'return' => 1)), 'text' => $str -> edit);
        }
    } else if (($ownpost && $age < $CFG -> maxeditingtime) || $cm -> cache -> caps['mod/forumlv:editanypost']) {
        $commands[] = array('url' => new moodle_url('/mod/forumlv/post.php', array('edit' => $post -> id)), 'text' => $str -> edit);
    }

    if ($cm -> cache -> caps['mod/forumlv:splitdiscussions'] && $post -> parent && $forumlv -> type != 'single') {
        $commands[] = array('url' => new moodle_url('/mod/forumlv/post.php', array('prune' => $post -> id)), 'text' => $str -> prune, 'title' => $str -> pruneheading);
    }

    if ($forumlv -> type == 'single' and $discussion -> firstpost == $post -> id) {
        // Do not allow deleting of first post in single simple type.
    } else if (($ownpost && $age < $CFG -> maxeditingtime && $cm -> cache -> caps['mod/forumlv:deleteownpost']) || $cm -> cache -> caps['mod/forumlv:deleteanypost']) {
        $commands[] = array('url' => new moodle_url('/mod/forumlv/post.php', array('delete' => $post -> id)), 'text' => $str -> delete);
    }

    if ($reply) {
        $commands[] = array('url' => new moodle_url('/mod/forumlv/post.php#mformforumlv', array('reply' => $post -> id)), 'text' => $str -> reply);
    }

    if ($CFG -> enableportfolios && ($cm -> cache -> caps['mod/forumlv:exportpost'] || ($ownpost && $cm -> cache -> caps['mod/forumlv:exportownpost']))) {
        $p = array('postid' => $post -> id);
        require_once ($CFG -> libdir . '/portfoliolib.php');
        $button = new portfolio_add_button();
        $button -> set_callback_options('forumlv_portfolio_caller', array('postid' => $post -> id), 'mod_forumlv');
        if (empty($attachments)) {
            $button -> set_formats(PORTFOLIO_FORMAT_PLAINHTML);
        } else {
            $button -> set_formats(PORTFOLIO_FORMAT_RICHHTML);
        }

        $porfoliohtml = $button -> to_html(PORTFOLIO_ADD_TEXT_LINK);
        if (!empty($porfoliohtml)) {
            $commands[] = $porfoliohtml;
        }
    }
    // Finished building commands

    // Begin output

    $output = '';

    if ($istracked) {
        if ($postisread) {
            $forumlvpostclass = ' read';
        } else {
            $forumlvpostclass = ' unread';
            $output .= html_writer::tag('a', '', array('name' => 'unread'));
        }
    } else {
        // ignore trackign status if not tracked or tracked param missing
        $forumlvpostclass = '';
    }

    $topicclass = '';
    if (empty($post -> parent)) {
        $topicclass = ' firstpost starter';
    }

    $output .= html_writer::tag('a', '', array('id' => 'p' . $post -> id));
    $output .= html_writer::start_tag('div', array('class' => 'forumlvpost clearfix' . $forumlvpostclass . $topicclass));
    $output .= html_writer::start_tag('div', array('class' => 'row header clearfix'));
    $output .= html_writer::start_tag('div', array('class' => 'left picture'));
    $output .= $OUTPUT -> user_picture($postuser, array('courseid' => $course -> id));
    $output .= html_writer::end_tag('div');

    $output .= html_writer::start_tag('div', array('class' => 'topic' . $topicclass));

    $postsubject = $post -> subject;
    if (empty($post -> subjectnoformat)) {
        $postsubject = format_string($postsubject);
    }
    $output .= html_writer::tag('div', $postsubject, array('class' => 'subject'));

    $by = new stdClass();
    $by -> name = html_writer::link($postuser -> profilelink, $postuser -> fullname);
    $by -> date = userdate($post -> modified);
    $output .= html_writer::tag('div', get_string('bynameondate', 'forumlv', $by), array('class' => 'author'));

    $output .= html_writer::end_tag('div');
    //topic
    $output .= html_writer::end_tag('div');
    //row

    $output .= html_writer::start_tag('div', array('class' => 'row maincontent clearfix'));
    $output .= html_writer::start_tag('div', array('class' => 'left'));

    $groupoutput = '';
    if ($groups) {
        $groupoutput = print_group_picture($groups, $course -> id, false, true, true);
    }
    if (empty($groupoutput)) {
        $groupoutput = '&nbsp;';
    }
    $output .= html_writer::tag('div', $groupoutput, array('class' => 'grouppictures'));

    $output .= html_writer::end_tag('div');
    //left side
    $output .= html_writer::start_tag('div', array('class' => 'no-overflow'));
    $output .= html_writer::start_tag('div', array('class' => 'content'));
    if (!empty($attachments)) {
        $output .= html_writer::tag('div', $attachments, array('class' => 'attachments'));
    }

    $options = new stdClass;
    $options -> para = false;
    $options -> trusted = $post -> messagetrust;
    $options -> context = $modcontext;
    if ($shortenpost) {
        // Prepare shortened version
        $postclass = 'shortenedpost';
        $postcontent = format_text(forumlv_shorten_post($post -> message), $post -> messageformat, $options, $course -> id);
        $postcontent .= html_writer::link($discussionlink, get_string('readtherest', 'forumlv'));
        $postcontent .= html_writer::tag('div', '(' . get_string('numwords', 'moodle', count_words($post -> message)) . ')', array('class' => 'post-word-count'));
    } else {
        // Prepare whole post
        $postclass = 'fullpost';
        $postcontent = format_text($post -> message, $post -> messageformat, $options, $course -> id);
        if (!empty($highlight)) {
            $postcontent = highlight($highlight, $postcontent);
        }
        if (!empty($forumlv -> displaywordcount)) {
            $postcontent .= html_writer::tag('div', get_string('numwords', 'moodle', count_words($post -> message)), array('class' => 'post-word-count'));
        }
        $postcontent .= html_writer::tag('div', $attachedimages, array('class' => 'attachedimages'));
    }

    // Output the post content
    $output .= html_writer::tag('div', $postcontent, array('class' => 'posting ' . $postclass));
    $output .= html_writer::end_tag('div');
    // Content
    $output .= html_writer::end_tag('div');
    // Content mask
    $output .= html_writer::end_tag('div');
    // Row

    $output .= html_writer::start_tag('div', array('class' => 'row side'));
    $output .= html_writer::tag('div', '&nbsp;', array('class' => 'left'));
    $output .= html_writer::start_tag('div', array('class' => 'options clearfix'));

    // Output ratings
    /*if (!empty($post->rating)) {
     //@lvs Adicionar escala LV
     $output .= html_writer::tag('div', $OUTPUT->render($post->rating), array('class'=>'forumlv-post-rating'));
     $output = $output . '<<<----';
     }*/

    /** @lvs exibe o form de avaliação no fórumlv */
    if (isset($post -> itemlv)) {
        $gerenciadorNotas = NotasLvFactory::criarGerenciador('moodle2');
        $gerenciadorNotas -> setModulo(new Forumlv($forumlv -> id));
        $lvs_output = $gerenciadorNotas -> avaliacaoAtual($post -> itemlv) . $gerenciadorNotas -> avaliadoPor($post -> itemlv) . $gerenciadorNotas -> formAvaliacaoAjax($post -> itemlv);
        $output .= html_writer::tag('div', $lvs_output, array('class' => 'forumlv-post-rating'));
    }
    /* fim de lvs */

    // Output the commands
    $commandhtml = array();
    foreach ($commands as $command) {
        if (is_array($command)) {
            $commandhtml[] = html_writer::link($command['url'], $command['text']);
        } else {
            $commandhtml[] = $command;
        }
    }
    $output .= html_writer::tag('div', implode(' | ', $commandhtml), array('class' => 'commands'));

    // Output link to post if required
    if ($link) {
        if ($post -> replies == 1) {
            $replystring = get_string('repliesone', 'forumlv', $post -> replies);
        } else {
            $replystring = get_string('repliesmany', 'forumlv', $post -> replies);
        }

        $output .= html_writer::start_tag('div', array('class' => 'link'));
        $output .= html_writer::link($discussionlink, get_string('discussthistopic', 'forumlv'));
        $output .= '&nbsp;(' . $replystring . ')';
        $output .= html_writer::end_tag('div');
        // link
    }

    // Output footer if required
    if ($footer) {
        $output .= html_writer::tag('div', $footer, array('class' => 'footer'));
    }

    // Close remaining open divs
    $output .= html_writer::end_tag('div');
    // content
    $output .= html_writer::end_tag('div');
    // row
    $output .= html_writer::end_tag('div');
    // forumlvpost

    // Mark the forumlv post as read if required
    if ($istracked && !$CFG -> forumlv_usermarksread && !$postisread) {
        forumlv_tp_mark_post_read($USER -> id, $post, $forumlv -> id);
    }

    if ($return) {
        return $output;
    }
    echo $output;
    return;
}

/**
 * Return rating related permissions
 *
 * @param string $options the context id
 * @return array an associative array of the user's rating permissions
 */
function forumlv_rating_permissions($contextid, $component, $ratingarea) {
    $context = context::instance_by_id($contextid, MUST_EXIST);
    if ($component != 'mod_forumlv' || $ratingarea != 'post') {
        // We don't know about this component/ratingarea so just return null to get the
        // default restrictive permissions.
        return null;
    }
    return array('view' => has_capability('mod/forumlv:viewrating', $context), 'viewany' => has_capability('mod/forumlv:viewanyrating', $context), 'viewall' => has_capability('mod/forumlv:viewallratings', $context), 'rate' => has_capability('mod/forumlv:rate', $context));
}

/**
 * Validates a submitted rating
 * @param array $params submitted data
 *            context => object the context in which the rated items exists [required]
 *            component => The component for this module - should always be mod_forumlv [required]
 *            ratingarea => object the context in which the rated items exists [required]
 *            itemid => int the ID of the object being rated [required]
 *            scaleid => int the scale from which the user can select a rating. Used for bounds checking. [required]
 *            rating => int the submitted rating [required]
 *            rateduserid => int the id of the user whose items have been rated. NOT the user who submitted the ratings. 0 to update all. [required]
 *            aggregation => int the aggregation method to apply when calculating grades ie RATING_AGGREGATE_AVERAGE [required]
 * @return boolean true if the rating is valid. Will throw rating_exception if not
 */
function forumlv_rating_validate($params) {
    global $DB, $USER;

    // Check the component is mod_forumlv
    if ($params['component'] != 'mod_forumlv') {
        throw new rating_exception('invalidcomponent');
    }

    // Check the ratingarea is post (the only rating area in forumlv)
    if ($params['ratingarea'] != 'post') {
        throw new rating_exception('invalidratingarea');
    }

    // Check the rateduserid is not the current user .. you can't rate your own posts
    if ($params['rateduserid'] == $USER -> id) {
        throw new rating_exception('nopermissiontorate');
    }

    // Fetch all the related records ... we need to do this anyway to call forumlv_user_can_see_post
    $post = $DB -> get_record('forumlv_posts', array('id' => $params['itemid'], 'userid' => $params['rateduserid']), '*', MUST_EXIST);
    $discussion = $DB -> get_record('forumlv_discussions', array('id' => $post -> discussion), '*', MUST_EXIST);
    $forumlv = $DB -> get_record('forumlv', array('id' => $discussion -> forumlv), '*', MUST_EXIST);
    $course = $DB -> get_record('course', array('id' => $forumlv -> course), '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance('forumlv', $forumlv -> id, $course -> id, false, MUST_EXIST);
    $context = context_module::instance($cm -> id);

    // Make sure the context provided is the context of the forumlv
    if ($context -> id != $params['context'] -> id) {
        throw new rating_exception('invalidcontext');
    }

    if ($forumlv -> scale != $params['scaleid']) {
        //the scale being submitted doesnt match the one in the database
        throw new rating_exception('invalidscaleid');
    }

    // check the item we're rating was created in the assessable time window
    if (!empty($forumlv -> assesstimestart) && !empty($forumlv -> assesstimefinish)) {
        if ($post -> created < $forumlv -> assesstimestart || $post -> created > $forumlv -> assesstimefinish) {
            throw new rating_exception('notavailable');
        }
    }

    //check that the submitted rating is valid for the scale

    // lower limit
    if ($params['rating'] < 0 && $params['rating'] != RATING_UNSET_RATING) {
        throw new rating_exception('invalidnum');
    }

    // upper limit
    if ($forumlv -> scale < 0) {
        //its a custom scale
        $scalerecord = $DB -> get_record('scale', array('id' => -$forumlv -> scale));
        if ($scalerecord) {
            $scalearray = explode(',', $scalerecord -> scale);
            if ($params['rating'] > count($scalearray)) {
                throw new rating_exception('invalidnum');
            }
        } else {
            throw new rating_exception('invalidscaleid');
        }
    } else if ($params['rating'] > $forumlv -> scale) {
        //if its numeric and submitted rating is above maximum
        throw new rating_exception('invalidnum');
    }

    // Make sure groups allow this user to see the item they're rating
    if ($discussion -> groupid > 0 and $groupmode = groups_get_activity_groupmode($cm, $course)) {// Groups are being used
        if (!groups_group_exists($discussion -> groupid)) {// Can't find group
            throw new rating_exception('cannotfindgroup');
            //something is wrong
        }

        if (!groups_is_member($discussion -> groupid) and !has_capability('moodle/site:accessallgroups', $context)) {
            // do not allow rating of posts from other groups when in SEPARATEGROUPS or VISIBLEGROUPS
            throw new rating_exception('notmemberofgroup');
        }
    }

    // perform some final capability checks
    if (!forumlv_user_can_see_post($forumlv, $discussion, $post, $USER, $cm)) {
        throw new rating_exception('nopermissiontorate');
    }

    return true;
}

/**
 * This function prints the overview of a discussion in the forumlv listing.
 * It needs some discussion information and some post information, these
 * happen to be combined for efficiency in the $post parameter by the function
 * that calls this one: forumlv_print_latest_discussions()
 *
 * @global object
 * @global object
 * @param object $post The post object (passed by reference for speed).
 * @param object $forumlv The forumlv object.
 * @param int $group Current group.
 * @param string $datestring Format to use for the dates.
 * @param boolean $cantrack Is tracking enabled for this forumlv.
 * @param boolean $forumlvtracked Is the user tracking this forumlv.
 * @param boolean $canviewparticipants True if user has the viewparticipants permission for this course
 */
function forumlv_print_discussion_header(&$post, $forumlv, $group = -1, $datestring = "", $cantrack = true, $forumlvtracked = true, $canviewparticipants = true, $modcontext = NULL) {

    global $USER, $CFG, $OUTPUT;

    static $rowcount;
    static $strmarkalldread;

    if (empty($modcontext)) {
        if (!$cm = get_coursemodule_from_instance('forumlv', $forumlv -> id, $forumlv -> course)) {
            print_error('invalidcoursemodule');
        }
        $modcontext = context_module::instance($cm -> id);
    }

    if (!isset($rowcount)) {
        $rowcount = 0;
        $strmarkalldread = get_string('markalldread', 'forumlv');
    } else {
        $rowcount = ($rowcount + 1) % 2;
    }

    $post -> subject = format_string($post -> subject, true);

    echo "\n\n";
    echo '<tr class="discussion r' . $rowcount . '">';

    // Topic
    echo '<td class="topic starter">';
    echo '<a href="' . $CFG -> wwwroot . '/mod/forumlv/discuss.php?d=' . $post -> discussion . '">' . $post -> subject . '</a>';
    echo "</td>\n";

    // Picture
    $postuser = new stdClass();
    $postuser -> id = $post -> userid;
    $postuser -> firstname = $post -> firstname;
    $postuser -> lastname = $post -> lastname;
    $postuser -> imagealt = $post -> imagealt;
    $postuser -> picture = $post -> picture;
    $postuser -> email = $post -> email;

    echo '<td class="picture">';
    echo $OUTPUT -> user_picture($postuser, array('courseid' => $forumlv -> course));
    echo "</td>\n";

    // User name
    $fullname = fullname($post, has_capability('moodle/site:viewfullnames', $modcontext));
    echo '<td class="author">';
    echo '<a href="' . $CFG -> wwwroot . '/user/view.php?id=' . $post -> userid . '&amp;course=' . $forumlv -> course . '">' . $fullname . '</a>';
    echo "</td>\n";

    // Group picture
    if ($group !== -1) {// Groups are active - group is a group data object or NULL
        echo '<td class="picture group">';
        if (!empty($group -> picture) and empty($group -> hidepicture)) {
            print_group_picture($group, $forumlv -> course, false, false, true);
        } else if (isset($group -> id)) {
            if ($canviewparticipants) {
                echo '<a href="' . $CFG -> wwwroot . '/user/index.php?id=' . $forumlv -> course . '&amp;group=' . $group -> id . '">' . $group -> name . '</a>';
            } else {
                echo $group -> name;
            }
        }
        echo "</td>\n";
    }

    if (has_capability('mod/forumlv:viewdiscussion', $modcontext)) {// Show the column with replies
        echo '<td class="replies">';
        echo '<a href="' . $CFG -> wwwroot . '/mod/forumlv/discuss.php?d=' . $post -> discussion . '">';
        echo $post -> replies . '</a>';
        echo "</td>\n";

        if ($cantrack) {
            echo '<td class="replies">';
            if ($forumlvtracked) {
                if ($post -> unread > 0) {
                    echo '<span class="unread">';
                    echo '<a href="' . $CFG -> wwwroot . '/mod/forumlv/discuss.php?d=' . $post -> discussion . '#unread">';
                    echo $post -> unread;
                    echo '</a>';
                    echo '<a title="' . $strmarkalldread . '" href="' . $CFG -> wwwroot . '/mod/forumlv/markposts.php?f=' . $forumlv -> id . '&amp;d=' . $post -> discussion . '&amp;mark=read&amp;returnpage=view.php">' . '<img src="' . $OUTPUT -> pix_url('t/markasread') . '" class="iconsmall" alt="' . $strmarkalldread . '" /></a>';
                    echo '</span>';
                } else {
                    echo '<span class="read">';
                    echo $post -> unread;
                    echo '</span>';
                }
            } else {
                echo '<span class="read">';
                echo '-';
                echo '</span>';
            }
            echo "</td>\n";
        }
    }

    echo '<td class="lastpost">';
    $usedate = (empty($post -> timemodified)) ? $post -> modified : $post -> timemodified;
    // Just in case
    $parenturl = (empty($post -> lastpostid)) ? '' : '&amp;parent=' . $post -> lastpostid;
    $usermodified = new stdClass();
    $usermodified -> id = $post -> usermodified;
    $usermodified -> firstname = $post -> umfirstname;
    $usermodified -> lastname = $post -> umlastname;
    echo '<a href="' . $CFG -> wwwroot . '/user/view.php?id=' . $post -> usermodified . '&amp;course=' . $forumlv -> course . '">' . fullname($usermodified) . '</a><br />';
    echo '<a href="' . $CFG -> wwwroot . '/mod/forumlv/discuss.php?d=' . $post -> discussion . $parenturl . '">' . userdate($usedate, $datestring) . '</a>';
    echo "</td>\n";

    echo "</tr>\n\n";

}

/**
 * Given a post object that we already know has a long message
 * this function truncates the message nicely to the first
 * sane place between $CFG->forumlv_longpost and $CFG->forumlv_shortpost
 *
 * @global object
 * @param string $message
 * @return string
 */
function forumlv_shorten_post($message) {

    global $CFG;

    $i = 0;
    $tag = false;
    $length = strlen($message);
    $count = 0;
    $stopzone = false;
    $truncate = 0;

    for ($i = 0; $i < $length; $i++) {
        $char = $message[$i];

        switch ($char) {
            case "<" :
                $tag = true;
                break;
            case ">" :
                $tag = false;
                break;
            default :
                if (!$tag) {
                    if ($stopzone) {
                        if ($char == ".") {
                            $truncate = $i + 1;
                            break 2;
                        }
                    }
                    $count++;
                }
                break;
        }
        if (!$stopzone) {
            if ($count > $CFG -> forumlv_shortpost) {
                $stopzone = true;
            }
        }
    }

    if (!$truncate) {
        $truncate = $i;
    }

    return substr($message, 0, $truncate);
}

/**
 * Print the drop down that allows the user to select how they want to have
 * the discussion displayed.
 *
 * @param int $id forumlv id if $forumlvtype is 'single',
 *              discussion id for any other forumlv type
 * @param mixed $mode forumlv layout mode
 * @param string $forumlvtype optional
 */
function forumlv_print_mode_form($id, $mode, $forumlvtype = '') {
    global $OUTPUT;
    if ($forumlvtype == 'single') {
        $select = new single_select(new moodle_url("/mod/forumlv/view.php", array('f' => $id)), 'mode', forumlv_get_layout_modes(), $mode, null, "mode");
        $select -> set_label(get_string('displaymode', 'forumlv'), array('class' => 'accesshide'));
        $select -> class = "forumlvmode";
    } else {
        $select = new single_select(new moodle_url("/mod/forumlv/discuss.php", array('d' => $id)), 'mode', forumlv_get_layout_modes(), $mode, null, "mode");
        $select -> set_label(get_string('displaymode', 'forumlv'), array('class' => 'accesshide'));
    }
    echo $OUTPUT -> render($select);
}

/**
 * @global object
 * @param object $course
 * @param string $search
 * @return string
 */
function forumlv_search_form($course, $search = '') {
    global $CFG, $OUTPUT;

    $output = '<div class="forumlvsearch">';
    $output .= '<form action="' . $CFG -> wwwroot . '/mod/forumlv/search.php" style="display:inline">';
    $output .= '<fieldset class="invisiblefieldset">';
    $output .= $OUTPUT -> help_icon('search');
    $output .= '<label class="accesshide" for="search" >' . get_string('search', 'forumlv') . '</label>';
    $output .= '<input id="search" name="search" type="text" size="18" value="' . s($search, true) . '" alt="search" />';
    $output .= '<label class="accesshide" for="searchforumlvs" >' . get_string('searchforumlvs', 'forumlv') . '</label>';
    $output .= '<input id="searchforumlvs" value="' . get_string('searchforumlvs', 'forumlv') . '" type="submit" />';
    $output .= '<input name="id" type="hidden" value="' . $course -> id . '" />';
    $output .= '</fieldset>';
    $output .= '</form>';
    $output .= '</div>';

    return $output;
}

/**
 * @global object
 * @global object
 */
function forumlv_set_return() {
    global $CFG, $SESSION;

    if (!isset($SESSION -> fromdiscussion)) {
        if (!empty($_SERVER['HTTP_REFERER'])) {
            $referer = $_SERVER['HTTP_REFERER'];
        } else {
            $referer = "";
        }
        // If the referer is NOT a login screen then save it.
        if (!strncasecmp("$CFG->wwwroot/login", $referer, 300)) {
            $SESSION -> fromdiscussion = $_SERVER["HTTP_REFERER"];
        }
    }
}

/**
 * @global object
 * @param string $default
 * @return string
 */
function forumlv_go_back_to($default) {
    global $SESSION;

    if (!empty($SESSION -> fromdiscussion)) {
        $returnto = $SESSION -> fromdiscussion;
        unset($SESSION -> fromdiscussion);
        return $returnto;
    } else {
        return $default;
    }
}

/**
 * Given a discussion object that is being moved to $forumlvto,
 * this function checks all posts in that discussion
 * for attachments, and if any are found, these are
 * moved to the new forumlv directory.
 *
 * @global object
 * @param object $discussion
 * @param int $forumlvfrom source forumlv id
 * @param int $forumlvto target forumlv id
 * @return bool success
 */
function forumlv_move_attachments($discussion, $forumlvfrom, $forumlvto) {
    global $DB;

    $fs = get_file_storage();

    $newcm = get_coursemodule_from_instance('forumlv', $forumlvto);
    $oldcm = get_coursemodule_from_instance('forumlv', $forumlvfrom);

    $newcontext = context_module::instance($newcm -> id);
    $oldcontext = context_module::instance($oldcm -> id);

    // loop through all posts, better not use attachment flag ;-)
    if ($posts = $DB -> get_records('forumlv_posts', array('discussion' => $discussion -> id), '', 'id, attachment')) {
        foreach ($posts as $post) {
            $fs -> move_area_files_to_new_context($oldcontext -> id, $newcontext -> id, 'mod_forumlv', 'post', $post -> id);
            $attachmentsmoved = $fs -> move_area_files_to_new_context($oldcontext -> id, $newcontext -> id, 'mod_forumlv', 'attachment', $post -> id);
            if ($attachmentsmoved > 0 && $post -> attachment != '1') {
                // Weird - let's fix it
                $post -> attachment = '1';
                $DB -> update_record('forumlv_posts', $post);
            } else if ($attachmentsmoved == 0 && $post -> attachment != '') {
                // Weird - let's fix it
                $post -> attachment = '';
                $DB -> update_record('forumlv_posts', $post);
            }
        }
    }

    return true;
}

/**
 * Returns attachments as formated text/html optionally with separate images
 *
 * @global object
 * @global object
 * @global object
 * @param object $post
 * @param object $cm
 * @param string $type html/text/separateimages
 * @return mixed string or array of (html text withouth images and image HTML)
 */
function forumlv_print_attachments($post, $cm, $type) {
    global $CFG, $DB, $USER, $OUTPUT;

    if (empty($post -> attachment)) {
        return $type !== 'separateimages' ? '' : array('', '');
    }

    if (!in_array($type, array('separateimages', 'html', 'text'))) {
        return $type !== 'separateimages' ? '' : array('', '');
    }

    if (!$context = context_module::instance($cm -> id)) {
        return $type !== 'separateimages' ? '' : array('', '');
    }
    $strattachment = get_string('attachment', 'forumlv');

    $fs = get_file_storage();

    $imagereturn = '';
    $output = '';

    $canexport = !empty($CFG -> enableportfolios) && (has_capability('mod/forumlv:exportpost', $context) || ($post -> userid == $USER -> id && has_capability('mod/forumlv:exportownpost', $context)));

    if ($canexport) {
        require_once ($CFG -> libdir . '/portfoliolib.php');
    }

    $files = $fs -> get_area_files($context -> id, 'mod_forumlv', 'attachment', $post -> id, "timemodified", false);
    if ($files) {
        if ($canexport) {
            $button = new portfolio_add_button();
        }
        foreach ($files as $file) {
            $filename = $file -> get_filename();
            $mimetype = $file -> get_mimetype();
            $iconimage = $OUTPUT -> pix_icon(file_file_icon($file), get_mimetype_description($file), 'moodle', array('class' => 'icon'));
            $path = file_encode_url($CFG -> wwwroot . '/pluginfile.php', '/' . $context -> id . '/mod_forumlv/attachment/' . $post -> id . '/' . $filename);

            if ($type == 'html') {
                $output .= "<a href=\"$path\">$iconimage</a> ";
                $output .= "<a href=\"$path\">" . s($filename) . "</a>";
                if ($canexport) {
                    $button -> set_callback_options('forumlv_portfolio_caller', array('postid' => $post -> id, 'attachment' => $file -> get_id()), 'mod_forumlv');
                    $button -> set_format_by_file($file);
                    $output .= $button -> to_html(PORTFOLIO_ADD_ICON_LINK);
                }
                $output .= "<br />";

            } else if ($type == 'text') {
                $output .= "$strattachment " . s($filename) . ":\n$path\n";

            } else {//'returnimages'
                if (in_array($mimetype, array('image/gif', 'image/jpeg', 'image/png'))) {
                    // Image attachments don't get printed as links
                    $imagereturn .= "<br /><img src=\"$path\" alt=\"\" />";
                    if ($canexport) {
                        $button -> set_callback_options('forumlv_portfolio_caller', array('postid' => $post -> id, 'attachment' => $file -> get_id()), 'mod_forumlv');
                        $button -> set_format_by_file($file);
                        $imagereturn .= $button -> to_html(PORTFOLIO_ADD_ICON_LINK);
                    }
                } else {
                    $output .= "<a href=\"$path\">$iconimage</a> ";
                    $output .= format_text("<a href=\"$path\">" . s($filename) . "</a>", FORMAT_HTML, array('context' => $context));
                    if ($canexport) {
                        $button -> set_callback_options('forumlv_portfolio_caller', array('postid' => $post -> id, 'attachment' => $file -> get_id()), 'mod_forumlv');
                        $button -> set_format_by_file($file);
                        $output .= $button -> to_html(PORTFOLIO_ADD_ICON_LINK);
                    }
                    $output .= '<br />';
                }
            }

            if (!empty($CFG -> enableplagiarism)) {
                require_once ($CFG -> libdir . '/plagiarismlib.php');
                $output .= plagiarism_get_links(array('userid' => $post -> userid, 'file' => $file, 'cmid' => $cm -> id, 'course' => $post -> course, 'forumlv' => $post -> forumlv));
                $output .= '<br />';
            }
        }
    }

    if ($type !== 'separateimages') {
        return $output;

    } else {
        return array($output, $imagereturn);
    }
}

////////////////////////////////////////////////////////////////////////////////
// File API                                                                   //
////////////////////////////////////////////////////////////////////////////////

/**
 * Lists all browsable file areas
 *
 * @package  mod_forumlv
 * @category files
 * @param stdClass $course course object
 * @param stdClass $cm course module object
 * @param stdClass $context context object
 * @return array
 */
function forumlv_get_file_areas($course, $cm, $context) {
    return array('attachment' => get_string('areaattachment', 'mod_forumlv'), 'post' => get_string('areapost', 'mod_forumlv'), );
}

/**
 * File browsing support for forumlv module.
 *
 * @package  mod_forumlv
 * @category files
 * @param stdClass $browser file browser object
 * @param stdClass $areas file areas
 * @param stdClass $course course object
 * @param stdClass $cm course module
 * @param stdClass $context context module
 * @param string $filearea file area
 * @param int $itemid item ID
 * @param string $filepath file path
 * @param string $filename file name
 * @return file_info instance or null if not found
 */
function forumlv_get_file_info($browser, $areas, $course, $cm, $context, $filearea, $itemid, $filepath, $filename) {
    global $CFG, $DB, $USER;

    if ($context -> contextlevel != CONTEXT_MODULE) {
        return null;
    }

    // filearea must contain a real area
    if (!isset($areas[$filearea])) {
        return null;
    }

    // Note that forumlv_user_can_see_post() additionally allows access for parent roles
    // and it explicitly checks qanda forumlv type, too. One day, when we stop requiring
    // course:managefiles, we will need to extend this.
    if (!has_capability('mod/forumlv:viewdiscussion', $context)) {
        return null;
    }

    if (is_null($itemid)) {
        require_once ($CFG -> dirroot . '/mod/forumlv/locallib.php');
        return new forumlv_file_info_container($browser, $course, $cm, $context, $areas, $filearea);
    }

    static $cached = array();
    // $cached will store last retrieved post, discussion and forumlv. To make sure that the cache
    // is cleared between unit tests we check if this is the same session
    if (!isset($cached['sesskey']) || $cached['sesskey'] != sesskey()) {
        $cached = array('sesskey' => sesskey());
    }

    if (isset($cached['post']) && $cached['post'] -> id == $itemid) {
        $post = $cached['post'];
    } else if ($post = $DB -> get_record('forumlv_posts', array('id' => $itemid))) {
        $cached['post'] = $post;
    } else {
        return null;
    }

    if (isset($cached['discussion']) && $cached['discussion'] -> id == $post -> discussion) {
        $discussion = $cached['discussion'];
    } else if ($discussion = $DB -> get_record('forumlv_discussions', array('id' => $post -> discussion))) {
        $cached['discussion'] = $discussion;
    } else {
        return null;
    }

    if (isset($cached['forumlv']) && $cached['forumlv'] -> id == $cm -> instance) {
        $forumlv = $cached['forumlv'];
    } else if ($forumlv = $DB -> get_record('forumlv', array('id' => $cm -> instance))) {
        $cached['forumlv'] = $forumlv;
    } else {
        return null;
    }

    $fs = get_file_storage();
    $filepath = is_null($filepath) ? '/' : $filepath;
    $filename = is_null($filename) ? '.' : $filename;
    if (!($storedfile = $fs -> get_file($context -> id, 'mod_forumlv', $filearea, $itemid, $filepath, $filename))) {
        return null;
    }

    // Checks to see if the user can manage files or is the owner.
    // TODO MDL-33805 - Do not use userid here and move the capability check above.
    if (!has_capability('moodle/course:managefiles', $context) && $storedfile -> get_userid() != $USER -> id) {
        return null;
    }
    // Make sure groups allow this user to see this file
    if ($discussion -> groupid > 0 && !has_capability('moodle/site:accessallgroups', $context)) {
        $groupmode = groups_get_activity_groupmode($cm, $course);
        if ($groupmode == SEPARATEGROUPS && !groups_is_member($discussion -> groupid)) {
            return null;
        }
    }

    // Make sure we're allowed to see it...
    if (!forumlv_user_can_see_post($forumlv, $discussion, $post, NULL, $cm)) {
        return null;
    }

    $urlbase = $CFG -> wwwroot . '/pluginfile.php';
    return new file_info_stored($browser, $context, $storedfile, $urlbase, $itemid, true, true, false, false);
}

/**
 * Serves the forumlv attachments. Implements needed access control ;-)
 *
 * @package  mod_forumlv
 * @category files
 * @param stdClass $course course object
 * @param stdClass $cm course module object
 * @param stdClass $context context object
 * @param string $filearea file area
 * @param array $args extra arguments
 * @param bool $forcedownload whether or not force download
 * @param array $options additional options affecting the file serving
 * @return bool false if file not found, does not return if found - justsend the file
 */
function forumlv_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = array()) {
    global $CFG, $DB;

    if ($context -> contextlevel != CONTEXT_MODULE) {
        return false;
    }

    require_course_login($course, true, $cm);

    $areas = forumlv_get_file_areas($course, $cm, $context);

    // filearea must contain a real area
    if (!isset($areas[$filearea])) {
        return false;
    }

    $postid = (int)array_shift($args);

    if (!$post = $DB -> get_record('forumlv_posts', array('id' => $postid))) {
        return false;
    }

    if (!$discussion = $DB -> get_record('forumlv_discussions', array('id' => $post -> discussion))) {
        return false;
    }

    if (!$forumlv = $DB -> get_record('forumlv', array('id' => $cm -> instance))) {
        return false;
    }

    $fs = get_file_storage();
    $relativepath = implode('/', $args);
    $fullpath = "/$context->id/mod_forumlv/$filearea/$postid/$relativepath";
    if (!$file = $fs -> get_file_by_hash(sha1($fullpath)) or $file -> is_directory()) {
        return false;
    }

    // Make sure groups allow this user to see this file
    if ($discussion -> groupid > 0) {
        $groupmode = groups_get_activity_groupmode($cm, $course);
        if ($groupmode == SEPARATEGROUPS) {
            if (!groups_is_member($discussion -> groupid) and !has_capability('moodle/site:accessallgroups', $context)) {
                return false;
            }
        }
    }

    // Make sure we're allowed to see it...
    if (!forumlv_user_can_see_post($forumlv, $discussion, $post, NULL, $cm)) {
        return false;
    }

    // finally send the file
    send_stored_file($file, 0, 0, true, $options);
    // download MUST be forced - security!
}

/**
 * If successful, this function returns the name of the file
 *
 * @global object
 * @param object $post is a full post record, including course and forumlv
 * @param object $forumlv
 * @param object $cm
 * @param mixed $mform
 * @param string $unused
 * @return bool
 */
function forumlv_add_attachment($post, $forumlv, $cm, $mform = null, $unused = null) {
    global $DB;

    if (empty($mform)) {
        return false;
    }

    if (empty($post -> attachments)) {
        return true;
        // Nothing to do
    }

    $context = context_module::instance($cm -> id);

    $info = file_get_draft_area_info($post -> attachments);
    $present = ($info['filecount'] > 0) ? '1' : '';
    file_save_draft_area_files($post -> attachments, $context -> id, 'mod_forumlv', 'attachment', $post -> id, mod_forumlv_post_form::attachment_options($forumlv));

    $DB -> set_field('forumlv_posts', 'attachment', $present, array('id' => $post -> id));

    return true;
}

/**
 * Add a new post in an existing discussion.
 *
 * @global object
 * @global object
 * @global object
 * @param object $post
 * @param mixed $mform
 * @param string $message
 * @return int
 */
function forumlv_add_new_post($post, $mform, &$message) {
    global $USER, $CFG, $DB;

    $discussion = $DB -> get_record('forumlv_discussions', array('id' => $post -> discussion));
    $forumlv = $DB -> get_record('forumlv', array('id' => $discussion -> forumlv));
    $cm = get_coursemodule_from_instance('forumlv', $forumlv -> id);
    $context = context_module::instance($cm -> id);

    $post -> created = $post -> modified = time();
    $post -> mailed = FORUMLV_MAILED_PENDING;
    $post -> userid = $USER -> id;
    $post -> attachment = "";

    $post -> id = $DB -> insert_record("forumlv_posts", $post);
    $post -> message = file_save_draft_area_files($post -> itemid, $context -> id, 'mod_forumlv', 'post', $post -> id, mod_forumlv_post_form::editor_options(), $post -> message);
    $DB -> set_field('forumlv_posts', 'message', $post -> message, array('id' => $post -> id));
    forumlv_add_attachment($post, $forumlv, $cm, $mform, $message);

    // Update discussion modified date
    $DB -> set_field("forumlv_discussions", "timemodified", $post -> modified, array("id" => $post -> discussion));
    $DB -> set_field("forumlv_discussions", "usermodified", $post -> userid, array("id" => $post -> discussion));

    if (forumlv_tp_can_track_forumlvs($forumlv) && forumlv_tp_is_tracked($forumlv)) {
        forumlv_tp_mark_post_read($post -> userid, $post, $post -> forumlv);
    }

    // Let Moodle know that assessable content is uploaded (eg for plagiarism detection)
    forumlv_trigger_content_uploaded_event($post, $cm, 'forumlv_add_new_post');

    return $post -> id;
}

/**
 * Update a post
 *
 * @global object
 * @global object
 * @global object
 * @param object $post
 * @param mixed $mform
 * @param string $message
 * @return bool
 */
function forumlv_update_post($post, $mform, &$message) {
    global $USER, $CFG, $DB;

    $discussion = $DB -> get_record('forumlv_discussions', array('id' => $post -> discussion));
    $forumlv = $DB -> get_record('forumlv', array('id' => $discussion -> forumlv));
    $cm = get_coursemodule_from_instance('forumlv', $forumlv -> id);
    $context = context_module::instance($cm -> id);

    $post -> modified = time();

    $DB -> update_record('forumlv_posts', $post);

    $discussion -> timemodified = $post -> modified;
    // last modified tracking
    $discussion -> usermodified = $post -> userid;
    // last modified tracking

    if (!$post -> parent) {// Post is a discussion starter - update discussion title and times too
        $discussion -> name = $post -> subject;
        $discussion -> timestart = $post -> timestart;
        $discussion -> timeend = $post -> timeend;
    }
    $post -> message = file_save_draft_area_files($post -> itemid, $context -> id, 'mod_forumlv', 'post', $post -> id, mod_forumlv_post_form::editor_options(), $post -> message);
    $DB -> set_field('forumlv_posts', 'message', $post -> message, array('id' => $post -> id));

    $DB -> update_record('forumlv_discussions', $discussion);

    forumlv_add_attachment($post, $forumlv, $cm, $mform, $message);

    if (forumlv_tp_can_track_forumlvs($forumlv) && forumlv_tp_is_tracked($forumlv)) {
        forumlv_tp_mark_post_read($post -> userid, $post, $post -> forumlv);
    }

    // Let Moodle know that assessable content is uploaded (eg for plagiarism detection)
    forumlv_trigger_content_uploaded_event($post, $cm, 'forumlv_update_post');

    return true;
}

/**
 * Given an object containing all the necessary data,
 * create a new discussion and return the id
 *
 * @param object $post
 * @param mixed $mform
 * @param string $unused
 * @param int $userid
 * @return object
 */
function forumlv_add_discussion($discussion, $mform = null, $unused = null, $userid = null) {
    global $USER, $CFG, $DB;

    $timenow = time();

    if (is_null($userid)) {
        $userid = $USER -> id;
    }

    // The first post is stored as a real post, and linked
    // to from the discuss entry.

    $forumlv = $DB -> get_record('forumlv', array('id' => $discussion -> forumlv));
    $cm = get_coursemodule_from_instance('forumlv', $forumlv -> id);

    $post = new stdClass();
    $post -> discussion = 0;
    $post -> parent = 0;
    $post -> userid = $userid;
    $post -> created = $timenow;
    $post -> modified = $timenow;
    $post -> mailed = FORUMLV_MAILED_PENDING;
    $post -> subject = $discussion -> name;
    $post -> message = $discussion -> message;
    $post -> messageformat = $discussion -> messageformat;
    $post -> messagetrust = $discussion -> messagetrust;
    $post -> attachments = isset($discussion -> attachments) ? $discussion -> attachments : null;
    $post -> forumlv = $forumlv -> id;
    // speedup
    $post -> course = $forumlv -> course;
    // speedup
    $post -> mailnow = $discussion -> mailnow;

    $post -> id = $DB -> insert_record("forumlv_posts", $post);

    // TODO: Fix the calling code so that there always is a $cm when this function is called
    if (!empty($cm -> id) && !empty($discussion -> itemid)) {// In "single simple discussions" this may not exist yet
        $context = context_module::instance($cm -> id);
        $text = file_save_draft_area_files($discussion -> itemid, $context -> id, 'mod_forumlv', 'post', $post -> id, mod_forumlv_post_form::editor_options(), $post -> message);
        $DB -> set_field('forumlv_posts', 'message', $text, array('id' => $post -> id));
    }

    // Now do the main entry for the discussion, linking to this first post

    $discussion -> firstpost = $post -> id;
    $discussion -> timemodified = $timenow;
    $discussion -> usermodified = $post -> userid;
    $discussion -> userid = $userid;

    $post -> discussion = $DB -> insert_record("forumlv_discussions", $discussion);

    // Finally, set the pointer on the post.
    $DB -> set_field("forumlv_posts", "discussion", $post -> discussion, array("id" => $post -> id));

    if (!empty($cm -> id)) {
        forumlv_add_attachment($post, $forumlv, $cm, $mform, $unused);
    }

    if (forumlv_tp_can_track_forumlvs($forumlv) && forumlv_tp_is_tracked($forumlv)) {
        forumlv_tp_mark_post_read($post -> userid, $post, $post -> forumlv);
    }

    // Let Moodle know that assessable content is uploaded (eg for plagiarism detection)
    if (!empty($cm -> id)) {
        forumlv_trigger_content_uploaded_event($post, $cm, 'forumlv_add_discussion');
    }

    return $post -> discussion;
}

/**
 * Deletes a discussion and handles all associated cleanup.
 *
 * @global object
 * @param object $discussion Discussion to delete
 * @param bool $fulldelete True when deleting entire forumlv
 * @param object $course Course
 * @param object $cm Course-module
 * @param object $forumlv Forum
 * @return bool
 */
function forumlv_delete_discussion($discussion, $fulldelete, $course, $cm, $forumlv) {
    global $DB, $CFG;
    require_once ($CFG -> libdir . '/completionlib.php');

    $result = true;

    if ($posts = $DB -> get_records("forumlv_posts", array("discussion" => $discussion -> id))) {
        foreach ($posts as $post) {
            $post -> course = $discussion -> course;
            $post -> forumlv = $discussion -> forumlv;
            if (!forumlv_delete_post($post, 'ignore', $course, $cm, $forumlv, $fulldelete)) {
                $result = false;
            }
        }
    }

    forumlv_tp_delete_read_records(-1, -1, $discussion -> id);

    if (!$DB -> delete_records("forumlv_discussions", array("id" => $discussion -> id))) {
        $result = false;
    }

    // Update completion state if we are tracking completion based on number of posts
    // But don't bother when deleting whole thing
    if (!$fulldelete) {
        $completion = new completion_info($course);
        if ($completion -> is_enabled($cm) == COMPLETION_TRACKING_AUTOMATIC && ($forumlv -> completiondiscussions || $forumlv -> completionreplies || $forumlv -> completionposts)) {
            $completion -> update_state($cm, COMPLETION_INCOMPLETE, $discussion -> userid);
        }
    }

    return $result;
}

/**
 * Deletes a single forumlv post.
 *
 * @global object
 * @param object $post Forum post object
 * @param mixed $children Whether to delete children. If false, returns false
 *   if there are any children (without deleting the post). If true,
 *   recursively deletes all children. If set to special value 'ignore', deletes
 *   post regardless of children (this is for use only when deleting all posts
 *   in a disussion).
 * @param object $course Course
 * @param object $cm Course-module
 * @param object $forumlv Forum
 * @param bool $skipcompletion True to skip updating completion state if it
 *   would otherwise be updated, i.e. when deleting entire forumlv anyway.
 * @return bool
 */
function forumlv_delete_post($post, $children, $course, $cm, $forumlv, $skipcompletion = false) {
    global $DB, $CFG;
    require_once ($CFG -> libdir . '/completionlib.php');

    $context = context_module::instance($cm -> id);

    if ($children !== 'ignore' && ($childposts = $DB -> get_records('forumlv_posts', array('parent' => $post -> id)))) {
        if ($children) {
            foreach ($childposts as $childpost) {
                forumlv_delete_post($childpost, true, $course, $cm, $forumlv, $skipcompletion);
            }
        } else {
            return false;
        }
    }

    //delete ratings
    require_once ($CFG -> dirroot . '/rating/lib.php');
    $delopt = new stdClass;
    $delopt -> contextid = $context -> id;
    $delopt -> component = 'mod_forumlv';
    $delopt -> ratingarea = 'post';
    $delopt -> itemid = $post -> id;
    $rm = new rating_manager();
    $rm -> delete_ratings($delopt);

    //delete attachments
    $fs = get_file_storage();
    $fs -> delete_area_files($context -> id, 'mod_forumlv', 'attachment', $post -> id);
    $fs -> delete_area_files($context -> id, 'mod_forumlv', 'post', $post -> id);

    if ($DB -> delete_records("forumlv_posts", array("id" => $post -> id))) {

        forumlv_tp_delete_read_records(-1, $post -> id);

        // Just in case we are deleting the last post
        forumlv_discussion_update_last_post($post -> discussion);

        // Update completion state if we are tracking completion based on number of posts
        // But don't bother when deleting whole thing

        if (!$skipcompletion) {
            $completion = new completion_info($course);
            if ($completion -> is_enabled($cm) == COMPLETION_TRACKING_AUTOMATIC && ($forumlv -> completiondiscussions || $forumlv -> completionreplies || $forumlv -> completionposts)) {
                $completion -> update_state($cm, COMPLETION_INCOMPLETE, $post -> userid);
            }
        }

        return true;
    }
    return false;
}

/**
 * Sends post content to plagiarism plugin
 * @param object $post Forum post object
 * @param object $cm Course-module
 * @param string $name
 * @return bool
 */
function forumlv_trigger_content_uploaded_event($post, $cm, $name) {
    $context = context_module::instance($cm -> id);
    $fs = get_file_storage();
    $files = $fs -> get_area_files($context -> id, 'mod_forumlv', 'attachment', $post -> id, "timemodified", false);
    $eventdata = new stdClass();
    $eventdata -> modulename = 'forumlv';
    $eventdata -> name = $name;
    $eventdata -> cmid = $cm -> id;
    $eventdata -> itemid = $post -> id;
    $eventdata -> courseid = $post -> course;
    $eventdata -> userid = $post -> userid;
    $eventdata -> content = $post -> message;
    if ($files) {
        $eventdata -> pathnamehashes = array_keys($files);
    }
    events_trigger('assessable_content_uploaded', $eventdata);

    return true;
}

/**
 * @global object
 * @param object $post
 * @param bool $children
 * @return int
 */
function forumlv_count_replies($post, $children = true) {
    global $DB;
    $count = 0;

    if ($children) {
        if ($childposts = $DB -> get_records('forumlv_posts', array('parent' => $post -> id))) {
            foreach ($childposts as $childpost) {
                $count++;
                // For this child
                $count += forumlv_count_replies($childpost, true);
            }
        }
    } else {
        $count += $DB -> count_records('forumlv_posts', array('parent' => $post -> id));
    }

    return $count;
}

/**
 * @global object
 * @param int $forumlvid
 * @param mixed $value
 * @return bool
 */
function forumlv_forcesubscribe($forumlvid, $value = 1) {
    global $DB;
    return $DB -> set_field("forumlv", "forcesubscribe", $value, array("id" => $forumlvid));
}

/**
 * @global object
 * @param object $forumlv
 * @return bool
 */
function forumlv_is_forcesubscribed($forumlv) {
    global $DB;
    if (isset($forumlv -> forcesubscribe)) {// then we use that
        return ($forumlv -> forcesubscribe == FORUMLV_FORCESUBSCRIBE);
    } else {// Check the database
        return ($DB -> get_field('forumlv', 'forcesubscribe', array('id' => $forumlv)) == FORUMLV_FORCESUBSCRIBE);
    }
}

function forumlv_get_forcesubscribed($forumlv) {
    global $DB;
    if (isset($forumlv -> forcesubscribe)) {// then we use that
        return $forumlv -> forcesubscribe;
    } else {// Check the database
        return $DB -> get_field('forumlv', 'forcesubscribe', array('id' => $forumlv));
    }
}

/**
 * @global object
 * @param int $userid
 * @param object $forumlv
 * @return bool
 */
function forumlv_is_subscribed($userid, $forumlv) {
    global $DB;
    if (is_numeric($forumlv)) {
        $forumlv = $DB -> get_record('forumlv', array('id' => $forumlv));
    }
    // If forumlv is force subscribed and has allowforcesubscribe, then user is subscribed.
    $cm = get_coursemodule_from_instance('forumlv', $forumlv -> id);
    if (forumlv_is_forcesubscribed($forumlv) && $cm && has_capability('mod/forumlv:allowforcesubscribe', context_module::instance($cm -> id), $userid)) {
        return true;
    }
    return $DB -> record_exists("forumlv_subscriptions", array("userid" => $userid, "forumlv" => $forumlv -> id));
}

function forumlv_get_subscribed_forumlvs($course) {
    global $USER, $CFG, $DB;
    $sql = "SELECT f.id
              FROM {forumlv} f
                   LEFT JOIN {forumlv_subscriptions} fs ON (fs.forumlv = f.id AND fs.userid = ?)
             WHERE f.course = ?
                   AND f.forcesubscribe <> " . FORUMLV_DISALLOWSUBSCRIBE . "
                   AND (f.forcesubscribe = " . FORUMLV_FORCESUBSCRIBE . " OR fs.id IS NOT NULL)";
    if ($subscribed = $DB -> get_records_sql($sql, array($USER -> id, $course -> id))) {
        foreach ($subscribed as $s) {
            $subscribed[$s -> id] = $s -> id;
        }
        return $subscribed;
    } else {
        return array();
    }
}

/**
 * Returns an array of forumlvs that the current user is subscribed to and is allowed to unsubscribe from
 *
 * @return array An array of unsubscribable forumlvs
 */
function forumlv_get_optional_subscribed_forumlvs() {
    global $USER, $DB;

    // Get courses that $USER is enrolled in and can see
    $courses = enrol_get_my_courses();
    if (empty($courses)) {
        return array();
    }

    $courseids = array();
    foreach ($courses as $course) {
        $courseids[] = $course -> id;
    }
    list($coursesql, $courseparams) = $DB -> get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'c');

    // get all forumlvs from the user's courses that they are subscribed to and which are not set to forced
    $sql = "SELECT f.id, cm.id as cm, cm.visible
              FROM {forumlv} f
                   JOIN {course_modules} cm ON cm.instance = f.id
                   JOIN {modules} m ON m.name = :modulename AND m.id = cm.module
                   LEFT JOIN {forumlv_subscriptions} fs ON (fs.forumlv = f.id AND fs.userid = :userid)
             WHERE f.forcesubscribe <> :forcesubscribe AND fs.id IS NOT NULL
                   AND cm.course $coursesql";
    $params = array_merge($courseparams, array('modulename' => 'forumlv', 'userid' => $USER -> id, 'forcesubscribe' => FORUMLV_FORCESUBSCRIBE));
    if (!$forumlvs = $DB -> get_records_sql($sql, $params)) {
        return array();
    }

    $unsubscribableforumlvs = array();
    // Array to return

    foreach ($forumlvs as $forumlv) {

        if (empty($forumlv -> visible)) {
            // the forumlv is hidden
            $context = context_module::instance($forumlv -> cm);
            if (!has_capability('moodle/course:viewhiddenactivities', $context)) {
                // the user can't see the hidden forumlv
                continue;
            }
        }

        // subscribe.php only requires 'mod/forumlv:managesubscriptions' when
        // unsubscribing a user other than yourself so we don't require it here either

        // A check for whether the forumlv has subscription set to forced is built into the SQL above

        $unsubscribableforumlvs[] = $forumlv;
    }

    return $unsubscribableforumlvs;
}

/**
 * Adds user to the subscriber list
 *
 * @global object
 * @param int $userid
 * @param int $forumlvid
 */
function forumlv_subscribe($userid, $forumlvid) {
    global $DB;

    if ($DB -> record_exists("forumlv_subscriptions", array("userid" => $userid, "forumlv" => $forumlvid))) {
        return true;
    }

    $sub = new stdClass();
    $sub -> userid = $userid;
    $sub -> forumlv = $forumlvid;

    return $DB -> insert_record("forumlv_subscriptions", $sub);
}

/**
 * Removes user from the subscriber list
 *
 * @global object
 * @param int $userid
 * @param int $forumlvid
 */
function forumlv_unsubscribe($userid, $forumlvid) {
    global $DB;
    return $DB -> delete_records("forumlv_subscriptions", array("userid" => $userid, "forumlv" => $forumlvid));
}

/**
 * Given a new post, subscribes or unsubscribes as appropriate.
 * Returns some text which describes what happened.
 *
 * @global objec
 * @param object $post
 * @param object $forumlv
 */
function forumlv_post_subscription($post, $forumlv) {

    global $USER;

    $action = '';
    $subscribed = forumlv_is_subscribed($USER -> id, $forumlv);

    if ($forumlv -> forcesubscribe == FORUMLV_FORCESUBSCRIBE) {// database ignored
        return "";

    } elseif (($forumlv -> forcesubscribe == FORUMLV_DISALLOWSUBSCRIBE) && !has_capability('moodle/course:manageactivities', context_course::instance($forumlv -> course), $USER -> id)) {
        if ($subscribed) {
            $action = 'unsubscribe';
            // sanity check, following MDL-14558
        } else {
            return "";
        }

    } else {// go with the user's choice
        if (isset($post -> subscribe)) {
            // no change
            if ((!empty($post -> subscribe) && $subscribed) || (empty($post -> subscribe) && !$subscribed)) {
                return "";

            } elseif (!empty($post -> subscribe) && !$subscribed) {
                $action = 'subscribe';

            } elseif (empty($post -> subscribe) && $subscribed) {
                $action = 'unsubscribe';
            }
        }
    }

    $info = new stdClass();
    $info -> name = fullname($USER);
    $info -> forumlv = format_string($forumlv -> name);

    switch ($action) {
        case 'subscribe' :
            forumlv_subscribe($USER -> id, $post -> forumlv);
            return "<p>" . get_string("nowsubscribed", "forumlv", $info) . "</p>";
        case 'unsubscribe' :
            forumlv_unsubscribe($USER -> id, $post -> forumlv);
            return "<p>" . get_string("nownotsubscribed", "forumlv", $info) . "</p>";
    }
}

/**
 * Generate and return the subscribe or unsubscribe link for a forumlv.
 *
 * @param object $forumlv the forumlv. Fields used are $forumlv->id and $forumlv->forcesubscribe.
 * @param object $context the context object for this forumlv.
 * @param array $messages text used for the link in its various states
 *      (subscribed, unsubscribed, forcesubscribed or cantsubscribe).
 *      Any strings not passed in are taken from the $defaultmessages array
 *      at the top of the function.
 * @param bool $cantaccessagroup
 * @param bool $fakelink
 * @param bool $backtoindex
 * @param array $subscribed_forumlvs
 * @return string
 */
function forumlv_get_subscribe_link($forumlv, $context, $messages = array(), $cantaccessagroup = false, $fakelink = true, $backtoindex = false, $subscribed_forumlvs = null) {
    global $CFG, $USER, $PAGE, $OUTPUT;
    $defaultmessages = array('subscribed' => get_string('unsubscribe', 'forumlv'), 'unsubscribed' => get_string('subscribe', 'forumlv'), 'cantaccessgroup' => get_string('no'), 'forcesubscribed' => get_string('everyoneissubscribed', 'forumlv'), 'cantsubscribe' => get_string('disallowsubscribe', 'forumlv'));
    $messages = $messages + $defaultmessages;

    if (forumlv_is_forcesubscribed($forumlv)) {
        return $messages['forcesubscribed'];
    } else if ($forumlv -> forcesubscribe == FORUMLV_DISALLOWSUBSCRIBE && !has_capability('mod/forumlv:managesubscriptions', $context)) {
        return $messages['cantsubscribe'];
    } else if ($cantaccessagroup) {
        return $messages['cantaccessgroup'];
    } else {
        if (!is_enrolled($context, $USER, '', true)) {
            return '';
        }
        if (is_null($subscribed_forumlvs)) {
            $subscribed = forumlv_is_subscribed($USER -> id, $forumlv);
        } else {
            $subscribed = !empty($subscribed_forumlvs[$forumlv -> id]);
        }
        if ($subscribed) {
            $linktext = $messages['subscribed'];
            $linktitle = get_string('subscribestop', 'forumlv');
        } else {
            $linktext = $messages['unsubscribed'];
            $linktitle = get_string('subscribestart', 'forumlv');
        }

        $options = array();
        if ($backtoindex) {
            $backtoindexlink = '&amp;backtoindex=1';
            $options['backtoindex'] = 1;
        } else {
            $backtoindexlink = '';
        }
        $link = '';

        if ($fakelink) {
            $PAGE -> requires -> js('/mod/forumlv/forumlv.js');
            $PAGE -> requires -> js_function_call('forumlv_produce_subscribe_link', array($forumlv -> id, $backtoindexlink, $linktext, $linktitle));
            $link = "<noscript>";
        }
        $options['id'] = $forumlv -> id;
        $options['sesskey'] = sesskey();
        $url = new moodle_url('/mod/forumlv/subscribe.php', $options);
        $link .= $OUTPUT -> single_button($url, $linktext, 'get', array('title' => $linktitle));
        if ($fakelink) {
            $link .= '</noscript>';
        }

        return $link;
    }
}

/**
 * Generate and return the track or no track link for a forumlv.
 *
 * @global object
 * @global object
 * @global object
 * @param object $forumlv the forumlv. Fields used are $forumlv->id and $forumlv->forcesubscribe.
 * @param array $messages
 * @param bool $fakelink
 * @return string
 */
function forumlv_get_tracking_link($forumlv, $messages = array(), $fakelink = true) {
    global $CFG, $USER, $PAGE, $OUTPUT;

    static $strnotrackforumlv, $strtrackforumlv;

    if (isset($messages['trackforumlv'])) {
        $strtrackforumlv = $messages['trackforumlv'];
    }
    if (isset($messages['notrackforumlv'])) {
        $strnotrackforumlv = $messages['notrackforumlv'];
    }
    if (empty($strtrackforumlv)) {
        $strtrackforumlv = get_string('trackforumlv', 'forumlv');
    }
    if (empty($strnotrackforumlv)) {
        $strnotrackforumlv = get_string('notrackforumlv', 'forumlv');
    }

    if (forumlv_tp_is_tracked($forumlv)) {
        $linktitle = $strnotrackforumlv;
        $linktext = $strnotrackforumlv;
    } else {
        $linktitle = $strtrackforumlv;
        $linktext = $strtrackforumlv;
    }

    $link = '';
    if ($fakelink) {
        $PAGE -> requires -> js('/mod/forumlv/forumlv.js');
        $PAGE -> requires -> js_function_call('forumlv_produce_tracking_link', Array($forumlv -> id, $linktext, $linktitle));
        // use <noscript> to print button in case javascript is not enabled
        $link .= '<noscript>';
    }
    $url = new moodle_url('/mod/forumlv/settracking.php', array('id' => $forumlv -> id));
    $link .= $OUTPUT -> single_button($url, $linktext, 'get', array('title' => $linktitle));

    if ($fakelink) {
        $link .= '</noscript>';
    }

    return $link;
}

/**
 * Returns true if user created new discussion already
 *
 * @global object
 * @global object
 * @param int $forumlvid
 * @param int $userid
 * @return bool
 */
function forumlv_user_has_posted_discussion($forumlvid, $userid) {
    global $CFG, $DB;

    $sql = "SELECT 'x'
              FROM {forumlv_discussions} d, {forumlv_posts} p
             WHERE d.forumlv = ? AND p.discussion = d.id AND p.parent = 0 and p.userid = ?";

    return $DB -> record_exists_sql($sql, array($forumlvid, $userid));
}

/**
 * @global object
 * @global object
 * @param int $forumlvid
 * @param int $userid
 * @return array
 */
function forumlv_discussions_user_has_posted_in($forumlvid, $userid) {
    global $CFG, $DB;

    $haspostedsql = "SELECT d.id AS id,
                            d.*
                       FROM {forumlv_posts} p,
                            {forumlv_discussions} d
                      WHERE p.discussion = d.id
                        AND d.forumlv = ?
                        AND p.userid = ?";

    return $DB -> get_records_sql($haspostedsql, array($forumlvid, $userid));
}

/**
 * @global object
 * @global object
 * @param int $forumlvid
 * @param int $did
 * @param int $userid
 * @return bool
 */
function forumlv_user_has_posted($forumlvid, $did, $userid) {
    global $DB;

    if (empty($did)) {
        // posted in any forumlv discussion?
        $sql = "SELECT 'x'
                  FROM {forumlv_posts} p
                  JOIN {forumlv_discussions} d ON d.id = p.discussion
                 WHERE p.userid = :userid AND d.forumlv = :forumlvid";
        return $DB -> record_exists_sql($sql, array('forumlvid' => $forumlvid, 'userid' => $userid));
    } else {
        return $DB -> record_exists('forumlv_posts', array('discussion' => $did, 'userid' => $userid));
    }
}

/**
 * Returns creation time of the first user's post in given discussion
 * @global object $DB
 * @param int $did Discussion id
 * @param int $userid User id
 * @return int|bool post creation time stamp or return false
 */
function forumlv_get_user_posted_time($did, $userid) {
    global $DB;

    $posttime = $DB -> get_field('forumlv_posts', 'MIN(created)', array('userid' => $userid, 'discussion' => $did));
    if (empty($posttime)) {
        return false;
    }
    return $posttime;
}

/**
 * @global object
 * @param object $forumlv
 * @param object $currentgroup
 * @param int $unused
 * @param object $cm
 * @param object $context
 * @return bool
 */
function forumlv_user_can_post_discussion($forumlv, $currentgroup = null, $unused = -1, $cm = NULL, $context = NULL) {
    // $forumlv is an object
    global $USER;

    // shortcut - guest and not-logged-in users can not post
    if (isguestuser() or !isloggedin()) {
        return false;
    }

    if (!$cm) {
        debugging('missing cm', DEBUG_DEVELOPER);
        if (!$cm = get_coursemodule_from_instance('forumlv', $forumlv -> id, $forumlv -> course)) {
            print_error('invalidcoursemodule');
        }
    }

    if (!$context) {
        $context = context_module::instance($cm -> id);
    }

    if ($currentgroup === null) {
        $currentgroup = groups_get_activity_group($cm);
    }

    $groupmode = groups_get_activity_groupmode($cm);

    if ($forumlv -> type == 'news') {
        $capname = 'mod/forumlv:addnews';
    } else if ($forumlv -> type == 'qanda') {
        $capname = 'mod/forumlv:addquestion';
    } else {
        $capname = 'mod/forumlv:startdiscussion';
    }

    if (!has_capability($capname, $context)) {
        return false;
    }

    if ($forumlv -> type == 'single') {
        return false;
    }

    if ($forumlv -> type == 'eachuser') {
        if (forumlv_user_has_posted_discussion($forumlv -> id, $USER -> id)) {
            return false;
        }
    }

    if (!$groupmode or has_capability('moodle/site:accessallgroups', $context)) {
        return true;
    }

    if ($currentgroup) {
        return groups_is_member($currentgroup);
    } else {
        // no group membership and no accessallgroups means no new discussions
        // reverted to 1.7 behaviour in 1.9+,  buggy in 1.8.0-1.9.0
        return false;
    }
}

/**
 * This function checks whether the user can reply to posts in a forumlv
 * discussion. Use forumlv_user_can_post_discussion() to check whether the user
 * can start discussions.
 *
 * @global object
 * @global object
 * @uses DEBUG_DEVELOPER
 * @uses CONTEXT_MODULE
 * @uses VISIBLEGROUPS
 * @param object $forumlv forumlv object
 * @param object $discussion
 * @param object $user
 * @param object $cm
 * @param object $course
 * @param object $context
 * @return bool
 */
function forumlv_user_can_post($forumlv, $discussion, $user = NULL, $cm = NULL, $course = NULL, $context = NULL) {
    global $USER, $DB;
    if (empty($user)) {
        $user = $USER;
    }

    // shortcut - guest and not-logged-in users can not post
    if (isguestuser($user) or empty($user -> id)) {
        return false;
    }

    if (!isset($discussion -> groupid)) {
        debugging('incorrect discussion parameter', DEBUG_DEVELOPER);
        return false;
    }

    if (!$cm) {
        debugging('missing cm', DEBUG_DEVELOPER);
        if (!$cm = get_coursemodule_from_instance('forumlv', $forumlv -> id, $forumlv -> course)) {
            print_error('invalidcoursemodule');
        }
    }

    if (!$course) {
        debugging('missing course', DEBUG_DEVELOPER);
        if (!$course = $DB -> get_record('course', array('id' => $forumlv -> course))) {
            print_error('invalidcourseid');
        }
    }

    if (!$context) {
        $context = context_module::instance($cm -> id);
    }

    // normal users with temporary guest access can not post, suspended users can not post either
    if (!is_viewing($context, $user -> id) and !is_enrolled($context, $user -> id, '', true)) {
        return false;
    }

    if ($forumlv -> type == 'news') {
        $capname = 'mod/forumlv:replynews';
    } else {
        $capname = 'mod/forumlv:replypost';
    }

    if (!has_capability($capname, $context, $user -> id)) {
        return false;
    }

    if (!$groupmode = groups_get_activity_groupmode($cm, $course)) {
        return true;
    }

    if (has_capability('moodle/site:accessallgroups', $context)) {
        return true;
    }

    if ($groupmode == VISIBLEGROUPS) {
        if ($discussion -> groupid == -1) {
            // allow students to reply to all participants discussions - this was not possible in Moodle <1.8
            return true;
        }
        return groups_is_member($discussion -> groupid);

    } else {
        //separate groups
        if ($discussion -> groupid == -1) {
            return false;
        }
        return groups_is_member($discussion -> groupid);
    }
}

/**
 * Checks to see if a user can view a particular post.
 *
 * @deprecated since Moodle 2.4 use forumlv_user_can_see_post() instead
 *
 * @param object $post
 * @param object $course
 * @param object $cm
 * @param object $forumlv
 * @param object $discussion
 * @param object $user
 * @return boolean
 */
function forumlv_user_can_view_post($post, $course, $cm, $forumlv, $discussion, $user = null) {
    debugging('forumlv_user_can_view_post() is deprecated. Please use forumlv_user_can_see_post() instead.', DEBUG_DEVELOPER);
    return forumlv_user_can_see_post($forumlv, $discussion, $post, $user, $cm);
}

/**
 * Check to ensure a user can view a timed discussion.
 *
 * @param object $discussion
 * @param object $user
 * @param object $context
 * @return boolean returns true if they can view post, false otherwise
 */
function forumlv_user_can_see_timed_discussion($discussion, $user, $context) {
    global $CFG;

    // Check that the user can view a discussion that is normally hidden due to access times.
    if (!empty($CFG -> forumlv_enabletimedposts)) {
        $time = time();
        if (($discussion -> timestart != 0 && $discussion -> timestart > $time) || ($discussion -> timeend != 0 && $discussion -> timeend < $time)) {
            if (!has_capability('mod/forumlv:viewhiddentimedposts', $context, $user -> id)) {
                return false;
            }
        }
    }

    return true;
}

/**
 * Check to ensure a user can view a group discussion.
 *
 * @param object $discussion
 * @param object $cm
 * @param object $context
 * @return boolean returns true if they can view post, false otherwise
 */
function forumlv_user_can_see_group_discussion($discussion, $cm, $context) {

    // If it's a grouped discussion, make sure the user is a member.
    if ($discussion -> groupid > 0) {
        $groupmode = groups_get_activity_groupmode($cm);
        if ($groupmode == SEPARATEGROUPS) {
            return groups_is_member($discussion -> groupid) || has_capability('moodle/site:accessallgroups', $context);
        }
    }

    return true;
}

/**
 * @global object
 * @global object
 * @uses DEBUG_DEVELOPER
 * @param object $forumlv
 * @param object $discussion
 * @param object $context
 * @param object $user
 * @return bool
 */
function forumlv_user_can_see_discussion($forumlv, $discussion, $context, $user = NULL) {
    global $USER, $DB;

    if (empty($user) || empty($user -> id)) {
        $user = $USER;
    }

    // retrieve objects (yuk)
    if (is_numeric($forumlv)) {
        debugging('missing full forumlv', DEBUG_DEVELOPER);
        if (!$forumlv = $DB -> get_record('forumlv', array('id' => $forumlv))) {
            return false;
        }
    }
    if (is_numeric($discussion)) {
        debugging('missing full discussion', DEBUG_DEVELOPER);
        if (!$discussion = $DB -> get_record('forumlv_discussions', array('id' => $discussion))) {
            return false;
        }
    }
    if (!$cm = get_coursemodule_from_instance('forumlv', $forumlv -> id, $forumlv -> course)) {
        print_error('invalidcoursemodule');
    }

    if (!has_capability('mod/forumlv:viewdiscussion', $context)) {
        return false;
    }

    if (!forumlv_user_can_see_timed_discussion($discussion, $user, $context)) {
        return false;
    }

    if (!forumlv_user_can_see_group_discussion($discussion, $cm, $context)) {
        return false;
    }

    if ($forumlv -> type == 'qanda' && !forumlv_user_has_posted($forumlv -> id, $discussion -> id, $user -> id) && !has_capability('mod/forumlv:viewqandawithoutposting', $context)) {
        return false;
    }
    return true;
}

/**
 * @global object
 * @global object
 * @param object $forumlv
 * @param object $discussion
 * @param object $post
 * @param object $user
 * @param object $cm
 * @return bool
 */
function forumlv_user_can_see_post($forumlv, $discussion, $post, $user = NULL, $cm = NULL) {
    global $CFG, $USER, $DB;

    // Context used throughout function.
    $modcontext = context_module::instance($cm -> id);

    // retrieve objects (yuk)
    if (is_numeric($forumlv)) {
        debugging('missing full forumlv', DEBUG_DEVELOPER);
        if (!$forumlv = $DB -> get_record('forumlv', array('id' => $forumlv))) {
            return false;
        }
    }

    if (is_numeric($discussion)) {
        debugging('missing full discussion', DEBUG_DEVELOPER);
        if (!$discussion = $DB -> get_record('forumlv_discussions', array('id' => $discussion))) {
            return false;
        }
    }
    if (is_numeric($post)) {
        debugging('missing full post', DEBUG_DEVELOPER);
        if (!$post = $DB -> get_record('forumlv_posts', array('id' => $post))) {
            return false;
        }
    }

    if (!isset($post -> id) && isset($post -> parent)) {
        $post -> id = $post -> parent;
    }

    if (!$cm) {
        debugging('missing cm', DEBUG_DEVELOPER);
        if (!$cm = get_coursemodule_from_instance('forumlv', $forumlv -> id, $forumlv -> course)) {
            print_error('invalidcoursemodule');
        }
    }

    if (empty($user) || empty($user -> id)) {
        $user = $USER;
    }

    $canviewdiscussion = !empty($cm -> cache -> caps['mod/forumlv:viewdiscussion']) || has_capability('mod/forumlv:viewdiscussion', $modcontext, $user -> id);
    if (!$canviewdiscussion && !has_all_capabilities(array('moodle/user:viewdetails', 'moodle/user:readuserposts'), context_user::instance($post -> userid))) {
        return false;
    }

    if (isset($cm -> uservisible)) {
        if (!$cm -> uservisible) {
            return false;
        }
    } else {
        if (!coursemodule_visible_for_user($cm, $user -> id)) {
            return false;
        }
    }

    if (!forumlv_user_can_see_timed_discussion($discussion, $user, $modcontext)) {
        return false;
    }

    if (!forumlv_user_can_see_group_discussion($discussion, $cm, $modcontext)) {
        return false;
    }

    if ($forumlv -> type == 'qanda') {
        $firstpost = forumlv_get_firstpost_from_discussion($discussion -> id);
        $userfirstpost = forumlv_get_user_posted_time($discussion -> id, $user -> id);

        return (($userfirstpost !== false && (time() - $userfirstpost >= $CFG -> maxeditingtime)) || $firstpost -> id == $post -> id || $post -> userid == $user -> id || $firstpost -> userid == $user -> id || has_capability('mod/forumlv:viewqandawithoutposting', $modcontext, $user -> id));
    }
    return true;
}

/**
 * Prints the discussion view screen for a forumlv.
 *
 * @global object
 * @global object
 * @param object $course The current course object.
 * @param object $forumlv Forum to be printed.
 * @param int $maxdiscussions .
 * @param string $displayformat The display format to use (optional).
 * @param string $sort Sort arguments for database query (optional).
 * @param int $groupmode Group mode of the forumlv (optional).
 * @param void $unused (originally current group)
 * @param int $page Page mode, page to display (optional).
 * @param int $perpage The maximum number of discussions per page(optional)
 *
 */
function forumlv_print_latest_discussions($course, $forumlv, $maxdiscussions = -1, $displayformat = 'plain', $sort = '', $currentgroup = -1, $groupmode = -1, $page = -1, $perpage = 100, $cm = NULL) {
    global $CFG, $USER, $OUTPUT;

    if (!$cm) {
        if (!$cm = get_coursemodule_from_instance('forumlv', $forumlv -> id, $forumlv -> course)) {
            print_error('invalidcoursemodule');
        }
    }
    $context = context_module::instance($cm -> id);

    if (empty($sort)) {
        $sort = "d.timemodified DESC";
    }

    $olddiscussionlink = false;

    // Sort out some defaults
    if ($perpage <= 0) {
        $perpage = 0;
        $page = -1;
    }

    if ($maxdiscussions == 0) {
        // all discussions - backwards compatibility
        $page = -1;
        $perpage = 0;
        if ($displayformat == 'plain') {
            $displayformat = 'header';
            // Abbreviate display by default
        }

    } else if ($maxdiscussions > 0) {
        $page = -1;
        $perpage = $maxdiscussions;
    }

    $fullpost = false;
    if ($displayformat == 'plain') {
        $fullpost = true;
    }

    // Decide if current user is allowed to see ALL the current discussions or not

    // First check the group stuff
    if ($currentgroup == -1 or $groupmode == -1) {
        $groupmode = groups_get_activity_groupmode($cm, $course);
        $currentgroup = groups_get_activity_group($cm);
    }

    $groups = array();
    //cache

    // If the user can post discussions, then this is a good place to put the
    // button for it. We do not show the button if we are showing site news
    // and the current user is a guest.

    $canstart = forumlv_user_can_post_discussion($forumlv, $currentgroup, $groupmode, $cm, $context);
    if (!$canstart and $forumlv -> type !== 'news') {
        if (isguestuser() or !isloggedin()) {
            $canstart = true;
        }
        if (!is_enrolled($context) and !is_viewing($context)) {
            // allow guests and not-logged-in to see the button - they are prompted to log in after clicking the link
            // normal users with temporary guest access see this button too, they are asked to enrol instead
            // do not show the button to users with suspended enrolments here
            $canstart = enrol_selfenrol_available($course -> id);
        }
    }

    if ($canstart) {
        echo '<div class="singlebutton forumlvaddnew">';
        echo "<form id=\"newdiscussionform\" method=\"get\" action=\"$CFG->wwwroot/mod/forumlv/post.php\">";
        echo '<div>';
        echo "<input type=\"hidden\" name=\"forumlv\" value=\"$forumlv->id\" />";
        switch ($forumlv->type) {
            case 'news' :
            case 'blog' :
                $buttonadd = get_string('addanewtopic', 'forumlv');
                break;
            case 'qanda' :
                $buttonadd = get_string('addanewquestion', 'forumlv');
                break;
            default :
                $buttonadd = get_string('addanewdiscussion', 'forumlv');
                break;
        }
        echo '<input type="submit" value="' . $buttonadd . '" />';
        echo '</div>';
        echo '</form>';
        echo "</div>\n";

    } else if (isguestuser() or !isloggedin() or $forumlv -> type == 'news') {
        // no button and no info

    } else if ($groupmode and has_capability('mod/forumlv:startdiscussion', $context)) {
        // inform users why they can not post new discussion
        if ($currentgroup) {
            echo $OUTPUT -> notification(get_string('cannotadddiscussion', 'forumlv'));
        } else {
            echo $OUTPUT -> notification(get_string('cannotadddiscussionall', 'forumlv'));
        }
    }

    // Get all the recent discussions we're allowed to see

    $getuserlastmodified = ($displayformat == 'header');

    if (!$discussions = forumlv_get_discussions($cm, $sort, $fullpost, null, $maxdiscussions, $getuserlastmodified, $page, $perpage)) {
        echo '<div class="forumlvnodiscuss">';
        if ($forumlv -> type == 'news') {
            echo '(' . get_string('nonews', 'forumlv') . ')';
        } else if ($forumlv -> type == 'qanda') {
            echo '(' . get_string('noquestions', 'forumlv') . ')';
        } else {
            echo '(' . get_string('nodiscussions', 'forumlv') . ')';
        }
        echo "</div>\n";
        return;
    }

    // If we want paging
    if ($page != -1) {
        ///Get the number of discussions found
        $numdiscussions = forumlv_get_discussions_count($cm);

        ///Show the paging bar
        echo $OUTPUT -> paging_bar($numdiscussions, $page, $perpage, "view.php?f=$forumlv->id");
        if ($numdiscussions > 1000) {
            // saves some memory on sites with very large forumlvs
            $replies = forumlv_count_discussion_replies($forumlv -> id, $sort, $maxdiscussions, $page, $perpage);
        } else {
            $replies = forumlv_count_discussion_replies($forumlv -> id);
        }

    } else {
        $replies = forumlv_count_discussion_replies($forumlv -> id);

        if ($maxdiscussions > 0 and $maxdiscussions <= count($discussions)) {
            $olddiscussionlink = true;
        }
    }

    $canviewparticipants = has_capability('moodle/course:viewparticipants', $context);

    $strdatestring = get_string('strftimerecentfull');

    // Check if the forumlv is tracked.
    if ($cantrack = forumlv_tp_can_track_forumlvs($forumlv)) {
        $forumlvtracked = forumlv_tp_is_tracked($forumlv);
    } else {
        $forumlvtracked = false;
    }

    if ($forumlvtracked) {
        $unreads = forumlv_get_discussions_unread($cm);
    } else {
        $unreads = array();
    }

    if ($displayformat == 'header') {
        echo '<table cellspacing="0" class="forumlvheaderlist">';
        echo '<thead>';
        echo '<tr>';
        echo '<th class="header topic" scope="col">' . get_string('discussion', 'forumlv') . '</th>';
        echo '<th class="header author" colspan="2" scope="col">' . get_string('startedby', 'forumlv') . '</th>';
        if ($groupmode > 0) {
            echo '<th class="header group" scope="col">' . get_string('group') . '</th>';
        }
        if (has_capability('mod/forumlv:viewdiscussion', $context)) {
            echo '<th class="header replies" scope="col">' . get_string('replies', 'forumlv') . '</th>';
            // If the forumlv can be tracked, display the unread column.
            if ($cantrack) {
                echo '<th class="header replies" scope="col">' . get_string('unread', 'forumlv');
                if ($forumlvtracked) {
                    echo '<a title="' . get_string('markallread', 'forumlv') . '" href="' . $CFG -> wwwroot . '/mod/forumlv/markposts.php?f=' . $forumlv -> id . '&amp;mark=read&amp;returnpage=view.php">' . '<img src="' . $OUTPUT -> pix_url('t/markasread') . '" class="iconsmall" alt="' . get_string('markallread', 'forumlv') . '" /></a>';
                }
                echo '</th>';
            }
        }
        echo '<th class="header lastpost" scope="col">' . get_string('lastpost', 'forumlv') . '</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
    }

    foreach ($discussions as $discussion) {
        if (!empty($replies[$discussion -> discussion])) {
            $discussion -> replies = $replies[$discussion -> discussion] -> replies;
            $discussion -> lastpostid = $replies[$discussion -> discussion] -> lastpostid;
        } else {
            $discussion -> replies = 0;
        }

        // SPECIAL CASE: The front page can display a news item post to non-logged in users.
        // All posts are read in this case.
        if (!$forumlvtracked) {
            $discussion -> unread = '-';
        } else if (empty($USER)) {
            $discussion -> unread = 0;
        } else {
            if (empty($unreads[$discussion -> discussion])) {
                $discussion -> unread = 0;
            } else {
                $discussion -> unread = $unreads[$discussion -> discussion];
            }
        }

        if (isloggedin()) {
            $ownpost = ($discussion -> userid == $USER -> id);
        } else {
            $ownpost = false;
        }
        // Use discussion name instead of subject of first post
        $discussion -> subject = $discussion -> name;

        switch ($displayformat) {
            case 'header' :
                if ($groupmode > 0) {
                    if (isset($groups[$discussion -> groupid])) {
                        $group = $groups[$discussion -> groupid];
                    } else {
                        $group = $groups[$discussion -> groupid] = groups_get_group($discussion -> groupid);
                    }
                } else {
                    $group = -1;
                }
                forumlv_print_discussion_header($discussion, $forumlv, $group, $strdatestring, $cantrack, $forumlvtracked, $canviewparticipants, $context);
                break;
            default :
                $link = false;

                if ($discussion -> replies) {
                    $link = true;
                } else {
                    $modcontext = context_module::instance($cm -> id);
                    $link = forumlv_user_can_see_discussion($forumlv, $discussion, $modcontext, $USER);
                }

                $discussion -> forumlv = $forumlv -> id;

                forumlv_print_post($discussion, $discussion, $forumlv, $cm, $course, $ownpost, 0, $link, false, '', null, true, $forumlvtracked);
                break;
        }
    }

    if ($displayformat == "header") {
        echo '</tbody>';
        echo '</table>';
    }

    if ($olddiscussionlink) {
        if ($forumlv -> type == 'news') {
            $strolder = get_string('oldertopics', 'forumlv');
        } else {
            $strolder = get_string('olderdiscussions', 'forumlv');
        }
        echo '<div class="forumlvolddiscuss">';
        echo '<a href="' . $CFG -> wwwroot . '/mod/forumlv/view.php?f=' . $forumlv -> id . '&amp;showall=1">';
        echo $strolder . '</a> ...</div>';
    }

    if ($page != -1) {///Show the paging bar
        echo $OUTPUT -> paging_bar($numdiscussions, $page, $perpage, "view.php?f=$forumlv->id");
    }
}

/**
 * Prints a forumlv discussion
 *
 * @uses CONTEXT_MODULE
 * @uses FORUMLV_MODE_FLATNEWEST
 * @uses FORUMLV_MODE_FLATOLDEST
 * @uses FORUMLV_MODE_THREADED
 * @uses FORUMLV_MODE_NESTED
 * @param stdClass $course
 * @param stdClass $cm
 * @param stdClass $forumlv
 * @param stdClass $discussion
 * @param stdClass $post
 * @param int $mode
 * @param mixed $canreply
 * @param bool $canrate
 */
function forumlv_print_discussion($course, $cm, $forumlv, $discussion, $post, $mode, $canreply = NULL, $canrate = false) {
    global $USER, $CFG;

    require_once ($CFG -> dirroot . '/rating/lib.php');

    $ownpost = (isloggedin() && $USER -> id == $post -> userid);

    $modcontext = context_module::instance($cm -> id);
    if ($canreply === NULL) {
        $reply = forumlv_user_can_post($forumlv, $discussion, $USER, $cm, $course, $modcontext);
    } else {
        $reply = $canreply;
    }

    // $cm holds general cache for forumlv functions
    $cm -> cache = new stdClass;
    $cm -> cache -> groups = groups_get_all_groups($course -> id, 0, $cm -> groupingid);
    $cm -> cache -> usersgroups = array();

    $posters = array();

    // preload all posts - TODO: improve...
    if ($mode == FORUMLV_MODE_FLATNEWEST) {
        $sort = "p.created DESC";
    } else {
        $sort = "p.created ASC";
    }

    $forumlvtracked = forumlv_tp_is_tracked($forumlv);
    $posts = forumlv_get_all_discussion_posts($discussion -> id, $sort, $forumlvtracked);
    $post = $posts[$post -> id];

    foreach ($posts as $pid => $p) {
        $posters[$p -> userid] = $p -> userid;
    }

    // preload all groups of ppl that posted in this discussion
    if ($postersgroups = groups_get_all_groups($course -> id, $posters, $cm -> groupingid, 'gm.id, gm.groupid, gm.userid')) {
        foreach ($postersgroups as $pg) {
            if (!isset($cm -> cache -> usersgroups[$pg -> userid])) {
                $cm -> cache -> usersgroups[$pg -> userid] = array();
            }
            $cm -> cache -> usersgroups[$pg -> userid][$pg -> groupid] = $pg -> groupid;
        }
        unset($postersgroups);
    }

    //load ratings
    if ($forumlv -> assessed != RATING_AGGREGATE_NONE) {
        $ratingoptions = new stdClass;
        $ratingoptions -> context = $modcontext;
        $ratingoptions -> component = 'mod_forumlv';
        $ratingoptions -> ratingarea = 'post';
        $ratingoptions -> items = $posts;
        $ratingoptions -> aggregate = $forumlv -> assessed;
        //the aggregation method
        $ratingoptions -> scaleid = $forumlv -> scale;
        $ratingoptions -> userid = $USER -> id;
        if ($forumlv -> type == 'single' or !$discussion -> id) {
            $ratingoptions -> returnurl = "$CFG->wwwroot/mod/forumlv/view.php?id=$cm->id";
        } else {
            $ratingoptions -> returnurl = "$CFG->wwwroot/mod/forumlv/discuss.php?d=$discussion->id";
        }
        $ratingoptions -> assesstimestart = $forumlv -> assesstimestart;
        $ratingoptions -> assesstimefinish = $forumlv -> assesstimefinish;

        //@lvs
        $gerenciadorNotas = NotasLvFactory::criarGerenciador('moodle2');
        $gerenciadorNotas -> setModulo(new Forumlv($course -> id));

        /** @lvs transforma posts em itens lvs */
        foreach ($posts as $postagem) {
            $wrappedItem = new stdClass();
            $wrappedItem -> id = $postagem -> id;
            $wrappedItem -> userid = $postagem -> userid;
            $wrappedItem -> created = $postagem -> created;
            $postagem -> itemlv = new Item('forumlv', 'post', $wrappedItem);
        }

        // $rm = new rating_manager();
        // $posts = $rm->get_ratings($ratingoptions);
    }

    $post -> forumlv = $forumlv -> id;
    // Add the forumlv id to the post object, later used by forumlv_print_post
    $post -> forumlvtype = $forumlv -> type;

    $post -> subject = format_string($post -> subject);

    $postread = !empty($post -> postread);

    forumlv_print_post($post, $discussion, $forumlv, $cm, $course, $ownpost, $reply, false, '', '', $postread, true, $forumlvtracked);

    switch ($mode) {
        case FORUMLV_MODE_FLATOLDEST :
        case FORUMLV_MODE_FLATNEWEST :
        default :
            forumlv_print_posts_flat($course, $cm, $forumlv, $discussion, $post, $mode, $reply, $forumlvtracked, $posts);
            break;

        case FORUMLV_MODE_THREADED :
            forumlv_print_posts_threaded($course, $cm, $forumlv, $discussion, $post, 0, $reply, $forumlvtracked, $posts);
            break;

        case FORUMLV_MODE_NESTED :
            forumlv_print_posts_nested($course, $cm, $forumlv, $discussion, $post, $reply, $forumlvtracked, $posts);
            break;
    }
}

/**
 * @global object
 * @global object
 * @uses FORUMLV_MODE_FLATNEWEST
 * @param object $course
 * @param object $cm
 * @param object $forumlv
 * @param object $discussion
 * @param object $post
 * @param object $mode
 * @param bool $reply
 * @param bool $forumlvtracked
 * @param array $posts
 * @return void
 */
function forumlv_print_posts_flat($course, &$cm, $forumlv, $discussion, $post, $mode, $reply, $forumlvtracked, $posts) {
    global $USER, $CFG;

    $link = false;

    if ($mode == FORUMLV_MODE_FLATNEWEST) {
        $sort = "ORDER BY created DESC";
    } else {
        $sort = "ORDER BY created ASC";
    }

    foreach ($posts as $post) {
        if (!$post -> parent) {
            continue;
        }
        $post -> subject = format_string($post -> subject);
        $ownpost = ($USER -> id == $post -> userid);

        $postread = !empty($post -> postread);

        forumlv_print_post($post, $discussion, $forumlv, $cm, $course, $ownpost, $reply, $link, '', '', $postread, true, $forumlvtracked);
    }
}

/**
 * @todo Document this function
 *
 * @global object
 * @global object
 * @uses CONTEXT_MODULE
 * @return void
 */
function forumlv_print_posts_threaded($course, &$cm, $forumlv, $discussion, $parent, $depth, $reply, $forumlvtracked, $posts) {
    global $USER, $CFG;

    $link = false;

    if (!empty($posts[$parent -> id] -> children)) {
        $posts = $posts[$parent -> id] -> children;

        $modcontext = context_module::instance($cm -> id);
        $canviewfullnames = has_capability('moodle/site:viewfullnames', $modcontext);

        foreach ($posts as $post) {

            echo '<div class="indent">';
            if ($depth > 0) {
                $ownpost = ($USER -> id == $post -> userid);
                $post -> subject = format_string($post -> subject);

                $postread = !empty($post -> postread);

                forumlv_print_post($post, $discussion, $forumlv, $cm, $course, $ownpost, $reply, $link, '', '', $postread, true, $forumlvtracked);
            } else {
                if (!forumlv_user_can_see_post($forumlv, $discussion, $post, NULL, $cm)) {
                    echo "</div>\n";
                    continue;
                }
                $by = new stdClass();
                $by -> name = fullname($post, $canviewfullnames);
                $by -> date = userdate($post -> modified);

                if ($forumlvtracked) {
                    if (!empty($post -> postread)) {
                        $style = '<span class="forumlvthread read">';
                    } else {
                        $style = '<span class="forumlvthread unread">';
                    }
                } else {
                    $style = '<span class="forumlvthread">';
                }
                echo $style . "<a name=\"$post->id\"></a>" . "<a href=\"discuss.php?d=$post->discussion&amp;parent=$post->id\">" . format_string($post -> subject, true) . "</a> ";
                print_string("bynameondate", "forumlv", $by);
                echo "</span>";
            }

            forumlv_print_posts_threaded($course, $cm, $forumlv, $discussion, $post, $depth - 1, $reply, $forumlvtracked, $posts);
            echo "</div>\n";
        }
    }
}

/**
 * @todo Document this function
 * @global object
 * @global object
 * @return void
 */
function forumlv_print_posts_nested($course, &$cm, $forumlv, $discussion, $parent, $reply, $forumlvtracked, $posts) {
    global $USER, $CFG;

    $link = false;

    if (!empty($posts[$parent -> id] -> children)) {
        $posts = $posts[$parent -> id] -> children;

        foreach ($posts as $post) {

            echo '<div class="indent">';
            if (!isloggedin()) {
                $ownpost = false;
            } else {
                $ownpost = ($USER -> id == $post -> userid);
            }

            $post -> subject = format_string($post -> subject);
            $postread = !empty($post -> postread);

            forumlv_print_post($post, $discussion, $forumlv, $cm, $course, $ownpost, $reply, $link, '', '', $postread, true, $forumlvtracked);
            forumlv_print_posts_nested($course, $cm, $forumlv, $discussion, $post, $reply, $forumlvtracked, $posts);
            echo "</div>\n";
        }
    }
}

/**
 * Returns all forumlv posts since a given time in specified forumlv.
 *
 * @todo Document this functions args
 * @global object
 * @global object
 * @global object
 * @global object
 */
function forumlv_get_recent_mod_activity(&$activities, &$index, $timestart, $courseid, $cmid, $userid = 0, $groupid = 0) {
    global $CFG, $COURSE, $USER, $DB;

    if ($COURSE -> id == $courseid) {
        $course = $COURSE;
    } else {
        $course = $DB -> get_record('course', array('id' => $courseid));
    }

    $modinfo = get_fast_modinfo($course);

    $cm = $modinfo -> cms[$cmid];
    $params = array($timestart, $cm -> instance);

    if ($userid) {
        $userselect = "AND u.id = ?";
        $params[] = $userid;
    } else {
        $userselect = "";
    }

    if ($groupid) {
        $groupselect = "AND gm.groupid = ?";
        $groupjoin = "JOIN {groups_members} gm ON  gm.userid=u.id";
        $params[] = $groupid;
    } else {
        $groupselect = "";
        $groupjoin = "";
    }

    if (!$posts = $DB -> get_records_sql("SELECT p.*, f.type AS forumlvtype, d.forumlv, d.groupid,
                                              d.timestart, d.timeend, d.userid AS duserid,
                                              u.firstname, u.lastname, u.email, u.picture, u.imagealt, u.email
                                         FROM {forumlv_posts} p
                                              JOIN {forumlv_discussions} d ON d.id = p.discussion
                                              JOIN {forumlv} f             ON f.id = d.forumlv
                                              JOIN {user} u              ON u.id = p.userid
                                              $groupjoin
                                        WHERE p.created > ? AND f.id = ?
                                              $userselect $groupselect
                                     ORDER BY p.id ASC", $params)) {// order by initial posting date
        return;
    }

    $groupmode = groups_get_activity_groupmode($cm, $course);
    $cm_context = context_module::instance($cm -> id);
    $viewhiddentimed = has_capability('mod/forumlv:viewhiddentimedposts', $cm_context);
    $accessallgroups = has_capability('moodle/site:accessallgroups', $cm_context);

    if (is_null($modinfo -> groups)) {
        $modinfo -> groups = groups_get_user_groups($course -> id);
        // load all my groups and cache it in modinfo
    }

    $printposts = array();
    foreach ($posts as $post) {

        if (!empty($CFG -> forumlv_enabletimedposts) and $USER -> id != $post -> duserid and (($post -> timestart > 0 and $post -> timestart > time()) or ($post -> timeend > 0 and $post -> timeend < time()))) {
            if (!$viewhiddentimed) {
                continue;
            }
        }

        if ($groupmode) {
            if ($post -> groupid == -1 or $groupmode == VISIBLEGROUPS or $accessallgroups) {
                // oki (Open discussions have groupid -1)
            } else {
                // separate mode
                if (isguestuser()) {
                    // shortcut
                    continue;
                }

                if (!array_key_exists($post -> groupid, $modinfo -> groups[0])) {
                    continue;
                }
            }
        }

        $printposts[] = $post;
    }

    if (!$printposts) {
        return;
    }

    $aname = format_string($cm -> name, true);

    foreach ($printposts as $post) {
        $tmpactivity = new stdClass();

        $tmpactivity -> type = 'forumlv';
        $tmpactivity -> cmid = $cm -> id;
        $tmpactivity -> name = $aname;
        $tmpactivity -> sectionnum = $cm -> sectionnum;
        $tmpactivity -> timestamp = $post -> modified;

        $tmpactivity -> content = new stdClass();
        $tmpactivity -> content -> id = $post -> id;
        $tmpactivity -> content -> discussion = $post -> discussion;
        $tmpactivity -> content -> subject = format_string($post -> subject);
        $tmpactivity -> content -> parent = $post -> parent;

        $tmpactivity -> user = new stdClass();
        $tmpactivity -> user -> id = $post -> userid;
        $tmpactivity -> user -> firstname = $post -> firstname;
        $tmpactivity -> user -> lastname = $post -> lastname;
        $tmpactivity -> user -> picture = $post -> picture;
        $tmpactivity -> user -> imagealt = $post -> imagealt;
        $tmpactivity -> user -> email = $post -> email;

        $activities[$index++] = $tmpactivity;
    }

    return;
}

/**
 * @todo Document this function
 * @global object
 */
function forumlv_print_recent_mod_activity($activity, $courseid, $detail, $modnames, $viewfullnames) {
    global $CFG, $OUTPUT;

    if ($activity -> content -> parent) {
        $class = 'reply';
    } else {
        $class = 'discussion';
    }

    echo '<table border="0" cellpadding="3" cellspacing="0" class="forumlv-recent">';

    echo "<tr><td class=\"userpicture\" valign=\"top\">";
    echo $OUTPUT -> user_picture($activity -> user, array('courseid' => $courseid));
    echo "</td><td class=\"$class\">";

    echo '<div class="title">';
    if ($detail) {
        $aname = s($activity -> name);
        echo "<img src=\"" . $OUTPUT -> pix_url('icon', $activity -> type) . "\" " . "class=\"icon\" alt=\"{$aname}\" />";
    }
    echo "<a href=\"$CFG->wwwroot/mod/forumlv/discuss.php?d={$activity->content->discussion}" . "#p{$activity->content->id}\">{$activity->content->subject}</a>";
    echo '</div>';

    echo '<div class="user">';
    $fullname = fullname($activity -> user, $viewfullnames);
    echo "<a href=\"$CFG->wwwroot/user/view.php?id={$activity->user->id}&amp;course=$courseid\">" . "{$fullname}</a> - " . userdate($activity -> timestamp);
    echo '</div>';
    echo "</td></tr></table>";

    return;
}

/**
 * recursively sets the discussion field to $discussionid on $postid and all its children
 * used when pruning a post
 *
 * @global object
 * @param int $postid
 * @param int $discussionid
 * @return bool
 */
function forumlv_change_discussionid($postid, $discussionid) {
    global $DB;
    $DB -> set_field('forumlv_posts', 'discussion', $discussionid, array('id' => $postid));
    if ($posts = $DB -> get_records('forumlv_posts', array('parent' => $postid))) {
        foreach ($posts as $post) {
            forumlv_change_discussionid($post -> id, $discussionid);
        }
    }
    return true;
}

/**
 * Prints the editing button on subscribers page
 *
 * @global object
 * @global object
 * @param int $courseid
 * @param int $forumlvid
 * @return string
 */
function forumlv_update_subscriptions_button($courseid, $forumlvid) {
    global $CFG, $USER;

    if (!empty($USER -> subscriptionsediting)) {
        $string = get_string('turneditingoff');
        $edit = "off";
    } else {
        $string = get_string('turneditingon');
        $edit = "on";
    }

    return "<form method=\"get\" action=\"$CFG->wwwroot/mod/forumlv/subscribers.php\">" . "<input type=\"hidden\" name=\"id\" value=\"$forumlvid\" />" . "<input type=\"hidden\" name=\"edit\" value=\"$edit\" />" . "<input type=\"submit\" value=\"$string\" /></form>";
}

/**
 * This function gets run whenever user is enrolled into course
 *
 * @deprecated deprecating this function as we will be using forumlv_user_role_assigned
 * @param stdClass $cp
 * @return void
 */
function forumlv_user_enrolled($cp) {
    global $DB;

    // NOTE: this has to be as fast as possible - we do not want to slow down enrolments!
    //       Originally there used to be 'mod/forumlv:initialsubscriptions' which was
    //       introduced because we did not have enrolment information in earlier versions...

    $sql = "SELECT f.id
              FROM {forumlv} f
         LEFT JOIN {forumlv_subscriptions} fs ON (fs.forumlv = f.id AND fs.userid = :userid)
             WHERE f.course = :courseid AND f.forcesubscribe = :initial AND fs.id IS NULL";
    $params = array('courseid' => $cp -> courseid, 'userid' => $cp -> userid, 'initial' => FORUMLV_INITIALSUBSCRIBE);

    $forumlvs = $DB -> get_records_sql($sql, $params);
    foreach ($forumlvs as $forumlv) {
        forumlv_subscribe($cp -> userid, $forumlv -> id);
    }
}

/**
 * This function gets run whenever user is assigned role in course
 *
 * @param stdClass $cp
 * @return void
 */
function forumlv_user_role_assigned($cp) {
    global $DB;

    $context = context::instance_by_id($cp -> contextid, MUST_EXIST);

    // If contextlevel is course then only subscribe user. Role assignment
    // at course level means user is enroled in course and can subscribe to forumlv.
    if ($context -> contextlevel != CONTEXT_COURSE) {
        return;
    }

    $sql = "SELECT f.id, cm.id AS cmid
              FROM {forumlv} f
              JOIN {course_modules} cm ON (cm.instance = f.id)
              JOIN {modules} m ON (m.id = cm.module)
         LEFT JOIN {forumlv_subscriptions} fs ON (fs.forumlv = f.id AND fs.userid = :userid)
             WHERE f.course = :courseid
               AND f.forcesubscribe = :initial
               AND m.name = 'forumlv'
               AND fs.id IS NULL";
    $params = array('courseid' => $context -> instanceid, 'userid' => $cp -> userid, 'initial' => FORUMLV_INITIALSUBSCRIBE);

    $forumlvs = $DB -> get_records_sql($sql, $params);
    foreach ($forumlvs as $forumlv) {
        // If user doesn't have allowforcesubscribe capability then don't subscribe.
        if (has_capability('mod/forumlv:allowforcesubscribe', context_module::instance($forumlv -> cmid), $cp -> userid)) {
            forumlv_subscribe($cp -> userid, $forumlv -> id);
        }
    }
}

/**
 * This function gets run whenever user is unenrolled from course
 *
 * @param stdClass $cp
 * @return void
 */
function forumlv_user_unenrolled($cp) {
    global $DB;

    // NOTE: this has to be as fast as possible!

    if ($cp -> lastenrol) {
        $params = array('userid' => $cp -> userid, 'courseid' => $cp -> courseid);
        $forumlvselect = "IN (SELECT f.id FROM {forumlv} f WHERE f.course = :courseid)";

        $DB -> delete_records_select('forumlv_subscriptions', "userid = :userid AND forumlv $forumlvselect", $params);
        $DB -> delete_records_select('forumlv_track_prefs', "userid = :userid AND forumlvid $forumlvselect", $params);
        $DB -> delete_records_select('forumlv_read', "userid = :userid AND forumlvid $forumlvselect", $params);
    }
}

// Functions to do with read tracking.

/**
 * Mark posts as read.
 *
 * @global object
 * @global object
 * @param object $user object
 * @param array $postids array of post ids
 * @return boolean success
 */
function forumlv_tp_mark_posts_read($user, $postids) {
    global $CFG, $DB;

    if (!forumlv_tp_can_track_forumlvs(false, $user)) {
        return true;
    }

    $status = true;

    $now = time();
    $cutoffdate = $now - ($CFG -> forumlv_oldpostdays * 24 * 3600);

    if (empty($postids)) {
        return true;

    } else if (count($postids) > 200) {
        while ($part = array_splice($postids, 0, 200)) {
            $status = forumlv_tp_mark_posts_read($user, $part) && $status;
        }
        return $status;
    }

    list($usql, $params) = $DB -> get_in_or_equal($postids);
    $params[] = $user -> id;

    $sql = "SELECT id
              FROM {forumlv_read}
             WHERE postid $usql AND userid = ?";
    if ($existing = $DB -> get_records_sql($sql, $params)) {
        $existing = array_keys($existing);
    } else {
        $existing = array();
    }

    $new = array_diff($postids, $existing);

    if ($new) {
        list($usql, $new_params) = $DB -> get_in_or_equal($new);
        $params = array($user -> id, $now, $now, $user -> id);
        $params = array_merge($params, $new_params);
        $params[] = $cutoffdate;

        $sql = "INSERT INTO {forumlv_read} (userid, postid, discussionid, forumlvid, firstread, lastread)

                SELECT ?, p.id, p.discussion, d.forumlv, ?, ?
                  FROM {forumlv_posts} p
                       JOIN {forumlv_discussions} d       ON d.id = p.discussion
                       JOIN {forumlv} f                   ON f.id = d.forumlv
                       LEFT JOIN {forumlv_track_prefs} tf ON (tf.userid = ? AND tf.forumlvid = f.id)
                 WHERE p.id $usql
                       AND p.modified >= ?
                       AND (f.trackingtype = " . FORUMLV_TRACKING_ON . "
                            OR (f.trackingtype = " . FORUMLV_TRACKING_OPTIONAL . " AND tf.id IS NULL))";
        $status = $DB -> execute($sql, $params) && $status;
    }

    if ($existing) {
        list($usql, $new_params) = $DB -> get_in_or_equal($existing);
        $params = array($now, $user -> id);
        $params = array_merge($params, $new_params);

        $sql = "UPDATE {forumlv_read}
                   SET lastread = ?
                 WHERE userid = ? AND postid $usql";
        $status = $DB -> execute($sql, $params) && $status;
    }

    return $status;
}

/**
 * Mark post as read.
 * @global object
 * @global object
 * @param int $userid
 * @param int $postid
 */
function forumlv_tp_add_read_record($userid, $postid) {
    global $CFG, $DB;

    $now = time();
    $cutoffdate = $now - ($CFG -> forumlv_oldpostdays * 24 * 3600);

    if (!$DB -> record_exists('forumlv_read', array('userid' => $userid, 'postid' => $postid))) {
        $sql = "INSERT INTO {forumlv_read} (userid, postid, discussionid, forumlvid, firstread, lastread)

                SELECT ?, p.id, p.discussion, d.forumlv, ?, ?
                  FROM {forumlv_posts} p
                       JOIN {forumlv_discussions} d ON d.id = p.discussion
                 WHERE p.id = ? AND p.modified >= ?";
        return $DB -> execute($sql, array($userid, $now, $now, $postid, $cutoffdate));

    } else {
        $sql = "UPDATE {forumlv_read}
                   SET lastread = ?
                 WHERE userid = ? AND postid = ?";
        return $DB -> execute($sql, array($now, $userid, $userid));
    }
}

/**
 * Returns all records in the 'forumlv_read' table matching the passed keys, indexed
 * by userid.
 *
 * @global object
 * @param int $userid
 * @param int $postid
 * @param int $discussionid
 * @param int $forumlvid
 * @return array
 */
function forumlv_tp_get_read_records($userid = -1, $postid = -1, $discussionid = -1, $forumlvid = -1) {
    global $DB;
    $select = '';
    $params = array();

    if ($userid > -1) {
        if ($select != '')
            $select .= ' AND ';
        $select .= 'userid = ?';
        $params[] = $userid;
    }
    if ($postid > -1) {
        if ($select != '')
            $select .= ' AND ';
        $select .= 'postid = ?';
        $params[] = $postid;
    }
    if ($discussionid > -1) {
        if ($select != '')
            $select .= ' AND ';
        $select .= 'discussionid = ?';
        $params[] = $discussionid;
    }
    if ($forumlvid > -1) {
        if ($select != '')
            $select .= ' AND ';
        $select .= 'forumlvid = ?';
        $params[] = $forumlvid;
    }

    return $DB -> get_records_select('forumlv_read', $select, $params);
}

/**
 * Returns all read records for the provided user and discussion, indexed by postid.
 *
 * @global object
 * @param inti $userid
 * @param int $discussionid
 */
function forumlv_tp_get_discussion_read_records($userid, $discussionid) {
    global $DB;
    $select = 'userid = ? AND discussionid = ?';
    $fields = 'postid, firstread, lastread';
    return $DB -> get_records_select('forumlv_read', $select, array($userid, $discussionid), '', $fields);
}

/**
 * If its an old post, do nothing. If the record exists, the maintenance will clear it up later.
 *
 * @return bool
 */
function forumlv_tp_mark_post_read($userid, $post, $forumlvid) {
    if (!forumlv_tp_is_post_old($post)) {
        return forumlv_tp_add_read_record($userid, $post -> id);
    } else {
        return true;
    }
}

/**
 * Marks a whole forumlv as read, for a given user
 *
 * @global object
 * @global object
 * @param object $user
 * @param int $forumlvid
 * @param int|bool $groupid
 * @return bool
 */
function forumlv_tp_mark_forumlv_read($user, $forumlvid, $groupid = false) {
    global $CFG, $DB;

    $cutoffdate = time() - ($CFG -> forumlv_oldpostdays * 24 * 60 * 60);

    $groupsel = "";
    $params = array($user -> id, $forumlvid, $cutoffdate);

    if ($groupid !== false) {
        $groupsel = " AND (d.groupid = ? OR d.groupid = -1)";
        $params[] = $groupid;
    }

    $sql = "SELECT p.id
              FROM {forumlv_posts} p
                   LEFT JOIN {forumlv_discussions} d ON d.id = p.discussion
                   LEFT JOIN {forumlv_read} r        ON (r.postid = p.id AND r.userid = ?)
             WHERE d.forumlv = ?
                   AND p.modified >= ? AND r.id is NULL
                   $groupsel";

    if ($posts = $DB -> get_records_sql($sql, $params)) {
        $postids = array_keys($posts);
        return forumlv_tp_mark_posts_read($user, $postids);
    }

    return true;
}

/**
 * Marks a whole discussion as read, for a given user
 *
 * @global object
 * @global object
 * @param object $user
 * @param int $discussionid
 * @return bool
 */
function forumlv_tp_mark_discussion_read($user, $discussionid) {
    global $CFG, $DB;

    $cutoffdate = time() - ($CFG -> forumlv_oldpostdays * 24 * 60 * 60);

    $sql = "SELECT p.id
              FROM {forumlv_posts} p
                   LEFT JOIN {forumlv_read} r ON (r.postid = p.id AND r.userid = ?)
             WHERE p.discussion = ?
                   AND p.modified >= ? AND r.id is NULL";

    if ($posts = $DB -> get_records_sql($sql, array($user -> id, $discussionid, $cutoffdate))) {
        $postids = array_keys($posts);
        return forumlv_tp_mark_posts_read($user, $postids);
    }

    return true;
}

/**
 * @global object
 * @param int $userid
 * @param object $post
 */
function forumlv_tp_is_post_read($userid, $post) {
    global $DB;
    return (forumlv_tp_is_post_old($post) || $DB -> record_exists('forumlv_read', array('userid' => $userid, 'postid' => $post -> id)));
}

/**
 * @global object
 * @param object $post
 * @param int $time Defautls to time()
 */
function forumlv_tp_is_post_old($post, $time = null) {
    global $CFG;

    if (is_null($time)) {
        $time = time();
    }
    return ($post -> modified < ($time - ($CFG -> forumlv_oldpostdays * 24 * 3600)));
}

/**
 * Returns the count of records for the provided user and discussion.
 *
 * @global object
 * @global object
 * @param int $userid
 * @param int $discussionid
 * @return bool
 */
function forumlv_tp_count_discussion_read_records($userid, $discussionid) {
    global $CFG, $DB;

    $cutoffdate = isset($CFG -> forumlv_oldpostdays) ? (time() - ($CFG -> forumlv_oldpostdays * 24 * 60 * 60)) : 0;

    $sql = 'SELECT COUNT(DISTINCT p.id) ' . 'FROM {forumlv_discussions} d ' . 'LEFT JOIN {forumlv_read} r ON d.id = r.discussionid AND r.userid = ? ' . 'LEFT JOIN {forumlv_posts} p ON p.discussion = d.id ' . 'AND (p.modified < ? OR p.id = r.postid) ' . 'WHERE d.id = ? ';

    return ($DB -> count_records_sql($sql, array($userid, $cutoffdate, $discussionid)));
}

/**
 * Returns the count of records for the provided user and discussion.
 *
 * @global object
 * @global object
 * @param int $userid
 * @param int $discussionid
 * @return int
 */
function forumlv_tp_count_discussion_unread_posts($userid, $discussionid) {
    global $CFG, $DB;

    $cutoffdate = isset($CFG -> forumlv_oldpostdays) ? (time() - ($CFG -> forumlv_oldpostdays * 24 * 60 * 60)) : 0;

    $sql = 'SELECT COUNT(p.id) ' . 'FROM {forumlv_posts} p ' . 'LEFT JOIN {forumlv_read} r ON r.postid = p.id AND r.userid = ? ' . 'WHERE p.discussion = ? ' . 'AND p.modified >= ? AND r.id is NULL';

    return $DB -> count_records_sql($sql, array($userid, $discussionid, $cutoffdate));
}

/**
 * Returns the count of posts for the provided forumlv and [optionally] group.
 * @global object
 * @global object
 * @param int $forumlvid
 * @param int|bool $groupid
 * @return int
 */
function forumlv_tp_count_forumlv_posts($forumlvid, $groupid = false) {
    global $CFG, $DB;
    $params = array($forumlvid);
    $sql = 'SELECT COUNT(*) ' . 'FROM {forumlv_posts} fp,{forumlv_discussions} fd ' . 'WHERE fd.forumlv = ? AND fp.discussion = fd.id';
    if ($groupid !== false) {
        $sql .= ' AND (fd.groupid = ? OR fd.groupid = -1)';
        $params[] = $groupid;
    }
    $count = $DB -> count_records_sql($sql, $params);

    return $count;
}

/**
 * Returns the count of records for the provided user and forumlv and [optionally] group.
 * @global object
 * @global object
 * @param int $userid
 * @param int $forumlvid
 * @param int|bool $groupid
 * @return int
 */
function forumlv_tp_count_forumlv_read_records($userid, $forumlvid, $groupid = false) {
    global $CFG, $DB;

    $cutoffdate = time() - ($CFG -> forumlv_oldpostdays * 24 * 60 * 60);

    $groupsel = '';
    $params = array($userid, $forumlvid, $cutoffdate);
    if ($groupid !== false) {
        $groupsel = "AND (d.groupid = ? OR d.groupid = -1)";
        $params[] = $groupid;
    }

    $sql = "SELECT COUNT(p.id)
              FROM  {forumlv_posts} p
                    JOIN {forumlv_discussions} d ON d.id = p.discussion
                    LEFT JOIN {forumlv_read} r   ON (r.postid = p.id AND r.userid= ?)
              WHERE d.forumlv = ?
                    AND (p.modified < $cutoffdate OR (p.modified >= ? AND r.id IS NOT NULL))
                    $groupsel";

    return $DB -> get_field_sql($sql, $params);
}

/**
 * Returns the count of records for the provided user and course.
 * Please note that group access is ignored!
 *
 * @global object
 * @global object
 * @param int $userid
 * @param int $courseid
 * @return array
 */
function forumlv_tp_get_course_unread_posts($userid, $courseid) {
    global $CFG, $DB;

    $now = round(time(), -2);
    // db cache friendliness
    $cutoffdate = $now - ($CFG -> forumlv_oldpostdays * 24 * 60 * 60);
    $params = array($userid, $userid, $courseid, $cutoffdate);

    if (!empty($CFG -> forumlv_enabletimedposts)) {
        $timedsql = "AND d.timestart < ? AND (d.timeend = 0 OR d.timeend > ?)";
        $params[] = $now;
        $params[] = $now;
    } else {
        $timedsql = "";
    }

    $sql = "SELECT f.id, COUNT(p.id) AS unread
              FROM {forumlv_posts} p
                   JOIN {forumlv_discussions} d       ON d.id = p.discussion
                   JOIN {forumlv} f                   ON f.id = d.forumlv
                   JOIN {course} c                  ON c.id = f.course
                   LEFT JOIN {forumlv_read} r         ON (r.postid = p.id AND r.userid = ?)
                   LEFT JOIN {forumlv_track_prefs} tf ON (tf.userid = ? AND tf.forumlvid = f.id)
             WHERE f.course = ?
                   AND p.modified >= ? AND r.id is NULL
                   AND (f.trackingtype = " . FORUMLV_TRACKING_ON . "
                        OR (f.trackingtype = " . FORUMLV_TRACKING_OPTIONAL . " AND tf.id IS NULL))
                   $timedsql
          GROUP BY f.id";

    if ($return = $DB -> get_records_sql($sql, $params)) {
        return $return;
    }

    return array();
}

/**
 * Returns the count of records for the provided user and forumlv and [optionally] group.
 *
 * @global object
 * @global object
 * @global object
 * @param object $cm
 * @param object $course
 * @return int
 */
function forumlv_tp_count_forumlv_unread_posts($cm, $course) {
    global $CFG, $USER, $DB;

    static $readcache = array();

    $forumlvid = $cm -> instance;

    if (!isset($readcache[$course -> id])) {
        $readcache[$course -> id] = array();
        if ($counts = forumlv_tp_get_course_unread_posts($USER -> id, $course -> id)) {
            foreach ($counts as $count) {
                $readcache[$course -> id][$count -> id] = $count -> unread;
            }
        }
    }

    if (empty($readcache[$course -> id][$forumlvid])) {
        // no need to check group mode ;-)
        return 0;
    }

    $groupmode = groups_get_activity_groupmode($cm, $course);

    if ($groupmode != SEPARATEGROUPS) {
        return $readcache[$course -> id][$forumlvid];
    }

    if (has_capability('moodle/site:accessallgroups', context_module::instance($cm -> id))) {
        return $readcache[$course -> id][$forumlvid];
    }

    require_once ($CFG -> dirroot . '/course/lib.php');

    $modinfo = get_fast_modinfo($course);
    if (is_null($modinfo -> groups)) {
        $modinfo -> groups = groups_get_user_groups($course -> id, $USER -> id);
    }

    $mygroups = $modinfo -> groups[$cm -> groupingid];

    // add all groups posts
    if (empty($mygroups)) {
        $mygroups = array(-1 => -1);
    } else {
        $mygroups[-1] = -1;
    }

    list($groups_sql, $groups_params) = $DB -> get_in_or_equal($mygroups);

    $now = round(time(), -2);
    // db cache friendliness
    $cutoffdate = $now - ($CFG -> forumlv_oldpostdays * 24 * 60 * 60);
    $params = array($USER -> id, $forumlvid, $cutoffdate);

    if (!empty($CFG -> forumlv_enabletimedposts)) {
        $timedsql = "AND d.timestart < ? AND (d.timeend = 0 OR d.timeend > ?)";
        $params[] = $now;
        $params[] = $now;
    } else {
        $timedsql = "";
    }

    $params = array_merge($params, $groups_params);

    $sql = "SELECT COUNT(p.id)
              FROM {forumlv_posts} p
                   JOIN {forumlv_discussions} d ON p.discussion = d.id
                   LEFT JOIN {forumlv_read} r   ON (r.postid = p.id AND r.userid = ?)
             WHERE d.forumlv = ?
                   AND p.modified >= ? AND r.id is NULL
                   $timedsql
                   AND d.groupid $groups_sql";

    return $DB -> get_field_sql($sql, $params);
}

/**
 * Deletes read records for the specified index. At least one parameter must be specified.
 *
 * @global object
 * @param int $userid
 * @param int $postid
 * @param int $discussionid
 * @param int $forumlvid
 * @return bool
 */
function forumlv_tp_delete_read_records($userid = -1, $postid = -1, $discussionid = -1, $forumlvid = -1) {
    global $DB;
    $params = array();

    $select = '';
    if ($userid > -1) {
        if ($select != '')
            $select .= ' AND ';
        $select .= 'userid = ?';
        $params[] = $userid;
    }
    if ($postid > -1) {
        if ($select != '')
            $select .= ' AND ';
        $select .= 'postid = ?';
        $params[] = $postid;
    }
    if ($discussionid > -1) {
        if ($select != '')
            $select .= ' AND ';
        $select .= 'discussionid = ?';
        $params[] = $discussionid;
    }
    if ($forumlvid > -1) {
        if ($select != '')
            $select .= ' AND ';
        $select .= 'forumlvid = ?';
        $params[] = $forumlvid;
    }
    if ($select == '') {
        return false;
    } else {
        return $DB -> delete_records_select('forumlv_read', $select, $params);
    }
}

/**
 * Get a list of forumlvs not tracked by the user.
 *
 * @global object
 * @global object
 * @param int $userid The id of the user to use.
 * @param int $courseid The id of the course being checked.
 * @return mixed An array indexed by forumlv id, or false.
 */
function forumlv_tp_get_untracked_forumlvs($userid, $courseid) {
    global $CFG, $DB;

    $sql = "SELECT f.id
              FROM {forumlv} f
                   LEFT JOIN {forumlv_track_prefs} ft ON (ft.forumlvid = f.id AND ft.userid = ?)
             WHERE f.course = ?
                   AND (f.trackingtype = " . FORUMLV_TRACKING_OFF . "
                        OR (f.trackingtype = " . FORUMLV_TRACKING_OPTIONAL . " AND ft.id IS NOT NULL))";

    if ($forumlvs = $DB -> get_records_sql($sql, array($userid, $courseid))) {
        foreach ($forumlvs as $forumlv) {
            $forumlvs[$forumlv -> id] = $forumlv;
        }
        return $forumlvs;

    } else {
        return array();
    }
}

/**
 * Determine if a user can track forumlvs and optionally a particular forumlv.
 * Checks the site settings, the user settings and the forumlv settings (if
 * requested).
 *
 * @global object
 * @global object
 * @global object
 * @param mixed $forumlv The forumlv object to test, or the int id (optional).
 * @param mixed $userid The user object to check for (optional).
 * @return boolean
 */
function forumlv_tp_can_track_forumlvs($forumlv = false, $user = false) {
    global $USER, $CFG, $DB;

    // if possible, avoid expensive
    // queries
    if (empty($CFG -> forumlv_trackreadposts)) {
        return false;
    }

    if ($user === false) {
        $user = $USER;
    }

    if (isguestuser($user) or empty($user -> id)) {
        return false;
    }

    if ($forumlv === false) {
        // general abitily to track forumlvs
        //@lvs corrigido renomeação :  o certo é trackforums e não trackforumlvs
        return (bool)$user -> trackforums;
    }

    // Work toward always passing an object...
    if (is_numeric($forumlv)) {
        debugging('Better use proper forumlv object.', DEBUG_DEVELOPER);
        $forumlv = $DB -> get_record('forumlv', array('id' => $forumlv), '', 'id,trackingtype');
    }

    $forumlvallows = ($forumlv -> trackingtype == FORUMLV_TRACKING_OPTIONAL);
    $forumlvforced = ($forumlv -> trackingtype == FORUMLV_TRACKING_ON);
    //@lvs corrigido renomeação : o certo é trackforums e não trackforumlvs
    return ($forumlvforced || $forumlvallows) && !empty($user -> trackforums);
}

/**
 * Tells whether a specific forumlv is tracked by the user. A user can optionally
 * be specified. If not specified, the current user is assumed.
 *
 * @global object
 * @global object
 * @global object
 * @param mixed $forumlv If int, the id of the forumlv being checked; if object, the forumlv object
 * @param int $userid The id of the user being checked (optional).
 * @return boolean
 */
function forumlv_tp_is_tracked($forumlv, $user = false) {
    global $USER, $CFG, $DB;

    if ($user === false) {
        $user = $USER;
    }

    if (isguestuser($user) or empty($user -> id)) {
        return false;
    }

    // Work toward always passing an object...
    if (is_numeric($forumlv)) {
        debugging('Better use proper forumlv object.', DEBUG_DEVELOPER);
        $forumlv = $DB -> get_record('forumlv', array('id' => $forumlv));
    }

    if (!forumlv_tp_can_track_forumlvs($forumlv, $user)) {
        return false;
    }

    $forumlvallows = ($forumlv -> trackingtype == FORUMLV_TRACKING_OPTIONAL);
    $forumlvforced = ($forumlv -> trackingtype == FORUMLV_TRACKING_ON);

    return $forumlvforced || ($forumlvallows && $DB -> get_record('forumlv_track_prefs', array('userid' => $user -> id, 'forumlvid' => $forumlv -> id)) === false);
}

/**
 * @global object
 * @global object
 * @param int $forumlvid
 * @param int $userid
 */
function forumlv_tp_start_tracking($forumlvid, $userid = false) {
    global $USER, $DB;

    if ($userid === false) {
        $userid = $USER -> id;
    }

    return $DB -> delete_records('forumlv_track_prefs', array('userid' => $userid, 'forumlvid' => $forumlvid));
}

/**
 * @global object
 * @global object
 * @param int $forumlvid
 * @param int $userid
 */
function forumlv_tp_stop_tracking($forumlvid, $userid = false) {
    global $USER, $DB;

    if ($userid === false) {
        $userid = $USER -> id;
    }

    if (!$DB -> record_exists('forumlv_track_prefs', array('userid' => $userid, 'forumlvid' => $forumlvid))) {
        $track_prefs = new stdClass();
        $track_prefs -> userid = $userid;
        $track_prefs -> forumlvid = $forumlvid;
        $DB -> insert_record('forumlv_track_prefs', $track_prefs);
    }

    return forumlv_tp_delete_read_records($userid, -1, -1, $forumlvid);
}

/**
 * Clean old records from the forumlv_read table.
 * @global object
 * @global object
 * @return void
 */
function forumlv_tp_clean_read_records() {
    global $CFG, $DB;

    if (!isset($CFG -> forumlv_oldpostdays)) {
        return;
    }
    // Look for records older than the cutoffdate that are still in the forumlv_read table.
    $cutoffdate = time() - ($CFG -> forumlv_oldpostdays * 24 * 60 * 60);

    //first get the oldest tracking present - we need tis to speedup the next delete query
    $sql = "SELECT MIN(fp.modified) AS first
              FROM {forumlv_posts} fp
                   JOIN {forumlv_read} fr ON fr.postid=fp.id";
    if (!$first = $DB -> get_field_sql($sql)) {
        // nothing to delete;
        return;
    }

    // now delete old tracking info
    $sql = "DELETE
              FROM {forumlv_read}
             WHERE postid IN (SELECT fp.id
                                FROM {forumlv_posts} fp
                               WHERE fp.modified >= ? AND fp.modified < ?)";
    $DB -> execute($sql, array($first, $cutoffdate));
}

/**
 * Sets the last post for a given discussion
 *
 * @global object
 * @global object
 * @param into $discussionid
 * @return bool|int
 **/
function forumlv_discussion_update_last_post($discussionid) {
    global $CFG, $DB;

    // Check the given discussion exists
    if (!$DB -> record_exists('forumlv_discussions', array('id' => $discussionid))) {
        return false;
    }

    // Use SQL to find the last post for this discussion
    $sql = "SELECT id, userid, modified
              FROM {forumlv_posts}
             WHERE discussion=?
             ORDER BY modified DESC";

    // Lets go find the last post
    if (($lastposts = $DB -> get_records_sql($sql, array($discussionid), 0, 1))) {
        $lastpost = reset($lastposts);
        $discussionobject = new stdClass();
        $discussionobject -> id = $discussionid;
        $discussionobject -> usermodified = $lastpost -> userid;
        $discussionobject -> timemodified = $lastpost -> modified;
        $DB -> update_record('forumlv_discussions', $discussionobject);
        return $lastpost -> id;
    }

    // To get here either we couldn't find a post for the discussion (weird)
    // or we couldn't update the discussion record (weird x2)
    return false;
}

/**
 * @return array
 */
function forumlv_get_view_actions() {
    return array('view discussion', 'search', 'forumlv', 'forumlvs', 'subscribers', 'view forumlv');
}

/**
 * @return array
 */
function forumlv_get_post_actions() {
    return array('add discussion', 'add post', 'delete discussion', 'delete post', 'move discussion', 'prune post', 'update post');
}

/**
 * Returns a warning object if a user has reached the number of posts equal to
 * the warning/blocking setting, or false if there is no warning to show.
 *
 * @param int|stdClass $forumlv the forumlv id or the forumlv object
 * @param stdClass $cm the course module
 * @return stdClass|bool returns an object with the warning information, else
 *         returns false if no warning is required.
 */
function forumlv_check_throttling($forumlv, $cm = null) {
    global $CFG, $DB, $USER;

    if (is_numeric($forumlv)) {
        $forumlv = $DB -> get_record('forumlv', array('id' => $forumlv), '*', MUST_EXIST);
    }

    if (!is_object($forumlv)) {
        return false;
        // This is broken.
    }

    if (!$cm) {
        $cm = get_coursemodule_from_instance('forumlv', $forumlv -> id, $forumlv -> course, false, MUST_EXIST);
    }

    if (empty($forumlv -> blockafter)) {
        return false;
    }

    if (empty($forumlv -> blockperiod)) {
        return false;
    }

    $modcontext = context_module::instance($cm -> id);
    if (has_capability('mod/forumlv:postwithoutthrottling', $modcontext)) {
        return false;
    }

    // Get the number of posts in the last period we care about.
    $timenow = time();
    $timeafter = $timenow - $forumlv -> blockperiod;
    $numposts = $DB -> count_records_sql('SELECT COUNT(p.id) FROM {forumlv_posts} p
                                        JOIN {forumlv_discussions} d
                                        ON p.discussion = d.id WHERE d.forumlv = ?
                                        AND p.userid = ? AND p.created > ?', array($forumlv -> id, $USER -> id, $timeafter));

    $a = new stdClass();
    $a -> blockafter = $forumlv -> blockafter;
    $a -> numposts = $numposts;
    $a -> blockperiod = get_string('secondstotime' . $forumlv -> blockperiod);

    if ($forumlv -> blockafter <= $numposts) {
        $warning = new stdClass();
        $warning -> canpost = false;
        $warning -> errorcode = 'forumlvblockingtoomanyposts';
        $warning -> module = 'error';
        $warning -> additional = $a;
        $warning -> link = $CFG -> wwwroot . '/mod/forumlv/view.php?f=' . $forumlv -> id;

        return $warning;
    }

    if ($forumlv -> warnafter <= $numposts) {
        $warning = new stdClass();
        $warning -> canpost = true;
        $warning -> errorcode = 'forumlvblockingalmosttoomanyposts';
        $warning -> module = 'forumlv';
        $warning -> additional = $a;
        $warning -> link = null;

        return $warning;
    }
}

/**
 * Throws an error if the user is no longer allowed to post due to having reached
 * or exceeded the number of posts specified in 'Post threshold for blocking'
 * setting.
 *
 * @since Moodle 2.5
 * @param stdClass $thresholdwarning the warning information returned
 *        from the function forumlv_check_throttling.
 */
function forumlv_check_blocking_threshold($thresholdwarning) {
    if (!empty($thresholdwarning) && !$thresholdwarning -> canpost) {
        print_error($thresholdwarning -> errorcode, $thresholdwarning -> module, $thresholdwarning -> link, $thresholdwarning -> additional);
    }
}

/**
 * Removes all grades from gradebook
 *
 * @global object
 * @global object
 * @param int $courseid
 * @param string $type optional
 */
function forumlv_reset_gradebook($courseid, $type = '') {
    global $CFG, $DB;

    $wheresql = '';
    $params = array($courseid);
    if ($type) {
        $wheresql = "AND f.type=?";
        $params[] = $type;
    }

    $sql = "SELECT f.*, cm.idnumber as cmidnumber, f.course as courseid
              FROM {forumlv} f, {course_modules} cm, {modules} m
             WHERE m.name='forumlv' AND m.id=cm.module AND cm.instance=f.id AND f.course=? $wheresql";

    if ($forumlvs = $DB -> get_records_sql($sql, $params)) {
        foreach ($forumlvs as $forumlv) {
            forumlv_grade_item_update($forumlv, 'reset');
        }
    }
}

/**
 * This function is used by the reset_course_userdata function in moodlelib.
 * This function will remove all posts from the specified forumlv
 * and clean up any related data.
 *
 * @global object
 * @global object
 * @param $data the data submitted from the reset course.
 * @return array status array
 */
function forumlv_reset_userdata($data) {
    global $CFG, $DB;
    require_once ($CFG -> dirroot . '/rating/lib.php');

    $componentstr = get_string('modulenameplural', 'forumlv');
    $status = array();

    $params = array($data -> courseid);

    $removeposts = false;
    $typesql = "";
    if (!empty($data -> reset_forumlv_all)) {
        $removeposts = true;
        $typesstr = get_string('resetforumlvsall', 'forumlv');
        $types = array();
    } else if (!empty($data -> reset_forumlv_types)) {
        $removeposts = true;
        $typesql = "";
        $types = array();
        $forumlv_types_all = forumlv_get_forumlv_types_all();
        foreach ($data->reset_forumlv_types as $type) {
            if (!array_key_exists($type, $forumlv_types_all)) {
                continue;
            }
            $typesql .= " AND f.type=?";
            $types[] = $forumlv_types_all[$type];
            $params[] = $type;
        }
        $typesstr = get_string('resetforumlvs', 'forumlv') . ': ' . implode(', ', $types);
    }
    $alldiscussionssql = "SELECT fd.id
                            FROM {forumlv_discussions} fd, {forumlv} f
                           WHERE f.course=? AND f.id=fd.forumlv";

    $allforumlvssql = "SELECT f.id
                            FROM {forumlv} f
                           WHERE f.course=?";

    $allpostssql = "SELECT fp.id
                            FROM {forumlv_posts} fp, {forumlv_discussions} fd, {forumlv} f
                           WHERE f.course=? AND f.id=fd.forumlv AND fd.id=fp.discussion";

    $forumlvssql = $forumlvs = $rm = null;

    if ($removeposts || !empty($data -> reset_forumlv_ratings)) {
        $forumlvssql = "$allforumlvssql $typesql";
        $forumlvs = $forumlvs = $DB -> get_records_sql($forumlvssql, $params);
        $rm = new rating_manager();
        $ratingdeloptions = new stdClass;
        $ratingdeloptions -> component = 'mod_forumlv';
        $ratingdeloptions -> ratingarea = 'post';
    }

    if ($removeposts) {
        $discussionssql = "$alldiscussionssql $typesql";
        $postssql = "$allpostssql $typesql";

        // now get rid of all attachments
        $fs = get_file_storage();
        if ($forumlvs) {
            foreach ($forumlvs as $forumlvid => $unused) {
                if (!$cm = get_coursemodule_from_instance('forumlv', $forumlvid)) {
                    continue;
                }
                $context = context_module::instance($cm -> id);
                $fs -> delete_area_files($context -> id, 'mod_forumlv', 'attachment');
                $fs -> delete_area_files($context -> id, 'mod_forumlv', 'post');

                //remove ratings
                $ratingdeloptions -> contextid = $context -> id;
                $rm -> delete_ratings($ratingdeloptions);
            }
        }

        // first delete all read flags
        $DB -> delete_records_select('forumlv_read', "forumlvid IN ($forumlvssql)", $params);

        // remove tracking prefs
        $DB -> delete_records_select('forumlv_track_prefs', "forumlvid IN ($forumlvssql)", $params);

        // remove posts from queue
        $DB -> delete_records_select('forumlv_queue', "discussionid IN ($discussionssql)", $params);

        // all posts - initial posts must be kept in single simple discussion forumlvs
        $DB -> delete_records_select('forumlv_posts', "discussion IN ($discussionssql) AND parent <> 0", $params);
        // first all children
        $DB -> delete_records_select('forumlv_posts', "discussion IN ($discussionssql AND f.type <> 'single') AND parent = 0", $params);
        // now the initial posts for non single simple

        // finally all discussions except single simple forumlvs
        $DB -> delete_records_select('forumlv_discussions', "forumlv IN ($forumlvssql AND f.type <> 'single')", $params);

        // remove all grades from gradebook
        if (empty($data -> reset_gradebook_grades)) {
            if (empty($types)) {
                forumlv_reset_gradebook($data -> courseid);
            } else {
                foreach ($types as $type) {
                    forumlv_reset_gradebook($data -> courseid, $type);
                }
            }
        }

        $status[] = array('component' => $componentstr, 'item' => $typesstr, 'error' => false);
    }

    // remove all ratings in this course's forumlvs
    if (!empty($data -> reset_forumlv_ratings)) {
        if ($forumlvs) {
            foreach ($forumlvs as $forumlvid => $unused) {
                if (!$cm = get_coursemodule_from_instance('forumlv', $forumlvid)) {
                    continue;
                }
                $context = context_module::instance($cm -> id);

                //remove ratings
                $ratingdeloptions -> contextid = $context -> id;
                $rm -> delete_ratings($ratingdeloptions);
            }
        }

        // remove all grades from gradebook
        if (empty($data -> reset_gradebook_grades)) {
            forumlv_reset_gradebook($data -> courseid);
        }
    }

    // remove all subscriptions unconditionally - even for users still enrolled in course
    if (!empty($data -> reset_forumlv_subscriptions)) {
        $DB -> delete_records_select('forumlv_subscriptions', "forumlv IN ($allforumlvssql)", $params);
        $status[] = array('component' => $componentstr, 'item' => get_string('resetsubscriptions', 'forumlv'), 'error' => false);
    }

    // remove all tracking prefs unconditionally - even for users still enrolled in course
    if (!empty($data -> reset_forumlv_track_prefs)) {
        $DB -> delete_records_select('forumlv_track_prefs', "forumlvid IN ($allforumlvssql)", $params);
        $status[] = array('component' => $componentstr, 'item' => get_string('resettrackprefs', 'forumlv'), 'error' => false);
    }

    /// updating dates - shift may be negative too
    if ($data -> timeshift) {
        shift_course_mod_dates('forumlv', array('assesstimestart', 'assesstimefinish'), $data -> timeshift, $data -> courseid);
        $status[] = array('component' => $componentstr, 'item' => get_string('datechanged'), 'error' => false);
    }

    return $status;
}

/**
 * Called by course/reset.php
 *
 * @param $mform form passed by reference
 */
function forumlv_reset_course_form_definition(&$mform) {
    $mform -> addElement('header', 'forumlvheader', get_string('modulenameplural', 'forumlv'));

    $mform -> addElement('checkbox', 'reset_forumlv_all', get_string('resetforumlvsall', 'forumlv'));

    $mform -> addElement('select', 'reset_forumlv_types', get_string('resetforumlvs', 'forumlv'), forumlv_get_forumlv_types_all(), array('multiple' => 'multiple'));
    $mform -> setAdvanced('reset_forumlv_types');
    $mform -> disabledIf('reset_forumlv_types', 'reset_forumlv_all', 'checked');

    $mform -> addElement('checkbox', 'reset_forumlv_subscriptions', get_string('resetsubscriptions', 'forumlv'));
    $mform -> setAdvanced('reset_forumlv_subscriptions');

    $mform -> addElement('checkbox', 'reset_forumlv_track_prefs', get_string('resettrackprefs', 'forumlv'));
    $mform -> setAdvanced('reset_forumlv_track_prefs');
    $mform -> disabledIf('reset_forumlv_track_prefs', 'reset_forumlv_all', 'checked');

    $mform -> addElement('checkbox', 'reset_forumlv_ratings', get_string('deleteallratings'));
    $mform -> disabledIf('reset_forumlv_ratings', 'reset_forumlv_all', 'checked');
}

/**
 * Course reset form defaults.
 * @return array
 */
function forumlv_reset_course_form_defaults($course) {
    return array('reset_forumlv_all' => 1, 'reset_forumlv_subscriptions' => 0, 'reset_forumlv_track_prefs' => 0, 'reset_forumlv_ratings' => 1);
}

/**
 * Converts a forumlv to use the Roles System
 *
 * @global object
 * @global object
 * @param object $forumlv        a forumlv object with the same attributes as a record
 *                        from the forumlv database table
 * @param int $forumlvmodid   the id of the forumlv module, from the modules table
 * @param array $teacherroles array of roles that have archetype teacher
 * @param array $studentroles array of roles that have archetype student
 * @param array $guestroles   array of roles that have archetype guest
 * @param int $cmid         the course_module id for this forumlv instance
 * @return boolean      forumlv was converted or not
 */
function forumlv_convert_to_roles($forumlv, $forumlvmodid, $teacherroles = array(), $studentroles = array(), $guestroles = array(), $cmid = NULL) {

    global $CFG, $DB, $OUTPUT;

    if (!isset($forumlv -> open) && !isset($forumlv -> assesspublic)) {
        // We assume that this forumlv has already been converted to use the
        // Roles System. Columns forumlv.open and forumlv.assesspublic get dropped
        // once the forumlv module has been upgraded to use Roles.
        return false;
    }

    if ($forumlv -> type == 'teacher') {

        // Teacher forumlvs should be converted to normal forumlvs that
        // use the Roles System to implement the old behavior.
        // Note:
        //   Seems that teacher forumlvs were never backed up in 1.6 since they
        //   didn't have an entry in the course_modules table.
        require_once ($CFG -> dirroot . '/course/lib.php');

        if ($DB -> count_records('forumlv_discussions', array('forumlv' => $forumlv -> id)) == 0) {
            // Delete empty teacher forumlvs.
            $DB -> delete_records('forumlv', array('id' => $forumlv -> id));
        } else {
            // Create a course module for the forumlv and assign it to
            // section 0 in the course.
            $mod = new stdClass();
            $mod -> course = $forumlv -> course;
            $mod -> module = $forumlvmodid;
            $mod -> instance = $forumlv -> id;
            $mod -> section = 0;
            $mod -> visible = 0;
            // Hide the forumlv
            $mod -> visibleold = 0;
            // Hide the forumlv
            $mod -> groupmode = 0;

            if (!$cmid = add_course_module($mod)) {
                print_error('cannotcreateinstanceforteacher', 'forumlv');
            } else {
                $sectionid = course_add_cm_to_section($forumlv -> course, $mod -> coursemodule, 0);
            }

            // Change the forumlv type to general.
            $forumlv -> type = 'general';
            $DB -> update_record('forumlv', $forumlv);

            $context = context_module::instance($cmid);

            // Create overrides for default student and guest roles (prevent).
            foreach ($studentroles as $studentrole) {
                assign_capability('mod/forumlv:viewdiscussion', CAP_PREVENT, $studentrole -> id, $context -> id);
                assign_capability('mod/forumlv:viewhiddentimedposts', CAP_PREVENT, $studentrole -> id, $context -> id);
                assign_capability('mod/forumlv:startdiscussion', CAP_PREVENT, $studentrole -> id, $context -> id);
                assign_capability('mod/forumlv:replypost', CAP_PREVENT, $studentrole -> id, $context -> id);
                assign_capability('mod/forumlv:viewrating', CAP_PREVENT, $studentrole -> id, $context -> id);
                assign_capability('mod/forumlv:viewanyrating', CAP_PREVENT, $studentrole -> id, $context -> id);
                assign_capability('mod/forumlv:rate', CAP_PREVENT, $studentrole -> id, $context -> id);
                assign_capability('mod/forumlv:createattachment', CAP_PREVENT, $studentrole -> id, $context -> id);
                assign_capability('mod/forumlv:deleteownpost', CAP_PREVENT, $studentrole -> id, $context -> id);
                assign_capability('mod/forumlv:deleteanypost', CAP_PREVENT, $studentrole -> id, $context -> id);
                assign_capability('mod/forumlv:splitdiscussions', CAP_PREVENT, $studentrole -> id, $context -> id);
                assign_capability('mod/forumlv:movediscussions', CAP_PREVENT, $studentrole -> id, $context -> id);
                assign_capability('mod/forumlv:editanypost', CAP_PREVENT, $studentrole -> id, $context -> id);
                assign_capability('mod/forumlv:viewqandawithoutposting', CAP_PREVENT, $studentrole -> id, $context -> id);
                assign_capability('mod/forumlv:viewsubscribers', CAP_PREVENT, $studentrole -> id, $context -> id);
                assign_capability('mod/forumlv:managesubscriptions', CAP_PREVENT, $studentrole -> id, $context -> id);
                assign_capability('mod/forumlv:postwithoutthrottling', CAP_PREVENT, $studentrole -> id, $context -> id);
            }
            foreach ($guestroles as $guestrole) {
                assign_capability('mod/forumlv:viewdiscussion', CAP_PREVENT, $guestrole -> id, $context -> id);
                assign_capability('mod/forumlv:viewhiddentimedposts', CAP_PREVENT, $guestrole -> id, $context -> id);
                assign_capability('mod/forumlv:startdiscussion', CAP_PREVENT, $guestrole -> id, $context -> id);
                assign_capability('mod/forumlv:replypost', CAP_PREVENT, $guestrole -> id, $context -> id);
                assign_capability('mod/forumlv:viewrating', CAP_PREVENT, $guestrole -> id, $context -> id);
                assign_capability('mod/forumlv:viewanyrating', CAP_PREVENT, $guestrole -> id, $context -> id);
                assign_capability('mod/forumlv:rate', CAP_PREVENT, $guestrole -> id, $context -> id);
                assign_capability('mod/forumlv:createattachment', CAP_PREVENT, $guestrole -> id, $context -> id);
                assign_capability('mod/forumlv:deleteownpost', CAP_PREVENT, $guestrole -> id, $context -> id);
                assign_capability('mod/forumlv:deleteanypost', CAP_PREVENT, $guestrole -> id, $context -> id);
                assign_capability('mod/forumlv:splitdiscussions', CAP_PREVENT, $guestrole -> id, $context -> id);
                assign_capability('mod/forumlv:movediscussions', CAP_PREVENT, $guestrole -> id, $context -> id);
                assign_capability('mod/forumlv:editanypost', CAP_PREVENT, $guestrole -> id, $context -> id);
                assign_capability('mod/forumlv:viewqandawithoutposting', CAP_PREVENT, $guestrole -> id, $context -> id);
                assign_capability('mod/forumlv:viewsubscribers', CAP_PREVENT, $guestrole -> id, $context -> id);
                assign_capability('mod/forumlv:managesubscriptions', CAP_PREVENT, $guestrole -> id, $context -> id);
                assign_capability('mod/forumlv:postwithoutthrottling', CAP_PREVENT, $guestrole -> id, $context -> id);
            }
        }
    } else {
        // Non-teacher forumlv.

        if (empty($cmid)) {
            // We were not given the course_module id. Try to find it.
            if (!$cm = get_coursemodule_from_instance('forumlv', $forumlv -> id)) {
                echo $OUTPUT -> notification('Could not get the course module for the forumlv');
                return false;
            } else {
                $cmid = $cm -> id;
            }
        }
        $context = context_module::instance($cmid);

        // $forumlv->open defines what students can do:
        //   0 = No discussions, no replies
        //   1 = No discussions, but replies are allowed
        //   2 = Discussions and replies are allowed
        switch ($forumlv->open) {
            case 0 :
                foreach ($studentroles as $studentrole) {
                    assign_capability('mod/forumlv:startdiscussion', CAP_PREVENT, $studentrole -> id, $context -> id);
                    assign_capability('mod/forumlv:replypost', CAP_PREVENT, $studentrole -> id, $context -> id);
                }
                break;
            case 1 :
                foreach ($studentroles as $studentrole) {
                    assign_capability('mod/forumlv:startdiscussion', CAP_PREVENT, $studentrole -> id, $context -> id);
                    assign_capability('mod/forumlv:replypost', CAP_ALLOW, $studentrole -> id, $context -> id);
                }
                break;
            case 2 :
                foreach ($studentroles as $studentrole) {
                    assign_capability('mod/forumlv:startdiscussion', CAP_ALLOW, $studentrole -> id, $context -> id);
                    assign_capability('mod/forumlv:replypost', CAP_ALLOW, $studentrole -> id, $context -> id);
                }
                break;
        }

        // $forumlv->assessed defines whether forumlv rating is turned
        // on (1 or 2) and who can rate posts:
        //   1 = Everyone can rate posts
        //   2 = Only teachers can rate posts
        switch ($forumlv->assessed) {
            case 1 :
                foreach ($studentroles as $studentrole) {
                    assign_capability('mod/forumlv:rate', CAP_ALLOW, $studentrole -> id, $context -> id);
                }
                foreach ($teacherroles as $teacherrole) {
                    assign_capability('mod/forumlv:rate', CAP_ALLOW, $teacherrole -> id, $context -> id);
                }
                break;
            case 2 :
                foreach ($studentroles as $studentrole) {
                    assign_capability('mod/forumlv:rate', CAP_PREVENT, $studentrole -> id, $context -> id);
                }
                foreach ($teacherroles as $teacherrole) {
                    assign_capability('mod/forumlv:rate', CAP_ALLOW, $teacherrole -> id, $context -> id);
                }
                break;
        }

        // $forumlv->assesspublic defines whether students can see
        // everybody's ratings:
        //   0 = Students can only see their own ratings
        //   1 = Students can see everyone's ratings
        switch ($forumlv->assesspublic) {
            case 0 :
                foreach ($studentroles as $studentrole) {
                    assign_capability('mod/forumlv:viewanyrating', CAP_PREVENT, $studentrole -> id, $context -> id);
                }
                foreach ($teacherroles as $teacherrole) {
                    assign_capability('mod/forumlv:viewanyrating', CAP_ALLOW, $teacherrole -> id, $context -> id);
                }
                break;
            case 1 :
                foreach ($studentroles as $studentrole) {
                    assign_capability('mod/forumlv:viewanyrating', CAP_ALLOW, $studentrole -> id, $context -> id);
                }
                foreach ($teacherroles as $teacherrole) {
                    assign_capability('mod/forumlv:viewanyrating', CAP_ALLOW, $teacherrole -> id, $context -> id);
                }
                break;
        }

        if (empty($cm)) {
            $cm = $DB -> get_record('course_modules', array('id' => $cmid));
        }

        // $cm->groupmode:
        // 0 - No groups
        // 1 - Separate groups
        // 2 - Visible groups
        switch ($cm->groupmode) {
            case 0 :
                break;
            case 1 :
                foreach ($studentroles as $studentrole) {
                    assign_capability('moodle/site:accessallgroups', CAP_PREVENT, $studentrole -> id, $context -> id);
                }
                foreach ($teacherroles as $teacherrole) {
                    assign_capability('moodle/site:accessallgroups', CAP_ALLOW, $teacherrole -> id, $context -> id);
                }
                break;
            case 2 :
                foreach ($studentroles as $studentrole) {
                    assign_capability('moodle/site:accessallgroups', CAP_ALLOW, $studentrole -> id, $context -> id);
                }
                foreach ($teacherroles as $teacherrole) {
                    assign_capability('moodle/site:accessallgroups', CAP_ALLOW, $teacherrole -> id, $context -> id);
                }
                break;
        }
    }
    return true;
}

/**
 * Returns array of forumlv layout modes
 *
 * @return array
 */
function forumlv_get_layout_modes() {
    return array(FORUMLV_MODE_FLATOLDEST => get_string('modeflatoldestfirst', 'forumlv'), FORUMLV_MODE_FLATNEWEST => get_string('modeflatnewestfirst', 'forumlv'), FORUMLV_MODE_THREADED => get_string('modethreaded', 'forumlv'), FORUMLV_MODE_NESTED => get_string('modenested', 'forumlv'));
}

/**
 * Returns array of forumlv types chooseable on the forumlv editing form
 *
 * @return array
 */
function forumlv_get_forumlv_types() {
    return array('general' => get_string('generalforumlv', 'forumlv'), 'eachuser' => get_string('eachuserforumlv', 'forumlv'), 'single' => get_string('singleforumlv', 'forumlv'), 'qanda' => get_string('qandaforumlv', 'forumlv'), 'blog' => get_string('blogforumlv', 'forumlv'));
}

/**
 * Returns array of all forumlv layout modes
 *
 * @return array
 */
function forumlv_get_forumlv_types_all() {
    return array('news' => get_string('namenews', 'forumlv'), 'social' => get_string('namesocial', 'forumlv'), 'general' => get_string('generalforumlv', 'forumlv'), 'eachuser' => get_string('eachuserforumlv', 'forumlv'), 'single' => get_string('singleforumlv', 'forumlv'), 'qanda' => get_string('qandaforumlv', 'forumlv'), 'blog' => get_string('blogforumlv', 'forumlv'));
}

/**
 * Returns array of forumlv open modes
 *
 * @return array
 */
function forumlv_get_open_modes() {
    return array('2' => get_string('openmode2', 'forumlv'), '1' => get_string('openmode1', 'forumlv'), '0' => get_string('openmode0', 'forumlv'));
}

/**
 * Returns all other caps used in module
 *
 * @return array
 */
function forumlv_get_extra_capabilities() {
    return array('moodle/site:accessallgroups', 'moodle/site:viewfullnames', 'moodle/site:trustcontent', 'moodle/rating:view', 'moodle/rating:viewany', 'moodle/rating:viewall', 'moodle/rating:rate');
}

/**
 * This function is used to extend the global navigation by add forumlv nodes if there
 * is relevant content.
 *
 * @param navigation_node $navref
 * @param stdClass $course
 * @param stdClass $module
 * @param stdClass $cm
 */
/*************************************************
 function forumlv_extend_navigation($navref, $course, $module, $cm) {
 global $CFG, $OUTPUT, $USER;

 $limit = 5;

 $discussions = forumlv_get_discussions($cm,"d.timemodified DESC", false, -1, $limit);
 $discussioncount = forumlv_get_discussions_count($cm);
 if (!is_array($discussions) || count($discussions)==0) {
 return;
 }
 $discussionnode = $navref->add(get_string('discussions', 'forumlv').' ('.$discussioncount.')');
 $discussionnode->mainnavonly = true;
 $discussionnode->display = false; // Do not display on navigation (only on navbar)

 foreach ($discussions as $discussion) {
 $icon = new pix_icon('i/feedback', '');
 $url = new moodle_url('/mod/forumlv/discuss.php', array('d'=>$discussion->discussion));
 $discussionnode->add($discussion->subject, $url, navigation_node::TYPE_SETTING, null, null, $icon);
 }

 if ($discussioncount > count($discussions)) {
 if (!empty($navref->action)) {
 $url = $navref->action;
 } else {
 $url = new moodle_url('/mod/forumlv/view.php', array('id'=>$cm->id));
 }
 $discussionnode->add(get_string('viewalldiscussions', 'forumlv'), $url, navigation_node::TYPE_SETTING, null, null, $icon);
 }

 $index = 0;
 $recentposts = array();
 $lastlogin = time() - COURSE_MAX_RECENT_PERIOD;
 if (!isguestuser() and !empty($USER->lastcourseaccess[$course->id])) {
 if ($USER->lastcourseaccess[$course->id] > $lastlogin) {
 $lastlogin = $USER->lastcourseaccess[$course->id];
 }
 }
 forumlv_get_recent_mod_activity($recentposts, $index, $lastlogin, $course->id, $cm->id);

 if (is_array($recentposts) && count($recentposts)>0) {
 $recentnode = $navref->add(get_string('recentactivity').' ('.count($recentposts).')');
 $recentnode->mainnavonly = true;
 $recentnode->display = false;
 foreach ($recentposts as $post) {
 $icon = new pix_icon('i/feedback', '');
 $url = new moodle_url('/mod/forumlv/discuss.php', array('d'=>$post->content->discussion));
 $title = $post->content->subject."\n".userdate($post->timestamp, get_string('strftimerecent', 'langconfig'))."\n".$post->user->firstname.' '.$post->user->lastname;
 $recentnode->add($title, $url, navigation_node::TYPE_SETTING, null, null, $icon);
 }
 }
 }
 *************************/

/**
 * Adds module specific settings to the settings block
 *
 * @param settings_navigation $settings The settings navigation object
 * @param navigation_node $forumlvnode The node to add module settings to
 */
function forumlv_extend_settings_navigation(settings_navigation $settingsnav, navigation_node $forumlvnode) {
    global $USER, $PAGE, $CFG, $DB, $OUTPUT;

    $forumlvobject = $DB -> get_record("forumlv", array("id" => $PAGE -> cm -> instance));
    if (empty($PAGE -> cm -> context)) {
        $PAGE -> cm -> context = context_module::instance($PAGE -> cm -> instance);
    }

    // for some actions you need to be enrolled, beiing admin is not enough sometimes here
    $enrolled = is_enrolled($PAGE -> cm -> context, $USER, '', false);
    $activeenrolled = is_enrolled($PAGE -> cm -> context, $USER, '', true);

    $canmanage = has_capability('mod/forumlv:managesubscriptions', $PAGE -> cm -> context);
    $subscriptionmode = forumlv_get_forcesubscribed($forumlvobject);
    $cansubscribe = ($activeenrolled && $subscriptionmode != FORUMLV_FORCESUBSCRIBE && ($subscriptionmode != FORUMLV_DISALLOWSUBSCRIBE || $canmanage));

    if ($canmanage) {
        $mode = $forumlvnode -> add(get_string('subscriptionmode', 'forumlv'), null, navigation_node::TYPE_CONTAINER);

        $allowchoice = $mode -> add(get_string('subscriptionoptional', 'forumlv'), new moodle_url('/mod/forumlv/subscribe.php', array('id' => $forumlvobject -> id, 'mode' => FORUMLV_CHOOSESUBSCRIBE, 'sesskey' => sesskey())), navigation_node::TYPE_SETTING);
        $forceforever = $mode -> add(get_string("subscriptionforced", "forumlv"), new moodle_url('/mod/forumlv/subscribe.php', array('id' => $forumlvobject -> id, 'mode' => FORUMLV_FORCESUBSCRIBE, 'sesskey' => sesskey())), navigation_node::TYPE_SETTING);
        $forceinitially = $mode -> add(get_string("subscriptionauto", "forumlv"), new moodle_url('/mod/forumlv/subscribe.php', array('id' => $forumlvobject -> id, 'mode' => FORUMLV_INITIALSUBSCRIBE, 'sesskey' => sesskey())), navigation_node::TYPE_SETTING);
        $disallowchoice = $mode -> add(get_string('subscriptiondisabled', 'forumlv'), new moodle_url('/mod/forumlv/subscribe.php', array('id' => $forumlvobject -> id, 'mode' => FORUMLV_DISALLOWSUBSCRIBE, 'sesskey' => sesskey())), navigation_node::TYPE_SETTING);

        switch ($subscriptionmode) {
            case FORUMLV_CHOOSESUBSCRIBE :
                // 0
                $allowchoice -> action = null;
                $allowchoice -> add_class('activesetting');
                break;
            case FORUMLV_FORCESUBSCRIBE :
                // 1
                $forceforever -> action = null;
                $forceforever -> add_class('activesetting');
                break;
            case FORUMLV_INITIALSUBSCRIBE :
                // 2
                $forceinitially -> action = null;
                $forceinitially -> add_class('activesetting');
                break;
            case FORUMLV_DISALLOWSUBSCRIBE :
                // 3
                $disallowchoice -> action = null;
                $disallowchoice -> add_class('activesetting');
                break;
        }

    } else if ($activeenrolled) {

        switch ($subscriptionmode) {
            case FORUMLV_CHOOSESUBSCRIBE :
                // 0
                $notenode = $forumlvnode -> add(get_string('subscriptionoptional', 'forumlv'));
                break;
            case FORUMLV_FORCESUBSCRIBE :
                // 1
                $notenode = $forumlvnode -> add(get_string('subscriptionforced', 'forumlv'));
                break;
            case FORUMLV_INITIALSUBSCRIBE :
                // 2
                $notenode = $forumlvnode -> add(get_string('subscriptionauto', 'forumlv'));
                break;
            case FORUMLV_DISALLOWSUBSCRIBE :
                // 3
                $notenode = $forumlvnode -> add(get_string('subscriptiondisabled', 'forumlv'));
                break;
        }
    }

    if ($cansubscribe) {
        if (forumlv_is_subscribed($USER -> id, $forumlvobject)) {
            $linktext = get_string('unsubscribe', 'forumlv');
        } else {
            $linktext = get_string('subscribe', 'forumlv');
        }
        $url = new moodle_url('/mod/forumlv/subscribe.php', array('id' => $forumlvobject -> id, 'sesskey' => sesskey()));
        $forumlvnode -> add($linktext, $url, navigation_node::TYPE_SETTING);
    }

    if (has_capability('mod/forumlv:viewsubscribers', $PAGE -> cm -> context)) {
        $url = new moodle_url('/mod/forumlv/subscribers.php', array('id' => $forumlvobject -> id));
        $forumlvnode -> add(get_string('showsubscribers', 'forumlv'), $url, navigation_node::TYPE_SETTING);
    }

    if ($enrolled && forumlv_tp_can_track_forumlvs($forumlvobject)) {// keep tracking info for users with suspended enrolments
        if ($forumlvobject -> trackingtype != FORUMLV_TRACKING_OPTIONAL) {
            //tracking forced on or off in forumlv settings so dont provide a link here to change it
            //could add unclickable text like for forced subscription but not sure this justifies adding another menu item
        } else {
            if (forumlv_tp_is_tracked($forumlvobject)) {
                $linktext = get_string('notrackforumlv', 'forumlv');
            } else {
                $linktext = get_string('trackforumlv', 'forumlv');
            }
            $url = new moodle_url('/mod/forumlv/settracking.php', array('id' => $forumlvobject -> id));
            $forumlvnode -> add($linktext, $url, navigation_node::TYPE_SETTING);
        }
    }

    if (!isloggedin() && $PAGE -> course -> id == SITEID) {
        $userid =  guest_user() -> id;
    } else {
        $userid = $USER -> id;
    }

    $hascourseaccess = ($PAGE -> course -> id == SITEID) || can_access_course($PAGE -> course, $userid);
    $enablerssfeeds = !empty($CFG -> enablerssfeeds) && !empty($CFG -> forumlv_enablerssfeeds);

    if ($enablerssfeeds && $forumlvobject -> rsstype && $forumlvobject -> rssarticles && $hascourseaccess) {

        if (!function_exists('rss_get_url')) {
            require_once ("$CFG->libdir/rsslib.php");
        }

        if ($forumlvobject -> rsstype == 1) {
            $string = get_string('rsssubscriberssdiscussions', 'forumlv');
        } else {
            $string = get_string('rsssubscriberssposts', 'forumlv');
        }

        $url = new moodle_url(rss_get_url($PAGE -> cm -> context -> id, $userid, "mod_forumlv", $forumlvobject -> id));
        $forumlvnode -> add($string, $url, settings_navigation::TYPE_SETTING, null, null, new pix_icon('i/rss', ''));
    }
}

/**
 * Abstract class used by forumlv subscriber selection controls
 * @package mod-forumlv
 * @copyright 2009 Sam Hemelryk
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class forumlv_subscriber_selector_base extends user_selector_base {

    /**
     * The id of the forumlv this selector is being used for
     * @var int
     */
    protected $forumlvid = null;
    /**
     * The context of the forumlv this selector is being used for
     * @var object
     */
    protected $context = null;
    /**
     * The id of the current group
     * @var int
     */
    protected $currentgroup = null;

    /**
     * Constructor method
     * @param string $name
     * @param array $options
     */
    public function __construct($name, $options) {
        $options['accesscontext'] = $options['context'];
        parent::__construct($name, $options);
        if (isset($options['context'])) {
            $this -> context = $options['context'];
        }
        if (isset($options['currentgroup'])) {
            $this -> currentgroup = $options['currentgroup'];
        }
        if (isset($options['forumlvid'])) {
            $this -> forumlvid = $options['forumlvid'];
        }
    }

    /**
     * Returns an array of options to seralise and store for searches
     *
     * @return array
     */
    protected function get_options() {
        global $CFG;
        $options = parent::get_options();
        $options['file'] = substr(__FILE__, strlen($CFG -> dirroot . '/'));
        $options['context'] = $this -> context;
        $options['currentgroup'] = $this -> currentgroup;
        $options['forumlvid'] = $this -> forumlvid;
        return $options;
    }

}

/**
 * A user selector control for potential subscribers to the selected forumlv
 * @package mod-forumlv
 * @copyright 2009 Sam Hemelryk
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class forumlv_potential_subscriber_selector extends forumlv_subscriber_selector_base {
    /**
     * If set to true EVERYONE in this course is force subscribed to this forumlv
     * @var bool
     */
    protected $forcesubscribed = false;
    /**
     * Can be used to store existing subscribers so that they can be removed from
     * the potential subscribers list
     */
    protected $existingsubscribers = array();

    /**
     * Constructor method
     * @param string $name
     * @param array $options
     */
    public function __construct($name, $options) {
        parent::__construct($name, $options);
        if (isset($options['forcesubscribed'])) {
            $this -> forcesubscribed = true;
        }
    }

    /**
     * Returns an arary of options for this control
     * @return array
     */
    protected function get_options() {
        $options = parent::get_options();
        if ($this -> forcesubscribed === true) {
            $options['forcesubscribed'] = 1;
        }
        return $options;
    }

    /**
     * Finds all potential users
     *
     * Potential subscribers are all enroled users who are not already subscribed.
     *
     * @param string $search
     * @return array
     */
    public function find_users($search) {
        global $DB;

        $whereconditions = array();
        list($wherecondition, $params) = $this -> search_sql($search, 'u');
        if ($wherecondition) {
            $whereconditions[] = $wherecondition;
        }

        if (!$this -> forcesubscribed) {
            $existingids = array();
            foreach ($this->existingsubscribers as $group) {
                foreach ($group as $user) {
                    $existingids[$user -> id] = 1;
                }
            }
            if ($existingids) {
                list($usertest, $userparams) = $DB -> get_in_or_equal(array_keys($existingids), SQL_PARAMS_NAMED, 'existing', false);
                $whereconditions[] = 'u.id ' . $usertest;
                $params = array_merge($params, $userparams);
            }
        }

        if ($whereconditions) {
            $wherecondition = 'WHERE ' . implode(' AND ', $whereconditions);
        }

        list($esql, $eparams) = get_enrolled_sql($this -> context, '', $this -> currentgroup, true);
        $params = array_merge($params, $eparams);

        $fields = 'SELECT ' . $this -> required_fields_sql('u');
        $countfields = 'SELECT COUNT(u.id)';

        $sql = " FROM {user} u
                 JOIN ($esql) je ON je.id = u.id
                      $wherecondition";

        list($sort, $sortparams) = users_order_by_sql('u', $search, $this -> accesscontext);
        $order = ' ORDER BY ' . $sort;

        // Check to see if there are too many to show sensibly.
        if (!$this -> is_validating()) {
            $potentialmemberscount = $DB -> count_records_sql($countfields . $sql, $params);
            if ($potentialmemberscount > $this -> maxusersperpage) {
                return $this -> too_many_results($search, $potentialmemberscount);
            }
        }

        // If not, show them.
        $availableusers = $DB -> get_records_sql($fields . $sql . $order, array_merge($params, $sortparams));

        if (empty($availableusers)) {
            return array();
        }

        if ($this -> forcesubscribed) {
            return array(get_string("existingsubscribers", 'forumlv') => $availableusers);
        } else {
            return array(get_string("potentialsubscribers", 'forumlv') => $availableusers);
        }
    }

    /**
     * Sets the existing subscribers
     * @param array $users
     */
    public function set_existing_subscribers(array $users) {
        $this -> existingsubscribers = $users;
    }

    /**
     * Sets this forumlv as force subscribed or not
     */
    public function set_force_subscribed($setting = true) {
        $this -> forcesubscribed = true;
    }

}

/**
 * User selector control for removing subscribed users
 * @package mod-forumlv
 * @copyright 2009 Sam Hemelryk
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class forumlv_existing_subscriber_selector extends forumlv_subscriber_selector_base {

    /**
     * Finds all subscribed users
     *
     * @param string $search
     * @return array
     */
    public function find_users($search) {
        global $DB;
        list($wherecondition, $params) = $this -> search_sql($search, 'u');
        $params['forumlvid'] = $this -> forumlvid;

        // only active enrolled or everybody on the frontpage
        list($esql, $eparams) = get_enrolled_sql($this -> context, '', $this -> currentgroup, true);
        $fields = $this -> required_fields_sql('u');
        list($sort, $sortparams) = users_order_by_sql('u', $search, $this -> accesscontext);
        $params = array_merge($params, $eparams, $sortparams);

        $subscribers = $DB -> get_records_sql("SELECT $fields
                                               FROM {user} u
                                               JOIN ($esql) je ON je.id = u.id
                                               JOIN {forumlv_subscriptions} s ON s.userid = u.id
                                              WHERE $wherecondition AND s.forumlv = :forumlvid
                                           ORDER BY $sort", $params);

        return array(get_string("existingsubscribers", 'forumlv') => $subscribers);
    }

}

/**
 * Adds information about unread messages, that is only required for the course view page (and
 * similar), to the course-module object.
 * @param cm_info $cm Course-module object
 */
function forumlv_cm_info_view(cm_info $cm) {
    global $CFG;

    // Get tracking status (once per request)
    static $initialised;
    static $usetracking, $strunreadpostsone;
    if (!isset($initialised)) {
        if ($usetracking = forumlv_tp_can_track_forumlvs()) {
            $strunreadpostsone = get_string('unreadpostsone', 'forumlv');
        }
        $initialised = true;
    }

    if ($usetracking) {
        if ($unread = forumlv_tp_count_forumlv_unread_posts($cm, $cm -> get_course())) {
            $out = '<span class="unread"> <a href="' . $cm -> get_url() . '">';
            if ($unread == 1) {
                $out .= $strunreadpostsone;
            } else {
                $out .= get_string('unreadpostsnumber', 'forumlv', $unread);
            }
            $out .= '</a></span>';
            $cm -> set_after_link($out);
        }
    }
}

/**
 * Return a list of page types
 * @param string $pagetype current page type
 * @param stdClass $parentcontext Block's parent context
 * @param stdClass $currentcontext Current context of block
 */
function forumlv_page_type_list($pagetype, $parentcontext, $currentcontext) {
    $forumlv_pagetype = array('mod-forumlv-*' => get_string('page-mod-forumlv-x', 'forumlv'), 'mod-forumlv-view' => get_string('page-mod-forumlv-view', 'forumlv'), 'mod-forumlv-discuss' => get_string('page-mod-forumlv-discuss', 'forumlv'));
    return $forumlv_pagetype;
}

/**
 * Gets all of the courses where the provided user has posted in a forumlv.
 *
 * @global moodle_database $DB The database connection
 * @param stdClass $user The user who's posts we are looking for
 * @param bool $discussionsonly If true only look for discussions started by the user
 * @param bool $includecontexts If set to trye contexts for the courses will be preloaded
 * @param int $limitfrom The offset of records to return
 * @param int $limitnum The number of records to return
 * @return array An array of courses
 */
function forumlv_get_courses_user_posted_in($user, $discussionsonly = false, $includecontexts = true, $limitfrom = null, $limitnum = null) {
    global $DB;

    // If we are only after discussions we need only look at the forumlv_discussions
    // table and join to the userid there. If we are looking for posts then we need
    // to join to the forumlv_posts table.
    if (!$discussionsonly) {
        $joinsql = 'JOIN {forumlv_discussions} fd ON fd.course = c.id
                    JOIN {forumlv_posts} fp ON fp.discussion = fd.id';
        $wheresql = 'fp.userid = :userid';
        $params = array('userid' => $user -> id);
    } else {
        $joinsql = 'JOIN {forumlv_discussions} fd ON fd.course = c.id';
        $wheresql = 'fd.userid = :userid';
        $params = array('userid' => $user -> id);
    }

    // Join to the context table so that we can preload contexts if required.
    if ($includecontexts) {
        list($ctxselect, $ctxjoin) = context_instance_preload_sql('c.id', CONTEXT_COURSE, 'ctx');
    } else {
        $ctxselect = '';
        $ctxjoin = '';
    }

    // Now we need to get all of the courses to search.
    // All courses where the user has posted within a forumlv will be returned.
    $sql = "SELECT DISTINCT c.* $ctxselect
            FROM {course} c
            $joinsql
            $ctxjoin
            WHERE $wheresql";
    $courses = $DB -> get_records_sql($sql, $params, $limitfrom, $limitnum);
    if ($includecontexts) {
        array_map('context_instance_preload', $courses);
    }
    return $courses;
}

/**
 * Gets all of the forumlvs a user has posted in for one or more courses.
 *
 * @global moodle_database $DB
 * @param stdClass $user
 * @param array $courseids An array of courseids to search or if not provided
 *                       all courses the user has posted within
 * @param bool $discussionsonly If true then only forumlvs where the user has started
 *                       a discussion will be returned.
 * @param int $limitfrom The offset of records to return
 * @param int $limitnum The number of records to return
 * @return array An array of forumlvs the user has posted within in the provided courses
 */
function forumlv_get_forumlvs_user_posted_in($user, array $courseids = null, $discussionsonly = false, $limitfrom = null, $limitnum = null) {
    global $DB;

    if (!is_null($courseids)) {
        list($coursewhere, $params) = $DB -> get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'courseid');
        $coursewhere = ' AND f.course ' . $coursewhere;
    } else {
        $coursewhere = '';
        $params = array();
    }
    $params['userid'] = $user -> id;
    $params['forumlv'] = 'forumlv';

    if ($discussionsonly) {
        $join = 'JOIN {forumlv_discussions} ff ON ff.forumlv = f.id';
    } else {
        $join = 'JOIN {forumlv_discussions} fd ON fd.forumlv = f.id
                 JOIN {forumlv_posts} ff ON ff.discussion = fd.id';
    }

    $sql = "SELECT f.*, cm.id AS cmid
              FROM {forumlv} f
              JOIN {course_modules} cm ON cm.instance = f.id
              JOIN {modules} m ON m.id = cm.module
              JOIN (
                  SELECT f.id
                    FROM {forumlv} f
                    {$join}
                   WHERE ff.userid = :userid
                GROUP BY f.id
                   ) j ON j.id = f.id
             WHERE m.name = :forumlv
                 {$coursewhere}";

    $courseforumlvs = $DB -> get_records_sql($sql, $params, $limitfrom, $limitnum);
    return $courseforumlvs;
}

/**
 * Returns posts made by the selected user in the requested courses.
 *
 * This method can be used to return all of the posts made by the requested user
 * within the given courses.
 * For each course the access of the current user and requested user is checked
 * and then for each post access to the post and forumlv is checked as well.
 *
 * This function is safe to use with usercapabilities.
 *
 * @global moodle_database $DB
 * @param stdClass $user The user whose posts we want to get
 * @param array $courses The courses to search
 * @param bool $musthaveaccess If set to true errors will be thrown if the user
 *                             cannot access one or more of the courses to search
 * @param bool $discussionsonly If set to true only discussion starting posts
 *                              will be returned.
 * @param int $limitfrom The offset of records to return
 * @param int $limitnum The number of records to return
 * @return stdClass An object the following properties
 *               ->totalcount: the total number of posts made by the requested user
 *                             that the current user can see.
 *               ->courses: An array of courses the current user can see that the
 *                          requested user has posted in.
 *               ->forumlvs: An array of forumlvs relating to the posts returned in the
 *                         property below.
 *               ->posts: An array containing the posts to show for this request.
 */
function forumlv_get_posts_by_user($user, array $courses, $musthaveaccess = false, $discussionsonly = false, $limitfrom = 0, $limitnum = 50) {
    global $DB, $USER, $CFG;

    $return = new stdClass;
    $return -> totalcount = 0;
    // The total number of posts that the current user is able to view
    $return -> courses = array();
    // The courses the current user can access
    $return -> forumlvs = array();
    // The forumlvs that the current user can access that contain posts
    $return -> posts = array();
    // The posts to display

    // First up a small sanity check. If there are no courses to check we can
    // return immediately, there is obviously nothing to search.
    if (empty($courses)) {
        return $return;
    }

    // A couple of quick setups
    $isloggedin = isloggedin();
    $isguestuser = $isloggedin && isguestuser();
    $iscurrentuser = $isloggedin && $USER -> id == $user -> id;

    // Checkout whether or not the current user has capabilities over the requested
    // user and if so they have the capabilities required to view the requested
    // users content.
    $usercontext = context_user::instance($user -> id, MUST_EXIST);
    $hascapsonuser = !$iscurrentuser && $DB -> record_exists('role_assignments', array('userid' => $USER -> id, 'contextid' => $usercontext -> id));
    $hascapsonuser = $hascapsonuser && has_all_capabilities(array('moodle/user:viewdetails', 'moodle/user:readuserposts'), $usercontext);

    // Before we actually search each course we need to check the user's access to the
    // course. If the user doesn't have the appropraite access then we either throw an
    // error if a particular course was requested or we just skip over the course.
    foreach ($courses as $course) {
        $coursecontext = context_course::instance($course -> id, MUST_EXIST);
        if ($iscurrentuser || $hascapsonuser) {
            // If it is the current user, or the current user has capabilities to the
            // requested user then all we need to do is check the requested users
            // current access to the course.
            // Note: There is no need to check group access or anything of the like
            // as either the current user is the requested user, or has granted
            // capabilities on the requested user. Either way they can see what the
            // requested user posted, although its VERY unlikely in the `parent` situation
            // that the current user will be able to view the posts in context.
            if (!is_viewing($coursecontext, $user) && !is_enrolled($coursecontext, $user)) {
                // Need to have full access to a course to see the rest of own info
                if ($musthaveaccess) {
                    print_error('errorenrolmentrequired', 'forumlv');
                }
                continue;
            }
        } else {
            // Check whether the current user is enrolled or has access to view the course
            // if they don't we immediately have a problem.
            if (!can_access_course($course)) {
                if ($musthaveaccess) {
                    print_error('errorenrolmentrequired', 'forumlv');
                }
                continue;
            }

            // Check whether the requested user is enrolled or has access to view the course
            // if they don't we immediately have a problem.
            if (!can_access_course($course, $user)) {
                if ($musthaveaccess) {
                    print_error('notenrolled', 'forumlv');
                }
                continue;
            }

            // If groups are in use and enforced throughout the course then make sure
            // we can meet in at least one course level group.
            // Note that we check if either the current user or the requested user have
            // the capability to access all groups. This is because with that capability
            // a user in group A could post in the group B forumlv. Grrrr.
            if (groups_get_course_groupmode($course) == SEPARATEGROUPS && $course -> groupmodeforce && !has_capability('moodle/site:accessallgroups', $coursecontext) && !has_capability('moodle/site:accessallgroups', $coursecontext, $user -> id)) {
                // If its the guest user to bad... the guest user cannot access groups
                if (!$isloggedin or $isguestuser) {
                    // do not use require_login() here because we might have already used require_login($course)
                    if ($musthaveaccess) {
                        redirect(get_login_url());
                    }
                    continue;
                }
                // Get the groups of the current user
                $mygroups = array_keys(groups_get_all_groups($course -> id, $USER -> id, $course -> defaultgroupingid, 'g.id, g.name'));
                // Get the groups the requested user is a member of
                $usergroups = array_keys(groups_get_all_groups($course -> id, $user -> id, $course -> defaultgroupingid, 'g.id, g.name'));
                // Check whether they are members of the same group. If they are great.
                $intersect = array_intersect($mygroups, $usergroups);
                if (empty($intersect)) {
                    // But they're not... if it was a specific course throw an error otherwise
                    // just skip this course so that it is not searched.
                    if ($musthaveaccess) {
                        print_error("groupnotamember", '', $CFG -> wwwroot . "/course/view.php?id=$course->id");
                    }
                    continue;
                }
            }
        }
        // Woo hoo we got this far which means the current user can search this
        // this course for the requested user. Although this is only the course accessibility
        // handling that is complete, the forumlv accessibility tests are yet to come.
        $return -> courses[$course -> id] = $course;
    }
    // No longer beed $courses array - lose it not it may be big
    unset($courses);

    // Make sure that we have some courses to search
    if (empty($return -> courses)) {
        // If we don't have any courses to search then the reality is that the current
        // user doesn't have access to any courses is which the requested user has posted.
        // Although we do know at this point that the requested user has posts.
        if ($musthaveaccess) {
            print_error('permissiondenied');
        } else {
            return $return;
        }
    }

    // Next step: Collect all of the forumlvs that we will want to search.
    // It is important to note that this step isn't actually about searching, it is
    // about determining which forumlvs we can search by testing accessibility.
    $forumlvs = forumlv_get_forumlvs_user_posted_in($user, array_keys($return -> courses), $discussionsonly);

    // Will be used to build the where conditions for the search
    $forumlvsearchwhere = array();
    // Will be used to store the where condition params for the search
    $forumlvsearchparams = array();
    // Will record forumlvs where the user can freely access everything
    $forumlvsearchfullaccess = array();
    // DB caching friendly
    $now = round(time(), -2);
    // For each course to search we want to find the forumlvs the user has posted in
    // and providing the current user can access the forumlv create a search condition
    // for the forumlv to get the requested users posts.
    foreach ($return->courses as $course) {
        // Now we need to get the forumlvs
        $modinfo = get_fast_modinfo($course);
        if (empty($modinfo -> instances['forumlv'])) {
            // hmmm, no forumlvs? well at least its easy... skip!
            continue;
        }
        // Iterate
        foreach ($modinfo->get_instances_of('forumlv') as $forumlvid => $cm) {
            if (!$cm -> uservisible or !isset($forumlvs[$forumlvid])) {
                continue;
            }
            // Get the forumlv in question
            $forumlv = $forumlvs[$forumlvid];
            // This is needed for functionality later on in the forumlv code....
            $forumlv -> cm = $cm;

            // Check that either the current user can view the forumlv, or that the
            // current user has capabilities over the requested user and the requested
            // user can view the discussion
            if (!has_capability('mod/forumlv:viewdiscussion', $cm -> context) && !($hascapsonuser && has_capability('mod/forumlv:viewdiscussion', $cm -> context, $user -> id))) {
                continue;
            }

            // This will contain forumlv specific where clauses
            $forumlvsearchselect = array();
            if (!$iscurrentuser && !$hascapsonuser) {
                // Make sure we check group access
                if (groups_get_activity_groupmode($cm, $course) == SEPARATEGROUPS and !has_capability('moodle/site:accessallgroups', $cm -> context)) {
                    $groups = $modinfo -> get_groups($cm -> groupingid);
                    $groups[] = -1;
                    list($groupid_sql, $groupid_params) = $DB -> get_in_or_equal($groups, SQL_PARAMS_NAMED, 'grps' . $forumlvid . '_');
                    $forumlvsearchparams = array_merge($forumlvsearchparams, $groupid_params);
                    $forumlvsearchselect[] = "d.groupid $groupid_sql";
                }

                // hidden timed discussions
                if (!empty($CFG -> forumlv_enabletimedposts) && !has_capability('mod/forumlv:viewhiddentimedposts', $cm -> context)) {
                    $forumlvsearchselect[] = "(d.userid = :userid{$forumlvid} OR (d.timestart < :timestart{$forumlvid} AND (d.timeend = 0 OR d.timeend > :timeend{$forumlvid})))";
                    $forumlvsearchparams['userid' . $forumlvid] = $user -> id;
                    $forumlvsearchparams['timestart' . $forumlvid] = $now;
                    $forumlvsearchparams['timeend' . $forumlvid] = $now;
                }

                // qanda access
                if ($forumlv -> type == 'qanda' && !has_capability('mod/forumlv:viewqandawithoutposting', $cm -> context)) {
                    // We need to check whether the user has posted in the qanda forumlv.
                    $discussionspostedin = forumlv_discussions_user_has_posted_in($forumlv -> id, $user -> id);
                    if (!empty($discussionspostedin)) {
                        $forumlvonlydiscussions = array();
                        // Holds discussion ids for the discussions the user is allowed to see in this forumlv.
                        foreach ($discussionspostedin as $d) {
                            $forumlvonlydiscussions[] = $d -> id;
                        }
                        list($discussionid_sql, $discussionid_params) = $DB -> get_in_or_equal($forumlvonlydiscussions, SQL_PARAMS_NAMED, 'qanda' . $forumlvid . '_');
                        $forumlvsearchparams = array_merge($forumlvsearchparams, $discussionid_params);
                        $forumlvsearchselect[] = "(d.id $discussionid_sql OR p.parent = 0)";
                    } else {
                        $forumlvsearchselect[] = "p.parent = 0";
                    }

                }

                if (count($forumlvsearchselect) > 0) {
                    $forumlvsearchwhere[] = "(d.forumlv = :forumlv{$forumlvid} AND " . implode(" AND ", $forumlvsearchselect) . ")";
                    $forumlvsearchparams['forumlv' . $forumlvid] = $forumlvid;
                } else {
                    $forumlvsearchfullaccess[] = $forumlvid;
                }
            } else {
                // The current user/parent can see all of their own posts
                $forumlvsearchfullaccess[] = $forumlvid;
            }
        }
    }

    // If we dont have any search conditions, and we don't have any forumlvs where
    // the user has full access then we just return the default.
    if (empty($forumlvsearchwhere) && empty($forumlvsearchfullaccess)) {
        return $return;
    }

    // Prepare a where condition for the full access forumlvs.
    if (count($forumlvsearchfullaccess) > 0) {
        list($fullidsql, $fullidparams) = $DB -> get_in_or_equal($forumlvsearchfullaccess, SQL_PARAMS_NAMED, 'fula');
        $forumlvsearchparams = array_merge($forumlvsearchparams, $fullidparams);
        $forumlvsearchwhere[] = "(d.forumlv $fullidsql)";
    }

    // Prepare SQL to both count and search.
    // We alias user.id to useridx because we forumlv_posts already has a userid field and not aliasing this would break
    // oracle and mssql.
    $userfields = user_picture::fields('u', null, 'useridx');
    $countsql = 'SELECT COUNT(*) ';
    $selectsql = 'SELECT p.*, d.forumlv, d.name AS discussionname, ' . $userfields . ' ';
    $wheresql = implode(" OR ", $forumlvsearchwhere);

    if ($discussionsonly) {
        if ($wheresql == '') {
            $wheresql = 'p.parent = 0';
        } else {
            $wheresql = 'p.parent = 0 AND (' . $wheresql . ')';
        }
    }

    $sql = "FROM {forumlv_posts} p
            JOIN {forumlv_discussions} d ON d.id = p.discussion
            JOIN {user} u ON u.id = p.userid
           WHERE ($wheresql)
             AND p.userid = :userid ";
    $orderby = "ORDER BY p.modified DESC";
    $forumlvsearchparams['userid'] = $user -> id;

    // Set the total number posts made by the requested user that the current user can see
    $return -> totalcount = $DB -> count_records_sql($countsql . $sql, $forumlvsearchparams);
    // Set the collection of posts that has been requested
    $return -> posts = $DB -> get_records_sql($selectsql . $sql . $orderby, $forumlvsearchparams, $limitfrom, $limitnum);

    // We need to build an array of forumlvs for which posts will be displayed.
    // We do this here to save the caller needing to retrieve them themselves before
    // printing these forumlvs posts. Given we have the forumlvs already there is
    // practically no overhead here.
    foreach ($return->posts as $post) {
        if (!array_key_exists($post -> forumlv, $return -> forumlvs)) {
            $return -> forumlvs[$post -> forumlv] = $forumlvs[$post -> forumlv];
        }
    }

    return $return;
}
