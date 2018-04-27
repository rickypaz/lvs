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
 * The mod_tarefalv abstract base event.
 *
 * @package    mod_tarefalv
 * @copyright  2014 Mark Nelson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_tarefalv\event;

defined('MOODLE_INTERNAL') || die();

/**
 * The mod_tarefalv abstract base event class.
 *
 * Most mod_tarefalv events can extend this class.
 *
 * @package    mod_tarefalv
 * @since      Moodle 2.7
 * @copyright  2014 Mark Nelson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class base extends \core\event\base {

    /** @var \tarefalv */
    protected $tarefalv;

    /**
     * Legacy log data.
     *
     * @var array
     */
    protected $legacylogdata;

    /**
     * Set tarefalv instance for this event.
     * @param \tarefalv $tarefalv
     * @throws \coding_exception
     */
    public function set_tarefalv(\tarefalv $tarefalv) {
        if ($this->is_triggered()) {
            throw new \coding_exception('set_tarefalv() must be done before triggerring of event');
        }
        if ($tarefalv->get_context()->id != $this->get_context()->id) {
            throw new \coding_exception('Invalid tarefalv isntance supplied!');
        }
        $this->tarefalv = $tarefalv;
    }

    /**
     * Get tarefalv instance.
     *
     * NOTE: to be used from observers only.
     *
     * @throws \coding_exception
     * @return \tarefalv
     */
    public function get_tarefalv() {
        if ($this->is_restored()) {
            throw new \coding_exception('get_tarefalv() is intended for event observers only');
        }
        if (!isset($this->tarefalv)) {
            debugging('tarefalv property should be initialised in each event', DEBUG_DEVELOPER);
            global $CFG;
            require_once($CFG->dirroot . '/mod/tarefalv/locallib.php');
            $cm = get_coursemodule_from_id('tarefalv', $this->contextinstanceid, 0, false, MUST_EXIST);
            $course = get_course($cm->course);
            $this->tarefalv = new \tarefalv($this->get_context(), $cm, $course);
        }
        return $this->tarefalv;
    }


    /**
     * Returns relevant URL.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/mod/tarefalv/view.php', array('id' => $this->contextinstanceid));
    }

    /**
     * Sets the legacy event log data.
     *
     * @param string $action The current action
     * @param string $info A detailed description of the change. But no more than 255 characters.
     * @param string $url The url to the tarefalv module instance.
     */
    public function set_legacy_logdata($action = '', $info = '', $url = '') {
        $fullurl = 'view.php?id=' . $this->contextinstanceid;
        if ($url != '') {
            $fullurl .= '&' . $url;
        }

        $this->legacylogdata = array($this->courseid, 'tarefalv', $action, $fullurl, $info, $this->contextinstanceid);
    }

    /**
     * Return legacy data for add_to_log().
     *
     * @return array
     */
    protected function get_legacy_logdata() {
        if (isset($this->legacylogdata)) {
            return $this->legacylogdata;
        }

        return null;
    }

    /**
     * Custom validation.
     *
     * @throws \coding_exception
     */
    protected function validate_data() {
        parent::validate_data();

        if ($this->contextlevel != CONTEXT_MODULE) {
            throw new \coding_exception('Context level must be CONTEXT_MODULE.');
        }
    }
}
