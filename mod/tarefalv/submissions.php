<?php

require_once("../../config.php");
require_once("lib.php");
require_once($CFG->libdir.'/plagiarismlib.php');

$id   = optional_param('id', 0, PARAM_INT);          // Course module ID
$a    = optional_param('a', 0, PARAM_INT);           // Assignment ID
$mode = optional_param('mode', 'all', PARAM_ALPHA);  // What mode are we in?
$download = optional_param('download' , 'none', PARAM_ALPHA); //ZIP download asked for?

$url = new moodle_url('/mod/tarefalv/submissions.php');
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
        print_error('coursemisconf', 'tarefalv');
    }
    if (! $cm = get_coursemodule_from_instance("tarefalv", $tarefalv->id, $course->id)) {
        print_error('invalidcoursemodule');
    }
    $url->param('a', $a);
}

if ($mode !== 'all') {
    $url->param('mode', $mode);
}
$PAGE->set_url($url);
require_login($course, false, $cm);

require_capability('mod/tarefalv:grade', context_module::instance($cm->id));

$PAGE->requires->js('/mod/tarefalv/tarefalv.js');

/// Load up the required tarefalv code
require($CFG->dirroot.'/mod/tarefalv/type/'.$tarefalv->tarefalvtype.'/tarefalv.class.php');
$tarefalvclass = 'tarefalv_'.$tarefalv->tarefalvtype;
$tarefalvinstance = new $tarefalvclass($cm->id, $tarefalv, $cm, $course);

if($download == "zip") {
    $tarefalvinstance->download_submissions();
} else {
    $tarefalvinstance->submissions($mode);   // Display or process the submissions
}