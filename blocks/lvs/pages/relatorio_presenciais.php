<?php
require_once('../../../config.php');
require_once($CFG->dirroot . '/blocks/lvs/biblioteca/lib.php');

use uab\ifce\lvs\controllers\RelatorioController;
use uab\ifce\lvs\moodle2\business\Moodle2CursoLv;
use uab\ifce\lvs\moodle2\controllers\Moodle2Controller;

$course 	= required_param('curso', PARAM_INT);
$estudante  = required_param('usuario', PARAM_INT);

if (!$course = $DB->get_record('course', array('id'=>$course))) {
	print_error("Invalid course id");
}

if (!$estudante = $DB->get_record('user', array('id'=>$estudante))) {
	print_error("Invalid user id");
}

require_login($course);

if (!has_capability('moodle/course:viewhiddenactivities', $PAGE->context) && $USER->id != $estudante->id) {
	throw new required_capability_exception($PAGE->context, 'moodle/course:viewhiddenactivities', 'nopermissions', '');
}

$cursolv = new Moodle2CursoLv($course->id);
$relatorio_controller = new RelatorioController($cursolv);

$relatorio_controller->setAdapterController( new Moodle2Controller() );

$breadcumb = get_string('notaspresenciais', 'block_lvs');

$PAGE->set_course($course);
$PAGE->set_url("/blocks/lvs/pages/relatorio_presenciais.php?curso=$course->id&usuario=$estudante->id");
$PAGE->set_title(format_string($course->fullname . ' : ' . $breadcumb));
$PAGE->set_heading(format_string($course->fullname . ' : ' . $breadcumb));

$PAGE->navbar->add($breadcumb);

$relatorio_controller->desempenhoPresencial($estudante);
?>