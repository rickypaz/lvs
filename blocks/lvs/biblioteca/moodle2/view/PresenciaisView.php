<?php
namespace uab\ifce\lvs\moodle2\view;

use uab\ifce\lvs\Template;

/**
*  	Constrói e exibe as páginas html relacionada às atividades presenciais
*  
*  	@category LVs
*	@package uab\ifce\lvs\moodle2\view
* 	@author Ricky Persivo (rickypaz@gmail.com)
* 	@version SVN $Id
*/
class PresenciaisView {

	/**
	 * 	Exibe a página html gerada no AVA
	 * 	@var \uab\ifce\lvs\view\AdapterView
	 * 	@see \uab\ifce\lvs\view\AdapterView
	 */
	private $_adapterView;
	
	/**
	 * 	Contém os dados enviados utilizados na construção da página html
	 * 	@var array:mixed
	 */
	private $_data;
	
	/**
	 * 	Responsável por gerar a página html
	 * 	@var Template
	 * 	@see Template
	 */
	private $_template;
	
	/**
	 *	Instancia PresenciaisView 
	 */
	public function __construct() {
		$this->_adapterView = new Moodle2View();
	}
	
	/**
	 *	Constrói e exibe a tela de configuração de atividades presenciais
	 *
	 * 	@access public
	 */
	public function configurarAtividadesPresenciais() {
		$this->_template = new Template('html/presenciais/configuracao_presenciais.html');
		$atividades = $this->_data['atividades'];
		$soma_porcentagens = 0;
		
		$this->_template->COURSE_ID = $this->_data['curso_id'];
		$this->_template->NUMERO_ATIVIDADES = count($atividades);
		
		$this->_template->WWWROOT = LVS_WWWROOT2;
		
		if(!$this->_data['somenteLeitura']) {
			if($this->_data['exibirGravacao']) {
				$this->_template->block('COLUNA_ACOES');
				$this->_template->block('GRAVAR');
			}
		} else {
			$this->_template->READONLY = 'readonly="readonly"';
		}
		
		if (!empty($atividades)) {
			$i = 1;
		
			foreach ($atividades as $atividade) {
				$this->_template->CONTADOR = $i++;
		
				$this->_template->PRESENCIAL_ID = $atividade->id;
				$this->_template->block('PRESENCIAL_INPUT_HIDDEN');
		
				$this->_template->PRESENCIAL_NOME = $atividade->nome;
				$this->_template->PRESENCIAL_DESCRICAO = $atividade->descricao;
				$this->_template->PRESENCIAL_PORCENTAGEM = $atividade->porcentagem;
				$this->_template->PRESENCIAL_MAX_FALTAS = $atividade->max_faltas;
				$soma_porcentagens += $atividade->porcentagem;
		
				if(!$this->_data['somenteLeitura'] && $this->_data['exibirGravacao']) {
					$this->_template->block('ACOES');
				}
		
				$this->_template->block('PRESENCIAL');
			}
		
			$this->_template->SOMA_PORCENTAGENS = $soma_porcentagens;
		} else {
			$this->_template->block('NENHUMA_ATIVIDADE');
		}
		
		$this->_adapterView->js('/blocks/lvs/pages/scripts/mask.js');
		$this->_adapterView->js('/blocks/lvs/pages/scripts/form.js');
		$this->_adapterView->css('/blocks/lvs/pages/css/lvs.css');
		$this->_exibirPagina();
	}

	/**
	 *	Constrói e exibe a tela de edição de atividades presenciais
	 *
	 * 	@access public
	 */
	public function editarAtividadePresencial() {
		$this->_template = new Template("html/presenciais/edicao_presencial.html");
		$atividade = $this->_data['atividade'];
		
		$this->_template->ID_ATIVIDADE = $atividade->id;
		$this->_template->NOME_ATIVIDADE = $atividade->nome;
		$this->_template->DESCRICAO_ATIVIDADE = $atividade->descricao;
		
// 		$this->_adapterView->css('/blocks/lvs/pages/css/form.css');
		$this->_exibirPagina();
	}
	
	/**
	 *	Constrói e exibe a tela de listagem de atividades presenciais
	 *
	 * 	@access public
	 */
	public function exibirAtividadesPresenciais() {
		$this->_template = new Template('html/presenciais/atividades_presenciais.html');
		$this->_template->CURSO_ID = $this->_data['curso_id'];
		$this->_template->BASEURL = LVS_WWWROOT;
		
		$presenciais = $this->_data['presenciais'];
		
		if(count($presenciais) > 0) {
			foreach ($presenciais as $presencial) {
				$this->_template->PRESENCIAL_ID = $presencial->id;
				$this->_template->PRESENCIAL_NOME = $presencial->nome;
				$this->_template->block('PRESENCIAL');
			}
		}
		
		$this->_adapterView->css('/blocks/lvs/pages/css/lvs.css');
		$this->_exibirPagina();
	}
	
	/**
	 *	Constrói e exibe a tela de lançamento de notas dos estudantes em uma atividade presencial
	 *
	 * 	@access public
	 */
	public function lancarNotasPresenciais() {
		$this->_template = new Template('html/presenciais/notas_presenciais.html');
		$this->_template->MENSAGEM_INICIAL = 'Voc&ecirc; poder&aacute; digitar/alterar notas e/ou faltas at&eacute; o dia: ' . $this->_data['dataLimite'];
		$this->_template->MAX_FALTAS = ord($this->_data['presencial']->max_faltas);
		$i=1;
		
		$estudantes = $this->_data['avaliacoes'];
		
		foreach ($estudantes as $estudante) {
			$this->_template->CONTADOR = $i++;
			$this->_template->PRESENCIAL_ID  = $this->_data['presencial']->id;
			$this->_template->ESTUDANTE_ID   = $estudante->id;
			$this->_template->ESTUDANTE_NOME = $estudante->firstname . ' ' . $estudante->lastname;
			$this->_template->ESTUDANTE_FOTO = $this->_adapterView->fotoUsuario($estudante->id);

			if(isset($estudante->avaliacao->id)) {
				$this->_template->AVALIACAO_ID 			  = $estudante->avaliacao->id; 
				$this->_template->CHECKED  				  = ($estudante->avaliacao->faltou_prova == 1) ? "checked='checked'" : null;
				$this->_template->ESTUDANTE_NOTA 		  = $estudante->avaliacao->nota;
				$this->_template->ESTUDANTE_NUMERO_FALTAS = $estudante->avaliacao->nr_faltas;
			}
		
			if (!$this->_data['podeEditar']) {
				$this->_template->READONLY = "readonly='readonly'";
				//$this->_template->DISABLED = "disabled='disabled'";
			}
		
			$this->_template->block('ESTUDANTE');
		}
		
		if ($this->_data['podeEditar']) {
			$this->_template->block('BEGIN_FORM');
			$this->_template->block('GRAVAR');
			$this->_template->block('END_FORM');
		}
		
		$this->_adapterView->js('/blocks/lvs/pages/scripts/mask.js');
		$this->_adapterView->css('/blocks/lvs/pages/css/lvs.css');
		$this->_exibirPagina();
	}
	
	/**
	 *	Recupera o conteúdo criado no Template e o exibe em tela através do AdaptarView
	 *
	 *	@access private
	 */
	private function _exibirPagina() {
		$this->_adapterView->setContent($this->_template->parse());
		$this->_adapterView->exibirPagina();
	}
	
	/**
	 * 	Armazena os dados utilizados na construção da tela de exibição. É um 'magic method', não chamá-lo diretamente
	 */
	public function __set($name, $value) {
		$this->_data[$name] = $value;
	}
	
}
?>