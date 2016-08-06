<?php

require_once('../../../config.php');
require_once('../lib.php');

$id      = required_param('id', PARAM_INT);
$groupid = optional_param('groupid', 0, PARAM_INT); //only for teachers

$url = new moodle_url('/mod/chatlv/gui_header_js/index.php', array('id'=>$id));
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

$context = context_module::instance($cm->id);

require_login($course, false, $cm);

require_capability('mod/chatlv:chatlv', $context);

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

if (!$chatlv_sid = chatlv_login_user($chatlv->id, 'header_js', $groupid, $course)) {
    print_error('cantlogin', 'chatlv');
}

$params = "chatlv_id=$id&chatlv_sid={$chatlv_sid}";

// fallback to the old jsupdate, but allow other update modes
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
   <?php echo "$strchatlv: " . $courseshortname . ": " . format_string($chatlv->name, true, array('context' => $context)) . "$groupname" ?>
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
  Sorry, this version of Moodle Chat needs a browser that handles frames.
 </noframes>
</html>
