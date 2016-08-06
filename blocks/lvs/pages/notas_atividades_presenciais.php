<?php
	include_once('../../../config.php');
	
	$course_id = required_param('id', PARAM_INT);
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

	$notaslv = 'Notas Atividades Presenciais'; // FIXME get_string('notaslvs', 'block_lvs');

	$PAGE->set_course($course);
	$PAGE->set_url("/course/view.php?id=$course->id");
	$PAGE->set_title(format_string($course->fullname . ' : ' . $notaslv));
	$PAGE->set_heading(format_string($course->fullname . ' : ' . $notaslv));
	
	$PAGE->requires->css('/blocks/lvs/biblioteca/dompdf/css/table.css');

	// FIXME essa lógica tem que ir para um objeto!
	$query = "SELECT atv.id, npre.nota, atv.nome FROM {lvs_nota_presencial} as npre, {lvs_atv_presencial} as atv 
	          WHERE atv.id_curso = ? AND atv.id = npre.id_atividade AND npre.id_avaliado = ?";
	$atividades = $DB->get_records_sql($query, array($course->id, $usuario->id));
	
	$table = new html_table();
	$table->head = array('Atividade', 'Nota');
	$table->align = array("center", "center");
	
	if (!empty($atividades)) {
	    foreach ($atividades as $atividade) {
	        $table->data[] = 'hr';
	        $table->data[] = array($atividade->nome, $atividade->nota);
	    }
	} else {
	    $table->data[] = array('-', 'Nenhuma nota postada para o usuário!');
	}
	
	echo $OUTPUT->header();
	echo '<br><center>';
	echo html_writer::table($table);
	echo '</center>';
	echo $OUTPUT->footer($course);