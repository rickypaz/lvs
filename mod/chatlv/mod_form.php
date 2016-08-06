<?php
if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}

require_once ($CFG->dirroot.'/course/moodleform_mod.php');
use uab\ifce\lvs\forms\FormModulosLV;

class mod_chatlv_mod_form extends moodleform_mod {

    function definition() {
        global $CFG;

        $mform = $this->_form;

//-------------------------------------------------------------------------------
        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('chatlvname', 'chatlv'), array('size'=>'64'));
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEANHTML);
        }
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $this->add_intro_editor(true, get_string('chatlvintro', 'chatlv'));

        // Chat sessions.
        $mform->addElement('header', 'sessionshdr', get_string('sessions', 'chatlv'));

        $mform->addElement('date_time_selector', 'chatlvtime', get_string('chatlvtime', 'chatlv'));

        $options=array();
        $options[0]  = get_string('donotusechatlvtime', 'chatlv');
        $options[1]  = get_string('repeatnone', 'chatlv');
        $options[2]  = get_string('repeatdaily', 'chatlv');
        $options[3]  = get_string('repeatweekly', 'chatlv');
        $mform->addElement('select', 'schedule', get_string('repeattimes', 'chatlv'), $options);

        $options=array();
        $options[0]    = get_string('neverdeletemessages', 'chatlv');
        $options[365]  = get_string('numdays', '', 365);
        $options[180]  = get_string('numdays', '', 180);
        $options[150]  = get_string('numdays', '', 150);
        $options[120]  = get_string('numdays', '', 120);
        $options[90]   = get_string('numdays', '', 90);
        $options[60]   = get_string('numdays', '', 60);
        $options[30]   = get_string('numdays', '', 30);
        $options[21]   = get_string('numdays', '', 21);
        $options[14]   = get_string('numdays', '', 14);
        $options[7]    = get_string('numdays', '', 7);
        $options[2]    = get_string('numdays', '', 2);
        $mform->addElement('select', 'keepdays', get_string('savemessages', 'chatlv'), $options);

        $mform->addElement('selectyesno', 'studentlogs', get_string('studentseereports', 'chatlv'));
        $mform->addHelpButton('studentlogs', 'studentseereports', 'chatlv');

        $this->standard_coursemodule_elements();
        $formlv = new FormModulosLV(); //@lvs adicionado campos essenciais para o fórum lv
        $formlv->add_header_lv_chatlv($mform);

        $this->add_action_buttons();
    }
}
