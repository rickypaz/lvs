<?php
namespace uab\ifce\lvs;

/**
 * 	Representação de uma nota lv no Lvômetro
 * 
 * 	@category LVs
 * 	@package uab\ifce\lvs
 * 	@author Allyson Bonetti 
 * 	@author Ricky Paz (rickypaz@gmail.com)
 * 	@version SVN $Id
 */
class Lvometro {

	private static $imagens = array("0.00" => 'lvometro0.png', "0.70" => 'lvometro1.png', "1.31" => 'lvometro2.png',
			"1.95" => 'lvometro3.png', "2.59" => 'lvometro4.png', "3.21" => 'lvometro5.png',
			"3.83" => 'lvometro6.png', "4.42" => 'lvometro7.png', "5.00" => 'lvometro8.png',
			"5.56" => 'lvometro9.png', "6.09" => 'lvometro10.png', "6.59" => 'lvometro11.png',
			"7.07" => 'lvometro12.png', "7.52" => 'lvometro13.png', "7.93" => 'lvometro14.png',
			"8.31" => 'lvometro15.png', "8.66" => 'lvometro16.png', "8.97" => 'lvometro17.png',
			"9.24" => 'lvometro18.png', "9.47" => 'lvometro19.png', "9.66" => 'lvometro20.png',
			"9.81" => 'lvometro21.png', "9.91" => 'lvometro22.png', "9.98" => 'lvometro23.png',
			"10.00" => 'lvometro24.png'
	);

	public static function retornaLvometro($nota) {
		$nota = (isset($nota)) ? $nota : 0;
		$nota = number_format($nota, 2, '.', ' ');

		return LVS_WWWROOT . '/imgs/lvometro/' . Lvometro::$imagens[$nota];
	}

}
?>