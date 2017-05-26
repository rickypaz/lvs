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
class DistanciaView {

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
	 * Instancia DistanciaView 
	 */
	public function __construct() {
		$this->_adapterView = new Moodle2View();
	}
	
	/**
	 *	Constrói e exibe a tela de configuração de atividades a distancia
	 *
	 * 	@access public
	 */
	public function configurarAtividadesDistancia() {
		$this->_template = new Template('html/distancia/atividades_distancia.html');
		$this->_template->BASEURL = LVS_WWWROOT2;
		$this->_template->COURSE_ID = $this->_data['curso'];
		$this->_template->SESSKEY = $this->_data['sesskey'];
		$total_atividades = 0;
		
		$total_atividades += $this->_adicionarForuns();
// 		$total_atividades += $this->_adicionarTarefas();
		$total_atividades += $this->_adicionarChats();
		$total_atividades += $this->_adicionarWikis();
		$total_atividades += $this->_adicionarQuizzes();
		
		if (!$this->_data['somenteLeitura']) {
			$this->_template->block('COLUNA_EXIBIRLV');
			$this->_template->block('COLUNA_ACOES');
			
			if ($total_atividades > 0) {
				$this->_template->block('GRAVAR');
			}
		} else {
			$this->_template->READONLY = 'readonly="readonly"';
			$this->_template->DISABLED = 'disabled="disabled"';
		}
		
		if ($total_atividades == 0) {
			$this->_template->block('NENHUMA_ATIVIDADE');
		}
		
		$this->_adapterView->css('/blocks/lvs/pages/css/lvs.css');
		$this->_adapterView->js('/blocks/lvs/pages/scripts/mask.js');
		$this->_adapterView->js('/blocks/lvs/pages/scripts/form.js');
		$this->_exibirPagina();	
	}
	
	private function _adicionarForuns() {
		$foruns = $this->_data['foruns'];
	
		if (count($foruns) != 0) {
			$this->_template->CONJUNTO_ATIVIDADES = 'Fóruns';
			$this->_template->DISTANCIA_TIPO = 'forumlv';
	
			$i = 1;
			foreach ($foruns as $forum) {
				$this->_anularDistanciasEtapas();
				
				$this->_template->CONTADOR = $i++;
				$this->_template->DISTANCIA_ID = $forum->id;
				$this->_template->DISTANCIA_CM = $forum->cm;
				$this->_template->DISTANCIA_NOME = $forum->name;
				$this->_template->DISTANCIA_DESCRICAO = $forum->intro;
				$this->_template->DISTANCIA_PORCENTAGEM = $forum->porcentagem;
				$this->_template->CHECKED = ($forum->exibir == 1) ? "checked='checked'" : null;
	
				$etapa = "DISTANCIA_ETAPA{$forum->etapa}";
				$this->_template->$etapa = "selected='selected'";
	
				if(!$this->_data['somenteLeitura']) {
					$this->_template->block('EXIBIRLV');
					$this->_template->block('ACOES');
				}
				
				$this->_template->block('ATIVIDADE_LV');
			}
			$this->_template->block('ATIVIDADES_LVS');
		}
	
		return count($foruns);
	}
	
	private function _adicionarTarefas() {
		$tarefas = $this->_data['tarefas'];
	
		if (count($tarefas) != 0) {
			$this->_template->CONJUNTO_ATIVIDADES = 'Tarefas';
			$this->_template->DISTANCIA_TIPO = 'tarefalv';
	
			$i = 1;
			foreach ($tarefas as $tarefa) {
				$this->_anularDistanciasEtapas();
				
				$this->_template->CONTADOR = $i++;
				$this->_template->DISTANCIA_ID = $tarefa->id;
				$this->_template->DISTANCIA_CM = $tarefa->cm;
				$this->_template->DISTANCIA_NOME = $tarefa->name;
				$this->_template->DISTANCIA_DESCRICAO = $tarefa->intro;
				$this->_template->DISTANCIA_PORCENTAGEM = $tarefa->porcentagem;
				$this->_template->CHECKED = ($tarefa->exibir == 1) ? "checked='checked'" : null;
	
				$etapa = "DISTANCIA_ETAPA{$tarefa->etapa}";
				$this->_template->$etapa = "selected='selected'";
				
				if(!$this->_data['somenteLeitura']) {
					$this->_template->block('EXIBIRLV');
					$this->_template->block('ACOES');
				}
	
				$this->_template->block('ATIVIDADE_LV');
			}
			$this->_template->block('ATIVIDADES_LVS');
		}
	
		return count($tarefas);
	}
	
	private function _adicionarQuizzes() {
		$quizzes = $this->_data['quizzes'];
	
		if (count($quizzes) != 0) {
			$this->_template->CONJUNTO_ATIVIDADES = 'Quizzes';
			$this->_template->DISTANCIA_TIPO = 'quizlv';
	
			$i = 1;
			foreach ($quizzes as $quiz) {
				$this->_anularDistanciasEtapas();
	
				$this->_template->CONTADOR = $i++;
				$this->_template->DISTANCIA_ID = $quiz->id;
				$this->_template->DISTANCIA_CM = $quiz->cm;
				$this->_template->DISTANCIA_NOME = $quiz->nome;
				$this->_template->DISTANCIA_DESCRICAO = (!empty($quiz->intro)) ? $quiz->intro : '-';
				$this->_template->DISTANCIA_PORCENTAGEM = $quiz->porcentagem;
				$this->_template->CHECKED = ($quiz->exibir == 1) ? "checked='checked'" : null;
	
				$etapa = "DISTANCIA_ETAPA{$quiz->etapa}";
				$this->_template->$etapa = "selected='selected'";
				
				if(!$this->_data['somenteLeitura']) {
					$this->_template->block('EXIBIRLV');
					$this->_template->block('ACOES');
				}
	
				$this->_template->block('ATIVIDADE_LV');
			}
			$this->_template->block('ATIVIDADES_LVS');
		}
	
		return count($quizzes);
	}
	
	private function _adicionarWikis() {
		$wikis = $this->_data['wikis'];
		
		if (count($wikis) != 0) {
			$this->_template->CONJUNTO_ATIVIDADES = 'Wikis';
			$this->_template->DISTANCIA_TIPO = 'wikilv';
				
			$i = 1;
			foreach ($wikis as $wiki) {
				$this->_anularDistanciasEtapas();
				
				$this->_template->CONTADOR = $i++;
				$this->_template->DISTANCIA_ID = $wiki->id;
				$this->_template->DISTANCIA_CM = $wiki->cm;
				$this->_template->DISTANCIA_NOME = $wiki->name;
				$this->_template->DISTANCIA_DESCRICAO = $wiki->intro;
				$this->_template->DISTANCIA_PORCENTAGEM = $wiki->porcentagem;
				$this->_template->CHECKED = ($wiki->exibir == 1) ? "checked='checked'" : null;
		
				$etapa = "DISTANCIA_ETAPA{$wiki->etapa}";
				$this->_template->$etapa = "selected='selected'";
				
				if(!$this->_data['somenteLeitura']) {
					$this->_template->block('EXIBIRLV');
					$this->_template->block('ACOES');
				}
		
				$this->_template->block('ATIVIDADE_LV');
			}
			$this->_template->block('ATIVIDADES_LVS');
		}
		
		return count($wikis); 
	}
	
	private function _adicionarChats() {
		$chats = $this->_data['chats'];
	
		if (count($chats) != 0) {
			$this->_template->CONJUNTO_ATIVIDADES = 'Chats';
			$this->_template->DISTANCIA_TIPO = 'chatlv';
	
			$i = 1;
			foreach ($chats as $chat) {
				$this->_anularDistanciasEtapas();
	
				$this->_template->CONTADOR = $i++;
				$this->_template->DISTANCIA_ID = $chat->id;
				$this->_template->DISTANCIA_CM = $chat->cm;
				$this->_template->DISTANCIA_NOME = $chat->name;
				$this->_template->DISTANCIA_DESCRICAO = $chat->intro;
				$this->_template->DISTANCIA_PORCENTAGEM = $chat->porcentagem;
				$this->_template->CHECKED = ($chat->exibir == 1) ? "checked='checked'" : null;
	
				$etapa = "DISTANCIA_ETAPA{$chat->etapa}";
				$this->_template->$etapa = "selected='selected'";
	
				if(!$this->_data['somenteLeitura']) {
					$this->_template->block('EXIBIRLV');
					$this->_template->block('ACOES');
				}
	
				$this->_template->block('ATIVIDADE_LV');
			}
			$this->_template->block('ATIVIDADES_LVS');
		}
	
		return count($chats);
	}
	
	private function _anularDistanciasEtapas() {
		$this->_template->DISTANCIA_ETAPA1 = $this->_template->DISTANCIA_ETAPA2 = $this->_template->DISTANCIA_ETAPA3 = 
		$this->_template->DISTANCIA_ETAPA4 = $this->_template->DISTANCIA_ETAPA5 = $this->_template->DISTANCIA_ETAPA6 = 
		$this->_template->DISTANCIA_ETAPA7 = $this->_template->DISTANCIA_ETAPA8 = $this->_template->DISTANCIA_ETAPA9 = 
		$this->_template->DISTANCIA_ETAPA10 = '';
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