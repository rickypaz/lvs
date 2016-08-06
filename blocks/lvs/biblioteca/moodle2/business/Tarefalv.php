<?php
namespace uab\ifce\lvs\moodle2\business;

use uab\ifce\lvs\EscalaLikert;

use uab\ifce\lvs\avaliacao\AvaliacaoLv;

use uab\ifce\lvs\business\Item;

use uab\ifce\lvs\business\AtividadeLv;

/**
 * 	Avalia o desempenho de estudantes na Tarefa LV
 * 
 * 	@category LVs
 * 	@package uab\ifce\lvs\moodle2\business
 * 	@author Allyson Bonetti
 * 	@author Ricky Paz (rickypaz@gmail.com)
 * 	@version SVN $Id
 * 	@todo criar camada de persistência
 */
class Tarefalv extends AtividadeLv {

	/**
	 * 	Nome da tabela do banco de dados que possui a avaliação lv dos estudantes avaliados
	 * 	@var string
	 */
	private $_tabelaAvaliacao = 'lvs_tarefalv';
	
	/**
	 * 	Nome da tabela do banco de dados que possui a configuração lv de todas as tarefaslvs
	 * 	@var string
	 */
	private $_tabelaConfiguracao = 'tarefalv';
	
	/**
	 * 	Nome da tabela do banco de dados que possui todas as notas lvs dadas
	 * 	@var string
	 */
	private $_tabelaNota = 'tarefalv_submissions';
	
	/**
	 * 	Contém as configurações da tarefalv
	 * 	@var \stdClass
	 * 	@access private
	 */
	private $_tarefalv;

	public function __construct( $tarefalv_id ) {
		$this->_init($tarefalv_id);
	}
	
	private function _init( $tarefalv_id ) {
		global $DB;
		$this->_tarefalv = $DB->get_record( $this->_tabelaConfiguracao, array('id'=>$tarefalv_id), 'id, course as cursoava, porcentagem, etapa, exibir' );
	}
	
	public function contribuicao( Item $item ) {
		return true;
	}
	
	public function getAvaliacao( Item $item ) {
		if( $item->getAvaliacao() != null )
			return $item->getAvaliacao();
		
		global $DB;
		$avaliacaolv = null;
		
		$avaliacao = $DB->get_record_select($this->_tabelaNota, 'id = ? AND grade is not null', array($item->getItem()->id), 'id, userid as estudante, teacher as avaliador, grade as nota');
		
		if ( $avaliacao ) {
			$avaliacaolv = new AvaliacaoLv();
			$avaliacaolv->setAvaliador( $avaliacao->avaliador );
			$avaliacaolv->setEstudante( $avaliacao->estudante );
			$avaliacaolv->setItem($item);
			$avaliacaolv->setNota( $avaliacao->nota );
			
			$item->setAvaliacao($avaliacaolv);
		}
		
		return $avaliacaolv;
	}
	
	public function getNota( $estudante ) {
		global $DB;
		
		return $DB->get_field($this->_tabelaAvaliacao, 'modulo_vetor', array('id_tarefalv'=>$this->_tarefalv->id, 'id_usuario'=>$estudante));
	}
	
	public function podeAvaliar( Item $item ) {
		if (!empty($this->_tarefalv->inicio_periodo_avaliacao) && !empty($this->_tarefalv->fim_periodo_avaliacao)) {
			$data_criacao_item = $item->getItem()->created;
		
			if( $data_criacao_item < $this->_tarefalv->inicio_periodo_avaliacao || $data_criacao_item > $this->_tarefalv->fim_periodo_avaliacao ) {
				return false;
			}
		}
		
		return true;
	}
	
	public function podeVerNota( Item $item ) {
		if (!empty($this->_tarefalv->inicio_periodo_avaliacao) && !empty($this->_tarefalv->fim_periodo_avaliacao)) {
			$data_criacao_item = $item->getItem()->created;
		
			if( $data_criacao_item < $this->_tarefalv->inicio_periodo_avaliacao || $data_criacao_item > $this->_tarefalv->fim_periodo_avaliacao ) {
				return false;
			}
		}
		
		return true;
	}
	
	/*
	 * 	@see uab\ifce\lvs\business.AtividadeLv::removerAvaliacao()
	 * 	@todo remover nota quando mudar a submission?	
	 */
	public function removerAvaliacao( $avaliacao ) {
		nao_implementado(__CLASS__, __FUNCTION__);
	}
	
	/* 
	 * 	@see uab\ifce\lvs\business.AtividadeLv::salvarAvaliacao()
	 * 	@todo lançar exceção caso a nota da nova avaliação seja menor que a nota atual
	 * 	@todo transforma a tabela 'rating' em $this->tabelaNota 
	 */
	public function salvarAvaliacao( AvaliacaoLv $avaliacao ) {
		$this->_avaliarDesempenho($avaliacao->getEstudante());		
	}

	/**
	 * 	Avalia o desempenho de um estudante na tarefalv
	 *
	 * 	@param int $estudante id do estudante
	 * 	@return float nota 
	 * 	@access private
	 * 	@todo criar entidade Tarefa com postagem(submission)
	 */
	private function _avaliarDesempenho( $estudante ) {
		global $DB;
		$LVx = 0;
		$escala = EscalaLikert::getEscalaEstendido();
		$carinhas = array('azul'=>0, 'verde'=>0, 'amarela'=>0, 'laranja'=>0, 'vermelha'=>0, 'preta'=>0);
	

		
		$submissions = $DB->get_records('tarefalv_submissions', array('tarefalv'=>$this->_tarefalv->id, 'userid'=>$estudante), 'id DESC');
	
		$desempenho_atual = $DB->get_record('lvs_tarefalv', array(
				'id_curso'=>$this->_tarefalv->cursoava,
				'id_tarefalv'=>$this->_tarefalv->id,
				'id_usuario'=>$estudante
		));
			
		if(empty($submissions) && !empty($desempenho_atual)) {
			$DB->delete_records($this->_tabelaAvaliacao, array('id'=>$desempenho_atual->id));
			return 0;
		} else {
			foreach ($submissions as $submission) {
				$coeficiente_passo = EscalaLikert::parseLikertEstendido($submission->grade);
				$coeficiente_passo = EscalaLikert::parseInt($coeficiente_passo);
					
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
						$carinhas['preta']++; break;
				}
	
				if ($submission->grade > $LVx)
					$LVx = $submission->grade;
			}
				
			$novo_desempenho = new \stdClass();
			$novo_desempenho->numero_carinhas_azul = $carinhas['azul'];
			$novo_desempenho->numero_carinhas_verde = $carinhas['verde'];
			$novo_desempenho->numero_carinhas_amarela = $carinhas['amarela'];
			$novo_desempenho->numero_carinhas_laranja = $carinhas['laranja'];
			$novo_desempenho->numero_carinhas_vermelha = $carinhas['vermelha'];
			$novo_desempenho->numero_carinhas_preta = $carinhas['preta'];
			$novo_desempenho->modulo_vetor = $LVx;
			$novo_desempenho->beta = $this->calcularBeta($LVx, $carinhas);
			
			if (empty($desempenho_atual)) {
				$novo_desempenho->id_curso = $this->_tarefalv->cursoava;
				$novo_desempenho->id_tarefalv = $this->_tarefalv->id;
				$novo_desempenho->id_usuario = $estudante;
				$DB->insert_record($this->_tabelaAvaliacao, $novo_desempenho);
			} else {
				$novo_desempenho->id = $desempenho_atual->id;
				$DB->update_record($this->_tabelaAvaliacao, $novo_desempenho);
			}
		}
	
		return $novo_desempenho->modulo_vetor;
	}

}
?>