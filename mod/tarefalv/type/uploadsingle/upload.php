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
 *
 * @package   mod-tarefalv
 * @copyright 2010 Dongsheng Cai <dongsheng@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(dirname(dirname(__FILE__))))).'/config.php');
require_once(dirname(__FILE__).'/upload_form.php');
require_once(dirname(__FILE__).'/tarefalv.class.php');
require_once("$CFG->dirroot/repository/lib.php");

$contextid = required_param('contextid', PARAM_INT);
$id = optional_param('id', null, PARAM_INT);

$formdata = new stdClass();
$formdata->userid = required_param('userid', PARAM_INT);
$formdata->offset = optional_param('offset', null, PARAM_INT);
$formdata->forcerefresh = optional_param('forcerefresh', null, PARAM_INT);
$formdata->mode = optional_param('mode', null, PARAM_ALPHA);

$url = new moodle_url('/mod/tarefalv/type/uploadsingle/upload.php',  array('contextid'=>$contextid,
                            'id'=>$id,'offset'=>$formdata->offset,'forcerefresh'=>$formdata->forcerefresh,'userid'=>$formdata->userid,'mode'=>$formdata->mode));

list($context, $course, $cm) = get_context_info_array($contextid);

if (!$tarefalv = $DB->get_record('tarefalv', array('id'=>$cm->instance))) {
    print_error('invalidid', 'tarefalv');
}

require_login($course, true, $cm);
if (isguestuser()) {
    die();
}
$instance = new tarefalv_uploadsingle($cm->id, $tarefalv, $cm, $course);

$fullname = format_string($course->fullname, true, array('context' => context_course::instance($course->id)));

$PAGE->set_url($url);
$PAGE->set_context($context);
$title = strip_tags($fullname.': '.get_string('modulename', 'tarefalv').': '.format_string($tarefalv->name,true));
$PAGE->set_title($title);
$PAGE->set_heading($title);

$options = array('subdirs'=>0, 'maxbytes'=>get_max_upload_file_size($CFG->maxbytes, $course->maxbytes, $tarefalv->maxbytes), 'maxfiles'=>1, 'accepted_types'=>'*', 'return_types'=>FILE_INTERNAL);

    $mform = new mod_tarefalv_uploadsingle_form(null, array('contextid'=>$contextid, 'userid'=>$formdata->userid, 'options'=>$options));

if ($mform->is_cancelled()) {
        redirect(new moodle_url('/mod/tarefalv/view.php', array('id'=>$cm->id)));
} else if ($mform->get_data()) {
    $instance->upload($mform);
    die();
//    redirect(new moodle_url('/mod/tarefalv/view.php', array('id'=>$cm->id)));
}

echo $OUTPUT->header();
echo $OUTPUT->box_start('generalbox');
$mform->display();
echo $OUTPUT->box_end();
echo $OUTPUT->footer();
