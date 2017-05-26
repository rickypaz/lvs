<?php 
namespace uab\ifce\lvs\moodle2\business;

use uab\ifce\lvs\business\AtividadesPresenciais;
use uab\ifce\lvs\business\CursoLv;

/**
 * 	Representação de um Curso LV no Moodle 2 
 * 	
 * 	@category LVs
 * 	@package uab\ifce\lvs\moodle2\business
 * 	@author Allyson Bonetti
 * 	@author Ricky Persivo (rickypaz@gmail.com)
 *	@version SVN $Id
 *	@see CursoModelo
 *	@todo criar camada de persistência
 *	@todo criar "pojo" de cursolv
 */
class Moodle2CursoLv extends CursoLv {

	/**
	 *	Representa um curso no Moodle 2 
	 * 	@var \stdClass
	 * 	@access private
	 */
	private $_cursoAVA;
	
	/**
	 * 	Gerenciador de atividades presenciais 
	 * 	@var AtividadesPresenciais
	 * 	@access private
	 */
	private $_presenciais;
	
	/**
	 *	Instancia um curso lv
	 * 	@param int $course_id id do curso do moodle
	 */
	public function __construct( $course_id ) {
		global $COURSE;

		$this->_init($course_id);
		// $cursolv->removerNotasDeAtividadesExcluidas(); // TODO FAZER MÉTODO
	}
	
	/**
	 * 	Inicializa as propriedades de CursoLV
	 * 
	 * 	@param int $course_id id do curso do moodle
	 * 	@access private
	 */
	private function _init($course_id) {
		global $DB;
		
		$this->_cursoAVA = $DB->get_record('course', array('id'=>$course_id));
		$this->configuracao = $this->getConfiguracao();
		$this->_presenciais = new AtividadesPresenciais($this);

		$this->addGerenciador('forumlv', new ForunsLv($this));
// 		$this->addGerenciador('tarefalv', new TarefasLv($this));
		$this->addGerenciador('chatlv', new ChatsLv($this));
		$this->addGerenciador('wikilv', new WikisLv($this));
		$this->addGerenciador('quizlv', new Quizzes($this));
	}
	
	/**
	 *	Altera a flag de atualização da tabela lvs_tabela_curso indicando que as notas lvs de todos os alunos
	 *	do curso devem ser recalculadas
	 *
	 *	@param [opcional] array $estudantes ids dos estudantes que devem ter as notas recalculadas
	 * 	@access public
	 * 	@todo recalcular notas diretamente
	 * 	@deprecated se tornará privado
	 */
	public function atualizarCurso($estudantes = array()) {
		global $DB;
		$sql = "UPDATE {lvs_tabela_curso} SET atualiza=1 WHERE id_curso=?";
		$params = array($this->_cursoAVA->id);
		
		if (!empty($estudantes)) {
			list($mask, $paramsin) = $DB->get_in_or_equal($estudantes, SQL_PARAMS_QM);
			$sql .= " AND id_usuario $mask";
			$params = array_merge($params, $paramsin);
		}
		
		$DB->execute($sql, $params);
	}
	
	public function avaliarDesempenho( $estudante ) {
		global $DB;
	
		$modulos = $this->getGerenciadores();
		$desempenho = new \stdClass();
		$desempenho->usuario = new \stdClass();
		$desempenho->usuario->id = $estudante;
		$desempenho->numeroAtividades = $desempenho->numeroFaltas = 0;
	
		foreach($modulos as $nomeAtividade => $atividade) {
			$desempenho->$nomeAtividade 	= $atividade->recuperarDesempenho($estudante);
			$desempenho->numeroAtividades  += $desempenho->$nomeAtividade->numeroAtividades;
			$desempenho->numeroFaltas	   += $desempenho->$nomeAtividade->numeroFaltas;
		}
		
		$desempenho->presencial = $this->_presenciais->recuperarDesempenho($estudante);
		$desempenho->numeroFaltas = $desempenho->numeroFaltas +	$desempenho->presencial->numeroFaltas;
	
		$desempenho->horasFaltadas 		= $this->_calcularFrequencia($desempenho);
		
		/*global $USER;				
		if( ($USER->id == 2787) && ($estudante == 324)){
			print_object($desempenho->horasFaltadas);
			print_object($this->configuracao->total_horas_curso);
			print_object(round(($desempenho->horasFaltadas / $this->configuracao->total_horas_curso) * 100, 2));
		}*/	
		
		$desempenho->percentualFaltas 		= round(($desempenho->horasFaltadas / $this->configuracao->total_horas_curso) * 100, 2);
		$desempenho->notaDistancia 		= $this->_calcularNotaDistancia($desempenho);
		$desempenho->notaPresencial 	= $this->_calcularNotaPresencial($desempenho);
		$desempenho->mediaParcial 		= $this->_calcularMediaParcial($desempenho);
		$desempenho->beta 				= $this->_calcularBeta($desempenho);
	
		if($desempenho->mediaParcial < $this->configuracao->media_curso) {
			$nota_af = $DB->get_record('lvs_avaliacao_final', array('id_curso'=>$this->_cursoAVA->id, 'id_avaliado'=>$estudante));
			$desempenho->notaAF = (!empty($nota_af)) ? round($nota_af->nota, 1) : NULL;
	
			if(isset($desempenho->notaAF)) {
				$desempenho->notaAF = number_format($desempenho->notaAF, 1);
				$desempenho->mediaFinal = number_format(round(($desempenho->mediaParcial + $desempenho->notaAF) / 2, 1), 1);
			}
			
		}
	
		// é de view??
		$desempenho->situacao = $this->_analisarSituacao($desempenho);
		$desempenho->lvicone = $this->obterCarinha($desempenho);
	
//		$this->_salvarGrade($desempenho); //FIXME descomentar
		return $this->_salvarDesempenho($desempenho);
	}

	public function betaMedio( ) {
		global $DB;

		$this->_removerNotasDeUsuariosExcluidos();

		$beta_total = $DB->get_records('lvs_tabela_curso', array('id_curso'=>$this->_cursoAVA->id), '', 'SUM(cast(beta as decimal(12,2))) as beta, COUNT(beta) as total_users');
		$betaMedio = current($beta_total)->beta;
		$users_curso = current($beta_total)->total_users;

		if($users_curso != 0)
			return round($betaMedio/$users_curso, 2);

		return 0;
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
		$modulos = $this->getGerenciadores();
		
		if ($soma_porcentagens_distancia < 100 || $porcentagem_nula ) {
			$total_atividades = 0;
			$atividades_por_modulo = array();
				
			foreach ($modulos as $nomeModulo => $modulo) {
				$atividades[$nomeModulo] = $modulo->recuperarAtividades();
				$total_atividades += count($atividades[$nomeModulo]);
			}
			
			if ($total_atividades > 0) {
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
	
	public function faltas( $estudante ) {
		$faltas = array();
		
		foreach ( $this->getGerenciadores() as $gerenciador ) {
			$faltas['distancia'][ $gerenciador::NOME ]['faltas'] = $gerenciador->numeroFaltas($estudante);
			$faltas['distancia'][ $gerenciador::NOME ]['numero_atividades'] = $gerenciador->quantidadeAtividades();
		}
		
		$faltas['presencial'] = $this->_presenciais->numeroFaltasDiscriminado($estudante);
		
		return $faltas;
	}

	public function getConfiguracao() {
		global $DB;

		if($this->configuracao == NULL)
			return $DB->get_record('lvs_config_curso', array('id_curso'=>$this->_cursoAVA->id));

		return $this->configuracao;
	}
	
	public function getDesempenho( $estudante ) {
		global $DB;
		$avaliacao = $DB->get_record('lvs_tabela_curso', array('id_curso'=>$this->_cursoAVA->id, 'id_usuario'=>$estudante));
		
		if (empty($avaliacao) || $avaliacao->atualiza == 1)
			return $this->avaliarDesempenho($estudante);
			
		return $avaliacao;
	}

	public function getEstudantes( ) {
		global $DB;
		$context =  \context_course::instance($this->_cursoAVA->id);
		
		$sql = "SELECT u.id, u.firstname, u.lastname FROM {role_assignments} r, {user} u 
				WHERE r.contextid = ? AND u.id = r.userid AND r.roleid =5 ORDER BY u.firstname, u.lastname";
		
		return $DB->get_records_sql($sql, array($context->id));
	}
	
	public function getGerenciadorPresencial( ) {
		return $this->_presenciais;		
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
	
	public function porcentagemDistancia() {
		$somatorio = 0;
		$modulos = $this->getGerenciadores();
	
		foreach ($modulos as $modulo) {
			$somatorio += $modulo->porcentagemDistancia();
		}
	
		return $somatorio;
	}

	public function removerCurso() {
		global $DB;
		$DB->delete_records('lvs_atv_presencial', array('id_curso'=>$this->_cursoAVA->id));
		$DB->delete_records('lvs_avaliacao_final', array('id_curso'=>$this->_cursoAVA->id));
		$DB->delete_records('lvs_config_curso', array('id_curso'=>$this->_cursoAVA->id));
		$DB->delete_records('lvs_tabela_curso', array('id_curso'=>$this->_cursoAVA->id));
	}
	
	public function salvarAvaliacaoFinal($avaliacao) {
		global $DB, $USER;
		$curso_id = $this->configuracao->id_curso;
	
		$avaliacao_atual = $DB->get_record('lvs_avaliacao_final', array('id_curso'=>$this->_cursoAVA->id, 'id_avaliado'=>$avaliacao->id_avaliado), 'id');
		$avaliacao->nota = str_replace(',', '.', $avaliacao->nota);
		$avaliacao->nota = number_format($avaliacao->nota, 1);
	
		if (empty($avaliacao_atual)) {
			$avaliacao->id_curso 	 = $this->_cursoAVA->id;
			$avaliacao->id_avaliador = $USER->id;
			$DB->insert_record('lvs_avaliacao_final', $avaliacao);
		} else {
			$avaliacao_atual->nota 			= $avaliacao->nota;
			$avaliacao_atual->id_avaliador  = $USER->id;
			$DB->update_record('lvs_avaliacao_final', $avaliacao_atual);
		}
	
		$this->atualizarCurso();
	}

	public function setConfiguracao( $configuracao ) {
		global $DB;
		$configuracao = (object) $configuracao;
			
		if(empty($this->configuracao)) {
			$configuracao->id_curso = $this->_cursoAVA->id;

			$atualizar_desempenhos = "UPDATE {lvs_tabela_curso} SET atualiza = ? WHERE id_curso=?"; //TODO criar método e usar transação
			$DB->execute($atualizar_desempenhos, array(1,$this->_cursoAVA->id));

			return $DB->insert_record('lvs_config_curso', $configuracao);
		} else {
			$configuracao->id = $this->configuracao->id;

			$atualizar_desempenhos = "UPDATE {lvs_tabela_curso} SET atualiza = ? WHERE id_curso=?";
			$DB->execute($atualizar_desempenhos, array(1,$this->_cursoAVA->id));

			return $DB->update_record('lvs_config_curso', $configuracao);
		}

		return false;
	}
	
	/**
	 * @deprecated
	 */
	public function totalAtividades() {
		return 0;
	}

	private function _analisarSituacao( $desempenho ) {
		if ($this->configuracao->exibelv) {
			if (!isset($desempenho->notaAF)) {
				if ($desempenho->percentualFaltas > $this->configuracao->percentual_faltas) {
					if (( $desempenho->presencial->faltouProva > 0)) { // $desempenho->presencial->FALTOU_PROVA
						return "SC / RF";
					} else {
						return "RF";
					}
				} else {
					if (( $desempenho->presencial->faltouProva > 0)) { // $desempenho->presencial->FALTOU_PROVA
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

	private function _calcularBeta( $desempenho ) {
		$modulos = $this->getGerenciadores();
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

	private function _calcularFrequencia( $desempenho ) {
		$numeroTotal = $desempenho->numeroAtividades + $desempenho->presencial->numeroAtividades;

		if($numeroTotal == $desempenho->numeroFaltas)
			return $this->configuracao->total_horas_curso;
		
		/*global $USER;				
		if( ($USER->id == 2787) && ($desempenho->usuario->id == 324)){
			print_object($this->configuracao->total_horas_curso);
			print_object($numeroTotal);
			print_object($desempenho->numeroFaltas);
			print_object(floor(($this->configuracao->total_horas_curso / $numeroTotal) * $desempenho->numeroFaltas));
		}*/
		
		return floor (($this->configuracao->total_horas_curso / $numeroTotal) * $desempenho->numeroFaltas);
	}

	private function _calcularMediaParcial( $desempenho ) {
		$nota = $desempenho->notaDistancia + $desempenho->notaPresencial;

		return round($nota, 1);
	}

	private function _calcularNotaDistancia( $desempenho ) {
		$notas = 0;
		$modulos = $this->getGerenciadores();
		
		foreach ($modulos as $nomeModulo => $modulo) {
			$notas += $desempenho->$nomeModulo->notaFinal;
		}
		
		$porcentagem = $this->configuracao->porcentagem_distancia/100;

		return round( $porcentagem * $notas, 2);
	}

	private function _calcularNotaPresencial( $desempenho ) {
		$nota = $desempenho->presencial->notaFinal;
		$porcentagem = $this->configuracao->porcentagem_presencial/100;

		return round($porcentagem * $nota, 2);
	}

	public function removerNotasDeAtividadesExcluidas( ) {
		nao_implementado(__CLASS__, __FUNCTION__);
// 		global $DB;
// 		$atualizacao_necessaria = false;

// 		foreach ($this->modulos_lvs as $modulo) {
// 			$modulos_ids = $DB->get_records($modulo, array('course'=>$this->_cursoAVA->id), '', 'id');

// 			// preciso saber se em lvs_modulo ou tabelas_curso contabiliza notas de atividades removidas
// 			// preciso saber se algum módulo foi removido

// 			if(!empty($modulos_ids)) {
// 				$modulos_ids = array_keys($modulos_ids);
// 				list($mask, $params) = $DB->get_in_or_equal($modulos_ids, SQL_PARAMS_QM, '', false, true);
// 				$params[] = $this->_cursoAVA->id;

// 				if($DB->record_exists_select("lvs_$modulo", "id_$modulo $mask AND id_curso = ?", $params)) {
// 					$atualizacao_necessaria |= $DB->delete_records_select("lvs_$modulo", "id_$modulo $mask AND id_curso = ?", $params);
// 				}
// 			} else if($DB->record_exists("lvs_$modulo", array("id_curso"=>$this->_cursoAVA->id))) {
// 				$atualizacao_necessaria |= $DB->delete_records("lvs_$modulo", array("id_curso"=>$this->_cursoAVA->id));
// 			}

// 			$primeira_letra = substr($modulo, 0, 1);
// 			$sql = "id_curso = ? AND n$primeira_letra > ?";
// 			$atualizacao_necessaria |= $DB->record_exists_select('lvs_tabela_curso', $sql, array($this->_cursoAVA->id, count($modulos_ids)));

// 			$this->avaliarDesempenho(4);
// 		}

// 		if($atualizacao_necessaria) {
// 			print_object('atualizou curso!');
// 			$this->atualizarCurso();
// 		}
	}

	private function _removerNotasDeUsuariosExcluidos( ) {
		global $DB;
		$context = \context_course::instance($this->_cursoAVA->id);

		$estudantes = $this->getEstudantes();
		
		if (!empty($estudantes)) 
		{
			$ids = array_keys($estudantes);
		
			list($mask, $params) = $DB->get_in_or_equal($ids, SQL_PARAMS_QM, '', false);		
			array_push($params, $this->_cursoAVA->id);

			$DB->delete_records_select('lvs_tabela_curso', "id_usuario $mask AND id_curso = ?", $params);
		}
	}

	private function _salvarDesempenho( $desempenho ) {
		global $DB;
		$modulos = $this->getGerenciadores();
		
		$avaliacao_atual = $DB->get_record('lvs_tabela_curso', array(
			'id_curso'	=> $this->configuracao->id_curso, 
			'id_usuario'=> $desempenho->usuario->id
		));
		
		// TODO: criar objeto próprio
		$nova_avaliacao = new \stdClass();
		$nova_avaliacao->numero_carinhas_azul 	  = 0;
		$nova_avaliacao->numero_carinhas_verde 	  = 0;
		$nova_avaliacao->numero_carinhas_amarela  = 0;
		$nova_avaliacao->numero_carinhas_laranja  = 0;
		$nova_avaliacao->numero_carinhas_vermelha = 0;
		
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
			$nova_avaliacao->id_curso 	= $this->_cursoAVA->id;
			$nova_avaliacao->id_usuario = $desempenho->usuario->id;
			$DB->insert_record('lvs_tabela_curso', $nova_avaliacao);
		} else {
			$nova_avaliacao->id = $avaliacao_atual->id;
			$DB->update_record('lvs_tabela_curso', $nova_avaliacao);
		}

		return $nova_avaliacao;
	}

	private function _salvarGrade($desempenho) {
		global $DB;

		$item_curso = $DB->get_record('grade_items', array('itemtype'=>'course', 'courseid'=>$this->_cursoAVA->id), 'id');
		$id_item_curso = $item_curso->id;
		
		$nota_curso = $DB->get_record('grade_grades', array('userid'=>$desempenho->usuario->id, 'itemid'=>$id_item_curso), 'id');
		
		echo 'user:' . $desempenho->usuario->id;
		echo 'item:' . $id_item_curso;
		echo '<pre>';
		print_r($nota_curso);
		echo '</pre>';
		
		$id_nota_curso = (!empty($nota_curso)) ? $nota_curso->id : null;

		$grade = new \stdClass();		
		$grade->finalgrade = 5;//(isset($desempenho->mediaFinal)) ? round($desempenho->mediaFinal, 1) : round($desempenho->mediaParcial, 1);

		if (empty($id_nota_curso)) {
			$novaentrada = new \stdClass();
			$grade->itemid = $id_item_curso;
			$grade->userid = $desempenho->usuario->id;
			$DB->insert_record('grade_grades', $grade);
		} else {
			$grade->id = $id_nota_curso;
			
			echo '<pre>';
			print_r($grade);
			echo '</pre>';
			
			exit;
			$DB->update_record('grade_grades', $grade);
		}
	}

	private function _tiverPorcentagemNula() {
		global $DB;
		$modulos = $this->getGerenciadores();
		
		foreach ($modulos as $modulo) {
			if( $modulo->porcentagemNula() ) {
				return true;
			}
		}

		return false;
	}
	
}
?>
