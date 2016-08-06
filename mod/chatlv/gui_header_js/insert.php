<?php

include('../../../config.php');
include('../lib.php');

$chatlv_sid     = required_param('chatlv_sid', PARAM_ALPHANUM);
$chatlv_message = required_param('chatlv_message', PARAM_RAW);

$PAGE->set_url('/mod/chatlv/gui_header_js/insert.php', array('chatlv_sid'=>$chatlv_sid,'chatlv_message'=>$chatlv_message));

if (!$chatlvuser = $DB->get_record('chatlv_users', array('sid'=>$chatlv_sid))) {
    print_error('notlogged', 'chatlv');
}

if (!$chatlv = $DB->get_record('chatlv', array('id'=>$chatlvuser->chatlvid))) {
    print_error('nochatlv', 'chatlv');
}

if (!$course = $DB->get_record('course', array('id'=>$chatlv->course))) {
    print_error('invalidcourseid');
}

if (!$cm = get_coursemodule_from_instance('chatlv', $chatlv->id, $course->id)) {
    print_error('invalidcoursemodule');
}

require_login($course, false, $cm);

if (isguestuser()) {
    print_error('noguests');
}

session_get_instance()->write_close();

/// Delete old users now

chatlv_delete_old_users();

/// Clean up the message

$chatlv_message = clean_text($chatlv_message, FORMAT_MOODLE);  // Strip bad tags

/// Add the message to the database

if (!empty($chatlv_message)) {

    $message = new stdClass();
    $message->chatlvid = $chatlvuser->chatlvid;
    $message->userid = $chatlvuser->userid;
    $message->groupid = $chatlvuser->groupid;
    $message->message = $chatlv_message;
    $message->timestamp = time();

    $DB->insert_record('chatlv_messages', $message);
    $DB->insert_record('chatlv_messages_current', $message);

    $chatlvuser->lastmessageping = time() - 2;
    $DB->update_record('chatlv_users', $chatlvuser);

    if ($cm = get_coursemodule_from_instance('chatlv', $chatlv->id, $course->id)) {
        add_to_log($course->id, 'chatlv', 'talk', "view.php?id=$cm->id", $chatlv->id, $cm->id);
    }
}

if ($chatlvuser->version == 'header_js') {

    $forcerefreshasap = ($CFG->chatlv_normal_updatemode != 'jsupdated'); // See bug MDL-6791

    $module = array(
        'name'      => 'mod_chatlv_header',
        'fullpath'  => '/mod/chatlv/gui_header_js/module.js'
    );
    $PAGE->requires->js_init_call('M.mod_chatlv_header.init_insert_nojsupdated', array($forcerefreshasap), true, $module);
}

redirect('../empty.php');