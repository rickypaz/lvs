<?php
if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}

require_once ($CFG->dirroot.'/course/moodleform_mod.php');

/** @LVs dependências mod_form tarefalv */
use uab\ifce\lvs\forms\FormModulosLV;
// fim das dependências

class mod_tarefalv_mod_form extends moodleform_mod {
    protected $_tarefalvinstance = null;

    function definition() {
        global $CFG, $DB, $PAGE;
        $mform =& $this->_form;

        // this hack is needed for different settings of each subtype
        if (!empty($this->_instance)) {
            if($ass = $DB->get_record('tarefalv', array('id'=>$this->_instance))) {
                $type = $ass->tarefalvtype;
            } else {
                print_error('invalidtarefalv', 'tarefalv');
            }
        } else {
            $type = required_param('type', PARAM_ALPHA);
        }
        $mform->addElement('hidden', 'tarefalvtype', $type);
        $mform->setType('tarefalvtype', PARAM_ALPHA);
        $mform->setDefault('tarefalvtype', $type);
        $mform->addElement('hidden', 'type', $type);
        $mform->setType('type', PARAM_ALPHA);
        $mform->setDefault('type', $type);

        require_once($CFG->dirroot.'/mod/tarefalv/type/'.$type.'/tarefalv.class.php');
        $tarefalvclass = 'tarefalv_'.$type;
        $tarefalvinstance = new $tarefalvclass();

//-------------------------------------------------------------------------------
        $mform->addElement('header', 'general', get_string('general', 'form'));

//        $mform->addElement('static', 'statictype', get_string('tarefalvtype', 'tarefalv'), get_string('type'.$type,'tarefalv'));

        $mform->addElement('text', 'name', get_string('tarefalvname', 'tarefalv'), array('size'=>'64'));
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEANHTML);
        }
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $this->add_intro_editor(true, get_string('description', 'tarefalv'));

        $mform->addElement('date_time_selector', 'timeavailable', get_string('availabledate', 'tarefalv'), array('optional'=>true));
        $mform->setDefault('timeavailable', time());
        $mform->addElement('date_time_selector', 'timedue', get_string('duedate', 'tarefalv'), array('optional'=>true));
        $mform->setDefault('timedue', time()+7*24*3600);

        $ynoptions = array( 0 => get_string('no'), 1 => get_string('yes'));

        if ($tarefalvinstance->supports_lateness()) {
            $mform->addElement('select', 'preventlate', get_string('preventlate', 'tarefalv'), $ynoptions);
            $mform->setDefault('preventlate', 0);
        }

        // hack to support pluggable tarefalv type titles
        if (get_string_manager()->string_exists('type'.$type, 'tarefalv')) {
            $typetitle = get_string('type'.$type, 'tarefalv');
        } else {
            $typetitle  = get_string('type'.$type, 'tarefalv_'.$type);
        }

        $this->standard_grading_coursemodule_elements();
        

        $mform->addElement('header', 'typedesc', $typetitle);

        $tarefalvinstance->setup_elements($mform);

        $this->standard_coursemodule_elements();
        
        /** @lvs inputs lvs */
        $formlv = new FormModulosLV(); //@lvs adicionado campos essenciais para o fórum lv
        $formlv->add_header_lv_tarefalv($mform);
        /** @lvs end */

        $this->add_action_buttons();

        // Add warning popup/noscript tag, if grades are changed by user.
        if ($mform->elementExists('grade') && !empty($this->_instance) && $DB->record_exists_select('tarefalv_submissions', 'tarefalv = ? AND grade <> -1', array($this->_instance))) {
            $module = array(
                'name' => 'mod_tarefalv',
                'fullpath' => '/mod/tarefalv/tarefalv.js',
                'requires' => array('node', 'event'),
                'strings' => array(array('changegradewarning', 'mod_tarefalv'))
                );
            $PAGE->requires->js_init_call('M.mod_tarefalv.init_grade_change', null, false, $module);

            // Add noscript tag in case
            $noscriptwarning = $mform->createElement('static', 'warning', null,  html_writer::tag('noscript', get_string('changegradewarning', 'mod_tarefalv')));
            $mform->insertElementBefore($noscriptwarning, 'grade');
        }
    }

    // Needed by plugin tarefalv types if they include a filemanager element in the settings form
    function has_instance() {
        return ($this->_instance != NULL);
    }

    // Needed by plugin tarefalv types if they include a filemanager element in the settings form
    function get_context() {
        return $this->context;
    }

    protected function get_tarefalv_instance() {
        global $CFG, $DB;

        if ($this->_tarefalvinstance) {
            return $this->_tarefalvinstance;
        }
        if (!empty($this->_instance)) {
            if($ass = $DB->get_record('tarefalv', array('id'=>$this->_instance))) {
                $type = $ass->tarefalvtype;
            } else {
                print_error('invalidtarefalv', 'tarefalv');
            }
        } else {
            $type = required_param('type', PARAM_ALPHA);
        }
        require_once($CFG->dirroot.'/mod/tarefalv/type/'.$type.'/tarefalv.class.php');
        $tarefalvclass = 'tarefalv_'.$type;
        $this->tarefalvinstance = new $tarefalvclass();
        return $this->tarefalvinstance;
    }


    function data_preprocessing(&$default_values) {
        // Allow plugin tarefalv types to preprocess form data (needed if they include any filemanager elements)
        $this->get_tarefalv_instance()->form_data_preprocessing($default_values, $this);
    }


    function validation($data, $files) {
        // Allow plugin tarefalv types to do any extra validation after the form has been submitted
        $errors = parent::validation($data, $files);
        $errors = array_merge($errors, $this->get_tarefalv_instance()->form_validation($data, $files));
        return $errors;
    }
}

