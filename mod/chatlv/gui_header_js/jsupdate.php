<?php

define('NO_MOODLE_COOKIES', true); // session not used here

require('../../../config.php');
require('../lib.php');

$chatlv_sid      = required_param('chatlv_sid', PARAM_ALPHANUM);
$chatlv_lasttime = optional_param('chatlv_lasttime', 0, PARAM_INT);
$chatlv_lastrow  = optional_param('chatlv_lastrow', 1, PARAM_INT);

$url = new moodle_url('/mod/chatlv/gui_header_js/jsupdate.php', array('chatlv_sid'=>$chatlv_sid));
if ($chatlv_lasttime !== 0) {
    $url->param('chatlv_lasttime', $chatlv_lasttime);
}
if ($chatlv_lastrow !== 1) {
    $url->param('chatlv_lastrow', $chatlv_lastrow);
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

if ($message = chatlv_get_latest_message($chatlvuser->chatlvid, $chatlvuser->groupid)) {
    $chatlv_newlasttime = $message->timestamp;
} else {
    $chatlv_newlasttime = 0;
}

if ($chatlv_lasttime == 0) { //display some previous messages
    $chatlv_lasttime = time() - $CFG->chatlv_old_ping; //TO DO - any better value??
}

$timenow    = time();

$params = array('groupid'=>$chatlvuser->groupid, 'chatlvid'=>$chatlvuser->chatlvid, 'lasttime'=>$chatlv_lasttime);

$groupselect = $chatlvuser->groupid ? " AND (groupid=:groupid OR groupid=0) " : "";

$messages = $DB->get_records_select("chatlv_messages_current",
                    "chatlvid = :chatlvid AND timestamp > :lasttime $groupselect", $params,
                    "timestamp ASC");

if ($messages) {
    $num = count($messages);
} else {
    $num = 0;
}

$chatlv_newrow = ($chatlv_lastrow + $num) % 2;

// no &amp; in url, does not work in header!
$refreshurl = "{$CFG->wwwroot}/mod/chatlv/gui_header_js/jsupdate.php?chatlv_sid=$chatlv_sid&chatlv_lasttime=$chatlv_newlasttime&chatlv_lastrow=$chatlv_newrow";
$refreshurlamp = "{$CFG->wwwroot}/mod/chatlv/gui_header_js/jsupdate.php?chatlv_sid=$chatlv_sid&amp;chatlv_lasttime=$chatlv_newlasttime&amp;chatlv_lastrow=$chatlv_newrow";

header('Expires: Sun, 28 Dec 1997 09:32:45 GMT');
header('Last-Modified: '.gmdate('D, d M Y H:i:s').' GMT');
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');
header('Content-Type: text/html; charset=utf-8');
header("Refresh: $CFG->chatlv_refresh_room; url=$refreshurl");

/// required stylesheets
$stylesheetshtml = '';
/*foreach ($CFG->stylesheets as $stylesheet) {
    //TODO: MDL-21120
    $stylesheetshtml .= '<link rel="stylesheet" type="text/css" href="'.$stylesheet.'" />';
}*/

// use ob to be able to send Content-Length headers
// needed for Keep-Alive to work
ob_start();

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
        if (parent.msg && parent.msg.document.getElementById("msgStarted") == null) {
            parent.msg.document.close();
            parent.msg.document.open("text/html","replace");
            parent.msg.document.write("<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.0 Transitional//EN\" \"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd\">");
            parent.msg.document.write("<html><head>");
            parent.msg.document.write("<meta http-equiv=\"content-type\" content=\"text/html; charset=utf-8\" />");
            parent.msg.document.write("<base target=\"_blank\" />");
            parent.msg.document.write("<?php echo addslashes_js($stylesheetshtml) ?>");
            parent.msg.document.write("<\/head><body class=\"mod-chatlv-gui_header_js course-<?php echo $chatlvuser->course ?>\" id=\"mod-chatlv-gui_header_js-jsupdate\"><div style=\"display: none\" id=\"msgStarted\">&nbsp;<\/div>");
        }
        <?php
        $beep = false;
        $refreshusers = false;
        $us = array ();
        if (($chatlv_lasttime != $chatlv_newlasttime) and $messages) {

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
                echo "if(parent.msg)";
                echo "parent.msg.document.write('".addslashes_js($formatmessage->html)."\\n');\n";
             }
        }

        $chatlvuser->lastping = time();
        $DB->set_field('chatlv_users', 'lastping', $chatlvuser->lastping, array('id'=>$chatlvuser->id));

        if ($refreshusers) {
        ?>
        var link = parent.users.document.getElementById('refreshLink');
        if (link != null) {
            parent.users.location.href = link.href;
        }
        <?php
        } else {
            foreach($us as $uid=>$lastping) {
                $min = (int) ($lastping/60);
                $sec = $lastping - ($min*60);
                $min = $min < 10 ? '0'.$min : $min;
                $sec = $sec < 10 ? '0'.$sec : $sec;
                $idle = $min.':'.$sec;
                echo "if (parent.users && parent.users.document.getElementById('uidle{$uid}') != null) {".
                        "parent.users.document.getElementById('uidle{$uid}').innerHTML = '$idle';}\n";
            }
        }
        ?>
        if(parent.input){
            var autoscroll = parent.input.document.getElementById('auto');
            if(parent.msg && autoscroll && autoscroll.checked){
                parent.msg.scroll(1,5000000);
            }
        }
        //]]>
        </script>
    </head>
    <body>
       <?php
            if ($beep) {
                echo '<embed src="../beep.wav" autostart="true" hidden="true" name="beep" />';
            }
        ?>
       <a href="<?php echo $refreshurlamp ?>" name="refreshLink">Refresh link</a>
    </body>
</html>
<?php

// support HTTP Keep-Alive
header("Content-Length: " . ob_get_length() );
ob_end_flush();
exit;


?>
