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

defined('MOODLE_INTERNAL') || die;

if ($ADMIN->fulltree) {
    $settings->add(new admin_setting_heading('chatlv_method_heading', get_string('generalconfig', 'chatlv'),
                       get_string('explaingeneralconfig', 'chatlv')));

    $options = array();
    $options['ajax']      = get_string('methodajax', 'chatlv');
    $options['header_js'] = get_string('methodnormal', 'chatlv');
    $options['sockets']   = get_string('methoddaemon', 'chatlv');
    $settings->add(new admin_setting_configselect('chatlv_method', get_string('method', 'chatlv'),
                       get_string('configmethod', 'chatlv'), 'ajax', $options));

    $settings->add(new admin_setting_configtext('chatlv_refresh_userlist', get_string('refreshuserlist', 'chatlv'),
                       get_string('configrefreshuserlist', 'chatlv'), 10, PARAM_INT));

    $settings->add(new admin_setting_configtext('chatlv_old_ping', get_string('oldping', 'chatlv'),
                       get_string('configoldping', 'chatlv'), 35, PARAM_INT));

    $settings->add(new admin_setting_heading('chatlv_normal_heading', get_string('methodnormal', 'chatlv'),
                       get_string('explainmethodnormal', 'chatlv')));

    $settings->add(new admin_setting_configtext('chatlv_refresh_room', get_string('refreshroom', 'chatlv'),
                       get_string('configrefreshroom', 'chatlv'), 5, PARAM_INT));

    $options = array();
    $options['jsupdate']  = get_string('normalkeepalive', 'chatlv');
    $options['jsupdated'] = get_string('normalstream', 'chatlv');
    $settings->add(new admin_setting_configselect('chatlv_normal_updatemode', get_string('updatemethod', 'chatlv'),
                       get_string('confignormalupdatemode', 'chatlv'), 'jsupdate', $options));

    $settings->add(new admin_setting_heading('chatlv_daemon_heading', get_string('methoddaemon', 'chatlv'),
                       get_string('explainmethoddaemon', 'chatlv')));

    $settings->add(new admin_setting_configtext('chatlv_serverhost', get_string('serverhost', 'chatlv'),
                       get_string('configserverhost', 'chatlv'), get_host_from_url($CFG->wwwroot)));

    $settings->add(new admin_setting_configtext('chatlv_serverip', get_string('serverip', 'chatlv'),
                       get_string('configserverip', 'chatlv'), '127.0.0.1'));

    $settings->add(new admin_setting_configtext('chatlv_serverport', get_string('serverport', 'chatlv'),
                       get_string('configserverport', 'chatlv'), 9111, PARAM_INT));

    $settings->add(new admin_setting_configtext('chatlv_servermax', get_string('servermax', 'chatlv'),
                       get_string('configservermax', 'chatlv'), 100, PARAM_INT));
}
