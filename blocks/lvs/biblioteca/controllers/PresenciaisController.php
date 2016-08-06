<?php
namespace uab\ifce\lvs\controllers;

use uab\ifce\lvs\business\CursoLv;
use uab\ifce\lvs\business\AtividadesPresenciais;
use uab\ifce\lvs\controllers\AdapterController;
use uab\ifce\lvs\moodle2\view\PresenciaisView;
use uab\ifce\lvs\util\Convert;

/**
* 	Controller responsável por receber e tratar as requisições referentes às atividades presenciais
* 
* 	@category LVs
*	@package uab\ifce\lvs\controllers
* 	@author Ricky Persivo (rickypaz@gmail.com)
*  	@version SVN $Id
*/
class PresenciaisController {
	
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
	 * 	Responsável por recuperar dados ou persistir alterações nas atividades presenciais
	 * 	@var AtividadesPresenciais
	 */
	private $_presenciaisModel;
	
	/**
	 * 	Exibe a página html referente às requisições
	 * 	@var PresenciaisView
	 */
	private $_presenciaisView;
	
	/**
	 * 	Instancia um PresenciaisController
	 * 
	 * 	@param CursoLv $cursolv
	 */
	public function __construct(CursoLv $cursolv) {
		$this->_presenciaisModel = new AtividadesPresenciais($cursolv);
		$this->_presenciaisView = new PresenciaisView();
	}
	
	/**
	 *	Exibe a página de configuração das atividades presenciais
	 *
	 * 	@access public
	 */
	public function configurarAtividadesPresenciais() {
		$curso_id = $this->_presenciaisModel->getCursoLv()->getConfiguracao()->id_curso;
		$presenciais = $this->_presenciaisModel->recuperarAtividades();
		
		if(!empty($this->_data['atividades'])) {
			$this->_data['atividades'] = (array) Convert::array_to_object($this->_data['atividades']);
			$this->_presenciaisModel->salvarAtividades($this->_data['atividades']);
			$this->_adapterController->redirect( LVS_WWWROOT . "/pages/configuracao_presenciais.php?curso=$curso_id", 'Alterações Realizadas com Sucesso', 0.7 );
		}
		
		$this->_presenciaisView->atividades = $presenciais;
		$this->_presenciaisView->curso_id = $this->_presenciaisModel->getCursoLv()->getConfiguracao()->id_curso;
		$this->_presenciaisView->exibirGravacao = true;
		$this->_presenciaisView->somenteLeitura = $this->_data['somente_leitura'];
		
		$this->_presenciaisView->configurarAtividadesPresenciais();
	}
	
	/**
	 * 	Edita os dados de uma atividade presencial
	 * 
	 * 	@param int $presencial id da atividade presencial
	 */
	public function editarAtividadePresencial($presencial) {
		$curso_id = $this->_presenciaisModel->getCursoLv()->getConfiguracao()->id_curso;
		$atividade = $this->_presenciaisModel->recuperarAtividade($presencial);
		
		if(!empty($this->_data['atividade'])) {
			$this->_data['atividade'] = (object) $this->_data['atividade'];
			$this->_presenciaisModel->salvarAtividades($this->_data['atividade']);
			$this->_adapterController->redirect( LVS_WWWROOT . "/pages/configuracao_presenciais.php?curso=$curso_id", 'Alterações Realizadas com Sucesso', 0.7 );
		}
		
		$this->_presenciaisView->atividade = $atividade;
		
		$this->_presenciaisView->editarAtividadePresencial();
	}
	
	/**
	 * 	Exibe todas as atividades presenciais de um cursolv
	 * 
	 * 	@access public
	 *	@todo corrigir o business object de CursoLV e centralizar os lvs em torno de seu id 
	 */
	public function exibirAtividadesPresenciais() {
		$cursolv = $this->_presenciaisModel->getCursoLv();
		
		if( !$cursolv->getConfiguracao() ) {
			$this->_adapterController->redirect( LVS_WWWROOT . '/pages/config_cursolv.php?curso=' . $this->_data['curso_ava']->id, "Preencha as informações do curso!", 0.5 );
		}
		
		$curso_id = $cursolv->getConfiguracao()->id_curso;
		$atividades = $this->_presenciaisModel->recuperarAtividades();
		
		if ( count($atividades) == 0 )
			$this->_adapterController->redirect( LVS_WWWROOT . "/pages/configuracao_presenciais.php?curso=$curso_id" );
		
		$this->_presenciaisView->curso_id = $curso_id; 
		$this->_presenciaisView->presenciais = $atividades; 
		
		$this->_presenciaisView->exibirAtividadesPresenciais();
	}
	
	/**
	 *	Exibe e permite o lançamento das notas e faltas de todos os estudantes em uma atividade presencial
	 *
	 *  @param $presencial id da atividade presencial
	 *  @access public
	 *  @throws \Exception lança exceção caso não exista uma atividade presencial associada ao id fornecido
	 */
	public function lancarNotasPresenciais($presencial) {
		$curso_id = $this->_presenciaisModel->getCursoLv()->getConfiguracao()->id_curso;
		$configuracao_cursolv = $this->_presenciaisModel->getCursoLv()->getConfiguracao();
		$avaliacoes = $this->_data['avaliacoes'];
		
		if(!empty($avaliacoes)) {
			$avaliacoes = (array) Convert::array_to_object($avaliacoes);
			$this->_presenciaisModel->salvarAvaliacoes($avaliacoes);
			$this->_adapterController->redirect( LVS_WWWROOT . '/pages/notas_presenciais.php?curso=' . $curso_id . '&id=' . $presencial, 'Alterações Realizadas com Sucesso', 0.8 );				
		} else {
			$presencial = $this->_presenciaisModel->recuperarAtividade($presencial);
			$avaliacoes = $this->_presenciaisModel->recuperarAvaliacoesNaAtividade( $presencial->id );
		}
		
		$dataatual = mktime(0, 0, 0, date("m"), date("d"), date("Y"));
		$this->_presenciaisView->curso_id 	= $curso_id;
		$this->_presenciaisView->avaliacoes = $avaliacoes;
		$this->_presenciaisView->dataLimite = date("d/m/Y", $configuracao_cursolv->data_limite);
		$this->_presenciaisView->podeEditar = $configuracao_cursolv->data_limite >= $dataatual;
		$this->_presenciaisView->presencial = $presencial;
			
		$this->_presenciaisView->lancarNotasPresenciais();
	}
	
	/**
	 * 	Remove uma atividade presencial
	 * 
	 * 	@param int $presencial id da atividade presencial
	 */
	public function removerAtividadePresencial($presencial) {
		$curso_id = $this->_presenciaisModel->getCursoLv()->getConfiguracao()->id_curso;
		$this->_presenciaisModel->removerAtividade($presencial);
		
		$this->_adapterController->redirect( 
			LVS_WWWROOT . '/pages/configuracao_presenciais.php?curso=' . $curso_id, 
			'Alterações Realizadas com Sucesso', 0
		);
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