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

require_once('../../../config.php');
require_once('../lib.php');

$id      = required_param('id', PARAM_INT);
$groupid = optional_param('groupid', 0, PARAM_INT);  // Only for teachers.
$message = optional_param('message', '', PARAM_CLEANHTML);
$refresh = optional_param('refresh', '', PARAM_RAW); // Force refresh.
$last    = optional_param('last', 0, PARAM_INT);     // Last time refresh or sending.
$newonly = optional_param('newonly', 0, PARAM_BOOL); // Show only new messages.

$url = new moodle_url('/mod/chatlv/gui_basic/index.php', array('id' => $id));
if ($groupid !== 0) {
    $url->param('groupid', $groupid);
}
if ($message !== 0) {
    $url->param('message', $message);
}
if ($refresh !== 0) {
    $url->param('refresh', $refresh);
}
if ($last !== 0) {
    $url->param('last', $last);
}
if ($newonly !== 0) {
    $url->param('newonly', $newonly);
}
$PAGE->set_url($url);

if (!$chatlv = $DB->get_record('chatlv', array('id' => $id))) {
    print_error('invalidid', 'chatlv');
}

if (!$course = $DB->get_record('course', array('id' => $chatlv->course))) {
    print_error('invalidcourseid');
}

if (!$cm = get_coursemodule_from_instance('chatlv', $chatlv->id, $course->id)) {
    print_error('invalidcoursemodule');
}

$context = context_module::instance($cm->id);
require_login($course, false, $cm);
require_capability('mod/chatlv:chatlv', $context);
$PAGE->set_pagelayout('popup');
$PAGE->set_popup_notification_allowed(false);

// Check to see if groups are being used here.
if ($groupmode = groups_get_activity_groupmode($cm)) { // Groups are being used.
    if ($groupid = groups_get_activity_group($cm)) {
        if (!$group = groups_get_group($groupid)) {
            print_error('invalidgroupid');
        }
        $groupname = ': '.$group->name;
    } else {
        $groupname = ': '.get_string('allparticipants');
    }
} else {
    $groupid = 0;
    $groupname = '';
}

$strchatlv  = get_string('modulename', 'chatlv'); // Must be before current_language() in chatlv_login_user() to force course language!
$strchatlvs = get_string('modulenameplural', 'chatlv');
$stridle  = get_string('idle', 'chatlv');
if (!$chatlvsid = chatlv_login_user($chatlv->id, 'basic', $groupid, $course)) {
    print_error('cantlogin', 'chatlv');
}

if (!$chatlvusers = chatlv_get_users($chatlv->id, $groupid, $cm->groupingid)) {
    print_error('errornousers', 'chatlv');
}

$DB->set_field('chatlv_users', 'lastping', time(), array('sid' => $chatlvsid));

if (!isset($SESSION->chatlvprefs)) {
    $SESSION->chatlvprefs = array();
}
if (!isset($SESSION->chatlvprefs[$chatlv->id])) {
    $SESSION->chatlvprefs[$chatlv->id] = array();
    $SESSION->chatlvprefs[$chatlv->id]['chatlventered'] = time();
}
$chatlventered = $SESSION->chatlvprefs[$chatlv->id]['chatlventered'];

$refreshedmessage = '';

if (!empty($refresh) and data_submitted()) {
    $refreshedmessage = $message;

    chatlv_delete_old_users();

} else if (empty($refresh) and data_submitted() and confirm_sesskey()) {

    if ($message != '') {

        $chatlvuser = $DB->get_record('chatlv_users', array('sid' => $chatlvsid));
        chatlv_send_chatlvmessage($chatlvuser, $message, 0, $cm);

        $DB->set_field('chatlv_users', 'lastmessageping', time(), array('sid' => $chatlvsid));
    }

    chatlv_delete_old_users();

    $url = new moodle_url('/mod/chatlv/gui_basic/index.php', array('id' => $id, 'newonly' => $newonly, 'last' => $last));
    redirect($url);
}

$PAGE->set_title("$strchatlv: $course->shortname: ".format_string($chatlv->name, true)."$groupname");
echo $OUTPUT->header();
echo $OUTPUT->container_start(null, 'page-mod-chatlv-gui_basic');

echo $OUTPUT->heading(format_string($course->shortname), 1);
echo $OUTPUT->heading(format_string($chatlv->name), 2);

echo $OUTPUT->heading(get_string('participants'), 3);

echo $OUTPUT->box_start('generalbox', 'participants');
echo '<ul>';
foreach ($chatlvusers as $chu) {
    echo '<li class="clearfix">';
    echo $OUTPUT->user_picture($chu, array('size' => 24, 'courseid' => $course->id));
    echo '<div class="userinfo">';
    echo fullname($chu).' ';
    if ($idle = time() - $chu->lastmessageping) {
        echo '<span class="idle">'.$stridle.' '.format_time($idle).'</span>';
    } else {
        echo '<span class="idle" />';
    }
    echo '</div>';
    echo '</li>';
}
echo '</ul>';
echo $OUTPUT->box_end();
echo '<div id="send">';
echo '<form id="editing" method="post" action="index.php">';

echo '<h2><label for="message">'.get_string('sendmessage', 'message').'</label></h2>';
echo '<div>';
echo '<input type="text" id="message" name="message" value="'.s($refreshedmessage, true).'" size="60" />';
echo '</div><div>';
echo '<input type="hidden" name="id" value="'.$id.'" />';
echo '<input type="hidden" name="groupid" value="'.$groupid.'" />';
echo '<input type="hidden" name="last" value="'.time().'" />';
echo '<input type="hidden" name="sesskey" value="'.sesskey().'" />';
echo '<input type="submit" value="'.get_string('submit').'" />&nbsp;';
echo '<input type="submit" name="refresh" value="'.get_string('refresh').'" />';
echo '<input type="checkbox" name="newonly" id="newonly" '.($newonly ? 'checked="checked" ' : '').'/>';
echo '<label for="newonly">'.get_string('newonlymsg', 'message').'</label>';
echo '</div>';
echo '</form>';
echo '</div>';

echo '<div id="messages">';
echo $OUTPUT->heading(get_string('messages', 'chatlv'), 3);

$allmessages = array();
$options = new stdClass();
$options->para = false;
$options->newlines = true;

$params = array('last' => $last, 'groupid' => $groupid, 'chatlvid' => $chatlv->id, 'chatlventered' => $chatlventered);

if ($newonly) {
    $lastsql = "AND timestamp > :last";
} else {
    $lastsql = "";
}

$groupselect = $groupid ? "AND (groupid=:groupid OR groupid=0)" : "";

$messages = $DB->get_records_select("chatlv_messages_current",
                    "chatlvid = :chatlvid AND timestamp > :chatlventered $lastsql $groupselect", $params,
                    "timestamp DESC");

if ($messages) {
    foreach ($messages as $message) {
        $allmessages[] = chatlv_format_message($message, $course->id, $USER);
    }
}
echo '<table class="generaltable"><tbody>';
echo '<tr>
        <th scope="col" class="cell">' . get_string('from') . '</th>
        <th scope="col" class="cell">' . get_string('message', 'message') . '</th>
        <th scope="col" class="cell">' . get_string('time') . '</th>
      </tr>';
if (empty($allmessages)) {
    echo get_string('nomessagesfound', 'message');
} else {
    foreach ($allmessages as $message) {
        echo $message->basic;
    }
}
echo '</tbody></table>';
echo '</div>';
echo $OUTPUT->container_end();
echo $OUTPUT->footer();
