<?php
namespace uab\ifce\lvs;

/**
 *  Carrega os dependências de classes dos LVs automaticamente
 *  
 *  @category LVs
 *	@package uab\ifce\lvs
 * 	@author Ricky Persivo (rickypaz@gmail.com)
 * 	@version SVN $Id
 */
class LvsAutoLoader {

	/**
	 * 	Diretório principal onde está armazenada a biblioteca lv
	 * 	@var string
	 */
	private $_basedir;
	
	/**
	 * 	Separador de namespace
	 * 	@var string
	 */
	private $_namespaceSeparator;
	
	/**
	 * 	Nome do vendor
	 * 	@var string
	 */
	private $_vendorsnamespace;

	/**
	 * 	Instancia LvsAutoLoader
	 * 	@param string $dirroot diretório principal onde está armazenada a biblioteca lv
	 */
	public function __construct($dirroot) {
		$this->_init($dirroot);
	}

	private function _init($dirroot) {
		$this->_namespaceSeparator = '\\';
		$this->_vendorsnamespace = 'uab\ifce\lvs\\';
		$this->_basedir = $dirroot;
	}

	/**
	 *	Registra o autoload dos lvs
	 *
	 *	@access public 
	 */
	public function registrar() {
		spl_autoload_register(array($this, 'loadClass'));
	}

	/**
	 * 	Verifica se a classe está contida na biblioteca lv e a carrega, se estiver
	 * 
	 * 	@param string $classname classe a ser carregada
	 */
	public function loadClass($classname) {
		$classname = str_replace($this->_vendorsnamespace, '', $classname);
		$classname = str_replace($this->_namespaceSeparator, DIRECTORY_SEPARATOR, $classname);
		$filename = $this->_basedir . DIRECTORY_SEPARATOR . $classname . '.php';
		
		if(is_readable($filename)) {
			require_once $filename;
		} 
	}

}
?>