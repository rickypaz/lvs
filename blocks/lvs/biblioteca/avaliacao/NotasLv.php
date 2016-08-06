<?php
namespace uab\ifce\lvs\avaliacao;

use uab\ifce\lvs\business\Item;

/**
 * 	Define uma interface para gerenciamente de notas Lvs
 *
 *	@category LVs
 *	@package uab\ifce\lvs\avaliacao
 *	@author Ricky Paz (rickypaz@gmail.com)
 *	@version SVN $Id
 */
interface NotasLv {

	/**
	 *	Retorna o html de exibição de avaliação likert
	 *
	 * 	@param Item $item
	 * 	@return string html
	 * 	@access public
	 */
	public function avaliacaoAtual( Item $item );
	
	/**
	 * 	Retorna o html que exibe o nome do avaliador do item
	 * 
	 * 	@param Item $item
	 * 	@return string 
	 * 	@access public
	 */
	public function avaliadoPor( Item $item );
	
	/**
	 * 	Cria uma AtividadeLv baseado no nome
	 *
	 * 	@param string $atividade_nome nome da atividade
	 * 	@param int $atividade_id id da atividade
	 * 	@return AtividadeLV
	 * 	@access public
	 */
	public function criarAtividadeLv( $atividade_nome, $atividade_id );

	/**
	 *	Retorna o formulário html de avaliação likert
	 *
	 * 	@param Item $item item a ser avaliado
	 * 	@return string html
	 * 	@access public
	 */
	public function formAvaliacao( Item $item );
	
	/**
	 *	Dado um item, retorna uma avaliação com a respectiva nota, caso haja
	 *
	 * 	@param Item item
	 * 	@return AvaliacaoLv
	 * 	@access public
	 */
	public function getAvaliacao( Item $item );
	
	/**
	 *	Dados os itens, retorna todas as avaliações
	 *
	 * 	@param array:Item $itens
	 * 	@return array:AvaliacaoLv indexado pelos ids dos itens
	 * 	@access public
	 */
	public function getAvaliacoes( $itens );

	/**
	 *	Determina se um item pode ser avaliado pelo usuário logado
	 *
	 * 	@param Item item
	 * 	@return bool
	 * 	@access public
	 */
	public function podeAvaliar( Item $item );

	/**
	 *	Determina se a avaliação de um item pode ser vista pelo usuário logado 
	 *
	 * 	@param Item item
	 * 	@return bool
	 * 	@access public
	 */
	public function podeVerNota( Item $item );

	/**
	 *	Remove uma avaliação
	 *
	 * 	@param AvaliacaoLv $avaliacao
	 * 	@access public
	 */
	public function removerAvaliacao( AvaliacaoLv $avaliacao );

	/**
	 *	Salva ou atualiza uma avaliação
	 *
	 * 	@param AvaliacaoLv $avaliacao
	 * 	@access public
	 */
	public function salvarAvaliacao( AvaliacaoLv $avaliacao );
	
}
?>