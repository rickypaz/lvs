<?php
define('LVS_SOURCE', $CFG->dirroot . '/blocks/lvs');
define('LVS_WWWROOT', $CFG->wwwroot . '/blocks/lvs');
define('LVS_DIRROOT', $CFG->dirroot . '/blocks/lvs');
define('LVS_WWWROOT2', $CFG->wwwroot);

define ('RATING_AGGREGATE_LVS', 1); // @todo remover
define ('RATING_ESCALA_LIKERT', 777); // @todo remover

use uab\ifce\lvs\Carinhas;
use uab\ifce\lvs\LvsAutoLoader;
use uab\ifce\lvs\avaliacao\NotasLvFactory;

require_once LVS_DIRROOT . '/biblioteca/LvsAutoLoader.php';

if(function_exists("__autoload")) {
	spl_autoload_register("__autoload");
}

$lvs_loader = new LvsAutoLoader(LVS_DIRROOT . '/biblioteca');
$lvs_loader->registrar();

$lvs_gerenciador_notas = NotasLvFactory::criarGerenciador('moodle2');

function nao_implementado($classe, $metodo, $error_type = E_USER_ERROR) {
	trigger_error($classe . '::' . $metodo . " não implementado", $error_type);
	echo '<br>';
}
?>