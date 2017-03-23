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
 * External mod_chatlv functions unit tests
 *
 * @package    mod_chatlv
 * @category   external
 * @copyright  2015 Juan Leyva <juan@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.0
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/webservice/tests/helpers.php');

/**
 * External mod_chatlv functions unit tests
 *
 * @package    mod_chatlv
 * @category   external
 * @copyright  2015 Juan Leyva <juan@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.0
 */
class mod_chatlv_external_testcase extends externallib_advanced_testcase {

    /**
     * Test login user
     */
    public function test_login_user() {
        global $DB;

        $this->resetAfterTest(true);

        // Setup test data.
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $chatlv = $this->getDataGenerator()->create_module('chatlv', array('course' => $course->id));

        $user = self::getDataGenerator()->create_user();
        $this->setUser($user);
        $studentrole = $DB->get_record('role', array('shortname' => 'student'));
        $this->getDataGenerator()->enrol_user($user->id, $course->id, $studentrole->id);

        $result = mod_chatlv_external::login_user($chatlv->id);
        $result = external_api::clean_returnvalue(mod_chatlv_external::login_user_returns(), $result);

        // Test session started.
        $sid = $DB->get_field('chatlv_users', 'sid', array('userid' => $user->id, 'chatlvid' => $chatlv->id));
        $this->assertEquals($result['chatlvsid'], $sid);

    }

    /**
     * Test get chatlv users
     */
    public function test_get_chatlv_users() {
        global $DB;

        $this->resetAfterTest(true);

        // Setup test data.
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $chatlv = $this->getDataGenerator()->create_module('chatlv', array('course' => $course->id));

        $user1 = self::getDataGenerator()->create_user();
        $user2 = self::getDataGenerator()->create_user();

        $this->setUser($user1);
        $studentrole = $DB->get_record('role', array('shortname' => 'student'));
        $this->getDataGenerator()->enrol_user($user1->id, $course->id, $studentrole->id);
        $this->getDataGenerator()->enrol_user($user2->id, $course->id, $studentrole->id);

        $result = mod_chatlv_external::login_user($chatlv->id);
        $result = external_api::clean_returnvalue(mod_chatlv_external::login_user_returns(), $result);

        $this->setUser($user2);
        $result = mod_chatlv_external::login_user($chatlv->id);
        $result = external_api::clean_returnvalue(mod_chatlv_external::login_user_returns(), $result);

        // Get users.
        $result = mod_chatlv_external::get_chatlv_users($result['chatlvsid']);
        $result = external_api::clean_returnvalue(mod_chatlv_external::get_chatlv_users_returns(), $result);

        // Check correct users.
        $this->assertCount(2, $result['users']);
        $found = 0;
        foreach ($result['users'] as $user) {
            if ($user['id'] == $user1->id or $user['id'] == $user2->id) {
                $found++;
            }
        }
        $this->assertEquals(2, $found);

    }

    /**
     * Test send and get chatlv messages
     */
    public function test_send_get_chatlv_message() {
        global $DB;

        $this->resetAfterTest(true);

        // Setup test data.
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $chatlv = $this->getDataGenerator()->create_module('chatlv', array('course' => $course->id));

        $user = self::getDataGenerator()->create_user();
        $this->setUser($user);
        $studentrole = $DB->get_record('role', array('shortname' => 'student'));
        $this->getDataGenerator()->enrol_user($user->id, $course->id, $studentrole->id);

        $result = mod_chatlv_external::login_user($chatlv->id);
        $result = external_api::clean_returnvalue(mod_chatlv_external::login_user_returns(), $result);
        $chatlvsid = $result['chatlvsid'];

        $result = mod_chatlv_external::send_chatlv_message($chatlvsid, 'hello!');
        $result = external_api::clean_returnvalue(mod_chatlv_external::send_chatlv_message_returns(), $result);

        // Test messages received.

        $result = mod_chatlv_external::get_chatlv_latest_messages($chatlvsid, 0);
        $result = external_api::clean_returnvalue(mod_chatlv_external::get_chatlv_latest_messages_returns(), $result);

        foreach ($result['messages'] as $message) {
            // Ommit system messages, like user just joined in.
            if ($message['system']) {
                continue;
            }
            $this->assertEquals('hello!', $message['message']);
        }
    }

    /**
     * Test view_chatlv
     */
    public function test_view_chatlv() {
        global $DB;

        $this->resetAfterTest(true);

        // Setup test data.
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $chatlv = $this->getDataGenerator()->create_module('chatlv', array('course' => $course->id));
        $context = context_module::instance($chatlv->cmid);
        $cm = get_coursemodule_from_instance('chatlv', $chatlv->id);

        // Test invalid instance id.
        try {
            mod_chatlv_external::view_chatlv(0);
            $this->fail('Exception expected due to invalid mod_chatlv instance id.');
        } catch (moodle_exception $e) {
            $this->assertEquals('invalidrecord', $e->errorcode);
        }

        // Test not-enrolled user.
        $user = self::getDataGenerator()->create_user();
        $this->setUser($user);
        try {
            mod_chatlv_external::view_chatlv($chatlv->id);
            $this->fail('Exception expected due to not enrolled user.');
        } catch (moodle_exception $e) {
            $this->assertEquals('requireloginerror', $e->errorcode);
        }

        // Test user with full capabilities.
        $studentrole = $DB->get_record('role', array('shortname' => 'student'));
        $this->getDataGenerator()->enrol_user($user->id, $course->id, $studentrole->id);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();

        $result = mod_chatlv_external::view_chatlv($chatlv->id);
        $result = external_api::clean_returnvalue(mod_chatlv_external::view_chatlv_returns(), $result);

        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = array_shift($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\mod_chatlv\event\course_module_viewed', $event);
        $this->assertEquals($context, $event->get_context());
        $moodlechatlv = new \moodle_url('/mod/chatlv/view.php', array('id' => $cm->id));
        $this->assertEquals($moodlechatlv, $event->get_url());
        $this->assertEventContextNotUsed($event);
        $this->assertNotEmpty($event->get_name());

        // Test user with no capabilities.
        // We need a explicit prohibit since this capability is only defined in authenticated user and guest roles.
        assign_capability('mod/chatlv:chatlv', CAP_PROHIBIT, $studentrole->id, $context->id);
        accesslib_clear_all_caches_for_unit_testing();

        try {
            mod_chatlv_external::view_chatlv($chatlv->id);
            $this->fail('Exception expected due to missing capability.');
        } catch (moodle_exception $e) {
            $this->assertEquals('nopermissions', $e->errorcode);
        }
    }

    /**
     * Test get_chatlvs_by_courses
     */
    public function test_get_chatlvs_by_courses() {
        global $DB, $USER;
        $this->resetAfterTest(true);
        $this->setAdminUser();
        $course1 = self::getDataGenerator()->create_course();
        $chatlvoptions1 = array(
                              'course' => $course1->id,
                              'name' => 'First Chatlv'
                             );
        $chatlv1 = self::getDataGenerator()->create_module('chatlv', $chatlvoptions1);
        $course2 = self::getDataGenerator()->create_course();
        $chatlvoptions2 = array(
                              'course' => $course2->id,
                              'name' => 'Second Chatlv'
                             );
        $chatlv2 = self::getDataGenerator()->create_module('chatlv', $chatlvoptions2);
        $student1 = $this->getDataGenerator()->create_user();
        $studentrole = $DB->get_record('role', array('shortname' => 'student'));

        // Enroll Student1 in Course1.
        self::getDataGenerator()->enrol_user($student1->id,  $course1->id, $studentrole->id);
        $this->setUser($student1);

        $chatlvs = mod_chatlv_external::get_chatlvs_by_courses();
        // We need to execute the return values cleaning process to simulate the web service server.
        $chatlvs = external_api::clean_returnvalue(mod_chatlv_external::get_chatlvs_by_courses_returns(), $chatlvs);
        $this->assertCount(1, $chatlvs['chatlvs']);
        $this->assertEquals('First Chatlv', $chatlvs['chatlvs'][0]['name']);
        // We see 11 fields.
        $this->assertCount(11, $chatlvs['chatlvs'][0]);

        // As Student you cannot see some chatlv properties like 'section'.
        $this->assertFalse(isset($chatlvs['chatlvs'][0]['section']));

        // Student1 is not enrolled in course2. The webservice will return a warning!
        $chatlvs = mod_chatlv_external::get_chatlvs_by_courses(array($course2->id));
        // We need to execute the return values cleaning process to simulate the web service server.
        $chatlvs = external_api::clean_returnvalue(mod_chatlv_external::get_chatlvs_by_courses_returns(), $chatlvs);
        $this->assertCount(0, $chatlvs['chatlvs']);
        $this->assertEquals(1, $chatlvs['warnings'][0]['warningcode']);

        // Now as admin.
        $this->setAdminUser();
        // As Admin we can see this chatlv.
        $chatlvs = mod_chatlv_external::get_chatlvs_by_courses(array($course2->id));
        // We need to execute the return values cleaning process to simulate the web service server.
        $chatlvs = external_api::clean_returnvalue(mod_chatlv_external::get_chatlvs_by_courses_returns(), $chatlvs);

        $this->assertCount(1, $chatlvs['chatlvs']);
        $this->assertEquals('Second Chatlv', $chatlvs['chatlvs'][0]['name']);
        // We see 16 fields.
        $this->assertCount(16, $chatlvs['chatlvs'][0]);
        // As an Admin you can see some chatlv properties like 'section'.
        $this->assertEquals(0, $chatlvs['chatlvs'][0]['section']);

        // Enrol student in the second course.
        self::getDataGenerator()->enrol_user($student1->id,  $course2->id, $studentrole->id);
        $this->setUser($student1);
        $chatlvs = mod_chatlv_external::get_chatlvs_by_courses();
        $chatlvs = external_api::clean_returnvalue(mod_chatlv_external::get_chatlvs_by_courses_returns(), $chatlvs);
        $this->assertCount(2, $chatlvs['chatlvs']);

    }
}
