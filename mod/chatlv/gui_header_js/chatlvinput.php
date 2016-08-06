<?php

define('NO_MOODLE_COOKIES', true); // session not used here

require_once('../../../config.php');
require_once($CFG->dirroot.'/mod/chatlv/lib.php');

$chatlv_sid = required_param('chatlv_sid', PARAM_ALPHANUM);
$chatlvid   = required_param('chatlv_id', PARAM_INT);

if (!$chatlvuser = $DB->get_record('chatlv_users', array('sid'=>$chatlv_sid))) {
    print_error('notlogged', 'chatlv');
}
if (!$chatlv = $DB->get_record('chatlv', array('id'=>$chatlvid))) {
    print_error('invalidid', 'chatlv');
}

if (!$course = $DB->get_record('course', array('id'=>$chatlv->course))) {
    print_error('invalidcourseid');
}

if (!$cm = get_coursemodule_from_instance('chatlv', $chatlv->id, $course->id)) {
    print_error('invalidcoursemodule');
}

$PAGE->set_url('/mod/chatlv/gui_header_js/chatlvinput.php', array('chatlv_sid'=>$chatlv_sid, 'chatlv_id'=>$chatlvid));
$PAGE->set_popup_notification_allowed(false);

//Get the user theme
$USER = $DB->get_record('user', array('id'=>$chatlvuser->userid));


$module = array(
    'name'      => 'mod_chatlv_header',
    'fullpath'  => '/mod/chatlv/gui_header_js/module.js',
    'requires'  => array('node')
);
$PAGE->requires->js_init_call('M.mod_chatlv_header.init_input', array(false), false, $module);

//Setup course, lang and theme
$PAGE->set_course($course);
$PAGE->set_pagelayout('embedded');
$PAGE->set_focuscontrol('input_chatlv_message');
$PAGE->set_cacheable(false);
echo $OUTPUT->header();

echo html_writer::start_tag('form', array('action'=>'../empty.php', 'method'=>'post', 'target'=>'empty', 'id'=>'inputForm', 'style'=>'margin:0'));
echo html_writer::label(get_string('entermessage', 'chatlv'), 'input_chatlv_message', false, array('class' => 'accesshide'));
echo html_writer::empty_tag('input', array('type'=>'text', 'id'=>'input_chatlv_message', 'name'=>'chatlv_message', 'size'=>'50', 'value'=>''));
echo html_writer::empty_tag('input', array('type'=>'checkbox', 'id'=>'auto', 'checked'=>'checked', 'value'=>''));
echo html_writer::tag('label', get_string('autoscroll', 'chatlv'), array('for'=>'auto'));
echo $OUTPUT->help_icon('usingchatlv', 'chatlv');
echo html_writer::end_tag('form');

echo html_writer::start_tag('form', array('action'=>'insert.php', 'method'=>'post', 'target'=>'empty', 'id'=>'sendForm'));
echo html_writer::empty_tag('input', array('type'=>'hidden', 'name'=>'chatlv_sid', 'value'=>$chatlv_sid));
echo html_writer::empty_tag('input', array('type'=>'hidden', 'name'=>'chatlv_message', 'id'=>'insert_chatlv_message'));
echo html_writer::end_tag('form');

echo $OUTPUT->footer();
