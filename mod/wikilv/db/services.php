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
 * Wikilv external functions and service definitions.
 *
 * @package    mod_wikilv
 * @category   external
 * @copyright  2015 Dani Palou <dani@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.1
 */

$functions = array(

    'mod_wikilv_get_wikilvs_by_courses' => array(
        'classname'     => 'mod_wikilv_external',
        'methodname'    => 'get_wikilvs_by_courses',
        'description'   => 'Returns a list of wikilv instances in a provided set of courses, if ' .
                           'no courses are provided then all the wikilv instances the user has access to will be returned.',
        'type'          => 'read',
        'capabilities'  => 'mod/wikilv:viewpage',
        'services'      => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),

    'mod_wikilv_view_wikilv' => array(
        'classname'     => 'mod_wikilv_external',
        'methodname'    => 'view_wikilv',
        'description'   => 'Trigger the course module viewed event and update the module completion status.',
        'type'          => 'write',
        'capabilities'  => 'mod/wikilv:viewpage',
        'services'      => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),

    'mod_wikilv_view_page' => array(
        'classname'     => 'mod_wikilv_external',
        'methodname'    => 'view_page',
        'description'   => 'Trigger the page viewed event and update the module completion status.',
        'type'          => 'write',
        'capabilities'  => 'mod/wikilv:viewpage',
        'services'      => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),

    'mod_wikilv_get_subwikilvs' => array(
        'classname'     => 'mod_wikilv_external',
        'methodname'    => 'get_subwikilvs',
        'description'   => 'Returns the list of subwikilvs the user can see in a specific wikilv.',
        'type'          => 'read',
        'capabilities'  => 'mod/wikilv:viewpage',
        'services'      => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),
    'mod_wikilv_get_subwikilv_pages' => array(
        'classname'     => 'mod_wikilv_external',
        'methodname'    => 'get_subwikilv_pages',
        'description'   => 'Returns the list of pages for a specific subwikilv.',
        'type'          => 'read',
        'capabilities'  => 'mod/wikilv:viewpage',
        'services'      => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),

    'mod_wikilv_get_subwikilv_files' => array(
        'classname'     => 'mod_wikilv_external',
        'methodname'    => 'get_subwikilv_files',
        'description'   => 'Returns the list of files for a specific subwikilv.',
        'type'          => 'read',
        'capabilities'  => 'mod/wikilv:viewpage',
        'services'      => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),

    'mod_wikilv_get_page_contents' => array(
        'classname'     => 'mod_wikilv_external',
        'methodname'    => 'get_page_contents',
        'description'   => 'Returns the contents of a page.',
        'type'          => 'read',
        'capabilities'  => 'mod/wikilv:viewpage',
        'services'      => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),

    'mod_wikilv_get_page_for_editing' => array(
        'classname'     => 'mod_wikilv_external',
        'methodname'    => 'get_page_for_editing',
        'description'   => 'Locks and retrieves info of page-section to be edited.',
        'type'          => 'write',
        'capabilities'  => 'mod/wikilv:editpage',
        'services'      => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),

    'mod_wikilv_new_page' => array(
        'classname'     => 'mod_wikilv_external',
        'methodname'    => 'new_page',
        'description'   => 'Create a new page in a subwikilv.',
        'type'          => 'write',
        'capabilities'  => 'mod/wikilv:editpage',
        'services'      => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),

    'mod_wikilv_edit_page' => array(
        'classname'     => 'mod_wikilv_external',
        'methodname'    => 'edit_page',
        'description'   => 'Save the contents of a page.',
        'type'          => 'write',
        'capabilities'  => 'mod/wikilv:editpage',
        'services'      => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    )
);
