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
 * Wikilv global search unit tests.
 *
 * @package     mod_wikilv
 * @category    test
 * @copyright   2016 Eric Merrill {@link http://www.merrilldigital.com}
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/search/tests/fixtures/testable_core_search.php');

/**
 * Provides the unit tests for wikilv global search.
 *
 * @package     mod_wikilv
 * @category    test
 * @copyright   2016 Eric Merrill {@link http://www.merrilldigital.com}
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_wikilv_search_testcase extends advanced_testcase {

    /**
     * @var string Area id
     */
    protected $wikilvcollabpageareaid = null;

    public function setUp() {
        $this->resetAfterTest(true);
        $this->setAdminUser();
        set_config('enableglobalsearch', true);

        $this->wikilvcollabpageareaid = \core_search\manager::generate_areaid('mod_wikilv', 'collaborative_page');

        // Set \core_search::instance to the mock_search_engine as we don't require the search engine to be working to test this.
        $search = testable_core_search::instance();
    }

    /**
     * Availability.
     *
     * @return void
     */
    public function test_search_enabled() {
        $searcharea = \core_search\manager::get_search_area($this->wikilvcollabpageareaid);
        list($componentname, $varname) = $searcharea->get_config_var_name();

        // Enabled by default once global search is enabled.
        $this->assertTrue($searcharea->is_enabled());

        set_config($varname . '_enabled', false, $componentname);
        $this->assertFalse($searcharea->is_enabled());

        set_config($varname . '_enabled', true, $componentname);
        $this->assertTrue($searcharea->is_enabled());
    }

    /**
     * Indexing collaborative page contents.
     *
     * @return void
     */
    public function test_collaborative_page_indexing() {
        global $DB;

        // Returns the instance as long as the area is supported.
        $searcharea = \core_search\manager::get_search_area($this->wikilvcollabpageareaid);
        $this->assertInstanceOf('\mod_wikilv\search\collaborative_page', $searcharea);

        $wikilvgenerator = $this->getDataGenerator()->get_plugin_generator('mod_wikilv');
        $course1 = self::getDataGenerator()->create_course();

        $collabwikilv = $this->getDataGenerator()->create_module('wikilv', array('course' => $course1->id));
        $cpage1 = $wikilvgenerator->create_first_page($collabwikilv);
        $cpage2 = $wikilvgenerator->create_content($collabwikilv);
        $cpage3 = $wikilvgenerator->create_content($collabwikilv);

        $indwikilv = $this->getDataGenerator()->create_module('wikilv', array('course' => $course1->id, 'wikilvmode' => 'individual'));
        $ipage1 = $wikilvgenerator->create_first_page($indwikilv);
        $ipage2 = $wikilvgenerator->create_content($indwikilv);
        $ipage3 = $wikilvgenerator->create_content($indwikilv);

        // All records.
        $recordset = $searcharea->get_recordset_by_timestamp(0);
        $this->assertTrue($recordset->valid());
        $nrecords = 0;
        foreach ($recordset as $record) {
            $this->assertInstanceOf('stdClass', $record);
            $doc = $searcharea->get_document($record);
            $this->assertInstanceOf('\core_search\document', $doc);

            // Static caches are working.
            $dbreads = $DB->perf_get_reads();
            $doc = $searcharea->get_document($record);
            $this->assertEquals($dbreads, $DB->perf_get_reads());
            $this->assertInstanceOf('\core_search\document', $doc);
            $nrecords++;
        }
        // If there would be an error/failure in the foreach above the recordset would be closed on shutdown.
        $recordset->close();

        // We expect 3 (not 6) pages.
        $this->assertEquals(3, $nrecords);

        // The +2 is to prevent race conditions.
        $recordset = $searcharea->get_recordset_by_timestamp(time() + 2);

        // No new records.
        $this->assertFalse($recordset->valid());
        $recordset->close();
    }

    /**
     * Check collaborative_page check access.
     *
     * @return void
     */
    public function test_collaborative_page_check_access() {
        global $DB;

        // Returns the instance as long as the area is supported.
        $searcharea = \core_search\manager::get_search_area($this->wikilvcollabpageareaid);
        $this->assertInstanceOf('\mod_wikilv\search\collaborative_page', $searcharea);

        $user1 = self::getDataGenerator()->create_user();
        $course1 = self::getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user($user1->id, $course1->id, 'student');

        $wikilvgenerator = $this->getDataGenerator()->get_plugin_generator('mod_wikilv');

        $collabwikilv = $this->getDataGenerator()->create_module('wikilv', array('course' => $course1->id));
        $cpage1 = $wikilvgenerator->create_first_page($collabwikilv);

        $this->setAdminUser();
        $this->assertEquals(\core_search\manager::ACCESS_GRANTED, $searcharea->check_access($cpage1->id));

        $this->setUser($user1);
        $this->assertEquals(\core_search\manager::ACCESS_GRANTED, $searcharea->check_access($cpage1->id));

        $this->assertEquals(\core_search\manager::ACCESS_DELETED, $searcharea->check_access($cpage1->id + 10));
    }
}
