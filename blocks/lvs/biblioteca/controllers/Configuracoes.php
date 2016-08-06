<?php
namespace uab\ifce\lvs\controllers;
 
/**
 * class Configuracoes
 *
 */
class Configuracoes
{

	/**
	 *
	 * @access private
	 */
	private $cursolv;

	/**
	 *
	 * @access private
	 */
	private $somenteLeitura;

	/**
	 *
	 * @access private
	 */
	private $template;

	/**
	 *
	 *
	 * @param business::CursoLv cursolv

	 * @return
	 * @access public
	 */
	public function __construct( $cursolv ) {
		$this->cursolv = $cursolv;
	} 

	/**
	 *
	 *
	 * @param string modo
	 * @return string
	 * @access public
	 */
	public function configurarAtividadesDistancia( $modo ) {
	}

	/**
	 *
	 *
	 * @param string modo
	 * @return string
	 * @access public
	 */
	public function configurarAtividadesPresenciais( $modo ) {
	}

	/**
	 *
	 *
	 * @return string
	 * @access public
	 */
	public function configurarCursoLv( ) {
	}

	/**
	 *
	 *
	 * @return bool
	 * @access public
	 */
	public function isSomenteLeitura( ) {
		return $this->somenteLeitura;
	}

	/**
	 *
	 *
	 * @param bool somenteLeitura
	 * @return
	 * @access public
	 */
	public function setSomenteLeitura( $somenteLeitura ) {
		$this->somenteLeitura = $somenteLeitura;
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