<?php
include('../../../config.php');
require_once $CFG->dirroot . '/blocks/lvs/biblioteca/lib.php';

use uab\ifce\lvs\moodle2\business\CursoLv;
use uab\ifce\lvs\moodle2\business\Quizzes;

require($CFG->dirroot.'/mod/tarefalv/lib.php');
require($CFG->dirroot.'/mod/tarefalv/type/upload/tarefalv.class.php');
// include_once($CFG->dirroot . '/blocks/lvs/biblioteca/tarefalv.php');	
	
$course_id = required_param('curso',PARAM_INT);
$quizzes_a_importar = $_POST['quiz'];

if (! $course = $DB->get_record('course', array('id'=>$course_id)) ) {
	error("Course is misconfigured");
}

require_login($course);
require_capability('moodle/course:viewhiddenactivities', $PAGE->context);

foreach ($quizzes_a_importar as $id => $dados) {
	if($dados['tipo'] == 0) {
		unset($quizzes_a_importar[$id]);
	}
}

$cursolv = new CursoLv($course->id);
$quizzes = new Quizzes($cursolv);

$quizzes->importarQuizzes($quizzes_a_importar);

$mensagem = get_string('alteracoes', 'block_lvs');
redirect("$CFG->wwwroot/blocks/lvs/pages/listar_quizzes.php?curso=$course->id", $mensagem);