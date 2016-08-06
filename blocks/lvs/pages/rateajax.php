<?php
/**
*  	Recebe uma avaliação de um item via ajax, armazena-a e recalcula nota do usuário avaliado
*  
* 	@author Ricky Persivo (rickypaz@gmail.com)
* 	@version SVN $Id
*/
require_once('../../../config.php');
require_once($CFG->dirroot . '/group/lib.php');
require_once($CFG->dirroot . '/mod/wikilv/locallib.php');
require_once($CFG->dirroot . '/blocks/lvs/biblioteca/lib.php');

use uab\ifce\lvs\avaliacao\AvaliacaoLv;
use uab\ifce\lvs\avaliacao\NotasLvFactory;
use uab\ifce\lvs\business\Item;
use uab\ifce\lvs\moodle2\business\Moodle2CursoLv;
use uab\ifce\lvs\util\Cache;

$contextid 	= required_param('contextid', PARAM_INT);
$cacheid 	= required_param('cacheid', PARAM_ALPHANUM);
$componente = required_param('componente', PARAM_ALPHA);
$item_id 	= required_param('componente_id', PARAM_INT);
$nota	 	= optional_param('rating', null, PARAM_FLOAT);
$estudante 	= optional_param('estudante', 0, PARAM_INT);
$returnurl 	= required_param('returnurl', PARAM_LOCALURL);

list($context, $course, $cm) = get_context_info_array($contextid);
require_login($course, false, $cm);

if (!confirm_sesskey() || !has_capability('moodle/rating:rate',$context)) {
	echo $OUTPUT->header();
	echo get_string('ratepermissiondenied', 'rating');
	echo $OUTPUT->footer();
	die();
}

$gerenciador = NotasLvFactory::criarGerenciador('moodle2');

$atividadelv = $gerenciador->criarAtividadeLv($cm->modname, $cm->instance); 
$cache 		 = new Cache('cachelvs.xml');
$cursolv	 = new Moodle2CursoLv($cm->course);
$avaliacao   = new AvaliacaoLv();
$itemAvaliado = $cache->recuperarDado($cacheid);

$gerenciador->setCursoLv($cursolv);
$gerenciador->setModulo($atividadelv);
$avaliacao->setAvaliador($USER->id);
$avaliacao->setEstudante($estudante);
$avaliacao->setNota($nota);
$avaliacao->setItem($itemAvaliado);


if (isset($nota)) {
	$gerenciador->salvarAvaliacao($avaliacao);
} else {
	$gerenciador->removerAvaliacao($avaliacao);
}

$resposta = array('avaliacao'=>$gerenciador->avaliacaoAtualCarinha($avaliacao));


echo json_encode($resposta);
?>
