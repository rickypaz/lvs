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
 * This file contains all necessary code to view a diff page
 *
 * @package mod-wikilv-2.0
 * @copyrigth 2009 Marc Alier, Jordi Piguillem marc.alier@upc.edu
 * @copyrigth 2009 Universitat Politecnica de Catalunya http://www.upc.edu
 *
 * @author Jordi Piguillem
 * @author Marc Alier
 * @author David Jimenez
 * @author Josep Arus
 * @author Kenneth Riba
 *
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// @lvs diff lvs wikilv
use uab\ifce\lvs\moodle2\business\Wikilv;
use uab\ifce\lvs\avaliacao\NotasLvFactory;
use uab\ifce\lvs\business\Item;


require_once('../../config.php');

require_once($CFG->dirroot . '/mod/wikilv/lib.php');
require_once($CFG->dirroot . '/mod/wikilv/locallib.php');
require_once($CFG->dirroot . '/mod/wikilv/pagelib.php');

require_once($CFG->dirroot . '/mod/wikilv/diff/difflib.php');
require_once($CFG->dirroot . '/mod/wikilv/diff/diff_nwikilv.php');

$pageid = required_param('pageid', PARAM_TEXT);
$compare = required_param('compare', PARAM_INT);
$comparewith = required_param('comparewith', PARAM_INT);

if (!$page = wikilv_get_page($pageid)) {
    print_error('incorrectpageid', 'wikilv');
}

if (!$subwikilv = wikilv_get_subwikilv($page->subwikilvid)) {
    print_error('incorrectsubwikilvid', 'wikilv');
}

if (!$wikilv = wikilv_get_wikilv($subwikilv->wikilvid)) {
    print_error('incorrectwikilvid', 'wikilv');
}

if (!$cm = get_coursemodule_from_instance('wikilv', $wikilv->id)) {
    print_error('invalidcoursemodule');
}

$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);

if ($compare >= $comparewith) {
    print_error("A page version can only be compared with an older version.");
}

require_login($course, true, $cm);

$wikilvpage = new page_wikilv_diff($wikilv, $subwikilv, $cm);

$wikilvpage->set_page($page);
$wikilvpage->set_comparison($compare, $comparewith);

add_to_log($course->id, "wikilv", "diff", "diff.php?pageid=".$pageid."&comparewith=".$comparewith."&compare=".$compare, $pageid, $cm->id);

$wikilvpage->print_header();

/** @lvs exibindo avaliação atual e form de avaliação de uma versão no wikilv */
if($comparewith == ($compare+1)) {
	$version = wikilv_get_wikilv_page_version($page->id, $comparewith);
	unset($version->content);

	$item = new Item('wikilv', 'version', $version);

	$gerenciadorNotas = NotasLvFactory::criarGerenciador('moodle2');
	$gerenciadorNotas->setModulo( new Wikilv($wikilv->id) );

	echo $gerenciadorNotas->avaliacaoAtual($item);
	echo $gerenciadorNotas->avaliadoPor($item);
	echo $gerenciadorNotas->formAvaliacao($item);
}
// fim lvs

$wikilvpage->print_content();

$wikilvpage->print_footer();
