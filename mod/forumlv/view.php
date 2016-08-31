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
 * @package   mod_forumlv
 * @copyright 1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

    require_once('../../config.php');
    require_once('lib.php');
    require_once($CFG->libdir.'/completionlib.php');

    $id          = optional_param('id', 0, PARAM_INT);       // Course Module ID
    $f           = optional_param('f', 0, PARAM_INT);        // Forumlv ID
    $mode        = optional_param('mode', 0, PARAM_INT);     // Display mode (for single forumlv)
    $showall     = optional_param('showall', '', PARAM_INT); // show all discussions on one page
    $changegroup = optional_param('group', -1, PARAM_INT);   // choose the current group
    $page        = optional_param('page', 0, PARAM_INT);     // which page to show
    $search      = optional_param('search', '', PARAM_CLEAN);// search string

    $params = array();
    if ($id) {
        $params['id'] = $id;
    } else {
        $params['f'] = $f;
    }
    if ($page) {
        $params['page'] = $page;
    }
    if ($search) {
        $params['search'] = $search;
    }
    $PAGE->set_url('/mod/forumlv/view.php', $params);

    if ($id) {
        if (! $cm = get_coursemodule_from_id('forumlv', $id)) {
            print_error('invalidcoursemodule');
        }
        if (! $course = $DB->get_record("course", array("id" => $cm->course))) {
            print_error('coursemisconf');
        }
        if (! $forumlv = $DB->get_record("forumlv", array("id" => $cm->instance))) {
            print_error('invalidforumlvid', 'forumlv');
        }
        if ($forumlv->type == 'single') {
            $PAGE->set_pagetype('mod-forumlv-discuss');
        }
        // move require_course_login here to use forced language for course
        // fix for MDL-6926
        require_course_login($course, true, $cm);
        $strforumlvs = get_string("modulenameplural", "forumlv");
        $strforumlv = get_string("modulename", "forumlv");
    } else if ($f) {

        if (! $forumlv = $DB->get_record("forumlv", array("id" => $f))) {
            print_error('invalidforumlvid', 'forumlv');
        }
        if (! $course = $DB->get_record("course", array("id" => $forumlv->course))) {
            print_error('coursemisconf');
        }

        if (!$cm = get_coursemodule_from_instance("forumlv", $forumlv->id, $course->id)) {
            print_error('missingparameter');
        }
        // move require_course_login here to use forced language for course
        // fix for MDL-6926
        require_course_login($course, true, $cm);
        $strforumlvs = get_string("modulenameplural", "forumlv");
        $strforumlv = get_string("modulename", "forumlv");
    } else {
        print_error('missingparameter');
    }

    if (!$PAGE->button) {
        $PAGE->set_button(forumlv_search_form($course, $search));
    }

    $context = context_module::instance($cm->id);
    $PAGE->set_context($context);

    if (!empty($CFG->enablerssfeeds) && !empty($CFG->forumlv_enablerssfeeds) && $forumlv->rsstype && $forumlv->rssarticles) {
        require_once("$CFG->libdir/rsslib.php");

        $rsstitle = format_string($course->shortname, true, array('context' => context_course::instance($course->id))) . ': ' . format_string($forumlv->name);
        rss_add_http_header($context, 'mod_forumlv', $forumlv, $rsstitle);
    }

/// Print header.

    $PAGE->set_title($forumlv->name);
    $PAGE->add_body_class('forumtype-'.$forumlv->type);
    $PAGE->set_heading($course->fullname);

/// Some capability checks.
    if (empty($cm->visible) and !has_capability('moodle/course:viewhiddenactivities', $context)) {
        notice(get_string("activityiscurrentlyhidden"));
    }

    if (!has_capability('mod/forumlv:viewdiscussion', $context)) {
        notice(get_string('noviewdiscussionspermission', 'forumlv'));
    }

    // Mark viewed and trigger the course_module_viewed event.
    forumlv_view($forumlv, $course, $cm, $context);

    echo $OUTPUT->header();

    echo $OUTPUT->heading(format_string($forumlv->name), 2);
    if (!empty($forumlv->intro) && $forumlv->type != 'single' && $forumlv->type != 'teacher') {
        echo $OUTPUT->box(format_module_intro('forumlv', $forumlv, $cm->id), 'generalbox', 'intro');
    }

/// find out current groups mode
    groups_print_activity_menu($cm, $CFG->wwwroot . '/mod/forumlv/view.php?id=' . $cm->id);

    $SESSION->fromdiscussion = qualified_me();   // Return here if we post or set subscription etc


/// Print settings and things across the top

    // If it's a simple single discussion forumlv, we need to print the display
    // mode control.
    if ($forumlv->type == 'single') {
        $discussion = NULL;
        $discussions = $DB->get_records('forumlv_discussions', array('forumlv'=>$forumlv->id), 'timemodified ASC');
        if (!empty($discussions)) {
            $discussion = array_pop($discussions);
        }
        if ($discussion) {
            if ($mode) {
                set_user_preference("forumlv_displaymode", $mode);
            }
            $displaymode = get_user_preferences("forumlv_displaymode", $CFG->forumlv_displaymode);
            forumlv_print_mode_form($forumlv->id, $displaymode, $forumlv->type);
        }
    }

    if (!empty($forumlv->blockafter) && !empty($forumlv->blockperiod)) {
        $a = new stdClass();
        $a->blockafter = $forumlv->blockafter;
        $a->blockperiod = get_string('secondstotime'.$forumlv->blockperiod);
        echo $OUTPUT->notification(get_string('thisforumlvisthrottled', 'forumlv', $a));
    }

    if ($forumlv->type == 'qanda' && !has_capability('moodle/course:manageactivities', $context)) {
        echo $OUTPUT->notification(get_string('qandanotify','forumlv'));
    }

    switch ($forumlv->type) {
        case 'single':
            if (!empty($discussions) && count($discussions) > 1) {
                echo $OUTPUT->notification(get_string('warnformorepost', 'forumlv'));
            }
            if (! $post = forumlv_get_post_full($discussion->firstpost)) {
                print_error('cannotfindfirstpost', 'forumlv');
            }
            if ($mode) {
                set_user_preference("forumlv_displaymode", $mode);
            }

            $canreply    = forumlv_user_can_post($forumlv, $discussion, $USER, $cm, $course, $context);
            $canrate     = has_capability('mod/forumlv:rate', $context);
            $displaymode = get_user_preferences("forumlv_displaymode", $CFG->forumlv_displaymode);

            echo '&nbsp;'; // this should fix the floating in FF
            forumlv_print_discussion($course, $cm, $forumlv, $discussion, $post, $displaymode, $canreply, $canrate);
            break;

        case 'eachuser':
            echo '<p class="mdl-align">';
            if (forumlv_user_can_post_discussion($forumlv, null, -1, $cm)) {
                print_string("allowsdiscussions", "forumlv");
            } else {
                echo '&nbsp;';
            }
            echo '</p>';
            if (!empty($showall)) {
                forumlv_print_latest_discussions($course, $forumlv, 0, 'header', '', -1, -1, -1, 0, $cm);
            } else {
                forumlv_print_latest_discussions($course, $forumlv, -1, 'header', '', -1, -1, $page, $CFG->forumlv_manydiscussions, $cm);
            }
            break;

        case 'teacher':
            if (!empty($showall)) {
                forumlv_print_latest_discussions($course, $forumlv, 0, 'header', '', -1, -1, -1, 0, $cm);
            } else {
                forumlv_print_latest_discussions($course, $forumlv, -1, 'header', '', -1, -1, $page, $CFG->forumlv_manydiscussions, $cm);
            }
            break;

        case 'blog':
            echo '<br />';
            if (!empty($showall)) {
                forumlv_print_latest_discussions($course, $forumlv, 0, 'plain', 'd.pinned DESC, p.created DESC', -1, -1, -1, 0, $cm);
            } else {
                forumlv_print_latest_discussions($course, $forumlv, -1, 'plain', 'd.pinned DESC, p.created DESC', -1, -1, $page,
                    $CFG->forumlv_manydiscussions, $cm);
            }
            break;

        default:
            echo '<br />';
            if (!empty($showall)) {
                forumlv_print_latest_discussions($course, $forumlv, 0, 'header', '', -1, -1, -1, 0, $cm);
            } else {
                forumlv_print_latest_discussions($course, $forumlv, -1, 'header', '', -1, -1, $page, $CFG->forumlv_manydiscussions, $cm);
            }


            break;
    }

    // Add the subscription toggle JS.
    $PAGE->requires->yui_module('moodle-mod_forumlv-subscriptiontoggle', 'Y.M.mod_forumlv.subscriptiontoggle.init');

    echo $OUTPUT->footer($course);
