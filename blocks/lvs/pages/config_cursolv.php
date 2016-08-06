<?php
include('../../../config.php');
require_once($CFG->libdir . '/filelib.php');
require("../biblioteca/lib.php");
require("Template.class.php");

use uab\ifce\lvs\moodle2\business\Moodle2CursoLv;

$course_id = required_param('curso', PARAM_INT);
$user_id = optional_param('usuario', 0, PARAM_INT);
$dados_configuracao = optional_param('configcurso', NULL, PARAM_INT);

if (!$course = $DB->get_record('course', array('id'=>$course_id)) ) {
	print_error('Invalid course id');
}

require_login($course);

$cursoLV = new Moodle2CursoLv($course->id);
$configuracao_cursolv = $cursoLV->getConfiguracao();
$config_curso_html = new Template('html/config_cursolv.html');
$notaslv = get_string('notaslvs', 'block_lvs');

$PAGE->set_course($course);
$PAGE->set_url("/blocks/lvs/pages/notaslv_impressao.php?curso=$course_id");
$PAGE->set_title(format_string($course->fullname . ' : ' . $notaslv));
$PAGE->set_heading(format_string($course->fullname . ' : ' . $notaslv));

$PAGE->navbar->add($notaslv);

$PAGE->requires->js('/blocks/lvs/pages/scripts/mask.js',true);
$PAGE->requires->js('/blocks/lvs/pages/scripts/form.js',true);
$PAGE->requires->js_init_call('M.block_lvs.config_cursolv');

// 	$PAGE->requires->css('/blocks/lvs/pages/css/form.css');

if (!isset($dados_configuracao)) {
	$exibir_lv = (isset($configuracao_cursolv->exibelv) && $configuracao_cursolv->exibelv == 1) ? 'checked="checked"' : null;
	$data_limite = (isset($configuracao_cursolv->data_limite)) ? date("d/m/Y", $configuracao_cursolv->data_limite) : null;

	if(!empty($configuracao_cursolv)) {
		$config_curso_html->TOTAL_HORAS = $configuracao_cursolv->total_horas_curso;
		$config_curso_html->TOTAL_HORAS_PRESENCIAIS = $configuracao_cursolv->total_horas_presenciais;
		$config_curso_html->MEDIA = $configuracao_cursolv->media_curso;
		$config_curso_html->MEDIA_AF = $configuracao_cursolv->media_af;
		$config_curso_html->MEDIA_APROV_AF = $configuracao_cursolv->media_aprov_af;
		$config_curso_html->PERCENTUAL_FALTAS = $configuracao_cursolv->percentual_faltas;
		$config_curso_html->DATA_LIMITE = $data_limite;
		$config_curso_html->PORCENTAGEM_PRESENCIAL = $configuracao_cursolv->porcentagem_presencial;
		$config_curso_html->PORCENTAGEM_DISTANCIA = $configuracao_cursolv->porcentagem_distancia;
		$config_curso_html->EXIBIR_CHECKED = $exibir_lv;
	}

	if(!has_capability('moodle/course:viewhiddenactivities', $PAGE->context)) {
		$config_curso_html->READONLY = "readonly='readonly'";
		$config_curso_html->DISABLED = "disabled='disabled'";
	} else {
		$config_curso_html->block('GRAVAR');
	}

	echo $OUTPUT->header();
	$config_curso_html->show();
	echo '<br/><hr><br/>';
	echo $OUTPUT->footer();
} else {
	$configuracao_cursolv = $_POST['cursolv'];
		
	$data_nova = explode("/", $configuracao_cursolv['data_limite']);
	$data_timestamp = mktime(0, 0, 0, $data_nova[1], $data_nova[0], $data_nova[2]);

	$configuracao_cursolv['data_limite'] = $data_timestamp;
	$configuracao_cursolv['exibelv'] = (isset($configuracao_cursolv['exibelv'])) ? $configuracao_cursolv['exibelv'] : 0;

	if($cursoLV->setConfiguracao($configuracao_cursolv)) {
		redirect("$CFG->wwwroot/course/view.php?id=$course->id", get_string('alteracoes', 'block_lvs'));
	}

	$msgerror2 = get_string('erroempty2', 'lvs');
	print_error($msgerror2, '', "$CFG->wwwroot/blocks/lvs/pages/config_cursolv.php?curso=$course->id");

	// TODO tornar campos não-nulos no banco??
	// campos obrigatórios
	// !empty($qhorascurso) and !empty($qhoraspresenciais) and !empty($cursodatalimite) and ( $porc_presencial >= 0 ) and ( $porc_distancia >= 0 ) )) {
}