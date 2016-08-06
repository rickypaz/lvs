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
//echo 1;

$offset = $_GET['offset'];

echo $offset."\n";
$limit = 1000;

$final = $offset+3000;

while($offset <= $final) {
	$sql = "select p.id postid, f.id forumid, p.userid
	from mdl_forumlv f 
		inner join mdl_forumlv_discussions d on d.forumlv = f.id 	
		inner join mdl_forumlv_posts p on p.discussion = d.id
	ORDER BY p.id, f.id, p.userid 
	LIMIT " . $limit . " OFFSET " . $offset;
//	echo "\n\n";

	if ($unreads = $DB->get_records_sql($sql)) 
	{

	       	foreach ($unreads as $unread) {
			//echo $unread -> forumid . " -- " .$unread -> userid. " avaliando...<br/>";
			$forumlv = new Forumlv($unread -> forumid);
			$forumlv->_avaliarDesempenho($unread -> userid);
	        }

		echo "\nAs notas foram atualizadas corretamente!! ;)\n";
	}

	$offset += 1000;
	echo $offset."\n";
	file_put_contents ("/var/www/moodle/blocks/lvs/tests/script.txt", date('d/m/Y H:i:s') . " - " . $offset."\n",FILE_APPEND);
//	sleep(1);
}

?>