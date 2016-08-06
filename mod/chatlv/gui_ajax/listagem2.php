<?php

use uab\ifce\lvs\moodle2\view\Moodle2View;

use uab\ifce\lvs\Carinhas;

define('NO_MOODLE_COOKIES', true); // session not used here

require_once('../../../config.php');
require_once($CFG->dirroot.'/mod/chatlv/lib.php');

$chatlv_sid   = required_param('chatlv_sid', PARAM_ALPHANUM);
$beep       = optional_param('beep', 0, PARAM_INT);  // beep target

$PAGE->set_url('/mod/chatlv/gui_header_js/users.php', array('chatlv_sid'=>$chatlv_sid));
$PAGE->set_popup_notification_allowed(false);

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

$PAGE->set_pagelayout('embedded');

$USER->description = '';

//Setup course, lang and theme
$PAGE->set_course($course);

$courseid = $chatlvuser->course;

if (!$cm = get_coursemodule_from_instance('chatlv', $chatlvuser->chatlvid, $courseid)) {
    print_error('invalidcoursemodule');
}

// if ($beep) {
//     $message->chatlvid    = $chatlvuser->chatlvid;
//     $message->userid    = $chatlvuser->userid;
//     $message->groupid   = $chatlvuser->groupid;
//     $message->message   = "beep $beep";
//     $message->system    = 0;
//     $message->timestamp = time();

//     $DB->insert_record('chatlv_messages', $message);
//     $DB->insert_record('chatlv_messages_current', $message);

//     $chatlvuser->lastmessageping = time();          // A beep is a ping  ;-)
// }

$chatlvuser->lastping = time();
$context = get_context_instance(CONTEXT_COURSE, $chatlvuser->course);
$idchatlv = $chatlvuser->chatlvid;
$DB->set_field('chatlv_users', 'lastping', $chatlvuser->lastping, array('id'=>$chatlvuser->id));

$refreshurl = "users.php?chatlv_sid=$chatlv_sid";

/// Get list of users

if (!$chatlvusers = chatlv_get_users($chatlvuser->chatlvid, $chatlvuser->groupid, $cm->groupingid)) {
    print_error('errornousers', 'chatlv');
}

$uidles = Array();
foreach ($chatlvusers as $chatlvuser) {
    $uidles[] = $chatlvuser->id;
}

$module = array(
    'name'      => 'mod_chatlv_header',
    'fullpath'  => '/mod/chatlv/gui_header_js/module.js',
    'requires'  => array('node')
);
$PAGE->requires->js_init_call('M.mod_chatlv_header.init_users', array($uidles), false, $module);

/// Print user panel body
$timenow    = time();
$stridle    = get_string('idle', 'chatlv');
$strbeep    = get_string('beep', 'chatlv');

$table = new html_table();
$table->width = '100%';
$table->data = array();

$isTeacher = has_capability('moodle/course:viewhiddenactivities', $context, $USER->id);

echo '<div style="display: none"><a href="' . $refreshurl . '" name="refreshLink">Refresh link</a></div>';
echo '<table style="border: double;" width="100%">';
echo '<tr>
		<td width="5%%" class="listaCell01"></td>
		<td width="55%" style="border: double;" class="listaCell01">Nome</td>
		<td width="30%" style="border: double;" align="center" class="listaCell02">n&deg; de intera&ccedil;&otilde;es</td>
		<td width="40%" style="border: double;" align="center" class="listaCell02">LV</td>
</tr>';

foreach ($chatlvusers as $chatlvuser) {
	if (($chatlvuser->id == $USER->id && !$isTeacher) || ($chatlvuser->id != $USER->id && $isTeacher)) {
		
		
		$moodleView = new Moodle2View();
		
		echo "<tr onmouseover=\"this.style.cursor='pointer';this.style.background='#FF9';this.alt='Click para saber o desempenho desse usuario';this.title='Click para saber o desempenho desse usuario'\" onmouseout=\"this.style.background='#FFF';\"><td style=\"border: double;\"  width=\"35\">";
		echo "<a target=\"_blank\" onClick=\"return openpopup('/user/view.php?id=$chatlvuser->id&amp;course=$courseid','user$chatlvuser->id','');\" href=\"$CFG->wwwroot/user/view.php?id=$chatlvuser->id&amp;course=$courseid\">";
		
		echo $moodleView->fotoUsuario($chatlvuser->id);
		echo '</a></td><td style="border: double;"  valign="center" valign="center">';
        echo '<p><font size="1">';
		echo fullname($chatlvuser) . '<br />';
		
		echo '</font></p>';
		echo '</td>';
		$sqlmessage = "SELECT COUNT(message)AS nmsg FROM {chatlv_messages} m inner join {rating} r on r.itemid = m.id and r.component = 'mod_chatlv'
			WHERE system!=1 AND m.userid=$chatlvuser->id AND chatlvid=$idchatlv";
		
		
		$linhamessage = $DB->get_records_sql($sqlmessage);
		$nmessage = current($linhamessage)->nmsg;
		
		echo "</a></td><td style=\"border: double;\" id=\"message{$chatlvuser->id}\" align=\"center\"><center>";
		echo $nmessage;
		echo '</center></td>';
		echo '</a></td><td style="border: double;" id="apro" align="center"><a href="'.$CFG->wwwroot . '/blocks/lvs/pages/relatorio_distancia.php?curso=' . $COURSE->id . '&usuario=' . $chatlvuser->id . '&atividade=chatlv" target="dd">visualizar</a>';
        echo '</center></td>';
        echo '</tr>';

        if (!$isTeacher) {
        
        $id_chat = $idchatlv;
        $id_usuario = $chatlvuser->id;
        
        $sqlcount = "SELECT COUNT(message) AS nmsg
        FROM {$CFG->prefix}chatlv_messages m inner join {rating} r on r.itemid = m.id and r.component = 'mod_chatlv'
        WHERE m.userid= $id_usuario
        AND chatlvid= $id_chat
        AND system != 1";
        $linhacount = $DB->get_records_sql($sqlcount);
        $nmessage = current($linhacount)->nmsg;
        
        echo '<table style="border: double;" width="100%">';
        if ($nmessage > 0) {
        $sqlmessage = "SELECT m.id, message, rating as grade
				FROM {$CFG->prefix}chatlv_messages m inner join {rating} r on r.itemid = m.id and r.component = 'mod_chatlv'
        				WHERE m.userid= $id_usuario
        				AND chatlvid= $id_chat
        				AND system != 1
        				";
        				echo '<tr>
        				<td width="75%" style="border: double;" class="listaCell01">Mensagens</td>
        				<td width="25%" style="border: double;" align="center" class="listaCell02">Notas</td>       </tr>';
        
        				
                $linhamessages = $DB->get_records_sql($sqlmessage);
                foreach ($linhamessages as $vetorlinhamessages) {
        						echo "<tr>";
        echo"<td style=\"border: double;\" id=\"message{$chatlvuser->id}\" >";
        echo $vetorlinhamessages->message;
        echo '</td>';
        echo"<td style=\"border: double;\" id=\"message{$chatlvuser->id}\" align=\"center\">";
        if (!is_null($vetorlinhamessages->grade)) {
        
        $carinhas = new Carinhas();
        $carinha = $carinhas->recuperarCarinha($vetorlinhamessages->grade);
        
        $imgcarinha = $carinha['arquivo'] ;
        $nomeimgcarinha = $carinha['descricao'];
        echo "<img  src='$imgcarinha' name='$nomeimgcarinha' alt='$nomeimgcarinha'/>";
        }else
        	echo '---';
        	echo '</td>';
        	echo "</tr>";
        		echo '<br/>';
        
        		//alterado all_msgs fim
        }
        } else {
        echo "<tr>";
        		echo"<td style=\"border: double;\" id=\"message{$chatlvuser->id}\" valign=\"center\">";
        		echo "Voce nao possui mensagens avaliadas!";
        		echo '</td>';
        		echo "</tr>";
        }
        echo "</table>";
        }
	}
}

ob_start();
?>
<script language="Javascript">
window.setTimeout("window.location.reload();", 10000);
</script>
<?php 
echo $OUTPUT->header();
echo html_writer::tag('div', html_writer::tag('a', 'Refresh link', array('href'=>$refreshurl, 'id'=>'refreshLink')), array('style'=>'display:none')); //TODO: localize
echo html_writer::table($table);
echo $OUTPUT->footer();

//
// Support HTTP Keep-Alive by printing Content-Length
//
// If the user pane is refreshing often, using keepalives
// is lighter on the server and faster for most clients.
//
// Apache is normally configured to have a 15s timeout on
// keepalives, so let's observe that. Unfortunately, we cannot
// autodetect the keepalive timeout.
//
// Using keepalives when the refresh is longer than the timeout
// wastes server resources keeping an apache child around on a
// connection that will timeout. So we don't.
if ($CFG->chatlv_refresh_userlist < 15) {
    header("Content-Length: " . ob_get_length() );
    ob_end_flush();
}

exit; // no further output
