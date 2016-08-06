<?php
namespace uab\ifce\lvs\controllers;

/**
* 	Adapter para o controller das requisições recebidas
*
*	@category LVs
*	@package uab\ifce\lvs\controllers
* 	@author Ricky Persivo (rickypaz@gmail.com)
* 	@version SVN $Id
*/
interface AdapterController {
	
	/**
	 * 	Redireciona a página para outra url
	 * 
	 * 	@param string $url destino
	 * 	@param string $mensagem mensagem a ser exibida durante o redirecionamento
	 * 	@param float $delay tempo de exibição da mensagem
	 */
	function redirect($url, $mensagem=null, $delay=null);
	
	/**
	 *	Chave única do usuário logado
	 *
	 *	@return string 
	 */
	function sesskey();
	
}
?>