<?php
namespace uab\ifce\lvs;

/**
 * 	class Carinhas
 * 
 * 	@category LVs
 * 	@package uab\ifce\lvs
 * 	@author Ricky Persivo (rickypaz@gmail.com)
 * 	@version SVN $Id
 * 	@todo alterar nome para EscalaLikertIconica
 */
class Carinhas {

	/**
	 *
	 * @access private
	 */
	private $diretorioImagens;

	/**
	 *
	 * @access private
	 */
	private $diretorioTemplate;

	/**
	 *
	 * @access private
	 */
	private $imagens;

	/**
	 *
	 * @access private
	 */
	private $template;

	/**
	 * 	Instancia Carinhas
	 */
	public function __construct() {
		$this->diretorioImagens = LVS_WWWROOT . '/imgs/carinhas/moodle3/';
		$this->diretorioTemplate = LVS_DIRROOT . '/pages/html/';
		$this->imagens = array(
				'MUITO_BOM' 		=> array('arquivo' => 'azul.gif', 'descricao' => "MUITO_BOM"),
				'BOM'		 		=> array('arquivo' => 'verde.gif', 'descricao' => "BOM"),
				'REGULAR' 			=> array('arquivo' => 'amarela.gif', 'descricao' => "REGULAR"),
				'FRACO' 			=> array('arquivo' => 'laranja.gif', 'descricao' => "FRACO"),
				'NAO_SATISFATORIO' 	=> array('arquivo' => 'vermelha.gif', 'descricao' => "NAO_SATISFATORIO"),
				'NEUTRO' 			=> array('arquivo' => 'cinza.gif', 'descricao' => "NEUTRO")
		);
	}

	/**
	 *	Retorna todas as carinhas correspodentes à escala likert
	 *
	 * 	@return array:string cada item contém o link para a imagem, sua descrição e seu valor
	 * 	@access public
	 */
	public function recuperarCarinhas() {
		$carinhas = $this->imagens;

		foreach ($carinhas as &$carinha) {
			$carinha['arquivo'] = $this->diretorioImagens . $carinha['arquivo'];
			$carinha['valor'] = EscalaLikert::parseInt($carinha['descricao']);
		}
		
		return $carinhas;
	}

	/**
	 *	Retorna a carinha likert correspondente à nota dada
	 *
	 * 	@param int $nota 
	 * 	@return array:string contém link para a imagem e a descrição da carinha 
	 * 	@access public
	 */
	public function recuperarCarinha($nota) {
		$likert = ( is_int($nota) || ctype_digit($nota) || ($nota == intval($nota)) && !is_float($nota) ) ? EscalaLikert::parseLikert($nota) : EscalaLikert::parseLikertEstendido($nota);

		if (isset($this->imagens[$likert])) {
			$carinha = $this->imagens[$likert];
			$carinha['arquivo'] = $this->diretorioImagens . $carinha['arquivo'];

			return $carinha;
		}
	}
	
	/**
	 * 	Retorna um <img> de html para exibição de uma carinha referente à nota dada
	 * 
	 * 	@param int $nota
	 * 	@return string html tag <img>
	 */
	public function recuperarCarinhaHtml($nota) {
		$likert = ( is_int($nota) || ctype_digit($nota) || ($nota == intval($nota)) && !is_float($nota) ) ? EscalaLikert::parseLikert($nota) : EscalaLikert::parseLikertEstendido($nota);
		
		if ($likert != '-') {		
			$carinha = $this->imagens[$likert];
			$carinha['arquivo'] = $this->diretorioImagens . $carinha['arquivo'];
		
			return "<img src='$carinha[arquivo]' title='$carinha[descricao]' />";
		}
		
		return '-';
	}
	
	/**
	 *	Retorna o html que contém a tabela likert de seleção de carinha. As tabela contém os cinco valores likert
	 *
	 *	FIXME o método não exibe, apenas constrói o componente visual. Alterar nome do método
	 * 	@param int notaAtual
	 * 	@return string html
	 * 	@access public
	 */
	public function exibirHtml( $notaAtual ) {
		$arquivo_template = $this->diretorioTemplate . 'likert.html';
		$template = new Template($arquivo_template);

		$carinhas = $this->recuperarCarinhas();
			
		foreach ($carinhas as $carinha) {
			$template->IMAGEM_ALT = $carinha['descricao'];
			$template->IMAGEM_NOME = $carinha['descricao'];
			$template->IMAGEM_SRC = $carinha['arquivo'];

			$template->block('CARINHA_IMAGEM');

			$template->VALOR_TITULO = str_replace('_', ' ', $carinha['descricao']);
			$template->VALOR_VALOR = $carinha['valor'];
				
			if($notaAtual !== null && $carinha['valor'] == $notaAtual)
				$template->VALOR_CHECKED = 'checked="checked"';
			else
				$template->VALOR_CHECKED = '';
				
			$template->block('CARINHA_VALOR');
		}
			
		return $template->parse();
	}

	/**
	 *	Retorna o html que contém a tabela likert estendido de seleção de carinha
	 *
	 *	FIXME o método não exibe, apenas constrói o componente visual. Alterar nome do método
	 * 	@param float nota
	 * 	@return string html
	 * 	@access public
	 */
	public function exibirHtmlEstendido( $notaAtual ) {
		$arquivo_template = $this->diretorioTemplate . 'likert_estendido.html';
		$template = new Template($arquivo_template);
					
		$carinhas = $this->recuperarCarinhas();
		$escala = EscalaLikert::getEscalaEstendido();
		$cores = $this->coresCarinhasEstendidas();
		
		$ic = 0;
		foreach ($carinhas as $carinha) {
			$qtdeValores = ($carinha['valor'] !== EscalaLikert::NEUTRO) ? 5 : 1;

			$template->IMAGEM_ALT = $carinha['descricao'];
			$template->IMAGEM_NOME = $carinha['descricao'];
			$template->IMAGEM_SRC = $carinha['arquivo'];

			$template->block('CARINHA_IMAGEM');

			for($i=0; $i<$qtdeValores; $i++) {
				$valor = $escala[($ic*5) + $i];

				$template->VALOR_COR = $cores[$valor];
				$template->VALOR_TITULO = ($carinha['valor'] != EscalaLikert::NEUTRO) ? $escala[($ic*5) + $i] : '-';
				$template->VALOR_VALOR = $valor;
				
				if($notaAtual !== null && $valor == $notaAtual)
					$template->VALOR_CHECKED = 'checked="checked"';
				else
					$template->VALOR_CHECKED = '';
					
				$template->block('CARINHA_VALOR');
			}
			
			$template->block('CARINHA_VALORES');
			$ic++;
		}
			
		return $template->parse();
	}

	
	/**
	 * 	Retorna um array que contém o relacionamento entre uma nota likert estendida e uma cor em hexadecimal
	 * 	
	 * 	@return array:string 
	 */
	private function coresCarinhasEstendidas() {
		$cores = array(
			EscalaLikert::NEUTRO => "#FFFFFF", //Branco
			"0" => "#FF0000", // Vermelho
			"0.70" => "#FF0000", // Vermelho
			"1.31" => "#FF0000", // Vermelho
			"1.95" => "#FF6347", // Vermelho
			"2.59" => "#FF6347", // Vermelho
			"3.21" => "#EE7600", // Laranja
			"3.83" => "#EE7600", // Laranja
			"4.42" => "#FFA500", // Laranja
			"5.00" => "#FFD700", // Laranja
			"5.56" => "#FFD700", // Laranja
			"6.09" => "#FFFF00", // Amarelo
			"6.59" => "#FFFF00", // Amarelo
			"7.07" => "#FFF68F", // Amarelo
			"7.52" => "#FFFF00", // Amarelo
			"7.93" => "#FFF68F", // Amarelo
			"8.31" => "#C0FF3E", // Verde
			"8.66" => "#C0FF3E", // Verde
			"8.97" => "#00EE00", // Verde
			"9.24" => "#4EEE94", // Verde
			"9.47" => "#66CDAA", // Verde
			"9.66" => "#87CEFA", // Azul
			"9.81" => "#87CEFA", // Azul
			"9.91" => "#1E90FF", // Azul
			"9.98" => "#1E90FF", // Azul
			"10" => "#0000FF" // Azul
		);

		return $cores;
	}

}
?>
