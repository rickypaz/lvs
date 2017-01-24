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

require_once('../../../config.php');
require_once('../lib.php');

$chatlvsid     = required_param('chatlv_sid', PARAM_ALPHANUM);
$chatlvmessage = required_param('chatlv_message', PARAM_RAW);

$PAGE->set_url('/mod/chatlv/gui_header_js/insert.php', array('chatlv_sid' => $chatlvsid, 'chatlv_message' => $chatlvmessage));

if (!$chatlvuser = $DB->get_record('chatlv_users', array('sid' => $chatlvsid))) {
    print_error('notlogged', 'chatlv');
}

if (!$chatlv = $DB->get_record('chatlv', array('id' => $chatlvuser->chatlvid))) {
    print_error('nochatlv', 'chatlv');
}

if (!$course = $DB->get_record('course', array('id' => $chatlv->course))) {
    print_error('invalidcourseid');
}

if (!$cm = get_coursemodule_from_instance('chatlv', $chatlv->id, $course->id)) {
    print_error('invalidcoursemodule');
}

require_login($course, false, $cm);

if (isguestuser()) {
    print_error('noguests');
}

\core\session\manager::write_close();

// Delete old users now.

chatlv_delete_old_users();

// Clean up the message.

$chatlvmessage = clean_text($chatlvmessage, FORMAT_MOODLE);  // Strip bad tags.

// Add the message to the database.

if (!empty($chatlvmessage)) {

    chatlv_send_chatlvmessage($chatlvuser, $chatlvmessage, 0, $cm);

    $chatlvuser->lastmessageping = time() - 2;
    $DB->update_record('chatlv_users', $chatlvuser);
}

if ($chatlvuser->version == 'header_js') {

    $forcerefreshasap = ($CFG->chatlv_normal_updatemode != 'jsupdated'); // See bug MDL-6791.

    $module = array(
        'name'      => 'mod_chatlv_header',
        'fullpath'  => '/mod/chatlv/gui_header_js/module.js'
    );
    $PAGE->requires->js_init_call('M.mod_chatlv_header.init_insert_nojsupdated', array($forcerefreshasap), true, $module);
}

redirect('../empty.php');
