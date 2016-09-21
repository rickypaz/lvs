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

require_once('../../config.php');
require_once(dirname(__FILE__) . '/create_form.php');
require_once($CFG->dirroot . '/mod/wikilv/lib.php');
require_once($CFG->dirroot . '/mod/wikilv/locallib.php');
require_once($CFG->dirroot . '/mod/wikilv/pagelib.php');

// this page accepts two actions: new and create
// 'new' action will display a form contains page title and page format
// selections
// 'create' action will create a new page in db, and redirect to
// page editing page.
$action = optional_param('action', 'new', PARAM_TEXT);
// The title of the new page, can be empty
$title = optional_param('title', get_string('newpage', 'wikilv'), PARAM_TEXT);
$wid = optional_param('wid', 0, PARAM_INT);
$swid = optional_param('swid', 0, PARAM_INT);
$group = optional_param('group', 0, PARAM_INT);
$uid = optional_param('uid', 0, PARAM_INT);

// 'create' action must be submitted by moodle form
// so sesskey must be checked
if ($action == 'create') {
    if (!confirm_sesskey()) {
        print_error('invalidsesskey');
    }
}

if (!empty($swid)) {
    $subwikilv = wikilv_get_subwikilv($swid);

    if (!$wikilv = wikilv_get_wikilv($subwikilv->wikilvid)) {
        print_error('incorrectwikilvid', 'wikilv');
    }

} else {
    $subwikilv = wikilv_get_subwikilv_by_group($wid, $group, $uid);

    if (!$wikilv = wikilv_get_wikilv($wid)) {
        print_error('incorrectwikilvid', 'wikilv');
    }

}

if (!$cm = get_coursemodule_from_instance('wikilv', $wikilv->id)) {
    print_error('invalidcoursemodule');
}

$groups = new stdClass();
if (groups_get_activity_groupmode($cm)) {
    $modulecontext = context_module::instance($cm->id);
    $canaccessgroups = has_capability('moodle/site:accessallgroups', $modulecontext);
    if ($canaccessgroups) {
        $groups->availablegroups = groups_get_all_groups($cm->course);
        $allpart = new stdClass();
        $allpart->id = '0';
        $allpart->name = get_string('allparticipants');
        array_unshift($groups->availablegroups, $allpart);
    } else {
        $groups->availablegroups = groups_get_all_groups($cm->course, $USER->id);
    }
    if (!empty($group)) {
        $groups->currentgroup = $group;
    } else {
        $groups->currentgroup = groups_get_activity_group($cm);
    }
}

$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);

require_login($course, true, $cm);

$wikilvpage = new page_wikilv_create($wikilv, $subwikilv, $cm);

if (!empty($swid)) {
    $wikilvpage->set_gid($subwikilv->groupid);
    $wikilvpage->set_uid($subwikilv->userid);
    $wikilvpage->set_swid($swid);
} else {
    $wikilvpage->set_wid($wid);
    $wikilvpage->set_gid($group);
    $wikilvpage->set_uid($uid);
}

$wikilvpage->set_availablegroups($groups);
$wikilvpage->set_title($title);

// set page action, and initialise moodle form
$wikilvpage->set_action($action);

switch ($action) {
case 'create':
    $newpageid = $wikilvpage->create_page($title);
    redirect($CFG->wwwroot . '/mod/wikilv/edit.php?pageid='.$newpageid);
    break;
case 'new':
    // Go straight to editing if we know the page title and we're in force format mode.
    if ((int)$wikilv->forceformat == 1 && $title != get_string('newpage', 'wikilv')) {
        $newpageid = $wikilvpage->create_page($title);
        redirect($CFG->wwwroot . '/mod/wikilv/edit.php?pageid='.$newpageid);
    } else {
        $wikilvpage->print_header();
        // Create a new page.
        $wikilvpage->print_content($title);
    }
    $wikilvpage->print_footer();
    break;
}
