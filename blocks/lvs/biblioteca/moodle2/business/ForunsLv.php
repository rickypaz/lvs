<?php
namespace uab\ifce\lvs\moodle2\business;

use uab\ifce\lvs\business\GerenciadorAtividadesDistancia;

/**
 * 	Representa e opera sobre um conjunto de fóruns lvs pertencentes ao mesmo curso
 *	
 *	@category LVs
 *	@package uab\ifce\lvs\moodle2\business
 *	@author Allyson Bonetti
 *	@author Ricky Paz (rickypaz@gmail.com)
 *	@version SVN $Id
 */
class ForunsLv extends GerenciadorAtividadesDistancia {

	const NOME = 'Fórum LV'; 
	
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
		$this->_tabelaAvaliacao 	= 'lvs_forumlv';
		$this->_tabelaConfiguracao 	= 'forumlv';
	}
	
	/**
	 *	Retorna o total de ausências de um estudante nos forunslvs do curso
	 *
	 *	@param int $estudante id do estudante
	 * 	@return int número de faltas
	 * 	@access public
	 */
	public function numeroFaltas($estudante) {
		global $DB;
		$faltas = 0;
		$curso_id = $this->getCursoLv()->getConfiguracao()->id_curso;
		$foruns = $DB->get_records('forumlv', array('course'=>$curso_id));
	
		if(!empty($foruns)) {
			foreach ($foruns as $forum) {
				// se estudante possui avaliação no forumlv, indica que ele participou e, portanto, não faltou
				$possui_avaliacao = $DB->record_exists($this->_tabelaAvaliacao, array(
						'id_curso'=>$curso_id, 
						'id_usuario'=>$estudante, 
						'id_forumlv'=>$forum->id
				));
				
				if(!$possui_avaliacao) {
					$discussion_id = $DB->get_field('forumlv_discussions', 'id', array('forumlv'=>$forum->id));
					$participou_forum = $DB->record_exists('forumlv_posts', array('discussion'=>$discussion_id, 'userid'=>$estudante));
				
					if (!$participou_forum)
						$faltas++;
				}
			}
		}
	
		return $faltas;
	}
	
	/**
	 *	Calcula a soma das porcentagens a distancia de todos os forunslvs do curso
	 *
	 *  @return float
	 *  @access public
	 */
	public function porcentagemDistancia() {
		global $DB;
		$curso_id = $this->_cursolv->getConfiguracao()->id_curso;
		$soma = $DB->get_record('forumlv', array('course'=>$curso_id), 'SUM(porcentagem) as total');
		
		return $soma->total;
	}
	
	/**
	 * 	Verifica se há algum forumlv do curso cuja porcentagem não tenha sido definida
	 *
	 * 	@return boolean true, caso haja, false, caso contrário
	 *  @access public
	 */
	public function porcentagemNula() {
		global $DB;
		$curso_id = $curso_id = $this->_cursolv->getConfiguracao()->id_curso;
	
		return $DB->record_exists('forumlv', array('course'=>$curso_id, 'porcentagem'=>NULL));
	}
	
	/**
	 * 	Retorna o número de fóruns lvs do curso
	 *
	 * 	@return int
	 * 	@access public
	 */
	public function quantidadeAtividades() {
		global $DB;
		$curso_id = $this->_cursolv->getConfiguracao()->id_curso;
		
		return $DB->count_records('forumlv', array('course'=>$curso_id));
	}
	
	/**
	 *	Retorna todos os forunslvs do curso
	 *	
	 * 	@return array
	 * 	@access public
	 */
	public function recuperarAtividades( ) {
		global $DB;
		$curso_id = $this->_cursolv->getConfiguracao()->id_curso;
		
		return $DB->get_records('forumlv', array('course'=>$curso_id));
	}

	/**
	 *	Retorna o desempenho de um estudante em cada forumlv avaliado
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
	 * 	Retorna a configuração lv de um ou mais forunslvs
	 *
	 * 	@param mixed forunslvs um forumlv ou um array de forunslvs
	 * 	@return array
	 * 	@access public
	 */
	public function recuperarConfiguracao( $forunslvs ) {
		global $DB;
		$configuracoes = array();
		$campos = 'id, name, intro, porcentagem, etapa, fator_multiplicativo, assesstimestart, assesstimefinish, exibir';
	
		if(!is_array($forunslvs)) {
			return $DB->get_record($this->_tabelaConfiguracao, array('id'=>$forunslvs->id), $campos);
		}
	
		foreach ($forunslvs as $forumlv) {
			$configuracoes[$forumlv->id] = $DB->get_record($this->_tabelaConfiguracao, array('id'=>$forumlv->id), $campos);
			$configuracoes[$forumlv->id]->cm  = get_coursemodule_from_instance('forumlv', $forumlv->id)->id;
		}
	
		return $configuracoes;
	}
	
	/**
	 *	Retorna o desempenho geral de um estudante no conjunto de foruns
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
	
	/* 
	 * 	@see uab\ifce\lvs\business.GerenciadorAtividadesDistancia::recuperarDesempenhoPorAtividade()
	 */
	public function recuperarDesempenhoPorAtividade( $estudante ) {
		global $DB;
		$atividades = $this->recuperarAtividades();
			
		foreach ($atividades as $atividade) {
			$atividade->avaliacaolv = $DB->get_record('lvs_forumlv', array('id_forumlv'=>$atividade->id, 'id_usuario'=>$estudante));
		}
		
		return $atividades;
	}
	
	/**
	 *	Armazena ou atualiza as configurações lvs de cada forumlv tais como porcentagem, etapa, exibição de notas,
	 *	fator multiplicativo e período de avaliação
	 *
	 * 	@param mixed atividades
	 * 	@access public
	 */
	public function salvarConfiguracao( $forunslvs ) {
		global $DB;
	
		if(!is_array($forunslvs)) {
			$forunslvs = array($forunslvs);
		}
	
		foreach ($forunslvs as $configuracao) {
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
	 *
	 *
	 * @param int atividade_id
	 * @return
	 * @access public
	 */
	public function removerAtividade( $atividade_id ) {
		nao_implementado(__CLASS__, __FUNCTION__, E_USER_ERROR);
	}
	
	/**
	 * 	Calcula a nota final nos forunslvs por meio da soma das notas de cada forumlv. A soma das notas é ponderada por meio da porcentagem
	 * 	de cada forumlv.
	 *
	 * 	@param array forunslvs avaliados
	 * 	@return float soma ponderada das notas
	 * 	@todo recuperar todas as porcentagens de uma só vez
	 */
	private function _calcularNotaPonderada($avaliacoes) {
		global $DB;
		$somatorio = 0;
	
		if (!empty($avaliacoes)) {
			foreach ($avaliacoes as $avaliacao) {
				$porcentagem = $DB->get_field($this->_tabelaConfiguracao, 'porcentagem', array('id'=>$avaliacao->id_forumlv));
				$somatorio += $avaliacao->modulo_vetor * $porcentagem / 100;
			}
		}
		
		return round($somatorio, 2);
	}
	
}
?>