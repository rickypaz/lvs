<?php
include('../../../config.php');
include($CFG->dirroot . '/blocks/lvs/biblioteca/lib.php');

use uab\ifce\lvs\moodle2\business\Moodle2CursoLv;
use uab\ifce\lvs\controllers\DistanciaController;
use uab\ifce\lvs\moodle2\controllers\Moodle2Controller;

$course_id = required_param('curso', PARAM_INT);

if (! $course = $DB->get_record('course', array('id'=>$course_id)) ) {
	print_error('Invalid course id');
}

require_login($course);

$breadcumb = get_string('config_ativ_distancia', 'block_lvs');

$PAGE->set_course($course);
$PAGE->set_url("/blocks/lvs/pages/atividades_distancia.php?curso=$course->id");
$PAGE->set_title(format_string($course->fullname . ' : ' . $breadcumb));
$PAGE->set_heading($course->fullname . ' : ' . $breadcumb);

$PAGE->navbar->add($breadcumb);
$PAGE->requires->js_init_call('M.block_lvs.config_distancia', array($CFG->wwwroot));

$data['somente_leitura'] = !has_capability('moodle/course:viewhiddenactivities', $PAGE->context);
$data['atividade'] = (isset($_POST['atividade'])) ? $_POST['atividade'] : array();
$data['curso_ava'] = new stdClass();
$data['curso_ava']->id = $course_id;
  
$cursolv = new Moodle2CursoLv($course->id);
$distanciaController = new DistanciaController($cursolv);

$distanciaController->setAdapterController(new Moodle2Controller());
$distanciaController->setData($data);

$distanciaController->configurarAtividadesDistancia();
?>