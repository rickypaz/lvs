<?php

require_once('../../config.php');
require_once('lib.php');

$id = required_param('id', PARAM_INT);   // course

$PAGE->set_url('/mod/chatlv/index.php', array('id'=>$id));

if (! $course = $DB->get_record('course', array('id'=>$id))) {
    print_error('invalidcourseid');
}

require_course_login($course);
$PAGE->set_pagelayout('incourse');

add_to_log($course->id, 'chatlv', 'view all', "index.php?id=$course->id", '');


/// Get all required strings

$strsectionname = get_string('sectionname', 'format_'.$course->format);
$strchatlvs = get_string('modulenameplural', 'chatlv');
$strchatlv  = get_string('modulename', 'chatlv');


/// Print the header
$PAGE->navbar->add($strchatlvs);
$PAGE->set_title($strchatlvs);
$PAGE->set_heading($course->fullname);
echo $OUTPUT->header();

/// Get all the appropriate data

if (! $chatlvs = get_all_instances_in_course('chatlv', $course)) {
    notice(get_string('thereareno', 'moodle', $strchatlvs), "../../course/view.php?id=$course->id");
    die();
}

$usesections = course_format_uses_sections($course->format);

/// Print the list of instances (your module will probably extend this)

$timenow  = time();
$strname  = get_string('name');

$table = new html_table();

if ($usesections) {
    $table->head  = array ($strsectionname, $strname);
    $table->align = array ('center', 'left');
} else {
    $table->head  = array ($strname);
    $table->align = array ('left');
}

$currentsection = '';
foreach ($chatlvs as $chatlv) {
    if (!$chatlv->visible) {
        //Show dimmed if the mod is hidden
        $link = "<a class=\"dimmed\" href=\"view.php?id=$chatlv->coursemodule\">".format_string($chatlv->name,true)."</a>";
    } else {
        //Show normal if the mod is visible
        $link = "<a href=\"view.php?id=$chatlv->coursemodule\">".format_string($chatlv->name,true)."</a>";
    }
    $printsection = '';
    if ($chatlv->section !== $currentsection) {
        if ($chatlv->section) {
            $printsection = get_section_name($course, $chatlv->section);
        }
        if ($currentsection !== '') {
            $table->data[] = 'hr';
        }
        $currentsection = $chatlv->section;
    }
    if ($usesections) {
        $table->data[] = array ($printsection, $link);
    } else {
        $table->data[] = array ($link);
    }
}

echo '<br />';

echo html_writer::table($table);

/// Finish the page

echo $OUTPUT->footer();


