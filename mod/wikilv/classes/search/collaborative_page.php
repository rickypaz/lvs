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
 * Search area for mod_wikilv collaborative pages.
 *
 * @package    mod_wikilv
 * @copyright  2016 Eric Merrill {@link http://www.merrilldigital.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_wikilv\search;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/wikilv/locallib.php');

/**
 * Search area for mod_wikilv collaborative pages.
 *
 * @package    mod_wikilv
 * @copyright  2016 Eric Merrill {@link http://www.merrilldigital.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class collaborative_page extends \core_search\area\base_mod {
    /**
     * @var array Cache of wikilv records.
     */
    protected $wikilvscache = array();

    /**
     * Returns a recordset with all required page information.
     *
     * @param int $modifiedfrom
     * @return moodle_recordset
     */
    public function get_recordset_by_timestamp($modifiedfrom = 0) {
        global $DB;

        $sql = 'SELECT p.*, w.id AS wikilvid, w.course AS courseid
                  FROM {wikilv_pages} p
                  JOIN {wikilv_subwikilvs} s ON s.id = p.subwikilvid
                  JOIN {wikilv} w ON w.id = s.wikilvid
                 WHERE p.timemodified >= ?
                   AND w.wikilvmode = ?
              ORDER BY p.timemodified ASC';
        return $DB->get_recordset_sql($sql, array($modifiedfrom, 'collaborative'));
    }

    /**
     * Returns the document for a particular page.
     *
     * @param \stdClass $record A record containing, at least, the indexed document id and a modified timestamp
     * @param array     $options Options for document creation
     * @return \core_search\document
     */
    public function get_document($record, $options = array()) {
        try {
            $cm = $this->get_cm('wikilv', $record->wikilvid, $record->courseid);
            $context = \context_module::instance($cm->id);
        } catch (\dml_missing_record_exception $ex) {
            // Notify it as we run here as admin, we should see everything.
            debugging('Error retrieving ' . $this->areaid . ' ' . $record->id . ' document, not all required data is available: ' .
                $ex->getMessage(), DEBUG_DEVELOPER);
            return false;
        } catch (\dml_exception $ex) {
            // Notify it as we run here as admin, we should see everything.
            debugging('Error retrieving ' . $this->areaid . ' ' . $record->id . ' document: ' . $ex->getMessage(), DEBUG_DEVELOPER);
            return false;
        }

        // Make a page object without extra fields.
        $page = clone $record;
        unset($page->courseid);
        unset($page->wikilvid);

        // Conversion based wikilv_print_page_content().
        // Check if we have passed the cache time.
        if ($page->timerendered + WIKILV_REFRESH_CACHE_TIME < time()) {
            $content = wikilv_refresh_cachedcontent($page);
            $page = $content['page'];
        }
        // Convert to text.
        $content = content_to_text($page->cachedcontent, FORMAT_MOODLE);

        // Prepare associative array with data from DB.
        $doc = \core_search\document_factory::instance($record->id, $this->componentname, $this->areaname);
        $doc->set('title', content_to_text($record->title, false));
        $doc->set('content', $content);
        $doc->set('contextid', $context->id);
        $doc->set('courseid', $record->courseid);
        $doc->set('owneruserid', \core_search\manager::NO_OWNER_ID);
        $doc->set('modified', $record->timemodified);

        // Check if this document should be considered new.
        if (isset($options['lastindexedtime']) && ($options['lastindexedtime'] < $record->timecreated)) {
            // If the document was created after the last index time, it must be new.
            $doc->set_is_new(true);
        }

        return $doc;
    }

    /**
     * Can the current user see the document.
     *
     * @param int $id The internal search area entity id.
     * @return bool True if the user can see it, false otherwise
     */
    public function check_access($id) {
        global $DB;

        try {
            $page = $DB->get_record('wikilv_pages', array('id' => $id), '*', MUST_EXIST);
            if (!isset($this->wikilvscache[$page->subwikilvid])) {
                $sql = 'SELECT w.*
                          FROM {wikilv_subwikilvs} s
                          JOIN {wikilv} w ON w.id = s.wikilvid
                         WHERE s.id = ?';
                $this->wikilvscache[$page->subwikilvid] = $DB->get_record_sql($sql, array('id' => $page->subwikilvid), MUST_EXIST);
            }
            $wikilv = $this->wikilvscache[$page->subwikilvid];
            $cminfo = $this->get_cm('wikilv', $wikilv->id, $wikilv->course);
        } catch (\dml_missing_record_exception $ex) {
            return \core_search\manager::ACCESS_DELETED;
        } catch (\dml_exception $ex) {
            return \core_search\manager::ACCESS_DENIED;
        }

        // Recheck uservisible although it should have already been checked in core_search.
        if ($cminfo->uservisible === false) {
            return \core_search\manager::ACCESS_DENIED;
        }

        $context = \context_module::instance($cminfo->id);

        if (!has_capability('mod/wikilv:viewpage', $context)) {
            return \core_search\manager::ACCESS_DENIED;
        }

        return \core_search\manager::ACCESS_GRANTED;
    }

    /**
     * Returns a url to the page.
     *
     * @param \core_search\document $doc
     * @return \moodle_url
     */
    public function get_doc_url(\core_search\document $doc) {
        $params = array('pageid' => $doc->get('itemid'));
        return new \moodle_url('/mod/wikilv/view.php', $params);
    }

    /**
     * Returns a url to the wikilv.
     *
     * @param \core_search\document $doc
     * @return \moodle_url
     */
    public function get_context_url(\core_search\document $doc) {
        $contextmodule = \context::instance_by_id($doc->get('contextid'));
        return new \moodle_url('/mod/wikilv/view.php', array('id' => $contextmodule->instanceid));
    }
}
