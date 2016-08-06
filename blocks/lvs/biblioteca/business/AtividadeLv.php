<?php
namespace uab\ifce\lvs\business;
 
use uab\ifce\lvs\avaliacao\AvaliacaoLv;

/**
 * 	Define uma interface para classes que implementarão um sistema de avaliação de uma atividade a distância 
 * 	
 * 	@category LVs
 * 	@package uab\ifce\lvs\business
 * 	@author Ricky Paz (rickypaz@gmail.com)
 * 	@version SVN $Id
 */
abstract class AtividadeLv {

	const ALFA = 7.5;
	
	/**
	 * 	Determina se o item corresponde ao documento final gerado pela atividade ou se é parte do mesmo
	 * 
	 * 	@param Item $item
	 * 	@return bool
	 * 	@access public
	 */
	public abstract function contribuicao( Item $item );
	
	/**
	 * 	Retorna a avaliação de um item 
	 * 
	 * 	@param Item $item
	 * 	@return AvaliacaoLv
	 * 	@access public
	 */
	public abstract function getAvaliacao( Item $item );
	
	/**
	 * 	Retorna a nota do estudante
	 *
	 * 	@param int $estudante id do estudante
	 * 	@return float nota
	 * 	@access public
	 */
	public abstract function getNota( $estudante );
	
	/**
	 * 	Determina se o usuário logado tem permissão para avaliar um item
	 * 
	 * 	@param Item $item item a ser avaliado
	 * 	@return bool
	 * 	@access public
	 */
	public abstract function podeAvaliar( Item $item );
	
	/**
	 * 	Determina se o usuário logado tem permissão para ver a avaliação um item
	 * 
	 * 	@param Item $item
	 * 	@return bool
	 * 	@access public
	 */
	public abstract function podeVerNota( Item $item );

	/**
	 *	Remove uma avaliação e reavalia o desempenho do estudante na atividade
	 *
	 * 	@param AvaliacaoLv $avaliacao
	 * 	@access public
	 */
	public abstract function removerAvaliacao( $avaliacao );
	
	/**
	 *	Salva uma avaliação e reavalia o desempenho do estudante na atividade
	 *
	 * 	@param AvaliacaoLv $avaliacao nova avaliação
	 * 	@access public
	 */
	public abstract function salvarAvaliacao( AvaliacaoLv $avaliacao );
	
	/**
	 * 	Calcula o fator ß dado o módulo do vetor e a quantidade de carinhas recebidas na atividade
	 *
	 * 	@param float $LVx módulo do vetor
	 * 	@param array $carinhas número de carinhas por cor [azul: int, verde: int, amarela: int, laranja: int, vermelha: int, preta: int]
	 * 	@return float beta
	 * 	@access protected
	 */
	protected function calcularBeta($LVx, $carinhas) {
		$positividade = $LVx + 3 * $carinhas['azul'] + 2 * $carinhas['verde'] + $carinhas['amarela'];
		$negatividade = sqrt(100 - pow($LVx, 2)) + $carinhas['laranja'] + 2 * $carinhas['vermelha'];
	
		if ($negatividade == 0)
			$negatividade = 1;
	
		return round(($positividade / $negatividade), 2);
	}
	
	/**
	 * 	Calcula o módulo do vetor LVx dada a variação angular
	 *
	 * 	@param float $I variação angular
	 * 	@return float
	 * 	@access protected
	 */
	protected function calcularModuloVetor($I) {
		return round(10 * cos(deg2rad((-12 * AtividadeLv::ALFA) + $I)), 2);
	}
	
	/**
	 * 	Delimita os valores possíveis da variação angular ao primeiro quadrante
	 *
	 * 	@param float $I variação angular
	 * 	@return float entre 0 e 90
	 * 	@access protected
	 */
	protected function limitarAoQuadrante($I) {
		if ($I > 90)
			return 90;
	
		if ($I < 0)
			return 0;
	
		return $I;
	}

}
?>