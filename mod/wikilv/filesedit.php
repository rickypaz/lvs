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
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Manage files in wikilv
 *
 * @package   mod_wikilv
 * @copyright 2011 Dongsheng Cai <dongsheng@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once('lib.php');
require_once('locallib.php');
require_once("$CFG->dirroot/mod/wikilv/filesedit_form.php");
require_once("$CFG->dirroot/repository/lib.php");

$subwikilvid = required_param('subwikilv', PARAM_INT);
// not being used for file management, we use it to generate navbar link
$pageid    = optional_param('pageid', 0, PARAM_INT);
$returnurl = optional_param('returnurl', '', PARAM_LOCALURL);

if (!$subwikilv = wikilv_get_subwikilv($subwikilvid)) {
    print_error('incorrectsubwikilvid', 'wikilv');
}

// Checking wikilv instance of that subwikilv
if (!$wikilv = wikilv_get_wikilv($subwikilv->wikilvid)) {
    print_error('incorrectwikilvid', 'wikilv');
}

// Checking course module instance
if (!$cm = get_coursemodule_from_instance("wikilv", $subwikilv->wikilvid)) {
    print_error('invalidcoursemodule');
}

// Checking course instance
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);

$context = context_module::instance($cm->id);

require_login($course, true, $cm);

if (!wikilv_user_can_view($subwikilv, $wikilv)) {
    print_error('cannotviewpage', 'wikilv');
}
require_capability('mod/wikilv:managefiles', $context);

if (empty($returnurl)) {
    $referer = get_local_referer(false);
    if (!empty($referer)) {
        $returnurl = $referer;
    } else {
        $returnurl = new moodle_url('/mod/wikilv/files.php', array('subwikilv' => $subwikilv->id, 'pageid' => $pageid));
    }
}

$title = get_string('editfiles', 'wikilv');

$struser = get_string('user');
$url = new moodle_url('/mod/wikilv/filesedit.php', array('subwikilv'=>$subwikilv->id, 'pageid'=>$pageid));
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_title($title);
$PAGE->set_heading($course->fullname);
$PAGE->navbar->add(format_string(get_string('wikilvfiles', 'wikilv')), $CFG->wwwroot . '/mod/wikilv/files.php?pageid=' . $pageid);
$PAGE->navbar->add(format_string($title));

$data = new stdClass();
$data->returnurl = $returnurl;
$data->subwikilvid = $subwikilv->id;
$maxbytes = get_max_upload_file_size($CFG->maxbytes, $COURSE->maxbytes);
$options = array('subdirs'=>0, 'maxbytes'=>$maxbytes, 'maxfiles'=>-1, 'accepted_types'=>'*', 'return_types'=>FILE_INTERNAL | FILE_REFERENCE);
file_prepare_standard_filemanager($data, 'files', $options, $context, 'mod_wikilv', 'attachments', $subwikilv->id);

$mform = new mod_wikilv_filesedit_form(null, array('data'=>$data, 'options'=>$options));

if ($mform->is_cancelled()) {
    redirect($returnurl);
} else if ($formdata = $mform->get_data()) {
    $formdata = file_postupdate_standard_filemanager($formdata, 'files', $options, $context, 'mod_wikilv', 'attachments', $subwikilv->id);
    redirect($returnurl);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($wikilv->name));
echo $OUTPUT->box(format_module_intro('wikilv', $wikilv, $PAGE->cm->id), 'generalbox', 'intro');
echo $OUTPUT->box_start('generalbox');
$mform->display();
echo $OUTPUT->box_end();
echo $OUTPUT->footer();
