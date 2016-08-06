<?php
use uab\ifce\lvs\Template;

include('../../../config.php');
require_once($CFG->dirroot . '/blocks/lvs/biblioteca/lib.php');

use uab\ifce\lvs\moodle2\business\CursoLv;
use uab\ifce\lvs\moodle2\business\Quizzes;
	
$course_id = required_param('curso',PARAM_INT);

if (! $course = $DB->get_record('course', array('id'=>$course_id)) ) {
	print_error('Invalid course id');
}

require_login($course);
require_capability('moodle/course:viewhiddenactivities', $PAGE->context);

$cursolv = new CursoLv($course->id);
$quizzes = new Quizzes($cursolv);
$template = new Template('html/listar_quizzes.html');
$notaslv = 'Importar Notas do Quiz'; // TODO por no get string

$PAGE->set_course($course);
$PAGE->set_url("/blocks/lvs/pages/listar_quizzes.php?curso=$course->id");
$PAGE->set_title(format_string($course->fullname . ' : ' . $notaslv));
$PAGE->set_heading($course->fullname . ' : ' . $notaslv);
		
$PAGE->navbar->add($notaslv);
	
$PAGE->requires->js('/blocks/lvs/pages/scripts/mask.js',true);
$PAGE->requires->js('/blocks/lvs/pages/scripts/form.js',true);
$PAGE->requires->js_init_call('M.block_lvs.config_distancia', array($CFG->wwwroot));
	
$quizzes_nao_importados = $quizzes->recuperarQuizzesNaoImportados();

$template->ACTION = 'importa_nota_quiz.php';
$template->CURSO = $course->id;

foreach($quizzes_nao_importados as $quiz) {
	$template->QUIZ_ID = $quiz->id;
	$template->block('QUIZ_INPUT_HIDDEN');
	
	$template->QUIZ_NOME = $quiz->name;
	$template->block('QUIZ');
}

if(empty($quizzes_nao_importados)) {
	$table->data[] = array('-','-','-','-');
}

$tableImportados = new html_table();
$tableImportados->head = array('Quizzes Importados');
$tableImportados->align = array("center");

$quizzes_importados = $quizzes->recuperarQuizzesImportados();

foreach($quizzes_importados as $quiz) {
// 	$tableImportados->data[] = 'hr';
// 	$remover = "<form name='rmquiz$quiz->id' action='remove_nota_quiz.php' method='post'>";
// 	$remover .= "<input type='hidden' name='id_curso' value='$course->id' />";
// 	$remover .= "<input type='hidden' name='id_quiz' value='$quiz->id' />";
// 	$remover .= "<input type='hidden' name='id_atividade' value='$quiz->id_atividade' />";
// 	$remover .= "<input type='hidden' name='distancia' value='$quiz->distancia' />";
// 	$remover .= "<input type='submit' value='Remover' />";
// 	$remover .= "</form>";
	
	$tableImportados->data[] = array($quiz->name);
}

if(empty($quizzes_importados)) {
	$tableImportados->data[] = array('-');
}

echo $OUTPUT->header();
echo '<br/><br/>';
$template->show();
echo '<br/><br/>';
echo html_writer::table($tableImportados);
echo "&nbsp;<input name='voltar' type='button' value='Voltar' onclick=\"location.href='$CFG->wwwroot/course/view.php?id=$course->id'\" >";
echo '<hr>';
echo $OUTPUT->footer();
?>