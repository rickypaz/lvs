<?php
exit;
set_time_limit(0); 
ini_set('memory_limit','1024M');

/**
*  	Recebe uma avaliação de um item via ajax, armazena-a e recalcula nota do usuário avaliado
*  
* 	@author Ricky Persivo (rickypaz@gmail.com)
* 	@version SVN $Id
*/
require_once('../../../config.php');
require_once($CFG->dirroot . '/group/lib.php');
require_once($CFG->dirroot . '/mod/forumlv/lib.php');
require_once($CFG->dirroot . '/blocks/lvs/biblioteca/lib.php');

use uab\ifce\lvs\avaliacao\AvaliacaoLv;
use uab\ifce\lvs\avaliacao\NotasLvFactory;
use uab\ifce\lvs\business\Item;
use uab\ifce\lvs\moodle2\business\Moodle2CursoLv;
use uab\ifce\lvs\moodle2\business\Forumlv;

global $DB;

$cursolv = new Moodle2CursoLv(166);
$cursolv->avaliarDesempenho( 324 );


?>
