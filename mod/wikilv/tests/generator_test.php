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
 * mod_wikilv generator tests
 *
 * @package    mod_wikilv
 * @category   test
 * @copyright  2013 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Genarator tests class for mod_wikilv.
 *
 * @package    mod_wikilv
 * @category   test
 * @copyright  2013 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_wikilv_generator_testcase extends advanced_testcase {

    public function test_create_instance() {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();

        $this->assertFalse($DB->record_exists('wikilv', array('course' => $course->id)));
        $wikilv = $this->getDataGenerator()->create_module('wikilv', array('course' => $course));
        $records = $DB->get_records('wikilv', array('course' => $course->id), 'id');
        $this->assertEquals(1, count($records));
        $this->assertTrue(array_key_exists($wikilv->id, $records));

        $params = array('course' => $course->id, 'name' => 'Another wikilv');
        $wikilv = $this->getDataGenerator()->create_module('wikilv', $params);
        $records = $DB->get_records('wikilv', array('course' => $course->id), 'id');
        $this->assertEquals(2, count($records));
        $this->assertEquals('Another wikilv', $records[$wikilv->id]->name);
    }

    public function test_create_content() {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $wikilv = $this->getDataGenerator()->create_module('wikilv', array('course' => $course));
        $wikilvgenerator = $this->getDataGenerator()->get_plugin_generator('mod_wikilv');

        $page1 = $wikilvgenerator->create_first_page($wikilv);
        $page2 = $wikilvgenerator->create_content($wikilv);
        $page3 = $wikilvgenerator->create_content($wikilv, array('title' => 'Custom title', 'tags' => array('Cats', 'mice')));
        unset($wikilv->cmid);
        $page4 = $wikilvgenerator->create_content($wikilv, array('tags' => 'Cats, dogs'));
        $subwikilvs = $DB->get_records('wikilv_subwikilvs', array('wikilvid' => $wikilv->id), 'id');
        $this->assertEquals(1, count($subwikilvs));
        $subwikilvid = key($subwikilvs);
        $records = $DB->get_records('wikilv_pages', array('subwikilvid' => $subwikilvid), 'id');
        $this->assertEquals(4, count($records));
        $this->assertEquals($page1->id, $records[$page1->id]->id);
        $this->assertEquals($page2->id, $records[$page2->id]->id);
        $this->assertEquals($page3->id, $records[$page3->id]->id);
        $this->assertEquals('Custom title', $records[$page3->id]->title);
        $this->assertEquals(array('Cats', 'mice'),
                array_values(core_tag_tag::get_item_tags_array('mod_wikilv', 'wikilv_pages', $page3->id)));
        $this->assertEquals(array('Cats', 'dogs'),
                array_values(core_tag_tag::get_item_tags_array('mod_wikilv', 'wikilv_pages', $page4->id)));
    }

    public function test_create_content_individual() {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $wikilv = $this->getDataGenerator()->create_module('wikilv',
                array('course' => $course, 'wikilvmode' => 'individual'));
        $wikilvgenerator = $this->getDataGenerator()->get_plugin_generator('mod_wikilv');

        $page1 = $wikilvgenerator->create_first_page($wikilv);
        $page2 = $wikilvgenerator->create_content($wikilv);
        $page3 = $wikilvgenerator->create_content($wikilv, array('title' => 'Custom title for admin'));
        $subwikilvs = $DB->get_records('wikilv_subwikilvs', array('wikilvid' => $wikilv->id), 'id');
        $this->assertEquals(1, count($subwikilvs));
        $subwikilvid = key($subwikilvs);
        $records = $DB->get_records('wikilv_pages', array('subwikilvid' => $subwikilvid), 'id');
        $this->assertEquals(3, count($records));
        $this->assertEquals($page1->id, $records[$page1->id]->id);
        $this->assertEquals($page2->id, $records[$page2->id]->id);
        $this->assertEquals($page3->id, $records[$page3->id]->id);
        $this->assertEquals('Custom title for admin', $records[$page3->id]->title);

        $user = $this->getDataGenerator()->create_user();
        $role = $DB->get_record('role', array('shortname' => 'student'));
        $this->getDataGenerator()->enrol_user($user->id, $course->id, $role->id);
        $this->setUser($user);

        $page1s = $wikilvgenerator->create_first_page($wikilv);
        $page2s = $wikilvgenerator->create_content($wikilv);
        $page3s = $wikilvgenerator->create_content($wikilv, array('title' => 'Custom title for student'));
        $subwikilvs = $DB->get_records('wikilv_subwikilvs', array('wikilvid' => $wikilv->id), 'id');
        $this->assertEquals(2, count($subwikilvs));
        next($subwikilvs);
        $subwikilvid = key($subwikilvs);
        $records = $DB->get_records('wikilv_pages', array('subwikilvid' => $subwikilvid), 'id');
        $this->assertEquals(3, count($records));
        $this->assertEquals($page1s->id, $records[$page1s->id]->id);
        $this->assertEquals($page2s->id, $records[$page2s->id]->id);
        $this->assertEquals($page3s->id, $records[$page3s->id]->id);
        $this->assertEquals('Custom title for student', $records[$page3s->id]->title);
    }
}
