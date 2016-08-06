<?php

/// This page prints reports and info about chatlvs

    require_once('../../config.php');
    require_once('lib.php');
    
    /* @lvs depend�ncias dos lvs  */
    use uab\ifce\lvs\business\Item;
    use uab\ifce\lvs\avaliacao\NotasLvFactory;
    use uab\ifce\lvs\moodle2\business\Moodle2CursoLv;
    use uab\ifce\lvs\moodle2\business\Chatlv;
    
    require_once($CFG->dirroot.'/blocks/lvs/biblioteca/lib.php'); // @lvs inclusão do loader dos lvs
    /* --- fim ---- */

    $id            = required_param('id', PARAM_INT);
    $start         = optional_param('start', 0, PARAM_INT);   // Start of period
    $end           = optional_param('end', 0, PARAM_INT);     // End of period
    $deletesession = optional_param('deletesession', 0, PARAM_BOOL);
    $confirmdelete = optional_param('confirmdelete', 0, PARAM_BOOL);
    $show_all      = optional_param('show_all', 0, PARAM_BOOL);

    $url = new moodle_url('/mod/chatlv/report.php', array('id'=>$id));
    if ($start !== 0) {
        $url->param('start', $start);
    }
    if ($end !== 0) {
        $url->param('end', $end);
    }
    if ($deletesession !== 0) {
        $url->param('deletesession', $deletesession);
    }
    if ($confirmdelete !== 0) {
        $url->param('confirmdelete', $confirmdelete);
    }
    $PAGE->set_url($url);

    if (! $cm = get_coursemodule_from_id('chatlv', $id)) {
        print_error('invalidcoursemodule');
    }
    if (! $chatlv = $DB->get_record('chatlv', array('id'=>$cm->instance))) {
        print_error('invalidcoursemodule');
    }
    if (! $course = $DB->get_record('course', array('id'=>$chatlv->course))) {
        print_error('coursemisconf');
    }

    $context = context_module::instance($cm->id);
    $PAGE->set_context($context);
    $PAGE->set_heading($course->fullname);

    require_login($course, false, $cm);

    if (empty($chatlv->studentlogs) && !has_capability('mod/chatlv:readlog', $context)) {
        notice(get_string('nopermissiontoseethechatlvlog', 'chatlv'));
    }

    add_to_log($course->id, 'chatlv', 'report', "report.php?id=$cm->id", $chatlv->id, $cm->id);

    $strchatlvs         = get_string('modulenameplural', 'chatlv');
    $strchatlv          = get_string('modulename', 'chatlv');
    $strchatlvreport    = get_string('chatlvreport', 'chatlv');
    $strseesession    = get_string('seesession', 'chatlv');
    $strdeletesession = get_string('deletesession', 'chatlv');

    $navlinks = array();

    $canexportsess = has_capability('mod/chatlv:exportsession', $context);

/// Print a session if one has been specified

    if ($start and $end and !$confirmdelete) {   // Show a full transcript
        $PAGE->navbar->add($strchatlvreport);
        $PAGE->set_title(format_string($chatlv->name).": $strchatlvreport");
        echo $OUTPUT->header();

    /// Check to see if groups are being used here
        $groupmode = groups_get_activity_groupmode($cm);
        $currentgroup = groups_get_activity_group($cm, true);
        groups_print_activity_menu($cm, $CFG->wwwroot . "/mod/chatlv/report.php?id=$cm->id");

        $params = array('currentgroup'=>$currentgroup, 'chatlvid'=>$chatlv->id, 'start'=>$start, 'end'=>$end);

        // If the user is allocated to a group, only show messages from people
        // in the same group, or no group
        if ($currentgroup) {
            $groupselect = " AND (groupid = :currentgroup OR groupid = 0)";
        } else {
            $groupselect = "";
        }

        if ($deletesession and has_capability('mod/chatlv:deletelog', $context)) {
            echo $OUTPUT->confirm(get_string('deletesessionsure', 'chatlv'),
                         "report.php?id=$cm->id&deletesession=1&confirmdelete=1&start=$start&end=$end",
                         "report.php?id=$cm->id");
        }

        if (!$messages = $DB->get_records_select('chatlv_messages', "chatlvid = :chatlvid AND timestamp >= :start AND timestamp <= :end $groupselect", $params, "timestamp ASC")) {
            echo $OUTPUT->heading(get_string('nomessages', 'chatlv'));

        } else {
            echo '<p class="boxaligncenter">'.userdate($start).' --> '. userdate($end).'</p>';

            echo $OUTPUT->box_start('center');
            $participates = array();
            foreach ($messages as $message) {  // We are walking FORWARDS through messages
                if (!isset($participates[$message->userid])) {
                    $participates[$message->userid] = true;
                }
                $formatmessage = chatlv_format_message($message, $course->id, $USER);
                if (isset($formatmessage->html)) {
                    echo $formatmessage->html;
                }
            }
            $participatedcap = array_key_exists($USER->id, $participates) && has_capability('mod/chatlv:exportparticipatedsession', $context);
            if (!empty($CFG->enableportfolios) && ($canexportsess || $participatedcap)) {
                require_once($CFG->libdir . '/portfoliolib.php');
                $buttonoptions  = array(
                    'id'    => $cm->id,
                    'start' => $start,
                    'end'   => $end,
                );
                $button = new portfolio_add_button();
                $button->set_callback_options('chatlv_portfolio_caller', $buttonoptions, 'mod_chatlv');
                $button->render();
            }
            echo $OUTPUT->box_end();
        }

        if (!$deletesession or !has_capability('mod/chatlv:deletelog', $context)) {
            echo $OUTPUT->continue_button("report.php?id=$cm->id");
        }

        echo $OUTPUT->footer();
        exit;
    }


/// Print the Sessions display
    $PAGE->navbar->add($strchatlvreport);
    $PAGE->set_title(format_string($chatlv->name).": $strchatlvreport");
    echo $OUTPUT->header();

    echo $OUTPUT->heading(format_string($chatlv->name).': '.get_string('sessions', 'chatlv'));


/// Check to see if groups are being used here
    if ($groupmode = groups_get_activity_groupmode($cm)) {   // Groups are being used
        $currentgroup = groups_get_activity_group($cm, true);
        groups_print_activity_menu($cm, $CFG->wwwroot . "/mod/chatlv/report.php?id=$cm->id");
    } else {
        $currentgroup = false;
    }

    $params = array('currentgroup'=>$currentgroup, 'chatlvid'=>$chatlv->id, 'start'=>$start, 'end'=>$end);

    // If the user is allocated to a group, only show discussions with people in
    // the same group, or no group
    if (!empty($currentgroup)) {
        $groupselect = " AND (groupid = :currentgroup OR groupid = 0)";
    } else {
        $groupselect = "";
    }

/// Delete a session if one has been specified

    if ($deletesession and has_capability('mod/chatlv:deletelog', $context) and $confirmdelete and $start and $end and confirm_sesskey()) {
        $DB->delete_records_select('chatlv_messages', "chatlvid = :chatlvid AND timestamp >= :start AND
                                                     timestamp <= :end $groupselect", $params);
        $strdeleted  = get_string('deleted');
        echo $OUTPUT->notification("$strdeleted: ".userdate($start).' --> '. userdate($end));
        unset($deletesession);
    }


/// Get the messages
    if (empty($messages)) {   /// May have already got them above
        if (!$messages = $DB->get_records_select('chatlv_messages', "chatlvid = :chatlvid $groupselect", $params, "timestamp DESC")) {
            echo $OUTPUT->heading(get_string('nomessages', 'chatlv'));
            echo $OUTPUT->footer();
            exit;
        }
    }

    if ($show_all) {
        echo $OUTPUT->heading(get_string('listing_all_sessions', 'chatlv') .
                      '&nbsp;<a href="report.php?id='.$cm->id.'&amp;show_all=0">' .
                      get_string('list_complete_sessions', 'chatlv') .  '</a>');
    }

/// Show all the sessions

    $sessiongap        = 5 * 60;    // 5 minutes silence means a new session
    $sessionend        = 0;
    $sessionstart      = 0;
    $sessionusers      = array();
    $lasttime          = 0;
    $complete_sessions = 0;

    $messagesleft = count($messages);

    foreach ($messages as $message) {  // We are walking BACKWARDS through the messages

        $messagesleft --;              // Countdown

        if (!$lasttime) {
            $lasttime = $message->timestamp;
        }
        if (!$sessionend) {
            $sessionend = $message->timestamp;
        }
        if ((($lasttime - $message->timestamp) < $sessiongap) and $messagesleft) {  // Same session
            if ($message->userid and !$message->system) {       // Remember user and count messages
                if (empty($sessionusers[$message->userid])) {
                    $sessionusers[$message->userid] = 1;
                } else {
                    $sessionusers[$message->userid] ++;
                }
            }
        } else {
            $sessionstart = $lasttime;

            $is_complete = ($sessionend - $sessionstart > 60 and count($sessionusers) > 1);
            if ($show_all or $is_complete) {

                echo '<p align="center">'.userdate($sessionstart).' --> '. userdate($sessionend).'</p>';

                echo $OUTPUT->box_start();

                arsort($sessionusers);
                foreach ($sessionusers as $sessionuser => $usermessagecount) {
                    if ($user = $DB->get_record('user', array('id'=>$sessionuser))) {
                        $OUTPUT->user_picture($user, array('courseid'=>$course->id));
                        echo '&nbsp;'.fullname($user, true); // XXX TODO  use capability instead of true
                        echo "&nbsp;($usermessagecount)<br />";
                    }
                }

                echo '<p align="right">';
                echo "<a href=\"report.php?id=$cm->id&amp;start=$sessionstart&amp;end=$sessionend\">$strseesession</a>";
                $participatedcap = (array_key_exists($USER->id, $sessionusers) && has_capability('mod/chatlv:exportparticipatedsession', $context));
                if (!empty($CFG->enableportfolios) && ($canexportsess || $participatedcap)) {
                    require_once($CFG->libdir . '/portfoliolib.php');
                    $buttonoptions  = array(
                        'id'    => $cm->id,
                        'start' => $sessionstart,
                        'end'   => $sessionend,
                    );
                    $button = new portfolio_add_button();
                    $button->set_callback_options('chatlv_portfolio_caller', $buttonoptions, 'mod_chatlv');
                    $portfoliobutton = $button->to_html(PORTFOLIO_ADD_TEXT_LINK);
                    if (!empty($portfoliobutton)) {
                        echo '<br />' . $portfoliobutton;
                    }
                }
                if (has_capability('mod/chatlv:deletelog', $context)) {
                    echo "<br /><a href=\"report.php?id=$cm->id&amp;start=$sessionstart&amp;end=$sessionend&amp;deletesession=1\">$strdeletesession</a>";
                }
                echo '</p>';
                echo $OUTPUT->box_end();
            }
            if ($is_complete) {
                $complete_sessions++;
            }

            $sessionend = $message->timestamp;
            $sessionusers = array();
            $sessionusers[$message->userid] = 1;
        }
        $lasttime = $message->timestamp;
    }

    if (!empty($CFG->enableportfolios) && $canexportsess) {
        require_once($CFG->libdir . '/portfoliolib.php');
        $button = new portfolio_add_button();
        $button->set_callback_options('chatlv_portfolio_caller', array('id' => $cm->id), 'mod_chatlv');
        $button->render(null, get_string('addalltoportfolio', 'portfolio'));
    }


    if (!$show_all and $complete_sessions == 0) {
        echo $OUTPUT->heading(get_string('no_complete_sessions_found', 'chatlv') .
                      '&nbsp;<a href="report.php?id='.$cm->id.'&amp;show_all=1">' .
                      get_string('list_all_sessions', 'chatlv') .
                      '</a>');
    }

/// Finish the page
    echo $OUTPUT->footer();
