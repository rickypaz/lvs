<?php 
namespace uab\ifce\lvs\avaliacao;

use uab\ifce\lvs\moodle2\avaliacao\Moodle2NotasLv;

/**
 * 	Fábrica de Gerenciadores de Notas. Eles são responsáveis por mostrar e armazenar
 *  avaliações.
 *  Gerenciadores disponíveis: moodle2
 *  
 *  @package uab\ifce\lvs\avaliacao
 */
class NotasLvFactory {

	/**
	 *	Cria um gerenciador de nota baseado no tipo de AVA
	 *
	 * 	@param string $tipo nome do AVA
	 * 	@return NotasLv
	 * 	@static
	 * 	@access public
	 */
	public static function criarGerenciador( $tipo ) {
		if(strcmp($tipo, 'moodle2') == 0) {
			return new Moodle2NotasLv();
		}
		return null; // FIXME deve lançar uma exceção!
	}

}
?>