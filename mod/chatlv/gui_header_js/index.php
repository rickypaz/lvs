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
$groupid = optional_param('groupid', 0, PARAM_INT); // Only for teachers.

$url = new moodle_url('/mod/chatlv/gui_header_js/index.php', array('id' => $id));
if ($groupid !== 0) {
    $url->param('groupid', $groupid);
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

// Check to see if groups are being used here.
if ($groupmode = groups_get_activity_groupmode($cm)) {   // Groups are being used.
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

$strchatlv = get_string('modulename', 'chatlv'); // Must be before current_language() in chatlv_login_user() to force course language!

if (!$chatlvsid = chatlv_login_user($chatlv->id, 'header_js', $groupid, $course)) {
    print_error('cantlogin', 'chatlv');
}

$params = "chatlv_id=$id&chatlv_sid={$chatlvsid}";

// Fallback to the old jsupdate, but allow other update modes.
$updatemode = 'jsupdate';
if (!empty($CFG->chatlv_normal_updatemode)) {
    $updatemode = $CFG->chatlv_normal_updatemode;
}

$courseshortname = format_string($course->shortname, true, array('context' => context_course::instance($course->id)));
?>

<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Frameset//EN" "http://www.w3.org/TR/html4/frameset.dtd">
<html>
 <head>
  <meta http-equiv="content-type" content="text/html; charset=utf-8" />
  <title>
   <?php echo "$strchatlv: " . $courseshortname . ": ".
              format_string($chatlv->name, true, array('context' => $context)) . "$groupname" ?>
  </title>
 </head>
 <frameset cols="*,200" border="5" framespacing="no" frameborder="yes" marginwidth="2" marginheight="1">
  <frameset rows="0,0,*,50" border="0" framespacing="no" frameborder="no" marginwidth="2" marginheight="1">
   <frame src="../empty.php" name="empty" scrolling="no" marginwidth="0" marginheight="0">
   <frame src="<?php echo $updatemode ?>.php?<?php echo $params ?>" name="jsupdate" scrolling="no" marginwidth="0" marginheight="0">
   <frame src="chatlvmsg.php?<?php echo $params ?>" name="msg" scrolling="auto" marginwidth="2" marginheight="1">
   <frame src="chatlvinput.php?<?php echo $params ?>" name="input" scrolling="no" marginwidth="2" marginheight="1">
  </frameset>
  <frame src="users.php?<?php echo $params ?>" name="users" scrolling="auto" marginwidth="5" marginheight="5">
 </frameset>
 <noframes>
  Sorry, this version of Moodle Chatlv needs a browser that handles frames.
 </noframes>
</html>
