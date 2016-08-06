<?php 
namespace uab\ifce\lvs\business;

/**
 * 	Gerencia um conjuntos de atividades a distância que pertencem ao mesmo módulo em um curso lv.
 * 	Por exemplo, gerenciador de fóruns lvs, de tarefas lvs, de wikis lvs, etc.
 *
 *	@category LVs
 *	@package uab\ifce\lvs\business
 * 	@author Ricky Persivo (rickypaz@gmail.com)
 * 	@version 1.0
 * 	@see GerenciadorAtividades
 */
abstract class GerenciadorAtividadesDistancia implements GerenciadorAtividades {

	/**
	 * 	Curso Lv sobre o qual o gerenciador de atividades irá atuar
	 *	@var CursoLv
	 * 	@access protected
	 */
	protected $_cursolv;
	
	/**
	 * 	Retorna a configuração lv de uma ou mais atividades
	 *
	 * 	@param mixed $atividades um objeto ou array de objetos de uma atividade
	 * 	@return array:\stdClass
	 * 	@access public
	 *  @abstract
	 */
	abstract public function recuperarConfiguracao( $atividades );
	
	/**
	 * 	Retorna o desempenho de estudante em cada atividade do módulo
	 *
	 * 	@param int $estudante id do estudante
	 * 	@return array:\stdClass
	 */
	abstract function recuperarDesempenhoPorAtividade( $estudante );
	
	/**
	 *	Armazena ou atualiza as configurações lvs de cada atividade
	 *
	 * 	@param mixed $atividades um objeto ou uma array de objetos contendo as configuracões a armazenar
	 * 	@access public
	 * 	@abstract
	 */
	abstract public function salvarConfiguracao( $atividades );

	/**
	 *	Retorna o curso lv associado ao conjunto de atividades
	 *
	 * 	@return CursoLv
	 * 	@access public
	 */
	public function getCursoLv( ) {
		return $this->_cursolv;
	}
	
	/**
	 *	Altera o curso lv associado ao conjunto de atividades
	 *
	 * 	@param CursoLv $cursolv
	 * 	@access public
	 */
	public function setCursoLv( CursoLv $cursolv ) {
		$this->_cursolv = $cursolv;
	}

}
?>