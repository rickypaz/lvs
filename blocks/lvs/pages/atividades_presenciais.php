<?php
include('../../../config.php');
require_once($CFG->dirroot . '/blocks/lvs/biblioteca/lib.php');

use uab\ifce\lvs\controllers\PresenciaisController;
use uab\ifce\lvs\moodle2\business\CursoLv;
use uab\ifce\lvs\moodle2\business\Moodle2CursoLv;
use uab\ifce\lvs\moodle2\controllers\Moodle2Controller;

$course_id = required_param('curso', PARAM_INT);

if(!$course = $DB->get_record('course', array('id'=>$course_id)) ) {
	print_error('Invalid course id');
}

require_login($course);
require_capability('moodle/course:viewhiddenactivities', $PAGE->context);

$breadcumb = get_string('notaspresenciais', 'block_lvs');

$PAGE->set_course($course);
$PAGE->set_url("/blocks/lvs/pages/atividades_presenciais.php?curso=$course->id");
$PAGE->set_title(format_string($course->fullname . ' : ' . $breadcumb));
$PAGE->set_heading(format_string($course->fullname . ' : ' . $breadcumb));
$PAGE->navbar->add($breadcumb);

$data['curso_ava'] = new stdClass();
$data['curso_ava']->id = $course_id;

$cursolv = new Moodle2CursoLv($course->id);
$presenciaisController = new PresenciaisController($cursolv);

$presenciaisController->setAdapterController(new Moodle2Controller());
$presenciaisController->setData($data);

$presenciaisController->exibirAtividadesPresenciais();
?>