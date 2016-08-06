<?php
include('../../../config.php');
require_once($CFG->dirroot . '/blocks/lvs/biblioteca/lib.php');

use uab\ifce\lvs\controllers\PresenciaisController;
use uab\ifce\lvs\moodle2\business\Moodle2CursoLv;
use uab\ifce\lvs\moodle2\controllers\Moodle2Controller;

$course_id = required_param('curso', PARAM_INT);
$atividade_id = required_param('id', PARAM_INT);
$atividades = (isset($_POST['atividade'])) ? $_POST['atividade'] : array();

if (!$course = $DB->get_record('course', array('id'=>$course_id)) ) {
	print_error('Invalid course id');
}

require_login($course);
require_capability('moodle/course:viewhiddenactivities', $PAGE->context);

$breadcumb = get_string('notaspresenciais', 'block_lvs');

$PAGE->set_course($course);
$PAGE->set_url("/blocks/lvs/pages/notas_presenciais.php?curso=$course->id&id=$atividade_id");
$PAGE->set_title(format_string($course->fullname . ' : ' . $breadcumb));
$PAGE->set_heading($course->fullname . ' : ' . $breadcumb);

$PAGE->navbar->add ( $breadcumb, new moodle_url("/blocks/lvs/pages/atividades_presenciais.php?curso=$course->id"), navigation_node::TYPE_CUSTOM );
$PAGE->navbar->add ( 'Lançar Notas / Frequência');
$PAGE->requires->js_init_call('M.block_lvs.npresencial');

$data['avaliacoes'] = $atividades;

$cursolv = new Moodle2CursoLv($course->id);
$presenciaisController = new PresenciaisController($cursolv);

$presenciaisController->setData($data);
$presenciaisController->setAdapterController(new Moodle2Controller());

$presenciaisController->lancarNotasPresenciais($atividade_id);
?>