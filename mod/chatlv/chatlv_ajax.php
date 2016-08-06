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

define('AJAX_SCRIPT', true);

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once(dirname(__FILE__) . '/lib.php');

$action       = optional_param('action', '', PARAM_ALPHANUM);
$beep_id      = optional_param('beep', '', PARAM_RAW);
$chatlv_sid     = required_param('chatlv_sid', PARAM_ALPHANUM);
$theme        = required_param('theme', PARAM_ALPHANUM);
$chatlv_message = optional_param('chatlv_message', '', PARAM_RAW);
$chatlv_lasttime = optional_param('chatlv_lasttime', 0, PARAM_INT);
$chatlv_lastrow  = optional_param('chatlv_lastrow', 1, PARAM_INT);

if (!confirm_sesskey()) {
    throw new moodle_exception('invalidsesskey', 'error');
}

if (!$chatlvuser = $DB->get_record('chatlv_users', array('sid'=>$chatlv_sid))) {
    throw new moodle_exception('notlogged', 'chatlv');
}
if (!$chatlv = $DB->get_record('chatlv', array('id'=>$chatlvuser->chatlvid))) {
    throw new moodle_exception('invaliduserid', 'error');
}
if (!$course = $DB->get_record('course', array('id'=>$chatlv->course))) {
    throw new moodle_exception('invalidcourseid', 'error');
}
if (!$cm = get_coursemodule_from_instance('chatlv', $chatlv->id, $course->id)) {
    throw new moodle_exception('invalidcoursemodule', 'error');
}

if (!isloggedin()) {
    throw new moodle_exception('notlogged', 'chatlv');
}

// setup $PAGE so that format_text will work properly
$PAGE->set_cm($cm, $course, $chatlv);
$PAGE->set_url('/mod/chatlv/chatlv_ajax.php', array('chatlv_sid'=>$chatlv_sid));

ob_start();
header('Expires: Sun, 28 Dec 1997 09:32:45 GMT');
header('Last-Modified: '.gmdate('D, d M Y H:i:s').' GMT');
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');
header('Content-Type: text/html; charset=utf-8');

switch ($action) {
case 'init':
    $users = chatlv_get_users($chatlvuser->chatlvid, $chatlvuser->groupid, $cm->groupingid);
    $users = chatlv_format_userlist($users, $course);
    $response['users'] = $users;
    echo json_encode($response);
    break;

case 'chatlv':
    session_get_instance()->write_close();
    chatlv_delete_old_users();
    $chatlv_message = clean_text($chatlv_message, FORMAT_MOODLE);

    if (!empty($beep_id)) {
        $chatlv_message = 'beep '.$beep_id;
    }

    if (!empty($chatlv_message)) {
        $message = new stdClass();
        $message->chatlvid    = $chatlvuser->chatlvid;
        $message->userid    = $chatlvuser->userid;
        $message->groupid   = $chatlvuser->groupid;
        $message->message   = $chatlv_message;
        $message->timestamp = time();

        $chatlvuser->lastmessageping = time() - 2;
        $DB->update_record('chatlv_users', $chatlvuser);

        $DB->insert_record('chatlv_messages', $message);
        $DB->insert_record('chatlv_messages_current', $message);
        // response ok message
        echo json_encode(true);
        add_to_log($course->id, 'chatlv', 'talk', "view.php?id=$cm->id", $chatlv->id, $cm->id);

        ob_end_flush();
    }
    break;

case 'update':
    if ((time() - $chatlv_lasttime) > $CFG->chatlv_old_ping) {
        chatlv_delete_old_users();
    }

    if ($latest_message = chatlv_get_latest_message($chatlvuser->chatlvid, $chatlvuser->groupid)) {
        $chatlv_newlasttime = $latest_message->timestamp;
    } else {
        $chatlv_newlasttime = 0;
    }

    if ($chatlv_lasttime == 0) {
        $chatlv_lasttime = time() - $CFG->chatlv_old_ping;
    }

    $params = array('groupid'=>$chatlvuser->groupid, 'chatlvid'=>$chatlvuser->chatlvid, 'lasttime'=>$chatlv_lasttime);

    $groupselect = $chatlvuser->groupid ? " AND (groupid=".$chatlvuser->groupid." OR groupid=0) " : "";

    $messages = $DB->get_records_select('chatlv_messages_current',
        'chatlvid = :chatlvid AND timestamp > :lasttime '.$groupselect, $params,
        'timestamp ASC');

    if (!empty($messages)) {
        $num = count($messages);
    } else {
        $num = 0;
    }
    $chatlv_newrow = ($chatlv_lastrow + $num) % 2;
    $send_user_list = false;
    if ($messages && ($chatlv_lasttime != $chatlv_newlasttime)) {
        foreach ($messages as $n => &$message) {
            $tmp = new stdClass();
            // when somebody enter room, user list will be updated
            if (!empty($message->system)){
                $send_user_list = true;
                $users = chatlv_format_userlist(chatlv_get_users($chatlvuser->chatlvid, $chatlvuser->groupid, $cm->groupingid), $course);
            }
            if ($html = chatlv_format_message_theme($message, $chatlvuser, $USER, $cm->groupingid, $theme)) {
                $message->mymessage = ($USER->id == $message->userid);
                $message->message  = $html->html;
                if (!empty($html->type)) {
                    $message->type = $html->type;
                }
            } else {
                unset($messages[$n]);
            }
        }
    }

    if(!empty($users) && $send_user_list){
        // return users when system message coming
        $response['users'] = $users;
    }

    $DB->set_field('chatlv_users', 'lastping', time(), array('id'=>$chatlvuser->id));

    $response['lasttime'] = $chatlv_newlasttime;
    $response['lastrow']  = $chatlv_newrow;
    if($messages){
        $response['msgs'] = $messages;
    }

    echo json_encode($response);
    header('Content-Length: ' . ob_get_length() );

    ob_end_flush();
    break;

default:
    break;
}
