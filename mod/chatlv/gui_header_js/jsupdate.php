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

define('NO_MOODLE_COOKIES', true); // Session not used here.

require('../../../config.php');
require('../lib.php');

$chatlvsid      = required_param('chatlv_sid', PARAM_ALPHANUM);
$chatlvlasttime = optional_param('chatlv_lasttime', 0, PARAM_INT);
$chatlvlastrow  = optional_param('chatlv_lastrow', 1, PARAM_INT);

$url = new moodle_url('/mod/chatlv/gui_header_js/jsupdate.php', array('chatlv_sid' => $chatlvsid));
if ($chatlvlasttime !== 0) {
    $url->param('chatlv_lasttime', $chatlvlasttime);
}
if ($chatlvlastrow !== 1) {
    $url->param('chatlv_lastrow', $chatlvlastrow);
}
$PAGE->set_url($url);


if (!$chatlvuser = $DB->get_record('chatlv_users', array('sid' => $chatlvsid))) {
    print_error('notlogged', 'chatlv');
}

// Get the minimal course.
if (!$course = $DB->get_record('course', array('id' => $chatlvuser->course))) {
    print_error('invalidcourseid');
}

// Get the user theme and enough info to be used in chatlv_format_message() which passes it along to.
// No optimisation here, it would break again in future!
if (!$user = $DB->get_record('user', array('id' => $chatlvuser->userid, 'deleted' => 0, 'suspended' => 0))) {
    print_error('invaliduser');
}
\core\session\manager::set_user($user);

// Setup course, lang and theme.
$PAGE->set_course($course);

// Force deleting of timed out users if there is a silence in room or just entering.
if ((time() - $chatlvlasttime) > $CFG->chatlv_old_ping) {
    // Must be done before chatlv_get_latest_message!
    chatlv_delete_old_users();
}

if ($message = chatlv_get_latest_message($chatlvuser->chatlvid, $chatlvuser->groupid)) {
    $chatlvnewlasttime = $message->timestamp;
} else {
    $chatlvnewlasttime = 0;
}

if ($chatlvlasttime == 0) { // Display some previous messages.
    $chatlvlasttime = time() - $CFG->chatlv_old_ping; // TO DO - any better value?
}

$timenow    = time();

$params = array('groupid' => $chatlvuser->groupid, 'chatlvid' => $chatlvuser->chatlvid, 'lasttime' => $chatlvlasttime);

$groupselect = $chatlvuser->groupid ? " AND (groupid=:groupid OR groupid=0) " : "";

$messages = $DB->get_records_select("chatlv_messages_current",
                    "chatlvid = :chatlvid AND timestamp > :lasttime $groupselect", $params,
                    "timestamp ASC");

if ($messages) {
    $num = count($messages);
} else {
    $num = 0;
}

$chatlvnewrow = ($chatlvlastrow + $num) % 2;

// No &amp; in url, does not work in header!
$baseurl = "{$CFG->wwwroot}/mod/chatlv/gui_header_js/jsupdate.php?";
$refreshurl = $baseurl . "chatlv_sid=$chatlvsid&chatlv_lasttime=$chatlvnewlasttime&chatlv_lastrow=$chatlvnewrow";
$refreshurlamp = $baseurl . "chatlv_sid=$chatlvsid&amp;chatlv_lasttime=$chatlvnewlasttime&amp;chatlv_lastrow=$chatlvnewrow";

header('Expires: Sun, 28 Dec 1997 09:32:45 GMT');
header('Last-Modified: '.gmdate('D, d M Y H:i:s').' GMT');
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');
header('Content-Type: text/html; charset=utf-8');
header("Refresh: $CFG->chatlv_refresh_room; url=$refreshurl");

// Use ob to be able to send Content-Length headers.
// Needed for Keep-Alive to work.
ob_start();

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html>
    <head>
        <meta http-equiv="content-type" content="text/html; charset=utf-8" />
        <script type="text/javascript">
        //<![CDATA[
        if (parent.msg && parent.msg.document.getElementById("msgStarted") == null) {
            parent.msg.document.close();
            parent.msg.document.open("text/html","replace");
            parent.msg.document.write("<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.0 Transitional//EN\" \"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd\">");
            parent.msg.document.write("<html><head>");
            parent.msg.document.write("<meta http-equiv=\"content-type\" content=\"text/html; charset=utf-8\" />");
            parent.msg.document.write("<base target=\"_blank\" />");
            parent.msg.document.write("<\/head><body class=\"mod-chatlv-gui_header_js course-<?php echo $chatlvuser->course ?>\" id=\"mod-chatlv-gui_header_js-jsupdate\"><div style=\"display: none\" id=\"msgStarted\">&nbsp;<\/div>");
        }
<?php
$beep = false;
$refreshusers = false;
$us = array ();
if (($chatlvlasttime != $chatlvnewlasttime) and $messages) {

    foreach ($messages as $message) {
        $chatlvlastrow = ($chatlvlastrow + 1) % 2;
        $formatmessage = chatlv_format_message($message, $chatlvuser->course, $USER, $chatlvlastrow);
        if ($formatmessage->beep) {
             $beep = true;
        }
        if ($formatmessage->refreshusers) {
             $refreshusers = true;
        }
        $us[$message->userid] = $timenow - $message->timestamp;
        echo "if(parent.msg)";
        echo "parent.msg.document.write('".addslashes_js($formatmessage->html)."\\n');\n";
    }
}

$chatlvuser->lastping = time();
$DB->set_field('chatlv_users', 'lastping', $chatlvuser->lastping, array('id' => $chatlvuser->id));

if ($refreshusers) {
?>
        var link = parent.users.document.getElementById('refreshLink');
        if (link != null) {
            parent.users.location.href = link.href;
        }
<?php
} else {
    foreach ($us as $uid => $lastping) {
        $min = (int) ($lastping / 60);
        $sec = $lastping - ($min * 60);
        $min = $min < 10 ? '0'.$min : $min;
        $sec = $sec < 10 ? '0'.$sec : $sec;
        $idle = $min.':'.$sec;
        echo "if (parent.users && parent.users.document.getElementById('uidle{$uid}') != null) {".
                "parent.users.document.getElementById('uidle{$uid}').innerHTML = '$idle';}\n";
    }
}
?>
        if (parent.input) {
            var autoscroll = parent.input.document.getElementById('auto');
            if (parent.msg && autoscroll && autoscroll.checked) {
                parent.msg.scroll(1,5000000);
            }
        }
        //]]>
        </script>
    </head>
    <body>
<?php
if ($beep) {
    echo '<embed src="../beep.wav" autostart="true" hidden="true" name="beep" />';
}
?>
       <a href="<?php echo $refreshurlamp ?>" name="refreshLink">Refresh link</a>
    </body>
</html>
<?php

// Support HTTP Keep-Alive.
header("Content-Length: " . ob_get_length() );
ob_end_flush();
exit;

