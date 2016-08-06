<?php
namespace uab\ifce\lvs;
 
/**
 * 	Representação da Escala Likert na forma numérica e textual
 * 	
 * 	@category LVs
 * 	@package uab\ifce\lvs
 * 	@author Ricky Persivo (rickypaz@gmail.com)
 * 	@version SVN $Id
 */
final class EscalaLikert {

	/**
	 * @access public
	 */
	const MUITO_BOM = 4;

	/**
	 * @access public
	 */
	const BOM = 3;

	/**
	 * @access public
	 */
	const REGULAR = 2;

	/**
	 * @access public
	 */
	const FRACO = 1;

	/**
	 * @access public
	 */
	const NAO_SATISFATORIO = 0;

	/**
	 * @access public
	 */
	const NEUTRO = -2;

	/**
	 *	Retorna a escala likert em ordem decrescente 
	 *
	 * 	@return array:int
	 * 	@static
	 * 	@access public
	 */
	public static function getEscala( ) {
		$escala = array(
				EscalaLikert::MUITO_BOM,
				EscalaLikert::BOM,
				EscalaLikert::REGULAR,
				EscalaLikert::FRACO,
				EscalaLikert::NAO_SATISFATORIO,
				EscalaLikert::NEUTRO
		);
			
		return $escala;
	}

	/**
	 *	Retorna a escala likert estendida em ordem decrescente
	 *
	 * 	@return array:float
	 * 	@static
	 * 	@access public
	 */
	public static function getEscalaEstendido( ) {
		$escala = array(
				"10", "9.98", "9.91", "9.81", "9.66",
				"9.47", "9.24", "8.97", "8.66", "8.31",
				"7.93", "7.52", "7.07", "6.59", "6.09",
				"5.56", "5.00", "4.42", "3.83", "3.21",
				"2.59", "1.95", "1.31", "0.70", "0",
				EscalaLikert::NEUTRO
		);

		return $escala;
	}

	/**
	 *	Dado o valor likert, retorna o seu valor numérico correspondente 
	 *
	 * 	@param string $likert
	 * 	@return int
	 * 	@static
	 * 	@access public
	 */
	public static function parseInt( $likert ) {
		switch($likert) {
			case 'MUITO_BOM'		: return EscalaLikert::MUITO_BOM;
			case 'BOM'				: return EscalaLikert::BOM;
			case 'REGULAR'			: return EscalaLikert::REGULAR;
			case 'FRACO'			: return EscalaLikert::FRACO;
			case 'NAO_SATISFATORIO'	: return EscalaLikert::NAO_SATISFATORIO;
			case 'NEUTRO'			: return EscalaLikert::NEUTRO;
		}
	}

	/**
	 *	Dado uma nota likert, retorna o likert correspondente
	 *
	 * 	@param int $nota likert
	 * 	@return string
	 * 	@static
	 * 	@access public
	 */
	public static function parseLikert( $nota ) {
		switch ($nota) {
			case EscalaLikert::MUITO_BOM: 			return 'MUITO_BOM';
			case EscalaLikert::BOM: 				return 'BOM';
			case EscalaLikert::REGULAR: 			return 'REGULAR';
			case EscalaLikert::FRACO: 				return 'FRACO';
			case EscalaLikert::NAO_SATISFATORIO: 	return 'NAO_SATISFATORIO';
			case EscalaLikert::NEUTRO: 				return 'NEUTRO';
		}
	}

	/**
	 *	Dado uma nota likert estendida, retorna o likert correspondente
	 *
	 * 	@param float $nota likert estendida
	 * 	@return string
	 * 	@static
	 * 	@access public
	 */
	public static function parseLikertEstendido( $nota ) {
		if ($nota >= 0 && $nota <= 2.59)
			return 'NAO_SATISFATORIO';

		if ($nota > 2.59 && $nota <= 5.56)
			return 'FRACO';

		if ($nota > 5.56 && $nota <= 7.93)
			return 'REGULAR';

		if ($nota > 7.93 && $nota <= 9.47)
			return 'BOM';

		if($nota > 9.47 && $nota <= 10)
			return 'MUITO_BOM';

		if($nota == -2)
			return 'NEUTRO';
		
		return '-';
	}

}
?>