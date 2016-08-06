<?php
require_once('../../../config.php');
require_once($CFG->dirroot . '/blocks/lvs/biblioteca/lib.php');
require_once(LVS_DIRROOT . '/biblioteca/dompdf/dompdf_config.inc.php');

use uab\ifce\lvs\Template;
use uab\ifce\lvs\controllers\RelatorioController;
use uab\ifce\lvs\moodle2\business\Moodle2CursoLv;
use uab\ifce\lvs\moodle2\controllers\Moodle2Controller;

$course_id  = required_param('curso', PARAM_INT);
$estudante  = optional_param('usuario', 0, PARAM_INT);
$tipo 		= optional_param('tipo', 'html', PARAM_ALPHA);

if (!$course = $DB->get_record('course', array('id'=>$course_id))) {
	print_error("Invalid course id");
}

require_login($course);

if (!has_capability('moodle/course:viewhiddenactivities', $PAGE->context) && $USER->id != $estudante && $tipo == 'html') {
	throw new required_capability_exception($PAGE->context, 'moodle/course:viewhiddenactivities', 'nopermissions', '');
}

$data['curso_ava'] = new stdClass();
$data['curso_ava']->id = $course->id;
$data['curso_ava']->nome = $course->fullname;
$data['curso_ava']->legenda = $course->shortname;
$data['curso_ava']->descricao = $course->summary;
$data['somenteLeitura'] = !has_capability('moodle/course:viewhiddenactivities', $PAGE->context);

$cursolv = new Moodle2CursoLv($course->id);
$relatorio_notas = new RelatorioController($cursolv);

$relatorio_notas->setAdapterController( new Moodle2Controller() );
$relatorio_notas->setData($data);

if ($tipo == 'html') {
	$breadcumb = get_string('notaslvs', 'block_lvs');
	
	$PAGE->set_course($course);
	$PAGE->set_url("/blocks/lvs/pages/relatorio_notas.php?curso=$course_id");
	$PAGE->set_title(format_string($course->fullname . ' : ' . $breadcumb));
	$PAGE->set_heading(format_string($course->fullname . ' : ' . $breadcumb));
	
	$PAGE->requires->js_init_call('M.block_lvs.gravaNotaFinal', array($CFG->wwwroot));
	
	$PAGE->navbar->add($breadcumb);
	
	if ($estudante != 0) {
		global $DB;
		$estudante = $DB->get_record('user', array('id'=>$estudante));
	}	
	$estudantes = (is_int($estudante)) ? array() : array($estudante);
	
	echo $relatorio_notas->desempenhoParticipantes($estudantes);
} else if ($tipo == 'pdf') {
	ini_set("memory_limit","100M");
	
	if ($estudante != 0) {
		global $DB;
		$estudante = $DB->get_record('user', array('id'=>$estudante));
	}
	$estudantes = (is_int($estudante)) ? null : array($estudante);
	
	$relatorio_notas->desempenhoParticipantesPdf($estudantes);
} else {
	throw new Exception($tipo . ' não suportado');
}
?>
