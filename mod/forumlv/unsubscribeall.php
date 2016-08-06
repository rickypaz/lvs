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
 * @package mod-forumlv
 * @copyright  2008 Petr Skoda (http://skodak.org)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once("../../config.php");
require_once("lib.php");

$confirm = optional_param('confirm', false, PARAM_BOOL);

$PAGE->set_url('/mod/forumlv/unsubscribeall.php');
$PAGE->set_context(context_user::instance($USER->id));

// Do not autologin guest. Only proper users can have forumlv subscriptions.
require_login(null, false);

$return = $CFG->wwwroot.'/';

if (isguestuser()) {
    redirect($return);
}

$strunsubscribeall = get_string('unsubscribeall', 'forumlv');
$PAGE->navbar->add(get_string('modulename', 'forumlv'));
$PAGE->navbar->add($strunsubscribeall);
$PAGE->set_title($strunsubscribeall);
$PAGE->set_heading(format_string($COURSE->fullname));
echo $OUTPUT->header();
echo $OUTPUT->heading($strunsubscribeall);

if (data_submitted() and $confirm and confirm_sesskey()) {
    $forumlvs = forumlv_get_optional_subscribed_forumlvs();

    foreach($forumlvs as $forumlv) {
        forumlv_unsubscribe($USER->id, $forumlv->id);
    }
    $DB->set_field('user', 'autosubscribe', 0, array('id'=>$USER->id));

    echo $OUTPUT->box(get_string('unsubscribealldone', 'forumlv'));
    echo $OUTPUT->continue_button($return);
    echo $OUTPUT->footer();
    die;

} else {
    $a = count(forumlv_get_optional_subscribed_forumlvs());

    if ($a) {
        $msg = get_string('unsubscribeallconfirm', 'forumlv', $a);
        echo $OUTPUT->confirm($msg, new moodle_url('unsubscribeall.php', array('confirm'=>1)), $return);
        echo $OUTPUT->footer();
        die;

    } else {
        echo $OUTPUT->box(get_string('unsubscribeallempty', 'forumlv'));
        echo $OUTPUT->continue_button($return);
        echo $OUTPUT->footer();
        die;
    }
}
