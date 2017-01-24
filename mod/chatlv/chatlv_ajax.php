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
$beepid       = optional_param('beep', '', PARAM_RAW);
$chatlvsid      = required_param('chatlv_sid', PARAM_ALPHANUM);
$theme        = required_param('theme', PARAM_ALPHANUMEXT);
$chatlvmessage  = optional_param('chatlv_message', '', PARAM_RAW);
$chatlvlasttime = optional_param('chatlv_lasttime', 0, PARAM_INT);
$chatlvlastrow  = optional_param('chatlv_lastrow', 1, PARAM_INT);

if (!confirm_sesskey()) {
    throw new moodle_exception('invalidsesskey', 'error');
}

if (!$chatlvuser = $DB->get_record('chatlv_users', array('sid' => $chatlvsid))) {
    throw new moodle_exception('notlogged', 'chatlv');
}
if (!$chatlv = $DB->get_record('chatlv', array('id' => $chatlvuser->chatlvid))) {
    throw new moodle_exception('invaliduserid', 'error');
}
if (!$course = $DB->get_record('course', array('id' => $chatlv->course))) {
    throw new moodle_exception('invalidcourseid', 'error');
}
if (!$cm = get_coursemodule_from_instance('chatlv', $chatlv->id, $course->id)) {
    throw new moodle_exception('invalidcoursemodule', 'error');
}

if (!isloggedin()) {
    throw new moodle_exception('notlogged', 'chatlv');
}

// Set up $PAGE so that format_text will work properly.
$PAGE->set_cm($cm, $course, $chatlv);
$PAGE->set_url('/mod/chatlv/chatlv_ajax.php', array('chatlv_sid' => $chatlvsid));

require_login($course, false, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/chatlv:chatlv', $context);

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
        \core\session\manager::write_close();
        chatlv_delete_old_users();
        $chatlvmessage = clean_text($chatlvmessage, FORMAT_MOODLE);

        if (!empty($beepid)) {
            $chatlvmessage = 'beep '.$beepid;
        }

        if (!empty($chatlvmessage)) {

            chatlv_send_chatlvmessage($chatlvuser, $chatlvmessage, 0, $cm);

            $chatlvuser->lastmessageping = time() - 2;
            $DB->update_record('chatlv_users', $chatlvuser);

            // Response OK message.
            echo json_encode(true);
            ob_end_flush();
        }
        break;

    case 'update':
        if ((time() - $chatlvlasttime) > $CFG->chatlv_old_ping) {
            chatlv_delete_old_users();
        }

        if ($latestmessage = chatlv_get_latest_message($chatlvuser->chatlvid, $chatlvuser->groupid)) {
            $chatlvnewlasttime = $latestmessage->timestamp;
        } else {
            $chatlvnewlasttime = 0;
        }

        if ($chatlvlasttime == 0) {
            $chatlvlasttime = time() - $CFG->chatlv_old_ping;
        }

        $messages = chatlv_get_latest_messages($chatlvuser, $chatlvlasttime);

        if (!empty($messages)) {
            $num = count($messages);
        } else {
            $num = 0;
        }
        $chatlvnewrow = ($chatlvlastrow + $num) % 2;
        $senduserlist = false;
        if ($messages && ($chatlvlasttime != $chatlvnewlasttime)) {
            foreach ($messages as $n => &$message) {
                $tmp = new stdClass();
                // When somebody enter room, user list will be updated.
                if (!empty($message->system)) {
                    $senduserlist = true;
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

        if ($senduserlist) {
            // Return users when system message arrives.
            $users = chatlv_format_userlist(chatlv_get_users($chatlvuser->chatlvid, $chatlvuser->groupid, $cm->groupingid), $course);
            $response['users'] = $users;
        }

        $DB->set_field('chatlv_users', 'lastping', time(), array('id' => $chatlvuser->id));

        $response['lasttime'] = $chatlvnewlasttime;
        $response['lastrow']  = $chatlvnewrow;
        if ($messages) {
            $response['msgs'] = $messages;
        }

        echo json_encode($response);
        header('Content-Length: ' . ob_get_length());

        ob_end_flush();
        break;

    default:
        break;
}
