<?php
namespace uab\ifce\lvs\moodle2\business;

use uab\ifce\lvs\EscalaLikert;
use uab\ifce\lvs\avaliacao\AvaliacaoLv;
use uab\ifce\lvs\business\Item;
use uab\ifce\lvs\business\CursoLv;
use uab\ifce\lvs\business\AtividadeLv;

class Chatlv extends AtividadeLv {
	
	/**
	 * 	Contém as configurações do chat lv
	 * 	@var \stdClass
	 * 	@access private
	 */
	private $_chatlv;
	
	/**
	 * 	Nome da tabela do banco de dados que possui a avaliação lv dos estudantes avaliados
	 * 	@var string
	 */
	private $_tabelaAvaliacao = 'lvs_chatlv';
	
	/**
	 * 	Nome da tabela do banco de dados que possui a configuração lv de todos os chatslvs
	 * 	@var string
	 */
	private $_tabelaConfiguracao = 'chatlv';
	
	/**
	 * 	Nome da tabela do banco de dados que possui todas as notas lvs dadas
	 * 	@var string
	 */
	private $_tabelaNota = 'rating';
	
	/**
	 * 	Instancia ChatLV
	 * 	@param int $chatlv_id id do fórumlv
	 */
	public function __construct( $chatlv_id ) {
		$this->_init($chatlv_id);
	}
	
	private function _init( $chatlv_id ) {
		global $DB;
		$this->_chatlv = $DB->get_record( $this->_tabelaConfiguracao, array('id'=>$chatlv_id),
				'id, course as cursoava, porcentagem, etapa, fator_multiplicativo, exibir' );
	}

	public function contribuicao( Item $item ) {
		return true;
	}

	public function getAvaliacao( Item $item ) {
		if( $item->getAvaliacao() != null )
			return $item->getAvaliacao();
		
		global $DB;
		$avaliacaolv = null;
		
		$avaliacao = $DB->get_record($this->_tabelaNota, array(
			'component'		=> 'mod_' . $item->getAtividade(), 
			'ratingarea'	=> $item->getComponente(),
			'itemid'		=> $item->getItem()->id
		));
		
		if ( $avaliacao ) {
			$avaliacaolv = new AvaliacaoLv();
			$avaliacaolv->setAvaliador( $avaliacao->userid );
			$avaliacaolv->setEstudante( $item->getItem()->userid );
			$avaliacaolv->setDataCriacao( $avaliacao->timecreated );
			$avaliacaolv->setDataModificacao( $avaliacao->timemodified );
			$avaliacaolv->setItem($item);
			$avaliacaolv->setNota( $avaliacao->rating );
			
			$item->setAvaliacao($avaliacaolv);
		}
		
		return $avaliacaolv;
	}
	
	public function getNota( $estudante ) {
		nao_implementado(__CLASS__, __FUNCTION__);
	}
	
	public function podeAvaliar( Item $item ) {
		return true;
	}
	
	public function podeVerNota( Item $item ) {
		return true;
	}
	
	public function removerAvaliacao( $avaliacao ) {
		global $DB;
		$item = $avaliacao->getItem();
		
		$avaliacao_atual = $DB->get_record($this->_tabelaNota, array(
				'component'	 => 'mod_'.$item->getAtividade(),
				'ratingarea' => $item->getComponente(),
				'itemid'	 => $item->getItem()->id
		));
		
		if($avaliacao_atual) {
			$DB->delete_records($this->_tabelaNota, array('id'=>$avaliacao_atual->id));
		}
		
		$this->_avaliarDesempenho($avaliacao->getEstudante());
	}
	
	public function salvarAvaliacao( AvaliacaoLv $avaliacao ) {
		global $DB;
		$avaliacao->setNota( intval($avaliacao->getNota()) );
		
		$nova_avaliacao = new \stdClass();
		$nova_avaliacao->contextid 	= 0;
		$nova_avaliacao->scaleid 	= 0;
		$nova_avaliacao->component 	= 'mod_chatlv';
		$nova_avaliacao->ratingarea	= $avaliacao->getItem()->getComponente();
		$nova_avaliacao->itemid 	= $avaliacao->getItem()->getItem()->id;
		$nova_avaliacao->userid		= $avaliacao->getAvaliador();
		$nova_avaliacao->rating	 	= $avaliacao->getNota();
		
		$avaliacao_atual = $DB->get_record($this->_tabelaNota, array(
				'component'	 => $nova_avaliacao->component,
				'ratingarea' => $nova_avaliacao->ratingarea,
				'itemid'	 => $nova_avaliacao->itemid
		));
		
		if(!$avaliacao_atual) {
			$nova_avaliacao->timecreated = $nova_avaliacao->timemodified = time();
			$DB->insert_record($this->_tabelaNota, $nova_avaliacao);
		} else {
			$nova_avaliacao->id = $avaliacao_atual->id;
			$nova_avaliacao->timemodified = time();
			$DB->update_record($this->_tabelaNota, $nova_avaliacao);
		}
		
		$this->_avaliarDesempenho($avaliacao->getEstudante());
	}
	
	public function removerSessao($inicio, $fim, $group) {
		global $DB;
		$params = array('chatlvid'=>$this->_chatlv->id, 'start'=>$inicio, 'end'=>$fim);
		$messages = $DB->get_records_select('chatlv_messages', "chatlvid = :chatlvid AND timestamp >= :start AND
                                                     timestamp <= :end AND system=0 $groupselect", $params);
		
		if (!empty($messages)) {
			$ids = array_keys($messages);
			$estudantes = array_unique(array_map(function($data) {
				return $data->userid;
			}, $messages));
			
			
			list($mask, $params) = $DB->get_in_or_equal($ids);
			
			$DB->delete_records_select($this->_tabelaNota, "component='mod_chatlv' AND ratingarea='message' AND itemid $mask", $params);
			
			foreach ($estudantes as $estudante) {
				$this->_avaliarDesempenho($estudante);
			}
		}
	}
 	
	/**
	 * 	Avalia o desempenho de um estudante no chatlv
	 *
	 * 	@param int $estudante id do estudante
	 * 	@return float nota
	 * 	@access private
	 * 	@todo criar entidade Chat com topico(discussion) e posts
	 * 	@todo recuperar apenas posts avaliados dentro do prazo definido
	 */
	private function _avaliarDesempenho( $estudante ) {
		global $DB;
		$cm = get_coursemodule_from_instance('chatlv', $this->_chatlv->id);
		$grupoid = 0;
		
		if ($cm->groupmode != 0) {
			$grupos = groups_get_user_groups($cm->course, $estudante); // TODO lançar exceção caso o mesmo estudante pertença a dois grupos!
			$grupoid = reset(reset($grupos));
		}
		
		$fator_multiplicativo = $this->_chatlv->fator_multiplicativo;
		$idcurso = $this->_chatlv->cursoava;

		$mensagens = $DB->get_records('chatlv_messages', array('chatlvid'=>'mod_chatlv', 'chatlvid'=>$this->_chatlv->id, 'system'=>0, 'userid'=>$estudante, 'groupid'=>$grupoid), 'id');
		
		if (!empty($mensagens)) {
			list($mask, $params) = $DB->get_in_or_equal(array_keys($mensagens));
			$mensagens_avaliadas = $DB->get_records_select($this->_tabelaNota, "component='mod_chatlv' AND ratingarea='message' AND itemid $mask", $params, 'itemid');
		}
		
		$desempenho_atual = $DB->get_record($this->_tabelaAvaliacao, array(
				'id_curso' => $this->_chatlv->cursoava,
				'id_chatlv'=> $this->_chatlv->id,
				'id_usuario' => $estudante
		));
		
		if ( empty($mensagens_avaliadas) && !empty($desempenho_atual) ) {
			$DB->delete_records($this->_tabelaAvaliacao, array('id'=>$desempenho_atual->id));
			return 0;
		} else {
			list($I, $carinhas) = $this->_calcularVariacaoAngular($mensagens_avaliadas);
		
			$novo_desempenho = new \stdClass();
			$novo_desempenho->numero_carinhas_azul = $carinhas['azul'];
			$novo_desempenho->numero_carinhas_verde = $carinhas['verde'];
			$novo_desempenho->numero_carinhas_amarela = $carinhas['amarela'];
			$novo_desempenho->numero_carinhas_laranja = $carinhas['laranja'];
			$novo_desempenho->numero_carinhas_vermelha = $carinhas['vermelha'];
			$novo_desempenho->numero_carinhas_preta = $carinhas['preta'];
			$novo_desempenho->modulo_vetor = $this->calcularModuloVetor($I);
			$novo_desempenho->beta = $this->calcularBeta($novo_desempenho->modulo_vetor, $carinhas);
		
			if (empty($desempenho_atual)) {
				$novo_desempenho->id_curso = $this->_chatlv->cursoava;
				$novo_desempenho->id_chatlv = $this->_chatlv->id;
				$novo_desempenho->id_usuario = $estudante;
				$DB->insert_record($this->_tabelaAvaliacao, $novo_desempenho);
			} else {
				$novo_desempenho->id = $desempenho_atual->id;
				$DB->update_record($this->_tabelaAvaliacao, $novo_desempenho);
			}
		}
	
		return $novo_desempenho->modulo_vetor;
	}

	/**
	 * 	Calcula a variação angular por meio das notas obtidas nas avaliações
	 *
	 * 	@param array:\stdClass $avaliacoes
	 * 	@return array [ variacao_angular: int, carinhas: array ]
	 * 	@access private
	 */
	private function _calcularVariacaoAngular($avaliacoes) {
		$I = 0;
		$postagem = 1;
		$m = $this->_chatlv->fator_multiplicativo / 2;
		
		if ($m == .5)
			$npostagem = 6;
		else
			$npostagem = 3;
		
		$carinhas = array('azul'=>0, 'verde'=>0, 'amarela'=>0, 'laranja'=>0, 'vermelha'=>0, 'preta'=>0);
	
		foreach ($avaliacoes as $avaliacao) {
			$coeficiente_passo = $avaliacao->rating;
	
			switch($coeficiente_passo) {
				case EscalaLikert::MUITO_BOM:
					$carinhas['azul']++; break;
				case EscalaLikert::BOM:
					$carinhas['verde']++; break;
				case EscalaLikert::REGULAR:
					$carinhas['amarela']++; break;
				case EscalaLikert::FRACO:
					$carinhas['laranja']++; break;
				case EscalaLikert::NAO_SATISFATORIO:
					$carinhas['vermelha']++; break;
				case EscalaLikert::NEUTRO:
					$carinhas['preta']++;
			}
			
			if ($coeficiente_passo != EscalaLikert::NEUTRO) {
				if ($postagem <= $npostagem) { // Primeira Postagem
					$I += ($m * $coeficiente_passo) * AtividadeLv::ALFA;
				} else {
					$I += ($coeficiente_passo == 0) ? -AtividadeLv::ALFA/2 : AtividadeLv::ALFA/2;
				}
				$postagem++;
			}
		}
	
		$I = $this->limitarAoQuadrante($I);
	
		return array($I, $carinhas);
	}
	
}
?>