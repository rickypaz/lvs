<?php
namespace uab\ifce\lvs\moodle2\business;

use uab\ifce\lvs\business\GerenciadorAtividadesDistancia;

class ChatsLv extends GerenciadorAtividadesDistancia {

	const NOME = 'Chat LV';
	
	/**
	 * 	Nome da tabela do banco de dados que possui a configuração lv de todos os chatslvs
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
		$this->_tabelaAvaliacao 	= 'lvs_chatlv';
		$this->_tabelaConfiguracao 	= 'chatlv';
	}
	
	public function numeroFaltas( $estudante ) {
		global $DB;
		$faltas = 0;
		$curso_id = $this->getCursoLv()->getConfiguracao()->id_curso;
		$chats = $DB->get_records('chatlv', array('course'=>$curso_id));
		
		if (!empty($chats)) {
			foreach ($chats as $chat) {
				$possui_avaliacao = $DB->record_exists($this->_tabelaAvaliacao, array(
						'id_curso'=>$curso_id,
						'id_usuario'=>$estudante,
						'id_chatlv'=>$chat->id
				));
				
				if (!$possui_avaliacao) {
					$participou_chat = $DB->record_exists('chatlv_messages', array('chatlvid'=>$chat->id, 'userid'=>$estudante));
				
					if (!$participou_chat)
						$faltas++;
				}
			}
		}
		
		return $faltas;
	}
	
	public function porcentagemDistancia() {
		global $DB;
		$curso_id = $this->_cursolv->getConfiguracao()->id_curso;
		$soma = $DB->get_record('chatlv', array('course'=>$curso_id), 'SUM(porcentagem) as total');
	
		return $soma->total;
	}
	
	public function porcentagemNula() {
		global $DB;
		$curso_id = $curso_id = $this->_cursolv->getConfiguracao()->id_curso;
	
		return $DB->record_exists('chatlv', array('course'=>$curso_id, 'porcentagem'=>NULL));
	}
	
	public function quantidadeAtividades( ) {
		global $DB;
		$curso_id = $this->_cursolv->getConfiguracao()->id_curso;
		
		return $DB->count_records('chatlv', array('course'=>$curso_id));
	}
	
	public function recuperarAtividades( ) {
		global $DB;
		$curso_id = $this->_cursolv->getConfiguracao()->id_curso;
		return $DB->get_records('chatlv', array('course'=>$curso_id));
	}
	
	public function recuperarAvaliacoes( $estudante ) {
		global $DB;
		$curso_id = $this->_cursolv->getConfiguracao()->id_curso;
		
		return $DB->get_records($this->_tabelaAvaliacao, array('id_curso'=>$curso_id, 'id_usuario'=>$estudante));
	}
	
	public function recuperarConfiguracao( $chatslvs ) {
		global $DB;
		$configuracoes = array();
		$campos = 'id, porcentagem, etapa, fator_multiplicativo, exibir';
		
		if(!is_array($chatslvs)) {
			return $DB->get_record($this->_tabelaConfiguracao, array('id'=>$chatslvs->id), $campos);
		}
		
		foreach ($chatslvs as $chatlv) {
			$configuracoes[$chatlv->id] = $DB->get_record($this->_tabelaConfiguracao, array('id'=>$chatlv->id), $campos);
			$configuracoes[$chatlv->id]->name = $chatlv->name;
			$configuracoes[$chatlv->id]->intro = $chatlv->intro;
			$configuracoes[$chatlv->id]->cm  = get_coursemodule_from_instance('chatlv', $chatlv->id)->id;
		}
		
		return $configuracoes;
	}
	
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
			$atividade->avaliacaolv = $DB->get_record('lvs_chatlv', array('id_chatlv'=>$atividade->id, 'id_usuario'=>$estudante));
		}
		
		return $atividades;
	}
	
	public function removerAtividade( $chatlv_id ) {
		global $DB;
		$DB->delete_records($this->_tabelaNota, array('modulo'=>'chatlv', 'modulo_id'=>$chatlv_id));
		$DB->delete_records($this->_tabelaAvaliacao, array('id_chatlv'=>$chatlv_id));
		$DB->delete_records($this->_tabelaConfiguracao, array('chatlvid'=>$chatlv_id));
	}

	public function salvarConfiguracao( $chatslvs ) {
		global $DB;
		
		if(!is_array($chatslvs)) {
			$chatslvs = array($chatslvs);
		}
		
		foreach ($chatslvs as $configuracao) {
			$configuracao = (object) $configuracao;
			$configuracao->exibir = (isset($configuracao->exibir)) ? 1 : 0;
		
			if(isset($configuracao->id)) {
				$DB->update_record($this->_tabelaConfiguracao, $configuracao);
			} elseif ($configuracao_id = $DB->get_field($this->_tabelaConfiguracao, 'id', array('id'=>$configuracao->chatlvid))) {
				$configuracao->id = $configuracao_id;
				$DB->update_record($this->_tabelaConfiguracao, $configuracao);
			} else {
				$DB->insert_record($this->_tabelaConfiguracao, $configuracao);
			}
		}
	}
	
	private function _calcularNotaPonderada($avaliacoes) {
		global $DB;
		$somatorio = 0;
	
		if (!empty($avaliacoes)) {
			foreach ($avaliacoes as $avaliacao) {
				$porcentagem = $DB->get_field($this->_tabelaConfiguracao, 'porcentagem', array('id'=>$avaliacao->id_chatlv));
				$somatorio += $avaliacao->modulo_vetor * $porcentagem / 100;
			}
		}
	
		return round($somatorio, 2);
	}
	
}
?>