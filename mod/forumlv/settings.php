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

/**
 * @package   mod_forumlv
 * @copyright  2009 Petr Skoda (http://skodak.org)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

if ($ADMIN->fulltree) {
    require_once($CFG->dirroot.'/mod/forumlv/lib.php');

    $settings->add(new admin_setting_configselect('forumlv_displaymode', get_string('displaymode', 'forumlv'),
                       get_string('configdisplaymode', 'forumlv'), FORUMLV_MODE_NESTED, forumlv_get_layout_modes()));

    $settings->add(new admin_setting_configcheckbox('forumlv_replytouser', get_string('replytouser', 'forumlv'),
                       get_string('configreplytouser', 'forumlv'), 1));

    // Less non-HTML characters than this is short
    $settings->add(new admin_setting_configtext('forumlv_shortpost', get_string('shortpost', 'forumlv'),
                       get_string('configshortpost', 'forumlv'), 300, PARAM_INT));

    // More non-HTML characters than this is long
    $settings->add(new admin_setting_configtext('forumlv_longpost', get_string('longpost', 'forumlv'),
                       get_string('configlongpost', 'forumlv'), 600, PARAM_INT));

    // Number of discussions on a page
    $settings->add(new admin_setting_configtext('forumlv_manydiscussions', get_string('manydiscussions', 'forumlv'),
                       get_string('configmanydiscussions', 'forumlv'), 100, PARAM_INT));

    if (isset($CFG->maxbytes)) {
        $maxbytes = 0;
        if (isset($CFG->forumlv_maxbytes)) {
            $maxbytes = $CFG->forumlv_maxbytes;
        }
        $settings->add(new admin_setting_configselect('forumlv_maxbytes', get_string('maxattachmentsize', 'forumlv'),
                           get_string('configmaxbytes', 'forumlv'), 512000, get_max_upload_sizes($CFG->maxbytes, 0, 0, $maxbytes)));
    }

    // Default number of attachments allowed per post in all forumlvs
    $settings->add(new admin_setting_configtext('forumlv_maxattachments', get_string('maxattachments', 'forumlv'),
                       get_string('configmaxattachments', 'forumlv'), 9, PARAM_INT));

    // Default Read Tracking setting.
    $options = array();
    $options[FORUMLV_TRACKING_OPTIONAL] = get_string('trackingoptional', 'forumlv');
    $options[FORUMLV_TRACKING_OFF] = get_string('trackingoff', 'forumlv');
    $options[FORUMLV_TRACKING_FORCED] = get_string('trackingon', 'forumlv');
    $settings->add(new admin_setting_configselect('forumlv_trackingtype', get_string('trackingtype', 'forumlv'),
                       get_string('configtrackingtype', 'forumlv'), FORUMLV_TRACKING_OPTIONAL, $options));

    // Default whether user needs to mark a post as read
    $settings->add(new admin_setting_configcheckbox('forumlv_trackreadposts', get_string('trackforumlv', 'forumlv'),
                       get_string('configtrackreadposts', 'forumlv'), 1));

    // Default whether user needs to mark a post as read.
    $settings->add(new admin_setting_configcheckbox('forumlv_allowforcedreadtracking', get_string('forcedreadtracking', 'forumlv'),
                       get_string('forcedreadtracking_desc', 'forumlv'), 0));

    // Default number of days that a post is considered old
    $settings->add(new admin_setting_configtext('forumlv_oldpostdays', get_string('oldpostdays', 'forumlv'),
                       get_string('configoldpostdays', 'forumlv'), 14, PARAM_INT));

    // Default whether user needs to mark a post as read
    $settings->add(new admin_setting_configcheckbox('forumlv_usermarksread', get_string('usermarksread', 'forumlv'),
                       get_string('configusermarksread', 'forumlv'), 0));

    $options = array();
    for ($i = 0; $i < 24; $i++) {
        $options[$i] = sprintf("%02d",$i);
    }
    // Default time (hour) to execute 'clean_read_records' cron
    $settings->add(new admin_setting_configselect('forumlv_cleanreadtime', get_string('cleanreadtime', 'forumlv'),
                       get_string('configcleanreadtime', 'forumlv'), 2, $options));

    // Default time (hour) to send digest email
    $settings->add(new admin_setting_configselect('digestmailtime', get_string('digestmailtime', 'forumlv'),
                       get_string('configdigestmailtime', 'forumlv'), 17, $options));

    if (empty($CFG->enablerssfeeds)) {
        $options = array(0 => get_string('rssglobaldisabled', 'admin'));
        $str = get_string('configenablerssfeeds', 'forumlv').'<br />'.get_string('configenablerssfeedsdisabled2', 'admin');

    } else {
        $options = array(0=>get_string('no'), 1=>get_string('yes'));
        $str = get_string('configenablerssfeeds', 'forumlv');
    }
    $settings->add(new admin_setting_configselect('forumlv_enablerssfeeds', get_string('enablerssfeeds', 'admin'),
                       $str, 0, $options));

    if (!empty($CFG->enablerssfeeds)) {
        $options = array(
            0 => get_string('none'),
            1 => get_string('discussions', 'forumlv'),
            2 => get_string('posts', 'forumlv')
        );
        $settings->add(new admin_setting_configselect('forumlv_rsstype', get_string('rsstypedefault', 'forumlv'),
                get_string('configrsstypedefault', 'forumlv'), 0, $options));

        $options = array(
            0  => '0',
            1  => '1',
            2  => '2',
            3  => '3',
            4  => '4',
            5  => '5',
            10 => '10',
            15 => '15',
            20 => '20',
            25 => '25',
            30 => '30',
            40 => '40',
            50 => '50'
        );
        $settings->add(new admin_setting_configselect('forumlv_rssarticles', get_string('rssarticles', 'forumlv'),
                get_string('configrssarticlesdefault', 'forumlv'), 0, $options));
    }

    $settings->add(new admin_setting_configcheckbox('forumlv_enabletimedposts', get_string('timedposts', 'forumlv'),
                       get_string('configenabletimedposts', 'forumlv'), 1));
}

