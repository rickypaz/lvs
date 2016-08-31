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
 * The module forumlvs external functions unit tests
 *
 * @package    mod_forumlv
 * @category   external
 * @copyright  2012 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/webservice/tests/helpers.php');

class mod_forumlv_external_testcase extends externallib_advanced_testcase {

    /**
     * Tests set up
     */
    protected function setUp() {
        global $CFG;

        // We must clear the subscription caches. This has to be done both before each test, and after in case of other
        // tests using these functions.
        \mod_forumlv\subscriptions::reset_forumlv_cache();

        require_once($CFG->dirroot . '/mod/forumlv/externallib.php');
    }

    public function tearDown() {
        // We must clear the subscription caches. This has to be done both before each test, and after in case of other
        // tests using these functions.
        \mod_forumlv\subscriptions::reset_forumlv_cache();
    }

    /**
     * Test get forumlvs
     */
    public function test_mod_forumlv_get_forumlvs_by_courses() {
        global $USER, $CFG, $DB;

        $this->resetAfterTest(true);

        // Create a user.
        $user = self::getDataGenerator()->create_user();

        // Set to the user.
        self::setUser($user);

        // Create courses to add the modules.
        $course1 = self::getDataGenerator()->create_course();
        $course2 = self::getDataGenerator()->create_course();

        // First forumlv.
        $record = new stdClass();
        $record->introformat = FORMAT_HTML;
        $record->course = $course1->id;
        $forumlv1 = self::getDataGenerator()->create_module('forumlv', $record);

        // Second forumlv.
        $record = new stdClass();
        $record->introformat = FORMAT_HTML;
        $record->course = $course2->id;
        $forumlv2 = self::getDataGenerator()->create_module('forumlv', $record);

        // Add discussions to the forumlvs.
        $record = new stdClass();
        $record->course = $course1->id;
        $record->userid = $user->id;
        $record->forumlv = $forumlv1->id;
        $discussion1 = self::getDataGenerator()->get_plugin_generator('mod_forumlv')->create_discussion($record);
        // Expect one discussion.
        $forumlv1->numdiscussions = 1;
        $forumlv1->cancreatediscussions = true;

        $record = new stdClass();
        $record->course = $course2->id;
        $record->userid = $user->id;
        $record->forumlv = $forumlv2->id;
        $discussion2 = self::getDataGenerator()->get_plugin_generator('mod_forumlv')->create_discussion($record);
        $discussion3 = self::getDataGenerator()->get_plugin_generator('mod_forumlv')->create_discussion($record);
        // Expect two discussions.
        $forumlv2->numdiscussions = 2;
        // Default limited role, no create discussion capability enabled.
        $forumlv2->cancreatediscussions = false;

        // Check the forumlv was correctly created.
        $this->assertEquals(2, $DB->count_records_select('forumlv', 'id = :forumlv1 OR id = :forumlv2',
                array('forumlv1' => $forumlv1->id, 'forumlv2' => $forumlv2->id)));

        // Enrol the user in two courses.
        // DataGenerator->enrol_user automatically sets a role for the user with the permission mod/form:viewdiscussion.
        $this->getDataGenerator()->enrol_user($user->id, $course1->id, null, 'manual');
        // Execute real Moodle enrolment as we'll call unenrol() method on the instance later.
        $enrol = enrol_get_plugin('manual');
        $enrolinstances = enrol_get_instances($course2->id, true);
        foreach ($enrolinstances as $courseenrolinstance) {
            if ($courseenrolinstance->enrol == "manual") {
                $instance2 = $courseenrolinstance;
                break;
            }
        }
        $enrol->enrol_user($instance2, $user->id);

        // Assign capabilities to view forumlvs for forumlv 2.
        $cm2 = get_coursemodule_from_id('forumlv', $forumlv2->cmid, 0, false, MUST_EXIST);
        $context2 = context_module::instance($cm2->id);
        $newrole = create_role('Role 2', 'role2', 'Role 2 description');
        $roleid2 = $this->assignUserCapability('mod/forumlv:viewdiscussion', $context2->id, $newrole);

        // Create what we expect to be returned when querying the two courses.
        unset($forumlv1->displaywordcount);
        unset($forumlv2->displaywordcount);

        $expectedforumlvs = array();
        $expectedforumlvs[$forumlv1->id] = (array) $forumlv1;
        $expectedforumlvs[$forumlv2->id] = (array) $forumlv2;

        // Call the external function passing course ids.
        $forumlvs = mod_forumlv_external::get_forumlvs_by_courses(array($course1->id, $course2->id));
        $forumlvs = external_api::clean_returnvalue(mod_forumlv_external::get_forumlvs_by_courses_returns(), $forumlvs);
        $this->assertCount(2, $forumlvs);
        foreach ($forumlvs as $forumlv) {
            $this->assertEquals($expectedforumlvs[$forumlv['id']], $forumlv);
        }

        // Call the external function without passing course id.
        $forumlvs = mod_forumlv_external::get_forumlvs_by_courses();
        $forumlvs = external_api::clean_returnvalue(mod_forumlv_external::get_forumlvs_by_courses_returns(), $forumlvs);
        $this->assertCount(2, $forumlvs);
        foreach ($forumlvs as $forumlv) {
            $this->assertEquals($expectedforumlvs[$forumlv['id']], $forumlv);
        }

        // Unenrol user from second course and alter expected forumlvs.
        $enrol->unenrol_user($instance2, $user->id);
        unset($expectedforumlvs[$forumlv2->id]);

        // Call the external function without passing course id.
        $forumlvs = mod_forumlv_external::get_forumlvs_by_courses();
        $forumlvs = external_api::clean_returnvalue(mod_forumlv_external::get_forumlvs_by_courses_returns(), $forumlvs);
        $this->assertCount(1, $forumlvs);
        $this->assertEquals($expectedforumlvs[$forumlv1->id], $forumlvs[0]);
        $this->assertTrue($forumlvs[0]['cancreatediscussions']);

        // Change the type of the forumlv, the user shouldn't be able to add discussions.
        $DB->set_field('forumlv', 'type', 'news', array('id' => $forumlv1->id));
        $forumlvs = mod_forumlv_external::get_forumlvs_by_courses();
        $forumlvs = external_api::clean_returnvalue(mod_forumlv_external::get_forumlvs_by_courses_returns(), $forumlvs);
        $this->assertFalse($forumlvs[0]['cancreatediscussions']);

        // Call for the second course we unenrolled the user from.
        $forumlvs = mod_forumlv_external::get_forumlvs_by_courses(array($course2->id));
        $forumlvs = external_api::clean_returnvalue(mod_forumlv_external::get_forumlvs_by_courses_returns(), $forumlvs);
        $this->assertCount(0, $forumlvs);
    }

    /**
     * Test get forumlv posts
     */
    public function test_mod_forumlv_get_forumlv_discussion_posts() {
        global $CFG, $PAGE;

        $this->resetAfterTest(true);

        // Set the CFG variable to allow track forumlvs.
        $CFG->forumlv_trackreadposts = true;

        // Create a user who can track forumlvs.
        $record = new stdClass();
        $record->trackforumlvs = true;
        $user1 = self::getDataGenerator()->create_user($record);
        // Create a bunch of other users to post.
        $user2 = self::getDataGenerator()->create_user();
        $user3 = self::getDataGenerator()->create_user();

        // Set the first created user to the test user.
        self::setUser($user1);

        // Create course to add the module.
        $course1 = self::getDataGenerator()->create_course();

        // Forumlv with tracking off.
        $record = new stdClass();
        $record->course = $course1->id;
        $record->trackingtype = FORUMLV_TRACKING_OFF;
        $forumlv1 = self::getDataGenerator()->create_module('forumlv', $record);
        $forumlv1context = context_module::instance($forumlv1->cmid);

        // Add discussions to the forumlvs.
        $record = new stdClass();
        $record->course = $course1->id;
        $record->userid = $user1->id;
        $record->forumlv = $forumlv1->id;
        $discussion1 = self::getDataGenerator()->get_plugin_generator('mod_forumlv')->create_discussion($record);

        $record = new stdClass();
        $record->course = $course1->id;
        $record->userid = $user2->id;
        $record->forumlv = $forumlv1->id;
        $discussion2 = self::getDataGenerator()->get_plugin_generator('mod_forumlv')->create_discussion($record);

        // Add 2 replies to the discussion 1 from different users.
        $record = new stdClass();
        $record->discussion = $discussion1->id;
        $record->parent = $discussion1->firstpost;
        $record->userid = $user2->id;
        $discussion1reply1 = self::getDataGenerator()->get_plugin_generator('mod_forumlv')->create_post($record);

        $record->parent = $discussion1reply1->id;
        $record->userid = $user3->id;
        $discussion1reply2 = self::getDataGenerator()->get_plugin_generator('mod_forumlv')->create_post($record);

        // Enrol the user in the  course.
        $enrol = enrol_get_plugin('manual');
        // Following line enrol and assign default role id to the user.
        // So the user automatically gets mod/forumlv:viewdiscussion on all forumlvs of the course.
        $this->getDataGenerator()->enrol_user($user1->id, $course1->id);
        $this->getDataGenerator()->enrol_user($user2->id, $course1->id);

        // Delete one user, to test that we still receive posts by this user.
        delete_user($user3);

        // Create what we expect to be returned when querying the discussion.
        $expectedposts = array(
            'posts' => array(),
            'warnings' => array(),
        );

        // User pictures are initially empty, we should get the links once the external function is called.
        $expectedposts['posts'][] = array(
            'id' => $discussion1reply2->id,
            'discussion' => $discussion1reply2->discussion,
            'parent' => $discussion1reply2->parent,
            'userid' => (int) $discussion1reply2->userid,
            'created' => $discussion1reply2->created,
            'modified' => $discussion1reply2->modified,
            'mailed' => $discussion1reply2->mailed,
            'subject' => $discussion1reply2->subject,
            'message' => file_rewrite_pluginfile_urls($discussion1reply2->message, 'pluginfile.php',
                    $forumlv1context->id, 'mod_forumlv', 'post', $discussion1reply2->id),
            'messageformat' => 1,   // This value is usually changed by external_format_text() function.
            'messagetrust' => $discussion1reply2->messagetrust,
            'attachment' => $discussion1reply2->attachment,
            'totalscore' => $discussion1reply2->totalscore,
            'mailnow' => $discussion1reply2->mailnow,
            'children' => array(),
            'canreply' => true,
            'postread' => false,
            'userfullname' => fullname($user3),
            'userpictureurl' => ''
        );

        $expectedposts['posts'][] = array(
            'id' => $discussion1reply1->id,
            'discussion' => $discussion1reply1->discussion,
            'parent' => $discussion1reply1->parent,
            'userid' => (int) $discussion1reply1->userid,
            'created' => $discussion1reply1->created,
            'modified' => $discussion1reply1->modified,
            'mailed' => $discussion1reply1->mailed,
            'subject' => $discussion1reply1->subject,
            'message' => file_rewrite_pluginfile_urls($discussion1reply1->message, 'pluginfile.php',
                    $forumlv1context->id, 'mod_forumlv', 'post', $discussion1reply1->id),
            'messageformat' => 1,   // This value is usually changed by external_format_text() function.
            'messagetrust' => $discussion1reply1->messagetrust,
            'attachment' => $discussion1reply1->attachment,
            'totalscore' => $discussion1reply1->totalscore,
            'mailnow' => $discussion1reply1->mailnow,
            'children' => array($discussion1reply2->id),
            'canreply' => true,
            'postread' => false,
            'userfullname' => fullname($user2),
            'userpictureurl' => ''
        );

        // Test a discussion with two additional posts (total 3 posts).
        $posts = mod_forumlv_external::get_forumlv_discussion_posts($discussion1->id, 'modified', 'DESC');
        $posts = external_api::clean_returnvalue(mod_forumlv_external::get_forumlv_discussion_posts_returns(), $posts);
        $this->assertEquals(3, count($posts['posts']));

        // Generate here the pictures because we need to wait to the external function to init the theme.
        $userpicture = new user_picture($user3);
        $userpicture->size = 1; // Size f1.
        $expectedposts['posts'][0]['userpictureurl'] = $userpicture->get_url($PAGE)->out(false);

        $userpicture = new user_picture($user2);
        $userpicture->size = 1; // Size f1.
        $expectedposts['posts'][1]['userpictureurl'] = $userpicture->get_url($PAGE)->out(false);

        // Unset the initial discussion post.
        array_pop($posts['posts']);
        $this->assertEquals($expectedposts, $posts);

        // Test discussion without additional posts. There should be only one post (the one created by the discussion).
        $posts = mod_forumlv_external::get_forumlv_discussion_posts($discussion2->id, 'modified', 'DESC');
        $posts = external_api::clean_returnvalue(mod_forumlv_external::get_forumlv_discussion_posts_returns(), $posts);
        $this->assertEquals(1, count($posts['posts']));

    }

    /**
     * Test get forumlv posts (qanda forumlv)
     */
    public function test_mod_forumlv_get_forumlv_discussion_posts_qanda() {
        global $CFG, $DB;

        $this->resetAfterTest(true);

        $record = new stdClass();
        $user1 = self::getDataGenerator()->create_user($record);
        $user2 = self::getDataGenerator()->create_user();

        // Set the first created user to the test user.
        self::setUser($user1);

        // Create course to add the module.
        $course1 = self::getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user($user1->id, $course1->id);
        $this->getDataGenerator()->enrol_user($user2->id, $course1->id);

        // Forumlv with tracking off.
        $record = new stdClass();
        $record->course = $course1->id;
        $record->type = 'qanda';
        $forumlv1 = self::getDataGenerator()->create_module('forumlv', $record);
        $forumlv1context = context_module::instance($forumlv1->cmid);

        // Add discussions to the forumlvs.
        $record = new stdClass();
        $record->course = $course1->id;
        $record->userid = $user2->id;
        $record->forumlv = $forumlv1->id;
        $discussion1 = self::getDataGenerator()->get_plugin_generator('mod_forumlv')->create_discussion($record);

        // Add 1 reply (not the actual user).
        $record = new stdClass();
        $record->discussion = $discussion1->id;
        $record->parent = $discussion1->firstpost;
        $record->userid = $user2->id;
        $discussion1reply1 = self::getDataGenerator()->get_plugin_generator('mod_forumlv')->create_post($record);

        // We still see only the original post.
        $posts = mod_forumlv_external::get_forumlv_discussion_posts($discussion1->id, 'modified', 'DESC');
        $posts = external_api::clean_returnvalue(mod_forumlv_external::get_forumlv_discussion_posts_returns(), $posts);
        $this->assertEquals(1, count($posts['posts']));

        // Add a new reply, the user is going to be able to see only the original post and their new post.
        $record = new stdClass();
        $record->discussion = $discussion1->id;
        $record->parent = $discussion1->firstpost;
        $record->userid = $user1->id;
        $discussion1reply2 = self::getDataGenerator()->get_plugin_generator('mod_forumlv')->create_post($record);

        $posts = mod_forumlv_external::get_forumlv_discussion_posts($discussion1->id, 'modified', 'DESC');
        $posts = external_api::clean_returnvalue(mod_forumlv_external::get_forumlv_discussion_posts_returns(), $posts);
        $this->assertEquals(2, count($posts['posts']));

        // Now, we can fake the time of the user post, so he can se the rest of the discussion posts.
        $discussion1reply2->created -= $CFG->maxeditingtime * 2;
        $DB->update_record('forumlv_posts', $discussion1reply2);

        $posts = mod_forumlv_external::get_forumlv_discussion_posts($discussion1->id, 'modified', 'DESC');
        $posts = external_api::clean_returnvalue(mod_forumlv_external::get_forumlv_discussion_posts_returns(), $posts);
        $this->assertEquals(3, count($posts['posts']));
    }

    /**
     * Test get forumlv discussions paginated
     */
    public function test_mod_forumlv_get_forumlv_discussions_paginated() {
        global $USER, $CFG, $DB, $PAGE;

        $this->resetAfterTest(true);

        // Set the CFG variable to allow track forumlvs.
        $CFG->forumlv_trackreadposts = true;

        // Create a user who can track forumlvs.
        $record = new stdClass();
        $record->trackforumlvs = true;
        $user1 = self::getDataGenerator()->create_user($record);
        // Create a bunch of other users to post.
        $user2 = self::getDataGenerator()->create_user();
        $user3 = self::getDataGenerator()->create_user();
        $user4 = self::getDataGenerator()->create_user();

        // Set the first created user to the test user.
        self::setUser($user1);

        // Create courses to add the modules.
        $course1 = self::getDataGenerator()->create_course();

        // First forumlv with tracking off.
        $record = new stdClass();
        $record->course = $course1->id;
        $record->trackingtype = FORUMLV_TRACKING_OFF;
        $forumlv1 = self::getDataGenerator()->create_module('forumlv', $record);

        // Add discussions to the forumlvs.
        $record = new stdClass();
        $record->course = $course1->id;
        $record->userid = $user1->id;
        $record->forumlv = $forumlv1->id;
        $discussion1 = self::getDataGenerator()->get_plugin_generator('mod_forumlv')->create_discussion($record);

        // Add three replies to the discussion 1 from different users.
        $record = new stdClass();
        $record->discussion = $discussion1->id;
        $record->parent = $discussion1->firstpost;
        $record->userid = $user2->id;
        $discussion1reply1 = self::getDataGenerator()->get_plugin_generator('mod_forumlv')->create_post($record);

        $record->parent = $discussion1reply1->id;
        $record->userid = $user3->id;
        $discussion1reply2 = self::getDataGenerator()->get_plugin_generator('mod_forumlv')->create_post($record);

        $record->userid = $user4->id;
        $discussion1reply3 = self::getDataGenerator()->get_plugin_generator('mod_forumlv')->create_post($record);

        // Enrol the user in the first course.
        $enrol = enrol_get_plugin('manual');

        // We don't use the dataGenerator as we need to get the $instance2 to unenrol later.
        $enrolinstances = enrol_get_instances($course1->id, true);
        foreach ($enrolinstances as $courseenrolinstance) {
            if ($courseenrolinstance->enrol == "manual") {
                $instance1 = $courseenrolinstance;
                break;
            }
        }
        $enrol->enrol_user($instance1, $user1->id);

        // Delete one user.
        delete_user($user4);

        // Assign capabilities to view discussions for forumlv 1.
        $cm = get_coursemodule_from_id('forumlv', $forumlv1->cmid, 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);
        $newrole = create_role('Role 2', 'role2', 'Role 2 description');
        $this->assignUserCapability('mod/forumlv:viewdiscussion', $context->id, $newrole);

        // Create what we expect to be returned when querying the forumlvs.

        $post1 = $DB->get_record('forumlv_posts', array('id' => $discussion1->firstpost), '*', MUST_EXIST);

        // User pictures are initially empty, we should get the links once the external function is called.
        $expecteddiscussions = array(
                'id' => $discussion1->firstpost,
                'name' => $discussion1->name,
                'groupid' => $discussion1->groupid,
                'timemodified' => $discussion1reply3->created,
                'usermodified' => $discussion1reply3->userid,
                'timestart' => $discussion1->timestart,
                'timeend' => $discussion1->timeend,
                'discussion' => $discussion1->id,
                'parent' => 0,
                'userid' => $discussion1->userid,
                'created' => $post1->created,
                'modified' => $post1->modified,
                'mailed' => $post1->mailed,
                'subject' => $post1->subject,
                'message' => $post1->message,
                'messageformat' => $post1->messageformat,
                'messagetrust' => $post1->messagetrust,
                'attachment' => $post1->attachment,
                'totalscore' => $post1->totalscore,
                'mailnow' => $post1->mailnow,
                'userfullname' => fullname($user1),
                'usermodifiedfullname' => fullname($user4),
                'userpictureurl' => '',
                'usermodifiedpictureurl' => '',
                'numreplies' => 3,
                'numunread' => 0,
                'pinned' => FORUMLV_DISCUSSION_UNPINNED
            );

        // Call the external function passing forumlv id.
        $discussions = mod_forumlv_external::get_forumlv_discussions_paginated($forumlv1->id);
        $discussions = external_api::clean_returnvalue(mod_forumlv_external::get_forumlv_discussions_paginated_returns(), $discussions);
        $expectedreturn = array(
            'discussions' => array($expecteddiscussions),
            'warnings' => array()
        );

        // Wait the theme to be loaded (the external_api call does that) to generate the user profiles.
        $userpicture = new user_picture($user1);
        $userpicture->size = 1; // Size f1.
        $expectedreturn['discussions'][0]['userpictureurl'] = $userpicture->get_url($PAGE)->out(false);

        $userpicture = new user_picture($user4);
        $userpicture->size = 1; // Size f1.
        $expectedreturn['discussions'][0]['usermodifiedpictureurl'] = $userpicture->get_url($PAGE)->out(false);

        $this->assertEquals($expectedreturn, $discussions);

        // Call without required view discussion capability.
        $this->unassignUserCapability('mod/forumlv:viewdiscussion', $context->id, $newrole);
        try {
            mod_forumlv_external::get_forumlv_discussions_paginated($forumlv1->id);
            $this->fail('Exception expected due to missing capability.');
        } catch (moodle_exception $e) {
            $this->assertEquals('noviewdiscussionspermission', $e->errorcode);
        }

        // Unenrol user from second course.
        $enrol->unenrol_user($instance1, $user1->id);

        // Call for the second course we unenrolled the user from, make sure exception thrown.
        try {
            mod_forumlv_external::get_forumlv_discussions_paginated($forumlv1->id);
            $this->fail('Exception expected due to being unenrolled from the course.');
        } catch (moodle_exception $e) {
            $this->assertEquals('requireloginerror', $e->errorcode);
        }
    }

    /**
     * Test get forumlv discussions paginated (qanda forumlvs)
     */
    public function test_mod_forumlv_get_forumlv_discussions_paginated_qanda() {

        $this->resetAfterTest(true);

        // Create courses to add the modules.
        $course = self::getDataGenerator()->create_course();

        $user1 = self::getDataGenerator()->create_user();
        $user2 = self::getDataGenerator()->create_user();

        // First forumlv with tracking off.
        $record = new stdClass();
        $record->course = $course->id;
        $record->type = 'qanda';
        $forumlv = self::getDataGenerator()->create_module('forumlv', $record);

        // Add discussions to the forumlvs.
        $discussionrecord = new stdClass();
        $discussionrecord->course = $course->id;
        $discussionrecord->userid = $user2->id;
        $discussionrecord->forumlv = $forumlv->id;
        $discussion = self::getDataGenerator()->get_plugin_generator('mod_forumlv')->create_discussion($discussionrecord);

        self::setAdminUser();
        $discussions = mod_forumlv_external::get_forumlv_discussions_paginated($forumlv->id);
        $discussions = external_api::clean_returnvalue(mod_forumlv_external::get_forumlv_discussions_paginated_returns(), $discussions);

        $this->assertCount(1, $discussions['discussions']);
        $this->assertCount(0, $discussions['warnings']);

        self::setUser($user1);
        $this->getDataGenerator()->enrol_user($user1->id, $course->id);

        $discussions = mod_forumlv_external::get_forumlv_discussions_paginated($forumlv->id);
        $discussions = external_api::clean_returnvalue(mod_forumlv_external::get_forumlv_discussions_paginated_returns(), $discussions);

        $this->assertCount(1, $discussions['discussions']);
        $this->assertCount(0, $discussions['warnings']);

    }

    /**
     * Test add_discussion_post
     */
    public function test_add_discussion_post() {
        global $CFG;

        $this->resetAfterTest(true);

        $user = self::getDataGenerator()->create_user();
        $otheruser = self::getDataGenerator()->create_user();

        self::setAdminUser();

        // Create course to add the module.
        $course = self::getDataGenerator()->create_course(array('groupmode' => VISIBLEGROUPS, 'groupmodeforce' => 0));

        // Forumlv with tracking off.
        $record = new stdClass();
        $record->course = $course->id;
        $forumlv = self::getDataGenerator()->create_module('forumlv', $record);
        $cm = get_coursemodule_from_id('forumlv', $forumlv->cmid, 0, false, MUST_EXIST);
        $forumlvcontext = context_module::instance($forumlv->cmid);

        // Add discussions to the forumlvs.
        $record = new stdClass();
        $record->course = $course->id;
        $record->userid = $user->id;
        $record->forumlv = $forumlv->id;
        $discussion = self::getDataGenerator()->get_plugin_generator('mod_forumlv')->create_discussion($record);

        // Try to post (user not enrolled).
        self::setUser($user);
        try {
            mod_forumlv_external::add_discussion_post($discussion->firstpost, 'some subject', 'some text here...');
            $this->fail('Exception expected due to being unenrolled from the course.');
        } catch (moodle_exception $e) {
            $this->assertEquals('requireloginerror', $e->errorcode);
        }

        $this->getDataGenerator()->enrol_user($user->id, $course->id);
        $this->getDataGenerator()->enrol_user($otheruser->id, $course->id);

        $createdpost = mod_forumlv_external::add_discussion_post($discussion->firstpost, 'some subject', 'some text here...');
        $createdpost = external_api::clean_returnvalue(mod_forumlv_external::add_discussion_post_returns(), $createdpost);

        $posts = mod_forumlv_external::get_forumlv_discussion_posts($discussion->id);
        $posts = external_api::clean_returnvalue(mod_forumlv_external::get_forumlv_discussion_posts_returns(), $posts);
        // We receive the discussion and the post.
        $this->assertEquals(2, count($posts['posts']));

        $tested = false;
        foreach ($posts['posts'] as $thispost) {
            if ($createdpost['postid'] == $thispost['id']) {
                $this->assertEquals('some subject', $thispost['subject']);
                $this->assertEquals('some text here...', $thispost['message']);
                $tested = true;
            }
        }
        $this->assertTrue($tested);

        // Test inline and regular attachment in post
        // Create a file in a draft area for inline attachments.
        $draftidinlineattach = file_get_unused_draft_itemid();
        $draftidattach = file_get_unused_draft_itemid();
        self::setUser($user);
        $usercontext = context_user::instance($user->id);
        $filepath = '/';
        $filearea = 'draft';
        $component = 'user';
        $filenameimg = 'shouldbeanimage.txt';
        $filerecordinline = array(
            'contextid' => $usercontext->id,
            'component' => $component,
            'filearea'  => $filearea,
            'itemid'    => $draftidinlineattach,
            'filepath'  => $filepath,
            'filename'  => $filenameimg,
        );
        $fs = get_file_storage();

        // Create a file in a draft area for regular attachments.
        $filerecordattach = $filerecordinline;
        $attachfilename = 'attachment.txt';
        $filerecordattach['filename'] = $attachfilename;
        $filerecordattach['itemid'] = $draftidattach;
        $fs->create_file_from_string($filerecordinline, 'image contents (not really)');
        $fs->create_file_from_string($filerecordattach, 'simple text attachment');

        $options = array(array('name' => 'inlineattachmentsid', 'value' => $draftidinlineattach),
                         array('name' => 'attachmentsid', 'value' => $draftidattach));
        $dummytext = 'Here is an inline image: <img src="' . $CFG->wwwroot
                     . "/draftfile.php/{$usercontext->id}/user/draft/{$draftidinlineattach}/{$filenameimg}"
                     . '" alt="inlineimage">.';
        $createdpost = mod_forumlv_external::add_discussion_post($discussion->firstpost, 'new post inline attachment',
                                                               $dummytext, $options);
        $createdpost = external_api::clean_returnvalue(mod_forumlv_external::add_discussion_post_returns(), $createdpost);

        $posts = mod_forumlv_external::get_forumlv_discussion_posts($discussion->id);
        $posts = external_api::clean_returnvalue(mod_forumlv_external::get_forumlv_discussion_posts_returns(), $posts);
        // We receive the discussion and the post.
        // Can't guarantee order of posts during tests.
        $postfound = false;
        foreach ($posts['posts'] as $thispost) {
            if ($createdpost['postid'] == $thispost['id']) {
                $this->assertEquals($createdpost['postid'], $thispost['id']);
                $this->assertEquals($thispost['attachment'], 1, "There should be a non-inline attachment");
                $this->assertCount(1, $thispost['attachments'], "There should be 1 attachment");
                $this->assertEquals($thispost['attachments'][0]['filename'], $attachfilename, "There should be 1 attachment");
                $this->assertContains('pluginfile.php', $thispost['message']);
                $postfound = true;
                break;
            }
        }

        $this->assertTrue($postfound);

        // Check not posting in groups the user is not member of.
        $group = $this->getDataGenerator()->create_group(array('courseid' => $course->id));
        groups_add_member($group->id, $otheruser->id);

        $forumlv = self::getDataGenerator()->create_module('forumlv', $record, array('groupmode' => SEPARATEGROUPS));
        $record->forumlv = $forumlv->id;
        $record->userid = $otheruser->id;
        $record->groupid = $group->id;
        $discussion = self::getDataGenerator()->get_plugin_generator('mod_forumlv')->create_discussion($record);

        try {
            mod_forumlv_external::add_discussion_post($discussion->firstpost, 'some subject', 'some text here...');
            $this->fail('Exception expected due to invalid permissions for posting.');
        } catch (moodle_exception $e) {
            $this->assertEquals('nopostforumlv', $e->errorcode);
        }

    }

    /*
     * Test add_discussion. A basic test since all the API functions are already covered by unit tests.
     */
    public function test_add_discussion() {
        global $CFG, $USER;
        $this->resetAfterTest(true);

        // Create courses to add the modules.
        $course = self::getDataGenerator()->create_course();

        $user1 = self::getDataGenerator()->create_user();
        $user2 = self::getDataGenerator()->create_user();

        // First forumlv with tracking off.
        $record = new stdClass();
        $record->course = $course->id;
        $record->type = 'news';
        $forumlv = self::getDataGenerator()->create_module('forumlv', $record);

        self::setUser($user1);
        $this->getDataGenerator()->enrol_user($user1->id, $course->id);

        try {
            mod_forumlv_external::add_discussion($forumlv->id, 'the subject', 'some text here...');
            $this->fail('Exception expected due to invalid permissions.');
        } catch (moodle_exception $e) {
            $this->assertEquals('cannotcreatediscussion', $e->errorcode);
        }

        self::setAdminUser();
        $createddiscussion = mod_forumlv_external::add_discussion($forumlv->id, 'the subject', 'some text here...');
        $createddiscussion = external_api::clean_returnvalue(mod_forumlv_external::add_discussion_returns(), $createddiscussion);

        $discussions = mod_forumlv_external::get_forumlv_discussions_paginated($forumlv->id);
        $discussions = external_api::clean_returnvalue(mod_forumlv_external::get_forumlv_discussions_paginated_returns(), $discussions);

        $this->assertCount(1, $discussions['discussions']);
        $this->assertCount(0, $discussions['warnings']);

        $this->assertEquals($createddiscussion['discussionid'], $discussions['discussions'][0]['discussion']);
        $this->assertEquals(-1, $discussions['discussions'][0]['groupid']);
        $this->assertEquals('the subject', $discussions['discussions'][0]['subject']);
        $this->assertEquals('some text here...', $discussions['discussions'][0]['message']);

        $discussion2pinned = mod_forumlv_external::add_discussion($forumlv->id, 'the pinned subject', 'some 2 text here...', -1,
                                                                array('options' => array('name' => 'discussionpinned',
                                                                                         'value' => true)));
        $discussion3 = mod_forumlv_external::add_discussion($forumlv->id, 'the non pinnedsubject', 'some 3 text here...');
        $discussions = mod_forumlv_external::get_forumlv_discussions_paginated($forumlv->id);
        $discussions = external_api::clean_returnvalue(mod_forumlv_external::get_forumlv_discussions_paginated_returns(), $discussions);
        $this->assertCount(3, $discussions['discussions']);
        $this->assertEquals($discussion2pinned['discussionid'], $discussions['discussions'][0]['discussion']);

        // Test inline and regular attachment in new discussion
        // Create a file in a draft area for inline attachments.

        $fs = get_file_storage();

        $draftidinlineattach = file_get_unused_draft_itemid();
        $draftidattach = file_get_unused_draft_itemid();

        $usercontext = context_user::instance($USER->id);
        $filepath = '/';
        $filearea = 'draft';
        $component = 'user';
        $filenameimg = 'shouldbeanimage.txt';
        $filerecord = array(
            'contextid' => $usercontext->id,
            'component' => $component,
            'filearea'  => $filearea,
            'itemid'    => $draftidinlineattach,
            'filepath'  => $filepath,
            'filename'  => $filenameimg,
        );

        // Create a file in a draft area for regular attachments.
        $filerecordattach = $filerecord;
        $attachfilename = 'attachment.txt';
        $filerecordattach['filename'] = $attachfilename;
        $filerecordattach['itemid'] = $draftidattach;
        $fs->create_file_from_string($filerecord, 'image contents (not really)');
        $fs->create_file_from_string($filerecordattach, 'simple text attachment');

        $dummytext = 'Here is an inline image: <img src="' . $CFG->wwwroot .
                    "/draftfile.php/{$usercontext->id}/user/draft/{$draftidinlineattach}/{$filenameimg}" .
                    '" alt="inlineimage">.';

        $options = array(array('name' => 'inlineattachmentsid', 'value' => $draftidinlineattach),
                         array('name' => 'attachmentsid', 'value' => $draftidattach));
        $createddiscussion = mod_forumlv_external::add_discussion($forumlv->id, 'the inline attachment subject',
                                                                $dummytext, -1, $options);
        $createddiscussion = external_api::clean_returnvalue(mod_forumlv_external::add_discussion_returns(), $createddiscussion);

        $discussions = mod_forumlv_external::get_forumlv_discussions_paginated($forumlv->id);
        $discussions = external_api::clean_returnvalue(mod_forumlv_external::get_forumlv_discussions_paginated_returns(), $discussions);

        $this->assertCount(4, $discussions['discussions']);
        $this->assertCount(0, $createddiscussion['warnings']);
        // Can't guarantee order of posts during tests.
        $postfound = false;
        foreach ($discussions['discussions'] as $thisdiscussion) {
            if ($createddiscussion['discussionid'] == $thisdiscussion['discussion']) {
                $this->assertEquals($thisdiscussion['attachment'], 1, "There should be a non-inline attachment");
                $this->assertCount(1, $thisdiscussion['attachments'], "There should be 1 attachment");
                $this->assertEquals($thisdiscussion['attachments'][0]['filename'], $attachfilename, "There should be 1 attachment");
                $this->assertNotContains('draftfile.php', $thisdiscussion['message']);
                $this->assertContains('pluginfile.php', $thisdiscussion['message']);
                $postfound = true;
                break;
            }
        }

        $this->assertTrue($postfound);
    }

    /**
     * Test adding discussions in a course with gorups
     */
    public function test_add_discussion_in_course_with_groups() {
        global $CFG;

        $this->resetAfterTest(true);

        // Create course to add the module.
        $course = self::getDataGenerator()->create_course(array('groupmode' => VISIBLEGROUPS, 'groupmodeforce' => 0));
        $user = self::getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        // Forumlv forcing separate gropus.
        $record = new stdClass();
        $record->course = $course->id;
        $forumlv = self::getDataGenerator()->create_module('forumlv', $record, array('groupmode' => SEPARATEGROUPS));

        // Try to post (user not enrolled).
        self::setUser($user);

        // The user is not enroled in any group, try to post in a forumlv with separate groups.
        try {
            mod_forumlv_external::add_discussion($forumlv->id, 'the subject', 'some text here...');
            $this->fail('Exception expected due to invalid group permissions.');
        } catch (moodle_exception $e) {
            $this->assertEquals('cannotcreatediscussion', $e->errorcode);
        }

        try {
            mod_forumlv_external::add_discussion($forumlv->id, 'the subject', 'some text here...', 0);
            $this->fail('Exception expected due to invalid group permissions.');
        } catch (moodle_exception $e) {
            $this->assertEquals('cannotcreatediscussion', $e->errorcode);
        }

        // Create a group.
        $group = $this->getDataGenerator()->create_group(array('courseid' => $course->id));

        // Try to post in a group the user is not enrolled.
        try {
            mod_forumlv_external::add_discussion($forumlv->id, 'the subject', 'some text here...', $group->id);
            $this->fail('Exception expected due to invalid group permissions.');
        } catch (moodle_exception $e) {
            $this->assertEquals('cannotcreatediscussion', $e->errorcode);
        }

        // Add the user to a group.
        groups_add_member($group->id, $user->id);

        // Try to post in a group the user is not enrolled.
        try {
            mod_forumlv_external::add_discussion($forumlv->id, 'the subject', 'some text here...', $group->id + 1);
            $this->fail('Exception expected due to invalid group.');
        } catch (moodle_exception $e) {
            $this->assertEquals('cannotcreatediscussion', $e->errorcode);
        }

        // Nost add the discussion using a valid group.
        $discussion = mod_forumlv_external::add_discussion($forumlv->id, 'the subject', 'some text here...', $group->id);
        $discussion = external_api::clean_returnvalue(mod_forumlv_external::add_discussion_returns(), $discussion);

        $discussions = mod_forumlv_external::get_forumlv_discussions_paginated($forumlv->id);
        $discussions = external_api::clean_returnvalue(mod_forumlv_external::get_forumlv_discussions_paginated_returns(), $discussions);

        $this->assertCount(1, $discussions['discussions']);
        $this->assertCount(0, $discussions['warnings']);
        $this->assertEquals($discussion['discussionid'], $discussions['discussions'][0]['discussion']);
        $this->assertEquals($group->id, $discussions['discussions'][0]['groupid']);

        // Now add a discussions without indicating a group. The function should guess the correct group.
        $discussion = mod_forumlv_external::add_discussion($forumlv->id, 'the subject', 'some text here...');
        $discussion = external_api::clean_returnvalue(mod_forumlv_external::add_discussion_returns(), $discussion);

        $discussions = mod_forumlv_external::get_forumlv_discussions_paginated($forumlv->id);
        $discussions = external_api::clean_returnvalue(mod_forumlv_external::get_forumlv_discussions_paginated_returns(), $discussions);

        $this->assertCount(2, $discussions['discussions']);
        $this->assertCount(0, $discussions['warnings']);
        $this->assertEquals($group->id, $discussions['discussions'][0]['groupid']);
        $this->assertEquals($group->id, $discussions['discussions'][1]['groupid']);

        // Enrol the same user in other group.
        $group2 = $this->getDataGenerator()->create_group(array('courseid' => $course->id));
        groups_add_member($group2->id, $user->id);

        // Now add a discussions without indicating a group. The function should guess the correct group (the first one).
        $discussion = mod_forumlv_external::add_discussion($forumlv->id, 'the subject', 'some text here...');
        $discussion = external_api::clean_returnvalue(mod_forumlv_external::add_discussion_returns(), $discussion);

        $discussions = mod_forumlv_external::get_forumlv_discussions_paginated($forumlv->id);
        $discussions = external_api::clean_returnvalue(mod_forumlv_external::get_forumlv_discussions_paginated_returns(), $discussions);

        $this->assertCount(3, $discussions['discussions']);
        $this->assertCount(0, $discussions['warnings']);
        $this->assertEquals($group->id, $discussions['discussions'][0]['groupid']);
        $this->assertEquals($group->id, $discussions['discussions'][1]['groupid']);
        $this->assertEquals($group->id, $discussions['discussions'][2]['groupid']);

    }

    /*
     * Test can_add_discussion. A basic test since all the API functions are already covered by unit tests.
     */
    public function test_can_add_discussion() {

        $this->resetAfterTest(true);

        // Create courses to add the modules.
        $course = self::getDataGenerator()->create_course();

        $user = self::getDataGenerator()->create_user();

        // First forumlv with tracking off.
        $record = new stdClass();
        $record->course = $course->id;
        $record->type = 'news';
        $forumlv = self::getDataGenerator()->create_module('forumlv', $record);

        // User with no permissions to add in a news forumlv.
        self::setUser($user);
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        $result = mod_forumlv_external::can_add_discussion($forumlv->id);
        $result = external_api::clean_returnvalue(mod_forumlv_external::can_add_discussion_returns(), $result);
        $this->assertFalse($result['status']);

        self::setAdminUser();
        $result = mod_forumlv_external::can_add_discussion($forumlv->id);
        $result = external_api::clean_returnvalue(mod_forumlv_external::can_add_discussion_returns(), $result);
        $this->assertTrue($result['status']);

    }

}
