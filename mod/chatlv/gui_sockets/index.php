<?php

require_once('../../../config.php');
require_once('../lib.php');

$id      = required_param('id', PARAM_INT);
$groupid = optional_param('groupid', 0, PARAM_INT); //only for teachers

$url = new moodle_url('/mod/chatlv/gui_sockets/index.php', array('id'=>$id));
if ($groupid !== 0) {
    $url->param('groupid', $groupid);
}
$PAGE->set_url($url);

if (!$chatlv = $DB->get_record('chatlv', array('id'=>$id))) {
    print_error('invalidid', 'chatlv');
}

if (!$course = $DB->get_record('course', array('id'=>$chatlv->course))) {
    print_error('invalidcourseid');
}

if (!$cm = get_coursemodule_from_instance('chatlv', $chatlv->id, $course->id)) {
    print_error('invalidcoursemodule');
}

require_login($course, false, $cm);
$context = context_module::instance($cm->id);

if (isguestuser()) {
    print_error('noguests', 'chatlv');
}

/// Check to see if groups are being used here
 if ($groupmode = groups_get_activity_groupmode($cm)) {   // Groups are being used
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

$strchatlv = get_string('modulename', 'chatlv'); // must be before current_language() in chatlv_login_user() to force course language!!!

if (!$chatlv_sid = chatlv_login_user($chatlv->id, 'sockets', $groupid, $course)) {
    print_error('cantlogin');
}

$params = "chatlv_sid=$chatlv_sid";
$courseshortname = format_string($course->shortname, true, array('context' => context_course::instance($course->id)));

?><!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Frameset//EN" "http://www.w3.org/TR/html4/frameset.dtd">
<html>
 <head>
  <meta http-equiv="content-type" content="text/html; charset=utf-8" />
  <title>
   <?php echo "$strchatlv: " . $courseshortname . ": " . format_string($chatlv->name, true, array('context' => $context)) . "$groupname" ?>
  </title>
 </head>
 <frameset cols="*,200" border="5" framespacing="no" frameborder="yes" marginwidth="2" marginheight="1">
  <frameset rows="0,*,70" border="0" framespacing="no" frameborder="no" marginwidth="2" marginheight="1">
   <frame src="../empty.php" name="empty" scrolling="auto" noresize marginwidth="2" marginheight="0">
   <frame src="<?php echo "http://$CFG->chatlv_serverhost:$CFG->chatlv_serverport?win=chatlv&amp;$params"; ?>" scrolling="auto" name="msg" noresize marginwidth="2" marginheight="0">
   <frame src="chatlvinput.php?<?php echo $params ?>" name="input" scrolling="no" marginwidth="2" marginheight="1">
  </frameset>
  <frame src="<?php echo "http://$CFG->chatlv_serverhost:$CFG->chatlv_serverport?win=users&amp;$params"; ?>" name="users" scrolling="auto" marginwidth="5" marginheight="5">
 </frameset>
 <noframes>
  Sorry, this version of Moodle Chat needs a browser that handles frames.
 </noframes>
</html>
