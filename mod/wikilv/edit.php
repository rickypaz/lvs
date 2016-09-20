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
 * This file contains all necessary code to edit a wikilv page
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

require_once('../../config.php');

require_once($CFG->dirroot . '/mod/wikilv/lib.php');
require_once($CFG->dirroot . '/mod/wikilv/locallib.php');
require_once($CFG->dirroot . '/mod/wikilv/pagelib.php');

$pageid = required_param('pageid', PARAM_INT);
$contentformat = optional_param('contentformat', '', PARAM_ALPHA);
$option = optional_param('editoption', '', PARAM_TEXT);
$section = optional_param('section', "", PARAM_RAW);
$version = optional_param('version', -1, PARAM_INT);
$attachments = optional_param('attachments', 0, PARAM_INT);
$deleteuploads = optional_param('deleteuploads', 0, PARAM_RAW);

$newcontent = '';
if (!empty($newcontent) && is_array($newcontent)) {
    $newcontent = $newcontent['text'];
}

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

if (!empty($section) && !$sectioncontent = wikilv_get_section_page($page, $section)) {
    print_error('invalidsection', 'wikilv');
}

require_login($course, true, $cm);

$context = context_module::instance($cm->id);

if (!wikilv_user_can_edit($subwikilv)) {
    print_error('cannoteditpage', 'wikilv');
}

if ($option == get_string('save', 'wikilv')) {
    if (!confirm_sesskey()) {
        print_error(get_string('invalidsesskey', 'wikilv'));
    }
    $wikilvpage = new page_wikilv_save($wikilv, $subwikilv, $cm);
    $wikilvpage->set_page($page);
    $wikilvpage->set_newcontent($newcontent);
    $wikilvpage->set_upload(true);
} else {
    if ($option == get_string('preview')) {
        if (!confirm_sesskey()) {
            print_error(get_string('invalidsesskey', 'wikilv'));
        }
        $wikilvpage = new page_wikilv_preview($wikilv, $subwikilv, $cm);
        $wikilvpage->set_page($page);
    } else {
        if ($option == get_string('cancel')) {
            //delete lock
            wikilv_delete_locks($page->id, $USER->id, $section);

            redirect($CFG->wwwroot . '/mod/wikilv/view.php?pageid=' . $pageid);
        } else {
            $wikilvpage = new page_wikilv_edit($wikilv, $subwikilv, $cm);
            $wikilvpage->set_page($page);
            $wikilvpage->set_upload($option == get_string('upload', 'wikilv'));
        }
    }

    if (has_capability('mod/wikilv:overridelock', $context)) {
        $wikilvpage->set_overridelock(true);
    }
}

if ($version >= 0) {
    $wikilvpage->set_versionnumber($version);
}

if (!empty($section)) {
    $wikilvpage->set_section($sectioncontent, $section);
}

if (!empty($attachments)) {
    $wikilvpage->set_attachments($attachments);
}

if (!empty($deleteuploads)) {
    $wikilvpage->set_deleteuploads($deleteuploads);
}

if (!empty($contentformat)) {
    $wikilvpage->set_format($contentformat);
}

$wikilvpage->print_header();

$wikilvpage->print_content();

$wikilvpage->print_footer();
