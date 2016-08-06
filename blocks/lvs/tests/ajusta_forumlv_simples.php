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


$sql = "UPDATE mdl_lvs_tabela_curso SET atualiza=1";
if ($updates = $DB->get_records_sql($sql)) print_object($updates);
exit;



/*$forumlv = new Forumlv(185);
$forumlv->_avaliarDesempenho(2817);
print_object($forumlv);*/

$sql = "select p.id postid, f.id forumid, p.userid
	from mdl_forumlv f 
		inner join mdl_forumlv_discussions d on d.forumlv = f.id 	
		inner join mdl_forumlv_posts p on p.discussion = d.id
	WHERE f.id = 185 
	ORDER BY p.id, f.id, p.userid";
if ($unreads = $DB->get_records_sql($sql)) print_object($unreads);


/*$sql = "select p.id postid, f.id forumid, p.userid
	from mdl_forumlv f 
		inner join mdl_forumlv_discussions d on d.forumlv = f.id 	
		inner join mdl_forumlv_posts p on p.discussion = d.id
	ORDER BY p.id, f.id, p.userid";


//$forumlv = new Forumlv(331);
//$forumlv->_avaliarDesempenho(529);

$offset = 54000;
$limit = 1000;
// 19400
while($offset <= 57000) {

	echo $sql = "select p.id postid, f.id forumid, p.userid
	from mdl_forumlv f 
		inner join mdl_forumlv_discussions d on d.forumlv = f.id 	
		inner join mdl_forumlv_posts p on p.discussion = d.id
	ORDER BY p.id, f.id, p.userid 
	LIMIT " . $limit . " OFFSET " . $offset;
	echo "<br/><br/>";

	if ($unreads = $DB->get_records_sql($sql)) 
	{
//print_object($unreads);
	       	foreach ($unreads as $unread) {
			echo $unread -> forumid . " -- " .$unread -> userid. " avaliando...<br/>";
			$forumlv = new Forumlv($unread -> forumid);
			$forumlv->_avaliarDesempenho($unread -> userid);
	        }

		echo '<br>As notas foram atualizadas corretamente!! ;)<br>';
	}

	$offset += 1000;
//	sleep(1);
}*/





?>
