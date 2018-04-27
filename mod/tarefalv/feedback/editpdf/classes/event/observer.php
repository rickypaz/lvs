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
 * An event observer.
 *
 * @package    tarefalvfeedback_editpdf
 * @copyright  2016 Damyon Wiese
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace tarefalvfeedback_editpdf\event;

/**
 * An event observer.
 * @copyright  2016 Damyon Wiese
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {

    /**
     * Listen to events and queue the submission for processing.
     * @param \mod_tarefalv\event\submission_created $event
     */
    public static function submission_created(\mod_tarefalv\event\submission_created $event) {
        global $DB;

        $submissionid = $event->other['submissionid'];
        $submissionattempt = $event->other['submissionattempt'];
        $fields = array( 'submissionid' => $submissionid, 'submissionattempt' => $submissionattempt);
        $record = (object) $fields;

        $exists = $DB->get_records('tarefalvfeedback_editpdf_queue', $fields);
        if (!$exists) {
            $DB->insert_record('tarefalvfeedback_editpdf_queue', $record);
        }
    }

    /**
     * Listen to events and queue the submission for processing.
     * @param \mod_tarefalv\event\submission_updated $event
     */
    public static function submission_updated(\mod_tarefalv\event\submission_updated $event) {
        global $DB;

        $submissionid = $event->other['submissionid'];
        $submissionattempt = $event->other['submissionattempt'];
        $fields = array( 'submissionid' => $submissionid, 'submissionattempt' => $submissionattempt);
        $record = (object) $fields;

        $exists = $DB->get_records('tarefalvfeedback_editpdf_queue', $fields);
        if (!$exists) {
            $DB->insert_record('tarefalvfeedback_editpdf_queue', $record);
        }
    }
}
