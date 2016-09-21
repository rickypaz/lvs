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
 * Unit tests for mod_wikilv lib
 *
 * @package    mod_wikilv
 * @category   external
 * @copyright  2015 Dani Palou <dani@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.1
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/wikilv/lib.php');
require_once($CFG->dirroot . '/mod/wikilv/locallib.php');
require_once($CFG->libdir . '/completionlib.php');

/**
 * Unit tests for mod_wikilv lib
 *
 * @package    mod_wikilv
 * @category   external
 * @copyright  2015 Dani Palou <dani@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.1
 */
class mod_wikilv_lib_testcase extends advanced_testcase {

    /**
     * Test wikilv_view.
     *
     * @return void
     */
    public function test_wikilv_view() {
        global $CFG;

        $CFG->enablecompletion = COMPLETION_ENABLED;
        $this->resetAfterTest();

        $this->setAdminUser();
        // Setup test data.
        $course = $this->getDataGenerator()->create_course(array('enablecompletion' => COMPLETION_ENABLED));
        $options = array('completion' => COMPLETION_TRACKING_AUTOMATIC, 'completionview' => COMPLETION_VIEW_REQUIRED);
        $wikilv = $this->getDataGenerator()->create_module('wikilv', array('course' => $course->id), $options);
        $context = context_module::instance($wikilv->cmid);
        $cm = get_coursemodule_from_instance('wikilv', $wikilv->id);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();

        wikilv_view($wikilv, $course, $cm, $context);

        $events = $sink->get_events();
        // 2 additional events thanks to completion.
        $this->assertCount(3, $events);
        $event = array_shift($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\mod_wikilv\event\course_module_viewed', $event);
        $this->assertEquals($context, $event->get_context());
        $moodleurl = new \moodle_url('/mod/wikilv/view.php', array('id' => $cm->id));
        $this->assertEquals($moodleurl, $event->get_url());
        $this->assertEventContextNotUsed($event);
        $this->assertNotEmpty($event->get_name());

        // Check completion status.
        $completion = new completion_info($course);
        $completiondata = $completion->get_data($cm);
        $this->assertEquals(1, $completiondata->completionstate);

    }

    /**
     * Test wikilv_page_view.
     *
     * @return void
     */
    public function test_wikilv_page_view() {
        global $CFG;

        $CFG->enablecompletion = COMPLETION_ENABLED;
        $this->resetAfterTest();

        $this->setAdminUser();
        // Setup test data.
        $course = $this->getDataGenerator()->create_course(array('enablecompletion' => COMPLETION_ENABLED));
        $options = array('completion' => COMPLETION_TRACKING_AUTOMATIC, 'completionview' => COMPLETION_VIEW_REQUIRED);
        $wikilv = $this->getDataGenerator()->create_module('wikilv', array('course' => $course->id), $options);
        $context = context_module::instance($wikilv->cmid);
        $cm = get_coursemodule_from_instance('wikilv', $wikilv->id);
        $firstpage = $this->getDataGenerator()->get_plugin_generator('mod_wikilv')->create_first_page($wikilv);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();

        wikilv_page_view($wikilv, $firstpage, $course, $cm, $context);

        $events = $sink->get_events();
        // 2 additional events thanks to completion.
        $this->assertCount(3, $events);
        $event = array_shift($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\mod_wikilv\event\page_viewed', $event);
        $this->assertEquals($context, $event->get_context());
        $pageurl = new \moodle_url('/mod/wikilv/view.php', array('pageid' => $firstpage->id));
        $this->assertEquals($pageurl, $event->get_url());
        $this->assertEventContextNotUsed($event);
        $this->assertNotEmpty($event->get_name());

        // Check completion status.
        $completion = new completion_info($course);
        $completiondata = $completion->get_data($cm);
        $this->assertEquals(1, $completiondata->completionstate);

    }

    /**
     * Test wikilv_user_can_edit without groups.
     *
     * @return void
     */
    public function test_wikilv_user_can_edit() {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        // Setup test data.
        $course = $this->getDataGenerator()->create_course();
        $indwikilv = $this->getDataGenerator()->create_module('wikilv', array('course' => $course->id, 'wikilvmode' => 'individual'));
        $colwikilv = $this->getDataGenerator()->create_module('wikilv', array('course' => $course->id, 'wikilvmode' => 'collaborative'));

        // Create users.
        $student = self::getDataGenerator()->create_user();
        $teacher = self::getDataGenerator()->create_user();

        // Users enrolments.
        $studentrole = $DB->get_record('role', array('shortname' => 'student'));
        $teacherrole = $DB->get_record('role', array('shortname' => 'editingteacher'));
        $this->getDataGenerator()->enrol_user($student->id, $course->id, $studentrole->id, 'manual');
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, $teacherrole->id, 'manual');

        // Simulate collaborative subwikilv.
        $swcol = new stdClass();
        $swcol->id = -1;
        $swcol->wikilvid = $colwikilv->id;
        $swcol->groupid = 0;
        $swcol->userid = 0;

        // Simulate individual subwikilvs (1 per user).
        $swindstudent = clone($swcol);
        $swindstudent->wikilvid = $indwikilv->id;
        $swindstudent->userid = $student->id;

        $swindteacher = clone($swindstudent);
        $swindteacher->userid = $teacher->id;

        $this->setUser($student);

        // Check that the student can edit the collaborative subwikilv.
        $this->assertTrue(wikilv_user_can_edit($swcol));

        // Check that the student can edit his individual subwikilv.
        $this->assertTrue(wikilv_user_can_edit($swindstudent));

        // Check that the student cannot edit teacher's individual subwikilv.
        $this->assertFalse(wikilv_user_can_edit($swindteacher));

        // Now test as a teacher.
        $this->setUser($teacher);

        // Check that the teacher can edit the collaborative subwikilv.
        $this->assertTrue(wikilv_user_can_edit($swcol));

        // Check that the teacher can edit his individual subwikilv.
        $this->assertTrue(wikilv_user_can_edit($swindteacher));

        // Check that the teacher can edit student's individual subwikilv.
        $this->assertTrue(wikilv_user_can_edit($swindstudent));

    }

    /**
     * Test wikilv_user_can_edit using collaborative wikilvs with groups.
     *
     * @return void
     */
    public function test_wikilv_user_can_edit_with_groups_collaborative() {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        // Setup test data.
        $course = $this->getDataGenerator()->create_course();
        $wikilvsepcol = $this->getDataGenerator()->create_module('wikilv', array('course' => $course->id,
                                                        'groupmode' => SEPARATEGROUPS, 'wikilvmode' => 'collaborative'));
        $wikilvviscol = $this->getDataGenerator()->create_module('wikilv', array('course' => $course->id,
                                                        'groupmode' => VISIBLEGROUPS, 'wikilvmode' => 'collaborative'));

        // Create users.
        $student = self::getDataGenerator()->create_user();
        $student2 = self::getDataGenerator()->create_user();
        $teacher = self::getDataGenerator()->create_user();

        // Users enrolments.
        $studentrole = $DB->get_record('role', array('shortname' => 'student'));
        $teacherrole = $DB->get_record('role', array('shortname' => 'editingteacher'));
        $this->getDataGenerator()->enrol_user($student->id, $course->id, $studentrole->id, 'manual');
        $this->getDataGenerator()->enrol_user($student2->id, $course->id, $studentrole->id, 'manual');
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, $teacherrole->id, 'manual');

        // Create groups.
        $group1 = $this->getDataGenerator()->create_group(array('courseid' => $course->id));
        $this->getDataGenerator()->create_group_member(array('userid' => $student->id, 'groupid' => $group1->id));
        $this->getDataGenerator()->create_group_member(array('userid' => $student2->id, 'groupid' => $group1->id));
        $group2 = $this->getDataGenerator()->create_group(array('courseid' => $course->id));
        $this->getDataGenerator()->create_group_member(array('userid' => $student2->id, 'groupid' => $group2->id));

        // Simulate all the possible subwikilvs.
        // Subwikilvs in collaborative wikilvs: 1 subwikilv per group + 1 subwikilv for all participants.
        $swsepcolg1 = new stdClass();
        $swsepcolg1->id = -1;
        $swsepcolg1->wikilvid = $wikilvsepcol->id;
        $swsepcolg1->groupid = $group1->id;
        $swsepcolg1->userid = 0;

        $swsepcolg2 = clone($swsepcolg1);
        $swsepcolg2->groupid = $group2->id;

        $swsepcolallparts = clone($swsepcolg1); // All participants.
        $swsepcolallparts->groupid = 0;

        $swviscolg1 = clone($swsepcolg1);
        $swviscolg1->wikilvid = $wikilvviscol->id;

        $swviscolg2 = clone($swviscolg1);
        $swviscolg2->groupid = $group2->id;

        $swviscolallparts = clone($swviscolg1); // All participants.
        $swviscolallparts->groupid = 0;

        $this->setUser($student);

        // Check that the student can edit his group's subwikilv both in separate and visible groups.
        $this->assertTrue(wikilv_user_can_edit($swsepcolg1));
        $this->assertTrue(wikilv_user_can_edit($swviscolg1));

        // Check that the student cannot edit subwikilv from group 2 both in separate and visible groups.
        $this->assertFalse(wikilv_user_can_edit($swsepcolg2));
        $this->assertFalse(wikilv_user_can_edit($swviscolg2));

        // Now test as student 2.
        $this->setUser($student2);

        // Check that the student 2 can edit subwikilvs from both groups both in separate and visible groups.
        $this->assertTrue(wikilv_user_can_edit($swsepcolg1));
        $this->assertTrue(wikilv_user_can_edit($swviscolg1));
        $this->assertTrue(wikilv_user_can_edit($swsepcolg2));
        $this->assertTrue(wikilv_user_can_edit($swviscolg2));

        // Check that the student 2 cannot edit subwikilvs from all participants.
        $this->assertFalse(wikilv_user_can_edit($swsepcolallparts));
        $this->assertFalse(wikilv_user_can_edit($swviscolallparts));

        // Now test it as a teacher.
        $this->setUser($teacher);

        // Check that teacher can edit all subwikilvs.
        $this->assertTrue(wikilv_user_can_edit($swsepcolg1));
        $this->assertTrue(wikilv_user_can_edit($swviscolg1));
        $this->assertTrue(wikilv_user_can_edit($swsepcolg2));
        $this->assertTrue(wikilv_user_can_edit($swviscolg2));
        $this->assertTrue(wikilv_user_can_edit($swsepcolallparts));
        $this->assertTrue(wikilv_user_can_edit($swviscolallparts));
    }

    /**
     * Test wikilv_user_can_edit using individual wikilvs with groups.
     *
     * @return void
     */
    public function test_wikilv_user_can_edit_with_groups_individual() {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        // Setup test data.
        $course = $this->getDataGenerator()->create_course();
        $wikilvsepind = $this->getDataGenerator()->create_module('wikilv', array('course' => $course->id,
                                                        'groupmode' => SEPARATEGROUPS, 'wikilvmode' => 'individual'));
        $wikilvvisind = $this->getDataGenerator()->create_module('wikilv', array('course' => $course->id,
                                                        'groupmode' => VISIBLEGROUPS, 'wikilvmode' => 'individual'));

        // Create users.
        $student = self::getDataGenerator()->create_user();
        $student2 = self::getDataGenerator()->create_user();
        $teacher = self::getDataGenerator()->create_user();

        // Users enrolments.
        $studentrole = $DB->get_record('role', array('shortname' => 'student'));
        $teacherrole = $DB->get_record('role', array('shortname' => 'editingteacher'));
        $this->getDataGenerator()->enrol_user($student->id, $course->id, $studentrole->id, 'manual');
        $this->getDataGenerator()->enrol_user($student2->id, $course->id, $studentrole->id, 'manual');
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, $teacherrole->id, 'manual');

        // Create groups.
        $group1 = $this->getDataGenerator()->create_group(array('courseid' => $course->id));
        $this->getDataGenerator()->create_group_member(array('userid' => $student->id, 'groupid' => $group1->id));
        $this->getDataGenerator()->create_group_member(array('userid' => $student2->id, 'groupid' => $group1->id));
        $group2 = $this->getDataGenerator()->create_group(array('courseid' => $course->id));
        $this->getDataGenerator()->create_group_member(array('userid' => $student2->id, 'groupid' => $group2->id));

        // Simulate all the possible subwikilvs.
        // Subwikilvs in collaborative wikilvs: 1 subwikilv per group + 1 subwikilv for all participants.
        $swsepindg1s1 = new stdClass();
        $swsepindg1s1->id = -1;
        $swsepindg1s1->wikilvid = $wikilvsepind->id;
        $swsepindg1s1->groupid = $group1->id;
        $swsepindg1s1->userid = $student->id;

        $swsepindg1s2 = clone($swsepindg1s1);
        $swsepindg1s2->userid = $student2->id;

        $swsepindg2s2 = clone($swsepindg1s2);
        $swsepindg2s2->groupid = $group2->id;

        $swsepindteacher = clone($swsepindg1s1);
        $swsepindteacher->userid = $teacher->id;
        $swsepindteacher->groupid = 0;

        $swvisindg1s1 = clone($swsepindg1s1);
        $swvisindg1s1->wikilvid = $wikilvvisind->id;

        $swvisindg1s2 = clone($swvisindg1s1);
        $swvisindg1s2->userid = $student2->id;

        $swvisindg2s2 = clone($swvisindg1s2);
        $swvisindg2s2->groupid = $group2->id;

        $swvisindteacher = clone($swvisindg1s1);
        $swvisindteacher->userid = $teacher->id;
        $swvisindteacher->groupid = 0;

        $this->setUser($student);

        // Check that the student can edit his subwikilv both in separate and visible groups.
        $this->assertTrue(wikilv_user_can_edit($swsepindg1s1));
        $this->assertTrue(wikilv_user_can_edit($swvisindg1s1));

        // Check that the student cannot edit subwikilvs from another user even if he belongs to his group.
        $this->assertFalse(wikilv_user_can_edit($swsepindg1s2));
        $this->assertFalse(wikilv_user_can_edit($swvisindg1s2));

        // Now test as student 2.
        $this->setUser($student2);

        // Check that the student 2 can edit his subwikilvs from both groups both in separate and visible groups.
        $this->assertTrue(wikilv_user_can_edit($swsepindg1s2));
        $this->assertTrue(wikilv_user_can_edit($swvisindg1s2));
        $this->assertTrue(wikilv_user_can_edit($swsepindg2s2));
        $this->assertTrue(wikilv_user_can_edit($swvisindg2s2));

        // Now test it as a teacher.
        $this->setUser($teacher);

        // Check that teacher can edit all subwikilvs.
        $this->assertTrue(wikilv_user_can_edit($swsepindg1s1));
        $this->assertTrue(wikilv_user_can_edit($swsepindg1s2));
        $this->assertTrue(wikilv_user_can_edit($swsepindg2s2));
        $this->assertTrue(wikilv_user_can_edit($swsepindteacher));
        $this->assertTrue(wikilv_user_can_edit($swvisindg1s1));
        $this->assertTrue(wikilv_user_can_edit($swvisindg1s2));
        $this->assertTrue(wikilv_user_can_edit($swvisindg2s2));
        $this->assertTrue(wikilv_user_can_edit($swvisindteacher));
    }

    /**
     * Test wikilv_get_visible_subwikilvs without groups.
     *
     * @return void
     */
    public function test_wikilv_get_visible_subwikilvs_without_groups() {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        // Setup test data.
        $course = $this->getDataGenerator()->create_course();
        $indwikilv = $this->getDataGenerator()->create_module('wikilv', array('course' => $course->id, 'wikilvmode' => 'individual'));
        $colwikilv = $this->getDataGenerator()->create_module('wikilv', array('course' => $course->id, 'wikilvmode' => 'collaborative'));

        // Create users.
        $student = self::getDataGenerator()->create_user();
        $teacher = self::getDataGenerator()->create_user();

        // Users enrolments.
        $studentrole = $DB->get_record('role', array('shortname' => 'student'));
        $teacherrole = $DB->get_record('role', array('shortname' => 'editingteacher'));
        $this->getDataGenerator()->enrol_user($student->id, $course->id, $studentrole->id, 'manual');
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, $teacherrole->id, 'manual');

        $this->setUser($student);

        // Check that not passing a wikilv returns empty array.
        $result = wikilv_get_visible_subwikilvs(null);
        $this->assertEquals(array(), $result);

        // Check that the student can get the only subwikilv from the collaborative wikilv.
        $expectedsubwikilvs = array();
        $expectedsubwikilv = new stdClass();
        $expectedsubwikilv->id = -1; // We haven't created any page so the subwikilv hasn't been created.
        $expectedsubwikilv->wikilvid = $colwikilv->id;
        $expectedsubwikilv->groupid = 0;
        $expectedsubwikilv->userid = 0;
        $expectedsubwikilvs[] = $expectedsubwikilv;

        $result = wikilv_get_visible_subwikilvs($colwikilv);
        $this->assertEquals($expectedsubwikilvs, $result);

        // Create a page now so the subwikilv is created.
        $colfirstpage = $this->getDataGenerator()->get_plugin_generator('mod_wikilv')->create_first_page($colwikilv);

        // Call the function again, now we expect to have a subwikilv ID.
        $expectedsubwikilvs[0]->id = $colfirstpage->subwikilvid;
        $result = wikilv_get_visible_subwikilvs($colwikilv);
        $this->assertEquals($expectedsubwikilvs, $result);

        // Check that the teacher can see it too.
        $this->setUser($teacher);
        $result = wikilv_get_visible_subwikilvs($colwikilv);
        $this->assertEquals($expectedsubwikilvs, $result);

        // Check that the student can only see his subwikilv in the individual wikilv.
        $this->setUser($student);
        $expectedsubwikilvs[0]->id = -1;
        $expectedsubwikilvs[0]->wikilvid = $indwikilv->id;
        $expectedsubwikilvs[0]->userid = $student->id;
        $result = wikilv_get_visible_subwikilvs($indwikilv);
        $this->assertEquals($expectedsubwikilvs, $result);

        // Check that the teacher can see his subwikilv and the student subwikilv in the individual wikilv.
        $this->setUser($teacher);
        $teachersubwikilv = new stdClass();
        $teachersubwikilv->id = -1;
        $teachersubwikilv->wikilvid = $indwikilv->id;
        $teachersubwikilv->groupid = 0;
        $teachersubwikilv->userid = $teacher->id;
        $expectedsubwikilvs[] = $teachersubwikilv;

        $result = wikilv_get_visible_subwikilvs($indwikilv);
        $this->assertEquals($expectedsubwikilvs, $result, '', 0, 10, true); // Compare without order.
    }

    /**
     * Test wikilv_get_visible_subwikilvs using collaborative wikilvs with groups.
     *
     * @return void
     */
    public function test_wikilv_get_visible_subwikilvs_with_groups_collaborative() {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        // Setup test data.
        $course = $this->getDataGenerator()->create_course();
        $wikilvsepcol = $this->getDataGenerator()->create_module('wikilv', array('course' => $course->id,
                                                        'groupmode' => SEPARATEGROUPS, 'wikilvmode' => 'collaborative'));
        $wikilvviscol = $this->getDataGenerator()->create_module('wikilv', array('course' => $course->id,
                                                        'groupmode' => VISIBLEGROUPS, 'wikilvmode' => 'collaborative'));

        // Create users.
        $student = self::getDataGenerator()->create_user();
        $student2 = self::getDataGenerator()->create_user();
        $student3 = self::getDataGenerator()->create_user();
        $teacher = self::getDataGenerator()->create_user();

        // Users enrolments.
        $studentrole = $DB->get_record('role', array('shortname' => 'student'));
        $teacherrole = $DB->get_record('role', array('shortname' => 'editingteacher'));
        $this->getDataGenerator()->enrol_user($student->id, $course->id, $studentrole->id, 'manual');
        $this->getDataGenerator()->enrol_user($student2->id, $course->id, $studentrole->id, 'manual');
        $this->getDataGenerator()->enrol_user($student3->id, $course->id, $studentrole->id, 'manual');
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, $teacherrole->id, 'manual');

        // Create groups.
        $group1 = $this->getDataGenerator()->create_group(array('courseid' => $course->id));
        $this->getDataGenerator()->create_group_member(array('userid' => $student->id, 'groupid' => $group1->id));
        $this->getDataGenerator()->create_group_member(array('userid' => $student2->id, 'groupid' => $group1->id));
        $group2 = $this->getDataGenerator()->create_group(array('courseid' => $course->id));
        $this->getDataGenerator()->create_group_member(array('userid' => $student2->id, 'groupid' => $group2->id));
        $this->getDataGenerator()->create_group_member(array('userid' => $student3->id, 'groupid' => $group2->id));

        $this->setUser($student);

        // Create all the possible subwikilvs. We haven't created any page so ids will be -1.
        // Subwikilvs in collaborative wikilvs: 1 subwikilv per group + 1 subwikilv for all participants.
        $swsepcolg1 = new stdClass();
        $swsepcolg1->id = -1;
        $swsepcolg1->wikilvid = $wikilvsepcol->id;
        $swsepcolg1->groupid = $group1->id;
        $swsepcolg1->userid = 0;

        $swsepcolg2 = clone($swsepcolg1);
        $swsepcolg2->groupid = $group2->id;

        $swsepcolallparts = clone($swsepcolg1); // All participants.
        $swsepcolallparts->groupid = 0;

        $swviscolg1 = clone($swsepcolg1);
        $swviscolg1->wikilvid = $wikilvviscol->id;

        $swviscolg2 = clone($swviscolg1);
        $swviscolg2->groupid = $group2->id;

        $swviscolallparts = clone($swviscolg1); // All participants.
        $swviscolallparts->groupid = 0;

        // Check that the student can get only the subwikilv from his group in collaborative wikilv with separate groups.
        $expectedsubwikilvs = array($swsepcolg1);
        $result = wikilv_get_visible_subwikilvs($wikilvsepcol);
        $this->assertEquals($expectedsubwikilvs, $result);

        // Check that he can get subwikilvs from both groups in collaborative wikilv with visible groups, and also all participants.
        $expectedsubwikilvs = array($swviscolallparts, $swviscolg1, $swviscolg2);
        $result = wikilv_get_visible_subwikilvs($wikilvviscol);
        $this->assertEquals($expectedsubwikilvs, $result, '', 0, 10, true);

        // Now test it as a teacher. No need to check visible groups wikilvs because the result is the same as student.
        $this->setUser($teacher);

        // Check that he can get the subwikilvs from all the groups in collaborative wikilv with separate groups.
        $expectedsubwikilvs = array($swsepcolg1, $swsepcolg2, $swsepcolallparts);
        $result = wikilv_get_visible_subwikilvs($wikilvsepcol);
        $this->assertEquals($expectedsubwikilvs, $result, '', 0, 10, true);
    }

    /**
     * Test wikilv_get_visible_subwikilvs using individual wikilvs with groups.
     *
     * @return void
     */
    public function test_wikilv_get_visible_subwikilvs_with_groups_individual() {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        // Setup test data.
        $course = $this->getDataGenerator()->create_course();
        $wikilvsepind = $this->getDataGenerator()->create_module('wikilv', array('course' => $course->id,
                                                        'groupmode' => SEPARATEGROUPS, 'wikilvmode' => 'individual'));
        $wikilvvisind = $this->getDataGenerator()->create_module('wikilv', array('course' => $course->id,
                                                        'groupmode' => VISIBLEGROUPS, 'wikilvmode' => 'individual'));

        // Create users.
        $student = self::getDataGenerator()->create_user();
        $student2 = self::getDataGenerator()->create_user();
        $student3 = self::getDataGenerator()->create_user();
        $teacher = self::getDataGenerator()->create_user();

        // Users enrolments.
        $studentrole = $DB->get_record('role', array('shortname' => 'student'));
        $teacherrole = $DB->get_record('role', array('shortname' => 'editingteacher'));
        $this->getDataGenerator()->enrol_user($student->id, $course->id, $studentrole->id, 'manual');
        $this->getDataGenerator()->enrol_user($student2->id, $course->id, $studentrole->id, 'manual');
        $this->getDataGenerator()->enrol_user($student3->id, $course->id, $studentrole->id, 'manual');
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, $teacherrole->id, 'manual');

        // Create groups.
        $group1 = $this->getDataGenerator()->create_group(array('courseid' => $course->id));
        $this->getDataGenerator()->create_group_member(array('userid' => $student->id, 'groupid' => $group1->id));
        $this->getDataGenerator()->create_group_member(array('userid' => $student2->id, 'groupid' => $group1->id));
        $group2 = $this->getDataGenerator()->create_group(array('courseid' => $course->id));
        $this->getDataGenerator()->create_group_member(array('userid' => $student2->id, 'groupid' => $group2->id));
        $this->getDataGenerator()->create_group_member(array('userid' => $student3->id, 'groupid' => $group2->id));

        $this->setUser($student);

        // Create all the possible subwikilvs to be returned. We haven't created any page so ids will be -1.
        // Subwikilvs in individual wikilvs: 1 subwikilv per user and group. If user doesn't belong to any group then groupid is 0.
        $swsepindg1s1 = new stdClass();
        $swsepindg1s1->id = -1;
        $swsepindg1s1->wikilvid = $wikilvsepind->id;
        $swsepindg1s1->groupid = $group1->id;
        $swsepindg1s1->userid = $student->id;

        $swsepindg1s2 = clone($swsepindg1s1);
        $swsepindg1s2->userid = $student2->id;

        $swsepindg2s2 = clone($swsepindg1s2);
        $swsepindg2s2->groupid = $group2->id;

        $swsepindg2s3 = clone($swsepindg1s1);
        $swsepindg2s3->userid = $student3->id;
        $swsepindg2s3->groupid = $group2->id;

        $swsepindteacher = clone($swsepindg1s1);
        $swsepindteacher->userid = $teacher->id;
        $swsepindteacher->groupid = 0;

        $swvisindg1s1 = clone($swsepindg1s1);
        $swvisindg1s1->wikilvid = $wikilvvisind->id;

        $swvisindg1s2 = clone($swvisindg1s1);
        $swvisindg1s2->userid = $student2->id;

        $swvisindg2s2 = clone($swvisindg1s2);
        $swvisindg2s2->groupid = $group2->id;

        $swvisindg2s3 = clone($swvisindg1s1);
        $swvisindg2s3->userid = $student3->id;
        $swvisindg2s3->groupid = $group2->id;

        $swvisindteacher = clone($swvisindg1s1);
        $swvisindteacher->userid = $teacher->id;
        $swvisindteacher->groupid = 0;

        // Check that student can get the subwikilvs from his group in individual wikilv with separate groups.
        $expectedsubwikilvs = array($swsepindg1s1, $swsepindg1s2);
        $result = wikilv_get_visible_subwikilvs($wikilvsepind);
        $this->assertEquals($expectedsubwikilvs, $result, '', 0, 10, true);

        // Check that he can get subwikilvs from all users and groups in individual wikilv with visible groups.
        $expectedsubwikilvs = array($swvisindg1s1, $swvisindg1s2, $swvisindg2s2, $swvisindg2s3, $swvisindteacher);
        $result = wikilv_get_visible_subwikilvs($wikilvvisind);
        $this->assertEquals($expectedsubwikilvs, $result, '', 0, 10, true);

        // Now test it as a teacher. No need to check visible groups wikilvs because the result is the same as student.
        $this->setUser($teacher);

        // Check that teacher can get the subwikilvs from all the groups in individual wikilv with separate groups.
        $expectedsubwikilvs = array($swsepindg1s1, $swsepindg1s2, $swsepindg2s2, $swsepindg2s3, $swsepindteacher);
        $result = wikilv_get_visible_subwikilvs($wikilvsepind);
        $this->assertEquals($expectedsubwikilvs, $result, '', 0, 10, true);
    }

    public function test_mod_wikilv_get_tagged_pages() {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        // Setup test data.
        $wikilvgenerator = $this->getDataGenerator()->get_plugin_generator('mod_wikilv');
        $course3 = $this->getDataGenerator()->create_course();
        $course2 = $this->getDataGenerator()->create_course();
        $course1 = $this->getDataGenerator()->create_course();
        $wikilv1 = $this->getDataGenerator()->create_module('wikilv', array('course' => $course1->id));
        $wikilv2 = $this->getDataGenerator()->create_module('wikilv', array('course' => $course2->id));
        $wikilv3 = $this->getDataGenerator()->create_module('wikilv', array('course' => $course3->id));
        $page11 = $wikilvgenerator->create_content($wikilv1, array('tags' => array('Cats', 'Dogs')));
        $page12 = $wikilvgenerator->create_content($wikilv1, array('tags' => array('Cats', 'mice')));
        $page13 = $wikilvgenerator->create_content($wikilv1, array('tags' => array('Cats')));
        $page14 = $wikilvgenerator->create_content($wikilv1);
        $page15 = $wikilvgenerator->create_content($wikilv1, array('tags' => array('Cats')));
        $page21 = $wikilvgenerator->create_content($wikilv2, array('tags' => array('Cats')));
        $page22 = $wikilvgenerator->create_content($wikilv2, array('tags' => array('Cats', 'Dogs')));
        $page23 = $wikilvgenerator->create_content($wikilv2, array('tags' => array('mice', 'Cats')));
        $page31 = $wikilvgenerator->create_content($wikilv3, array('tags' => array('mice', 'Cats')));

        $tag = core_tag_tag::get_by_name(0, 'Cats');

        // Admin can see everything.
        $res = mod_wikilv_get_tagged_pages($tag, /*$exclusivemode = */false,
                /*$fromctx = */0, /*$ctx = */0, /*$rec = */1, /*$page = */0);
        $this->assertRegExp('/'.$page11->title.'/', $res->content);
        $this->assertRegExp('/'.$page12->title.'/', $res->content);
        $this->assertRegExp('/'.$page13->title.'/', $res->content);
        $this->assertNotRegExp('/'.$page14->title.'/', $res->content);
        $this->assertRegExp('/'.$page15->title.'/', $res->content);
        $this->assertRegExp('/'.$page21->title.'/', $res->content);
        $this->assertNotRegExp('/'.$page22->title.'/', $res->content);
        $this->assertNotRegExp('/'.$page23->title.'/', $res->content);
        $this->assertNotRegExp('/'.$page31->title.'/', $res->content);
        $this->assertEmpty($res->prevpageurl);
        $this->assertNotEmpty($res->nextpageurl);
        $res = mod_wikilv_get_tagged_pages($tag, /*$exclusivemode = */false,
                /*$fromctx = */0, /*$ctx = */0, /*$rec = */1, /*$page = */1);
        $this->assertNotRegExp('/'.$page11->title.'/', $res->content);
        $this->assertNotRegExp('/'.$page12->title.'/', $res->content);
        $this->assertNotRegExp('/'.$page13->title.'/', $res->content);
        $this->assertNotRegExp('/'.$page14->title.'/', $res->content);
        $this->assertNotRegExp('/'.$page15->title.'/', $res->content);
        $this->assertNotRegExp('/'.$page21->title.'/', $res->content);
        $this->assertRegExp('/'.$page22->title.'/', $res->content);
        $this->assertRegExp('/'.$page23->title.'/', $res->content);
        $this->assertRegExp('/'.$page31->title.'/', $res->content);
        $this->assertNotEmpty($res->prevpageurl);
        $this->assertEmpty($res->nextpageurl);

        // Create and enrol a user.
        $student = self::getDataGenerator()->create_user();
        $studentrole = $DB->get_record('role', array('shortname' => 'student'));
        $this->getDataGenerator()->enrol_user($student->id, $course1->id, $studentrole->id, 'manual');
        $this->getDataGenerator()->enrol_user($student->id, $course2->id, $studentrole->id, 'manual');
        $this->setUser($student);
        core_tag_index_builder::reset_caches();

        // User can not see pages in course 3 because he is not enrolled.
        $res = mod_wikilv_get_tagged_pages($tag, /*$exclusivemode = */false,
                /*$fromctx = */0, /*$ctx = */0, /*$rec = */1, /*$page = */1);
        $this->assertRegExp('/'.$page22->title.'/', $res->content);
        $this->assertRegExp('/'.$page23->title.'/', $res->content);
        $this->assertNotRegExp('/'.$page31->title.'/', $res->content);

        // User can search wikilv pages inside a course.
        $coursecontext = context_course::instance($course1->id);
        $res = mod_wikilv_get_tagged_pages($tag, /*$exclusivemode = */false,
                /*$fromctx = */0, /*$ctx = */$coursecontext->id, /*$rec = */1, /*$page = */0);
        $this->assertRegExp('/'.$page11->title.'/', $res->content);
        $this->assertRegExp('/'.$page12->title.'/', $res->content);
        $this->assertRegExp('/'.$page13->title.'/', $res->content);
        $this->assertNotRegExp('/'.$page14->title.'/', $res->content);
        $this->assertRegExp('/'.$page15->title.'/', $res->content);
        $this->assertNotRegExp('/'.$page21->title.'/', $res->content);
        $this->assertNotRegExp('/'.$page22->title.'/', $res->content);
        $this->assertNotRegExp('/'.$page23->title.'/', $res->content);
        $this->assertEmpty($res->nextpageurl);
    }
}
