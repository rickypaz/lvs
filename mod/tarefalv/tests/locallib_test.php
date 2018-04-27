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
 * Unit tests for (some of) mod/tarefalv/locallib.php.
 *
 * @package    mod_tarefalv
 * @category   phpunit
 * @copyright  1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/tarefalv/locallib.php');
require_once($CFG->dirroot . '/mod/tarefalv/upgradelib.php');
require_once($CFG->dirroot . '/mod/tarefalv/tests/base_test.php');

/**
 * Unit tests for (some of) mod/tarefalv/locallib.php.
 *
 * @copyright  1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_tarefalv_locallib_testcase extends mod_tarefalv_base_testcase {

    public function test_return_links() {
        global $PAGE;
        $this->setUser($this->editingteachers[0]);
        $returnaction = 'RETURNACTION';
        $returnparams = array('param'=>'1');
        $tarefalv = $this->create_instance();
        $PAGE->set_url(new moodle_url('/mod/tarefalv/view.php', array('id' => $tarefalv->get_course_module()->id)));
        $tarefalv->register_return_link($returnaction, $returnparams);
        $this->assertEquals($returnaction, $tarefalv->get_return_action());
        $this->assertEquals($returnparams, $tarefalv->get_return_params());
    }

    public function test_get_feedback_plugins() {
        $this->setUser($this->editingteachers[0]);
        $tarefalv = $this->create_instance();
        $installedplugins = array_keys(core_component::get_plugin_list('tarefalvfeedback'));

        foreach ($tarefalv->get_feedback_plugins() as $plugin) {
            $this->assertContains($plugin->get_type(), $installedplugins, 'Feedback plugin not in list of installed plugins');
        }
    }

    public function test_get_submission_plugins() {
        $this->setUser($this->editingteachers[0]);
        $tarefalv = $this->create_instance();
        $installedplugins = array_keys(core_component::get_plugin_list('tarefalvsubmission'));

        foreach ($tarefalv->get_submission_plugins() as $plugin) {
            $this->assertContains($plugin->get_type(), $installedplugins, 'Submission plugin not in list of installed plugins');
        }
    }

    public function test_is_blind_marking() {
        $this->setUser($this->editingteachers[0]);
        $tarefalv = $this->create_instance(array('blindmarking'=>1));
        $this->assertEquals(true, $tarefalv->is_blind_marking());

        // Test cannot see student names.
        $gradingtable = new tarefalv_grading_table($tarefalv, 1, '', 0, true);
        $output = $tarefalv->get_renderer()->render($gradingtable);
        $this->assertEquals(true, strpos($output, get_string('hiddenuser', 'tarefalv')));

        // Test students cannot reveal identities.
        $nopermission = false;
        $this->students[0]->ignoresesskey = true;
        $this->setUser($this->students[0]);
        $this->expectException('required_capability_exception');
        $tarefalv->reveal_identities();
        $this->students[0]->ignoresesskey = false;

        // Test teachers cannot reveal identities.
        $nopermission = false;
        $this->teachers[0]->ignoresesskey = true;
        $this->setUser($this->teachers[0]);
        $this->expectException('required_capability_exception');
        $tarefalv->reveal_identities();
        $this->teachers[0]->ignoresesskey = false;

        // Test sesskey is required.
        $this->setUser($this->editingteachers[0]);
        $this->expectException('moodle_exception');
        $tarefalv->reveal_identities();

        // Test editingteacher can reveal identities if sesskey is ignored.
        $this->editingteachers[0]->ignoresesskey = true;
        $this->setUser($this->editingteachers[0]);
        $tarefalv->reveal_identities();
        $this->assertEquals(false, $tarefalv->is_blind_marking());
        $this->editingteachers[0]->ignoresesskey = false;

        // Test student names are visible.
        $gradingtable = new tarefalv_grading_table($tarefalv, 1, '', 0, true);
        $output = $tarefalv->get_renderer()->render($gradingtable);
        $this->assertEquals(false, strpos($output, get_string('hiddenuser', 'tarefalv')));

        // Set this back to default.
        $this->editingteachers[0]->ignoresesskey = false;
    }

    /**
     * Data provider for test_get_tarefalv_perpage
     *
     * @return array Provider data
     */
    public function get_tarefalv_perpage_provider() {
        return array(
            array(
                'maxperpage' => -1,
                'userprefs' => array(
                    -1 => -1,
                    10 => 10,
                    20 => 20,
                    50 => 50,
                ),
            ),
            array(
                'maxperpage' => 15,
                'userprefs' => array(
                    -1 => 15,
                    10 => 10,
                    20 => 15,
                    50 => 15,
                ),
            ),
        );
    }

    /**
     * Test maxperpage
     *
     * @dataProvider get_tarefalv_perpage_provider
     * @param integer $maxperpage site config value
     * @param array $userprefs Array of user preferences and expected page sizes
     */
    public function test_get_tarefalv_perpage($maxperpage, $userprefs) {

        $this->setUser($this->editingteachers[0]);
        $tarefalv = $this->create_instance();
        set_config('maxperpage', $maxperpage, 'tarefalv');
        set_user_preference('tarefalv_perpage', null);
        $this->assertEquals(10, $tarefalv->get_tarefalv_perpage());
        foreach ($userprefs as $pref => $perpage) {
            set_user_preference('tarefalv_perpage', $pref);
            $this->assertEquals($perpage, $tarefalv->get_tarefalv_perpage());
        }
    }

    /**
     * Test submissions with extension date.
     */
    public function test_gradingtable_extension_due_date() {
        global $PAGE;

        // Setup the assignment.
        $this->create_extra_users();
        $this->setUser($this->editingteachers[0]);
        $tarefalv = $this->create_instance(array(
            'tarefalvsubmission_onlinetext_enabled'=>1,
            'duedate' => time() - 4 * 24 * 60 * 60,
         ));
        $PAGE->set_url(new moodle_url('/mod/tarefalv/view.php', array(
            'id' => $tarefalv->get_course_module()->id,
            'action' => 'grading',
        )));

        // Check that the assignment is late.
        $gradingtable = new tarefalv_grading_table($tarefalv, 1, '', 0, true);
        $output = $tarefalv->get_renderer()->render($gradingtable);
        $this->assertContains(get_string('submissionstatus_', 'tarefalv'), $output);
        $this->assertContains(get_string('overdue', 'tarefalv', format_time(4*24*60*60)), $output);

        // Grant an extension.
        $extendedtime = time() + 2 * 24 * 60 * 60;
        $tarefalv->testable_save_user_extension($this->students[0]->id, $extendedtime);
        $gradingtable = new tarefalv_grading_table($tarefalv, 1, '', 0, true);
        $output = $tarefalv->get_renderer()->render($gradingtable);
        $this->assertContains(get_string('submissionstatus_', 'tarefalv'), $output);
        $this->assertContains(get_string('userextensiondate', 'tarefalv', userdate($extendedtime)), $output);

        // Simulate a submission.
        $this->setUser($this->students[0]);
        $submission = $tarefalv->get_user_submission($this->students[0]->id, true);
        $submission->status = TAREFALV_SUBMISSION_STATUS_SUBMITTED;
        $tarefalv->testable_update_submission($submission, $this->students[0]->id, true, false);
        $data = new stdClass();
        $data->onlinetext_editor = array('itemid'=>file_get_unused_draft_itemid(),
                                         'text'=>'Submission text',
                                         'format'=>FORMAT_MOODLE);
        $plugin = $tarefalv->get_submission_plugin_by_type('onlinetext');
        $plugin->save($submission, $data);

        // Verify output.
        $this->setUser($this->editingteachers[0]);
        $gradingtable = new tarefalv_grading_table($tarefalv, 1, '', 0, true);
        $output = $tarefalv->get_renderer()->render($gradingtable);
        $this->assertContains(get_string('submissionstatus_submitted', 'tarefalv'), $output);
        $this->assertContains(get_string('userextensiondate', 'tarefalv', userdate($extendedtime)), $output);
    }

    /**
     * Test that late submissions with extension date calculate correctly.
     */
    public function test_gradingtable_extension_date_calculation_for_lateness() {
        global $PAGE;

        // Setup the assignment.
        $this->create_extra_users();
        $this->setUser($this->editingteachers[0]);
        $time = time();
        $tarefalv = $this->create_instance(array(
            'tarefalvsubmission_onlinetext_enabled'=>1,
            'duedate' => $time - 4 * 24 * 60 * 60,
         ));
        $PAGE->set_url(new moodle_url('/mod/tarefalv/view.php', array(
            'id' => $tarefalv->get_course_module()->id,
            'action' => 'grading',
        )));

        // Check that the assignment is late.
        $gradingtable = new tarefalv_grading_table($tarefalv, 1, '', 0, true);
        $output = $tarefalv->get_renderer()->render($gradingtable);
        $this->assertContains(get_string('submissionstatus_', 'tarefalv'), $output);
        $difftime = time() - $time;
        $this->assertContains(get_string('overdue', 'tarefalv', format_time(4*24*60*60 + $difftime)), $output);

        // Grant an extension that is in the past.
        $tarefalv->testable_save_user_extension($this->students[0]->id, $time - 2 * 24 * 60 * 60);
        $gradingtable = new tarefalv_grading_table($tarefalv, 1, '', 0, true);
        $output = $tarefalv->get_renderer()->render($gradingtable);
        $this->assertContains(get_string('submissionstatus_', 'tarefalv'), $output);
        $this->assertContains(get_string('userextensiondate', 'tarefalv', userdate($time - 2*24*60*60)), $output);
        $difftime = time() - $time;
        $this->assertContains(get_string('overdue', 'tarefalv', format_time(2*24*60*60 + $difftime)), $output);

        // Simulate a submission.
        $this->setUser($this->students[0]);
        $submission = $tarefalv->get_user_submission($this->students[0]->id, true);
        $submission->status = TAREFALV_SUBMISSION_STATUS_SUBMITTED;
        $tarefalv->testable_update_submission($submission, $this->students[0]->id, true, false);
        $data = new stdClass();
        $data->onlinetext_editor = array('itemid'=>file_get_unused_draft_itemid(),
                                         'text'=>'Submission text',
                                         'format'=>FORMAT_MOODLE);
        $plugin = $tarefalv->get_submission_plugin_by_type('onlinetext');
        $plugin->save($submission, $data);
        $submittedtime = time();

        // Verify output.
        $this->setUser($this->editingteachers[0]);
        $gradingtable = new tarefalv_grading_table($tarefalv, 1, '', 0, true);
        $output = $tarefalv->get_renderer()->render($gradingtable);
        $this->assertContains(get_string('submissionstatus_submitted', 'tarefalv'), $output);
        $this->assertContains(get_string('userextensiondate', 'tarefalv', userdate($time - 2*24*60*60)), $output);

        $difftime = $submittedtime - $time;
        $this->assertContains(get_string('submittedlateshort', 'tarefalv', format_time(2*24*60*60 + $difftime)), $output);
    }

    public function test_gradingtable_status_rendering() {
        global $PAGE;

        // Setup the assignment.
        $this->create_extra_users();
        $this->setUser($this->editingteachers[0]);
        $time = time();
        $tarefalv = $this->create_instance(array(
            'tarefalvsubmission_onlinetext_enabled' => 1,
            'duedate' => $time - 4 * 24 * 60 * 60,
         ));
        $PAGE->set_url(new moodle_url('/mod/tarefalv/view.php', array(
            'id' => $tarefalv->get_course_module()->id,
            'action' => 'grading',
        )));

        // Check that the assignment is late.
        $gradingtable = new tarefalv_grading_table($tarefalv, 1, '', 0, true);
        $output = $tarefalv->get_renderer()->render($gradingtable);
        $this->assertContains(get_string('submissionstatus_', 'tarefalv'), $output);
        $difftime = time() - $time;
        $this->assertContains(get_string('overdue', 'tarefalv', format_time(4 * 24 * 60 * 60 + $difftime)), $output);

        // Simulate a student viewing the assignment without submitting.
        $this->setUser($this->students[0]);
        $submission = $tarefalv->get_user_submission($this->students[0]->id, true);
        $submission->status = TAREFALV_SUBMISSION_STATUS_NEW;
        $tarefalv->testable_update_submission($submission, $this->students[0]->id, true, false);
        $submittedtime = time();

        // Verify output.
        $this->setUser($this->editingteachers[0]);
        $gradingtable = new tarefalv_grading_table($tarefalv, 1, '', 0, true);
        $output = $tarefalv->get_renderer()->render($gradingtable);
        $difftime = $submittedtime - $time;
        $this->assertContains(get_string('overdue', 'tarefalv', format_time(4 * 24 * 60 * 60 + $difftime)), $output);

        $document = new DOMDocument();
        @$document->loadHTML($output);
        $xpath = new DOMXPath($document);
        $this->assertEquals('', $xpath->evaluate('string(//td[@id="mod_tarefalv_grading_r0_c8"])'));
    }

    /**
     * Check that group submission information is rendered correctly in the
     * grading table.
     */
    public function test_gradingtable_group_submissions_rendering() {
        global $PAGE;

        $this->create_extra_users();
        // Now verify group assignments.
        $this->setUser($this->teachers[0]);
        $tarefalv = $this->create_instance(array(
            'teamsubmission' => 1,
            'tarefalvsubmission_onlinetext_enabled' => 1,
            'submissiondrafts' => 1,
            'requireallteammemberssubmit' => 0,
        ));
        $PAGE->set_url(new moodle_url('/mod/tarefalv/view.php', array(
            'id' => $tarefalv->get_course_module()->id,
            'action' => 'grading',
        )));

        // Add a submission.
        $this->setUser($this->extrastudents[0]);
        $data = new stdClass();
        $data->onlinetext_editor = array('itemid'=>file_get_unused_draft_itemid(),
                                         'text'=>'Submission text',
                                         'format'=>FORMAT_MOODLE);
        $notices = array();
        $tarefalv->save_submission($data, $notices);

        $submission = $tarefalv->get_group_submission($this->extrastudents[0]->id, 0, true);
        $submission->status = TAREFALV_SUBMISSION_STATUS_SUBMITTED;
        $tarefalv->testable_update_submission($submission, $this->extrastudents[0]->id, true, true);

        // Check output.
        $this->setUser($this->teachers[0]);
        $gradingtable = new tarefalv_grading_table($tarefalv, 4, '', 0, true);
        $output = $tarefalv->get_renderer()->render($gradingtable);
        $document = new DOMDocument();
        @$document->loadHTML($output);
        $xpath = new DOMXPath($document);

        // Check status.
        $this->assertSame(get_string('submissionstatus_submitted', 'tarefalv'), $xpath->evaluate('string(//td[@id="mod_tarefalv_grading_r0_c4"]/div[@class="submissionstatussubmitted"])'));
        $this->assertSame(get_string('submissionstatus_submitted', 'tarefalv'), $xpath->evaluate('string(//td[@id="mod_tarefalv_grading_r3_c4"]/div[@class="submissionstatussubmitted"])'));

        // Check submission last modified date
        $this->assertGreaterThan(0, strtotime($xpath->evaluate('string(//td[@id="mod_tarefalv_grading_r0_c8"])')));
        $this->assertGreaterThan(0, strtotime($xpath->evaluate('string(//td[@id="mod_tarefalv_grading_r3_c8"])')));

        // Check group.
        $this->assertSame($this->groups[0]->name, $xpath->evaluate('string(//td[@id="mod_tarefalv_grading_r0_c5"])'));
        $this->assertSame($this->groups[0]->name, $xpath->evaluate('string(//td[@id="mod_tarefalv_grading_r3_c5"])'));

        // Check submission text.
        $this->assertSame('Submission text', $xpath->evaluate('string(//td[@id="mod_tarefalv_grading_r0_c9"]/div/div)'));
        $this->assertSame('Submission text', $xpath->evaluate('string(//td[@id="mod_tarefalv_grading_r3_c9"]/div/div)'));

        // Check comments can be made.
        $this->assertSame(1, (int)$xpath->evaluate('count(//td[@id="mod_tarefalv_grading_r0_c10"]//textarea)'));
        $this->assertSame(1, (int)$xpath->evaluate('count(//td[@id="mod_tarefalv_grading_r3_c10"]//textarea)'));
    }

    public function test_show_intro() {
        // Test whether we are showing the intro at the correct times.
        $this->setUser($this->editingteachers[0]);
        $tarefalv = $this->create_instance(array('alwaysshowdescription'=>1));

        $this->assertEquals(true, $tarefalv->testable_show_intro());

        $tomorrow = time() + (24*60*60);

        $tarefalv = $this->create_instance(array('alwaysshowdescription'=>0,
                                               'allowsubmissionsfromdate'=>$tomorrow));
        $this->assertEquals(false, $tarefalv->testable_show_intro());
        $yesterday = time() - (24*60*60);
        $tarefalv = $this->create_instance(array('alwaysshowdescription'=>0,
                                               'allowsubmissionsfromdate'=>$yesterday));
        $this->assertEquals(true, $tarefalv->testable_show_intro());
    }

    public function test_has_submissions_or_grades() {
        $this->setUser($this->editingteachers[0]);
        $tarefalv = $this->create_instance(array('tarefalvsubmission_onlinetext_enabled'=>1));

        $instance = $tarefalv->get_instance();

        // Should start empty.
        $this->assertEquals(false, $tarefalv->has_submissions_or_grades());

        // Simulate a submission.
        $this->setUser($this->students[0]);
        $submission = $tarefalv->get_user_submission($this->students[0]->id, true);

        // The submission is still new.
        $this->assertEquals(false, $tarefalv->has_submissions_or_grades());

        // Submit the submission.
        $submission->status = TAREFALV_SUBMISSION_STATUS_SUBMITTED;
        $tarefalv->testable_update_submission($submission, $this->students[0]->id, true, false);
        $data = new stdClass();
        $data->onlinetext_editor = array('itemid'=>file_get_unused_draft_itemid(),
                                         'text'=>'Submission text',
                                         'format'=>FORMAT_MOODLE);
        $plugin = $tarefalv->get_submission_plugin_by_type('onlinetext');
        $plugin->save($submission, $data);

        // Now test again.
        $this->assertEquals(true, $tarefalv->has_submissions_or_grades());
        // Set this back to default.
        $this->students[0]->ignoresesskey = false;
    }

    public function test_delete_grades() {
        $this->setUser($this->editingteachers[0]);
        $tarefalv = $this->create_instance();

        // Simulate adding a grade.
        $this->setUser($this->teachers[0]);
        $data = new stdClass();
        $data->grade = '50.0';
        $tarefalv->testable_apply_grade_to_user($data, $this->students[0]->id, 0);

        // Now see if the data is in the gradebook.
        $gradinginfo = grade_get_grades($this->course->id,
                                        'mod',
                                        'tarefalv',
                                        $tarefalv->get_instance()->id);

        $this->assertNotEquals(0, count($gradinginfo->items));

        $tarefalv->testable_delete_grades();
        $gradinginfo = grade_get_grades($this->course->id,
                                        'mod',
                                        'tarefalv',
                                        $tarefalv->get_instance()->id);

        $this->assertEquals(0, count($gradinginfo->items));
    }

    public function test_delete_instance() {
        $this->setUser($this->editingteachers[0]);
        $tarefalv = $this->create_instance(array('tarefalvsubmission_onlinetext_enabled'=>1));

        // Simulate adding a grade.
        $this->setUser($this->teachers[0]);
        $data = new stdClass();
        $data->grade = '50.0';
        $tarefalv->testable_apply_grade_to_user($data, $this->students[0]->id, 0);

        // Simulate a submission.
        $this->setUser($this->students[0]);
        $submission = $tarefalv->get_user_submission($this->students[0]->id, true);
        $data = new stdClass();
        $data->onlinetext_editor = array('itemid'=>file_get_unused_draft_itemid(),
                                         'text'=>'Submission text',
                                         'format'=>FORMAT_MOODLE);
        $plugin = $tarefalv->get_submission_plugin_by_type('onlinetext');
        $plugin->save($submission, $data);

        // Now try and delete.
        $this->assertEquals(true, $tarefalv->delete_instance());
    }

    public function test_reset_userdata() {
        global $DB;

        $now = time();
        $this->setUser($this->editingteachers[0]);
        $tarefalv = $this->create_instance(array('tarefalvsubmission_onlinetext_enabled'=>1,
                                               'duedate'=>$now));

        // Simulate adding a grade.
        $this->setUser($this->teachers[0]);
        $data = new stdClass();
        $data->grade = '50.0';
        $tarefalv->testable_apply_grade_to_user($data, $this->students[0]->id, 0);

        // Simulate a submission.
        $this->setUser($this->students[0]);
        $submission = $tarefalv->get_user_submission($this->students[0]->id, true);
        $data = new stdClass();
        $data->onlinetext_editor = array('itemid'=>file_get_unused_draft_itemid(),
                                         'text'=>'Submission text',
                                         'format'=>FORMAT_MOODLE);
        $plugin = $tarefalv->get_submission_plugin_by_type('onlinetext');
        $plugin->save($submission, $data);

        $this->assertEquals(true, $tarefalv->has_submissions_or_grades());
        // Now try and reset.
        $data = new stdClass();
        $data->reset_tarefalv_submissions = 1;
        $data->reset_gradebook_grades = 1;
        $data->reset_tarefalv_user_overrides = 1;
        $data->reset_tarefalv_group_overrides = 1;
        $data->courseid = $this->course->id;
        $data->timeshift = 24*60*60;
        $this->setUser($this->editingteachers[0]);
        $tarefalv->reset_userdata($data);
        $this->assertEquals(false, $tarefalv->has_submissions_or_grades());

        // Reload the instance data.
        $instance = $DB->get_record('tarefalv', array('id'=>$tarefalv->get_instance()->id));
        $this->assertEquals($now + 24*60*60, $instance->duedate);

        // Test reset using tarefalv_reset_userdata().
        $tarefalvduedate = $instance->duedate; // Keep old updated value for comparison.
        $data->timeshift = 2*24*60*60;
        tarefalv_reset_userdata($data);
        $instance = $DB->get_record('tarefalv', array('id' => $tarefalv->get_instance()->id));
        $this->assertEquals($tarefalvduedate + 2*24*60*60, $instance->duedate);

        // Create one more assignment and reset, make sure time shifted for previous assignment is not changed.
        $tarefalv2 = $this->create_instance(array('tarefalvsubmission_onlinetext_enabled' => 1,
                                               'duedate' => $now));
        $tarefalvduedate = $instance->duedate;
        $data->timeshift = 3*24*60*60;
        $tarefalv2->reset_userdata($data);
        $instance = $DB->get_record('tarefalv', array('id' => $tarefalv->get_instance()->id));
        $this->assertEquals($tarefalvduedate, $instance->duedate);
        $instance2 = $DB->get_record('tarefalv', array('id' => $tarefalv2->get_instance()->id));
        $this->assertEquals($now + 3*24*60*60, $instance2->duedate);

        // Reset both assignments using tarefalv_reset_userdata() and make sure both assignments have same date.
        $tarefalvduedate = $instance->duedate;
        $tarefalv2duedate = $instance2->duedate;
        $data->timeshift = 4*24*60*60;
        tarefalv_reset_userdata($data);
        $instance = $DB->get_record('tarefalv', array('id' => $tarefalv->get_instance()->id));
        $this->assertEquals($tarefalvduedate + 4*24*60*60, $instance->duedate);
        $instance2 = $DB->get_record('tarefalv', array('id' => $tarefalv2->get_instance()->id));
        $this->assertEquals($tarefalv2duedate + 4*24*60*60, $instance2->duedate);
    }

    public function test_plugin_settings() {
        global $DB;

        $now = time();
        $this->setUser($this->editingteachers[0]);
        $tarefalv = $this->create_instance(array('tarefalvsubmission_file_enabled'=>1,
                                               'tarefalvsubmission_file_maxfiles'=>12,
                                               'tarefalvsubmission_file_maxsizebytes'=>10));

        $plugin = $tarefalv->get_submission_plugin_by_type('file');
        $this->assertEquals('12', $plugin->get_config('maxfilesubmissions'));
    }

    public function test_update_calendar() {
        global $DB;

        $this->setUser($this->editingteachers[0]);
        $userctx = context_user::instance($this->editingteachers[0]->id)->id;

        // Hack to pretend that there was an editor involved. We need both $_POST and $_REQUEST, and a sesskey.
        $draftid = file_get_unused_draft_itemid();
        $_REQUEST['introeditor'] = $draftid;
        $_POST['introeditor'] = $draftid;
        $_POST['sesskey'] = sesskey();

        // Write links to a draft area.
        $fakearealink1 = file_rewrite_pluginfile_urls('<a href="@@PLUGINFILE@@/pic.gif">link</a>', 'draftfile.php', $userctx,
            'user', 'draft', $draftid);
        $fakearealink2 = file_rewrite_pluginfile_urls('<a href="@@PLUGINFILE@@/pic.gif">new</a>', 'draftfile.php', $userctx,
            'user', 'draft', $draftid);

        // Create a new assignment with links to a draft area.
        $now = time();
        $tarefalv = $this->create_instance(array(
            'duedate' => $now,
            'intro' => $fakearealink1,
            'introformat' => FORMAT_HTML
        ));

        // See if there is an event in the calendar.
        $params = array('modulename'=>'tarefalv', 'instance'=>$tarefalv->get_instance()->id);
        $event = $DB->get_record('event', $params);
        $this->assertNotEmpty($event);
        $this->assertSame('link', $event->description);     // The pluginfile links are removed.

        // Make sure the same works when updating the assignment.
        $instance = $tarefalv->get_instance();
        $instance->instance = $instance->id;
        $instance->intro = $fakearealink2;
        $instance->introformat = FORMAT_HTML;
        $tarefalv->update_instance($instance);
        $params = array('modulename' => 'tarefalv', 'instance' => $tarefalv->get_instance()->id);
        $event = $DB->get_record('event', $params);
        $this->assertNotEmpty($event);
        $this->assertSame('new', $event->description);     // The pluginfile links are removed.

        // Create an assignment with a description that should be hidden.
        $tarefalv = $this->create_instance(array('duedate'=>$now + 160,
                                               'alwaysshowdescription'=>false,
                                               'allowsubmissionsfromdate'=>$now + 60,
                                               'intro'=>'Some text'));

        // Get the event from the calendar.
        $params = array('modulename'=>'tarefalv', 'instance'=>$tarefalv->get_instance()->id);
        $event = $DB->get_record('event', $params);

        $this->assertEmpty($event->description);

        // Change the allowsubmissionfromdate to the past - do this directly in the DB
        // because if we call the assignment update method - it will update the calendar
        // and we want to test that this works from cron.
        $DB->set_field('tarefalv', 'allowsubmissionsfromdate', $now - 60, array('id'=>$tarefalv->get_instance()->id));
        // Run cron to update the event in the calendar.
        tarefalv::cron();
        $event = $DB->get_record('event', $params);

        $this->assertContains('Some text', $event->description);

    }

    public function test_update_instance() {
        global $DB;

        $this->setUser($this->editingteachers[0]);
        $tarefalv = $this->create_instance(array('tarefalvsubmission_onlinetext_enabled'=>1));

        $now = time();
        $instance = $tarefalv->get_instance();
        $instance->duedate = $now;
        $instance->instance = $instance->id;
        $instance->tarefalvsubmission_onlinetext_enabled = 1;

        $tarefalv->update_instance($instance);

        $instance = $DB->get_record('tarefalv', array('id'=>$tarefalv->get_instance()->id));
        $this->assertEquals($now, $instance->duedate);
    }

    public function test_cannot_submit_empty() {
        global $PAGE;

        $this->setUser($this->editingteachers[0]);
        $tarefalv = $this->create_instance(array('submissiondrafts'=>1));

        $PAGE->set_url(new moodle_url('/mod/tarefalv/view.php', array('id' => $tarefalv->get_course_module()->id)));

        // Test you cannot see the submit button for an offline assignment regardless.
        $this->setUser($this->students[0]);
        $output = $tarefalv->view_student_summary($this->students[0], true);
        $this->assertNotContains(get_string('submitassignment', 'tarefalv'), $output, 'Can submit empty offline assignment');

        // Test you cannot see the submit button for an online text assignment with no submission.
        $this->setUser($this->editingteachers[0]);
        $instance = $tarefalv->get_instance();
        $instance->instance = $instance->id;
        $instance->tarefalvsubmission_onlinetext_enabled = 1;

        $tarefalv->update_instance($instance);
        $this->setUser($this->students[0]);
        $output = $tarefalv->view_student_summary($this->students[0], true);
        $this->assertNotContains(get_string('submitassignment', 'tarefalv'), $output, 'Cannot submit empty onlinetext assignment');

        // Simulate a submission.
        $submission = $tarefalv->get_user_submission($this->students[0]->id, true);
        $data = new stdClass();
        $data->onlinetext_editor = array('itemid'=>file_get_unused_draft_itemid(),
                                         'text'=>'Submission text',
                                         'format'=>FORMAT_MOODLE);
        $plugin = $tarefalv->get_submission_plugin_by_type('onlinetext');
        $plugin->save($submission, $data);
        // Test you can see the submit button for an online text assignment with a submission.
        $output = $tarefalv->view_student_summary($this->students[0], true);
        $this->assertContains(get_string('submitassignment', 'tarefalv'), $output, 'Can submit non empty onlinetext assignment');
    }

    /**
     * Test new_submission_empty
     *
     * We only test combinations of plugins here. Individual plugins are tested
     * in their respective test files.
     *
     * @dataProvider test_new_submission_empty_testcases
     * @param string $data The file submission data
     * @param bool $expected The expected return value
     */
    public function test_new_submission_empty($data, $expected) {
        $this->resetAfterTest();
        $tarefalv = $this->create_instance(['tarefalvsubmission_file_enabled' => 1,
                                          'tarefalvsubmission_file_maxfiles' => 12,
                                          'tarefalvsubmission_file_maxsizebytes' => 10,
                                          'tarefalvsubmission_onlinetext_enabled' => 1]);
        $this->setUser($this->students[0]);
        $submission = new stdClass();

        if ($data['file'] && isset($data['file']['filename'])) {
            $itemid = file_get_unused_draft_itemid();
            $submission->files_filemanager = $itemid;
            $data['file'] += ['contextid' => context_user::instance($this->students[0]->id)->id, 'itemid' => $itemid];
            $fs = get_file_storage();
            $fs->create_file_from_string((object)$data['file'], 'Content of ' . $data['file']['filename']);
        }

        if ($data['onlinetext']) {
            $submission->onlinetext_editor = ['text' => $data['onlinetext']];
        }

        $result = $tarefalv->new_submission_empty($submission);
        $this->assertTrue($result === $expected);
    }

    /**
     * Dataprovider for the test_new_submission_empty testcase
     *
     * @return array of testcases
     */
    public function test_new_submission_empty_testcases() {
        return [
            'With file and onlinetext' => [
                [
                    'file' => [
                        'component' => 'user',
                        'filearea' => 'draft',
                        'filepath' => '/',
                        'filename' => 'not_a_virus.exe'
                    ],
                    'onlinetext' => 'Balin Fundinul Uzbadkhazaddumu'
                ],
                false
            ]
        ];
    }

    public function test_list_participants() {
        global $CFG, $DB;

        $this->create_extra_users();
        $this->setUser($this->editingteachers[0]);
        $tarefalv = $this->create_instance(array('grade'=>100));

        $this->assertEquals(self::DEFAULT_STUDENT_COUNT + self::EXTRA_STUDENT_COUNT, count($tarefalv->list_participants(null, true)));

        // Teacher with user preference set should see suspended users as well.
        set_user_preference('grade_report_showonlyactiveenrol', false);
        $tarefalv = $this->create_instance(array('grade'=>100));
        $this->assertEquals(self::DEFAULT_STUDENT_COUNT + self::EXTRA_STUDENT_COUNT + self::EXTRA_SUSPENDED_COUNT,
                count($tarefalv->list_participants(null, true)));

        // Non-editing teacher should not see suspended users, even if user preference is set.
        $this->setUser($this->teachers[0]);
        set_user_preference('grade_report_showonlyactiveenrol', false);
        $tarefalv = $this->create_instance(array('grade'=>100));
        $this->assertEquals(self::DEFAULT_STUDENT_COUNT + self::EXTRA_STUDENT_COUNT, count($tarefalv->list_participants(null, true)));

        // Turn on availability and a group restriction, and check that it doesn't
        // show users who aren't in the group.
        $CFG->enableavailability = true;
        $specialgroup = $this->getDataGenerator()->create_group(
                array('courseid' => $this->course->id));
        $tarefalv = $this->create_instance(array('grade' => 100,
                'availability' => json_encode(\core_availability\tree::get_root_json(
                    array(\availability_group\condition::get_json($specialgroup->id))))));
        groups_add_member($specialgroup, $this->students[0]);
        groups_add_member($specialgroup, $this->students[1]);
        $this->assertEquals(2, count($tarefalv->list_participants(null, true)));
    }

    public function test_get_participant_user_not_exist() {
        $tarefalv = $this->create_instance(array('grade' => 100));
        $this->assertNull($tarefalv->get_participant('-1'));
    }

    public function test_get_participant_not_enrolled() {
        $tarefalv = $this->create_instance(array('grade' => 100));
        $user = $this->getDataGenerator()->create_user();
        $this->assertNull($tarefalv->get_participant($user->id));
    }

    public function test_get_participant_no_submission() {
        $tarefalv = $this->create_instance(array('grade' => 100));
        $student = $this->students[0];
        $participant = $tarefalv->get_participant($student->id);

        $this->assertEquals($student->id, $participant->id);
        $this->assertFalse($participant->submitted);
        $this->assertFalse($participant->requiregrading);
        $this->assertFalse($participant->grantedextension);
    }

    public function test_get_participant_granted_extension() {
        $tarefalv = $this->create_instance(array('grade' => 100));
        $student = $this->students[0];
        $this->setUser($this->editingteachers[0]);
        $tarefalv->save_user_extension($student->id, time());
        $participant = $tarefalv->get_participant($student->id);

        $this->assertEquals($student->id, $participant->id);
        $this->assertFalse($participant->submitted);
        $this->assertFalse($participant->requiregrading);
        $this->assertTrue($participant->grantedextension);
    }

    public function test_get_participant_with_ungraded_submission() {
        $tarefalv = $this->create_instance(array('grade' => 100));
        $student = $this->students[0];
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_tarefalv');

        $this->setUser($student);

        // Simulate a submission.
        $data = new stdClass();
        $data->onlinetext_editor = array(
            'itemid' => file_get_unused_draft_itemid(),
            'text' => 'Student submission text',
            'format' => FORMAT_MOODLE
        );

        $notices = array();
        $tarefalv->save_submission($data, $notices);

        $data = new stdClass;
        $data->userid = $student->id;
        $tarefalv->submit_for_grading($data, array());

        $participant = $tarefalv->get_participant($student->id);

        $this->assertEquals($student->id, $participant->id);
        $this->assertTrue($participant->submitted);
        $this->assertTrue($participant->requiregrading);
        $this->assertFalse($participant->grantedextension);
    }

    public function test_get_participant_with_graded_submission() {
        $tarefalv = $this->create_instance(array('grade' => 100));
        $student = $this->students[0];
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_tarefalv');

        $this->setUser($student);

        // Simulate a submission.
        $data = new stdClass();
        $data->onlinetext_editor = array(
            'itemid' => file_get_unused_draft_itemid(),
            'text' => 'Student submission text',
            'format' => FORMAT_MOODLE
        );

        $notices = array();
        $tarefalv->save_submission($data, $notices);

        $data = new stdClass;
        $data->userid = $student->id;
        $tarefalv->submit_for_grading($data, array());

        // This is to make sure the grade happens after the submission because
        // we have no control over the timemodified values.
        $this->waitForSecond();
        // Grade the submission.
        $this->setUser($this->teachers[0]);

        $data = new stdClass();
        $data->grade = '50.0';
        $tarefalv->testable_apply_grade_to_user($data, $student->id, 0);

        $participant = $tarefalv->get_participant($student->id);

        $this->assertEquals($student->id, $participant->id);
        $this->assertTrue($participant->submitted);
        $this->assertFalse($participant->requiregrading);
        $this->assertFalse($participant->grantedextension);
    }

    public function test_count_teams() {
        $this->create_extra_users();
        $this->setUser($this->editingteachers[0]);
        $tarefalv1 = $this->create_instance(array('teamsubmission' => 1));
        $this->assertEquals(self::GROUP_COUNT + 1, $tarefalv1->count_teams());

        $grouping = $this->getDataGenerator()->create_grouping(array('courseid' => $this->course->id));
        $this->getDataGenerator()->create_grouping_group(array('groupid' => $this->groups[0]->id, 'groupingid' => $grouping->id));
        $this->getDataGenerator()->create_grouping_group(array('groupid' => $this->groups[1]->id, 'groupingid' => $grouping->id));

        // No active group and non group submissions allowed => 2 groups + the default one.
        $params = array(
            'teamsubmission' => 1,
            'teamsubmissiongroupingid' => $grouping->id,
            'preventsubmissionnotingroup' => false
        );
        $tarefalv2 = $this->create_instance($params);
        $this->assertEquals(3, $tarefalv2->count_teams());

        // An active group => Just the selected one.
        $this->assertEquals(1, $tarefalv2->count_teams($this->groups[0]->id));

        // No active group and non group submissions allowed => 2 groups + no default one.
        $params = array('teamsubmission' => 1, 'teamsubmissiongroupingid' => $grouping->id, 'preventsubmissionnotingroup' => true);
        $tarefalv3 = $this->create_instance($params);
        $this->assertEquals(2, $tarefalv3->count_teams());

        $tarefalv4 = $this->create_instance(array('teamsubmission' => 1, 'preventsubmissionnotingroup' => true));
        $this->assertEquals(self::GROUP_COUNT, $tarefalv4->count_teams());
    }

    public function test_submit_to_default_group() {
        global $DB, $SESSION;

        $this->preventResetByRollback();
        $sink = $this->redirectMessages();

        $this->setUser($this->editingteachers[0]);
        $params = array('teamsubmission' => 1,
                        'tarefalvsubmission_onlinetext_enabled' => 1,
                        'submissiondrafts' => 0,
                        'groupmode' => VISIBLEGROUPS);
        $tarefalv = $this->create_instance($params);

        $newstudent = $this->getDataGenerator()->create_user();
        $studentrole = $DB->get_record('role', array('shortname'=>'student'));
        $this->getDataGenerator()->enrol_user($newstudent->id,
                                              $this->course->id,
                                              $studentrole->id);
        $this->setUser($newstudent);
        $data = new stdClass();
        $data->onlinetext_editor = array('itemid'=>file_get_unused_draft_itemid(),
                                         'text'=>'Submission text',
                                         'format'=>FORMAT_MOODLE);
        $notices = array();

        $group = $tarefalv->get_submission_group($newstudent->id);
        $this->assertFalse($group, 'New student is in default group');
        $tarefalv->save_submission($data, $notices);
        $this->assertEmpty($notices, 'No errors on save submission');

        // Set active groups to all groups.
        $this->setUser($this->editingteachers[0]);
        $SESSION->activegroup[$this->course->id]['aag'][0] = 0;
        $this->assertEquals(1, $tarefalv->count_submissions_with_status(TAREFALV_SUBMISSION_STATUS_SUBMITTED));

        // Set an active group.
        $anothergroup = $this->groups[0];
        $SESSION->activegroup[$this->course->id]['aag'][0] = (int)$anothergroup->id;
        $this->assertEquals(0, $tarefalv->count_submissions_with_status(TAREFALV_SUBMISSION_STATUS_SUBMITTED));

        $sink->close();
    }

    public function test_count_submissions() {
        global $SESSION;

        $this->create_extra_users();
        $this->setUser($this->editingteachers[0]);
        $tarefalv1 = $this->create_instance(array('tarefalvsubmission_onlinetext_enabled' => 1));

        // Simulate a submission.
        $this->setUser($this->extrastudents[0]);
        $submission = $tarefalv1->get_user_submission($this->extrastudents[0]->id, true);
        $submission->status = TAREFALV_SUBMISSION_STATUS_DRAFT;
        $tarefalv1->testable_update_submission($submission, $this->extrastudents[0]->id, true, false);
        // Leave this one as DRAFT.
        $data = new stdClass();
        $data->onlinetext_editor = array('itemid'=>file_get_unused_draft_itemid(),
                                         'text'=>'Submission text',
                                         'format'=>FORMAT_MOODLE);
        $plugin = $tarefalv1->get_submission_plugin_by_type('onlinetext');
        $plugin->save($submission, $data);

        // Simulate adding a grade.
        $this->setUser($this->teachers[0]);
        $data = new stdClass();
        $data->grade = '50.0';
        $tarefalv1->testable_apply_grade_to_user($data, $this->extrastudents[0]->id, 0);

        // Simulate a submission.
        $this->setUser($this->extrastudents[1]);
        $submission = $tarefalv1->get_user_submission($this->extrastudents[1]->id, true);
        $submission->status = TAREFALV_SUBMISSION_STATUS_SUBMITTED;
        $tarefalv1->testable_update_submission($submission, $this->extrastudents[1]->id, true, false);
        $data = new stdClass();
        $data->onlinetext_editor = array('itemid'=>file_get_unused_draft_itemid(),
                                         'text'=>'Submission text',
                                         'format'=>FORMAT_MOODLE);
        $plugin = $tarefalv1->get_submission_plugin_by_type('onlinetext');
        $plugin->save($submission, $data);

        // Simulate a submission.
        $this->setUser($this->extrastudents[2]);
        $submission = $tarefalv1->get_user_submission($this->extrastudents[2]->id, true);
        $submission->status = TAREFALV_SUBMISSION_STATUS_SUBMITTED;
        $tarefalv1->testable_update_submission($submission, $this->extrastudents[2]->id, true, false);
        $data = new stdClass();
        $data->onlinetext_editor = array('itemid'=>file_get_unused_draft_itemid(),
                                         'text'=>'Submission text',
                                         'format'=>FORMAT_MOODLE);
        $plugin = $tarefalv1->get_submission_plugin_by_type('onlinetext');
        $plugin->save($submission, $data);

        // Simulate a submission.
        $this->setUser($this->extrastudents[3]);
        $submission = $tarefalv1->get_user_submission($this->extrastudents[3]->id, true);
        $submission->status = TAREFALV_SUBMISSION_STATUS_SUBMITTED;
        $tarefalv1->testable_update_submission($submission, $this->extrastudents[3]->id, true, false);
        $data = new stdClass();
        $data->onlinetext_editor = array('itemid'=>file_get_unused_draft_itemid(),
                                         'text'=>'Submission text',
                                         'format'=>FORMAT_MOODLE);
        $plugin = $tarefalv1->get_submission_plugin_by_type('onlinetext');
        $plugin->save($submission, $data);

        // Simulate a submission for suspended user, this will never be counted.
        $this->setUser($this->extrastudents[3]);
        $submission = $tarefalv1->get_user_submission($this->extrasuspendedstudents[0]->id, true);
        $submission->status = TAREFALV_SUBMISSION_STATUS_SUBMITTED;
        $tarefalv1->testable_update_submission($submission, $this->extrasuspendedstudents[0]->id, true, false);
        $data = new stdClass();
        $data->onlinetext_editor = array('itemid'=>file_get_unused_draft_itemid(),
                                         'text'=>'Submission text',
                                         'format'=>FORMAT_MOODLE);
        $plugin = $tarefalv1->get_submission_plugin_by_type('onlinetext');
        $plugin->save($submission, $data);

        // Wait 1 second so the submission and grade do not have the same timemodified.
        $this->waitForSecond();
        // Simulate adding a grade.
        $this->setUser($this->editingteachers[0]);
        $data = new stdClass();
        $data->grade = '50.0';
        $tarefalv1->testable_apply_grade_to_user($data, $this->extrastudents[3]->id, 0);
        $tarefalv1->testable_apply_grade_to_user($data, $this->extrasuspendedstudents[0]->id, 0);

        // Create a new submission with status NEW.
        $this->setUser($this->extrastudents[4]);
        $submission = $tarefalv1->get_user_submission($this->extrastudents[4]->id, true);

        $this->assertEquals(2, $tarefalv1->count_grades());
        $this->assertEquals(4, $tarefalv1->count_submissions());
        $this->assertEquals(5, $tarefalv1->count_submissions(true));
        $this->assertEquals(2, $tarefalv1->count_submissions_need_grading());
        $this->assertEquals(3, $tarefalv1->count_submissions_with_status(TAREFALV_SUBMISSION_STATUS_SUBMITTED));
        $this->assertEquals(1, $tarefalv1->count_submissions_with_status(TAREFALV_SUBMISSION_STATUS_DRAFT));

        // Groups.
        $tarefalv2 = $this->create_instance(array(
            'tarefalvsubmission_onlinetext_enabled' => 1,
            'groupmode' => VISIBLEGROUPS
        ));

        $this->setUser($this->extrastudents[1]);
        $submission = $tarefalv2->get_user_submission($this->extrastudents[1]->id, true);
        $submission->status = TAREFALV_SUBMISSION_STATUS_SUBMITTED;
        $tarefalv2->testable_update_submission($submission, $this->extrastudents[1]->id, true, false);
        $data = new stdClass();
        $data->onlinetext_editor = array('itemid' => file_get_unused_draft_itemid(),
                                         'text' => 'Submission text',
                                         'format' => FORMAT_MOODLE);
        $plugin = $tarefalv2->get_submission_plugin_by_type('onlinetext');
        $plugin->save($submission, $data);

        $this->assertEquals(1, $tarefalv2->count_submissions_with_status(TAREFALV_SUBMISSION_STATUS_SUBMITTED));

        // Set active groups to all groups.
        $this->setUser($this->editingteachers[0]);
        $SESSION->activegroup[$this->course->id]['aag'][0] = 0;
        $this->assertEquals(1, $tarefalv2->count_submissions_with_status(TAREFALV_SUBMISSION_STATUS_SUBMITTED));

        // Set the user group.
        $studentgroups = groups_get_user_groups($this->course->id, $this->extrastudents[1]->id);
        $this->assertEquals(1, count($studentgroups));
        $studentgroup = array_pop($studentgroups);
        $SESSION->activegroup[$this->course->id]['aag'][0] = $studentgroup[0];
        $this->assertEquals(1, $tarefalv2->count_submissions_with_status(TAREFALV_SUBMISSION_STATUS_SUBMITTED));

        // Set another group.
        $anothergroup = $this->groups[0];
        $this->assertNotEquals($anothergroup->id, $studentgroup[0]);
        $SESSION->activegroup[$this->course->id]['aag'][0] = (int)$anothergroup->id;
        $this->assertEquals(0, $tarefalv2->count_submissions_with_status(TAREFALV_SUBMISSION_STATUS_SUBMITTED));
    }

    public function test_count_submissions_for_groups() {
        $this->create_extra_users();
        $groupid = null;
        $this->setUser($this->editingteachers[0]);
        $tarefalv = $this->create_instance(array('tarefalvsubmission_onlinetext_enabled' => 1, 'teamsubmission' => 1));

        // Simulate a submission.
        $this->setUser($this->extrastudents[0]);
        $submission = $tarefalv->get_group_submission($this->extrastudents[0]->id, $groupid, true);
        $submission->status = TAREFALV_SUBMISSION_STATUS_DRAFT;
        $tarefalv->testable_update_submission($submission, $this->extrastudents[0]->id, true, false);
        // Leave this one as DRAFT.
        $data = new stdClass();
        $data->onlinetext_editor = array('itemid' => file_get_unused_draft_itemid(),
                                         'text' => 'Submission text',
                                         'format' => FORMAT_MOODLE);
        $plugin = $tarefalv->get_submission_plugin_by_type('onlinetext');
        $plugin->save($submission, $data);

        // Simulate adding a grade.
        $this->setUser($this->teachers[0]);
        $data = new stdClass();
        $data->grade = '50.0';
        $tarefalv->testable_apply_grade_to_user($data, $this->extrastudents[0]->id, 0);

        // Simulate a submission.
        $this->setUser($this->extrastudents[1]);
        $submission = $tarefalv->get_group_submission($this->extrastudents[1]->id, $groupid, true);
        $submission->status = TAREFALV_SUBMISSION_STATUS_SUBMITTED;
        $tarefalv->testable_update_submission($submission, $this->extrastudents[1]->id, true, false);
        $data = new stdClass();
        $data->onlinetext_editor = array('itemid' => file_get_unused_draft_itemid(),
                                         'text' => 'Submission text',
                                         'format' => FORMAT_MOODLE);
        $plugin = $tarefalv->get_submission_plugin_by_type('onlinetext');
        $plugin->save($submission, $data);

        // Simulate a submission.
        $this->setUser($this->extrastudents[2]);
        $submission = $tarefalv->get_group_submission($this->extrastudents[2]->id, $groupid, true);
        $submission->status = TAREFALV_SUBMISSION_STATUS_SUBMITTED;
        $tarefalv->testable_update_submission($submission, $this->extrastudents[2]->id, true, false);
        $data = new stdClass();
        $data->onlinetext_editor = array('itemid' => file_get_unused_draft_itemid(),
                                         'text' => 'Submission text',
                                         'format' => FORMAT_MOODLE);
        $plugin = $tarefalv->get_submission_plugin_by_type('onlinetext');
        $plugin->save($submission, $data);

        // Simulate a submission.
        $this->setUser($this->extrastudents[3]);
        $submission = $tarefalv->get_group_submission($this->extrastudents[3]->id, $groupid, true);
        $submission->status = TAREFALV_SUBMISSION_STATUS_SUBMITTED;
        $tarefalv->testable_update_submission($submission, $this->extrastudents[3]->id, true, false);
        $data = new stdClass();
        $data->onlinetext_editor = array('itemid' => file_get_unused_draft_itemid(),
                                         'text' => 'Submission text',
                                         'format' => FORMAT_MOODLE);
        $plugin = $tarefalv->get_submission_plugin_by_type('onlinetext');
        $plugin->save($submission, $data);

        // Simulate adding a grade.
        $this->setUser($this->editingteachers[0]);
        $data = new stdClass();
        $data->grade = '50.0';
        $tarefalv->testable_apply_grade_to_user($data, $this->extrastudents[3]->id, 0);
        $tarefalv->testable_apply_grade_to_user($data, $this->extrasuspendedstudents[0]->id, 0);

        // Create a new submission with status NEW.
        $this->setUser($this->extrastudents[4]);
        $submission = $tarefalv->get_group_submission($this->extrastudents[4]->id, $groupid, true);

        $this->assertEquals(2, $tarefalv->count_grades());
        $this->assertEquals(4, $tarefalv->count_submissions());
        $this->assertEquals(5, $tarefalv->count_submissions(true));
        $this->assertEquals(3, $tarefalv->count_submissions_with_status(TAREFALV_SUBMISSION_STATUS_SUBMITTED));
        $this->assertEquals(1, $tarefalv->count_submissions_with_status(TAREFALV_SUBMISSION_STATUS_DRAFT));
    }

    public function test_get_grading_userid_list() {
        $this->create_extra_users();
        $this->setUser($this->editingteachers[0]);
        $tarefalv = $this->create_instance();

        $users = $tarefalv->testable_get_grading_userid_list();
        $this->assertEquals(self::DEFAULT_STUDENT_COUNT + self::EXTRA_STUDENT_COUNT, count($users));

        $this->setUser($this->editingteachers[0]);
        set_user_preference('grade_report_showonlyactiveenrol', false);
        $tarefalv = $this->create_instance();

        $users = $tarefalv->testable_get_grading_userid_list();
        $this->assertEquals(self::DEFAULT_STUDENT_COUNT + self::EXTRA_STUDENT_COUNT + self::EXTRA_SUSPENDED_COUNT, count($users));
    }

    public function test_cron() {
        // First run cron so there are no messages waiting to be sent (from other tests).
        cron_setup_user();
        tarefalv::cron();

        // Now create an assignment and add some feedback.
        $this->setUser($this->editingteachers[0]);
        $tarefalv = $this->create_instance(array('sendstudentnotifications'=>1));

        // Simulate adding a grade.
        $this->setUser($this->teachers[0]);
        $data = new stdClass();
        $data->grade = '50.0';
        $tarefalv->testable_apply_grade_to_user($data, $this->students[0]->id, 0);
        $tarefalv->testable_apply_grade_to_user($data, $this->students[1]->id, 0);

        $data->sendstudentnotifications = false;
        $tarefalv->testable_apply_grade_to_user($data, $this->students[2]->id, 0);

        // Now run cron and see that one message was sent.
        $this->preventResetByRollback();
        $sink = $this->redirectMessages();
        cron_setup_user();
        $this->expectOutputRegex('/Done processing 2 assignment submissions/');
        tarefalv::cron();

        $messages = $sink->get_messages();
        // The sent count should be 2, because the 3rd one was marked as do not send notifications.
        $this->assertEquals(2, count($messages));
        $this->assertEquals(1, $messages[0]->notification);
        $this->assertEquals($tarefalv->get_instance()->name, $messages[0]->contexturlname);

        // Regrading a grade causes a notification to the user.
        $data->sendstudentnotifications = true;
        $tarefalv->testable_apply_grade_to_user($data, $this->students[0]->id, 0);
        tarefalv::cron();
        $messages = $sink->get_messages();
        $this->assertEquals(3, count($messages));
    }

    /**
     * Test delivery of grade notifications as controlled by marking workflow.
     */
    public function test_markingworkflow_cron() {
        // First run cron so there are no messages waiting to be sent (from other tests).
        cron_setup_user();
        tarefalv::cron();

        // Now create an assignment with marking workflow enabled.
        $this->setUser($this->editingteachers[0]);
        $tarefalv = $this->create_instance(array('sendstudentnotifications' => 1, 'markingworkflow' => 1));

        // Simulate adding a grade.
        $this->setUser($this->teachers[0]);
        $data = new stdClass();
        $data->grade = '50.0';

        // This student will not receive notification.
        $data->sendstudentnotifications = 1;
        $data->workflowstate = TAREFALV_MARKING_WORKFLOW_STATE_READYFORRELEASE;
        $tarefalv->testable_apply_grade_to_user($data, $this->students[0]->id, 0);

        // This student will receive notification.
        $data->sendstudentnotifications = 1;
        $data->workflowstate = TAREFALV_MARKING_WORKFLOW_STATE_RELEASED;
        $tarefalv->testable_apply_grade_to_user($data, $this->students[1]->id, 0);

        // Now run cron and see that one message was sent.
        $this->preventResetByRollback();
        $sink = $this->redirectMessages();
        cron_setup_user();
        $this->expectOutputRegex('/Done processing 1 assignment submissions/');
        tarefalv::cron();

        $messages = $sink->get_messages();
        $this->assertEquals(1, count($messages));
        $this->assertEquals($messages[0]->useridto, $this->students[1]->id);
        $this->assertEquals($tarefalv->get_instance()->name, $messages[0]->contexturlname);
    }

    public function test_cron_message_includes_courseid() {
        // First run cron so there are no messages waiting to be sent (from other tests).
        cron_setup_user();
        tarefalv::cron();

        // Now create an assignment.
        $this->setUser($this->editingteachers[0]);
        $tarefalv = $this->create_instance(array('sendstudentnotifications' => 1));

        // Simulate adding a grade.
        $this->setUser($this->teachers[0]);
        $data = new stdClass();
        $data->grade = '50.0';
        $tarefalv->testable_apply_grade_to_user($data, $this->students[0]->id, 0);

        $this->preventResetByRollback();
        $sink = $this->redirectEvents();
        $this->expectOutputRegex('/Done processing 1 assignment submissions/');

        tarefalv::cron();

        $events = $sink->get_events();
        // Two messages are sent, one to student and one to teacher. This generates
        // four events:
        // core\event\message_sent
        // core\event\message_viewed
        // core\event\message_sent
        // core\event\message_viewed.
        $event = reset($events);
        $this->assertInstanceOf('\core\event\message_sent', $event);
        $this->assertEquals($tarefalv->get_course()->id, $event->other['courseid']);
        $sink->close();
    }

    public function test_is_graded() {
        $this->setUser($this->editingteachers[0]);
        $tarefalv = $this->create_instance();

        // Simulate adding a grade.
        $this->setUser($this->teachers[0]);
        $data = new stdClass();
        $data->grade = '50.0';
        $tarefalv->testable_apply_grade_to_user($data, $this->students[0]->id, 0);

        $this->assertEquals(true, $tarefalv->testable_is_graded($this->students[0]->id));
        $this->assertEquals(false, $tarefalv->testable_is_graded($this->students[1]->id));
    }

    public function test_can_grade() {
        global $DB;

        $this->setUser($this->editingteachers[0]);
        $tarefalv = $this->create_instance();

        $this->setUser($this->students[0]);
        $this->assertEquals(false, $tarefalv->can_grade());
        $this->setUser($this->editingteachers[0]);
        $this->assertEquals(true, $tarefalv->can_grade());
        $this->setUser($this->teachers[0]);
        $this->assertEquals(true, $tarefalv->can_grade());

        // Test the viewgrades capability - without mod/tarefalv:grade.
        $this->setUser($this->students[0]);
        $studentrole = $DB->get_record('role', array('shortname' => 'student'));
        tarefalv_capability('mod/tarefalv:viewgrades', CAP_ALLOW, $studentrole->id, $tarefalv->get_context()->id);
        $this->assertEquals(false, $tarefalv->can_grade());
    }

    public function test_can_view_submission() {
        global $DB;

        $this->create_extra_users();
        $this->setUser($this->editingteachers[0]);
        $tarefalv = $this->create_instance();

        $this->setUser($this->students[0]);
        $this->assertEquals(true, $tarefalv->can_view_submission($this->students[0]->id));
        $this->assertEquals(false, $tarefalv->can_view_submission($this->students[1]->id));
        $this->assertEquals(false, $tarefalv->can_view_submission($this->teachers[0]->id));
        $this->setUser($this->teachers[0]);
        $this->assertEquals(true, $tarefalv->can_view_submission($this->students[0]->id));
        $this->assertEquals(true, $tarefalv->can_view_submission($this->students[1]->id));
        $this->assertEquals(true, $tarefalv->can_view_submission($this->teachers[0]->id));
        $this->assertEquals(false, $tarefalv->can_view_submission($this->extrasuspendedstudents[0]->id));
        $this->setUser($this->editingteachers[0]);
        $this->assertEquals(true, $tarefalv->can_view_submission($this->students[0]->id));
        $this->assertEquals(true, $tarefalv->can_view_submission($this->students[1]->id));
        $this->assertEquals(true, $tarefalv->can_view_submission($this->teachers[0]->id));
        $this->assertEquals(true, $tarefalv->can_view_submission($this->extrasuspendedstudents[0]->id));

        // Test the viewgrades capability - without mod/tarefalv:grade.
        $this->setUser($this->students[0]);
        $studentrole = $DB->get_record('role', array('shortname' => 'student'));
        tarefalv_capability('mod/tarefalv:viewgrades', CAP_ALLOW, $studentrole->id, $tarefalv->get_context()->id);
        $this->assertEquals(true, $tarefalv->can_view_submission($this->students[0]->id));
        $this->assertEquals(true, $tarefalv->can_view_submission($this->students[1]->id));
        $this->assertEquals(true, $tarefalv->can_view_submission($this->teachers[0]->id));
        $this->assertEquals(false, $tarefalv->can_view_submission($this->extrasuspendedstudents[0]->id));
    }


    public function test_update_submission() {
        $this->create_extra_users();
        $this->setUser($this->editingteachers[0]);
        $tarefalv = $this->create_instance();

        $this->setUser($this->extrastudents[0]);
        $now = time();
        $submission = $tarefalv->get_user_submission($this->extrastudents[0]->id, true);
        $tarefalv->testable_update_submission($submission, $this->extrastudents[0]->id, true, false);

        $this->setUser($this->teachers[0]);
        // Verify the gradebook update.
        $gradinginfo = grade_get_grades($this->course->id,
                                        'mod',
                                        'tarefalv',
                                        $tarefalv->get_instance()->id,
                                        $this->extrastudents[0]->id);

        $this->assertEquals($this->extrastudents[0]->id,
                            $gradinginfo->items[0]->grades[$this->extrastudents[0]->id]->usermodified);

        // Now verify group assignments.
        $this->setUser($this->editingteachers[0]);
        $tarefalv = $this->create_instance(array('teamsubmission'=>1));

        $this->setUser($this->extrastudents[0]);
        $now = time();
        $submission = $tarefalv->get_group_submission($this->extrastudents[0]->id, 0, true);
        $tarefalv->testable_update_submission($submission, $this->extrastudents[0]->id, true, true);

        // Check that at least 2 active members and 1 suspended member of the submission group had their submission updated.

        $this->setUser($this->editingteachers[0]);
        $gradinginfo = grade_get_grades($this->course->id,
                                        'mod',
                                        'tarefalv',
                                        $tarefalv->get_instance()->id,
                                        $this->extrastudents[0]->id);

        $this->assertEquals($this->extrastudents[0]->id,
                            $gradinginfo->items[0]->grades[$this->extrastudents[0]->id]->usermodified);

        $gradinginfo = grade_get_grades($this->course->id,
                                        'mod',
                                        'tarefalv',
                                        $tarefalv->get_instance()->id,
                                        $this->extrastudents[self::GROUP_COUNT]->id);

        $this->assertEquals($this->extrastudents[self::GROUP_COUNT]->id,
                            $gradinginfo->items[0]->grades[$this->extrastudents[self::GROUP_COUNT]->id]->usermodified);

        $gradinginfo = grade_get_grades($this->course->id,
                                        'mod',
                                        'tarefalv',
                                        $tarefalv->get_instance()->id,
                                        $this->extrasuspendedstudents[0]->id);
        $this->assertEquals($this->extrasuspendedstudents[0]->id,
                            $gradinginfo->items[0]->grades[$this->extrasuspendedstudents[0]->id]->usermodified);

        // Check the same with non-editing teacher and make sure submission is not updated for suspended user.
        $this->setUser($this->editingteachers[0]);
        $tarefalv = $this->create_instance(array('teamsubmission'=>1));

        $this->setUser($this->extrastudents[1]);
        $now = time();
        $submission = $tarefalv->get_group_submission($this->extrastudents[1]->id, 0, true);
        $tarefalv->testable_update_submission($submission, $this->extrastudents[1]->id, true, true);

        $this->setUser($this->teachers[0]);
        $gradinginfo = grade_get_grades($this->course->id,
                                        'mod',
                                        'tarefalv',
                                        $tarefalv->get_instance()->id,
                                        $this->extrastudents[1]->id);

        $this->assertEquals($this->extrastudents[1]->id,
                            $gradinginfo->items[0]->grades[$this->extrastudents[1]->id]->usermodified);

        $gradinginfo = grade_get_grades($this->course->id,
                                        'mod',
                                        'tarefalv',
                                        $tarefalv->get_instance()->id,
                                        $this->extrastudents[self::GROUP_COUNT+1]->id);

        $this->assertEquals($this->extrastudents[self::GROUP_COUNT+1]->id,
                            $gradinginfo->items[0]->grades[$this->extrastudents[self::GROUP_COUNT+1]->id]->usermodified);

        $gradinginfo = grade_get_grades($this->course->id,
                                        'mod',
                                        'tarefalv',
                                        $tarefalv->get_instance()->id,
                                        $this->extrasuspendedstudents[1]->id);
        $this->assertEquals($this->extrasuspendedstudents[1]->id,
                            $gradinginfo->items[0]->grades[$this->extrasuspendedstudents[1]->id]->usermodified);

        // Now verify blind marking.
        $this->setUser($this->editingteachers[0]);
        $tarefalv = $this->create_instance(array('blindmarking'=>1));

        $this->setUser($this->extrastudents[0]);
        $now = time();
        $submission = $tarefalv->get_user_submission($this->extrastudents[0]->id, true);
        $tarefalv->testable_update_submission($submission, $this->extrastudents[0]->id, true, false);

        $this->setUser($this->editingteachers[0]);
        $gradinginfo = grade_get_grades($this->course->id,
                                        'mod',
                                        'tarefalv',
                                        $tarefalv->get_instance()->id,
                                        $this->extrastudents[0]->id);

        $this->assertEquals(null, $gradinginfo->items[0]->grades[$this->extrastudents[0]->id]->datesubmitted);
    }

    public function test_group_submissions_submit_for_marking_requireallteammemberssubmit() {
        global $PAGE;

        $this->create_extra_users();
        // Now verify group assignments.
        $this->setUser($this->editingteachers[0]);
        $tarefalv = $this->create_instance(array('teamsubmission'=>1,
                                               'tarefalvsubmission_onlinetext_enabled'=>1,
                                               'submissiondrafts'=>1,
                                               'requireallteammemberssubmit'=>1));
        $PAGE->set_url(new moodle_url('/mod/tarefalv/view.php', array('id' => $tarefalv->get_course_module()->id)));

        // Add a submission.
        $this->setUser($this->extrastudents[0]);
        $data = new stdClass();
        $data->onlinetext_editor = array('itemid'=>file_get_unused_draft_itemid(),
                                         'text'=>'Submission text',
                                         'format'=>FORMAT_MOODLE);

        $notices = array();
        $tarefalv->save_submission($data, $notices);

        // Check we can see the submit button.
        $output = $tarefalv->view_student_summary($this->extrastudents[0], true);
        $this->assertContains(get_string('submitassignment', 'tarefalv'), $output);

        $submission = $tarefalv->get_group_submission($this->extrastudents[0]->id, 0, true);
        $submission->status = TAREFALV_SUBMISSION_STATUS_SUBMITTED;
        $tarefalv->testable_update_submission($submission, $this->extrastudents[0]->id, true, true);

        // Check that the student does not see "Submit" button.
        $output = $tarefalv->view_student_summary($this->extrastudents[0], true);
        $this->assertNotContains(get_string('submitassignment', 'tarefalv'), $output);

        // Change to another user in the same group.
        $this->setUser($this->extrastudents[self::GROUP_COUNT]);
        $output = $tarefalv->view_student_summary($this->extrastudents[self::GROUP_COUNT], true);
        $this->assertContains(get_string('submitassignment', 'tarefalv'), $output);

        $submission = $tarefalv->get_group_submission($this->extrastudents[self::GROUP_COUNT]->id, 0, true);
        $submission->status = TAREFALV_SUBMISSION_STATUS_SUBMITTED;
        $tarefalv->testable_update_submission($submission, $this->extrastudents[self::GROUP_COUNT]->id, true, true);
        $output = $tarefalv->view_student_summary($this->extrastudents[self::GROUP_COUNT], true);
        $this->assertNotContains(get_string('submitassignment', 'tarefalv'), $output);
    }

    public function test_group_submissions_submit_for_marking() {
        global $PAGE;

        $this->create_extra_users();
        // Now verify group assignments.
        $this->setUser($this->editingteachers[0]);
        $time = time();
        $tarefalv = $this->create_instance(array('teamsubmission'=>1,
                                               'tarefalvsubmission_onlinetext_enabled'=>1,
                                               'submissiondrafts'=>1,
                                               'requireallteammemberssubmit'=>0,
                                               'duedate' => $time - 2*24*60*60));
        $PAGE->set_url(new moodle_url('/mod/tarefalv/view.php', array('id' => $tarefalv->get_course_module()->id)));

        $this->setUser($this->extrastudents[0]);
        // Add a submission.
        $data = new stdClass();
        $data->onlinetext_editor = array('itemid'=>file_get_unused_draft_itemid(),
                                         'text'=>'Submission text',
                                         'format'=>FORMAT_MOODLE);

        $notices = array();
        $tarefalv->save_submission($data, $notices);

        // Check we can see the submit button.
        $output = $tarefalv->view_student_summary($this->extrastudents[0], true);
        $this->assertContains(get_string('submitassignment', 'tarefalv'), $output);
        $this->assertContains(get_string('timeremaining', 'tarefalv'), $output);
        $difftime = time() - $time;
        $this->assertContains(get_string('overdue', 'tarefalv', format_time(2*24*60*60 + $difftime)), $output);

        $submission = $tarefalv->get_group_submission($this->extrastudents[0]->id, 0, true);
        $submission->status = TAREFALV_SUBMISSION_STATUS_SUBMITTED;
        $tarefalv->testable_update_submission($submission, $this->extrastudents[0]->id, true, true);

        // Check that the student does not see "Submit" button.
        $output = $tarefalv->view_student_summary($this->extrastudents[0], true);
        $this->assertNotContains(get_string('submitassignment', 'tarefalv'), $output);

        // Change to another user in the same group.
        $this->setUser($this->extrastudents[self::GROUP_COUNT]);
        $output = $tarefalv->view_student_summary($this->extrastudents[self::GROUP_COUNT], true);
        $this->assertNotContains(get_string('submitassignment', 'tarefalv'), $output);

        // Check that time remaining is not overdue.
        $this->assertContains(get_string('timeremaining', 'tarefalv'), $output);
        $difftime = time() - $time;
        $this->assertContains(get_string('submittedlate', 'tarefalv', format_time(2*24*60*60 + $difftime)), $output);

        $submission = $tarefalv->get_group_submission($this->extrastudents[self::GROUP_COUNT]->id, 0, true);
        $submission->status = TAREFALV_SUBMISSION_STATUS_SUBMITTED;
        $tarefalv->testable_update_submission($submission, $this->extrastudents[self::GROUP_COUNT]->id, true, true);
        $output = $tarefalv->view_student_summary($this->extrastudents[self::GROUP_COUNT], true);
        $this->assertNotContains(get_string('submitassignment', 'tarefalv'), $output);
    }

    public function test_submissions_open() {
        $this->setUser($this->editingteachers[0]);

        $now = time();
        $tomorrow = $now + 24*60*60;
        $oneweek = $now + 7*24*60*60;
        $yesterday = $now - 24*60*60;

        $tarefalv = $this->create_instance();
        $this->assertEquals(true, $tarefalv->testable_submissions_open($this->students[0]->id));

        $tarefalv = $this->create_instance(array('duedate'=>$tomorrow));
        $this->assertEquals(true, $tarefalv->testable_submissions_open($this->students[0]->id));

        $tarefalv = $this->create_instance(array('duedate'=>$yesterday));
        $this->assertEquals(true, $tarefalv->testable_submissions_open($this->students[0]->id));

        $tarefalv = $this->create_instance(array('duedate'=>$yesterday, 'cutoffdate'=>$tomorrow));
        $this->assertEquals(true, $tarefalv->testable_submissions_open($this->students[0]->id));

        $tarefalv = $this->create_instance(array('duedate'=>$yesterday, 'cutoffdate'=>$yesterday));
        $this->assertEquals(false, $tarefalv->testable_submissions_open($this->students[0]->id));

        $tarefalv->testable_save_user_extension($this->students[0]->id, $tomorrow);
        $this->assertEquals(true, $tarefalv->testable_submissions_open($this->students[0]->id));

        $tarefalv = $this->create_instance(array('submissiondrafts'=>1));
        $this->assertEquals(true, $tarefalv->testable_submissions_open($this->students[0]->id));

        $this->setUser($this->students[0]);
        $now = time();
        $submission = $tarefalv->get_user_submission($this->students[0]->id, true);
        $submission->status = TAREFALV_SUBMISSION_STATUS_SUBMITTED;
        $tarefalv->testable_update_submission($submission, $this->students[0]->id, true, false);
        $this->setUser($this->editingteachers[0]);
        $this->assertEquals(false, $tarefalv->testable_submissions_open($this->students[0]->id));
    }

    public function test_get_graders() {
        $this->create_extra_users();
        $this->setUser($this->editingteachers[0]);

        // Create an assignment with no groups.
        $tarefalv = $this->create_instance();
        $this->assertCount(self::DEFAULT_TEACHER_COUNT +
                           self::DEFAULT_EDITING_TEACHER_COUNT +
                           self::EXTRA_TEACHER_COUNT +
                           self::EXTRA_EDITING_TEACHER_COUNT,
                           $tarefalv->testable_get_graders($this->students[0]->id));

        // Force create an assignment with SEPARATEGROUPS.
        $data = new stdClass();
        $data->courseid = $this->course->id;
        $data->name = 'Grouping';
        $groupingid = groups_create_grouping($data);
        groups_tarefalv_grouping($groupingid, $this->groups[0]->id);
        $tarefalv = $this->create_instance(array('groupingid' => $groupingid, 'groupmode' => SEPARATEGROUPS));

        $this->setUser($this->students[1]);
        $this->assertCount(4, $tarefalv->testable_get_graders($this->students[0]->id));
        // Note the second student is in a group that is not in the grouping.
        // This means that we get all graders that are not in a group in the grouping.
        $this->assertCount(10, $tarefalv->testable_get_graders($this->students[1]->id));
    }

    public function test_get_notified_users() {
        global $CFG, $DB;

        $capability = 'mod/tarefalv:receivegradernotifications';
        $coursecontext = context_course::instance($this->course->id);
        $role = $DB->get_record('role', array('shortname' => 'teacher'));

        $this->create_extra_users();
        $this->setUser($this->editingteachers[0]);

        // Create an assignment with no groups.
        $tarefalv = $this->create_instance();

        $this->assertCount(self::DEFAULT_TEACHER_COUNT +
                           self::DEFAULT_EDITING_TEACHER_COUNT +
                           self::EXTRA_TEACHER_COUNT +
                           self::EXTRA_EDITING_TEACHER_COUNT,
                           $tarefalv->testable_get_notifiable_users($this->students[0]->id));

        // Change nonediting teachers role to not receive grader notifications.
        tarefalv_capability($capability, CAP_PROHIBIT, $role->id, $coursecontext);

        $this->assertCount(self::DEFAULT_EDITING_TEACHER_COUNT +
                           self::EXTRA_EDITING_TEACHER_COUNT,
                           $tarefalv->testable_get_notifiable_users($this->students[0]->id));

        // Reset nonediting teachers role to default.
        untarefalv_capability($capability, $role->id, $coursecontext);

        // Force create an assignment with SEPARATEGROUPS.
        $data = new stdClass();
        $data->courseid = $this->course->id;
        $data->name = 'Grouping';
        $groupingid = groups_create_grouping($data);
        groups_tarefalv_grouping($groupingid, $this->groups[0]->id);
        $tarefalv = $this->create_instance(array('groupingid' => $groupingid, 'groupmode' => SEPARATEGROUPS));

        $this->setUser($this->students[1]);
        $this->assertCount(4, $tarefalv->testable_get_notifiable_users($this->students[0]->id));
        // Note the second student is in a group that is not in the grouping.
        // This means that we get all graders that are not in a group in the grouping.
        $this->assertCount(10, $tarefalv->testable_get_notifiable_users($this->students[1]->id));

        // Change nonediting teachers role to not receive grader notifications.
        tarefalv_capability($capability, CAP_PROHIBIT, $role->id, $coursecontext);

        $this->assertCount(2, $tarefalv->testable_get_notifiable_users($this->students[0]->id));
        // Note the second student is in a group that is not in the grouping.
        // This means that we get all graders that are not in a group in the grouping.
        $this->assertCount(5, $tarefalv->testable_get_notifiable_users($this->students[1]->id));
    }

    public function test_group_members_only() {
        global $CFG;

        $this->setAdminUser();
        $this->create_extra_users();
        $CFG->enableavailability = true;
        $grouping = $this->getDataGenerator()->create_grouping(array('courseid' => $this->course->id));
        groups_tarefalv_grouping($grouping->id, $this->groups[0]->id);

        // Force create an assignment with SEPARATEGROUPS.
        $instance = $this->getDataGenerator()->create_module('tarefalv', array('course'=>$this->course->id),
                array('availability' => json_encode(\core_availability\tree::get_root_json(array(
                    \availability_grouping\condition::get_json()))),
                'groupingid' => $grouping->id));

        $cm = get_coursemodule_from_instance('tarefalv', $instance->id);
        $context = context_module::instance($cm->id);
        $tarefalv = new testable_tarefalv($context, $cm, $this->course);

        $this->setUser($this->teachers[0]);
        get_fast_modinfo($this->course, 0, true);
        $this->assertCount(5, $tarefalv->list_participants(0, true));

    }

    public function test_get_uniqueid_for_user() {
        $this->setUser($this->editingteachers[0]);
        $tarefalv = $this->create_instance();

        foreach ($this->students as $student) {
            $uniqueid = $tarefalv->get_uniqueid_for_user($student->id);
            $this->assertEquals($student->id, $tarefalv->get_user_id_for_uniqueid($uniqueid));
        }
    }

    public function test_show_student_summary() {
        global $CFG, $PAGE;

        $this->setUser($this->editingteachers[0]);
        $tarefalv = $this->create_instance();
        $PAGE->set_url(new moodle_url('/mod/tarefalv/view.php', array('id' => $tarefalv->get_course_module()->id)));

        // No feedback should be available because this student has not been graded.
        $this->setUser($this->students[0]);
        $output = $tarefalv->view_student_summary($this->students[0], true);
        $this->assertEquals(false, strpos($output, 'Feedback'), 'Do not show feedback if there is no grade');
        // Simulate adding a grade.
        $this->setUser($this->teachers[0]);
        $data = new stdClass();
        $data->grade = '50.0';
        $tarefalv->testable_apply_grade_to_user($data, $this->students[0]->id, 0);

        // Now we should see the feedback.
        $this->setUser($this->students[0]);
        $output = $tarefalv->view_student_summary($this->students[0], true);
        $this->assertNotEquals(false, strpos($output, 'Feedback'), 'Show feedback if there is a grade');

        // Now hide the grade in gradebook.
        $this->setUser($this->teachers[0]);
        require_once($CFG->libdir.'/gradelib.php');
        $gradeitem = new grade_item(array(
            'itemtype'      => 'mod',
            'itemmodule'    => 'tarefalv',
            'iteminstance'  => $tarefalv->get_instance()->id,
            'courseid'      => $this->course->id));

        $gradeitem->set_hidden(1, false);

        // No feedback should be available because the grade is hidden.
        $this->setUser($this->students[0]);
        $output = $tarefalv->view_student_summary($this->students[0], true);
        $this->assertEquals(false, strpos($output, 'Feedback'), 'Do not show feedback if the grade is hidden in the gradebook');

        // Do the same but add feedback.
        $tarefalv = $this->create_instance(array('tarefalvfeedback_comments_enabled' => 1));

        $this->setUser($this->teachers[0]);
        $grade = $tarefalv->get_user_grade($this->students[0]->id, true);
        $data = new stdClass();
        $data->tarefalvfeedbackcomments_editor = array('text'=>'Tomato sauce',
                                         'format'=>FORMAT_MOODLE);
        $plugin = $tarefalv->get_feedback_plugin_by_type('comments');
        $plugin->save($grade, $data);

        // Should have feedback but no grade.
        $this->setUser($this->students[0]);
        $output = $tarefalv->view_student_summary($this->students[0], true);
        $this->assertNotEquals(false, strpos($output, 'Feedback'), 'Show feedback even if there is no grade');
        $this->assertEquals(false, strpos($output, 'Grade'), 'Do not show grade when there is no grade.');
        $this->assertEquals(false, strpos($output, 'Graded on'), 'Do not show graded date when there is no grade.');

        // Now hide the grade in gradebook.
        $this->setUser($this->teachers[0]);
        $gradeitem = new grade_item(array(
            'itemtype'      => 'mod',
            'itemmodule'    => 'tarefalv',
            'iteminstance'  => $tarefalv->get_instance()->id,
            'courseid'      => $this->course->id));

        $gradeitem->set_hidden(1, false);

        // No feedback should be available because the grade is hidden.
        $this->setUser($this->students[0]);
        $output = $tarefalv->view_student_summary($this->students[0], true);
        $this->assertEquals(false, strpos($output, 'Feedback'), 'Do not show feedback if the grade is hidden in the gradebook');
    }

    public function test_attempt_reopen_method_manual() {
        global $PAGE;

        $this->setUser($this->editingteachers[0]);
        $tarefalv = $this->create_instance(array('attemptreopenmethod'=>TAREFALV_ATTEMPT_REOPEN_METHOD_MANUAL,
                                               'maxattempts'=>3,
                                               'submissiondrafts'=>1,
                                               'tarefalvsubmission_onlinetext_enabled'=>1));
        $PAGE->set_url(new moodle_url('/mod/tarefalv/view.php', array('id' => $tarefalv->get_course_module()->id)));

        // Student should be able to see an add submission button.
        $this->setUser($this->students[0]);
        $output = $tarefalv->view_student_summary($this->students[0], true);
        $this->assertNotEquals(false, strpos($output, get_string('addsubmission', 'tarefalv')));

        // Add a submission.
        $now = time();
        $submission = $tarefalv->get_user_submission($this->students[0]->id, true);
        $data = new stdClass();
        $data->onlinetext_editor = array('itemid'=>file_get_unused_draft_itemid(),
                                         'text'=>'Submission text',
                                         'format'=>FORMAT_MOODLE);
        $plugin = $tarefalv->get_submission_plugin_by_type('onlinetext');
        $plugin->save($submission, $data);

        // And now submit it for marking.
        $submission->status = TAREFALV_SUBMISSION_STATUS_SUBMITTED;
        $tarefalv->testable_update_submission($submission, $this->students[0]->id, true, false);

        // Verify the student cannot make changes to the submission.
        $output = $tarefalv->view_student_summary($this->students[0], true);
        $this->assertEquals(false, strpos($output, get_string('addsubmission', 'tarefalv')));

        // Mark the submission.
        $this->setUser($this->teachers[0]);
        $data = new stdClass();
        $data->grade = '50.0';
        $tarefalv->testable_apply_grade_to_user($data, $this->students[0]->id, 0);

        // Check the student can see the grade.
        $this->setUser($this->students[0]);
        $output = $tarefalv->view_student_summary($this->students[0], true);
        $this->assertNotEquals(false, strpos($output, '50.0'));

        // Allow the student another attempt.
        $this->teachers[0]->ignoresesskey = true;
        $this->setUser($this->teachers[0]);
        $result = $tarefalv->testable_process_add_attempt($this->students[0]->id);
        $this->assertEquals(true, $result);

        // Check that the previous attempt is now in the submission history table.
        $this->setUser($this->students[0]);
        $output = $tarefalv->view_student_summary($this->students[0], true);
        // Need a better check.
        $this->assertNotEquals(false, strpos($output, 'Submission text'), 'Contains: Submission text');

        // Check that the student now has a button for Add a new attempt".
        $this->assertNotEquals(false, strpos($output, get_string('addnewattempt', 'tarefalv')));
        // Check that the student now does not have a button for Submit.
        $this->assertEquals(false, strpos($output, get_string('submitassignment', 'tarefalv')));

        // Check that the student now has a submission history.
        $this->assertNotEquals(false, strpos($output, get_string('attempthistory', 'tarefalv')));

        $this->setUser($this->teachers[0]);
        // Check that the grading table loads correctly and contains this user.
        // This is also testing that we do not get duplicate rows in the grading table.
        $gradingtable = new tarefalv_grading_table($tarefalv, 100, '', 0, true);
        $output = $tarefalv->get_renderer()->render($gradingtable);
        $this->assertEquals(true, strpos($output, $this->students[0]->lastname));

        // Should be 1 not 2.
        $this->assertEquals(1, $tarefalv->count_submissions());
        $this->assertEquals(1, $tarefalv->count_submissions_with_status('reopened'));
        $this->assertEquals(0, $tarefalv->count_submissions_need_grading());
        $this->assertEquals(1, $tarefalv->count_grades());

        // Change max attempts to unlimited.
        $formdata = clone($tarefalv->get_instance());
        $formdata->maxattempts = TAREFALV_UNLIMITED_ATTEMPTS;
        $formdata->instance = $formdata->id;
        $tarefalv->update_instance($formdata);

        // Mark the submission again.
        $data = new stdClass();
        $data->grade = '60.0';
        $tarefalv->testable_apply_grade_to_user($data, $this->students[0]->id, 1);

        // Check the grade exists.
        $grades = $tarefalv->get_user_grades_for_gradebook($this->students[0]->id);
        $this->assertEquals(60, (int)$grades[$this->students[0]->id]->rawgrade);

        // Check we can reopen still.
        $result = $tarefalv->testable_process_add_attempt($this->students[0]->id);
        $this->assertEquals(true, $result);

        // Should no longer have a grade because there is no grade for the latest attempt.
        $grades = $tarefalv->get_user_grades_for_gradebook($this->students[0]->id);
        $this->assertEmpty($grades);

    }

    /**
     * Test reopen behavior when in "Reopen until pass" mode.
     */
    public function test_attempt_reopen_method_untilpass() {
        global $PAGE;

        $this->setUser($this->editingteachers[0]);
        $tarefalv = $this->create_instance(array('attemptreopenmethod' => TAREFALV_ATTEMPT_REOPEN_METHOD_UNTILPASS,
                'maxattempts' => 3,
                'submissiondrafts' => 1,
                'tarefalvsubmission_onlinetext_enabled' => 1));
        $PAGE->set_url(new moodle_url('/mod/tarefalv/view.php', array('id' => $tarefalv->get_course_module()->id)));

        // Set grade to pass to 80.
        $gradeitem = $tarefalv->get_grade_item();
        $gradeitem->gradepass = '80.0';
        $gradeitem->update();

        // Student should be able to see an add submission button.
        $this->setUser($this->students[0]);
        $output = $tarefalv->view_student_summary($this->students[0], true);
        $this->assertNotEquals(false, strpos($output, get_string('addsubmission', 'tarefalv')));

        // Add a submission.
        $now = time();
        $submission = $tarefalv->get_user_submission($this->students[0]->id, true);
        $data = new stdClass();
        $data->onlinetext_editor = array('itemid' => file_get_unused_draft_itemid(),
                'text' => 'Submission text',
                'format' => FORMAT_MOODLE);
        $plugin = $tarefalv->get_submission_plugin_by_type('onlinetext');
        $plugin->save($submission, $data);

        // And now submit it for marking.
        $submission->status = TAREFALV_SUBMISSION_STATUS_SUBMITTED;
        $tarefalv->testable_update_submission($submission, $this->students[0]->id, true, false);

        // Verify the student cannot make a new attempt.
        $output = $tarefalv->view_student_summary($this->students[0], true);
        $this->assertEquals(false, strpos($output, get_string('addnewattempt', 'tarefalv')));

        // Mark the submission as non-passing.
        $this->setUser($this->teachers[0]);
        $data = new stdClass();
        $data->grade = '50.0';
        $tarefalv->testable_apply_grade_to_user($data, $this->students[0]->id, 0);

        // Check the student can see the grade.
        $this->setUser($this->students[0]);
        $output = $tarefalv->view_student_summary($this->students[0], true);
        $this->assertNotEquals(false, strpos($output, '50.0'));

        // Check that the student now has a button for Add a new attempt.
        $output = $tarefalv->view_student_summary($this->students[0], true);
        $this->assertNotEquals(false, strpos($output, get_string('addnewattempt', 'tarefalv')));

        // Check that the student now does not have a button for Submit.
        $this->assertEquals(false, strpos($output, get_string('submitassignment', 'tarefalv')));

        // Check that the student now has a submission history.
        $this->assertNotEquals(false, strpos($output, get_string('attempthistory', 'tarefalv')));

        // Add a second submission.
        $now = time();
        $submission = $tarefalv->get_user_submission($this->students[0]->id, true, 1);
        $data = new stdClass();
        $data->onlinetext_editor = array('itemid' => file_get_unused_draft_itemid(),
                'text' => 'Submission text',
                'format' => FORMAT_MOODLE);
        $plugin = $tarefalv->get_submission_plugin_by_type('onlinetext');
        $plugin->save($submission, $data);

        // And now submit it for marking.
        $submission->status = TAREFALV_SUBMISSION_STATUS_SUBMITTED;
        $tarefalv->testable_update_submission($submission, $this->students[0]->id, true, false);

        // Mark the submission as passing.
        $this->setUser($this->teachers[0]);
        $data = new stdClass();
        $data->grade = '80.0';
        $tarefalv->testable_apply_grade_to_user($data, $this->students[0]->id, 1);

        // Check that the student does not have a button for Add a new attempt.
        $this->setUser($this->students[0]);
        $output = $tarefalv->view_student_summary($this->students[0], true);
        $this->assertEquals(false, strpos($output, get_string('addnewattempt', 'tarefalv')));

        // Re-mark the submission as not passing.
        $this->setUser($this->teachers[0]);
        $data = new stdClass();
        $data->grade = '50.0';
        $tarefalv->testable_apply_grade_to_user($data, $this->students[0]->id, 1);

        // Check that the student now has a button for Add a new attempt.
        $this->setUser($this->students[0]);
        $output = $tarefalv->view_student_summary($this->students[0], true);
        $this->assertNotEquals(false, strpos($output, get_string('addnewattempt', 'tarefalv')));

        // Add a submission as a second student.
        $this->setUser($this->students[1]);
        $now = time();
        $submission = $tarefalv->get_user_submission($this->students[1]->id, true);
        $data = new stdClass();
        $data->onlinetext_editor = array('itemid' => file_get_unused_draft_itemid(),
                'text' => 'Submission text',
                'format' => FORMAT_MOODLE);
        $plugin = $tarefalv->get_submission_plugin_by_type('onlinetext');
        $plugin->save($submission, $data);

        // And now submit it for marking.
        $submission->status = TAREFALV_SUBMISSION_STATUS_SUBMITTED;
        $tarefalv->testable_update_submission($submission, $this->students[1]->id, true, false);

        // Mark the submission as passing.
        $this->setUser($this->teachers[0]);
        $data = new stdClass();
        $data->grade = '100.0';
        $tarefalv->testable_apply_grade_to_user($data, $this->students[1]->id, 0);

        // Check the student can see the grade.
        $this->setUser($this->students[1]);
        $output = $tarefalv->view_student_summary($this->students[1], true);
        $this->assertNotEquals(false, strpos($output, '100.0'));

        // Check that the student does not have a button for Add a new attempt.
        $output = $tarefalv->view_student_summary($this->students[1], true);
        $this->assertEquals(false, strpos($output, get_string('addnewattempt', 'tarefalv')));

        // Set grade to pass to 0, so that no attempts should reopen.
        $gradeitem = $tarefalv->get_grade_item();
        $gradeitem->gradepass = '0';
        $gradeitem->update();

        // Add another submission.
        $this->setUser($this->students[2]);
        $now = time();
        $submission = $tarefalv->get_user_submission($this->students[2]->id, true);
        $data = new stdClass();
        $data->onlinetext_editor = array('itemid' => file_get_unused_draft_itemid(),
                'text' => 'Submission text',
                'format' => FORMAT_MOODLE);
        $plugin = $tarefalv->get_submission_plugin_by_type('onlinetext');
        $plugin->save($submission, $data);

        // And now submit it for marking.
        $submission->status = TAREFALV_SUBMISSION_STATUS_SUBMITTED;
        $tarefalv->testable_update_submission($submission, $this->students[2]->id, true, false);

        // Mark the submission as graded.
        $this->setUser($this->teachers[0]);
        $data = new stdClass();
        $data->grade = '0.0';
        $tarefalv->testable_apply_grade_to_user($data, $this->students[2]->id, 0);

        // Check the student can see the grade.
        $this->setUser($this->students[2]);
        $output = $tarefalv->view_student_summary($this->students[2], true);
        $this->assertNotEquals(false, strpos($output, '0.0'));

        // Check that the student does not have a button for Add a new attempt.
        $output = $tarefalv->view_student_summary($this->students[2], true);
        $this->assertEquals(false, strpos($output, get_string('addnewattempt', 'tarefalv')));
    }


    public function test_markingworkflow() {
        global $PAGE;

        $this->setUser($this->editingteachers[0]);
        $tarefalv = $this->create_instance(array('markingworkflow'=>1));
        $PAGE->set_url(new moodle_url('/mod/tarefalv/view.php', array('id' => $tarefalv->get_course_module()->id)));

        // Mark the submission and set to notmarked.
        $this->setUser($this->teachers[0]);
        $data = new stdClass();
        $data->grade = '50.0';
        $data->workflowstate = TAREFALV_MARKING_WORKFLOW_STATE_NOTMARKED;
        $tarefalv->testable_apply_grade_to_user($data, $this->students[0]->id, 0);

        // Check the student can't see the grade.
        $this->setUser($this->students[0]);
        $output = $tarefalv->view_student_summary($this->students[0], true);
        $this->assertEquals(false, strpos($output, '50.0'));

        // Make sure the grade isn't pushed to the gradebook.
        $grades = $tarefalv->get_user_grades_for_gradebook($this->students[0]->id);
        $this->assertEmpty($grades);

        // Mark the submission and set to inmarking.
        $this->setUser($this->teachers[0]);
        $data = new stdClass();
        $data->grade = '50.0';
        $data->workflowstate = TAREFALV_MARKING_WORKFLOW_STATE_INMARKING;
        $tarefalv->testable_apply_grade_to_user($data, $this->students[0]->id, 0);

        // Check the student can't see the grade.
        $this->setUser($this->students[0]);
        $output = $tarefalv->view_student_summary($this->students[0], true);
        $this->assertEquals(false, strpos($output, '50.0'));

        // Make sure the grade isn't pushed to the gradebook.
        $grades = $tarefalv->get_user_grades_for_gradebook($this->students[0]->id);
        $this->assertEmpty($grades);

        // Mark the submission and set to readyforreview.
        $this->setUser($this->teachers[0]);
        $data = new stdClass();
        $data->grade = '50.0';
        $data->workflowstate = TAREFALV_MARKING_WORKFLOW_STATE_READYFORREVIEW;
        $tarefalv->testable_apply_grade_to_user($data, $this->students[0]->id, 0);

        // Check the student can't see the grade.
        $this->setUser($this->students[0]);
        $output = $tarefalv->view_student_summary($this->students[0], true);
        $this->assertEquals(false, strpos($output, '50.0'));

        // Make sure the grade isn't pushed to the gradebook.
        $grades = $tarefalv->get_user_grades_for_gradebook($this->students[0]->id);
        $this->assertEmpty($grades);

        // Mark the submission and set to inreview.
        $this->setUser($this->teachers[0]);
        $data = new stdClass();
        $data->grade = '50.0';
        $data->workflowstate = TAREFALV_MARKING_WORKFLOW_STATE_INREVIEW;
        $tarefalv->testable_apply_grade_to_user($data, $this->students[0]->id, 0);

        // Check the student can't see the grade.
        $this->setUser($this->students[0]);
        $output = $tarefalv->view_student_summary($this->students[0], true);
        $this->assertEquals(false, strpos($output, '50.0'));

        // Make sure the grade isn't pushed to the gradebook.
        $grades = $tarefalv->get_user_grades_for_gradebook($this->students[0]->id);
        $this->assertEmpty($grades);

        // Mark the submission and set to readyforrelease.
        $this->setUser($this->teachers[0]);
        $data = new stdClass();
        $data->grade = '50.0';
        $data->workflowstate = TAREFALV_MARKING_WORKFLOW_STATE_READYFORRELEASE;
        $tarefalv->testable_apply_grade_to_user($data, $this->students[0]->id, 0);

        // Check the student can't see the grade.
        $this->setUser($this->students[0]);
        $output = $tarefalv->view_student_summary($this->students[0], true);
        $this->assertEquals(false, strpos($output, '50.0'));

        // Make sure the grade isn't pushed to the gradebook.
        $grades = $tarefalv->get_user_grades_for_gradebook($this->students[0]->id);
        $this->assertEmpty($grades);

        // Mark the submission and set to released.
        $this->setUser($this->teachers[0]);
        $data = new stdClass();
        $data->grade = '50.0';
        $data->workflowstate = TAREFALV_MARKING_WORKFLOW_STATE_RELEASED;
        $tarefalv->testable_apply_grade_to_user($data, $this->students[0]->id, 0);

        // Check the student can see the grade.
        $this->setUser($this->students[0]);
        $output = $tarefalv->view_student_summary($this->students[0], true);
        $this->assertNotEquals(false, strpos($output, '50.0'));

        // Make sure the grade is pushed to the gradebook.
        $grades = $tarefalv->get_user_grades_for_gradebook($this->students[0]->id);
        $this->assertEquals(50, (int)$grades[$this->students[0]->id]->rawgrade);
    }

    public function test_markerallocation() {
        global $PAGE;

        $this->setUser($this->editingteachers[0]);
        $tarefalv = $this->create_instance(array('markingworkflow'=>1, 'markingallocation'=>1));
        $PAGE->set_url(new moodle_url('/mod/tarefalv/view.php', array('id' => $tarefalv->get_course_module()->id)));

        // Allocate marker to submission.
        $data = new stdClass();
        $data->allocatedmarker = $this->teachers[0]->id;
        $tarefalv->testable_apply_grade_to_user($data, $this->students[0]->id, 0);

        // Check the allocated marker can view the submission.
        $this->setUser($this->teachers[0]);

        $users = $tarefalv->list_participants(0, true);
        $user = reset($users);
        $this->assertEquals($this->students[0]->id, $user->id);

        $cm = get_coursemodule_from_instance('tarefalv', $tarefalv->get_instance()->id);
        $context = context_module::instance($cm->id);
        $tarefalv = new testable_tarefalv($context, $cm, $this->course);
        // Check that other teachers can't view this submission.
        $this->setUser($this->teachers[1]);
        $users = $tarefalv->list_participants(0, true);
        $this->assertEquals(0, count($users));
    }

    /**
     * @expectedException moodle_exception
     */
    public function test_teacher_submit_for_student() {
        global $PAGE;

        $this->preventResetByRollback();
        $sink = $this->redirectMessages();

        $this->setUser($this->editingteachers[0]);

        $tarefalv = $this->create_instance(array('tarefalvsubmission_onlinetext_enabled'=>1, 'submissiondrafts'=>1));
        $PAGE->set_url(new moodle_url('/mod/tarefalv/view.php', array('id' => $tarefalv->get_course_module()->id)));

        $this->setUser($this->students[0]);
        // Simulate a submission.
        $data = new stdClass();
        $data->onlinetext_editor = array('itemid'=>file_get_unused_draft_itemid(),
                                         'text'=>'Student submission text',
                                         'format'=>FORMAT_MOODLE);

        $notices = array();
        $tarefalv->save_submission($data, $notices);

        // Check that the submission text was saved.
        $output = $tarefalv->view_student_summary($this->students[0], true);
        $this->assertContains('Student submission text', $output, 'Contains student submission text');

        // Check that a teacher teacher with the extra capability can edit a students submission.
        $this->setUser($this->teachers[0]);
        $data = new stdClass();
        $data->userid = $this->students[0]->id;
        $data->onlinetext_editor = array('itemid'=>file_get_unused_draft_itemid(),
                                         'text'=>'Teacher edited submission text',
                                         'format'=>FORMAT_MOODLE);

        // Add the required capability.
        $roleid = create_role('Dummy role', 'dummyrole', 'dummy role description');
        tarefalv_capability('mod/tarefalv:editothersubmission', CAP_ALLOW, $roleid, $tarefalv->get_context()->id);
        role_tarefalv($roleid, $this->teachers[0]->id, $tarefalv->get_context()->id);
        accesslib_clear_all_caches_for_unit_testing();

        // Try to save the submission.
        $notices = array();
        $tarefalv->save_submission($data, $notices);

        // Check that the teacher can submit the students work.
        $data = new stdClass();
        $data->userid = $this->students[0]->id;
        $notices = array();
        $tarefalv->submit_for_grading($data, $notices);

        // Revert to draft so the student can edit it.
        $tarefalv->revert_to_draft($this->students[0]->id);

        $this->setUser($this->students[0]);

        // Check that the submission text was saved.
        $output = $tarefalv->view_student_summary($this->students[0], true);
        $this->assertContains('Teacher edited submission text', $output, 'Contains student submission text');

        // Check that the student can submit their work.
        $data = new stdClass();
        $tarefalv->submit_for_grading($data, $notices);

        $output = $tarefalv->view_student_summary($this->students[0], true);
        $this->assertNotContains(get_string('addsubmission', 'tarefalv'), $output);

        // Set to a default editing teacher who should not be able to edit this submission.
        $this->setUser($this->editingteachers[1]);

        // Revert to draft so the submission is editable.
        $tarefalv->revert_to_draft($this->students[0]->id);

        $data = new stdClass();
        $data->userid = $this->students[0]->id;
        $data->onlinetext_editor = array('itemid'=>file_get_unused_draft_itemid(),
                                         'text'=>'Teacher 2 edited submission text',
                                         'format'=>FORMAT_MOODLE);

        $notices = array();
        $tarefalv->save_submission($data, $notices);

        $sink->close();
    }

    public function test_disable_submit_after_cutoff_date() {
        global $PAGE;

        $this->setUser($this->editingteachers[0]);
        $now = time();
        $tomorrow = $now + 24*60*60;
        $lastweek = $now - 7*24*60*60;
        $yesterday = $now - 24*60*60;

        $tarefalv = $this->create_instance(array('duedate'=>$yesterday,
                                               'cutoffdate'=>$tomorrow,
                                               'tarefalvsubmission_onlinetext_enabled'=>1));
        $PAGE->set_url(new moodle_url('/mod/tarefalv/view.php', array('id' => $tarefalv->get_course_module()->id)));

        // Student should be able to see an add submission button.
        $this->setUser($this->students[0]);
        $output = $tarefalv->view_student_summary($this->students[0], true);
        $this->assertNotEquals(false, strpos($output, get_string('addsubmission', 'tarefalv')));

        // Add a submission but don't submit now.
        $submission = $tarefalv->get_user_submission($this->students[0]->id, true);
        $data = new stdClass();
        $data->onlinetext_editor = array('itemid'=>file_get_unused_draft_itemid(),
                                         'text'=>'Submission text',
                                         'format'=>FORMAT_MOODLE);
        $plugin = $tarefalv->get_submission_plugin_by_type('onlinetext');
        $plugin->save($submission, $data);

        // Create another instance with cut-off and due-date already passed.
        $this->setUser($this->editingteachers[0]);
        $now = time();
        $tarefalv = $this->create_instance(array('duedate'=>$lastweek,
                                               'cutoffdate'=>$yesterday,
                                               'tarefalvsubmission_onlinetext_enabled'=>1));

        $this->setUser($this->students[0]);
        $output = $tarefalv->view_student_summary($this->students[0], true);
        $this->assertNotContains($output, get_string('editsubmission', 'tarefalv'),
                                 'Should not be able to edit after cutoff date.');
        $this->assertNotContains($output, get_string('submitassignment', 'tarefalv'),
                                 'Should not be able to submit after cutoff date.');
    }
    /**
     * Testing for submission comment plugin settings
     */
    public function test_submission_comment_plugin_settings() {
        global $CFG;

        $commentconfig = false;
        if (!empty($CFG->usecomments)) {
            $commentconfig = $CFG->usecomments;
        }

        $CFG->usecomments = true;
        $tarefalv = $this->create_instance();
        $plugin = $tarefalv->get_submission_plugin_by_type('comments');
        $this->assertEquals(1, $plugin->is_enabled('enabled'));

        $tarefalv = $this->create_instance(array('tarefalvsubmission_comments_enabled' => 0));
        $plugin = $tarefalv->get_submission_plugin_by_type('comments');
        $this->assertEquals(1, $plugin->is_enabled('enabled'));

        $tarefalv = $this->create_instance(array('tarefalvsubmission_comments_enabled' => 1));
        $plugin = $tarefalv->get_submission_plugin_by_type('comments');
        $this->assertEquals(1, $plugin->is_enabled('enabled'));

        $CFG->usecomments = false;
        $tarefalv = $this->create_instance();
        $plugin = $tarefalv->get_submission_plugin_by_type('comments');
        $this->assertEquals(0, $plugin->is_enabled('enabled'));

        $tarefalv = $this->create_instance(array('tarefalvsubmission_comments_enabled' => 0));
        $plugin = $tarefalv->get_submission_plugin_by_type('comments');
        $this->assertEquals(0, $plugin->is_enabled('enabled'));

        $tarefalv = $this->create_instance(array('tarefalvsubmission_comments_enabled' => 1));
        $plugin = $tarefalv->get_submission_plugin_by_type('comments');
        $this->assertEquals(0, $plugin->is_enabled('enabled'));

        $CFG->usecomments = $commentconfig;
    }

    /**
     * Testing for comment inline settings
     */
    public function test_feedback_comment_commentinline() {
        global $CFG;

        $sourcetext = "Hello!

I'm writing to you from the Moodle Majlis in Muscat, Oman, where we just had several days of Moodle community goodness.

URL outside a tag: https://moodle.org/logo/logo-240x60.gif
Plugin url outside a tag: @@PLUGINFILE@@/logo-240x60.gif

External link 1:<img src='https://moodle.org/logo/logo-240x60.gif' alt='Moodle'/>
External link 2:<img alt=\"Moodle\" src=\"https://moodle.org/logo/logo-240x60.gif\"/>
Internal link 1:<img src='@@PLUGINFILE@@/logo-240x60.gif' alt='Moodle'/>
Internal link 2:<img alt=\"Moodle\" src=\"@@PLUGINFILE@@logo-240x60.gif\"/>
Anchor link 1:<a href=\"@@PLUGINFILE@@logo-240x60.gif\" alt=\"bananas\">Link text</a>
Anchor link 2:<a title=\"bananas\" href=\"../logo-240x60.gif\">Link text</a>
";

        // Note the internal images have been stripped and the html is purified (quotes fixed in this case).
        $filteredtext = "Hello!

I'm writing to you from the Moodle Majlis in Muscat, Oman, where we just had several days of Moodle community goodness.

URL outside a tag: https://moodle.org/logo/logo-240x60.gif
Plugin url outside a tag: @@PLUGINFILE@@/logo-240x60.gif

External link 1:<img src=\"https://moodle.org/logo/logo-240x60.gif\" alt=\"Moodle\" />
External link 2:<img alt=\"Moodle\" src=\"https://moodle.org/logo/logo-240x60.gif\" />
Internal link 1:
Internal link 2:
Anchor link 1:Link text
Anchor link 2:<a title=\"bananas\" href=\"../logo-240x60.gif\">Link text</a>
";

        $this->setUser($this->editingteachers[0]);
        $params = array('tarefalvsubmission_onlinetext_enabled' => 1,
                        'tarefalvfeedback_comments_enabled' => 1,
                        'tarefalvfeedback_comments_commentinline' => 1);
        $tarefalv = $this->create_instance($params);

        $this->setUser($this->students[0]);
        // Add a submission but don't submit now.
        $submission = $tarefalv->get_user_submission($this->students[0]->id, true);
        $data = new stdClass();

        // Test the internal link is stripped, but the external one is not.
        $data->onlinetext_editor = array('itemid'=>file_get_unused_draft_itemid(),
                                         'text'=>$sourcetext,
                                         'format'=>FORMAT_MOODLE);

        $plugin = $tarefalv->get_submission_plugin_by_type('onlinetext');
        $plugin->save($submission, $data);

        $this->setUser($this->editingteachers[0]);

        $data = new stdClass();
        require_once($CFG->dirroot . '/mod/tarefalv/gradeform.php');
        $pagination = array('userid'=>$this->students[0]->id,
                            'rownum'=>0,
                            'last'=>true,
                            'useridlistid' => $tarefalv->get_useridlist_key_id(),
                            'attemptnumber'=>0);
        $formparams = array($tarefalv, $data, $pagination);
        $mform = new mod_tarefalv_grade_form(null, $formparams);

        $this->assertEquals($filteredtext, $data->tarefalvfeedbackcomments_editor['text']);
    }

    /**
     * Testing for feedback comment plugin settings
     */
    public function test_feedback_plugin_settings() {

        $tarefalv = $this->create_instance();
        $plugin = $tarefalv->get_feedback_plugin_by_type('comments');
        $this->assertEquals(0, $plugin->is_enabled('enabled'));

        $tarefalv = $this->create_instance(array('tarefalvfeedback_comments_enabled' => 0));
        $plugin = $tarefalv->get_feedback_plugin_by_type('comments');
        $this->assertEquals(0, $plugin->is_enabled('enabled'));

        $tarefalv = $this->create_instance(array('tarefalvfeedback_comments_enabled' => 1));
        $plugin = $tarefalv->get_feedback_plugin_by_type('comments');
        $this->assertEquals(1, $plugin->is_enabled('enabled'));
    }

    /**
     * Testing if gradebook feedback plugin is enabled.
     */
    public function test_is_gradebook_feedback_enabled() {
        $adminconfig = get_config('tarefalv');
        $gradebookplugin = $adminconfig->feedback_plugin_for_gradebook;

        // Create assignment with gradebook feedback enabled and grade = 0.
        $tarefalv = $this->create_instance(array($gradebookplugin . '_enabled' => 1, 'grades' => 0));

        // Get gradebook feedback plugin.
        $gradebookplugintype = str_replace('tarefalvfeedback_', '', $gradebookplugin);
        $plugin = $tarefalv->get_feedback_plugin_by_type($gradebookplugintype);
        $this->assertEquals(1, $plugin->is_enabled('enabled'));
        $this->assertEquals(1, $tarefalv->is_gradebook_feedback_enabled());

        // Create assignment with gradebook feedback disabled and grade = 0.
        $tarefalv = $this->create_instance(array($gradebookplugin . '_enabled' => 0, 'grades' => 0));
        $plugin = $tarefalv->get_feedback_plugin_by_type($gradebookplugintype);
        $this->assertEquals(0, $plugin->is_enabled('enabled'));
    }

    /**
     * Testing can_edit_submission
     */
    public function test_can_edit_submission() {
        global $PAGE, $DB;
        $this->create_extra_users();

        $this->setAdminUser();
        // Create assignment (onlinetext).
        $tarefalv = $this->create_instance(array('tarefalvsubmission_onlinetext_enabled'=>1, 'submissiondrafts'=>1));
        $PAGE->set_url(new moodle_url('/mod/tarefalv/view.php', array('id' => $tarefalv->get_course_module()->id)));

        // Check student can edit their own submission.
        $this->assertTrue($tarefalv->can_edit_submission($this->students[0]->id, $this->students[0]->id));
        // Check student cannot edit others submission.
        $this->assertFalse($tarefalv->can_edit_submission($this->students[0]->id, $this->students[1]->id));

        // Check teacher cannot (by default) edit a students submission.
        $this->assertFalse($tarefalv->can_edit_submission($this->students[0]->id, $this->teachers[0]->id));

        // Add the required capability to edit a student submission.
        $roleid = create_role('Dummy role', 'dummyrole', 'dummy role description');
        tarefalv_capability('mod/tarefalv:editothersubmission', CAP_ALLOW, $roleid, $tarefalv->get_context()->id);
        role_tarefalv($roleid, $this->teachers[0]->id, $tarefalv->get_context()->id);
        accesslib_clear_all_caches_for_unit_testing();
        // Retest - should now have access.
        $this->assertTrue($tarefalv->can_edit_submission($this->students[0]->id, $this->teachers[0]->id));

        // Force create an assignment with SEPARATEGROUPS.
        $data = new stdClass();
        $data->courseid = $this->course->id;
        $data->name = 'Grouping';
        $groupingid = groups_create_grouping($data);
        groups_tarefalv_grouping($groupingid, $this->groups[0]->id);
        groups_tarefalv_grouping($groupingid, $this->groups[1]->id);
        $tarefalv = $this->create_instance(array('groupingid' => $groupingid, 'groupmode' => SEPARATEGROUPS));

        // Add the capability to the new assignment for extra students 0 and 1.
        tarefalv_capability('mod/tarefalv:editothersubmission', CAP_ALLOW, $roleid, $tarefalv->get_context()->id);
        role_tarefalv($roleid, $this->extrastudents[0]->id, $tarefalv->get_context()->id);
        role_tarefalv($roleid, $this->extrastudents[1]->id, $tarefalv->get_context()->id);
        accesslib_clear_all_caches_for_unit_testing();

        // Verify the extra student does not have the capability to edit a submission not in their group.
        $this->assertFalse($tarefalv->can_edit_submission($this->students[0]->id, $this->extrastudents[1]->id));
        // Verify the extra student does have the capability to edit a submission in their group.
        $this->assertTrue($tarefalv->can_edit_submission($this->students[0]->id, $this->extrastudents[0]->id));

    }

    /**
     * Test if the view blind details capability works
     */
    public function test_can_view_blind_details() {
        global $DB;
        // Note: The shared setUp leads to terrible tests. Please don't use it.
        $roles = $DB->get_records('role', null, '', 'shortname, id');
        $course = $this->getDataGenerator()->create_course([]);

        $student = $this->students[0];// Get a student user.
        $this->getDataGenerator()->enrol_user($student->id,
                                              $course->id,
                                              $roles['student']->id);

        // Create a teacher. Shouldn't be able to view blind marking ID.
        $teacher = $this->getDataGenerator()->create_user();

        $this->getDataGenerator()->enrol_user($teacher->id,
                                              $course->id,
                                              $roles['teacher']->id);

        // Create a manager.. Should be able to view blind marking ID.
        $manager = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($manager->id,
                                              $course->id,
                                              $roles['manager']->id);

        // Generate blind marking assignment.
        $tarefalv = $this->create_instance(array('course' => $course->id, 'blindmarking' => 1));
        $this->assertEquals(true, $tarefalv->is_blind_marking());

        // Test student names are hidden to teacher.
        $this->setUser($teacher);
        $gradingtable = new tarefalv_grading_table($tarefalv, 1, '', 0, true);
        $output = $tarefalv->get_renderer()->render($gradingtable);
        $this->assertEquals(true, strpos($output, get_string('hiddenuser', 'tarefalv')));    // "Participant" is somewhere on the page.
        $this->assertEquals(false, strpos($output, fullname($student)));    // Students full name doesn't appear.

        // Test student names are visible to manager.
        $this->setUser($manager);
        $gradingtable = new tarefalv_grading_table($tarefalv, 1, '', 0, true);
        $output = $tarefalv->get_renderer()->render($gradingtable);
        $this->assertEquals(true, strpos($output, get_string('hiddenuser', 'tarefalv')));
        $this->assertEquals(true, strpos($output, fullname($student)));
    }

    /**
     * Testing get_shared_group_members
     */
    public function test_get_shared_group_members() {
        $this->create_extra_users();
        $this->setAdminUser();

        // Force create an assignment with SEPARATEGROUPS.
        $data = new stdClass();
        $data->courseid = $this->course->id;
        $data->name = 'Grouping';
        $groupingid = groups_create_grouping($data);
        groups_tarefalv_grouping($groupingid, $this->groups[0]->id);
        groups_tarefalv_grouping($groupingid, $this->groups[1]->id);
        $tarefalv = $this->create_instance(array('groupingid' => $groupingid, 'groupmode' => SEPARATEGROUPS));
        $cm = $tarefalv->get_course_module();

        // Add the capability to access allgroups.
        $roleid = create_role('Access all groups role', 'accessallgroupsrole', '');
        tarefalv_capability('moodle/site:accessallgroups', CAP_ALLOW, $roleid, $tarefalv->get_context()->id);
        role_tarefalv($roleid, $this->extrastudents[3]->id, $tarefalv->get_context()->id);
        accesslib_clear_all_caches_for_unit_testing();

        // Get shared group members for students 0 and 1.
        $groupmembers = array();
        $groupmembers[0] = $tarefalv->get_shared_group_members($cm, $this->students[0]->id);
        $groupmembers[1] = $tarefalv->get_shared_group_members($cm, $this->students[1]->id);

        // They should share groups with extrastudents 0 and 1.
        $this->assertTrue(in_array($this->extrastudents[0]->id, $groupmembers[0]));
        $this->assertFalse(in_array($this->extrastudents[0]->id, $groupmembers[1]));
        $this->assertTrue(in_array($this->extrastudents[1]->id, $groupmembers[1]));
        $this->assertFalse(in_array($this->extrastudents[1]->id, $groupmembers[0]));

        // Lists of group members for students and extrastudents should be the same.
        $this->assertEquals($groupmembers[0], $tarefalv->get_shared_group_members($cm, $this->extrastudents[0]->id));
        $this->assertEquals($groupmembers[1], $tarefalv->get_shared_group_members($cm, $this->extrastudents[1]->id));

        // Get all group members for extrastudent 3 wich can access all groups.
        $allgroupmembers = $tarefalv->get_shared_group_members($cm, $this->extrastudents[3]->id);

        // Extrastudent 3 should see students 0 and 1, extrastudent 0 and 1.
        $this->assertTrue(in_array($this->students[0]->id, $allgroupmembers));
        $this->assertTrue(in_array($this->students[1]->id, $allgroupmembers));
        $this->assertTrue(in_array($this->extrastudents[0]->id, $allgroupmembers));
        $this->assertTrue(in_array($this->extrastudents[1]->id , $allgroupmembers));
    }

    /**
     * Test get plugins file areas
     */
    public function test_get_plugins_file_areas() {
        $this->setUser($this->editingteachers[0]);
        $tarefalv = $this->create_instance();

        // Test that all the submission and feedback plugins are returning the expected file aras.
        $usingfilearea = 0;
        $coreplugins = core_plugin_manager::standard_plugins_list('tarefalvsubmission');
        foreach ($tarefalv->get_submission_plugins() as $plugin) {
            $type = $plugin->get_type();
            if (!in_array($type, $coreplugins)) {
                continue;
            }
            $fileareas = $plugin->get_file_areas();

            if ($type == 'onlinetext') {
                $this->assertEquals(array('submissions_onlinetext' => 'Online text'), $fileareas);
                $usingfilearea++;
            } else if ($type == 'file') {
                $this->assertEquals(array('submission_files' => 'File submissions'), $fileareas);
                $usingfilearea++;
            } else {
                $this->assertEmpty($fileareas);
            }
        }
        $this->assertEquals(2, $usingfilearea);

        $usingfilearea = 0;
        $coreplugins = core_plugin_manager::standard_plugins_list('tarefalvfeedback');
        foreach ($tarefalv->get_feedback_plugins() as $plugin) {
            $type = $plugin->get_type();
            if (!in_array($type, $coreplugins)) {
                continue;
            }
            $fileareas = $plugin->get_file_areas();

            if ($type == 'editpdf') {
                $this->assertEquals(array('download' => 'Annotate PDF'), $fileareas);
                $usingfilearea++;
            } else if ($type == 'file') {
                $this->assertEquals(array('feedback_files' => 'Feedback files'), $fileareas);
                $usingfilearea++;
            } else {
                $this->assertEmpty($fileareas);
            }
        }
        $this->assertEquals(2, $usingfilearea);
    }

    /**
     * Test override exists
     *
     * This function needs to obey the group override logic as per the tarefalv grading table and
     * the overview block.
     */
    public function test_override_exists() {
        global $DB;

        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();

        // Create an tarefalv instance.
        $tarefalv = $this->create_instance(['course' => $course]);
        $tarefalvinstance = $tarefalv->get_instance();

        // Create users.
        $users = [
            'Only in group A'                     => $this->getDataGenerator()->create_user(),
            'Only in group B'                     => $this->getDataGenerator()->create_user(),
            'In group A and B (no user override)' => $this->getDataGenerator()->create_user(),
            'In group A and B (user override)'    => $this->getDataGenerator()->create_user(),
            'In no groups'                        => $this->getDataGenerator()->create_user()
        ];

        // Enrol users.
        foreach ($users as $user) {
            $this->getDataGenerator()->enrol_user($user->id, $course->id);
        }

        // Create groups.
        $groupa = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        $groupb = $this->getDataGenerator()->create_group(['courseid' => $course->id]);

        // Add members to groups.
        // Group A.
        $this->getDataGenerator()->create_group_member(
            ['groupid' => $groupa->id, 'userid' => $users['Only in group A']->id]);
        $this->getDataGenerator()->create_group_member(
            ['groupid' => $groupa->id, 'userid' => $users['In group A and B (no user override)']->id]);
        $this->getDataGenerator()->create_group_member(
            ['groupid' => $groupa->id, 'userid' => $users['In group A and B (user override)']->id]);

        // Group B.
        $this->getDataGenerator()->create_group_member(
            ['groupid' => $groupb->id, 'userid' => $users['Only in group B']->id]);
        $this->getDataGenerator()->create_group_member(
            ['groupid' => $groupb->id, 'userid' => $users['In group A and B (no user override)']->id]);
        $this->getDataGenerator()->create_group_member(
            ['groupid' => $groupb->id, 'userid' => $users['In group A and B (user override)']->id]);

        // Overrides for each of the groups, and a user override.
        $overrides = [
            // Override for group A, highest priority (numerically lowest sortorder).
            [
                'tarefalvid' => $tarefalvinstance->id,
                'groupid' => $groupa->id,
                'userid' => null,
                'sortorder' => 1,
                'allowsubmissionsfromdate' => 1,
                'duedate' => 2,
                'cutoffdate' => 3
            ],
            // Override for group B, lower priority (numerically higher sortorder).
            [
                'tarefalvid' => $tarefalvinstance->id,
                'groupid' => $groupb->id,
                'userid' => null,
                'sortorder' => 2,
                'allowsubmissionsfromdate' => 5,
                'duedate' => 6,
                'cutoffdate' => 6
            ],
            // User override.
            [
                'tarefalvid' => $tarefalvinstance->id,
                'groupid' => null,
                'userid' => $users['In group A and B (user override)']->id,
                'sortorder' => null,
                'allowsubmissionsfromdate' => 7,
                'duedate' => 8,
                'cutoffdate' => 9
            ],
        ];

        // Kinda hacky, need to add the ID to the overrides in the above array
        // for later.
        foreach ($overrides as &$override) {
            $override['id'] = $DB->insert_record('tarefalv_overrides', $override);
        }

        $returnedoverrides = array_reduce(array_keys($users), function($carry, $description) use ($users, $tarefalv) {
            return $carry + ['For user ' . lcfirst($description) => $tarefalv->override_exists($users[$description]->id)];
        }, []);

        // Test we get back the correct override from override_exists (== checks all object members match).
        // User only in group A should see the group A override.
        $this->assertTrue($returnedoverrides['For user only in group A'] == (object)$overrides[0]);
        // User only in group B should see the group B override.
        $this->assertTrue($returnedoverrides['For user only in group B'] == (object)$overrides[1]);
        // User in group A and B, with no user override should see the group A override
        // as it has higher priority (numerically lower sortorder).
        $this->assertTrue($returnedoverrides['For user in group A and B (no user override)'] == (object)$overrides[0]);
        // User in group A and B, with a user override should see the user override
        // as it has higher priority (numerically lower sortorder).
        $this->assertTrue($returnedoverrides['For user in group A and B (user override)'] == (object)$overrides[2]);
        // User with no overrides should get nothing.
        $this->assertNull($returnedoverrides['For user in no groups']->duedate);
        $this->assertNull($returnedoverrides['For user in no groups']->cutoffdate);
        $this->assertNull($returnedoverrides['For user in no groups']->allowsubmissionsfromdate);
    }

    /**
     * Test the quicksave grades processor
     */
    public function test_process_save_quick_grades() {
        $this->editingteachers[0]->ignoresesskey = true;
        $this->setUser($this->editingteachers[0]);

        $tarefalv = $this->create_instance(array('attemptreopenmethod' => TAREFALV_ATTEMPT_REOPEN_METHOD_MANUAL));

        // Initially grade the user.
        $grade = $tarefalv->get_user_grade($this->students[0]->id, false);
        if (!$grade) {
            $grade = new stdClass();
            $grade->attemptnumber = '';
            $grade->timemodified = '';
        }
        $data = array(
            'grademodified_' . $this->students[0]->id => $grade->timemodified,
            'gradeattempt_' . $this->students[0]->id => $grade->attemptnumber,
            'quickgrade_' . $this->students[0]->id => '60.0'
        );
        $result = $tarefalv->testable_process_save_quick_grades($data);
        $this->assertContains(get_string('quickgradingchangessaved', 'tarefalv'), $result);
        $grade = $tarefalv->get_user_grade($this->students[0]->id, false);
        $this->assertEquals('60.0', $grade->grade);

        // Attempt to grade with a past attempts grade info.
        $tarefalv->testable_process_add_attempt($this->students[0]->id);
        $data = array(
            'grademodified_' . $this->students[0]->id => $grade->timemodified,
            'gradeattempt_' . $this->students[0]->id => $grade->attemptnumber,
            'quickgrade_' . $this->students[0]->id => '50.0'
        );
        $result = $tarefalv->testable_process_save_quick_grades($data);
        $this->assertContains(get_string('errorrecordmodified', 'tarefalv'), $result);
        $grade = $tarefalv->get_user_grade($this->students[0]->id, false);
        $this->assertFalse($grade);

        // Attempt to grade a the attempt.
        $submission = $tarefalv->get_user_submission($this->students[0]->id, false);
        $data = array(
            'grademodified_' . $this->students[0]->id => '',
            'gradeattempt_' . $this->students[0]->id => $submission->attemptnumber,
            'quickgrade_' . $this->students[0]->id => '40.0'
        );
        $result = $tarefalv->testable_process_save_quick_grades($data);
        $this->assertContains(get_string('quickgradingchangessaved', 'tarefalv'), $result);
        $grade = $tarefalv->get_user_grade($this->students[0]->id, false);
        $this->assertEquals('40.0', $grade->grade);

        // Catch grade update conflicts.
        // Save old data for later.
        $pastdata = $data;
        // Update the grade the 'good' way.
        $data = array(
            'grademodified_' . $this->students[0]->id => $grade->timemodified,
            'gradeattempt_' . $this->students[0]->id => $grade->attemptnumber,
            'quickgrade_' . $this->students[0]->id => '30.0'
        );
        $result = $tarefalv->testable_process_save_quick_grades($data);
        $this->assertContains(get_string('quickgradingchangessaved', 'tarefalv'), $result);
        $grade = $tarefalv->get_user_grade($this->students[0]->id, false);
        $this->assertEquals('30.0', $grade->grade);

        // Now update using 'old' data. Should fail.
        $result = $tarefalv->testable_process_save_quick_grades($pastdata);
        $this->assertContains(get_string('errorrecordmodified', 'tarefalv'), $result);
        $grade = $tarefalv->get_user_grade($this->students[0]->id, false);
        $this->assertEquals('30.0', $grade->grade);
    }

    /**
     * Test updating activity completion when submitting an assessment.
     */
    public function test_update_activity_completion_records_solitary_submission() {
        $tarefalv = $this->create_instance(array('grade' => 100,
                'completion' => COMPLETION_TRACKING_AUTOMATIC,
                'requireallteammemberssubmit' => 0));

        $cm = $tarefalv->get_course_module();

        $student = $this->students[0];
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_tarefalv');

        $this->setUser($student);

        // Simulate a submission.
        $data = new stdClass();
        $data->onlinetext_editor = array(
            'itemid' => file_get_unused_draft_itemid(),
            'text' => 'Student submission text',
            'format' => FORMAT_MOODLE
        );
        $completion = new completion_info($this->course);

        $notices = array();
        $tarefalv->save_submission($data, $notices);

        $submission = $tarefalv->get_user_submission($student->id, true);

        // Check that completion is not met yet.
        $completiondata = $completion->get_data($cm, false, $student->id);
        $this->assertEquals(0, $completiondata->completionstate);
        $tarefalv->testable_update_activity_completion_records(0, 0, $submission,
                $student->id, COMPLETION_COMPLETE, $completion);
        // Completion should now be met.
        $completiondata = $completion->get_data($cm, false, $student->id);
        $this->assertEquals(1, $completiondata->completionstate);
    }

    /**
     * Test updating activity completion when submitting an assessment.
     */
    public function test_update_activity_completion_records_team_submission() {
        $tarefalv = $this->create_instance(array('grade' => 100,
                'completion' => COMPLETION_TRACKING_AUTOMATIC,
                 'teamsubmission' => 1));

        $cm = $tarefalv->get_course_module();

        $student1 = $this->students[0];
        $student2 = $this->students[1];
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_tarefalv');

        // Put both users into a group.
        $group1 = $this->getDataGenerator()->create_group(array('courseid' => $this->course->id));
        $this->getDataGenerator()->create_group_member(array('groupid' => $group1->id, 'userid' => $student1->id));
        $this->getDataGenerator()->create_group_member(array('groupid' => $group1->id, 'userid' => $student2->id));

        $this->setUser($student1);

        // Simulate a submission.
        $data = new stdClass();
        $data->onlinetext_editor = array(
            'itemid' => file_get_unused_draft_itemid(),
            'text' => 'Student submission text',
            'format' => FORMAT_MOODLE
        );
        $completion = new completion_info($this->course);

        $notices = array();
        $tarefalv->save_submission($data, $notices);

        $submission = $tarefalv->get_user_submission($student1->id, true);
        $submission->status = TAREFALV_SUBMISSION_STATUS_SUBMITTED;
        $submission->groupid = $group1->id;

        // Check that completion is not met yet.
        $completiondata = $completion->get_data($cm, false, $student1->id);
        $this->assertEquals(0, $completiondata->completionstate);
        $completiondata = $completion->get_data($cm, false, $student2->id);
        $this->assertEquals(0, $completiondata->completionstate);
        $tarefalv->testable_update_activity_completion_records(1, 0, $submission, $student1->id,
                COMPLETION_COMPLETE, $completion);
        // Completion should now be met.
        $completiondata = $completion->get_data($cm, false, $student1->id);
        $this->assertEquals(1, $completiondata->completionstate);
        $completiondata = $completion->get_data($cm, false, $student2->id);
        $this->assertEquals(1, $completiondata->completionstate);
    }

    public function get_assignments_with_rescaled_null_grades_provider() {
        return [
            'Negative less than one is errant' => [
                'grade' => -0.64,
                'count' => 1,
            ],
            'Negative more than one is errant' => [
                'grade' => -30.18,
                'count' => 1,
            ],
            'Negative one exactly is not errant' => [
                'grade' => TAREFALV_GRADE_NOT_SET,
                'count' => 0,
            ],
            'Positive grade is not errant' => [
                'grade' => 1,
                'count' => 0,
            ],
            'Large grade is not errant' => [
                'grade' => 100,
                'count' => 0,
            ],
            'Zero grade is not errant' => [
                'grade' => 0,
                'count' => 0,
            ],
        ];
    }

    /**
     * Test determining if the assignment as any null grades that were rescaled.
     * @dataProvider get_assignments_with_rescaled_null_grades_provider
     */
    public function test_get_assignments_with_rescaled_null_grades($grade, $count) {
        global $DB;

        $this->resetAfterTest();

        $tarefalv = $this->create_instance(array('grade' => 100));

        // Try getting a student's grade. This will give a grade of -1.
        // Then we can override it with a bad negative grade.
        $tarefalv->get_user_grade($this->students[0]->id, true);

        // Set the grade to something errant.
        $DB->set_field(
            'tarefalv_grades',
            'grade',
            $grade,
            [
                'userid' => $this->students[0]->id,
                'assignment' => $tarefalv->get_instance()->id,
            ]
        );

        $this->assertCount($count, get_assignments_with_rescaled_null_grades());
    }

    /**
     * Data provider for test_fix_null_grades
     * @return array[] Test data for test_fix_null_grades. Each element should contain grade, expectedcount and gradebookvalue
     */
    public function fix_null_grades_provider() {
        return [
            'Negative less than one is errant' => [
                'grade' => -0.64,
                'gradebookvalue' => null,
            ],
            'Negative more than one is errant' => [
                'grade' => -30.18,
                'gradebookvalue' => null,
            ],
            'Negative one exactly is not errant, but shouldn\'t be pushed to gradebook' => [
                'grade' => TAREFALV_GRADE_NOT_SET,
                'gradebookvalue' => null,
            ],
            'Positive grade is not errant' => [
                'grade' => 1,
                'gradebookvalue' => 1,
            ],
            'Large grade is not errant' => [
                'grade' => 100,
                'gradebookvalue' => 100,
            ],
            'Zero grade is not errant' => [
                'grade' => 0,
                'gradebookvalue' => 0,
            ],
        ];
    }

    /**
     * Test fix_null_grades
     * @param number $grade The grade we should set in the tarefalv grading table.
     * @param number $expectedcount The finalgrade we expect in the gradebook after fixing the grades.
     * @dataProvider fix_null_grades_provider
     */
    public function test_fix_null_grades($grade, $gradebookvalue) {
        global $DB;

        $this->resetAfterTest();

        $studentid = $this->students[0]->id;

        $tarefalv = $this->create_instance();

        // Try getting a student's grade. This will give a grade of -1.
        // Then we can override it with a bad negative grade.
        $tarefalv->get_user_grade($studentid, true);

        // Set the grade to something errant.
        $DB->set_field(
            'tarefalv_grades',
            'grade',
            $grade,
            [
                'userid' => $studentid,
                'assignment' => $tarefalv->get_instance()->id,
            ]
        );
        $tarefalv->grade = $grade;
        $tarefalvtemp = clone $tarefalv->get_instance();
        $tarefalvtemp->cmidnumber = $tarefalv->get_course_module()->idnumber;
        tarefalv_update_grades($tarefalvtemp);

        // Check that the gradebook was updated with the tarefalv grade. So we can guarentee test results later on.
        $expectedgrade = $grade == -1 ? null : $grade; // Tarefalv sends null to the gradebook for -1 grades.
        $gradegrade = grade_grade::fetch(array('userid' => $studentid, 'itemid' => $tarefalv->get_grade_item()->id));
        $this->assertEquals($expectedgrade, $gradegrade->rawgrade);

        // Call fix_null_grades().
        $method = new ReflectionMethod(tarefalv::class, 'fix_null_grades');
        $method->setAccessible(true);
        $result = $method->invoke($tarefalv);

        $this->assertSame(true, $result);

        $gradegrade = grade_grade::fetch(array('userid' => $studentid, 'itemid' => $tarefalv->get_grade_item()->id));

        // Check that the grade was updated in the gradebook by fix_null_grades.
        $this->assertEquals($gradebookvalue, $gradegrade->finalgrade);
    }

    /**
     * Test grade override displays 'Graded' for students
     */
    public function test_grade_submission_override() {
        global $DB, $PAGE, $OUTPUT;

        $this->setUser($this->editingteachers[0]);
        $tarefalv = $this->create_instance(array('tarefalvsubmission_onlinetext_enabled' => 1));

        $studentid = $this->students[0]->id;

        // Simulate adding a grade.
        $this->setUser($this->teachers[0]);
        $data = new stdClass();
        $data->grade = '50.0';
        $tarefalv->testable_apply_grade_to_user($data, $studentid, 0);

        // Set grade override.
        $gradegrade = grade_grade::fetch(array('userid' => $studentid, 'itemid' => $tarefalv->get_grade_item()->id));

        // Check that grade submission is not overridden yet.
        $this->assertEquals(false, $gradegrade->is_overridden());

        // Simulate a submission.
        $this->setUser($this->students[0]);
        $submission = $tarefalv->get_user_submission($studentid, true);

        $PAGE->set_url(new moodle_url('/mod/tarefalv/view.php', array('id' => $tarefalv->get_course_module()->id)));

        // Set override grade grade, and check that grade submission has been overridden.
        $gradegrade->set_overridden(true);
        $this->assertEquals(true, $gradegrade->is_overridden());

        // Check that submissionslocked message 'This assignment is not accepting submissions' does not appear for student.
        $gradingtable = new tarefalv_grading_table($tarefalv, 1, '', 0, true);
        $output = $tarefalv->get_renderer()->render($gradingtable);
        $this->assertContains(get_string('submissionstatus_', 'tarefalv'), $output);

        $tarefalvsubmissionstatus = $tarefalv->get_tarefalv_submission_status_renderable($this->students[0], true);
        $output2 = $tarefalv->get_renderer()->render($tarefalvsubmissionstatus);

        // Check that submissionslocked 'This assignment is not accepting submissions' message does not appear for student.
        $this->assertNotContains(get_string('submissionslocked', 'tarefalv'), $output2);
        // Check that submissionstatus_marked 'Graded' message does appear for student.
        $this->assertContains(get_string('submissionstatus_marked', 'tarefalv'), $output2);
    }
}
