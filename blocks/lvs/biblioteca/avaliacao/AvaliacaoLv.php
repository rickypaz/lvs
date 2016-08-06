<?php 
namespace uab\ifce\lvs\avaliacao;

use uab\ifce\lvs\business\Item;

/**
 * 	Representa uma avaliação de um Item
 * 	
 * 	@category LVs
 * 	@package uab\ifce\lvs\avaliacao
 * 	@author Ricky Paz (rickypaz@gmail.com)
 * 	@version SVN $Id
 */
class AvaliacaoLv {

	/**
	 *	Id do usuário que avaliou
	 * 	@param int
	 * 	@access private
	 */
	private $_avaliador;

	/**
	 *	Id do usuário avaliado
	 *	@param int
	 * 	@access private
	 */
	private $_estudante;

	/**
	 *	Item avaliado
	 *	@param Item
	 * 	@access private
	 */
	private $_item;

	/**
	 *	Nota atribuída ao item
	 *	@param float
	 * 	@access private
	 */
	private $_nota;

	/**
	 *	Determina a escala likert utilizada. Se true, a escala é likert estendida
	 * 	@param bool
	 * 	@access private
	 */
	private $_carinhasEstendido = false;

	/**
	 *	Data da primeira avaliação
	 *	@param timestamp
	 *	@access private
	 */
	private $_dataCriacao;

	/**
	 *	Data da última avaliação
	 *	@param timestamp
	 * 	@access private
	 */
	private $_dataModificacao;

	/**
	 *	Retorna o id do avaliador
	 *	
	 *	@return int id do avaliador
	 *	@access public 
	 */
	public function getAvaliador( ) {
		return $this->_avaliador;
	}
	
	/**
	 *	Retorna o id do estudante
	 *
	 *	@return int 
	 *	@access public
	 */
	public function getEstudante( ) {
		return $this->_estudante;
	}
	
	/**
	 *	Retorna o item avaliado
	 *
	 *	@return Item
	 *	@access public
	 */
	public function getItem( ) {
		return $this->_item;
	}
	
	/**
	 *	Retorna a nota da avaliação
	 *
	 *	@return float
	 *	@access public
	 */
	public function getNota( ) {
		return $this->_nota;
	}
	
	/**
	 *	Retorna a data da primeira avaliação
	 *
	 *	@return timestamp
	 *	@access public
	 */
	public function getDataCriacao( ) {
		return $this->_dataCriacao;
	}
	
	/**
	 *	Retorna a data da última avaliação
	 *
	 *	@return timestamp
	 *	@access public
	 */
	public function getDataModificacao( ) {
		return $this->_dataModificacao;
	}
	
	/**
	 *	Determina se a escala likert utilizada é a normal ou estendida
	 *
	 *	@return bool
	 *	@access public
	 */
	public function isCarinhasEstendido( ) {
		return $this->_carinhasEstendido;
	}
	
	/**
	 *	Altera o avaliador do item
	 *
	 *	@return int $avaliador id do avaliador
	 *	@access public
	 */
	public function setAvaliador( $avaliador ) {
		$this->_avaliador = $avaliador;
	}
	
	/**
	 *	Altera a escala likert utilizada
	 *
	 *	@return bool $carinhasEstendido
	 *	@access public
	 */
	public function setCarinhasEstendido( $carinhasEstendido ) {
		$this->_carinhasEstendido = $carinhasEstendido;
	}
	
	/**
	 *	Altera a data da primeira avaliação
	 *
	 *	@return timestamp $dataCriacao
	 *	@access public
	 */
	public function setDataCriacao( $dataCriacao ) {
		$this->_dataCriacao = $dataCriacao;
	}
	
	/**
	 *	Altera a data da última avaliação
	 *
	 *	@return timestamp $dataModificacao
	 *	@access public
	 */
	public function setDataModificacao( $dataModificacao ) {
		$this->_dataModificacao = $dataModificacao;
	}
	
	/**
	 *	Altera o estudante avaliado
	 *
	 *	@return int $estudante id do estudante
	 *	@access public
	 */
	public function setEstudante( $estudante ) {
		$this->_estudante = $estudante;
	}
	
	/**
	 *	Altera o item avaliado
	 *
	 *	@return Item $item
	 *	@access public
	 */
	public function setItem( Item $item ) {
		$this->_item = $item;
	}

	/**
	 *	Altera a nota
	 *
	 *	@return float $nota
	 *	@access public
	 */
	public function setNota( $nota ) {
		$this->_nota = $nota;
	}
	
}
?>