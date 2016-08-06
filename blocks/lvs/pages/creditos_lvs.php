<?php
include_once('../../../config.php');

$course_id = optional_param('curso', 0, PARAM_INT);

if(!$course = $DB->get_record('course', array('id'=>$course_id))) {
	print_error("Invalid course id");
}

require_login($course);

$creditosLV = get_string('creditos', 'block_lvs');

$PAGE->set_course($course);
$PAGE->set_url("/blocks/lvs/pages/creditos_lvs.php?curso=$course->id");
$PAGE->set_title(format_string($course->fullname . ' : ' . $creditosLV));
$PAGE->set_heading($course->fullname . ' : ' . $creditosLV);

$PAGE->navbar->add($creditosLV);

$idealizacaoModelagem = "Gilvandenys Leite Sales (" . "<a href = 'mailto:denyssales@ifce.edu.br'>denyssales@ifce.edu.br</a>" . ")<br />" .
		"Giovanni Cordeiro Barroso <br />" .
		"Jos&eacute; Marques Soares <br />";

$desenvolvedores = "C. Maur&iacute;cio J. de M. Dourado Junior <br />" .
		"Allyson Bonetti Fran&ccedil;a (" . "<a href = 'mailto:allysonbonetti@gmail.com'>allysonbonetti@gmail.com</a>" . ")<br />" .
		"Manoel Fiuza Lima Junior <br />" .
		"Ricky Paz Persivo Cunha (" . "<a href = 'mailto:rickypaz@gmail.com'>rickypaz@gmail.com</a>" . ")<br />";

$designGrafico = "David Jucimon <br />" .
		"Luana Cavalcante Crisóstomo <br />";

$instituicoesEnvolvidas = "Universidade Federal do Cear&aacute;<br />" .
		"Instituto Federal de Educa&ccedil;&atilde;o, Ci&ecirc;ncia e Tecnologia do Cear&aacute;<br />";

$observacao = "Este trabalho &eacute; o produto da tese de doutorado de Gilvandenys Leite Sales, desenvolvida no Departamento de Engenharia de Teleinform&aacute;tica da Universidade Federal do Cear&aacute;. Coordenado pelo IFCE, foi subsidiado por bolsas do MEC/SETEC e CAPES.";

$table = new html_table();

$table->head = array("&nbsp;", '');
$table->align = array('right','left');

$table->data[] = array('<b>Idealiza&ccedil;&atilde;o e Modelagem:</b> ', $idealizacaoModelagem);
$table->data[] = array('<b>Desenvolvedores:</b> ', $desenvolvedores);
$table->data[] = array('<b>Design Gr&aacute;fico:</b> ', $designGrafico);
$table->data[] = array('<b>Institui&ccedil;&otilde;es Envolvidas:</b> ', $instituicoesEnvolvidas);
$table->data[] = array('<b>Observa&ccedil;&atilde;o:</b> ', $observacao);

echo $OUTPUT->header();
echo html_writer::table($table);
echo '<br/><hr><br/>';
echo $OUTPUT->footer();