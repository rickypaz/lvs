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

define('NO_MOODLE_COOKIES', true); // Session not used here.

require('../../../config.php');
require('../lib.php');

$chatlvsid = required_param('chatlv_sid', PARAM_ALPHANUM);

$PAGE->set_url('/mod/chatlv/gui_sockets/chatlvinput.php', array('chatlv_sid' => $chatlvsid));
$PAGE->set_popup_notification_allowed(false);

if (!$chatlvuser = $DB->get_record('chatlv_users', array('sid' => $chatlvsid))) {
    print_error('notlogged', 'chatlv');
}

// Get the user theme.
$USER = $DB->get_record('user', array('id' => $chatlvuser->userid));

// Setup course, lang and theme.
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
    </form>

    <form action="<?php echo "http://$CFG->chatlv_serverhost:$CFG->chatlv_serverport/"; ?>" method="get" target="empty" id="sendform">
        <input type="hidden" name="win" value="message" />
        <input type="hidden" name="chatlv_message" value="" />
        <input type="hidden" name="chatlv_msgidnr" value="0" />
        <input type="hidden" name="chatlv_sid" value="<?php echo $chatlvsid ?>" />
    </form>
<?php
echo $OUTPUT->footer();

