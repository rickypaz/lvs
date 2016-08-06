<?php 
namespace uab\ifce\lvs\business;

/**
 * class Atividades
 *
 */
interface Atividades
{

	/**
	 *
	 * @return array
	 * @access public
	 */
	public function recuperarAtividades( );

	/**
	 *
	 * @return array
	 */
	public function recuperarAvaliacoes( $usuario_id );

	/**
	 * 
	 * 	@param int usuario_id código único de identificação do usuário
	 * 	@return stdClass { avaliacoes: array, notaFinal: float, numeroFaltas: int, numeroAtividades: int, carinhasAzuis: int, 
	 * 		carinhasVerdes: int, carinhasAmarelas: int, carinhasLaranjas: int, carinhasVermelhas: int, carinhasPretas: int}
	 * 	@access public
	 */
	public function recuperarDesempenho( $usuario_id );

	/**
	 *
	 * @param int atividade_id
	 * @access public
	 */
	public function removerAtividade( $atividade_id );

}
?>