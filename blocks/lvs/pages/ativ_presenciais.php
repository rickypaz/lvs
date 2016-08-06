<?php
	include('../../../config.php');
	require '../biblioteca/AtividadePresencial.php';
	
	$course_id = required_param('curso', PARAM_INT);
	
	if(!$course = $DB->get_record('course', array('id'=>$course_id)) ) {
		print_error('Invalid course id');
	}
	
	require_login($course);
	
	if(!has_capability('moodle/course:viewhiddenactivities', $PAGE->context)) {
		redirect('ativ_presenciais_config.php?curso=' . $course->id);		
	}
	
	$atividadesPresenciais = new AtividadePresencial($course->id);
	$strNotasPresenciais = get_string('notaspresenciais', 'block_lvs');
	$strAtividadePresencial = get_string('ativpresencial', 'block_lvs');
	$strConfiguracao = get_string('config_presencial', 'block_lvs');
	
	$PAGE->set_course($course);
	$PAGE->set_url("/blocks/lvs/pages/ativ_presenciais.php?curso=$course->id");
	$PAGE->set_title(format_string($course->fullname . ' : ' . $strNotasPresenciais));
	$PAGE->set_heading(format_string($course->fullname . ' : ' . $strNotasPresenciais));
	
	$PAGE->navbar->add($strNotasPresenciais);
	
	$PAGE->requires->css('/blocks/lvs/pages/css/lvs.css');
	
	$table = new html_table();
	
	$table->head = array($strAtividadePresencial, '');
	$table->align = array("center", "center");
	$table->attributes['class'] = 'generaltable boxaligncenter width80';
	
	$atividades = $atividadesPresenciais->getAtividades();
	
	if(count($atividades) > 0) {
		foreach ($atividades as $atividade) {
			$link = html_writer::link("npresencial.php?curso=$course->id&id=$atividade->id", "Lançar Notas / Frequência");
			$table->data[] = array($atividade->nome, $link);
		}
	}
	
	$configuracao_button = html_writer::start_tag('form', array('action'=>"ativ_presenciais_config.php?curso=$course->id", 'method'=>'post'));
	$configuracao_button .= html_writer::start_tag('input', array('type'=>'submit', 'value'=>$strConfiguracao));
	
	$table->data[] = array('', $configuracao_button);
	
	echo $OUTPUT->header();
	echo '<br/><br/>';
	echo html_writer::table($table);
	echo '<br/><hr><br/>';
	echo $OUTPUT->footer();