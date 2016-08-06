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
 * Library of functions and constants for module chatlv
 *
 * @package   mod-chatlv
 * @copyright 1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once($CFG->dirroot.'/calendar/lib.php');

/** @lvs depend�ncias dos lvs  */
use uab\ifce\lvs\business\Item;
use uab\ifce\lvs\avaliacao\NotasLvFactory;
use uab\ifce\lvs\moodle2\business\Moodle2CursoLv;
use uab\ifce\lvs\moodle2\business\ChatsLv;
use uab\ifce\lvs\moodle2\business\Chatlv;

require_once($CFG->dirroot.'/blocks/lvs/biblioteca/lib.php'); // @lvs inclus�o do loader dos lvs
// fim lvs

// The HTML head for the message window to start with (<!-- nix --> is used to get some browsers starting with output
global $CHAT_HTMLHEAD;
$CHAT_HTMLHEAD = "<!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.0 Transitional//EN\" \"http://www.w3.org/TR/REC-html40/loose.dtd\"><html><head></head>\n<body>\n\n".padding_chatlv(200);

// The HTML head for the message window to start with (with js scrolling)
global $CHAT_HTMLHEAD_JS;
$CHAT_HTMLHEAD_JS = <<<EOD
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.0 Transitional//EN" "http://www.w3.org/TR/REC-html40/loose.dtd">
<html><head><script type="text/javascript">
//<![CDATA[
function move(){
    if (scroll_active)
        window.scroll(1,400000);
    window.setTimeout("move()",100);
}
var scroll_active = true;
move();
//]]>
</script>
</head>
<body onBlur="scroll_active = true" onFocus="scroll_active = false">
EOD;
global $CHAT_HTMLHEAD_JS;
$CHAT_HTMLHEAD_JS .= padding_chatlv(200);

// The HTML code for standard empty pages (e.g. if a user was kicked out)
global $CHAT_HTMLHEAD_OUT;
$CHAT_HTMLHEAD_OUT = "<!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.0 Transitional//EN\" \"http://www.w3.org/TR/REC-html40/loose.dtd\"><html><head><title>You are out!</title></head><body></body></html>";

// The HTML head for the message input page
global $CHAT_HTMLHEAD_MSGINPUT;
$CHAT_HTMLHEAD_MSGINPUT = "<!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.0 Transitional//EN\" \"http://www.w3.org/TR/REC-html40/loose.dtd\"><html><head><title>Message Input</title></head><body>";

// The HTML code for the message input page, with JavaScript
global $CHAT_HTMLHEAD_MSGINPUT_JS;
$CHAT_HTMLHEAD_MSGINPUT_JS = <<<EOD
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.0 Transitional//EN" "http://www.w3.org/TR/REC-html40/loose.dtd">
<html>
    <head><title>Message Input</title>
    <script type="text/javascript">
    //<![CDATA[
    scroll_active = true;
    function empty_field_and_submit(){
        document.fdummy.arsc_message.value=document.f.arsc_message.value;
        document.fdummy.submit();
        document.f.arsc_message.focus();
        document.f.arsc_message.select();
        return false;
    }
    //]]>
    </script>
    </head><body OnLoad="document.f.arsc_message.focus();document.f.arsc_message.select();">;
EOD;

// Dummy data that gets output to the browser as needed, in order to make it show output
global $CHAT_DUMMY_DATA;
$CHAT_DUMMY_DATA = padding_chatlv(200);

/**
 * @param int $n
 * @return string
 */
function padding_chatlv($n){
    $str = '';
    for($i=0; $i<$n; $i++){
        $str.="<!-- nix -->\n";
    }
    return $str;
}

/**
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will create a new instance and return the id number
 * of the new instance.
 *
 * @global object
 * @param object $chatlv
 * @return int
 */
function chatlv_add_instance($chatlv) {
    global $DB;

    $chatlv->timemodified = time();
    
    //@lvs se o checkbox estiver desmarcado setar para 0
    $chatlv->exibir = (isset($chatlv->exibir)) ? 1 : 0;
    
    $returnid = $DB->insert_record("chatlv", $chatlv);

    $event = new stdClass();
    $event->name        = $chatlv->name;
    $event->description = format_module_intro('chatlv', $chatlv, $chatlv->coursemodule);
    $event->courseid    = $chatlv->course;
    $event->groupid     = 0;
    $event->userid      = 0;
    $event->modulename  = 'chatlv';
    $event->instance    = $returnid;
    $event->eventtype   = 'chatlvtime';
    $event->timestart   = $chatlv->chatlvtime;
    $event->timeduration = 0;

    calendar_event::create($event);

    return $returnid;
}

/**
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will update an existing instance with new data.
 *
 * @global object
 * @param object $chatlv
 * @return bool
 */
function chatlv_update_instance($chatlv) {
    global $DB;

    $chatlv->timemodified = time();
    $chatlv->id = $chatlv->instance;

    //@lvs se o checkbox estiver desmarcado setar para 0
    $chatlv->exibir = (isset($chatlv->exibir)) ? 1 : 0;
    
    $DB->update_record("chatlv", $chatlv);

    $event = new stdClass();

    if ($event->id = $DB->get_field('event', 'id', array('modulename'=>'chatlv', 'instance'=>$chatlv->id))) {

        $event->name        = $chatlv->name;
        $event->description = format_module_intro('chatlv', $chatlv, $chatlv->coursemodule);
        $event->timestart   = $chatlv->chatlvtime;

        $calendarevent = calendar_event::load($event->id);
        $calendarevent->update($event);
    }

    return true;
}

/**
 * Given an ID of an instance of this module,
 * this function will permanently delete the instance
 * and any data that depends on it.
 *
 * @global object
 * @param int $id
 * @return bool
 */
function chatlv_delete_instance($id) {
    global $DB;


    if (! $chatlv = $DB->get_record('chatlv', array('id'=>$id))) {
        return false;
    }

    $result = true;

    // Delete any dependent records here

    if (! $DB->delete_records('chatlv', array('id'=>$chatlv->id))) {
        $result = false;
    }
    if (! $DB->delete_records('chatlv_messages', array('chatlvid'=>$chatlv->id))) {
        $result = false;
    }
    if (! $DB->delete_records('chatlv_messages_current', array('chatlvid'=>$chatlv->id))) {
        $result = false;
    }
    if (! $DB->delete_records('chatlv_users', array('chatlvid'=>$chatlv->id))) {
        $result = false;
    }

    if (! $DB->delete_records('event', array('modulename'=>'chatlv', 'instance'=>$chatlv->id))) {
        $result = false;
    }
    
    /** @lvs remove notas, avaliações e configuração do chatlv */
    $cursolv = new Moodle2CursoLv($chatlv->course);
    $gerenciadorChats = new ChatsLv($cursolv);
    $gerenciadorChats->removerAtividade($chatlv->id);
    // lvs fim

    return $result;
}

/**
 * Return a small object with summary information about what a
 * user has done with a given particular instance of this module
 * Used for user activity reports.
 * <code>
 * $return->time = the time they did it
 * $return->info = a short text description
 * </code>
 *
 * @param object $course
 * @param object $user
 * @param object $mod
 * @param object $chatlv
 * @return void
 */
function chatlv_user_outline($course, $user, $mod, $chatlv) {
    return NULL;
}

/**
 * Print a detailed representation of what a  user has done with
 * a given particular instance of this module, for user activity reports.
 *
 * @param object $course
 * @param object $user
 * @param object $mod
 * @param object $chatlv
 * @return bool
 */
function chatlv_user_complete($course, $user, $mod, $chatlv) {
    return true;
}

/**
 * Given a course and a date, prints a summary of all chatlv rooms past and present
 * This function is called from block_recent_activity
 *
 * @global object
 * @global object
 * @global object
 * @param object $course
 * @param bool $viewfullnames
 * @param int|string $timestart Timestamp
 * @return bool
 */
function chatlv_print_recent_activity($course, $viewfullnames, $timestart) {
    global $CFG, $USER, $DB, $OUTPUT;

    // this is approximate only, but it is really fast ;-)
    $timeout = $CFG->chatlv_old_ping * 10;

    if (!$mcms = $DB->get_records_sql("SELECT cm.id, MAX(chm.timestamp) AS lasttime
                                         FROM {course_modules} cm
                                         JOIN {modules} md        ON md.id = cm.module
                                         JOIN {chatlv} ch           ON ch.id = cm.instance
                                         JOIN {chatlv_messages} chm ON chm.chatlvid = ch.id
                                        WHERE chm.timestamp > ? AND ch.course = ? AND md.name = 'chatlv'
                                     GROUP BY cm.id
                                     ORDER BY lasttime ASC", array($timestart, $course->id))) {
         return false;
    }

    $past     = array();
    $current  = array();
    $modinfo = get_fast_modinfo($course); // reference needed because we might load the groups

    foreach ($mcms as $cmid=>$mcm) {
        if (!array_key_exists($cmid, $modinfo->cms)) {
            continue;
        }
        $cm = $modinfo->cms[$cmid];
        $cm->lasttime = $mcm->lasttime;
        if (!$modinfo->cms[$cm->id]->uservisible) {
            continue;
        }

        if (groups_get_activity_groupmode($cm) != SEPARATEGROUPS
         or has_capability('moodle/site:accessallgroups', context_module::instance($cm->id))) {
            if ($timeout > time() - $cm->lasttime) {
                $current[] = $cm;
            } else {
                $past[] = $cm;
            }

            continue;
        }

        if (is_null($modinfo->groups)) {
            $modinfo->groups = groups_get_user_groups($course->id); // load all my groups and cache it in modinfo
        }

        // verify groups in separate mode
        if (!$mygroupids = $modinfo->groups[$cm->groupingid]) {
            continue;
        }

        // ok, last post was not for my group - we have to query db to get last message from one of my groups
        // only minor problem is that the order will not be correct
        $mygroupids = implode(',', $mygroupids);
        $cm->mygroupids = $mygroupids;

        if (!$mcm = $DB->get_record_sql("SELECT cm.id, MAX(chm.timestamp) AS lasttime
                                           FROM {course_modules} cm
                                           JOIN {chatlv} ch           ON ch.id = cm.instance
                                           JOIN {chatlv_messages_current} chm ON chm.chatlvid = ch.id
                                          WHERE chm.timestamp > ? AND cm.id = ? AND
                                                (chm.groupid IN ($mygroupids) OR chm.groupid = 0)
                                       GROUP BY cm.id", array($timestart, $cm->id))) {
             continue;
        }

        $cm->lasttime = $mcm->lasttime;
        if ($timeout > time() - $cm->lasttime) {
            $current[] = $cm;
        } else {
            $past[] = $cm;
        }
    }

    if (!$past and !$current) {
        return false;
    }

    $strftimerecent = get_string('strftimerecent');

    if ($past) {
        echo $OUTPUT->heading(get_string("pastchatlvs", 'chatlv').':');

        foreach ($past as $cm) {
            $link = $CFG->wwwroot.'/mod/chatlv/view.php?id='.$cm->id;
            $date = userdate($cm->lasttime, $strftimerecent);
            echo '<div class="head"><div class="date">'.$date.'</div></div>';
            echo '<div class="info"><a href="'.$link.'">'.format_string($cm->name,true).'</a></div>';
        }
    }

    if ($current) {
        echo $OUTPUT->heading(get_string("currentchatlvs", 'chatlv').':');

        $oldest = floor((time()-$CFG->chatlv_old_ping)/10)*10;  // better db caching

        $timeold    = time() - $CFG->chatlv_old_ping;
        $timeold    = floor($timeold/10)*10;  // better db caching
        $timeoldext = time() - ($CFG->chatlv_old_ping*10); // JSless gui_basic needs much longer timeouts
        $timeoldext = floor($timeoldext/10)*10;  // better db caching

        $params = array('timeold'=>$timeold, 'timeoldext'=>$timeoldext, 'cmid'=>$cm->id);

        $timeout = "AND (chu.version<>'basic' AND chu.lastping>:timeold) OR (chu.version='basic' AND chu.lastping>:timeoldext)";

        foreach ($current as $cm) {
            //count users first
            if (isset($cm->mygroupids)) {
                $groupselect = "AND (chu.groupid IN ({$cm->mygroupids}) OR chu.groupid = 0)";
            } else {
                $groupselect = "";
            }

            if (!$users = $DB->get_records_sql("SELECT u.id, u.firstname, u.lastname, u.email, u.picture
                                                  FROM {course_modules} cm
                                                  JOIN {chatlv} ch        ON ch.id = cm.instance
                                                  JOIN {chatlv_users} chu ON chu.chatlvid = ch.id
                                                  JOIN {user} u         ON u.id = chu.userid
                                                 WHERE cm.id = :cmid $timeout $groupselect
                                              GROUP BY u.id, u.firstname, u.lastname, u.email, u.picture", $params)) {
            }

            $link = $CFG->wwwroot.'/mod/chatlv/view.php?id='.$cm->id;
            $date = userdate($cm->lasttime, $strftimerecent);

            echo '<div class="head"><div class="date">'.$date.'</div></div>';
            echo '<div class="info"><a href="'.$link.'">'.format_string($cm->name,true).'</a></div>';
            echo '<div class="userlist">';
            if ($users) {
                echo '<ul>';
                    foreach ($users as $user) {
                        echo '<li>'.fullname($user, $viewfullnames).'</li>';
                    }
                echo '</ul>';
            }
            echo '</div>';
        }
    }

    return true;
}

/**
 * Function to be run periodically according to the moodle cron
 * This function searches for things that need to be done, such
 * as sending out mail, toggling flags etc ...
 *
 * @global object
 * @return bool
 */
function chatlv_cron () {
    global $DB;

    chatlv_update_chatlv_times();

    chatlv_delete_old_users();

    /// Delete old messages with a
    /// single SQL query.
    $subselect = "SELECT c.keepdays
                    FROM {chatlv} c
                   WHERE c.id = {chatlv_messages}.chatlvid";

    $sql = "DELETE
              FROM {chatlv_messages}
             WHERE ($subselect) > 0 AND timestamp < ( ".time()." -($subselect) * 24 * 3600)";

    $DB->execute($sql);

    $sql = "DELETE
              FROM {chatlv_messages_current}
             WHERE timestamp < ( ".time()." - 8 * 3600)";

    $DB->execute($sql);

    return true;
}

/**
 * This standard function will check all instances of this module
 * and make sure there are up-to-date events created for each of them.
 * If courseid = 0, then every chatlv event in the site is checked, else
 * only chatlv events belonging to the course specified are checked.
 * This function is used, in its new format, by restore_refresh_events()
 *
 * @global object
 * @param int $courseid
 * @return bool
 */
function chatlv_refresh_events($courseid = 0) {
    global $DB;

    if ($courseid) {
        if (! $chatlvs = $DB->get_records("chatlv", array("course"=>$courseid))) {
            return true;
        }
    } else {
        if (! $chatlvs = $DB->get_records("chatlv")) {
            return true;
        }
    }
    $moduleid = $DB->get_field('modules', 'id', array('name'=>'chatlv'));

    foreach ($chatlvs as $chatlv) {
        $cm = get_coursemodule_from_id('chatlv', $chatlv->id);
        $event = new stdClass();
        $event->name        = $chatlv->name;
        $event->description = format_module_intro('chatlv', $chatlv, $cm->id);
        $event->timestart   = $chatlv->chatlvtime;

        if ($event->id = $DB->get_field('event', 'id', array('modulename'=>'chatlv', 'instance'=>$chatlv->id))) {
            $calendarevent = calendar_event::load($event->id);
            $calendarevent->update($event);
        } else {
            $event->courseid    = $chatlv->course;
            $event->groupid     = 0;
            $event->userid      = 0;
            $event->modulename  = 'chatlv';
            $event->instance    = $chatlv->id;
            $event->eventtype   = 'chatlvtime';
            $event->timeduration = 0;
            $event->visible     = $DB->get_field('course_modules', 'visible', array('module'=>$moduleid, 'instance'=>$chatlv->id));

            calendar_event::create($event);
        }
    }
    return true;
}


//////////////////////////////////////////////////////////////////////
/// Functions that require some SQL

/**
 * @global object
 * @param int $chatlvid
 * @param int $groupid
 * @param int $groupingid
 * @return array
 */
function chatlv_get_users($chatlvid, $groupid=0, $groupingid=0) {
    global $DB;

    $params = array('chatlvid'=>$chatlvid, 'groupid'=>$groupid, 'groupingid'=>$groupingid);

    if ($groupid) {
        $groupselect = " AND (c.groupid=:groupid OR c.groupid='0')";
    } else {
        $groupselect = "";
    }

    if (!empty($groupingid)) {
        $groupingjoin = "JOIN {groups_members} gm ON u.id = gm.userid
                         JOIN {groupings_groups} gg ON gm.groupid = gg.groupid AND gg.groupingid = :groupingid ";

    } else {
        $groupingjoin = '';
    }

    $ufields = user_picture::fields('u');
    return $DB->get_records_sql("SELECT DISTINCT $ufields, c.lastmessageping, c.firstping
                                   FROM {chatlv_users} c
                                   JOIN {user} u ON u.id = c.userid $groupingjoin
                                  WHERE c.chatlvid = :chatlvid $groupselect
                               ORDER BY c.firstping ASC", $params);
}

/**
 * @global object
 * @param int $chatlvid
 * @param int $groupid
 * @return array
 */
function chatlv_get_latest_message($chatlvid, $groupid=0) {
    global $DB;

    $params = array('chatlvid'=>$chatlvid, 'groupid'=>$groupid);

    if ($groupid) {
        $groupselect = "AND (groupid=:groupid OR groupid=0)";
    } else {
        $groupselect = "";
    }

    $sql = "SELECT *
        FROM {chatlv_messages_current} WHERE chatlvid = :chatlvid $groupselect
        ORDER BY timestamp DESC";

    // return the lastest one message
    return $DB->get_record_sql($sql, $params, true);
}


//////////////////////////////////////////////////////////////////////
// login if not already logged in

/**
 * login if not already logged in
 *
 * @global object
 * @global object
 * @param int $chatlvid
 * @param string $version
 * @param int $groupid
 * @param object $course
 * @return bool|int Returns the chatlv users sid or false
 */
function chatlv_login_user($chatlvid, $version, $groupid, $course) {
    global $USER, $DB;

    if (($version != 'sockets') and $chatlvuser = $DB->get_record('chatlv_users', array('chatlvid'=>$chatlvid, 'userid'=>$USER->id, 'groupid'=>$groupid))) {
        // this will update logged user information
        $chatlvuser->version  = $version;
        $chatlvuser->ip       = $USER->lastip;
        $chatlvuser->lastping = time();
        $chatlvuser->lang     = current_language();

        // Sometimes $USER->lastip is not setup properly
        // during login. Update with current value if possible
        // or provide a dummy value for the db
        if (empty($chatlvuser->ip)) {
            $chatlvuser->ip = getremoteaddr();
        }

        if (($chatlvuser->course != $course->id) or ($chatlvuser->userid != $USER->id)) {
            return false;
        }
        $DB->update_record('chatlv_users', $chatlvuser);

    } else {
        $chatlvuser = new stdClass();
        $chatlvuser->chatlvid   = $chatlvid;
        $chatlvuser->userid   = $USER->id;
        $chatlvuser->groupid  = $groupid;
        $chatlvuser->version  = $version;
        $chatlvuser->ip       = $USER->lastip;
        $chatlvuser->lastping = $chatlvuser->firstping = $chatlvuser->lastmessageping = time();
        $chatlvuser->sid      = random_string(32);
        $chatlvuser->course   = $course->id; //caching - needed for current_language too
        $chatlvuser->lang     = current_language(); //caching - to resource intensive to find out later

        // Sometimes $USER->lastip is not setup properly
        // during login. Update with current value if possible
        // or provide a dummy value for the db
        if (empty($chatlvuser->ip)) {
            $chatlvuser->ip = getremoteaddr();
        }


        $DB->insert_record('chatlv_users', $chatlvuser);

        if ($version == 'sockets') {
            // do not send 'enter' message, chatlvd will do it
        } else {
            $message = new stdClass();
            $message->chatlvid    = $chatlvuser->chatlvid;
            $message->userid    = $chatlvuser->userid;
            $message->groupid   = $groupid;
            $message->message   = 'enter';
            $message->system    = 1;
            $message->timestamp = time();

            $DB->insert_record('chatlv_messages', $message);
            $DB->insert_record('chatlv_messages_current', $message);
        }
    }

    return $chatlvuser->sid;
}

/**
 * Delete the old and in the way
 *
 * @global object
 * @global object
 */
function chatlv_delete_old_users() {
// Delete the old and in the way
    global $CFG, $DB;

    $timeold = time() - $CFG->chatlv_old_ping;
    $timeoldext = time() - ($CFG->chatlv_old_ping*10); // JSless gui_basic needs much longer timeouts

    $query = "(version<>'basic' AND lastping<?) OR (version='basic' AND lastping<?)";
    $params = array($timeold, $timeoldext);

    if ($oldusers = $DB->get_records_select('chatlv_users', $query, $params) ) {
        $DB->delete_records_select('chatlv_users', $query, $params);
        foreach ($oldusers as $olduser) {
            $message = new stdClass();
            $message->chatlvid    = $olduser->chatlvid;
            $message->userid    = $olduser->userid;
            $message->groupid   = $olduser->groupid;
            $message->message   = 'exit';
            $message->system    = 1;
            $message->timestamp = time();

            $DB->insert_record('chatlv_messages', $message);
            $DB->insert_record('chatlv_messages_current', $message);
        }
    }
}

/**
 * Updates chatlv records so that the next chatlv time is correct
 *
 * @global object
 * @param int $chatlvid
 * @return void
 */
function chatlv_update_chatlv_times($chatlvid=0) {
/// Updates chatlv records so that the next chatlv time is correct
    global $DB;

    $timenow = time();

    $params = array('timenow'=>$timenow, 'chatlvid'=>$chatlvid);

    if ($chatlvid) {
        if (!$chatlvs[] = $DB->get_record_select("chatlv", "id = :chatlvid AND chatlvtime <= :timenow AND schedule > 0", $params)) {
            return;
        }
    } else {
        if (!$chatlvs = $DB->get_records_select("chatlv", "chatlvtime <= :timenow AND schedule > 0", $params)) {
            return;
        }
    }

    foreach ($chatlvs as $chatlv) {
        switch ($chatlv->schedule) {
            case 1: // Single event - turn off schedule and disable
                    $chatlv->chatlvtime = 0;
                    $chatlv->schedule = 0;
                    break;
            case 2: // Repeat daily
                    while ($chatlv->chatlvtime <= $timenow) {
                        $chatlv->chatlvtime += 24 * 3600;
                    }
                    break;
            case 3: // Repeat weekly
                    while ($chatlv->chatlvtime <= $timenow) {
                        $chatlv->chatlvtime += 7 * 24 * 3600;
                    }
                    break;
        }
        $DB->update_record("chatlv", $chatlv);

        $event = new stdClass();           // Update calendar too

        $cond = "modulename='chatlv' AND instance = :chatlvid AND timestart <> :chatlvtime";
        $params = array('chatlvtime'=>$chatlv->chatlvtime, 'chatlvid'=>$chatlvid);

        if ($event->id = $DB->get_field_select('event', 'id', $cond, $params)) {
            $event->timestart   = $chatlv->chatlvtime;
            $calendarevent = calendar_event::load($event->id);
            $calendarevent->update($event, false);
        }
    }
}

/**
 * @global object
 * @global object
 * @param object $message
 * @param int $courseid
 * @param object $sender
 * @param object $currentuser
 * @param string $chatlv_lastrow
 * @return bool|string Returns HTML or false
 */
function chatlv_format_message_manually($message, $courseid, $sender, $currentuser, $chatlv_lastrow=NULL) {
    global $CFG, $USER, $OUTPUT;

    $output = new stdClass();
    $output->beep = false;       // by default
    $output->refreshusers = false; // by default

    // Use get_user_timezone() to find the correct timezone for displaying this message:
    // It's either the current user's timezone or else decided by some Moodle config setting
    // First, "reset" $USER->timezone (which could have been set by a previous call to here)
    // because otherwise the value for the previous $currentuser will take precedence over $CFG->timezone
    $USER->timezone = 99;
    $tz = get_user_timezone($currentuser->timezone);

    // Before formatting the message time string, set $USER->timezone to the above.
    // This will allow dst_offset_on (called by userdate) to work correctly, otherwise the
    // message times appear off because DST is not taken into account when it should be.
    $USER->timezone = $tz;
    $message->strtime = userdate($message->timestamp, get_string('strftimemessage', 'chatlv'), $tz);

    $message->picture = $OUTPUT->user_picture($sender, array('size'=>false, 'courseid'=>$courseid, 'link'=>false));

    if ($courseid) {
        $message->picture = "<a onclick=\"window.open('$CFG->wwwroot/user/view.php?id=$sender->id&amp;course=$courseid')\" href=\"$CFG->wwwroot/user/view.php?id=$sender->id&amp;course=$courseid\">$message->picture</a>";
    }

    //Calculate the row class
    if ($chatlv_lastrow !== NULL) {
        $rowclass = ' class="r'.$chatlv_lastrow.'" ';
    } else {
        $rowclass = '';
    }

    // Start processing the message

    if(!empty($message->system)) {
        // System event
        $output->text = $message->strtime.': '.get_string('message'.$message->message, 'chatlv', fullname($sender));
        $output->html  = '<table class="chatlv-event"><tr'.$rowclass.'><td class="picture">'.$message->picture.'</td><td class="text">';
        $output->html .= '<span class="event">'.$output->text.'</span></td></tr></table>';
        $output->basic = '<dl><dt class="event">'.$message->strtime.': '.get_string('message'.$message->message, 'chatlv', fullname($sender)).'</dt></dl>';

        if($message->message == 'exit' or $message->message == 'enter') {
            $output->refreshusers = true; //force user panel refresh ASAP
        }
        return $output;
    }

    // It's not a system event
    $text = trim($message->message);

    /// Parse the text to clean and filter it
    $options = new stdClass();
    $options->para = false;
    $text = format_text($text, FORMAT_MOODLE, $options, $courseid);

    // And now check for special cases
    $patternTo = '#^\s*To\s([^:]+):(.*)#';
    $special = false;

    if (substr($text, 0, 5) == 'beep ') {
        /// It's a beep!
        $special = true;
        $beepwho = trim(substr($text, 5));

        if ($beepwho == 'all') {   // everyone
            $outinfo = $message->strtime.': '.get_string('messagebeepseveryone', 'chatlv', fullname($sender));
            $outmain = '';
            $output->beep = true;  // (eventually this should be set to
                                   //  to a filename uploaded by the user)

        } else if ($beepwho == $currentuser->id) {  // current user
            $outinfo = $message->strtime.': '.get_string('messagebeepsyou', 'chatlv', fullname($sender));
            $outmain = '';
            $output->beep = true;

        } else {  //something is not caught?
            return false;
        }
    } else if (substr($text, 0, 1) == '/') {     /// It's a user command
        $special = true;
        $pattern = '#(^\/)(\w+).*#';
        preg_match($pattern, $text, $matches);
        $command = isset($matches[2]) ? $matches[2] : false;
        // Support some IRC commands.
        switch ($command){
            case 'me':
                $outinfo = $message->strtime;
                $outmain = '*** <b>'.$sender->firstname.' '.substr($text, 4).'</b>';
                break;
            default:
                // Error, we set special back to false to use the classic message output.
                $special = false;
                break;
        }
    } else if (preg_match($patternTo, $text)) {
        $special = true;
        $matches = array();
        preg_match($patternTo, $text, $matches);
        if (isset($matches[1]) && isset($matches[2])) {
            $outinfo = $message->strtime;
            $outmain = $sender->firstname.' '.get_string('saidto', 'chatlv').' <i>'.$matches[1].'</i>: '.$matches[2];
        } else {
            // Error, we set special back to false to use the classic message output.
            $special = false;
        }
    }

    if(!$special) {
        $outinfo = $message->strtime.' '.$sender->firstname;
        $outmain = $text;
    }

    /// Format the message as a small table

    $output->text  = strip_tags($outinfo.': '.$outmain);

    $output->html  = "<table class=\"chatlv-message\"><tr$rowclass><td class=\"picture\" valign=\"top\">$message->picture</td><td class=\"text\">";
    
    /** @lvs form de avaliação chatlv */
    $itemlv = new Item('chatlv', 'message', $message);
    $gerenciadorNotas = NotasLvFactory::criarGerenciador('moodle2');
    $gerenciadorNotas->setModulo( new Chatlv($message->chatlvid) );
    unset($itemlv->getItem()->picture);
    unset($itemlv->getItem()->message);
     
    $lvs_output = $gerenciadorNotas->avaliacaoAtual($itemlv) . $gerenciadorNotas->avaliadoPor($itemlv) . $gerenciadorNotas->formAvaliacaoAjax($itemlv);
    $output->html .= html_writer::tag('div', $lvs_output);
    // fim lvs
    
    $output->html .= "<span class=\"title\">$outinfo</span>";
    if ($outmain) {
        $output->html .= ": $outmain";
        $output->basic = '<dl><dt class="title">'.$outinfo.':</dt><dd class="text">'.$outmain.'</dd></dl>';
    } else {
        $output->basic = '<dl><dt class="title">'.$outinfo.'</dt></dl>';
    }
    $output->html .= "</td></tr></table>";
    return $output;
}

/**
 * @global object
 * @param object $message
 * @param int $courseid
 * @param object $currentuser
 * @param string $chatlv_lastrow
 * @return bool|string Returns HTML or false
 */
function chatlv_format_message($message, $courseid, $currentuser, $chatlv_lastrow=NULL) {
/// Given a message object full of information, this function
/// formats it appropriately into text and html, then
/// returns the formatted data.
    global $DB;

    static $users;     // Cache user lookups

    if (isset($users[$message->userid])) {
        $user = $users[$message->userid];
    } else if ($user = $DB->get_record('user', array('id'=>$message->userid), user_picture::fields())) {
        $users[$message->userid] = $user;
    } else {
        return NULL;
    }
    return chatlv_format_message_manually($message, $courseid, $user, $currentuser, $chatlv_lastrow);
}

/**
 * @global object
 * @param object $message message to be displayed.
 * @param mixed $chatlvuser user chatlv data
 * @param object $currentuser current user for whom the message should be displayed.
 * @param int $groupingid course module grouping id
 * @param string $theme name of the chatlv theme.
 * @return bool|string Returns HTML or false
 */
function chatlv_format_message_theme ($message, $chatlvuser, $currentuser, $groupingid, $theme = 'bubble') {
    global $CFG, $USER, $OUTPUT, $COURSE, $DB, $PAGE;
    require_once($CFG->dirroot.'/mod/chatlv/locallib.php');

    static $users;     // Cache user lookups

    $result = new stdClass();

    if (file_exists($CFG->dirroot . '/mod/chatlv/gui_ajax/theme/'.$theme.'/config.php')) {
        include($CFG->dirroot . '/mod/chatlv/gui_ajax/theme/'.$theme.'/config.php');
    }

    if (isset($users[$message->userid])) {
        $sender = $users[$message->userid];
    } else if ($sender = $DB->get_record('user', array('id'=>$message->userid), user_picture::fields())) {
        $users[$message->userid] = $sender;
    } else {
        return NULL;
    }

    $USER->timezone = 99;
    $tz = get_user_timezone($currentuser->timezone);
    $USER->timezone = $tz;

    if (empty($chatlvuser->course)) {
        $courseid = $COURSE->id;
    } else {
        $courseid = $chatlvuser->course;
    }

    $message->strtime = userdate($message->timestamp, get_string('strftimemessage', 'chatlv'), $tz);
    $message->picture = $OUTPUT->user_picture($sender, array('courseid'=>$courseid));

    $message->picture = "<a target='_blank' href=\"$CFG->wwwroot/user/view.php?id=$sender->id&amp;course=$courseid\">$message->picture</a>";

    // Start processing the message
    if(!empty($message->system)) {
        $result->type = 'system';

        $senderprofile = $CFG->wwwroot.'/user/view.php?id='.$sender->id.'&amp;course='.$courseid;
        $event = get_string('message'.$message->message, 'chatlv', fullname($sender));
        $eventmessage = new event_message($senderprofile, fullname($sender), $message->strtime, $event, $theme);

        $output = $PAGE->get_renderer('mod_chatlv');
        $result->html = $output->render($eventmessage);

        return $result;
    }

    // It's not a system event
    $text = trim($message->message);

    /// Parse the text to clean and filter it
    $options = new stdClass();
    $options->para = false;
    $text = format_text($text, FORMAT_MOODLE, $options, $courseid);

    // And now check for special cases
    $special = false;
    $outtime = $message->strtime;

    // Initialise variables.
    $outmain = '';
    $patternTo = '#^\s*To\s([^:]+):(.*)#';

    if (substr($text, 0, 5) == 'beep ') {
        $special = true;
        /// It's a beep!
        $result->type = 'beep';
        $beepwho = trim(substr($text, 5));

        if ($beepwho == 'all') {   // everyone
            $outmain =  get_string('messagebeepseveryone', 'chatlv', fullname($sender));
        } else if ($beepwho == $currentuser->id) {  // current user
            $outmain = get_string('messagebeepsyou', 'chatlv', fullname($sender));
        } else if ($sender->id == $currentuser->id) {  //something is not caught?
            //allow beep for a active chatlv user only, else user can beep anyone and get fullname
            if (!empty($chatlvuser) && is_numeric($beepwho)) {
               $chatlvusers = chatlv_get_users($chatlvuser->chatlvid, $chatlvuser->groupid, $groupingid);
               if (array_key_exists($beepwho, $chatlvusers)) {
                   $outmain = get_string('messageyoubeep', 'chatlv', fullname($chatlvusers[$beepwho]));
               } else {
                   $outmain = get_string('messageyoubeep', 'chatlv', $beepwho);
               }
            } else {
                $outmain = get_string('messageyoubeep', 'chatlv', $beepwho);
            }
        }
    } else if (substr($text, 0, 1) == '/') {     /// It's a user command
        $special = true;
        $result->type = 'command';
        $pattern = '#(^\/)(\w+).*#';
        preg_match($pattern, $text, $matches);
        $command = isset($matches[2]) ? $matches[2] : false;
        // Support some IRC commands.
        switch ($command){
            case 'me':
                $outmain = '*** <b>'.$sender->firstname.' '.substr($text, 4).'</b>';
                break;
            default:
                // Error, we set special back to false to use the classic message output.
                $special = false;
                break;
        }
    } else if (preg_match($patternTo, $text)) {
        $special = true;
        $result->type = 'dialogue';
        $matches = array();
        preg_match($patternTo, $text, $matches);
        if (isset($matches[1]) && isset($matches[2])) {
            $outmain = $sender->firstname.' <b>'.get_string('saidto', 'chatlv').'</b> <i>'.$matches[1].'</i>: '.$matches[2];
        } else {
            // Error, we set special back to false to use the classic message output.
            $special = false;
        }
    }

    if (!$special) {
        $outmain = $text;
    }

    $result->text = strip_tags($outtime.': '.$outmain);

    $mymessageclass = '';
    if ($sender->id == $USER->id) {
        $mymessageclass = 'chatlv-message-mymessage';
    }

    $senderprofile = $CFG->wwwroot.'/user/view.php?id='.$sender->id.'&amp;course='.$courseid;
    $usermessage = new user_message($senderprofile, fullname($sender), $message->picture, $mymessageclass, $outtime, $outmain, $theme);

    $usermessage->messagelv = $message;
    $output = $PAGE->get_renderer('mod_chatlv');
    $result->html = $output->render($usermessage);

    //When user beeps other user, then don't show any timestamp to other users in chatlv.
    if (('' === $outmain) && $special) {
        return false;
    } else {
        return $result;
    }
}

/**
 * @global object $DB
 * @global object $CFG
 * @global object $COURSE
 * @global object $OUTPUT
 * @param object $users
 * @param object $course
 * @return array return formatted user list
 */
function chatlv_format_userlist($users, $course) {
    global $CFG, $DB, $COURSE, $OUTPUT;
    $result = array();
    foreach($users as $user){
        $item = array();
        $item['name'] = fullname($user);
        $item['url'] = $CFG->wwwroot.'/user/view.php?id='.$user->id.'&amp;course='.$course->id;
        $item['picture'] = $OUTPUT->user_picture($user);
        $item['id'] = $user->id;
        $result[] = $item;
    }
    return $result;
}

/**
 * Print json format error
 * @param string $level
 * @param string $msg
 */
function chatlv_print_error($level, $msg) {
    header('Content-Length: ' . ob_get_length() );
    $error = new stdClass();
    $error->level = $level;
    $error->msg   = $msg;
    $response['error'] = $error;
    echo json_encode($response);
    ob_end_flush();
    exit;
}

/**
 * @return array
 */
function chatlv_get_view_actions() {
    return array('view','view all','report');
}

/**
 * @return array
 */
function chatlv_get_post_actions() {
    return array('talk');
}

/**
 * @global object
 * @global object
 * @param array $courses
 * @param array $htmlarray Passed by reference
 */
function chatlv_print_overview($courses, &$htmlarray) {
    global $USER, $CFG;

    if (empty($courses) || !is_array($courses) || count($courses) == 0) {
        return array();
    }

    if (!$chatlvs = get_all_instances_in_courses('chatlv',$courses)) {
        return;
    }

    $strchatlv = get_string('modulename', 'chatlv');
    $strnextsession  = get_string('nextsession', 'chatlv');

    foreach ($chatlvs as $chatlv) {
        if ($chatlv->chatlvtime and $chatlv->schedule) {  // A chatlv is scheduled
            $str = '<div class="chatlv overview"><div class="name">'.
                   $strchatlv.': <a '.($chatlv->visible?'':' class="dimmed"').
                   ' href="'.$CFG->wwwroot.'/mod/chatlv/view.php?id='.$chatlv->coursemodule.'">'.
                   $chatlv->name.'</a></div>';
            $str .= '<div class="info">'.$strnextsession.': '.userdate($chatlv->chatlvtime).'</div></div>';

            if (empty($htmlarray[$chatlv->course]['chatlv'])) {
                $htmlarray[$chatlv->course]['chatlv'] = $str;
            } else {
                $htmlarray[$chatlv->course]['chatlv'] .= $str;
            }
        }
    }
}


/**
 * Implementation of the function for printing the form elements that control
 * whether the course reset functionality affects the chatlv.
 *
 * @param object $mform form passed by reference
 */
function chatlv_reset_course_form_definition(&$mform) {
    $mform->addElement('header', 'chatlvheader', get_string('modulenameplural', 'chatlv'));
    $mform->addElement('advcheckbox', 'reset_chatlv', get_string('removemessages','chatlv'));
}

/**
 * Course reset form defaults.
 *
 * @param object $course
 * @return array
 */
function chatlv_reset_course_form_defaults($course) {
    return array('reset_chatlv'=>1);
}

/**
 * Actual implementation of the reset course functionality, delete all the
 * chatlv messages for course $data->courseid.
 *
 * @global object
 * @global object
 * @param object $data the data submitted from the reset course.
 * @return array status array
 */
function chatlv_reset_userdata($data) {
    global $CFG, $DB;

    $componentstr = get_string('modulenameplural', 'chatlv');
    $status = array();

    if (!empty($data->reset_chatlv)) {
        $chatlvessql = "SELECT ch.id
                        FROM {chatlv} ch
                       WHERE ch.course=?";
        $params = array($data->courseid);

        $DB->delete_records_select('chatlv_messages', "chatlvid IN ($chatlvessql)", $params);
        $DB->delete_records_select('chatlv_messages_current', "chatlvid IN ($chatlvessql)", $params);
        $DB->delete_records_select('chatlv_users', "chatlvid IN ($chatlvessql)", $params);
        $status[] = array('component'=>$componentstr, 'item'=>get_string('removemessages', 'chatlv'), 'error'=>false);
    }

    /// updating dates - shift may be negative too
    if ($data->timeshift) {
        shift_course_mod_dates('chatlv', array('chatlvtime'), $data->timeshift, $data->courseid);
        $status[] = array('component'=>$componentstr, 'item'=>get_string('datechanged'), 'error'=>false);
    }

    return $status;
}

/**
 * Returns all other caps used in module
 *
 * @return array
 */
function chatlv_get_extra_capabilities() {
    return array('moodle/site:accessallgroups', 'moodle/site:viewfullnames');
}


/**
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed True if module supports feature, null if doesn't know
 */
function chatlv_supports($feature) {
    switch($feature) {
        case FEATURE_GROUPS:                  return true;
        case FEATURE_GROUPINGS:               return true;
        case FEATURE_GROUPMEMBERSONLY:        return true;
        case FEATURE_MOD_INTRO:               return true;
        case FEATURE_BACKUP_MOODLE2:          return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS: return true;
        case FEATURE_GRADE_HAS_GRADE:         return false;
        case FEATURE_GRADE_OUTCOMES:          return true;
        case FEATURE_SHOW_DESCRIPTION:        return true;

        default: return null;
    }
}

function chatlv_extend_navigation($navigation, $course, $module, $cm) {
    global $CFG;

    $currentgroup = groups_get_activity_group($cm, true);

    if (has_capability('mod/chatlv:chatlv', context_module::instance($cm->id))) {
        $strenterchatlv    = get_string('enterchatlv', 'chatlv');

        $target = $CFG->wwwroot.'/mod/chatlv/';
        $params = array('id'=>$cm->instance);

        if ($currentgroup) {
            $params['groupid'] = $currentgroup;
        }

        $links = array();

        $url = new moodle_url($target.'gui_'.$CFG->chatlv_method.'/index.php', $params);
        $action = new popup_action('click', $url, 'chatlv'.$course->id.$cm->instance.$currentgroup, array('height' => 500, 'width' => 700));
        $links[] = new action_link($url, $strenterchatlv, $action);

        $url = new moodle_url($target.'gui_basic/index.php', $params);
        $action = new popup_action('click', $url, 'chatlv'.$course->id.$cm->instance.$currentgroup, array('height' => 500, 'width' => 700));
        $links[] = new action_link($url, get_string('noframesjs', 'message'), $action);

        foreach ($links as $link) {
            $navigation->add($link->text, $link, navigation_node::TYPE_SETTING, null ,null, new pix_icon('i/group' , ''));
        }
    }

    $chatlvusers = chatlv_get_users($cm->instance, $currentgroup, $cm->groupingid);
    if (is_array($chatlvusers) && count($chatlvusers)>0) {
        $users = $navigation->add(get_string('currentusers', 'chatlv'));
        foreach ($chatlvusers as $chatlvuser) {
            $userlink = new moodle_url('/user/view.php', array('id'=>$chatlvuser->id,'course'=>$course->id));
            $users->add(fullname($chatlvuser).' '.format_time(time() - $chatlvuser->lastmessageping), $userlink, navigation_node::TYPE_USER, null, null, new pix_icon('i/user', ''));
        }
    }
}

/**
 * Adds module specific settings to the settings block
 *
 * @param settings_navigation $settings The settings navigation object
 * @param navigation_node $chatlvnode The node to add module settings to
 */
function chatlv_extend_settings_navigation(settings_navigation $settings, navigation_node $chatlvnode) {
    global $DB, $PAGE, $USER;
    $chatlv = $DB->get_record("chatlv", array("id" => $PAGE->cm->instance));

    if ($chatlv->chatlvtime && $chatlv->schedule) {
        $nextsessionnode = $chatlvnode->add(get_string('nextsession', 'chatlv').': '.userdate($chatlv->chatlvtime).' ('.usertimezone($USER->timezone));
        $nextsessionnode->add_class('note');
    }

    $currentgroup = groups_get_activity_group($PAGE->cm, true);
    if ($currentgroup) {
        $groupselect = " AND groupid = '$currentgroup'";
    } else {
        $groupselect = '';
    }

    if ($chatlv->studentlogs || has_capability('mod/chatlv:readlog',$PAGE->cm->context)) {
        if ($DB->get_records_select('chatlv_messages', "chatlvid = ? $groupselect", array($chatlv->id))) {
            $chatlvnode->add(get_string('viewreport', 'chatlv'), new moodle_url('/mod/chatlv/report.php', array('id'=>$PAGE->cm->id)));
        }
    }
}

/**
 * user logout event handler
 *
 * @param object $user full $USER object
 */
function chatlv_user_logout($user) {
    global $DB;
    $DB->delete_records('chatlv_users', array('userid'=>$user->id));
}

/**
 * Return a list of page types
 * @param string $pagetype current page type
 * @param stdClass $parentcontext Block's parent context
 * @param stdClass $currentcontext Current context of block
 */
function chatlv_page_type_list($pagetype, $parentcontext, $currentcontext) {
    $module_pagetype = array('mod-chatlv-*'=>get_string('page-mod-chatlv-x', 'chatlv'));
    return $module_pagetype;
}
