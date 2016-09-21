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
 * Wikilv files management
 *
 * @package mod_wikilv
 * @copyright 2011 Dongsheng Cai <dongsheng@moodle.com>
 *
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->dirroot . '/mod/wikilv/lib.php');
require_once($CFG->dirroot . '/mod/wikilv/locallib.php');

$pageid       = required_param('pageid', PARAM_INT); // Page ID
$wid          = optional_param('wid', 0, PARAM_INT); // Wikilv ID
$currentgroup = optional_param('group', 0, PARAM_INT); // Group ID
$userid       = optional_param('uid', 0, PARAM_INT); // User ID
$groupanduser = optional_param('groupanduser', null, PARAM_TEXT);

if (!$page = wikilv_get_page($pageid)) {
    print_error('incorrectpageid', 'wikilv');
}

if ($groupanduser) {
    list($currentgroup, $userid) = explode('-', $groupanduser);
    $currentgroup = clean_param($currentgroup, PARAM_INT);
    $userid       = clean_param($userid, PARAM_INT);
}

if ($wid) {
    // in group mode
    if (!$wikilv = wikilv_get_wikilv($wid)) {
        print_error('incorrectwikilvid', 'wikilv');
    }
    if (!$subwikilv = wikilv_get_subwikilv_by_group($wikilv->id, $currentgroup, $userid)) {
        // create subwikilv if doesn't exist
        $subwikilvid = wikilv_add_subwikilv($wikilv->id, $currentgroup, $userid);
        $subwikilv = wikilv_get_subwikilv($subwikilvid);
    }
} else {
    // no group
    if (!$subwikilv = wikilv_get_subwikilv($page->subwikilvid)) {
        print_error('incorrectsubwikilvid', 'wikilv');
    }

    // Checking wikilv instance of that subwikilv
    if (!$wikilv = wikilv_get_wikilv($subwikilv->wikilvid)) {
        print_error('incorrectwikilvid', 'wikilv');
    }
}

// Checking course module instance
if (!$cm = get_coursemodule_from_instance("wikilv", $subwikilv->wikilvid)) {
    print_error('invalidcoursemodule');
}

// Checking course instance
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);

$context = context_module::instance($cm->id);


$PAGE->set_url('/mod/wikilv/files.php', array('pageid'=>$pageid));
require_login($course, true, $cm);

if (!wikilv_user_can_view($subwikilv, $wikilv)) {
    print_error('cannotviewfiles', 'wikilv');
}

$PAGE->set_title(get_string('wikilvfiles', 'wikilv'));
$PAGE->set_heading($course->fullname);
$PAGE->navbar->add(format_string(get_string('wikilvfiles', 'wikilv')));
echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($wikilv->name));
echo $OUTPUT->box(format_module_intro('wikilv', $wikilv, $PAGE->cm->id), 'generalbox', 'intro');

$renderer = $PAGE->get_renderer('mod_wikilv');

$tabitems = array('view' => 'view', 'edit' => 'edit', 'comments' => 'comments', 'history' => 'history', 'map' => 'map', 'files' => 'files', 'admin' => 'admin');

$options = array('activetab'=>'files');
echo $renderer->tabs($page, $tabitems, $options);


echo $OUTPUT->box_start('generalbox');
echo $renderer->wikilv_print_subwikilv_selector($PAGE->activityrecord, $subwikilv, $page, 'files');
echo $renderer->wikilv_files_tree($context, $subwikilv);
echo $OUTPUT->box_end();

if (has_capability('mod/wikilv:managefiles', $context)) {
    echo $OUTPUT->single_button(new moodle_url('/mod/wikilv/filesedit.php', array('subwikilv'=>$subwikilv->id, 'pageid'=>$pageid)), get_string('editfiles', 'wikilv'), 'get');
}
echo $OUTPUT->footer();
