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
 * Disable the tarefalv module for new installs
 *
 * @package mod_tarefalv
 * @copyright 2012 NetSpot {@link http://www.netspot.com.au}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();


/**
 * Code run after the mod_tarefalv module database tables have been created.
 * Disables this plugin for new installs
 * @return bool
 */
function xmldb_tarefalv_install() {
    global $DB;

    // do the install
    $DB->set_field('modules', 'visible', '1', array('name'=>'tarefalv')); // Hide main module

    // Should not need to modify course modinfo because this is a new install

    return true;
}


