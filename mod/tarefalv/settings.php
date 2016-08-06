<?php

defined('MOODLE_INTERNAL') || die;

if ($ADMIN->fulltree) {
    require_once($CFG->dirroot.'/mod/tarefalv/lib.php');

    if (isset($CFG->maxbytes)) {
        $maxbytes = 0;
        if (isset($CFG->tarefalv_maxbytes)) {
            $maxbytes = $CFG->tarefalv_maxbytes;
        }
        $settings->add(new admin_setting_configselect('tarefalv_maxbytes', get_string('maximumsize', 'tarefalv'),
                           get_string('configmaxbytes', 'tarefalv'), 1048576, get_max_upload_sizes($CFG->maxbytes, 0, 0, $maxbytes)));
    }

    $options = array(TAREFALV_COUNT_WORDS   => trim(get_string('numwords', '', '?')),
                     TAREFALV_COUNT_LETTERS => trim(get_string('numletters', '', '?')));
    $settings->add(new admin_setting_configselect('tarefalv_itemstocount', get_string('itemstocount', 'tarefalv'),
                       get_string('configitemstocount', 'tarefalv'), TAREFALV_COUNT_WORDS, $options));

    $settings->add(new admin_setting_configcheckbox('tarefalv_showrecentsubmissions', get_string('showrecentsubmissions', 'tarefalv'),
                       get_string('configshowrecentsubmissions', 'tarefalv'), 1));
}
