<?php 
namespace uab\ifce\lvs\forms;

/**
 * class FormsAvaliacao
 * 
 * 	@category LVs
 * 	@package uab\ifce\lvs\forms
 * 	@author Ricky Paz (rickypaz@gmail.com)
 * 	@version SVN $Id
 */
interface FormsAvaliacao {

	/**
	 * 	Adiciona um input hidden ao formulário
	 *
	 * 	@param string $atributos atributos do input hidden
	 */
	public function adicionarInput($atributos);
	
	/**
	 *	Constrói html com a avaliação atual
	 *
	 * 	@param AvaliacaoLv $avaliacao
	 * 	@return string html
	 * 	@access public
	 */
	public function avaliacaoAtual( $avaliacao );
	
	/**
	 *	Constrói html com o nome do avaliador
	 *
	 * 	@param string $nome nome do avaliador
	 * 	@return string html
	 * 	@access public
	 */
	public function avaliacaoPor( $nome );

	/**
	 *	
	 *
	 * @param avaliacao::AvaliacaoLv avaliacaoAtual
	 * @return string
	 * @access public
	 */
	public function likert( $avaliacaoAtual );

	/**
	 *
	 *
	 * @param avaliacao::AvaliacaoLv avaliacaoAtual
	 * @return string
	 * @access public
	 */
	public function likertAjax( $avaliacaoAtual );

	/**
	 *
	 *
	 * @param avaliacao::AvaliacaoLv avaliacaoAtual
	 * @return string
	 * @access public
	 */
	public function likertEstendido( $avaliacaoAtual );

	/**
	 *
	 *
	 * @param avaliacao::AvaliacaoLv avaliacaoAtual
	 * @return string
	 * @access public
	 */
	public function likertEstendidoAjax( $avaliacaoAtual );

}
?>