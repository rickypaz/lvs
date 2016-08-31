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
 * Subscribe to or unsubscribe from a forumlv or manage forumlv subscription mode
 *
 * This script can be used by either individual users to subscribe to or
 * unsubscribe from a forumlv (no 'mode' param provided), or by forumlv managers
 * to control the subscription mode (by 'mode' param).
 * This script can be called from a link in email so the sesskey is not
 * required parameter. However, if sesskey is missing, the user has to go
 * through a confirmation page that redirects the user back with the
 * sesskey.
 *
 * @package   mod_forumlv
 * @copyright  1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once($CFG->dirroot.'/mod/forumlv/lib.php');

$id             = required_param('id', PARAM_INT);             // The forumlv to set subscription on.
$mode           = optional_param('mode', null, PARAM_INT);     // The forumlv's subscription mode.
$user           = optional_param('user', 0, PARAM_INT);        // The userid of the user to subscribe, defaults to $USER.
$discussionid   = optional_param('d', null, PARAM_INT);        // The discussionid to subscribe.
$sesskey        = optional_param('sesskey', null, PARAM_RAW);
$returnurl      = optional_param('returnurl', null, PARAM_RAW);

$url = new moodle_url('/mod/forumlv/subscribe.php', array('id'=>$id));
if (!is_null($mode)) {
    $url->param('mode', $mode);
}
if ($user !== 0) {
    $url->param('user', $user);
}
if (!is_null($sesskey)) {
    $url->param('sesskey', $sesskey);
}
if (!is_null($discussionid)) {
    $url->param('d', $discussionid);
    if (!$discussion = $DB->get_record('forumlv_discussions', array('id' => $discussionid, 'forumlv' => $id))) {
        print_error('invaliddiscussionid', 'forumlv');
    }
}
$PAGE->set_url($url);

$forumlv   = $DB->get_record('forumlv', array('id' => $id), '*', MUST_EXIST);
$course  = $DB->get_record('course', array('id' => $forumlv->course), '*', MUST_EXIST);
$cm      = get_coursemodule_from_instance('forumlv', $forumlv->id, $course->id, false, MUST_EXIST);
$context = context_module::instance($cm->id);

if ($user) {
    require_sesskey();
    if (!has_capability('mod/forumlv:managesubscriptions', $context)) {
        print_error('nopermissiontosubscribe', 'forumlv');
    }
    $user = $DB->get_record('user', array('id' => $user), '*', MUST_EXIST);
} else {
    $user = $USER;
}

if (isset($cm->groupmode) && empty($course->groupmodeforce)) {
    $groupmode = $cm->groupmode;
} else {
    $groupmode = $course->groupmode;
}

$issubscribed = \mod_forumlv\subscriptions::is_subscribed($user->id, $forumlv, $discussionid, $cm);

// For a user to subscribe when a groupmode is set, they must have access to at least one group.
if ($groupmode && !$issubscribed && !has_capability('moodle/site:accessallgroups', $context)) {
    if (!groups_get_all_groups($course->id, $USER->id)) {
        print_error('cannotsubscribe', 'forumlv');
    }
}

require_login($course, false, $cm);

if (is_null($mode) and !is_enrolled($context, $USER, '', true)) {   // Guests and visitors can't subscribe - only enrolled
    $PAGE->set_title($course->shortname);
    $PAGE->set_heading($course->fullname);
    if (isguestuser()) {
        echo $OUTPUT->header();
        echo $OUTPUT->confirm(get_string('subscribeenrolledonly', 'forumlv').'<br /><br />'.get_string('liketologin'),
                     get_login_url(), new moodle_url('/mod/forumlv/view.php', array('f'=>$id)));
        echo $OUTPUT->footer();
        exit;
    } else {
        // There should not be any links leading to this place, just redirect.
        redirect(
                new moodle_url('/mod/forumlv/view.php', array('f'=>$id)),
                get_string('subscribeenrolledonly', 'forumlv'),
                null,
                \core\output\notification::NOTIFY_ERROR
            );
    }
}

$returnto = optional_param('backtoindex',0,PARAM_INT)
    ? "index.php?id=".$course->id
    : "view.php?f=$id";

if ($returnurl) {
    $returnto = $returnurl;
}

if (!is_null($mode) and has_capability('mod/forumlv:managesubscriptions', $context)) {
    require_sesskey();
    switch ($mode) {
        case FORUMLV_CHOOSESUBSCRIBE : // 0
            \mod_forumlv\subscriptions::set_subscription_mode($forumlv->id, FORUMLV_CHOOSESUBSCRIBE);
            redirect(
                    $returnto,
                    get_string('everyonecannowchoose', 'forumlv'),
                    null,
                    \core\output\notification::NOTIFY_SUCCESS
                );
            break;
        case FORUMLV_FORCESUBSCRIBE : // 1
            \mod_forumlv\subscriptions::set_subscription_mode($forumlv->id, FORUMLV_FORCESUBSCRIBE);
            redirect(
                    $returnto,
                    get_string('everyoneisnowsubscribed', 'forumlv'),
                    null,
                    \core\output\notification::NOTIFY_SUCCESS
                );
            break;
        case FORUMLV_INITIALSUBSCRIBE : // 2
            if ($forumlv->forcesubscribe <> FORUMLV_INITIALSUBSCRIBE) {
                $users = \mod_forumlv\subscriptions::get_potential_subscribers($context, 0, 'u.id, u.email', '');
                foreach ($users as $user) {
                    \mod_forumlv\subscriptions::subscribe_user($user->id, $forumlv, $context);
                }
            }
            \mod_forumlv\subscriptions::set_subscription_mode($forumlv->id, FORUMLV_INITIALSUBSCRIBE);
            redirect(
                    $returnto,
                    get_string('everyoneisnowsubscribed', 'forumlv'),
                    null,
                    \core\output\notification::NOTIFY_SUCCESS
                );
            break;
        case FORUMLV_DISALLOWSUBSCRIBE : // 3
            \mod_forumlv\subscriptions::set_subscription_mode($forumlv->id, FORUMLV_DISALLOWSUBSCRIBE);
            redirect(
                    $returnto,
                    get_string('noonecansubscribenow', 'forumlv'),
                    null,
                    \core\output\notification::NOTIFY_SUCCESS
                );
            break;
        default:
            print_error(get_string('invalidforcesubscribe', 'forumlv'));
    }
}

if (\mod_forumlv\subscriptions::is_forcesubscribed($forumlv)) {
    redirect(
            $returnto,
            get_string('everyoneisnowsubscribed', 'forumlv'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
}

$info = new stdClass();
$info->name  = fullname($user);
$info->forumlv = format_string($forumlv->name);

if ($issubscribed) {
    if (is_null($sesskey)) {
        // We came here via link in email.
        $PAGE->set_title($course->shortname);
        $PAGE->set_heading($course->fullname);
        echo $OUTPUT->header();

        $viewurl = new moodle_url('/mod/forumlv/view.php', array('f' => $id));
        if ($discussionid) {
            $a = new stdClass();
            $a->forumlv = format_string($forumlv->name);
            $a->discussion = format_string($discussion->name);
            echo $OUTPUT->confirm(get_string('confirmunsubscribediscussion', 'forumlv', $a),
                    $PAGE->url, $viewurl);
        } else {
            echo $OUTPUT->confirm(get_string('confirmunsubscribe', 'forumlv', format_string($forumlv->name)),
                    $PAGE->url, $viewurl);
        }
        echo $OUTPUT->footer();
        exit;
    }
    require_sesskey();
    if ($discussionid === null) {
        if (\mod_forumlv\subscriptions::unsubscribe_user($user->id, $forumlv, $context, true)) {
            redirect(
                    $returnto,
                    get_string('nownotsubscribed', 'forumlv', $info),
                    null,
                    \core\output\notification::NOTIFY_SUCCESS
                );
        } else {
            print_error('cannotunsubscribe', 'forumlv', get_local_referer(false));
        }
    } else {
        if (\mod_forumlv\subscriptions::unsubscribe_user_from_discussion($user->id, $discussion, $context)) {
            $info->discussion = $discussion->name;
            redirect(
                    $returnto,
                    get_string('discussionnownotsubscribed', 'forumlv', $info),
                    null,
                    \core\output\notification::NOTIFY_SUCCESS
                );
        } else {
            print_error('cannotunsubscribe', 'forumlv', get_local_referer(false));
        }
    }

} else {  // subscribe
    if (\mod_forumlv\subscriptions::subscription_disabled($forumlv) && !has_capability('mod/forumlv:managesubscriptions', $context)) {
        print_error('disallowsubscribe', 'forumlv', get_local_referer(false));
    }
    if (!has_capability('mod/forumlv:viewdiscussion', $context)) {
        print_error('noviewdiscussionspermission', 'forumlv', get_local_referer(false));
    }
    if (is_null($sesskey)) {
        // We came here via link in email.
        $PAGE->set_title($course->shortname);
        $PAGE->set_heading($course->fullname);
        echo $OUTPUT->header();

        $viewurl = new moodle_url('/mod/forumlv/view.php', array('f' => $id));
        if ($discussionid) {
            $a = new stdClass();
            $a->forumlv = format_string($forumlv->name);
            $a->discussion = format_string($discussion->name);
            echo $OUTPUT->confirm(get_string('confirmsubscribediscussion', 'forumlv', $a),
                    $PAGE->url, $viewurl);
        } else {
            echo $OUTPUT->confirm(get_string('confirmsubscribe', 'forumlv', format_string($forumlv->name)),
                    $PAGE->url, $viewurl);
        }
        echo $OUTPUT->footer();
        exit;
    }
    require_sesskey();
    if ($discussionid == null) {
        \mod_forumlv\subscriptions::subscribe_user($user->id, $forumlv, $context, true);
        redirect(
                $returnto,
                get_string('nowsubscribed', 'forumlv', $info),
                null,
                \core\output\notification::NOTIFY_SUCCESS
            );
    } else {
        $info->discussion = $discussion->name;
        \mod_forumlv\subscriptions::subscribe_user_to_discussion($user->id, $discussion, $context);
        redirect(
                $returnto,
                get_string('discussionnowsubscribed', 'forumlv', $info),
                null,
                \core\output\notification::NOTIFY_SUCCESS
            );
    }
}
