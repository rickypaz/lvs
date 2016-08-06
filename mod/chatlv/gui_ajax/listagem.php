<?php
// $Id: users.php,v 1.13.2.1 2007/12/14 21:22:47 skodak Exp $

$nomoodlecookie = true;     // Session not needed!

include_once('../../../config.php');
include_once('../lib.php');
include_once($CFG->dirroot . '/blocks/lvs/biblioteca/chatlv.php');

$chatlv_sid = required_param('chatlv_sid', PARAM_ALPHANUM);
$beep = optional_param('beep', 0, PARAM_INT);  // beep target

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
course_setup($course);
$context = get_context_instance(CONTEXT_COURSE, $chatlvuser->course);
$courseid = $chatlvuser->course;
//alterado pega do banco de dados que horas foi programado para acabar o chatlv
// pois dps que acaba o usuario nï¿½o poderï¿½ mais ser avaliado
$idchatlv = $chatlvuser->chatlvid;

/* $sqltimefim = "SELECT chatlvtimefim FROM {$CFG->prefix}chatlv WHERE id = $idchatlv";
  $linhatimefim = get_records_sql($sqltimefim);
  $timefim = current($linhatimefim)->chatlvtimefim; */

if (!$cm = get_coursemodule_from_instance('chatlv', $chatlvuser->chatlvid, $courseid)) {
    error('Course Module ID was incorrect');
}
/*
  if ($beep) {
  $message->chatlvid    = $chatlvuser->chatlvid;
  $message->userid    = $chatlvuser->userid;
  $message->groupid   = $chatlvuser->groupid;
  $message->message   = "beep $beep";
  $message->system    = 0;
  $message->timestamp = time();

  if (!insert_record('chatlv_messages', $message)) {
  error('Could not insert a chatlv message!');
  }

  $chatlvuser->lastmessageping = time();          // A beep is a ping  ;-)
  }

  $chatlvuser->lastping = time();
  set_field('chatlv_users', 'lastping', $chatlvuser->lastping, 'id', $chatlvuser->id  );
 */

//Setup course, lang and theme
$PAGE->set_course($course);

$courseid = $chatlvuser->course;

if (!$cm = get_coursemodule_from_instance('chatlv', $chatlvuser->chatlvid, $courseid)) {
	print_error('invalidcoursemodule');
}


$chatlvuser->lastping = time();
$DB->set_field('chatlv_users', 'lastping', $chatlvuser->lastping, array('id'=>$chatlvuser->id));

$refreshurl = "users.php?chatlv_sid=$chatlv_sid";


$refreshurl = "listagem.php?chatlv_sid=$chatlv_sid";

/// Get list of users

if (!$chatlvusers = chatlv_get_users($chatlvuser->chatlvid, $chatlvuser->groupid, $cm->groupingid)) {
    error(get_string('errornousers', 'chatlv'));
}

ob_start();
?>
<script type="text/javascript">
    //<![CDATA[
    var timer = null
    var f = 1; //seconds
    var uidles = new Array(<?php echo count($chatlvusers) ?>);
<?php
$i = 0;
foreach ($chatlvusers as $chatlvuser) {
    echo "uidles[$i] = 'uidle{$chatlvuser->id}';\n";
    $i++;
}
?>

    function stop() {
        clearTimeout(timer)
    }

    function start() {
        timer = setTimeout("update()", f*1000);
    }

    function update() {
        for(i=0; i<uidles.length; i++) {
            el = document.getElementById(uidles[i]);
            if (el != null) {
                parts = el.innerHTML.split(":");
                time = f + (parseInt(parts[0], 10)*60) + parseInt(parts[1], 10);
                min = Math.floor(time/60);
                sec = time % 60;
                el.innerHTML = ((min < 10) ? "0" : "") + min + ":" + ((sec < 10) ? "0" : "") + sec;
            }
        }
        timer = setTimeout("update()", f*1000);
    }
    //]]>
</script>
<?php
/// Print headers
//echo "<META HTTP-EQUIV=\"Refresh\" CONTENT=\"10;URL=listagem.php?chatlv_sid=$_GET[chatlv_sid]\">";
$meta = ob_get_clean();


// Use ob to support Keep-Alive
ob_start();
?>
<script language="Javascript">
window.setTimeout("window.location.reload();", 10000);
</script>
<?php
print_header('', '', '', '', $meta, false, '', '', false, 'onload="start()" onunload="stop()"');


/// Print user panel body
/* $timenow    = time();
  $stridle    = get_string('idle', 'chatlv');
  $strbeep    = get_string('beep', 'chatlv'); */
//alterado visão do professor do frame listagem
if (has_capability('moodle/course:viewhiddenactivities', $context, $USER->id)) {

    echo '<div style="display: none"><a href="' . $refreshurl . '" name="refreshLink">Refresh link</a></div>';
    echo '<table style="border: double;" width="100%">';
    echo '<tr>
            <td width="5%%" class="listaCell01"></td>

			<td width="55%" style="border: double;" class="listaCell01">Nome</td>

            <td width="30%" style="border: double;" align="center" class="listaCell02">n&deg; de intera&ccedil;&otilde;es</td>
			<td width="40%" style="border: double;" align="center" class="listaCell02">LV</td>
        </tr>';
    foreach ($chatlvusers as $chatlvuser) {
        if (($chatlvuser->id != $USER->id) and (!has_capability('moodle/course:viewhiddenactivities', $context, $chatlvuser->id))) {
            /* $lastping = $timenow - $chatlvuser->lastmessageping;
              $min = (int) ($lastping/60);
              $sec = $lastping - ($min*60);
              $min = $min < 10 ? '0'.$min : $min;
              $sec = $sec < 10 ? '0'.$sec : $sec;
              $idle = $min.':'.$sec; */

            //id usuario $chatlvuser->id
            //id chat  $idchatlv
            //echo "<tr onClick=\"return openpopup('/mod/chatlv/nota_chat.php?id=$idchatlv&usuario=$chatlvuser->id','','width=400,height=400,left=850,top=150');\"  onmouseover=\"this.style.cursor='pointer';this.style.background='#FF9';this.alt='Click para saber o desempenho desse usuario';this.title='Click para saber o desempenho desse usuario'\" onmouseout=\"this.style.background='#FFF';\"><td style=\"border: double;\"  width=\"35\">";
            echo "<tr onmouseover=\"this.style.cursor='pointer';this.style.background='#FF9';this.alt='Click para saber o desempenho desse usuario';this.title='Click para saber o desempenho desse usuario'\" onmouseout=\"this.style.background='#FFF';\"><td style=\"border: double;\"  width=\"35\">";
            echo "<a target=\"_blank\" onClick=\"return openpopup('/user/view.php?id=$chatlvuser->id&amp;course=$courseid','user$chatlvuser->id','');\" href=\"$CFG->wwwroot/user/view.php?id=$chatlvuser->id&amp;course=$courseid\">";

            print_user_picture($chatlvuser->id, 0, $chatlvuser->picture, false, false, false);
            echo '</a></td><td style="border: double;"  valign="center" valign="center">';
            echo '<p><font size="1">';
            echo fullname($chatlvuser) . '<br />';

            //echo "<span class=\"dimmed_text\">$stridle <span name=\"uidles\" id=\"uidle{$chatlvuser->id}\">$idle</span></span>";
            //echo " <a href=\"listagem.php?chatlv_sid=$chatlv_sid&amp;beep=$chatlvuser->id\">$strbeep</a>";
            echo '</font></p>';
            echo '</td>';
            // sql para mostrar o numero de interaï¿½ï¿½es do usuario. Quando vc configura o id para ficar id=\"message{$chatlvuser->id}\" a pï¿½gina jsupdate ou jsupdated.php executa um JS parent.userslistagem.document.getElementById('message{$uid}').innerHTML = '$nmessage'; que pega o campo pelo id e atualiza.
            $sqlmessage = "SELECT COUNT(message)AS nmsg FROM {$CFG->prefix}chatlv_messages WHERE system!=1 AND userid=$chatlvuser->id AND chatlvid=$idchatlv AND grade != -1";
            $linhamessage = get_records_sql($sqlmessage);
            $nmessage = current($linhamessage)->nmsg;

            /* $sqlaproveitamento = "SELECT round((SELECT SUM(coeficientes) FROM {$CFG->prefix}chatlv_resultado WHERE id_chat=$idchatlv and id_avaliado=$chatlvuser->id)
              /12*100,2) as aproveitamento";
              $linhaaproveitamento = get_records_sql($sqlaproveitamento);
              $aproveitamento = current($linhaaproveitamento)->aproveitamento; */

            echo "</a></td><td style=\"border: double;\" id=\"message{$chatlvuser->id}\" align=\"center\"><center>";
            echo $nmessage;
            echo '</center></td>';
            //echo '</a></td><td style="border: double;" id="apro" align="center">';
            
            echo '</a></td><td style="border: double;" id="apro" align="center"><a href="'.$CFG->wwwroot . '/blocks/lvs/pages/nota_atividade_multipla.php?id=' . $COURSE->id . '&usuario=' . $chatlvuser->id . '&atv=chatlv" target="dd">visualizar</a>';

            /* if ($aproveitamento > 100) {
              echo  100.00;
              }
              else {
              echo  $aproveitamento ;
              } */
            echo '</center></td>';
            echo '</tr>';
        }
    }
    // added 2 </div>s, xhtml strict complaints
    //echo '</table>';
 
}
//alterado visï¿½o do aluno do frame listagem
else {

    echo '<div style="display: none"><a href="' . $refreshurl . '" name="refreshLink">Refresh link</a></div>';
    echo '<table style="border: double;" width="100%">';
    echo '<tr>
            <td width="5%" class="listaCell01"></td>

			<td width="55%" style="border: double;" class="listaCell01">Nome</td>

            <td width="30%" style="border: double;" align="center" class="listaCell02">n&deg; de intera&ccedil;&otilde;es</td>
			<td width="40%" style="border: double;" align="center" class="listaCell02">LV</td>
        </tr>';

    foreach ($chatlvusers as $chatlvuser) {
        if ($chatlvuser->id == $USER->id) {
            /* $lastping = $timenow - $chatlvuser->lastmessageping;
              $min = (int) ($lastping/60);
              $sec = $lastping - ($min*60);
              $min = $min < 10 ? '0'.$min : $min;
              $sec = $sec < 10 ? '0'.$sec : $sec;
              $idle = $min.':'.$sec; */

            //id usuario $chatlvuser->id
            //id chat  $idchatlv
            //echo "<tr onClick=\"return openpopup('/mod/chatlv/nota_chat.php?id=$idchatlv&usuario=$chatlvuser->id','','width=400,height=400,left=850,top=150');\"  onmouseover=\"this.style.cursor='pointer';this.style.background='#FF9';this.alt='Click para saber seu desempenho';this.title='Click para saber seu desempenho'\" onmouseout=\"this.style.background='#FFF';\"><td style=\"border: double;\"  width=\"35\">";
            echo "<tr onmouseover=\"this.style.cursor='pointer';this.style.background='#FF9';this.alt='Click para saber seu desempenho';this.title='Click para saber seu desempenho'\" onmouseout=\"this.style.background='#FFF';\"><td style=\"border: double;\"  width=\"35\">";
            echo "<a target=\"_blank\" onClick=\"return openpopup('/user/view.php?id=$chatlvuser->id&amp;course=$courseid','user$chatlvuser->id','');\" href=\"$CFG->wwwroot/user/view.php?id=$chatlvuser->id&amp;course=$courseid\">";
            print_user_picture($chatlvuser->id, 0, $chatlvuser->picture, false, false, false);
            echo '</a></td><td style="border: double;"  valign="center">';
            echo '<p><font size="1">';
            echo fullname($chatlvuser) . '<br />';

            //echo "<span class=\"dimmed_text\">$stridle <span name=\"uidles\" id=\"uidle{$chatlvuser->id}\">$idle</span></span>";
            //echo " <a href=\"listagem.php?chatlv_sid=$chatlv_sid&amp;beep=$chatlvuser->id\">$strbeep</a>";
            echo '</font></p>';
            echo '</td>';
            // sql para mostrar o numero de interaï¿½ï¿½es do usuario. Quando vc configura o id para ficar id=\"message{$chatlvuser->id}\" a pï¿½gina jsupdate ou jsupdated.php executa um JS parent.userslistagem.document.getElementById('message{$uid}').innerHTML = '$nmessage'; que pega o campo pelo id e atualiza.
            $sqlmessage = "SELECT COUNT(message)AS nmsg FROM {$CFG->prefix}chatlv_messages WHERE system!=1 AND userid=$chatlvuser->id AND chatlvid=$idchatlv AND grade != -1";
            $linhamessage = get_records_sql($sqlmessage);
            $nmessage = current($linhamessage)->nmsg;

            /* $sqlaproveitamento = "SELECT round((SELECT SUM(coeficientes) FROM {$CFG->prefix}chatlv_resultado WHERE id_chat=$idchatlv and id_avaliado=$chatlvuser->id)
              /12*100,2) as aproveitamento";

              $linhaaproveitamento = get_records_sql($sqlaproveitamento);
              $aproveitamento = current($linhaaproveitamento)->aproveitamento; */

            echo "</a></td><td style=\"border: double;\" id=\"message{$chatlvuser->id}\" align=\"center\">";
            echo $nmessage;
            echo '</td>';
            echo '</a></td><td style="border: double;" id="apro" align="center"><a href="'.$CFG->wwwroot . '/blocks/lvs/pages/nota_atividade_multipla.php?id=' . $COURSE->id . '&usuario=' . $chatlvuser->id . '&atv=chatlv" target="dd">visualizar</a>';


            /* if ($aproveitamento > 100) {
              echo  '100.00';
              }
              else {
              echo  $aproveitamento ;
              } */
            echo '</td>';
            echo '</tr>';

            //alterado msgs_chat colocada no mesmo frame do listagens
            $id_chat = $idchatlv;
            $id_usuario = $chatlvuser->id;

            $sqlcount = "SELECT COUNT(message) AS nmsg
			FROM {$CFG->prefix}chatlv_messages
			WHERE userid= $id_usuario
			AND chatlvid= $id_chat
			AND system != 1
                        AND grade != -1
			";
            $linhacount = get_records_sql($sqlcount);
            $nmessage = current($linhacount)->nmsg;

            echo '<table style="border: double;" width="100%">';
            if ($nmessage > 0) {
                $sqlmessage = "SELECT message, grade
				FROM {$CFG->prefix}chatlv_messages
				WHERE userid= $id_usuario
				AND chatlvid= $id_chat
				AND system != 1
                                AND grade != -1
				";
                echo '<tr> 
			<td width="75%" style="border: double;" class="listaCell01">Mensagens</td>
            <td width="25%" style="border: double;" align="center" class="listaCell02">Notas</td>       </tr>';

                $linhamessages = get_records_sql($sqlmessage);
                foreach ($linhamessages as $vetorlinhamessages) {
                    echo "<tr>";
                    echo"<td style=\"border: double;\" id=\"message{$chatlvuser->id}\" >";
                    echo $vetorlinhamessages->message;
                    echo '</td>';
                    echo"<td style=\"border: double;\" id=\"message{$chatlvuser->id}\" align=\"center\">";
                    if (!is_null($vetorlinhamessages->grade)) {
                        $carinha = retorna_carinha($vetorlinhamessages->grade);
                        $imgcarinha = $carinha['foto_carinha'] ;
                        $nomeimgcarinha = $carinha['nome_foto_carinha'];
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
        // added 2 </div>s, xhtml strict complaints
        //echo '</table>';
        
    }
}
echo '</table>';
print_footer('empty');
echo '<br/>';

//if (!isteacher($course->id, $USER->id)) {
    /*
      echo "<a onmouseover=\"this.style.cursor='pointer';\" onClick=\"return openpopup('/mod/chatlv/msgs_chat.php?id=$idchatlv&usuario=$USER->id','','scrollbars=1,width=400,height=400,left=850,top=150');\">Visualizar historico de mensagens avaliadas</a>";
     */
//} else if (isteacher($course->id, $USER->id)) {
//
//    //window.opener.location.href = "outrapagina.html";
//    //window.close()
//
//
//
//    echo "<a onmouseover=\"this.style.cursor='pointer';\" onclick=\"fechar('$CFG->wwwroot/mod/chatlv/allmsgs_chat.php?id=$idchatlv&curso=$courseid');\">";
//    echo get_string('avalietodasmsgs', 'chatlv');
//    echo "</a>";
//}
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
    header("Content-Length: " . ob_get_length());
    ob_end_flush();
}

exit; // no further output
?>