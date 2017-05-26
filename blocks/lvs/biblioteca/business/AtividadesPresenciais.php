<?php 
namespace uab\ifce\lvs\business;

use uab\ifce\lvs\moodle2\business\Quizzes;

/**
 * 	Gerencia o conjunto de atividades presenciais pertencentes ao mesmo curso
 *
 *	@category LVs
 *	@package uab\ifce\lvs\business
 *	@author Allyson Bonetti 
 *	@author Ricky Paz (rickypaz@gmail.com)
 *	@version SVN $Id
 *	@todo criar camada de persistência (AtividadesPresenciaisDAO)
 */
class AtividadesPresenciais implements GerenciadorAtividades {

	/**
	 * 	CursoLv que terá as atividades presenciais geradas
	 *	@var CursoLv
	 * 	@access private
	 */
	private $_cursolv;
	
	/**
	 * 	Nome da tabela do banco de dados que possui a avaliação de todos os estudantes
	 * 	@var string
	 */
	private $_tabelaAvaliacao;
	
	/**
	 * 	Nome da tabela do banco de dados que possui a configuração de todas as atividades presenciais
	 * 	@var string
	 */
	private $_tabelaConfiguracao;

	/**
	 * Instancia AtividadesPresenciais
	 *
	 * @param business::CursoLv cursolv
	 * @access public
	 */
	public function __construct( $cursolv ) {
		$this->_cursolv = $cursolv;
		$this->_tabelaAvaliacao = 'lvs_nota_presencial';
		$this->_tabelaConfiguracao = 'lvs_atv_presencial';
	}
	
	private function _faltouProva( $avaliacoes ) {
		if (!empty($avaliacoes)) 
		{

			foreach ($avaliacoes as $avaliacao) {

				if ($avaliacao->faltou_prova == 1)

					return $avaliacao->faltou_prova;

			}

		}

		return 0;
	}

	/**
	 *	Retorna o total de ausências de um estudante nas atividades presenciais
	 * 
	 *	@param int $estudante id do estudante
	 * 	@return int número de faltas
	 * 	@access public
	 * 	@todo mudar nome do método para numeroAusencias
	 */
	public function numeroFaltas($estudante) {
		global $DB;
		$faltas = 0;
		$curso_id = $this->_cursolv->getConfiguracao()->id_curso;
		$presenciais = $DB->get_records('lvs_atv_presencial', array('id_curso'=>$curso_id), 'id', 'id, max_faltas, porcentagem');

		foreach($presenciais as $presencial) {
			$nota = $DB->get_record('lvs_nota_presencial', array('id_atividade'=>$presencial->id, 'id_avaliado'=>$estudante));

			if (!empty($nota)) 
				$faltas += $nota->nr_faltas;
			else if($presencial->porcentagem != 0)
				$faltas += $presencial->max_faltas;
		}

		return $faltas;
	}
	
	/**
	 * 	
	 * 	@param int $estudante id do estudante
	 * 	@todo mudar nome do método
	 */
	public function numeroFaltasDiscriminado( $estudante ) {
		global $DB;
		$faltas = array();
		$curso_id = $this->_cursolv->getConfiguracao()->id_curso;
		$presenciais = $DB->get_records('lvs_atv_presencial', array('id_curso'=>$curso_id), 'id', 'id, nome, max_faltas');

		foreach($presenciais as $presencial) {
			$nota = $DB->get_record('lvs_nota_presencial', array('id_atividade'=>$presencial->id, 'id_avaliado'=>$estudante));

			$faltas[$presencial->nome]['ausencias'] = ($nota) ? 0 : 1;
			$faltas[$presencial->nome]['faltas'] = ($nota) ? $nota->nr_faltas : $presencial->max_faltas; 
			//adicionado para exibir no relatorio de atividades !
			$faltas[$presencial->nome]['faltasdaatividade'] = $presencial->max_faltas; 
		}

		/*global $USER;				
		if($USER->id == 2787){
			print_object($faltas);
		}*/
						
		
		return $faltas;
	}
	
	/* 
	 * 	Retorna o número de atividades presenciais. Esse número é representado pela quantidade de atividades
	 * 	vezes o seu número de turnos
	 * 
	 * 	@see uab\ifce\lvs\business.GerenciadorAtividades::quantidadeAtividades()
	 */
	public function quantidadeAtividades() {
		global $DB;
		$quantidadeTurnos = 0;
		$curso_id = $this->_cursolv->getConfiguracao()->id_curso;
		$presenciais = $DB->get_records($this->_tabelaConfiguracao, array('id_curso'=>$curso_id));
		
		if (!empty($presenciais)) {
			foreach ($presenciais as $presencial) {
				$quantidadeTurnos += $presencial->max_faltas;
			}
		}
		
		return $quantidadeTurnos;
	}

	/**
	 * 	Dada uma atividade presencial, marca como faltosos alunos que não possuem nota
	 *
	 * 	@param int $presencial id da atividade presencial
	 * 	@access public
	 */
	public function ausentarAlunoSemNota( $presencial ) {
		nao_implementado(__CLASS__, __FUNCTION__);
	}

	/**
	 * 	Retorna uma atividade presencial
	 *
	 *	@param int $presencial id da atividade presencial
	 * 	@return \stdClass atividade ou false, caso ela não exista
	 * 	@access public
	 */
	public function recuperarAtividade( $presencial ) {
		global $DB;
		$curso_id = $this->_cursolv->getConfiguracao()->id_curso;
	
		return $DB->get_record('lvs_atv_presencial', array('id'=>$presencial));
	}
	
	/**
	 *	Retorna todas as atividades presenciais do curso
	 *
	 *	@return array:\stdClass { id: int, id_curso: int, nome: string, descricao: string, porcentagem: float, max_faltas: int }
	 * 	@access public
	 */
	public function recuperarAtividades( ) {
		global $DB;
		$curso_id = $this->_cursolv->getConfiguracao()->id_curso;
		
		return $DB->get_records('lvs_atv_presencial', array('id_curso'=>$curso_id), 'id');
	}

	/**
	 *	Retorna o desempenho geral de um estudante nas atividades presenciais
	 *
	 * 	@param int $estudante id do estudante
	 * 	@access public
	 * 	@todo alterar nome do método. Ele não apenas recupera, como calcula
	 */
	public function recuperarDesempenho( $estudante ) {
		$desempenho = new \stdClass();
		
		$desempenho->avaliacoes 		= $this->recuperarAvaliacoes($estudante);
		$desempenho->notaFinal 			= $this->_calcularNota($desempenho->avaliacoes);
		$desempenho->numeroFaltas 		= $this->numeroFaltas($estudante);
		$desempenho->numeroAtividades 	= $this->quantidadeAtividades();
		$desempenho->faltouProva		= $this->_faltouProva($desempenho->avaliacoes);
				
		return $desempenho;
	}

	/**
	 *	Armazena ou atualiza as atividades presenciais do curso
	 *
	 * 	@param mixed $presenciais um objeto ou uma array de objetos
	 * 	@return todos os objetos armazenados com os respectivos ids na mesma ordem em que foram fornecidos
	 * 	@access public
	 */
	public function salvarAtividades( $presenciais ) {
		global $DB;
		$atividades_salvas = array();
		
		if(!is_array($presenciais)) {
			$presenciais = array($presenciais);
		}
		
		foreach ($presenciais as $presencial) {
			$presencial = (object) $presencial;
			
			if(!isset($presencial->id) || empty($presencial->id))
				$presencial->id = $DB->insert_record( $this->_tabelaConfiguracao, $presencial );
			else 
				$DB->update_record( $this->_tabelaConfiguracao, $presencial );
			
			$atividades_salvas[] = $presencial;
		} 
		
		if (!empty($atividades_salvas)) {
			$this->_cursolv->atualizarCurso();
		}
		
		return $atividades_salvas;
	}
	
	public function salvarAvaliacoes( $avaliacoes ) {
		global $DB;
				
		if(!is_array($avaliacoes)) {
			$avaliacoes = array($avaliacoes);
		}
		
		$presencial = $this->recuperarAtividade(reset($avaliacoes)->id_atividade);
				
		foreach ($avaliacoes as $avaliacao) 
		{
            if(!isset($avaliacao->nota) || (isset($avaliacao->nota) && $avaliacao->nota == ''))
            {
                $avaliacao->nr_faltas = $presencial->max_faltas;
                $avaliacao->faltou_prova = 1;
            } 
            else
            {
            	$avaliacao->nota = str_replace(',','.',$avaliacao->nota);
            	$avaliacao->nota = number_format($avaliacao->nota, 1);
                $avaliacao->faltou_prova = 0;
            }
              
			$avaliacao = (object) $avaliacao;
			$avaliacao->id = $DB->get_field( $this->_tabelaAvaliacao, 'id', array('id_atividade'=>$avaliacao->id_atividade, 'id_avaliado'=>$avaliacao->id_avaliado)); 
			$avaliacao->faltou_prova = isset($avaliacao->faltou_prova)? $avaliacao->faltou_prova : 0;
			
			if( $avaliacao->nr_faltas <= $presencial->max_faltas ) {
				if(!isset($avaliacao->id) || empty($avaliacao->id))
					$avaliacao->id = $DB->insert_record( $this->_tabelaAvaliacao, $avaliacao );
				else
					$DB->update_record( $this->_tabelaAvaliacao, $avaliacao );
			}
		}
		
		$this->_cursolv->atualizarCurso();
	}

	/**
	 *	Remove a atividade presencial
	 *
	 * 	@param int $presencial id da atividade presencial 
	 * 	@access public
	 * 	@todo receber objetos e uma lista
	 */
	public function removerAtividadePorId( $presencial ) {
		global $DB;

		$DB->delete_records($this->_tabelaConfiguracao, array('id'=>$presencial));
	}

	/**
	 *	Remove a atividade presencial e distribui sua porcentagem a outra atividade presencial
	 *
	 * 	@param int $presencial id da atividade presencial 
	 * 	@access public
	 * 	@todo receber objetos e uma lista
	 */
	public function removerAtividade( $presencial ) {
		global $DB;
		$quizzes = new Quizzes($this->_cursolv);
		
		$porcentagem = $DB->get_field($this->_tabelaConfiguracao, 'porcentagem', array('id'=>$presencial));
		$DB->delete_records($this->_tabelaConfiguracao, array('id'=>$presencial));
		
		try {
			$quizzes->removerQuizImportadoPorPresencial($presencial);
		} catch (\Exception $e) {}
		
		$atividades = $this->recuperarAtividades();
		
		if (count($atividades) != 0) {
			$atividade = reset($atividades);
			$atividade->porcentagem += $porcentagem;
			
			$this->salvarAtividades($atividade);
		}
	}
	
	/**
	 * 	Retorna o CursoLv
	 *
	 * 	@return CursoLv
	 * 	@access public
	 */
	public function getCursoLv( ) {
		return $this->_cursolv;
	}
	
	/**
	 *	Altera o CursoLv
	 *
	 * 	@param CursoLv $cursolv
	 * 	@access public
	 */
	public function setCursoLv( $cursolv ) {
		$this->_cursolv = $cursolv;
	}
	
	/**
	 *	Retorna o desempenho de um estudante nas atividades presenciais
	 *
	 * 	@param int $estudante id do estudante
	 * 	@return array:\stdClass cada elemento representa uma avaliação
	 * 	@access public
	 */
	public function recuperarAvaliacoes( $estudante ) {
		global $DB;
		$avaliacoes = array();
		$curso_id = $this->_cursolv->getConfiguracao()->id_curso;
		
		$sql = "SELECT atividade.id, atividade.nome, atividade.porcentagem, atividade.max_faltas, nota.nota, nota.nr_faltas, nota.faltou_prova
				FROM {lvs_atv_presencial} as atividade 
				LEFT JOIN {lvs_nota_presencial} as nota ON atividade.id = nota.id_atividade AND nota.id_avaliado = ?  
				WHERE atividade.id_curso = ?";
	
		return $DB->get_records_sql($sql, array($estudante, $curso_id));
	}
	
	public function recuperarAvaliacoesNaAtividade( $atividade ) {
		global $DB;
		
		if(!$DB->record_exists($this->_tabelaConfiguracao, array('id'=>$atividade))) 
			throw new \Exception('Atividade Presencial inexistente');
		
		$estudantes = $this->_cursolv->getEstudantes();
		$ids = array_keys($estudantes);
		
		list($mask, $params) = $DB->get_in_or_equal($ids, SQL_PARAMS_QM);
		$curso_id = $this->_cursolv->getConfiguracao()->id_curso;

		$sql = "SELECT nota.id_avaliado, atividade.id, atividade.porcentagem, atividade.max_faltas, nota.id as avaliacao_id, nota.nota, nota.nr_faltas, nota.faltou_prova
				FROM {lvs_atv_presencial} as atividade
				INNER JOIN {lvs_nota_presencial} as nota ON atividade.id = nota.id_atividade
				WHERE atividade.id = ?";
		$notas = $DB->get_records_sql($sql, array($atividade));
		
		
		foreach ($estudantes as $estudante) {
		    $estudante->avaliacao = new \stdClass();
			if(isset($notas[$estudante->id])) {
				$estudante->avaliacao->id 			= $notas[$estudante->id]->avaliacao_id;
				$estudante->avaliacao->nota 		= $notas[$estudante->id]->nota;
				$estudante->avaliacao->nr_faltas 	= $notas[$estudante->id]->nr_faltas;
				$estudante->avaliacao->faltou_prova = $notas[$estudante->id]->faltou_prova;
			}
		}

		return $estudantes;
	}
	
	/**
	 * 	Soma as notas das avaliações ponderadas pela porcentagem da atividade presencial avaliada
	 * 	
	 * 	@param array:\stdClass $avaliacoes
	 * 	@return float média ponderada das avaliações
	 */
	private function _calcularNota($avaliacoes) {
		$somatorio = 0;
	
		if (!empty($avaliacoes)) {
			foreach ($avaliacoes as $avaliacao) {
				if ($avaliacao->nota !== null)
					$somatorio += $avaliacao->nota * $avaliacao->porcentagem / 100;
			}
		}
	
		return round($somatorio, 2);
	}

}
?>