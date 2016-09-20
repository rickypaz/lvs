<?php

// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.

/**
 * This file defines de main wikilv configuration form
 *
 * @package mod_wikilv
 * @copyright 2009 Marc Alier, Jordi Piguillem marc.alier@upc.edu
 * @copyright 2009 Universitat Politecnica de Catalunya http://www.upc.edu
 *
 * @author Jordi Piguillem
 * @author Marc Alier
 * @author David Jimenez
 * @author Josep Arus
 * @author Kenneth Riba
 *
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
/** @LVs dependências mod_form wikilv */
use uab\ifce\lvs\forms\FormModulosLV;
// fim das dependências

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}

require_once('moodleform_mod.php');
require_once($CFG->dirroot . '/mod/wikilv/locallib.php');
require_once($CFG->dirroot . '/lib/datalib.php');

class mod_wikilv_mod_form extends moodleform_mod {

    protected function definition() {
        $mform = $this->_form;
        $required = get_string('required');

        //-------------------------------------------------------------------------------
        // Adding the "general" fieldset, where all the common settings are shown.
        $mform->addElement('header', 'general', get_string('general', 'form'));

        // Adding the standard "name" field.
        $mform->addElement('text', 'name', get_string('wikilvname', 'wikilv'), array('size' => '64'));
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', $required, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        // Adding the optional "intro" and "introformat" pair of fields
        $this->standard_intro_elements(get_string('wikilvintro', 'wikilv'));

        $wikilvmodeoptions = array ('collaborative' => get_string('wikilvmodecollaborative', 'wikilv'), 'individual' => get_string('wikilvmodeindividual', 'wikilv'));
        // Don't allow changes to the wikilv type once it is set.
        $wikilvtype_attr = array();
        if (!empty($this->_instance)) {
            $wikilvtype_attr['disabled'] = 'disabled';
        }
        $mform->addElement('select', 'wikilvmode', get_string('wikilvmode', 'wikilv'), $wikilvmodeoptions, $wikilvtype_attr);
        $mform->addHelpButton('wikilvmode', 'wikilvmode', 'wikilv');

        $attr = array('size' => '20');
        if (!empty($this->_instance)) {
            $attr['disabled'] = 'disabled';
        }
        $mform->addElement('text', 'firstpagetitle', get_string('firstpagetitle', 'wikilv'), $attr);
        $mform->addHelpButton('firstpagetitle', 'firstpagetitle', 'wikilv');
        $mform->setType('firstpagetitle', PARAM_TEXT);
        if (empty($this->_instance)) {
            $mform->addRule('firstpagetitle', $required, 'required', null, 'client');
        }

        // Format.
        $mform->addElement('header', 'wikilvfieldset', get_string('format'));

        $formats = wikilv_get_formats();
        $editoroptions = array();
        foreach ($formats as $format) {
            $editoroptions[$format] = get_string($format, 'wikilv');
        }
        $mform->addElement('select', 'defaultformat', get_string('defaultformat', 'wikilv'), $editoroptions);
        $mform->addHelpButton('defaultformat', 'defaultformat', 'wikilv');

        $mform->addElement('checkbox', 'forceformat', get_string('forceformat', 'wikilv'));
        $mform->addHelpButton('forceformat', 'forceformat', 'wikilv');

        //-------------------------------------------------------------------------------
        // Add standard elements, common to all modules.
        $this->standard_coursemodule_elements();

        /** @lvs inputs lvs */
        $formlv = new FormModulosLV(); //@lvs adicionado campos essenciais para o fórum lv
        $formlv->add_header_lv_wikilv($mform);
        /** @lvs end */
        //-------------------------------------------------------------------------------
        // Add standard buttons, common to all modules.
        $this->add_action_buttons();

    }
    
    // @lvs
    function data_preprocessing(&$default_values){
    	if(isset($default_values['fator_multiplicativo']) &&  $default_values['fator_multiplicativo'] == 3) {
    		$default_values['ratingtime'] = $default_values['assesstimestart'] = $default_values['assesstimefinish'] = 0;
    	}
    	 
    	if(isset($default_values['assesstimestart']) &&  isset($default_values['assesstimefinish']))
    		$default_values['ratingtime'] = ($default_values['assesstimestart'] && $default_values['assesstimefinish']) ? 1 : 0;
    }
}
