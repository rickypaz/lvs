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
 * Post-install code for the tarefalvfeedback_file module.
 *
 * @package tarefalvfeedback_file
 * @copyright  1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

/**
 * Code run after the tarefalvfeedback_file module database tables have been created.
 * Moves the feedback file plugin down
 *
 * @return bool
 */
function xmldb_tarefalvfeedback_file_install() {
    global $CFG;

    require_once($CFG->dirroot . '/mod/tarefalv/adminlib.php');

    // Set the correct initial order for the plugins.
    $pluginmanager = new tarefalv_plugin_manager('tarefalvfeedback');
    $pluginmanager->move_plugin('file', 'down');

    return true;
}


