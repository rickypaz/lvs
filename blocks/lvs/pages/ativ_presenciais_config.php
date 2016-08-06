<?php
	include('../../../config.php');
	require '../biblioteca/AtividadePresencial.php';
	require 'Template.class.php';
	
	$course_id = required_param('curso', PARAM_INT);
	
	if (!$course = $DB->get_record('course', array('id'=>$course_id)) ) {
		print_error('Invalid course id');
	}
	
	require_login($course);
	
	$atividadesPresenciais = new AtividadePresencial($course->id);
	$ativ_presenciais_config_html = new Template('html/ativ_presenciais_config.html');
	$config_presencial_str = get_string('config_ativ_presencial', 'block_lvs');
	
	$PAGE->set_course($course);
	$PAGE->set_url("/blocks/lvs/pages/ativ_presenciais_config.php?curso=$course->id");
	$PAGE->set_title(format_string($course->fullname . ' : ' . $config_presencial_str));
	$PAGE->set_heading($course->fullname . ' : ' . $config_presencial_str);
	
	$PAGE->navbar->add($config_presencial_str);
	
	$PAGE->requires->js('/blocks/lvs/pages/scripts/mask.js',true);
	$PAGE->requires->js_init_call('M.block_lvs.config_presencial', array($CFG->wwwroot));
	
	$PAGE->requires->css('/blocks/lvs/pages/css/lvs.css');
	
	if (!isset($_POST['Salvar'])) {
		$atividades = $atividadesPresenciais->getAtividades();
		$numero_atividades = count($atividades);
		 
		$ativ_presenciais_config_html->COURSE_ID = $course->id;
		$ativ_presenciais_config_html->NUMERO_ATIVIDADES = $numero_atividades;
		 
		$stredit = get_string('edit');
		$strdelete = get_string('delete');
		$strupdate = get_string('update');
	
		if ($numero_atividades != 0) {
			$i = 0;
			$table = new html_table();
	
			$table->head = array('Nome', 'Descrição', '%', 'Nº de Turnos', 'Ações');
			$table->align = array("center", "center", "center", "center", "center");
			$table->attributes['class'] = "boxaligncenter generaltable width80";
			
			$ativ_presenciais_config_html->WWWROOT = $CFG->wwwroot;
			
			if(has_capability('moodle/course:viewhiddenactivities', $PAGE->context)) {
				$ativ_presenciais_config_html->block('COLUNA_ACOES');
				$ativ_presenciais_config_html->block('GRAVAR');
			} else {
				$ativ_presenciais_config_html->READONLY = 'readonly="readonly"';
			}
	
			foreach ($atividades as $atividade) {
				$i++;
	
				$ativ_presenciais_config_html->ID_ATIVIDADE = "idAtivPresencial$i";
				$ativ_presenciais_config_html->NAME_ATIVIDADE = "data[atividade][$i][id]";
				$ativ_presenciais_config_html->VALUE_ATIVIDADE = $atividade->id;
				$ativ_presenciais_config_html->block('ATIVIDADE_ID');
	
				$ativ_presenciais_config_html->ATIVIDADE_NOME = $atividade->nome;
				$ativ_presenciais_config_html->ATIVIDADE_DESCRICAO = $atividade->descricao;
				
				$ativ_presenciais_config_html->ATIVIDADE_PORCENTAGEM = $atividade->porcentagem;
				$ativ_presenciais_config_html->ATIVIDADE_PORCENTAGEM_ID = 'porcAtividadepresencial' . $i;
				$ativ_presenciais_config_html->ATIVIDADE_PORCENTAGEM_NAME = 'data[atividade][' . $i . '][porcentagem]';
				
				$ativ_presenciais_config_html->ATIVIDADE_MAX_FALTAS = $atividade->max_faltas;
				$ativ_presenciais_config_html->ATIVIDADE_MAX_FALTAS_ID = 'faltasMaxAtividadepresencial' . $i;
				$ativ_presenciais_config_html->ATIVIDADE_MAX_FALTAS_NAME = 'data[atividade][' . $i . '][max_faltas]';
				
				if(has_capability('moodle/course:viewhiddenactivities', $PAGE->context)) {
					$ativ_presenciais_config_html->block('ACOES');
				}
				
				$ativ_presenciais_config_html->block('ATIVIDADE');
			}
	
			$ativ_presenciais_config_html->block('ATIVIDADES');
		} else {
			$ativ_presenciais_config_html->block('NENHUMA_ATIVIDADE');
		}
	} else {
		$atividades = $_POST['data']['atividade'];
		$atividadesPresenciais->salvarAtividades($atividades);
		 
		redirect("$CFG->wwwroot/blocks/lvs/pages/ativ_presenciais_config.php?curso=$course->id", get_string('alteracoes', 'block_lvs'));
	}
	
	echo $OUTPUT->header();
	echo '<br/><br/>';
	echo $ativ_presenciais_config_html->show();
	echo '<hr>';
	echo $OUTPUT->footer();