<?php
namespace uab\ifce\lvs\moodle2\business;

use uab\ifce\lvs\business\GerenciadorAtividadesDistancia;

use uab\ifce\lvs\business\AtividadesLv;

/**
 * 	Representa e opera sobre o conjunto de tarefas lvs pertencentes ao mesmo curso
 *
 *	@category LVs
 *	@package uab\ifce\lvs\moodle2\business
 *	@author Allyson Bonetti
 *	@author Ricky Paz (rickypaz@gmail.com)
 *	@version SVN $Id
 */
class TarefasLv extends GerenciadorAtividadesDistancia {
	
	const NOME = 'Tarefa LV';
	
	/**
	 * 	Nome da tabela do banco de dados que possui a configuração lv de todos os wikilvs
	 * 	@var string
	 */
	private $_tabelaConfiguracao;
	
	/**
	 * 	Nome da tabela do banco de dados que possui a avaliação lv dos estudantes avaliados
	 * 	@var string
	 */
	private $_tabelaAvaliacao;
	
	/**
	 * 	Nome da tabela do banco de dados que possui todas as notas lvs dadas
	 * 	@var string
	 */
	private $_tabelaNota;
	
	/**
	 * @param CursoLv $cursolv
	 */
	public function __construct($cursolv) {
		$this->_cursolv 			= $cursolv;
		$this->_tabelaNota 			= 'rating';
		$this->_tabelaAvaliacao 	= 'lvs_tarefalv';
		$this->_tabelaConfiguracao 	= 'tarefalv';
	}
	
	/**
	 *	Retorna o total de ausências de um estudante nas tarefaslvs do curso
	 *
	 *	@param int $estudante id do estudante
	 * 	@return int número de faltas
	 * 	@access public
	 */
	public function numeroFaltas($estudante) {
		global $DB;
		$faltas = 0;
		$curso_id = $this->getCursoLv()->getConfiguracao()->id_curso;
		$tarefas = $DB->get_records('tarefalv', array('course'=>$curso_id));
	
		if(!empty($tarefas)) {
			foreach ($tarefas as $tarefa) {
				$possui_avaliacao = $DB->record_exists('lvs_tarefalv', array(
						'id_curso'=>$curso_id, 
						'id_usuario'=>$estudante, 
						'id_tarefalv'=> $tarefa->id
				));
				
				if(!$possui_avaliacao) {
					$qtde_submissions = $DB->count_records('tarefalv_submissions', array('tarefalv'=>$tarefa->id, 'userid'=>$estudante));
				
					if($qtde_submissions == 0)
						$faltas++;
				}
			}
		}
	
		return $faltas;
	}
	
	/**
	 *	Calcula a soma das porcentagens a distancia de todos as tarefaslvs do curso
	 *
	 *  @return float
	 *  @access public
	 */
	public function porcentagemDistancia() {
		global $DB;
		$curso_id = $this->_cursolv->getConfiguracao()->id_curso;
		$soma = $DB->get_record('tarefalv', array('course'=>$curso_id), 'SUM(porcentagem) as total');
	
		return $soma->total;
	}
	
	/**
	 * 	Verifica se há alguma tarefalv do curso cuja porcentagem não tenha sido definida
	 *
	 * 	@return boolean true, caso haja, false, caso contrário
	 *  @access public
	 */
	public function porcentagemNula() {
		global $DB;
		$curso_id = $curso_id = $this->_cursolv->getConfiguracao()->id_curso;
	
		return $DB->record_exists('tarefalv', array('course'=>$curso_id, 'porcentagem'=>NULL));
	}
	
	/**
	 * 	Retorna o número de tarefaslvs do curso
	 *
	 * 	@return int
	 * 	@access public
	 */
	public function quantidadeAtividades() {
		global $DB;
		$curso_id = $this->_cursolv->getConfiguracao()->id_curso;
	
		return $DB->count_records('tarefalv', array('course'=>$curso_id));
	}
	
	/**
	 *	Retorna todos as tarefaslvs do curso
	 *	
	 * 	@return array
	 * 	@access public
	 */
	public function recuperarAtividades( ) {
		global $DB;
		$curso_id = $this->_cursolv->getConfiguracao()->id_curso;
		
		return $DB->get_records('tarefalv', array('course'=>$curso_id));
	}
	
	/**
	 *	Retorna o desempenho de um estudante em cada tarefalv avaliada
	 *
	 * 	@param int $estudante id do estudante
	 * 	@return array:\stdClass cada elemento representa uma avaliação
	 * 	@access public
	 */
	public function recuperarAvaliacoes( $estudante ) {
		global $DB;
		$curso_id = $this->_cursolv->getConfiguracao()->id_curso;
		
		return $DB->get_records($this->_tabelaAvaliacao, array('id_curso'=>$curso_id, 'id_usuario'=>$estudante));
	}
	
	/**
	 *	Retorna o desempenho geral de um estudante no conjunto de tarefas do curso
	 *
	 * 	@param int $estudante id do estudante
	 * 	@access public
	 * 	@todo alterar nome do método. Ele não apenas recupera, como calcula
	 * 	@todo em calcular nota ponderada, substituir parâmetro por id do usuário e usar estratégias de cache
	 */
	public function recuperarDesempenho( $estudante ) {
		$desempenho = new \stdClass();

		$desempenho->avaliacoes = $this->recuperarAvaliacoes($estudante);
		$desempenho->notaFinal = $this->_calcularNotaPonderada($desempenho->avaliacoes);
		$desempenho->numeroFaltas = $this->numeroFaltas($estudante);
		$desempenho->carinhasAzuis = $desempenho->carinhasVerdes = $desempenho->carinhasAmarelas = $desempenho->carinhasLaranjas = $desempenho->carinhasVermelhas = 0;
		$desempenho->numeroAtividades = $this->quantidadeAtividades();
		
		foreach($desempenho->avaliacoes as $avaliacao) {
			$desempenho->carinhasAzuis 		+= $avaliacao->numero_carinhas_azul;
			$desempenho->carinhasVerdes 	+= $avaliacao->numero_carinhas_verde;
			$desempenho->carinhasAmarelas 	+= $avaliacao->numero_carinhas_laranja;
			$desempenho->carinhasLaranjas 	+= $avaliacao->numero_carinhas_amarela;
			$desempenho->carinhasVermelhas 	+= $avaliacao->numero_carinhas_vermelha;
		}
		
		return $desempenho;
	}
	
	public function recuperarDesempenhoPorAtividade( $estudante ) {
		global $DB;
		$atividades = $this->recuperarAtividades();
	
		foreach ($atividades as $atividade) {
			$atividade->avaliacaolv = $DB->get_record('lvs_tarefalv', array('id_tarefalv'=>$atividade->id, 'id_usuario'=>$estudante));
		}
	
		return $atividades;
	}
	
	/**
	 * 	Retorna a configuração lv de uma ou mais tarefaslvs
	 *
	 * 	@param mixed $tarefaslvs uma tarefalv ou um array de tarefaslvs
	 * 	@return array
	 * 	@access public
	 */
	public function recuperarConfiguracao( $tarefaslvs ) {
		global $DB;
		$configuracoes = array();
		$campos = 'id, name, intro, porcentagem, etapa, exibir';
	
		if(!is_array($tarefaslvs)) {
			return $DB->get_record($this->_tabelaConfiguracao, array('id'=>$tarefaslvs->id), $campos);
		}
	
		foreach ($tarefaslvs as $tarefalv) {
			$configuracoes[$tarefalv->id] = $DB->get_record($this->_tabelaConfiguracao, array('id'=>$tarefalv->id), $campos);
			$configuracoes[$tarefalv->id]->cm  = get_coursemodule_from_instance('tarefalv', $tarefalv->id)->id;
		}
	
		return $configuracoes;
	}
	
	/**
	 *
	 * @param int atividade_id
	 * @access public
	 */
	public function removerAtividade( $atividade_id ) {
		nao_implementado(__CLASS__, __FUNCTION__);
	}
	
	
	/**
	 *	Armazena ou atualiza as configurações lvs de cada tarefalv tais como porcentagem, etapa, exibição de notas,
	 *	fator multiplicativo e período de avaliação
	 *
	 * 	@param mixed $tarefaslvs
	 * 	@access public
	 */
	public function salvarConfiguracao( $tarefaslvs ) {
		global $DB;
	
		if(!is_array($tarefaslvs)) {
			$tarefaslvs = array($tarefaslvs);
		}
	
		foreach ($tarefaslvs as $configuracao) {
			$configuracao = (object) $configuracao;
            $configuracao->exibir = (isset($configuracao->exibir)) ? 1 : 0;
	
			if(isset($configuracao->id)) {
				$DB->update_record($this->_tabelaConfiguracao, $configuracao);
			} elseif ($configuracao_id = $DB->get_field($this->_tabelaConfiguracao, 'id', array('id'=>$configuracao->wikilvid))) {
				$configuracao->id = $configuracao_id;
				$DB->update_record($this->_tabelaConfiguracao, $configuracao);
			} else {
				$DB->insert_record($this->_tabelaConfiguracao, $configuracao);
			}
		}
	}
	
	/**
	 * 	Calcula a nota final nas tarefaslvs por meio da soma das notas de cada tarefalv. A soma das notas é ponderada por meio da porcentagem
	 * 	de cada tarefalv.
	 *
	 * 	@param array $avaliacoes tarefaslvs avaliadas
	 * 	@return float soma ponderada das notas
	 * 	@todo recuperar todas as porcentagens de uma só vez
	 */
	private function _calcularNotaPonderada($avaliacoes) {
		global $DB;
		$somatorio = 0;
	
		if (!empty($avaliacoes)) {
			foreach ($avaliacoes as $avaliacao) {
				$porcentagem = $DB->get_field($this->_tabelaConfiguracao, 'porcentagem', array('id'=>$avaliacao->id_tarefalv));
				$somatorio += $avaliacao->modulo_vetor * $porcentagem / 100;
			}
		}
	
		return round($somatorio, 2);
	}

}