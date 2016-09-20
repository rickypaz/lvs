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
 * mod_wikilv data generator.
 *
 * @package    mod_wikilv
 * @category   test
 * @copyright  2013 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * mod_wikilv data generator class.
 *
 * @package    mod_wikilv
 * @category   test
 * @copyright  2013 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_wikilv_generator extends testing_module_generator {

    /**
     * @var int keep track of how many pages have been created.
     */
    protected $pagecount = 0;

    /**
     * To be called from data reset code only,
     * do not use in tests.
     * @return void
     */
    public function reset() {
        $this->pagecount = 0;
        parent::reset();
    }

    public function create_instance($record = null, array $options = null) {
        // Add default values for wikilv.
        $record = (array)$record + array(
            'wikilvmode' => 'collaborative',
            'firstpagetitle' => 'Front page for wikilv '.($this->instancecount+1),
            'defaultformat' => 'html',
            'forceformat' => 0
        );

        return parent::create_instance($record, (array)$options);
    }

    public function create_content($wikilv, $record = array()) {
        $record = (array)$record + array(
            'wikilvid' => $wikilv->id
        );
        return $this->create_page($wikilv, $record);
    }

    public function create_first_page($wikilv, $record = array()) {
        $record = (array)$record + array(
            'title' => $wikilv->firstpagetitle,
        );
        return $this->create_page($wikilv, $record);
    }

    /**
     * Generates a page in wikilv.
     *
     * @param stdClass wikilv object returned from create_instance (if known)
     * @param stdClass|array $record data to insert as wikilv entry.
     * @return stdClass
     * @throws coding_exception if neither $record->wikilvid nor $wikilv->id is specified
     */
    public function create_page($wikilv, $record = array()) {
        global $CFG, $USER;
        require_once($CFG->dirroot.'/mod/wikilv/locallib.php');
        $this->pagecount++;
        $record = (array)$record + array(
            'title' => 'wikilv page '.$this->pagecount,
            'wikilvid' => $wikilv->id,
            'subwikilvid' => 0,
            'group' => 0,
            'content' => 'Wikilv page content '.$this->pagecount,
            'format' => $wikilv->defaultformat
        );
        if (empty($record['wikilvid']) && empty($record['subwikilvid'])) {
            throw new coding_exception('wikilv page generator requires either wikilvid or subwikilvid');
        }
        if (!$record['subwikilvid']) {
            if (!isset($record['userid'])) {
                $record['userid'] = ($wikilv->wikilvmode == 'individual') ? $USER->id : 0;
            }
            if ($subwikilv = wikilv_get_subwikilv_by_group($record['wikilvid'], $record['group'], $record['userid'])) {
                $record['subwikilvid'] = $subwikilv->id;
            } else {
                $record['subwikilvid'] = wikilv_add_subwikilv($record['wikilvid'], $record['group'], $record['userid']);
            }
        }

        $wikilvpage = wikilv_get_page_by_title($record['subwikilvid'], $record['title']);
        if (!$wikilvpage) {
            $pageid = wikilv_create_page($record['subwikilvid'], $record['title'], $record['format'], $USER->id);
            $wikilvpage = wikilv_get_page($pageid);
        }
        $rv = wikilv_save_page($wikilvpage, $record['content'], $USER->id);

        if (array_key_exists('tags', $record)) {
            $tags = is_array($record['tags']) ? $record['tags'] : preg_split('/,/', $record['tags']);
            if (empty($wikilv->cmid)) {
                $cm = get_coursemodule_from_instance('wikilv', $wikilv->id, isset($wikilv->course) ? $wikilv->course : 0);
                $wikilv->cmid = $cm->id;
            }
            core_tag_tag::set_item_tags('mod_wikilv', 'wikilv_pages', $wikilvpage->id,
                    context_module::instance($wikilv->cmid), $tags);
        }
        return $rv['page'];
    }
}
