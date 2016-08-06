<?php
include('../../../config.php');
require_once($CFG->dirroot . '/blocks/lvs/biblioteca/lib.php');

use uab\ifce\lvs\controllers\PresenciaisController;
use uab\ifce\lvs\moodle2\business\CursoLv;
use uab\ifce\lvs\moodle2\business\Moodle2CursoLv;
use uab\ifce\lvs\moodle2\controllers\Moodle2Controller;

$acao 			= required_param('acao', PARAM_ALPHA);
$course_id 		= required_param('curso', PARAM_INT);
$atividade_id 	= required_param('id', PARAM_INT);
$porcentagem 	= optional_param('porc', 0, PARAM_INT);

if (!$course = $DB->get_record('course', array('id'=>$course_id)) ) {
	print_error('Invalid course id');
}

require_login($course);
require_capability('moodle/course:viewhiddenactivities', $PAGE->context);

$breadcumb_presenciais  = get_string('notaspresenciais', 'block_lvs');
$breadcumb_configuracao = get_string('config_ativ_presencial', 'block_lvs');

$PAGE->set_course($course);
$PAGE->set_url("/blocks/lvs/pages/edicao_presencial.php?curso=$course->id");
$PAGE->set_title(format_string($course->fullname . ' : ' . $breadcumb_configuracao));
$PAGE->set_heading($course->fullname);

$PAGE->navbar->add($breadcumb_presenciais, new moodle_url("/blocks/lvs/pages/atividades_presenciais.php?curso=$course->id"), navigation_node::TYPE_CUSTOM );
$PAGE->navbar->add($breadcumb_configuracao, new moodle_url("/blocks/lvs/pages/configuracao_presenciais.php?curso=$course->id"), navigation_node::TYPE_CUSTOM );

$data['atividade'] = (isset($_POST['atividade'])) ? $_POST['atividade'] : array();

$cursolv = new Moodle2CursoLv($course->id);
$presenciaisController = new PresenciaisController($cursolv);

$presenciaisController->setAdapterController(new Moodle2Controller());

if($acao == 'd') {
	$presenciaisController->removerAtividadePresencial($atividade_id);
} else if($acao == 'e') {
	$PAGE->requires->js_init_call('M.block_lv.config_presencial_edit');
	$presenciaisController->setData($data);
	$presenciaisController->editarAtividadePresencial($atividade_id);
} else {
	throw new Exception('Opção de ação (' . $acao . ') Inválida');
}
?>