<?php
namespace uab\ifce\lvs\controllers;

use uab\ifce\lvs\business\AtividadesPresenciais;
use uab\ifce\lvs\business\CursoLv;
use uab\ifce\lvs\controllers\AdapterController;
use uab\ifce\lvs\moodle2\business\Quizzes;
use uab\ifce\lvs\moodle2\view\QuizzesView;

/**
*  	Controller responsável por receber e tratar as requisições referentes aos quizzes
*
*	@category LVs
*	@package uab\ifce\lvs\controllers
* 	@author Ricky Persivo (rickypaz@gmail.com)
* 	@version 1.0
*/
class QuizzesController {
	
	/**
	 * 	Controller responsável por ações genéricas a todos os controllers 
	 * 	@var AdapterController
	 */
	private $_adapterController;
	
	/**
	 * 	Dados recebidos via GET ou POST
	 * 	@var mixed
	 */
	private $_data;
	
	/**
	 * 	Responsável por recuperar e armazenar quizzes importados como atividades presenciais
	 * 	@var AtividadesPresenciais
	 */
	private $_gerenciadorPresenciais;
	
	/**
	 * 	Responsável por recuperar dados ou persistir alterações nos quizzes
	 * 	@var Quizzes
	 */
	private $_quizzesModel;
	
	/**
	 * 	Exibe a página html referente às requisições 
	 * 	@var QuizzesView
	 */
	private $_quizzesView;
	
	/**
	 * 	Instancia um QuizzesController
	 * 
	 * 	@param CursoLv $cursolv
	 */
	public function __construct(CursoLv $cursolv) {
		$this->_quizzesModel = new Quizzes($cursolv);
		$this->_quizzesView = new QuizzesView();
		$this->_gerenciadorPresenciais = new AtividadesPresenciais($cursolv);
	}
	
	/**
	 *	Exibe a página de importação de quizzes presenciais e a distância
	 *
	 * 	@access public
	 */
	public function importarQuizzes() {
		$cursolv = $this->_quizzesModel->getCursoLv();
		
		if( !$cursolv->getConfiguracao() ) {
			$this->_adapterController->redirect( LVS_WWWROOT . '/pages/config_cursolv.php?curso=' . $this->_data['curso_ava']->id, "Preencha as informações do curso!", 0.5 );
		}
		
		$curso_id = $cursolv->getConfiguracao()->id_curso;
		 
		if(!empty($this->_data['atividades'])) {
			$this->_removerQuizzesImportados();
			$this->_importarQuizzesDistancia();
			$this->_importarQuizzesPresenciais();
			$this->_atualizarAtividadesPresenciais();
			
			$this->_adapterController->redirect( LVS_WWWROOT . '/pages/importar_quizzes.php?curso=' . $curso_id, 'Alterações Realizadas com Sucesso', 0.7 );
		} 
		
		$quizzesDisponiveis = $this->_quizzesModel->recuperarQuizzesDisponiveis();
		$quizzesDistancia = $this->_quizzesModel->recuperarQuizzesDistanciaImportados();
		
		$presenciais = $this->_gerenciadorPresenciais->recuperarAtividades();
		$quizzesPresenciais = $this->_quizzesModel->recuperarQuizzesPresenciaisImportados(); 
		
		foreach ($quizzesPresenciais as $quiz) {
			$quiz->presencial = (isset($presenciais[$quiz->id_atividade])) ? $presenciais[$quiz->id_atividade] : null;
			unset($presenciais[$quiz->id_atividade]);
		}
		
		$this->_quizzesView->curso_id = $curso_id;
		$this->_quizzesView->quizzesDisponiveis = $quizzesDisponiveis;
		$this->_quizzesView->quizzesPresenciais = $quizzesPresenciais;
		$this->_quizzesView->quizzesDistancia = $quizzesDistancia;
		$this->_quizzesView->presenciais = $presenciais;
		
		$this->_quizzesView->importarQuizzes();
	}
	
	/**
	 * 	Recebe as requisições de importação de quizzes a distância e as repassa ao gerenciador responsável
	 * 
	 *  @access private
	 */
	private function _importarQuizzesDistancia( ) {
		$quizzes = $this->_data['atividades']['quiz'];
		$importacao = array();

		foreach ($quizzes as $quiz) {
			if (isset($quiz['acao']) && $quiz['distancia'] == 1) {
				$importacao[] = $quiz;
			}
		}
		
		if (!empty($importacao)) {
			$this->_quizzesModel->importarQuizzes($importacao);
		}
	}
	
	/**
	 * 	Recebe as requisições de importação de quizzes presenciais e as repassa ao gerenciador responsável
	 *
	 *  @access private
	 */
	private function _importarQuizzesPresenciais() 
	{
		$quizzes = $this->_data['atividades']['quiz'];
		$importacao = array();

		foreach ($quizzes as $quiz) 
		{
			if (isset($quiz['acao']) && $quiz['distancia'] == 0) 
			{
				$importacao[] = $quiz;
			}
		}
		
		if (!empty($importacao)) 
		{
			$this->_quizzesModel->importarQuizzes($importacao);
		}
	}
	
	/**
	 *	Verifica os quizzes que foram retirados da importação e os repassa para o gerenciador responsável removê-los
	 *
	 *  @access private
	 */
	private function _removerQuizzesImportados( ) {
		$quizzes = $this->_data['atividades']['quiz'];
		$remocao = array();
		
		foreach ($quizzes as $index => $quiz) {
			if ($quiz['id'] && !isset($quiz['acao'])) {
				$remocao[] = $quiz;
			}
		}

		if (!empty($remocao)) {
			$this->_quizzesModel->removerQuizzes($remocao);
		}
	}

	/**
	 *	Atualiza as porcentagens das atividades presenciais existentes
	 *
	 *  @access private
	 */
	private function _atualizarAtividadesPresenciais() {
		$presenciais = isset($this->_data['atividades']['presencial']) ? $this->_data['atividades']['presencial'] : array();

		if (count($presenciais) > 0) 
		{
			$this->_gerenciadorPresenciais->salvarAtividades($presenciais);
		}
	}
	
	/**
	 * 	Altera os dados utilzados pelo controller no tratamento de requisições
	 * 	@param mixed $data
	 */
	public function setData($data) {
		$this->_data = $data;
	}
	
	/**
	 * 	Altera o controller base
	 *  
	 * 	@param AdapterController $adapterController
	 */
	public function setAdapterController(AdapterController $adapterController) {
		$this->_adapterController = $adapterController;
	}
}
?>