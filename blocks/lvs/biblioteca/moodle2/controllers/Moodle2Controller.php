<?php
namespace uab\ifce\lvs\moodle2\controllers;

use uab\ifce\lvs\controllers\AdapterController;

/**
*  	Controller do Moodle 2 responsável por procedimentos comuns a todos os controllers
*  	
*  	@category LVs
*	@package uab\ifce\lvs\moodle2\controllers
* 	@author Ricky Persivo (rickypaz@gmail.com)
* 	@version SVN $Id
* 	@see AdapterController
*/
class Moodle2Controller implements AdapterController {
	
	public function redirect($url, $mensagem=null, $delay=null) {
		redirect( $url, $mensagem, $delay );
	}
	
	public function sesskey() {
		return sesskey();
	}
	
}
?>