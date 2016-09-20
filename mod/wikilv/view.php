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
 * This file contains all necessary code to view a wikilv page
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
// @lvs dependências lvs
use uab\ifce\lvs\moodle2\business\Moodle2CursoLv;
use uab\ifce\lvs\moodle2\business\WikisLv;
use uab\ifce\lvs\moodle2\business\Wikilv;
use uab\ifce\lvs\business\Item;
use uab\ifce\lvs\avaliacao\NotasLvFactory;
// fim

require_once('../../config.php');
require_once($CFG->dirroot . '/mod/wikilv/lib.php');
require_once($CFG->dirroot . '/mod/wikilv/locallib.php');
require_once($CFG->dirroot . '/mod/wikilv/pagelib.php');

$id = optional_param('id', 0, PARAM_INT); // Course Module ID

$pageid = optional_param('pageid', 0, PARAM_INT); // Page ID

$wid = optional_param('wid', 0, PARAM_INT); // Wikilv ID
$title = optional_param('title', '', PARAM_TEXT); // Page Title
$currentgroup = optional_param('group', 0, PARAM_INT); // Group ID
$userid = optional_param('uid', 0, PARAM_INT); // User ID
$groupanduser = optional_param('groupanduser', 0, PARAM_TEXT);

$edit = optional_param('edit', -1, PARAM_BOOL);

$action = optional_param('action', '', PARAM_ALPHA);
$swid = optional_param('swid', 0, PARAM_INT); // Subwikilv ID

/*
 * Case 0:
 *
 * User that comes from a course. First wikilv page must be shown
 *
 * URL params: id -> course module id
 *
 */
if ($id) {
    // Cheacking course module instance
    if (!$cm = get_coursemodule_from_id('wikilv', $id)) {
        print_error('invalidcoursemodule');
    }

    // Checking course instance
    $course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);

    require_login($course, true, $cm);

    // Checking wikilv instance
    if (!$wikilv = wikilv_get_wikilv($cm->instance)) {
        print_error('incorrectwikilvid', 'wikilv');
    }
    $PAGE->set_cm($cm);

    // Getting the subwikilv corresponding to that wikilv, group and user.
    //
    // Also setting the page if it exists or getting the first page title form
    // that wikilv

    // Getting current group id
    $currentgroup = groups_get_activity_group($cm);

    // Getting current user id
    if ($wikilv->wikilvmode == 'individual') {
        $userid = $USER->id;
    } else {
        $userid = 0;
    }

    // Getting subwikilv. If it does not exists, redirecting to create page
    if (!$subwikilv = wikilv_get_subwikilv_by_group($wikilv->id, $currentgroup, $userid)) {
        $params = array('wid' => $wikilv->id, 'group' => $currentgroup, 'uid' => $userid, 'title' => $wikilv->firstpagetitle);
        $url = new moodle_url('/mod/wikilv/create.php', $params);
        redirect($url);
    }

    // Getting first page. If it does not exists, redirecting to create page
    if (!$page = wikilv_get_first_page($subwikilv->id, $wikilv)) {
        $params = array('swid'=>$subwikilv->id, 'title'=>$wikilv->firstpagetitle);
        $url = new moodle_url('/mod/wikilv/create.php', $params);
        redirect($url);
    }

    /*
     * Case 1:
     *
     * A user wants to see a page.
     *
     * URL Params: pageid -> page id
     *
     */
} elseif ($pageid) {

    // Checking page instance
    if (!$page = wikilv_get_page($pageid)) {
        print_error('incorrectpageid', 'wikilv');
    }

    // Checking subwikilv
    if (!$subwikilv = wikilv_get_subwikilv($page->subwikilvid)) {
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

    $currentgroup = $subwikilv->groupid;

    // Checking course instance
    $course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);

    require_login($course, true, $cm);
    /*
     * Case 2:
     *
     * Trying to read a page from another group or user
     *
     * Page can exists or not.
     *  * If it exists, page must be shown
     *  * If it does not exists, system must ask for its creation
     *
     * URL params: wid -> subwikilv id (required)
     *             title -> a page title (required)
     *             group -> group id (optional)
     *             uid -> user id (optional)
     *             groupanduser -> (optional)
     */
} elseif ($wid && $title) {

    // Setting wikilv instance
    if (!$wikilv = wikilv_get_wikilv($wid)) {
        print_error('incorrectwikilvid', 'wikilv');
    }

    // Checking course module
    if (!$cm = get_coursemodule_from_instance("wikilv", $wikilv->id)) {
        print_error('invalidcoursemodule');
    }

    // Checking course instance
    $course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);

    require_login($course, true, $cm);

    $groupmode = groups_get_activity_groupmode($cm);

    if ($wikilv->wikilvmode == 'individual' && ($groupmode == SEPARATEGROUPS || $groupmode == VISIBLEGROUPS)) {
        list($gid, $uid) = explode('-', $groupanduser);
    } else if ($wikilv->wikilvmode == 'individual') {
        $gid = 0;
        $uid = $userid;
    } else if ($groupmode == NOGROUPS) {
        $gid = 0;
        $uid = 0;
    } else {
        $gid = $currentgroup;
        $uid = 0;
    }

    // Getting subwikilv instance. If it does not exists, redirect to create page
    if (!$subwikilv = wikilv_get_subwikilv_by_group($wikilv->id, $gid, $uid)) {
        $context = context_module::instance($cm->id);

        $modeanduser = $wikilv->wikilvmode == 'individual' && $uid != $USER->id;
        $modeandgroupmember = $wikilv->wikilvmode == 'collaborative' && !groups_is_member($gid);

        $manage = has_capability('mod/wikilv:managewikilv', $context);
        $edit = has_capability('mod/wikilv:editpage', $context);
        $manageandedit = $manage && $edit;

        if ($groupmode == VISIBLEGROUPS and ($modeanduser || $modeandgroupmember) and !$manageandedit) {
            print_error('nocontent','wikilv');
        }

        $params = array('wid' => $wikilv->id, 'group' => $gid, 'uid' => $uid, 'title' => $title);
        $url = new moodle_url('/mod/wikilv/create.php', $params);
        redirect($url);
    }

    // Checking is there is a page with this title. If it does not exists, redirect to first page
    if (!$page = wikilv_get_page_by_title($subwikilv->id, $title)) {
        $params = array('wid' => $wikilv->id, 'group' => $gid, 'uid' => $uid, 'title' => $wikilv->firstpagetitle);
        // Check to see if the first page has been created
        if (!wikilv_get_page_by_title($subwikilv->id, $wikilv->firstpagetitle)) {
            $url = new moodle_url('/mod/wikilv/create.php', $params);
        } else {
            $url = new moodle_url('/mod/wikilv/view.php', $params);
        }
        redirect($url);
    }

    //    /*
    //     * Case 3:
    //     *
    //     * A user switches group when is 'reading' a non-existent page.
    //     *
    //     * URL Params: wid -> wikilv id
    //     *             title -> page title
    //     *             currentgroup -> group id
    //     *
    //     */
    //} elseif ($wid && $title && $currentgroup) {
    //
    //    // Checking wikilv instance
    //    if (!$wikilv = wikilv_get_wikilv($wid)) {
    //        print_error('incorrectwikilvid', 'wikilv');
    //    }
    //
    //    // Checking subwikilv instance
    //    // @TODO: Fix call to wikilv_get_subwikilv_by_group
    //    if (!$currentgroup = groups_get_activity_group($cm)){
    //        $currentgroup = 0;
    //    }
    //    if (!$subwikilv = wikilv_get_subwikilv_by_group($wid, $currentgroup)) {
    //        print_error('incorrectsubwikilvid', 'wikilv');
    //    }
    //
    //    // Checking page instance
    //    if ($page = wikilv_get_page_by_title($subwikilv->id, $title)) {
    //        unset($title);
    //    }
    //
    //    // Checking course instance
    //    $course = $DB->get_record('course', array('id'=>$cm->course), '*', MUST_EXIST);
    //
    //    // Checking course module instance
    //    if (!$cm = get_coursemodule_from_instance("wikilv", $wikilv->id, $course->id)) {
    //        print_error('invalidcoursemodule');
    //    }
    //
    //    $subwikilv = null;
    //    $page = null;
    //
    //    /*
    //     * Case 4:
    //     *
    //     * Error. No more options
    //     */
} else {
    print_error('invalidparameters', 'wikilv');
}

if (!wikilv_user_can_view($subwikilv, $wikilv)) {
    print_error('cannotviewpage', 'wikilv');
}

if (($edit != - 1) and $PAGE->user_allowed_editing()) {
    $USER->editing = $edit;
}

$wikilvpage = new page_wikilv_view($wikilv, $subwikilv, $cm);

$wikilvpage->set_gid($currentgroup);
$wikilvpage->set_page($page);

$context = context_module::instance($cm->id);
if ($pageid) {
    wikilv_page_view($wikilv, $page, $course, $cm, $context, null, null, $subwikilv);
} else if ($id) {
    wikilv_view($wikilv, $course, $cm, $context);
} else if ($wid && $title) {
    $other = array(
        'title' => $title,
        'wid' => $wid,
        'group' => $gid,
        'groupanduser' => $groupanduser
    );
    wikilv_page_view($wikilv, $page, $course, $cm, $context, $uid, $other, $subwikilv);
}

$wikilvpage->print_header();

// @lvs exibição do form de avaliação em wikilv
$gerenciadorNotas = NotasLvFactory::criarGerenciador('moodle2');
$gerenciadorWikis = new WikisLv(new Moodle2CursoLv($course->id));
$item = new Item('wikilv', 'subwikilv', $subwikilv);

$gerenciadorNotas->setModulo( new Wikilv($wikilv->id) );

$configlv = $gerenciadorWikis->recuperarConfiguracao($wikilv);
$gerenciadorNotas->getAvaliacao($item)->setCarinhasEstendido( $configlv->fator_multiplicativo == 3 );

echo $gerenciadorNotas->avaliacaoAtual($item);
echo $gerenciadorNotas->avaliadoPor($item);
echo $gerenciadorNotas->formAvaliacao($item);
// lvs fim

$wikilvpage->print_content();

$wikilvpage->print_footer();
