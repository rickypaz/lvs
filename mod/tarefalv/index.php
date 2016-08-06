<?php

require_once("../../config.php");
require_once("lib.php");
require_once($CFG->libdir.'/gradelib.php');

$id = required_param('id', PARAM_INT);   // course

if (!$course = $DB->get_record('course', array('id'=>$id))) {
    print_error('invalidcourseid');
}

require_course_login($course);
$PAGE->set_pagelayout('incourse');

add_to_log($course->id, "tarefalv", "view all", "index.php?id=$course->id", "");

$strtarefalvs = get_string("modulenameplural", "tarefalv");
$strtarefalv = get_string("modulename", "tarefalv");
$strtarefalvtype = get_string("tarefalvtype", "tarefalv");
$strsectionname  = get_string('sectionname', 'format_'.$course->format);
$strname = get_string("name");
$strduedate = get_string("duedate", "tarefalv");
$strsubmitted = get_string("submitted", "tarefalv");
$strgrade = get_string("grade");


$PAGE->set_url('/mod/tarefalv/index.php', array('id'=>$course->id));
$PAGE->navbar->add($strtarefalvs);
$PAGE->set_title($strtarefalvs);
$PAGE->set_heading($course->fullname);
echo $OUTPUT->header();

if (!$cms = get_coursemodules_in_course('tarefalv', $course->id, 'cm.idnumber, m.tarefalvtype, m.timedue')) {
    notice(get_string('notarefalvs', 'tarefalv'), "../../course/view.php?id=$course->id");
    die;
}

$usesections = course_format_uses_sections($course->format);

$timenow = time();

$table = new html_table();

if ($usesections) {
    $table->head  = array ($strsectionname, $strname, $strtarefalvtype, $strduedate, $strsubmitted, $strgrade);
} else {
    $table->head  = array ($strname, $strtarefalvtype, $strduedate, $strsubmitted, $strgrade);
}

$currentsection = "";

$types = tarefalv_types();

$modinfo = get_fast_modinfo($course);
foreach ($modinfo->instances['tarefalv'] as $cm) {
    if (!$cm->uservisible) {
        continue;
    }

    $cm->timedue        = $cms[$cm->id]->timedue;
    $cm->tarefalvtype = $cms[$cm->id]->tarefalvtype;
    $cm->idnumber       = $cms[$cm->id]->idnumber;

    //Show dimmed if the mod is hidden
    $class = $cm->visible ? '' : 'class="dimmed"';

    $link = "<a $class href=\"view.php?id=$cm->id\">".format_string($cm->name)."</a>";

    $printsection = "";
    if ($usesections) {
        if ($cm->sectionnum !== $currentsection) {
            if ($cm->sectionnum) {
                $printsection = get_section_name($course, $cm->sectionnum);
            }
            if ($currentsection !== "") {
                $table->data[] = 'hr';
            }
            $currentsection = $cm->sectionnum;
        }
    }

    if (!file_exists($CFG->dirroot.'/mod/tarefalv/type/'.$cm->tarefalvtype.'/tarefalv.class.php')) {
        continue;
    }

    require_once ($CFG->dirroot.'/mod/tarefalv/type/'.$cm->tarefalvtype.'/tarefalv.class.php');
    $tarefalvclass = 'tarefalv_'.$cm->tarefalvtype;
    $tarefalvinstance = new $tarefalvclass($cm->id, NULL, $cm, $course);

    $submitted = $tarefalvinstance->submittedlink(true);

    $grading_info = grade_get_grades($course->id, 'mod', 'tarefalv', $cm->instance, $USER->id);
    if (isset($grading_info->items[0]) && !$grading_info->items[0]->grades[$USER->id]->hidden ) {
        $grade = $grading_info->items[0]->grades[$USER->id]->str_grade;
    }
    else {
        $grade = '-';
    }

    $type = $types[$cm->tarefalvtype];

    // if type has an 'all.php' defined, make this a link
    $pathtoall = "{$CFG->dirroot}/mod/tarefalv/type/{$cm->tarefalvtype}/all.php";
    if (file_exists($pathtoall)) {
        $type = "<a href=\"{$CFG->wwwroot}/mod/tarefalv/type/{$cm->tarefalvtype}/".
            "all.php?id={$course->id}\">$type</a>";
    }

    $due = $cm->timedue ? userdate($cm->timedue) : '-';

    if ($usesections) {
        $table->data[] = array ($printsection, $link, $type, $due, $submitted, $grade);
    } else {
        $table->data[] = array ($link, $type, $due, $submitted, $grade);
    }
}

echo "<br />";

echo html_writer::table($table);

echo $OUTPUT->footer();
