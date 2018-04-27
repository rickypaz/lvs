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
 * Upgrade code for install
 *
 * @package   mod_tarefalv
 * @copyright 2012 NetSpot {@link http://www.netspot.com.au}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * upgrade this assignment instance - this function could be skipped but it will be needed later
 * @param int $oldversion The old version of the tarefalv module
 * @return bool
 */
function xmldb_tarefalv_upgrade($oldversion) {
    global $CFG, $DB;

    $dbman = $DB->get_manager();

    // Moodle v3.1.0 release upgrade line.
    // Put any upgrade step following this.

    if ($oldversion < 2016100301) {

        // Define table tarefalv_overrides to be created.
        $table = new xmldb_table('tarefalv_overrides');

        // Adding fields to table tarefalv_overrides.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('tarefalvid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('groupid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('sortorder', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('allowsubmissionsfromdate', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('duedate', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('cutoffdate', XMLDB_TYPE_INTEGER, '10', null, null, null, null);

        // Adding keys to table tarefalv_overrides.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->add_key('tarefalvid', XMLDB_KEY_FOREIGN, array('tarefalvid'), 'tarefalv', array('id'));
        $table->add_key('groupid', XMLDB_KEY_FOREIGN, array('groupid'), 'groups', array('id'));
        $table->add_key('userid', XMLDB_KEY_FOREIGN, array('userid'), 'user', array('id'));

        // Conditionally launch create table for tarefalv_overrides.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Tarefalv savepoint reached.
        upgrade_mod_savepoint(true, 2016100301, 'tarefalv');
    }

    // Automatically generated Moodle v3.2.0 release upgrade line.
    // Put any upgrade step following this.

    if ($oldversion < 2017021500) {
        // Fix event types of tarefalv events.
        $params = [
            'modulename' => 'tarefalv',
            'eventtype' => 'close'
        ];
        $select = "modulename = :modulename AND eventtype = :eventtype";
        $DB->set_field_select('event', 'eventtype', 'due', $select, $params);

        // Delete 'open' events.
        $params = [
            'modulename' => 'tarefalv',
            'eventtype' => 'open'
        ];
        $DB->delete_records('event', $params);

        // Tarefalv savepoint reached.
        upgrade_mod_savepoint(true, 2017021500, 'tarefalv');
    }

    if ($oldversion < 2017031300) {
        // Add a 'gradingduedate' field to the 'tarefalv' table.
        $table = new xmldb_table('tarefalv');
        $field = new xmldb_field('gradingduedate', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, 0, 'cutoffdate');

        // Conditionally launch add field.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Tarefalv savepoint reached.
        upgrade_mod_savepoint(true, 2017031300, 'tarefalv');
    }

    if ($oldversion < 2017042800) {
        // Update query to set the grading due date one week after the due date.
        // Only tarefalv instances with grading due date not set and with a due date of not older than 3 weeks will be updated.
        $sql = "UPDATE {tarefalv}
                   SET gradingduedate = duedate + :weeksecs
                 WHERE gradingduedate = 0
                       AND duedate > :timelimit";

        // Calculate the time limit, which is 3 weeks before the current date.
        $interval = new DateInterval('P3W');
        $timelimit = new DateTime();
        $timelimit->sub($interval);

        // Update query params.
        $params = [
            'weeksecs' => WEEKSECS,
            'timelimit' => $timelimit->getTimestamp()
        ];

        // Execute DB update for tarefalv instances.
        $DB->execute($sql, $params);

        // Tarefalv savepoint reached.
        upgrade_mod_savepoint(true, 2017042800, 'tarefalv');
    }

    // Automatically generated Moodle v3.3.0 release upgrade line.
    // Put any upgrade step following this.
    if ($oldversion < 2017061200) {
        // Data fix any tarefalv group override event priorities which may have been accidentally nulled due to a bug on the group
        // overrides edit form.

        // First, find all tarefalv group override events having null priority (and join their corresponding tarefalv_overrides entry).
        $sql = "SELECT e.id AS id, o.sortorder AS priority
                  FROM {tarefalv_overrides} o
                  JOIN {event} e ON (e.modulename = 'tarefalv' AND o.tarefalvid = e.instance AND e.groupid = o.groupid)
                 WHERE o.groupid IS NOT NULL AND e.priority IS NULL
              ORDER BY o.id";
        $affectedrs = $DB->get_recordset_sql($sql);

        // Now update the event's priority based on the tarefalv_overrides sortorder we found. This uses similar logic to
        // tarefalv_refresh_events(), except we've restricted the set of assignments and overrides we're dealing with here.
        foreach ($affectedrs as $record) {
            $DB->set_field('event', 'priority', $record->priority, ['id' => $record->id]);
        }
        $affectedrs->close();

        // Main savepoint reached.
        upgrade_mod_savepoint(true, 2017061200, 'tarefalv');
    }

    if ($oldversion < 2017061205) {
        require_once($CFG->dirroot.'/mod/tarefalv/upgradelib.php');
        $brokentarefalvs = get_assignments_with_rescaled_null_grades();

        // Set config value.
        foreach ($brokentarefalvs as $tarefalv) {
            set_config('has_rescaled_null_grades_' . $tarefalv, 1, 'tarefalv');
        }

        // Main savepoint reached.
        upgrade_mod_savepoint(true, 2017061205, 'tarefalv');
    }

    // Automatically generated Moodle v3.4.0 release upgrade line.
    // Put any upgrade step following this.

    return true;
}
