<?php

define('NO_MOODLE_COOKIES', true); // session not used here

require('../../../config.php');
require('../lib.php');

$chatlv_sid = required_param('chatlv_sid', PARAM_ALPHANUM);

$PAGE->set_url('/mod/chatlv/gui_sockets/chatlvinput.php', array('chatlv_sid'=>$chatlv_sid));
$PAGE->set_popup_notification_allowed(false);

if (!$chatlvuser = $DB->get_record('chatlv_users', array('sid'=>$chatlv_sid))) {
    print_error('notlogged', 'chatlv');
}

//Get the user theme
$USER = $DB->get_record('user', array('id'=>$chatlvuser->userid));

//Setup course, lang and theme
$PAGE->set_pagelayout('embedded');
$PAGE->set_course($DB->get_record('course', array('id' => $chatlvuser->course)));
$PAGE->requires->js('/mod/chatlv/gui_sockets/chatlv_gui_sockets.js', true);
$PAGE->requires->js_function_call('setfocus');
$PAGE->set_focuscontrol('chatlv_message');
$PAGE->set_cacheable(false);
echo $OUTPUT->header();

?>

    <form action="../empty.php" method="get" target="empty" id="inputform"
          onsubmit="return empty_field_and_submit();">
        <label class="accesshide" for="chatlv_message"><?php print_string('entermessage', 'chatlv'); ?></label>
        <input type="text" name="chatlv_message" id="chatlv_message" size="60" value="" />
        <?php echo $OUTPUT->help_icon('usingchatlv', 'chatlv'); ?>
    </form>

    <form action="<?php echo "http://$CFG->chatlv_serverhost:$CFG->chatlv_serverport/"; ?>" method="get" target="empty" id="sendform">
        <input type="hidden" name="win" value="message" />
        <input type="hidden" name="chatlv_message" value="" />
        <input type="hidden" name="chatlv_msgidnr" value="0" />
        <input type="hidden" name="chatlv_sid" value="<?php echo $chatlv_sid ?>" />
    </form>
<?php
    echo $OUTPUT->footer();
?>
