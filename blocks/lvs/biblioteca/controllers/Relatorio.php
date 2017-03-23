<?php 
namespace uab\ifce\lvs\controllers;

use uab\ifce\lvs\Template;

/**
 * 	class Relatorio
 * 
 * 	@package uab\ifce\lvs\controllers
 */
class Relatorio
{

	/**
	 *	
	 *	@var \uab\ifce\lvs\business\CursoLv
	 * 	@access private
	 */
	private $_cursolv;
	
	/**
	 * @var Template
	 */
	private $_template;
	
	private $_somenteLeitura;
	
	/**
	 * 	Diretório onde são armazenados os relatórios gerados
	 * 
	 * 	@var string
	 * 	@todo remover!
	 */
	private $_tempdir;

	/**
	 *
	 *
	 * @param business::CursoLv cursolv
	 * @return
	 * @access public
	 */
	public function __construct( $cursolv ) {
		$this->_cursolv = $cursolv;
		$this->_somenteLeitura = true;
		$this->_tempdir = sys_get_temp_dir();
	}

	/**
	 *	Retorna uma div html contendo o desempenho dos estudantes no curso
	 *
	 * 	@param mixed $estudantes id de um estudante ou uma lista de ids. Se não informado, serão calculados os desempenhos de todos os estudantes inscritos no curso
	 * 	@return string html
	 * 	@access public
	 * 	@todo aceitar o param estudantes como int, array ou nulo
	 * 	@todo retirar dependência de $DB
	 */
	public function desempenhoParticipantes( $estudantes = null ) {
		global $DB;
		$this->_template = new Template('html/biblioteca/notaslv_impressao.html');
		$curso = $this->_cursolv->getConfiguracao()->id_curso;
		$estudantes = $this->_cursolv->recuperarEstudantes();
		$course = $DB->get_record('course', array('id'=>$curso));
		$modulos = $this->_cursolv->recuperarAtividades();
		$isAF = false;
		
		$this->_template->CURSO_NOME 	 = $course->fullname;
		$this->_template->CURSO_LEGENDA 	 = $course->shortname;
		$this->_template->CURSO_DESCRICAO = $course->summary;
		$this->_template->BASE_URL 		 = LVS_WWWROOT;
		$this->_template->CURSO_ID 	 	 = $curso;
		
		$i = 0;
		foreach ($estudantes as $estudante) {
			$desempenho = $this->_cursolv->recuperarDesempenho($estudante->id);
			
			$this->_template->ESTUDANTE_NOME = $estudante->firstname . ' ' . $estudante->lastname;

			if ($i % 2 == 0) {
				$this->_template->CLASS_ALUNO = "list_row";
			} else {
				$this->_template->CLASS_ALUNO = "list_row_color";
			}
			
			foreach ($modulos as $nomeModulo => $modulo) {
				$str_nota = "nota_$nomeModulo";
				$str_ausencias = "ausencias_$nomeModulo";
			
				$this->_template->NOTA_ATIVIDADE 					= $desempenho->$str_nota;
				$this->_template->TARGET_ATIVIDADE 					= '_blank';
				$this->_template->LINK_AVALIACOES_USUARIO_ATIVIDADE = "relatorio_atividade.php?id=$curso&usuario=$estudante->id&atv=$nomeModulo";
				$this->_template->AUSENCIAS_ATIVIDADE 				= $desempenho->$str_ausencias;
				
				$this->_template->block('ATIVIDADE_DISTANCIA');
			}
			
			$this->_template->NOTA_DISTANCIA 					= $desempenho->nd;
			$this->_template->NOTA_ATIVIDADE 					= $desempenho->np;
			$this->_template->TARGET_ATIVIDADE 					= '_blank';
			$this->_template->LINK_AVALIACOES_USUARIO_ATIVIDADE 	= "relatorio_atividade_presencial.php.php?id=$curso&usuario=$estudante->id";
			$this->_template->AUSENCIAS_ATIVIDADE 				= $desempenho->aap;
			
			$this->_template->block('ATIVIDADE_PRESENCIAL');

			$this->_template->MEDIA 		= $desempenho->media_parcial;
			$this->_template->TOTAL_FALTAS 	= $desempenho->ntf;
			$this->_template->FATOR_BETA 	= $desempenho->beta;
			
			if($desempenho->situacao == 'C' &&  $this->_somenteLeitura){
				$this->_template->IMAGEM_LV_ICONE = 'cursando.gif';
			} else {
				$this->_template->IMAGEM_LV_ICONE = $desempenho->lv_icone;
			}

			$media_final = ($desempenho->media_final == NULL && $desempenho->af == NULL)? 
								$desempenho->media_parcial : $desempenho->media_final;
			
			$this->_template->MEDIA_FINAL = round($media_final, 1);
			$this->_template->NOTA_AF = $desempenho->situacao;

			// se o estudante estiver de AF ou a nota final dele já tenha sido dada, em ambos os casos, é permitido fornecer a nota final
			if ( ($desempenho->situacao == 'AF' || $desempenho->af !== NULL) && !$this->_somenteLeitura ) {
				$isAF = true;
				$this->_template->NOTA_AF 	= ($desempenho->af == NULL)? 0 : $desempenho->af;
				$this->_template->ESTUDANTE 	= $estudante->id;
				$this->_template->block('AF');
			} else {
				$this->_template->block('AM');
			}

			$this->_template->SITUACAO = $desempenho->situacao;
			$this->_template->block('ALUNO');

			$i++;
		}
		
		if ($isAF && !$this->_somenteLeitura) {
			$this->_template->block('SALVAR_NOTAS_AF');
		
			$this->_template->FORM_ACTION = "biblioteca/grava_av_final.php?curso=$curso";
			$this->_template->block('INIT_FORM_AF');
		
			$this->_template->block('END_FORM_AF');
		}
		
		$this->_template->BETA_MEDIO 				= $this->_cursolv->betaMedio();
		$this->_template->MEDIA_CURSO 				= $this->_cursolv->getConfiguracao()->media_curso;
		$this->_template->MEDIA_APROVACAO_AF_CURSO 	= $this->_cursolv->getConfiguracao()->media_aprov_af;
		$this->_template->MEDIA_AF_CURSO 			= $this->_cursolv->getConfiguracao()->media_af;
		$this->_template->PERCENTUAL_FALTAS_CURSO 	= $this->_cursolv->getConfiguracao()->percentual_faltas;
		
		return $this->_template->parse();
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
		file_put_contents($caminho, $this->_template->parse());
		
		redirect(LVS_WWWROOT . '/biblioteca/dompdf/dompdf.php?base_path=template/&input_file=' . rawurlencode($arquivo));
		exit;
	}
	
	public function isSomenteLeitura() {
		return $this->_somenteLeitura;
	}
	
	public function setSomenteLeitura($somenteLeitura) {
		$this->_somenteLeitura = $somenteLeitura;
	}
	
	/**
	 * 	Retorna o html que será utilizado para a construção do relatório em PDF
	 * 	
	 * 	@param mixed $estudantes id de um estudante ou uma lista de ids. Se não informado, serão calculados os desempenhos de todos os estudantes inscritos no curso
	 * 	@return string html
	 * 	@access public
	 * 	@todo aceitar o param estudantes como int, array ou nulo
	 * 	@todo retirar dependência de $DB
	 */
	private function _versaoPdf($estudantes = null) {
		global $DB;
		$this->_template = new Template('html/biblioteca/notaslv_pdf.html');
		$curso = $this->_cursolv->getConfiguracao()->id_curso;
		$estudantes = $this->_cursolv->recuperarEstudantes();
		$course = $DB->get_record('course', array('id'=>$curso));
		$modulos = $this->_cursolv->recuperarAtividades();
		$isAF = false;
		
		$this->_template->CURSO_NOME 	  = $course->fullname;
		$this->_template->CURSO_LEGENDA   = $course->shortname;
		$this->_template->CURSO_DESCRICAO = $course->summary;
		$this->_template->BASE_URL 		  = LVS_DIRROOT;
		
		$i = 0;
		foreach ($estudantes as $estudante) {
			$desempenho = $this->_cursolv->recuperarDesempenho($estudante->id);
		
			$this->_template->ESTUDANTE_NOME = $estudante->firstname . ' ' . $estudante->lastname;
		
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
		
			if($desempenho->situacao == 'C'){
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
	}

}
?>