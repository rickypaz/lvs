<?php 
namespace uab\ifce\lvs\moodle2\business;

use uab\ifce\lvs\business\CursoLv;
use uab\ifce\lvs\business\GerenciadorAtividadesDistancia;

/**
 * 	Representa e opera sobre o conjunto de wikilvs pertencentes ao mesmo curso
 * 
 * 	@category LVs
 * 	@package uab\ifce\lvs\moodle2\business
 * 	@author Ricky Paz (rickypaz@gmail.com)
 * 	@version SVN $Id
 */
class WikisLv extends GerenciadorAtividadesDistancia {
	
	const NOME = 'Wiki LV';
	
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
	
	public function __construct(CursoLv $cursolv) {
		$this->_cursolv 			= $cursolv;
		$this->_tabelaNota 			= 'lvs_notaslv';
		$this->_tabelaAvaliacao 	= 'lvs_wikilv';
		$this->_tabelaConfiguracao 	= 'wikilv';
	}
	
	public function numeroFaltas( $estudante ) {
		global $DB;
		$faltas = 0;
		$curso_id = $this->_cursolv->getConfiguracao()->id_curso;
		$wikilvs = $DB->get_records('wikilv', array('course'=>$curso_id));
		
		
	
		if(!empty($wikilvs)) {
			foreach ($wikilvs as $wikilv) {
// 				se estudante possui avaliação no wikilv, indica que ele participou e, portanto, não faltou
				$possui_avaliacao = $DB->record_exists($this->_tabelaAvaliacao, array(
					'id_curso'=>$curso_id, 'id_usuario'=>$estudante, 'id_wikilv'=>$wikilv->id
				));
	
// 				FIXME versões criadas por um estudante, mesmo que não avaliadas, contam como presença!
				if(!$possui_avaliacao)
					$faltas++;
			}
		}
	
		
		return $faltas;
	}
	
	/**
	 *	Calcula a soma das porcentangens a distancia de todos os wikis do curso
	 *
	 *  @return float
	 *  @access public
	 */
	public function porcentagemDistancia() {
		global $DB;
		$curso_id = $curso_id = $this->_cursolv->getConfiguracao()->id_curso;
		
		$soma = $DB->get_record('wikilv', array('course'=>$curso_id), 'SUM(porcentagem) as total');
		
		return $soma->total;
	}
	
	/**
	 * 	Verifica se há algum wikilv cuja porcentagem não tenha sido definida
	 * 
	 * 	@return boolean true, caso haja, false, caso contrário
	 */
	public function porcentagemNula() {
		global $DB;
		$curso_id = $curso_id = $this->_cursolv->getConfiguracao()->id_curso;
		
		return $DB->record_exists('wikilv', array('course'=>$curso_id, 'porcentagem'=>NULL));
	}
	
	/**
	 * 	Retorna o número de wikilvs do curso
	 *
	 * 	@return int número de wikilvs
	 * 	@access public
	 */
	public function quantidadeAtividades() {
		global $DB;
		$curso_id = $this->_cursolv->getConfiguracao()->id_curso;
	
		return $DB->count_records('wikilv', array('course'=>$curso_id));
	}
	
	/**
	 *	Retorna todos os wikilvs do curso
	 *	
	 * 	@return array
	 * 	@access public
	 */
	public function recuperarAtividades( ) {
		global $DB;
		$curso_id = $this->_cursolv->getConfiguracao()->id_curso;

		return $DB->get_records('wikilv', array('course'=>$curso_id));
	}
	
	/**
	 *	Retorna o desempenho de um estudante em cada wikilv avaliado
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
	 * 	Retorna a configuração lv de um ou mais wikilvs
	 *
	 * 	@param mixed wikilvs um wikilv ou uma array de wikislvs
	 * 	@return array
	 * 	@access public
	 */
	public function recuperarConfiguracao( $wikilvs ) {
		global $DB;
		$configuracoes = array();
		$campos = 'id, name, intro, porcentagem, etapa, fator_multiplicativo, assesstimestart, assesstimefinish, exibir';
		
		if(!is_array($wikilvs)) {
			return $DB->get_record($this->_tabelaConfiguracao, array('id'=>$wikilvs->id), $campos);
		}
		
		foreach ($wikilvs as $wikilv) {
			$configuracoes[$wikilv->id] = $DB->get_record($this->_tabelaConfiguracao, array('id'=>$wikilv->id), $campos);
			$configuracoes[$wikilv->id]->cm  = get_coursemodule_from_instance('wikilv', $wikilv->id)->id;
		}
	
		return $configuracoes;
	}
	
	/**
	 *	Retorna o desempenho geral de um estudante no conjunto de wikis
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
			$desempenho->carinhasAzuis += $avaliacao->numero_carinhas_azul;
			$desempenho->carinhasVerdes += $avaliacao->numero_carinhas_verde;
			$desempenho->carinhasAmarelas += $avaliacao->numero_carinhas_laranja;
			$desempenho->carinhasLaranjas += $avaliacao->numero_carinhas_amarela;
			$desempenho->carinhasVermelhas += $avaliacao->numero_carinhas_vermelha;
		}
		
		return $desempenho;
	}
	
	public function recuperarDesempenhoPorAtividade( $estudante ) {
		global $DB;
		$atividades = $this->recuperarAtividades();
		
		foreach ($atividades as $atividade) {
			$atividade->avaliacaolv = $DB->get_record('lvs_wikilv', array('id_wikilv'=>$atividade->id, 'id_usuario'=>$estudante));
		}
	
		return $atividades;
	}
	
	/**
	 *	Remove a configuração e todas as avaliações e notas relacionadas ao wikilv
	 *
	 * 	@param int wikilv_id
	 * 	@access public
	 * 	@todo remover notas do wiki
	 */
	public function removerAtividade( $wikilv_id ) {
		global $DB;
		$DB->delete_records($this->_tabelaNota, array('modulo'=>'wikilv', 'modulo_id'=>$wikilv_id));
		$DB->delete_records($this->_tabelaAvaliacao, array('id_wikilv'=>$wikilv_id));
	}
	
	/**
	 *	Armazena ou atualiza as configurações lvs de cada wikilv tais como porcentagem, etapa, exibição de notas, 
	 *	fator multiplicativo e período de avaliação
	 *
	 * 	@param mixed atividades
	 * 	@access public
	 * 	@todo remover avaliação do wiki após atualizar sua configuração!
	 */
	public function salvarConfiguracao( $wikilvs ) {
		global $DB;
		
		if(!is_array($wikilvs)) {
			$wikilvs = array($wikilvs);
		}
		
		foreach ($wikilvs as $configuracao) {
			$configuracao = (object) $configuracao;
			$configuracao->exibir = (isset($configuracao->exibir)) ? 1 : 0;

			if(isset($configuracao->id)) {
				$DB->update_record($this->_tabelaConfiguracao, $configuracao);
				
			} elseif ($configuracao_id = $DB->get_field($this->_tabelaConfiguracao, 'id', array('id'=>$configuracao->id))) {
				$configuracao->id = $configuracao_id;
				$DB->update_record($this->_tabelaConfiguracao, $configuracao);				
			} else {
				$DB->insert_record($this->_tabelaConfiguracao, $configuracao);
			}
		}
	}
	
	/**
	 * 	Calcula a nota final nos wikilvs por meio da soma das notas de cada wikilv. A soma das notas é ponderada por meio da porcentagem
	 * 	de cada wikilv.
	 * 
	 * 	@param array wikilvs avaliados
	 * 	@return float soma ponderada das notas
	 * 	@todo recuperar todas as porcentagens de uma só vez
	 */
	private function _calcularNotaPonderada($avaliacoes) {
		global $DB;
		$somatorio = 0;
	
		if (!empty($avaliacoes)) {
			foreach ($avaliacoes as $avaliacao) {
				$porcentagem = $DB->get_field($this->_tabelaConfiguracao, 'porcentagem', array('id'=>$avaliacao->id_wikilv));
				$somatorio += $avaliacao->modulo_vetor * $porcentagem / 100;
			}
		}
		
		return round($somatorio, 2);
	}

}
?>