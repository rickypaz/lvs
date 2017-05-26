<?php
namespace uab\ifce\lvs\controllers;

use uab\ifce\lvs\util\Convert;
use uab\ifce\lvs\business\CursoLv;
use uab\ifce\lvs\controllers\AdapterController;
use uab\ifce\lvs\moodle2\business\ForunsLv;
use uab\ifce\lvs\moodle2\business\TarefasLv;
use uab\ifce\lvs\moodle2\business\ChatsLv;
use uab\ifce\lvs\moodle2\business\Quizzes;
use uab\ifce\lvs\moodle2\business\WikisLv;
use uab\ifce\lvs\moodle2\view\DistanciaView;


/**
* 	Controller responsável por receber e tratar as requisições referentes às atividades a distancia
*
*	@category LVs
*	@package uab\ifce\lvs\controllers
* 	@author Ricky Persivo (rickypaz@gmail.com)
* 	@version SVN $Id
*/
class DistanciaController {
	
	/**
	 * 	Controller responsável por ações genéricas a todos os controllers 
	 * 	@var AdapterController
	 */
	private $_adapterController;
	
	/**
	 * 	Responsável por recuperar dados ou persistir alterações nas atividades a distância
	 * 	@var CursoLv
	 */
	private $_cursolvModel;
	
	/**
	 * 	Dados recebidos via GET ou POST
	 * 	@var mixed
	 */
	private $_data;
	
	/**
	 * 	Exibe a página html referente às requisições
	 * 	@var DistanciaView
	 */
	private $_distanciaView;
	
	/**
	 * 	Instancia um DistanciaController
	 * 
	 * 	@param CursoLv $cursolv
	 */
	public function __construct(CursoLv $cursolv) {
		$this->_cursolvModel = $cursolv;
		$this->_distanciaView = new DistanciaView(); 
	}
	
	public function configurarAtividadesDistancia() {
		if (! $this->_cursolvModel->getConfiguracao() ) {
			$this->_adapterController->redirect( LVS_WWWROOT . '/pages/config_cursolv.php?curso=' . $this->_data['curso_ava']->id, "Preencha as informações do curso!", 0.5);
		}
		
		$curso_id = $this->_cursolvModel->getConfiguracao()->id_curso;

		$gerenciadorForunsLv  = new ForunsLv($this->_cursolvModel);
// 		$gerenciadorTarefasLv = new TarefasLv($this->_cursolvModel);
		$gerenciadorChatsLv   = new ChatsLv($this->_cursolvModel);
		$gerenciadorWikisLv   = new WikisLv($this->_cursolvModel);
		$gerenciadorQuizzes   = new Quizzes($this->_cursolvModel);
		
		if(!empty($this->_data['atividade'])) {
			
			if (isset($this->_data['atividade']['forumlv'])) {
				$foruns = (array) Convert::array_to_object($this->_data['atividade']['forumlv']);
				$gerenciadorForunsLv->salvarConfiguracao($foruns);
			}
			
// 			if (isset($this->_data['atividade']['tarefalv'])) {
// 				$tarefas = (array) Convert::array_to_object($this->_data['atividade']['tarefalv']);
// 				$gerenciadorTarefasLv->salvarConfiguracao($tarefas);
// 			}
			
			if (isset($this->_data['atividade']['chatlv'])) {
				$chats = (array) Convert::array_to_object($this->_data['atividade']['chatlv']);
				$gerenciadorChatsLv->salvarConfiguracao($chats);
			}
			
			if (isset($this->_data['atividade']['wikilv'])) {
				$wikis = (array) Convert::array_to_object($this->_data['atividade']['wikilv']);
				$gerenciadorWikisLv->salvarConfiguracao($wikis);
			}
			
			if (isset($this->_data['atividade']['quizlv'])) {
				$quizzes = (array) Convert::array_to_object($this->_data['atividade']['quizlv']);
				$gerenciadorQuizzes->salvarConfiguracao($quizzes);
			}
			
			$this->_adapterController->redirect( LVS_WWWROOT . '/pages/atividades_distancia.php?curso=' . $curso_id, 'Alterações Realizadas com Sucesso', 0.7 );
		}
		
		
		$foruns = $gerenciadorForunsLv->recuperarAtividades();
// 		$tarefas = $gerenciadorTarefasLv->recuperarAtividades();
		$chats = $gerenciadorChatsLv->recuperarAtividades();
		$wikis = $gerenciadorWikisLv->recuperarAtividades();
		$quizzes = $gerenciadorQuizzes->recuperarAtividades(); 
		
		$this->_cursolvModel->calcularPorcentagemAtividades();
		
		$this->_distanciaView->curso   = $curso_id;
		$this->_distanciaView->foruns  = $gerenciadorForunsLv->recuperarConfiguracao($foruns);
// 		$this->_distanciaView->tarefas = $gerenciadorTarefasLv->recuperarConfiguracao($tarefas);
		$this->_distanciaView->chats   = $gerenciadorChatsLv->recuperarConfiguracao($chats);
		$this->_distanciaView->wikis   = $gerenciadorWikisLv->recuperarConfiguracao($wikis);
		$this->_distanciaView->quizzes = $gerenciadorQuizzes->recuperarConfiguracao($quizzes);
		$this->_distanciaView->sesskey = $this->_adapterController->sesskey();
		$this->_distanciaView->somenteLeitura = $this->_data['somente_leitura'];
		
		$this->_distanciaView->configurarAtividadesDistancia();
	}
	
	/**
	 * 	Altera os dados utilzados pelo controller no tratamento de requisições
	 * 	@param mixed $data
	 */
	public function setData($data) {
		$this->_data = $data;
	}
	
	public function setAdapterController(AdapterController $adapterController) {
		$this->_adapterController = $adapterController;
	}
	
}
?>