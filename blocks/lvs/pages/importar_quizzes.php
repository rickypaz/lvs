<?php
include('../../../config.php');
require ($CFG->dirroot . '/blocks/lvs/biblioteca/lib.php');

use uab\ifce\lvs\controllers\QuizzesController;
use uab\ifce\lvs\moodle2\business\Moodle2CursoLv;
use uab\ifce\lvs\moodle2\controllers\Moodle2Controller;

global $COURSE;
	
$course_id = required_param('curso',PARAM_INT);

if(! $course = $DB->get_record('course', array('id'=>$course_id)) ) {
	print_error('Invalid course id');
}

require_login($course);
require_capability('moodle/course:viewhiddenactivities', $PAGE->context);

$breadcumb  = 'Importar Notas do Quiz'; // TODO por no get string

$PAGE->set_course($course);
$PAGE->set_url("/blocks/lvs/pages/listar_quizzes.php?curso=$course->id");
$PAGE->set_title(format_string($course->fullname . ' : ' . $breadcumb));
$PAGE->set_heading($course->fullname . ' : ' . $breadcumb);
		
$PAGE->navbar->add($breadcumb);
$PAGE->requires->js_init_call('M.block_lvs.importar_quizzes', array($CFG->wwwroot));

$data['atividades'] = $_POST;
$data['curso_ava'] = new stdClass();
$data['curso_ava']->id = $course->id;

$cursolv  = new Moodle2CursoLv($course->id);
$quizzesController = new QuizzesController($cursolv);

$quizzesController->setData($data);
$quizzesController->setAdapterController(new Moodle2Controller());

$quizzesController->importarQuizzes();
?>
