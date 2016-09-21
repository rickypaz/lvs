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
 * Wikilv module external functions tests.
 *
 * @package    mod_wikilv
 * @category   external
 * @copyright  2015 Dani Palou <dani@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.1
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/webservice/tests/helpers.php');
require_once($CFG->dirroot . '/mod/wikilv/lib.php');

/**
 * Wikilv module external functions tests
 *
 * @package    mod_wikilv
 * @category   external
 * @copyright  2015 Dani Palou <dani@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.1
 */
class mod_wikilv_external_testcase extends externallib_advanced_testcase {

    /**
     * Set up for every test
     */
    public function setUp() {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        // Setup test data.
        $this->course = $this->getDataGenerator()->create_course();
        $this->wikilv = $this->getDataGenerator()->create_module('wikilv', array('course' => $this->course->id));
        $this->context = context_module::instance($this->wikilv->cmid);
        $this->cm = get_coursemodule_from_instance('wikilv', $this->wikilv->id);

        // Create users.
        $this->student = self::getDataGenerator()->create_user();
        $this->student2 = self::getDataGenerator()->create_user();
        $this->teacher = self::getDataGenerator()->create_user();

        // Users enrolments.
        $this->studentrole = $DB->get_record('role', array('shortname' => 'student'));
        $this->teacherrole = $DB->get_record('role', array('shortname' => 'editingteacher'));
        $this->getDataGenerator()->enrol_user($this->student->id, $this->course->id, $this->studentrole->id, 'manual');
        $this->getDataGenerator()->enrol_user($this->student2->id, $this->course->id, $this->studentrole->id, 'manual');
        $this->getDataGenerator()->enrol_user($this->teacher->id, $this->course->id, $this->teacherrole->id, 'manual');

        // Create first pages.
        $this->firstpage = $this->getDataGenerator()->get_plugin_generator('mod_wikilv')->create_first_page($this->wikilv);
    }

    /**
     * Create two collaborative wikilvs (separate/visible groups), 2 groups and a first page for each wikilv and group.
     */
    private function create_collaborative_wikilvs_with_groups() {
        // Create groups and add student to one of them.
        if (!isset($this->group1)) {
            $this->group1 = $this->getDataGenerator()->create_group(array('courseid' => $this->course->id));
            $this->getDataGenerator()->create_group_member(array('userid' => $this->student->id, 'groupid' => $this->group1->id));
            $this->getDataGenerator()->create_group_member(array('userid' => $this->student2->id, 'groupid' => $this->group1->id));
        }
        if (!isset($this->group2)) {
            $this->group2 = $this->getDataGenerator()->create_group(array('courseid' => $this->course->id));
        }

        // Create two collaborative wikilvs.
        $this->wikilvsep = $this->getDataGenerator()->create_module('wikilv',
                                                        array('course' => $this->course->id, 'groupmode' => SEPARATEGROUPS));
        $this->wikilvvis = $this->getDataGenerator()->create_module('wikilv',
                                                        array('course' => $this->course->id, 'groupmode' => VISIBLEGROUPS));

        // Create pages.
        $wikilvgenerator = $this->getDataGenerator()->get_plugin_generator('mod_wikilv');
        $this->fpsepg1 = $wikilvgenerator->create_first_page($this->wikilvsep, array('group' => $this->group1->id));
        $this->fpsepg2 = $wikilvgenerator->create_first_page($this->wikilvsep, array('group' => $this->group2->id));
        $this->fpsepall = $wikilvgenerator->create_first_page($this->wikilvsep, array('group' => 0)); // All participants.
        $this->fpvisg1 = $wikilvgenerator->create_first_page($this->wikilvvis, array('group' => $this->group1->id));
        $this->fpvisg2 = $wikilvgenerator->create_first_page($this->wikilvvis, array('group' => $this->group2->id));
        $this->fpvisall = $wikilvgenerator->create_first_page($this->wikilvvis, array('group' => 0)); // All participants.
    }

    /**
     * Create two individual wikilvs (separate/visible groups), 2 groups and a first page for each wikilv and group.
     */
    private function create_individual_wikilvs_with_groups() {
        // Create groups and add student to one of them.
        if (!isset($this->group1)) {
            $this->group1 = $this->getDataGenerator()->create_group(array('courseid' => $this->course->id));
            $this->getDataGenerator()->create_group_member(array('userid' => $this->student->id, 'groupid' => $this->group1->id));
            $this->getDataGenerator()->create_group_member(array('userid' => $this->student2->id, 'groupid' => $this->group1->id));
        }
        if (!isset($this->group2)) {
            $this->group2 = $this->getDataGenerator()->create_group(array('courseid' => $this->course->id));
        }

        // Create two individual wikilvs.
        $this->wikilvsepind = $this->getDataGenerator()->create_module('wikilv', array('course' => $this->course->id,
                                                        'groupmode' => SEPARATEGROUPS, 'wikilvmode' => 'individual'));
        $this->wikilvvisind = $this->getDataGenerator()->create_module('wikilv', array('course' => $this->course->id,
                                                        'groupmode' => VISIBLEGROUPS, 'wikilvmode' => 'individual'));

        // Create pages. Student can only create pages in his groups.
        $wikilvgenerator = $this->getDataGenerator()->get_plugin_generator('mod_wikilv');
        $this->setUser($this->teacher);
        $this->fpsepg1indt = $wikilvgenerator->create_first_page($this->wikilvsepind, array('group' => $this->group1->id));
        $this->fpsepg2indt = $wikilvgenerator->create_first_page($this->wikilvsepind, array('group' => $this->group2->id));
        $this->fpsepallindt = $wikilvgenerator->create_first_page($this->wikilvsepind, array('group' => 0)); // All participants.
        $this->fpvisg1indt = $wikilvgenerator->create_first_page($this->wikilvvisind, array('group' => $this->group1->id));
        $this->fpvisg2indt = $wikilvgenerator->create_first_page($this->wikilvvisind, array('group' => $this->group2->id));
        $this->fpvisallindt = $wikilvgenerator->create_first_page($this->wikilvvisind, array('group' => 0)); // All participants.

        $this->setUser($this->student);
        $this->fpsepg1indstu = $wikilvgenerator->create_first_page($this->wikilvsepind, array('group' => $this->group1->id));
        $this->fpvisg1indstu = $wikilvgenerator->create_first_page($this->wikilvvisind, array('group' => $this->group1->id));

        $this->setUser($this->student2);
        $this->fpsepg1indstu2 = $wikilvgenerator->create_first_page($this->wikilvsepind, array('group' => $this->group1->id));
        $this->fpvisg1indstu2 = $wikilvgenerator->create_first_page($this->wikilvvisind, array('group' => $this->group1->id));

    }

    /*
     * Test get wikilvs by courses
     */
    public function test_mod_wikilv_get_wikilvs_by_courses() {

        // Create additional course.
        $course2 = self::getDataGenerator()->create_course();

        // Second wikilv.
        $record = new stdClass();
        $record->course = $course2->id;
        $wikilv2 = self::getDataGenerator()->create_module('wikilv', $record);

        // Execute real Moodle enrolment as we'll call unenrol() method on the instance later.
        $enrol = enrol_get_plugin('manual');
        $enrolinstances = enrol_get_instances($course2->id, true);
        foreach ($enrolinstances as $courseenrolinstance) {
            if ($courseenrolinstance->enrol == "manual") {
                $instance2 = $courseenrolinstance;
                break;
            }
        }
        $enrol->enrol_user($instance2, $this->student->id, $this->studentrole->id);

        self::setUser($this->student);

        $returndescription = mod_wikilv_external::get_wikilvs_by_courses_returns();

        // Create what we expect to be returned when querying the two courses.
        // First for the student user.
        $expectedfields = array('id', 'coursemodule', 'course', 'name', 'intro', 'introformat', 'firstpagetitle', 'wikilvmode',
                                'defaultformat', 'forceformat', 'editbegin', 'editend', 'section', 'visible', 'groupmode',
                                'groupingid');

        // Add expected coursemodule and data.
        $wikilv1 = $this->wikilv;
        $wikilv1->coursemodule = $wikilv1->cmid;
        $wikilv1->introformat = 1;
        $wikilv1->section = 0;
        $wikilv1->visible = true;
        $wikilv1->groupmode = 0;
        $wikilv1->groupingid = 0;

        $wikilv2->coursemodule = $wikilv2->cmid;
        $wikilv2->introformat = 1;
        $wikilv2->section = 0;
        $wikilv2->visible = true;
        $wikilv2->groupmode = 0;
        $wikilv2->groupingid = 0;

        foreach ($expectedfields as $field) {
            $expected1[$field] = $wikilv1->{$field};
            $expected2[$field] = $wikilv2->{$field};
        }
        // Users can create pages by default.
        $expected1['cancreatepages'] = true;
        $expected2['cancreatepages'] = true;

        $expectedwikilvs = array($expected2, $expected1);

        // Call the external function passing course ids.
        $result = mod_wikilv_external::get_wikilvs_by_courses(array($course2->id, $this->course->id));
        $result = external_api::clean_returnvalue($returndescription, $result);

        $this->assertEquals($expectedwikilvs, $result['wikilvs']);
        $this->assertCount(0, $result['warnings']);

        // Call the external function without passing course id.
        $result = mod_wikilv_external::get_wikilvs_by_courses();
        $result = external_api::clean_returnvalue($returndescription, $result);
        $this->assertEquals($expectedwikilvs, $result['wikilvs']);
        $this->assertCount(0, $result['warnings']);

        // Unenrol user from second course and alter expected wikilvs.
        $enrol->unenrol_user($instance2, $this->student->id);
        array_shift($expectedwikilvs);

        // Call the external function without passing course id.
        $result = mod_wikilv_external::get_wikilvs_by_courses();
        $result = external_api::clean_returnvalue($returndescription, $result);
        $this->assertEquals($expectedwikilvs, $result['wikilvs']);

        // Call for the second course we unenrolled the user from, expected warning.
        $result = mod_wikilv_external::get_wikilvs_by_courses(array($course2->id));
        $this->assertCount(1, $result['warnings']);
        $this->assertEquals('1', $result['warnings'][0]['warningcode']);
        $this->assertEquals($course2->id, $result['warnings'][0]['itemid']);

        // Now, try as a teacher for getting all the additional fields.
        self::setUser($this->teacher);

        $additionalfields = array('timecreated', 'timemodified');

        foreach ($additionalfields as $field) {
            $expectedwikilvs[0][$field] = $wikilv1->{$field};
        }

        $result = mod_wikilv_external::get_wikilvs_by_courses();
        $result = external_api::clean_returnvalue($returndescription, $result);
        $this->assertEquals($expectedwikilvs, $result['wikilvs']);

        // Admin also should get all the information.
        self::setAdminUser();

        $result = mod_wikilv_external::get_wikilvs_by_courses(array($this->course->id));
        $result = external_api::clean_returnvalue($returndescription, $result);
        $this->assertEquals($expectedwikilvs, $result['wikilvs']);

        // Now, prohibit capabilities.
        $this->setUser($this->student);
        $contextcourse1 = context_course::instance($this->course->id);
        // Prohibit capability = mod:wikilv:viewpage on Course1 for students.
        assign_capability('mod/wikilv:viewpage', CAP_PROHIBIT, $this->studentrole->id, $contextcourse1->id);
        accesslib_clear_all_caches_for_unit_testing();

        $wikilvs = mod_wikilv_external::get_wikilvs_by_courses(array($this->course->id));
        $wikilvs = external_api::clean_returnvalue(mod_wikilv_external::get_wikilvs_by_courses_returns(), $wikilvs);
        $this->assertFalse(isset($wikilvs['wikilvs'][0]['intro']));

        // Prohibit capability = mod:wikilv:createpage on Course1 for students.
        assign_capability('mod/wikilv:createpage', CAP_PROHIBIT, $this->studentrole->id, $contextcourse1->id);
        accesslib_clear_all_caches_for_unit_testing();

        $wikilvs = mod_wikilv_external::get_wikilvs_by_courses(array($this->course->id));
        $wikilvs = external_api::clean_returnvalue(mod_wikilv_external::get_wikilvs_by_courses_returns(), $wikilvs);
        $this->assertFalse($wikilvs['wikilvs'][0]['cancreatepages']);

    }

    /**
     * Test view_wikilv.
     */
    public function test_view_wikilv() {

        // Test invalid instance id.
        try {
            mod_wikilv_external::view_wikilv(0);
            $this->fail('Exception expected due to invalid mod_wikilv instance id.');
        } catch (moodle_exception $e) {
            $this->assertEquals('incorrectwikilvid', $e->errorcode);
        }

        // Test not-enrolled user.
        $usernotenrolled = self::getDataGenerator()->create_user();
        $this->setUser($usernotenrolled);
        try {
            mod_wikilv_external::view_wikilv($this->wikilv->id);
            $this->fail('Exception expected due to not enrolled user.');
        } catch (moodle_exception $e) {
            $this->assertEquals('requireloginerror', $e->errorcode);
        }

        // Test user with full capabilities.
        $this->setUser($this->student);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();

        $result = mod_wikilv_external::view_wikilv($this->wikilv->id);
        $result = external_api::clean_returnvalue(mod_wikilv_external::view_wikilv_returns(), $result);

        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = array_shift($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\mod_wikilv\event\course_module_viewed', $event);
        $this->assertEquals($this->context, $event->get_context());
        $moodlewikilv = new \moodle_url('/mod/wikilv/view.php', array('id' => $this->cm->id));
        $this->assertEquals($moodlewikilv, $event->get_url());
        $this->assertEventContextNotUsed($event);
        $this->assertNotEmpty($event->get_name());

        // Test user with no capabilities.
        // We need a explicit prohibit since this capability is allowed for students by default.
        assign_capability('mod/wikilv:viewpage', CAP_PROHIBIT, $this->studentrole->id, $this->context->id);
        accesslib_clear_all_caches_for_unit_testing();

        try {
            mod_wikilv_external::view_wikilv($this->wikilv->id);
            $this->fail('Exception expected due to missing capability.');
        } catch (moodle_exception $e) {
            $this->assertEquals('cannotviewpage', $e->errorcode);
        }

    }

    /**
     * Test view_page.
     */
    public function test_view_page() {

        // Test invalid page id.
        try {
            mod_wikilv_external::view_page(0);
            $this->fail('Exception expected due to invalid view_page page id.');
        } catch (moodle_exception $e) {
            $this->assertEquals('incorrectpageid', $e->errorcode);
        }

        // Test not-enrolled user.
        $usernotenrolled = self::getDataGenerator()->create_user();
        $this->setUser($usernotenrolled);
        try {
            mod_wikilv_external::view_page($this->firstpage->id);
            $this->fail('Exception expected due to not enrolled user.');
        } catch (moodle_exception $e) {
            $this->assertEquals('requireloginerror', $e->errorcode);
        }

        // Test user with full capabilities.
        $this->setUser($this->student);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();

        $result = mod_wikilv_external::view_page($this->firstpage->id);
        $result = external_api::clean_returnvalue(mod_wikilv_external::view_page_returns(), $result);

        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = array_shift($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\mod_wikilv\event\page_viewed', $event);
        $this->assertEquals($this->context, $event->get_context());
        $pageurl = new \moodle_url('/mod/wikilv/view.php', array('pageid' => $this->firstpage->id));
        $this->assertEquals($pageurl, $event->get_url());
        $this->assertEventContextNotUsed($event);
        $this->assertNotEmpty($event->get_name());

        // Test user with no capabilities.
        // We need a explicit prohibit since this capability is allowed for students by default.
        assign_capability('mod/wikilv:viewpage', CAP_PROHIBIT, $this->studentrole->id, $this->context->id);
        accesslib_clear_all_caches_for_unit_testing();

        try {
            mod_wikilv_external::view_page($this->firstpage->id);
            $this->fail('Exception expected due to missing capability.');
        } catch (moodle_exception $e) {
            $this->assertEquals('cannotviewpage', $e->errorcode);
        }

    }

    /**
     * Test get_subwikilvs.
     */
    public function test_get_subwikilvs() {

        // Test invalid wikilv id.
        try {
            mod_wikilv_external::get_subwikilvs(0);
            $this->fail('Exception expected due to invalid get_subwikilvs wikilv id.');
        } catch (moodle_exception $e) {
            $this->assertEquals('incorrectwikilvid', $e->errorcode);
        }

        // Test not-enrolled user.
        $usernotenrolled = self::getDataGenerator()->create_user();
        $this->setUser($usernotenrolled);
        try {
            mod_wikilv_external::get_subwikilvs($this->wikilv->id);
            $this->fail('Exception expected due to not enrolled user.');
        } catch (moodle_exception $e) {
            $this->assertEquals('requireloginerror', $e->errorcode);
        }

        // Test user with full capabilities.
        $this->setUser($this->student);

        // Create what we expect to be returned. We only test a basic case because deep testing is already done
        // in the tests for wikilv_get_visible_subwikilvs.
        $expectedsubwikilvs = array();
        $expectedsubwikilv = array(
                'id' => $this->firstpage->subwikilvid,
                'wikilvid' => $this->wikilv->id,
                'groupid' => 0,
                'userid' => 0,
                'canedit' => true
            );
        $expectedsubwikilvs[] = $expectedsubwikilv;

        $result = mod_wikilv_external::get_subwikilvs($this->wikilv->id);
        $result = external_api::clean_returnvalue(mod_wikilv_external::get_subwikilvs_returns(), $result);
        $this->assertEquals($expectedsubwikilvs, $result['subwikilvs']);
        $this->assertCount(0, $result['warnings']);

        // Test user with no capabilities.
        // We need a explicit prohibit since this capability is allowed for students by default.
        assign_capability('mod/wikilv:viewpage', CAP_PROHIBIT, $this->studentrole->id, $this->context->id);
        accesslib_clear_all_caches_for_unit_testing();

        try {
            mod_wikilv_external::get_subwikilvs($this->wikilv->id);
            $this->fail('Exception expected due to missing capability.');
        } catch (moodle_exception $e) {
            $this->assertEquals('nopermissions', $e->errorcode);
        }

    }

    /**
     * Test get_subwikilv_pages using an invalid wikilv instance.
     */
    public function test_get_subwikilv_pages_invalid_instance() {
        $this->setExpectedException('moodle_exception');
        mod_wikilv_external::get_subwikilv_pages(0);
    }

    /**
     * Test get_subwikilv_pages using a user not enrolled in the course.
     */
    public function test_get_subwikilv_pages_unenrolled_user() {
        // Create and use the user.
        $usernotenrolled = self::getDataGenerator()->create_user();
        $this->setUser($usernotenrolled);

        $this->setExpectedException('require_login_exception');
        mod_wikilv_external::get_subwikilv_pages($this->wikilv->id);
    }

    /**
     * Test get_subwikilv_pages using a hidden wikilv as student.
     */
    public function test_get_subwikilv_pages_hidden_wikilv_as_student() {
        // Create a hidden wikilv and try to get the list of pages.
        $hiddenwikilv = $this->getDataGenerator()->create_module('wikilv',
                            array('course' => $this->course->id, 'visible' => false));

        $this->setUser($this->student);
        $this->setExpectedException('require_login_exception');
        mod_wikilv_external::get_subwikilv_pages($hiddenwikilv->id);
    }

    /**
     * Test get_subwikilv_pages without the viewpage capability.
     */
    public function test_get_subwikilv_pages_without_viewpage_capability() {
        // Prohibit capability = mod/wikilv:viewpage on the course for students.
        $contextcourse = context_course::instance($this->course->id);
        assign_capability('mod/wikilv:viewpage', CAP_PROHIBIT, $this->studentrole->id, $contextcourse->id);
        accesslib_clear_all_caches_for_unit_testing();

        $this->setUser($this->student);
        $this->setExpectedException('moodle_exception');
        mod_wikilv_external::get_subwikilv_pages($this->wikilv->id);
    }

    /**
     * Test get_subwikilv_pages using an invalid userid.
     */
    public function test_get_subwikilv_pages_invalid_userid() {
        // Create an individual wikilv.
        $indwikilv = $this->getDataGenerator()->create_module('wikilv',
                                array('course' => $this->course->id, 'wikilvmode' => 'individual'));

        $this->setExpectedException('moodle_exception');
        mod_wikilv_external::get_subwikilv_pages($indwikilv->id, 0, -10);
    }

    /**
     * Test get_subwikilv_pages using an invalid groupid.
     */
    public function test_get_subwikilv_pages_invalid_groupid() {
        // Create testing data.
        $this->create_collaborative_wikilvs_with_groups();

        $this->setExpectedException('moodle_exception');
        mod_wikilv_external::get_subwikilv_pages($this->wikilvsep->id, -111);
    }

    /**
     * Test get_subwikilv_pages, check that a student can't see another user pages in an individual wikilv without groups.
     */
    public function test_get_subwikilv_pages_individual_student_see_other_user() {
        // Create an individual wikilv.
        $indwikilv = $this->getDataGenerator()->create_module('wikilv',
                                array('course' => $this->course->id, 'wikilvmode' => 'individual'));

        $this->setUser($this->student);
        $this->setExpectedException('moodle_exception');
        mod_wikilv_external::get_subwikilv_pages($indwikilv->id, 0, $this->teacher->id);
    }

    /**
     * Test get_subwikilv_pages, check that a student can't get the pages from another group in
     * a collaborative wikilv using separate groups.
     */
    public function test_get_subwikilv_pages_collaborative_separate_groups_student_see_other_group() {
        // Create testing data.
        $this->create_collaborative_wikilvs_with_groups();

        $this->setUser($this->student);
        $this->setExpectedException('moodle_exception');
        mod_wikilv_external::get_subwikilv_pages($this->wikilvsep->id, $this->group2->id);
    }

    /**
     * Test get_subwikilv_pages, check that a student can't get the pages from another group in
     * an individual wikilv using separate groups.
     */
    public function test_get_subwikilv_pages_individual_separate_groups_student_see_other_group() {
        // Create testing data.
        $this->create_individual_wikilvs_with_groups();

        $this->setUser($this->student);
        $this->setExpectedException('moodle_exception');
        mod_wikilv_external::get_subwikilv_pages($this->wikilvsepind->id, $this->group2->id, $this->teacher->id);
    }

    /**
     * Test get_subwikilv_pages, check that a student can't get the pages from all participants in
     * a collaborative wikilv using separate groups.
     */
    public function test_get_subwikilv_pages_collaborative_separate_groups_student_see_all_participants() {
        // Create testing data.
        $this->create_collaborative_wikilvs_with_groups();

        $this->setUser($this->student);
        $this->setExpectedException('moodle_exception');
        mod_wikilv_external::get_subwikilv_pages($this->wikilvsep->id, 0);
    }

    /**
     * Test get_subwikilv_pages, check that a student can't get the pages from all participants in
     * an individual wikilv using separate groups.
     */
    public function test_get_subwikilv_pages_individual_separate_groups_student_see_all_participants() {
        // Create testing data.
        $this->create_individual_wikilvs_with_groups();

        $this->setUser($this->student);
        $this->setExpectedException('moodle_exception');
        mod_wikilv_external::get_subwikilv_pages($this->wikilvsepind->id, 0, $this->teacher->id);
    }

    /**
     * Test get_subwikilv_pages without groups and collaborative wikilv.
     */
    public function test_get_subwikilv_pages_collaborative() {

        // Test user with full capabilities.
        $this->setUser($this->student);

        // Set expected result: first page.
        $expectedpages = array();
        $expectedfirstpage = (array) $this->firstpage;
        $expectedfirstpage['caneditpage'] = true; // No groups and students have 'mod/wikilv:editpage' capability.
        $expectedfirstpage['firstpage'] = true;
        $expectedfirstpage['contentformat'] = 1;
        $expectedpages[] = $expectedfirstpage;

        $result = mod_wikilv_external::get_subwikilv_pages($this->wikilv->id);
        $result = external_api::clean_returnvalue(mod_wikilv_external::get_subwikilv_pages_returns(), $result);
        $this->assertEquals($expectedpages, $result['pages']);

        // Check that groupid param is ignored since the wikilv isn't using groups.
        $result = mod_wikilv_external::get_subwikilv_pages($this->wikilv->id, 1234);
        $result = external_api::clean_returnvalue(mod_wikilv_external::get_subwikilv_pages_returns(), $result);
        $this->assertEquals($expectedpages, $result['pages']);

        // Check that userid param is ignored since the wikilv is collaborative.
        $result = mod_wikilv_external::get_subwikilv_pages($this->wikilv->id, 1234, 1234);
        $result = external_api::clean_returnvalue(mod_wikilv_external::get_subwikilv_pages_returns(), $result);
        $this->assertEquals($expectedpages, $result['pages']);

        // Add a new page to the wikilv and test again. We'll use a custom title so it's returned first if sorted by title.
        $newpage = $this->getDataGenerator()->get_plugin_generator('mod_wikilv')->create_page(
                                $this->wikilv, array('title' => 'AAA'));

        $expectednewpage = (array) $newpage;
        $expectednewpage['caneditpage'] = true; // No groups and students have 'mod/wikilv:editpage' capability.
        $expectednewpage['firstpage'] = false;
        $expectednewpage['contentformat'] = 1;
        array_unshift($expectedpages, $expectednewpage); // Add page to the beginning since it orders by title by default.

        $result = mod_wikilv_external::get_subwikilv_pages($this->wikilv->id);
        $result = external_api::clean_returnvalue(mod_wikilv_external::get_subwikilv_pages_returns(), $result);
        $this->assertEquals($expectedpages, $result['pages']);

        // Now we'll order by ID. Since first page was created first it'll have a lower ID.
        $expectedpages = array($expectedfirstpage, $expectednewpage);
        $result = mod_wikilv_external::get_subwikilv_pages($this->wikilv->id, 0, 0, array('sortby' => 'id'));
        $result = external_api::clean_returnvalue(mod_wikilv_external::get_subwikilv_pages_returns(), $result);
        $this->assertEquals($expectedpages, $result['pages']);

        // Check that WS doesn't return page content if includecontent is false, it returns the size instead.
        foreach ($expectedpages as $i => $expectedpage) {
            if (function_exists('mb_strlen') && ((int)ini_get('mbstring.func_overload') & 2)) {
                $expectedpages[$i]['contentsize'] = mb_strlen($expectedpages[$i]['cachedcontent'], '8bit');
            } else {
                $expectedpages[$i]['contentsize'] = strlen($expectedpages[$i]['cachedcontent']);
            }
            unset($expectedpages[$i]['cachedcontent']);
            unset($expectedpages[$i]['contentformat']);
        }
        $result = mod_wikilv_external::get_subwikilv_pages($this->wikilv->id, 0, 0, array('sortby' => 'id', 'includecontent' => 0));
        $result = external_api::clean_returnvalue(mod_wikilv_external::get_subwikilv_pages_returns(), $result);
        $this->assertEquals($expectedpages, $result['pages']);
    }

    /**
     * Test get_subwikilv_pages without groups.
     */
    public function test_get_subwikilv_pages_individual() {

        // Create an individual wikilv to test userid param.
        $indwikilv = $this->getDataGenerator()->create_module('wikilv',
                                array('course' => $this->course->id, 'wikilvmode' => 'individual'));

        // Perform a request before creating any page to check that an empty array is returned if subwikilv doesn't exist.
        $result = mod_wikilv_external::get_subwikilv_pages($indwikilv->id);
        $result = external_api::clean_returnvalue(mod_wikilv_external::get_subwikilv_pages_returns(), $result);
        $this->assertEquals(array(), $result['pages']);

        // Create first pages as student and teacher.
        $this->setUser($this->student);
        $indfirstpagestudent = $this->getDataGenerator()->get_plugin_generator('mod_wikilv')->create_first_page($indwikilv);
        $this->setUser($this->teacher);
        $indfirstpageteacher = $this->getDataGenerator()->get_plugin_generator('mod_wikilv')->create_first_page($indwikilv);

        // Check that teacher can get his pages.
        $expectedteacherpage = (array) $indfirstpageteacher;
        $expectedteacherpage['caneditpage'] = true;
        $expectedteacherpage['firstpage'] = true;
        $expectedteacherpage['contentformat'] = 1;
        $expectedpages = array($expectedteacherpage);

        $result = mod_wikilv_external::get_subwikilv_pages($indwikilv->id, 0, $this->teacher->id);
        $result = external_api::clean_returnvalue(mod_wikilv_external::get_subwikilv_pages_returns(), $result);
        $this->assertEquals($expectedpages, $result['pages']);

        // Check that the teacher can see the student's pages.
        $expectedstudentpage = (array) $indfirstpagestudent;
        $expectedstudentpage['caneditpage'] = true;
        $expectedstudentpage['firstpage'] = true;
        $expectedstudentpage['contentformat'] = 1;
        $expectedpages = array($expectedstudentpage);

        $result = mod_wikilv_external::get_subwikilv_pages($indwikilv->id, 0, $this->student->id);
        $result = external_api::clean_returnvalue(mod_wikilv_external::get_subwikilv_pages_returns(), $result);
        $this->assertEquals($expectedpages, $result['pages']);

        // Now check that student can get his pages.
        $this->setUser($this->student);

        $result = mod_wikilv_external::get_subwikilv_pages($indwikilv->id, 0, $this->student->id);
        $result = external_api::clean_returnvalue(mod_wikilv_external::get_subwikilv_pages_returns(), $result);
        $this->assertEquals($expectedpages, $result['pages']);

        // Check that not using userid uses current user.
        $result = mod_wikilv_external::get_subwikilv_pages($indwikilv->id, 0);
        $result = external_api::clean_returnvalue(mod_wikilv_external::get_subwikilv_pages_returns(), $result);
        $this->assertEquals($expectedpages, $result['pages']);
    }

    /**
     * Test get_subwikilv_pages with groups and collaborative wikilvs.
     */
    public function test_get_subwikilv_pages_separate_groups_collaborative() {

        // Create testing data.
        $this->create_collaborative_wikilvs_with_groups();

        $this->setUser($this->student);

        // Try to get pages from a valid group in separate groups wikilv.

        $expectedpage = (array) $this->fpsepg1;
        $expectedpage['caneditpage'] = true; // User belongs to group and has 'mod/wikilv:editpage' capability.
        $expectedpage['firstpage'] = true;
        $expectedpage['contentformat'] = 1;
        $expectedpages = array($expectedpage);

        $result = mod_wikilv_external::get_subwikilv_pages($this->wikilvsep->id, $this->group1->id);
        $result = external_api::clean_returnvalue(mod_wikilv_external::get_subwikilv_pages_returns(), $result);
        $this->assertEquals($expectedpages, $result['pages']);

        // Let's check that not using groupid returns the same result (current group).
        $result = mod_wikilv_external::get_subwikilv_pages($this->wikilvsep->id);
        $result = external_api::clean_returnvalue(mod_wikilv_external::get_subwikilv_pages_returns(), $result);
        $this->assertEquals($expectedpages, $result['pages']);

        // Check that teacher can view a group pages without belonging to it.
        $this->setUser($this->teacher);
        $result = mod_wikilv_external::get_subwikilv_pages($this->wikilvsep->id, $this->group1->id);
        $result = external_api::clean_returnvalue(mod_wikilv_external::get_subwikilv_pages_returns(), $result);
        $this->assertEquals($expectedpages, $result['pages']);

        // Check that teacher can get the pages from all participants.
        $expectedpage = (array) $this->fpsepall;
        $expectedpage['caneditpage'] = true;
        $expectedpage['firstpage'] = true;
        $expectedpage['contentformat'] = 1;
        $expectedpages = array($expectedpage);

        $result = mod_wikilv_external::get_subwikilv_pages($this->wikilvsep->id, 0);
        $result = external_api::clean_returnvalue(mod_wikilv_external::get_subwikilv_pages_returns(), $result);
        $this->assertEquals($expectedpages, $result['pages']);
    }

    /**
     * Test get_subwikilv_pages with groups and collaborative wikilvs.
     */
    public function test_get_subwikilv_pages_visible_groups_collaborative() {

        // Create testing data.
        $this->create_collaborative_wikilvs_with_groups();

        $this->setUser($this->student);

        // Try to get pages from a valid group in visible groups wikilv.

        $expectedpage = (array) $this->fpvisg1;
        $expectedpage['caneditpage'] = true; // User belongs to group and has 'mod/wikilv:editpage' capability.
        $expectedpage['firstpage'] = true;
        $expectedpage['contentformat'] = 1;
        $expectedpages = array($expectedpage);

        $result = mod_wikilv_external::get_subwikilv_pages($this->wikilvvis->id, $this->group1->id);
        $result = external_api::clean_returnvalue(mod_wikilv_external::get_subwikilv_pages_returns(), $result);
        $this->assertEquals($expectedpages, $result['pages']);

        // Check that with visible groups a student can get the pages of groups he doesn't belong to.
        $expectedpage = (array) $this->fpvisg2;
        $expectedpage['caneditpage'] = false; // User doesn't belong to group so he can't edit the page.
        $expectedpage['firstpage'] = true;
        $expectedpage['contentformat'] = 1;
        $expectedpages = array($expectedpage);

        $result = mod_wikilv_external::get_subwikilv_pages($this->wikilvvis->id, $this->group2->id);
        $result = external_api::clean_returnvalue(mod_wikilv_external::get_subwikilv_pages_returns(), $result);
        $this->assertEquals($expectedpages, $result['pages']);

        // Check that with visible groups a student can get the pages of all participants.
        $expectedpage = (array) $this->fpvisall;
        $expectedpage['caneditpage'] = false;
        $expectedpage['firstpage'] = true;
        $expectedpage['contentformat'] = 1;
        $expectedpages = array($expectedpage);

        $result = mod_wikilv_external::get_subwikilv_pages($this->wikilvvis->id, 0);
        $result = external_api::clean_returnvalue(mod_wikilv_external::get_subwikilv_pages_returns(), $result);
        $this->assertEquals($expectedpages, $result['pages']);
    }

    /**
     * Test get_subwikilv_pages with groups and individual wikilvs.
     */
    public function test_get_subwikilv_pages_separate_groups_individual() {

        // Create testing data.
        $this->create_individual_wikilvs_with_groups();

        $this->setUser($this->student);

        // Check that student can retrieve his pages from separate wikilv.
        $expectedpage = (array) $this->fpsepg1indstu;
        $expectedpage['caneditpage'] = true;
        $expectedpage['firstpage'] = true;
        $expectedpage['contentformat'] = 1;
        $expectedpages = array($expectedpage);

        $result = mod_wikilv_external::get_subwikilv_pages($this->wikilvsepind->id, $this->group1->id, $this->student->id);
        $result = external_api::clean_returnvalue(mod_wikilv_external::get_subwikilv_pages_returns(), $result);
        $this->assertEquals($expectedpages, $result['pages']);

        // Check that not using userid uses current user.
        $result = mod_wikilv_external::get_subwikilv_pages($this->wikilvsepind->id, $this->group1->id);
        $result = external_api::clean_returnvalue(mod_wikilv_external::get_subwikilv_pages_returns(), $result);
        $this->assertEquals($expectedpages, $result['pages']);

        // Check that the teacher can see the student pages.
        $this->setUser($this->teacher);
        $result = mod_wikilv_external::get_subwikilv_pages($this->wikilvsepind->id, $this->group1->id, $this->student->id);
        $result = external_api::clean_returnvalue(mod_wikilv_external::get_subwikilv_pages_returns(), $result);
        $this->assertEquals($expectedpages, $result['pages']);

        // Check that a student can see pages from another user that belongs to his groups.
        $this->setUser($this->student);
        $expectedpage = (array) $this->fpsepg1indstu2;
        $expectedpage['caneditpage'] = false;
        $expectedpage['firstpage'] = true;
        $expectedpage['contentformat'] = 1;
        $expectedpages = array($expectedpage);

        $result = mod_wikilv_external::get_subwikilv_pages($this->wikilvsepind->id, $this->group1->id, $this->student2->id);
        $result = external_api::clean_returnvalue(mod_wikilv_external::get_subwikilv_pages_returns(), $result);
        $this->assertEquals($expectedpages, $result['pages']);
    }

    /**
     * Test get_subwikilv_pages with groups and individual wikilvs.
     */
    public function test_get_subwikilv_pages_visible_groups_individual() {

        // Create testing data.
        $this->create_individual_wikilvs_with_groups();

        $this->setUser($this->student);

        // Check that student can retrieve his pages from visible wikilv.
        $expectedpage = (array) $this->fpvisg1indstu;
        $expectedpage['caneditpage'] = true;
        $expectedpage['firstpage'] = true;
        $expectedpage['contentformat'] = 1;
        $expectedpages = array($expectedpage);

        $result = mod_wikilv_external::get_subwikilv_pages($this->wikilvvisind->id, $this->group1->id, $this->student->id);
        $result = external_api::clean_returnvalue(mod_wikilv_external::get_subwikilv_pages_returns(), $result);
        $this->assertEquals($expectedpages, $result['pages']);

        // Check that student can see teacher pages in visible groups, even if the user doesn't belong to the group.
        $expectedpage = (array) $this->fpvisg2indt;
        $expectedpage['caneditpage'] = false;
        $expectedpage['firstpage'] = true;
        $expectedpage['contentformat'] = 1;
        $expectedpages = array($expectedpage);

        $result = mod_wikilv_external::get_subwikilv_pages($this->wikilvvisind->id, $this->group2->id, $this->teacher->id);
        $result = external_api::clean_returnvalue(mod_wikilv_external::get_subwikilv_pages_returns(), $result);
        $this->assertEquals($expectedpages, $result['pages']);

        // Check that with visible groups a student can get the pages of all participants.
        $expectedpage = (array) $this->fpvisallindt;
        $expectedpage['caneditpage'] = false;
        $expectedpage['firstpage'] = true;
        $expectedpage['contentformat'] = 1;
        $expectedpages = array($expectedpage);

        $result = mod_wikilv_external::get_subwikilv_pages($this->wikilvvisind->id, 0, $this->teacher->id);
        $result = external_api::clean_returnvalue(mod_wikilv_external::get_subwikilv_pages_returns(), $result);
        $this->assertEquals($expectedpages, $result['pages']);
    }

    /**
     * Test get_page_contents using an invalid pageid.
     */
    public function test_get_page_contents_invalid_pageid() {
        $this->setExpectedException('moodle_exception');
        mod_wikilv_external::get_page_contents(0);
    }

    /**
     * Test get_page_contents using a user not enrolled in the course.
     */
    public function test_get_page_contents_unenrolled_user() {
        // Create and use the user.
        $usernotenrolled = self::getDataGenerator()->create_user();
        $this->setUser($usernotenrolled);

        $this->setExpectedException('require_login_exception');
        mod_wikilv_external::get_page_contents($this->firstpage->id);
    }

    /**
     * Test get_page_contents using a hidden wikilv as student.
     */
    public function test_get_page_contents_hidden_wikilv_as_student() {
        // Create a hidden wikilv and try to get a page contents.
        $hiddenwikilv = $this->getDataGenerator()->create_module('wikilv',
                            array('course' => $this->course->id, 'visible' => false));
        $hiddenpage = $this->getDataGenerator()->get_plugin_generator('mod_wikilv')->create_page($hiddenwikilv);

        $this->setUser($this->student);
        $this->setExpectedException('require_login_exception');
        mod_wikilv_external::get_page_contents($hiddenpage->id);
    }

    /**
     * Test get_page_contents without the viewpage capability.
     */
    public function test_get_page_contents_without_viewpage_capability() {
        // Prohibit capability = mod/wikilv:viewpage on the course for students.
        $contextcourse = context_course::instance($this->course->id);
        assign_capability('mod/wikilv:viewpage', CAP_PROHIBIT, $this->studentrole->id, $contextcourse->id);
        accesslib_clear_all_caches_for_unit_testing();

        $this->setUser($this->student);
        $this->setExpectedException('moodle_exception');
        mod_wikilv_external::get_page_contents($this->firstpage->id);
    }

    /**
     * Test get_page_contents, check that a student can't get a page from another group when
     * using separate groups.
     */
    public function test_get_page_contents_separate_groups_student_see_other_group() {
        // Create testing data.
        $this->create_individual_wikilvs_with_groups();

        $this->setUser($this->student);
        $this->setExpectedException('moodle_exception');
        mod_wikilv_external::get_page_contents($this->fpsepg2indt->id);
    }

    /**
     * Test get_page_contents without groups. We won't test all the possible cases because that's already
     * done in the tests for get_subwikilv_pages.
     */
    public function test_get_page_contents() {

        // Test user with full capabilities.
        $this->setUser($this->student);

        // Set expected result: first page.
        $expectedpage = array(
            'id' => $this->firstpage->id,
            'wikilvid' => $this->wikilv->id,
            'subwikilvid' => $this->firstpage->subwikilvid,
            'groupid' => 0, // No groups.
            'userid' => 0, // Collaborative.
            'title' => $this->firstpage->title,
            'cachedcontent' => $this->firstpage->cachedcontent,
            'contentformat' => 1,
            'caneditpage' => true
        );

        $result = mod_wikilv_external::get_page_contents($this->firstpage->id);
        $result = external_api::clean_returnvalue(mod_wikilv_external::get_page_contents_returns(), $result);
        $this->assertEquals($expectedpage, $result['page']);

        // Add a new page to the wikilv and test with it.
        $newpage = $this->getDataGenerator()->get_plugin_generator('mod_wikilv')->create_page($this->wikilv);

        $expectedpage['id'] = $newpage->id;
        $expectedpage['title'] = $newpage->title;
        $expectedpage['cachedcontent'] = $newpage->cachedcontent;

        $result = mod_wikilv_external::get_page_contents($newpage->id);
        $result = external_api::clean_returnvalue(mod_wikilv_external::get_page_contents_returns(), $result);
        $this->assertEquals($expectedpage, $result['page']);
    }

    /**
     * Test get_page_contents with groups. We won't test all the possible cases because that's already
     * done in the tests for get_subwikilv_pages.
     */
    public function test_get_page_contents_with_groups() {

        // Create testing data.
        $this->create_individual_wikilvs_with_groups();

        // Try to get page from a valid group in separate groups wikilv.
        $this->setUser($this->student);

        $expectedfpsepg1indstu = array(
            'id' => $this->fpsepg1indstu->id,
            'wikilvid' => $this->wikilvsepind->id,
            'subwikilvid' => $this->fpsepg1indstu->subwikilvid,
            'groupid' => $this->group1->id,
            'userid' => $this->student->id,
            'title' => $this->fpsepg1indstu->title,
            'cachedcontent' => $this->fpsepg1indstu->cachedcontent,
            'contentformat' => 1,
            'caneditpage' => true
        );

        $result = mod_wikilv_external::get_page_contents($this->fpsepg1indstu->id);
        $result = external_api::clean_returnvalue(mod_wikilv_external::get_page_contents_returns(), $result);
        $this->assertEquals($expectedfpsepg1indstu, $result['page']);

        // Check that teacher can view a group pages without belonging to it.
        $this->setUser($this->teacher);
        $result = mod_wikilv_external::get_page_contents($this->fpsepg1indstu->id);
        $result = external_api::clean_returnvalue(mod_wikilv_external::get_page_contents_returns(), $result);
        $this->assertEquals($expectedfpsepg1indstu, $result['page']);
    }

    /**
     * Test get_subwikilv_files using a wikilv without files.
     */
    public function test_get_subwikilv_files_no_files() {
        $result = mod_wikilv_external::get_subwikilv_files($this->wikilv->id);
        $result = external_api::clean_returnvalue(mod_wikilv_external::get_subwikilv_files_returns(), $result);
        $this->assertCount(0, $result['files']);
        $this->assertCount(0, $result['warnings']);
    }

    /**
     * Test get_subwikilv_files, check that a student can't get files from another group's subwikilv when
     * using separate groups.
     */
    public function test_get_subwikilv_files_separate_groups_student_see_other_group() {
        // Create testing data.
        $this->create_collaborative_wikilvs_with_groups();

        $this->setUser($this->student);
        $this->setExpectedException('moodle_exception');
        mod_wikilv_external::get_subwikilv_files($this->wikilvsep->id, $this->group2->id);
    }

    /**
     * Test get_subwikilv_files using a collaborative wikilv without groups.
     */
    public function test_get_subwikilv_files_collaborative_no_groups() {
        $this->setUser($this->student);

        // Add a file as subwikilv attachment.
        $fs = get_file_storage();
        $file = array('component' => 'mod_wikilv', 'filearea' => 'attachments',
                'contextid' => $this->context->id, 'itemid' => $this->firstpage->subwikilvid,
                'filename' => 'image.jpg', 'filepath' => '/', 'timemodified' => time());
        $content = 'IMAGE';
        $fs->create_file_from_string($file, $content);

        $expectedfile = array(
            'filename' => $file['filename'],
            'filepath' => $file['filepath'],
            'mimetype' => 'image/jpeg',
            'filesize' => strlen($content),
            'timemodified' => $file['timemodified'],
            'fileurl' => moodle_url::make_webservice_pluginfile_url($file['contextid'], $file['component'],
                            $file['filearea'], $file['itemid'], $file['filepath'], $file['filename']),
        );

        // Call the WS and check that it returns this file.
        $result = mod_wikilv_external::get_subwikilv_files($this->wikilv->id);
        $result = external_api::clean_returnvalue(mod_wikilv_external::get_subwikilv_files_returns(), $result);
        $this->assertCount(1, $result['files']);
        $this->assertEquals($expectedfile, $result['files'][0]);

        // Now add another file to the same subwikilv.
        $file['filename'] = 'Another image.jpg';
        $file['timemodified'] = time();
        $content = 'ANOTHER IMAGE';
        $fs->create_file_from_string($file, $content);

        $expectedfile['filename'] = $file['filename'];
        $expectedfile['timemodified'] = $file['timemodified'];
        $expectedfile['filesize'] = strlen($content);
        $expectedfile['fileurl'] = moodle_url::make_webservice_pluginfile_url($file['contextid'], $file['component'],
                            $file['filearea'], $file['itemid'], $file['filepath'], $file['filename']);

        // Call the WS and check that it returns both files file.
        $result = mod_wikilv_external::get_subwikilv_files($this->wikilv->id);
        $result = external_api::clean_returnvalue(mod_wikilv_external::get_subwikilv_files_returns(), $result);
        $this->assertCount(2, $result['files']);
        // The new file is returned first because they're returned in alphabetical order.
        $this->assertEquals($expectedfile, $result['files'][0]);
    }

    /**
     * Test get_subwikilv_files using an individual wikilv with visible groups.
     */
    public function test_get_subwikilv_files_visible_groups_individual() {
        // Create testing data.
        $this->create_individual_wikilvs_with_groups();

        $this->setUser($this->student);

        // Add a file as subwikilv attachment in the student group 1 subwikilv.
        $fs = get_file_storage();
        $contextwikilv = context_module::instance($this->wikilvvisind->cmid);
        $file = array('component' => 'mod_wikilv', 'filearea' => 'attachments',
                'contextid' => $contextwikilv->id, 'itemid' => $this->fpvisg1indstu->subwikilvid,
                'filename' => 'image.jpg', 'filepath' => '/', 'timemodified' => time());
        $content = 'IMAGE';
        $fs->create_file_from_string($file, $content);

        $expectedfile = array(
            'filename' => $file['filename'],
            'filepath' => $file['filepath'],
            'mimetype' => 'image/jpeg',
            'filesize' => strlen($content),
            'timemodified' => $file['timemodified'],
            'fileurl' => moodle_url::make_webservice_pluginfile_url($file['contextid'], $file['component'],
                            $file['filearea'], $file['itemid'], $file['filepath'], $file['filename']),
        );

        // Call the WS and check that it returns this file.
        $result = mod_wikilv_external::get_subwikilv_files($this->wikilvvisind->id, $this->group1->id);
        $result = external_api::clean_returnvalue(mod_wikilv_external::get_subwikilv_files_returns(), $result);
        $this->assertCount(1, $result['files']);
        $this->assertEquals($expectedfile, $result['files'][0]);

        // Now check that a teacher can see it too.
        $this->setUser($this->teacher);
        $result = mod_wikilv_external::get_subwikilv_files($this->wikilvvisind->id, $this->group1->id, $this->student->id);
        $result = external_api::clean_returnvalue(mod_wikilv_external::get_subwikilv_files_returns(), $result);
        $this->assertCount(1, $result['files']);
        $this->assertEquals($expectedfile, $result['files'][0]);
    }


    /**
     * Test get_page_for_editing. We won't test all the possible cases because that's already
     * done in the tests for wikilv_parser_proxy::get_section.
     */
    public function test_get_page_for_editing() {

        $this->create_individual_wikilvs_with_groups();

        // We add a <span> in the first title to verify the WS works sending HTML in section.
        $sectioncontent = '<h1><span>Title1</span></h1>Text inside section';
        $pagecontent = $sectioncontent.'<h1>Title2</h1>Text inside section';
        $newpage = $this->getDataGenerator()->get_plugin_generator('mod_wikilv')->create_page(
                                $this->wikilv, array('content' => $pagecontent));

        // Test user with full capabilities.
        $this->setUser($this->student);

        // Set expected result: Full Page content.
        $expected = array(
            'content' => $pagecontent,
            'contentformat' => 'html',
            'version' => '1'
        );

        $result = mod_wikilv_external::get_page_for_editing($newpage->id);
        $result = external_api::clean_returnvalue(mod_wikilv_external::get_page_for_editing_returns(), $result);
        $this->assertEquals($expected, $result['pagesection']);

        // Set expected result: Section Page content.
        $expected = array(
            'content' => $sectioncontent,
            'contentformat' => 'html',
            'version' => '1'
        );

        $result = mod_wikilv_external::get_page_for_editing($newpage->id, '<span>Title1</span>');
        $result = external_api::clean_returnvalue(mod_wikilv_external::get_page_for_editing_returns(), $result);
        $this->assertEquals($expected, $result['pagesection']);
    }

    /**
     * Test new_page. We won't test all the possible cases because that's already
     * done in the tests for wikilv_create_page.
     */
    public function test_new_page() {

        $this->create_individual_wikilvs_with_groups();

        $sectioncontent = '<h1>Title1</h1>Text inside section';
        $pagecontent = $sectioncontent.'<h1>Title2</h1>Text inside section';
        $pagetitle = 'Page Title';

        // Test user with full capabilities.
        $this->setUser($this->student);

        // Test on existing subwikilv.
        $result = mod_wikilv_external::new_page($pagetitle, $pagecontent, 'html', $this->fpsepg1indstu->subwikilvid);
        $result = external_api::clean_returnvalue(mod_wikilv_external::new_page_returns(), $result);
        $this->assertInternalType('int', $result['pageid']);

        $version = wikilv_get_current_version($result['pageid']);
        $this->assertEquals($pagecontent, $version->content);
        $this->assertEquals('html', $version->contentformat);

        $page = wikilv_get_page($result['pageid']);
        $this->assertEquals($pagetitle, $page->title);

        // Test existing page creation.
        try {
            mod_wikilv_external::new_page($pagetitle, $pagecontent, 'html', $this->fpsepg1indstu->subwikilvid);
            $this->fail('Exception expected due to creation of an existing page.');
        } catch (moodle_exception $e) {
            $this->assertEquals('pageexists', $e->errorcode);
        }

        // Test on non existing subwikilv. Add student to group2 to have a new subwikilv to be created.
        $this->getDataGenerator()->create_group_member(array('userid' => $this->student->id, 'groupid' => $this->group2->id));
        $result = mod_wikilv_external::new_page($pagetitle, $pagecontent, 'html', null, $this->wikilvsepind->id, $this->student->id,
            $this->group2->id);
        $result = external_api::clean_returnvalue(mod_wikilv_external::new_page_returns(), $result);
        $this->assertInternalType('int', $result['pageid']);

        $version = wikilv_get_current_version($result['pageid']);
        $this->assertEquals($pagecontent, $version->content);
        $this->assertEquals('html', $version->contentformat);

        $page = wikilv_get_page($result['pageid']);
        $this->assertEquals($pagetitle, $page->title);

        $subwikilv = wikilv_get_subwikilv($page->subwikilvid);
        $expected = new StdClass();
        $expected->id = $subwikilv->id;
        $expected->wikilvid = $this->wikilvsepind->id;
        $expected->groupid = $this->group2->id;
        $expected->userid = $this->student->id;
        $this->assertEquals($expected, $subwikilv);

        // Check page creation for a user not in course.
        $this->studentnotincourse = self::getDataGenerator()->create_user();
        $this->anothercourse = $this->getDataGenerator()->create_course();
        $this->groupnotincourse = $this->getDataGenerator()->create_group(array('courseid' => $this->anothercourse->id));

        try {
            mod_wikilv_external::new_page($pagetitle, $pagecontent, 'html', null, $this->wikilvsepind->id,
                $this->studentnotincourse->id, $this->groupnotincourse->id);
            $this->fail('Exception expected due to creation of an invalid subwikilv creation.');
        } catch (moodle_exception $e) {
            $this->assertEquals('cannoteditpage', $e->errorcode);
        }

    }

    /**
     * Test edit_page. We won't test all the possible cases because that's already
     * done in the tests for wikilv_save_section / wikilv_save_page.
     */
    public function test_edit_page() {

        $this->create_individual_wikilvs_with_groups();

        // Test user with full capabilities.
        $this->setUser($this->student);

        $newpage = $this->getDataGenerator()->get_plugin_generator('mod_wikilv')->create_page($this->wikilvsepind,
            array('group' => $this->group1->id, 'content' => 'Test'));

        // Test edit whole page.
        // We add <span> in the titles to verify the WS works sending HTML in section.
        $sectioncontent = '<h1><span>Title1</span></h1>Text inside section';
        $newpagecontent = $sectioncontent.'<h1><span>Title2</span></h1>Text inside section';

        $result = mod_wikilv_external::edit_page($newpage->id, $newpagecontent);
        $result = external_api::clean_returnvalue(mod_wikilv_external::edit_page_returns(), $result);
        $this->assertInternalType('int', $result['pageid']);

        $version = wikilv_get_current_version($result['pageid']);
        $this->assertEquals($newpagecontent, $version->content);

        // Test edit section.
        $newsectioncontent = '<h1><span>Title2</span></h1>New test2';
        $section = '<span>Title2</span>';

        $result = mod_wikilv_external::edit_page($newpage->id, $newsectioncontent, $section);
        $result = external_api::clean_returnvalue(mod_wikilv_external::edit_page_returns(), $result);
        $this->assertInternalType('int', $result['pageid']);

        $expected = $sectioncontent . $newsectioncontent;

        $version = wikilv_get_current_version($result['pageid']);
        $this->assertEquals($expected, $version->content);

        // Test locked section.
        $newsectioncontent = '<h1><span>Title2</span></h1>New test2';
        $section = '<span>Title2</span>';

        try {
            // Using user 1 to avoid other users to edit.
            wikilv_set_lock($newpage->id, 1, $section, true);
            mod_wikilv_external::edit_page($newpage->id, $newsectioncontent, $section);
            $this->fail('Exception expected due to locked section');
        } catch (moodle_exception $e) {
            $this->assertEquals('pageislocked', $e->errorcode);
        }

        // Test edit non existing section.
        $newsectioncontent = '<h1>Title3</h1>New test3';
        $section = 'Title3';

        try {
            mod_wikilv_external::edit_page($newpage->id, $newsectioncontent, $section);
            $this->fail('Exception expected due to non existing section in the page.');
        } catch (moodle_exception $e) {
            $this->assertEquals('invalidsection', $e->errorcode);
        }

    }

}
