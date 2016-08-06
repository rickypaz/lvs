<?php 
namespace uab\ifce\lvs\business;

use uab\ifce\lvs\avaliacao\AvaliacaoLv;

/**
 * 	Wrapper de Itens avaliados
 * 
 * 	@category LVs
 * 	@package uab\ifce\lvs\business
 * 	@author Ricky Paz (rickypaz@gmail.com)
 * 	@version SVN $Id
 */
class Item implements \Serializable {

	/**
	 *	Nome da atividade lv a qual o item pertence
	 *	@var string
	 * 	@access private
	 */
	private $_atividade;

	/**
	 * 	Nome do componente avaliado
	 *	@var string
	 * 	@access private
	 * 	@example 'post' para fóruns, 'version' para wikis, etc.
	 */
	private $_componente;

	/**
	 *	Item avaliado
	 *	@var \stdClass
	 * 	@access private
	 */
	private $_item;
	
	/**
	 * 	Avaliação do item
	 * 	@var AvaliacaoLv 
	 * 	@access private
	 */
	private $_avaliacao;
	
	/**
	 *	Instancia Item
	 *
	 * 	@param string nomeAtividade
	 * 	@param string tipo
	 * 	@param \stdClass item
	 */
	public function __construct( $atividade,  $tipo,  $item ) {
		$this->_atividade = $atividade;
		$this->_componente = $tipo;
		$this->_item = $item;
	} 
	
	/**
	 *	Retorna o nome da atividade a qual o item pertence
	 *
	 * 	@return string
	 * 	@access public
	 */
	public function getAtividade( ) {
		return $this->_atividade;
	}
	
	/**
	 *	Retorna a avaliação do item
	 *
	 * 	@return AvaliacaoLv
	 * 	@access public
	 */
	public function getAvaliacao( ) {
		return $this->_avaliacao;
	}
	
	/**
	 * 	Retorna o nome do componente avaliado
	 * 	
	 * 	@return string nome do componente
	 * 	@access public
	 */
	public function getComponente( ) {
		return $this->_componente;
	}
	
	/**
	 * 	Retorna o item avaliado
	 *
	 * 	@return \stdClass
	 * 	@access public
	 */
	public function getItem( ) {
		return $this->_item;
	}
	
	/**
	 * 	Altera o nome da atividade a qual o item pertence
	 * 	
	 * 	@param string $atividade nome da atividade
	 * 	@access public
	 */
	public function setAtividade( $atividade ) {
		$this->_atividade = $atividade;
	}
	
	/**
	 * 	Altera a avaliação do item
	 *
	 * 	@param AvaliacaoLv $avaliacao nova avaliação
	 * 	@access public
	 */
	public function setAvaliacao( AvaliacaoLv $avaliacao ) {
		$this->_avaliacao = $avaliacao;
	}
	
	/**
	 * 	Altera o nome do componente avaliado
	 *
	 * 	@param string $componente nome do componente
	 * 	@access public
	 */
	public function setComponente( $componente ) {
		$this->_componente = $componente;
	}
	
	/**
	 * 	Altera o item avaliado
	 * 	
	 * 	@param \stdClass $item
	 * 	@access public
	 */
	public function setItem( $item ) {
		$this->_item = $item;
	}
	
	/**
	 * 	@deprecated
	 * 	@see Item::getComponente( ... )
	 */
	public function getTipo( ) {
		return $this->getComponente();
	}
	
	/**
	 * 	@deprecated
	 * 	@see Item::getAtividade( )
	 */
	public function getNomeAtividade() {
		return $this->getAtividade();
	}
	
	/**
	 * 	@deprecated
	 * 	@see Item::setAtividade( ... )
	 */
	public function setNomeAtividade($nomeAtividade) {
		$this->setAtividade( $nomeAtividade );
	}
	
	/**
	 * 	@deprecated
	 * 	@see Item::setComponente( ... )
	 */
	public function setTipo( $componente ) {
		$this->setComponente( $componente );
	}
	
	public function serialize() {
		$data = array(
			'nomeAtividade' => $this->_atividade,
			'tipo' => $this->_componente,
			'item' => serialize($this->_item)
		);
		
		return serialize($data);
	}
	
	public function unserialize($data) {
		$data = unserialize($data);

		$this->_atividade = $data['nomeAtividade'];
		$this->_componente = $data['tipo'];
		$this->_item = unserialize($data['item']);
	}

}
?>