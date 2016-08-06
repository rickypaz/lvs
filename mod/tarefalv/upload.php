<?php

require_once("../../config.php");
require_once("lib.php");

$id = optional_param('id', 0, PARAM_INT);  // Course module ID
$a  = optional_param('a', 0, PARAM_INT);   // Assignment ID

$url = new moodle_url('/mod/tarefalv/upload.php');
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
        print_error('invalidcoursemodule');
    }
    if (! $course = $DB->get_record("course", array("id"=>$tarefalv->course))) {
        print_error('invalidid', 'tarefalv');
    }
    if (! $cm = get_coursemodule_from_instance("tarefalv", $tarefalv->id, $course->id)) {
        print_error('invalidcoursemodule', 'tarefalv');
    }
    $url->param('a', $a);
}

$PAGE->set_url($url);
require_login($course, false, $cm);

/// Load up the required tarefalv code
require_once($CFG->dirroot.'/mod/tarefalv/type/'.$tarefalv->tarefalvtype.'/tarefalv.class.php');
$tarefalvclass = 'tarefalv_'.$tarefalv->tarefalvtype;
$tarefalvinstance = new $tarefalvclass($cm->id, $tarefalv, $cm, $course);

$tarefalvinstance->upload();   // Upload files