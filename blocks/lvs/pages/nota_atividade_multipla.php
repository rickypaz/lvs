<?php
include_once('../../../config.php');
require_once('../biblioteca/CursoLV.php');
require_once('../biblioteca/lvometro.php');
require_once('Template.class.php');

$course_id = required_param('id', PARAM_INT);
$atividade_nome = required_param('atv', PARAM_ALPHANUMEXT);
$user_id = required_param('usuario', PARAM_INT);

if(!$course = $DB->get_record('course', array('id'=>$course_id))) {
	print_error("Invalid course id");
}

if(!$usuario = $DB->get_record('user', array('id'=>$user_id))) {
	print_error("Invalid user id");
}

require_login($course);

if (!has_capability('moodle/course:viewhiddenactivities', $PAGE->context) && $USER->id != $usuario->id) {
	throw new required_capability_exception($PAGE->context, 'moodle/course:viewhiddenactivities', 'nopermissions', '');
}

$cursoLV = new CursoLV($course->id);
$atividades = $DB->get_records($atividade_nome, array('course'=>$course->id), 'name ASC', 'id, name, exibir');
$nota_atividade_multipla_html = new Template('html/nota_atividade_multipla.html');
$notaslv = get_string('notaslvstarefa', 'block_lvs'); // FIXME 'Notas LV'

$PAGE->set_course($course);
$PAGE->set_url("/blocks/lvs/pages/notaslv_impressao.php?curso=$course_id");
$PAGE->set_title(format_string($course->fullname . ' : ' . $notaslv));
$PAGE->set_heading(format_string($course->fullname . ' : ' . $notaslv));

$qlv = 0;
foreach($atividades as $atividade) {
	if ($atividade->exibir == 1) {
		$avaliacao = $DB->get_record("lvs_$atividade_nome", array("id_$atividade_nome"=>$atividade->id, 'id_usuario'=>$usuario->id));
		$nota = 0;

		$nota_atividade_multipla_html->ALUNO_NOME = $usuario->firstname . " " . $usuario->lastname;
		$nota_atividade_multipla_html->ATIVIDADE_NOME = $atividade->name;

		$nota_atividade_multipla_html->CARINHAS_AZUIS = '-';
		$nota_atividade_multipla_html->CARINHAS_VERDES = '-';
		$nota_atividade_multipla_html->CARINHAS_AMARELAS = '-';
		$nota_atividade_multipla_html->CARINHAS_LARANJAS = '-';
		$nota_atividade_multipla_html->CARINHAS_VERMELHAS = '-';
		$nota_atividade_multipla_html->CARINHAS_NEUTRAS = '-';
		$nota_atividade_multipla_html->ATIVIDADE_NOTA = '-';
		$nota_atividade_multipla_html->ATIVIDADE_BETA = '-';

		if(!empty($avaliacao)) {
			$nota_atividade_multipla_html->CARINHAS_AZUIS = $avaliacao->numero_carinhas_azul;
			$nota_atividade_multipla_html->CARINHAS_VERDES = $avaliacao->numero_carinhas_verde;
			$nota_atividade_multipla_html->CARINHAS_AMARELAS = $avaliacao->numero_carinhas_amarela;
			$nota_atividade_multipla_html->CARINHAS_LARANJAS = $avaliacao->numero_carinhas_laranja;
			$nota_atividade_multipla_html->CARINHAS_VERMELHAS = $avaliacao->numero_carinhas_vermelha;
			$nota_atividade_multipla_html->CARINHAS_NEUTRAS = $avaliacao->numero_carinhas_preta;

			$nota_atividade_multipla_html->ATIVIDADE_NOTA = $avaliacao->modulo_vetor;
			$nota_atividade_multipla_html->ATIVIDADE_BETA = $avaliacao->beta;
			$nota = $avaliacao->modulo_vetor;
		}

		$nota_atividade_multipla_html->LVOMETRO_SRC = lvometro::retornaLvometro($nota);
		$nota_atividade_multipla_html->block('AVALIACAO_ATIVIDADE');
		$qlv++;
	}
}

if ($qlv == 0) {
	$nota_atividade_multipla_html->block('VISUALIZACAO_NAO_LIBERADA');
}

echo $OUTPUT->header();
$nota_atividade_multipla_html->show();
echo $OUTPUT->footer($course);