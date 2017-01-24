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

// This page prints a particular instance of chatlv.

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once($CFG->dirroot . '/mod/chatlv/lib.php');
require_once($CFG->libdir . '/completionlib.php');

$id   = optional_param('id', 0, PARAM_INT);
$c    = optional_param('c', 0, PARAM_INT);
$edit = optional_param('edit', -1, PARAM_BOOL);

if ($id) {
    if (! $cm = get_coursemodule_from_id('chatlv', $id)) {
        print_error('invalidcoursemodule');
    }

    if (! $course = $DB->get_record('course', array('id' => $cm->course))) {
        print_error('coursemisconf');
    }

    chatlv_update_chatlv_times($cm->instance);

    if (! $chatlv = $DB->get_record('chatlv', array('id' => $cm->instance))) {
        print_error('invalidid', 'chatlv');
    }

} else {
    chatlv_update_chatlv_times($c);

    if (! $chatlv = $DB->get_record('chatlv', array('id' => $c))) {
        print_error('coursemisconf');
    }
    if (! $course = $DB->get_record('course', array('id' => $chatlv->course))) {
        print_error('coursemisconf');
    }
    if (! $cm = get_coursemodule_from_instance('chatlv', $chatlv->id, $course->id)) {
        print_error('invalidcoursemodule');
    }
}

require_course_login($course, true, $cm);

$context = context_module::instance($cm->id);
$PAGE->set_context($context);

// Show some info for guests.
if (isguestuser()) {
    $PAGE->set_title($chatlv->name);
    echo $OUTPUT->header();
    echo $OUTPUT->confirm('<p>'.get_string('noguests', 'chatlv').'</p>'.get_string('liketologin'),
            get_login_url(), $CFG->wwwroot.'/course/view.php?id='.$course->id);

    echo $OUTPUT->footer();
    exit;
}

// Completion and trigger events.
chatlv_view($chatlv, $course, $cm, $context);

$strenterchatlv    = get_string('enterchatlv', 'chatlv');
$stridle         = get_string('idle', 'chatlv');
$strcurrentusers = get_string('currentusers', 'chatlv');
$strnextsession  = get_string('nextsession', 'chatlv');

$courseshortname = format_string($course->shortname, true, array('context' => context_course::instance($course->id)));
$title = $courseshortname . ': ' . format_string($chatlv->name);

// Initialize $PAGE.
$PAGE->set_url('/mod/chatlv/view.php', array('id' => $cm->id));
$PAGE->set_title($title);
$PAGE->set_heading($course->fullname);

// Print the page header.
echo $OUTPUT->header();

// Check to see if groups are being used here.
$groupmode = groups_get_activity_groupmode($cm);
$currentgroup = groups_get_activity_group($cm, true);

// URL parameters.
$params = array();
if ($currentgroup) {
    $groupselect = " AND groupid = '$currentgroup'";
    $groupparam = "_group{$currentgroup}";
    $params['groupid'] = $currentgroup;
} else {
    $groupselect = "";
    $groupparam = "";
}

echo $OUTPUT->heading(format_string($chatlv->name), 2);

if ($chatlv->intro) {
    echo $OUTPUT->box(format_module_intro('chatlv', $chatlv, $cm->id), 'generalbox', 'intro');
}

groups_print_activity_menu($cm, $CFG->wwwroot . "/mod/chatlv/view.php?id=$cm->id");

if (has_capability('mod/chatlv:chatlv', $context)) {
    // Print the main part of the page.
    echo $OUTPUT->box_start('generalbox', 'enterlink');

    $now = time();
    $span = $chatlv->chatlvtime - $now;
    if ($chatlv->chatlvtime and $chatlv->schedule and ($span > 0)) {  // A chatlv is scheduled.
        echo '<p>';
        echo get_string('sessionstart', 'chatlv', format_time($span));
        echo '</p>';
    }

    $params['id'] = $chatlv->id;
    $chatlvtarget = new moodle_url("/mod/chatlv/gui_$CFG->chatlv_method/index.php", $params);
    echo '<p>';
    echo $OUTPUT->action_link($chatlvtarget,
                              $strenterchatlv,
                              new popup_action('click', $chatlvtarget, "chatlv{$course->id}_{$chatlv->id}{$groupparam}",
                                               array('height' => 500, 'width' => 700)));
    echo '</p>';

    $params['id'] = $chatlv->id;
    $link = new moodle_url('/mod/chatlv/gui_basic/index.php', $params);
    $action = new popup_action('click', $link, "chatlv{$course->id}_{$chatlv->id}{$groupparam}",
                               array('height' => 500, 'width' => 700));
    echo '<p>';
    echo $OUTPUT->action_link($link, get_string('noframesjs', 'message'), $action,
                              array('title' => get_string('modulename', 'chatlv')));
    echo '</p>';

    if ($chatlv->studentlogs or has_capability('mod/chatlv:readlog', $context)) {
        if ($msg = $DB->get_records_select('chatlv_messages', "chatlvid = ? $groupselect", array($chatlv->id))) {
            echo '<p>';
            echo html_writer::link(new moodle_url('/mod/chatlv/report.php', array('id' => $cm->id)),
                                   get_string('viewreport', 'chatlv'));
            echo '</p>';
        }
    }

    echo $OUTPUT->box_end();

} else {
    echo $OUTPUT->box_start('generalbox', 'notallowenter');
    echo '<p>'.get_string('notallowenter', 'chatlv').'</p>';
    echo $OUTPUT->box_end();
}

chatlv_delete_old_users();

if ($chatlvusers = chatlv_get_users($chatlv->id, $currentgroup, $cm->groupingid)) {
    $timenow = time();
    echo $OUTPUT->box_start('generalbox', 'chatlvcurrentusers');
    echo $OUTPUT->heading($strcurrentusers, 3);
    echo '<table>';
    foreach ($chatlvusers as $chatlvuser) {
        $lastping = $timenow - $chatlvuser->lastmessageping;
        echo '<tr><td class="chatlvuserimage">';
        $url = new moodle_url('/user/view.php', array('id' => $chatlvuser->id, 'course' => $chatlv->course));
        echo html_writer::link($url, $OUTPUT->user_picture($chatlvuser));
        echo '</td><td class="chatlvuserdetails">';
        echo '<p>'.fullname($chatlvuser).'</p>';
        echo '<span class="idletime">'.$stridle.': '.format_time($lastping).'</span>';
        echo '</td></tr>';
    }
    echo '</table>';
    echo $OUTPUT->box_end();
}

echo $OUTPUT->footer();
