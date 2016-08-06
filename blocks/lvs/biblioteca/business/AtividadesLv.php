<?php 
namespace uab\ifce\lvs\business;

/**
 * 	Gerencia um conjuntos de atividades pertencentes ao mesmo módulo
 *
 *	@package uab\ifce\lvs\business
 */
abstract class AtividadesLv implements Atividades
{

	/**
	 *	@var CursoLv
	 * 	@access private
	 */
	protected $cursolv;
	
	/**
	 *
	 * @param mixed atividades
	 * @access public
	 */
	abstract public function recuperarConfiguracao( $atividades );
	
	/**
	 *
	 * @param mixed atividades
	 * @access public
	 */
	abstract public function salvarConfiguracao( $atividades );

	/**
	 *
	 *
	 * @return business::CursoLv
	 * @access public
	 */
	public function getCursoLv( ) {
		return $this->cursolv;
	}
	
	/**
	 *
	 *
	 * @param business::CursoLv cursolv
	
	 * @return
	 * @access public
	 */
	public function setCursoLv( $cursolv ) {
		$this->cursolv = $cursolv;
	}

}
?>