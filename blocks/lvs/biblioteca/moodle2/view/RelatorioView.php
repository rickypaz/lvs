<?php
namespace uab\ifce\lvs\moodle2\view;

use uab\ifce\lvs\Lvometro;
use uab\ifce\lvs\Template;
use uab\ifce\lvs\moodle2\business\Forumlv;

/**
*  	Constrói e exibe os relatórios do curso lv. Os tipos de relatório suportados são em html e pdf.
*
*	@category LVs
*	@package uab\ifce\lvs\moodle2\view
* 	@author Ricky Persivo (rickypaz@gmail.com)
*	@version SVN $Id
*/
class RelatorioView {
	
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
	 * 	Mecanismo de template que separa o HTML do código PHP 
	 * 	@var Template
	 * 	@see Template
	 */
	private $_template;
	
	/**
	 *	Instancia RelatorioView 
	 */
	public function __construct() {
		$this->_adapterView = new Moodle2View();
	}
	
	/**
	 *	Exibe desempenho de um estudante em cada atividade de um módulo (forumlv, quizlv...)
	 *
	 *	@access public
	 */
	public function desempenhoDistancia( ) {
		$this->_template = new Template('html/relatorios/relatorio_distancia.html');
		$modulo = $this->_data['modulo'];
		$atividades = $this->_data['atividades'];
		$estudante = $this->_data['estudante'];
		$quantidade_atividades = 0;
		
		//codigo para saber qual o id do forum!
		/*global $USER;
		if($USER->id == 2787){ 
			print_object($atividades);
			exit;
		}*/

		
		foreach ($atividades as $atividade) {
			if ($atividade->exibir == 1) {
				$avaliacao = $atividade->avaliacaolv;
				$nota = 0;
		
				$this->_template->ALUNO_NOME = $estudante->firstname . " " . $estudante->lastname;
				$this->_template->ATIVIDADE_NOME = $atividade->name;
		
				$this->_template->CARINHAS_AZUIS = '-';
				$this->_template->CARINHAS_VERDES = '-';
				$this->_template->CARINHAS_AMARELAS = '-';
				$this->_template->CARINHAS_LARANJAS = '-';
				$this->_template->CARINHAS_VERMELHAS = '-';
				$this->_template->CARINHAS_NEUTRAS = '-';
				$this->_template->ATIVIDADE_NOTA = '-';
				$this->_template->ATIVIDADE_BETA = '-';
		
				if(!empty($avaliacao)) {
					$this->_template->CARINHAS_AZUIS = $avaliacao->numero_carinhas_azul;
					$this->_template->CARINHAS_VERDES = $avaliacao->numero_carinhas_verde;
					$this->_template->CARINHAS_AMARELAS = $avaliacao->numero_carinhas_amarela;
					$this->_template->CARINHAS_LARANJAS = $avaliacao->numero_carinhas_laranja;
					$this->_template->CARINHAS_VERMELHAS = $avaliacao->numero_carinhas_vermelha;
					$this->_template->CARINHAS_NEUTRAS = $avaliacao->numero_carinhas_preta;
		
					$this->_template->ATIVIDADE_NOTA = $avaliacao->modulo_vetor;
					$this->_template->ATIVIDADE_BETA = $avaliacao->beta;
					$nota = $avaliacao->modulo_vetor;
				}
		
				$this->_template->LVOMETRO_SRC = Lvometro::retornaLvometro($nota);
				
				if ($modulo == 'wikilv') {
					$this->_template->CONTRIBUICOES_NOTA = $avaliacao->modulo_vetor - $avaliacao->modulo_vetor_pf;
					$this->_template->PF_NOTA = $avaliacao->modulo_vetor_pf;
					
					$this->_template->block('WIKILV');
				}else if ($modulo == 'forumlv') {
					$this->_template->NUMERO_MINIMO_MENSAGENS = Forumlv::getNumeroMinimodeMensagens($atividade->fator_multiplicativo);
					$this->_template->block('NUM_MENSAGENS');
				}
				
				$this->_template->block('AVALIACAO_ATIVIDADE');
				$quantidade_atividades++;
			}
		}
		
		if ($quantidade_atividades == 0) {
			$this->_template->block('VISUALIZACAO_NAO_LIBERADA');
		}
		
		$this->_exibirPagina();
	}
	
	
	/**
	 *	Constrói e exibe a tela contendo o desempenho de todos os estudantes inscritos no curso 
	 *
	 *	@access public
	 */
	public function desempenhoParticipantes( ) {
		$this->_template = new Template('html/relatorios/relatorio_notas.html');
		$curso = $this->_data['curso'];
		$estudantes = $this->_data['estudantes'];
		$modulos = $this->_data['modulos'];
		$isAF = false;
		$this->_template->CURSO_NOME = strip_tags($curso->nome);
		$this->_template->CURSO_LEGENDA = strip_tags($curso->legenda);
		$this->_template->CURSO_DESCRICAO = strip_tags($curso->descricao);
		$this->_template->BASE_URL = LVS_WWWROOT;
		$this->_template->CURSO_ID = $curso->id;
		$this->_template->INDIVIDUAL = (count($estudantes) == 1) ? '&usuario=' . reset($estudantes)->id : false;   
		
		$i = 0;
		foreach ($estudantes as $estudante) {
			$desempenho = $estudante->desempenho;

			$this->_template->ESTUDANTE_ID = $estudante->id;
			$this->_template->ESTUDANTE_NOME = $estudante->firstname . ' ' . $estudante->lastname;
		
			if ($i % 2 == 0) {
				$this->_template->CLASS_ALUNO = "list_row";
			} else {
				$this->_template->CLASS_ALUNO = "list_row_color";
			}
				
			foreach ($modulos as $modulo) {
				$str_nota = "nota_$modulo";
				$str_ausencias = "ausencias_$modulo";
					
				$this->_template->NOME_ATIVIDADE 	= $modulo;
				$this->_template->NOTA_ATIVIDADE 	= $desempenho->$str_nota;
				$this->_template->TARGET_ATIVIDADE 	= '_blank';
				
				$this->_template->AUSENCIAS_ATIVIDADE = $desempenho->$str_ausencias;
		
				$this->_template->block('ATIVIDADE_DISTANCIA');
			}
				
			$this->_template->NOTA_DISTANCIA 	  = $desempenho->nd;
			$this->_template->NOTA_ATIVIDADE 	  = $desempenho->np;
			$this->_template->TARGET_ATIVIDADE 	  = '_blank';
			$this->_template->AUSENCIAS_ATIVIDADE = $desempenho->aap;
				
			$this->_template->block('ATIVIDADE_PRESENCIAL');
		
			$this->_template->MEDIA 		= $desempenho->media_parcial;
			$this->_template->TOTAL_FALTAS 	= $desempenho->ntf;
			$this->_template->FATOR_BETA 	= $desempenho->beta;
				
			if($desempenho->situacao == 'C' &&  $this->_data['somenteLeitura']){
				$this->_template->IMAGEM_LV_ICONE = 'cursando.gif';
			} else {
				$this->_template->IMAGEM_LV_ICONE = $desempenho->lv_icone;
			}
		
			$media_final = ($desempenho->media_final == NULL && $desempenho->af == NULL)?
			$desempenho->media_parcial : $desempenho->media_final;
				
			$this->_template->MEDIA_FINAL = round($media_final, 1);
			$this->_template->NOTA_AF = $desempenho->situacao;
		
			// se o estudante estiver de AF ou a nota final dele já tenha sido dada, em ambos os casos, é permitido fornecer a nota final
			if ( ($desempenho->situacao == 'AF' || $desempenho->af !== NULL) && !$this->_data['somenteLeitura'] ) {
				$isAF = true;
				$this->_template->NOTA_AF 	= ($desempenho->af == NULL)? 0 : $desempenho->af;
				$this->_template->ESTUDANTE_ID 	= $estudante->id;
				$this->_template->block('AF');
			} else {
				$this->_template->block('AM');
			}
		
			$this->_template->SITUACAO = $desempenho->situacao;
			$this->_template->block('ALUNO');
		
			$i++;
		}
		
		if ($isAF && !$this->_data['somenteLeitura']) {
			$this->_template->block('SALVAR_NOTAS_AF');
		
			$this->_template->FORM_ACTION = "biblioteca/grava_av_final.php?curso=$curso->id";
			$this->_template->block('INIT_FORM_AF');
		
			$this->_template->block('END_FORM_AF');
		}
		
		$this->_template->BETA_MEDIO 				= $this->_data['betaMedio'];
		$this->_template->MEDIA_CURSO 				= $this->_data['mediaCurso'];
		$this->_template->MEDIA_APROVACAO_AF_CURSO 	= $this->_data['mediaAprovacaoCurso'];
		$this->_template->MEDIA_AF_CURSO 			= $this->_data['mediaAFCurso'];
		$this->_template->PERCENTUAL_FALTAS_CURSO 	= $this->_data['percentualFaltasCurso'];
		
		$this->_adapterView->css('/blocks/lvs/biblioteca/dompdf/css/table.css');
		$this->_exibirPagina();
	}
	
	/**
	 *	Constrói e exibe a tela contendo a nota de um estudante em cada atividade presencial
	 *
	 * 	@access public
	 */
	public function desempenhoPresencial( ) {
		$this->_template = new Template('html/relatorios/relatorio_presenciais.html');
		$atividades = $this->_data['atividades'];
		
		if (!empty($atividades)) {
			foreach ($atividades as $atividade) {
				$this->_template->PRESENCIAL_NOME = $atividade->nome;
				$this->_template->PRESENCIAL_NOTA = ($atividade->faltou_prova == 0) ? $atividade->nota : '<b style="color:red;">2a chamada</b>';
				$this->_template->block('PRESENCIAL');
			}
		} else {
			$this->_template->block('NENHUMA_ATIVIDADE');
		}
		
		$this->_adapterView->css('/blocks/lvs/biblioteca/dompdf/css/table.css');
		$this->_exibirPagina();
	}
	
	/**
	 *	Constrói e exibe a tela contendo o relatório de faltas de um estudante
	 *
	 *	@access public
	 */
	public function faltasEstudante( ) {
		$this->_template = new Template('html/relatorios/relatorio_faltas.html');
		$faltas = $this->_data['faltas'];
		
		foreach ($faltas['distancia'] as $modulo => $falta) {
			if($falta['numero_atividades'] > 0){    
    			$this->_template->DISTANCIA_NOME = $modulo;
    			$this->_template->DISTANCIA_AUSENCIAS = $falta['faltas'] . ' de ' . $falta['numero_atividades'];
    			$this->_template->block('ATIVIDADE_DISTANCIA');
            }
		}
		
		foreach ($faltas['presencial'] as $nome => $falta) {
			$this->_template->PRESENCIAL_NOME = $nome;
			$this->_template->PRESENCIAL_AUSENCIAS = $falta['ausencias'];
			$this->_template->PRESENCIAL_FALTAS = $falta['faltas'];
			$this->_template->FALTAS_DA_ATIVIDADE = $falta['faltasdaatividade'];
			$this->_template->block('ATIVIDADE_PRESENCIAL');
		}
		
		if (!$faltas['presencial'])
			$this->_template->block('NENHUMA_PRESENCIAL');
		
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