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
 * Displays a post, and all the posts below it.
 * If no post is given, displays all posts in a discussion
 *
 * @package mod-forumlv
 * @copyright 1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

    require_once('../../config.php');

    $d      = required_param('d', PARAM_INT);                // Discussion ID
    $parent = optional_param('parent', 0, PARAM_INT);        // If set, then display this post and all children.
    $mode   = optional_param('mode', 0, PARAM_INT);          // If set, changes the layout of the thread
    $move   = optional_param('move', 0, PARAM_INT);          // If set, moves this discussion to another forumlv
    $mark   = optional_param('mark', '', PARAM_ALPHA);       // Used for tracking read posts if user initiated.
    $postid = optional_param('postid', 0, PARAM_INT);        // Used for tracking read posts if user initiated.

    $url = new moodle_url('/mod/forumlv/discuss.php', array('d'=>$d));
    if ($parent !== 0) {
        $url->param('parent', $parent);
    }
    $PAGE->set_url($url);

    $discussion = $DB->get_record('forumlv_discussions', array('id' => $d), '*', MUST_EXIST);
    $course = $DB->get_record('course', array('id' => $discussion->course), '*', MUST_EXIST);
    $forumlv = $DB->get_record('forumlv', array('id' => $discussion->forumlv), '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance('forumlv', $forumlv->id, $course->id, false, MUST_EXIST);

    require_course_login($course, true, $cm);

    // move this down fix for MDL-6926
    require_once($CFG->dirroot.'/mod/forumlv/lib.php');

    $modcontext = context_module::instance($cm->id);
    require_capability('mod/forumlv:viewdiscussion', $modcontext, NULL, true, 'noviewdiscussionspermission', 'forumlv');

    if (!empty($CFG->enablerssfeeds) && !empty($CFG->forumlv_enablerssfeeds) && $forumlv->rsstype && $forumlv->rssarticles) {
        require_once("$CFG->libdir/rsslib.php");

        $rsstitle = format_string($course->shortname, true, array('context' => context_course::instance($course->id))) . ': ' . format_string($forumlv->name);
        rss_add_http_header($modcontext, 'mod_forumlv', $forumlv, $rsstitle);
    }

/// move discussion if requested
    if ($move > 0 and confirm_sesskey()) {
        $return = $CFG->wwwroot.'/mod/forumlv/discuss.php?d='.$discussion->id;

        require_capability('mod/forumlv:movediscussions', $modcontext);

        if ($forumlv->type == 'single') {
            print_error('cannotmovefromsingleforumlv', 'forumlv', $return);
        }

        if (!$forumlvto = $DB->get_record('forumlv', array('id' => $move))) {
            print_error('cannotmovetonotexist', 'forumlv', $return);
        }

        if ($forumlvto->type == 'single') {
            print_error('cannotmovetosingleforumlv', 'forumlv', $return);
        }

        if (!$cmto = get_coursemodule_from_instance('forumlv', $forumlvto->id, $course->id)) {
            print_error('cannotmovetonotfound', 'forumlv', $return);
        }

        if (!coursemodule_visible_for_user($cmto)) {
            print_error('cannotmovenotvisible', 'forumlv', $return);
        }

        require_capability('mod/forumlv:startdiscussion', context_module::instance($cmto->id));

        if (!forumlv_move_attachments($discussion, $forumlv->id, $forumlvto->id)) {
            echo $OUTPUT->notification("Errors occurred while moving attachment directories - check your file permissions");
        }
        $DB->set_field('forumlv_discussions', 'forumlv', $forumlvto->id, array('id' => $discussion->id));
        $DB->set_field('forumlv_read', 'forumlvid', $forumlvto->id, array('discussionid' => $discussion->id));
        add_to_log($course->id, 'forumlv', 'move discussion', "discuss.php?d=$discussion->id", $discussion->id, $cmto->id);

        require_once($CFG->libdir.'/rsslib.php');
        require_once($CFG->dirroot.'/mod/forumlv/rsslib.php');

        // Delete the RSS files for the 2 forumlvs to force regeneration of the feeds
        forumlv_rss_delete_file($forumlv);
        forumlv_rss_delete_file($forumlvto);

        redirect($return.'&moved=-1&sesskey='.sesskey());
    }

    add_to_log($course->id, 'forumlv', 'view discussion', "discuss.php?d=$discussion->id", $discussion->id, $cm->id);

    unset($SESSION->fromdiscussion);

    if ($mode) {
        set_user_preference('forumlv_displaymode', $mode);
    }

    $displaymode = get_user_preferences('forumlv_displaymode', $CFG->forumlv_displaymode);

    if ($parent) {
        // If flat AND parent, then force nested display this time
        if ($displaymode == FORUMLV_MODE_FLATOLDEST or $displaymode == FORUMLV_MODE_FLATNEWEST) {
            $displaymode = FORUMLV_MODE_NESTED;
        }
    } else {
        $parent = $discussion->firstpost;
    }

    if (! $post = forumlv_get_post_full($parent)) {
        print_error("notexists", 'forumlv', "$CFG->wwwroot/mod/forumlv/view.php?f=$forumlv->id");
    }

    if (!forumlv_user_can_see_post($forumlv, $discussion, $post, null, $cm)) {
        print_error('noviewdiscussionspermission', 'forumlv', "$CFG->wwwroot/mod/forumlv/view.php?id=$forumlv->id");
    }

    if ($mark == 'read' or $mark == 'unread') {
        if ($CFG->forumlv_usermarksread && forumlv_tp_can_track_forumlvs($forumlv) && forumlv_tp_is_tracked($forumlv)) {
            if ($mark == 'read') {
                forumlv_tp_add_read_record($USER->id, $postid);
            } else {
                // unread
                forumlv_tp_delete_read_records($USER->id, $postid);
            }
        }
    }

    $searchform = forumlv_search_form($course);

    $forumlvnode = $PAGE->navigation->find($cm->id, navigation_node::TYPE_ACTIVITY);
    if (empty($forumlvnode)) {
        $forumlvnode = $PAGE->navbar;
    } else {
        $forumlvnode->make_active();
    }
    $node = $forumlvnode->add(format_string($discussion->name), new moodle_url('/mod/forumlv/discuss.php', array('d'=>$discussion->id)));
    $node->display = false;
    if ($node && $post->id != $discussion->firstpost) {
        $node->add(format_string($post->subject), $PAGE->url);
    }

    $PAGE->set_title("$course->shortname: ".format_string($discussion->name));
    $PAGE->set_heading($course->fullname);
    $PAGE->set_button($searchform);
    echo $OUTPUT->header();

/// Check to see if groups are being used in this forumlv
/// If so, make sure the current person is allowed to see this discussion
/// Also, if we know they should be able to reply, then explicitly set $canreply for performance reasons

    $canreply = forumlv_user_can_post($forumlv, $discussion, $USER, $cm, $course, $modcontext);
    if (!$canreply and $forumlv->type !== 'news') {
        if (isguestuser() or !isloggedin()) {
            $canreply = true;
        }
        if (!is_enrolled($modcontext) and !is_viewing($modcontext)) {
            // allow guests and not-logged-in to see the link - they are prompted to log in after clicking the link
            // normal users with temporary guest access see this link too, they are asked to enrol instead
            $canreply = enrol_selfenrol_available($course->id);
        }
    }

/// Print the controls across the top
    echo '<div class="discussioncontrols clearfix">';

    if (!empty($CFG->enableportfolios) && has_capability('mod/forumlv:exportdiscussion', $modcontext)) {
        require_once($CFG->libdir.'/portfoliolib.php');
        $button = new portfolio_add_button();
        $button->set_callback_options('forumlv_portfolio_caller', array('discussionid' => $discussion->id), 'mod_forumlv');
        $button = $button->to_html(PORTFOLIO_ADD_FULL_FORM, get_string('exportdiscussion', 'mod_forumlv'));
        $buttonextraclass = '';
        if (empty($button)) {
            // no portfolio plugin available.
            $button = '&nbsp;';
            $buttonextraclass = ' noavailable';
        }
        echo html_writer::tag('div', $button, array('class' => 'discussioncontrol exporttoportfolio'.$buttonextraclass));
    } else {
        echo html_writer::tag('div', '&nbsp;', array('class'=>'discussioncontrol nullcontrol'));
    }

    // groups selector not needed here
    echo '<div class="discussioncontrol displaymode">';
    forumlv_print_mode_form($discussion->id, $displaymode);
    echo "</div>";

    if ($forumlv->type != 'single'
                && has_capability('mod/forumlv:movediscussions', $modcontext)) {

        echo '<div class="discussioncontrol movediscussion">';
        // Popup menu to move discussions to other forumlvs. The discussion in a
        // single discussion forumlv can't be moved.
        $modinfo = get_fast_modinfo($course);
        if (isset($modinfo->instances['forumlv'])) {
            $forumlvmenu = array();
            // Check forumlv types and eliminate simple discussions.
            $forumlvcheck = $DB->get_records('forumlv', array('course' => $course->id),'', 'id, type');
            foreach ($modinfo->instances['forumlv'] as $forumlvcm) {
                if (!$forumlvcm->uservisible || !has_capability('mod/forumlv:startdiscussion',
                    context_module::instance($forumlvcm->id))) {
                    continue;
                }
                $section = $forumlvcm->sectionnum;
                $sectionname = get_section_name($course, $section);
                if (empty($forumlvmenu[$section])) {
                    $forumlvmenu[$section] = array($sectionname => array());
                }
                $forumlvidcompare = $forumlvcm->instance != $forumlv->id;
                $forumlvtypecheck = $forumlvcheck[$forumlvcm->instance]->type !== 'single';
                if ($forumlvidcompare and $forumlvtypecheck) {
                    $url = "/mod/forumlv/discuss.php?d=$discussion->id&move=$forumlvcm->instance&sesskey=".sesskey();
                    $forumlvmenu[$section][$sectionname][$url] = format_string($forumlvcm->name);
                }
            }
            if (!empty($forumlvmenu)) {
                echo '<div class="movediscussionoption">';
                $select = new url_select($forumlvmenu, '',
                        array(''=>get_string("movethisdiscussionto", "forumlv")),
                        'forumlvmenu', get_string('move'));
                echo $OUTPUT->render($select);
                echo "</div>";
            }
        }
        echo "</div>";
    }
    echo '<div class="clearfloat">&nbsp;</div>';
    echo "</div>";

    if (!empty($forumlv->blockafter) && !empty($forumlv->blockperiod)) {
        $a = new stdClass();
        $a->blockafter  = $forumlv->blockafter;
        $a->blockperiod = get_string('secondstotime'.$forumlv->blockperiod);
        echo $OUTPUT->notification(get_string('thisforumlvisthrottled','forumlv',$a));
    }

    if ($forumlv->type == 'qanda' && !has_capability('mod/forumlv:viewqandawithoutposting', $modcontext) &&
                !forumlv_user_has_posted($forumlv->id,$discussion->id,$USER->id)) {
        echo $OUTPUT->notification(get_string('qandanotify','forumlv'));
    }

    if ($move == -1 and confirm_sesskey()) {
        echo $OUTPUT->notification(get_string('discussionmoved', 'forumlv', format_string($forumlv->name,true)));
    }

    $canrate = has_capability('mod/forumlv:rate', $modcontext);
    forumlv_print_discussion($course, $cm, $forumlv, $discussion, $post, $displaymode, $canreply, $canrate);

    echo $OUTPUT->footer();



