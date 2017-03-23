<?php 
namespace uab\ifce\lvs\moodle2\business;

use uab\ifce\lvs\business\AtividadesPresenciais;
use uab\ifce\lvs\business\CursoLv as CursoModelo;

/**
 * 	class CursoLv
 *
 */
class CursoLv extends CursoModelo
{

	/**
	 *
	 * @access private
	 */
	private $course;
	
	/**
	 * 	Cache para armazenamento do desempenho dos participantes do curso
	 * 
	 * 	@var array
	 */
	private $_desempenhoParticipantes = array();
	
	private $_presenciais;
	
	/**
	 *
	 * 	@param int course_id
	 *	@access public
	 */
	public function __construct( $course_id ) {
		$this->_init($course_id);
	}
	
	private function _init($course_id) {
		global $DB;
		
		$this->course = $DB->get_record('course', array('id'=>$course_id));
		$this->configuracao = $this->getConfiguracao();
		$this->_presenciais = new AtividadesPresenciais($this);
		
		$this->adicionarAtividade('forumlv', new ForunsLv($this));
		$this->adicionarAtividade('tarefalv', new TarefasLv($this));
		$this->adicionarAtividade('wikilv', new WikisLv($this));
	}
	
	/**
	 *	Altera a flag de atualização da tabela lvs_tabela_curso indicando que as notas lvs de todos os alunos
	 *	do curso devem ser recalculadas
	 *
	 *	@param [opcional] array $estudantes id dos estudantes que devem ter as notas recalculadas
	 */
	public function atualizarCurso($estudantes = array()) {
		global $DB;
		$sql = "UPDATE {lvs_tabela_curso} SET atualiza=1 WHERE id_curso=?";
		$params = array($this->course->id);
		
		if(!empty($estudantes)) {
			list($mask, $paramsin) = $DB->get_in_or_equal($estudantes, SQL_PARAMS_QM);
			$sql .= " AND id_usuario $mask";
			$params = array_merge($params, $paramsin);
		}
		
		$DB->execute($sql, $params);
	}

	/**
	 * 	Calcula e retorna o fator beta do curso
	 * 
	 * 	@return float fator beta do curso
	 */
	public function betaMedio() {
		global $DB;

		$this->removerNotasDeUsuariosExcluidos();

		$beta_total = $DB->get_records('lvs_tabela_curso', array('id_curso'=>$this->course->id), '', 'SUM(beta) as beta, COUNT(beta) as total_users');
		$betaMedio = current($beta_total)->beta;
		$users_curso = current($beta_total)->total_users;

		if($users_curso != 0)
			return round($betaMedio/$users_curso, 2);

		return 0;
	}

	/**
	 * 	Retorna o course do moodle correspondente ao CursoLV
	 * 
	 * 	@param int $course_id id do course no moodle
	 * 	@return stdClass course
	 */
	private function getCourse($course_id) {
		global $DB;
		return $DB->get_record('course', array('id'=>$course_id));
	}

	public function getConfiguracao() {
		global $DB;

		if($this->configuracao == NULL) {
			return $DB->get_record('lvs_config_curso', array('id_curso'=>$this->course->id));
		}

		return $this->configuracao;
	}

	/* 
	 * (non-PHPdoc)
	 * 
	 * @see uab\ifce\lvs\business.CursoLv::avaliarDesempenho()
	 */
	public function avaliarDesempenho($estudante) {
		global $DB;
		$modulos = $this->recuperarAtividades();
		
		$desempenho = new \stdClass();
		$desempenho->usuario->id = $estudante;
		$desempenho->numeroAtividades = $desempenho->numeroFaltas = 0;
		
		foreach($modulos as $nomeAtividade => $atividade) {
			$desempenho->$nomeAtividade 	= $atividade->recuperarDesempenho($estudante);
			$desempenho->numeroAtividades  += $desempenho->$nomeAtividade->numeroAtividades;
			$desempenho->numeroFaltas	   += $desempenho->$nomeAtividade->numeroFaltas;
		}
		
		$desempenho->presencial = $this->_presenciais->recuperarDesempenho($estudante);
		
		$desempenho->horasFaltadas 		= $this->calcularFrequencia($desempenho);
		$desempenho->percentualFaltas 	= round(($desempenho->horasFaltadas / $this->configuracao->total_horas_curso) * 100, 2);
		$desempenho->notaDistancia 		= $this->calcularNotaDistancia($desempenho);
		$desempenho->notaPresencial 	= $this->calcularNotaPresencial($desempenho);
		$desempenho->mediaParcial 		= $this->calcularMediaParcial($desempenho);
		$desempenho->beta 				= $this->calcularBeta($desempenho);

		if($desempenho->mediaParcial < $this->configuracao->media_curso) {
			$nota_af = $DB->get_record('lvs_avaliacao_final', array('id_curso'=>$this->course->id, 'id_avaliado'=>$estudante));
			$desempenho->notaAF = (!empty($nota_af)) ? round($nota_af->nota, 1) : NULL;

			if(isset($desempenho->notaAF))
				$desempenho->mediaFinal = round(($desempenho->mediaParcial + $desempenho->notaAF) / 2, 1);
		}
		
		// é de view??
		$desempenho->situacao = $this->analisarSituacao($desempenho);
		$desempenho->lvicone = $this->obterCarinha($desempenho);
		
// 		// 		$this->salvarGrade($desempenho); FIXME descomentar
		return $this->salvarDesempenho($desempenho);
	}
	
	public function totalAtividades() {
		return 0;
	}

	/* 	
	 * 	(non-PHPdoc)
	 * 
	 * 	@see uab\ifce\lvs\business.CursoLv::recuperarDesempenho()
	 */
	public function recuperarDesempenho($estudante) {
		global $DB;
		$avaliacao = $DB->get_record('lvs_tabela_curso', array('id_curso'=>$this->course->id, 'id_usuario'=>$estudante));

		if (empty($avaliacao) || $avaliacao->atualiza == 1)
			return $this->avaliarDesempenho($estudante);
			
		return $avaliacao;
	}

	/**
	 * 	Calcula a soma das porcentagens de todas as atividades a distância do curso
	 * 
	 * 	@return float soma das porcentagens
	 */
	public function porcentagemDistancia() {
		global $DB;
		$somatorio = 0;
		$modulos = $this->recuperarAtividades();

		foreach ($modulos as $modulo) {
			$somatorio += $modulo->porcentagemDistancia();
		}
		
		return $somatorio;
	}

	/**
	 * 	@param stdClass $desempenho
	 *	@todo transferir para um objeto apropriado de imagens  
	 */
	public function obterCarinha($desempenho) {
		global $CFG;
	
		if (strrpos($desempenho->situacao, "SC") !== FALSE) {
			return '2cham.gif';
		}
		
		if (( $desempenho->percentualFaltas > $this->configuracao->percentual_faltas && $desempenho->beta != 0 && $this->configuracao->exibelv == 1)) {
			return 'vermelha.gif';
		}
			
		if ($desempenho->beta == 0) {
			return 'cinza.gif';
		}
			
		if ($desempenho->beta >= 3.78) {
			return 'azul.gif';
		}
			
		if ($desempenho->beta >= 2.62) {
			return 'verde.gif';
		}
			
		if ($desempenho->beta >= 0.9) {
			return 'amarela.gif';
		}
		
		if ($desempenho->beta >= 0.3) {
			return 'laranja.gif';
		}
		
		return 'vermelha.gif';
	}

	/**
	 * 	Caso a soma das porcentagens das atividades lvs seja menor que 100 ou uma atividade lv não possua porcentagem
	 * 	definida, a porcentagens de todas as atividades são calculadas e distribuídas uniformemente
	 * 
	 * 	@access public
	 */
	public function calcularPorcentagemAtividades() {
		global $DB;
		$porcentagem_nula = $this->_tiverPorcentagemNula();
		$soma_porcentagens_distancia = $this->porcentagemDistancia();
		$modulos = $this->recuperarAtividades();
		
		if($soma_porcentagens_distancia < 100 || $porcentagem_nula ) {
			$total_atividades = 0;
			$atividades_por_modulo = array();
			
			foreach ($modulos as $nomeModulo => $modulo) {
				$atividades[$nomeModulo] = $modulo->recuperarAtividades();
				$total_atividades += count($atividades[$nomeModulo]);
			}
			
			if($total_atividades > 0) {
				$porcentagem_a_distribuir = ( $soma_porcentagens_distancia < 100 ) ? 100-$soma_porcentagens_distancia : 100;
				$porcentagem_por_atividade = floor( $porcentagem_a_distribuir/$total_atividades );
				$porcentagem_restante = ($resto = $porcentagem_a_distribuir % $total_atividades) ? array_fill(1, $resto, 1) : array();

				foreach ($modulos as $nomeModulo => $modulo) {
					$configuracoes = $modulo->recuperarConfiguracao( $atividades[$nomeModulo] );
					
					foreach($configuracoes as $configuracao) {
						$configuracao->porcentagem = ($porcentagem_nula) ? $porcentagem_por_atividade + array_pop($porcentagem_restante) :
						$configuracao->porcentagem + $porcentagem_por_atividade + array_pop($porcentagem_restante);
					}
					
					$modulo->salvarConfiguracao($configuracoes);
				}
				
				$this->atualizarCurso();
			}
		}
	}
	
	/**
	 *	Retorna todos os estudantes do curso
	 *
	 * 	@return array:\stdClass estudantes
	 * 	@access public
	 */
	public function recuperarEstudantes() {
		global $DB;
		$context = \context_course::instance($this->course->id);
		
		$sql = "SELECT u.id, u.firstname, u.lastname FROM {role_assignments} r, {user} u 
				WHERE r.contextid = ? AND u.id = r.userid AND r.roleid =5 ORDER BY u.firstname";
		
		return $DB->get_records_sql($sql, array($context->id));
	}

	public function removerCurso() {
		global $DB;
		$DB->delete_records('lvs_atv_presencial', array('id_curso'=>$this->course->id));
		$DB->delete_records('lvs_avaliacao_final', array('id_curso'=>$this->course->id));
		$DB->delete_records('lvs_config_curso', array('id_curso'=>$this->course->id));
		$DB->delete_records('lvs_tabela_curso', array('id_curso'=>$this->course->id));
	}
	
	/* (non-PHPdoc)
	 * @see uab\ifce\lvs\business.CursoLv::salvarAvaliacaoFinal()
	*/
	public function salvarAvaliacaoFinal($avaliacao) {
		global $DB, $USER;
		$curso_id = $this->configuracao->id_curso;
	
		$avaliacao_atual = $DB->get_record('lvs_avaliacao_final', array('id_curso'=>$this->course->id, 'id_avaliado'=>$avaliacao->id_avaliado), 'id');
	
		if(strpos($avaliacao->nota, ','))
			$af->nota = str_replace(',', '.', $af->nota);
	
		if (empty($avaliacao_atual)) {
			$avaliacao->id_curso 	 = $this->course->id;
			$avaliacao->id_avaliador = $USER->id;
			$DB->insert_record('lvs_avaliacao_final', $avaliacao);
		} else {
			$avaliacao_atual->nota 			= $avaliacao->nota;
			$avaliacao_atual->id_avaliador  = $USER->id;
			$DB->update_record('lvs_avaliacao_final', $avaliacao_atual);
		}
	
		$this->atualizarCurso();
	}

	public function salvarConfiguracao($configuracao) {
		global $DB;
		$configuracao = (object) $configuracao;
			
		if(empty($this->configuracao)) {
			$configuracao->id_curso = $this->course->id;

			$atualizar_desempenhos = "UPDATE {lvs_tabela_curso} SET atualiza = ? WHERE id_curso=?"; //TODO criar método e usar transação
			$DB->execute($atualizar_desempenhos, array(1,$this->course->id));

			return $DB->insert_record('lvs_config_curso', $configuracao);
		} else {
			$configuracao->id = $this->configuracao->id;

			$atualizar_desempenhos = "UPDATE {lvs_tabela_curso} SET atualiza = ? WHERE id_curso=?";
			$DB->execute($atualizar_desempenhos, array(1,$this->course->id));

			return $DB->update_record('lvs_config_curso', $configuracao);
		}

		return false;
	}

	private function analisarSituacao($desempenho) {
		if ($this->configuracao->exibelv) {
			if (!isset($desempenho->notaAF)) {
				if ($desempenho->percentualFaltas > $this->configuracao->percentual_faltas) {
					if (( 0 > 0)) { // $desempenho->presencial->FALTOU_PROVA
						return "SC / RF";
					} else {
						return "RF";
					}
				} else {
					if (( 0 > 0)) { // $desempenho->presencial->FALTOU_PROVA
						if ($desempenho->mediaParcial >= $this->configuracao->media_curso) {
							return "SC / AM";
						} else if ( $desempenho->mediaParcial >= $this->configuracao->media_af) {
							return "SC / AF";
						} else {
							return "SC / R";
						}
					} else {
						if ($desempenho->mediaParcial >= $this->configuracao->media_curso) {
							return "AM";
						} else if ($desempenho->mediaParcial >= $this->configuracao->media_af) {
							return "AF";
						} else {
							return "R";
						}
					}
				}
			} else {
				if (isset($desempenho->notaAF) && $desempenho->notaAF >= 0) {
					if ($desempenho->mediaFinal >= $this->configuracao->media_aprov_af)
						return 'AMF';
					else
						return 'RMF';
				}
			}
		} else {
			return 'C';
		}
	}

	private function calcularBeta($desempenho) {
		$modulos = $this->recuperarAtividades();
		$positividade_distancia = $negatividade_distancia = $positividade_presencial = 0;
		$carinhas = array();
		
		foreach ($modulos as $nomeModulo => $modulo) {
			$carinhas['azuis'] 		= $desempenho->$nomeModulo->carinhasAzuis;
			$carinhas['verdes'] 	= $desempenho->$nomeModulo->carinhasVerdes;
			$carinhas['amarelas']	= $desempenho->$nomeModulo->carinhasAmarelas;
			$carinhas['laranjas'] 	= $desempenho->$nomeModulo->carinhasLaranjas;
			$carinhas['vermelhas']	= $desempenho->$nomeModulo->carinhasVermelhas;
			
			foreach ($desempenho->$nomeModulo->avaliacoes as $avaliacao) {
				$positividade_distancia += $avaliacao->modulo_vetor;
				$negatividade_distancia += sqrt(100 - pow($avaliacao->modulo_vetor, 2));
			}
		}

		$negatividade_presencial = $desempenho->presencial->numeroFaltas*10;

		foreach ($desempenho->presencial->avaliacoes as $avaliacao) {
			$positividade_presencial += $avaliacao->nota;
			$negatividade_presencial += sqrt(100 - pow($avaliacao->nota, 2));
		}

		$positividade = $positividade_distancia + $positividade_presencial + 3 * $carinhas['azuis'] + 2 * $carinhas['verdes'] + $carinhas['amarelas'];
		$negatividade = $negatividade_distancia + $negatividade_presencial + $carinhas['laranjas'] + 2 * $carinhas['vermelhas'] + $desempenho->horasFaltadas;

		if ($negatividade == 0)
			$negatividade = 1;

		$beta = round(($positividade / ($negatividade)), 2);

		return $beta;
	}

	private function calcularFrequencia($desempenho) {
		if($desempenho->numeroAtividades == $desempenho->numeroFaltas)
			return $this->configuracao->total_horas_curso;

		return floor($this->configuracao->total_horas_curso / $desempenho->numeroAtividades) * $desempenho->numeroFaltas;
	}

	private function calcularMediaParcial($desempenho) {
		$nota = $desempenho->notaDistancia + $desempenho->notaPresencial;

		return round($nota, 1);
	}

	private function calcularNotaDistancia($desempenho) {
		$notas = 0;
		$modulos = $this->recuperarAtividades();
		
		foreach ($modulos as $nomeModulo => $modulo) {
			$notas += $desempenho->$nomeModulo->notaFinal;
		}
		
		$porcentagem = $this->configuracao->porcentagem_distancia/100;

		return round( $porcentagem * $notas, 2);
	}

	private function calcularNotaPresencial($desempenho) {
		$nota = $desempenho->presencial->notaFinal;
		$porcentagem = $this->configuracao->porcentagem_presencial/100;

		return round($porcentagem * $nota, 2);
	}

	public function removerNotasDeAtividadesExcluidas() {
		nao_implementado(__CLASS__, __FUNCTION__);
// 		global $DB;
// 		$atualizacao_necessaria = false;

// 		foreach ($this->modulos_lvs as $modulo) {
// 			$modulos_ids = $DB->get_records($modulo, array('course'=>$this->course->id), '', 'id');

// 			// preciso saber se em lvs_modulo ou tabelas_curso contabiliza notas de atividades removidas
// 			// preciso saber se algum módulo foi removido

// 			if(!empty($modulos_ids)) {
// 				$modulos_ids = array_keys($modulos_ids);
// 				list($mask, $params) = $DB->get_in_or_equal($modulos_ids, SQL_PARAMS_QM, '', false, true);
// 				$params[] = $this->course->id;

// 				if($DB->record_exists_select("lvs_$modulo", "id_$modulo $mask AND id_curso = ?", $params)) {
// 					$atualizacao_necessaria |= $DB->delete_records_select("lvs_$modulo", "id_$modulo $mask AND id_curso = ?", $params);
// 				}
// 			} else if($DB->record_exists("lvs_$modulo", array("id_curso"=>$this->course->id))) {
// 				$atualizacao_necessaria |= $DB->delete_records("lvs_$modulo", array("id_curso"=>$this->course->id));
// 			}

// 			$primeira_letra = substr($modulo, 0, 1);
// 			$sql = "id_curso = ? AND n$primeira_letra > ?";
// 			$atualizacao_necessaria |= $DB->record_exists_select('lvs_tabela_curso', $sql, array($this->course->id, count($modulos_ids)));

// 			$this->avaliarDesempenho(4);
// 		}

// 		if($atualizacao_necessaria) {
// 			print_object('atualizou curso!');
// 			$this->atualizarCurso();
// 		}
	}

	private function removerNotasDeUsuariosExcluidos() {
		global $DB;
		$context = \context_course::instance($this->course->id);

		$estudantes = $this->recuperarEstudantes();
		$ids = array_keys($estudantes);
		
		list($mask, $params) = $DB->get_in_or_equal($ids, SQL_PARAMS_QM, '', false);		
		array_push($params, $this->course->id);

		$DB->delete_records_select('lvs_tabela_curso', "id_usuario $mask AND id_curso = ?", $params);
	}

	private function salvarDesempenho( $desempenho ) {
		global $DB;
		$modulos = $this->recuperarAtividades();
		
		$avaliacao_atual = $DB->get_record('lvs_tabela_curso', array(
			'id_curso'	=> $this->configuracao->id_curso, 
			'id_usuario'=> $desempenho->usuario->id
		));
		
		$nova_avaliacao = new \stdClass();
		
		foreach ($modulos as $nomeModulo => $gerenciador) {
			$str_nota = "nota_$nomeModulo";
			$str_ausencias = "ausencias_$nomeModulo";
			
			$nova_avaliacao->$str_nota 		= round($this->configuracao->porcentagem_distancia / 100 * $desempenho->$nomeModulo->notaFinal, 2);
			$nova_avaliacao->$str_ausencias = $desempenho->$nomeModulo->numeroFaltas . ' de ' . $desempenho->$nomeModulo->numeroAtividades;
			
			$nova_avaliacao->numero_carinhas_azul 	  += $desempenho->$nomeModulo->carinhasAzuis;
			$nova_avaliacao->numero_carinhas_verde 	  += $desempenho->$nomeModulo->carinhasVerdes;
			$nova_avaliacao->numero_carinhas_amarela  += $desempenho->$nomeModulo->carinhasAmarelas;
			$nova_avaliacao->numero_carinhas_laranja  += $desempenho->$nomeModulo->carinhasLaranjas;
			$nova_avaliacao->numero_carinhas_vermelha += $desempenho->$nomeModulo->carinhasVermelhas;
		}
		
		$nova_avaliacao->nd 			= $desempenho->notaDistancia;
		$nova_avaliacao->np 			= $desempenho->notaPresencial;
		$nova_avaliacao->aap 			= $desempenho->presencial->numeroFaltas . ' de ' . $desempenho->presencial->numeroAtividades;
		$nova_avaliacao->media_parcial 	= $desempenho->mediaParcial;
		$nova_avaliacao->ntf 			= $desempenho->horasFaltadas . ' / ' . $desempenho->percentualFaltas;
		$nova_avaliacao->beta 			= $desempenho->beta;
		$nova_avaliacao->lv_icone 		= $desempenho->lvicone;
		$nova_avaliacao->af 			= (isset($desempenho->notaAF)) ? $desempenho->notaAF : NULL;
		$nova_avaliacao->media_final 	= (isset($desempenho->mediaFinal)) ? $desempenho->mediaFinal : NULL;
		$nova_avaliacao->situacao 		= $desempenho->situacao;
		$nova_avaliacao->atualiza 		= 0;

		if (empty($avaliacao_atual)) {
			$nova_avaliacao->id_curso 	= $this->course->id;
			$nova_avaliacao->id_usuario = $desempenho->usuario->id;
			$DB->insert_record('lvs_tabela_curso', $nova_avaliacao);
		} else {
			$nova_avaliacao->id = $avaliacao_atual->id;
			$DB->update_record('lvs_tabela_curso', $nova_avaliacao);
		}

		return $nova_avaliacao;
	}

	private function salvarGrade($desempenho) {
		global $DB;

		// FIXME retirar essa chamada daqui, pois ocorrerá várias vezes!!!
		$linhatype = $DB->get_record('grade_items', array('itemtype'=>'course', 'courseid'=>$this->course->id), 'id');
		$iditem = $linhatype->id;

		$DB->delete_records('grade_grades', array('rawgrade'=>'> 0'));

		$linhagrade = $DB->get_record('grade_grades', array('userid'=>$desempenho->user->id, 'itemid'=>$iditem), 'id');
		$idgrade_grades = $linhagrade->id;

		$grade = new stdClass();
		$grade->finalgrade = (isset($desempenho->mediaFinal)) ? round($desempenho->mediaFinal, 1) : round($desempenho->mediaParcial, 1);

		if (empty($idgrade_grades)) {
			$novaentrada = new stdClass();
			$grade->itemid = $iditem;
			$grade->userid = $desempenho->user->id;
			$DB->insert_record('grade_grades', $grade);
		} else {
			$grade->id = $idgrade_grades;
			$DB->update_record('grade_grades', $grade);
		}
	}

	private function _tiverPorcentagemNula() {
		global $DB;
		$modulos = $this->recuperarAtividades();
		
		foreach ($modulos as $modulo) {
			if( $modulo->porcentagemNula() ) {
				return true;
			}
		}

		return false;
	}
	
}
?>