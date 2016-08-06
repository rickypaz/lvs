<?php 
namespace uab\ifce\lvs\forms;
use uab\ifce\lvs\moodle2\business\Moodle2CursoLv;
use uab\ifce\lvs\moodle2\business\Forumlv;

class FormModulosLV {
	
	public function  add_header_lv_forumlv(&$mform){
		global $COURSE, $CFG;
        //@lvs Verificar se a confiruração dos lvs já foram feitas
        $cursolv = new Moodle2CursoLv($COURSE -> id);
        if (!$cursolv -> getConfiguracao()) {
            redirect("$CFG->wwwroot/blocks/lvs/pages/config_cursolv.php?curso=$COURSE->id", "Preencha as informações do curso !");
        }	
            
		$mform->removeElement('type');
		$mform->addElement('select', 'type', get_string('forumlvtype', 'forumlv'), array ('single' => get_string('singleforumlv', 'forumlv')));
		$mform->insertElementBefore($mform->removeElement('type', false), 'attachmentswordcounthdr');
        
		$mform->addElement('header', 'lvs', get_string('scalelvs', 'block_lvs'));
        $mform->setExpanded('lvs');
		
		for($ietapa=1;$ietapa<=10;$ietapa++){
			$etapa[$ietapa] = $ietapa;
		}
		$mform->addElement('select', 'etapa', get_string('etapa','block_lvs'), $etapa);
        

		$minimo_mensagens = Forumlv::$fatorMultiplicativo;
		$mform->addElement('select', 'fator_multiplicativo', get_string('minimo_mensagens','block_lvs'), $minimo_mensagens);
		$mform->setDefault('fator_multiplicativo', 3);

		$mform->addElement('checkbox', 'exibir', 'Exibir LV');
		$mform->setDefault('exibir', 1);
		
		
        
		$mform->removeElement('assessed');
        
        //@lvs tipo de agregação ficará como campo oculto
		//@lvs tipo de agregação padrão será RATING_AGGREGATE_NONE 0 que será alterado pela função data_preprocessing (mod_form.php - forumlv) para 2 RATING_AGGREGATE_COUNT
		$mform->addElement('hidden', 'assessed', 0);
        $mform->setType('assessed', PARAM_INT);
		
		$mform->removeElement('scale');
		//@lvs a escala a ser utilizada será a de 0 a 100
		$mform->addElement('select', 'scale', get_string('escala_notas','block_lvs'), array(100 => get_string('scalelvs', 'block_lvs')));
		$mform->insertElementBefore($mform->removeElement('scale', false), 'etapa');
		
		$mform->insertElementBefore($mform->removeElement('ratingtime', false), 'etapa');
		$mform->insertElementBefore($mform->removeElement('assesstimestart', false), 'etapa');
		$mform->insertElementBefore($mform->removeElement('assesstimefinish', false), 'etapa');		
		
	}
	
	public function  add_header_lv_tarefalv(&$mform) {
		global $COURSE, $CFG;
        //@lvs Verificar se a confiruração dos lvs já foram feitas
        $cursolv = new Moodle2CursoLv($COURSE -> id);
        if (!$cursolv -> getConfiguracao()) {
            redirect("$CFG->wwwroot/blocks/lvs/pages/config_cursolv.php?curso=$COURSE->id", "Preencha as informações do curso !");
        }	
		
		$mform->removeElement('grade');
		$mform->addElement('header', 'lvs', get_string('scalelvs', 'block_lvs'));
        $mform->setExpanded('lvs');
		
		$scale[] = get_string('scalelvs', 'block_lvs');
		$mform->addElement('select', 'grade', get_string('grade'), $scale);
		
		for($ietapa=1;$ietapa<=10;$ietapa++){
			$etapa[$ietapa] = $ietapa;
		}
		$mform->addElement('select', 'etapa', get_string('etapa','block_lvs'), $etapa);
		
		$mform->addElement('checkbox', 'exibir', 'Exibir LV');
		$mform->setDefault('exibir', 1);
	}
	
    public function add_header_lv_chatlv(&$mform){
    	global $COURSE, $CFG;
        //@lvs Verificar se a confiruração dos lvs já foram feitas
        $cursolv = new Moodle2CursoLv($COURSE -> id);
        if (!$cursolv -> getConfiguracao()) {
            redirect("$CFG->wwwroot/blocks/lvs/pages/config_cursolv.php?curso=$COURSE->id", "Preencha as informações do curso !");
        }
        
        $mform->addElement('header', 'lvs', get_string('scalelvs', 'block_lvs'));
        $mform->setExpanded('lvs');
        
        $scale[] = get_string('scalelvs', 'block_lvs');
        $mform->addElement('select', 'grade', get_string('escala_notas','block_lvs'), $scale);
        
        for($ietapa=1;$ietapa<=10;$ietapa++){
            $etapa[$ietapa] = $ietapa;
        }
        $mform->addElement('select', 'etapa', get_string('etapa','block_lvs'), $etapa);
        
        $minimo_mensagens = array(1=>6, 2=>3);
        $mform->addElement('select', 'fator_multiplicativo', get_string('minimo_mensagens','block_lvs'), $minimo_mensagens);
        $mform->setDefault('fator_multiplicativo', 3);
        
        $mform->addElement('checkbox', 'exibir', 'Exibir LV');
        $mform->setDefault('exibir', 1);
    }
    
    public function  add_header_lv_wikilv(&$mform){
    	global $COURSE, $CFG;
        //@lvs Verificar se a confiruração dos lvs já foram feitas
        $cursolv = new Moodle2CursoLv($COURSE -> id);
        if (!$cursolv -> getConfiguracao()) {
            redirect("$CFG->wwwroot/blocks/lvs/pages/config_cursolv.php?curso=$COURSE->id", "Preencha as informações do curso !");
        }
		
    	$mform->addElement('header', 'lvs', get_string('scalelvs', 'block_lvs'));
        $mform->setExpanded('lvs');
    	
    	for($ietapa=1;$ietapa<=10;$ietapa++){
    		$etapa[$ietapa] = $ietapa;
    	}
    	$mform->addElement('select', 'etapa', get_string('etapa','block_lvs'), $etapa);
    		
    	$mform->addElement('checkbox', 'exibir', 'Exibir LV');
    	$mform->setDefault('exibir', 1);
    		
    	$minimo_contribuicoes = array('0.5'=>5, '1'=>4, '1.5'=>3, '2'=>2, '2.5'=>1,'3'=>0);
    	$mform->addElement('select', 'fator_multiplicativo', get_string('minimo_contribuicoes', 'wikilv') , $minimo_contribuicoes);
    	$mform->setDefault('fator_multiplicativo', 3);
    	
    	$mform->addElement('checkbox', 'ratingtime', get_string('ratingtime', 'wikilv'));
    	$mform->disabledIf('ratingtime', 'fator_multiplicativo', 'eq', 3);
    	
    	$mform->addElement('date_time_selector', 'assesstimestart', get_string('from'));
    	$mform->disabledIf('assesstimestart', 'ratingtime');
    	$mform->disabledIf('assesstimestart', 'fator_multiplicativo', 'eq', 3);
    	
    	$mform->addElement('date_time_selector', 'assesstimefinish', get_string('to'));
    	$mform->disabledIf('assesstimefinish', 'ratingtime');
    	$mform->disabledIf('assesstimefinish', 'fator_multiplicativo', 'eq', 3);
    
    }
    
}
?>