<?php 
namespace uab\ifce\lvs\controllers;

use uab\ifce\lvs\Template;
use uab\ifce\lvs\business\CursoLv;
use uab\ifce\lvs\moodle2\view\RelatorioView;

/**
 * 	Controller responsável por receber e tratar as requisições referentes à geração de relatórios 
 *
 * 	@category LVs
 * 	@package uab\ifce\lvs\controllers
 * 	@author Allyson Bonetti (rickypaz@gmail.com)
 * 	@author Ricky Persivo (rickypaz@gmail.com)
 * 	@version SVN $Id
 */
class RelatorioController {

	/**
	 * 	Controller responsável por ações genéricas a todos os controllers 
	 * 	@var AdapterController
	 */
	private $_adapterController;
	
	/**
	 *	Fonte de dados do relatório
	 *	@var CursoLv
	 * 	@access private
	 */
	private $_cursolv;
	
	/**
	 * 	Dados enviados pelo pela requisição
	 * 	@var array
	 */
	private $_data;
	
	/**
	 * 	Diretório temporário onde são armazenados os relatórios gerados em pdf
	 * 	@var string
	 */
	private $_tempdir;
	
	/**
	 * 	@var Template
	 * 	@see Template
	 */
	private $_template;
	
	/**
	 * 	View associada ao controller
	 * 	@var RelatorioView
	 */
	private $_relatorioView;

	/**
	 * 	Instancia RelatorioController
	 * 	@param CursoLv $cursolv
	 */
	public function __construct( $cursolv ) {
		$this->_cursolv = $cursolv;
		$this->_tempdir = sys_get_temp_dir();
		$this->_relatorioView = new RelatorioView();
	}
	
	public function desempenhoDistancia( $modulo, $estudante ) {
		$atividades = $this->_cursolv->getGerenciador($modulo)->recuperarDesempenhoPorAtividade($estudante->id);
		
		$this->_relatorioView->modulo = $modulo;
		$this->_relatorioView->estudante = $estudante;
		$this->_relatorioView->atividades = $atividades;
		
		$this->_relatorioView->desempenhoDistancia();
	}

	/**
	 *	Retorna uma div html contendo o desempenho dos estudantes no curso
	 *
	 * 	@param array:\stdClass $estudantes lista de estudantes que terão suas notas exibidas no relatório. Se não informado, serão calculados os desempenhos de todos os estudantes inscritos no curso
	 * 	@return string html
	 * 	@access public
	 * 	@todo adicionar camada de persistência
	 */
	public function desempenhoParticipantes( $estudantes ) {
		if( !$this->_cursolv->getConfiguracao() ) {
			$this->_adapterController->redirect( LVS_WWWROOT . '/pages/config_cursolv.php?curso=' . $this->_data['curso_ava']->id, "Preencha as informações do curso!", 0.5 );
		}
		
		$curso = $this->_cursolv->getConfiguracao()->id_curso;
		$this->_cursolv->calcularPorcentagemAtividades();
		
		$estudantes = (!empty($estudantes)) ? $estudantes : $this->_cursolv->getEstudantes();
		
		foreach ($estudantes as &$estudante) {
			$estudante->desempenho = $this->_cursolv->getDesempenho($estudante->id);
		}
		
		$this->_relatorioView->modulos 				 = array_keys( $this->_cursolv->getGerenciadores() ); 
		$this->_relatorioView->curso 				 = $this->_data['curso_ava'];
		$this->_relatorioView->estudantes 			 = $estudantes;
		$this->_relatorioView->betaMedio 			 = $this->_cursolv->betaMedio();
		$this->_relatorioView->mediaCurso 			 = $this->_cursolv->getConfiguracao()->media_curso;
		$this->_relatorioView->mediaAprovacaoCurso 	 = $this->_cursolv->getConfiguracao()->media_aprov_af;
		$this->_relatorioView->mediaAFCurso 		 = $this->_cursolv->getConfiguracao()->media_af;
		$this->_relatorioView->percentualFaltasCurso = $this->_cursolv->getConfiguracao()->percentual_faltas;
		$this->_relatorioView->somenteLeitura 		 = $this->_data['somenteLeitura'];
		
		$this->_relatorioView->desempenhoParticipantes();
	}
	
	/**
	 * 	Gera um arquivo pdf contendo o desempenho dos estudantes no curso 
	 * 
	 * 	@param mixed $estudantes id de um estudante ou uma lista de ids. Se não informado, serão calculados os desempenhos de todos os estudantes inscritos no curso
	 * 	@access public
	 * 	@todo aceitar o param estudantes como int, array ou nulo
	 */
	public function desempenhoParticipantesPdf( $estudantes = null ) {
		$versao_pdf = $this->_versaoPdf($estudantes);

		$arquivo = uniqid('rel') .  '.html';
		$caminho = LVS_DIRROOT . '/biblioteca/dompdf/template/' . $arquivo;	
		file_put_contents($caminho, $versao_pdf);

		redirect(LVS_WWWROOT . '/biblioteca/dompdf/dompdf.php?base_path=template%2F&input_file=' . rawurlencode($arquivo));
		exit;
	}
	
	
	/**
	 * 	Exibe as notas presenciais de um estudante num curso lv
	 * 	
	 * 	@param stdClass $estudante
	 * 	@access public 
	 */
	public function desempenhoPresencial( $estudante ) {
		$this->_relatorioView->atividades = $this->_cursolv->getGerenciadorPresencial()->recuperarAvaliacoes($estudante->id);
		$this->_relatorioView->desempenhoPresencial();
	}
	
	/**
	 * 	Exibe o total de faltas de estudante em cada módulo a distância e nas atividades presenciais
	 * 
	 * 	@param int $estudante id do estudante
	 * 	@access public
	 */
	public function faltasEstudante( $estudante ) {
		if( !$this->_cursolv->getConfiguracao() ) {
			$this->_adapterController->redirect( LVS_WWWROOT . '/pages/config_cursolv.php?curso=' . $this->_data['curso_ava']->id, "Preencha as informações do curso!", 0.5 );
		}
		
		$faltas = $this->_cursolv->faltas($estudante);

		$this->_relatorioView->estudante = $estudante;
		$this->_relatorioView->faltas = $faltas;
		
		$this->_relatorioView->faltasEstudante();
	}
	
	/**
	 * 	Retorna o html que será utilizado para a construção do relatório em PDF
	 * 	
	 * 	@param mixed $estudantes id de um estudante ou uma lista de ids. Se não informado, serão calculados os desempenhos de todos os estudantes inscritos no curso
	 * 	@return string html
	 * 	@access public
	 * 	@todo aceitar o param estudantes como int, array ou nulo
	 */
	private function _versaoPdf($estudantes = null) {
		$this->_template = new Template('html/relatorios/notaslv_pdf.html');
		$isAF = false;
		
		$curso 		= $this->_cursolv->getConfiguracao()->id_curso;
		$estudantes = ($estudantes !== null) ? $estudantes : $this->_cursolv->getEstudantes();
		$modulos 	= $this->_cursolv->getGerenciadores();
		
		$this->_template->CURSO_NOME 	  = $this->_data['curso_ava']->nome;
		$this->_template->CURSO_LEGENDA   = $this->_data['curso_ava']->legenda;
		$this->_template->CURSO_DESCRICAO = $this->_data['curso_ava']->descricao;
		$this->_template->BASE_URL 		  = LVS_DIRROOT;
		
		$i = 0;
		foreach ($estudantes as $estudante) {
			$desempenho = $this->_cursolv->getDesempenho($estudante->id);
		
			$this->_template->ESTUDANTE_NOME = $estudante->firstname . ' ' . $estudante->lastname;
		    $this->_template->BETA_MEDIO = $this->_cursolv->betaMedio();
			
			if ($i % 2 == 0) {
				$this->_template->CLASS_ALUNO = "list_row";
			} else {
				$this->_template->CLASS_ALUNO = "list_row_color";
			}
		
			foreach ($modulos as $nomeModulo => $modulo) {
				$str_nota = "nota_$nomeModulo";
				$str_ausencias = "ausencias_$nomeModulo";
					
				$this->_template->NOTA_ATIVIDADE 	 = $desempenho->$str_nota;
				$this->_template->AUSENCIAS_ATIVIDADE = $desempenho->$str_ausencias;
		
				$this->_template->block('ATIVIDADE_DISTANCIA');
			}
		
			$this->_template->NOTA_DISTANCIA 	 = $desempenho->nd;
			$this->_template->NOTA_ATIVIDADE 	 = $desempenho->np;
			$this->_template->AUSENCIAS_ATIVIDADE = $desempenho->aap;
		
			$this->_template->block('ATIVIDADE_PRESENCIAL');
		
			$this->_template->MEDIA 			= $desempenho->media_parcial;
			$this->_template->TOTAL_FALTAS 	= $desempenho->ntf;
			$this->_template->FATOR_BETA 	= $desempenho->beta;
		
			if ($desempenho->situacao == 'C') {
				$this->_template->IMAGEM_LV_ICONE = 'cursando.gif';
			} else {
				$this->_template->IMAGEM_LV_ICONE = $desempenho->lv_icone;
			}
		
			$media_final = ($desempenho->media_final == NULL && $desempenho->af == NULL)?
			$desempenho->media_parcial : $desempenho->media_final;
		
			$this->_template->MEDIA_FINAL = round($media_final, 1);
			$this->_template->NOTA_AF = $desempenho->situacao;
		
			// se o estudante estiver de AF ou a nota final dele já tenha sido dada, em ambos os casos, é permitido fornecer a nota final
			if ( $desempenho->situacao == 'AF' || $desempenho->af !== NULL ) {
				$isAF = true;
				$this->_template->NOTA_AF 	= ($desempenho->af == NULL)? 0 : $desempenho->af;
				$this->_template->block('AF');
			} else {
				$this->_template->block('AM');
			}
		
			$this->_template->SITUACAO = $desempenho->situacao;
			$this->_template->block('ALUNO');
		
			$i++;
		}
		
		$this->_template->MEDIA_CURSO 				= $this->_cursolv->getConfiguracao()->media_curso;
		$this->_template->MEDIA_APROVACAO_AF_CURSO 	= $this->_cursolv->getConfiguracao()->media_aprov_af;
		$this->_template->MEDIA_AF_CURSO 			= $this->_cursolv->getConfiguracao()->media_af;
		$this->_template->PERCENTUAL_FALTAS_CURSO 	= $this->_cursolv->getConfiguracao()->percentual_faltas;
		
		return $this->_template->parse();
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