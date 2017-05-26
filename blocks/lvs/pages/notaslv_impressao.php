<?php
include_once('../../../config.php');
require_once("../biblioteca/dompdf/dompdf_config.inc.php");
require_once('../biblioteca/CursoLV.php');
require_once('Template.class.php');

$course_id = required_param('curso', PARAM_INT);
$user_id = optional_param('usuario', 0, PARAM_INT);

if(!$course = $DB->get_record('course', array('id'=>$course_id))) {
	print_error("Invalid course id");
}

require_login($course);

$cursolv = new CursoLV($course->id);
// $cursolv->calcularPorcentagemAtividades();
$cursolv->removerNotasDeAtividadesExcluidas();

if (! $cursolv->getConfiguracao()) {
	redirect("$CFG->wwwroot/blocks/lvs/pages/config_cursolv.php?curso=$course->id", "Preencha as informações do curso !");
}

$notaslv_impressao_html = new Template('html/notaslv_impressao.html');
$notaslv = get_string('notaslvs', 'block_lvs');

$PAGE->set_course($course);
$PAGE->set_url("/blocks/lvs/pages/notaslv_impressao.php?curso=$course_id");
$PAGE->set_title(format_string($course->fullname . ' : ' . $notaslv));
$PAGE->set_heading(format_string($course->fullname . ' : ' . $notaslv));

$PAGE->navbar->add($notaslv);

$PAGE->requires->css('/blocks/lvs/biblioteca/dompdf/css/table.css');

$filepath = "../biblioteca/dompdf/template/$USER->id/";

if (!is_dir($filepath))
	mkdir($filepath);

if (file_exists($filepath . $USER->id . ".html")) {
	unlink($filepath . $USER->id . ".html");
}

$users = null;

if (empty($user_id) && has_capability('moodle/course:viewhiddenactivities', $PAGE->context)) {
	$sqlusers = "SELECT * FROM {role_assignments} r,{user} u
	WHERE r.contextid = ? AND u.id = r.userid AND r.roleid =5
	ORDER BY u.firstname";
	$users = $DB->get_records_sql($sqlusers, array($PAGE->context->id));
} else if($USER->id == $user_id || has_capability('moodle/course:viewhiddenactivities', $PAGE->context)) {
	$sqlusers = "SELECT * FROM {role_assignments} r,{user} u
	WHERE r.contextid = ? AND u.id = r.userid AND r.roleid =5 AND u.id = ?
	ORDER BY u.firstname";
	$users = $DB->get_records_sql($sqlusers, array($PAGE->context->id, $user_id));
} else {
	throw new required_capability_exception($PAGE->context, 'moodle/course:viewhiddenactivities', 'nopermissions', '');
}

$notaslv_impressao_html->LINK_EXPORTAR = "../biblioteca/dompdf/dompdf.php?base_path=template%2F$USER->id%2F&input_file=" . rawurlencode("$USER->id.html");
$notaslv_impressao_html->COURSE_FULLNAME = $course->fullname;
$notaslv_impressao_html->COURSE_SHORTNAME = $course->shortname;
$notaslv_impressao_html->COURSE_SUMMARY = $course->summary;

$i = 0;
$nusers = 30;
$totalusers = count($users);
$isAF = false;

foreach ($users as $user) {
	$userid = $user->userid;
	$desempenho = $cursolv->getDesempenho($userid);

	$notaslv_impressao_html->USER_FIRSTNAME = $user->firstname;
	$notaslv_impressao_html->USER_LASTNAME = $user->lastname;

	if ($i % 2 == 0) {
		$notaslv_impressao_html->CLASS_ALUNO = "list_row";
	} else {
		$notaslv_impressao_html->CLASS_ALUNO = "list_row_color";
	}

	$notaslv_impressao_html->IMAGE_ATIVIDADE = "$CFG->wwwroot/blocks/lvs/pages/imgs/icons/popup.gif";

	$notaslv_impressao_html->NOTA_ATIVIDADE = $desempenho->nf;
	$notaslv_impressao_html->TARGET_ATIVIDADE = 'flv';
	$notaslv_impressao_html->LINK_AVALIACOES_USUARIO_ATIVIDADE = "nota_atividade_multipla.php?id=$course->id&usuario=$userid&atv=forumlv";
	$notaslv_impressao_html->AUSENCIAS_ATIVIDADE = $desempenho->aaf;
	$notaslv_impressao_html->block('ATIVIDADE_DISTANCIA');

	$notaslv_impressao_html->NOTA_ATIVIDADE = $desempenho->nt;
	$notaslv_impressao_html->TARGET_ATIVIDADE = 'tlv';
	$notaslv_impressao_html->LINK_AVALIACOES_USUARIO_ATIVIDADE = "nota_atividade_multipla.php?id=$course->id&usuario=$userid&atv=tarefalv";
	$notaslv_impressao_html->AUSENCIAS_ATIVIDADE = $desempenho->aat;
	$notaslv_impressao_html->block('ATIVIDADE_DISTANCIA');

	// 		$notaslv_impressao_html->NOTA_ATIVIDADE = $desempenho->nc;
	// 		$notaslv_impressao_html->TARGET_ATIVIDADE = 'clv';
	// 		$notaslv_impressao_html->LINK_AVALIACOES_USUARIO_ATIVIDADE = "nota_atividade_multipla.php?id=$course->id&usuario=$userid&atv=chatlv";
	// 		$notaslv_impressao_html->AUSENCIAS_ATIVIDADE = $desempenho->aac;
	// 		$notaslv_impressao_html->block('ATIVIDADE_DISTANCIA');

	// 		$notaslv_impressao_html->NOTA_ATIVIDADE = $desempenho->nw;
	// 		$notaslv_impressao_html->TARGET_ATIVIDADE = 'wlv';
	// 		$notaslv_impressao_html->LINK_AVALIACOES_USUARIO_ATIVIDADE = "nota_atividade_multipla.php?id=$course->id&usuario=$userid&atv=wikilv";
	// 		$notaslv_impressao_html->AUSENCIAS_ATIVIDADE = $desempenho->aaw;
	// 		$notaslv_impressao_html->block('ATIVIDADE_DISTANCIA');

	$notaslv_impressao_html->NOTA_DISTANCIA = $desempenho->nd;

	$notaslv_impressao_html->NOTA_ATIVIDADE = $desempenho->np;
	$notaslv_impressao_html->TARGET_ATIVIDADE = 'tplv';
	$notaslv_impressao_html->LINK_AVALIACOES_USUARIO_ATIVIDADE = "notas_atividades_presenciais.php?id=$course->id&usuario=$userid";
	$notaslv_impressao_html->AUSENCIAS_ATIVIDADE = $desempenho->aap;
	$notaslv_impressao_html->block('ATIVIDADE_PRESENCIAL');

	$notaslv_impressao_html->MEDIA = $desempenho->media_parcial;
	$notaslv_impressao_html->TOTAL_FALTAS = $desempenho->ntf;
	$notaslv_impressao_html->FATOR_BETA = $desempenho->beta;
		
	if($desempenho->situacao == 'C' && !has_capability('moodle/course:viewhiddenactivities', $PAGE->context, $USER->id)){
		$notaslv_impressao_html->IMAGEM_LV_ICONE = "<img src='$CFG->wwwroot/blocks/lvs/imgs/carinhas/cursando.gif'/>";
	} else {
		$notaslv_impressao_html->IMAGEM_LV_ICONE = $desempenho->lv_icone;
	}
		
	$media_final = ($desempenho->media_final == NULL && $desempenho->af == NULL)? $desempenho->media_parcial : $desempenho->media_final;
	$notaslv_impressao_html->MEDIA_FINAL = round($media_final, 1);
		
	$notaslv_impressao_html->NOTA_AF = $desempenho->situacao;
		
	if (($desempenho->situacao == 'AF' && has_capability('moodle/course:viewhiddenactivities', $PAGE->context, $USER->id)) || (has_capability('moodle/course:viewhiddenactivities', $PAGE->context, $USER->id) && $desempenho->af !== NULL)) {
		$isAF = true;
		$notaslv_impressao_html->NOTA_AF = ($desempenho->af == NULL)? 0 : $desempenho->af;
		$notaslv_impressao_html->USER_ID = $userid;
		$notaslv_impressao_html->block('AF');
	} else {
		$notaslv_impressao_html->block('AM');
	}

	$notaslv_impressao_html->SITUACAO = $desempenho->situacao;
	$notaslv_impressao_html->block('ALUNO');
		
	$i++;
}

if (($isAF && has_capability('moodle/course:viewhiddenactivities', $PAGE->context, $USER->id))) {
	$notaslv_impressao_html->block('SALVAR_NOTAS_AF');

	$notaslv_impressao_html->FORM_ACTION = "grava_av_final.php?curso=$course->id";
	$notaslv_impressao_html->block('INIT_FORM_AF');

	$notaslv_impressao_html->block('END_FORM_AF');
}

$notaslv_impressao_html->BETA_MEDIO = $cursolv->getBetaMedio();
$notaslv_impressao_html->MEDIA_CURSO = $cursolv->getConfiguracao()->media_curso;
$notaslv_impressao_html->MEDIA_APROVACAO_AF_CURSO = $cursolv->getConfiguracao()->media_aprov_af;
$notaslv_impressao_html->MEDIA_AF_CURSO = $cursolv->getConfiguracao()->media_af;
$notaslv_impressao_html->PERCENTUAL_FALTAS_CURSO = $cursolv->getConfiguracao()->percentual_faltas;
$notaslv_impressao_html->IMAGE_TABELA_BETA = "$CFG->wwwroot/blocks/lvs/pages/imgs/tabelabeta.png";

if ($cursolv->getConfiguracao()->exibelv == 1)
	$notaslv_impressao_html->block('PREPARAR_EXPORTACAO');

echo $OUTPUT->header();
$notaslv_impressao_html->show();

$notaslv_impressao_html->IMAGE_PDF = "../../../../imgs/tabelabeta.png"; // FIXME
$notaslv_impressao_html->block('LEGENDA_CARINHAS');
$notaslv_impressao_html->block('SPACE');

// FIXME VERSÃO PARA IMPRESSÃO TEM PROBLEMAS NA EXIBIÇÃO DE IMAGENS!!
$versao_impressao = $notaslv_impressao_html->parse();
$versao_impressao = str_replace("<br/><center><a href='$notaslv_impressao_html->LINK_EXPORTAR'>Exportar Para PDF</a><br/><br/>", '', $versao_impressao);
$versao_impressao = str_replace('<table class="detail" style="margin: 0px; border-top: none;"><tr><td class="label">&beta; Médio:</td><td class=\"field\">{BETA_MEDIO}</td></tr></table>', '', $versao_impressao);
$versao_impressao = str_replace("<br/><a href='#' style='float: right;' onclick=\"if(confirm('Antes de prosseguir com essa ação, certifique-se que todas as notas das atividades a distância e presenciais, incluindo segundas chamadas, bem como frequ&ecirc;ncias, foram devidamente lan&ccedil;adas .')) alert('Notas prontas para exportação !')\">Preparar exportação para o bloco de notas Moodle.</a><br/>", '', $versao_impressao);
$versao_impressao = str_replace("<img src='http://localhost/moodle2.1.2/blocks/lvs/pages/imgs/icons/popup.gif'>", '', $versao_impressao);
$versao_impressao = str_replace("<td class='center' style='border-width: 1'><img style='height: 188; width: 328;' src='$notaslv_impressao_html->IMAGE_TABELA_BETA' alt='' /></td>", '', $versao_impressao);
$versao_impressao = str_replace("$CFG->wwwroot/blocks/lvs/imgs/carinhas/", '../../../../imgs/carinhas/', $versao_impressao);

$versao_impressao = str_replace("display:none;", '', $versao_impressao);
$versao_impressao = str_replace("Fator &beta;", 'Beta', $versao_impressao);
?>

<?php
// TENTAR NÃO CRIAR OS TEMPLATES SEMPRE, E EXCLUIR OS CRIADOS DE TEMPOS EM TEMPOS
$fp = fopen("../biblioteca/dompdf/template/$USER->id/" . $USER->id . ".html", "a");
$head = '<?php ini_set("memory_limit","100M"); ?>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
<link rel="stylesheet" href="../../css/table.css" type="text/css" />
</head>
<body class="page">';
$script = '<script type="text/php">

if ( isset($pdf) ) {

$font = Font_Metrics::get_font("helvetica");
$size = 6;
$color = array(0,0,0);
$text_height = Font_Metrics::get_font_height($font, $size);

$foot = $pdf->open_object();

$w = $pdf->get_width();
$h = $pdf->get_height();

// Draw a line along the bottom
$y = $h - 2.5 * $text_height - 24;
$pdf->line(16, $y, $w - 16, $y, array(0,0,0), 1);

$y += $text_height;

// Add the job number
$text = "Learning Vectors";
$pdf->text(16, $y, $text, $font, $size, $color);

$pdf->close_object();
$pdf->add_object($foot, "all");


$text = "Página {PAGE_NUM} de {PAGE_COUNT}";
$width = Font_Metrics::get_text_width("Página 1 de 2", $font, $size);

$pdf->page_text($w / 2 - $width / 2, $y, $text, $font, $size, $color);

}
</script>';
$foot = '</body></html>';
fwrite($fp, $head . $script . $versao_impressao . $foot);
fclose($fp);
?>