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
 * The mod_wikilv page viewed event.
 *
 * @package    mod_wikilv
 * @copyright  2013 Rajesh Taneja <rajesh@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_wikilv\event;
defined('MOODLE_INTERNAL') || die();

/**
 * The mod_wikilv page viewed event class.
 *
 * @property-read array $other {
 *      Extra information about the event.
 *
 *      - string title: (optional) the wikilv title
 *      - int wid: (optional) the wikilv id
 *      - int group: (optional) the group id
 *      - string groupanduser: (optional) the groupid-userid
 * }
 *
 * @package    mod_wikilv
 * @since      Moodle 2.7
 * @copyright  2013 Rajesh Taneja <rajesh@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class page_viewed extends \core\event\base {
    /**
     * Init method.
     *
     * @return void
     */
    protected function init() {
        $this->data['crud'] = 'r';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
        $this->data['objecttable'] = 'wikilv_pages';
    }

    /**
     * Return localised event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventpageviewed', 'mod_wikilv');
    }

    /**
     * Returns description of what happened.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '$this->userid' viewed the page with id '$this->objectid' for the wikilv with " .
            "course module id '$this->contextinstanceid'.";
    }

    /**
     * Return the legacy event log data.
     *
     * @return array
     */
    protected function get_legacy_logdata() {
        if (!empty($this->other['wid'])) {
            return(array($this->courseid, 'wikilv', 'view',
                'view.php?wid=' . $this->data['other']['wid'] . '&title=' . $this->data['other']['title'],
                $this->data['other']['wid'], $this->contextinstanceid));
        } else if (!empty($this->other['prettyview'])) {
            return(array($this->courseid, 'wikilv', 'view',
                'prettyview.php?pageid=' . $this->objectid, $this->objectid, $this->contextinstanceid));
        } else {
            return(array($this->courseid, 'wikilv', 'view',
                'view.php?pageid=' . $this->objectid, $this->objectid, $this->contextinstanceid));
        }
    }

    /**
     * Get URL related to the action.
     *
     * @return \moodle_url
     */
    public function get_url() {
        if (!empty($this->data['other']['wid'])) {
            return new \moodle_url('/mod/wikilv/view.php', array('wid' => $this->data['other']['wid'],
                    'title' => $this->data['other']['title'],
                    'uid' => $this->relateduserid,
                    'groupanduser' => $this->data['other']['groupanduser'],
                    'group' => $this->data['other']['group']
                ));
        } else if (!empty($this->other['prettyview'])) {
            return new \moodle_url('/mod/wikilv/prettyview.php', array('pageid' => $this->objectid));
        } else {
            return new \moodle_url('/mod/wikilv/view.php', array('pageid' => $this->objectid));
        }
    }

    public static function get_objectid_mapping() {
        return array('db' => 'wikilv_pages', 'restore' => 'wikilv_page');
    }

    public static function get_other_mapping() {
        $othermapped = array();
        $othermapped['wid'] = array('db' => 'wikilv', 'restore' => 'wikilv');
        $othermapped['group'] = array('db' => 'groups', 'restore' => 'group');

        return $othermapped;
    }
}
