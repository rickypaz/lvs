<?php
include('../../../config.php');
require_once($CFG->dirroot . '/blocks/lvs/biblioteca/lib.php');

use uab\ifce\lvs\controllers\PresenciaisController;
use uab\ifce\lvs\moodle2\business\CursoLv;
use uab\ifce\lvs\moodle2\business\Moodle2CursoLv;
use uab\ifce\lvs\moodle2\controllers\Moodle2Controller;

$course_id = required_param('curso', PARAM_INT);
$atividades = (isset($_POST['presencial'])) ? $_POST['presencial'] : array();

if (!$course = $DB->get_record('course', array('id'=>$course_id)) ) {
	print_error('Invalid course id');
}

require_login($course);

$breadcumb = get_string('notaspresenciais', 'block_lvs');

$PAGE->set_course($course);
$PAGE->set_url("/blocks/lvs/pages/configuracao_presenciais.php?curso=$course->id");
$PAGE->set_title( format_string($course->fullname . ' : ' . get_string('config_ativ_presencial', 'block_lvs')) );
$PAGE->set_heading( $course->fullname . ' : ' . get_string('config_ativ_presencial', 'block_lvs') );

$PAGE->navbar->add ( $breadcumb, new moodle_url("/blocks/lvs/pages/atividades_presenciais.php?curso=$course->id"), navigation_node::TYPE_CUSTOM );
$PAGE->navbar->add ( 'Configuração');
$PAGE->requires->js_init_call('M.block_lvs.config_presencial', array($CFG->wwwroot));

$data['somente_leitura'] = !has_capability('moodle/course:viewhiddenactivities', $PAGE->context);
$data['atividades'] = $atividades;

$cursolv = new Moodle2CursoLv($course->id);
$presenciaisController = new PresenciaisController($cursolv);

$presenciaisController->setData($data);
$presenciaisController->setAdapterController(new Moodle2Controller());

$presenciaisController->configurarAtividadesPresenciais();
?>