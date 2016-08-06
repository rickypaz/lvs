<?php
namespace uab\ifce\lvs\util;

use uab\ifce\lvs\business\Item;

/**
*  	Enter description here...
*	@package
* 	@author Ricky Persivo (rickypaz@gmail.com)
* 	@version
*/
class Cache {
	
	/**
	 * 	Arquivo que armazena os dados temporários
	 * 	@var string
	 */
	private $_file;
	
	/**
	 * 	Tag sobre a qual a cache deve operar
	 * 	@var \DOMElement
	 */
	private $_root;
	
	/**
	 * 	Nome da tag que deve ser criada ou atualizada
	 * 	@var string
	 */
	private $_tagname;
	
	/**
	 * 	
	 * 	@var \DOMDocument
	 */
	private $_domDocument;
	
	public function __construct($source) {
		$this->_file = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $source;
		$this->carregarArquivo();
	}
	
	private function carregarArquivo() {
		$this->_domDocument = new \DOMDocument('1.0', 'utf-8');
		
		if(!file_exists($this->_file)) {
			$this->_domDocument->save($this->_file);
		} else {
			$this->_domDocument->load($this->_file);
		}
	}
	
	public function recuperarDado($id) {
		$dadoNode = $this->_domDocument->getElementById($id);
		return unserialize($dadoNode->nodeValue); 
	}
	
	/**
	 * 	Armazena um dado. Se o id for fornecido, os dados armazenados serão atualizados, caso contrário, serão criados.
	 * 
	 * 	@param mixed $item 
	 * 	@param string $id
	 */
	public function salvarDado($dado, $id = null) {
		if(isset($id)) {
			$itemAtual = $this->_root->getElementById($id);
			$this->_root->removeChild($itemAtual);
		} else {
			$id = uniqid('data');
		}
		
		$dadoNode = $this->_criarNode($dado, $id);
		$this->_root->appendChild($dadoNode);
		
		$this->_domDocument->save($this->_file);
		
		return $id;
	}
	
	/**
	 * 	Cria um DomElement correspondente ao dado a ser armazenado
	 * 
	 * 	@param mixed $dado
	 * 	@return DOMElement
	 */
	private function _criarNode($dado, $id) {
		$dado = serialize($dado);
		$dadoNode = $this->_domDocument->createElement( $this->_tagname, $dado );
		$dadoNode->setAttribute('xml:id', $id);
		
		return $dadoNode; 
	}
	
	public function getTagname() {
		return $this->_tagname;
	}
	
	public function setTagname($tagname) {
		$this->_tagname = $tagname;
		return $this;
	}
	
	public function getRoot() {
		if(!empty($this->_root)) {
			return  $this->_root->tagName;
		}
		return null;
	}
	
	public function setRoot($rootname) {
		$elements = $this->_domDocument->getElementsByTagName($rootname);
		
		if($elements->length > 1)
			throw new Exception(); // FIXME adicionar uma mensagem de exceção
			
		if($elements->length == 0) {
			$node = $this->_domDocument->createElement($rootname);
			$this->_domDocument->appendChild($node);
			$this->_domDocument->save($this->_file);
		} else {
			$node = $elements->item(0);
		}
		
		$this->_root = $node;
		 
		return $this;
	}
	
}
?>