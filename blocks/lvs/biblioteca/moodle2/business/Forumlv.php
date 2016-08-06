<?php
namespace uab\ifce\lvs\moodle2\business;

use uab\ifce\lvs\EscalaLikert;
use uab\ifce\lvs\avaliacao\AvaliacaoLv;
use uab\ifce\lvs\business\AtividadeLv;
use uab\ifce\lvs\business\CursoLv;
use uab\ifce\lvs\business\Item;

/**
 * 	Avalia o desempenho de estudantes no Fórum LV
 * 	
 * 	@category LVs
 * 	@package uab\ifce\lvs\moodle2\business
 * 	@author Allyson Bonetti
 * 	@author Ricky Paz (rickypaz@gmail.com)
 * 	@version SVN $Id
 * 	@todo criar camada de persistência
 */
class Forumlv extends AtividadeLv {

	/**
	 * 	Contém as configurações do fórum lv
	 * 	@var \stdClass
	 * 	@access private
	 */
	private $_forumlv;

	/**
	 * 	Nome da tabela do banco de dados que possui a avaliação lv dos estudantes avaliados
	 * 	@var string
	 */
	private $_tabelaAvaliacao = 'lvs_forumlv';
	
	/**
	 * 	Nome da tabela do banco de dados que possui a configuração lv de todos os fórunslvs
	 * 	@var string
	 */
	private $_tabelaConfiguracao = 'forumlv';
	
	/**
	 * 	Nome da tabela do banco de dados que possui todas as notas lvs dadas
	 * 	@var string
	 */
	private $_tabelaNota = 'rating';
	
	public static $fatorMultiplicativo = array(1=>10, 2=>6, 3=>2, 6=>1);
	
	public static function getNumeroMinimodeMensagens( $fator_multiplicativo ) {
		return Forumlv::$fatorMultiplicativo[$fator_multiplicativo];
	}
	
	
	/**
	 * 	Instancia Forumlv
	 * 	@param int $forumlv_id id do fórumlv
	 */
	public function __construct( $forumlv_id ) {
		$this->_init($forumlv_id);
	}
	
	private function _init( $forumlv_id ) {
		global $DB;
		$this->_forumlv = $DB->get_record( $this->_tabelaConfiguracao, array('id'=>$forumlv_id), 
				'id, course as cursoava, porcentagem, etapa, fator_multiplicativo, assesstimestart as inicio_periodo_avaliacao, 
				assesstimefinish as fim_periodo_avaliacao, exibir' );
	}
	
	/**
	 * 	Avalia o desempenho dos estudantes
	 * 
	 * 	@param array $estudantes ids dos estudantes a serem avaliados
	 * 	@access public
	 */
	public function avaliarDesempenhoGeral( $estudantes ) {
		if (!empty($estudantes)) { 
			foreach ($estudantes as $estudante ) { 
				$this->_avaliarDesempenho($estudante);
			}
		}
	}

	public function contribuicao( Item $item ) {
		return true;
	}
	
	public function getAtividade($atividade_id) {
		nao_implementado(__CLASS__, __FUNCTION__);
	}
	
	/*
	 * @see uab\ifce\lvs\business.AtividadeLv::getAvaliacao()
	 */
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
	
	
	/* 
	 * 	@see uab\ifce\lvs\business.AtividadeLv::getNota()
	 * 	@todo usar sistema de cache
	 */
	public function getNota( $estudante ) {
		global $DB;
		
		return $DB->get_field($this->_tabelaAvaliacao, 'modulo_vetor', array('id_forumlv'=>$this->_forumlv->id, 'id_usuario'=>$estudante));
	}
	
	public function podeAvaliar( Item $item ) {
		if (!empty($this->_forumlv->inicio_periodo_avaliacao) && !empty($this->_forumlv->fim_periodo_avaliacao)) {
			$data_criacao_item = $item->getItem()->created;
		
			if( $data_criacao_item < $this->_forumlv->inicio_periodo_avaliacao || $data_criacao_item > $this->_forumlv->fim_periodo_avaliacao ) {
				return false;
			}
		}
		
		return true;
	}
	
	public function podeVerNota( Item $item ) {
		if (!empty($this->_forumlv->inicio_periodo_avaliacao) && !empty($this->_forumlv->fim_periodo_avaliacao)) {
			$data_criacao_item = $item->getItem()->created;
		
			if( $data_criacao_item < $this->_forumlv->inicio_periodo_avaliacao || $data_criacao_item > $this->_forumlv->fim_periodo_avaliacao ) {
				return false;
			}
		}
		
		return true;
	}
	
	/*
	 * 	@see uab\ifce\lvs\business.AtividadeLv::salvarAvaliacao()
	 */
	public function salvarAvaliacao( AvaliacaoLv $avaliacao ) {
		global $DB;
		$avaliacao->setNota( intval($avaliacao->getNota()) );
		
		$nova_avaliacao = new \stdClass();
		$nova_avaliacao->contextid 	= 0;
		$nova_avaliacao->scaleid 	= 0;
		$nova_avaliacao->component 	= 'mod_forumlv';
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
	
	/*
	 * @see uab\ifce\lvs\business.AtividadeLv::removerAvaliacao()
	 */
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
	
	/**
	 * 	Avalia o desempenho de um estudante no fórumlv
	 * 	
	 * 	@param int $estudante id do estudante
	 * 	@return float nota
	 * 	@access private
	 * 	@todo criar entidade Forum com topico(discussion) e posts
	 * 	@todo recuperar apenas posts avaliados dentro do prazo definido
	 */
	private function _avaliarDesempenho( $estudante ) {


		global $DB;
		$cm = get_coursemodule_from_instance('forumlv', $this->_forumlv->id);
		$posts = $posts_avaliados = array();

		$discussions = forumlv_get_discussions($cm);

		$discussion = reset($discussions);

		 /*if ($this->_forumlv->inicio_periodo_avaliacao && $this->_forumlv->fim_periodo_avaliacao) {
			 $posts = $DB->get_records_select('forumlv_posts', 'discussion=? AND created >= ? AND created <= ?',
				 array( $discussion->discussion, $this->_forumlv->inicio_periodo_avaliacao, $this->_forumlv->fim_periodo_avaliacao),
			 'created', 'id'); 
		 } else {*/
			$posts = $DB->get_records('forumlv_posts', array('discussion'=>$discussion->discussion, 'userid'=>$estudante), 'created ASC', 'id');
//		}
		
		// print_object($posts);
		// exit;


		
		if ( !empty($posts) ) {
			list($mask, $params) = $DB->get_in_or_equal(array_keys($posts));		
			$posts_avaliados = $DB->get_records_select($this->_tabelaNota, "component='mod_forumlv' AND ratingarea='post' AND itemid $mask", $params, 'itemid');
		}

		$desempenho_atual = $DB->get_record($this->_tabelaAvaliacao, array(
				'id_curso' => $this->_forumlv->cursoava,
				'id_forumlv'=> $this->_forumlv->id,
				'id_usuario' => $estudante
		));

		
		if ( empty($posts_avaliados) && !empty($desempenho_atual) ) {
			$DB->delete_records($this->_tabelaAvaliacao, array('id'=>$desempenho_atual->id));
			return 0;
		} else {
			list($I, $carinhas) = $this->_calcularVariacaoAngular($posts_avaliados);
	
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
				$novo_desempenho->id_curso = $this->_forumlv->cursoava;
				$novo_desempenho->id_forumlv = $this->_forumlv->id;
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
		$m = $this->_forumlv->fator_multiplicativo / 2;
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
				if ($postagem == 1 || $postagem == 2) { // Primeira Postagem ou Segunda Postagem
					$I += ($m * $coeficiente_passo) * AtividadeLv::ALFA;
				} else {
					$I += ($coeficiente_passo < 2) ? -AtividadeLv::ALFA : AtividadeLv::ALFA;
				} 
				$postagem++;
			}
		}
	
		$I = $this->limitarAoQuadrante($I);

		return array($I, $carinhas);
	}
	
}
?>
