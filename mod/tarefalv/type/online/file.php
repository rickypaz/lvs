<?php

require("../../../../config.php");
require("../../lib.php");
require("tarefalv.class.php");

$id     = required_param('id', PARAM_INT);      // Course Module ID
$userid = required_param('userid', PARAM_INT);  // User ID

$PAGE->set_url('/mod/tarefalv/type/online/file.php', array('id'=>$id, 'userid'=>$userid));

if (! $cm = get_coursemodule_from_id('tarefalv', $id)) {
    print_error('invalidcoursemodule');
}

if (! $tarefalv = $DB->get_record("tarefalv", array("id"=>$cm->instance))) {
    print_error('invalidid', 'tarefalv');
}

if (! $course = $DB->get_record("course", array("id"=>$tarefalv->course))) {
    print_error('coursemisconf', 'tarefalv');
}

if (! $user = $DB->get_record("user", array("id"=>$userid))) {
    print_error('usermisconf', 'tarefalv');
}

require_login($course, false, $cm);

$context = context_module::instance($cm->id);
if (($USER->id != $user->id) && !has_capability('mod/tarefalv:grade', $context)) {
    print_error('cannotviewtarefalv', 'tarefalv');
}

if ($tarefalv->tarefalvtype != 'online') {
    print_error('invalidtype', 'tarefalv');
}

$tarefalvinstance = new tarefalv_online($cm->id, $tarefalv, $cm, $course);

if ($submission = $tarefalvinstance->get_submission($user->id)) {
    $PAGE->set_pagelayout('popup');
    $PAGE->set_title(fullname($user,true).': '.$tarefalv->name);
    echo $OUTPUT->header();
    echo $OUTPUT->box_start('generalbox boxaligcenter', 'dates');
    echo '<table>';
    if ($tarefalv->timedue) {
        echo '<tr><td class="c0">'.get_string('duedate','tarefalv').':</td>';
        echo '    <td class="c1">'.userdate($tarefalv->timedue).'</td></tr>';
    }
    echo '<tr><td class="c0">'.get_string('lastedited').':</td>';
    echo '    <td class="c1">'.userdate($submission->timemodified);
    /// Decide what to count
        if ($CFG->tarefalv_itemstocount == TAREFALV_COUNT_WORDS) {
            echo ' ('.get_string('numwords', '', count_words(format_text($submission->data1, $submission->data2))).')</td></tr>';
        } else if ($CFG->tarefalv_itemstocount == TAREFALV_COUNT_LETTERS) {
            echo ' ('.get_string('numletters', '', count_letters(format_text($submission->data1, $submission->data2))).')</td></tr>';
        }
    echo '</table>';
    echo $OUTPUT->box_end();

    $text = file_rewrite_pluginfile_urls($submission->data1, 'pluginfile.php', $context->id, 'mod_tarefalv', $tarefalvinstance->filearea, $submission->id);
    echo $OUTPUT->box(format_text($text, $submission->data2, array('overflowdiv'=>true)), 'generalbox boxaligncenter boxwidthwide');
    echo $OUTPUT->close_window_button();
    echo $OUTPUT->footer();
} else {
    print_string('emptysubmission', 'tarefalv');
}