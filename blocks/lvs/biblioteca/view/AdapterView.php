<?php
namespace uab\ifce\lvs\view;

/**
*  	Adapter para exibição das páginas geradas pelos LVs em qualquer AVA 
*  
*  	@category LVs
*	@package uab\ifce\lvs\view
* 	@author Ricky Persivo (rickypaz@gmail.com)
* 	@version 1.0
*/
interface AdapterView {
	
	/**
	 * 	Adiciona um arquivo css
	 *  
	 * 	@param string $arquivo caminho do arquivo .css
	 * 	@access public
	 */
	function css($arquivo);
	
	/**
	 *	Renderiza a página gerada
	 *
	 * 	@access publib
	 */
	function exibirPagina();
		
	/**
	 * 	Recupera a foto armazenada pelo usuário, caso exista
	 * 
	 * 	@param int $usuario
	 * 	@access public
	 */
	function fotoUsuario($usuario);
	
	/**
	 * 	Adiciona um arquivo javascript
	 *
	 * 	@param string $arquivo caminho do arquivo .js
	 * 	@access public
	 */
	function js($arquivo);
	
}
?>