<?php
require_once('../../../../config.php');
require_once($CFG->dirroot . '/blocks/lvs/biblioteca/lib.php');

use uab\ifce\lvs\moodle2\business\Moodle2CursoLv;

$course_id = required_param('curso', PARAM_INT);

if(!$course = $DB->get_record('course', array('id'=>$course_id))) {
	print_error("Invalid course id");
}

require_login($course);
require_capability('moodle/course:viewhiddenactivities', $PAGE->context);

$cursolv = new Moodle2CursoLv($course->id);
$notaslv = 'Prova Final'; // FIXME get_string('notaslvs', 'block_lvs');

$PAGE->set_course($course);
$PAGE->set_url("/course/view.php?id=$course->id");
$PAGE->set_title(format_string($course->fullname . ' : ' . $notaslv));
$PAGE->set_heading(format_string($course->fullname . ' : ' . $notaslv));

$notas = $_POST['nota']['usuario'];

foreach($notas as $estudante => $nota) {
	$avaliacao = new stdClass();
	$avaliacao->id_avaliado = $estudante;
	$avaliacao->nota = $nota;

	$cursolv->salvarAvaliacaoFinal($avaliacao);
}

redirect("$CFG->wwwroot/blocks/lvs/pages/relatorio_notas.php?curso=$course->id", get_string('alteracoes', 'block_lvs'), 1);