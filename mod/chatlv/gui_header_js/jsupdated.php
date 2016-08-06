<?php

/** jsupdated.php - notes by Martin Langhoff <martin@catalyst.net.nz>
 **
 ** This is an alternative version of jsupdate.php that acts
 ** as a long-running daemon. It will feed/stall/feed JS updates
 ** to the client. From the module configuration select "Stream"
 ** updates.
 **
 ** The client connection is not forever though. Once we reach
 ** CHAT_MAX_CLIENT_UPDATES, it will force the client to re-fetch it.
 **
 ** This buys us all the benefits that chatlvd has, minus the setup,
 ** as we are using apache to do the daemon handling.
 **
 **/


define('CHAT_MAX_CLIENT_UPDATES', 1000);
define('NO_MOODLE_COOKIES', true); // session not used here
define('NO_OUTPUT_BUFFERING', true);

require('../../../config.php');
require('../lib.php');

// we are going to run for a long time
// avoid being terminated by php
@set_time_limit(0);

$chatlv_sid      = required_param('chatlv_sid',          PARAM_ALPHANUM);
$chatlv_lasttime = optional_param('chatlv_lasttime',  0, PARAM_INT);
$chatlv_lastrow  = optional_param('chatlv_lastrow',   1, PARAM_INT);
$chatlv_lastid   = optional_param('chatlv_lastid',    0, PARAM_INT);

$url = new moodle_url('/mod/chatlv/gui_header_js/jsupdated.php', array('chatlv_sid'=>$chatlv_sid));
if ($chatlv_lasttime !== 0) {
    $url->param('chatlv_lasttime', $chatlv_lasttime);
}
if ($chatlv_lastrow !== 1) {
    $url->param('chatlv_lastrow', $chatlv_lastrow);
}
if ($chatlv_lastid !== 1) {
    $url->param('chatlv_lastid', $chatlv_lastid);
}
$PAGE->set_url($url);

if (!$chatlvuser = $DB->get_record('chatlv_users', array('sid'=>$chatlv_sid))) {
    print_error('notlogged', 'chatlv');
}

//Get the minimal course
if (!$course = $DB->get_record('course', array('id'=>$chatlvuser->course))) {
    print_error('invalidcourseid');
}

//Get the user theme and enough info to be used in chatlv_format_message() which passes it along to
// chatlv_format_message_manually() -- and only id and timezone are used.
if (!$USER = $DB->get_record('user', array('id'=>$chatlvuser->userid))) { // no optimisation here, it would break again in future!
    print_error('invaliduser');
}
$USER->description = '';

//Setup course, lang and theme
$PAGE->set_course($course);

// force deleting of timed out users if there is a silence in room or just entering
if ((time() - $chatlv_lasttime) > $CFG->chatlv_old_ping) {
    // must be done before chatlv_get_latest_message!!!
    chatlv_delete_old_users();
}

//
// Time to send headers, and lay out the basic JS updater page
//
header('Expires: Sun, 28 Dec 1997 09:32:45 GMT');
header('Last-Modified: '.gmdate('D, d M Y H:i:s').' GMT');
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');
header('Content-Type: text/html; charset=utf-8');

/// required stylesheets
$stylesheetshtml = '';
/*foreach ($CFG->stylesheets as $stylesheet) {
    //TODO: MDL-21120
    $stylesheetshtml .= '<link rel="stylesheet" type="text/css" href="'.$stylesheet.'" />';
}*/

$refreshurl = "{$CFG->wwwroot}/mod/chatlv/gui_header_js/jsupdated.php?chatlv_sid=$chatlv_sid&chatlv_lasttime=$chatlv_lasttime&chatlv_lastrow=$chatlv_newrow&chatlv_lastid=$chatlv_lastid";
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html>
    <head>
        <meta http-equiv="content-type" content="text/html; charset=utf-8" />
        <script type="text/javascript">
        //<![CDATA[
        function safari_refresh() {
            self.location.href= '<?php echo $refreshurl;?>';
        }
        var issafari = false;
        if(window.devicePixelRatio){
            issafari = true;
            setTimeout('safari_refresh()', <?php echo $CFG->chatlv_refresh_room*1000;?>);
        }
        if (parent.msg.document.getElementById("msgStarted") == null) {
            parent.msg.document.close();
            parent.msg.document.open("text/html","replace");
            parent.msg.document.write("<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.0 Transitional//EN\" \"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd\">");
            parent.msg.document.write("<html><head>");
            parent.msg.document.write("<meta http-equiv=\"content-type\" content=\"text/html; charset=utf-8\" />");
            parent.msg.document.write("<base target=\"_blank\" />");
            parent.msg.document.write("<?php echo addslashes_js($stylesheetshtml) ?>");
            parent.msg.document.write("</head><body class=\"mod-chatlv-gui_header_js course-<?php echo $chatlvuser->course ?>\" id=\"mod-chatlv-gui_header_js-jsupdate\"><div style=\"display: none\" id=\"msgStarted\">&nbsp;</div>");
        }
        //]]>
        </script>
    </head>
    <body>

<?php

    // Ensure the HTML head makes it out there
    echo $CHAT_DUMMY_DATA;

    for ($n=0; $n <= CHAT_MAX_CLIENT_UPDATES; $n++) {

        // ping first so we can later shortcut as needed.
        $chatlvuser->lastping = time();
        $DB->set_field('chatlv_users', 'lastping', $chatlvuser->lastping, array('id'=>$chatlvuser->id));

        if ($message = chatlv_get_latest_message($chatlvuser->chatlvid, $chatlvuser->groupid)) {
            $chatlv_newlasttime = $message->timestamp;
            $chatlv_newlastid   = $message->id;
        } else {
            $chatlv_newlasttime = 0;
            $chatlv_newlastid   = 0;
            print " \n";
            print $CHAT_DUMMY_DATA;
            sleep($CFG->chatlv_refresh_room);
            continue;
        }

        $timenow    = time();

        $params = array('groupid'=>$chatlvuser->groupid, 'lastid'=>$chatlv_lastid, 'lasttime'=>$chatlv_lasttime, 'chatlvid'=>$chatlvuser->chatlvid);
        $groupselect = $chatlvuser->groupid ? " AND (groupid=:groupid OR groupid=0) " : "";

        $newcriteria = '';
        if ($chatlv_lastid > 0) {
            $newcriteria = "id > :lastid";
        } else {
            if ($chatlv_lasttime == 0) { //display some previous messages
                $chatlv_lasttime = $timenow - $CFG->chatlv_old_ping; //TO DO - any better value??
            }
            $newcriteria = "timestamp > :lasttime";
        }

        $messages = $DB->get_records_select("chatlv_messages_current",
                                       "chatlvid = :chatlvid AND $newcriteria $groupselect", $params,
                                       "timestamp ASC");

        if ($messages) {
            $num = count($messages);
        } else {
            print " \n";
            print $CHAT_DUMMY_DATA;
            sleep($CFG->chatlv_refresh_room);
            continue;
            $num = 0;
        }

        print '<script type="text/javascript">' . "\n";
        print "//<![CDATA[\n\n";

        $chatlv_newrow = ($chatlv_lastrow + $num) % 2;

        $refreshusers = false;
        $us = array ();
        if (($chatlv_lasttime != $chatlv_newlasttime) and $messages) {

            $beep         = false;
            $refreshusers = false;
            foreach ($messages as $message) {
                $chatlv_lastrow = ($chatlv_lastrow + 1) % 2;
                $formatmessage = chatlv_format_message($message, $chatlvuser->course, $USER, $chatlv_lastrow);
                if ($formatmessage->beep) {
                    $beep = true;
                }
                if ($formatmessage->refreshusers) {
                    $refreshusers = true;
                }
                $us[$message->userid] = $timenow - $message->timestamp;
                echo "parent.msg.document.write('".addslashes_js($formatmessage->html )."\\n');\n";

            }
            // from the last message printed...
            // a strange case where lack of closures is useful!
            $chatlv_lasttime = $message->timestamp;
            $chatlv_lastid   = $message->id;
        }

        if ($refreshusers) {
            echo "if (parent.users.document.anchors[0] != null) {" .
                "parent.users.location.href = parent.users.document.anchors[0].href;}\n";
        } else {
            foreach($us as $uid=>$lastping) {
                $min = (int) ($lastping/60);
                $sec = $lastping - ($min*60);
                $min = $min < 10 ? '0'.$min : $min;
                $sec = $sec < 10 ? '0'.$sec : $sec;
                $idle = $min.':'.$sec;
                echo "if (parent.users.document.getElementById('uidle{$uid}') != null) {".
                        "parent.users.document.getElementById('uidle{$uid}').innerHTML = '$idle';}\n";
            }
        }

        print <<<EOD
        if(parent.input){
            var autoscroll = parent.input.document.getElementById('auto');
            if(parent.msg && autoscroll && autoscroll.checked){
                parent.msg.scroll(1,5000000);
            }
        }
EOD;
        print "//]]>\n";
        print '</script>' . "\n\n";
        if ($beep) {
            print '<embed src="../beep.wav" autostart="true" hidden="true" name="beep" />';
        }
        print $CHAT_DUMMY_DATA;
        sleep($CFG->chatlv_refresh_room);
    } // here ends the for() loop

    // here & should be written & :-D
    $refreshurl = "{$CFG->wwwroot}/mod/chatlv/gui_header_js/jsupdated.php?chatlv_sid=$chatlv_sid&chatlv_lasttime=$chatlv_lasttime&chatlv_lastrow=$chatlv_newrow&chatlv_lastid=$chatlv_lastid";
    print '<script type="text/javascript">' . "\n";
    print "//<![CDATA[ \n\n";
    print "location.href = '$refreshurl';\n";
    print "//]]>\n";
    print '</script>' . "\n\n";

?>

    </body>
</html>
