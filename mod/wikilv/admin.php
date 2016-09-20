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
 * Delete wikilv pages or versions
 *
 * This will show options for deleting wikilv pages or purging page versions
 * If user have wikilv:managewikilv ability then only this page will show delete
 * options
 *
 * @package mod_wikilv
 * @copyright 2011 Rajesh Taneja
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->dirroot . '/mod/wikilv/lib.php');
require_once($CFG->dirroot . '/mod/wikilv/locallib.php');
require_once($CFG->dirroot . '/mod/wikilv/pagelib.php');

$pageid = required_param('pageid', PARAM_INT); // Page ID
$delete = optional_param('delete', 0, PARAM_INT); // ID of the page to be deleted.
$option = optional_param('option', 1, PARAM_INT); // Option ID
$listall = optional_param('listall', 0, PARAM_INT); // list all pages
$toversion = optional_param('toversion', 0, PARAM_INT); // max version to be deleted
$fromversion = optional_param('fromversion', 0, PARAM_INT); // min version to be deleted

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

if (!wikilv_user_can_view($subwikilv, $wikilv)) {
    print_error('cannotviewpage', 'wikilv');
}

$context = context_module::instance($cm->id);
require_capability('mod/wikilv:managewikilv', $context);

//Delete page if a page ID to delete was supplied
if (!empty($delete) && confirm_sesskey()) {
    if ($pageid != $delete) {
        // Validate that we are deleting from the same subwikilv.
        $deletepage = wikilv_get_page($delete);
        if (!$deletepage || $deletepage->subwikilvid != $page->subwikilvid) {
            print_error('incorrectsubwikilvid', 'wikilv');
        }
    }
    wikilv_delete_pages($context, $delete, $page->subwikilvid);
    //when current wikilv page is deleted, then redirect user to create that page, as
    //current pageid is invalid after deletion.
    if ($pageid == $delete) {
        $params = array('swid' => $page->subwikilvid, 'title' => $page->title);
        $url = new moodle_url('/mod/wikilv/create.php', $params);
        redirect($url);
    }
}

//delete version if toversion and fromversion are set.
if (!empty($toversion) && !empty($fromversion) && confirm_sesskey()) {
    //make sure all versions should not be deleted...
    $versioncount = wikilv_count_wikilv_page_versions($pageid);
    $versioncount -= 1; //ignore version 0
    $totalversionstodelete = $toversion - $fromversion;
    $totalversionstodelete += 1; //added 1 as toversion should be included

    if (($totalversionstodelete >= $versioncount) || ($versioncount <= 1)) {
        print_error('incorrectdeleteversions', 'wikilv');
    } else {
        $versions = array();
        for ($i = $fromversion; $i <= $toversion; $i++) {
            //Add all version to deletion list which exist
            if (wikilv_get_wikilv_page_version($pageid, $i)) {
                array_push($versions, $i);
            }
        }
        $purgeversions[$pageid] = $versions;
        wikilv_delete_page_versions($purgeversions, $context);
    }
}

//show actual page
$wikilvpage = new page_wikilv_admin($wikilv, $subwikilv, $cm);

$wikilvpage->set_page($page);
$wikilvpage->print_header();
$wikilvpage->set_view($option, empty($listall)?true:false);
$wikilvpage->print_content();

$wikilvpage->print_footer();
