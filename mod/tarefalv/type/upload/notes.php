<?php

require('../../../../config.php');
require('../../lib.php');
require('tarefalv.class.php');

$id     = required_param('id', PARAM_INT);      // Course Module ID
$userid = required_param('userid', PARAM_INT);  // User ID
$offset = optional_param('offset', 0, PARAM_INT);
$mode   = optional_param('mode', '', PARAM_ALPHA);

$url = new moodle_url('/mod/tarefalv/type/online/file.php', array('id'=>$id, 'userid'=>$userid));
if ($offset !== 0) {
    $url->param('offset',$offset);
}
if ($mode !== 0) {
    $url->param('mode',$mode);
}
$PAGE->set_url($url);

if (! $cm = get_coursemodule_from_id('tarefalv', $id)) {
    print_error('invalidcoursemodule');
}

if (! $tarefalv = $DB->get_record('tarefalv', array('id'=>$cm->instance))) {
    print_error('invalidid', 'tarefalv');
}

if (! $course = $DB->get_record('course', array('id'=>$tarefalv->course))) {
    print_error('coursemisconf', 'tarefalv');
}

if (! $user = $DB->get_record('user', array('id'=>$userid))) {
    print_error("invaliduserid");
}

require_login($course, false, $cm);

if (!has_capability('mod/tarefalv:grade', context_module::instance($cm->id))) {
    print_error('cannotviewtarefalv', 'tarefalv');
}

if ($tarefalv->tarefalvtype != 'upload') {
    print_error('invalidtype', 'tarefalv');
}

$tarefalvinstance = new tarefalv_upload($cm->id, $tarefalv, $cm, $course);

$returnurl = "../../submissions.php?id={$tarefalvinstance->cm->id}&amp;userid=$userid&amp;offset=$offset&amp;mode=single";

if ($submission = $tarefalvinstance->get_submission($user->id)
  and !empty($submission->data1)) {
    $PAGE->set_title(fullname($user,true).': '.$tarefalv->name);
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('notes', 'tarefalv').' - '.fullname($user,true));
    echo $OUTPUT->box(format_text($submission->data1, FORMAT_HTML, array('overflowdiv'=>true)), 'generalbox boxaligncenter boxwidthwide');
    if ($mode != 'single') {
        echo $OUTPUT->close_window_button();
    } else {
        echo $OUTPUT->continue_button($returnurl);
    }
    echo $OUTPUT->footer();
} else {
    $PAGE->set_title(fullname($user,true).': '.$tarefalv->name);
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('notes', 'tarefalv').' - '.fullname($user,true));
    echo $OUTPUT->box(get_string('notesempty', 'tarefalv'), 'generalbox boxaligncenter boxwidthwide');
    if ($mode != 'single') {
        echo $OUTPUT->close_window_button();
    } else {
        echo $OUTPUT->continue_button($returnurl);
    }
    echo $OUTPUT->footer();
}