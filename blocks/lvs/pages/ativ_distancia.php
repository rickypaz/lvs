<?php
use uab\ifce\lvs\moodle2\business\CursoLv as CursoMoodle2;

use uab\ifce\lvs\moodle2\business\WikisLv;

include('../../../config.php');
include($CFG->dirroot . '/blocks/lvs/biblioteca/lib.php');
require_once('../biblioteca/CursoLV.php');
require_once('../biblioteca/ForumLV.php');
require_once('../biblioteca/TarefaLV.php');
require_once('Template.class.php');

$course_id = required_param('curso', PARAM_INT);

if (! $course = $DB->get_record('course', array('id'=>$course_id)) ) {
	print_error('Invalid course id');
}

require_login($course);
require_capability('moodle/course:viewhiddenactivities', $PAGE->context);

$cursolv = new CursoMoodle2($course->id);

if (! $cursolv->getConfiguracao()) {
	redirect("$CFG->wwwroot/blocks/lvs/pages/config_cursolv.php?curso=$course->id", "Preencha as informações do curso !");
}
$forumlv = new ForumLV($course->id);
$tarefalv = new TarefaLV($course->id);
$wikislvs = new WikisLv($cursolv);
$ativ_presencial_html = new Template('html/ativ_distancia.html');

$notaslv = get_string('config_ativ_distancia', 'block_lvs');

$PAGE->set_course($course);
$PAGE->set_url("/blocks/lvs/pages/ativ_distancia.php?curso=$course->id");
$PAGE->set_title(format_string($course->fullname . ' : ' . $notaslv));
$PAGE->set_heading($course->fullname . ' : ' . $notaslv);

$PAGE->navbar->add($notaslv);

$PAGE->requires->js('/blocks/lvs/pages/scripts/mask.js',true);
$PAGE->requires->js('/blocks/lvs/pages/scripts/form.js',true);
$PAGE->requires->js_init_call('M.block_lvs.config_distancia', array($CFG->wwwroot));

$PAGE->requires->css('/blocks/lvs/pages/css/lvs.css');

if (!isset($_POST['Salvar'])) {
	$cursolv->calcularPorcentagemAtividades();
	
	$table   = new html_table();
	$foruns  = $forumlv->getAtividades();
	$tarefas = $tarefalv->getAtividades();
	$wikis = $wikislvs->recuperarAtividades();
	
	$ativ_presencial_html->NUMERO_FORUNS = count($foruns);
	$ativ_presencial_html->NUMERO_TAREFAS = count($tarefas);
	$ativ_presencial_html->NUMERO_WIKIS = count($wikis);

	$stredit = get_string('edit');
	$strdelete = get_string('delete');
	$strupdate = get_string('update');
	$path = $CFG->wwwroot . '/course';

	if (count($foruns) != 0 || count($tarefas) != 0 || count($wikis) != 0) {
		$table->head = array('Nome', 'Introdu&ccedil;&atilde;o', '%', 'Etapa', 'Exibir LV', 'A&ccedil;&otilde;es');
	} else {
		$ativ_presencial_html->block('NENHUMA_ATIVIDADE');
	}

	$table->align = array("center", "center", "center", "center", "center", "center");
	$table->attributes['class'] = 'generaltable boxaligncenter width80';

	if (count($foruns) != 0) {
		$table->data[] = array('', '<strong>F&oacute;runs</strong>', '');
	}

	$i = 0;
	foreach ($foruns as $forum) {
		$i++;
		$cm = get_coursemodule_from_instance('forumlv', $forum->id);
		//$ativ_presencial_html->ID_ATIVIDADE
		$ativ_presencial_html->NAME_ATIVIDADE = "atividade[forumlv][$i][id]";
		$ativ_presencial_html->VALUE_ATIVIDADE = $forum->id;
		$ativ_presencial_html->block('ATIVIDADE');

		$porcentagem = html_writer::empty_tag('input', array('type'=>'text', 'id'=>"porcForum$i", 'name'=>"atividade[forumlv][$i][porcentagem]", 'value'=>$forum->porcentagem,  'style'=>'width:40px'));
		$porcentagem .= '%<br />';

		$exibir = html_writer::checkbox("atividade[forumlv][$i][exibir]", 1, $forum->exibir);
		$exibir .= "<br />";

		$options = array();
		for ($ietapa = 1; $ietapa <= 10; $ietapa++) {
			$options[$ietapa] = $ietapa;
		}
		$etapa = html_writer::select($options, "atividade[forumlv][$i][etapa]", $forum->etapa);
		$etapa .= "<br/>";

		$icons = html_writer::empty_tag('span', array('class'=>'commands'));
		$icons .= html_writer::empty_tag('a', array('class'=>'editing_update', 'title'=>$strupdate, 'href'=>"$path/mod.php?update=$cm->id&sesskey=".sesskey()));
		$icons .= html_writer::empty_tag('img', array('src'=> $OUTPUT->pix_url("/t/edit"), 'class'=>'iconsmall', 'alt'=>"$strupdate"));
		$icons .= html_writer::empty_tag('a', array('href'=>"$path/mod.php?delete=$cm->id&sesskey=".sesskey(), 'onclick'=>"return confirm('Deseja realmente excluir essa atividade ?');"));
		$icons .= html_writer::empty_tag('img', array('src'=> $OUTPUT->pix_url("/t/delete"), 'class'=>'iconsmall', 'alt'=>"$strdelete"));

		$table->data[] = array($forum->name, $forum->intro, $porcentagem, $etapa, $exibir , $icons);
	}

	if (count($tarefas) != 0) {
		$table->data[] = array('', '<strong>Tarefas</strong>', '');
	}

	$j = 0;
	foreach ($tarefas as $tarefa) {
		$j++;
		$cm = get_coursemodule_from_instance('tarefalv', $tarefa->id);
		//$ativ_presencial_html->ID_ATIVIDADE
		$ativ_presencial_html->NAME_ATIVIDADE = "atividade[tarefalv][$j][id]";
		$ativ_presencial_html->VALUE_ATIVIDADE = $tarefa->id;
		$ativ_presencial_html->block('ATIVIDADE');

		$porcentagem = html_writer::empty_tag('input', array('type'=>'text', 'id'=>"porcTarefa$j", 'name'=>"atividade[tarefalv][$j][porcentagem]", 'value'=>$tarefa->porcentagem,  'style'=>'width:40px'));
		$porcentagem .= '%<br />';

		$exibir = html_writer::checkbox("atividade[tarefalv][$j][exibir]", 1, $tarefa->exibir);
		$exibir .= "<br />";

		$options = array();
		for ($ietapa = 1; $ietapa <= 10; $ietapa++) {
			$options[$ietapa] = $ietapa;
		}
		$etapa = html_writer::select($options, "atividade[tarefalv][$j][etapa]", $tarefa->etapa);
		$etapa .= "<br/>";

		$icons = html_writer::empty_tag('span', array('class'=>'commands'));
		$icons .= html_writer::empty_tag('a', array('class'=>'editing_update', 'title'=>$strupdate, 'href'=>"$path/mod.php?update=$cm->id&sesskey=".sesskey()));
		$icons .= html_writer::empty_tag('img', array('src'=> $OUTPUT->pix_url("/t/edit"), 'class'=>'iconsmall', 'alt'=>"$strupdate"));
		$icons .= html_writer::empty_tag('a', array('href'=>"$path/mod.php?delete=$cm->id&sesskey=".sesskey(), 'onclick'=>"return confirm('Deseja realmente excluir essa atividade ?');"));
		$icons .= html_writer::empty_tag('img', array('src'=> $OUTPUT->pix_url("/t/delete"), 'class'=>'iconsmall', 'alt'=>"$strdelete"));

		$table->data[] = array($tarefa->name, $tarefa->intro, $porcentagem, $etapa, $exibir , $icons );
	}
	
	$wikis_configuracoes = $wikislvs->recuperarConfiguracao($wikis);
	
	if (count($wikis) != 0) {
		$table->data[] = array('', '<strong>Wikis</strong>', '');
	}
	
	$j = 0;
	foreach ($wikis as $wiki) {
		$j++;
		$cm = get_coursemodule_from_instance('wikilv', $wiki->id);
		//$ativ_presencial_html->ID_ATIVIDADE
		$ativ_presencial_html->NAME_ATIVIDADE = "atividade[wikilv][$j][id]";
		$ativ_presencial_html->VALUE_ATIVIDADE = $wiki->id;
		$ativ_presencial_html->block('ATIVIDADE');
	
		$porcentagem = html_writer::empty_tag('input', array('type'=>'text', 'id'=>"porcWiki$j", 'name'=>"atividade[wikilv][$j][porcentagem]", 'value'=>$wikis_configuracoes[$wiki->id]->porcentagem,  'style'=>'width:40px'));
		$porcentagem .= '%<br />';
	
		$exibir = html_writer::checkbox("atividade[wikilv][$j][exibir]", 1, $wikis_configuracoes[$wiki->id]->exibir);
		$exibir .= "<br />";
	
		$options = array();
		for ($ietapa = 1; $ietapa <= 10; $ietapa++) {
			$options[$ietapa] = $ietapa;
		}
		$etapa = html_writer::select($options, "atividade[wikilv][$j][etapa]", $wikis_configuracoes[$wiki->id]->etapa);
		$etapa .= "<br/>";
		
		$icons = html_writer::empty_tag('span', array('class'=>'commands'));
		$icons .= html_writer::empty_tag('a', array('class'=>'editing_update', 'title'=>$strupdate, 'href'=>"$path/mod.php?update=$cm->id&sesskey=".sesskey()));
		$icons .= html_writer::empty_tag('img', array('src'=> $OUTPUT->pix_url("/t/edit"), 'class'=>'iconsmall', 'alt'=>"$strupdate"));
		$icons .= html_writer::empty_tag('a', array('href'=>"$path/mod.php?delete=$cm->id&sesskey=".sesskey(), 'onclick'=>"return confirm('Deseja realmente excluir essa atividade ?');"));
		$icons .= html_writer::empty_tag('img', array('src'=> $OUTPUT->pix_url("/t/delete"), 'class'=>'iconsmall', 'alt'=>"$strdelete"));
	
		$table->data[] = array($wiki->name, $wiki->intro, $porcentagem, $etapa, $exibir , $icons );
	}

	$ativ_presencial_html->COURSE_ID = $course->id;
	$ativ_presencial_html->TABLE_ATIVIDADES = html_writer::table($table);

	if (count($foruns) != 0 || count($tarefas) != 0 || count($wikis) != 0) {
		$ativ_presencial_html->block('MODO_EDICAO');
	}

	echo $OUTPUT->header();
	$ativ_presencial_html->show();
	echo '<br/><hr><br/>';
	echo $OUTPUT->footer();
} else {
	$atividades = $_POST['atividade'];
	
	if($_POST['numero_foruns'])
		$forumlv->salvarAtividades($atividades['forumlv']);

	if($_POST['numero_tarefas'])
		$tarefalv->salvarAtividades($atividades['tarefalv']);
	
	if($_POST['numero_wikis'])
		$wikislvs->salvarConfiguracao($atividades['wikilv']);

	$cursolv->atualizarCurso();

	redirect("$CFG->wwwroot/blocks/lvs/pages/ativ_distancia.php?curso=$course->id", get_string('alteracoes', 'block_lvs'));
}