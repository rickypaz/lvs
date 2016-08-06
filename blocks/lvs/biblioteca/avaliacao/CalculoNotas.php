<?php 
require_once 'avaliacao/LmsRating.php';
require_once 'business/AtividadeLv.php';

/**
 * class CalculoNotas
 *
 */
abstract class CalculoNotas {

	/**
	 *
	 *
	 * @param string nomeAtividade
	 * @return business::AtividadeLv
	 * @access public
	 */
	abstract public function criarNotasLv( $nomeAtividade );

	/**
	 *
	 *
	 * @return avaliacao::LmsRating
	 * @access public
	 */
	abstract public function criarLmsRating( );

}
?>