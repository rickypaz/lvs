<?php
namespace uab\ifce\lvs\forms;

use uab\ifce\lvs\Carinhas;
use uab\ifce\lvs\Template;
use	uab\ifce\lvs\forms\FormsAvaliacao; 

/**
 * 	class FormsAvaliacaoImpl
 * 
 * 	@category LVs
 * 	@package uab\ifce\lvs\forms
 * 	@author Ricky Paz (rickypaz@gmail.com)
 * 	@version SVN $Id
 */
class FormsAvaliacaoImpl implements FormsAvaliacao {
	
	/**
	 * 	Responsável por transformar notas em carinhas e construir o html de exibição das carinhas
	 * 	@var Carinhas
	 * 	@access private
	 */
	private $_carinhas;
	
	/**
	 * 	Contém o html gerado pelos métodos de geração de formulário 
	 * 	@var string
	 * 	@access private
	 */
	private $_data = '';
	
	/**
	 * 	@var Template
	 * 	@see Template
	 * 	@access private
	 */
	private $_template;
	
	/**
	 *	Instancia FormsAvaliacaoImpl 
	 */
	public function __construct() {
		$this->_carinhas = new Carinhas();
	}
	
	/**
	 * 	Adiciona um input hidden no formulário
	 * 	
	 * 	@param string $atributos atributos do input separados por (;)
	 * 	@example "name=exemplo;value=teste"
	 * 	@access public
	 */
	public function adicionarInput($atributos) {
		$domHtml = new \DOMDocument();
		$domHtml->loadHTML($this->_data);
		
		$domInput = $this->_criarInputHidden($domHtml, $atributos);
	
		$form = $domHtml->getElementsByTagName('form')->item(0);
		$form->appendChild( $domInput );
			
		$this->_data = $domHtml->saveHTML();
	}
	
	/**
	 *	Retorna o componente html que exibe a nota lv da avaliação
	 *
	 * 	@param avaliacao::AvaliacaoLv avaliacao
	 * 	@return string html
	 * 	@access public
	 */
	public function avaliacaoAtual( $avaliacaoAtual ) {
		$nota = $avaliacaoAtual->getNota();
		$html = "<p style='display: inline;'>Avaliação Atual: </p>";
		$html .= "<div id='lvs_avaliacaoatual' style='display: inline;'>";
		
		if($nota === null) {
			$html .= '<b><i>Não avaliado</i></b>';
		} else {
			$carinha = $this->_carinhas->recuperarCarinha($nota);
			$html .= "<img src='$carinha[arquivo]' alt='$carinha[descricao]' />";
		}
		
		$html .= '</div>';
		
		return $html;
	}
	
	public function avaliacaoPor( $nome ) {
		return '<p>Avaliado por: ' . $nome;
	}
	
	/**
	 *	Retorna um formulário html com escala icônica likert para avaliação de um Item
	 *
	 * 	@param AvaliacaoLv avaliacaoAtual
	 * 	@return string html
	 * 	@access public
	 */
	public function likert( $avaliacaoAtual ) {
		$this->_template = new Template( LVS_DIRROOT . '/pages/html/form_avaliacao.html' );
		
		$this->_template->ACTION 					= LVS_WWWROOT . '/pages/rate.php';
		$this->_template->AVALIACAO_COMPONENTE 		= $avaliacaoAtual->getItem()->getComponente();
		$this->_template->AVALIACAO_COMPONENTE_ID 	= $avaliacaoAtual->getItem()->getItem()->id;
		$this->_template->AVALIACAO_ESTUDANTE 		= $avaliacaoAtual->getEstudante();
		$this->_template->CARINHAS_LIKERT			= $this->_carinhas->exibirHtml($avaliacaoAtual->getNota());
		
		$this->_template->block('SUBMIT');
		
		$this->_data = $this->_template->parse();
		
		return $this->_template->parse();
	}
	
	public function getForm() {
		return $this->_data;
	}

	/**
	 * 	Retorna um formulário ajax html com escala icônica likert para avaliação de um Item
	 *
	 * 	@param AvaliacaoLv $avaliacaoAtual
	 * 	@return string html
	 * 	@access public
	 */
	public function likertAjax( $avaliacaoAtual ) {
		$this->_template = new Template( LVS_DIRROOT . '/pages/html/form_avaliacao.html' );
		
		$this->_template->ACTION 					= LVS_WWWROOT . '/pages/rate.php';
		$this->_template->AVALIACAO_COMPONENTE 		= $avaliacaoAtual->getItem()->getComponente();
		$this->_template->AVALIACAO_COMPONENTE_ID 	= $avaliacaoAtual->getItem()->getItem()->id;
		$this->_template->AVALIACAO_ESTUDANTE 		= $avaliacaoAtual->getEstudante();
		$this->_template->CARINHAS_LIKERT			= $this->_carinhas->exibirHtml($avaliacaoAtual->getNota());
		
		$this->_data = $this->_template->parse();
		
		return $this->_template->parse();
	}

	/**
	 *	Retorna o formulário html estendido para avaliação de um Item
	 *
	 * 	@param avaliacao::AvaliacaoLv avaliacao
	 * 	@return string html
	 * 	@access public
	 */
	public function likertEstendido( $avaliacaoAtual ) {
		$this->_template = new Template( LVS_DIRROOT . '/pages/html/form_avaliacao.html' );
		
		$this->_template->ACTION 					= LVS_WWWROOT . '/pages/rate.php';
		$this->_template->AVALIACAO_COMPONENTE 		= $avaliacaoAtual->getItem()->getTipo();
		$this->_template->AVALIACAO_COMPONENTE_ID 	= $avaliacaoAtual->getItem()->getItem()->id;
		$this->_template->AVALIACAO_ESTUDANTE 		= $avaliacaoAtual->getEstudante();
		$this->_template->CARINHAS_LIKERT			= $this->_carinhas->exibirHtmlEstendido($avaliacaoAtual->getNota());
		
		$this->_template->block('SUBMIT');
		
		$this->_data = $this->_template->parse();
		
		return $this->_template->parse();
	}

	/**
	 *
	 *
	 * 	@param avaliacao::AvaliacaoLv avaliacaoAtual
	 * 	@return string
	 * 	@access public
	 */
	public function likertEstendidoAjax( $avaliacaoAtual ) {
		trigger_error("Implement " . __FUNCTION__);
	}
	
	/**
	 * 	Cria um input hidden com os atributos fornecidos
	 * 
	 * 	@param \DomDocument $domDocument
	 * 	@param string $atributos
	 * 	@return \DOMElement
	 */
	private function _criarInputHidden($domDocument, $atributos) {
		$domInput = $domDocument->createElement('input');
		$atributos = explode(';', $atributos);
		
		foreach ($atributos as $atributo) {
			$key_value = explode('=', $atributo, 2);
			$domInput->setAttribute($key_value[0], $key_value[1]);
		}
		
		$domInput->setAttribute('type', 'hidden');
		
		return $domInput;
	} 

}
?>