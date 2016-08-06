<?php 
require_once 'avaliacao/AvaliacaoLv.php';

/**
 * 	Interface para recuperação e armazenamento de notas 
 *
 *	@category LVs
 *	@package
 *	@author Ricky Paz (rickypaz@gmail.com)
 *	@version SVN $Id
 *	@todo essa classe é usada?!?!
 */
interface LmsRating {

	/**
	 *	Remove uma avaliação
	 *
	 * 	@param avaliacao::AvaliacaoLv avaliacao
	 * 	@access public
	 */
	public function removerAvaliacao( $avaliacao );
	
	/**
	 *	Persiste uma avaliação
	 *
	 * 	@param avaliacao::AvaliacaoLv avaliacao
	 * 	@access public
	 */
	public function salvarAvaliacao( $avaliacao );

}
?>