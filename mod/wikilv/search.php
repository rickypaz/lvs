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
 * @package mod_wikilv
 * @copyright 2010 Dongsheng Cai <dongsheng@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->dirroot . '/mod/wikilv/lib.php');
require_once($CFG->dirroot . '/mod/wikilv/locallib.php');
require_once($CFG->dirroot . '/mod/wikilv/pagelib.php');

$search = optional_param('searchstring', null, PARAM_TEXT);
$courseid = optional_param('courseid', 0, PARAM_INT);
$searchcontent = optional_param('searchwikilvcontent', 0, PARAM_INT);
$cmid = optional_param('cmid', 0, PARAM_INT);
$subwikilvid = optional_param('subwikilvid', 0, PARAM_INT);
$userid = optional_param('uid', 0, PARAM_INT);

if (!$course = $DB->get_record('course', array('id' => $courseid))) {
    print_error('invalidcourseid');
}
if (!$cm = get_coursemodule_from_id('wikilv', $cmid)) {
    print_error('invalidcoursemodule');
}

require_login($course, true, $cm);

// Checking wikilv instance
if (!$wikilv = wikilv_get_wikilv($cm->instance)) {
    print_error('incorrectwikilvid', 'wikilv');
}

if ($subwikilvid) {
    // Subwikilv id is specified.
    $subwikilv = wikilv_get_subwikilv($subwikilvid);
    if (!$subwikilv || $subwikilv->wikilvid != $wikilv->id) {
        print_error('incorrectsubwikilvid', 'wikilv');
    }
} else {
    // Getting current group id
    $gid = groups_get_activity_group($cm);

    // Getting current user id
    if ($wikilv->wikilvmode == 'individual') {
        $userid = $userid ? $userid : $USER->id;
    } else {
        $userid = 0;
    }
    if (!$subwikilv = wikilv_get_subwikilv_by_group($cm->instance, $gid, $userid)) {
        // Subwikilv does not exist yet, redirect to the view page (which will redirect to create page if allowed).
        $params = array('wid' => $wikilv->id, 'group' => $gid, 'uid' => $userid, 'title' => $wikilv->firstpagetitle);
        $url = new moodle_url('/mod/wikilv/view.php', $params);
        redirect($url);
    }
}

if ($subwikilv && !wikilv_user_can_view($subwikilv, $wikilv)) {
    print_error('cannotviewpage', 'wikilv');
}

$wikilvpage = new page_wikilv_search($wikilv, $subwikilv, $cm);

$wikilvpage->set_search_string($search, $searchcontent);

$wikilvpage->set_title(get_string('search'));

$wikilvpage->print_header();

$wikilvpage->print_content();

$wikilvpage->print_footer();
