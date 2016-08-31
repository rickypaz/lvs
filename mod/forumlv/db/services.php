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
 * Forumlv external functions and service definitions.
 *
 * @package    mod_forumlv
 * @copyright  2012 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$functions = array(

    'mod_forumlv_get_forumlvs_by_courses' => array(
        'classname' => 'mod_forumlv_external',
        'methodname' => 'get_forumlvs_by_courses',
        'classpath' => 'mod/forumlv/externallib.php',
        'description' => 'Returns a list of forumlv instances in a provided set of courses, if
            no courses are provided then all the forumlv instances the user has access to will be
            returned.',
        'type' => 'read',
        'capabilities' => 'mod/forumlv:viewdiscussion',
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),

    'mod_forumlv_get_forumlv_discussion_posts' => array(
        'classname' => 'mod_forumlv_external',
        'methodname' => 'get_forumlv_discussion_posts',
        'classpath' => 'mod/forumlv/externallib.php',
        'description' => 'Returns a list of forumlv posts for a discussion.',
        'type' => 'read',
        'capabilities' => 'mod/forumlv:viewdiscussion, mod/forumlv:viewqandawithoutposting',
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),

    'mod_forumlv_get_forumlv_discussions_paginated' => array(
        'classname' => 'mod_forumlv_external',
        'methodname' => 'get_forumlv_discussions_paginated',
        'classpath' => 'mod/forumlv/externallib.php',
        'description' => 'Returns a list of forumlv discussions optionally sorted and paginated.',
        'type' => 'read',
        'capabilities' => 'mod/forumlv:viewdiscussion, mod/forumlv:viewqandawithoutposting',
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),

    'mod_forumlv_view_forumlv' => array(
        'classname' => 'mod_forumlv_external',
        'methodname' => 'view_forumlv',
        'classpath' => 'mod/forumlv/externallib.php',
        'description' => 'Trigger the course module viewed event and update the module completion status.',
        'type' => 'write',
        'capabilities' => 'mod/forumlv:viewdiscussion',
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),

    'mod_forumlv_view_forumlv_discussion' => array(
        'classname' => 'mod_forumlv_external',
        'methodname' => 'view_forumlv_discussion',
        'classpath' => 'mod/forumlv/externallib.php',
        'description' => 'Trigger the forumlv discussion viewed event.',
        'type' => 'write',
        'capabilities' => 'mod/forumlv:viewdiscussion',
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),

    'mod_forumlv_add_discussion_post' => array(
        'classname' => 'mod_forumlv_external',
        'methodname' => 'add_discussion_post',
        'classpath' => 'mod/forumlv/externallib.php',
        'description' => 'Create new posts into an existing discussion.',
        'type' => 'write',
        'capabilities' => 'mod/forumlv:replypost',
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),

    'mod_forumlv_add_discussion' => array(
        'classname' => 'mod_forumlv_external',
        'methodname' => 'add_discussion',
        'classpath' => 'mod/forumlv/externallib.php',
        'description' => 'Add a new discussion into an existing forumlv.',
        'type' => 'write',
        'capabilities' => 'mod/forumlv:startdiscussion',
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),

    'mod_forumlv_can_add_discussion' => array(
        'classname' => 'mod_forumlv_external',
        'methodname' => 'can_add_discussion',
        'classpath' => 'mod/forumlv/externallib.php',
        'description' => 'Check if the current user can add discussions in the given forumlv (and optionally for the given group).',
        'type' => 'read',
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),
);
