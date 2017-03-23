<?php
namespace uab\ifce\lvs\moodle2\avaliacao;

use uab\ifce\lvs\moodle2\business\Chatlv;

use uab\ifce\lvs\Carinhas;
use uab\ifce\lvs\avaliacao\AvaliacaoLv;
use uab\ifce\lvs\avaliacao\NotasLv;
use uab\ifce\lvs\avaliacao\NotasLvFactory;
use uab\ifce\lvs\business\AtividadeLv;
use uab\ifce\lvs\business\Item;
use uab\ifce\lvs\forms\FormsAvaliacaoImpl;
use uab\ifce\lvs\moodle2\business\Moodle2CursoLv;
use uab\ifce\lvs\moodle2\business\Forumlv;
use uab\ifce\lvs\moodle2\business\Wikilv;
use uab\ifce\lvs\util\Cache;

/**
 * 	Gerencia as avaliações dos itens postados no moodle2. A permissão de usuários para ver e avaliar itens.
 *
 *  @category LVs
 *  @package uab\ifce\lvs\moodle2\avaliacao
 *  @author Ricky Paz (rickypaz@gmail.com)
 *  @version SVN $Id
 * 	@see \uab\ifce\lvs\avaliacao\NotasLv 
 */
class Moodle2NotasLv implements NotasLv {
	
	/**
	 * 	Indica se o ajax está ou não habilitado
	 * 	@var bool
	 */
	private $_ajax;
	
	/**
	 * 	@var \uab\ifce\lvs\business\CursoLv
	 */
	private $_cursolv;
	
	private $_cache;
	
	/**
	 * 	Constrói os componentes visuais de exibição de notas e formulários de avaliação lv
	 * 	@var \uab\ifce\lvs\forms\FormsAvaliacao
	 */
	private $_formAvaliacao;
	
	/**
	 * @var \uab\ifce\lvs\business\AtividadeLv
	 */
	private $_modulo;
	
	/**
	 * 	Indica se o arquivo .js deve se carregado inline. Por padrão, é false
	 * 	@var bool
	 */
	private $_inline = false;
	
	public function __construct() {
		$this->_init();
	}
	
	private function _init() {
		global $CFG;
		$this->_formAvaliacao = new FormsAvaliacaoImpl();
		$this->_cache = new Cache('cachelvs.xml');
		$this->_cache->setRoot('itens')->setTagname('item');
	} 
	
	public function avaliacaoAtual( Item $item ) {
		if ($this->podeVerNota($item)) {
			$avaliacao = $this->getAvaliacao($item);
			$nota = $avaliacao->getNota();
			
			if ($avaliacao->isCarinhasEstendido() && $nota !== null) {
				$nota = floatval($nota);
				$avaliacao->setNota($nota);
			}
			
			return $this->_formAvaliacao->avaliacaoAtual($avaliacao);
		}
	}
	
	/**
	 *	Retorna o html de exibição de avaliação likert
	 *
	 * 	@param AvaliacaoLv $avaliacao
	 * 	@return string tag img
	 * 	@access public
	 * 	@todo retirar a variável global!
	 */
	public function avaliacaoAtualCarinha( $avaliacao ) {
		
		$carinhaslvs = new Carinhas();
		$item = $avaliacao->getItem();
		$nota = $avaliacao->getNota();

		if ($this->podeVerNota($item)) {
			return ($nota !== null) ? $carinhaslvs->recuperarCarinhaHtml($nota) : '<b><i>Não avaliado</i></b>';
		}
	}
	
	public function avaliadoPor( Item $item ) {
		global $DB;
		
		if ($this->podeVerNota($item) && $avaliacao = $this->getAvaliacao($item)) {
			$avaliacao = $this->getAvaliacao($item);
			
			if ($avaliador = $avaliacao->getAvaliador()) {
				$avaliador = $DB->get_record('user', array('id'=>$avaliador));
				$avaliador = $avaliador->firstname . ' ' . $avaliador->lastname;
				
				return $this->_formAvaliacao->avaliacaoPor($avaliador);
			}
		}
	}	
 	
	public function criarAtividadeLv( $nome, $id ) {
		if ( $nome == 'chatlv' )
			return new ChatLv($id);
		
		if ( $nome == 'forumlv' )
			return new Forumlv($id);
		
		if ( $nome == 'wikilv' )
			return new Wikilv($id);
		
		throw new \Exception();
	}

	public function formAvaliacao( Item $item ) {
		if ( $this->podeAvaliar($item) ) {
			global $context;
			$avaliacao = $this->getAvaliacao($item);
			$avaliacao->setEstudante($item->getItem()->userid);
			
			$contextid = $context->id;
			$cacheid = $this->_cache->salvarDado($item);
			$sesskey = sesskey();
			
			$returnurl = stripcslashes($_SERVER['REQUEST_URI']) ;
			
			if (!$avaliacao->isCarinhasEstendido())  
				$this->_formAvaliacao->likert($avaliacao);
			else 
				$this->_formAvaliacao->likertEstendido($avaliacao);
			
			$this->_formAvaliacao->adicionarInput("name=cacheid;value=$cacheid");
			$this->_formAvaliacao->adicionarInput("name=contextid;value=$contextid");
			$this->_formAvaliacao->adicionarInput("name=returnurl;value=$returnurl");
			$this->_formAvaliacao->adicionarInput("name=sesskey;value=$sesskey");
			
			$this->_addJs();
			return $this->_formAvaliacao->getForm();
		}
	}
	
	/**
	 * 
	 * 	@todo colocar esse método na interface
	 * 	@todo refatorar esse método juntamente com Moodle2NotasLv::formAvaliacao para unificar código comum
	 */
	public function formAvaliacaoAjax( Item $item ) {
		if ( $this->podeAvaliar($item) ) {
			global $context;
			$avaliacao = $this->getAvaliacao($item);
			$avaliacao->setEstudante($item->getItem()->userid);
				
			$contextid = $context->id;
			$cacheid = $this->_cache->salvarDado($item);
			$sesskey = sesskey();
				
			$returnurl = stripcslashes($_SERVER['REQUEST_URI']) ;
				
			if (!$avaliacao->isCarinhasEstendido())
				$this->_formAvaliacao->likertAjax($avaliacao);
			else
				$this->_formAvaliacao->likertEstendido($avaliacao);
				
			$this->_formAvaliacao->adicionarInput("name=cacheid;value=$cacheid");
			$this->_formAvaliacao->adicionarInput("name=contextid;value=$contextid");
			$this->_formAvaliacao->adicionarInput("name=returnurl;value=$returnurl");
			$this->_formAvaliacao->adicionarInput("name=sesskey;value=$sesskey");
			
			$this->_addJs(true);
			return $this->_formAvaliacao->getForm();
		}
	}

	public function podeAvaliar( Item $item ) {
		global $USER, $cm, $context;

		if (!$context) {
			$context = \context_module::instance($cm->id);
		}

		// verifica se usuário tem permissão para avaliar
		if (!has_capability("mod/$cm->modname:rate", $context))
			return false;

		$contribuicao = $this->_modulo->contribuicao($item);
		$avaliacao 	= $this->getAvaliacao($item);
		$avaliador 	= $avaliacao->getAvaliador();
		$estudante 	= $avaliacao->getEstudante();
		$nota 		= $avaliacao->getNota();
		
		// verifica se o o item a ser avaliado foi criado pelo usuário que deseja avaliá-lo
		if ($contribuicao && $item->getItem()->userid == $USER->id) {
			return false;
		}

		// verifica se o item a ser avaliado já foi avaliado por outro usuário
		if (!empty($nota) && $avaliador != $USER->id) {
			return false;
		}
		
		// itens pertencentes a admin/professores/tutores não possuem notas
		if(has_capability("mod/$cm->modname:viewanyrating", $context, $item->getItem()->userid))
			return false;

		return $this->_modulo->podeAvaliar($item);
	}

	/* 
	 * @see uab\ifce\lvs\avaliacao.NotasLv::podeVerNota()
	 */
	public function podeVerNota( Item $item ) {
		global $cm, $context, $USER;
		$contribuicao = $this->_modulo->contribuicao($item);
		
		if(!isset($context)) 
			$context = \context_module::instance($cm->id);

		if(!has_capability("mod/$cm->modname:viewrating", $context))
			return false;

		// verifica se o item pertence ao usuário logado
		if ($contribuicao && $item->getItem()->userid != $USER->id && !has_capability("mod/$cm->modname:viewanyrating", $context))
			return false;

		// itens pertencentes a admin/professores/tutores não possuem notas
		if(has_capability("mod/$cm->modname:viewanyrating", $context, $item->getItem()->userid))
			return false;

		return $this->_modulo->podeVerNota($item);
	}
	
	/* 
	 * @see uab\ifce\lvs\avaliacao.NotasLv::getAvaliacao()
	 */
	public function getAvaliacao( Item $item ) {
		if( $item->getAvaliacao() )
			return $item->getAvaliacao();
		
		if( $avaliacao = $this->_modulo->getAvaliacao($item) )
			return $avaliacao;

		$avaliacao = new AvaliacaoLv();
		$item->setAvaliacao($avaliacao);
		$avaliacao->setItem($item);
		 
		return $avaliacao;
	}

	/* 
	 * @see uab\ifce\lvs\avaliacao.NotasLv::getAvaliacoes()
	 * @todo recuperar todos de uma vez, e não um por um
	 */
	public function getAvaliacoes( $itens ) {
		$avaliacoes = array();
		
		foreach ($itens as $item) {
			$avaliacao = $this->_modulo->getAvaliacao($item);
			
			if( $avaliacao )
				$avaliacoes[ $item->getItem()->id ] = $avaliacao;
		}
		
		return $avaliacoes;
	}

	/* 
	 * 	@see uab\ifce\lvs\avaliacao.NotasLv::removerAvaliacao()
	 * 	@todo salvar no sistema de notas do moodle 2
	 */
	public function removerAvaliacao( AvaliacaoLv $avaliacao ) {
		$this->_modulo->removerAvaliacao($avaliacao);

		$estudantes = array( $avaliacao->getEstudante() );
		$this->_cursolv->atualizarCurso($estudantes);
		
		// $notas = $this->_modulo->getDesempenho($avaliacao->getEstudante());
		// $this->_salvarEmGrades($avaliacao);
	}

	/*
	 * 	@see uab\ifce\lvs\avaliacao.NotasLv::salvarAvaliacao()
	 * 	@todo salvar no sistema de notas do moodle 2
	 */
	public function salvarAvaliacao( AvaliacaoLv $avaliacao ) {
		$this->_modulo->salvarAvaliacao($avaliacao);
		
		if ($avaliacao->getEstudante() != 0) {
			$estudantes = array($avaliacao->getEstudante());
			$this->_cursolv->atualizarCurso($estudantes);
		} else {
			$this->_cursolv->atualizarCurso();
		}

		// $notas = $this->_modulo->getDesempenho($avaliacao->getEstudante());
		// $this->_salvarEmGrades($avaliacao);
	}
	
	public function setCursoLv(Moodle2CursoLv $cursolv) {
		$this->_cursolv = $cursolv;
	}
	
	public function setModulo(AtividadeLv $modulo) {
		$this->_modulo = $modulo;
	}
	
	private function _addJs($ajaxForm=false) {
		global $PAGE;
		static $done = false;
		
        if ($done) {
        	$this->_ajax = true;
        	return true;
        }
        
        if ($ajaxForm) {
        	$this->enabledAjax = true;
        }
        
        $PAGE->requires->js_init_call('M.block_lvs.ratinglvs', array(LVS_WWWROOT2, $ajaxForm));
        
        $done = true;
	}
	
	private function _recuperarCursoLv($course_id) {
		if($this->_cursolv == null)
			$this->_cursolv = new CursoLv($course_id);
			
		return $this->_cursolv;
	}
	
	private function _salvarEmGrades( $avaliacao ) {
		trigger_error("Implement " . __FUNCTION__);
	}
	
	/**
	 * 	Salva/atualiza a nota na tabela de notas lvs
	 * 	@param avalicao::AvaliacaoLv $avaliacao
	 */
	private function _salvarNotasLv( $avaliacao ) {
		global $DB;
		
		$novaAvaliacao = new \stdClass();
		$novaAvaliacao->mod 			= $avaliacao->getItem()->getNomeAtividade();
		$novaAvaliacao->componente 		= $avaliacao->getItem()->getTipo();
		$novaAvaliacao->componente_id 	= $avaliacao->getItem()->getItem()->id;
		$novaAvaliacao->avaliador	 	= $avaliacao->getAvaliador();
		$novaAvaliacao->estudante	 	= $avaliacao->getEstudante();
		$novaAvaliacao->nota	 		= $avaliacao->getNota();
		
		$condicoes = array(
				'mod'=>$novaAvaliacao->mod,
				'componente'=>$novaAvaliacao->componente,
				'componente_id'=>$novaAvaliacao->componente_id,
				'estudante'=>$novaAvaliacao->estudante,
		);
		$avaliacaoAtual = $DB->get_record('lvs_notaslv', $condicoes);
		
		if(!$avaliacaoAtual) {
			$novaAvaliacao->data_criacao = time();
			$DB->insert_record('lvs_notaslv', $novaAvaliacao);
		} else {
			$novaAvaliacao->id = $avaliacaoAtual->id;
			$novaAvaliacao->data_modificacao = time();
			$DB->update_record('lvs_notaslv', $novaAvaliacao);
		}
	}
	
}
?>
