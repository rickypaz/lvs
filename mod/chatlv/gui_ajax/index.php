<?php
require_once('../../../config.php');
require_once('../lib.php');

$id      = required_param('id', PARAM_INT);
$groupid = optional_param('groupid', 0, PARAM_INT); //only for teachers
$theme   = optional_param('theme', 'compact', PARAM_SAFEDIR);

$url = new moodle_url('/mod/chatlv/gui_ajax/index.php', array('id'=>$id));
if ($groupid !== 0) {
    $url->param('groupid', $groupid);
}
$PAGE->set_url($url);
$PAGE->set_popup_notification_allowed(false); // No popup notifications in the chatlv window

$chatlv = $DB->get_record('chatlv', array('id'=>$id), '*', MUST_EXIST);
$course = $DB->get_record('course', array('id'=>$chatlv->course), '*', MUST_EXIST);
$cm = get_coursemodule_from_instance('chatlv', $chatlv->id, $course->id, false, MUST_EXIST);

$context = context_module::instance($cm->id);
require_login($course, false, $cm);
require_capability('mod/chatlv:chatlv', $context);

/// Check to see if groups are being used here
 if ($groupmode = groups_get_activity_groupmode($cm)) {   // Groups are being used
    if ($groupid = groups_get_activity_group($cm)) {
        if (!$group = groups_get_group($groupid)) {
            print_error('invalidgroupid');
        }
        $groupname = ': '.$group->name;
    } else {
        $groupname = ': '.get_string('allparticipants');
    }
} else {
    $groupid = 0;
    $groupname = '';
}

// if requested theme doesn't exist, use default 'bubble' theme
if (!file_exists(dirname(__FILE__) . '/theme/'.$theme.'/chatlv.css')) {
    $theme = 'compact';
}

// login chatlv room
if (!$chatlv_sid = chatlv_login_user($chatlv->id, 'ajax', $groupid, $course)) {
    print_error('cantlogin', 'chatlv');
}
$courseshortname = format_string($course->shortname, true, array('context' => context_course::instance($course->id)));
$module = array(
    'name'      => 'mod_chatlv_ajax', // chatlv gui's are not real plugins, we have to break the naming standards for JS modules here :-(
    'fullpath'  => '/mod/chatlv/gui_ajax/module.js',
    'requires'  => array('base', 'dom', 'event', 'event-mouseenter', 'event-key', 'json-parse', 'io', 'overlay', 'yui2-resize', 'yui2-layout', 'yui2-menu'),
    'strings'   => array(array('send', 'chatlv'), array('sending', 'chatlv'), array('inputarea', 'chatlv'), array('userlist', 'chatlv'),
                         array('modulename', 'chatlv'), array('beep', 'chatlv'), array('talk', 'chatlv'))
);
$modulecfg = array(
    'home'=>$CFG->httpswwwroot.'/mod/chatlv/view.php?id='.$cm->id,
    'chatlvurl'=>$CFG->httpswwwroot.'/mod/chatlv/gui_ajax/index.php?id='.$id,
    'theme'=>$theme,
    'userid'=>$USER->id,
    'sid'=>$chatlv_sid,
    'timer'=>3000,
    'chatlv_lasttime'=>0,
    'chatlv_lastrow'=>null,
    'chatlvroom_name' => $courseshortname . ": " . format_string($chatlv->name, true) . $groupname
);
$PAGE->requires->js_init_call('M.mod_chatlv_ajax.init', array($modulecfg), false, $module);

// @lvs adição javascript para avaliação em tempo real
$PAGE->requires->js_init_call('M.block_lvs.ratinglvs', array(LVS_WWWROOT2, true));
// fim lvs

$PAGE->set_title(get_string('modulename', 'chatlv').": $courseshortname: ".format_string($chatlv->name,true)."$groupname");
$PAGE->add_body_class('yui-skin-sam');
$PAGE->set_pagelayout('embedded');
$PAGE->requires->css('/mod/chatlv/gui_ajax/theme/'.$theme.'/chatlv.css');

echo $OUTPUT->header();
echo $OUTPUT->box(html_writer::tag('h2',  get_string('participants'), array('class' => 'accesshide')) .
        '<ul id="users-list"></ul>', '', 'chatlv-userlist');
echo $OUTPUT->box('', '', 'chatlv-options');
echo $OUTPUT->box(html_writer::tag('h2',  get_string('messages', 'chatlv'), array('class' => 'accesshide')) .
        '<ul id="messages-list"></ul>', '', 'chatlv-messages');
$table = new html_table();
$table->data = array(
    array(' &raquo; <label class="accesshide" for="input-message">' . get_string('entermessage', 'chatlv') . ' </label><input type="text" disabled="true" id="input-message" value="Loading..." size="50" /> <input type="button" id="button-send" value="'.get_string('send', 'chatlv').'" /> <a id="choosetheme" href="###">'.get_string('themes').' &raquo; </a>')
);
echo $OUTPUT->box(html_writer::tag('h2',  get_string('composemessage', 'chatlv'), array('class' => 'accesshide')) .
        html_writer::table($table), '', 'chatlv-input-area');
echo $OUTPUT->box('', '', 'chatlv-notify');

$frame = html_writer::tag('frame', '', array('src'=>"listagem.php", 'name'=>"users", 'scrolling'=>"auto"));
$frameset = html_writer::tag('frameset', $frame, array());

// @LVS
$params = "chatlv_id=$id&chatlv_sid=$chatlv_sid";
$iframe = '<iframe height="600" width="900" src="listagem2.php?' . $params . '"> Your browser does not diplay iFrames</iframe>';

echo $OUTPUT->box($iframe, '', 'chatlv-grades');

echo $OUTPUT->footer();
