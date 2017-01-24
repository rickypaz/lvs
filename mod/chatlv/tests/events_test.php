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
 * Events tests.
 *
 * @package    mod_chatlv
 * @copyright  2013 Frédéric Massart
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/chatlv/lib.php');

/**
 * Events tests class.
 *
 * @package    mod_chatlv
 * @copyright  2013 Frédéric Massart
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_chatlv_events_testcase extends advanced_testcase {

    public function test_message_sent() {
        global $DB;
        $this->resetAfterTest();

        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $chatlv = $this->getDataGenerator()->create_module('chatlv', array('course' => $course->id));
        $cm = $DB->get_record('course_modules', array('id' => $chatlv->cmid));

        // Logging in first user to the chatlv.
        $this->setUser($user1->id);
        $sid1 = chatlv_login_user($chatlv->id, 'ajax', 0, $course);

        // Logging in second user to the chatlv.
        $this->setUser($user2->id);
        $sid2 = chatlv_login_user($chatlv->id, 'ajax', 0, $course);

        // Getting the chatlvuser record.
        $chatlvuser1 = $DB->get_record('chatlv_users', array('sid' => $sid1));
        $chatlvuser2 = $DB->get_record('chatlv_users', array('sid' => $sid2));

        $sink = $this->redirectEvents();

        // Send a messaging from the first user. We pass the CM to chatlv_send_chatlvmessage() this time.
        // This ensures that the event triggered when sending a message is filled with the correct information.
        $this->setUser($user1->id);
        $messageid = chatlv_send_chatlvmessage($chatlvuser1, 'Hello!', false, $cm);
        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = reset($events);
        $this->assertInstanceOf('\mod_chatlv\event\message_sent', $event);
        $this->assertEquals($messageid, $event->objectid);
        $this->assertEquals($user1->id, $event->relateduserid);
        $this->assertEquals($user1->id, $event->userid);
        $expected = array($course->id, 'chatlv', 'talk', "view.php?id=$cm->id", $chatlv->id, $cm->id, $user1->id);
        $this->assertEventLegacyLogData($expected, $event);

        // Send a messaging from the first user. We DO NOT pass the CM to chatlv_send_chatlvmessage() this time.
        // This ensures that the event triggered when sending a message is filled with the correct information.
        $sink->clear();
        $this->setUser($user2->id);
        $messageid = chatlv_send_chatlvmessage($chatlvuser2, 'Hello!');
        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = reset($events);
        $this->assertInstanceOf('\mod_chatlv\event\message_sent', $event);
        $this->assertEquals($messageid, $event->objectid);
        $this->assertEquals($user2->id, $event->relateduserid);
        $this->assertEquals($user2->id, $event->userid);
        $expected = array($course->id, 'chatlv', 'talk', "view.php?id=$cm->id", $chatlv->id, $cm->id, $user2->id);
        $this->assertEventLegacyLogData($expected, $event);
        $this->assertEventContextNotUsed($event);

        // Sending a message from the system should not trigger any event.
        $sink->clear();
        $this->setAdminUser();
        chatlv_send_chatlvmessage($chatlvuser1, 'enter', true);
        $this->assertEquals(0, $sink->count());

        $sink->close();
    }

    public function test_sessions_viewed() {
        global $USER;
        $this->resetAfterTest();

        // Not much can be tested here as the event is only triggered on a page load,
        // let's just check that the event contains the expected basic information.
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $chatlv = $this->getDataGenerator()->create_module('chatlv', array('course' => $course->id));

        $params = array(
            'context' => context_module::instance($chatlv->cmid),
            'objectid' => $chatlv->id,
            'other' => array(
                'start' => 1234,
                'end' => 5678
            )
        );
        $event = \mod_chatlv\event\sessions_viewed::create($params);
        $event->add_record_snapshot('chatlv', $chatlv);
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $event = reset($events);
        $this->assertInstanceOf('\mod_chatlv\event\sessions_viewed', $event);
        $this->assertEquals($USER->id, $event->userid);
        $this->assertEquals(context_module::instance($chatlv->cmid), $event->get_context());
        $this->assertEquals(1234, $event->other['start']);
        $this->assertEquals(5678, $event->other['end']);
        $this->assertEquals($chatlv->id, $event->objectid);
        $this->assertEquals($chatlv, $event->get_record_snapshot('chatlv', $chatlv->id));
        $expected = array($course->id, 'chatlv', 'report', "report.php?id=$chatlv->cmid", $chatlv->id, $chatlv->cmid);
        $this->assertEventLegacyLogData($expected, $event);
        $this->assertEventContextNotUsed($event);
    }

    public function test_course_module_instance_list_viewed() {
        global $USER;
        $this->resetAfterTest();

        // Not much can be tested here as the event is only triggered on a page load,
        // let's just check that the event contains the expected basic information.
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();

        $params = array(
            'context' => context_course::instance($course->id)
        );
        $event = \mod_chatlv\event\course_module_instance_list_viewed::create($params);
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $event = reset($events);
        $this->assertInstanceOf('\mod_chatlv\event\course_module_instance_list_viewed', $event);
        $this->assertEquals($USER->id, $event->userid);
        $this->assertEquals(context_course::instance($course->id), $event->get_context());
        $expected = array($course->id, 'chatlv', 'view all', "index.php?id=$course->id", '');
        $this->assertEventLegacyLogData($expected, $event);
        $this->assertEventContextNotUsed($event);
    }

    public function test_course_module_viewed() {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $chatlv = $this->getDataGenerator()->create_module('chatlv', array('course' => $course->id));
        $cm = get_coursemodule_from_instance('chatlv', $chatlv->id);
        $context = context_module::instance($cm->id);

        $params = array(
            'objectid' => $chatlv->id,
            'context' => $context
        );
        $event = \mod_chatlv\event\course_module_viewed::create($params);
        $event->add_record_snapshot('chatlv', $chatlv);
        $event->trigger();

        $expected = array($course->id, 'chatlv', 'view', "view.php?id=$cm->id", $chatlv->id, $cm->id);
        $this->assertEventLegacyLogData($expected, $event);
        $this->assertEventContextNotUsed($event);
        $url = new moodle_url('/mod/chatlv/view.php', array('id' => $cm->id));
        $this->assertEquals($url, $event->get_url());
        $event->get_name();
    }
}
