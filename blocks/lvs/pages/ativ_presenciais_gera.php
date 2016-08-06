<?php
	include('../../../config.php');
	require '../biblioteca/AtividadePresencial.php';

	$course_id = required_param('curso', PARAM_INT);

	if (!$course = $DB->get_record('course', array('id'=>$course_id)) ) {
	    print_error('Invalid course id');
	}
	
	require_login($course);
	require_capability('moodle/course:viewhiddenactivities', $PAGE->context);
	
	$atividadesPresenciais = new AtividadePresencial($course->id); 
	$config_presencial_str = get_string("config_ativ_presencial", "block_lvs"); 
	
	$PAGE->set_course($course);
    $PAGE->set_url("/blocks/lvs/pages/ativ_presenciais_gera.php?curso=$course->id");
	$PAGE->set_title(format_string($course->fullname . ' : ' . $config_presencial_str));
	$PAGE->set_heading($course->fullname . ' : ' . $config_presencial_str);
	
	$PAGE->navbar->add($config_presencial_str);
	
	$PAGE->requires->js('/blocks/lvs/pages/scripts/mask.js',true);
	$PAGE->requires->js_init_call('M.block_lvs.config_presencial_gera');

	if (!isset($_POST['gerar']) && !isset($_POST['gravar'])) {
	    $nome_atividade_presencial_str = get_string("ativpresencial", "block_lvs"); 
	    $quantidade_str = get_string('quantidade', 'block_lvs');
	    
	    $gerar_input = html_writer::empty_tag('input', array('type'=>'hidden', 'name'=>'gerar', 'value'=>'gerar'));
	    $curso_input = html_writer::empty_tag('input', array('type'=>'hidden', 'name'=>'curso', 'value'=>$course->id));
		$numero_atividades_input = html_writer::empty_tag('input', array('type'=>'text', 'id'=>'lvs_qtdadeativ', 'name'=>'qtdadeativ', 'style'=>'width:50px'));
	    
	    $table = new html_table();
	    
	    $table->head = array('', $quantidade_str);
	    $table->align = array("center", "center");
// 	    $table->data[] = 'hr';
	    $table->data[] = array($nome_atividade_presencial_str, $numero_atividades_input);
	    
	    $gerar_submit = html_writer::empty_tag('input', array('type'=>'submit', 'name'=>'gerar', 'value'=>'Gerar'));
	    $voltar_button = html_writer::empty_tag('input', array('type'=>'button', 'id'=>'lvs_voltar', 'name'=>'voltar', 'value'=>'Voltar'));
	    
	    $form = $gerar_input . $curso_input . html_writer::table($table) . "<br/>" . $gerar_submit . $voltar_button;

	    $content = html_writer::tag('form', $form, array('name'=>'form1', 'target'=>'_self', 'method'=>'post', 'action'=>'#'));
	} else if (isset($_POST['gerar'])) {
// 		$PAGE->requires->css("/blocks/lvs/pages/css/form.css");
		
	    $numero_atividades_novas = $_POST['qtdadeativ'];
		
		if (empty($numero_atividades_novas)) {
	        $numero_atividades_novas = 0;
	    }

	    $atividades = $atividadesPresenciais->getAtividades();
	    $numero_atividades_existentes = count($atividades);
	
	    if (empty($numero_atividades_existentes)) {
	        $numero_atividades_existentes = 0;
	    }
	
	    $input_gravar = html_writer::empty_tag('input', array('type'=>'hidden', 'name'=>'gravar', 'value'=>'gravar'));
	    $input_numero_atividades_novas = html_writer::empty_tag('input', array('type'=>'hidden', 'id'=>'lvs_atividades_novas', 'name'=>'presencial[quantidade_novas]', 'value'=>$numero_atividades_novas));
		$input_numero_atividades_existentes = html_writer::empty_tag('input', array('type'=>'hidden', 'id'=>'lvs_atividades_existentes', 'name'=>'presencial[quantidade_existentes]', 'value'=>$numero_atividades_existentes));
	
	    if ($numero_atividades_existentes != 0) {
	    	$table = new html_table();
	    	$input_atividade_presencial = '';
	    	
	        $table->head = array('Nome', 'Descrição', '%', 'Nº de Turnos');
	        $table->align = array("center", "center", "center", "center");
	
	        $i = 0;
	        foreach ($atividades as $atividade) {
	            $i++;
	            
	            $input_atividade_presencial .= html_writer::empty_tag('input', array('type'=>'hidden', 'id'=>"idAtivPresencialant$i", 'name'=>"presencial[existente][$i][id]", 'value'=>$atividade->id)); 
	            $input_porcentagem = html_writer::empty_tag('input', array('type'=>'text', 'id'=>"porcAtividadepresencialant$i",'name'=>"presencial[existente][$i][porcentagem]", 'value'=>$atividade->porcentagem, 'style'=>'width:40px'));
	            $input_porcentagem .= "%<br/>";
	            $input_maximo_faltas = html_writer::empty_tag('input', array('type'=>'text', 'id'=>"faltasMaxAtividadepresencial$i", 'name'=>"presencial[existente][$i][max_faltas]", 'value'=>$atividade->max_faltas, 'style'=>'width:40px'));
	            $input_maximo_faltas .= "<br />";
	
// 	            $table->data[] = 'hr';
	            $table->data[] = array($atividade->nome, $atividade->descricao, $input_porcentagem, $input_maximo_faltas);
	        }
	    }
	
	    for ($im = 1; $im <= $numero_atividades_novas; $im++) {
	    	$legend = html_writer::tag('legend', "Atividade $im");

	    	$label_titulo = html_writer::label("Título Atividade Presencial:", "tituloAtividadePresencial$im");
	    	$input_titulo = html_writer::empty_tag('input', array('type'=>'text', 'id'=>"tituloAtividadePresencial$im", 'name'=>"presencial[nova][$im][nome]", 'style'=>'width:500px'));
	    	
	    	$label_descricao = html_writer::label("Descrição:", "descricaoAtividadePresencial$im");
	    	$textarea_descricao = html_writer::tag('textarea', '', array('id'=>"descricaoAtividadePresencial$im", 'name'=>"presencial[nova][$im][descricao]",'cols'=>'80', 'rows'=>'5'));
	    	
	    	$label_porcentagem = html_writer::label("Porcentagem desta Atividade:", "porcAtividadePresencial$im");
	    	$input_porcentagem = html_writer::empty_tag('input', array('type'=>'text', 'id'=>"porcAtividadePresencial$im", 'name'=>"presencial[nova][$im][porcentagem]", 'style'=>'width:40px'));
	    	
	    	$label_faltas = html_writer::label("Turnos previstos para esta Atividade:", "maxFaltasAtividadePresencial$im");
	    	$input_faltas = html_writer::empty_tag('input', array('type'=>'text', 'id'=>"maxFaltasAtividadePresencial$im", 'name'=>"presencial[nova][$im][max_faltas]", 'style'=>'width:40px'));
	        
	    	$fieldset_contents = $legend . $label_titulo . $input_titulo . '<br/>' . $label_descricao . '<br/>' . $textarea_descricao . '<br/>' .
	    					$label_porcentagem . $input_porcentagem . '%<br/>' . $label_faltas . $input_faltas . '<br/>';
	        
	    	$fieldset .= html_writer::tag('fieldset', $fieldset_contents);
	    }
	
	    $submit_enviar = html_writer::empty_tag('input', array('type'=>'submit', 'value'=>'Enviar', 'class'=>'botao'));
	    $table = (isset($table)) ? html_writer::table($table) : null; 
	    
	    $form = $input_gravar . $input_numero_atividades_novas . $input_numero_atividades_existentes . $input_atividade_presencial .
	    			 $table . $fieldset . $submit_enviar;
	    			 
	    $content = html_writer::tag('form', $form, array('id'=>'lvs_presencial_gera', 'name'=>'form1', 'target'=>'_self', 'method'=>'POST', 'action'=>'#'));
	} else {
		$data = $_POST['presencial']; 
		
		$atividades_novas = (isset($data['nova'])) ? $data['nova']: array();
		$atividades_existentes = (isset($data['existente'])) ? $data['existente']: array(); 

		$atividades = array_merge($atividades_novas, $atividades_existentes);
		$atividadesPresenciais->salvarAtividades($atividades);

		redirect("$CFG->wwwroot/blocks/lvs/pages/ativ_presenciais_config.php?curso=$course->id", get_string('alteracoes', 'block_lvs'));
	}
	
	echo $OUTPUT->header();
	echo "<br/>";
	echo $content;
	echo "<br/><hr><br/>";
	echo $OUTPUT->footer();