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
 * @package   mod_forumlv
 * @copyright 1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/** Include required files */
require_once(__DIR__ . '/deprecatedlib.php');
require_once($CFG->libdir.'/filelib.php');
require_once($CFG->libdir.'/eventslib.php');

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
define('FORUMLV_DISALLOWSUBSCRIBE',3);

/**
 * FORUMLV_TRACKING_OFF - Tracking is not available for this forumlv.
 */
define('FORUMLV_TRACKING_OFF', 0);

/**
 * FORUMLV_TRACKING_OPTIONAL - Tracking is based on user preference.
 */
define('FORUMLV_TRACKING_OPTIONAL', 1);

/**
 * FORUMLV_TRACKING_FORCED - Tracking is on, regardless of user setting.
 * Treated as FORUMLV_TRACKING_OPTIONAL if $CFG->forumlv_allowforcedreadtracking is off.
 */
define('FORUMLV_TRACKING_FORCED', 2);

define('FORUMLV_MAILED_PENDING', 0);
define('FORUMLV_MAILED_SUCCESS', 1);
define('FORUMLV_MAILED_ERROR', 2);

if (!defined('FORUMLV_CRON_USER_CACHE')) {
    /** Defines how many full user records are cached in forumlv cron. */
    define('FORUMLV_CRON_USER_CACHE', 5000);
}

/**
 * FORUMLV_POSTS_ALL_USER_GROUPS - All the posts in groups where the user is enrolled.
 */
define('FORUMLV_POSTS_ALL_USER_GROUPS', -2);

define('FORUMLV_DISCUSSION_PINNED', 1);
define('FORUMLV_DISCUSSION_UNPINNED', 0);

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

    $forumlv->timemodified = time();

    if (empty($forumlv->assessed)) {
        $forumlv->assessed = 0;
    }

    if (empty($forumlv->ratingtime) or empty($forumlv->assessed)) {
        $forumlv->assesstimestart  = 0;
        $forumlv->assesstimefinish = 0;
    }

    //@lvs se o checkbox estiver desmarcado setar para 0
    $forumlv->exibir = (isset($forumlv->exibir)) ? 1 : 0;

    $forumlv->id = $DB->insert_record('forumlv', $forumlv);
    $modcontext = context_module::instance($forumlv->coursemodule);

    if ($forumlv->type == 'single') {  // Create related discussion.
        $discussion = new stdClass();
        $discussion->course        = $forumlv->course;
        $discussion->forumlv         = $forumlv->id;
        $discussion->name          = $forumlv->name;
        $discussion->assessed      = $forumlv->assessed;
        $discussion->message       = $forumlv->intro;
        $discussion->messageformat = $forumlv->introformat;
        $discussion->messagetrust  = trusttext_trusted(context_course::instance($forumlv->course));
        $discussion->mailnow       = false;
        $discussion->groupid       = -1;

        $message = '';

        $discussion->id = forumlv_add_discussion($discussion, null, $message);

        if ($mform and $draftid = file_get_submitted_draft_itemid('introeditor')) {
            // Ugly hack - we need to copy the files somehow.
            $discussion = $DB->get_record('forumlv_discussions', array('id'=>$discussion->id), '*', MUST_EXIST);
            $post = $DB->get_record('forumlv_posts', array('id'=>$discussion->firstpost), '*', MUST_EXIST);

            $options = array('subdirs'=>true); // Use the same options as intro field!
            $post->message = file_save_draft_area_files($draftid, $modcontext->id, 'mod_forumlv', 'post', $post->id, $options, $post->message);
            $DB->set_field('forumlv_posts', 'message', $post->message, array('id'=>$post->id));
        }
    }

    forumlv_grade_item_update($forumlv);

    return $forumlv->id;
}

/**
 * Handle changes following the creation of a forumlv instance.
 * This function is typically called by the course_module_created observer.
 *
 * @param object $context the forumlv context
 * @param stdClass $forumlv The forumlv object
 * @return void
 */
function forumlv_instance_created($context, $forumlv) {
    if ($forumlv->forcesubscribe == FORUMLV_INITIALSUBSCRIBE) {
        $users = \mod_forumlv\subscriptions::get_potential_subscribers($context, 0, 'u.id, u.email');
        foreach ($users as $user) {
            \mod_forumlv\subscriptions::subscribe_user($user->id, $forumlv, $context);
        }
    }
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

    $forumlv->timemodified = time();
    $forumlv->id           = $forumlv->instance;

    if (empty($forumlv->assessed)) {
        $forumlv->assessed = 0;
    }

    if (empty($forumlv->ratingtime) or empty($forumlv->assessed)) {
        $forumlv->assesstimestart  = 0;
        $forumlv->assesstimefinish = 0;
    }

    $oldforumlv = $DB->get_record('forumlv', array('id'=>$forumlv->id));

    // MDL-3942 - if the aggregation type or scale (i.e. max grade) changes then recalculate the grades for the entire forumlv
    // if  scale changes - do we need to recheck the ratings, if ratings higher than scale how do we want to respond?
    // for count and sum aggregation types the grade we check to make sure they do not exceed the scale (i.e. max score) when calculating the grade
    if (($oldforumlv->assessed<>$forumlv->assessed) or ($oldforumlv->scale<>$forumlv->scale)) {
        forumlv_update_grades($forumlv); // recalculate grades for the forumlv
    }

    if ($forumlv->type == 'single') {  // Update related discussion and post.
        $discussions = $DB->get_records('forumlv_discussions', array('forumlv'=>$forumlv->id), 'timemodified ASC');
        if (!empty($discussions)) {
            if (count($discussions) > 1) {
                echo $OUTPUT->notification(get_string('warnformorepost', 'forumlv'));
            }
            $discussion = array_pop($discussions);
        } else {
            // try to recover by creating initial discussion - MDL-16262
            $discussion = new stdClass();
            $discussion->course          = $forumlv->course;
            $discussion->forumlv           = $forumlv->id;
            $discussion->name            = $forumlv->name;
            $discussion->assessed        = $forumlv->assessed;
            $discussion->message         = $forumlv->intro;
            $discussion->messageformat   = $forumlv->introformat;
            $discussion->messagetrust    = true;
            $discussion->mailnow         = false;
            $discussion->groupid         = -1;

            $message = '';

            forumlv_add_discussion($discussion, null, $message);

            if (! $discussion = $DB->get_record('forumlv_discussions', array('forumlv'=>$forumlv->id))) {
                print_error('cannotadd', 'forumlv');
            }
        }
        if (! $post = $DB->get_record('forumlv_posts', array('id'=>$discussion->firstpost))) {
            print_error('cannotfindfirstpost', 'forumlv');
        }

        $cm         = get_coursemodule_from_instance('forumlv', $forumlv->id);
        $modcontext = context_module::instance($cm->id, MUST_EXIST);

        $post = $DB->get_record('forumlv_posts', array('id'=>$discussion->firstpost), '*', MUST_EXIST);
        $post->subject       = $forumlv->name;
        $post->message       = $forumlv->intro;
        $post->messageformat = $forumlv->introformat;
        $post->messagetrust  = trusttext_trusted($modcontext);
        $post->modified      = $forumlv->timemodified;
        $post->userid        = $USER->id;    // MDL-18599, so that current teacher can take ownership of activities.

        if ($mform and $draftid = file_get_submitted_draft_itemid('introeditor')) {
            // Ugly hack - we need to copy the files somehow.
            $options = array('subdirs'=>true); // Use the same options as intro field!
            $post->message = file_save_draft_area_files($draftid, $modcontext->id, 'mod_forumlv', 'post', $post->id, $options, $post->message);
        }

        $DB->update_record('forumlv_posts', $post);
        $discussion->name = $forumlv->name;
        $DB->update_record('forumlv_discussions', $discussion);
    }

    //@lvs se o checkbox estiver desmarcado setar para 0
    $forumlv->exibir = (isset($forumlv->exibir)) ? 1 : 0;

    $DB->update_record('forumlv', $forumlv);

    $modcontext = context_module::instance($forumlv->coursemodule);
    if (($forumlv->forcesubscribe == FORUMLV_INITIALSUBSCRIBE) && ($oldforumlv->forcesubscribe <> $forumlv->forcesubscribe)) {
        $users = \mod_forumlv\subscriptions::get_potential_subscribers($modcontext, 0, 'u.id, u.email', '');
        foreach ($users as $user) {
            \mod_forumlv\subscriptions::subscribe_user($user->id, $forumlv, $modcontext);
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

    if (!$forumlv = $DB->get_record('forumlv', array('id'=>$id))) {
        return false;
    }
    if (!$cm = get_coursemodule_from_instance('forumlv', $forumlv->id)) {
        return false;
    }
    if (!$course = $DB->get_record('course', array('id'=>$cm->course))) {
        return false;
    }

    $context = context_module::instance($cm->id);

    // now get rid of all files
    $fs = get_file_storage();
    $fs->delete_area_files($context->id);

    $result = true;

    // Delete digest and subscription preferences.
    $DB->delete_records('forumlv_digests', array('forumlv' => $forumlv->id));
    $DB->delete_records('forumlv_subscriptions', array('forumlv'=>$forumlv->id));
    $DB->delete_records('forumlv_discussion_subs', array('forumlv' => $forumlv->id));

    if ($discussions = $DB->get_records('forumlv_discussions', array('forumlv'=>$forumlv->id))) {
        foreach ($discussions as $discussion) {
            if (!forumlv_delete_discussion($discussion, true, $course, $cm, $forumlv)) {
                $result = false;
            }
        }
    }

    forumlv_tp_delete_read_records(-1, -1, -1, $forumlv->id);

    if (!$DB->delete_records('forumlv', array('id'=>$forumlv->id))) {
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
        case FEATURE_GROUPS:                  return true;
        case FEATURE_GROUPINGS:               return true;
        case FEATURE_MOD_INTRO:               return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS: return true;
        case FEATURE_COMPLETION_HAS_RULES:    return true;
        case FEATURE_GRADE_HAS_GRADE:         return true;
        case FEATURE_GRADE_OUTCOMES:          return true;
        case FEATURE_RATE:                    return true;
        case FEATURE_BACKUP_MOODLE2:          return true;
        case FEATURE_SHOW_DESCRIPTION:        return true;
        case FEATURE_PLAGIARISM:              return true;

        default: return null;
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
function forumlv_get_completion_state($course,$cm,$userid,$type) {
    global $CFG,$DB;

    // Get forumlv details
    if (!($forumlv=$DB->get_record('forumlv',array('id'=>$cm->instance)))) {
        throw new Exception("Can't find forumlv {$cm->instance}");
    }

    $result=$type; // Default return value

    $postcountparams=array('userid'=>$userid,'forumlvid'=>$forumlv->id);
    $postcountsql="
SELECT
    COUNT(1)
FROM
    {forumlv_posts} fp
    INNER JOIN {forumlv_discussions} fd ON fp.discussion=fd.id
WHERE
    fp.userid=:userid AND fd.forumlv=:forumlvid";

    if ($forumlv->completiondiscussions) {
        $value = $forumlv->completiondiscussions <=
                 $DB->count_records('forumlv_discussions',array('forumlv'=>$forumlv->id,'userid'=>$userid));
        if ($type == COMPLETION_AND) {
            $result = $result && $value;
        } else {
            $result = $result || $value;
        }
    }
    if ($forumlv->completionreplies) {
        $value = $forumlv->completionreplies <=
                 $DB->get_field_sql( $postcountsql.' AND fp.parent<>0',$postcountparams);
        if ($type==COMPLETION_AND) {
            $result = $result && $value;
        } else {
            $result = $result || $value;
        }
    }
    if ($forumlv->completionposts) {
        $value = $forumlv->completionposts <= $DB->get_field_sql($postcountsql,$postcountparams);
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
 * @return string A unique message-id
 */
function forumlv_get_email_message_id($postid, $usertoid) {
    return generate_email_messageid(hash('sha256', $postid . 'to' . $usertoid));
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

    unset($user->institution);
    unset($user->department);
    unset($user->address);
    unset($user->city);
    unset($user->url);
    unset($user->currentlogin);
    unset($user->description);
    unset($user->descriptionformat);
}

/**
 * Function to be run periodically according to the scheduled task.
 *
 * Finds all posts that have yet to be mailed out, and mails them
 * out to all subscribers as well as other maintance tasks.
 *
 * NOTE: Since 2.7.2 this function is run by scheduled task rather
 * than standard cron.
 *
 * @todo MDL-44734 The function will be split up into seperate tasks.
 */
function forumlv_cron() {
    global $CFG, $USER, $DB, $PAGE;

    $site = get_site();

    // The main renderers.
    $htmlout = $PAGE->get_renderer('mod_forumlv', 'email', 'htmlemail');
    $textout = $PAGE->get_renderer('mod_forumlv', 'email', 'textemail');
    $htmldigestfullout = $PAGE->get_renderer('mod_forumlv', 'emaildigestfull', 'htmlemail');
    $textdigestfullout = $PAGE->get_renderer('mod_forumlv', 'emaildigestfull', 'textemail');
    $htmldigestbasicout = $PAGE->get_renderer('mod_forumlv', 'emaildigestbasic', 'htmlemail');
    $textdigestbasicout = $PAGE->get_renderer('mod_forumlv', 'emaildigestbasic', 'textemail');

    // All users that are subscribed to any post that needs sending,
    // please increase $CFG->extramemorylimit on large sites that
    // send notifications to a large number of users.
    $users = array();
    $userscount = 0; // Cached user counter - count($users) in PHP is horribly slow!!!

    // Status arrays.
    $mailcount  = array();
    $errorcount = array();

    // caches
    $discussions        = array();
    $forumlvs             = array();
    $courses            = array();
    $coursemodules      = array();
    $subscribedusers    = array();
    $messageinboundhandlers = array();

    // Posts older than 2 days will not be mailed.  This is to avoid the problem where
    // cron has not been running for a long time, and then suddenly people are flooded
    // with mail from the past few weeks or months
    $timenow   = time();
    $endtime   = $timenow - $CFG->maxeditingtime;
    $starttime = $endtime - 48 * 3600;   // Two days earlier

    // Get the list of forumlv subscriptions for per-user per-forumlv maildigest settings.
    $digestsset = $DB->get_recordset('forumlv_digests', null, '', 'id, userid, forumlv, maildigest');
    $digests = array();
    foreach ($digestsset as $thisrow) {
        if (!isset($digests[$thisrow->forumlv])) {
            $digests[$thisrow->forumlv] = array();
        }
        $digests[$thisrow->forumlv][$thisrow->userid] = $thisrow->maildigest;
    }
    $digestsset->close();

    // Create the generic messageinboundgenerator.
    $messageinboundgenerator = new \core\message\inbound\address_manager();
    $messageinboundgenerator->set_handler('\mod_forumlv\message\inbound\reply_handler');

    if ($posts = forumlv_get_unmailed_posts($starttime, $endtime, $timenow)) {
        // Mark them all now as being mailed.  It's unlikely but possible there
        // might be an error later so that a post is NOT actually mailed out,
        // but since mail isn't crucial, we can accept this risk.  Doing it now
        // prevents the risk of duplicated mails, which is a worse problem.

        if (!forumlv_mark_old_posts_as_mailed($endtime)) {
            mtrace('Errors occurred while trying to mark some posts as being mailed.');
            return false;  // Don't continue trying to mail them, in case we are in a cron loop
        }

        // checking post validity, and adding users to loop through later
        foreach ($posts as $pid => $post) {

            $discussionid = $post->discussion;
            if (!isset($discussions[$discussionid])) {
                if ($discussion = $DB->get_record('forumlv_discussions', array('id'=> $post->discussion))) {
                    $discussions[$discussionid] = $discussion;
                    \mod_forumlv\subscriptions::fill_subscription_cache($discussion->forumlv);
                    \mod_forumlv\subscriptions::fill_discussion_subscription_cache($discussion->forumlv);

                } else {
                    mtrace('Could not find discussion ' . $discussionid);
                    unset($posts[$pid]);
                    continue;
                }
            }
            $forumlvid = $discussions[$discussionid]->forumlv;
            if (!isset($forumlvs[$forumlvid])) {
                if ($forumlv = $DB->get_record('forumlv', array('id' => $forumlvid))) {
                    $forumlvs[$forumlvid] = $forumlv;
                } else {
                    mtrace('Could not find forumlv '.$forumlvid);
                    unset($posts[$pid]);
                    continue;
                }
            }
            $courseid = $forumlvs[$forumlvid]->course;
            if (!isset($courses[$courseid])) {
                if ($course = $DB->get_record('course', array('id' => $courseid))) {
                    $courses[$courseid] = $course;
                } else {
                    mtrace('Could not find course '.$courseid);
                    unset($posts[$pid]);
                    continue;
                }
            }
            if (!isset($coursemodules[$forumlvid])) {
                if ($cm = get_coursemodule_from_instance('forumlv', $forumlvid, $courseid)) {
                    $coursemodules[$forumlvid] = $cm;
                } else {
                    mtrace('Could not find course module for forumlv '.$forumlvid);
                    unset($posts[$pid]);
                    continue;
                }
            }

            // Save the Inbound Message datakey here to reduce DB queries later.
            $messageinboundgenerator->set_data($pid);
            $messageinboundhandlers[$pid] = $messageinboundgenerator->fetch_data_key();

            // Caching subscribed users of each forumlv.
            if (!isset($subscribedusers[$forumlvid])) {
                $modcontext = context_module::instance($coursemodules[$forumlvid]->id);
                if ($subusers = \mod_forumlv\subscriptions::fetch_subscribed_users($forumlvs[$forumlvid], 0, $modcontext, 'u.*', true)) {

                    foreach ($subusers as $postuser) {
                        // this user is subscribed to this forumlv
                        $subscribedusers[$forumlvid][$postuser->id] = $postuser->id;
                        $userscount++;
                        if ($userscount > FORUMLV_CRON_USER_CACHE) {
                            // Store minimal user info.
                            $minuser = new stdClass();
                            $minuser->id = $postuser->id;
                            $users[$postuser->id] = $minuser;
                        } else {
                            // Cache full user record.
                            forumlv_cron_minimise_user_record($postuser);
                            $users[$postuser->id] = $postuser;
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

        foreach ($users as $userto) {
            // Terminate if processing of any account takes longer than 2 minutes.
            core_php_time_limit::raise(120);

            mtrace('Processing user ' . $userto->id);

            // Init user caches - we keep the cache for one cycle only, otherwise it could consume too much memory.
            if (isset($userto->username)) {
                $userto = clone($userto);
            } else {
                $userto = $DB->get_record('user', array('id' => $userto->id));
                forumlv_cron_minimise_user_record($userto);
            }
            $userto->viewfullnames = array();
            $userto->canpost       = array();
            $userto->markposts     = array();

            // Setup this user so that the capabilities are cached, and environment matches receiving user.
            cron_setup_user($userto);

            // Reset the caches.
            foreach ($coursemodules as $forumlvid => $unused) {
                $coursemodules[$forumlvid]->cache       = new stdClass();
                $coursemodules[$forumlvid]->cache->caps = array();
                unset($coursemodules[$forumlvid]->uservisible);
            }

            foreach ($posts as $pid => $post) {
                $discussion = $discussions[$post->discussion];
                $forumlv      = $forumlvs[$discussion->forumlv];
                $course     = $courses[$forumlv->course];
                $cm         =& $coursemodules[$forumlv->id];

                // Do some checks to see if we can bail out now.

                // Only active enrolled users are in the list of subscribers.
                // This does not necessarily mean that the user is subscribed to the forumlv or to the discussion though.
                if (!isset($subscribedusers[$forumlv->id][$userto->id])) {
                    // The user does not subscribe to this forumlv.
                    continue;
                }

                if (!\mod_forumlv\subscriptions::is_subscribed($userto->id, $forumlv, $post->discussion, $coursemodules[$forumlv->id])) {
                    // The user does not subscribe to this forumlv, or to this specific discussion.
                    continue;
                }

                if ($subscriptiontime = \mod_forumlv\subscriptions::fetch_discussion_subscription($forumlv->id, $userto->id)) {
                    // Skip posts if the user subscribed to the discussion after it was created.
                    if (isset($subscriptiontime[$post->discussion]) && ($subscriptiontime[$post->discussion] > $post->created)) {
                        continue;
                    }
                }

                // Don't send email if the forumlv is Q&A and the user has not posted.
                // Initial topics are still mailed.
                if ($forumlv->type == 'qanda' && !forumlv_get_user_posted_time($discussion->id, $userto->id) && $pid != $discussion->firstpost) {
                    mtrace('Did not email ' . $userto->id.' because user has not posted in discussion');
                    continue;
                }

                // Get info about the sending user.
                if (array_key_exists($post->userid, $users)) {
                    // We might know the user already.
                    $userfrom = $users[$post->userid];
                    if (!isset($userfrom->idnumber)) {
                        // Minimalised user info, fetch full record.
                        $userfrom = $DB->get_record('user', array('id' => $userfrom->id));
                        forumlv_cron_minimise_user_record($userfrom);
                    }

                } else if ($userfrom = $DB->get_record('user', array('id' => $post->userid))) {
                    forumlv_cron_minimise_user_record($userfrom);
                    // Fetch only once if possible, we can add it to user list, it will be skipped anyway.
                    if ($userscount <= FORUMLV_CRON_USER_CACHE) {
                        $userscount++;
                        $users[$userfrom->id] = $userfrom;
                    }
                } else {
                    mtrace('Could not find user ' . $post->userid . ', author of post ' . $post->id . '. Unable to send message.');
                    continue;
                }

                // Note: If we want to check that userto and userfrom are not the same person this is probably the spot to do it.

                // Setup global $COURSE properly - needed for roles and languages.
                cron_setup_user($userto, $course);

                // Fill caches.
                if (!isset($userto->viewfullnames[$forumlv->id])) {
                    $modcontext = context_module::instance($cm->id);
                    $userto->viewfullnames[$forumlv->id] = has_capability('moodle/site:viewfullnames', $modcontext);
                }
                if (!isset($userto->canpost[$discussion->id])) {
                    $modcontext = context_module::instance($cm->id);
                    $userto->canpost[$discussion->id] = forumlv_user_can_post($forumlv, $discussion, $userto, $cm, $course, $modcontext);
                }
                if (!isset($userfrom->groups[$forumlv->id])) {
                    if (!isset($userfrom->groups)) {
                        $userfrom->groups = array();
                        if (isset($users[$userfrom->id])) {
                            $users[$userfrom->id]->groups = array();
                        }
                    }
                    $userfrom->groups[$forumlv->id] = groups_get_all_groups($course->id, $userfrom->id, $cm->groupingid);
                    if (isset($users[$userfrom->id])) {
                        $users[$userfrom->id]->groups[$forumlv->id] = $userfrom->groups[$forumlv->id];
                    }
                }

                // Make sure groups allow this user to see this email.
                if ($discussion->groupid > 0 and $groupmode = groups_get_activity_groupmode($cm, $course)) {
                    // Groups are being used.
                    if (!groups_group_exists($discussion->groupid)) {
                        // Can't find group - be safe and don't this message.
                        continue;
                    }

                    if (!groups_is_member($discussion->groupid) and !has_capability('moodle/site:accessallgroups', $modcontext)) {
                        // Do not send posts from other groups when in SEPARATEGROUPS or VISIBLEGROUPS.
                        continue;
                    }
                }

                // Make sure we're allowed to see the post.
                if (!forumlv_user_can_see_post($forumlv, $discussion, $post, null, $cm)) {
                    mtrace('User ' . $userto->id .' can not see ' . $post->id . '. Not sending message.');
                    continue;
                }

                // OK so we need to send the email.

                // Does the user want this post in a digest?  If so postpone it for now.
                $maildigest = forumlv_get_user_maildigest_bulk($digests, $userto, $forumlv->id);

                if ($maildigest > 0) {
                    // This user wants the mails to be in digest form.
                    $queue = new stdClass();
                    $queue->userid       = $userto->id;
                    $queue->discussionid = $discussion->id;
                    $queue->postid       = $post->id;
                    $queue->timemodified = $post->created;
                    $DB->insert_record('forumlv_queue', $queue);
                    continue;
                }

                // Prepare to actually send the post now, and build up the content.

                $cleanforumlvname = str_replace('"', "'", strip_tags(format_string($forumlv->name)));

                $userfrom->customheaders = array (
                    // Headers to make emails easier to track.
                    'List-Id: "'        . $cleanforumlvname . '" ' . generate_email_messageid('moodleforumlv' . $forumlv->id),
                    'List-Help: '       . $CFG->wwwroot . '/mod/forumlv/view.php?f=' . $forumlv->id,
                    'Message-ID: '      . forumlv_get_email_message_id($post->id, $userto->id),
                    'X-Course-Id: '     . $course->id,
                    'X-Course-Name: '   . format_string($course->fullname, true),

                    // Headers to help prevent auto-responders.
                    'Precedence: Bulk',
                    'X-Auto-Response-Suppress: All',
                    'Auto-Submitted: auto-generated',
                );

                $shortname = format_string($course->shortname, true, array('context' => context_course::instance($course->id)));

                // Generate a reply-to address from using the Inbound Message handler.
                $replyaddress = null;
                if ($userto->canpost[$discussion->id] && array_key_exists($post->id, $messageinboundhandlers)) {
                    $messageinboundgenerator->set_data($post->id, $messageinboundhandlers[$post->id]);
                    $replyaddress = $messageinboundgenerator->generate($userto->id);
                }

                if (!isset($userto->canpost[$discussion->id])) {
                    $canreply = forumlv_user_can_post($forumlv, $discussion, $userto, $cm, $course, $modcontext);
                } else {
                    $canreply = $userto->canpost[$discussion->id];
                }

                $data = new \mod_forumlv\output\forumlv_post_email(
                        $course,
                        $cm,
                        $forumlv,
                        $discussion,
                        $post,
                        $userfrom,
                        $userto,
                        $canreply
                    );

                $userfrom->customheaders[] = sprintf('List-Unsubscribe: <%s>',
                    $data->get_unsubscribediscussionlink());

                if (!isset($userto->viewfullnames[$forumlv->id])) {
                    $data->viewfullnames = has_capability('moodle/site:viewfullnames', $modcontext, $userto->id);
                } else {
                    $data->viewfullnames = $userto->viewfullnames[$forumlv->id];
                }

                // Not all of these variables are used in the default language
                // string but are made available to support custom subjects.
                $a = new stdClass();
                $a->subject = $data->get_subject();
                $a->forumlvname = $cleanforumlvname;
                $a->sitefullname = format_string($site->fullname);
                $a->siteshortname = format_string($site->shortname);
                $a->courseidnumber = $data->get_courseidnumber();
                $a->coursefullname = $data->get_coursefullname();
                $a->courseshortname = $data->get_coursename();
                $postsubject = html_to_text(get_string('postmailsubject', 'forumlv', $a), 0);

                $rootid = forumlv_get_email_message_id($discussion->firstpost, $userto->id);

                if ($post->parent) {
                    // This post is a reply, so add reply header (RFC 2822).
                    $parentid = forumlv_get_email_message_id($post->parent, $userto->id);
                    $userfrom->customheaders[] = "In-Reply-To: $parentid";

                    // If the post is deeply nested we also reference the parent message id and
                    // the root message id (if different) to aid threading when parts of the email
                    // conversation have been deleted (RFC1036).
                    if ($post->parent != $discussion->firstpost) {
                        $userfrom->customheaders[] = "References: $rootid $parentid";
                    } else {
                        $userfrom->customheaders[] = "References: $parentid";
                    }
                }

                // MS Outlook / Office uses poorly documented and non standard headers, including
                // Thread-Topic which overrides the Subject and shouldn't contain Re: or Fwd: etc.
                $a->subject = $discussion->name;
                $threadtopic = html_to_text(get_string('postmailsubject', 'forumlv', $a), 0);
                $userfrom->customheaders[] = "Thread-Topic: $threadtopic";
                $userfrom->customheaders[] = "Thread-Index: " . substr($rootid, 1, 28);

                // Send the post now!
                mtrace('Sending ', '');

                $eventdata = new \core\message\message();
                $eventdata->component           = 'mod_forumlv';
                $eventdata->name                = 'posts';
                $eventdata->userfrom            = $userfrom;
                $eventdata->userto              = $userto;
                $eventdata->subject             = $postsubject;
                $eventdata->fullmessage         = $textout->render($data);
                $eventdata->fullmessageformat   = FORMAT_PLAIN;
                $eventdata->fullmessagehtml     = $htmlout->render($data);
                $eventdata->notification        = 1;
                $eventdata->replyto             = $replyaddress;
                if (!empty($replyaddress)) {
                    // Add extra text to email messages if they can reply back.
                    $textfooter = "\n\n" . get_string('replytopostbyemail', 'mod_forumlv');
                    $htmlfooter = html_writer::tag('p', get_string('replytopostbyemail', 'mod_forumlv'));
                    $additionalcontent = array('fullmessage' => array('footer' => $textfooter),
                                     'fullmessagehtml' => array('footer' => $htmlfooter));
                    $eventdata->set_additional_content('email', $additionalcontent);
                }

                // If forumlv_replytouser is not set then send mail using the noreplyaddress.
                if (empty($CFG->forumlv_replytouser)) {
                    $eventdata->userfrom = core_user::get_noreply_user();
                }

                $smallmessagestrings = new stdClass();
                $smallmessagestrings->user          = fullname($userfrom);
                $smallmessagestrings->forumlvname     = "$shortname: " . format_string($forumlv->name, true) . ": " . $discussion->name;
                $smallmessagestrings->message       = $post->message;

                // Make sure strings are in message recipients language.
                $eventdata->smallmessage = get_string_manager()->get_string('smallmessage', 'forumlv', $smallmessagestrings, $userto->lang);

                $contexturl = new moodle_url('/mod/forumlv/discuss.php', array('d' => $discussion->id), 'p' . $post->id);
                $eventdata->contexturl = $contexturl->out();
                $eventdata->contexturlname = $discussion->name;

                $mailresult = message_send($eventdata);
                if (!$mailresult) {
                    mtrace("Error: mod/forumlv/lib.php forumlv_cron(): Could not send out mail for id $post->id to user $userto->id".
                            " ($userto->email) .. not trying again.");
                    $errorcount[$post->id]++;
                } else {
                    $mailcount[$post->id]++;

                    // Mark post as read if forumlv_usermarksread is set off.
                    if (!$CFG->forumlv_usermarksread) {
                        $userto->markposts[$post->id] = $post->id;
                    }
                }

                mtrace('post ' . $post->id . ': ' . $post->subject);
            }

            // Mark processed posts as read.
            forumlv_tp_mark_posts_read($userto, $userto->markposts);
            unset($userto);
        }
    }

    if ($posts) {
        foreach ($posts as $post) {
            mtrace($mailcount[$post->id]." users were sent post $post->id, '$post->subject'");
            if ($errorcount[$post->id]) {
                $DB->set_field('forumlv_posts', 'mailed', FORUMLV_MAILED_ERROR, array('id' => $post->id));
            }
        }
    }

    // release some memory
    unset($subscribedusers);
    unset($mailcount);
    unset($errorcount);

    cron_setup_user();

    $sitetimezone = core_date::get_server_timezone();

    // Now see if there are any digest mails waiting to be sent, and if we should send them

    mtrace('Starting digest processing...');

    core_php_time_limit::raise(300); // terminate if not able to fetch all digests in 5 minutes

    if (!isset($CFG->digestmailtimelast)) {    // To catch the first time
        set_config('digestmailtimelast', 0);
    }

    $timenow = time();
    $digesttime = usergetmidnight($timenow, $sitetimezone) + ($CFG->digestmailtime * 3600);

    // Delete any really old ones (normally there shouldn't be any)
    $weekago = $timenow - (7 * 24 * 3600);
    $DB->delete_records_select('forumlv_queue', "timemodified < ?", array($weekago));
    mtrace ('Cleaned old digest records');

    if ($CFG->digestmailtimelast < $digesttime and $timenow > $digesttime) {

        mtrace('Sending forumlv digests: '.userdate($timenow, '', $sitetimezone));

        $digestposts_rs = $DB->get_recordset_select('forumlv_queue', "timemodified < ?", array($digesttime));

        if ($digestposts_rs->valid()) {

            // We have work to do
            $usermailcount = 0;

            //caches - reuse the those filled before too
            $discussionposts = array();
            $userdiscussions = array();

            foreach ($digestposts_rs as $digestpost) {
                if (!isset($posts[$digestpost->postid])) {
                    if ($post = $DB->get_record('forumlv_posts', array('id' => $digestpost->postid))) {
                        $posts[$digestpost->postid] = $post;
                    } else {
                        continue;
                    }
                }
                $discussionid = $digestpost->discussionid;
                if (!isset($discussions[$discussionid])) {
                    if ($discussion = $DB->get_record('forumlv_discussions', array('id' => $discussionid))) {
                        $discussions[$discussionid] = $discussion;
                    } else {
                        continue;
                    }
                }
                $forumlvid = $discussions[$discussionid]->forumlv;
                if (!isset($forumlvs[$forumlvid])) {
                    if ($forumlv = $DB->get_record('forumlv', array('id' => $forumlvid))) {
                        $forumlvs[$forumlvid] = $forumlv;
                    } else {
                        continue;
                    }
                }

                $courseid = $forumlvs[$forumlvid]->course;
                if (!isset($courses[$courseid])) {
                    if ($course = $DB->get_record('course', array('id' => $courseid))) {
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
                $userdiscussions[$digestpost->userid][$digestpost->discussionid] = $digestpost->discussionid;
                $discussionposts[$digestpost->discussionid][$digestpost->postid] = $digestpost->postid;
            }
            $digestposts_rs->close(); /// Finished iteration, let's close the resultset

            // Data collected, start sending out emails to each user
            foreach ($userdiscussions as $userid => $thesediscussions) {

                core_php_time_limit::raise(120); // terminate if processing of any account takes longer than 2 minutes

                cron_setup_user();

                mtrace(get_string('processingdigest', 'forumlv', $userid), '... ');

                // First of all delete all the queue entries for this user
                $DB->delete_records_select('forumlv_queue', "userid = ? AND timemodified < ?", array($userid, $digesttime));

                // Init user caches - we keep the cache for one cycle only,
                // otherwise it would unnecessarily consume memory.
                if (array_key_exists($userid, $users) and isset($users[$userid]->username)) {
                    $userto = clone($users[$userid]);
                } else {
                    $userto = $DB->get_record('user', array('id' => $userid));
                    forumlv_cron_minimise_user_record($userto);
                }
                $userto->viewfullnames = array();
                $userto->canpost       = array();
                $userto->markposts     = array();

                // Override the language and timezone of the "current" user, so that
                // mail is customised for the receiver.
                cron_setup_user($userto);

                $postsubject = get_string('digestmailsubject', 'forumlv', format_string($site->shortname, true));

                $headerdata = new stdClass();
                $headerdata->sitename = format_string($site->fullname, true);
                $headerdata->userprefs = $CFG->wwwroot.'/user/forumlv.php?id='.$userid.'&amp;course='.$site->id;

                $posttext = get_string('digestmailheader', 'forumlv', $headerdata)."\n\n";
                $headerdata->userprefs = '<a target="_blank" href="'.$headerdata->userprefs.'">'.get_string('digestmailprefs', 'forumlv').'</a>';

                $posthtml = '<p>'.get_string('digestmailheader', 'forumlv', $headerdata).'</p>'
                    . '<br /><hr size="1" noshade="noshade" />';

                foreach ($thesediscussions as $discussionid) {

                    core_php_time_limit::raise(120);   // to be reset for each post

                    $discussion = $discussions[$discussionid];
                    $forumlv      = $forumlvs[$discussion->forumlv];
                    $course     = $courses[$forumlv->course];
                    $cm         = $coursemodules[$forumlv->id];

                    //override language
                    cron_setup_user($userto, $course);

                    // Fill caches
                    if (!isset($userto->viewfullnames[$forumlv->id])) {
                        $modcontext = context_module::instance($cm->id);
                        $userto->viewfullnames[$forumlv->id] = has_capability('moodle/site:viewfullnames', $modcontext);
                    }
                    if (!isset($userto->canpost[$discussion->id])) {
                        $modcontext = context_module::instance($cm->id);
                        $userto->canpost[$discussion->id] = forumlv_user_can_post($forumlv, $discussion, $userto, $cm, $course, $modcontext);
                    }

                    $strforumlvs      = get_string('forumlvs', 'forumlv');
                    $canunsubscribe = ! \mod_forumlv\subscriptions::is_forcesubscribed($forumlv);
                    $canreply       = $userto->canpost[$discussion->id];
                    $shortname = format_string($course->shortname, true, array('context' => context_course::instance($course->id)));

                    $posttext .= "\n \n";
                    $posttext .= '=====================================================================';
                    $posttext .= "\n \n";
                    $posttext .= "$shortname -> $strforumlvs -> ".format_string($forumlv->name,true);
                    if ($discussion->name != $forumlv->name) {
                        $posttext  .= " -> ".format_string($discussion->name,true);
                    }
                    $posttext .= "\n";
                    $posttext .= $CFG->wwwroot.'/mod/forumlv/discuss.php?d='.$discussion->id;
                    $posttext .= "\n";

                    $posthtml .= "<p><font face=\"sans-serif\">".
                    "<a target=\"_blank\" href=\"$CFG->wwwroot/course/view.php?id=$course->id\">$shortname</a> -> ".
                    "<a target=\"_blank\" href=\"$CFG->wwwroot/mod/forumlv/index.php?id=$course->id\">$strforumlvs</a> -> ".
                    "<a target=\"_blank\" href=\"$CFG->wwwroot/mod/forumlv/view.php?f=$forumlv->id\">".format_string($forumlv->name,true)."</a>";
                    if ($discussion->name == $forumlv->name) {
                        $posthtml .= "</font></p>";
                    } else {
                        $posthtml .= " -> <a target=\"_blank\" href=\"$CFG->wwwroot/mod/forumlv/discuss.php?d=$discussion->id\">".format_string($discussion->name,true)."</a></font></p>";
                    }
                    $posthtml .= '<p>';

                    $postsarray = $discussionposts[$discussionid];
                    sort($postsarray);
                    $sentcount = 0;

                    foreach ($postsarray as $postid) {
                        $post = $posts[$postid];

                        if (array_key_exists($post->userid, $users)) { // we might know him/her already
                            $userfrom = $users[$post->userid];
                            if (!isset($userfrom->idnumber)) {
                                $userfrom = $DB->get_record('user', array('id' => $userfrom->id));
                                forumlv_cron_minimise_user_record($userfrom);
                            }

                        } else if ($userfrom = $DB->get_record('user', array('id' => $post->userid))) {
                            forumlv_cron_minimise_user_record($userfrom);
                            if ($userscount <= FORUMLV_CRON_USER_CACHE) {
                                $userscount++;
                                $users[$userfrom->id] = $userfrom;
                            }

                        } else {
                            mtrace('Could not find user '.$post->userid);
                            continue;
                        }

                        if (!isset($userfrom->groups[$forumlv->id])) {
                            if (!isset($userfrom->groups)) {
                                $userfrom->groups = array();
                                if (isset($users[$userfrom->id])) {
                                    $users[$userfrom->id]->groups = array();
                                }
                            }
                            $userfrom->groups[$forumlv->id] = groups_get_all_groups($course->id, $userfrom->id, $cm->groupingid);
                            if (isset($users[$userfrom->id])) {
                                $users[$userfrom->id]->groups[$forumlv->id] = $userfrom->groups[$forumlv->id];
                            }
                        }

                        // Headers to help prevent auto-responders.
                        $userfrom->customheaders = array(
                                "Precedence: Bulk",
                                'X-Auto-Response-Suppress: All',
                                'Auto-Submitted: auto-generated',
                            );

                        $maildigest = forumlv_get_user_maildigest_bulk($digests, $userto, $forumlv->id);
                        if (!isset($userto->canpost[$discussion->id])) {
                            $canreply = forumlv_user_can_post($forumlv, $discussion, $userto, $cm, $course, $modcontext);
                        } else {
                            $canreply = $userto->canpost[$discussion->id];
                        }

                        $data = new \mod_forumlv\output\forumlv_post_email(
                                $course,
                                $cm,
                                $forumlv,
                                $discussion,
                                $post,
                                $userfrom,
                                $userto,
                                $canreply
                            );

                        if (!isset($userto->viewfullnames[$forumlv->id])) {
                            $data->viewfullnames = has_capability('moodle/site:viewfullnames', $modcontext, $userto->id);
                        } else {
                            $data->viewfullnames = $userto->viewfullnames[$forumlv->id];
                        }

                        if ($maildigest == 2) {
                            // Subjects and link only.
                            $posttext .= $textdigestbasicout->render($data);
                            $posthtml .= $htmldigestbasicout->render($data);
                        } else {
                            // The full treatment.
                            $posttext .= $textdigestfullout->render($data);
                            $posthtml .= $htmldigestfullout->render($data);

                            // Create an array of postid's for this user to mark as read.
                            if (!$CFG->forumlv_usermarksread) {
                                $userto->markposts[$post->id] = $post->id;
                            }
                        }
                        $sentcount++;
                    }
                    $footerlinks = array();
                    if ($canunsubscribe) {
                        $footerlinks[] = "<a href=\"$CFG->wwwroot/mod/forumlv/subscribe.php?id=$forumlv->id\">" . get_string("unsubscribe", "forumlv") . "</a>";
                    } else {
                        $footerlinks[] = get_string("everyoneissubscribed", "forumlv");
                    }
                    $footerlinks[] = "<a href='{$CFG->wwwroot}/mod/forumlv/index.php?id={$forumlv->course}'>" . get_string("digestmailpost", "forumlv") . '</a>';
                    $posthtml .= "\n<div class='mdl-right'><font size=\"1\">" . implode('&nbsp;', $footerlinks) . '</font></div>';
                    $posthtml .= '<hr size="1" noshade="noshade" /></p>';
                }

                if (empty($userto->mailformat) || $userto->mailformat != 1) {
                    // This user DOESN'T want to receive HTML
                    $posthtml = '';
                }

                $eventdata = new \core\message\message();
                $eventdata->component           = 'mod_forumlv';
                $eventdata->name                = 'digests';
                $eventdata->userfrom            = core_user::get_noreply_user();
                $eventdata->userto              = $userto;
                $eventdata->subject             = $postsubject;
                $eventdata->fullmessage         = $posttext;
                $eventdata->fullmessageformat   = FORMAT_PLAIN;
                $eventdata->fullmessagehtml     = $posthtml;
                $eventdata->notification        = 1;
                $eventdata->smallmessage        = get_string('smallmessagedigest', 'forumlv', $sentcount);
                $mailresult = message_send($eventdata);

                if (!$mailresult) {
                    mtrace("ERROR: mod/forumlv/cron.php: Could not send out digest mail to user $userto->id ".
                        "($userto->email)... not trying again.");
                } else {
                    mtrace("success.");
                    $usermailcount++;

                    // Mark post as read if forumlv_usermarksread is set off
                    forumlv_tp_mark_posts_read($userto, $userto->markposts);
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

    if (!empty($CFG->forumlv_lastreadclean)) {
        $timenow = time();
        if ($CFG->forumlv_lastreadclean + (24*3600) < $timenow) {
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
 *
 * @param object $course
 * @param object $user
 * @param object $mod TODO this is not used in this function, refactor
 * @param object $forumlv
 * @return object A standard object with 2 variables: info (number of posts for this user) and time (last modified)
 */
function forumlv_user_outline($course, $user, $mod, $forumlv) {
    global $CFG;
    require_once("$CFG->libdir/gradelib.php");
    $grades = grade_get_grades($course->id, 'mod', 'forumlv', $forumlv->id, $user->id);
    if (empty($grades->items[0]->grades)) {
        $grade = false;
    } else {
        $grade = reset($grades->items[0]->grades);
    }

    $count = forumlv_count_user_posts($forumlv->id, $user->id);

    if ($count && $count->postcount > 0) {
        $result = new stdClass();
        $result->info = get_string("numposts", "forumlv", $count->postcount);
        $result->time = $count->lastpost;
        if ($grade) {
            $result->info .= ', ' . get_string('grade') . ': ' . $grade->str_long_grade;
        }
        return $result;
    } else if ($grade) {
        $result = new stdClass();
        $result->info = get_string('grade') . ': ' . $grade->str_long_grade;

        //datesubmitted == time created. dategraded == time modified or time overridden
        //if grade was last modified by the user themselves use date graded. Otherwise use date submitted
        //TODO: move this copied & pasted code somewhere in the grades API. See MDL-26704
        if ($grade->usermodified == $user->id || empty($grade->datesubmitted)) {
            $result->time = $grade->dategraded;
        } else {
            $result->time = $grade->datesubmitted;
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
    global $CFG,$USER, $OUTPUT;
    require_once("$CFG->libdir/gradelib.php");

    $grades = grade_get_grades($course->id, 'mod', 'forumlv', $forumlv->id, $user->id);
    if (!empty($grades->items[0]->grades)) {
        $grade = reset($grades->items[0]->grades);
        echo $OUTPUT->container(get_string('grade').': '.$grade->str_long_grade);
        if ($grade->str_feedback) {
            echo $OUTPUT->container(get_string('feedback').': '.$grade->str_feedback);
        }
    }

    if ($posts = forumlv_get_user_posts($forumlv->id, $user->id)) {

        if (!$cm = get_coursemodule_from_instance('forumlv', $forumlv->id, $course->id)) {
            print_error('invalidcoursemodule');
        }
        $discussions = forumlv_get_user_involved_discussions($forumlv->id, $user->id);

        foreach ($posts as $post) {
            if (!isset($discussions[$post->discussion])) {
                continue;
            }
            $discussion = $discussions[$post->discussion];

            forumlv_print_post($post, $discussion, $forumlv, $cm, $course, false, false, false);
        }
    } else {
        echo "<p>".get_string("noposts", "forumlv")."</p>";
    }
}

/**
 * Filters the forumlv discussions according to groups membership and config.
 *
 * @since  Moodle 2.8, 2.7.1, 2.6.4
 * @param  array $discussions Discussions with new posts array
 * @return array Forumlvs with the number of new posts
 */
function forumlv_filter_user_groups_discussions($discussions) {

    // Group the remaining discussions posts by their forumlvid.
    $filteredforumlvs = array();

    // Discard not visible groups.
    foreach ($discussions as $discussion) {

        // Course data is already cached.
        $instances = get_fast_modinfo($discussion->course)->get_instances();
        $forumlv = $instances['forumlv'][$discussion->forumlv];

        // Continue if the user should not see this discussion.
        if (!forumlv_is_user_group_discussion($forumlv, $discussion->groupid)) {
            continue;
        }

        // Grouping results by forumlv.
        if (empty($filteredforumlvs[$forumlv->instance])) {
            $filteredforumlvs[$forumlv->instance] = new stdClass();
            $filteredforumlvs[$forumlv->instance]->id = $forumlv->id;
            $filteredforumlvs[$forumlv->instance]->count = 0;
        }
        $filteredforumlvs[$forumlv->instance]->count += $discussion->count;

    }

    return $filteredforumlvs;
}

/**
 * Returns whether the discussion group is visible by the current user or not.
 *
 * @since Moodle 2.8, 2.7.1, 2.6.4
 * @param cm_info $cm The discussion course module
 * @param int $discussiongroupid The discussion groupid
 * @return bool
 */
function forumlv_is_user_group_discussion(cm_info $cm, $discussiongroupid) {

    if ($discussiongroupid == -1 || $cm->effectivegroupmode != SEPARATEGROUPS) {
        return true;
    }

    if (isguestuser()) {
        return false;
    }

    if (has_capability('moodle/site:accessallgroups', context_module::instance($cm->id)) ||
            in_array($discussiongroupid, $cm->get_modinfo()->get_groups($cm->groupingid))) {
        return true;
    }

    return false;
}

/**
 * @global object
 * @global object
 * @global object
 * @param array $courses
 * @param array $htmlarray
 */
function forumlv_print_overview($courses,&$htmlarray) {
    global $USER, $CFG, $DB, $SESSION;

    if (empty($courses) || !is_array($courses) || count($courses) == 0) {
        return array();
    }

    if (!$forumlvs = get_all_instances_in_courses('forumlv',$courses)) {
        return;
    }

    // Courses to search for new posts
    $coursessqls = array();
    $params = array();
    foreach ($courses as $course) {

        // If the user has never entered into the course all posts are pending
        if ($course->lastaccess == 0) {
            $coursessqls[] = '(d.course = ?)';
            $params[] = $course->id;

        // Only posts created after the course last access
        } else {
            $coursessqls[] = '(d.course = ? AND p.created > ?)';
            $params[] = $course->id;
            $params[] = $course->lastaccess;
        }
    }
    $params[] = $USER->id;
    $coursessql = implode(' OR ', $coursessqls);

    $sql = "SELECT d.id, d.forumlv, d.course, d.groupid, COUNT(*) as count "
                .'FROM {forumlv_discussions} d '
                .'JOIN {forumlv_posts} p ON p.discussion = d.id '
                ."WHERE ($coursessql) "
                .'AND p.userid != ? '
                .'AND (d.timestart <= ? AND (d.timeend = 0 OR d.timeend > ?)) '
                .'GROUP BY d.id, d.forumlv, d.course, d.groupid '
                .'ORDER BY d.course, d.forumlv';
    $params[] = time();
    $params[] = time();

    // Avoid warnings.
    if (!$discussions = $DB->get_records_sql($sql, $params)) {
        $discussions = array();
    }

    $forumlvsnewposts = forumlv_filter_user_groups_discussions($discussions);

    // also get all forumlv tracking stuff ONCE.
    $trackingforumlvs = array();
    foreach ($forumlvs as $forumlv) {
        if (forumlv_tp_can_track_forumlvs($forumlv)) {
            $trackingforumlvs[$forumlv->id] = $forumlv;
        }
    }

    if (count($trackingforumlvs) > 0) {
        $cutoffdate = isset($CFG->forumlv_oldpostdays) ? (time() - ($CFG->forumlv_oldpostdays*24*60*60)) : 0;
        $sql = 'SELECT d.forumlv,d.course,COUNT(p.id) AS count '.
            ' FROM {forumlv_posts} p '.
            ' JOIN {forumlv_discussions} d ON p.discussion = d.id '.
            ' LEFT JOIN {forumlv_read} r ON r.postid = p.id AND r.userid = ? WHERE (';
        $params = array($USER->id);

        foreach ($trackingforumlvs as $track) {
            $sql .= '(d.forumlv = ? AND (d.groupid = -1 OR d.groupid = 0 OR d.groupid = ?)) OR ';
            $params[] = $track->id;
            if (isset($SESSION->currentgroup[$track->course])) {
                $groupid =  $SESSION->currentgroup[$track->course];
            } else {
                // get first groupid
                $groupids = groups_get_all_groups($track->course, $USER->id);
                if ($groupids) {
                    reset($groupids);
                    $groupid = key($groupids);
                    $SESSION->currentgroup[$track->course] = $groupid;
                } else {
                    $groupid = 0;
                }
                unset($groupids);
            }
            $params[] = $groupid;
        }
        $sql = substr($sql,0,-3); // take off the last OR
        $sql .= ') AND p.modified >= ? AND r.id is NULL ';
        $sql .= 'AND (d.timestart < ? AND (d.timeend = 0 OR d.timeend > ?)) ';
        $sql .= 'GROUP BY d.forumlv,d.course';
        $params[] = $cutoffdate;
        $params[] = time();
        $params[] = time();

        if (!$unread = $DB->get_records_sql($sql, $params)) {
            $unread = array();
        }
    } else {
        $unread = array();
    }

    if (empty($unread) and empty($forumlvsnewposts)) {
        return;
    }

    $strforumlv = get_string('modulename','forumlv');

    foreach ($forumlvs as $forumlv) {
        $str = '';
        $count = 0;
        $thisunread = 0;
        $showunread = false;
        // either we have something from logs, or trackposts, or nothing.
        if (array_key_exists($forumlv->id, $forumlvsnewposts) && !empty($forumlvsnewposts[$forumlv->id])) {
            $count = $forumlvsnewposts[$forumlv->id]->count;
        }
        if (array_key_exists($forumlv->id,$unread)) {
            $thisunread = $unread[$forumlv->id]->count;
            $showunread = true;
        }
        if ($count > 0 || $thisunread > 0) {
            $str .= '<div class="overview forum"><div class="name">'.$strforumlv.': <a title="'.$strforumlv.'" href="'.$CFG->wwwroot.'/mod/forumlv/view.php?f='.$forumlv->id.'">'.
                $forumlv->name.'</a></div>';
            $str .= '<div class="info"><span class="postsincelogin">';
            $str .= get_string('overviewnumpostssince', 'forumlv', $count)."</span>";
            if (!empty($showunread)) {
                $str .= '<div class="unreadposts">'.get_string('overviewnumunread', 'forumlv', $thisunread).'</div>';
            }
            $str .= '</div></div>';
        }
        if (!empty($str)) {
            if (!array_key_exists($forumlv->course,$htmlarray)) {
                $htmlarray[$forumlv->course] = array();
            }
            if (!array_key_exists('forumlv',$htmlarray[$forumlv->course])) {
                $htmlarray[$forumlv->course]['forumlv'] = ''; // initialize, avoid warnings
            }
            $htmlarray[$forumlv->course]['forumlv'] .= $str;
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

    $allnamefields = user_picture::fields('u', null, 'duserid');
    if (!$posts = $DB->get_records_sql("SELECT p.*, f.type AS forumlvtype, d.forumlv, d.groupid,
                                              d.timestart, d.timeend, $allnamefields
                                         FROM {forumlv_posts} p
                                              JOIN {forumlv_discussions} d ON d.id = p.discussion
                                              JOIN {forumlv} f             ON f.id = d.forumlv
                                              JOIN {user} u              ON u.id = p.userid
                                        WHERE p.created > ? AND f.course = ?
                                     ORDER BY p.id ASC", array($timestart, $course->id))) { // order by initial posting date
         return false;
    }

    $modinfo = get_fast_modinfo($course);

    $groupmodes = array();
    $cms    = array();

    $strftimerecent = get_string('strftimerecent');

    $printposts = array();
    foreach ($posts as $post) {
        if (!isset($modinfo->instances['forumlv'][$post->forumlv])) {
            // not visible
            continue;
        }
        $cm = $modinfo->instances['forumlv'][$post->forumlv];
        if (!$cm->uservisible) {
            continue;
        }
        $context = context_module::instance($cm->id);

        if (!has_capability('mod/forumlv:viewdiscussion', $context)) {
            continue;
        }

        if (!empty($CFG->forumlv_enabletimedposts) and $USER->id != $post->duserid
          and (($post->timestart > 0 and $post->timestart > time()) or ($post->timeend > 0 and $post->timeend < time()))) {
            if (!has_capability('mod/forumlv:viewhiddentimedposts', $context)) {
                continue;
            }
        }

        // Check that the user can see the discussion.
        if (forumlv_is_user_group_discussion($cm, $post->groupid)) {
            $printposts[] = $post;
        }

    }
    unset($posts);

    if (!$printposts) {
        return false;
    }

    echo $OUTPUT->heading(get_string('newforumlvposts', 'forumlv').':', 3);
    echo "\n<ul class='unlist'>\n";

    foreach ($printposts as $post) {
        $subjectclass = empty($post->parent) ? ' bold' : '';

        echo '<li><div class="head">'.
               '<div class="date">'.userdate($post->modified, $strftimerecent).'</div>'.
               '<div class="name">'.fullname($post, $viewfullnames).'</div>'.
             '</div>';
        echo '<div class="info'.$subjectclass.'">';
        if (empty($post->parent)) {
            echo '"<a href="'.$CFG->wwwroot.'/mod/forumlv/discuss.php?d='.$post->discussion.'">';
        } else {
            echo '"<a href="'.$CFG->wwwroot.'/mod/forumlv/discuss.php?d='.$post->discussion.'&amp;parent='.$post->parent.'#p'.$post->id.'">';
        }
        $post->subject = break_up_long_words(format_string($post->subject, true));
        echo $post->subject;
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

    require_once($CFG->dirroot.'/rating/lib.php');

    $ratingoptions = new stdClass;
    $ratingoptions->component = 'mod_forumlv';
    $ratingoptions->ratingarea = 'post';

    //need these to work backwards to get a context id. Is there a better way to get contextid from a module instance?
    $ratingoptions->modulename = 'forumlv';
    $ratingoptions->moduleid   = $forumlv->id;
    $ratingoptions->userid = $userid;
    $ratingoptions->aggregationmethod = $forumlv->assessed;
    $ratingoptions->scaleid = $forumlv->scale;
    $ratingoptions->itemtable = 'forumlv_posts';
    $ratingoptions->itemtableusercolumn = 'userid';

    $rm = new rating_manager();
    return $rm->get_user_grades($ratingoptions);
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
function forumlv_update_grades($forumlv, $userid=0, $nullifnone=true) {
    global $CFG, $DB;
    require_once($CFG->libdir.'/gradelib.php');

    if (!$forumlv->assessed) {
        forumlv_grade_item_update($forumlv);

    } else if ($grades = forumlv_get_user_grades($forumlv, $userid)) {
        forumlv_grade_item_update($forumlv, $grades);

    } else if ($userid and $nullifnone) {
        $grade = new stdClass();
        $grade->userid   = $userid;
        $grade->rawgrade = NULL;
        forumlv_grade_item_update($forumlv, $grade);

    } else {
        forumlv_grade_item_update($forumlv);
    }
}

/**
 * Create/update grade item for given forumlv
 *
 * @category grade
 * @uses GRADE_TYPE_NONE
 * @uses GRADE_TYPE_VALUE
 * @uses GRADE_TYPE_SCALE
 * @param stdClass $forumlv Forumlv object with extra cmidnumber
 * @param mixed $grades Optional array/object of grade(s); 'reset' means reset grades in gradebook
 * @return int 0 if ok
 */
function forumlv_grade_item_update($forumlv, $grades=NULL) {
    global $CFG;
    if (!function_exists('grade_update')) { //workaround for buggy PHP versions
        require_once($CFG->libdir.'/gradelib.php');
    }

    $params = array('itemname'=>$forumlv->name, 'idnumber'=>$forumlv->cmidnumber);

    if (!$forumlv->assessed or $forumlv->scale == 0) {
        $params['gradetype'] = GRADE_TYPE_NONE;

    } else if ($forumlv->scale > 0) {
        $params['gradetype'] = GRADE_TYPE_VALUE;
        $params['grademax']  = $forumlv->scale;
        $params['grademin']  = 0;

    } else if ($forumlv->scale < 0) {
        $params['gradetype'] = GRADE_TYPE_SCALE;
        $params['scaleid']   = -$forumlv->scale;
    }

    if ($grades  === 'reset') {
        $params['reset'] = true;
        $grades = NULL;
    }

    return grade_update('mod/forumlv', $forumlv->course, 'mod', 'forumlv', $forumlv->id, 0, $grades, $params);
}

/**
 * Delete grade item for given forumlv
 *
 * @category grade
 * @param stdClass $forumlv Forumlv object
 * @return grade_item
 */
function forumlv_grade_item_delete($forumlv) {
    global $CFG;
    require_once($CFG->libdir.'/gradelib.php');

    return grade_update('mod/forumlv', $forumlv->course, 'mod', 'forumlv', $forumlv->id, 0, NULL, array('deleted'=>1));
}


/**
 * This function returns if a scale is being used by one forumlv
 *
 * @global object
 * @param int $forumlvid
 * @param int $scaleid negative number
 * @return bool
 */
function forumlv_scale_used ($forumlvid,$scaleid) {
    global $DB;
    $return = false;

    $rec = $DB->get_record("forumlv",array("id" => "$forumlvid","scale" => "-$scaleid"));

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
    if ($scaleid and $DB->record_exists('forumlv', array('scale' => -$scaleid))) {
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

    $allnames = get_all_user_name_fields(true, 'u');
    return $DB->get_record_sql("SELECT p.*, d.forumlv, $allnames, u.email, u.picture, u.imagealt
                             FROM {forumlv_posts} p
                                  JOIN {forumlv_discussions} d ON p.discussion = d.id
                                  LEFT JOIN {user} u ON p.userid = u.id
                            WHERE p.id = ?", array($postid));
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
function forumlv_get_all_discussion_posts($discussionid, $sort, $tracking=false) {
    global $CFG, $DB, $USER;

    $tr_sel  = "";
    $tr_join = "";
    $params = array();

    if ($tracking) {
        $tr_sel  = ", fr.id AS postread";
        $tr_join = "LEFT JOIN {forumlv_read} fr ON (fr.postid = p.id AND fr.userid = ?)";
        $params[] = $USER->id;
    }

    $allnames = get_all_user_name_fields(true, 'u');
    $params[] = $discussionid;
    if (!$posts = $DB->get_records_sql("SELECT p.*, $allnames, u.email, u.picture, u.imagealt $tr_sel
                                     FROM {forumlv_posts} p
                                          LEFT JOIN {user} u ON p.userid = u.id
                                          $tr_join
                                    WHERE p.discussion = ?
                                 ORDER BY $sort", $params)) {
        return array();
    }

    foreach ($posts as $pid=>$p) {
        if ($tracking) {
            if (forumlv_tp_is_post_old($p)) {
                 $posts[$pid]->postread = true;
            }
        }
        if (!$p->parent) {
            continue;
        }
        if (!isset($posts[$p->parent])) {
            continue; // parent does not exist??
        }
        if (!isset($posts[$p->parent]->children)) {
            $posts[$p->parent]->children = array();
        }
        $posts[$p->parent]->children[$pid] =& $posts[$pid];
    }

    // Start with the last child of the first post.
    $post = &$posts[reset($posts)->id];

    $lastpost = false;
    while (!$lastpost) {
        if (!isset($post->children)) {
            $post->lastpost = true;
            $lastpost = true;
        } else {
             // Go to the last child of this post.
            $post = &$posts[end($post->children)->id];
        }
    }

    return $posts;
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
 *         Forumlv objects have the following attributes:
 *         id, type, course, cmid, cmvisible, cmgroupmode, accessallgroups,
 *         viewhiddentimedposts
 */
function forumlv_get_readable_forumlvs($userid, $courseid=0) {

    global $CFG, $DB, $USER;
    require_once($CFG->dirroot.'/course/lib.php');

    if (!$forumlvmod = $DB->get_record('modules', array('name' => 'forumlv'))) {
        print_error('notinstalled', 'forumlv');
    }

    if ($courseid) {
        $courses = $DB->get_records('course', array('id' => $courseid));
    } else {
        // If no course is specified, then the user can see SITE + his courses.
        $courses1 = $DB->get_records('course', array('id' => SITEID));
        $courses2 = enrol_get_users_courses($userid, true, array('modinfo'));
        $courses = array_merge($courses1, $courses2);
    }
    if (!$courses) {
        return array();
    }

    $readableforumlvs = array();

    foreach ($courses as $course) {

        $modinfo = get_fast_modinfo($course);

        if (empty($modinfo->instances['forumlv'])) {
            // hmm, no forumlvs?
            continue;
        }

        $courseforumlvs = $DB->get_records('forumlv', array('course' => $course->id));

        foreach ($modinfo->instances['forumlv'] as $forumlvid => $cm) {
            if (!$cm->uservisible or !isset($courseforumlvs[$forumlvid])) {
                continue;
            }
            $context = context_module::instance($cm->id);
            $forumlv = $courseforumlvs[$forumlvid];
            $forumlv->context = $context;
            $forumlv->cm = $cm;

            if (!has_capability('mod/forumlv:viewdiscussion', $context)) {
                continue;
            }

         /// group access
            if (groups_get_activity_groupmode($cm, $course) == SEPARATEGROUPS and !has_capability('moodle/site:accessallgroups', $context)) {

                $forumlv->onlygroups = $modinfo->get_groups($cm->groupingid);
                $forumlv->onlygroups[] = -1;
            }

        /// hidden timed discussions
            $forumlv->viewhiddentimedposts = true;
            if (!empty($CFG->forumlv_enabletimedposts)) {
                if (!has_capability('mod/forumlv:viewhiddentimedposts', $context)) {
                    $forumlv->viewhiddentimedposts = false;
                }
            }

        /// qanda access
            if ($forumlv->type == 'qanda'
                    && !has_capability('mod/forumlv:viewqandawithoutposting', $context)) {

                // We need to check whether the user has posted in the qanda forumlv.
                $forumlv->onlydiscussions = array();  // Holds discussion ids for the discussions
                                                    // the user is allowed to see in this forumlv.
                if ($discussionspostedin = forumlv_discussions_user_has_posted_in($forumlv->id, $USER->id)) {
                    foreach ($discussionspostedin as $d) {
                        $forumlv->onlydiscussions[] = $d->id;
                    }
                }
            }

            $readableforumlvs[$forumlv->id] = $forumlv;
        }

        unset($modinfo);

    } // End foreach $courses

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
function forumlv_search_posts($searchterms, $courseid=0, $limitfrom=0, $limitnum=50,
                            &$totalcount, $extrasql='') {
    global $CFG, $DB, $USER;
    require_once($CFG->libdir.'/searchlib.php');

    $forumlvs = forumlv_get_readable_forumlvs($USER->id, $courseid);

    if (count($forumlvs) == 0) {
        $totalcount = 0;
        return false;
    }

    $now = round(time(), -2); // db friendly

    $fullaccess = array();
    $where = array();
    $params = array();

    foreach ($forumlvs as $forumlvid => $forumlv) {
        $select = array();

        if (!$forumlv->viewhiddentimedposts) {
            $select[] = "(d.userid = :userid{$forumlvid} OR (d.timestart < :timestart{$forumlvid} AND (d.timeend = 0 OR d.timeend > :timeend{$forumlvid})))";
            $params = array_merge($params, array('userid'.$forumlvid=>$USER->id, 'timestart'.$forumlvid=>$now, 'timeend'.$forumlvid=>$now));
        }

        $cm = $forumlv->cm;
        $context = $forumlv->context;

        if ($forumlv->type == 'qanda'
            && !has_capability('mod/forumlv:viewqandawithoutposting', $context)) {
            if (!empty($forumlv->onlydiscussions)) {
                list($discussionid_sql, $discussionid_params) = $DB->get_in_or_equal($forumlv->onlydiscussions, SQL_PARAMS_NAMED, 'qanda'.$forumlvid.'_');
                $params = array_merge($params, $discussionid_params);
                $select[] = "(d.id $discussionid_sql OR p.parent = 0)";
            } else {
                $select[] = "p.parent = 0";
            }
        }

        if (!empty($forumlv->onlygroups)) {
            list($groupid_sql, $groupid_params) = $DB->get_in_or_equal($forumlv->onlygroups, SQL_PARAMS_NAMED, 'grps'.$forumlvid.'_');
            $params = array_merge($params, $groupid_params);
            $select[] = "d.groupid $groupid_sql";
        }

        if ($select) {
            $selects = implode(" AND ", $select);
            $where[] = "(d.forumlv = :forumlv{$forumlvid} AND $selects)";
            $params['forumlv'.$forumlvid] = $forumlvid;
        } else {
            $fullaccess[] = $forumlvid;
        }
    }

    if ($fullaccess) {
        list($fullid_sql, $fullid_params) = $DB->get_in_or_equal($fullaccess, SQL_PARAMS_NAMED, 'fula');
        $params = array_merge($params, $fullid_params);
        $where[] = "(d.forumlv $fullid_sql)";
    }

    $selectdiscussion = "(".implode(" OR ", $where).")";

    $messagesearch = '';
    $searchstring = '';

    // Need to concat these back together for parser to work.
    foreach($searchterms as $searchterm){
        if ($searchstring != '') {
            $searchstring .= ' ';
        }
        $searchstring .= $searchterm;
    }

    // We need to allow quoted strings for the search. The quotes *should* be stripped
    // by the parser, but this should be examined carefully for security implications.
    $searchstring = str_replace("\\\"","\"",$searchstring);
    $parser = new search_parser();
    $lexer = new search_lexer($parser);

    if ($lexer->parse($searchstring)) {
        $parsearray = $parser->get_parsed_array();
        list($messagesearch, $msparams) = search_generate_SQL($parsearray, 'p.message', 'p.subject',
                                                              'p.userid', 'u.id', 'u.firstname',
                                                              'u.lastname', 'p.modified', 'd.forumlv');
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

    $allnames = get_all_user_name_fields(true, 'u');
    $searchsql = "SELECT p.*,
                         d.forumlv,
                         $allnames,
                         u.email,
                         u.picture,
                         u.imagealt
                    FROM $fromsql
                   WHERE $selectsql
                ORDER BY p.modified DESC";

    $totalcount = $DB->count_records_sql($countsql, $params);

    return $DB->get_records_sql($searchsql, $params, $limitfrom, $limitnum);
}

/**
 * Returns a list of all new posts that have not been mailed yet
 *
 * @param int $starttime posts created after this time
 * @param int $endtime posts created before this
 * @param int $now used for timed discussions only
 * @return array
 */
function forumlv_get_unmailed_posts($starttime, $endtime, $now=null) {
    global $CFG, $DB;

    $params = array();
    $params['mailed'] = FORUMLV_MAILED_PENDING;
    $params['ptimestart'] = $starttime;
    $params['ptimeend'] = $endtime;
    $params['mailnow'] = 1;

    if (!empty($CFG->forumlv_enabletimedposts)) {
        if (empty($now)) {
            $now = time();
        }
        $selectsql = "AND (p.created >= :ptimestart OR d.timestart >= :pptimestart)";
        $params['pptimestart'] = $starttime;
        $timedsql = "AND (d.timestart < :dtimestart AND (d.timeend = 0 OR d.timeend > :dtimeend))";
        $params['dtimestart'] = $now;
        $params['dtimeend'] = $now;
    } else {
        $timedsql = "";
        $selectsql = "AND p.created >= :ptimestart";
    }

    return $DB->get_records_sql("SELECT p.*, d.course, d.forumlv
                                 FROM {forumlv_posts} p
                                 JOIN {forumlv_discussions} d ON d.id = p.discussion
                                 WHERE p.mailed = :mailed
                                 $selectsql
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
function forumlv_mark_old_posts_as_mailed($endtime, $now=null) {
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

    if (empty($CFG->forumlv_enabletimedposts)) {
        return $DB->execute("UPDATE {forumlv_posts}
                             SET mailed = :mailedsuccess
                             WHERE (created < :endtime OR mailnow = :mailnow)
                             AND mailed = :mailedpending", $params);
    } else {
        return $DB->execute("UPDATE {forumlv_posts}
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

    if (!empty($CFG->forumlv_enabletimedposts)) {
        $cm = get_coursemodule_from_instance('forumlv', $forumlvid);
        if (!has_capability('mod/forumlv:viewhiddentimedposts' , context_module::instance($cm->id))) {
            $now = time();
            $timedsql = "AND (d.timestart < ? AND (d.timeend = 0 OR d.timeend > ?))";
            $params[] = $now;
            $params[] = $now;
        }
    }

    $allnames = get_all_user_name_fields(true, 'u');
    return $DB->get_records_sql("SELECT p.*, d.forumlv, $allnames, u.email, u.picture, u.imagealt
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
    if (!empty($CFG->forumlv_enabletimedposts)) {
        $cm = get_coursemodule_from_instance('forumlv', $forumlvid);
        if (!has_capability('mod/forumlv:viewhiddentimedposts' , context_module::instance($cm->id))) {
            $now = time();
            $timedsql = "AND (d.timestart < ? AND (d.timeend = 0 OR d.timeend > ?))";
            $params[] = $now;
            $params[] = $now;
        }
    }

    return $DB->get_records_sql("SELECT DISTINCT d.*
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
    if (!empty($CFG->forumlv_enabletimedposts)) {
        $cm = get_coursemodule_from_instance('forumlv', $forumlvid);
        if (!has_capability('mod/forumlv:viewhiddentimedposts' , context_module::instance($cm->id))) {
            $now = time();
            $timedsql = "AND (d.timestart < ? AND (d.timeend = 0 OR d.timeend > ?))";
            $params[] = $now;
            $params[] = $now;
        }
    }

    return $DB->get_record_sql("SELECT COUNT(p.id) AS postcount, MAX(p.modified) AS lastpost
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

    $allnames = get_all_user_name_fields(true, 'u');
    if ($log->action == "add post") {

        return $DB->get_record_sql("SELECT p.*, f.type AS forumlvtype, d.forumlv, d.groupid, $allnames, u.email, u.picture
                                 FROM {forumlv_discussions} d,
                                      {forumlv_posts} p,
                                      {forumlv} f,
                                      {user} u
                                WHERE p.id = ?
                                  AND d.id = p.discussion
                                  AND p.userid = u.id
                                  AND u.deleted <> '1'
                                  AND f.id = d.forumlv", array($log->info));


    } else if ($log->action == "add discussion") {

        return $DB->get_record_sql("SELECT p.*, f.type AS forumlvtype, d.forumlv, d.groupid, $allnames, u.email, u.picture
                                 FROM {forumlv_discussions} d,
                                      {forumlv_posts} p,
                                      {forumlv} f,
                                      {user} u
                                WHERE d.id = ?
                                  AND d.firstpost = p.id
                                  AND p.userid = u.id
                                  AND u.deleted <> '1'
                                  AND f.id = d.forumlv", array($log->info));
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

    return $DB->get_record_sql("SELECT p.*
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
function forumlv_count_discussion_replies($forumlvid, $forumlvsort="", $limit=-1, $page=-1, $perpage=0) {
    global $CFG, $DB;

    if ($limit > 0) {
        $limitfrom = 0;
        $limitnum  = $limit;
    } else if ($page != -1) {
        $limitfrom = $page*$perpage;
        $limitnum  = $perpage;
    } else {
        $limitfrom = 0;
        $limitnum  = 0;
    }

    if ($forumlvsort == "") {
        $orderby = "";
        $groupby = "";

    } else {
        $orderby = "ORDER BY $forumlvsort";
        $groupby = ", ".strtolower($forumlvsort);
        $groupby = str_replace('desc', '', $groupby);
        $groupby = str_replace('asc', '', $groupby);
    }

    if (($limitfrom == 0 and $limitnum == 0) or $forumlvsort == "") {
        $sql = "SELECT p.discussion, COUNT(p.id) AS replies, MAX(p.id) AS lastpostid
                  FROM {forumlv_posts} p
                       JOIN {forumlv_discussions} d ON p.discussion = d.id
                 WHERE p.parent > 0 AND d.forumlv = ?
              GROUP BY p.discussion";
        return $DB->get_records_sql($sql, array($forumlvid));

    } else {
        $sql = "SELECT p.discussion, (COUNT(p.id) - 1) AS replies, MAX(p.id) AS lastpostid
                  FROM {forumlv_posts} p
                       JOIN {forumlv_discussions} d ON p.discussion = d.id
                 WHERE d.forumlv = ?
              GROUP BY p.discussion $groupby $orderby";
        return $DB->get_records_sql($sql, array($forumlvid), $limitfrom, $limitnum);
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

    $now = round(time(), -2); // db cache friendliness

    $params = array($course->id);

    if (!isset($cache[$course->id])) {
        if (!empty($CFG->forumlv_enabletimedposts)) {
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

        if ($counts = $DB->get_records_sql($sql, $params)) {
            foreach ($counts as $count) {
                $counts[$count->id] = $count->dcount;
            }
            $cache[$course->id] = $counts;
        } else {
            $cache[$course->id] = array();
        }
    }

    if (empty($cache[$course->id][$forumlv->id])) {
        return 0;
    }

    $groupmode = groups_get_activity_groupmode($cm, $course);

    if ($groupmode != SEPARATEGROUPS) {
        return $cache[$course->id][$forumlv->id];
    }

    if (has_capability('moodle/site:accessallgroups', context_module::instance($cm->id))) {
        return $cache[$course->id][$forumlv->id];
    }

    require_once($CFG->dirroot.'/course/lib.php');

    $modinfo = get_fast_modinfo($course);

    $mygroups = $modinfo->get_groups($cm->groupingid);

    // add all groups posts
    $mygroups[-1] = -1;

    list($mygroups_sql, $params) = $DB->get_in_or_equal($mygroups);
    $params[] = $forumlv->id;

    if (!empty($CFG->forumlv_enabletimedposts)) {
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

    return $DB->get_field_sql($sql, $params);
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
 * @param int $groupid if groups enabled, get discussions for this group overriding the current group.
 *                     Use FORUMLV_POSTS_ALL_USER_GROUPS for all the user groups
 * @return array
 */
function forumlv_get_discussions($cm, $forumlvsort="", $fullpost=true, $unused=-1, $limit=-1,
                                $userlastmodified=false, $page=-1, $perpage=0, $groupid = -1) {
    global $CFG, $DB, $USER;

    $timelimit = '';

    $now = round(time(), -2);
    $params = array($cm->instance);

    $modcontext = context_module::instance($cm->id);

    if (!has_capability('mod/forumlv:viewdiscussion', $modcontext)) { /// User must have perms to view discussions
        return array();
    }

    if (!empty($CFG->forumlv_enabletimedposts)) { /// Users must fulfill timed posts

        if (!has_capability('mod/forumlv:viewhiddentimedposts', $modcontext)) {
            $timelimit = " AND ((d.timestart <= ? AND (d.timeend = 0 OR d.timeend > ?))";
            $params[] = $now;
            $params[] = $now;
            if (isloggedin()) {
                $timelimit .= " OR d.userid = ?";
                $params[] = $USER->id;
            }
            $timelimit .= ")";
        }
    }

    if ($limit > 0) {
        $limitfrom = 0;
        $limitnum  = $limit;
    } else if ($page != -1) {
        $limitfrom = $page*$perpage;
        $limitnum  = $perpage;
    } else {
        $limitfrom = 0;
        $limitnum  = 0;
    }

    $groupmode    = groups_get_activity_groupmode($cm);

    if ($groupmode) {

        if (empty($modcontext)) {
            $modcontext = context_module::instance($cm->id);
        }

        // Special case, we received a groupid to override currentgroup.
        if ($groupid > 0) {
            $course = get_course($cm->course);
            if (!groups_group_visible($groupid, $course, $cm)) {
                // User doesn't belong to this group, return nothing.
                return array();
            }
            $currentgroup = $groupid;
        } else if ($groupid === -1) {
            $currentgroup = groups_get_activity_group($cm);
        } else {
            // Get discussions for all groups current user can see.
            $currentgroup = null;
        }

        if ($groupmode == VISIBLEGROUPS or has_capability('moodle/site:accessallgroups', $modcontext)) {
            if ($currentgroup) {
                $groupselect = "AND (d.groupid = ? OR d.groupid = -1)";
                $params[] = $currentgroup;
            } else {
                $groupselect = "";
            }

        } else {
            // Separate groups.

            // Get discussions for all groups current user can see.
            if ($currentgroup === null) {
                $mygroups = array_keys(groups_get_all_groups($cm->course, $USER->id, $cm->groupingid, 'g.id'));
                if (empty($mygroups)) {
                     $groupselect = "AND d.groupid = -1";
                } else {
                    list($insqlgroups, $inparamsgroups) = $DB->get_in_or_equal($mygroups);
                    $groupselect = "AND (d.groupid = -1 OR d.groupid $insqlgroups)";
                    $params = array_merge($params, $inparamsgroups);
                }
            } else if ($currentgroup) {
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
        $forumlvsort = forumlv_get_default_sort_order();
    }
    if (empty($fullpost)) {
        $postdata = "p.id,p.subject,p.modified,p.discussion,p.userid";
    } else {
        $postdata = "p.*";
    }

    if (empty($userlastmodified)) {  // We don't need to know this
        $umfields = "";
        $umtable  = "";
    } else {
        $umfields = ', ' . get_all_user_name_fields(true, 'um', null, 'um') . ', um.email AS umemail, um.picture AS umpicture,
                        um.imagealt AS umimagealt';
        $umtable  = " LEFT JOIN {user} um ON (d.usermodified = um.id)";
    }

    $allnames = get_all_user_name_fields(true, 'u');
    $sql = "SELECT $postdata, d.name, d.timemodified, d.usermodified, d.groupid, d.timestart, d.timeend, d.pinned, $allnames,
                   u.email, u.picture, u.imagealt $umfields
              FROM {forumlv_discussions} d
                   JOIN {forumlv_posts} p ON p.discussion = d.id
                   JOIN {user} u ON p.userid = u.id
                   $umtable
             WHERE d.forumlv = ? AND p.parent = 0
                   $timelimit $groupselect
          ORDER BY $forumlvsort, d.id DESC";
    return $DB->get_records_sql($sql, $params, $limitfrom, $limitnum);
}

/**
 * Gets the neighbours (previous and next) of a discussion.
 *
 * The calculation is based on the timemodified when time modified or time created is identical
 * It will revert to using the ID to sort consistently. This is better tha skipping a discussion.
 *
 * For blog-style forumlvs, the calculation is based on the original creation time of the
 * blog post.
 *
 * Please note that this does not check whether or not the discussion passed is accessible
 * by the user, it simply uses it as a reference to find the neighbours. On the other hand,
 * the returned neighbours are checked and are accessible to the current user.
 *
 * @param object $cm The CM record.
 * @param object $discussion The discussion record.
 * @param object $forumlv The forumlv instance record.
 * @return array That always contains the keys 'prev' and 'next'. When there is a result
 *               they contain the record with minimal information such as 'id' and 'name'.
 *               When the neighbour is not found the value is false.
 */
function forumlv_get_discussion_neighbours($cm, $discussion, $forumlv) {
    global $CFG, $DB, $USER;

    if ($cm->instance != $discussion->forumlv or $discussion->forumlv != $forumlv->id or $forumlv->id != $cm->instance) {
        throw new coding_exception('Discussion is not part of the same forumlv.');
    }

    $neighbours = array('prev' => false, 'next' => false);
    $now = round(time(), -2);
    $params = array();

    $modcontext = context_module::instance($cm->id);
    $groupmode    = groups_get_activity_groupmode($cm);
    $currentgroup = groups_get_activity_group($cm);

    // Users must fulfill timed posts.
    $timelimit = '';
    if (!empty($CFG->forumlv_enabletimedposts)) {
        if (!has_capability('mod/forumlv:viewhiddentimedposts', $modcontext)) {
            $timelimit = ' AND ((d.timestart <= :tltimestart AND (d.timeend = 0 OR d.timeend > :tltimeend))';
            $params['tltimestart'] = $now;
            $params['tltimeend'] = $now;
            if (isloggedin()) {
                $timelimit .= ' OR d.userid = :tluserid';
                $params['tluserid'] = $USER->id;
            }
            $timelimit .= ')';
        }
    }

    // Limiting to posts accessible according to groups.
    $groupselect = '';
    if ($groupmode) {
        if ($groupmode == VISIBLEGROUPS || has_capability('moodle/site:accessallgroups', $modcontext)) {
            if ($currentgroup) {
                $groupselect = 'AND (d.groupid = :groupid OR d.groupid = -1)';
                $params['groupid'] = $currentgroup;
            }
        } else {
            if ($currentgroup) {
                $groupselect = 'AND (d.groupid = :groupid OR d.groupid = -1)';
                $params['groupid'] = $currentgroup;
            } else {
                $groupselect = 'AND d.groupid = -1';
            }
        }
    }

    $params['forumlvid'] = $cm->instance;
    $params['discid1'] = $discussion->id;
    $params['discid2'] = $discussion->id;
    $params['discid3'] = $discussion->id;
    $params['discid4'] = $discussion->id;
    $params['disctimecompare1'] = $discussion->timemodified;
    $params['disctimecompare2'] = $discussion->timemodified;
    $params['pinnedstate1'] = (int) $discussion->pinned;
    $params['pinnedstate2'] = (int) $discussion->pinned;
    $params['pinnedstate3'] = (int) $discussion->pinned;
    $params['pinnedstate4'] = (int) $discussion->pinned;

    $sql = "SELECT d.id, d.name, d.timemodified, d.groupid, d.timestart, d.timeend
              FROM {forumlv_discussions} d
              JOIN {forumlv_posts} p ON d.firstpost = p.id
             WHERE d.forumlv = :forumlvid
               AND d.id <> :discid1
                   $timelimit
                   $groupselect";
    $comparefield = "d.timemodified";
    $comparevalue = ":disctimecompare1";
    $comparevalue2  = ":disctimecompare2";
    if (!empty($CFG->forumlv_enabletimedposts)) {
        // Here we need to take into account the release time (timestart)
        // if one is set, of the neighbouring posts and compare it to the
        // timestart or timemodified of *this* post depending on if the
        // release date of this post is in the future or not.
        // This stops discussions that appear later because of the
        // timestart value from being buried under discussions that were
        // made afterwards.
        $comparefield = "CASE WHEN d.timemodified < d.timestart
                                THEN d.timestart ELSE d.timemodified END";
        if ($discussion->timemodified < $discussion->timestart) {
            // Normally we would just use the timemodified for sorting
            // discussion posts. However, when timed discussions are enabled,
            // then posts need to be sorted base on the later of timemodified
            // or the release date of the post (timestart).
            $params['disctimecompare1'] = $discussion->timestart;
            $params['disctimecompare2'] = $discussion->timestart;
        }
    }
    $orderbydesc = forumlv_get_default_sort_order(true, $comparefield, 'd', false);
    $orderbyasc = forumlv_get_default_sort_order(false, $comparefield, 'd', false);

    if ($forumlv->type === 'blog') {
         $subselect = "SELECT pp.created
                   FROM {forumlv_discussions} dd
                   JOIN {forumlv_posts} pp ON dd.firstpost = pp.id ";

         $subselectwhere1 = " WHERE dd.id = :discid3";
         $subselectwhere2 = " WHERE dd.id = :discid4";

         $comparefield = "p.created";

         $sub1 = $subselect.$subselectwhere1;
         $comparevalue = "($sub1)";

         $sub2 = $subselect.$subselectwhere2;
         $comparevalue2 = "($sub2)";

         $orderbydesc = "d.pinned, p.created DESC";
         $orderbyasc = "d.pinned, p.created ASC";
    }

    $prevsql = $sql . " AND ( (($comparefield < $comparevalue) AND :pinnedstate1 = d.pinned)
                         OR ($comparefield = $comparevalue2 AND (d.pinned = 0 OR d.pinned = :pinnedstate4) AND d.id < :discid2)
                         OR (d.pinned = 0 AND d.pinned <> :pinnedstate2))
                   ORDER BY CASE WHEN d.pinned = :pinnedstate3 THEN 1 ELSE 0 END DESC, $orderbydesc, d.id DESC";

    $nextsql = $sql . " AND ( (($comparefield > $comparevalue) AND :pinnedstate1 = d.pinned)
                         OR ($comparefield = $comparevalue2 AND (d.pinned = 1 OR d.pinned = :pinnedstate4) AND d.id > :discid2)
                         OR (d.pinned = 1 AND d.pinned <> :pinnedstate2))
                   ORDER BY CASE WHEN d.pinned = :pinnedstate3 THEN 1 ELSE 0 END DESC, $orderbyasc, d.id ASC";

    $neighbours['prev'] = $DB->get_record_sql($prevsql, $params, IGNORE_MULTIPLE);
    $neighbours['next'] = $DB->get_record_sql($nextsql, $params, IGNORE_MULTIPLE);
    return $neighbours;
}

/**
 * Get the sql to use in the ORDER BY clause for forumlv discussions.
 *
 * This has the ordering take timed discussion windows into account.
 *
 * @param bool $desc True for DESC, False for ASC.
 * @param string $compare The field in the SQL to compare to normally sort by.
 * @param string $prefix The prefix being used for the discussion table.
 * @param bool $pinned sort pinned posts to the top
 * @return string
 */
function forumlv_get_default_sort_order($desc = true, $compare = 'd.timemodified', $prefix = 'd', $pinned = true) {
    global $CFG;

    if (!empty($prefix)) {
        $prefix .= '.';
    }

    $dir = $desc ? 'DESC' : 'ASC';

    if ($pinned == true) {
        $pinned = "{$prefix}pinned DESC,";
    } else {
        $pinned = '';
    }

    $sort = "{$prefix}timemodified";
    if (!empty($CFG->forumlv_enabletimedposts)) {
        $sort = "CASE WHEN {$compare} < {$prefix}timestart
                 THEN {$prefix}timestart
                 ELSE {$compare}
                 END";
    }
    return "$pinned $sort $dir";
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
    $cutoffdate = $now - ($CFG->forumlv_oldpostdays*24*60*60);

    $params = array();
    $groupmode    = groups_get_activity_groupmode($cm);
    $currentgroup = groups_get_activity_group($cm);

    if ($groupmode) {
        $modcontext = context_module::instance($cm->id);

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

    if (!empty($CFG->forumlv_enabletimedposts)) {
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

    if ($unreads = $DB->get_records_sql($sql, $params)) {
        foreach ($unreads as $unread) {
            $unreads[$unread->id] = $unread->unread;
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
    $params = array($cm->instance);
    $groupmode    = groups_get_activity_groupmode($cm);
    $currentgroup = groups_get_activity_group($cm);

    if ($groupmode) {
        $modcontext = context_module::instance($cm->id);

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

    $timelimit = "";

    if (!empty($CFG->forumlv_enabletimedposts)) {

        $modcontext = context_module::instance($cm->id);

        if (!has_capability('mod/forumlv:viewhiddentimedposts', $modcontext)) {
            $timelimit = " AND ((d.timestart <= ? AND (d.timeend = 0 OR d.timeend > ?))";
            $params[] = $now;
            $params[] = $now;
            if (isloggedin()) {
                $timelimit .= " OR d.userid = ?";
                $params[] = $USER->id;
            }
            $timelimit .= ")";
        }
    }

    $sql = "SELECT COUNT(d.id)
              FROM {forumlv_discussions} d
                   JOIN {forumlv_posts} p ON p.discussion = d.id
             WHERE d.forumlv = ? AND p.parent = 0
                   $groupselect $timelimit";

    return $DB->get_field_sql($sql, $params);
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

    if ($forumlvs = $DB->get_records_select("forumlv", "course = ? AND type = ?", array($courseid, $type), "id ASC")) {
        // There should always only be ONE, but with the right combination of
        // errors there might be more.  In this case, just return the oldest one (lowest ID).
        foreach ($forumlvs as $forumlv) {
            return $forumlv;   // ie the first one
        }
    }

    // Doesn't exist, so create one now.
    $forumlv = new stdClass();
    $forumlv->course = $courseid;
    $forumlv->type = "$type";
    if (!empty($USER->htmleditor)) {
        $forumlv->introformat = $USER->htmleditor;
    }
    switch ($forumlv->type) {
        case "news":
            $forumlv->name  = get_string("namenews", "forumlv");
            $forumlv->intro = get_string("intronews", "forumlv");
            $forumlv->forcesubscribe = FORUMLV_FORCESUBSCRIBE;
            $forumlv->assessed = 0;
            if ($courseid == SITEID) {
                $forumlv->name  = get_string("sitenews");
                $forumlv->forcesubscribe = 0;
            }
            break;
        case "social":
            $forumlv->name  = get_string("namesocial", "forumlv");
            $forumlv->intro = get_string("introsocial", "forumlv");
            $forumlv->assessed = 0;
            $forumlv->forcesubscribe = 0;
            break;
        case "blog":
            $forumlv->name = get_string('blogforumlv', 'forumlv');
            $forumlv->intro = get_string('introblog', 'forumlv');
            $forumlv->assessed = 0;
            $forumlv->forcesubscribe = 0;
            break;
        default:
            echo $OUTPUT->notification("That forumlv type doesn't exist!");
            return false;
            break;
    }

    $forumlv->timemodified = time();
    $forumlv->id = $DB->insert_record("forumlv", $forumlv);

    if (! $module = $DB->get_record("modules", array("name" => "forumlv"))) {
        echo $OUTPUT->notification("Could not find forumlv module!!");
        return false;
    }
    $mod = new stdClass();
    $mod->course = $courseid;
    $mod->module = $module->id;
    $mod->instance = $forumlv->id;
    $mod->section = 0;
    include_once("$CFG->dirroot/course/lib.php");
    if (! $mod->coursemodule = add_course_module($mod) ) {
        echo $OUTPUT->notification("Could not add a new course module to the course '" . $courseid . "'");
        return false;
    }
    $sectionid = course_add_cm_to_section($courseid, $mod->coursemodule, 0);
    return $DB->get_record("forumlv", array("id" => "$forumlv->id"));
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
function forumlv_print_post($post, $discussion, $forumlv, &$cm, $course, $ownpost=false, $reply=false, $link=false,
                          $footer="", $highlight="", $postisread=null, $dummyifcantsee=true, $istracked=null, $return=false) {
    global $USER, $CFG, $OUTPUT;

    require_once($CFG->libdir . '/filelib.php');

    // String cache
    static $str;
    // This is an extremely hacky way to ensure we only print the 'unread' anchor
    // the first time we encounter an unread post on a page. Ideally this would
    // be moved into the caller somehow, and be better testable. But at the time
    // of dealing with this bug, this static workaround was the most surgical and
    // it fits together with only printing th unread anchor id once on a given page.
    static $firstunreadanchorprinted = false;

    $modcontext = context_module::instance($cm->id);

    $post->course = $course->id;
    $post->forumlv  = $forumlv->id;
    $post->message = file_rewrite_pluginfile_urls($post->message, 'pluginfile.php', $modcontext->id, 'mod_forumlv', 'post', $post->id);
    if (!empty($CFG->enableplagiarism)) {
        require_once($CFG->libdir.'/plagiarismlib.php');
        $post->message .= plagiarism_get_links(array('userid' => $post->userid,
            'content' => $post->message,
            'cmid' => $cm->id,
            'course' => $post->course,
            'forumlv' => $post->forumlv));
    }

    // caching
    if (!isset($cm->cache)) {
        $cm->cache = new stdClass;
    }

    if (!isset($cm->cache->caps)) {
        $cm->cache->caps = array();
        $cm->cache->caps['mod/forumlv:viewdiscussion']   = has_capability('mod/forumlv:viewdiscussion', $modcontext);
        $cm->cache->caps['moodle/site:viewfullnames']  = has_capability('moodle/site:viewfullnames', $modcontext);
        $cm->cache->caps['mod/forumlv:editanypost']      = has_capability('mod/forumlv:editanypost', $modcontext);
        $cm->cache->caps['mod/forumlv:splitdiscussions'] = has_capability('mod/forumlv:splitdiscussions', $modcontext);
        $cm->cache->caps['mod/forumlv:deleteownpost']    = has_capability('mod/forumlv:deleteownpost', $modcontext);
        $cm->cache->caps['mod/forumlv:deleteanypost']    = has_capability('mod/forumlv:deleteanypost', $modcontext);
        $cm->cache->caps['mod/forumlv:viewanyrating']    = has_capability('mod/forumlv:viewanyrating', $modcontext);
        $cm->cache->caps['mod/forumlv:exportpost']       = has_capability('mod/forumlv:exportpost', $modcontext);
        $cm->cache->caps['mod/forumlv:exportownpost']    = has_capability('mod/forumlv:exportownpost', $modcontext);
    }

    if (!isset($cm->uservisible)) {
        $cm->uservisible = \core_availability\info_module::is_user_visible($cm, 0, false);
    }

    if ($istracked && is_null($postisread)) {
        $postisread = forumlv_tp_is_post_read($USER->id, $post);
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
        $output .= html_writer::tag('a', '', array('id'=>'p'.$post->id));
        $output .= html_writer::start_tag('div', array('class'=>'forumpost clearfix',
                                                       'role' => 'region',
                                                       'aria-label' => get_string('hiddenforumlvpost', 'forumlv')));
        $output .= html_writer::start_tag('div', array('class'=>'row header'));
        $output .= html_writer::tag('div', '', array('class'=>'left picture')); // Picture
        if ($post->parent) {
            $output .= html_writer::start_tag('div', array('class'=>'topic'));
        } else {
            $output .= html_writer::start_tag('div', array('class'=>'topic starter'));
        }
        $output .= html_writer::tag('div', get_string('forumlvsubjecthidden','forumlv'), array('class' => 'subject',
                                                                                           'role' => 'header')); // Subject.
        $output .= html_writer::tag('div', get_string('forumlvauthorhidden', 'forumlv'), array('class' => 'author',
                                                                                           'role' => 'header')); // Author.
        $output .= html_writer::end_tag('div');
        $output .= html_writer::end_tag('div'); // row
        $output .= html_writer::start_tag('div', array('class'=>'row'));
        $output .= html_writer::tag('div', '&nbsp;', array('class'=>'left side')); // Groups
        $output .= html_writer::tag('div', get_string('forumlvbodyhidden','forumlv'), array('class'=>'content')); // Content
        $output .= html_writer::end_tag('div'); // row
        $output .= html_writer::end_tag('div'); // forumlvpost

        if ($return) {
            return $output;
        }
        echo $output;
        return;
    }

    if (empty($str)) {
        $str = new stdClass;
        $str->edit         = get_string('edit', 'forumlv');
        $str->delete       = get_string('delete', 'forumlv');
        $str->reply        = get_string('reply', 'forumlv');
        $str->parent       = get_string('parent', 'forumlv');
        $str->pruneheading = get_string('pruneheading', 'forumlv');
        $str->prune        = get_string('prune', 'forumlv');
        $str->displaymode     = get_user_preferences('forumlv_displaymode', $CFG->forumlv_displaymode);
        $str->markread     = get_string('markread', 'forumlv');
        $str->markunread   = get_string('markunread', 'forumlv');
    }

    $discussionlink = new moodle_url('/mod/forumlv/discuss.php', array('d'=>$post->discussion));

    // Build an object that represents the posting user
    $postuser = new stdClass;
    $postuserfields = explode(',', user_picture::fields());
    $postuser = username_load_fields_from_object($postuser, $post, null, $postuserfields);
    $postuser->id = $post->userid;
    $postuser->fullname    = fullname($postuser, $cm->cache->caps['moodle/site:viewfullnames']);
    $postuser->profilelink = new moodle_url('/user/view.php', array('id'=>$post->userid, 'course'=>$course->id));

    // Prepare the groups the posting user belongs to
    if (isset($cm->cache->usersgroups)) {
        $groups = array();
        if (isset($cm->cache->usersgroups[$post->userid])) {
            foreach ($cm->cache->usersgroups[$post->userid] as $gid) {
                $groups[$gid] = $cm->cache->groups[$gid];
            }
        }
    } else {
        $groups = groups_get_all_groups($course->id, $post->userid, $cm->groupingid);
    }

    // Prepare the attachements for the post, files then images
    list($attachments, $attachedimages) = forumlv_print_attachments($post, $cm, 'separateimages');

    // Determine if we need to shorten this post
    $shortenpost = ($link && (strlen(strip_tags($post->message)) > $CFG->forumlv_longpost));


    // Prepare an array of commands
    $commands = array();

    // Add a permalink.
    $permalink = new moodle_url($discussionlink);
    $permalink->set_anchor('p' . $post->id);
    $commands[] = array('url' => $permalink, 'text' => get_string('permalink', 'forumlv'));

    // SPECIAL CASE: The front page can display a news item post to non-logged in users.
    // Don't display the mark read / unread controls in this case.
    if ($istracked && $CFG->forumlv_usermarksread && isloggedin()) {
        $url = new moodle_url($discussionlink, array('postid'=>$post->id, 'mark'=>'unread'));
        $text = $str->markunread;
        if (!$postisread) {
            $url->param('mark', 'read');
            $text = $str->markread;
        }
        if ($str->displaymode == FORUMLV_MODE_THREADED) {
            $url->param('parent', $post->parent);
        } else {
            $url->set_anchor('p'.$post->id);
        }
        $commands[] = array('url'=>$url, 'text'=>$text);
    }

    // Zoom in to the parent specifically
    if ($post->parent) {
        $url = new moodle_url($discussionlink);
        if ($str->displaymode == FORUMLV_MODE_THREADED) {
            $url->param('parent', $post->parent);
        } else {
            $url->set_anchor('p'.$post->parent);
        }
        $commands[] = array('url'=>$url, 'text'=>$str->parent);
    }

    // Hack for allow to edit news posts those are not displayed yet until they are displayed
    $age = time() - $post->created;
    if (!$post->parent && $forumlv->type == 'news' && $discussion->timestart > time()) {
        $age = 0;
    }

    if ($forumlv->type == 'single' and $discussion->firstpost == $post->id) {
        if (has_capability('moodle/course:manageactivities', $modcontext)) {
            // The first post in single simple is the forumlv description.
            $commands[] = array('url'=>new moodle_url('/course/modedit.php', array('update'=>$cm->id, 'sesskey'=>sesskey(), 'return'=>1)), 'text'=>$str->edit);
        }
    } else if (($ownpost && $age < $CFG->maxeditingtime) || $cm->cache->caps['mod/forumlv:editanypost']) {
        $commands[] = array('url'=>new moodle_url('/mod/forumlv/post.php', array('edit'=>$post->id)), 'text'=>$str->edit);
    }

    if ($cm->cache->caps['mod/forumlv:splitdiscussions'] && $post->parent && $forumlv->type != 'single') {
        $commands[] = array('url'=>new moodle_url('/mod/forumlv/post.php', array('prune'=>$post->id)), 'text'=>$str->prune, 'title'=>$str->pruneheading);
    }

    if ($forumlv->type == 'single' and $discussion->firstpost == $post->id) {
        // Do not allow deleting of first post in single simple type.
    } else if (($ownpost && $age < $CFG->maxeditingtime && $cm->cache->caps['mod/forumlv:deleteownpost']) || $cm->cache->caps['mod/forumlv:deleteanypost']) {
        $commands[] = array('url'=>new moodle_url('/mod/forumlv/post.php', array('delete'=>$post->id)), 'text'=>$str->delete);
    }

    if ($reply) {
        $commands[] = array('url'=>new moodle_url('/mod/forumlv/post.php#mformforumlv', array('reply'=>$post->id)), 'text'=>$str->reply);
    }

    if ($CFG->enableportfolios && ($cm->cache->caps['mod/forumlv:exportpost'] || ($ownpost && $cm->cache->caps['mod/forumlv:exportownpost']))) {
        $p = array('postid' => $post->id);
        require_once($CFG->libdir.'/portfoliolib.php');
        $button = new portfolio_add_button();
        $button->set_callback_options('forumlv_portfolio_caller', array('postid' => $post->id), 'mod_forumlv');
        if (empty($attachments)) {
            $button->set_formats(PORTFOLIO_FORMAT_PLAINHTML);
        } else {
            $button->set_formats(PORTFOLIO_FORMAT_RICHHTML);
        }

        $porfoliohtml = $button->to_html(PORTFOLIO_ADD_TEXT_LINK);
        if (!empty($porfoliohtml)) {
            $commands[] = $porfoliohtml;
        }
    }
    // Finished building commands


    // Begin output

    $output  = '';

    if ($istracked) {
        if ($postisread) {
            $forumlvpostclass = ' read';
        } else {
            $forumlvpostclass = ' unread';
            // If this is the first unread post printed then give it an anchor and id of unread.
            if (!$firstunreadanchorprinted) {
                $output .= html_writer::tag('a', '', array('id' => 'unread'));
                $firstunreadanchorprinted = true;
            }
        }
    } else {
        // ignore trackign status if not tracked or tracked param missing
        $forumlvpostclass = '';
    }

    $topicclass = '';
    if (empty($post->parent)) {
        $topicclass = ' firstpost starter';
    }

    if (!empty($post->lastpost)) {
        $forumlvpostclass .= ' lastpost';
    }

    $postbyuser = new stdClass;
    $postbyuser->post = $post->subject;
    $postbyuser->user = $postuser->fullname;
    $discussionbyuser = get_string('postbyuser', 'forumlv', $postbyuser);
    $output .= html_writer::tag('a', '', array('id'=>'p'.$post->id));
    $output .= html_writer::start_tag('div', array('class'=>'forumpost clearfix'.$forumlvpostclass.$topicclass,
                                                   'role' => 'region',
                                                   'aria-label' => $discussionbyuser));
    $output .= html_writer::start_tag('div', array('class'=>'row header clearfix'));
    $output .= html_writer::start_tag('div', array('class'=>'left picture'));
    $output .= $OUTPUT->user_picture($postuser, array('courseid'=>$course->id));
    $output .= html_writer::end_tag('div');


    $output .= html_writer::start_tag('div', array('class'=>'topic'.$topicclass));

    $postsubject = $post->subject;
    if (empty($post->subjectnoformat)) {
        $postsubject = format_string($postsubject);
    }
    $output .= html_writer::tag('div', $postsubject, array('class'=>'subject',
                                                           'role' => 'heading',
                                                           'aria-level' => '2'));

    $by = new stdClass();
    $by->name = html_writer::link($postuser->profilelink, $postuser->fullname);
    $by->date = userdate($post->modified);
    $output .= html_writer::tag('div', get_string('bynameondate', 'forumlv', $by), array('class'=>'author',
                                                                                       'role' => 'heading',
                                                                                       'aria-level' => '2'));

    $output .= html_writer::end_tag('div'); //topic
    $output .= html_writer::end_tag('div'); //row

    $output .= html_writer::start_tag('div', array('class'=>'row maincontent clearfix'));
    $output .= html_writer::start_tag('div', array('class'=>'left'));

    $groupoutput = '';
    if ($groups) {
        $groupoutput = print_group_picture($groups, $course->id, false, true, true);
    }
    if (empty($groupoutput)) {
        $groupoutput = '&nbsp;';
    }
    $output .= html_writer::tag('div', $groupoutput, array('class'=>'grouppictures'));

    $output .= html_writer::end_tag('div'); //left side
    $output .= html_writer::start_tag('div', array('class'=>'no-overflow'));
    $output .= html_writer::start_tag('div', array('class'=>'content'));

    $options = new stdClass;
    $options->para    = false;
    $options->trusted = $post->messagetrust;
    $options->context = $modcontext;
    if ($shortenpost) {
        // Prepare shortened version by filtering the text then shortening it.
        $postclass    = 'shortenedpost';
        $postcontent  = format_text($post->message, $post->messageformat, $options);
        $postcontent  = shorten_text($postcontent, $CFG->forumlv_shortpost);
        $postcontent .= html_writer::link($discussionlink, get_string('readtherest', 'forumlv'));
        $postcontent .= html_writer::tag('div', '('.get_string('numwords', 'moodle', count_words($post->message)).')',
            array('class'=>'post-word-count'));
    } else {
        // Prepare whole post
        $postclass    = 'fullpost';
        $postcontent  = format_text($post->message, $post->messageformat, $options, $course->id);
        if (!empty($highlight)) {
            $postcontent = highlight($highlight, $postcontent);
        }
        if (!empty($forumlv->displaywordcount)) {
            $postcontent .= html_writer::tag('div', get_string('numwords', 'moodle', count_words($post->message)),
                array('class'=>'post-word-count'));
        }
        $postcontent .= html_writer::tag('div', $attachedimages, array('class'=>'attachedimages'));
    }

    // Output the post content
    $output .= html_writer::tag('div', $postcontent, array('class'=>'posting '.$postclass));
    $output .= html_writer::end_tag('div'); // Content
    $output .= html_writer::end_tag('div'); // Content mask
    $output .= html_writer::end_tag('div'); // Row

    $output .= html_writer::start_tag('div', array('class'=>'row side'));
    $output .= html_writer::tag('div','&nbsp;', array('class'=>'left'));
    $output .= html_writer::start_tag('div', array('class'=>'options clearfix'));

    if (!empty($attachments)) {
        $output .= html_writer::tag('div', $attachments, array('class' => 'attachments'));
    }

    // Output ratings
    //@lvs Adicionar escala LV
    /*if (!empty($post->rating)) {
        $output .= html_writer::tag('div', $OUTPUT->render($post->rating), array('class'=>'forumlv-post-rating'));
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
    $output .= html_writer::tag('div', implode(' | ', $commandhtml), array('class'=>'commands'));

    // Output link to post if required
    if ($link && forumlv_user_can_post($forumlv, $discussion, $USER, $cm, $course, $modcontext)) {
        if ($post->replies == 1) {
            $replystring = get_string('repliesone', 'forumlv', $post->replies);
        } else {
            $replystring = get_string('repliesmany', 'forumlv', $post->replies);
        }
        if (!empty($discussion->unread) && $discussion->unread !== '-') {
            $replystring .= ' <span class="sep">/</span> <span class="unread">';
            if ($discussion->unread == 1) {
                $replystring .= get_string('unreadpostsone', 'forumlv');
            } else {
                $replystring .= get_string('unreadpostsnumber', 'forumlv', $discussion->unread);
            }
            $replystring .= '</span>';
        }

        $output .= html_writer::start_tag('div', array('class'=>'link'));
        $output .= html_writer::link($discussionlink, get_string('discussthistopic', 'forumlv'));
        $output .= '&nbsp;('.$replystring.')';
        $output .= html_writer::end_tag('div'); // link
    }

    // Output footer if required
    if ($footer) {
        $output .= html_writer::tag('div', $footer, array('class'=>'footer'));
    }

    // Close remaining open divs
    $output .= html_writer::end_tag('div'); // content
    $output .= html_writer::end_tag('div'); // row
    $output .= html_writer::end_tag('div'); // forumlvpost

    // Mark the forumlv post as read if required
    if ($istracked && !$CFG->forumlv_usermarksread && !$postisread) {
        forumlv_tp_mark_post_read($USER->id, $post, $forumlv->id);
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
    return array(
        'view'    => has_capability('mod/forumlv:viewrating', $context),
        'viewany' => has_capability('mod/forumlv:viewanyrating', $context),
        'viewall' => has_capability('mod/forumlv:viewallratings', $context),
        'rate'    => has_capability('mod/forumlv:rate', $context)
    );
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
    if ($params['rateduserid'] == $USER->id) {
        throw new rating_exception('nopermissiontorate');
    }

    // Fetch all the related records ... we need to do this anyway to call forumlv_user_can_see_post
    $post = $DB->get_record('forumlv_posts', array('id' => $params['itemid'], 'userid' => $params['rateduserid']), '*', MUST_EXIST);
    $discussion = $DB->get_record('forumlv_discussions', array('id' => $post->discussion), '*', MUST_EXIST);
    $forumlv = $DB->get_record('forumlv', array('id' => $discussion->forumlv), '*', MUST_EXIST);
    $course = $DB->get_record('course', array('id' => $forumlv->course), '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance('forumlv', $forumlv->id, $course->id , false, MUST_EXIST);
    $context = context_module::instance($cm->id);

    // Make sure the context provided is the context of the forumlv
    if ($context->id != $params['context']->id) {
        throw new rating_exception('invalidcontext');
    }

    if ($forumlv->scale != $params['scaleid']) {
        //the scale being submitted doesnt match the one in the database
        throw new rating_exception('invalidscaleid');
    }

    // check the item we're rating was created in the assessable time window
    if (!empty($forumlv->assesstimestart) && !empty($forumlv->assesstimefinish)) {
        if ($post->created < $forumlv->assesstimestart || $post->created > $forumlv->assesstimefinish) {
            throw new rating_exception('notavailable');
        }
    }

    //check that the submitted rating is valid for the scale

    // lower limit
    if ($params['rating'] < 0  && $params['rating'] != RATING_UNSET_RATING) {
        throw new rating_exception('invalidnum');
    }

    // upper limit
    if ($forumlv->scale < 0) {
        //its a custom scale
        $scalerecord = $DB->get_record('scale', array('id' => -$forumlv->scale));
        if ($scalerecord) {
            $scalearray = explode(',', $scalerecord->scale);
            if ($params['rating'] > count($scalearray)) {
                throw new rating_exception('invalidnum');
            }
        } else {
            throw new rating_exception('invalidscaleid');
        }
    } else if ($params['rating'] > $forumlv->scale) {
        //if its numeric and submitted rating is above maximum
        throw new rating_exception('invalidnum');
    }

    // Make sure groups allow this user to see the item they're rating
    if ($discussion->groupid > 0 and $groupmode = groups_get_activity_groupmode($cm, $course)) {   // Groups are being used
        if (!groups_group_exists($discussion->groupid)) { // Can't find group
            throw new rating_exception('cannotfindgroup');//something is wrong
        }

        if (!groups_is_member($discussion->groupid) and !has_capability('moodle/site:accessallgroups', $context)) {
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
 * Can the current user see ratings for a given itemid?
 *
 * @param array $params submitted data
 *            contextid => int contextid [required]
 *            component => The component for this module - should always be mod_forumlv [required]
 *            ratingarea => object the context in which the rated items exists [required]
 *            itemid => int the ID of the object being rated [required]
 *            scaleid => int scale id [optional]
 * @return bool
 * @throws coding_exception
 * @throws rating_exception
 */
function mod_forumlv_rating_can_see_item_ratings($params) {
    global $DB, $USER;

    // Check the component is mod_forumlv.
    if (!isset($params['component']) || $params['component'] != 'mod_forumlv') {
        throw new rating_exception('invalidcomponent');
    }

    // Check the ratingarea is post (the only rating area in forumlv).
    if (!isset($params['ratingarea']) || $params['ratingarea'] != 'post') {
        throw new rating_exception('invalidratingarea');
    }

    if (!isset($params['itemid'])) {
        throw new rating_exception('invaliditemid');
    }

    $post = $DB->get_record('forumlv_posts', array('id' => $params['itemid']), '*', MUST_EXIST);
    $discussion = $DB->get_record('forumlv_discussions', array('id' => $post->discussion), '*', MUST_EXIST);
    $forumlv = $DB->get_record('forumlv', array('id' => $discussion->forumlv), '*', MUST_EXIST);
    $course = $DB->get_record('course', array('id' => $forumlv->course), '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance('forumlv', $forumlv->id, $course->id , false, MUST_EXIST);

    // Perform some final capability checks.
    if (!forumlv_user_can_see_post($forumlv, $discussion, $post, $USER, $cm)) {
        return false;
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
 * @param boolean $canviewhiddentimedposts True if user has the viewhiddentimedposts permission for this forumlv
 */
function forumlv_print_discussion_header(&$post, $forumlv, $group = -1, $datestring = "",
                                        $cantrack = true, $forumlvtracked = true, $canviewparticipants = true, $modcontext = null,
                                        $canviewhiddentimedposts = false) {

    global $COURSE, $USER, $CFG, $OUTPUT, $PAGE;

    static $rowcount;
    static $strmarkalldread;

    if (empty($modcontext)) {
        if (!$cm = get_coursemodule_from_instance('forumlv', $forumlv->id, $forumlv->course)) {
            print_error('invalidcoursemodule');
        }
        $modcontext = context_module::instance($cm->id);
    }

    if (!isset($rowcount)) {
        $rowcount = 0;
        $strmarkalldread = get_string('markalldread', 'forumlv');
    } else {
        $rowcount = ($rowcount + 1) % 2;
    }

    $post->subject = format_string($post->subject,true);

    $timeddiscussion = !empty($CFG->forumlv_enabletimedposts) && ($post->timestart || $post->timeend);
    $timedoutsidewindow = '';
    if ($timeddiscussion && ($post->timestart > time() || ($post->timeend != 0 && $post->timeend < time()))) {
        $timedoutsidewindow = ' dimmed_text';
    }

    echo "\n\n";
    echo '<tr class="discussion r'.$rowcount.$timedoutsidewindow.'">';

    $topicclass = 'topic starter';
    if (FORUMLV_DISCUSSION_PINNED == $post->pinned) {
        $topicclass .= ' pinned';
    }
    echo '<td class="'.$topicclass.'">';
    if (FORUMLV_DISCUSSION_PINNED == $post->pinned) {
        echo $OUTPUT->pix_icon('i/pinned', get_string('discussionpinned', 'forumlv'), 'mod_forumlv');
    }
    $canalwaysseetimedpost = $USER->id == $post->userid || $canviewhiddentimedposts;
    if ($timeddiscussion && $canalwaysseetimedpost) {
        echo $PAGE->get_renderer('mod_forumlv')->timed_discussion_tooltip($post, empty($timedoutsidewindow));
    }

    echo '<a href="'.$CFG->wwwroot.'/mod/forumlv/discuss.php?d='.$post->discussion.'">'.$post->subject.'</a>';
    echo "</td>\n";

    // Picture
    $postuser = new stdClass();
    $postuserfields = explode(',', user_picture::fields());
    $postuser = username_load_fields_from_object($postuser, $post, null, $postuserfields);
    $postuser->id = $post->userid;
    echo '<td class="picture">';
    echo $OUTPUT->user_picture($postuser, array('courseid'=>$forumlv->course));
    echo "</td>\n";

    // User name
    $fullname = fullname($postuser, has_capability('moodle/site:viewfullnames', $modcontext));
    echo '<td class="author">';
    echo '<a href="'.$CFG->wwwroot.'/user/view.php?id='.$post->userid.'&amp;course='.$forumlv->course.'">'.$fullname.'</a>';
    echo "</td>\n";

    // Group picture
    if ($group !== -1) {  // Groups are active - group is a group data object or NULL
        echo '<td class="picture group">';
        if (!empty($group->picture) and empty($group->hidepicture)) {
            if ($canviewparticipants && $COURSE->groupmode) {
                $picturelink = true;
            } else {
                $picturelink = false;
            }
            print_group_picture($group, $forumlv->course, false, false, $picturelink);
        } else if (isset($group->id)) {
            if ($canviewparticipants && $COURSE->groupmode) {
                echo '<a href="'.$CFG->wwwroot.'/user/index.php?id='.$forumlv->course.'&amp;group='.$group->id.'">'.$group->name.'</a>';
            } else {
                echo $group->name;
            }
        }
        echo "</td>\n";
    }

    if (has_capability('mod/forumlv:viewdiscussion', $modcontext)) {   // Show the column with replies
        echo '<td class="replies">';
        echo '<a href="'.$CFG->wwwroot.'/mod/forumlv/discuss.php?d='.$post->discussion.'">';
        echo $post->replies.'</a>';
        echo "</td>\n";

        if ($cantrack) {
            echo '<td class="replies">';
            if ($forumlvtracked) {
                if ($post->unread > 0) {
                    echo '<span class="unread">';
                    echo '<a href="'.$CFG->wwwroot.'/mod/forumlv/discuss.php?d='.$post->discussion.'#unread">';
                    echo $post->unread;
                    echo '</a>';
                    echo '<a title="'.$strmarkalldread.'" href="'.$CFG->wwwroot.'/mod/forumlv/markposts.php?f='.
                         $forumlv->id.'&amp;d='.$post->discussion.'&amp;mark=read&amp;returnpage=view.php&amp;sesskey=' . sesskey() . '">' .
                         '<img src="'.$OUTPUT->pix_url('t/markasread') . '" class="iconsmall" alt="'.$strmarkalldread.'" /></a>';
                    echo '</span>';
                } else {
                    echo '<span class="read">';
                    echo $post->unread;
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
    $usedate = (empty($post->timemodified)) ? $post->modified : $post->timemodified;  // Just in case
    $parenturl = '';
    $usermodified = new stdClass();
    $usermodified->id = $post->usermodified;
    $usermodified = username_load_fields_from_object($usermodified, $post, 'um');

    // In QA forumlvs we check that the user can view participants.
    if ($forumlv->type !== 'qanda' || $canviewparticipants) {
        echo '<a href="'.$CFG->wwwroot.'/user/view.php?id='.$post->usermodified.'&amp;course='.$forumlv->course.'">'.
             fullname($usermodified).'</a><br />';
        $parenturl = (empty($post->lastpostid)) ? '' : '&amp;parent='.$post->lastpostid;
    }

    echo '<a href="'.$CFG->wwwroot.'/mod/forumlv/discuss.php?d='.$post->discussion.$parenturl.'">'.
          userdate($usedate, $datestring).'</a>';
    echo "</td>\n";

    // is_guest should be used here as this also checks whether the user is a guest in the current course.
    // Guests and visitors cannot subscribe - only enrolled users.
    if ((!is_guest($modcontext, $USER) && isloggedin()) && has_capability('mod/forumlv:viewdiscussion', $modcontext)) {
        // Discussion subscription.
        if (\mod_forumlv\subscriptions::is_subscribable($forumlv)) {
            echo '<td class="discussionsubscription">';
            echo forumlv_get_discussion_subscription_icon($forumlv, $post->discussion);
            echo '</td>';
        }
    }

    echo "</tr>\n\n";

}

/**
 * Return the markup for the discussion subscription toggling icon.
 *
 * @param stdClass $forumlv The forumlv object.
 * @param int $discussionid The discussion to create an icon for.
 * @return string The generated markup.
 */
function forumlv_get_discussion_subscription_icon($forumlv, $discussionid, $returnurl = null, $includetext = false) {
    global $USER, $OUTPUT, $PAGE;

    if ($returnurl === null && $PAGE->url) {
        $returnurl = $PAGE->url->out();
    }

    $o = '';
    $subscriptionstatus = \mod_forumlv\subscriptions::is_subscribed($USER->id, $forumlv, $discussionid);
    $subscriptionlink = new moodle_url('/mod/forumlv/subscribe.php', array(
        'sesskey' => sesskey(),
        'id' => $forumlv->id,
        'd' => $discussionid,
        'returnurl' => $returnurl,
    ));

    if ($includetext) {
        $o .= $subscriptionstatus ? get_string('subscribed', 'mod_forumlv') : get_string('notsubscribed', 'mod_forumlv');
    }

    if ($subscriptionstatus) {
        $output = $OUTPUT->pix_icon('t/subscribed', get_string('clicktounsubscribe', 'forumlv'), 'mod_forumlv');
        if ($includetext) {
            $output .= get_string('subscribed', 'mod_forumlv');
        }

        return html_writer::link($subscriptionlink, $output, array(
                'title' => get_string('clicktounsubscribe', 'forumlv'),
                'class' => 'discussiontoggle iconsmall',
                'data-forumlvid' => $forumlv->id,
                'data-discussionid' => $discussionid,
                'data-includetext' => $includetext,
            ));

    } else {
        $output = $OUTPUT->pix_icon('t/unsubscribed', get_string('clicktosubscribe', 'forumlv'), 'mod_forumlv');
        if ($includetext) {
            $output .= get_string('notsubscribed', 'mod_forumlv');
        }

        return html_writer::link($subscriptionlink, $output, array(
                'title' => get_string('clicktosubscribe', 'forumlv'),
                'class' => 'discussiontoggle iconsmall',
                'data-forumlvid' => $forumlv->id,
                'data-discussionid' => $discussionid,
                'data-includetext' => $includetext,
            ));
    }
}

/**
 * Return a pair of spans containing classes to allow the subscribe and
 * unsubscribe icons to be pre-loaded by a browser.
 *
 * @return string The generated markup
 */
function forumlv_get_discussion_subscription_icon_preloaders() {
    $o = '';
    $o .= html_writer::span('&nbsp;', 'preload-subscribe');
    $o .= html_writer::span('&nbsp;', 'preload-unsubscribe');
    return $o;
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
function forumlv_print_mode_form($id, $mode, $forumlvtype='') {
    global $OUTPUT;
    if ($forumlvtype == 'single') {
        $select = new single_select(new moodle_url("/mod/forumlv/view.php", array('f'=>$id)), 'mode', forumlv_get_layout_modes(), $mode, null, "mode");
        $select->set_label(get_string('displaymode', 'forumlv'), array('class' => 'accesshide'));
        $select->class = "forummode";
    } else {
        $select = new single_select(new moodle_url("/mod/forumlv/discuss.php", array('d'=>$id)), 'mode', forumlv_get_layout_modes(), $mode, null, "mode");
        $select->set_label(get_string('displaymode', 'forumlv'), array('class' => 'accesshide'));
    }
    echo $OUTPUT->render($select);
}

/**
 * @global object
 * @param object $course
 * @param string $search
 * @return string
 */
function forumlv_search_form($course, $search='') {
    global $CFG, $OUTPUT;

    $output  = '<div class="forumlvsearch">';
    $output .= '<form action="'.$CFG->wwwroot.'/mod/forumlv/search.php" style="display:inline">';
    $output .= '<fieldset class="invisiblefieldset">';
    $output .= $OUTPUT->help_icon('search');
    $output .= '<label class="accesshide" for="search" >'.get_string('search', 'forumlv').'</label>';
    $output .= '<input id="search" name="search" type="text" size="18" value="'.s($search, true).'" />';
    $output .= '<label class="accesshide" for="searchforumlvs" >'.get_string('searchforumlvs', 'forumlv').'</label>';
    $output .= '<input id="searchforumlvs" value="'.get_string('searchforumlvs', 'forumlv').'" type="submit" />';
    $output .= '<input name="id" type="hidden" value="'.$course->id.'" />';
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

    if (! isset($SESSION->fromdiscussion)) {
        $referer = get_local_referer(false);
        // If the referer is NOT a login screen then save it.
        if (! strncasecmp("$CFG->wwwroot/login", $referer, 300)) {
            $SESSION->fromdiscussion = $referer;
        }
    }
}


/**
 * @global object
 * @param string|\moodle_url $default
 * @return string
 */
function forumlv_go_back_to($default) {
    global $SESSION;

    if (!empty($SESSION->fromdiscussion)) {
        $returnto = $SESSION->fromdiscussion;
        unset($SESSION->fromdiscussion);
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

    $newcontext = context_module::instance($newcm->id);
    $oldcontext = context_module::instance($oldcm->id);

    // loop through all posts, better not use attachment flag ;-)
    if ($posts = $DB->get_records('forumlv_posts', array('discussion'=>$discussion->id), '', 'id, attachment')) {
        foreach ($posts as $post) {
            $fs->move_area_files_to_new_context($oldcontext->id,
                    $newcontext->id, 'mod_forumlv', 'post', $post->id);
            $attachmentsmoved = $fs->move_area_files_to_new_context($oldcontext->id,
                    $newcontext->id, 'mod_forumlv', 'attachment', $post->id);
            if ($attachmentsmoved > 0 && $post->attachment != '1') {
                // Weird - let's fix it
                $post->attachment = '1';
                $DB->update_record('forumlv_posts', $post);
            } else if ($attachmentsmoved == 0 && $post->attachment != '') {
                // Weird - let's fix it
                $post->attachment = '';
                $DB->update_record('forumlv_posts', $post);
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

    if (empty($post->attachment)) {
        return $type !== 'separateimages' ? '' : array('', '');
    }

    if (!in_array($type, array('separateimages', 'html', 'text'))) {
        return $type !== 'separateimages' ? '' : array('', '');
    }

    if (!$context = context_module::instance($cm->id)) {
        return $type !== 'separateimages' ? '' : array('', '');
    }
    $strattachment = get_string('attachment', 'forumlv');

    $fs = get_file_storage();

    $imagereturn = '';
    $output = '';

    $canexport = !empty($CFG->enableportfolios) && (has_capability('mod/forumlv:exportpost', $context) || ($post->userid == $USER->id && has_capability('mod/forumlv:exportownpost', $context)));

    if ($canexport) {
        require_once($CFG->libdir.'/portfoliolib.php');
    }

    // We retrieve all files according to the time that they were created.  In the case that several files were uploaded
    // at the sametime (e.g. in the case of drag/drop upload) we revert to using the filename.
    $files = $fs->get_area_files($context->id, 'mod_forumlv', 'attachment', $post->id, "filename", false);
    if ($files) {
        if ($canexport) {
            $button = new portfolio_add_button();
        }
        foreach ($files as $file) {
            $filename = $file->get_filename();
            $mimetype = $file->get_mimetype();
            $iconimage = $OUTPUT->pix_icon(file_file_icon($file), get_mimetype_description($file), 'moodle', array('class' => 'icon'));
            $path = file_encode_url($CFG->wwwroot.'/pluginfile.php', '/'.$context->id.'/mod_forumlv/attachment/'.$post->id.'/'.$filename);

            if ($type == 'html') {
                $output .= "<a href=\"$path\">$iconimage</a> ";
                $output .= "<a href=\"$path\">".s($filename)."</a>";
                if ($canexport) {
                    $button->set_callback_options('forumlv_portfolio_caller', array('postid' => $post->id, 'attachment' => $file->get_id()), 'mod_forumlv');
                    $button->set_format_by_file($file);
                    $output .= $button->to_html(PORTFOLIO_ADD_ICON_LINK);
                }
                $output .= "<br />";

            } else if ($type == 'text') {
                $output .= "$strattachment ".s($filename).":\n$path\n";

            } else { //'returnimages'
                if (in_array($mimetype, array('image/gif', 'image/jpeg', 'image/png'))) {
                    // Image attachments don't get printed as links
                    $imagereturn .= "<br /><img src=\"$path\" alt=\"\" />";
                    if ($canexport) {
                        $button->set_callback_options('forumlv_portfolio_caller', array('postid' => $post->id, 'attachment' => $file->get_id()), 'mod_forumlv');
                        $button->set_format_by_file($file);
                        $imagereturn .= $button->to_html(PORTFOLIO_ADD_ICON_LINK);
                    }
                } else {
                    $output .= "<a href=\"$path\">$iconimage</a> ";
                    $output .= format_text("<a href=\"$path\">".s($filename)."</a>", FORMAT_HTML, array('context'=>$context));
                    if ($canexport) {
                        $button->set_callback_options('forumlv_portfolio_caller', array('postid' => $post->id, 'attachment' => $file->get_id()), 'mod_forumlv');
                        $button->set_format_by_file($file);
                        $output .= $button->to_html(PORTFOLIO_ADD_ICON_LINK);
                    }
                    $output .= '<br />';
                }
            }

            if (!empty($CFG->enableplagiarism)) {
                require_once($CFG->libdir.'/plagiarismlib.php');
                $output .= plagiarism_get_links(array('userid' => $post->userid,
                    'file' => $file,
                    'cmid' => $cm->id,
                    'course' => $cm->course,
                    'forumlv' => $cm->instance));
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
    return array(
        'attachment' => get_string('areaattachment', 'mod_forumlv'),
        'post' => get_string('areapost', 'mod_forumlv'),
    );
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

    if ($context->contextlevel != CONTEXT_MODULE) {
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
        require_once($CFG->dirroot.'/mod/forumlv/locallib.php');
        return new forumlv_file_info_container($browser, $course, $cm, $context, $areas, $filearea);
    }

    static $cached = array();
    // $cached will store last retrieved post, discussion and forumlv. To make sure that the cache
    // is cleared between unit tests we check if this is the same session
    if (!isset($cached['sesskey']) || $cached['sesskey'] != sesskey()) {
        $cached = array('sesskey' => sesskey());
    }

    if (isset($cached['post']) && $cached['post']->id == $itemid) {
        $post = $cached['post'];
    } else if ($post = $DB->get_record('forumlv_posts', array('id' => $itemid))) {
        $cached['post'] = $post;
    } else {
        return null;
    }

    if (isset($cached['discussion']) && $cached['discussion']->id == $post->discussion) {
        $discussion = $cached['discussion'];
    } else if ($discussion = $DB->get_record('forumlv_discussions', array('id' => $post->discussion))) {
        $cached['discussion'] = $discussion;
    } else {
        return null;
    }

    if (isset($cached['forumlv']) && $cached['forumlv']->id == $cm->instance) {
        $forumlv = $cached['forumlv'];
    } else if ($forumlv = $DB->get_record('forumlv', array('id' => $cm->instance))) {
        $cached['forumlv'] = $forumlv;
    } else {
        return null;
    }

    $fs = get_file_storage();
    $filepath = is_null($filepath) ? '/' : $filepath;
    $filename = is_null($filename) ? '.' : $filename;
    if (!($storedfile = $fs->get_file($context->id, 'mod_forumlv', $filearea, $itemid, $filepath, $filename))) {
        return null;
    }

    // Checks to see if the user can manage files or is the owner.
    // TODO MDL-33805 - Do not use userid here and move the capability check above.
    if (!has_capability('moodle/course:managefiles', $context) && $storedfile->get_userid() != $USER->id) {
        return null;
    }
    // Make sure groups allow this user to see this file
    if ($discussion->groupid > 0 && !has_capability('moodle/site:accessallgroups', $context)) {
        $groupmode = groups_get_activity_groupmode($cm, $course);
        if ($groupmode == SEPARATEGROUPS && !groups_is_member($discussion->groupid)) {
            return null;
        }
    }

    // Make sure we're allowed to see it...
    if (!forumlv_user_can_see_post($forumlv, $discussion, $post, NULL, $cm)) {
        return null;
    }

    $urlbase = $CFG->wwwroot.'/pluginfile.php';
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
function forumlv_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options=array()) {
    global $CFG, $DB;

    if ($context->contextlevel != CONTEXT_MODULE) {
        return false;
    }

    require_course_login($course, true, $cm);

    $areas = forumlv_get_file_areas($course, $cm, $context);

    // filearea must contain a real area
    if (!isset($areas[$filearea])) {
        return false;
    }

    $postid = (int)array_shift($args);

    if (!$post = $DB->get_record('forumlv_posts', array('id'=>$postid))) {
        return false;
    }

    if (!$discussion = $DB->get_record('forumlv_discussions', array('id'=>$post->discussion))) {
        return false;
    }

    if (!$forumlv = $DB->get_record('forumlv', array('id'=>$cm->instance))) {
        return false;
    }

    $fs = get_file_storage();
    $relativepath = implode('/', $args);
    $fullpath = "/$context->id/mod_forumlv/$filearea/$postid/$relativepath";
    if (!$file = $fs->get_file_by_hash(sha1($fullpath)) or $file->is_directory()) {
        return false;
    }

    // Make sure groups allow this user to see this file
    if ($discussion->groupid > 0) {
        $groupmode = groups_get_activity_groupmode($cm, $course);
        if ($groupmode == SEPARATEGROUPS) {
            if (!groups_is_member($discussion->groupid) and !has_capability('moodle/site:accessallgroups', $context)) {
                return false;
            }
        }
    }

    // Make sure we're allowed to see it...
    if (!forumlv_user_can_see_post($forumlv, $discussion, $post, NULL, $cm)) {
        return false;
    }

    // finally send the file
    send_stored_file($file, 0, 0, true, $options); // download MUST be forced - security!
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
function forumlv_add_attachment($post, $forumlv, $cm, $mform=null, $unused=null) {
    global $DB;

    if (empty($mform)) {
        return false;
    }

    if (empty($post->attachments)) {
        return true;   // Nothing to do
    }

    $context = context_module::instance($cm->id);

    $info = file_get_draft_area_info($post->attachments);
    $present = ($info['filecount']>0) ? '1' : '';
    file_save_draft_area_files($post->attachments, $context->id, 'mod_forumlv', 'attachment', $post->id,
            mod_forumlv_post_form::attachment_options($forumlv));

    $DB->set_field('forumlv_posts', 'attachment', $present, array('id'=>$post->id));

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
 * @param string $unused formerly $message, renamed in 2.8 as it was unused.
 * @return int
 */
function forumlv_add_new_post($post, $mform, $unused = null) {
    global $USER, $CFG, $DB;

    $discussion = $DB->get_record('forumlv_discussions', array('id' => $post->discussion));
    $forumlv      = $DB->get_record('forumlv', array('id' => $discussion->forumlv));
    $cm         = get_coursemodule_from_instance('forumlv', $forumlv->id);
    $context    = context_module::instance($cm->id);

    $post->created    = $post->modified = time();
    $post->mailed     = FORUMLV_MAILED_PENDING;
    $post->userid     = $USER->id;
    $post->attachment = "";
    if (!isset($post->totalscore)) {
        $post->totalscore = 0;
    }
    if (!isset($post->mailnow)) {
        $post->mailnow    = 0;
    }

    $post->id = $DB->insert_record("forumlv_posts", $post);
    $post->message = file_save_draft_area_files($post->itemid, $context->id, 'mod_forumlv', 'post', $post->id,
            mod_forumlv_post_form::editor_options($context, null), $post->message);
    $DB->set_field('forumlv_posts', 'message', $post->message, array('id'=>$post->id));
    forumlv_add_attachment($post, $forumlv, $cm, $mform);

    // Update discussion modified date
    $DB->set_field("forumlv_discussions", "timemodified", $post->modified, array("id" => $post->discussion));
    $DB->set_field("forumlv_discussions", "usermodified", $post->userid, array("id" => $post->discussion));

    if (forumlv_tp_can_track_forumlvs($forumlv) && forumlv_tp_is_tracked($forumlv)) {
        forumlv_tp_mark_post_read($post->userid, $post, $post->forumlv);
    }

    // Let Moodle know that assessable content is uploaded (eg for plagiarism detection)
    forumlv_trigger_content_uploaded_event($post, $cm, 'forumlv_add_new_post');

    return $post->id;
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

    $discussion = $DB->get_record('forumlv_discussions', array('id' => $post->discussion));
    $forumlv      = $DB->get_record('forumlv', array('id' => $discussion->forumlv));
    $cm         = get_coursemodule_from_instance('forumlv', $forumlv->id);
    $context    = context_module::instance($cm->id);

    $post->modified = time();

    $DB->update_record('forumlv_posts', $post);

    $discussion->timemodified = $post->modified; // last modified tracking
    $discussion->usermodified = $post->userid;   // last modified tracking

    if (!$post->parent) {   // Post is a discussion starter - update discussion title and times too
        $discussion->name      = $post->subject;
        $discussion->timestart = $post->timestart;
        $discussion->timeend   = $post->timeend;

        if (isset($post->pinned)) {
            $discussion->pinned = $post->pinned;
        }
    }
    $post->message = file_save_draft_area_files($post->itemid, $context->id, 'mod_forumlv', 'post', $post->id,
            mod_forumlv_post_form::editor_options($context, $post->id), $post->message);
    $DB->set_field('forumlv_posts', 'message', $post->message, array('id'=>$post->id));

    $DB->update_record('forumlv_discussions', $discussion);

    forumlv_add_attachment($post, $forumlv, $cm, $mform, $message);

    if (forumlv_tp_can_track_forumlvs($forumlv) && forumlv_tp_is_tracked($forumlv)) {
        forumlv_tp_mark_post_read($post->userid, $post, $post->forumlv);
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
function forumlv_add_discussion($discussion, $mform=null, $unused=null, $userid=null) {
    global $USER, $CFG, $DB;

    $timenow = isset($discussion->timenow) ? $discussion->timenow : time();

    if (is_null($userid)) {
        $userid = $USER->id;
    }

    // The first post is stored as a real post, and linked
    // to from the discuss entry.

    $forumlv = $DB->get_record('forumlv', array('id'=>$discussion->forumlv));
    $cm    = get_coursemodule_from_instance('forumlv', $forumlv->id);

    $post = new stdClass();
    $post->discussion    = 0;
    $post->parent        = 0;
    $post->userid        = $userid;
    $post->created       = $timenow;
    $post->modified      = $timenow;
    $post->mailed        = FORUMLV_MAILED_PENDING;
    $post->subject       = $discussion->name;
    $post->message       = $discussion->message;
    $post->messageformat = $discussion->messageformat;
    $post->messagetrust  = $discussion->messagetrust;
    $post->attachments   = isset($discussion->attachments) ? $discussion->attachments : null;
    $post->forumlv         = $forumlv->id;     // speedup
    $post->course        = $forumlv->course; // speedup
    $post->mailnow       = $discussion->mailnow;

    $post->id = $DB->insert_record("forumlv_posts", $post);

    // TODO: Fix the calling code so that there always is a $cm when this function is called
    if (!empty($cm->id) && !empty($discussion->itemid)) {   // In "single simple discussions" this may not exist yet
        $context = context_module::instance($cm->id);
        $text = file_save_draft_area_files($discussion->itemid, $context->id, 'mod_forumlv', 'post', $post->id,
                mod_forumlv_post_form::editor_options($context, null), $post->message);
        $DB->set_field('forumlv_posts', 'message', $text, array('id'=>$post->id));
    }

    // Now do the main entry for the discussion, linking to this first post

    $discussion->firstpost    = $post->id;
    $discussion->timemodified = $timenow;
    $discussion->usermodified = $post->userid;
    $discussion->userid       = $userid;
    $discussion->assessed     = 0;

    $post->discussion = $DB->insert_record("forumlv_discussions", $discussion);

    // Finally, set the pointer on the post.
    $DB->set_field("forumlv_posts", "discussion", $post->discussion, array("id"=>$post->id));

    if (!empty($cm->id)) {
        forumlv_add_attachment($post, $forumlv, $cm, $mform, $unused);
    }

    if (forumlv_tp_can_track_forumlvs($forumlv) && forumlv_tp_is_tracked($forumlv)) {
        forumlv_tp_mark_post_read($post->userid, $post, $post->forumlv);
    }

    // Let Moodle know that assessable content is uploaded (eg for plagiarism detection)
    if (!empty($cm->id)) {
        forumlv_trigger_content_uploaded_event($post, $cm, 'forumlv_add_discussion');
    }

    return $post->discussion;
}


/**
 * Deletes a discussion and handles all associated cleanup.
 *
 * @global object
 * @param object $discussion Discussion to delete
 * @param bool $fulldelete True when deleting entire forumlv
 * @param object $course Course
 * @param object $cm Course-module
 * @param object $forumlv Forumlv
 * @return bool
 */
function forumlv_delete_discussion($discussion, $fulldelete, $course, $cm, $forumlv) {
    global $DB, $CFG;
    require_once($CFG->libdir.'/completionlib.php');

    $result = true;

    if ($posts = $DB->get_records("forumlv_posts", array("discussion" => $discussion->id))) {
        foreach ($posts as $post) {
            $post->course = $discussion->course;
            $post->forumlv  = $discussion->forumlv;
            if (!forumlv_delete_post($post, 'ignore', $course, $cm, $forumlv, $fulldelete)) {
                $result = false;
            }
        }
    }

    forumlv_tp_delete_read_records(-1, -1, $discussion->id);

    // Discussion subscriptions must be removed before discussions because of key constraints.
    $DB->delete_records('forumlv_discussion_subs', array('discussion' => $discussion->id));
    if (!$DB->delete_records("forumlv_discussions", array("id" => $discussion->id))) {
        $result = false;
    }

    // Update completion state if we are tracking completion based on number of posts
    // But don't bother when deleting whole thing
    if (!$fulldelete) {
        $completion = new completion_info($course);
        if ($completion->is_enabled($cm) == COMPLETION_TRACKING_AUTOMATIC &&
           ($forumlv->completiondiscussions || $forumlv->completionreplies || $forumlv->completionposts)) {
            $completion->update_state($cm, COMPLETION_INCOMPLETE, $discussion->userid);
        }
    }

    return $result;
}


/**
 * Deletes a single forumlv post.
 *
 * @global object
 * @param object $post Forumlv post object
 * @param mixed $children Whether to delete children. If false, returns false
 *   if there are any children (without deleting the post). If true,
 *   recursively deletes all children. If set to special value 'ignore', deletes
 *   post regardless of children (this is for use only when deleting all posts
 *   in a disussion).
 * @param object $course Course
 * @param object $cm Course-module
 * @param object $forumlv Forumlv
 * @param bool $skipcompletion True to skip updating completion state if it
 *   would otherwise be updated, i.e. when deleting entire forumlv anyway.
 * @return bool
 */
function forumlv_delete_post($post, $children, $course, $cm, $forumlv, $skipcompletion=false) {
    global $DB, $CFG, $USER;
    require_once($CFG->libdir.'/completionlib.php');

    $context = context_module::instance($cm->id);

    if ($children !== 'ignore' && ($childposts = $DB->get_records('forumlv_posts', array('parent'=>$post->id)))) {
       if ($children) {
           foreach ($childposts as $childpost) {
               forumlv_delete_post($childpost, true, $course, $cm, $forumlv, $skipcompletion);
           }
       } else {
           return false;
       }
    }

    // Delete ratings.
    require_once($CFG->dirroot.'/rating/lib.php');
    $delopt = new stdClass;
    $delopt->contextid = $context->id;
    $delopt->component = 'mod_forumlv';
    $delopt->ratingarea = 'post';
    $delopt->itemid = $post->id;
    $rm = new rating_manager();
    $rm->delete_ratings($delopt);

    // Delete attachments.
    $fs = get_file_storage();
    $fs->delete_area_files($context->id, 'mod_forumlv', 'attachment', $post->id);
    $fs->delete_area_files($context->id, 'mod_forumlv', 'post', $post->id);

    // Delete cached RSS feeds.
    if (!empty($CFG->enablerssfeeds)) {
        require_once($CFG->dirroot.'/mod/forumlv/rsslib.php');
        forumlv_rss_delete_file($forumlv);
    }

    if ($DB->delete_records("forumlv_posts", array("id" => $post->id))) {

        forumlv_tp_delete_read_records(-1, $post->id);

    // Just in case we are deleting the last post
        forumlv_discussion_update_last_post($post->discussion);

        // Update completion state if we are tracking completion based on number of posts
        // But don't bother when deleting whole thing

        if (!$skipcompletion) {
            $completion = new completion_info($course);
            if ($completion->is_enabled($cm) == COMPLETION_TRACKING_AUTOMATIC &&
               ($forumlv->completiondiscussions || $forumlv->completionreplies || $forumlv->completionposts)) {
                $completion->update_state($cm, COMPLETION_INCOMPLETE, $post->userid);
            }
        }

        $params = array(
            'context' => $context,
            'objectid' => $post->id,
            'other' => array(
                'discussionid' => $post->discussion,
                'forumlvid' => $forumlv->id,
                'forumlvtype' => $forumlv->type,
            )
        );
        if ($post->userid !== $USER->id) {
            $params['relateduserid'] = $post->userid;
        }
        $event = \mod_forumlv\event\post_deleted::create($params);
        $event->add_record_snapshot('forumlv_posts', $post);
        $event->trigger();

        return true;
    }
    return false;
}

/**
 * Sends post content to plagiarism plugin
 * @param object $post Forumlv post object
 * @param object $cm Course-module
 * @param string $name
 * @return bool
*/
function forumlv_trigger_content_uploaded_event($post, $cm, $name) {
    $context = context_module::instance($cm->id);
    $fs = get_file_storage();
    $files = $fs->get_area_files($context->id, 'mod_forumlv', 'attachment', $post->id, "timemodified", false);
    $params = array(
        'context' => $context,
        'objectid' => $post->id,
        'other' => array(
            'content' => $post->message,
            'pathnamehashes' => array_keys($files),
            'discussionid' => $post->discussion,
            'triggeredfrom' => $name,
        )
    );
    $event = \mod_forumlv\event\assessable_uploaded::create($params);
    $event->trigger();
    return true;
}

/**
 * @global object
 * @param object $post
 * @param bool $children
 * @return int
 */
function forumlv_count_replies($post, $children=true) {
    global $DB;
    $count = 0;

    if ($children) {
        if ($childposts = $DB->get_records('forumlv_posts', array('parent' => $post->id))) {
           foreach ($childposts as $childpost) {
               $count ++;                   // For this child
               $count += forumlv_count_replies($childpost, true);
           }
        }
    } else {
        $count += $DB->count_records('forumlv_posts', array('parent' => $post->id));
    }

    return $count;
}

/**
 * Given a new post, subscribes or unsubscribes as appropriate.
 * Returns some text which describes what happened.
 *
 * @param object $fromform The submitted form
 * @param stdClass $forumlv The forumlv record
 * @param stdClass $discussion The forumlv discussion record
 * @return string
 */
function forumlv_post_subscription($fromform, $forumlv, $discussion) {
    global $USER;

    if (\mod_forumlv\subscriptions::is_forcesubscribed($forumlv)) {
        return "";
    } else if (\mod_forumlv\subscriptions::subscription_disabled($forumlv)) {
        $subscribed = \mod_forumlv\subscriptions::is_subscribed($USER->id, $forumlv);
        if ($subscribed && !has_capability('moodle/course:manageactivities', context_course::instance($forumlv->course), $USER->id)) {
            // This user should not be subscribed to the forumlv.
            \mod_forumlv\subscriptions::unsubscribe_user($USER->id, $forumlv);
        }
        return "";
    }

    $info = new stdClass();
    $info->name  = fullname($USER);
    $info->discussion = format_string($discussion->name);
    $info->forumlv = format_string($forumlv->name);

    if (isset($fromform->discussionsubscribe) && $fromform->discussionsubscribe) {
        if ($result = \mod_forumlv\subscriptions::subscribe_user_to_discussion($USER->id, $discussion)) {
            return html_writer::tag('p', get_string('discussionnowsubscribed', 'forumlv', $info));
        }
    } else {
        if ($result = \mod_forumlv\subscriptions::unsubscribe_user_from_discussion($USER->id, $discussion)) {
            return html_writer::tag('p', get_string('discussionnownotsubscribed', 'forumlv', $info));
        }
    }

    return '';
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
function forumlv_get_subscribe_link($forumlv, $context, $messages = array(), $cantaccessagroup = false, $fakelink=true, $backtoindex=false, $subscribed_forumlvs=null) {
    global $CFG, $USER, $PAGE, $OUTPUT;
    $defaultmessages = array(
        'subscribed' => get_string('unsubscribe', 'forumlv'),
        'unsubscribed' => get_string('subscribe', 'forumlv'),
        'cantaccessgroup' => get_string('no'),
        'forcesubscribed' => get_string('everyoneissubscribed', 'forumlv'),
        'cantsubscribe' => get_string('disallowsubscribe','forumlv')
    );
    $messages = $messages + $defaultmessages;

    if (\mod_forumlv\subscriptions::is_forcesubscribed($forumlv)) {
        return $messages['forcesubscribed'];
    } else if (\mod_forumlv\subscriptions::subscription_disabled($forumlv) &&
            !has_capability('mod/forumlv:managesubscriptions', $context)) {
        return $messages['cantsubscribe'];
    } else if ($cantaccessagroup) {
        return $messages['cantaccessgroup'];
    } else {
        if (!is_enrolled($context, $USER, '', true)) {
            return '';
        }

        $subscribed = \mod_forumlv\subscriptions::is_subscribed($USER->id, $forumlv);
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
            $PAGE->requires->js('/mod/forumlv/forumlv.js');
            $PAGE->requires->js_function_call('forumlv_produce_subscribe_link', array($forumlv->id, $backtoindexlink, $linktext, $linktitle));
            $link = "<noscript>";
        }
        $options['id'] = $forumlv->id;
        $options['sesskey'] = sesskey();
        $url = new moodle_url('/mod/forumlv/subscribe.php', $options);
        $link .= $OUTPUT->single_button($url, $linktext, 'get', array('title'=>$linktitle));
        if ($fakelink) {
            $link .= '</noscript>';
        }

        return $link;
    }
}

/**
 * Returns true if user created new discussion already.
 *
 * @param int $forumlvid  The forumlv to check for postings
 * @param int $userid   The user to check for postings
 * @param int $groupid  The group to restrict the check to
 * @return bool
 */
function forumlv_user_has_posted_discussion($forumlvid, $userid, $groupid = null) {
    global $CFG, $DB;

    $sql = "SELECT 'x'
              FROM {forumlv_discussions} d, {forumlv_posts} p
             WHERE d.forumlv = ? AND p.discussion = d.id AND p.parent = 0 AND p.userid = ?";

    $params = [$forumlvid, $userid];

    if ($groupid) {
        $sql .= " AND d.groupid = ?";
        $params[] = $groupid;
    }

    return $DB->record_exists_sql($sql, $params);
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

    return $DB->get_records_sql($haspostedsql, array($forumlvid, $userid));
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
        return $DB->record_exists_sql($sql, array('forumlvid'=>$forumlvid,'userid'=>$userid));
    } else {
        return $DB->record_exists('forumlv_posts', array('discussion'=>$did,'userid'=>$userid));
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

    $posttime = $DB->get_field('forumlv_posts', 'MIN(created)', array('userid'=>$userid, 'discussion'=>$did));
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
function forumlv_user_can_post_discussion($forumlv, $currentgroup=null, $unused=-1, $cm=NULL, $context=NULL) {
// $forumlv is an object
    global $USER;

    // shortcut - guest and not-logged-in users can not post
    if (isguestuser() or !isloggedin()) {
        return false;
    }

    if (!$cm) {
        debugging('missing cm', DEBUG_DEVELOPER);
        if (!$cm = get_coursemodule_from_instance('forumlv', $forumlv->id, $forumlv->course)) {
            print_error('invalidcoursemodule');
        }
    }

    if (!$context) {
        $context = context_module::instance($cm->id);
    }

    if ($currentgroup === null) {
        $currentgroup = groups_get_activity_group($cm);
    }

    $groupmode = groups_get_activity_groupmode($cm);

    if ($forumlv->type == 'news') {
        $capname = 'mod/forumlv:addnews';
    } else if ($forumlv->type == 'qanda') {
        $capname = 'mod/forumlv:addquestion';
    } else {
        $capname = 'mod/forumlv:startdiscussion';
    }

    if (!has_capability($capname, $context)) {
        return false;
    }

    if ($forumlv->type == 'single') {
        return false;
    }

    if ($forumlv->type == 'eachuser') {
        if (forumlv_user_has_posted_discussion($forumlv->id, $USER->id, $currentgroup)) {
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
function forumlv_user_can_post($forumlv, $discussion, $user=NULL, $cm=NULL, $course=NULL, $context=NULL) {
    global $USER, $DB;
    if (empty($user)) {
        $user = $USER;
    }

    // shortcut - guest and not-logged-in users can not post
    if (isguestuser($user) or empty($user->id)) {
        return false;
    }

    if (!isset($discussion->groupid)) {
        debugging('incorrect discussion parameter', DEBUG_DEVELOPER);
        return false;
    }

    if (!$cm) {
        debugging('missing cm', DEBUG_DEVELOPER);
        if (!$cm = get_coursemodule_from_instance('forumlv', $forumlv->id, $forumlv->course)) {
            print_error('invalidcoursemodule');
        }
    }

    if (!$course) {
        debugging('missing course', DEBUG_DEVELOPER);
        if (!$course = $DB->get_record('course', array('id' => $forumlv->course))) {
            print_error('invalidcourseid');
        }
    }

    if (!$context) {
        $context = context_module::instance($cm->id);
    }

    // normal users with temporary guest access can not post, suspended users can not post either
    if (!is_viewing($context, $user->id) and !is_enrolled($context, $user->id, '', true)) {
        return false;
    }

    if ($forumlv->type == 'news') {
        $capname = 'mod/forumlv:replynews';
    } else {
        $capname = 'mod/forumlv:replypost';
    }

    if (!has_capability($capname, $context, $user->id)) {
        return false;
    }

    if (!$groupmode = groups_get_activity_groupmode($cm, $course)) {
        return true;
    }

    if (has_capability('moodle/site:accessallgroups', $context)) {
        return true;
    }

    if ($groupmode == VISIBLEGROUPS) {
        if ($discussion->groupid == -1) {
            // allow students to reply to all participants discussions - this was not possible in Moodle <1.8
            return true;
        }
        return groups_is_member($discussion->groupid);

    } else {
        //separate groups
        if ($discussion->groupid == -1) {
            return false;
        }
        return groups_is_member($discussion->groupid);
    }
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
    if (!empty($CFG->forumlv_enabletimedposts)) {
        $time = time();
        if (($discussion->timestart != 0 && $discussion->timestart > $time)
            || ($discussion->timeend != 0 && $discussion->timeend < $time)) {
            if (!has_capability('mod/forumlv:viewhiddentimedposts', $context, $user->id)) {
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
    if ($discussion->groupid > 0) {
        $groupmode = groups_get_activity_groupmode($cm);
        if ($groupmode == SEPARATEGROUPS) {
            return groups_is_member($discussion->groupid) || has_capability('moodle/site:accessallgroups', $context);
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
function forumlv_user_can_see_discussion($forumlv, $discussion, $context, $user=NULL) {
    global $USER, $DB;

    if (empty($user) || empty($user->id)) {
        $user = $USER;
    }

    // retrieve objects (yuk)
    if (is_numeric($forumlv)) {
        debugging('missing full forumlv', DEBUG_DEVELOPER);
        if (!$forumlv = $DB->get_record('forumlv',array('id'=>$forumlv))) {
            return false;
        }
    }
    if (is_numeric($discussion)) {
        debugging('missing full discussion', DEBUG_DEVELOPER);
        if (!$discussion = $DB->get_record('forumlv_discussions',array('id'=>$discussion))) {
            return false;
        }
    }
    if (!$cm = get_coursemodule_from_instance('forumlv', $forumlv->id, $forumlv->course)) {
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
function forumlv_user_can_see_post($forumlv, $discussion, $post, $user=NULL, $cm=NULL) {
    global $CFG, $USER, $DB;

    // Context used throughout function.
    $modcontext = context_module::instance($cm->id);

    // retrieve objects (yuk)
    if (is_numeric($forumlv)) {
        debugging('missing full forumlv', DEBUG_DEVELOPER);
        if (!$forumlv = $DB->get_record('forumlv',array('id'=>$forumlv))) {
            return false;
        }
    }

    if (is_numeric($discussion)) {
        debugging('missing full discussion', DEBUG_DEVELOPER);
        if (!$discussion = $DB->get_record('forumlv_discussions',array('id'=>$discussion))) {
            return false;
        }
    }
    if (is_numeric($post)) {
        debugging('missing full post', DEBUG_DEVELOPER);
        if (!$post = $DB->get_record('forumlv_posts',array('id'=>$post))) {
            return false;
        }
    }

    if (!isset($post->id) && isset($post->parent)) {
        $post->id = $post->parent;
    }

    if (!$cm) {
        debugging('missing cm', DEBUG_DEVELOPER);
        if (!$cm = get_coursemodule_from_instance('forumlv', $forumlv->id, $forumlv->course)) {
            print_error('invalidcoursemodule');
        }
    }

    if (empty($user) || empty($user->id)) {
        $user = $USER;
    }

    $canviewdiscussion = !empty($cm->cache->caps['mod/forumlv:viewdiscussion']) || has_capability('mod/forumlv:viewdiscussion', $modcontext, $user->id);
    if (!$canviewdiscussion && !has_all_capabilities(array('moodle/user:viewdetails', 'moodle/user:readuserposts'), context_user::instance($post->userid))) {
        return false;
    }

    if (isset($cm->uservisible)) {
        if (!$cm->uservisible) {
            return false;
        }
    } else {
        if (!\core_availability\info_module::is_user_visible($cm, $user->id, false)) {
            return false;
        }
    }

    if (!forumlv_user_can_see_timed_discussion($discussion, $user, $modcontext)) {
        return false;
    }

    if (!forumlv_user_can_see_group_discussion($discussion, $cm, $modcontext)) {
        return false;
    }

    if ($forumlv->type == 'qanda') {
        $firstpost = forumlv_get_firstpost_from_discussion($discussion->id);
        $userfirstpost = forumlv_get_user_posted_time($discussion->id, $user->id);

        return (($userfirstpost !== false && (time() - $userfirstpost >= $CFG->maxeditingtime)) ||
                $firstpost->id == $post->id || $post->userid == $user->id || $firstpost->userid == $user->id ||
                has_capability('mod/forumlv:viewqandawithoutposting', $modcontext, $user->id));
    }
    return true;
}


/**
 * Prints the discussion view screen for a forumlv.
 *
 * @global object
 * @global object
 * @param object $course The current course object.
 * @param object $forumlv Forumlv to be printed.
 * @param int $maxdiscussions .
 * @param string $displayformat The display format to use (optional).
 * @param string $sort Sort arguments for database query (optional).
 * @param int $groupmode Group mode of the forumlv (optional).
 * @param void $unused (originally current group)
 * @param int $page Page mode, page to display (optional).
 * @param int $perpage The maximum number of discussions per page(optional)
 * @param boolean $subscriptionstatus Whether the user is currently subscribed to the discussion in some fashion.
 *
 */
function forumlv_print_latest_discussions($course, $forumlv, $maxdiscussions = -1, $displayformat = 'plain', $sort = '',
                                        $currentgroup = -1, $groupmode = -1, $page = -1, $perpage = 100, $cm = null) {
    global $CFG, $USER, $OUTPUT;

    if (!$cm) {
        if (!$cm = get_coursemodule_from_instance('forumlv', $forumlv->id, $forumlv->course)) {
            print_error('invalidcoursemodule');
        }
    }
    $context = context_module::instance($cm->id);

    if (empty($sort)) {
        $sort = forumlv_get_default_sort_order();
    }

    $olddiscussionlink = false;

 // Sort out some defaults
    if ($perpage <= 0) {
        $perpage = 0;
        $page    = -1;
    }

    if ($maxdiscussions == 0) {
        // all discussions - backwards compatibility
        $page    = -1;
        $perpage = 0;
        if ($displayformat == 'plain') {
            $displayformat = 'header';  // Abbreviate display by default
        }

    } else if ($maxdiscussions > 0) {
        $page    = -1;
        $perpage = $maxdiscussions;
    }

    $fullpost = false;
    if ($displayformat == 'plain') {
        $fullpost = true;
    }


// Decide if current user is allowed to see ALL the current discussions or not

// First check the group stuff
    if ($currentgroup == -1 or $groupmode == -1) {
        $groupmode    = groups_get_activity_groupmode($cm, $course);
        $currentgroup = groups_get_activity_group($cm);
    }

    $groups = array(); //cache

// If the user can post discussions, then this is a good place to put the
// button for it. We do not show the button if we are showing site news
// and the current user is a guest.

    $canstart = forumlv_user_can_post_discussion($forumlv, $currentgroup, $groupmode, $cm, $context);
    if (!$canstart and $forumlv->type !== 'news') {
        if (isguestuser() or !isloggedin()) {
            $canstart = true;
        }
        if (!is_enrolled($context) and !is_viewing($context)) {
            // allow guests and not-logged-in to see the button - they are prompted to log in after clicking the link
            // normal users with temporary guest access see this button too, they are asked to enrol instead
            // do not show the button to users with suspended enrolments here
            $canstart = enrol_selfenrol_available($course->id);
        }
    }

    if ($canstart) {
        echo '<div class="singlebutton forumlvaddnew">';
        echo "<form id=\"newdiscussionform\" method=\"get\" action=\"$CFG->wwwroot/mod/forumlv/post.php\">";
        echo '<div>';
        echo "<input type=\"hidden\" name=\"forumlv\" value=\"$forumlv->id\" />";
        switch ($forumlv->type) {
            case 'news':
            case 'blog':
                $buttonadd = get_string('addanewtopic', 'forumlv');
                break;
            case 'qanda':
                $buttonadd = get_string('addanewquestion', 'forumlv');
                break;
            default:
                $buttonadd = get_string('addanewdiscussion', 'forumlv');
                break;
        }
        echo '<input type="submit" value="'.$buttonadd.'" />';
        echo '</div>';
        echo '</form>';
        echo "</div>\n";

    } else if (isguestuser() or !isloggedin() or $forumlv->type == 'news' or
        $forumlv->type == 'qanda' and !has_capability('mod/forumlv:addquestion', $context) or
        $forumlv->type != 'qanda' and !has_capability('mod/forumlv:startdiscussion', $context)) {
        // no button and no info

    } else if ($groupmode and !has_capability('moodle/site:accessallgroups', $context)) {
        // inform users why they can not post new discussion
        if (!$currentgroup) {
            echo $OUTPUT->notification(get_string('cannotadddiscussionall', 'forumlv'));
        } else if (!groups_is_member($currentgroup)) {
            echo $OUTPUT->notification(get_string('cannotadddiscussion', 'forumlv'));
        }
    }

// Get all the recent discussions we're allowed to see

    $getuserlastmodified = ($displayformat == 'header');

    if (! $discussions = forumlv_get_discussions($cm, $sort, $fullpost, null, $maxdiscussions, $getuserlastmodified, $page, $perpage) ) {
        echo '<div class="forumlvnodiscuss">';
        if ($forumlv->type == 'news') {
            echo '('.get_string('nonews', 'forumlv').')';
        } else if ($forumlv->type == 'qanda') {
            echo '('.get_string('noquestions','forumlv').')';
        } else {
            echo '('.get_string('nodiscussions', 'forumlv').')';
        }
        echo "</div>\n";
        return;
    }

// If we want paging
    if ($page != -1) {
        ///Get the number of discussions found
        $numdiscussions = forumlv_get_discussions_count($cm);

        ///Show the paging bar
        echo $OUTPUT->paging_bar($numdiscussions, $page, $perpage, "view.php?f=$forumlv->id");
        if ($numdiscussions > 1000) {
            // saves some memory on sites with very large forumlvs
            $replies = forumlv_count_discussion_replies($forumlv->id, $sort, $maxdiscussions, $page, $perpage);
        } else {
            $replies = forumlv_count_discussion_replies($forumlv->id);
        }

    } else {
        $replies = forumlv_count_discussion_replies($forumlv->id);

        if ($maxdiscussions > 0 and $maxdiscussions <= count($discussions)) {
            $olddiscussionlink = true;
        }
    }

    $canviewparticipants = has_capability('moodle/course:viewparticipants',$context);
    $canviewhiddentimedposts = has_capability('mod/forumlv:viewhiddentimedposts', $context);

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
        echo '<th class="header topic" scope="col">'.get_string('discussion', 'forumlv').'</th>';
        echo '<th class="header author" colspan="2" scope="col">'.get_string('startedby', 'forumlv').'</th>';
        if ($groupmode > 0) {
            echo '<th class="header group" scope="col">'.get_string('group').'</th>';
        }
        if (has_capability('mod/forumlv:viewdiscussion', $context)) {
            echo '<th class="header replies" scope="col">'.get_string('replies', 'forumlv').'</th>';
            // If the forumlv can be tracked, display the unread column.
            if ($cantrack) {
                echo '<th class="header replies" scope="col">'.get_string('unread', 'forumlv');
                if ($forumlvtracked) {
                    echo '<a title="'.get_string('markallread', 'forumlv').
                         '" href="'.$CFG->wwwroot.'/mod/forumlv/markposts.php?f='.
                         $forumlv->id.'&amp;mark=read&amp;returnpage=view.php&amp;sesskey=' . sesskey() . '">'.
                         '<img src="'.$OUTPUT->pix_url('t/markasread') . '" class="iconsmall" alt="'.get_string('markallread', 'forumlv').'" /></a>';
                }
                echo '</th>';
            }
        }
        echo '<th class="header lastpost" scope="col">'.get_string('lastpost', 'forumlv').'</th>';
        if ((!is_guest($context, $USER) && isloggedin()) && has_capability('mod/forumlv:viewdiscussion', $context)) {
            if (\mod_forumlv\subscriptions::is_subscribable($forumlv)) {
                echo '<th class="header discussionsubscription" scope="col">';
                echo forumlv_get_discussion_subscription_icon_preloaders();
                echo '</th>';
            }
        }
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
    }

    foreach ($discussions as $discussion) {
        if ($forumlv->type == 'qanda' && !has_capability('mod/forumlv:viewqandawithoutposting', $context) &&
            !forumlv_user_has_posted($forumlv->id, $discussion->discussion, $USER->id)) {
            $canviewparticipants = false;
        }

        if (!empty($replies[$discussion->discussion])) {
            $discussion->replies = $replies[$discussion->discussion]->replies;
            $discussion->lastpostid = $replies[$discussion->discussion]->lastpostid;
        } else {
            $discussion->replies = 0;
        }

        // SPECIAL CASE: The front page can display a news item post to non-logged in users.
        // All posts are read in this case.
        if (!$forumlvtracked) {
            $discussion->unread = '-';
        } else if (empty($USER)) {
            $discussion->unread = 0;
        } else {
            if (empty($unreads[$discussion->discussion])) {
                $discussion->unread = 0;
            } else {
                $discussion->unread = $unreads[$discussion->discussion];
            }
        }

        if (isloggedin()) {
            $ownpost = ($discussion->userid == $USER->id);
        } else {
            $ownpost=false;
        }
        // Use discussion name instead of subject of first post.
        $discussion->subject = $discussion->name;

        switch ($displayformat) {
            case 'header':
                if ($groupmode > 0) {
                    if (isset($groups[$discussion->groupid])) {
                        $group = $groups[$discussion->groupid];
                    } else {
                        $group = $groups[$discussion->groupid] = groups_get_group($discussion->groupid);
                    }
                } else {
                    $group = -1;
                }
                forumlv_print_discussion_header($discussion, $forumlv, $group, $strdatestring, $cantrack, $forumlvtracked,
                    $canviewparticipants, $context, $canviewhiddentimedposts);
            break;
            default:
                $link = false;

                if ($discussion->replies) {
                    $link = true;
                } else {
                    $modcontext = context_module::instance($cm->id);
                    $link = forumlv_user_can_see_discussion($forumlv, $discussion, $modcontext, $USER);
                }

                $discussion->forumlv = $forumlv->id;

                forumlv_print_post($discussion, $discussion, $forumlv, $cm, $course, $ownpost, 0, $link, false,
                        '', null, true, $forumlvtracked);
            break;
        }
    }

    if ($displayformat == "header") {
        echo '</tbody>';
        echo '</table>';
    }

    if ($olddiscussionlink) {
        if ($forumlv->type == 'news') {
            $strolder = get_string('oldertopics', 'forumlv');
        } else {
            $strolder = get_string('olderdiscussions', 'forumlv');
        }
        echo '<div class="forumolddiscuss">';
        echo '<a href="'.$CFG->wwwroot.'/mod/forumlv/view.php?f='.$forumlv->id.'&amp;showall=1">';
        echo $strolder.'</a> ...</div>';
    }

    if ($page != -1) { ///Show the paging bar
        echo $OUTPUT->paging_bar($numdiscussions, $page, $perpage, "view.php?f=$forumlv->id");
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
function forumlv_print_discussion($course, $cm, $forumlv, $discussion, $post, $mode, $canreply=NULL, $canrate=false) {
    global $USER, $CFG;

    require_once($CFG->dirroot.'/rating/lib.php');

    $ownpost = (isloggedin() && $USER->id == $post->userid);

    $modcontext = context_module::instance($cm->id);
    if ($canreply === NULL) {
        $reply = forumlv_user_can_post($forumlv, $discussion, $USER, $cm, $course, $modcontext);
    } else {
        $reply = $canreply;
    }

    // $cm holds general cache for forumlv functions
    $cm->cache = new stdClass;
    $cm->cache->groups      = groups_get_all_groups($course->id, 0, $cm->groupingid);
    $cm->cache->usersgroups = array();

    $posters = array();

    // preload all posts - TODO: improve...
    if ($mode == FORUMLV_MODE_FLATNEWEST) {
        $sort = "p.created DESC";
    } else {
        $sort = "p.created ASC";
    }

    $forumlvtracked = forumlv_tp_is_tracked($forumlv);
    $posts = forumlv_get_all_discussion_posts($discussion->id, $sort, $forumlvtracked);
    $post = $posts[$post->id];

    foreach ($posts as $pid=>$p) {
        $posters[$p->userid] = $p->userid;
    }

    // preload all groups of ppl that posted in this discussion
    if ($postersgroups = groups_get_all_groups($course->id, $posters, $cm->groupingid, 'gm.id, gm.groupid, gm.userid')) {
        foreach($postersgroups as $pg) {
            if (!isset($cm->cache->usersgroups[$pg->userid])) {
                $cm->cache->usersgroups[$pg->userid] = array();
            }
            $cm->cache->usersgroups[$pg->userid][$pg->groupid] = $pg->groupid;
        }
        unset($postersgroups);
    }

    //load ratings
    if ($forumlv->assessed != RATING_AGGREGATE_NONE) {
        $ratingoptions = new stdClass;
        $ratingoptions->context = $modcontext;
        $ratingoptions->component = 'mod_forumlv';
        $ratingoptions->ratingarea = 'post';
        $ratingoptions->items = $posts;
        $ratingoptions->aggregate = $forumlv->assessed;//the aggregation method
        $ratingoptions->scaleid = $forumlv->scale;
        $ratingoptions->userid = $USER->id;
        if ($forumlv->type == 'single' or !$discussion->id) {
            $ratingoptions->returnurl = "$CFG->wwwroot/mod/forumlv/view.php?id=$cm->id";
        } else {
            $ratingoptions->returnurl = "$CFG->wwwroot/mod/forumlv/discuss.php?d=$discussion->id";
        }
        $ratingoptions->assesstimestart = $forumlv->assesstimestart;
        $ratingoptions->assesstimefinish = $forumlv->assesstimefinish;

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

//         $rm = new rating_manager();
//         $posts = $rm->get_ratings($ratingoptions);
    }


    $post->forumlv = $forumlv->id;   // Add the forumlv id to the post object, later used by forumlv_print_post
    $post->forumlvtype = $forumlv->type;

    $post->subject = format_string($post->subject);

    $postread = !empty($post->postread);

    forumlv_print_post($post, $discussion, $forumlv, $cm, $course, $ownpost, $reply, false,
                         '', '', $postread, true, $forumlvtracked);

    switch ($mode) {
        case FORUMLV_MODE_FLATOLDEST :
        case FORUMLV_MODE_FLATNEWEST :
        default:
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

    $link  = false;

    if ($mode == FORUMLV_MODE_FLATNEWEST) {
        $sort = "ORDER BY created DESC";
    } else {
        $sort = "ORDER BY created ASC";
    }

    foreach ($posts as $post) {
        if (!$post->parent) {
            continue;
        }
        $post->subject = format_string($post->subject);
        $ownpost = ($USER->id == $post->userid);

        $postread = !empty($post->postread);

        forumlv_print_post($post, $discussion, $forumlv, $cm, $course, $ownpost, $reply, $link,
                             '', '', $postread, true, $forumlvtracked);
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

    $link  = false;

    if (!empty($posts[$parent->id]->children)) {
        $posts = $posts[$parent->id]->children;

        $modcontext       = context_module::instance($cm->id);
        $canviewfullnames = has_capability('moodle/site:viewfullnames', $modcontext);

        foreach ($posts as $post) {

            echo '<div class="indent">';
            if ($depth > 0) {
                $ownpost = ($USER->id == $post->userid);
                $post->subject = format_string($post->subject);

                $postread = !empty($post->postread);

                forumlv_print_post($post, $discussion, $forumlv, $cm, $course, $ownpost, $reply, $link,
                                     '', '', $postread, true, $forumlvtracked);
            } else {
                if (!forumlv_user_can_see_post($forumlv, $discussion, $post, NULL, $cm)) {
                    echo "</div>\n";
                    continue;
                }
                $by = new stdClass();
                $by->name = fullname($post, $canviewfullnames);
                $by->date = userdate($post->modified);

                if ($forumlvtracked) {
                    if (!empty($post->postread)) {
                        $style = '<span class="forumthread read">';
                    } else {
                        $style = '<span class="forumthread unread">';
                    }
                } else {
                    $style = '<span class="forumthread">';
                }
                echo $style."<a name=\"$post->id\"></a>".
                     "<a href=\"discuss.php?d=$post->discussion&amp;parent=$post->id\">".format_string($post->subject,true)."</a> ";
                print_string("bynameondate", "forumlv", $by);
                echo "</span>";
            }

            forumlv_print_posts_threaded($course, $cm, $forumlv, $discussion, $post, $depth-1, $reply, $forumlvtracked, $posts);
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

    $link  = false;

    if (!empty($posts[$parent->id]->children)) {
        $posts = $posts[$parent->id]->children;

        foreach ($posts as $post) {

            echo '<div class="indent">';
            if (!isloggedin()) {
                $ownpost = false;
            } else {
                $ownpost = ($USER->id == $post->userid);
            }

            $post->subject = format_string($post->subject);
            $postread = !empty($post->postread);

            forumlv_print_post($post, $discussion, $forumlv, $cm, $course, $ownpost, $reply, $link,
                                 '', '', $postread, true, $forumlvtracked);
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
function forumlv_get_recent_mod_activity(&$activities, &$index, $timestart, $courseid, $cmid, $userid=0, $groupid=0)  {
    global $CFG, $COURSE, $USER, $DB;

    if ($COURSE->id == $courseid) {
        $course = $COURSE;
    } else {
        $course = $DB->get_record('course', array('id' => $courseid));
    }

    $modinfo = get_fast_modinfo($course);

    $cm = $modinfo->cms[$cmid];
    $params = array($timestart, $cm->instance);

    if ($userid) {
        $userselect = "AND u.id = ?";
        $params[] = $userid;
    } else {
        $userselect = "";
    }

    if ($groupid) {
        $groupselect = "AND d.groupid = ?";
        $params[] = $groupid;
    } else {
        $groupselect = "";
    }

    $allnames = get_all_user_name_fields(true, 'u');
    if (!$posts = $DB->get_records_sql("SELECT p.*, f.type AS forumlvtype, d.forumlv, d.groupid,
                                              d.timestart, d.timeend, d.userid AS duserid,
                                              $allnames, u.email, u.picture, u.imagealt, u.email
                                         FROM {forumlv_posts} p
                                              JOIN {forumlv_discussions} d ON d.id = p.discussion
                                              JOIN {forumlv} f             ON f.id = d.forumlv
                                              JOIN {user} u              ON u.id = p.userid
                                        WHERE p.created > ? AND f.id = ?
                                              $userselect $groupselect
                                     ORDER BY p.id ASC", $params)) { // order by initial posting date
         return;
    }

    $groupmode       = groups_get_activity_groupmode($cm, $course);
    $cm_context      = context_module::instance($cm->id);
    $viewhiddentimed = has_capability('mod/forumlv:viewhiddentimedposts', $cm_context);
    $accessallgroups = has_capability('moodle/site:accessallgroups', $cm_context);

    $printposts = array();
    foreach ($posts as $post) {

        if (!empty($CFG->forumlv_enabletimedposts) and $USER->id != $post->duserid
          and (($post->timestart > 0 and $post->timestart > time()) or ($post->timeend > 0 and $post->timeend < time()))) {
            if (!$viewhiddentimed) {
                continue;
            }
        }

        if ($groupmode) {
            if ($post->groupid == -1 or $groupmode == VISIBLEGROUPS or $accessallgroups) {
                // oki (Open discussions have groupid -1)
            } else {
                // separate mode
                if (isguestuser()) {
                    // shortcut
                    continue;
                }

                if (!in_array($post->groupid, $modinfo->get_groups($cm->groupingid))) {
                    continue;
                }
            }
        }

        $printposts[] = $post;
    }

    if (!$printposts) {
        return;
    }

    $aname = format_string($cm->name,true);

    foreach ($printposts as $post) {
        $tmpactivity = new stdClass();

        $tmpactivity->type         = 'forumlv';
        $tmpactivity->cmid         = $cm->id;
        $tmpactivity->name         = $aname;
        $tmpactivity->sectionnum   = $cm->sectionnum;
        $tmpactivity->timestamp    = $post->modified;

        $tmpactivity->content = new stdClass();
        $tmpactivity->content->id         = $post->id;
        $tmpactivity->content->discussion = $post->discussion;
        $tmpactivity->content->subject    = format_string($post->subject);
        $tmpactivity->content->parent     = $post->parent;

        $tmpactivity->user = new stdClass();
        $additionalfields = array('id' => 'userid', 'picture', 'imagealt', 'email');
        $additionalfields = explode(',', user_picture::fields());
        $tmpactivity->user = username_load_fields_from_object($tmpactivity->user, $post, null, $additionalfields);
        $tmpactivity->user->id = $post->userid;

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

    if ($activity->content->parent) {
        $class = 'reply';
    } else {
        $class = 'discussion';
    }

    echo '<table border="0" cellpadding="3" cellspacing="0" class="forum-recent">';

    echo "<tr><td class=\"userpicture\" valign=\"top\">";
    echo $OUTPUT->user_picture($activity->user, array('courseid'=>$courseid));
    echo "</td><td class=\"$class\">";

    if ($activity->content->parent) {
        $class = 'title';
    } else {
        // Bold the title of new discussions so they stand out.
        $class = 'title bold';
    }
    echo "<div class=\"{$class}\">";
    if ($detail) {
        $aname = s($activity->name);
        echo "<img src=\"" . $OUTPUT->pix_url('icon', $activity->type) . "\" ".
             "class=\"icon\" alt=\"{$aname}\" />";
    }
    echo "<a href=\"$CFG->wwwroot/mod/forumlv/discuss.php?d={$activity->content->discussion}"
         ."#p{$activity->content->id}\">{$activity->content->subject}</a>";
    echo '</div>';

    echo '<div class="user">';
    $fullname = fullname($activity->user, $viewfullnames);
    echo "<a href=\"$CFG->wwwroot/user/view.php?id={$activity->user->id}&amp;course=$courseid\">"
         ."{$fullname}</a> - ".userdate($activity->timestamp);
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
    $DB->set_field('forumlv_posts', 'discussion', $discussionid, array('id' => $postid));
    if ($posts = $DB->get_records('forumlv_posts', array('parent' => $postid))) {
        foreach ($posts as $post) {
            forumlv_change_discussionid($post->id, $discussionid);
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

    if (!empty($USER->subscriptionsediting)) {
        $string = get_string('turneditingoff');
        $edit = "off";
    } else {
        $string = get_string('turneditingon');
        $edit = "on";
    }

    return "<form method=\"get\" action=\"$CFG->wwwroot/mod/forumlv/subscribers.php\">".
           "<input type=\"hidden\" name=\"id\" value=\"$forumlvid\" />".
           "<input type=\"hidden\" name=\"edit\" value=\"$edit\" />".
           "<input type=\"submit\" value=\"$string\" /></form>";
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
    $cutoffdate = $now - ($CFG->forumlv_oldpostdays * 24 * 3600);

    if (empty($postids)) {
        return true;

    } else if (count($postids) > 200) {
        while ($part = array_splice($postids, 0, 200)) {
            $status = forumlv_tp_mark_posts_read($user, $part) && $status;
        }
        return $status;
    }

    list($usql, $postidparams) = $DB->get_in_or_equal($postids, SQL_PARAMS_NAMED, 'postid');

    $insertparams = array(
        'userid1' => $user->id,
        'userid2' => $user->id,
        'userid3' => $user->id,
        'firstread' => $now,
        'lastread' => $now,
        'cutoffdate' => $cutoffdate,
    );
    $params = array_merge($postidparams, $insertparams);

    if ($CFG->forumlv_allowforcedreadtracking) {
        $trackingsql = "AND (f.trackingtype = ".FORUMLV_TRACKING_FORCED."
                        OR (f.trackingtype = ".FORUMLV_TRACKING_OPTIONAL." AND tf.id IS NULL))";
    } else {
        $trackingsql = "AND ((f.trackingtype = ".FORUMLV_TRACKING_OPTIONAL."  OR f.trackingtype = ".FORUMLV_TRACKING_FORCED.")
                            AND tf.id IS NULL)";
    }

    // First insert any new entries.
    $sql = "INSERT INTO {forumlv_read} (userid, postid, discussionid, forumlvid, firstread, lastread)

            SELECT :userid1, p.id, p.discussion, d.forumlv, :firstread, :lastread
                FROM {forumlv_posts} p
                    JOIN {forumlv_discussions} d       ON d.id = p.discussion
                    JOIN {forumlv} f                   ON f.id = d.forumlv
                    LEFT JOIN {forumlv_track_prefs} tf ON (tf.userid = :userid2 AND tf.forumlvid = f.id)
                    LEFT JOIN {forumlv_read} fr        ON (
                            fr.userid = :userid3
                        AND fr.postid = p.id
                        AND fr.discussionid = d.id
                        AND fr.forumlvid = f.id
                    )
                WHERE p.id $usql
                    AND p.modified >= :cutoffdate
                    $trackingsql
                    AND fr.id IS NULL";

    $status = $DB->execute($sql, $params) && $status;

    // Then update all records.
    $updateparams = array(
        'userid' => $user->id,
        'lastread' => $now,
    );
    $params = array_merge($postidparams, $updateparams);
    $status = $DB->set_field_select('forumlv_read', 'lastread', $now, '
                userid      =  :userid
            AND lastread    <> :lastread
            AND postid      ' . $usql,
            $params) && $status;

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
    $cutoffdate = $now - ($CFG->forumlv_oldpostdays * 24 * 3600);

    if (!$DB->record_exists('forumlv_read', array('userid' => $userid, 'postid' => $postid))) {
        $sql = "INSERT INTO {forumlv_read} (userid, postid, discussionid, forumlvid, firstread, lastread)

                SELECT ?, p.id, p.discussion, d.forumlv, ?, ?
                  FROM {forumlv_posts} p
                       JOIN {forumlv_discussions} d ON d.id = p.discussion
                 WHERE p.id = ? AND p.modified >= ?";
        return $DB->execute($sql, array($userid, $now, $now, $postid, $cutoffdate));

    } else {
        $sql = "UPDATE {forumlv_read}
                   SET lastread = ?
                 WHERE userid = ? AND postid = ?";
        return $DB->execute($sql, array($now, $userid, $userid));
    }
}

/**
 * If its an old post, do nothing. If the record exists, the maintenance will clear it up later.
 *
 * @return bool
 */
function forumlv_tp_mark_post_read($userid, $post, $forumlvid) {
    if (!forumlv_tp_is_post_old($post)) {
        return forumlv_tp_add_read_record($userid, $post->id);
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
function forumlv_tp_mark_forumlv_read($user, $forumlvid, $groupid=false) {
    global $CFG, $DB;

    $cutoffdate = time() - ($CFG->forumlv_oldpostdays*24*60*60);

    $groupsel = "";
    $params = array($user->id, $forumlvid, $cutoffdate);

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

    if ($posts = $DB->get_records_sql($sql, $params)) {
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

    $cutoffdate = time() - ($CFG->forumlv_oldpostdays*24*60*60);

    $sql = "SELECT p.id
              FROM {forumlv_posts} p
                   LEFT JOIN {forumlv_read} r ON (r.postid = p.id AND r.userid = ?)
             WHERE p.discussion = ?
                   AND p.modified >= ? AND r.id is NULL";

    if ($posts = $DB->get_records_sql($sql, array($user->id, $discussionid, $cutoffdate))) {
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
    return (forumlv_tp_is_post_old($post) ||
            $DB->record_exists('forumlv_read', array('userid' => $userid, 'postid' => $post->id)));
}

/**
 * @global object
 * @param object $post
 * @param int $time Defautls to time()
 */
function forumlv_tp_is_post_old($post, $time=null) {
    global $CFG;

    if (is_null($time)) {
        $time = time();
    }
    return ($post->modified < ($time - ($CFG->forumlv_oldpostdays * 24 * 3600)));
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

    $now = round(time(), -2); // DB cache friendliness.
    $cutoffdate = $now - ($CFG->forumlv_oldpostdays * 24 * 60 * 60);
    $params = array($userid, $userid, $courseid, $cutoffdate, $userid);

    if (!empty($CFG->forumlv_enabletimedposts)) {
        $timedsql = "AND d.timestart < ? AND (d.timeend = 0 OR d.timeend > ?)";
        $params[] = $now;
        $params[] = $now;
    } else {
        $timedsql = "";
    }

    if ($CFG->forumlv_allowforcedreadtracking) {
        $trackingsql = "AND (f.trackingtype = ".FORUMLV_TRACKING_FORCED."
                            OR (f.trackingtype = ".FORUMLV_TRACKING_OPTIONAL." AND tf.id IS NULL
                                AND (SELECT trackforumlvs FROM {user} WHERE id = ?) = 1))";
    } else {
        $trackingsql = "AND ((f.trackingtype = ".FORUMLV_TRACKING_OPTIONAL." OR f.trackingtype = ".FORUMLV_TRACKING_FORCED.")
                            AND tf.id IS NULL
                            AND (SELECT trackforumlvs FROM {user} WHERE id = ?) = 1)";
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
                   $trackingsql
                   $timedsql
          GROUP BY f.id";

    if ($return = $DB->get_records_sql($sql, $params)) {
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

    $forumlvid = $cm->instance;

    if (!isset($readcache[$course->id])) {
        $readcache[$course->id] = array();
        if ($counts = forumlv_tp_get_course_unread_posts($USER->id, $course->id)) {
            foreach ($counts as $count) {
                $readcache[$course->id][$count->id] = $count->unread;
            }
        }
    }

    if (empty($readcache[$course->id][$forumlvid])) {
        // no need to check group mode ;-)
        return 0;
    }

    $groupmode = groups_get_activity_groupmode($cm, $course);

    if ($groupmode != SEPARATEGROUPS) {
        return $readcache[$course->id][$forumlvid];
    }

    if (has_capability('moodle/site:accessallgroups', context_module::instance($cm->id))) {
        return $readcache[$course->id][$forumlvid];
    }

    require_once($CFG->dirroot.'/course/lib.php');

    $modinfo = get_fast_modinfo($course);

    $mygroups = $modinfo->get_groups($cm->groupingid);

    // add all groups posts
    $mygroups[-1] = -1;

    list ($groups_sql, $groups_params) = $DB->get_in_or_equal($mygroups);

    $now = round(time(), -2); // db cache friendliness
    $cutoffdate = $now - ($CFG->forumlv_oldpostdays*24*60*60);
    $params = array($USER->id, $forumlvid, $cutoffdate);

    if (!empty($CFG->forumlv_enabletimedposts)) {
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

    return $DB->get_field_sql($sql, $params);
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
function forumlv_tp_delete_read_records($userid=-1, $postid=-1, $discussionid=-1, $forumlvid=-1) {
    global $DB;
    $params = array();

    $select = '';
    if ($userid > -1) {
        if ($select != '') $select .= ' AND ';
        $select .= 'userid = ?';
        $params[] = $userid;
    }
    if ($postid > -1) {
        if ($select != '') $select .= ' AND ';
        $select .= 'postid = ?';
        $params[] = $postid;
    }
    if ($discussionid > -1) {
        if ($select != '') $select .= ' AND ';
        $select .= 'discussionid = ?';
        $params[] = $discussionid;
    }
    if ($forumlvid > -1) {
        if ($select != '') $select .= ' AND ';
        $select .= 'forumlvid = ?';
        $params[] = $forumlvid;
    }
    if ($select == '') {
        return false;
    }
    else {
        return $DB->delete_records_select('forumlv_read', $select, $params);
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

    if ($CFG->forumlv_allowforcedreadtracking) {
        $trackingsql = "AND (f.trackingtype = ".FORUMLV_TRACKING_OFF."
                            OR (f.trackingtype = ".FORUMLV_TRACKING_OPTIONAL." AND (ft.id IS NOT NULL
                                OR (SELECT trackforumlvs FROM {user} WHERE id = ?) = 0)))";
    } else {
        $trackingsql = "AND (f.trackingtype = ".FORUMLV_TRACKING_OFF."
                            OR ((f.trackingtype = ".FORUMLV_TRACKING_OPTIONAL." OR f.trackingtype = ".FORUMLV_TRACKING_FORCED.")
                                AND (ft.id IS NOT NULL
                                    OR (SELECT trackforumlvs FROM {user} WHERE id = ?) = 0)))";
    }

    $sql = "SELECT f.id
              FROM {forumlv} f
                   LEFT JOIN {forumlv_track_prefs} ft ON (ft.forumlvid = f.id AND ft.userid = ?)
             WHERE f.course = ?
                   $trackingsql";

    if ($forumlvs = $DB->get_records_sql($sql, array($userid, $courseid, $userid))) {
        foreach ($forumlvs as $forumlv) {
            $forumlvs[$forumlv->id] = $forumlv;
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
function forumlv_tp_can_track_forumlvs($forumlv=false, $user=false) {
    global $USER, $CFG, $DB;

    // if possible, avoid expensive
    // queries
    if (empty($CFG->forumlv_trackreadposts)) {
        return false;
    }

    if ($user === false) {
        $user = $USER;
    }

    if (isguestuser($user) or empty($user->id)) {
        return false;
    }

    if ($forumlv === false) {
        if ($CFG->forumlv_allowforcedreadtracking) {
            // Since we can force tracking, assume yes without a specific forumlv.
            return true;
        } else {
        	//@lvs corrigido renomeação :  o certo é trackforums e não trackforumlvs
            return (bool)$user->trackforums;
        }
    }

    // Work toward always passing an object...
    if (is_numeric($forumlv)) {
        debugging('Better use proper forumlv object.', DEBUG_DEVELOPER);
        $forumlv = $DB->get_record('forumlv', array('id' => $forumlv), '', 'id,trackingtype');
    }

    $forumlvallows = ($forumlv->trackingtype == FORUMLV_TRACKING_OPTIONAL);
    $forumlvforced = ($forumlv->trackingtype == FORUMLV_TRACKING_FORCED);

    if ($CFG->forumlv_allowforcedreadtracking) {
        // If we allow forcing, then forced forumlvs takes procidence over user setting.
    	//@lvs corrigido renomeação : o certo é trackforums e não trackforumlvs
        return ($forumlvforced || ($forumlvallows  && (!empty($user->trackforums) && (bool)$user->trackforums)));
    } else {
        // If we don't allow forcing, user setting trumps.
    	//@lvs corrigido renomeação : o certo é trackforums e não trackforumlvs
        return ($forumlvforced || $forumlvallows)  && !empty($user->trackforums);
    }
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
function forumlv_tp_is_tracked($forumlv, $user=false) {
    global $USER, $CFG, $DB;

    if ($user === false) {
        $user = $USER;
    }

    if (isguestuser($user) or empty($user->id)) {
        return false;
    }

    // Work toward always passing an object...
    if (is_numeric($forumlv)) {
        debugging('Better use proper forumlv object.', DEBUG_DEVELOPER);
        $forumlv = $DB->get_record('forumlv', array('id' => $forumlv));
    }

    if (!forumlv_tp_can_track_forumlvs($forumlv, $user)) {
        return false;
    }

    $forumlvallows = ($forumlv->trackingtype == FORUMLV_TRACKING_OPTIONAL);
    $forumlvforced = ($forumlv->trackingtype == FORUMLV_TRACKING_FORCED);
    $userpref = $DB->get_record('forumlv_track_prefs', array('userid' => $user->id, 'forumlvid' => $forumlv->id));

    if ($CFG->forumlv_allowforcedreadtracking) {
        return $forumlvforced || ($forumlvallows && $userpref === false);
    } else {
        return  ($forumlvallows || $forumlvforced) && $userpref === false;
    }
}

/**
 * @global object
 * @global object
 * @param int $forumlvid
 * @param int $userid
 */
function forumlv_tp_start_tracking($forumlvid, $userid=false) {
    global $USER, $DB;

    if ($userid === false) {
        $userid = $USER->id;
    }

    return $DB->delete_records('forumlv_track_prefs', array('userid' => $userid, 'forumlvid' => $forumlvid));
}

/**
 * @global object
 * @global object
 * @param int $forumlvid
 * @param int $userid
 */
function forumlv_tp_stop_tracking($forumlvid, $userid=false) {
    global $USER, $DB;

    if ($userid === false) {
        $userid = $USER->id;
    }

    if (!$DB->record_exists('forumlv_track_prefs', array('userid' => $userid, 'forumlvid' => $forumlvid))) {
        $track_prefs = new stdClass();
        $track_prefs->userid = $userid;
        $track_prefs->forumlvid = $forumlvid;
        $DB->insert_record('forumlv_track_prefs', $track_prefs);
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

    if (!isset($CFG->forumlv_oldpostdays)) {
        return;
    }
// Look for records older than the cutoffdate that are still in the forumlv_read table.
    $cutoffdate = time() - ($CFG->forumlv_oldpostdays*24*60*60);

    //first get the oldest tracking present - we need tis to speedup the next delete query
    $sql = "SELECT MIN(fp.modified) AS first
              FROM {forumlv_posts} fp
                   JOIN {forumlv_read} fr ON fr.postid=fp.id";
    if (!$first = $DB->get_field_sql($sql)) {
        // nothing to delete;
        return;
    }

    // now delete old tracking info
    $sql = "DELETE
              FROM {forumlv_read}
             WHERE postid IN (SELECT fp.id
                                FROM {forumlv_posts} fp
                               WHERE fp.modified >= ? AND fp.modified < ?)";
    $DB->execute($sql, array($first, $cutoffdate));
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
    if (!$DB->record_exists('forumlv_discussions', array('id' => $discussionid))) {
        return false;
    }

// Use SQL to find the last post for this discussion
    $sql = "SELECT id, userid, modified
              FROM {forumlv_posts}
             WHERE discussion=?
             ORDER BY modified DESC";

// Lets go find the last post
    if (($lastposts = $DB->get_records_sql($sql, array($discussionid), 0, 1))) {
        $lastpost = reset($lastposts);
        $discussionobject = new stdClass();
        $discussionobject->id           = $discussionid;
        $discussionobject->usermodified = $lastpost->userid;
        $discussionobject->timemodified = $lastpost->modified;
        $DB->update_record('forumlv_discussions', $discussionobject);
        return $lastpost->id;
    }

// To get here either we couldn't find a post for the discussion (weird)
// or we couldn't update the discussion record (weird x2)
    return false;
}


/**
 * List the actions that correspond to a view of this module.
 * This is used by the participation report.
 *
 * Note: This is not used by new logging system. Event with
 *       crud = 'r' and edulevel = LEVEL_PARTICIPATING will
 *       be considered as view action.
 *
 * @return array
 */
function forumlv_get_view_actions() {
    return array('view discussion', 'search', 'forumlv', 'forumlvs', 'subscribers', 'view forumlv');
}

/**
 * List the actions that correspond to a post of this module.
 * This is used by the participation report.
 *
 * Note: This is not used by new logging system. Event with
 *       crud = ('c' || 'u' || 'd') and edulevel = LEVEL_PARTICIPATING
 *       will be considered as post action.
 *
 * @return array
 */
function forumlv_get_post_actions() {
    return array('add discussion','add post','delete discussion','delete post','move discussion','prune post','update post');
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
        $forumlv = $DB->get_record('forumlv', array('id' => $forumlv), '*', MUST_EXIST);
    }

    if (!is_object($forumlv)) {
        return false; // This is broken.
    }

    if (!$cm) {
        $cm = get_coursemodule_from_instance('forumlv', $forumlv->id, $forumlv->course, false, MUST_EXIST);
    }

    if (empty($forumlv->blockafter)) {
        return false;
    }

    if (empty($forumlv->blockperiod)) {
        return false;
    }

    $modcontext = context_module::instance($cm->id);
    if (has_capability('mod/forumlv:postwithoutthrottling', $modcontext)) {
        return false;
    }

    // Get the number of posts in the last period we care about.
    $timenow = time();
    $timeafter = $timenow - $forumlv->blockperiod;
    $numposts = $DB->count_records_sql('SELECT COUNT(p.id) FROM {forumlv_posts} p
                                        JOIN {forumlv_discussions} d
                                        ON p.discussion = d.id WHERE d.forumlv = ?
                                        AND p.userid = ? AND p.created > ?', array($forumlv->id, $USER->id, $timeafter));

    $a = new stdClass();
    $a->blockafter = $forumlv->blockafter;
    $a->numposts = $numposts;
    $a->blockperiod = get_string('secondstotime'.$forumlv->blockperiod);

    if ($forumlv->blockafter <= $numposts) {
        $warning = new stdClass();
        $warning->canpost = false;
        $warning->errorcode = 'forumlvblockingtoomanyposts';
        $warning->module = 'error';
        $warning->additional = $a;
        $warning->link = $CFG->wwwroot . '/mod/forumlv/view.php?f=' . $forumlv->id;

        return $warning;
    }

    if ($forumlv->warnafter <= $numposts) {
        $warning = new stdClass();
        $warning->canpost = true;
        $warning->errorcode = 'forumlvblockingalmosttoomanyposts';
        $warning->module = 'forumlv';
        $warning->additional = $a;
        $warning->link = null;

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
    if (!empty($thresholdwarning) && !$thresholdwarning->canpost) {
        print_error($thresholdwarning->errorcode,
                    $thresholdwarning->module,
                    $thresholdwarning->link,
                    $thresholdwarning->additional);
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
function forumlv_reset_gradebook($courseid, $type='') {
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

    if ($forumlvs = $DB->get_records_sql($sql, $params)) {
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
    require_once($CFG->dirroot.'/rating/lib.php');

    $componentstr = get_string('modulenameplural', 'forumlv');
    $status = array();

    $params = array($data->courseid);

    $removeposts = false;
    $typesql     = "";
    if (!empty($data->reset_forumlv_all)) {
        $removeposts = true;
        $typesstr    = get_string('resetforumlvsall', 'forumlv');
        $types       = array();
    } else if (!empty($data->reset_forumlv_types)){
        $removeposts = true;
        $types       = array();
        $sqltypes    = array();
        $forumlv_types_all = forumlv_get_forumlv_types_all();
        foreach ($data->reset_forumlv_types as $type) {
            if (!array_key_exists($type, $forumlv_types_all)) {
                continue;
            }
            $types[] = $forumlv_types_all[$type];
            $sqltypes[] = $type;
        }
        if (!empty($sqltypes)) {
            list($typesql, $typeparams) = $DB->get_in_or_equal($sqltypes);
            $typesql = " AND f.type " . $typesql;
            $params = array_merge($params, $typeparams);
        }
        $typesstr = get_string('resetforumlvs', 'forumlv').': '.implode(', ', $types);
    }
    $alldiscussionssql = "SELECT fd.id
                            FROM {forumlv_discussions} fd, {forumlv} f
                           WHERE f.course=? AND f.id=fd.forumlv";

    $allforumlvssql      = "SELECT f.id
                            FROM {forumlv} f
                           WHERE f.course=?";

    $allpostssql       = "SELECT fp.id
                            FROM {forumlv_posts} fp, {forumlv_discussions} fd, {forumlv} f
                           WHERE f.course=? AND f.id=fd.forumlv AND fd.id=fp.discussion";

    $forumlvssql = $forumlvs = $rm = null;

    if( $removeposts || !empty($data->reset_forumlv_ratings) ) {
        $forumlvssql      = "$allforumlvssql $typesql";
        $forumlvs = $forumlvs = $DB->get_records_sql($forumlvssql, $params);
        $rm = new rating_manager();
        $ratingdeloptions = new stdClass;
        $ratingdeloptions->component = 'mod_forumlv';
        $ratingdeloptions->ratingarea = 'post';
    }

    if ($removeposts) {
        $discussionssql = "$alldiscussionssql $typesql";
        $postssql       = "$allpostssql $typesql";

        // now get rid of all attachments
        $fs = get_file_storage();
        if ($forumlvs) {
            foreach ($forumlvs as $forumlvid=>$unused) {
                if (!$cm = get_coursemodule_from_instance('forumlv', $forumlvid)) {
                    continue;
                }
                $context = context_module::instance($cm->id);
                $fs->delete_area_files($context->id, 'mod_forumlv', 'attachment');
                $fs->delete_area_files($context->id, 'mod_forumlv', 'post');

                //remove ratings
                $ratingdeloptions->contextid = $context->id;
                $rm->delete_ratings($ratingdeloptions);
            }
        }

        // first delete all read flags
        $DB->delete_records_select('forumlv_read', "forumlvid IN ($forumlvssql)", $params);

        // remove tracking prefs
        $DB->delete_records_select('forumlv_track_prefs', "forumlvid IN ($forumlvssql)", $params);

        // remove posts from queue
        $DB->delete_records_select('forumlv_queue', "discussionid IN ($discussionssql)", $params);

        // all posts - initial posts must be kept in single simple discussion forumlvs
        $DB->delete_records_select('forumlv_posts', "discussion IN ($discussionssql) AND parent <> 0", $params); // first all children
        $DB->delete_records_select('forumlv_posts', "discussion IN ($discussionssql AND f.type <> 'single') AND parent = 0", $params); // now the initial posts for non single simple

        // finally all discussions except single simple forumlvs
        $DB->delete_records_select('forumlv_discussions', "forumlv IN ($forumlvssql AND f.type <> 'single')", $params);

        // remove all grades from gradebook
        if (empty($data->reset_gradebook_grades)) {
            if (empty($types)) {
                forumlv_reset_gradebook($data->courseid);
            } else {
                foreach ($types as $type) {
                    forumlv_reset_gradebook($data->courseid, $type);
                }
            }
        }

        $status[] = array('component'=>$componentstr, 'item'=>$typesstr, 'error'=>false);
    }

    // remove all ratings in this course's forumlvs
    if (!empty($data->reset_forumlv_ratings)) {
        if ($forumlvs) {
            foreach ($forumlvs as $forumlvid=>$unused) {
                if (!$cm = get_coursemodule_from_instance('forumlv', $forumlvid)) {
                    continue;
                }
                $context = context_module::instance($cm->id);

                //remove ratings
                $ratingdeloptions->contextid = $context->id;
                $rm->delete_ratings($ratingdeloptions);
            }
        }

        // remove all grades from gradebook
        if (empty($data->reset_gradebook_grades)) {
            forumlv_reset_gradebook($data->courseid);
        }
    }

    // remove all digest settings unconditionally - even for users still enrolled in course.
    if (!empty($data->reset_forumlv_digests)) {
        $DB->delete_records_select('forumlv_digests', "forumlv IN ($allforumlvssql)", $params);
        $status[] = array('component' => $componentstr, 'item' => get_string('resetdigests', 'forumlv'), 'error' => false);
    }

    // remove all subscriptions unconditionally - even for users still enrolled in course
    if (!empty($data->reset_forumlv_subscriptions)) {
        $DB->delete_records_select('forumlv_subscriptions', "forumlv IN ($allforumlvssql)", $params);
        $DB->delete_records_select('forumlv_discussion_subs', "forumlv IN ($allforumlvssql)", $params);
        $status[] = array('component' => $componentstr, 'item' => get_string('resetsubscriptions', 'forumlv'), 'error' => false);
    }

    // remove all tracking prefs unconditionally - even for users still enrolled in course
    if (!empty($data->reset_forumlv_track_prefs)) {
        $DB->delete_records_select('forumlv_track_prefs', "forumlvid IN ($allforumlvssql)", $params);
        $status[] = array('component'=>$componentstr, 'item'=>get_string('resettrackprefs','forumlv'), 'error'=>false);
    }

    /// updating dates - shift may be negative too
    if ($data->timeshift) {
        shift_course_mod_dates('forumlv', array('assesstimestart', 'assesstimefinish'), $data->timeshift, $data->courseid);
        $status[] = array('component'=>$componentstr, 'item'=>get_string('datechanged'), 'error'=>false);
    }

    return $status;
}

/**
 * Called by course/reset.php
 *
 * @param $mform form passed by reference
 */
function forumlv_reset_course_form_definition(&$mform) {
    $mform->addElement('header', 'forumlvheader', get_string('modulenameplural', 'forumlv'));

    $mform->addElement('checkbox', 'reset_forumlv_all', get_string('resetforumlvsall','forumlv'));

    $mform->addElement('select', 'reset_forumlv_types', get_string('resetforumlvs', 'forumlv'), forumlv_get_forumlv_types_all(), array('multiple' => 'multiple'));
    $mform->setAdvanced('reset_forumlv_types');
    $mform->disabledIf('reset_forumlv_types', 'reset_forumlv_all', 'checked');

    $mform->addElement('checkbox', 'reset_forumlv_digests', get_string('resetdigests','forumlv'));
    $mform->setAdvanced('reset_forumlv_digests');

    $mform->addElement('checkbox', 'reset_forumlv_subscriptions', get_string('resetsubscriptions','forumlv'));
    $mform->setAdvanced('reset_forumlv_subscriptions');

    $mform->addElement('checkbox', 'reset_forumlv_track_prefs', get_string('resettrackprefs','forumlv'));
    $mform->setAdvanced('reset_forumlv_track_prefs');
    $mform->disabledIf('reset_forumlv_track_prefs', 'reset_forumlv_all', 'checked');

    $mform->addElement('checkbox', 'reset_forumlv_ratings', get_string('deleteallratings'));
    $mform->disabledIf('reset_forumlv_ratings', 'reset_forumlv_all', 'checked');
}

/**
 * Course reset form defaults.
 * @return array
 */
function forumlv_reset_course_form_defaults($course) {
    return array('reset_forumlv_all'=>1, 'reset_forumlv_digests' => 0, 'reset_forumlv_subscriptions'=>0, 'reset_forumlv_track_prefs'=>0, 'reset_forumlv_ratings'=>1);
}

/**
 * Returns array of forumlv layout modes
 *
 * @return array
 */
function forumlv_get_layout_modes() {
    return array (FORUMLV_MODE_FLATOLDEST => get_string('modeflatoldestfirst', 'forumlv'),
                  FORUMLV_MODE_FLATNEWEST => get_string('modeflatnewestfirst', 'forumlv'),
                  FORUMLV_MODE_THREADED   => get_string('modethreaded', 'forumlv'),
                  FORUMLV_MODE_NESTED     => get_string('modenested', 'forumlv'));
}

/**
 * Returns array of forumlv types chooseable on the forumlv editing form
 *
 * @return array
 */
function forumlv_get_forumlv_types() {
    return array ('general'  => get_string('generalforumlv', 'forumlv'),
                  'eachuser' => get_string('eachuserforumlv', 'forumlv'),
                  'single'   => get_string('singleforumlv', 'forumlv'),
                  'qanda'    => get_string('qandaforumlv', 'forumlv'),
                  'blog'     => get_string('blogforumlv', 'forumlv'));
}

/**
 * Returns array of all forumlv layout modes
 *
 * @return array
 */
function forumlv_get_forumlv_types_all() {
    return array ('news'     => get_string('namenews','forumlv'),
                  'social'   => get_string('namesocial','forumlv'),
                  'general'  => get_string('generalforumlv', 'forumlv'),
                  'eachuser' => get_string('eachuserforumlv', 'forumlv'),
                  'single'   => get_string('singleforumlv', 'forumlv'),
                  'qanda'    => get_string('qandaforumlv', 'forumlv'),
                  'blog'     => get_string('blogforumlv', 'forumlv'));
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
 * Adds module specific settings to the settings block
 *
 * @param settings_navigation $settings The settings navigation object
 * @param navigation_node $forumlvnode The node to add module settings to
 */
function forumlv_extend_settings_navigation(settings_navigation $settingsnav, navigation_node $forumlvnode) {
    global $USER, $PAGE, $CFG, $DB, $OUTPUT;

    $forumlvobject = $DB->get_record("forumlv", array("id" => $PAGE->cm->instance));
    if (empty($PAGE->cm->context)) {
        $PAGE->cm->context = context_module::instance($PAGE->cm->instance);
    }

    $params = $PAGE->url->params();
    if (!empty($params['d'])) {
        $discussionid = $params['d'];
    }

    // for some actions you need to be enrolled, beiing admin is not enough sometimes here
    $enrolled = is_enrolled($PAGE->cm->context, $USER, '', false);
    $activeenrolled = is_enrolled($PAGE->cm->context, $USER, '', true);

    $canmanage  = has_capability('mod/forumlv:managesubscriptions', $PAGE->cm->context);
    $subscriptionmode = \mod_forumlv\subscriptions::get_subscription_mode($forumlvobject);
    $cansubscribe = $activeenrolled && !\mod_forumlv\subscriptions::is_forcesubscribed($forumlvobject) &&
            (!\mod_forumlv\subscriptions::subscription_disabled($forumlvobject) || $canmanage);

    if ($canmanage) {
        $mode = $forumlvnode->add(get_string('subscriptionmode', 'forumlv'), null, navigation_node::TYPE_CONTAINER);

        $allowchoice = $mode->add(get_string('subscriptionoptional', 'forumlv'), new moodle_url('/mod/forumlv/subscribe.php', array('id'=>$forumlvobject->id, 'mode'=>FORUMLV_CHOOSESUBSCRIBE, 'sesskey'=>sesskey())), navigation_node::TYPE_SETTING);
        $forceforever = $mode->add(get_string("subscriptionforced", "forumlv"), new moodle_url('/mod/forumlv/subscribe.php', array('id'=>$forumlvobject->id, 'mode'=>FORUMLV_FORCESUBSCRIBE, 'sesskey'=>sesskey())), navigation_node::TYPE_SETTING);
        $forceinitially = $mode->add(get_string("subscriptionauto", "forumlv"), new moodle_url('/mod/forumlv/subscribe.php', array('id'=>$forumlvobject->id, 'mode'=>FORUMLV_INITIALSUBSCRIBE, 'sesskey'=>sesskey())), navigation_node::TYPE_SETTING);
        $disallowchoice = $mode->add(get_string('subscriptiondisabled', 'forumlv'), new moodle_url('/mod/forumlv/subscribe.php', array('id'=>$forumlvobject->id, 'mode'=>FORUMLV_DISALLOWSUBSCRIBE, 'sesskey'=>sesskey())), navigation_node::TYPE_SETTING);

        switch ($subscriptionmode) {
            case FORUMLV_CHOOSESUBSCRIBE : // 0
                $allowchoice->action = null;
                $allowchoice->add_class('activesetting');
                break;
            case FORUMLV_FORCESUBSCRIBE : // 1
                $forceforever->action = null;
                $forceforever->add_class('activesetting');
                break;
            case FORUMLV_INITIALSUBSCRIBE : // 2
                $forceinitially->action = null;
                $forceinitially->add_class('activesetting');
                break;
            case FORUMLV_DISALLOWSUBSCRIBE : // 3
                $disallowchoice->action = null;
                $disallowchoice->add_class('activesetting');
                break;
        }

    } else if ($activeenrolled) {

        switch ($subscriptionmode) {
            case FORUMLV_CHOOSESUBSCRIBE : // 0
                $notenode = $forumlvnode->add(get_string('subscriptionoptional', 'forumlv'));
                break;
            case FORUMLV_FORCESUBSCRIBE : // 1
                $notenode = $forumlvnode->add(get_string('subscriptionforced', 'forumlv'));
                break;
            case FORUMLV_INITIALSUBSCRIBE : // 2
                $notenode = $forumlvnode->add(get_string('subscriptionauto', 'forumlv'));
                break;
            case FORUMLV_DISALLOWSUBSCRIBE : // 3
                $notenode = $forumlvnode->add(get_string('subscriptiondisabled', 'forumlv'));
                break;
        }
    }

    if ($cansubscribe) {
        if (\mod_forumlv\subscriptions::is_subscribed($USER->id, $forumlvobject, null, $PAGE->cm)) {
            $linktext = get_string('unsubscribe', 'forumlv');
        } else {
            $linktext = get_string('subscribe', 'forumlv');
        }
        $url = new moodle_url('/mod/forumlv/subscribe.php', array('id'=>$forumlvobject->id, 'sesskey'=>sesskey()));
        $forumlvnode->add($linktext, $url, navigation_node::TYPE_SETTING);

        if (isset($discussionid)) {
            if (\mod_forumlv\subscriptions::is_subscribed($USER->id, $forumlvobject, $discussionid, $PAGE->cm)) {
                $linktext = get_string('unsubscribediscussion', 'forumlv');
            } else {
                $linktext = get_string('subscribediscussion', 'forumlv');
            }
            $url = new moodle_url('/mod/forumlv/subscribe.php', array(
                    'id' => $forumlvobject->id,
                    'sesskey' => sesskey(),
                    'd' => $discussionid,
                    'returnurl' => $PAGE->url->out(),
                ));
            $forumlvnode->add($linktext, $url, navigation_node::TYPE_SETTING);
        }
    }

    if (has_capability('mod/forumlv:viewsubscribers', $PAGE->cm->context)){
        $url = new moodle_url('/mod/forumlv/subscribers.php', array('id'=>$forumlvobject->id));
        $forumlvnode->add(get_string('showsubscribers', 'forumlv'), $url, navigation_node::TYPE_SETTING);
    }

    if ($enrolled && forumlv_tp_can_track_forumlvs($forumlvobject)) { // keep tracking info for users with suspended enrolments
        if ($forumlvobject->trackingtype == FORUMLV_TRACKING_OPTIONAL
                || ((!$CFG->forumlv_allowforcedreadtracking) && $forumlvobject->trackingtype == FORUMLV_TRACKING_FORCED)) {
            if (forumlv_tp_is_tracked($forumlvobject)) {
                $linktext = get_string('notrackforumlv', 'forumlv');
            } else {
                $linktext = get_string('trackforumlv', 'forumlv');
            }
            $url = new moodle_url('/mod/forumlv/settracking.php', array(
                    'id' => $forumlvobject->id,
                    'sesskey' => sesskey(),
                ));
            $forumlvnode->add($linktext, $url, navigation_node::TYPE_SETTING);
        }
    }

    if (!isloggedin() && $PAGE->course->id == SITEID) {
        $userid = guest_user()->id;
    } else {
        $userid = $USER->id;
    }

    $hascourseaccess = ($PAGE->course->id == SITEID) || can_access_course($PAGE->course, $userid);
    $enablerssfeeds = !empty($CFG->enablerssfeeds) && !empty($CFG->forumlv_enablerssfeeds);

    if ($enablerssfeeds && $forumlvobject->rsstype && $forumlvobject->rssarticles && $hascourseaccess) {

        if (!function_exists('rss_get_url')) {
            require_once("$CFG->libdir/rsslib.php");
        }

        if ($forumlvobject->rsstype == 1) {
            $string = get_string('rsssubscriberssdiscussions','forumlv');
        } else {
            $string = get_string('rsssubscriberssposts','forumlv');
        }

        $url = new moodle_url(rss_get_url($PAGE->cm->context->id, $userid, "mod_forumlv", $forumlvobject->id));
        $forumlvnode->add($string, $url, settings_navigation::TYPE_SETTING, null, null, new pix_icon('i/rss', ''));
    }
}

/**
 * Adds information about unread messages, that is only required for the course view page (and
 * similar), to the course-module object.
 * @param cm_info $cm Course-module object
 */
function forumlv_cm_info_view(cm_info $cm) {
    global $CFG;

    if (forumlv_tp_can_track_forumlvs()) {
        if ($unread = forumlv_tp_count_forumlv_unread_posts($cm, $cm->get_course())) {
            $out = '<span class="unread"> <a href="' . $cm->url . '">';
            if ($unread == 1) {
                $out .= get_string('unreadpostsone', 'forumlv');
            } else {
                $out .= get_string('unreadpostsnumber', 'forumlv', $unread);
            }
            $out .= '</a></span>';
            $cm->set_after_link($out);
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
    $forumlv_pagetype = array(
        'mod-forumlv-*'=>get_string('page-mod-forumlv-x', 'forumlv'),
        'mod-forumlv-view'=>get_string('page-mod-forumlv-view', 'forumlv'),
        'mod-forumlv-discuss'=>get_string('page-mod-forumlv-discuss', 'forumlv')
    );
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
        $subquery = "(SELECT DISTINCT fd.course
                         FROM {forumlv_discussions} fd
                         JOIN {forumlv_posts} fp ON fp.discussion = fd.id
                        WHERE fp.userid = :userid )";
    } else {
        $subquery= "(SELECT DISTINCT fd.course
                         FROM {forumlv_discussions} fd
                        WHERE fd.userid = :userid )";
    }

    $params = array('userid' => $user->id);

    // Join to the context table so that we can preload contexts if required.
    if ($includecontexts) {
        $ctxselect = ', ' . context_helper::get_preload_record_columns_sql('ctx');
        $ctxjoin = "LEFT JOIN {context} ctx ON (ctx.instanceid = c.id AND ctx.contextlevel = :contextlevel)";
        $params['contextlevel'] = CONTEXT_COURSE;
    } else {
        $ctxselect = '';
        $ctxjoin = '';
    }

    // Now we need to get all of the courses to search.
    // All courses where the user has posted within a forumlv will be returned.
    $sql = "SELECT c.* $ctxselect
            FROM {course} c
            $ctxjoin
            WHERE c.id IN ($subquery)";
    $courses = $DB->get_records_sql($sql, $params, $limitfrom, $limitnum);
    if ($includecontexts) {
        array_map('context_helper::preload_from_record', $courses);
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
        list($coursewhere, $params) = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'courseid');
        $coursewhere = ' AND f.course '.$coursewhere;
    } else {
        $coursewhere = '';
        $params = array();
    }
    $params['userid'] = $user->id;
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

    $courseforumlvs = $DB->get_records_sql($sql, $params, $limitfrom, $limitnum);
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
    $return->totalcount = 0;    // The total number of posts that the current user is able to view
    $return->courses = array(); // The courses the current user can access
    $return->forumlvs = array();  // The forumlvs that the current user can access that contain posts
    $return->posts = array();   // The posts to display

    // First up a small sanity check. If there are no courses to check we can
    // return immediately, there is obviously nothing to search.
    if (empty($courses)) {
        return $return;
    }

    // A couple of quick setups
    $isloggedin = isloggedin();
    $isguestuser = $isloggedin && isguestuser();
    $iscurrentuser = $isloggedin && $USER->id == $user->id;

    // Checkout whether or not the current user has capabilities over the requested
    // user and if so they have the capabilities required to view the requested
    // users content.
    $usercontext = context_user::instance($user->id, MUST_EXIST);
    $hascapsonuser = !$iscurrentuser && $DB->record_exists('role_assignments', array('userid' => $USER->id, 'contextid' => $usercontext->id));
    $hascapsonuser = $hascapsonuser && has_all_capabilities(array('moodle/user:viewdetails', 'moodle/user:readuserposts'), $usercontext);

    // Before we actually search each course we need to check the user's access to the
    // course. If the user doesn't have the appropraite access then we either throw an
    // error if a particular course was requested or we just skip over the course.
    foreach ($courses as $course) {
        $coursecontext = context_course::instance($course->id, MUST_EXIST);
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
            if (!can_access_course($course, $user) && !is_enrolled($coursecontext, $user)) {
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
            if (groups_get_course_groupmode($course) == SEPARATEGROUPS && $course->groupmodeforce
              && !has_capability('moodle/site:accessallgroups', $coursecontext) && !has_capability('moodle/site:accessallgroups', $coursecontext, $user->id)) {
                // If its the guest user to bad... the guest user cannot access groups
                if (!$isloggedin or $isguestuser) {
                    // do not use require_login() here because we might have already used require_login($course)
                    if ($musthaveaccess) {
                        redirect(get_login_url());
                    }
                    continue;
                }
                // Get the groups of the current user
                $mygroups = array_keys(groups_get_all_groups($course->id, $USER->id, $course->defaultgroupingid, 'g.id, g.name'));
                // Get the groups the requested user is a member of
                $usergroups = array_keys(groups_get_all_groups($course->id, $user->id, $course->defaultgroupingid, 'g.id, g.name'));
                // Check whether they are members of the same group. If they are great.
                $intersect = array_intersect($mygroups, $usergroups);
                if (empty($intersect)) {
                    // But they're not... if it was a specific course throw an error otherwise
                    // just skip this course so that it is not searched.
                    if ($musthaveaccess) {
                        print_error("groupnotamember", '', $CFG->wwwroot."/course/view.php?id=$course->id");
                    }
                    continue;
                }
            }
        }
        // Woo hoo we got this far which means the current user can search this
        // this course for the requested user. Although this is only the course accessibility
        // handling that is complete, the forumlv accessibility tests are yet to come.
        $return->courses[$course->id] = $course;
    }
    // No longer beed $courses array - lose it not it may be big
    unset($courses);

    // Make sure that we have some courses to search
    if (empty($return->courses)) {
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
    $forumlvs = forumlv_get_forumlvs_user_posted_in($user, array_keys($return->courses), $discussionsonly);

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
        if (empty($modinfo->instances['forumlv'])) {
            // hmmm, no forumlvs? well at least its easy... skip!
            continue;
        }
        // Iterate
        foreach ($modinfo->get_instances_of('forumlv') as $forumlvid => $cm) {
            if (!$cm->uservisible or !isset($forumlvs[$forumlvid])) {
                continue;
            }
            // Get the forumlv in question
            $forumlv = $forumlvs[$forumlvid];

            // This is needed for functionality later on in the forumlv code. It is converted to an object
            // because the cm_info is readonly from 2.6. This is a dirty hack because some other parts of the
            // code were expecting an writeable object. See {@link forumlv_print_post()}.
            $forumlv->cm = new stdClass();
            foreach ($cm as $key => $value) {
                $forumlv->cm->$key = $value;
            }

            // Check that either the current user can view the forumlv, or that the
            // current user has capabilities over the requested user and the requested
            // user can view the discussion
            if (!has_capability('mod/forumlv:viewdiscussion', $cm->context) && !($hascapsonuser && has_capability('mod/forumlv:viewdiscussion', $cm->context, $user->id))) {
                continue;
            }

            // This will contain forumlv specific where clauses
            $forumlvsearchselect = array();
            if (!$iscurrentuser && !$hascapsonuser) {
                // Make sure we check group access
                if (groups_get_activity_groupmode($cm, $course) == SEPARATEGROUPS and !has_capability('moodle/site:accessallgroups', $cm->context)) {
                    $groups = $modinfo->get_groups($cm->groupingid);
                    $groups[] = -1;
                    list($groupid_sql, $groupid_params) = $DB->get_in_or_equal($groups, SQL_PARAMS_NAMED, 'grps'.$forumlvid.'_');
                    $forumlvsearchparams = array_merge($forumlvsearchparams, $groupid_params);
                    $forumlvsearchselect[] = "d.groupid $groupid_sql";
                }

                // hidden timed discussions
                if (!empty($CFG->forumlv_enabletimedposts) && !has_capability('mod/forumlv:viewhiddentimedposts', $cm->context)) {
                    $forumlvsearchselect[] = "(d.userid = :userid{$forumlvid} OR (d.timestart < :timestart{$forumlvid} AND (d.timeend = 0 OR d.timeend > :timeend{$forumlvid})))";
                    $forumlvsearchparams['userid'.$forumlvid] = $user->id;
                    $forumlvsearchparams['timestart'.$forumlvid] = $now;
                    $forumlvsearchparams['timeend'.$forumlvid] = $now;
                }

                // qanda access
                if ($forumlv->type == 'qanda' && !has_capability('mod/forumlv:viewqandawithoutposting', $cm->context)) {
                    // We need to check whether the user has posted in the qanda forumlv.
                    $discussionspostedin = forumlv_discussions_user_has_posted_in($forumlv->id, $user->id);
                    if (!empty($discussionspostedin)) {
                        $forumlvonlydiscussions = array();  // Holds discussion ids for the discussions the user is allowed to see in this forumlv.
                        foreach ($discussionspostedin as $d) {
                            $forumlvonlydiscussions[] = $d->id;
                        }
                        list($discussionid_sql, $discussionid_params) = $DB->get_in_or_equal($forumlvonlydiscussions, SQL_PARAMS_NAMED, 'qanda'.$forumlvid.'_');
                        $forumlvsearchparams = array_merge($forumlvsearchparams, $discussionid_params);
                        $forumlvsearchselect[] = "(d.id $discussionid_sql OR p.parent = 0)";
                    } else {
                        $forumlvsearchselect[] = "p.parent = 0";
                    }

                }

                if (count($forumlvsearchselect) > 0) {
                    $forumlvsearchwhere[] = "(d.forumlv = :forumlv{$forumlvid} AND ".implode(" AND ", $forumlvsearchselect).")";
                    $forumlvsearchparams['forumlv'.$forumlvid] = $forumlvid;
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
        list($fullidsql, $fullidparams) = $DB->get_in_or_equal($forumlvsearchfullaccess, SQL_PARAMS_NAMED, 'fula');
        $forumlvsearchparams = array_merge($forumlvsearchparams, $fullidparams);
        $forumlvsearchwhere[] = "(d.forumlv $fullidsql)";
    }

    // Prepare SQL to both count and search.
    // We alias user.id to useridx because we forumlv_posts already has a userid field and not aliasing this would break
    // oracle and mssql.
    $userfields = user_picture::fields('u', null, 'useridx');
    $countsql = 'SELECT COUNT(*) ';
    $selectsql = 'SELECT p.*, d.forumlv, d.name AS discussionname, '.$userfields.' ';
    $wheresql = implode(" OR ", $forumlvsearchwhere);

    if ($discussionsonly) {
        if ($wheresql == '') {
            $wheresql = 'p.parent = 0';
        } else {
            $wheresql = 'p.parent = 0 AND ('.$wheresql.')';
        }
    }

    $sql = "FROM {forumlv_posts} p
            JOIN {forumlv_discussions} d ON d.id = p.discussion
            JOIN {user} u ON u.id = p.userid
           WHERE ($wheresql)
             AND p.userid = :userid ";
    $orderby = "ORDER BY p.modified DESC";
    $forumlvsearchparams['userid'] = $user->id;

    // Set the total number posts made by the requested user that the current user can see
    $return->totalcount = $DB->count_records_sql($countsql.$sql, $forumlvsearchparams);
    // Set the collection of posts that has been requested
    $return->posts = $DB->get_records_sql($selectsql.$sql.$orderby, $forumlvsearchparams, $limitfrom, $limitnum);

    // We need to build an array of forumlvs for which posts will be displayed.
    // We do this here to save the caller needing to retrieve them themselves before
    // printing these forumlvs posts. Given we have the forumlvs already there is
    // practically no overhead here.
    foreach ($return->posts as $post) {
        if (!array_key_exists($post->forumlv, $return->forumlvs)) {
            $return->forumlvs[$post->forumlv] = $forumlvs[$post->forumlv];
        }
    }

    return $return;
}

/**
 * Set the per-forumlv maildigest option for the specified user.
 *
 * @param stdClass $forumlv The forumlv to set the option for.
 * @param int $maildigest The maildigest option.
 * @param stdClass $user The user object. This defaults to the global $USER object.
 * @throws invalid_digest_setting thrown if an invalid maildigest option is provided.
 */
function forumlv_set_user_maildigest($forumlv, $maildigest, $user = null) {
    global $DB, $USER;

    if (is_number($forumlv)) {
        $forumlv = $DB->get_record('forumlv', array('id' => $forumlv));
    }

    if ($user === null) {
        $user = $USER;
    }

    $course  = $DB->get_record('course', array('id' => $forumlv->course), '*', MUST_EXIST);
    $cm      = get_coursemodule_from_instance('forumlv', $forumlv->id, $course->id, false, MUST_EXIST);
    $context = context_module::instance($cm->id);

    // User must be allowed to see this forumlv.
    require_capability('mod/forumlv:viewdiscussion', $context, $user->id);

    // Validate the maildigest setting.
    $digestoptions = forumlv_get_user_digest_options($user);

    if (!isset($digestoptions[$maildigest])) {
        throw new moodle_exception('invaliddigestsetting', 'mod_forumlv');
    }

    // Attempt to retrieve any existing forumlv digest record.
    $subscription = $DB->get_record('forumlv_digests', array(
        'userid' => $user->id,
        'forumlv' => $forumlv->id,
    ));

    // Create or Update the existing maildigest setting.
    if ($subscription) {
        if ($maildigest == -1) {
            $DB->delete_records('forumlv_digests', array('forumlv' => $forumlv->id, 'userid' => $user->id));
        } else if ($maildigest !== $subscription->maildigest) {
            // Only update the maildigest setting if it's changed.

            $subscription->maildigest = $maildigest;
            $DB->update_record('forumlv_digests', $subscription);
        }
    } else {
        if ($maildigest != -1) {
            // Only insert the maildigest setting if it's non-default.

            $subscription = new stdClass();
            $subscription->forumlv = $forumlv->id;
            $subscription->userid = $user->id;
            $subscription->maildigest = $maildigest;
            $subscription->id = $DB->insert_record('forumlv_digests', $subscription);
        }
    }
}

/**
 * Determine the maildigest setting for the specified user against the
 * specified forumlv.
 *
 * @param Array $digests An array of forumlvs and user digest settings.
 * @param stdClass $user The user object containing the id and maildigest default.
 * @param int $forumlvid The ID of the forumlv to check.
 * @return int The calculated maildigest setting for this user and forumlv.
 */
function forumlv_get_user_maildigest_bulk($digests, $user, $forumlvid) {
    if (isset($digests[$forumlvid]) && isset($digests[$forumlvid][$user->id])) {
        $maildigest = $digests[$forumlvid][$user->id];
        if ($maildigest === -1) {
            $maildigest = $user->maildigest;
        }
    } else {
        $maildigest = $user->maildigest;
    }
    return $maildigest;
}

/**
 * Retrieve the list of available user digest options.
 *
 * @param stdClass $user The user object. This defaults to the global $USER object.
 * @return array The mapping of values to digest options.
 */
function forumlv_get_user_digest_options($user = null) {
    global $USER;

    // Revert to the global user object.
    if ($user === null) {
        $user = $USER;
    }

    $digestoptions = array();
    $digestoptions['0']  = get_string('emaildigestoffshort', 'mod_forumlv');
    $digestoptions['1']  = get_string('emaildigestcompleteshort', 'mod_forumlv');
    $digestoptions['2']  = get_string('emaildigestsubjectsshort', 'mod_forumlv');

    // We need to add the default digest option at the end - it relies on
    // the contents of the existing values.
    $digestoptions['-1'] = get_string('emaildigestdefault', 'mod_forumlv',
            $digestoptions[$user->maildigest]);

    // Resort the options to be in a sensible order.
    ksort($digestoptions);

    return $digestoptions;
}

/**
 * Determine the current context if one was not already specified.
 *
 * If a context of type context_module is specified, it is immediately
 * returned and not checked.
 *
 * @param int $forumlvid The ID of the forumlv
 * @param context_module $context The current context.
 * @return context_module The context determined
 */
function forumlv_get_context($forumlvid, $context = null) {
    global $PAGE;

    if (!$context || !($context instanceof context_module)) {
        // Find out forumlv context. First try to take current page context to save on DB query.
        if ($PAGE->cm && $PAGE->cm->modname === 'forumlv' && $PAGE->cm->instance == $forumlvid
                && $PAGE->context->contextlevel == CONTEXT_MODULE && $PAGE->context->instanceid == $PAGE->cm->id) {
            $context = $PAGE->context;
        } else {
            $cm = get_coursemodule_from_instance('forumlv', $forumlvid);
            $context = \context_module::instance($cm->id);
        }
    }

    return $context;
}

/**
 * Mark the activity completed (if required) and trigger the course_module_viewed event.
 *
 * @param  stdClass $forumlv   forumlv object
 * @param  stdClass $course  course object
 * @param  stdClass $cm      course module object
 * @param  stdClass $context context object
 * @since Moodle 2.9
 */
function forumlv_view($forumlv, $course, $cm, $context) {

    // Completion.
    $completion = new completion_info($course);
    $completion->set_module_viewed($cm);

    // Trigger course_module_viewed event.

    $params = array(
        'context' => $context,
        'objectid' => $forumlv->id
    );

    $event = \mod_forumlv\event\course_module_viewed::create($params);
    $event->add_record_snapshot('course_modules', $cm);
    $event->add_record_snapshot('course', $course);
    $event->add_record_snapshot('forumlv', $forumlv);
    $event->trigger();
}

/**
 * Trigger the discussion viewed event
 *
 * @param  stdClass $modcontext module context object
 * @param  stdClass $forumlv      forumlv object
 * @param  stdClass $discussion discussion object
 * @since Moodle 2.9
 */
function forumlv_discussion_view($modcontext, $forumlv, $discussion) {
    $params = array(
        'context' => $modcontext,
        'objectid' => $discussion->id,
    );

    $event = \mod_forumlv\event\discussion_viewed::create($params);
    $event->add_record_snapshot('forumlv_discussions', $discussion);
    $event->add_record_snapshot('forumlv', $forumlv);
    $event->trigger();
}

/**
 * Set the discussion to pinned and trigger the discussion pinned event
 *
 * @param  stdClass $modcontext module context object
 * @param  stdClass $forumlv      forumlv object
 * @param  stdClass $discussion discussion object
 * @since Moodle 3.1
 */
function forumlv_discussion_pin($modcontext, $forumlv, $discussion) {
    global $DB;

    $DB->set_field('forumlv_discussions', 'pinned', FORUMLV_DISCUSSION_PINNED, array('id' => $discussion->id));

    $params = array(
        'context' => $modcontext,
        'objectid' => $discussion->id,
        'other' => array('forumlvid' => $forumlv->id)
    );

    $event = \mod_forumlv\event\discussion_pinned::create($params);
    $event->add_record_snapshot('forumlv_discussions', $discussion);
    $event->trigger();
}

/**
 * Set discussion to unpinned and trigger the discussion unpin event
 *
 * @param  stdClass $modcontext module context object
 * @param  stdClass $forumlv      forumlv object
 * @param  stdClass $discussion discussion object
 * @since Moodle 3.1
 */
function forumlv_discussion_unpin($modcontext, $forumlv, $discussion) {
    global $DB;

    $DB->set_field('forumlv_discussions', 'pinned', FORUMLV_DISCUSSION_UNPINNED, array('id' => $discussion->id));

    $params = array(
        'context' => $modcontext,
        'objectid' => $discussion->id,
        'other' => array('forumlvid' => $forumlv->id)
    );

    $event = \mod_forumlv\event\discussion_unpinned::create($params);
    $event->add_record_snapshot('forumlv_discussions', $discussion);
    $event->trigger();
}

/**
 * Add nodes to myprofile page.
 *
 * @param \core_user\output\myprofile\tree $tree Tree object
 * @param stdClass $user user object
 * @param bool $iscurrentuser
 * @param stdClass $course Course object
 *
 * @return bool
 */
function mod_forumlv_myprofile_navigation(core_user\output\myprofile\tree $tree, $user, $iscurrentuser, $course) {
    if (isguestuser($user)) {
        // The guest user cannot post, so it is not possible to view any posts.
        // May as well just bail aggressively here.
        return false;
    }
    $postsurl = new moodle_url('/mod/forumlv/user.php', array('id' => $user->id));
    if (!empty($course)) {
        $postsurl->param('course', $course->id);
    }
    $string = get_string('forumlvposts', 'mod_forumlv');
    $node = new core_user\output\myprofile\node('miscellaneous', 'forumlvposts', $string, null, $postsurl);
    $tree->add_node($node);

    $discussionssurl = new moodle_url('/mod/forumlv/user.php', array('id' => $user->id, 'mode' => 'discussions'));
    if (!empty($course)) {
        $discussionssurl->param('course', $course->id);
    }
    $string = get_string('myprofileotherdis', 'mod_forumlv');
    $node = new core_user\output\myprofile\node('miscellaneous', 'forumlvdiscussions', $string, null,
        $discussionssurl);
    $tree->add_node($node);

    return true;
}
