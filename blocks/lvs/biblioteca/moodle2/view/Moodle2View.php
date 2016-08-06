<?php
namespace uab\ifce\lvs\moodle2\view;

use uab\ifce\lvs\view\AdapterView;

/**
*  	Exibe o conteúdo html no Moodle 2
*  
*	@category LVs
*	@package uab\ifce\lvs\moodle2\view
* 	@author Ricky Persivo (rickypaz@gmail.com)
* 	@version SVN $Id
* 	@see AdapterView
*/
class Moodle2View implements AdapterView {
	
	/**
	 * 	Conteúdo a ser exibido
	 * 	@var string html
	 */
	private $_conteudo;
	
	public function css($arquivo) {
		global $PAGE;
		$PAGE->requires->css($arquivo);
	}
	
	public function exibirPagina() {
		global $OUTPUT;
		
		echo $OUTPUT->header();
		echo $this->_conteudo;
		echo $OUTPUT->footer();
	}
	
	public function fotoUsuario($usuario) {
		global $COURSE, $OUTPUT, $DB;
		$user = $DB->get_record('user', array('id'=>$usuario));
		
		return $OUTPUT->user_picture($user, array('courseid'=>$COURSE->id));
	}
	
	public function js($arquivo) {
		global $PAGE;
		$PAGE->requires->js($arquivo,true);
	}
	
	/**
	 * 	Altera o conteúdo a ser exibido
	 * 	
	 * 	@param string $content html
	 * 	@access public
	 * 	@todo alterar para setConteudo
	 */
	public function setContent($conteudo) {
		$this->_conteudo = $conteudo;
	}
	
}
?>