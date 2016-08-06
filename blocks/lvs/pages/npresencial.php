<?php
	include('../../../config.php');
	require_once '../biblioteca/CursoLV.php';
	require_once '../biblioteca/AtividadePresencial.php';
	require_once 'Template.class.php';
	
	$course_id = required_param('curso', PARAM_INT);
	$atividade_id = required_param('id', PARAM_INT);

	if (!$course = $DB->get_record('course', array('id'=>$course_id)) ) {
	    print_error('Invalid course id');
	} 
	
	require_login($course);
	require_capability('moodle/course:viewhiddenactivities', $PAGE->context);
	
	$cursoLV = new CursoLV($course->id);
	$npresencial_html = new Template('html/npresencial.html');
	$atividadePresencial = new AtividadePresencial($course->id);
	$notasPresenciais = get_string('notaspresenciais', 'block_lvs');
	$numero_usuarios = 0;
	
	$configuracao_cursolv = $cursoLV->getConfiguracao();
	$atividade = $atividadePresencial->getAtividade($atividade_id);
	
	$PAGE->set_course($course);
    $PAGE->set_url("/blocks/lvs/pages/npresencial.php?curso=$course->id&id=$atividade->id");
	$PAGE->set_title(format_string($course->fullname . ' : ' . $notasPresenciais));
	$PAGE->set_heading($course->fullname . ' : ' . $notasPresenciais);
	
	$PAGE->navbar->add($notasPresenciais);
	
	$PAGE->requires->js('/blocks/lvs/pages/scripts/mask.js',true);
	$PAGE->requires->js_init_call('M.block_lv.npresencial');
	
	$PAGE->requires->css('/blocks/lvs/pages/css/lvs.css');
	
	$dataatual = mktime(0, 0, 0, date("m"), date("d"), date("Y"));
	$nova_data = date("d/m/Y", $configuracao_cursolv->data_limite);
	
	$msgdata = get_string('datalimitemsg', 'block_lvs');
	$npresencial_html->MENSAGEM_INICIAL = $msgdata . ' ' . $nova_data;
	 
	$primeironome = get_string("nome", "block_lvs");
	$ultimonome = get_string("sobrenome", "block_lvs");
	$notapresencial = get_string('notapresencial', 'block_lvs');
	$faltas = get_string('faltas', 'block_lvs');
	
	$permitir_edicao = $configuracao_cursolv->data_limite >= $dataatual;
	
	$table = new html_table();
	
	$table->head = array('  ', $primeironome . '/' . $ultimonome, $notapresencial, $faltas, '2ª Chamada');
	$table->align = array("center", "center", "center", "center", "center");
	$table->attributes['class'] = 'generaltable boxaligncenter width80';
	
	if (has_capability('moodle/course:viewhiddenactivities', $PAGE->context, $USER->id)) {
		$sqlusers = "SELECT * FROM {role_assignments} r, {user} u WHERE r.contextid = ? AND u.id = r.userid AND r.roleid = 5 ORDER BY u.firstname";
		$users = $DB->get_records_sql($sqlusers, array($PAGE->context->id));
		$numero_usuarios = count($users);
		
		$i=0;
	    foreach ($users as $user) {
	    	$i++;
	    	
	        if (!has_capability('moodle/course:viewhiddenactivities', $PAGE->context, $user->userid)) {
				$imagem = $OUTPUT->user_picture($user, array('courseid'=>$course->id));
	            $avaliacao = $atividadePresencial->getAvaliacao($user->userid, $atividade->id);
	            
	            if(!empty($avaliacao)) {
	            	$nota_presencial = $avaliacao->nota;
	            	$falta = $avaliacao->nr_faltas;
	            	$faltaprova = $avaliacao->faltou_prova;
	            } else {
	            	$nota_presencial = '';
	            	$falta = 0;
	            	$faltaprova = 0;
	            }
	            
	            if ($permitir_edicao) {
	            	$input_nota = "Nota: ";
	            	$input_nota .= html_writer::empty_tag('input', array('type'=>'text', 'id'=>"lvs_notap_$i" ,'name'=>"atividade[$user->userid][nota]", 'value'=>$nota_presencial, 'style'=>"width:50px"));
	            	
	            	$input_faltas = "Turnos: ";
	            	$input_faltas .= html_writer::empty_tag('input', array('type'=>'text', 'id'=>"lvs_faltap_$i", 'name'=>"atividade[$user->userid][nr_faltas]", 'value'=>$falta, 'style'=>'width:50px'));
	            	
	            	$checkbox_falta_prova = html_writer::checkbox("atividade[$user->userid][faltou_prova]", '', $faltaprova, '', array('style'=>'width:50px', 'disabled'=>'disabled'));
	            } else {
	            	$input_nota = $nota_presencial;
	            	$input_falta = $falta;
	            	$checkbox_falta_prova = $faltaprova;
	            }
	
// 	            $table->data[] = 'hr';
	            $table->data[] = array($imagem, $user->firstname . ' ' . $user->lastname, $input_nota, $input_faltas, $checkbox_falta_prova);
	        }
	    }
	}
	
	if ($permitir_edicao) {
		$npresencial_html->ACTION_FORM = "grava_nota_presencial.php?curso=$course->id";
		$npresencial_html->ID_ATIVIDADE = $atividade->id;
		$npresencial_html->NUMERO_USUARIOS = $numero_usuarios;
		$npresencial_html->MAX_FALTAS = ord($atividade->max_faltas);
		$npresencial_html->TABLE_USER = html_writer::table($table);

		$npresencial_html->block('BEGIN_FORM');
		$npresencial_html->block('USER');
		$npresencial_html->block('GRAVAR');
		$npresencial_html->block('END_FORM');
	}
	
	echo $OUTPUT->header();
	$npresencial_html->show();
	echo '<br/><hr><br/>';
	echo $OUTPUT->footer();