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
 * Displays a post, and all the posts below it.
 * If no post is given, displays all posts in a discussion
 *
 * @package   mod_forumlv
 * @copyright 1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

$d      = required_param('d', PARAM_INT);                // Discussion ID
$parent = optional_param('parent', 0, PARAM_INT);        // If set, then display this post and all children.
$mode   = optional_param('mode', 0, PARAM_INT);          // If set, changes the layout of the thread
$move   = optional_param('move', 0, PARAM_INT);          // If set, moves this discussion to another forumlv
$mark   = optional_param('mark', '', PARAM_ALPHA);       // Used for tracking read posts if user initiated.
$postid = optional_param('postid', 0, PARAM_INT);        // Used for tracking read posts if user initiated.
$pin    = optional_param('pin', -1, PARAM_INT);          // If set, pin or unpin this discussion.

$url = new moodle_url('/mod/forumlv/discuss.php', array('d'=>$d));
if ($parent !== 0) {
    $url->param('parent', $parent);
}
$PAGE->set_url($url);

$discussion = $DB->get_record('forumlv_discussions', array('id' => $d), '*', MUST_EXIST);
$course = $DB->get_record('course', array('id' => $discussion->course), '*', MUST_EXIST);
$forumlv = $DB->get_record('forumlv', array('id' => $discussion->forumlv), '*', MUST_EXIST);
$cm = get_coursemodule_from_instance('forumlv', $forumlv->id, $course->id, false, MUST_EXIST);

require_course_login($course, true, $cm);

// move this down fix for MDL-6926
require_once($CFG->dirroot.'/mod/forumlv/lib.php');

$modcontext = context_module::instance($cm->id);
require_capability('mod/forumlv:viewdiscussion', $modcontext, NULL, true, 'noviewdiscussionspermission', 'forumlv');

if (!empty($CFG->enablerssfeeds) && !empty($CFG->forumlv_enablerssfeeds) && $forumlv->rsstype && $forumlv->rssarticles) {
    require_once("$CFG->libdir/rsslib.php");

    $rsstitle = format_string($course->shortname, true, array('context' => context_course::instance($course->id))) . ': ' . format_string($forumlv->name);
    rss_add_http_header($modcontext, 'mod_forumlv', $forumlv, $rsstitle);
}

// Move discussion if requested.
if ($move > 0 and confirm_sesskey()) {
    $return = $CFG->wwwroot.'/mod/forumlv/discuss.php?d='.$discussion->id;

    if (!$forumlvto = $DB->get_record('forumlv', array('id' => $move))) {
        print_error('cannotmovetonotexist', 'forumlv', $return);
    }

    require_capability('mod/forumlv:movediscussions', $modcontext);

    if ($forumlv->type == 'single') {
        print_error('cannotmovefromsingleforumlv', 'forumlv', $return);
    }

    if (!$forumlvto = $DB->get_record('forumlv', array('id' => $move))) {
        print_error('cannotmovetonotexist', 'forumlv', $return);
    }

    if ($forumlvto->type == 'single') {
        print_error('cannotmovetosingleforumlv', 'forumlv', $return);
    }

    // Get target forumlv cm and check it is visible to current user.
    $modinfo = get_fast_modinfo($course);
    $forumlvs = $modinfo->get_instances_of('forumlv');
    if (!array_key_exists($forumlvto->id, $forumlvs)) {
        print_error('cannotmovetonotfound', 'forumlv', $return);
    }
    $cmto = $forumlvs[$forumlvto->id];
    if (!$cmto->uservisible) {
        print_error('cannotmovenotvisible', 'forumlv', $return);
    }

    $destinationctx = context_module::instance($cmto->id);
    require_capability('mod/forumlv:startdiscussion', $destinationctx);

    if (!forumlv_move_attachments($discussion, $forumlv->id, $forumlvto->id)) {
        echo $OUTPUT->notification("Errors occurred while moving attachment directories - check your file permissions");
    }
    // For each subscribed user in this forumlv and discussion, copy over per-discussion subscriptions if required.
    $discussiongroup = $discussion->groupid == -1 ? 0 : $discussion->groupid;
    $potentialsubscribers = \mod_forumlv\subscriptions::fetch_subscribed_users(
        $forumlv,
        $discussiongroup,
        $modcontext,
        'u.id',
        true
    );

    // Pre-seed the subscribed_discussion caches.
    // Firstly for the forumlv being moved to.
    \mod_forumlv\subscriptions::fill_subscription_cache($forumlvto->id);
    // And also for the discussion being moved.
    \mod_forumlv\subscriptions::fill_subscription_cache($forumlv->id);
    $subscriptionchanges = array();
    $subscriptiontime = time();
    foreach ($potentialsubscribers as $subuser) {
        $userid = $subuser->id;
        $targetsubscription = \mod_forumlv\subscriptions::is_subscribed($userid, $forumlvto, null, $cmto);
        $discussionsubscribed = \mod_forumlv\subscriptions::is_subscribed($userid, $forumlv, $discussion->id);
        $forumlvsubscribed = \mod_forumlv\subscriptions::is_subscribed($userid, $forumlv);

        if ($forumlvsubscribed && !$discussionsubscribed && $targetsubscription) {
            // The user has opted out of this discussion and the move would cause them to receive notifications again.
            // Ensure they are unsubscribed from the discussion still.
            $subscriptionchanges[$userid] = \mod_forumlv\subscriptions::FORUMLV_DISCUSSION_UNSUBSCRIBED;
        } else if (!$forumlvsubscribed && $discussionsubscribed && !$targetsubscription) {
            // The user has opted into this discussion and would otherwise not receive the subscription after the move.
            // Ensure they are subscribed to the discussion still.
            $subscriptionchanges[$userid] = $subscriptiontime;
        }
    }

    $DB->set_field('forumlv_discussions', 'forumlv', $forumlvto->id, array('id' => $discussion->id));
    $DB->set_field('forumlv_read', 'forumlvid', $forumlvto->id, array('discussionid' => $discussion->id));

    // Delete the existing per-discussion subscriptions and replace them with the newly calculated ones.
    $DB->delete_records('forumlv_discussion_subs', array('discussion' => $discussion->id));
    $newdiscussion = clone $discussion;
    $newdiscussion->forumlv = $forumlvto->id;
    foreach ($subscriptionchanges as $userid => $preference) {
        if ($preference != \mod_forumlv\subscriptions::FORUMLV_DISCUSSION_UNSUBSCRIBED) {
            // Users must have viewdiscussion to a discussion.
            if (has_capability('mod/forumlv:viewdiscussion', $destinationctx, $userid)) {
                \mod_forumlv\subscriptions::subscribe_user_to_discussion($userid, $newdiscussion, $destinationctx);
            }
        } else {
            \mod_forumlv\subscriptions::unsubscribe_user_from_discussion($userid, $newdiscussion, $destinationctx);
        }
    }

    $params = array(
        'context' => $destinationctx,
        'objectid' => $discussion->id,
        'other' => array(
            'fromforumlvid' => $forumlv->id,
            'toforumlvid' => $forumlvto->id,
        )
    );
    $event = \mod_forumlv\event\discussion_moved::create($params);
    $event->add_record_snapshot('forumlv_discussions', $discussion);
    $event->add_record_snapshot('forumlv', $forumlv);
    $event->add_record_snapshot('forumlv', $forumlvto);
    $event->trigger();

    // Delete the RSS files for the 2 forumlvs to force regeneration of the feeds
    require_once($CFG->dirroot.'/mod/forumlv/rsslib.php');
    forumlv_rss_delete_file($forumlv);
    forumlv_rss_delete_file($forumlvto);

    redirect($return.'&move=-1&sesskey='.sesskey());
}
// Pin or unpin discussion if requested.
if ($pin !== -1 && confirm_sesskey()) {
    require_capability('mod/forumlv:pindiscussions', $modcontext);

    $params = array('context' => $modcontext, 'objectid' => $discussion->id, 'other' => array('forumlvid' => $forumlv->id));

    switch ($pin) {
        case FORUMLV_DISCUSSION_PINNED:
            // Pin the discussion and trigger discussion pinned event.
            forumlv_discussion_pin($modcontext, $forumlv, $discussion);
            break;
        case FORUMLV_DISCUSSION_UNPINNED:
            // Unpin the discussion and trigger discussion unpinned event.
            forumlv_discussion_unpin($modcontext, $forumlv, $discussion);
            break;
        default:
            echo $OUTPUT->notification("Invalid value when attempting to pin/unpin discussion");
            break;
    }

    redirect(new moodle_url('/mod/forumlv/discuss.php', array('d' => $discussion->id)));
}

// Trigger discussion viewed event.
forumlv_discussion_view($modcontext, $forumlv, $discussion);

unset($SESSION->fromdiscussion);

if ($mode) {
    set_user_preference('forumlv_displaymode', $mode);
}

$displaymode = get_user_preferences('forumlv_displaymode', $CFG->forumlv_displaymode);

if ($parent) {
    // If flat AND parent, then force nested display this time
    if ($displaymode == FORUMLV_MODE_FLATOLDEST or $displaymode == FORUMLV_MODE_FLATNEWEST) {
        $displaymode = FORUMLV_MODE_NESTED;
    }
} else {
    $parent = $discussion->firstpost;
}

if (! $post = forumlv_get_post_full($parent)) {
    print_error("notexists", 'forumlv', "$CFG->wwwroot/mod/forumlv/view.php?f=$forumlv->id");
}

if (!forumlv_user_can_see_post($forumlv, $discussion, $post, null, $cm)) {
    print_error('noviewdiscussionspermission', 'forumlv', "$CFG->wwwroot/mod/forumlv/view.php?id=$forumlv->id");
}

if ($mark == 'read' or $mark == 'unread') {
    if ($CFG->forumlv_usermarksread && forumlv_tp_can_track_forumlvs($forumlv) && forumlv_tp_is_tracked($forumlv)) {
        if ($mark == 'read') {
            forumlv_tp_add_read_record($USER->id, $postid);
        } else {
            // unread
            forumlv_tp_delete_read_records($USER->id, $postid);
        }
    }
}

$searchform = forumlv_search_form($course);

$forumlvnode = $PAGE->navigation->find($cm->id, navigation_node::TYPE_ACTIVITY);
if (empty($forumlvnode)) {
    $forumlvnode = $PAGE->navbar;
} else {
    $forumlvnode->make_active();
}
$node = $forumlvnode->add(format_string($discussion->name), new moodle_url('/mod/forumlv/discuss.php', array('d'=>$discussion->id)));
$node->display = false;
if ($node && $post->id != $discussion->firstpost) {
    $node->add(format_string($post->subject), $PAGE->url);
}

$PAGE->set_title("$course->shortname: ".format_string($discussion->name));
$PAGE->set_heading($course->fullname);
$PAGE->set_button($searchform);
$renderer = $PAGE->get_renderer('mod_forumlv');

echo $OUTPUT->header();

echo $OUTPUT->heading(format_string($forumlv->name), 2);
echo $OUTPUT->heading(format_string($discussion->name), 3, 'discussionname');

// is_guest should be used here as this also checks whether the user is a guest in the current course.
// Guests and visitors cannot subscribe - only enrolled users.
if ((!is_guest($modcontext, $USER) && isloggedin()) && has_capability('mod/forumlv:viewdiscussion', $modcontext)) {
    // Discussion subscription.
    if (\mod_forumlv\subscriptions::is_subscribable($forumlv)) {
        echo html_writer::div(
            forumlv_get_discussion_subscription_icon($forumlv, $post->discussion, null, true),
            'discussionsubscription'
        );
        echo forumlv_get_discussion_subscription_icon_preloaders();
    }
}


/// Check to see if groups are being used in this forumlv
/// If so, make sure the current person is allowed to see this discussion
/// Also, if we know they should be able to reply, then explicitly set $canreply for performance reasons

$canreply = forumlv_user_can_post($forumlv, $discussion, $USER, $cm, $course, $modcontext);
if (!$canreply and $forumlv->type !== 'news') {
    if (isguestuser() or !isloggedin()) {
        $canreply = true;
    }
    if (!is_enrolled($modcontext) and !is_viewing($modcontext)) {
        // allow guests and not-logged-in to see the link - they are prompted to log in after clicking the link
        // normal users with temporary guest access see this link too, they are asked to enrol instead
        $canreply = enrol_selfenrol_available($course->id);
    }
}

// Output the links to neighbour discussions.
$neighbours = forumlv_get_discussion_neighbours($cm, $discussion, $forumlv);
$neighbourlinks = $renderer->neighbouring_discussion_navigation($neighbours['prev'], $neighbours['next']);
echo $neighbourlinks;

/// Print the controls across the top
echo '<div class="discussioncontrols clearfix"><div class="controlscontainer">';

if (!empty($CFG->enableportfolios) && has_capability('mod/forumlv:exportdiscussion', $modcontext)) {
    require_once($CFG->libdir.'/portfoliolib.php');
    $button = new portfolio_add_button();
    $button->set_callback_options('forumlv_portfolio_caller', array('discussionid' => $discussion->id), 'mod_forumlv');
    $button = $button->to_html(PORTFOLIO_ADD_FULL_FORM, get_string('exportdiscussion', 'mod_forumlv'));
    $buttonextraclass = '';
    if (empty($button)) {
        // no portfolio plugin available.
        $button = '&nbsp;';
        $buttonextraclass = ' noavailable';
    }
    echo html_writer::tag('div', $button, array('class' => 'discussioncontrol exporttoportfolio'.$buttonextraclass));
} else {
    echo html_writer::tag('div', '&nbsp;', array('class'=>'discussioncontrol nullcontrol'));
}

// groups selector not needed here
echo '<div class="discussioncontrol displaymode">';
forumlv_print_mode_form($discussion->id, $displaymode);
echo "</div>";

if ($forumlv->type != 'single'
            && has_capability('mod/forumlv:movediscussions', $modcontext)) {

    echo '<div class="discussioncontrol movediscussion">';
    // Popup menu to move discussions to other forumlvs. The discussion in a
    // single discussion forumlv can't be moved.
    $modinfo = get_fast_modinfo($course);
    if (isset($modinfo->instances['forumlv'])) {
        $forumlvmenu = array();
        // Check forumlv types and eliminate simple discussions.
        $forumlvcheck = $DB->get_records('forumlv', array('course' => $course->id),'', 'id, type');
        foreach ($modinfo->instances['forumlv'] as $forumlvcm) {
            if (!$forumlvcm->uservisible || !has_capability('mod/forumlv:startdiscussion',
                context_module::instance($forumlvcm->id))) {
                continue;
            }
            $section = $forumlvcm->sectionnum;
            $sectionname = get_section_name($course, $section);
            if (empty($forumlvmenu[$section])) {
                $forumlvmenu[$section] = array($sectionname => array());
            }
            $forumlvidcompare = $forumlvcm->instance != $forumlv->id;
            $forumlvtypecheck = $forumlvcheck[$forumlvcm->instance]->type !== 'single';
            if ($forumlvidcompare and $forumlvtypecheck) {
                $url = "/mod/forumlv/discuss.php?d=$discussion->id&move=$forumlvcm->instance&sesskey=".sesskey();
                $forumlvmenu[$section][$sectionname][$url] = format_string($forumlvcm->name);
            }
        }
        if (!empty($forumlvmenu)) {
            echo '<div class="movediscussionoption">';
            $select = new url_select($forumlvmenu, '',
                    array('/mod/forumlv/discuss.php?d=' . $discussion->id => get_string("movethisdiscussionto", "forumlv")),
                    'forumlvmenu', get_string('move'));
            echo $OUTPUT->render($select);
            echo "</div>";
        }
    }
    echo "</div>";
}

if (has_capability('mod/forumlv:pindiscussions', $modcontext)) {
    if ($discussion->pinned == FORUMLV_DISCUSSION_PINNED) {
        $pinlink = FORUMLV_DISCUSSION_UNPINNED;
        $pintext = get_string('discussionunpin', 'forumlv');
    } else {
        $pinlink = FORUMLV_DISCUSSION_PINNED;
        $pintext = get_string('discussionpin', 'forumlv');
    }
    $button = new single_button(new moodle_url('discuss.php', array('pin' => $pinlink, 'd' => $discussion->id)), $pintext, 'post');
    echo html_writer::tag('div', $OUTPUT->render($button), array('class' => 'discussioncontrol pindiscussion'));
}


echo "</div></div>";

if (!empty($forumlv->blockafter) && !empty($forumlv->blockperiod)) {
    $a = new stdClass();
    $a->blockafter  = $forumlv->blockafter;
    $a->blockperiod = get_string('secondstotime'.$forumlv->blockperiod);
    echo $OUTPUT->notification(get_string('thisforumlvisthrottled','forumlv',$a));
}

if ($forumlv->type == 'qanda' && !has_capability('mod/forumlv:viewqandawithoutposting', $modcontext) &&
            !forumlv_user_has_posted($forumlv->id,$discussion->id,$USER->id)) {
    echo $OUTPUT->notification(get_string('qandanotify', 'forumlv'));
}

if ($move == -1 and confirm_sesskey()) {
    echo $OUTPUT->notification(get_string('discussionmoved', 'forumlv', format_string($forumlv->name,true)), 'notifysuccess');
}

$canrate = has_capability('mod/forumlv:rate', $modcontext);
forumlv_print_discussion($course, $cm, $forumlv, $discussion, $post, $displaymode, $canreply, $canrate);

echo $neighbourlinks;

// Add the subscription toggle JS.
$PAGE->requires->yui_module('moodle-mod_forumlv-subscriptiontoggle', 'Y.M.mod_forumlv.subscriptiontoggle.init');

echo $OUTPUT->footer();
