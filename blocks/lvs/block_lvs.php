<?php
require_once("pages/Template.class.php");

class block_lvs extends block_list {

	function init() {
		$this->title = 'Notas LV';//get_string('pluginname', 'block_lv');
	}

	function get_content() {
		global $CFG, $OUTPUT, $USER;

		if (empty($this->instance)) {
			$this->content = '';
			return $this->content;
		}

		//@LVS MENU MODULO LV
		$this->content = new stdClass();
		$this->content->items = array();
		$this->content->icons = array();
		$this->content->footer = '';

		/// MDL-13252 Always get the course context or else the context may be incorrect in the user/index.php
		$currentcontext = $this->page->context;

// 		$this->page->requires->js('/blocks/lvs/js/jquery.tools.min.js',true);
		$this->page->requires->css('/blocks/lvs/css/module.css',true);

		$template = $this->init_template();

// 		$this->page->requires->js('/blocks/lvs/js/module.js');
		$this->content->items[]= $template;

		return $this->content;
	}

	// my moodle can only have SITEID and it's redundant here, so take it away
	function applicable_formats() {
		return array('all' => true, 'my' => false, 'tag' => false);
	}

	function init_template() {
		global $CFG, $USER;
		$template = new Template("$CFG->dirroot/blocks/lvs/pages/html/block_lvs.html");

		$template->ROOT = $CFG->wwwroot;
		$template->COURSE_ID = $this->page->course->id;
		$template->USER_ID = $USER->id;
		//$template->TAMANHO_IMAGEM = '32';

		if(has_capability('moodle/site:config', $this->page->context)){
			$template->block("VISAO_ADMIN");
		}
		
		if(has_capability('moodle/course:viewhiddenactivities', $this->page->context)) {
			$template->block("VISAO_COORDENADOR");
		} else {
			$template->block("VISAO_ESTUDANTE");
		}

		return $template->parse();
	}

}