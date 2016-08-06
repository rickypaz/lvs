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
 * This file is used to display and organise forumlv subscribers
 *
 * @package mod-forumlv
 * @copyright 1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once("../../config.php");
require_once("lib.php");

$id    = required_param('id',PARAM_INT);           // forumlv
$group = optional_param('group',0,PARAM_INT);      // change of group
$edit  = optional_param('edit',-1,PARAM_BOOL);     // Turn editing on and off

$url = new moodle_url('/mod/forumlv/subscribers.php', array('id'=>$id));
if ($group !== 0) {
    $url->param('group', $group);
}
if ($edit !== 0) {
    $url->param('edit', $edit);
}
$PAGE->set_url($url);

$forumlv = $DB->get_record('forumlv', array('id'=>$id), '*', MUST_EXIST);
$course = $DB->get_record('course', array('id'=>$forumlv->course), '*', MUST_EXIST);
if (! $cm = get_coursemodule_from_instance('forumlv', $forumlv->id, $course->id)) {
    $cm->id = 0;
}

require_login($course, false, $cm);

$context = context_module::instance($cm->id);
if (!has_capability('mod/forumlv:viewsubscribers', $context)) {
    print_error('nopermissiontosubscribe', 'forumlv');
}

unset($SESSION->fromdiscussion);

add_to_log($course->id, "forumlv", "view subscribers", "subscribers.php?id=$forumlv->id", $forumlv->id, $cm->id);

$forumlvoutput = $PAGE->get_renderer('mod_forumlv');
$currentgroup = groups_get_activity_group($cm);
$options = array('forumlvid'=>$forumlv->id, 'currentgroup'=>$currentgroup, 'context'=>$context);
$existingselector = new forumlv_existing_subscriber_selector('existingsubscribers', $options);
$subscriberselector = new forumlv_potential_subscriber_selector('potentialsubscribers', $options);
$subscriberselector->set_existing_subscribers($existingselector->find_users(''));

if (data_submitted()) {
    require_sesskey();
    $subscribe = (bool)optional_param('subscribe', false, PARAM_RAW);
    $unsubscribe = (bool)optional_param('unsubscribe', false, PARAM_RAW);
    /** It has to be one or the other, not both or neither */
    if (!($subscribe xor $unsubscribe)) {
        print_error('invalidaction');
    }
    if ($subscribe) {
        $users = $subscriberselector->get_selected_users();
        foreach ($users as $user) {
            if (!forumlv_subscribe($user->id, $id)) {
                print_error('cannotaddsubscriber', 'forumlv', '', $user->id);
            }
        }
    } else if ($unsubscribe) {
        $users = $existingselector->get_selected_users();
        foreach ($users as $user) {
            if (!forumlv_unsubscribe($user->id, $id)) {
                print_error('cannotremovesubscriber', 'forumlv', '', $user->id);
            }
        }
    }
    $subscriberselector->invalidate_selected_users();
    $existingselector->invalidate_selected_users();
    $subscriberselector->set_existing_subscribers($existingselector->find_users(''));
}

$strsubscribers = get_string("subscribers", "forumlv");
$PAGE->navbar->add($strsubscribers);
$PAGE->set_title($strsubscribers);
$PAGE->set_heading($COURSE->fullname);
if (has_capability('mod/forumlv:managesubscriptions', $context)) {
    $PAGE->set_button(forumlv_update_subscriptions_button($course->id, $id));
    if ($edit != -1) {
        $USER->subscriptionsediting = $edit;
    }
} else {
    unset($USER->subscriptionsediting);
}
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('forumlv', 'forumlv').' '.$strsubscribers);
if (empty($USER->subscriptionsediting)) {
    echo $forumlvoutput->subscriber_overview(forumlv_subscribed_users($course, $forumlv, $currentgroup, $context), $forumlv, $course);
} else if (forumlv_is_forcesubscribed($forumlv)) {
    $subscriberselector->set_force_subscribed(true);
    echo $forumlvoutput->subscribed_users($subscriberselector);
} else {
    echo $forumlvoutput->subscriber_selection_form($existingselector, $subscriberselector);
}
echo $OUTPUT->footer();