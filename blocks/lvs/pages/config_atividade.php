<?php
	include('../../../config.php');
	require_once '../biblioteca/AtividadePresencial.php';
	require_once 'Template.class.php';
	
	$course_id = required_param('curso', PARAM_INT);
	$atividade_id = required_param('id', PARAM_INT);
	$porcentagem = optional_param('porc', 0, PARAM_INT);
	
	if (!$course = $DB->get_record('course', array('id'=>$course_id)) ) {
		print_error('Invalid course id');
	}
	$context = context_course::instance($course->id);
	
	require_login($course);
	require_capability('moodle/course:viewhiddenactivities', $PAGE->context);
		
	$content = null;
	$cursoLV = new CursoLV($course->id);
	$atividadesPresenciais = new AtividadePresencial($course->id);
	$config_atividade_str = get_string('notaspresenciais', 'lvs');
	
	$PAGE->set_course($course);
	$PAGE->set_url("/blocks/lvs/pages/ativ_presenciais_config.php?curso=$course->id");
	$PAGE->set_title(format_string($course->fullname . ' : ' . $config_atividade_str));
	$PAGE->set_heading($course->fullname);
	
	$PAGE->navbar->add($course->fullname, null, null, navigation_node::TYPE_CUSTOM, new moodle_url("/course/view.php?id=$course->id"));
	$PAGE->navbar->add($config_atividade_str);
	
	if ($_GET['acao'] == 'd') {
		$atividadesPresenciais->removerAtividade($atividade_id);
		$atividades = $atividadesPresenciais->getAtividades();
	
		if (count($atividades) != 0) {
			$atividade = reset($atividades);
			$atividade->porcentagem += $porcentagem;
		}
	
		$atividadesPresenciais->salvarAtividades($atividades);
	
		redirect("$CFG->wwwroot/blocks/lvs/pages/ativ_presenciais_config.php?curso=$course->id", get_string('alteracoes', 'block_lvs'));
	} else if ($_GET['acao'] == 'e' && empty($_POST['acao'])) {	
		$PAGE->requires->js_init_call('M.block_lv.config_presencial_edit');
		$PAGE->requires->css("/blocks/lvs/pages/css/form.css");
		
		$config_atividade_edit_html = new Template("html/config_atividade_edit.html");
		$atividade = $atividadesPresenciais->getAtividade($atividade_id);
		
		$config_atividade_edit_html->ID_ATIVIDADE = $atividade->id;
		$config_atividade_edit_html->NOME_ATIVIDADE = $atividade->nome;
		$config_atividade_edit_html->DESCRICAO_ATIVIDADE = $atividade->descricao;
		
		$content = $config_atividade_edit_html->parse();
	} else if ($_POST['acao'] == 'gravar') {
		$atividade = $_POST['atividade'];
		$atividadesPresenciais->salvarAtividades(array($atividade));
	
		redirect("$CFG->wwwroot/blocks/lvs/pages/ativ_presenciais_config.php?curso=$course->id", get_string('alteracoes', 'block_lvs'));
	}
	
	echo $OUTPUT->header();
	echo '<br/><br/>';
	echo $content;
	echo '<br/><hr/><br/>';
	echo $OUTPUT->footer();