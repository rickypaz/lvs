<?php

require_once("../../config.php");
require_once("lib.php");
require_once($CFG->libdir . '/completionlib.php');
require_once($CFG->libdir . '/plagiarismlib.php');

$id = optional_param('id', 0, PARAM_INT);  // Course Module ID
$a  = optional_param('a', 0, PARAM_INT);   // Assignment ID

$url = new moodle_url('/mod/tarefalv/view.php');
if ($id) {
    if (! $cm = get_coursemodule_from_id('tarefalv', $id)) {
        print_error('invalidcoursemodule');
    }

    if (! $tarefalv = $DB->get_record("tarefalv", array("id"=>$cm->instance))) {
        print_error('invalidid', 'tarefalv');
    }

    if (! $course = $DB->get_record("course", array("id"=>$tarefalv->course))) {
        print_error('coursemisconf', 'tarefalv');
    }
    $url->param('id', $id);
} else {
    if (!$tarefalv = $DB->get_record("tarefalv", array("id"=>$a))) {
        print_error('invalidid', 'tarefalv');
    }
    if (! $course = $DB->get_record("course", array("id"=>$tarefalv->course))) {
        print_error('coursemisconf', 'tarefalv');
    }
    if (! $cm = get_coursemodule_from_instance("tarefalv", $tarefalv->id, $course->id)) {
        print_error('invalidcoursemodule');
    }
    $url->param('a', $a);
}

$PAGE->set_url($url);
require_login($course, true, $cm);

$PAGE->requires->js('/mod/tarefalv/tarefalv.js');

require ("$CFG->dirroot/mod/tarefalv/type/$tarefalv->tarefalvtype/tarefalv.class.php");
$tarefalvclass = "tarefalv_$tarefalv->tarefalvtype";
$tarefalvinstance = new $tarefalvclass($cm->id, $tarefalv, $cm, $course);

/// Mark as viewed
$completion=new completion_info($course);
$completion->set_module_viewed($cm);

$tarefalvinstance->view();   // Actually display the tarefalv!