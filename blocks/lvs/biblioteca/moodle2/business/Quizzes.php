<?php
namespace uab\ifce\lvs\moodle2\business;

use uab\ifce\lvs\EscalaLikert;
use uab\ifce\lvs\business\AtividadesPresenciais;
use uab\ifce\lvs\business\GerenciadorAtividades;
use uab\ifce\lvs\business\GerenciadorAtividadesDistancia;
use uab\ifce\lvs\util\Convert;

/**
 *  Gerencia os quizzes existentes permitindo sua importação ao sistema de notas lvs.
 *  
 *  @category LVs
 *	@package uab\ifce\lvs\moodle2\business
 * 	@author Ricky Persivo (rickypaz@gmail.com)
 * 	@version 1.0
 * 	@todo utilizar injeção de dependência
 * 	@todo criar camada de acesso a dados
 * 	@todo utilizar quizzesDAO
 * 	@todo trabalhar com objetos em vez de array
 */
class Quizzes extends GerenciadorAtividadesDistancia implements GerenciadorAtividades {

	const NOME = 'Quiz LV';
	
	private static $PRESENCIAL = 0;
	private static $DISTANCIA = 1;
	 
	/**
	 * 	Responsável por importar quizzes como atividades presenciais
	 * 	@var AtividadesPresenciais
	 */
	private $_gerenciadorPresenciais;
	
	/**
	 * 	Nome da tabela do banco de dados que possui a avaliação de todos os estudantes nos quizzes a distância
	 * 	@var string
	 */
	private $_tabelaAvaliacao;
	
	/**
	 * 	Nome da tabela do banco de dados que possui a configuração de todas os quizzes a distância
	 * 	@var string
	 */
	private $_tabelaConfiguracao;
	
	public function __construct($cursolv) {

		$this->_cursolv = $cursolv;
		$this->_tabelaAvaliacao = 'lvs_quizlv';
		$this->_tabelaConfiguracao = 'quizlv';
		$this->_gerenciadorPresenciais = new AtividadesPresenciais($cursolv);

		//$this->_removerQuizzesExcluidos();
		$this->atualizarQuizzes();
	}
	
	/**
	 * 	Importa as notas dos estudantes no quizlv para os LVs
	 * 
	 * 	@param array:\stdClass $quizzes lista de quizzes que terão suas notas importadas
	 * 	@access public
	 */
	public function atualizarNotas( $quizzes ) {
		foreach ($quizzes as $quiz) {
			if ($quiz->distancia == Quizzes::$DISTANCIA) {
				$this->_importarComoDistancia($quiz);
			} else {
				$this->_importarComoPresencial($quiz);
			}
		}
	}
	
	/**
	 *	Atualiza as notas de todos os quizzes desatualizados  
	 */
	public function atualizarQuizzes( ) {
		global $DB;
		$configuracao = $this->_cursolv->getConfiguracao();
		
		if (isset($configuracao) && !empty($configuracao) ) {		
			$curso_id = $configuracao->id_curso;
			
			$sql = 'SELECT grades.id as grade_id, quiz_importado.id as id, quiz_importado.id_curso, quiz_importado.id_quiz, 
						quiz_importado.id_atividade, quiz_importado.distancia, grades.userid as estudante
					FROM {quiz} AS quiz
						INNER JOIN {quizlv} AS quiz_importado
					 		ON quiz.id = quiz_importado.id_quiz
					 	INNER JOIN {quiz_grades} AS grades
					 		ON quiz.id = grades.quiz 
					WHERE quiz.course = ? AND grades.timemodified > quiz_importado.ultima_importacao
					ORDER BY quiz.id asc';
			$params = array($curso_id);
			
			$quizzes = $DB->get_records_sql($sql, $params);
			
			foreach ($quizzes as $quiz) 
			{
				if ($quiz->distancia == Quizzes::$PRESENCIAL) 
				{
					$quiz->presencial = $DB->get_record('lvs_atv_presencial', array('id'=>$quiz->id_atividade));
				}
			}
	
			if (!empty($quizzes)) 
			{
				$estudantes = array();
				foreach ($quizzes as $quiz) {
					$estudantes[] = $quiz->estudante;
				}
				
				$this->importarQuizzes($quizzes, array_unique($estudantes));
			}
		}
	}
	
	public function importarAlunos() {
		global $DB;
		$quizzes_presenciais = $this->recuperarQuizzesPresenciaisImportados();
		$importados = $estudantes = array();
		
		foreach ($quizzes_presenciais as $quiz) {
			$sql = "SELECT u.id
					FROM {role_assignments} r, {user} u 
					WHERE r.contextid = 15 AND u.id = r.userid AND r.roleid =5 and r.userid NOT IN (
						select n.id_avaliado
						from {quizlv} q
							inner join {lvs_atv_presencial} p on p.id = q.id_atividade and p.id_curso = q.id_curso and q.distancia = 0
							inner join {lvs_nota_presencial} n on n.id_atividade = p.id
						where q.id_curso = ? and q.id = ?)";
			$params = array($this->_cursolv->getConfiguracao()->id_curso, $quiz->id);
			
			$estudantes = array_keys($DB->get_records_sql($sql, $params));
			$importados = array_merge($importados, array_unique($estudantes));
		}
		
		$this->importarQuizzes($quizzes_presenciais, array_unique($estudantes));
	}
	
	/**
	 *	Retorna o total de ausências de um estudante nos quizzes a distância
	 *
	 *	@param int $estudante id do estudante
	 * 	@return int número de faltas
	 * 	@access public
	 */
	public function numeroFaltas( $estudante ) {
		global $DB;
		$quizzes = $this->recuperarAtividades();
 		$faltas = 0;
 		
		foreach ($quizzes as $quiz) {
			$possui_avaliacao = $DB->record_exists($this->_tabelaAvaliacao, array(
				'id_quizlv'=>$quiz->id, 'id_usuario'=>$estudante
			));

			if(!$possui_avaliacao)
				$faltas++;
		}
	
		return $faltas;
	}
	
	/**
	 *	Calcula a soma das porcentangens a distancia de todos os quizzes a distância do curso
	 *
	 *  @return float
	 *  @access public
	 */
	public function porcentagemDistancia() {
		global $DB;
		$curso_id = $curso_id = $this->_cursolv->getConfiguracao()->id_curso;
	
		$sql = 'SELECT SUM(porcentagem) as total FROM {quizlv_configuracao} as config INNER JOIN {quizlv} as q ON q.id = config.quizlvid
		WHERE q.id_curso = ?';
		$soma = $DB->get_record_sql($sql, array($curso_id));
	
		return $soma->total;
	}
	
	/**
	 * 	Verifica se há algum quizlv cuja porcentagem não tenha sido definida
	 *
	 * 	@return boolean true, caso haja, false, caso contrário
	 */
	public function porcentagemNula() {
		global $DB;
		$curso_id = $curso_id = $this->_cursolv->getConfiguracao()->id_curso;
	
		$sql = 'SELECT COUNT(*) FROM {quizlv_configuracao} as config INNER JOIN {quizlv} as q ON q.id = config.quizlvid
		WHERE q.id_curso = ? AND config.porcentagem is NULL';
	
		return $DB->count_records_sql($sql, array($curso_id)) > 0;
	}
	
	/**
	 * 	Retorna o número de quizzes a distância do curso
	 *
	 * 	@return int número de quizzes
	 * 	@access public
	 */
	public function quantidadeAtividades() {
		global $DB;
		$curso_id = $this->_cursolv->getConfiguracao()->id_curso;
	
		return $DB->count_records('quizlv', array('id_curso'=>$curso_id, 'distancia'=>Quizzes::$DISTANCIA));
	}
	
	/**
	 *	Retorna todos os quizzes a distancia
	 *
	 * 	@return array:\stdClass
	 * 	@access public
	 */
	public function recuperarAtividades( ) {
		global $DB;
		$curso_id = $this->_cursolv->getConfiguracao()->id_curso;
		
		$sql = "SELECT c.*, q.*, nome as name FROM {quizlv} q 
				INNER JOIN {quizlv_configuracao} c ON q.id = c.quizlvid
				WHERE q.id_curso = ? AND q.distancia = ?";
		
		return $DB->get_records_sql($sql, array($curso_id, Quizzes::$DISTANCIA));
	}
	
	/**
	 *	Retorna o desempenho de um estudante em cada quizlv
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
	 *
	 * 	@param int $estudante id do estudante
	 * 	@return stdClass { avaliacoes: array, notaFinal: float, numeroFaltas: int, numeroAtividades: int, carinhasAzuis: int,
	 * 		carinhasVerdes: int, carinhasAmarelas: int, carinhasLaranjas: int, carinhasVermelhas: int, carinhasPretas: int}
	 * 	@access public
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
			$atividade->avaliacaolv = $DB->get_record('lvs_quizlv', array('id_quizlv'=>$atividade->id, 'id_usuario'=>$estudante));
		}
	
		return $atividades;
	}
	
	/**
	 *	Remove os quizzes importados
	 *
	 * 	@param array:\stdClass $quizzes objeto ou array de quizzes a remover
	 * 	@access public
	 */
	public function removerAtividade( $quizzes ) {
		global $DB;

		if (!is_array($quizzes))
			$quizzes = array($quizzes);

		$ids = array_map( function($data) {
			$data = (object) $data;
			return $data->id;
		}, $quizzes);
					
		list($mask, $params) = $DB->get_in_or_equal($ids, SQL_PARAMS_QM);

		foreach ($quizzes as $quiz) {
			if (is_array($quiz))
				$quiz = Convert::array_to_object($quiz);

			if ($quiz->distancia == 0) {
				$this->_gerenciadorPresenciais->removerAtividadePorId($quiz->id_atividade);
			} else {
				$this->_removerQuizDistancia($quiz->id);
			}
			
		}
		
		$DB->delete_records_select($this->_tabelaConfiguracao, "id $mask", $params);
	}

	/**
	 * 	Remove um quiz presencial importado dada a atividade presencial
	 * 
	 * 	@param int $presencial id da atividade presencial
	 * 	@throws \Exception se não houver um quiz importado que utilize a atividade presencial 
	 */
	public function removerQuizImportadoPorPresencial( $presencial ) {
		debug('removerQuizImportadoPorPresencial');
		global $DB;
		$quiz = $DB->get_record($this->_tabelaConfiguracao, array('id_atividade'=>$presencial), 'id', MUST_EXIST);
		debug($quiz);
		$this->removerAtividade($quiz);
	}
	
	/**
	 *	Remove todos os quizzes importados, presenciais ou a distância
	 */
	public function removerTodasAtividades( ) {
		global $DB;
		$curso_id = $this->_cursolv->getConfiguracao()->id_curso;
		$quizzes = $DB->get_records($this->_tabelaConfiguracao, array('id_curso'=>$curso_id));
		
		if( !empty($quizzes) ) {
			$this->removerAtividade($quizzes);
		}
	}
	
	/**
	 * 	Alias para Quizzes::removerAtividade(...)
	 * 	
	 * 	@access public
	 * 	@see Quizzes::removerAtividade( $quizzes )
	 */
	public function removerQuizzes( $quizzes ) {
		$this->removerAtividade($quizzes);
	}
	
	/**
	 * 	Retorna a configuração lv de um ou mais quizzes
	 *
	 * 	@param mixed $quizzes um quizlv ou uma array de quizzeslvs a distancia
	 * 	@return array:\stdClass
	 * 	@access public
	 */
	public function recuperarConfiguracao( $quizzes ) {
		global $DB;
		$configuracoes = array();
	
		if(!is_array($quizzes)) {
			return $DB->get_record('quizlv_configuracao', array('quizlvid'=>$quizzes->id));
		}
	
		foreach ($quizzes as $quiz) {
			$configuracoes[$quiz->id] = $quiz;
			$configuracoes[$quiz->id]->cm  = get_coursemodule_from_instance('quiz', $quiz->id_quiz)->id;
		}
		
		return $configuracoes;
	}
	
	/**
	 *	Armazena ou atualiza as configurações lvs de cada quizlv tais como porcentagem, etapa, exibição de notas
	 *
	 * 	@param mixed $quizzes
	 * 	@access public
	 */
	public function salvarConfiguracao( $quizzes ) {
		global $DB;
		
		if(!is_array($quizzes)) {
			$quizzes = array($quizzes);
		}
		
		foreach ($quizzes as $configuracao) {
			$configuracao = (object) $configuracao;
			$configuracao->exibir = (isset($configuracao->exibir)) ? 1 : 0;
			
			if ($configuracao_id = $DB->get_field('quizlv_configuracao', 'id', array('quizlvid'=>$configuracao->id))) 
			{
				$configuracao->id = $configuracao_id; 
				$DB->update_record('quizlv_configuracao', $configuracao);
			} 
			else 
			{
				$DB->insert_record('quizlv_configuracao', $configuracao);
			}
		}
	}
	
	/**
	 * 	Importa as notas dos quizzes para o sistema de notas lvs. Armazena os quizzes e suas notas nas tabelas presencial e/ou distância	
	 * 
	 * 	@param mixed $quizzes uma array ou um objeto { id: int, id_curso: int, id_quiz: int, distancia: [0|1] } 
	 * 	@param array $estudantes ids dos estudantes que terão suas notas atualizadas. Se não informado, todos os estudantes terão suas notas
	 * 	atualizadas 
	 * 	@access public
	 * 	@todo chamar cálculo de porcentagem de atividades somente se necessário
	 */
	public function importarQuizzes( $quizzes, $estudantes = array() ) {
		if (!is_array($quizzes)) 
			$quizzes = array($quizzes);

		foreach ($quizzes as &$quiz) 
		{
			$quiz = (is_object($quiz)) ? $quiz : Convert::array_to_object($quiz); // FIXME receber todos os dados como object e não como array
			
			if ($quiz->distancia == Quizzes::$DISTANCIA) 
			{
				$this->_criarQuizLv($quiz);
				$this->_importarComoDistancia($quiz);
			} 
			else 
			{
				$presencial = $this->_criarAtividadePresencial($quiz);
				$quiz->id_atividade = $presencial->id;
				$quiz->nome = $quiz->presencial->nome;
				$quiz->descricao = $quiz->presencial->descricao;
				$quiz->presencial = $presencial;
				
				$this->_criarQuizLv($quiz);
				$this->_importarComoPresencial($quiz);
			}
		}
		
		if (!empty($quizzes)) 
		{
			$this->_cursolv->atualizarCurso($estudantes);
		}
		
		$this->getCursoLv()->calcularPorcentagemAtividades();
	}
	
	/**
	 * 	Retorna todos os quizzes aptos à importação. O prazo de encerramento deve estar finalizado ou definido, o nome do quiz deve 
	 * 	terminar com (DISTANCIA) ou (PRESENCIAL) e a nota máxima dever ser 10
	 *
	 * 	@return caso seja uma quiz a distância, array:\stdClass { id: int, id_quiz: int, nome: string, descricao: string, distancia: true }
	 * 	se presencial, array:\stdClass { id: int, id_quiz: int, nome: string, descricao: string, presencial: { id: int, nome: string, descricao: string } }
	 * 	@access public
	 */
	public function recuperarQuizzesDisponiveis() {
		global $DB;
		$curso_id = $this->_cursolv->getConfiguracao()->id_curso;
	
		$sql = 'SELECT quiz.id as id_quiz, quiz.name as nome, quiz.intro as descricao FROM {quiz} AS quiz 
				LEFT JOIN {' . $this->_tabelaConfiguracao . "} AS quiz_importado ON quiz.id = quiz_importado.id_quiz 
				WHERE quiz.course = ? AND quiz_importado.id is NULL 
						AND quiz.grade = 10 
				ORDER BY quiz.name"; // AND quiz.timeclose < ?
		$params = array($curso_id, time());
		
		return $DB->get_records_sql($sql, $params);
	}
	
	/**
	 * 	Retorna todos os quizzes importados como atividade a distância
	 * 
	 * 	@return array:\stdClass { id: int, id_quiz: int, nome: string, atualizado: [t|f] }
	 * 	@access public
	 */
	public function recuperarQuizzesDistanciaImportados() {
		return $this->_recuperarQuizzesImportados(true);
	}
	
	/**
	 * 	Retorna todos os quizzes importados como atividade presencial
	 * 
	 * 	@return array:\stdClass { id: int, id_quiz: int, nome: string, atualizado: [t|f] }
	 * 	@access public
	 */
	public function recuperarQuizzesPresenciaisImportados() {
		return $this->_recuperarQuizzesImportados();
	}
	
	/**
	 * 	Calcula o fator ß dado o módulo do vetor e a quantidade de carinhas recebidas na atividade
	 *
	 * 	@param float $LVx módulo do vetor
	 * 	@param array $carinhas número de carinhas por cor {azul: int, verde: int, amarela: int, laranja: int, vermelha: int, preta: int}
	 * 	@return float beta
	 * 	@access private
	 */
	private function _calcularBeta($LVx, $carinhas) {
		$positividade = $LVx + 3 * $carinhas['azul'] + 2 * $carinhas['verde'] + $carinhas['amarela'];
		$negatividade = sqrt(100 - pow($LVx, 2)) + $carinhas['laranja'] + 2 * $carinhas['vermelha'];
	
		if ($negatividade == 0)
			$negatividade = 1;
	
		return round(($positividade / $negatividade), 2);
	}
	
	/**
	 * 	Calcula a nota final nos quizzes lvs por meio da soma das notas de cada quizlv. A soma das notas é ponderada por meio da porcentagem
	 * 	de cada quizlv.
	 *
	 * 	@param array $avaliacoes quizzes avaliados
	 * 	@return float soma ponderada das notas
	 * 	@todo recuperar todas as porcentagens de uma só vez
	 */
	private function _calcularNotaPonderada($avaliacoes) {
		global $DB;
		$somatorio = 0;
	
		if (!empty($avaliacoes)) {
			foreach ($avaliacoes as $avaliacao) {
				$porcentagem = $DB->get_field('quizlv_configuracao', 'porcentagem', array('quizlvid'=>$avaliacao->id_quizlv));
				$somatorio += $avaliacao->modulo_vetor * $porcentagem / 100;
			}
		}
	
		return round($somatorio, 2);
	}
	
	/**
	 * 	Converte a nota obtida no quiz à primeira nota igual ou acima na escala estendida likert
	 * 
	 * 	@param float $nota
	 * 	@return float
	 * 	@todo renomar método
	 */
	private function _converterNota($nota) {
		$escala = EscalaLikert::getEscalaEstendido();
		$escala = array_reverse($escala);
		
		foreach ($escala as $valor) {
			if($valor >= $nota)
				return $valor;
		}
	}
	
	/**
	 * 	Cria e salva uma atividade presencial no curso
	 * 
	 * 	@param \stdClass $quizlv um objeto referente a um quizlv
	 * 	@return \stdClass um atividade presencial com o id
	 */
	private function _criarAtividadePresencial($quizlv) {
		$presencial = new \stdClass();
		$presencial = $quizlv->presencial;
		$presencial->id = $quizlv->id_atividade;
		$presencial->id_curso = $quizlv->id_curso;
		
		$atividades = $this->_gerenciadorPresenciais->salvarAtividades($presencial);

		return reset($atividades);
	}
	
	/**
	 * 	Cria um quizlv, caso não possua id, ou atualiza seus dados
	 * 
	 * 	@param \stdClass $quizlv
	 * 	@access private
	 */
	private function _criarQuizLv($quizlv) {
		global $DB; 
		$quizlv->ultima_importacao = time();
		
		if ($quizlv->id) {
			$quiz_atual = $DB->get_record($this->_tabelaConfiguracao, array('id'=>$quizlv->id));
			
			if ($quiz_atual->distancia != $quizlv->distancia) {
				$this->removerAtividade($quiz_atual);
				
				if ($quizlv->distancia == Quizzes::$DISTANCIA) {
					unset($quizlv->id_atividade);
				}
				unset($quizlv->id);
			}
		}

		if( !isset($quizlv->id) || empty($quizlv->id) )
			$quizlv->id = $DB->insert_record($this->_tabelaConfiguracao, $quizlv);
		else
			$DB->update_record($this->_tabelaConfiguracao, $quizlv);
	}
	
	/**
	 * 	Dado um quizlv a distancia, importa as notas do quiz nos LVs
	 * 
	 * 	@param \stdClass $quizlv
	 * 	@access private 
	 * 	@todo lançar exceção caso nenhum quiz tenha sido avaliado
	 */
	private function _importarComoDistancia( $quizlv ) {
		global $DB;
		$avaliacoes = $this->_recuperarQuizzesRespondidos($quizlv);
		
		if(!empty($avaliacoes)) {
			foreach ($avaliacoes as $avaliacao) {
				$desempenho = new \stdClass();
				$carinhas = array('azul'=>0, 'verde'=>0, 'amarela'=>0, 'laranja'=>0, 'vermelha'=>0, 'preta'=>0);
				
				$nota   = $this->_converterNota($avaliacao->nota);	
				$likert = EscalaLikert::parseLikertEstendido($nota);
				$likert = EscalaLikert::parseInt($likert);
				
				if($likert == EscalaLikert::MUITO_BOM)
					$carinhas['azul'] = 1;
				else if($likert == EscalaLikert::BOM)
					$carinhas['verde'] = 1;
				else if($likert == EscalaLikert::REGULAR)
					$carinhas['amarela'] = 1;
				else if($likert == EscalaLikert::FRACO)
					$carinhas['laranja'] = 1;
				else if($likert == EscalaLikert::NAO_SATISFATORIO)
					$carinhas['vermelha'] = 1;
				else if($likert == EscalaLikert::NEUTRO)
					$carinhas['preta'] = 1;
				
				$desempenho->id_curso   	 			= $quizlv->id_curso;
				$desempenho->id_quizlv  	 			= $quizlv->id;
				$desempenho->id_usuario 	 			= $avaliacao->estudante;
				$desempenho->numero_carinhas_azul 		= $carinhas['azul'];
				$desempenho->numero_carinhas_verde 		= $carinhas['verde'];
				$desempenho->numero_carinhas_amarela 	= $carinhas['amarela']; 
				$desempenho->numero_carinhas_laranja 	= $carinhas['laranja']; 
				$desempenho->numero_carinhas_vermelha 	= $carinhas['vermelha']; 
				$desempenho->numero_carinhas_preta 		= $carinhas['preta'];
				$desempenho->modulo_vetor 	 			= $nota;
				$desempenho->beta 						= $this->_calcularBeta( $desempenho->modulo_vetor, $carinhas );
				
				try {
					$desempenho->id = $DB->get_field($this->_tabelaAvaliacao, 'id', array(
						'id_quizlv'=>$quizlv->id, 
						'id_usuario'=>$avaliacao->estudante
					), MUST_EXIST);
					$DB->update_record($this->_tabelaAvaliacao, $desempenho);
				} catch(\Exception $exception) {
					$DB->insert_record($this->_tabelaAvaliacao, $desempenho);
				}
			}
		}
		
		$configuracao = new \stdClass();
		$configuracao->id = $DB->get_field('quizlv_configuracao', 'id', array('quizlvid'=>$quizlv->id), IGNORE_MISSING);
		$configuracao->quizlvid = $quizlv->id;
		$configuracao->etapa = $configuracao->exibir = 1;
		
		if ($configuracao->id)
			$DB->update_record('quizlv_configuracao', $configuracao);
		else 
			$DB->insert_record('quizlv_configuracao', $configuracao);
	}
	
	/**
	 * 	Dado um quizlv presencial, cria uma atividade presencial e importa as notas do quiz na atividade
	 * 
	 * 	@param \stdClass $quizlv
	 * 	@access private 
	 * 	@todo lançar exceção caso nenhum quiz tenha sido avaliado
	 */
	private function _importarComoPresencial($quizlv) {
		$avaliacoes 		 = array();
		$quizzes_avaliados 	 = $this->_recuperarQuizzesRespondidos($quizlv);
		$estudantes_faltosos = $this->_cursolv->getEstudantes();
		
		if(!empty($quizzes_avaliados)) {
			foreach ($quizzes_avaliados as $quiz) {
				unset($estudantes_faltosos[$quiz->estudante]);
				
				$avaliacao = new \stdClass();
				$avaliacao->id_atividade = $quizlv->presencial->id;
				$avaliacao->id_avaliado = $quiz->estudante;
				$avaliacao->nota = round($quiz->nota,1);
				$avaliacao->nr_faltas = $avaliacao->faltou_prova = 0;
				
				$avaliacoes[] = $avaliacao;
			}
			
			foreach($estudantes_faltosos as $estudante) {
				$avaliacao = new \stdClass();
				$avaliacao->id_atividade = $quizlv->presencial->id;
				$avaliacao->id_avaliado = $estudante->id;
				$avaliacao->nr_faltas = $quizlv->presencial->max_faltas;
				$avaliacao->nota = null;
				$avaliacao->faltou_prova = 1;
				
				$avaliacoes[] = $avaliacao;
			}
			
			$this->_gerenciadorPresenciais->salvarAvaliacoes($avaliacoes);
		}
	}
	
	private function _recuperarQuizzesImportados($distancia = false) {
		global $DB;
		$curso_id = $this->_cursolv->getConfiguracao()->id_curso;
		
		$sql = 'SELECT quiz_importado.id, quiz_importado.id_quiz, quiz_importado.nome, quiz_importado.id_atividade, 
					 quiz_importado.id_curso, distancia, MAX(grades.timemodified) <= quiz_importado.ultima_importacao as atualizado
				FROM {quiz} AS quiz
				INNER JOIN {' . $this->_tabelaConfiguracao . '} AS quiz_importado 
					ON quiz.id = quiz_importado.id_quiz
				LEFT JOIN {quiz_grades} AS grades 
					ON quiz.id = grades.quiz
				WHERE quiz.course = ? AND quiz_importado.distancia = ?
				GROUP BY quiz_importado.id, quiz_importado.id_quiz, quiz_importado.nome,
					quiz_importado.id_curso, quiz_importado.distancia, 
					quiz_importado.ultima_importacao, quiz_importado.id_atividade
				ORDER BY quiz_importado.nome';
		$params = array($curso_id, $distancia);
		
		return $DB->get_records_sql($sql, $params);
	}
	
	/**
	 * 	Retorna todas as notas no quiz
	 *   
	 * 	@param stdClass $quiz
	 * 	@return array:\stdClass
	 */
	private function _recuperarQuizzesRespondidos($quizlv) {
		global $DB;
	
		$sql = 'SELECT grades.id, quiz.id as quiz, grades.userid as estudante, grades.grade as nota 
				FROM {quiz} as quiz 
				INNER JOIN {quiz_grades} AS grades ON quiz.id = grades.quiz 
				WHERE quiz.course = ? AND quiz.id = ?';
		$params = array( $quizlv->id_curso, $quizlv->id_quiz );
	
		return $DB->get_records_sql($sql, $params);
	}
	
	private function _removerQuizDistancia( $quiz )
	{
		global $DB;
		$DB->delete_records('quizlv_configuracao', array('quizlvid'=>$quiz));
		$DB->delete_records($this->_tabelaAvaliacao, array('id_quizlv'=>$quiz));
	}
	
	/**
	 *	Verifica se algum quiz pertencente ao curso foi removido. Caso tenha sido e o mesmo tenha sido previamente
	 *	importado, a importação é desfeita
	 *
	 * 	@access private
	 */
	private function _removerQuizzesExcluidos() {
		global $DB;
		$configuracao = $this->_cursolv->getConfiguracao();

		if (isset($configuracao) && !empty($configuracao) ) {
			$curso_id = $configuracao->id_curso;
			$quizzes2 = $DB->get_records_select('quiz',"course = ?", array($curso_id), '', 'id');
				
			if(!empty($quizzes2)) {
				$ids = array_keys($quizzes2);
				list($mask, $params) = $DB->get_in_or_equal($ids, SQL_PARAMS_QM, '', false);
			
				$quizzes = $DB->get_records_select('quizlv', "id_quiz $mask", $params);
				
				//@ script remover quiz errado. Quiz orfão no qual a atividade foi excluída.
				/*global $USER;
				if($USER->id == 2787){ 
					//print_object($quizzes2);
					$quiz_a_remover = $DB->get_records_select('quizlv', "id_quiz = 393");
					print_object($quiz_a_remover);
					$this->removerAtividade($quiz_a_remover);
					$this->_cursolv->atualizarCurso();
					exit;
				}*/

				$this->_cursolv->atualizarCurso();
				
				if(!empty($quizzes)) {
					$this->removerAtividade($quizzes);
					$this->_cursolv->atualizarCurso();
				}
			} else {

				$this->removerTodasAtividades();
				$this->_cursolv->atualizarCurso();
			}
		}
	}

}
?>
