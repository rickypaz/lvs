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
 * This file adds the settings pages to the navigation menu
 *
 * @package   mod_tarefalv
 * @copyright 2012 NetSpot {@link http://www.netspot.com.au}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot . '/mod/tarefalv/adminlib.php');

$ADMIN->add('modsettings', new admin_category('modtarefalvfolder', new lang_string('pluginname', 'mod_tarefalv'), $module->is_enabled() === false));

$settings = new admin_settingpage($section, get_string('settings', 'mod_tarefalv'), 'moodle/site:config', $module->is_enabled() === false);

if ($ADMIN->fulltree) {
    $menu = array();
    foreach (core_component::get_plugin_list('tarefalvfeedback') as $type => $notused) {
        $visible = !get_config('tarefalvfeedback_' . $type, 'disabled');
        if ($visible) {
            $menu['tarefalvfeedback_' . $type] = new lang_string('pluginname', 'tarefalvfeedback_' . $type);
        }
    }

    // The default here is feedback_comments (if it exists).
    $name = new lang_string('feedbackplugin', 'mod_tarefalv');
    $description = new lang_string('feedbackpluginforgradebook', 'mod_tarefalv');
    $settings->add(new admin_setting_configselect('tarefalv/feedback_plugin_for_gradebook',
                                                  $name,
                                                  $description,
                                                  'tarefalvfeedback_comments',
                                                  $menu));

    $name = new lang_string('showrecentsubmissions', 'mod_tarefalv');
    $description = new lang_string('configshowrecentsubmissions', 'mod_tarefalv');
    $settings->add(new admin_setting_configcheckbox('tarefalv/showrecentsubmissions',
                                                    $name,
                                                    $description,
                                                    0));

    $name = new lang_string('sendsubmissionreceipts', 'mod_tarefalv');
    $description = new lang_string('sendsubmissionreceipts_help', 'mod_tarefalv');
    $settings->add(new admin_setting_configcheckbox('tarefalv/submissionreceipts',
                                                    $name,
                                                    $description,
                                                    1));

    $name = new lang_string('submissionstatement', 'mod_tarefalv');
    $description = new lang_string('submissionstatement_help', 'mod_tarefalv');
    $default = get_string('submissionstatementdefault', 'mod_tarefalv');
    $setting = new admin_setting_configtextarea('tarefalv/submissionstatement',
                                                    $name,
                                                    $description,
                                                    $default);
    $setting->set_force_ltr(false);
    $settings->add($setting);

    $name = new lang_string('maxperpage', 'mod_tarefalv');
    $options = array(
        -1 => get_string('unlimitedpages', 'mod_tarefalv'),
        10 => 10,
        20 => 20,
        50 => 50,
        100 => 100,
    );
    $description = new lang_string('maxperpage_help', 'mod_tarefalv');
    $settings->add(new admin_setting_configselect('tarefalv/maxperpage',
                                                    $name,
                                                    $description,
                                                    -1,
                                                    $options));

    $name = new lang_string('defaultsettings', 'mod_tarefalv');
    $description = new lang_string('defaultsettings_help', 'mod_tarefalv');
    $settings->add(new admin_setting_heading('defaultsettings', $name, $description));

    $name = new lang_string('alwaysshowdescription', 'mod_tarefalv');
    $description = new lang_string('alwaysshowdescription_help', 'mod_tarefalv');
    $setting = new admin_setting_configcheckbox('tarefalv/alwaysshowdescription',
                                                    $name,
                                                    $description,
                                                    1);
    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, false);
    $setting->set_locked_flag_options(admin_setting_flag::ENABLED, false);
    $settings->add($setting);

    $name = new lang_string('allowsubmissionsfromdate', 'mod_tarefalv');
    $description = new lang_string('allowsubmissionsfromdate_help', 'mod_tarefalv');
    $setting = new admin_setting_configduration('tarefalv/allowsubmissionsfromdate',
                                                    $name,
                                                    $description,
                                                    0);
    $setting->set_enabled_flag_options(admin_setting_flag::ENABLED, true);
    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, false);
    $settings->add($setting);

    $name = new lang_string('duedate', 'mod_tarefalv');
    $description = new lang_string('duedate_help', 'mod_tarefalv');
    $setting = new admin_setting_configduration('tarefalv/duedate',
                                                    $name,
                                                    $description,
                                                    604800);
    $setting->set_enabled_flag_options(admin_setting_flag::ENABLED, true);
    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, false);
    $settings->add($setting);

    $name = new lang_string('cutoffdate', 'mod_tarefalv');
    $description = new lang_string('cutoffdate_help', 'mod_tarefalv');
    $setting = new admin_setting_configduration('tarefalv/cutoffdate',
                                                    $name,
                                                    $description,
                                                    1209600);
    $setting->set_enabled_flag_options(admin_setting_flag::ENABLED, false);
    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, false);
    $settings->add($setting);

    $name = new lang_string('gradingduedate', 'mod_tarefalv');
    $description = new lang_string('gradingduedate_help', 'mod_tarefalv');
    $setting = new admin_setting_configduration('tarefalv/gradingduedate',
                                                    $name,
                                                    $description,
                                                    1209600);
    $setting->set_enabled_flag_options(admin_setting_flag::ENABLED, true);
    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, false);
    $settings->add($setting);

    $name = new lang_string('submissiondrafts', 'mod_tarefalv');
    $description = new lang_string('submissiondrafts_help', 'mod_tarefalv');
    $setting = new admin_setting_configcheckbox('tarefalv/submissiondrafts',
                                                    $name,
                                                    $description,
                                                    0);
    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, false);
    $setting->set_locked_flag_options(admin_setting_flag::ENABLED, false);
    $settings->add($setting);

    $name = new lang_string('requiresubmissionstatement', 'mod_tarefalv');
    $description = new lang_string('requiresubmissionstatement_help', 'mod_tarefalv');
    $setting = new admin_setting_configcheckbox('tarefalv/requiresubmissionstatement',
                                                    $name,
                                                    $description,
                                                    0);
    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, false);
    $setting->set_locked_flag_options(admin_setting_flag::ENABLED, false);
    $settings->add($setting);

    // Constants from "locallib.php".
    $options = array(
        'none' => get_string('attemptreopenmethod_none', 'mod_tarefalv'),
        'manual' => get_string('attemptreopenmethod_manual', 'mod_tarefalv'),
        'untilpass' => get_string('attemptreopenmethod_untilpass', 'mod_tarefalv')
    );
    $name = new lang_string('attemptreopenmethod', 'mod_tarefalv');
    $description = new lang_string('attemptreopenmethod_help', 'mod_tarefalv');
    $setting = new admin_setting_configselect('tarefalv/attemptreopenmethod',
                                                    $name,
                                                    $description,
                                                    'none',
                                                    $options);
    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, false);
    $setting->set_locked_flag_options(admin_setting_flag::ENABLED, false);
    $settings->add($setting);

    // Constants from "locallib.php".
    $options = array(-1 => get_string('unlimitedattempts', 'mod_tarefalv'));
    $options += array_combine(range(1, 30), range(1, 30));
    $name = new lang_string('maxattempts', 'mod_tarefalv');
    $description = new lang_string('maxattempts_help', 'mod_tarefalv');
    $setting = new admin_setting_configselect('tarefalv/maxattempts',
                                                    $name,
                                                    $description,
                                                    -1,
                                                    $options);
    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, false);
    $setting->set_locked_flag_options(admin_setting_flag::ENABLED, false);
    $settings->add($setting);

    $name = new lang_string('teamsubmission', 'mod_tarefalv');
    $description = new lang_string('teamsubmission_help', 'mod_tarefalv');
    $setting = new admin_setting_configcheckbox('tarefalv/teamsubmission',
                                                    $name,
                                                    $description,
                                                    0);
    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, false);
    $setting->set_locked_flag_options(admin_setting_flag::ENABLED, false);
    $settings->add($setting);

    $name = new lang_string('preventsubmissionnotingroup', 'mod_tarefalv');
    $description = new lang_string('preventsubmissionnotingroup_help', 'mod_tarefalv');
    $setting = new admin_setting_configcheckbox('tarefalv/preventsubmissionnotingroup',
        $name,
        $description,
        0);
    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, false);
    $setting->set_locked_flag_options(admin_setting_flag::ENABLED, false);
    $settings->add($setting);

    $name = new lang_string('requireallteammemberssubmit', 'mod_tarefalv');
    $description = new lang_string('requireallteammemberssubmit_help', 'mod_tarefalv');
    $setting = new admin_setting_configcheckbox('tarefalv/requireallteammemberssubmit',
                                                    $name,
                                                    $description,
                                                    0);
    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, false);
    $setting->set_locked_flag_options(admin_setting_flag::ENABLED, false);
    $settings->add($setting);

    $name = new lang_string('teamsubmissiongroupingid', 'mod_tarefalv');
    $description = new lang_string('teamsubmissiongroupingid_help', 'mod_tarefalv');
    $setting = new admin_setting_configempty('tarefalv/teamsubmissiongroupingid',
                                                    $name,
                                                    $description);
    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, false);
    $settings->add($setting);

    $name = new lang_string('sendnotifications', 'mod_tarefalv');
    $description = new lang_string('sendnotifications_help', 'mod_tarefalv');
    $setting = new admin_setting_configcheckbox('tarefalv/sendnotifications',
                                                    $name,
                                                    $description,
                                                    0);
    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, false);
    $setting->set_locked_flag_options(admin_setting_flag::ENABLED, false);
    $settings->add($setting);

    $name = new lang_string('sendlatenotifications', 'mod_tarefalv');
    $description = new lang_string('sendlatenotifications_help', 'mod_tarefalv');
    $setting = new admin_setting_configcheckbox('tarefalv/sendlatenotifications',
                                                    $name,
                                                    $description,
                                                    0);
    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, false);
    $setting->set_locked_flag_options(admin_setting_flag::ENABLED, false);
    $settings->add($setting);

    $name = new lang_string('sendstudentnotificationsdefault', 'mod_tarefalv');
    $description = new lang_string('sendstudentnotificationsdefault_help', 'mod_tarefalv');
    $setting = new admin_setting_configcheckbox('tarefalv/sendstudentnotifications',
                                                    $name,
                                                    $description,
                                                    1);
    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, false);
    $setting->set_locked_flag_options(admin_setting_flag::ENABLED, false);
    $settings->add($setting);

    $name = new lang_string('blindmarking', 'mod_tarefalv');
    $description = new lang_string('blindmarking_help', 'mod_tarefalv');
    $setting = new admin_setting_configcheckbox('tarefalv/blindmarking',
                                                    $name,
                                                    $description,
                                                    0);
    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, false);
    $setting->set_locked_flag_options(admin_setting_flag::ENABLED, false);
    $settings->add($setting);

    $name = new lang_string('markingworkflow', 'mod_tarefalv');
    $description = new lang_string('markingworkflow_help', 'mod_tarefalv');
    $setting = new admin_setting_configcheckbox('tarefalv/markingworkflow',
                                                    $name,
                                                    $description,
                                                    0);
    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, false);
    $setting->set_locked_flag_options(admin_setting_flag::ENABLED, false);
    $settings->add($setting);

    $name = new lang_string('markingallocation', 'mod_tarefalv');
    $description = new lang_string('markingallocation_help', 'mod_tarefalv');
    $setting = new admin_setting_configcheckbox('tarefalv/markingallocation',
                                                    $name,
                                                    $description,
                                                    0);
    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, false);
    $setting->set_locked_flag_options(admin_setting_flag::ENABLED, false);
    $settings->add($setting);
}

$ADMIN->add('modtarefalvfolder', $settings);
// Tell core we already added the settings structure.
$settings = null;

$ADMIN->add('modtarefalvfolder', new admin_category('tarefalvsubmissionplugins',
    new lang_string('submissionplugins', 'tarefalv'), !$module->is_enabled()));
$ADMIN->add('tarefalvsubmissionplugins', new tarefalv_admin_page_manage_tarefalv_plugins('tarefalvsubmission'));
$ADMIN->add('modtarefalvfolder', new admin_category('tarefalvfeedbackplugins',
    new lang_string('feedbackplugins', 'tarefalv'), !$module->is_enabled()));
$ADMIN->add('tarefalvfeedbackplugins', new tarefalv_admin_page_manage_tarefalv_plugins('tarefalvfeedback'));

foreach (core_plugin_manager::instance()->get_plugins_of_type('tarefalvsubmission') as $plugin) {
    /** @var \mod_tarefalv\plugininfo\tarefalvsubmission $plugin */
    $plugin->load_settings($ADMIN, 'tarefalvsubmissionplugins', $hassiteconfig);
}

foreach (core_plugin_manager::instance()->get_plugins_of_type('tarefalvfeedback') as $plugin) {
    /** @var \mod_tarefalv\plugininfo\tarefalvfeedback $plugin */
    $plugin->load_settings($ADMIN, 'tarefalvfeedbackplugins', $hassiteconfig);
}
