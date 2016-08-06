<?php
require_once('../../../config.php');
require_once($CFG->dirroot . '/blocks/lvs/biblioteca/lib.php');
require_once(LVS_DIRROOT . '/biblioteca/dompdf/dompdf_config.inc.php');

use uab\ifce\lvs\Template;
use uab\ifce\lvs\controllers\RelatorioController;
use uab\ifce\lvs\moodle2\business\Moodle2CursoLv;
use uab\ifce\lvs\moodle2\controllers\Moodle2Controller;

$course_id  = required_param('curso', PARAM_INT);
$estudante  = required_param('usuario', PARAM_INT);

if(!$course = $DB->get_record('course', array('id'=>$course_id))) {
	print_error("Invalid course id");
}

require_login($course);

if(!has_capability('moodle/course:viewhiddenactivities', $PAGE->context) && $USER->id != $estudante) {
	throw new required_capability_exception($PAGE->context, 'moodle/course:viewhiddenactivities', 'nopermissions', '');
}

$cursolv = new Moodle2CursoLv($course->id);
$relatorio_faltas = new RelatorioController($cursolv);
$relatorio_faltas->setAdapterController( new Moodle2Controller() );

$breadcumb = get_string('faltaslv', 'block_lvs');

$PAGE->set_course($course);
$PAGE->set_url("/blocks/lvs/pages/relatorio_faltas.php?curso=$course_id");
$PAGE->set_title(format_string($course->fullname . ' : ' . $breadcumb));
$PAGE->set_heading(format_string($course->fullname . ' : ' . $breadcumb));

$PAGE->navbar->add($breadcumb);

echo $relatorio_faltas->faltasEstudante($estudante);
?>