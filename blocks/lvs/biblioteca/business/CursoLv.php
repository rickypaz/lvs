<?php 
namespace uab\ifce\lvs\business;

/**
 * 	Interface de gerenciamento de um curso lv. Responsável pelo avaliação de desempenho lv dos estudantes inscritos no curso 
 *
 *	@category LVs
 *	@package uab\ifce\lvs\business
 *	@author Ricky Paz (rickypaz@gmail.com)
 *	@version SVN $Id
 *	@abstract
 */
abstract class CursoLv {

	/**
	 *	Contém as configurações do curso
	 *	@var \stdClass
	 * 	@access protected
	 */
	protected $configuracao;

	/**
	 *	Contém todos os gerenciadores de atividades do curso
	 *
	 *	@var array:\uab\ifce\lvs\business\Atividades
	 * 	@access private
	 */
	private $_gerenciadores = array();
	
	/**
	 * 	Adiciona um gerenciador de atividades no curso. Gerenciadores são responsáveis por calcular e recuperar o desempenho de estudantes
	 * 	nas atividades que gerencia.
	 *
	 * 	@param string $nomeGerenciador
	 * 	@param GerenciadorAtividadesDistancia $gerenciador
	 * 	@access public
	 */
	public function addGerenciador( $nomeGerenciador, GerenciadorAtividadesDistancia $gerenciador ) {
		$this->_gerenciadores[$nomeGerenciador] = $gerenciador;
	}
	
	/**
	 * 	Retorna um gerenciador de atividade a distância
	 *
	 * 	@param string $nomeGerenciador nome do gerenciador
	 * 	@return GerenciadorAtividadesDistancia
	 * 	@access public
	 * 	@todo lançar exceção quando não existir
	 */
	public function getGerenciador( $nomeGerenciador ) {
		return isset($this->_gerenciadores[$nomeGerenciador]) ? $this->_gerenciadores[$nomeGerenciador] : null;
	}
	
	/**
	 * 	Retorna todos os gerenciadores de atividades a distância do curso
	 *
	 * 	@return array:GerenciadorAtividadesDistancia
	 * 	@access public
	 * 	@todo retornar apenas os nomes
	 */
	public function getGerenciadores( ) {
		return $this->_gerenciadores;
	}
	
	/**
	 * 	Remove um gerenciador de atividades do curso
	 *
	 * 	@param string $nomeGerenciador
	 * 	@access public
	 */
	public function removerGerenciador( $nomeGerenciador ) {
		unset($this->_gerenciadores[$nomeGerenciador]);
	}
	
	/**
	 *	Atualiza as notas dos estudantes do curso. Caso nenhum estudante seja fornecido, todos devem ter suas notas atualizadas
	 *
	 *	@param array $estudantes ids dos estudantes que terão suas notas atualizadas
	 * 	@abstract
	 * 	@access public
	 */
	abstract public function atualizarCurso( $estudantes );

	/**
	 *	Avalia o desempenho lv de um estudante
	 *
	 * 	@param int $estudante id do estudante
	 * 	@return \stdClass
	 * 	@abstract
	 * 	@access public
	 */
	abstract public function avaliarDesempenho( $estudante );
	
	/**
	 *	Calcula e retorna o fator ß do curso
	 *
	 * 	@return float
	 * 	@abstract
	 * 	@access public
	 */
	abstract public function betaMedio( );

	/**
	 *	Soma a porcentagem de todas as atividades a distância do curso. Caso seja diferente de 100, recalcula as porcentagens para 
	 *	que seja 100
	 *
	 * 	@abstract
	 * 	@access public
	 */
	abstract public function calcularPorcentagemAtividades( );
	
	/**
	 *	Retorna as configurações do curso
	 *
	 * 	@return \stdClass contém as configurações do curso
	 * 	@abstract
	 * 	@access public
	 */
	abstract public function getConfiguracao( );
	
	/**
	 *	Retorna o desempenho de estudante
	 *
	 * 	@param int $estudante id do estudante
	 * 	@return \stdClass
	 * 	@abstract
	 * 	@access public
	 */
	abstract public function getDesempenho( $estudante );
	
	/**
	 *	Retorna todos os estudantes inscritos no curso
	 *
	 *	@return array:\stdClass
	 * 	@access public
	 */
	abstract public function getEstudantes( );
	
	/**
	 *	Retorna o gerenciador de atividades presenciais
	 *
	 * 	@return AtividadesPresenciais
	 * 	@access public
	 */
	abstract function getGerenciadorPresencial( );
	
	/**
	 * 	Calcula a soma das porcentagens de todas as atividades a distância do curso
	 *
	 * 	@return float soma das porcentagens
	 * 	@abstract
	 * 	@access public
	 */
	abstract public function porcentagemDistancia( );
	
	/**
	 *	Remove o curso lv, incluindo as atividades lvs e as notas. 
	 *
	 * 	@abstract
	 * 	@access public
	 */
	abstract public function removerCurso( );

	/**
	 *	Salva a avaliação final de estudante que tenha ficado em AF
	 *
	 * 	@param \stdClass $af avaliação final
	 * 	@abstract
	 * 	@access public
	 */
	abstract public function salvarAvaliacaoFinal( $af );

	/**
	 *	Armazena/atualiza as configurações do curso
	 *
	 * 	@param \stdClass $configuracao contém as configurações do curso
	 * 	@return bool true, caso o armazenamento tenha sido bem-sucedido, false, caso contrário
	 * 	@abstract
	 * 	@access public
	 */
	abstract public function setConfiguracao( $configuracao );

}
?>