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

require_once(dirname(__FILE__) . '/../../config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/mod/forumlv/lib.php');
require_once($CFG->libdir . '/rsslib.php');

$id = optional_param('id', 0, PARAM_INT);                   // Course id
$subscribe = optional_param('subscribe', null, PARAM_INT);  // Subscribe/Unsubscribe all forumlvs

$url = new moodle_url('/mod/forumlv/index.php', array('id'=>$id));
if ($subscribe !== null) {
    require_sesskey();
    $url->param('subscribe', $subscribe);
}
$PAGE->set_url($url);

if ($id) {
    if (! $course = $DB->get_record('course', array('id' => $id))) {
        print_error('invalidcourseid');
    }
} else {
    $course = get_site();
}

require_course_login($course);
$PAGE->set_pagelayout('incourse');
$coursecontext = context_course::instance($course->id);


unset($SESSION->fromdiscussion);

$params = array(
    'context' => context_course::instance($course->id)
);
$event = \mod_forumlv\event\course_module_instance_list_viewed::create($params);
$event->add_record_snapshot('course', $course);
$event->trigger();

$strforumlvs       = get_string('forumlvs', 'forumlv');
$strforumlv        = get_string('forumlv', 'forumlv');
$strdescription  = get_string('description');
$strdiscussions  = get_string('discussions', 'forumlv');
$strsubscribed   = get_string('subscribed', 'forumlv');
$strunreadposts  = get_string('unreadposts', 'forumlv');
$strtracking     = get_string('tracking', 'forumlv');
$strmarkallread  = get_string('markallread', 'forumlv');
$strtrackforumlv   = get_string('trackforumlv', 'forumlv');
$strnotrackforumlv = get_string('notrackforumlv', 'forumlv');
$strsubscribe    = get_string('subscribe', 'forumlv');
$strunsubscribe  = get_string('unsubscribe', 'forumlv');
$stryes          = get_string('yes');
$strno           = get_string('no');
$strrss          = get_string('rss');
$stremaildigest  = get_string('emaildigest');

$searchform = forumlv_search_form($course);

// Retrieve the list of forumlv digest options for later.
$digestoptions = forumlv_get_user_digest_options();
$digestoptions_selector = new single_select(new moodle_url('/mod/forumlv/maildigest.php',
    array(
        'backtoindex' => 1,
    )),
    'maildigest',
    $digestoptions,
    null,
    '');
$digestoptions_selector->method = 'post';

// Start of the table for General Forumlvs

$generaltable = new html_table();
$generaltable->head  = array ($strforumlv, $strdescription, $strdiscussions);
$generaltable->align = array ('left', 'left', 'center');

if ($usetracking = forumlv_tp_can_track_forumlvs()) {
    $untracked = forumlv_tp_get_untracked_forumlvs($USER->id, $course->id);

    $generaltable->head[] = $strunreadposts;
    $generaltable->align[] = 'center';

    $generaltable->head[] = $strtracking;
    $generaltable->align[] = 'center';
}

// Fill the subscription cache for this course and user combination.
\mod_forumlv\subscriptions::fill_subscription_cache_for_course($course->id, $USER->id);

$can_subscribe = is_enrolled($coursecontext);
if ($can_subscribe) {
    $generaltable->head[] = $strsubscribed;
    $generaltable->align[] = 'center';

    $generaltable->head[] = $stremaildigest . ' ' . $OUTPUT->help_icon('emaildigesttype', 'mod_forumlv');
    $generaltable->align[] = 'center';
}

if ($show_rss = (($can_subscribe || $course->id == SITEID) &&
                 isset($CFG->enablerssfeeds) && isset($CFG->forumlv_enablerssfeeds) &&
                 $CFG->enablerssfeeds && $CFG->forumlv_enablerssfeeds)) {
    $generaltable->head[] = $strrss;
    $generaltable->align[] = 'center';
}

$usesections = course_format_uses_sections($course->format);

$table = new html_table();

// Parse and organise all the forumlvs.  Most forumlvs are course modules but
// some special ones are not.  These get placed in the general forumlvs
// category with the forumlvs in section 0.

$forumlvs = $DB->get_records_sql("
    SELECT f.*,
           d.maildigest
      FROM {forumlv} f
 LEFT JOIN {forumlv_digests} d ON d.forumlv = f.id AND d.userid = ?
     WHERE f.course = ?
    ", array($USER->id, $course->id));

$generalforumlvs  = array();
$learningforumlvs = array();
$modinfo = get_fast_modinfo($course);

foreach ($modinfo->get_instances_of('forumlv') as $forumlvid=>$cm) {
    if (!$cm->uservisible or !isset($forumlvs[$forumlvid])) {
        continue;
    }

    $forumlv = $forumlvs[$forumlvid];

    if (!$context = context_module::instance($cm->id, IGNORE_MISSING)) {
        continue;   // Shouldn't happen
    }

    if (!has_capability('mod/forumlv:viewdiscussion', $context)) {
        continue;
    }

    // fill two type array - order in modinfo is the same as in course
    if ($forumlv->type == 'news' or $forumlv->type == 'social') {
        $generalforumlvs[$forumlv->id] = $forumlv;

    } else if ($course->id == SITEID or empty($cm->sectionnum)) {
        $generalforumlvs[$forumlv->id] = $forumlv;

    } else {
        $learningforumlvs[$forumlv->id] = $forumlv;
    }
}

// Do course wide subscribe/unsubscribe if requested
if (!is_null($subscribe)) {
    if (isguestuser() or !$can_subscribe) {
        // There should not be any links leading to this place, just redirect.
        redirect(
                new moodle_url('/mod/forumlv/index.php', array('id' => $id)),
                get_string('subscribeenrolledonly', 'forumlv'),
                null,
                \core\output\notification::NOTIFY_ERROR
            );
    }
    // Can proceed now, the user is not guest and is enrolled
    foreach ($modinfo->get_instances_of('forumlv') as $forumlvid=>$cm) {
        $forumlv = $forumlvs[$forumlvid];
        $modcontext = context_module::instance($cm->id);
        $cansub = false;

        if (has_capability('mod/forumlv:viewdiscussion', $modcontext)) {
            $cansub = true;
        }
        if ($cansub && $cm->visible == 0 &&
            !has_capability('mod/forumlv:managesubscriptions', $modcontext))
        {
            $cansub = false;
        }
        if (!\mod_forumlv\subscriptions::is_forcesubscribed($forumlv)) {
            $subscribed = \mod_forumlv\subscriptions::is_subscribed($USER->id, $forumlv, null, $cm);
            $canmanageactivities = has_capability('moodle/course:manageactivities', $coursecontext, $USER->id);
            if (($canmanageactivities || \mod_forumlv\subscriptions::is_subscribable($forumlv)) && $subscribe && !$subscribed && $cansub) {
                \mod_forumlv\subscriptions::subscribe_user($USER->id, $forumlv, $modcontext, true);
            } else if (!$subscribe && $subscribed) {
                \mod_forumlv\subscriptions::unsubscribe_user($USER->id, $forumlv, $modcontext, true);
            }
        }
    }
    $returnto = forumlv_go_back_to(new moodle_url('/mod/forumlv/index.php', array('id' => $course->id)));
    $shortname = format_string($course->shortname, true, array('context' => context_course::instance($course->id)));
    if ($subscribe) {
        redirect(
                $returnto,
                get_string('nowallsubscribed', 'forumlv', $shortname),
                null,
                \core\output\notification::NOTIFY_SUCCESS
            );
    } else {
        redirect(
                $returnto,
                get_string('nowallunsubscribed', 'forumlv', $shortname),
                null,
                \core\output\notification::NOTIFY_SUCCESS
            );
    }
}

/// First, let's process the general forumlvs and build up a display

if ($generalforumlvs) {
    foreach ($generalforumlvs as $forumlv) {
        $cm      = $modinfo->instances['forumlv'][$forumlv->id];
        $context = context_module::instance($cm->id);

        $count = forumlv_count_discussions($forumlv, $cm, $course);

        if ($usetracking) {
            if ($forumlv->trackingtype == FORUMLV_TRACKING_OFF) {
                $unreadlink  = '-';
                $trackedlink = '-';

            } else {
                if (isset($untracked[$forumlv->id])) {
                        $unreadlink  = '-';
                } else if ($unread = forumlv_tp_count_forumlv_unread_posts($cm, $course)) {
                        $unreadlink = '<span class="unread"><a href="view.php?f='.$forumlv->id.'">'.$unread.'</a>';
                    $unreadlink .= '<a title="'.$strmarkallread.'" href="markposts.php?f='.
                                   $forumlv->id.'&amp;mark=read&amp;sesskey=' . sesskey() . '"><img src="'.$OUTPUT->pix_url('t/markasread') . '" alt="'.$strmarkallread.'" class="iconsmall" /></a></span>';
                } else {
                    $unreadlink = '<span class="read">0</span>';
                }

                if (($forumlv->trackingtype == FORUMLV_TRACKING_FORCED) && ($CFG->forumlv_allowforcedreadtracking)) {
                    $trackedlink = $stryes;
                } else if ($forumlv->trackingtype === FORUMLV_TRACKING_OFF || ($USER->trackforumlvs == 0)) {
                    $trackedlink = '-';
                } else {
                    $aurl = new moodle_url('/mod/forumlv/settracking.php', array(
                            'id' => $forumlv->id,
                            'sesskey' => sesskey(),
                        ));
                    if (!isset($untracked[$forumlv->id])) {
                        $trackedlink = $OUTPUT->single_button($aurl, $stryes, 'post', array('title'=>$strnotrackforumlv));
                    } else {
                        $trackedlink = $OUTPUT->single_button($aurl, $strno, 'post', array('title'=>$strtrackforumlv));
                    }
                }
            }
        }

        $forumlv->intro = shorten_text(format_module_intro('forumlv', $forumlv, $cm->id), $CFG->forumlv_shortpost);
        $forumlvname = format_string($forumlv->name, true);

        if ($cm->visible) {
            $style = '';
        } else {
            $style = 'class="dimmed"';
        }
        $forumlvlink = "<a href=\"view.php?f=$forumlv->id\" $style>".format_string($forumlv->name,true)."</a>";
        $discussionlink = "<a href=\"view.php?f=$forumlv->id\" $style>".$count."</a>";

        $row = array ($forumlvlink, $forumlv->intro, $discussionlink);
        if ($usetracking) {
            $row[] = $unreadlink;
            $row[] = $trackedlink;    // Tracking.
        }

        if ($can_subscribe) {
            $row[] = forumlv_get_subscribe_link($forumlv, $context, array('subscribed' => $stryes,
                    'unsubscribed' => $strno, 'forcesubscribed' => $stryes,
                    'cantsubscribe' => '-'), false, false, true);

            $digestoptions_selector->url->param('id', $forumlv->id);
            if ($forumlv->maildigest === null) {
                $digestoptions_selector->selected = -1;
            } else {
                $digestoptions_selector->selected = $forumlv->maildigest;
            }
            $row[] = $OUTPUT->render($digestoptions_selector);
        }

        //If this forumlv has RSS activated, calculate it
        if ($show_rss) {
            if ($forumlv->rsstype and $forumlv->rssarticles) {
                //Calculate the tooltip text
                if ($forumlv->rsstype == 1) {
                    $tooltiptext = get_string('rsssubscriberssdiscussions', 'forumlv');
                } else {
                    $tooltiptext = get_string('rsssubscriberssposts', 'forumlv');
                }

                if (!isloggedin() && $course->id == SITEID) {
                    $userid = guest_user()->id;
                } else {
                    $userid = $USER->id;
                }
                //Get html code for RSS link
                $row[] = rss_get_link($context->id, $userid, 'mod_forumlv', $forumlv->id, $tooltiptext);
            } else {
                $row[] = '&nbsp;';
            }
        }

        $generaltable->data[] = $row;
    }
}


// Start of the table for Learning Forumlvs
$learningtable = new html_table();
$learningtable->head  = array ($strforumlv, $strdescription, $strdiscussions);
$learningtable->align = array ('left', 'left', 'center');

if ($usetracking) {
    $learningtable->head[] = $strunreadposts;
    $learningtable->align[] = 'center';

    $learningtable->head[] = $strtracking;
    $learningtable->align[] = 'center';
}

if ($can_subscribe) {
    $learningtable->head[] = $strsubscribed;
    $learningtable->align[] = 'center';

    $learningtable->head[] = $stremaildigest . ' ' . $OUTPUT->help_icon('emaildigesttype', 'mod_forumlv');
    $learningtable->align[] = 'center';
}

if ($show_rss = (($can_subscribe || $course->id == SITEID) &&
                 isset($CFG->enablerssfeeds) && isset($CFG->forumlv_enablerssfeeds) &&
                 $CFG->enablerssfeeds && $CFG->forumlv_enablerssfeeds)) {
    $learningtable->head[] = $strrss;
    $learningtable->align[] = 'center';
}

/// Now let's process the learning forumlvs

if ($course->id != SITEID) {    // Only real courses have learning forumlvs
    // 'format_.'$course->format only applicable when not SITEID (format_site is not a format)
    $strsectionname  = get_string('sectionname', 'format_'.$course->format);
    // Add extra field for section number, at the front
    array_unshift($learningtable->head, $strsectionname);
    array_unshift($learningtable->align, 'center');


    if ($learningforumlvs) {
        $currentsection = '';
            foreach ($learningforumlvs as $forumlv) {
            $cm      = $modinfo->instances['forumlv'][$forumlv->id];
            $context = context_module::instance($cm->id);

            $count = forumlv_count_discussions($forumlv, $cm, $course);

            if ($usetracking) {
                if ($forumlv->trackingtype == FORUMLV_TRACKING_OFF) {
                    $unreadlink  = '-';
                    $trackedlink = '-';

                } else {
                    if (isset($untracked[$forumlv->id])) {
                        $unreadlink  = '-';
                    } else if ($unread = forumlv_tp_count_forumlv_unread_posts($cm, $course)) {
                        $unreadlink = '<span class="unread"><a href="view.php?f='.$forumlv->id.'">'.$unread.'</a>';
                        $unreadlink .= '<a title="'.$strmarkallread.'" href="markposts.php?f='.
                                       $forumlv->id.'&amp;mark=read&sesskey=' . sesskey() . '"><img src="'.$OUTPUT->pix_url('t/markasread') . '" alt="'.$strmarkallread.'" class="iconsmall" /></a></span>';
                    } else {
                        $unreadlink = '<span class="read">0</span>';
                    }

                    if (($forumlv->trackingtype == FORUMLV_TRACKING_FORCED) && ($CFG->forumlv_allowforcedreadtracking)) {
                        $trackedlink = $stryes;
                    } else if ($forumlv->trackingtype === FORUMLV_TRACKING_OFF || ($USER->trackforumlvs == 0)) {
                        $trackedlink = '-';
                    } else {
                        $aurl = new moodle_url('/mod/forumlv/settracking.php', array('id'=>$forumlv->id));
                        if (!isset($untracked[$forumlv->id])) {
                            $trackedlink = $OUTPUT->single_button($aurl, $stryes, 'post', array('title'=>$strnotrackforumlv));
                        } else {
                            $trackedlink = $OUTPUT->single_button($aurl, $strno, 'post', array('title'=>$strtrackforumlv));
                        }
                    }
                }
            }

            $forumlv->intro = shorten_text(format_module_intro('forumlv', $forumlv, $cm->id), $CFG->forumlv_shortpost);

            if ($cm->sectionnum != $currentsection) {
                $printsection = get_section_name($course, $cm->sectionnum);
                if ($currentsection) {
                    $learningtable->data[] = 'hr';
                }
                $currentsection = $cm->sectionnum;
            } else {
                $printsection = '';
            }

            $forumlvname = format_string($forumlv->name,true);

            if ($cm->visible) {
                $style = '';
            } else {
                $style = 'class="dimmed"';
            }
            $forumlvlink = "<a href=\"view.php?f=$forumlv->id\" $style>".format_string($forumlv->name,true)."</a>";
            $discussionlink = "<a href=\"view.php?f=$forumlv->id\" $style>".$count."</a>";

            $row = array ($printsection, $forumlvlink, $forumlv->intro, $discussionlink);
            if ($usetracking) {
                $row[] = $unreadlink;
                $row[] = $trackedlink;    // Tracking.
            }

            if ($can_subscribe) {
                $row[] = forumlv_get_subscribe_link($forumlv, $context, array('subscribed' => $stryes,
                    'unsubscribed' => $strno, 'forcesubscribed' => $stryes,
                    'cantsubscribe' => '-'), false, false, true);

                $digestoptions_selector->url->param('id', $forumlv->id);
                if ($forumlv->maildigest === null) {
                    $digestoptions_selector->selected = -1;
                } else {
                    $digestoptions_selector->selected = $forumlv->maildigest;
                }
                $row[] = $OUTPUT->render($digestoptions_selector);
            }

            //If this forumlv has RSS activated, calculate it
            if ($show_rss) {
                if ($forumlv->rsstype and $forumlv->rssarticles) {
                    //Calculate the tolltip text
                    if ($forumlv->rsstype == 1) {
                        $tooltiptext = get_string('rsssubscriberssdiscussions', 'forumlv');
                    } else {
                        $tooltiptext = get_string('rsssubscriberssposts', 'forumlv');
                    }
                    //Get html code for RSS link
                    $row[] = rss_get_link($context->id, $USER->id, 'mod_forumlv', $forumlv->id, $tooltiptext);
                } else {
                    $row[] = '&nbsp;';
                }
            }

            $learningtable->data[] = $row;
        }
    }
}


/// Output the page
$PAGE->navbar->add($strforumlvs);
$PAGE->set_title("$course->shortname: $strforumlvs");
$PAGE->set_heading($course->fullname);
$PAGE->set_button($searchform);
echo $OUTPUT->header();

// Show the subscribe all options only to non-guest, enrolled users
if (!isguestuser() && isloggedin() && $can_subscribe) {
    echo $OUTPUT->box_start('subscription');
    echo html_writer::tag('div',
        html_writer::link(new moodle_url('/mod/forumlv/index.php', array('id'=>$course->id, 'subscribe'=>1, 'sesskey'=>sesskey())),
            get_string('allsubscribe', 'forumlv')),
        array('class'=>'helplink'));
    echo html_writer::tag('div',
        html_writer::link(new moodle_url('/mod/forumlv/index.php', array('id'=>$course->id, 'subscribe'=>0, 'sesskey'=>sesskey())),
            get_string('allunsubscribe', 'forumlv')),
        array('class'=>'helplink'));
    echo $OUTPUT->box_end();
    echo $OUTPUT->box('&nbsp;', 'clearer');
}

if ($generalforumlvs) {
    echo $OUTPUT->heading(get_string('generalforumlvs', 'forumlv'), 2);
    echo html_writer::table($generaltable);
}

if ($learningforumlvs) {
    echo $OUTPUT->heading(get_string('learningforumlvs', 'forumlv'), 2);
    echo html_writer::table($learningtable);
}

echo $OUTPUT->footer();

