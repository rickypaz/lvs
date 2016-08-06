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
 * Redirect the user to the appropriate submission related page within /mod/tarefalv
 *
 * Based on the supplied parameters and the user's capabilities the user will be redirected
 * to either their own submission, a particular student's submission or a summary of all submissions
 *
 * @package   mod_tarefalv
 * @category  grade
 * @copyright 1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once("../../config.php");

$id   = required_param('id', PARAM_INT);          // Course module ID
$userid = optional_param('userid', 0, PARAM_INT); // Graded user ID (optional)

$PAGE->set_url('/mod/tarefalv/grade.php', array('id'=>$id));
if (! $cm = get_coursemodule_from_id('tarefalv', $id)) {
    print_error('invalidcoursemodule');
}

if (! $tarefalv = $DB->get_record("tarefalv", array("id"=>$cm->instance))) {
    print_error('invalidid', 'tarefalv');
}

if (! $course = $DB->get_record("course", array("id"=>$tarefalv->course))) {
    print_error('coursemisconf', 'tarefalv');
}

require_login($course, false, $cm);

if (has_capability('mod/tarefalv:grade', context_module::instance($cm->id))) {
    if ($userid) {
        redirect('submissions.php?id='.$cm->id.'&userid='.$userid.'&mode=single&filter=0&offset=0');
    } else {
        redirect('submissions.php?id='.$cm->id);
    }
} else {
    // user will view his own submission, parameter $userid is ignored
    redirect('view.php?id='.$cm->id);
}
