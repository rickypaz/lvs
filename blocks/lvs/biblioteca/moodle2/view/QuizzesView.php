<?php
namespace uab\ifce\lvs\moodle2\view;

use uab\ifce\lvs\Template;

/**
*  	Constrói e exibe as páginas html relacionada aos quizzes
*  
*  	@category LVs
*	@package uab\ifce\lvs\moodle2\view
* 	@author Ricky Persivo (rickypaz@gmail.com)
* 	@version SVN $Id
* 	@todo não instanciar o adapterView no Construtor
*/
class QuizzesView {

	/**
	 * 	Exibe a página html gerada no AVA
	 * 	@var \uab\ifce\lvs\view\AdapterView
	 * 	@see \uab\ifce\lvs\view\AdapterView
	 */
	private $_adapterView;
	
	/**
	 * 	Contém os dados enviados utilizados na construção da página html
	 * 	@var array:mixed
	 */
	private $_data;
	
	/**
	 * 	Responsável por gerar a página html
	 * 	@var Template
	 * 	@see Template
	 */
	private $_template;
	
	/**
	 *	Instancia QuizzesView 
	 */
	public function __construct() {
		$this->_adapterView = new Moodle2View();
	}
	
	/**
	 *	Constrói e exibe a tela de importação de quizzes presenciais e a distância
	 *
	 *	@access public 
	 */
	public function importarQuizzes() {
		$this->_template = new Template('html/importar_quizzes.html');
		$this->_template->BASEURL = LVS_WWWROOT2;
		$this->_template->CURSO = $this->_data['curso_id'];

		$this->_quizzesDisponiveis();
 		$this->_quizzesDistancia();
 		$this->_quizzesPresencial();
		
		$this->_adapterView->js('/blocks/lvs/pages/scripts/form.js');
		$this->_exibirPagina();
	}
	
	/**
	 *	Recupera o conteúdo criado no Template e o exibe em tela através do AdaptarView 
	 *
	 *	@access private
	 */
	private function _exibirPagina() {
		$this->_adapterView->setContent($this->_template->parse());
		$this->_adapterView->exibirPagina();
	}
	
	private function _quizzesDisponiveis( ) {
		$quizzesDisponiveis = $this->_data['quizzesDisponiveis'];
        
		if(count($quizzesDisponiveis) > 0) {
    		$i = 1;
    		
    		foreach($quizzesDisponiveis as $quiz) {
    			$this->_template->CONTADOR = $i++;
    		
    			$this->_template->ID = "";
    			$this->_template->QUIZ_ID = $quiz->id_quiz;
    			$this->_template->QUIZ_NOME = $quiz->nome;
    				
    			$this->_template->block('QUIZ_NAO_IMPORTADO');
    		}

    		$this->_template->MSG_ZERO_QUIZ_DISPLAY = 'display: none;';
        }
        
        $this->_template->CONTADOR_QUIZZES = count($quizzesDisponiveis);
	}
	
	/**
	 *	Constrói a tela de importação de quizzes a distância 
	 *
	 *	@access private
	 */
	private function _quizzesDistancia() {
		$this->_template->addFile('ATIVIDADES_DISTANCIA', 'html/quizzes/quizzes_distancia.html');
		$quizzesDistancia = $this->_data['quizzesDistancia'];
		
        if (count($quizzesDistancia) > 0) 
        {
    		$i = count($this->_data['quizzesDisponiveis']) + 1;
    		
    		foreach($quizzesDistancia as $quiz) {
    			$this->_template->CONTADOR = $i++;
    
    			$this->_template->ID = $quiz->id;
    			$this->_template->QUIZ_ID = $quiz->id_quiz;
    			$this->_template->QUIZ_NOME = $quiz->nome;
    			
    			$this->_template->block('QUIZ_DISTANCIA');
    		}
    		
    		$this->_template->MSG_ZERO_QUIZ_DISTANCIA_DISPLAY = 'display: none;';
        }
        
        $this->_template->CONTADOR_DISTANCIA = count($quizzesDistancia);
	}
	
	/**
	 *	Constrói a tela de importação de quizzes presenciais 
	 *
	 *	@access private
	 */
	private function _quizzesPresencial() {
		$this->_template->addFile('ATIVIDADES_PRESENCIAIS', 'html/quizzes/quizzes_presenciais.html');

		$presenciais = $this->_data['presenciais'];
		$quizzesPresenciais  = $this->_data['quizzesPresenciais'];
		
		if (count($presenciais) + count($quizzesPresenciais) > 0) {

			$i = 1;
			foreach ($presenciais as $presencial) {
				$this->_template->CONTADOR = $i++;
				$this->_template->PRESENCIAL_ID = $presencial->id;
				$this->_template->PRESENCIAL_NOME = $presencial->nome;
				$this->_template->PRESENCIAL_PORCENTAGEM = $presencial->porcentagem;
				$this->_template->PRESENCIAL_MAX_FALTAS = $presencial->max_faltas;
	
				$this->_template->block('PRESENCIAL');
			}
		
    		$i = count($this->_data['quizzesDisponiveis']) + count($this->_data['quizzesDistancia']) + 1;
    		foreach($quizzesPresenciais as $quiz) {
    			$this->_template->CONTADOR 			= $i++;
    			$this->_template->QUIZLV_ID 		= $quiz->id;
    			$this->_template->QUIZ_ID 			= $quiz->id_quiz;
    			$this->_template->QUIZ_ATIVIDADE 	= $quiz->presencial->id;
    			$this->_template->QUIZ_NOME 		= $quiz->presencial->nome;
    			$this->_template->QUIZ_PORCENTAGEM  = $quiz->presencial->porcentagem;
    			$this->_template->QUIZ_MAX_FALTAS   = $quiz->presencial->max_faltas;
    			
    			if(!empty($quiz->presencial->id)) {
    				$this->_template->block('QUIZ_PRESENCIAL');
    			}
    		}
    		
        	$this->_template->MSG_ZERO_QUIZ_PRESENCIAL_DISPLAY = 'display: none;';
		}
        
        $this->_template->CONTADOR_PRESENCIAL = count($presenciais) + count($quizzesPresenciais);
	}
	
	/**
	 * 	Armazena os dados utilizados na construção da tela de exibição. É um 'magic method', não chamá-lo diretamente
	 */
	public function __set($name, $value) {
		$this->_data[$name] = $value;
	}
	
}
?>