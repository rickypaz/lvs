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
 * Edit and save a new post to a discussion
 *
 * @package mod-forumlv
 * @copyright 1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once('lib.php');
require_once($CFG->libdir.'/completionlib.php');

$reply   = optional_param('reply', 0, PARAM_INT);
$forumlv   = optional_param('forumlv', 0, PARAM_INT);
$edit    = optional_param('edit', 0, PARAM_INT);
$delete  = optional_param('delete', 0, PARAM_INT);
$prune   = optional_param('prune', 0, PARAM_INT);
$name    = optional_param('name', '', PARAM_CLEAN);
$confirm = optional_param('confirm', 0, PARAM_INT);
$groupid = optional_param('groupid', null, PARAM_INT);

$PAGE->set_url('/mod/forumlv/post.php', array(
        'reply' => $reply,
        'forumlv' => $forumlv,
        'edit'  => $edit,
        'delete'=> $delete,
        'prune' => $prune,
        'name'  => $name,
        'confirm'=>$confirm,
        'groupid'=>$groupid,
        ));
//these page_params will be passed as hidden variables later in the form.
$page_params = array('reply'=>$reply, 'forumlv'=>$forumlv, 'edit'=>$edit);

$sitecontext = context_system::instance();

if (!isloggedin() or isguestuser()) {

    if (!isloggedin() and !get_referer()) {
        // No referer+not logged in - probably coming in via email  See MDL-9052
        require_login();
    }

    if (!empty($forumlv)) {      // User is starting a new discussion in a forumlv
        if (! $forumlv = $DB->get_record('forumlv', array('id' => $forumlv))) {
            print_error('invalidforumlvid', 'forumlv');
        }
    } else if (!empty($reply)) {      // User is writing a new reply
        if (! $parent = forumlv_get_post_full($reply)) {
            print_error('invalidparentpostid', 'forumlv');
        }
        if (! $discussion = $DB->get_record('forumlv_discussions', array('id' => $parent->discussion))) {
            print_error('notpartofdiscussion', 'forumlv');
        }
        if (! $forumlv = $DB->get_record('forumlv', array('id' => $discussion->forumlv))) {
            print_error('invalidforumlvid');
        }
    }
    if (! $course = $DB->get_record('course', array('id' => $forumlv->course))) {
        print_error('invalidcourseid');
    }

    if (!$cm = get_coursemodule_from_instance('forumlv', $forumlv->id, $course->id)) { // For the logs
        print_error('invalidcoursemodule');
    } else {
        $modcontext = context_module::instance($cm->id);
    }

    $PAGE->set_cm($cm, $course, $forumlv);
    $PAGE->set_context($modcontext);
    $PAGE->set_title($course->shortname);
    $PAGE->set_heading($course->fullname);

    echo $OUTPUT->header();
    echo $OUTPUT->confirm(get_string('noguestpost', 'forumlv').'<br /><br />'.get_string('liketologin'), get_login_url(), get_referer(false));
    echo $OUTPUT->footer();
    exit;
}

require_login(0, false);   // Script is useless unless they're logged in

if (!empty($forumlv)) {      // User is starting a new discussion in a forumlv
    if (! $forumlv = $DB->get_record("forumlv", array("id" => $forumlv))) {
        print_error('invalidforumlvid', 'forumlv');
    }
    if (! $course = $DB->get_record("course", array("id" => $forumlv->course))) {
        print_error('invalidcourseid');
    }
    if (! $cm = get_coursemodule_from_instance("forumlv", $forumlv->id, $course->id)) {
        print_error("invalidcoursemodule");
    }

    $coursecontext = context_course::instance($course->id);

    if (! forumlv_user_can_post_discussion($forumlv, $groupid, -1, $cm)) {
        if (!isguestuser()) {
            if (!is_enrolled($coursecontext)) {
                if (enrol_selfenrol_available($course->id)) {
                    $SESSION->wantsurl = qualified_me();
                    $SESSION->enrolcancel = $_SERVER['HTTP_REFERER'];
                    redirect($CFG->wwwroot.'/enrol/index.php?id='.$course->id, get_string('youneedtoenrol'));
                }
            }
        }
        print_error('nopostforumlv', 'forumlv');
    }

    if (!$cm->visible and !has_capability('moodle/course:viewhiddenactivities', $coursecontext)) {
        print_error("activityiscurrentlyhidden");
    }

    if (isset($_SERVER["HTTP_REFERER"])) {
        $SESSION->fromurl = $_SERVER["HTTP_REFERER"];
    } else {
        $SESSION->fromurl = '';
    }


    // Load up the $post variable.

    $post = new stdClass();
    $post->course        = $course->id;
    $post->forumlv         = $forumlv->id;
    $post->discussion    = 0;           // ie discussion # not defined yet
    $post->parent        = 0;
    $post->subject       = '';
    $post->userid        = $USER->id;
    $post->message       = '';
    $post->messageformat = editors_get_preferred_format();
    $post->messagetrust  = 0;

    if (isset($groupid)) {
        $post->groupid = $groupid;
    } else {
        $post->groupid = groups_get_activity_group($cm);
    }

    forumlv_set_return();

} else if (!empty($reply)) {      // User is writing a new reply

    if (! $parent = forumlv_get_post_full($reply)) {
        print_error('invalidparentpostid', 'forumlv');
    }
    if (! $discussion = $DB->get_record("forumlv_discussions", array("id" => $parent->discussion))) {
        print_error('notpartofdiscussion', 'forumlv');
    }
    if (! $forumlv = $DB->get_record("forumlv", array("id" => $discussion->forumlv))) {
        print_error('invalidforumlvid', 'forumlv');
    }
    if (! $course = $DB->get_record("course", array("id" => $discussion->course))) {
        print_error('invalidcourseid');
    }
    if (! $cm = get_coursemodule_from_instance("forumlv", $forumlv->id, $course->id)) {
        print_error('invalidcoursemodule');
    }

    // Ensure lang, theme, etc. is set up properly. MDL-6926
    $PAGE->set_cm($cm, $course, $forumlv);

    $coursecontext = context_course::instance($course->id);
    $modcontext    = context_module::instance($cm->id);

    if (! forumlv_user_can_post($forumlv, $discussion, $USER, $cm, $course, $modcontext)) {
        if (!isguestuser()) {
            if (!is_enrolled($coursecontext)) {  // User is a guest here!
                $SESSION->wantsurl = qualified_me();
                $SESSION->enrolcancel = $_SERVER['HTTP_REFERER'];
                redirect($CFG->wwwroot.'/enrol/index.php?id='.$course->id, get_string('youneedtoenrol'));
            }
        }
        print_error('nopostforumlv', 'forumlv');
    }

    // Make sure user can post here
    if (isset($cm->groupmode) && empty($course->groupmodeforce)) {
        $groupmode =  $cm->groupmode;
    } else {
        $groupmode = $course->groupmode;
    }
    if ($groupmode == SEPARATEGROUPS and !has_capability('moodle/site:accessallgroups', $modcontext)) {
        if ($discussion->groupid == -1) {
            print_error('nopostforumlv', 'forumlv');
        } else {
            if (!groups_is_member($discussion->groupid)) {
                print_error('nopostforumlv', 'forumlv');
            }
        }
    }

    if (!$cm->visible and !has_capability('moodle/course:viewhiddenactivities', $coursecontext)) {
        print_error("activityiscurrentlyhidden");
    }

    // Load up the $post variable.

    $post = new stdClass();
    $post->course      = $course->id;
    $post->forumlv       = $forumlv->id;
    $post->discussion  = $parent->discussion;
    $post->parent      = $parent->id;
    $post->subject     = $parent->subject;
    $post->userid      = $USER->id;
    $post->message     = '';

    $post->groupid = ($discussion->groupid == -1) ? 0 : $discussion->groupid;

    $strre = get_string('re', 'forumlv');
    if (!(substr($post->subject, 0, strlen($strre)) == $strre)) {
        $post->subject = $strre.' '.$post->subject;
    }

    unset($SESSION->fromdiscussion);

} else if (!empty($edit)) {  // User is editing their own post

    if (! $post = forumlv_get_post_full($edit)) {
        print_error('invalidpostid', 'forumlv');
    }
    if ($post->parent) {
        if (! $parent = forumlv_get_post_full($post->parent)) {
            print_error('invalidparentpostid', 'forumlv');
        }
    }

    if (! $discussion = $DB->get_record("forumlv_discussions", array("id" => $post->discussion))) {
        print_error('notpartofdiscussion', 'forumlv');
    }
    if (! $forumlv = $DB->get_record("forumlv", array("id" => $discussion->forumlv))) {
        print_error('invalidforumlvid', 'forumlv');
    }
    if (! $course = $DB->get_record("course", array("id" => $discussion->course))) {
        print_error('invalidcourseid');
    }
    if (!$cm = get_coursemodule_from_instance("forumlv", $forumlv->id, $course->id)) {
        print_error('invalidcoursemodule');
    } else {
        $modcontext = context_module::instance($cm->id);
    }

    $PAGE->set_cm($cm, $course, $forumlv);

    if (!($forumlv->type == 'news' && !$post->parent && $discussion->timestart > time())) {
        if (((time() - $post->created) > $CFG->maxeditingtime) and
                    !has_capability('mod/forumlv:editanypost', $modcontext)) {
            print_error('maxtimehaspassed', 'forumlv', '', format_time($CFG->maxeditingtime));
        }
    }
    if (($post->userid <> $USER->id) and
                !has_capability('mod/forumlv:editanypost', $modcontext)) {
        print_error('cannoteditposts', 'forumlv');
    }


    // Load up the $post variable.
    $post->edit   = $edit;
    $post->course = $course->id;
    $post->forumlv  = $forumlv->id;
    $post->groupid = ($discussion->groupid == -1) ? 0 : $discussion->groupid;

    $post = trusttext_pre_edit($post, 'message', $modcontext);

    unset($SESSION->fromdiscussion);


}else if (!empty($delete)) {  // User is deleting a post

    if (! $post = forumlv_get_post_full($delete)) {
        print_error('invalidpostid', 'forumlv');
    }
    if (! $discussion = $DB->get_record("forumlv_discussions", array("id" => $post->discussion))) {
        print_error('notpartofdiscussion', 'forumlv');
    }
    if (! $forumlv = $DB->get_record("forumlv", array("id" => $discussion->forumlv))) {
        print_error('invalidforumlvid', 'forumlv');
    }
    if (!$cm = get_coursemodule_from_instance("forumlv", $forumlv->id, $forumlv->course)) {
        print_error('invalidcoursemodule');
    }
    if (!$course = $DB->get_record('course', array('id' => $forumlv->course))) {
        print_error('invalidcourseid');
    }

    require_login($course, false, $cm);
    $modcontext = context_module::instance($cm->id);

    if ( !(($post->userid == $USER->id && has_capability('mod/forumlv:deleteownpost', $modcontext))
                || has_capability('mod/forumlv:deleteanypost', $modcontext)) ) {
        print_error('cannotdeletepost', 'forumlv');
    }


    $replycount = forumlv_count_replies($post);

    if (!empty($confirm) && confirm_sesskey()) {    // User has confirmed the delete
        //check user capability to delete post.
        $timepassed = time() - $post->created;
        if (($timepassed > $CFG->maxeditingtime) && !has_capability('mod/forumlv:deleteanypost', $modcontext)) {
            print_error("cannotdeletepost", "forumlv",
                      forumlv_go_back_to("discuss.php?d=$post->discussion"));
        }

        if ($post->totalscore) {
            notice(get_string('couldnotdeleteratings', 'rating'),
                    forumlv_go_back_to("discuss.php?d=$post->discussion"));

        } else if ($replycount && !has_capability('mod/forumlv:deleteanypost', $modcontext)) {
            print_error("couldnotdeletereplies", "forumlv",
                    forumlv_go_back_to("discuss.php?d=$post->discussion"));

        } else {
            if (! $post->parent) {  // post is a discussion topic as well, so delete discussion
                if ($forumlv->type == 'single') {
                    notice("Sorry, but you are not allowed to delete that discussion!",
                            forumlv_go_back_to("discuss.php?d=$post->discussion"));
                }
                forumlv_delete_discussion($discussion, false, $course, $cm, $forumlv);

                add_to_log($discussion->course, "forumlv", "delete discussion",
                           "view.php?id=$cm->id", "$forumlv->id", $cm->id);

                redirect("view.php?f=$discussion->forumlv");

            } else if (forumlv_delete_post($post, has_capability('mod/forumlv:deleteanypost', $modcontext),
                $course, $cm, $forumlv)) {

                if ($forumlv->type == 'single') {
                    // Single discussion forumlvs are an exception. We show
                    // the forumlv itself since it only has one discussion
                    // thread.
                    $discussionurl = "view.php?f=$forumlv->id";
                } else {
                    $discussionurl = "discuss.php?d=$post->discussion";
                }

                add_to_log($discussion->course, "forumlv", "delete post", $discussionurl, "$post->id", $cm->id);

                redirect(forumlv_go_back_to($discussionurl));
            } else {
                print_error('errorwhiledelete', 'forumlv');
            }
        }


    } else { // User just asked to delete something

        forumlv_set_return();
        $PAGE->navbar->add(get_string('delete', 'forumlv'));
        $PAGE->set_title($course->shortname);
        $PAGE->set_heading($course->fullname);

        if ($replycount) {
            if (!has_capability('mod/forumlv:deleteanypost', $modcontext)) {
                print_error("couldnotdeletereplies", "forumlv",
                      forumlv_go_back_to("discuss.php?d=$post->discussion"));
            }
            echo $OUTPUT->header();
            echo $OUTPUT->confirm(get_string("deletesureplural", "forumlv", $replycount+1),
                         "post.php?delete=$delete&confirm=$delete",
                         $CFG->wwwroot.'/mod/forumlv/discuss.php?d='.$post->discussion.'#p'.$post->id);

            forumlv_print_post($post, $discussion, $forumlv, $cm, $course, false, false, false);

            if (empty($post->edit)) {
                $forumlvtracked = forumlv_tp_is_tracked($forumlv);
                $posts = forumlv_get_all_discussion_posts($discussion->id, "created ASC", $forumlvtracked);
                forumlv_print_posts_nested($course, $cm, $forumlv, $discussion, $post, false, false, $forumlvtracked, $posts);
            }
        } else {
            echo $OUTPUT->header();
            echo $OUTPUT->confirm(get_string("deletesure", "forumlv", $replycount),
                         "post.php?delete=$delete&confirm=$delete",
                         $CFG->wwwroot.'/mod/forumlv/discuss.php?d='.$post->discussion.'#p'.$post->id);
            forumlv_print_post($post, $discussion, $forumlv, $cm, $course, false, false, false);
        }

    }
    echo $OUTPUT->footer();
    die;


} else if (!empty($prune)) {  // Pruning

    if (!$post = forumlv_get_post_full($prune)) {
        print_error('invalidpostid', 'forumlv');
    }
    if (!$discussion = $DB->get_record("forumlv_discussions", array("id" => $post->discussion))) {
        print_error('notpartofdiscussion', 'forumlv');
    }
    if (!$forumlv = $DB->get_record("forumlv", array("id" => $discussion->forumlv))) {
        print_error('invalidforumlvid', 'forumlv');
    }
    if ($forumlv->type == 'single') {
        print_error('cannotsplit', 'forumlv');
    }
    if (!$post->parent) {
        print_error('alreadyfirstpost', 'forumlv');
    }
    if (!$cm = get_coursemodule_from_instance("forumlv", $forumlv->id, $forumlv->course)) { // For the logs
        print_error('invalidcoursemodule');
    } else {
        $modcontext = context_module::instance($cm->id);
    }
    if (!has_capability('mod/forumlv:splitdiscussions', $modcontext)) {
        print_error('cannotsplit', 'forumlv');
    }

    if (!empty($name) && confirm_sesskey()) {    // User has confirmed the prune

        $newdiscussion = new stdClass();
        $newdiscussion->course       = $discussion->course;
        $newdiscussion->forumlv        = $discussion->forumlv;
        $newdiscussion->name         = $name;
        $newdiscussion->firstpost    = $post->id;
        $newdiscussion->userid       = $discussion->userid;
        $newdiscussion->groupid      = $discussion->groupid;
        $newdiscussion->assessed     = $discussion->assessed;
        $newdiscussion->usermodified = $post->userid;
        $newdiscussion->timestart    = $discussion->timestart;
        $newdiscussion->timeend      = $discussion->timeend;

        $newid = $DB->insert_record('forumlv_discussions', $newdiscussion);

        $newpost = new stdClass();
        $newpost->id      = $post->id;
        $newpost->parent  = 0;
        $newpost->subject = $name;

        $DB->update_record("forumlv_posts", $newpost);

        forumlv_change_discussionid($post->id, $newid);

        // update last post in each discussion
        forumlv_discussion_update_last_post($discussion->id);
        forumlv_discussion_update_last_post($newid);

        add_to_log($discussion->course, "forumlv", "prune post",
                       "discuss.php?d=$newid", "$post->id", $cm->id);

        redirect(forumlv_go_back_to("discuss.php?d=$newid"));

    } else { // User just asked to prune something

        $course = $DB->get_record('course', array('id' => $forumlv->course));

        $PAGE->set_cm($cm);
        $PAGE->set_context($modcontext);
        $PAGE->navbar->add(format_string($post->subject, true), new moodle_url('/mod/forumlv/discuss.php', array('d'=>$discussion->id)));
        $PAGE->navbar->add(get_string("prune", "forumlv"));
        $PAGE->set_title(format_string($discussion->name).": ".format_string($post->subject));
        $PAGE->set_heading($course->fullname);
        echo $OUTPUT->header();
        echo $OUTPUT->heading(get_string('pruneheading', 'forumlv'));
        echo '<center>';

        include('prune.html');

        forumlv_print_post($post, $discussion, $forumlv, $cm, $course, false, false, false);
        echo '</center>';
    }
    echo $OUTPUT->footer();
    die;
} else {
    print_error('unknowaction');

}

if (!isset($coursecontext)) {
    // Has not yet been set by post.php.
    $coursecontext = context_course::instance($forumlv->course);
}


// from now on user must be logged on properly

if (!$cm = get_coursemodule_from_instance('forumlv', $forumlv->id, $course->id)) { // For the logs
    print_error('invalidcoursemodule');
}
$modcontext = context_module::instance($cm->id);
require_login($course, false, $cm);

if (isguestuser()) {
    // just in case
    print_error('noguest');
}

if (!isset($forumlv->maxattachments)) {  // TODO - delete this once we add a field to the forumlv table
    $forumlv->maxattachments = 3;
}

require_once('post_form.php');

$thresholdwarning = forumlv_check_throttling($forumlv, $cm);
$mform_post = new mod_forumlv_post_form('post.php', array('course' => $course,
                                                        'cm' => $cm,
                                                        'coursecontext' => $coursecontext,
                                                        'modcontext' => $modcontext,
                                                        'forumlv' => $forumlv,
                                                        'post' => $post,
                                                        'thresholdwarning' => $thresholdwarning,
                                                        'edit' => $edit), 'post', '', array('id' => 'mformforumlv'));

$draftitemid = file_get_submitted_draft_itemid('attachments');
file_prepare_draft_area($draftitemid, $modcontext->id, 'mod_forumlv', 'attachment', empty($post->id)?null:$post->id, mod_forumlv_post_form::attachment_options($forumlv));

//load data into form NOW!

if ($USER->id != $post->userid) {   // Not the original author, so add a message to the end
    $data = new stdClass();
    $data->date = userdate($post->modified);
    if ($post->messageformat == FORMAT_HTML) {
        $data->name = '<a href="'.$CFG->wwwroot.'/user/view.php?id='.$USER->id.'&course='.$post->course.'">'.
                       fullname($USER).'</a>';
        $post->message .= '<p><span class="edited">('.get_string('editedby', 'forumlv', $data).')</span></p>';
    } else {
        $data->name = fullname($USER);
        $post->message .= "\n\n(".get_string('editedby', 'forumlv', $data).')';
    }
    unset($data);
}

$formheading = '';
if (!empty($parent)) {
    $heading = get_string("yourreply", "forumlv");
    $formheading = get_string('reply', 'forumlv');
} else {
    if ($forumlv->type == 'qanda') {
        $heading = get_string('yournewquestion', 'forumlv');
    } else {
        $heading = get_string('yournewtopic', 'forumlv');
    }
}

if (forumlv_is_subscribed($USER->id, $forumlv->id)) {
    $subscribe = true;

} else if (forumlv_user_has_posted($forumlv->id, 0, $USER->id)) {
    $subscribe = false;

} else {
    // user not posted yet - use subscription default specified in profile
    $subscribe = !empty($USER->autosubscribe);
}

$draftid_editor = file_get_submitted_draft_itemid('message');
$currenttext = file_prepare_draft_area($draftid_editor, $modcontext->id, 'mod_forumlv', 'post', empty($post->id) ? null : $post->id, mod_forumlv_post_form::editor_options(), $post->message);
$mform_post->set_data(array(        'attachments'=>$draftitemid,
                                    'general'=>$heading,
                                    'subject'=>$post->subject,
                                    'message'=>array(
                                        'text'=>$currenttext,
                                        'format'=>empty($post->messageformat) ? editors_get_preferred_format() : $post->messageformat,
                                        'itemid'=>$draftid_editor
                                    ),
                                    'subscribe'=>$subscribe?1:0,
                                    'mailnow'=>!empty($post->mailnow),
                                    'userid'=>$post->userid,
                                    'parent'=>$post->parent,
                                    'discussion'=>$post->discussion,
                                    'course'=>$course->id) +
                                    $page_params +

                            (isset($post->format)?array(
                                    'format'=>$post->format):
                                array())+

                            (isset($discussion->timestart)?array(
                                    'timestart'=>$discussion->timestart):
                                array())+

                            (isset($discussion->timeend)?array(
                                    'timeend'=>$discussion->timeend):
                                array())+

                            (isset($post->groupid)?array(
                                    'groupid'=>$post->groupid):
                                array())+

                            (isset($discussion->id)?
                                    array('discussion'=>$discussion->id):
                                    array()));

if ($fromform = $mform_post->get_data()) {

    if (empty($SESSION->fromurl)) {
        $errordestination = "$CFG->wwwroot/mod/forumlv/view.php?f=$forumlv->id";
    } else {
        $errordestination = $SESSION->fromurl;
    }

    $fromform->itemid        = $fromform->message['itemid'];
    $fromform->messageformat = $fromform->message['format'];
    $fromform->message       = $fromform->message['text'];
    // WARNING: the $fromform->message array has been overwritten, do not use it anymore!
    $fromform->messagetrust  = trusttext_trusted($modcontext);

    $contextcheck = isset($fromform->groupinfo) && has_capability('mod/forumlv:movediscussions', $modcontext);

    if ($fromform->edit) {           // Updating a post
        unset($fromform->groupid);
        $fromform->id = $fromform->edit;
        $message = '';

        //fix for bug #4314
        if (!$realpost = $DB->get_record('forumlv_posts', array('id' => $fromform->id))) {
            $realpost = new stdClass();
            $realpost->userid = -1;
        }


        // if user has edit any post capability
        // or has either startnewdiscussion or reply capability and is editting own post
        // then he can proceed
        // MDL-7066
        if ( !(($realpost->userid == $USER->id && (has_capability('mod/forumlv:replypost', $modcontext)
                            || has_capability('mod/forumlv:startdiscussion', $modcontext))) ||
                            has_capability('mod/forumlv:editanypost', $modcontext)) ) {
            print_error('cannotupdatepost', 'forumlv');
        }

        // If the user has access to all groups and they are changing the group, then update the post.
        if ($contextcheck) {
            if (empty($fromform->groupinfo)) {
                $fromform->groupinfo = -1;
            }
            $DB->set_field('forumlv_discussions' ,'groupid' , $fromform->groupinfo, array('firstpost' => $fromform->id));
        }

        $updatepost = $fromform; //realpost
        $updatepost->forumlv = $forumlv->id;
        if (!forumlv_update_post($updatepost, $mform_post, $message)) {
            print_error("couldnotupdate", "forumlv", $errordestination);
        }

        // MDL-11818
        if (($forumlv->type == 'single') && ($updatepost->parent == '0')){ // updating first post of single discussion type -> updating forumlv intro
            $forumlv->intro = $updatepost->message;
            $forumlv->timemodified = time();
            $DB->update_record("forumlv", $forumlv);
        }

        $timemessage = 2;
        if (!empty($message)) { // if we're printing stuff about the file upload
            $timemessage = 4;
        }

        if ($realpost->userid == $USER->id) {
            $message .= '<br />'.get_string("postupdated", "forumlv");
        } else {
            $realuser = $DB->get_record('user', array('id' => $realpost->userid));
            $message .= '<br />'.get_string("editedpostupdated", "forumlv", fullname($realuser));
        }

        if ($subscribemessage = forumlv_post_subscription($fromform, $forumlv)) {
            $timemessage = 4;
        }
        if ($forumlv->type == 'single') {
            // Single discussion forumlvs are an exception. We show
            // the forumlv itself since it only has one discussion
            // thread.
            $discussionurl = "view.php?f=$forumlv->id";
        } else {
            $discussionurl = "discuss.php?d=$discussion->id#p$fromform->id";
        }
        add_to_log($course->id, "forumlv", "update post",
                "$discussionurl&amp;parent=$fromform->id", "$fromform->id", $cm->id);

        redirect(forumlv_go_back_to("$discussionurl"), $message.$subscribemessage, $timemessage);

        exit;


    } else if ($fromform->discussion) { // Adding a new post to an existing discussion
        // Before we add this we must check that the user will not exceed the blocking threshold.
        forumlv_check_blocking_threshold($thresholdwarning);

        unset($fromform->groupid);
        $message = '';
        $addpost = $fromform;
        $addpost->forumlv=$forumlv->id;
        if ($fromform->id = forumlv_add_new_post($addpost, $mform_post, $message)) {

            $timemessage = 2;
            if (!empty($message)) { // if we're printing stuff about the file upload
                $timemessage = 4;
            }

            if ($subscribemessage = forumlv_post_subscription($fromform, $forumlv)) {
                $timemessage = 4;
            }

            if (!empty($fromform->mailnow)) {
                $message .= get_string("postmailnow", "forumlv");
                $timemessage = 4;
            } else {
                $message .= '<p>'.get_string("postaddedsuccess", "forumlv") . '</p>';
                $message .= '<p>'.get_string("postaddedtimeleft", "forumlv", format_time($CFG->maxeditingtime)) . '</p>';
            }

            if ($forumlv->type == 'single') {
                // Single discussion forumlvs are an exception. We show
                // the forumlv itself since it only has one discussion
                // thread.
                $discussionurl = "view.php?f=$forumlv->id";
            } else {
                $discussionurl = "discuss.php?d=$discussion->id";
            }
            add_to_log($course->id, "forumlv", "add post",
                      "$discussionurl&amp;parent=$fromform->id", "$fromform->id", $cm->id);

            // Update completion state
            $completion=new completion_info($course);
            if($completion->is_enabled($cm) &&
                ($forumlv->completionreplies || $forumlv->completionposts)) {
                $completion->update_state($cm,COMPLETION_COMPLETE);
            }

            redirect(forumlv_go_back_to("$discussionurl#p$fromform->id"), $message.$subscribemessage, $timemessage);

        } else {
            print_error("couldnotadd", "forumlv", $errordestination);
        }
        exit;

    } else { // Adding a new discussion.
        // Before we add this we must check that the user will not exceed the blocking threshold.
        forumlv_check_blocking_threshold($thresholdwarning);

        if (!forumlv_user_can_post_discussion($forumlv, $fromform->groupid, -1, $cm, $modcontext)) {
            print_error('cannotcreatediscussion', 'forumlv');
        }
        // If the user has access all groups capability let them choose the group.
        if ($contextcheck) {
            $fromform->groupid = $fromform->groupinfo;
        }
        if (empty($fromform->groupid)) {
            $fromform->groupid = -1;
        }

        $fromform->mailnow = empty($fromform->mailnow) ? 0 : 1;

        $discussion = $fromform;
        $discussion->name    = $fromform->subject;

        $newstopic = false;
        if ($forumlv->type == 'news' && !$fromform->parent) {
            $newstopic = true;
        }
        $discussion->timestart = $fromform->timestart;
        $discussion->timeend = $fromform->timeend;

        $message = '';
        if ($discussion->id = forumlv_add_discussion($discussion, $mform_post, $message)) {

            add_to_log($course->id, "forumlv", "add discussion",
                    "discuss.php?d=$discussion->id", "$discussion->id", $cm->id);

            $timemessage = 2;
            if (!empty($message)) { // if we're printing stuff about the file upload
                $timemessage = 4;
            }

            if ($fromform->mailnow) {
                $message .= get_string("postmailnow", "forumlv");
                $timemessage = 4;
            } else {
                $message .= '<p>'.get_string("postaddedsuccess", "forumlv") . '</p>';
                $message .= '<p>'.get_string("postaddedtimeleft", "forumlv", format_time($CFG->maxeditingtime)) . '</p>';
            }

            if ($subscribemessage = forumlv_post_subscription($discussion, $forumlv)) {
                $timemessage = 4;
            }

            // Update completion status
            $completion=new completion_info($course);
            if($completion->is_enabled($cm) &&
                ($forumlv->completiondiscussions || $forumlv->completionposts)) {
                $completion->update_state($cm,COMPLETION_COMPLETE);
            }

            redirect(forumlv_go_back_to("view.php?f=$fromform->forumlv"), $message.$subscribemessage, $timemessage);

        } else {
            print_error("couldnotadd", "forumlv", $errordestination);
        }

        exit;
    }
}



// To get here they need to edit a post, and the $post
// variable will be loaded with all the particulars,
// so bring up the form.

// $course, $forumlv are defined.  $discussion is for edit and reply only.

if ($post->discussion) {
    if (! $toppost = $DB->get_record("forumlv_posts", array("discussion" => $post->discussion, "parent" => 0))) {
        print_error('cannotfindparentpost', 'forumlv', '', $post->id);
    }
} else {
    $toppost = new stdClass();
    $toppost->subject = ($forumlv->type == "news") ? get_string("addanewtopic", "forumlv") :
                                                   get_string("addanewdiscussion", "forumlv");
}

if (empty($post->edit)) {
    $post->edit = '';
}

if (empty($discussion->name)) {
    if (empty($discussion)) {
        $discussion = new stdClass();
    }
    $discussion->name = $forumlv->name;
}
if ($forumlv->type == 'single') {
    // There is only one discussion thread for this forumlv type. We should
    // not show the discussion name (same as forumlv name in this case) in
    // the breadcrumbs.
    $strdiscussionname = '';
} else {
    // Show the discussion name in the breadcrumbs.
    $strdiscussionname = format_string($discussion->name).':';
}

$forcefocus = empty($reply) ? NULL : 'message';

if (!empty($discussion->id)) {
    $PAGE->navbar->add(format_string($toppost->subject, true), "discuss.php?d=$discussion->id");
}

if ($post->parent) {
    $PAGE->navbar->add(get_string('reply', 'forumlv'));
}

if ($edit) {
    $PAGE->navbar->add(get_string('edit', 'forumlv'));
}

$PAGE->set_title("$course->shortname: $strdiscussionname ".format_string($toppost->subject));
$PAGE->set_heading($course->fullname);

echo $OUTPUT->header();

// checkup
if (!empty($parent) && !forumlv_user_can_see_post($forumlv, $discussion, $post, null, $cm)) {
    print_error('cannotreply', 'forumlv');
}
if (empty($parent) && empty($edit) && !forumlv_user_can_post_discussion($forumlv, $groupid, -1, $cm, $modcontext)) {
    print_error('cannotcreatediscussion', 'forumlv');
}

if ($forumlv->type == 'qanda'
            && !has_capability('mod/forumlv:viewqandawithoutposting', $modcontext)
            && !empty($discussion->id)
            && !forumlv_user_has_posted($forumlv->id, $discussion->id, $USER->id)) {
    echo $OUTPUT->notification(get_string('qandanotify','forumlv'));
}

// If there is a warning message and we are not editing a post we need to handle the warning.
if (!empty($thresholdwarning) && !$edit) {
    // Here we want to throw an exception if they are no longer allowed to post.
    forumlv_check_blocking_threshold($thresholdwarning);
}

if (!empty($parent)) {
    if (!$discussion = $DB->get_record('forumlv_discussions', array('id' => $parent->discussion))) {
        print_error('notpartofdiscussion', 'forumlv');
    }

    forumlv_print_post($parent, $discussion, $forumlv, $cm, $course, false, false, false);
    if (empty($post->edit)) {
        if ($forumlv->type != 'qanda' || forumlv_user_can_see_discussion($forumlv, $discussion, $modcontext)) {
            $forumlvtracked = forumlv_tp_is_tracked($forumlv);
            $posts = forumlv_get_all_discussion_posts($discussion->id, "created ASC", $forumlvtracked);
            forumlv_print_posts_threaded($course, $cm, $forumlv, $discussion, $parent, 0, false, $forumlvtracked, $posts);
        }
    }
} else {
    if (!empty($forumlv->intro)) {
        echo $OUTPUT->box(format_module_intro('forumlv', $forumlv, $cm->id), 'generalbox', 'intro');

        if (!empty($CFG->enableplagiarism)) {
            require_once($CFG->libdir.'/plagiarismlib.php');
            echo plagiarism_print_disclosure($cm->id);
        }
    }
}

if (!empty($formheading)) {
    echo $OUTPUT->heading($formheading, 2, array('class' => 'accesshide'));
}
$mform_post->display();

echo $OUTPUT->footer();

