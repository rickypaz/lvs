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
 * Chatlv external functions and service definitions.
 *
 * @package    mod_chatlv
 * @category   external
 * @copyright  2015 Juan Leyva <juan@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.0
 */

defined('MOODLE_INTERNAL') || die;

$functions = array(

    'mod_chatlv_login_user' => array(
        'classname'     => 'mod_chatlv_external',
        'methodname'    => 'login_user',
        'description'   => 'Log a user into a chatlv room in the given chatlv.',
        'type'          => 'write',
        'capabilities'  => 'mod/chatlv:chatlv',
        'services'      => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),

    'mod_chatlv_get_chatlv_users' => array(
        'classname'     => 'mod_chatlv_external',
        'methodname'    => 'get_chatlv_users',
        'description'   => 'Get the list of users in the given chatlv session.',
        'type'          => 'read',
        'capabilities'  => 'mod/chatlv:chatlv',
        'services'      => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),

    'mod_chatlv_send_chatlv_message' => array(
        'classname'     => 'mod_chatlv_external',
        'methodname'    => 'send_chatlv_message',
        'description'   => 'Send a message on the given chatlv session.',
        'type'          => 'write',
        'capabilities'  => 'mod/chatlv:chatlv',
        'services'      => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),

    'mod_chatlv_get_chatlv_latest_messages' => array(
        'classname'     => 'mod_chatlv_external',
        'methodname'    => 'get_chatlv_latest_messages',
        'description'   => 'Get the latest messages from the given chatlv session.',
        'type'          => 'read',
        'capabilities'  => 'mod/chatlv:chatlv',
        'services'      => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),

    'mod_chatlv_view_chatlv' => array(
        'classname'     => 'mod_chatlv_external',
        'methodname'    => 'view_chatlv',
        'description'   => 'Trigger the course module viewed event and update the module completion status.',
        'type'          => 'write',
        'capabilities'  => 'mod/chatlv:chatlv',
        'services'      => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),

    'mod_chatlv_get_chatlvs_by_courses' => array(
        'classname'     => 'mod_chatlv_external',
        'methodname'    => 'get_chatlvs_by_courses',
        'description'   => 'Returns a list of chatlv instances in a provided set of courses,
                            if no courses are provided then all the chatlv instances the user has access to will be returned.',
        'type'          => 'read',
        'capabilities'  => '',
        'services'      => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    )
);
