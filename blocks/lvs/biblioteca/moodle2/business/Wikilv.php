<?php
namespace uab\ifce\lvs\moodle2\business;

use uab\ifce\lvs\EscalaLikert;
use uab\ifce\lvs\avaliacao\AvaliacaoLv;
use uab\ifce\lvs\business\Item;
use uab\ifce\lvs\business\AtividadeLv;

/**
 * 	Avalia o desempenho de estudantes no Wiki LV
 * 
 * 	No Moodle2, um wikilv é representado por um ou mais subwikilvs, com cada subwikilv representando um wikilv com divisões de grupos 
 * 	visíveis, não-visíveis ou sem grupos.
 *  
 * 	@category LVs
 * 	@package uab\ifce\lvs\moodle2\business
 * 	@author Ricky Paz (rickypaz@gmail.com)
 * 	@version SVN $Id
 * 	@todo criar camada de persistência
 */
class Wikilv extends AtividadeLv {
	
	/**
	 * 	Nome da tabela do banco de dados que possui a configuração lv de todos os wikilvs
	 * 	@var string
	 */
	private $_tabelaConfiguracao = 'wikilv';
	
	/**
	 * 	Nome da tabela do banco de dados que possui a avaliação lv dos estudantes avaliados
	 * 	@var string
	 */
	private $_tabelaAvaliacao = 'lvs_wikilv';
	
	/**
	 * 	Nome da tabela do banco de dados que possui todas as notas lvs dadas
	 * 	@var string
	 */
	private $_tabelaNota = 'lvs_notaslv';
	
	private $_wikilv;
	
	public function __construct( $wikilv_id ) {
		$this->_init($wikilv_id);
	}
	
	private function _init( $wikilv_id ) {
		global $DB;
		$this->_wikilv = $DB->get_record( $this->_tabelaConfiguracao, array('id'=>$wikilv_id) );
	}
	
	/**
	 * 	@return boolean
	 * 	@todo alguém usa esse método?!
	 */
	public function carinhasEstendido( ) {
		return $this->_wikilv->fator_multiplicativo == 3;
	}
	
	public function contribuicao(Item $item) {
		$tipo = $item->getTipo();

		if ($tipo == 'version' || $tipo == 'file' || $tipo == 'comment') 
			return true;
		
		return false;
	}
	
	public function getAvaliacao( Item $item ) {
		if( $item->getAvaliacao() != null )
			return $item->getAvaliacao();
		
		global $DB;
		$avaliacaolv = null;
		
		$avaliacao = $DB->get_record($this->_tabelaNota, array(
			'modulo'		=> $item->getAtividade(),
			'componente'	=> $item->getComponente(),
			'componente_id'	=> $item->getItem()->id
		));
		
		if ( $avaliacao ) {
			$avaliacaolv = new AvaliacaoLv();
			$avaliacaolv->setAvaliador( $avaliacao->avaliador );
			$avaliacaolv->setEstudante( $item->getItem()->userid );
			$avaliacaolv->setDataCriacao( $avaliacao->data_criacao );
			$avaliacaolv->setDataModificacao( $avaliacao->data_modificacao );
			$avaliacaolv->setItem($item);
			$avaliacaolv->setNota( $avaliacao->nota );
				
			$item->setAvaliacao($avaliacaolv);
		}
		
		return $avaliacaolv;
	}
	
	public function getNota( $estudante ) {
		nao_implementado(__CLASS__, __FUNCTION__);
	}
	
	public function podeAvaliar(Item $item) {
		$contribuicao = $this->contribuicao($item);
		
		// verifica se foi definido o período de avaliação
		if(!empty($this->_wikilv->assesstimestart) && !empty($this->_wikilv->assesstimefinish)) {
		 
			if($contribuicao) { // se for uma versão ou um arquivo enviado, só pode ser avaliado se enviado dentro do prazo de avaliação
				$data_criacao_item = $item->getItem()->timecreated;
				
				if( $data_criacao_item < $this->_wikilv->assesstimestart || $data_criacao_item > $this->_wikilv->assesstimefinish ) {
					return false;
				}
			} else if( time() <= $this->_wikilv->assesstimefinish ) { // um wiki só pode ser avaliado após o período de avaliação de versões
				return false; 
			}
		}
		
		if($contribuicao && $this->_wikilv->fator_multiplicativo == 3)
			return false;
		
		return true;
	}
	
	public function podeVerNota(Item $item) {
		$contribuicao = $this->contribuicao($item);
	
		// verifica se foi definido o período de avaliação
		if(!empty($this->_wikilv->assesstimestart) && !empty($this->_wikilv->assesstimefinish )) {
			if($contribuicao) {
				$data_criacao_item = $item->getItem()->timecreated;
				
				if( $data_criacao_item < $this->_wikilv->assesstimestart || $data_criacao_item > $this->_wikilv->assesstimefinish )
					return false;
			} else if (time() <= $this->_wikilv->assesstimefinish ) {
				return false;
			}
		}
		
		if($contribuicao && $this->_wikilv->fator_multiplicativo == 3)
			return false;
		
		return true;
	}
	
	public function recalcularNotas( ) {
		global $DB;
		$estudantes = $DB->get_records($this->_tabelaAvaliacao, array('id_wikilv'=>$this->_wikilv->id), '', 'id_usuario');
		
		foreach ($estudantes as $estudante) {
			$this->_avaliarDesempenho($estudante->id_usuario);
		}
		
		return array_keys($estudantes);
	}
	
	public function removerAvaliacao( $avaliacao ) {
		global $DB;
		$item = $avaliacao->getItem();
		
		$avaliacao_atual = $DB->get_record($this->_tabelaNota, array(
			'modulo'	  	=> $item->getAtividade(),
			'componente' 	=> $item->getComponente(),
			'componente_id' => $item->getItem()->id
		));
		
		if($avaliacao_atual) {
			$DB->delete_records($this->_tabelaNota, array('id'=>$avaliacao_atual->id));
		}
		
		if ($this->contribuicao($avaliacao->getItem()))
			$this->_avaliarDesempenho($avaliacao->getEstudante());
		else
			$this->_avaliarDesempenhoGeral($avaliacao->getItem()->getItem()->id);
	}
	
	public function salvarAvaliacao( AvaliacaoLv $avaliacao ) {
		global $DB;
		
		$nova_avaliacao = new \stdClass();
		$nova_avaliacao->modulo 		= 'wikilv';
		$nova_avaliacao->modulo_id 		= $this->_wikilv->id;
		$nova_avaliacao->componente		= $avaliacao->getItem()->getComponente();
		$nova_avaliacao->componente_id 	= $avaliacao->getItem()->getItem()->id;
		$nova_avaliacao->avaliador		= $avaliacao->getAvaliador();
		$nova_avaliacao->estudante 		= $avaliacao->getEstudante();
		$nova_avaliacao->nota	 		= $avaliacao->getNota();
		
		$avaliacao_atual = $DB->get_record($this->_tabelaNota, array(
			'modulo'	 	=> $nova_avaliacao->modulo,
			'componente'	=> $nova_avaliacao->componente,
			'componente_id'	=> $nova_avaliacao->componente_id
		));
		
		if(!$avaliacao_atual) {
			$nova_avaliacao->data_criacao = $nova_avaliacao->data_modificacao = time();
			$DB->insert_record($this->_tabelaNota, $nova_avaliacao);
		} else {
			$nova_avaliacao->id = $avaliacao_atual->id;
			$nova_avaliacao->data_modificacao = time();
			$DB->update_record($this->_tabelaNota, $nova_avaliacao);
		}
		
		if ($this->contribuicao($avaliacao->getItem())) {
			$avaliacao->setNota(intval($avaliacao->getNota()));
			$this->_avaliarDesempenho($avaliacao->getEstudante());
		}else 
			$this->_avaliarDesempenhoGeral($avaliacao->getItem()->getItem()->id);
	}
	
	/**
	 * 	Avalia o desempenho de um estudante
	 * 
	 * 	@param int $estudante id do estudante
	 * 	@return float nota
	 * 	@return float módulo do vetor LVx
	 * 	@access private
	 */
	private function _avaliarDesempenho( $estudante ) {
		global $DB;
		list($curso, $subwikilv) = $this->_getCursoAvaESubwikilv($estudante);
		$contribuicoes = wikilv_get_contributions($subwikilv->id, $estudante);
		
		// @todo lvs pegar arquivos e coment�rios como contribui��es
		
		$nota = $this->_notaProdutoFinal($subwikilv->id);
		$carinhas = array('azul'=>0, 'verde'=>0, 'amarela'=>0, 'laranja'=>0, 'vermelha'=>0, 'preta'=>0);
		
		$desempenho_atual = $DB->get_record($this->_tabelaAvaliacao, array(
				'id_curso'	=> $curso,
				'id_wikilv' => $this->_wikilv->id,
				'id_usuario'=> $estudante
		));
		
		if (empty($contribuicoes) && $nota == 0) {
			if (!empty($desempenho_atual))
				$DB->delete_records($this->_tabelaAvaliacao, array('id'=>$desempenho_atual->id));
		
			return 0;
		}
		
		$novo_desempenho = new \stdClass();
				
		if ($this->_wikilv->fator_multiplicativo == 3) {
			$likert = EscalaLikert::parseLikertEstendido($nota);
			$likert = EscalaLikert::parseInt($likert);
			
			switch($likert) {
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
					$carinhas['preta']++; break;
			}
			
			$novo_desempenho->modulo_vetor 		= $nota;
			$novo_desempenho->modulo_vetor_pf 	= $nota;
		} else {
			$avaliacoes = $this->_getAvaliacoes($contribuicoes);
			
			if (empty($avaliacoes) && $subwikilv->groupid == 0) {
				if (!empty($desempenho_atual)) 
					$DB->delete_records($this->_tabelaAvaliacao, array('id'=>$desempenho_atual->id));
				
				return 0;
			}
			 
			$I = $this->_wikilv->fator_multiplicativo * $nota * AtividadeLv::ALFA;
			$IPF = $I; // servirá para o cálculo da nota final apenas com a nota de produto final
		
			list($I, $carinhas) = $this->_calcularVariacaoAngular($avaliacoes, $I);
		
			$novo_desempenho->modulo_vetor = $this->calcularModuloVetor($I);
			$novo_desempenho->modulo_vetor_pf = $this->calcularModuloVetor($IPF);
		}
		
		
		
		$novo_desempenho->numero_carinhas_azul 		= $carinhas['azul'];
		$novo_desempenho->numero_carinhas_verde 	= $carinhas['verde'];
		$novo_desempenho->numero_carinhas_amarela 	= $carinhas['amarela'];
		$novo_desempenho->numero_carinhas_laranja 	= $carinhas['laranja'];
		$novo_desempenho->numero_carinhas_vermelha 	= $carinhas['vermelha'];
		$novo_desempenho->numero_carinhas_preta 	= $carinhas['preta'];
		$novo_desempenho->beta = $this->calcularBeta( $novo_desempenho->modulo_vetor, $carinhas );
		
		if (empty($desempenho_atual)) {
			$novo_desempenho->id_curso = $curso;
			$novo_desempenho->id_wikilv = $this->_wikilv->id;
			$novo_desempenho->id_usuario = $estudante;
			$DB->insert_record($this->_tabelaAvaliacao, $novo_desempenho);
		} else {
			$novo_desempenho->id = $desempenho_atual->id;
			$DB->update_record($this->_tabelaAvaliacao, $novo_desempenho);
		}
		
		return $novo_desempenho->modulo_vetor;
	}
	
	/**
	 * 	Avalia o desempenho de todos os estudantes pertencentes ao subwikilv
	 *
	 * 	@param int $subwikilv_id id do subwikilv
	 * 	@access private
	 * 	@todo no caso de não estar no modo grupos, estudantes que não possuirem participação não devem receber a nota 
	 */
	public function _avaliarDesempenhoGeral($subwikilv_id) {
		$notas = array();
		$subwikilv = wikilv_get_subwikilv($subwikilv_id);
		$wikilv = wikilv_get_wikilv($subwikilv->wikilvid);
	
		// roleid=5 para estudantes
		if ($subwikilv->groupid == 0) {
			$context = \context_course::instance($wikilv->course);
			$estudantes = get_role_users(5, $context,  'u.id'); // 5 para estudantes
		} else {
			$participantes = groups_get_members_by_role($subwikilv->groupid, $wikilv->course, 'u.id');
			$estudantes = $participantes[5]->users;
		}
		
		foreach($estudantes as $estudante) {
			$nota_final = $this->_avaliarDesempenho($estudante->id);
		}
	}

	private function _calcularVariacaoAngular($avaliacoes, $I) {
		$carinhas = array('azul'=>0, 'verde'=>0, 'amarela'=>0, 'laranja'=>0, 'vermelha'=>0, 'preta'=>0);
		
		foreach ($avaliacoes as $avaliacao) {
			$coeficiente_passo = $avaliacao->nota;
		
			if( $coeficiente_passo == EscalaLikert::MUITO_BOM ) {
				$carinhas['azul']++;
				$I += ( 0.5 * $coeficiente_passo * AtividadeLv::ALFA );
			} else if( $coeficiente_passo == EscalaLikert::BOM ) {
				$carinhas['verde']++;
				$I += ( 0.5 * $coeficiente_passo * AtividadeLv::ALFA );
			} else if( $coeficiente_passo == EscalaLikert::REGULAR ) {
				$carinhas['amarela']++;
				$I += ( 0.5 * $coeficiente_passo * AtividadeLv::ALFA );
			} else if( $coeficiente_passo == EscalaLikert::FRACO ) {
				$carinhas['laranja']++;
				$I += ( 0.5 * $coeficiente_passo * AtividadeLv::ALFA );
			} else if( $coeficiente_passo == EscalaLikert::NAO_SATISFATORIO ) {
				$carinhas['vermelha']++;
				$I -= AtividadeLv::ALFA;
			} else {
				$carinhas['preta']++;
			}
		}
		
		$I = $this->limitarAoQuadrante($I);
		
		return array($I, $carinhas);
	}
	
	/**
	 * 	Retorna todas as avaliações de versões criadas dentro do prazo de avaliação
	 *
	 * 	@param array:\stdClass $contribuicoes
	 * 	@return array:\stdClass
	 * 	@access private
	 */
	private function _getAvaliacoes($contribuicoes) {
		global $DB;
		$avaliacoes = array();
		
		if ($this->_wikilv->assesstimestart && $this->_wikilv->assesstimefinish)
		{
			foreach ($contribuicoes as $id => $contribuicao)
			{
				if ($contribuicao->timecreated < $this->_wikilv->assesstimestart ||
						$contribuicao->timecreated > $this->_wikilv->assesstimefinish)
					unset($contribuicoes[$id]);
			}
		}

		if(!empty($contribuicoes)) 
		{
			$ids = array_keys($contribuicoes);
			list($mask, $params) = $DB->get_in_or_equal($ids, SQL_PARAMS_QM);
			$sql = "modulo = 'wikilv' AND componente = 'version' AND componente_id $mask";
			
			$avaliacoes = $DB->get_records_select($this->_tabelaNota, $sql, $params);
		}
	
		return $avaliacoes;
	}
	
	/**
	 * 	Retorna o curso no ava e subwikilv a qual o estudante pertence
	 * 	
	 * 	@param int $estudante id do estudante
	 * 	@return array:\stdClass [course, subwikilv] 
	 * 	@access private 
	 */
	private function _getCursoAvaESubwikilv( $estudante ) {
		$cm = get_coursemodule_from_instance('wikilv', $this->_wikilv->id);
		
		if( $cm->groupmode == 0 ) {
			$subwikilv = wikilv_get_subwikilv_by_group($this->_wikilv->id, 0);
		} else {
			$grupos = groups_get_user_groups($cm->course, $estudante); // TODO lançar exceção caso o mesmo estudante pertença a dois grupos!
			$grupoid = reset(reset($grupos));
			$subwikilv = wikilv_get_subwikilv_by_group($this->_wikilv->id, $grupoid);
		}
	
		return array($cm->course, $subwikilv);
	}
	
	/**
	 * 	Retorna a nota de um subwikilv
	 *  
	 * 	@param int $subwikilv_id id do subwikilv
	 * 	@return int likert
	 * 	@access private
	 */
	private function _notaProdutoFinal($subwikilv_id) {
		global $DB;
		
		$produto_final = $DB->get_field($this->_tabelaNota, 'nota', array(
			'modulo'=>'wikilv',	
			'componente'=>'subwikilv',
			'componente_id'=>$subwikilv_id
		));
		
		if(empty($produto_final) || $produto_final == EscalaLikert::NEUTRO)
			$produto_final = 0;
		
		return $produto_final;
	}
	
}
?>