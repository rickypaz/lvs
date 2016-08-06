<?php 
namespace uab\ifce\lvs\business;

/**
 * 	Interface padrão para gerenciadores de atividades. Eles são responsáveis por adicionar, remover e atualizar um conjunto de 
 * 	atividades e por avaliar o desempenho de estudantes.
 * 
 * 	@category LVs
 * 	@package uab\ifce\lvs\business
 * 	@author Ricky Persivo (rickypaz@gmail.com)
 * 	@version 1.0
 */
interface GerenciadorAtividades {

	/**
	 *	Retorna o total de ausências de um estudante
	 *
	 *	@param int $estudante id do estudante
	 * 	@return int número de faltas
	 * 	@access public
	 */
	public function numeroFaltas( $estudante );
	
	/**
	 * 	Retorna o número de atividades
	 *
	 * 	@return int número de atividades
	 * 	@access public
	 */
	public function quantidadeAtividades( );
	
	/**
	 *	Retorna todas as atividades
	 *	
	 * 	@return array:\stdClass
	 * 	@access public
	 */
	public function recuperarAtividades( );

	/**
	 *	Retorna o desempenho de um estudante em cada atividade avaliada
	 *
	 * 	@param int $estudante id do estudante
	 * 	@return array:\stdClass cada elemento representa uma avaliação
	 * 	@access public
	 */
	public function recuperarAvaliacoes( $estudante );

	/**
	 * 	Retorna o desempenho geral de um estudante no conjunto de atividades
	 * 
	 * 	@param int $estudante id do estudante
	 * 	@return stdClass { avaliacoes: array, notaFinal: float, numeroFaltas: int, numeroAtividades: int, carinhasAzuis: int, 
	 * 		carinhasVerdes: int, carinhasAmarelas: int, carinhasLaranjas: int, carinhasVermelhas: int, carinhasPretas: int}
	 * 	@access public
	 * 	@todo alterar nome do método. Ele não apenas recupera, como calcula
	 */
	public function recuperarDesempenho( $estudante );

	/**
	 *	Remove uma atividade
	 *
	 * 	@param int $atividade id da atividade
	 * 	@access public
	 */
	public function removerAtividade( $atividade );

}
?>