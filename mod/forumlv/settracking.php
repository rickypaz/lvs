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
 * Set tracking option for the forumlv.
 *
 * @package mod-forumlv
 * @copyright 2005 mchurch
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once("../../config.php");
require_once("lib.php");

$id         = required_param('id',PARAM_INT);                           // The forumlv to subscribe or unsubscribe to
$returnpage = optional_param('returnpage', 'index.php', PARAM_FILE);    // Page to return to.

$url = new moodle_url('/mod/forumlv/settracking.php', array('id'=>$id));
if ($returnpage !== 'index.php') {
    $url->param('returnpage', $returnpage);
}
$PAGE->set_url($url);

if (! $forumlv = $DB->get_record("forumlv", array("id" => $id))) {
    print_error('invalidforumlvid', 'forumlv');
}

if (! $course = $DB->get_record("course", array("id" => $forumlv->course))) {
    print_error('invalidcoursemodule');
}

if (! $cm = get_coursemodule_from_instance("forumlv", $forumlv->id, $course->id)) {
    print_error('invalidcoursemodule');
}

require_course_login($course, false, $cm);

$returnto = forumlv_go_back_to($returnpage.'?id='.$course->id.'&f='.$forumlv->id);

if (!forumlv_tp_can_track_forumlvs($forumlv)) {
    redirect($returnto);
}

$info = new stdClass();
$info->name  = fullname($USER);
$info->forumlv = format_string($forumlv->name);
if (forumlv_tp_is_tracked($forumlv) ) {
    if (forumlv_tp_stop_tracking($forumlv->id)) {
        add_to_log($course->id, "forumlv", "stop tracking", "view.php?f=$forumlv->id", $forumlv->id, $cm->id);
        redirect($returnto, get_string("nownottracking", "forumlv", $info), 1);
    } else {
        print_error('cannottrack', '', $_SERVER["HTTP_REFERER"]);
    }

} else { // subscribe
    if (forumlv_tp_start_tracking($forumlv->id)) {
        add_to_log($course->id, "forumlv", "start tracking", "view.php?f=$forumlv->id", $forumlv->id, $cm->id);
        redirect($returnto, get_string("nowtracking", "forumlv", $info), 1);
    } else {
        print_error('cannottrack', '', $_SERVER["HTTP_REFERER"]);
    }
}


