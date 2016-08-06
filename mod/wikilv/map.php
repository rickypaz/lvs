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
 * This file contains all necessary code to view the navigation tab
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

require_once('../../config.php');
require_once($CFG->dirroot . '/mod/wikilv/lib.php');
require_once($CFG->dirroot . '/mod/wikilv/locallib.php');
require_once($CFG->dirroot . '/mod/wikilv/pagelib.php');

$pageid = required_param('pageid', PARAM_INT); // Page ID
$option = optional_param('option', 0, PARAM_INT); // Option ID

if (!$page = wikilv_get_page($pageid)) {
    print_error('incorrectpageid', 'wikilv');
}
if (!$subwikilv = wikilv_get_subwikilv($page->subwikilvid)) {
    print_error('incorrectsubwikilvid', 'wikilv');
}
if (!$cm = get_coursemodule_from_instance("wikilv", $subwikilv->wikilvid)) {
    print_error('invalidcoursemodule');
}
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
if (!$wikilv = wikilv_get_wikilv($subwikilv->wikilvid)) {
    print_error('incorrectwikilvid', 'wikilv');
}

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/wikilv:viewpage', $context);

$wikilvpage = new page_wikilv_map($wikilv, $subwikilv, $cm);
add_to_log($course->id, "wikilv", "map", "map.php?pageid=".$pageid, $pageid, $cm->id);

// Print page header
$wikilvpage->set_view($option);
$wikilvpage->set_page($page);
$wikilvpage->print_header();
$wikilvpage->print_content();

$wikilvpage->print_footer();
