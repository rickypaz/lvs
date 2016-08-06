<?php

//===================================================
// all.php
//
// Displays a complete list of online tarefalvs
// for the course. Rather like what happened in
// the old Journal activity.
// Howard Miller 2008
// See MDL-14045
//===================================================

require_once("../../../../config.php");
require_once("{$CFG->dirroot}/mod/tarefalv/lib.php");
require_once($CFG->libdir.'/gradelib.php');
require_once('tarefalv.class.php');

// get parameter
$id = required_param('id', PARAM_INT);   // course

if (!$course = $DB->get_record('course', array('id'=>$id))) {
    print_error('invalidcourse');
}

$PAGE->set_url('/mod/tarefalv/type/online/all.php', array('id'=>$id));

require_course_login($course);

// check for view capability at course level
$context = context_course::instance($course->id);
require_capability('mod/tarefalv:view',$context);

// various strings
$str = new stdClass;
$str->tarefalvs = get_string("modulenameplural", "tarefalv");
$str->duedate = get_string('duedate','tarefalv');
$str->duedateno = get_string('duedateno','tarefalv');
$str->editmysubmission = get_string('editmysubmission','tarefalv');
$str->emptysubmission = get_string('emptysubmission','tarefalv');
$str->notarefalvs = get_string('notarefalvs','tarefalv');
$str->onlinetext = get_string('typeonline','tarefalv');
$str->submitted = get_string('submitted','tarefalv');

$PAGE->navbar->add($str->tarefalvs, new moodle_url('/mod/tarefalv/index.php', array('id'=>$id)));
$PAGE->navbar->add($str->onlinetext);

// get all the tarefalvs in the course
$tarefalvs = get_all_instances_in_course('tarefalv',$course, $USER->id );

// array to hold display data
$views = array();

// loop over tarefalvs finding online ones
foreach( $tarefalvs as $tarefalv ) {
    // only interested in online tarefalvs
    if ($tarefalv->tarefalvtype != 'online') {
        continue;
    }

    // check we are allowed to view this
    $context = context_module::instance($tarefalv->coursemodule);
    if (!has_capability('mod/tarefalv:view',$context)) {
        continue;
    }

    // create instance of tarefalv class to get
    // submitted tarefalvs
    $onlineinstance = new tarefalv_online( $tarefalv->coursemodule );
    $submitted = $onlineinstance->submittedlink(true);
    $submission = $onlineinstance->get_submission();

    // submission (if there is one)
    if (empty($submission)) {
        $submissiontext = $str->emptysubmission;
        if (!empty($tarefalv->timedue)) {
            $submissiondate = "{$str->duedate} ".userdate( $tarefalv->timedue );

        } else {
            $submissiondate = $str->duedateno;
        }

    } else {
        $submissiontext = format_text( $submission->data1, $submission->data2 );
        $submissiondate  = "{$str->submitted} ".userdate( $submission->timemodified );
    }

    // edit link
    $editlink = "<a href=\"{$CFG->wwwroot}/mod/tarefalv/view.php?".
        "id={$tarefalv->coursemodule}&amp;edit=1\">{$str->editmysubmission}</a>";

    // format options for description
    $formatoptions = new stdClass;
    $formatoptions->noclean = true;

    // object to hold display data for tarefalv
    $view = new stdClass;

    // start to build view object
    $view->section = get_section_name($course, $tarefalv->section);

    $view->name = $tarefalv->name;
    $view->submitted = $submitted;
    $view->description = format_module_intro('tarefalv', $tarefalv, $tarefalv->coursemodule);
    $view->editlink = $editlink;
    $view->submissiontext = $submissiontext;
    $view->submissiondate = $submissiondate;
    $view->cm = $tarefalv->coursemodule;

    $views[] = $view;
}

//===================
// DISPLAY
//===================

$PAGE->set_title($str->tarefalvs);
echo $OUTPUT->header();

foreach ($views as $view) {
    echo $OUTPUT->container_start('clearfix generalbox tarefalv');

    // info bit
    echo $OUTPUT->heading("$view->section - $view->name", 3, 'mdl-left');
    if (!empty($view->submitted)) {
        echo '<div class="reportlink">'.$view->submitted.'</div>';
    }

    // description part
    echo '<div class="description">'.$view->description.'</div>';

    //submission part
    echo $OUTPUT->container_start('generalbox submission');
    echo '<div class="submissiondate">'.$view->submissiondate.'</div>';
    echo "<p class='no-overflow'>$view->submissiontext</p>\n";
    echo "<p>$view->editlink</p>\n";
    echo $OUTPUT->container_end();

    // feedback part
    $onlineinstance = new tarefalv_online( $view->cm );
    $onlineinstance->view_feedback();

    echo $OUTPUT->container_end();
}

echo $OUTPUT->footer();