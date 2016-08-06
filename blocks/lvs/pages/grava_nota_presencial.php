<?php
	require_once('../../../config.php');
	require_once '../biblioteca/AtividadePresencial.php';

	$course_id = required_param('curso', PARAM_INT);
	$atividade_id = required_param('id_atividade', PARAM_INT);

	if (!$course = $DB->get_record('course', array('id'=>$course_id)) ) {
	    print_error('Invalid course id');
	} 
	
	require_login($course);
	require_capability('moodle/course:viewhiddenactivities', $PAGE->context);
	
	$atividadePresencial = new AtividadePresencial($course->id);
	$atividade = $atividadePresencial->getAtividade($atividade_id);
	$notasPresenciais = get_string('notaspresenciais', 'lvs'); // 'Notas Presenciais';
	
	$PAGE->set_course($course);
    $PAGE->set_url("/blocks/lvs/pages/npresencial.php?curso=$course->id&id=$atividade->id");
	$PAGE->set_title(format_string($course->fullname . ' : ' . $notasPresenciais));
	$PAGE->set_heading($notasPresenciais);
	
	$PAGE->navbar->add($course->fullname, null, null, navigation_node::TYPE_CUSTOM, new moodle_url("/course/view.php?id=$course->id"));
	$PAGE->navbar->add($notasPresenciais);

	$sqlusers = "SELECT * FROM {role_assignments} r,{user} u
					WHERE r.contextid = ? AND u.id = r.userid AND r.roleid=5
		 			ORDER BY u.firstname";
	$users = $DB->get_records_sql($sqlusers, array($PAGE->context->id));
	
	foreach ($users as $user) {
	    if (!has_capability('moodle/course:viewhiddenactivities', $PAGE->context, $user->userid)) {
	        $data = $_POST['atividade'][$user->userid];
	        $avaliacao = $atividadePresencial->getAvaliacao($user->userid, $atividade->id);
	
	        if(empty($avaliacao)) {
	        	$avaliacao = (object) $data;
	        	$avaliacao->id_atividade = $atividade->id;
	        	$avaliacao->id_avaliado = $user->userid;
	        	$avaliacao->nota = (!empty($data['nota'])) ? $data['nota'] : NULL;
	        } else {
	        	$avaliacao->nota = (!empty($data['nota'])) ? $data['nota'] : NULL;
	        	$avaliacao->nr_faltas = $data['nr_faltas'];
	        	$avaliacao->faltou_prova = isset($data['faltou_prova'])? $data['faltou_prova'] : 0;
	        }
	        
	        $atividadePresencial->salvarAvaliacao($avaliacao);
	    }
	}
	
	$atividadePresencial->ausentarAlunoSemNota($atividade->id);
	$atividadePresencial->getCursoLV()->atualizarCurso();

    redirect("$CFG->wwwroot/blocks/lvs/pages/ativ_presenciais.php?curso=$course->id", get_string('alteracoes', 'block_lvs'));