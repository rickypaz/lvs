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

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/webservice/tests/helpers.php');

/**
 * External mod tarefalv functions unit tests
 *
 * @package mod_tarefalv
 * @category external
 * @copyright 2012 Paul Charsley
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_tarefalv_external_testcase extends externallib_advanced_testcase {

    /**
     * Tests set up
     */
    protected function setUp() {
        global $CFG;
        require_once($CFG->dirroot . '/mod/tarefalv/externallib.php');
    }

    /**
     * Test get_grades
     */
    public function test_get_grades() {
        global $DB, $USER;

        $this->resetAfterTest(true);
        // Create a course and assignment.
        $coursedata['idnumber'] = 'idnumbercourse';
        $coursedata['fullname'] = 'Lightwork Course';
        $coursedata['summary'] = 'Lightwork Course description';
        $coursedata['summaryformat'] = FORMAT_MOODLE;
        $course = self::getDataGenerator()->create_course($coursedata);

        $tarefalvdata['course'] = $course->id;
        $tarefalvdata['name'] = 'lightwork assignment';

        $tarefalv = self::getDataGenerator()->create_module('tarefalv', $tarefalvdata);

        // Create a manual enrolment record.
        $manualenroldata['enrol'] = 'manual';
        $manualenroldata['status'] = 0;
        $manualenroldata['courseid'] = $course->id;
        $enrolid = $DB->insert_record('enrol', $manualenroldata);

        // Create a teacher and give them capabilities.
        $context = context_course::instance($course->id);
        $roleid = $this->tarefalvUserCapability('moodle/course:viewparticipants', $context->id, 3);
        $context = context_module::instance($tarefalv->cmid);
        $this->tarefalvUserCapability('mod/tarefalv:grade', $context->id, $roleid);

        // Create the teacher's enrolment record.
        $userenrolmentdata['status'] = 0;
        $userenrolmentdata['enrolid'] = $enrolid;
        $userenrolmentdata['userid'] = $USER->id;
        $DB->insert_record('user_enrolments', $userenrolmentdata);

        // Create a student and give them 2 grades (for 2 attempts).
        $student = self::getDataGenerator()->create_user();

        $submission = new stdClass();
        $submission->assignment = $tarefalv->id;
        $submission->userid = $student->id;
        $submission->status = TAREFALV_SUBMISSION_STATUS_NEW;
        $submission->latest = 0;
        $submission->attemptnumber = 0;
        $submission->groupid = 0;
        $submission->timecreated = time();
        $submission->timemodified = time();
        $DB->insert_record('tarefalv_submission', $submission);

        $grade = new stdClass();
        $grade->assignment = $tarefalv->id;
        $grade->userid = $student->id;
        $grade->timecreated = time();
        $grade->timemodified = $grade->timecreated;
        $grade->grader = $USER->id;
        $grade->grade = 50;
        $grade->attemptnumber = 0;
        $DB->insert_record('tarefalv_grades', $grade);

        $submission = new stdClass();
        $submission->assignment = $tarefalv->id;
        $submission->userid = $student->id;
        $submission->status = TAREFALV_SUBMISSION_STATUS_NEW;
        $submission->latest = 1;
        $submission->attemptnumber = 1;
        $submission->groupid = 0;
        $submission->timecreated = time();
        $submission->timemodified = time();
        $DB->insert_record('tarefalv_submission', $submission);

        $grade = new stdClass();
        $grade->assignment = $tarefalv->id;
        $grade->userid = $student->id;
        $grade->timecreated = time();
        $grade->timemodified = $grade->timecreated;
        $grade->grader = $USER->id;
        $grade->grade = 75;
        $grade->attemptnumber = 1;
        $DB->insert_record('tarefalv_grades', $grade);

        $assignmentids[] = $tarefalv->id;
        $result = mod_tarefalv_external::get_grades($assignmentids);

        // We need to execute the return values cleaning process to simulate the web service server.
        $result = external_api::clean_returnvalue(mod_tarefalv_external::get_grades_returns(), $result);

        // Check that the correct grade information for the student is returned.
        $this->assertEquals(1, count($result['assignments']));
        $assignment = $result['assignments'][0];
        $this->assertEquals($tarefalv->id, $assignment['assignmentid']);
        // Should only get the last grade for this student.
        $this->assertEquals(1, count($assignment['grades']));
        $grade = $assignment['grades'][0];
        $this->assertEquals($student->id, $grade['userid']);
        // Should be the last grade (not the first).
        $this->assertEquals(75, $grade['grade']);
    }

    /**
     * Test get_assignments
     */
    public function test_get_assignments() {
        global $DB, $USER, $CFG;

        $this->resetAfterTest(true);

        $category = self::getDataGenerator()->create_category(array(
            'name' => 'Test category'
        ));

        // Create a course.
        $course1 = self::getDataGenerator()->create_course(array(
            'idnumber' => 'idnumbercourse1',
            'fullname' => '<b>Lightwork Course 1</b>',      // Adding tags here to check that external_format_string works.
            'shortname' => '<b>Lightwork Course 1</b>',     // Adding tags here to check that external_format_string works.
            'summary' => 'Lightwork Course 1 description',
            'summaryformat' => FORMAT_MOODLE,
            'category' => $category->id
        ));

        // Create a second course, just for testing.
        $course2 = self::getDataGenerator()->create_course(array(
            'idnumber' => 'idnumbercourse2',
            'fullname' => 'Lightwork Course 2',
            'summary' => 'Lightwork Course 2 description',
            'summaryformat' => FORMAT_MOODLE,
            'category' => $category->id
        ));

        // Create the assignment module with links to a filerecord.
        $tarefalv1 = self::getDataGenerator()->create_module('tarefalv', array(
            'course' => $course1->id,
            'name' => 'lightwork assignment',
            'intro' => 'the assignment intro text here <a href="@@PLUGINFILE@@/intro.txt">link</a>',
            'introformat' => FORMAT_HTML,
            'markingworkflow' => 1,
            'markingallocation' => 1
        ));

        // Add a file as assignment attachment.
        $context = context_module::instance($tarefalv1->cmid);
        $filerecord = array('component' => 'mod_tarefalv', 'filearea' => 'intro', 'contextid' => $context->id, 'itemid' => 0,
                'filename' => 'intro.txt', 'filepath' => '/');
        $fs = get_file_storage();
        $fs->create_file_from_string($filerecord, 'Test intro file');

        // Create manual enrolment record.
        $enrolid = $DB->insert_record('enrol', (object)array(
            'enrol' => 'manual',
            'status' => 0,
            'courseid' => $course1->id
        ));

        // Create the user and give them capabilities.
        $context = context_course::instance($course1->id);
        $roleid = $this->tarefalvUserCapability('moodle/course:view', $context->id);
        $context = context_module::instance($tarefalv1->cmid);
        $this->tarefalvUserCapability('mod/tarefalv:view', $context->id, $roleid);

        // Create the user enrolment record.
        $DB->insert_record('user_enrolments', (object)array(
            'status' => 0,
            'enrolid' => $enrolid,
            'userid' => $USER->id
        ));

        // Add a file as assignment attachment.
        $filerecord = array('component' => 'mod_tarefalv', 'filearea' => TAREFALV_INTROATTACHMENT_FILEAREA,
                'contextid' => $context->id, 'itemid' => 0,
                'filename' => 'introattachment.txt', 'filepath' => '/');
        $fs = get_file_storage();
        $fs->create_file_from_string($filerecord, 'Test intro attachment file');

        $result = mod_tarefalv_external::get_assignments();

        // We need to execute the return values cleaning process to simulate the web service server.
        $result = external_api::clean_returnvalue(mod_tarefalv_external::get_assignments_returns(), $result);

        // Check the course and assignment are returned.
        $this->assertEquals(1, count($result['courses']));
        $course = $result['courses'][0];
        $this->assertEquals('Lightwork Course 1', $course['fullname']);
        $this->assertEquals('Lightwork Course 1', $course['shortname']);
        $this->assertEquals(1, count($course['assignments']));
        $assignment = $course['assignments'][0];
        $this->assertEquals($tarefalv1->id, $assignment['id']);
        $this->assertEquals($course1->id, $assignment['course']);
        $this->assertEquals('lightwork assignment', $assignment['name']);
        $this->assertContains('the assignment intro text here', $assignment['intro']);
        $this->assertNotEmpty($assignment['configs']);
        // Check the url of the file attatched.
        $this->assertRegExp('@"' . $CFG->wwwroot . '/webservice/pluginfile.php/\d+/mod_tarefalv/intro/intro\.txt"@', $assignment['intro']);
        $this->assertEquals(1, $assignment['markingworkflow']);
        $this->assertEquals(1, $assignment['markingallocation']);
        $this->assertEquals(0, $assignment['preventsubmissionnotingroup']);

        $this->assertCount(1, $assignment['introattachments']);
        $this->assertEquals('introattachment.txt', $assignment['introattachments'][0]['filename']);

        // Now, hide the descritption until the submission from date.
        $DB->set_field('tarefalv', 'alwaysshowdescription', 0, array('id' => $tarefalv1->id));
        $DB->set_field('tarefalv', 'allowsubmissionsfromdate', time() + DAYSECS, array('id' => $tarefalv1->id));

        $result = mod_tarefalv_external::get_assignments(array($course1->id));

        // We need to execute the return values cleaning process to simulate the web service server.
        $result = external_api::clean_returnvalue(mod_tarefalv_external::get_assignments_returns(), $result);

        $this->assertEquals(1, count($result['courses']));
        $course = $result['courses'][0];
        $this->assertEquals('Lightwork Course 1', $course['fullname']);
        $this->assertEquals(1, count($course['assignments']));
        $assignment = $course['assignments'][0];
        $this->assertEquals($tarefalv1->id, $assignment['id']);
        $this->assertEquals($course1->id, $assignment['course']);
        $this->assertEquals('lightwork assignment', $assignment['name']);
        $this->assertArrayNotHasKey('intro', $assignment);
        $this->assertArrayNotHasKey('introattachments', $assignment);
        $this->assertEquals(1, $assignment['markingworkflow']);
        $this->assertEquals(1, $assignment['markingallocation']);
        $this->assertEquals(0, $assignment['preventsubmissionnotingroup']);

        $result = mod_tarefalv_external::get_assignments(array($course2->id));

        // We need to execute the return values cleaning process to simulate the web service server.
        $result = external_api::clean_returnvalue(mod_tarefalv_external::get_assignments_returns(), $result);

        $this->assertEquals(0, count($result['courses']));
        $this->assertEquals(1, count($result['warnings']));

        // Test with non-enrolled user, but with view capabilities.
        $this->setAdminUser();
        $result = mod_tarefalv_external::get_assignments();
        $result = external_api::clean_returnvalue(mod_tarefalv_external::get_assignments_returns(), $result);
        $this->assertEquals(0, count($result['courses']));
        $this->assertEquals(0, count($result['warnings']));

        // Expect no courses, because we are not using the special flag.
        $result = mod_tarefalv_external::get_assignments(array($course1->id));
        $result = external_api::clean_returnvalue(mod_tarefalv_external::get_assignments_returns(), $result);
        $this->assertCount(0, $result['courses']);

        // Now use the special flag to return courses where you are not enroled in.
        $result = mod_tarefalv_external::get_assignments(array($course1->id), array(), true);
        $result = external_api::clean_returnvalue(mod_tarefalv_external::get_assignments_returns(), $result);
        $this->assertCount(1, $result['courses']);

        $course = $result['courses'][0];
        $this->assertEquals('Lightwork Course 1', $course['fullname']);
        $this->assertEquals(1, count($course['assignments']));
        $assignment = $course['assignments'][0];
        $this->assertEquals($tarefalv1->id, $assignment['id']);
        $this->assertEquals($course1->id, $assignment['course']);
        $this->assertEquals('lightwork assignment', $assignment['name']);
        $this->assertArrayNotHasKey('intro', $assignment);
        $this->assertArrayNotHasKey('introattachments', $assignment);
        $this->assertEquals(1, $assignment['markingworkflow']);
        $this->assertEquals(1, $assignment['markingallocation']);
        $this->assertEquals(0, $assignment['preventsubmissionnotingroup']);
    }

    /**
     * Test get_assignments with submissionstatement.
     */
    public function test_get_assignments_with_submissionstatement() {
        global $DB, $USER, $CFG;

        $this->resetAfterTest(true);

        // Setup test data. Create 2 tarefalvs, one with requiresubmissionstatement and the other without it.
        $course = $this->getDataGenerator()->create_course();
        $tarefalv = $this->getDataGenerator()->create_module('tarefalv', array(
            'course' => $course->id,
            'requiresubmissionstatement' => 1
        ));
        $tarefalv2 = $this->getDataGenerator()->create_module('tarefalv', array('course' => $course->id));

        // Create student.
        $student = self::getDataGenerator()->create_user();

        // Users enrolments.
        $studentrole = $DB->get_record('role', array('shortname' => 'student'));
        $this->getDataGenerator()->enrol_user($student->id, $course->id, $studentrole->id, 'manual');

        // Update the submissionstatement.
        $submissionstatement = 'This is a fake submission statement.';
        set_config('submissionstatement', $submissionstatement, 'tarefalv');

        $this->setUser($student);

        $result = mod_tarefalv_external::get_assignments();
        // We need to execute the return values cleaning process to simulate the web service server.
        $result = external_api::clean_returnvalue(mod_tarefalv_external::get_assignments_returns(), $result);

        // Check that the amount of courses and assignments is right.
        $this->assertCount(1, $result['courses']);
        $assignmentsret = $result['courses'][0]['assignments'];
        $this->assertCount(2, $assignmentsret);

        // Order the returned assignments by ID.
        usort($assignmentsret, function($a, $b) {
            return strcmp($a['id'], $b['id']);
        });

        // Check that the first tarefalv contains the submission statement.
        $assignmentret = $assignmentsret[0];
        $this->assertEquals($tarefalv->id, $assignmentret['id']);
        $this->assertEquals(1, $assignmentret['requiresubmissionstatement']);
        $this->assertEquals($submissionstatement, $assignmentret['submissionstatement']);

        // Check that the second tarefalv does NOT contain the submission statement.
        $assignmentret = $assignmentsret[1];
        $this->assertEquals($tarefalv2->id, $assignmentret['id']);
        $this->assertEquals(0, $assignmentret['requiresubmissionstatement']);
        $this->assertArrayNotHasKey('submissionstatement', $assignmentret);
    }

    /**
     * Test get_submissions
     */
    public function test_get_submissions() {
        global $DB, $USER;

        $this->resetAfterTest(true);
        // Create a course and assignment.
        $coursedata['idnumber'] = 'idnumbercourse1';
        $coursedata['fullname'] = 'Lightwork Course 1';
        $coursedata['summary'] = 'Lightwork Course 1 description';
        $coursedata['summaryformat'] = FORMAT_MOODLE;
        $course1 = self::getDataGenerator()->create_course($coursedata);

        $tarefalvdata['course'] = $course1->id;
        $tarefalvdata['name'] = 'lightwork assignment';

        $tarefalv1 = self::getDataGenerator()->create_module('tarefalv', $tarefalvdata);

        // Create a student with an online text submission.
        // First attempt.
        $student = self::getDataGenerator()->create_user();
        $submission = new stdClass();
        $submission->assignment = $tarefalv1->id;
        $submission->userid = $student->id;
        $submission->timecreated = time();
        $submission->timemodified = $submission->timecreated;
        $submission->status = 'draft';
        $submission->attemptnumber = 0;
        $submission->latest = 0;
        $sid = $DB->insert_record('tarefalv_submission', $submission);

        // Second attempt.
        $submission = new stdClass();
        $submission->assignment = $tarefalv1->id;
        $submission->userid = $student->id;
        $submission->timecreated = time();
        $submission->timemodified = $submission->timecreated;
        $submission->status = 'submitted';
        $submission->attemptnumber = 1;
        $submission->latest = 1;
        $sid = $DB->insert_record('tarefalv_submission', $submission);
        $submission->id = $sid;

        $onlinetextsubmission = new stdClass();
        $onlinetextsubmission->onlinetext = "<p>online test text</p>";
        $onlinetextsubmission->onlineformat = 1;
        $onlinetextsubmission->submission = $submission->id;
        $onlinetextsubmission->assignment = $tarefalv1->id;
        $DB->insert_record('tarefalvsubmission_onlinetext', $onlinetextsubmission);

        // Create manual enrolment record.
        $manualenroldata['enrol'] = 'manual';
        $manualenroldata['status'] = 0;
        $manualenroldata['courseid'] = $course1->id;
        $enrolid = $DB->insert_record('enrol', $manualenroldata);

        // Create a teacher and give them capabilities.
        $context = context_course::instance($course1->id);
        $roleid = $this->tarefalvUserCapability('moodle/course:viewparticipants', $context->id, 3);
        $context = context_module::instance($tarefalv1->cmid);
        $this->tarefalvUserCapability('mod/tarefalv:grade', $context->id, $roleid);

        // Create the teacher's enrolment record.
        $userenrolmentdata['status'] = 0;
        $userenrolmentdata['enrolid'] = $enrolid;
        $userenrolmentdata['userid'] = $USER->id;
        $DB->insert_record('user_enrolments', $userenrolmentdata);

        $assignmentids[] = $tarefalv1->id;
        $result = mod_tarefalv_external::get_submissions($assignmentids);
        $result = external_api::clean_returnvalue(mod_tarefalv_external::get_submissions_returns(), $result);

        // Check the online text submission is returned.
        $this->assertEquals(1, count($result['assignments']));
        $assignment = $result['assignments'][0];
        $this->assertEquals($tarefalv1->id, $assignment['assignmentid']);
        $this->assertEquals(1, count($assignment['submissions']));
        $submission = $assignment['submissions'][0];
        $this->assertEquals($sid, $submission['id']);
        $this->assertCount(1, $submission['plugins']);
        $this->assertEquals('notgraded', $submission['gradingstatus']);
    }

    /**
     * Test get_user_flags
     */
    public function test_get_user_flags() {
        global $DB, $USER;

        $this->resetAfterTest(true);
        // Create a course and assignment.
        $coursedata['idnumber'] = 'idnumbercourse';
        $coursedata['fullname'] = 'Lightwork Course';
        $coursedata['summary'] = 'Lightwork Course description';
        $coursedata['summaryformat'] = FORMAT_MOODLE;
        $course = self::getDataGenerator()->create_course($coursedata);

        $tarefalvdata['course'] = $course->id;
        $tarefalvdata['name'] = 'lightwork assignment';

        $tarefalv = self::getDataGenerator()->create_module('tarefalv', $tarefalvdata);

        // Create a manual enrolment record.
        $manualenroldata['enrol'] = 'manual';
        $manualenroldata['status'] = 0;
        $manualenroldata['courseid'] = $course->id;
        $enrolid = $DB->insert_record('enrol', $manualenroldata);

        // Create a teacher and give them capabilities.
        $context = context_course::instance($course->id);
        $roleid = $this->tarefalvUserCapability('moodle/course:viewparticipants', $context->id, 3);
        $context = context_module::instance($tarefalv->cmid);
        $this->tarefalvUserCapability('mod/tarefalv:grade', $context->id, $roleid);

        // Create the teacher's enrolment record.
        $userenrolmentdata['status'] = 0;
        $userenrolmentdata['enrolid'] = $enrolid;
        $userenrolmentdata['userid'] = $USER->id;
        $DB->insert_record('user_enrolments', $userenrolmentdata);

        // Create a student and give them a user flag record.
        $student = self::getDataGenerator()->create_user();
        $userflag = new stdClass();
        $userflag->assignment = $tarefalv->id;
        $userflag->userid = $student->id;
        $userflag->locked = 0;
        $userflag->mailed = 0;
        $userflag->extensionduedate = 0;
        $userflag->workflowstate = 'inmarking';
        $userflag->allocatedmarker = $USER->id;

        $DB->insert_record('tarefalv_user_flags', $userflag);

        $assignmentids[] = $tarefalv->id;
        $result = mod_tarefalv_external::get_user_flags($assignmentids);

        // We need to execute the return values cleaning process to simulate the web service server.
        $result = external_api::clean_returnvalue(mod_tarefalv_external::get_user_flags_returns(), $result);

        // Check that the correct user flag information for the student is returned.
        $this->assertEquals(1, count($result['assignments']));
        $assignment = $result['assignments'][0];
        $this->assertEquals($tarefalv->id, $assignment['assignmentid']);
        // Should be one user flag record.
        $this->assertEquals(1, count($assignment['userflags']));
        $userflag = $assignment['userflags'][0];
        $this->assertEquals($student->id, $userflag['userid']);
        $this->assertEquals(0, $userflag['locked']);
        $this->assertEquals(0, $userflag['mailed']);
        $this->assertEquals(0, $userflag['extensionduedate']);
        $this->assertEquals('inmarking', $userflag['workflowstate']);
        $this->assertEquals($USER->id, $userflag['allocatedmarker']);
    }

    /**
     * Test get_user_mappings
     */
    public function test_get_user_mappings() {
        global $DB, $USER;

        $this->resetAfterTest(true);
        // Create a course and assignment.
        $coursedata['idnumber'] = 'idnumbercourse';
        $coursedata['fullname'] = 'Lightwork Course';
        $coursedata['summary'] = 'Lightwork Course description';
        $coursedata['summaryformat'] = FORMAT_MOODLE;
        $course = self::getDataGenerator()->create_course($coursedata);

        $tarefalvdata['course'] = $course->id;
        $tarefalvdata['name'] = 'lightwork assignment';

        $tarefalv = self::getDataGenerator()->create_module('tarefalv', $tarefalvdata);

        // Create a manual enrolment record.
        $manualenroldata['enrol'] = 'manual';
        $manualenroldata['status'] = 0;
        $manualenroldata['courseid'] = $course->id;
        $enrolid = $DB->insert_record('enrol', $manualenroldata);

        // Create a teacher and give them capabilities.
        $context = context_course::instance($course->id);
        $roleid = $this->tarefalvUserCapability('moodle/course:viewparticipants', $context->id, 3);
        $context = context_module::instance($tarefalv->cmid);
        $this->tarefalvUserCapability('mod/tarefalv:revealidentities', $context->id, $roleid);

        // Create the teacher's enrolment record.
        $userenrolmentdata['status'] = 0;
        $userenrolmentdata['enrolid'] = $enrolid;
        $userenrolmentdata['userid'] = $USER->id;
        $DB->insert_record('user_enrolments', $userenrolmentdata);

        // Create a student and give them a user mapping record.
        $student = self::getDataGenerator()->create_user();
        $mapping = new stdClass();
        $mapping->assignment = $tarefalv->id;
        $mapping->userid = $student->id;

        $DB->insert_record('tarefalv_user_mapping', $mapping);

        $assignmentids[] = $tarefalv->id;
        $result = mod_tarefalv_external::get_user_mappings($assignmentids);

        // We need to execute the return values cleaning process to simulate the web service server.
        $result = external_api::clean_returnvalue(mod_tarefalv_external::get_user_mappings_returns(), $result);

        // Check that the correct user mapping information for the student is returned.
        $this->assertEquals(1, count($result['assignments']));
        $assignment = $result['assignments'][0];
        $this->assertEquals($tarefalv->id, $assignment['assignmentid']);
        // Should be one user mapping record.
        $this->assertEquals(1, count($assignment['mappings']));
        $mapping = $assignment['mappings'][0];
        $this->assertEquals($student->id, $mapping['userid']);
    }

    /**
     * Test lock_submissions
     *
     * @expectedException moodle_exception
     */
    public function test_lock_submissions() {
        global $DB, $USER;

        $this->resetAfterTest(true);
        // Create a course and assignment and users.
        $course = self::getDataGenerator()->create_course();

        $generator = $this->getDataGenerator()->get_plugin_generator('mod_tarefalv');
        $params['course'] = $course->id;
        $params['tarefalvsubmission_onlinetext_enabled'] = 1;
        $instance = $generator->create_instance($params);
        $cm = get_coursemodule_from_instance('tarefalv', $instance->id);
        $context = context_module::instance($cm->id);

        $tarefalv = new tarefalv($context, $cm, $course);

        $student1 = self::getDataGenerator()->create_user();
        $student2 = self::getDataGenerator()->create_user();
        $studentrole = $DB->get_record('role', array('shortname'=>'student'));
        $this->getDataGenerator()->enrol_user($student1->id,
                                              $course->id,
                                              $studentrole->id);
        $this->getDataGenerator()->enrol_user($student2->id,
                                              $course->id,
                                              $studentrole->id);
        $teacher = self::getDataGenerator()->create_user();
        $teacherrole = $DB->get_record('role', array('shortname'=>'teacher'));
        $this->getDataGenerator()->enrol_user($teacher->id,
                                              $course->id,
                                              $teacherrole->id);

        // Create a student1 with an online text submission.
        // Simulate a submission.
        $this->setUser($student1);
        $submission = $tarefalv->get_user_submission($student1->id, true);
        $data = new stdClass();
        $data->onlinetext_editor = array('itemid'=>file_get_unused_draft_itemid(),
                                         'text'=>'Submission text',
                                         'format'=>FORMAT_MOODLE);
        $plugin = $tarefalv->get_submission_plugin_by_type('onlinetext');
        $plugin->save($submission, $data);

        // Ready to test.
        $this->setUser($teacher);
        $students = array($student1->id, $student2->id);
        $result = mod_tarefalv_external::lock_submissions($instance->id, $students);
        $result = external_api::clean_returnvalue(mod_tarefalv_external::lock_submissions_returns(), $result);

        // Check for 0 warnings.
        $this->assertEquals(0, count($result));

        $this->setUser($student2);
        $submission = $tarefalv->get_user_submission($student2->id, true);
        $data = new stdClass();
        $data->onlinetext_editor = array('itemid'=>file_get_unused_draft_itemid(),
                                         'text'=>'Submission text',
                                         'format'=>FORMAT_MOODLE);
        $notices = array();
        $tarefalv->save_submission($data, $notices);
    }

    /**
     * Test unlock_submissions
     */
    public function test_unlock_submissions() {
        global $DB, $USER;

        $this->resetAfterTest(true);
        // Create a course and assignment and users.
        $course = self::getDataGenerator()->create_course();

        $generator = $this->getDataGenerator()->get_plugin_generator('mod_tarefalv');
        $params['course'] = $course->id;
        $params['tarefalvsubmission_onlinetext_enabled'] = 1;
        $instance = $generator->create_instance($params);
        $cm = get_coursemodule_from_instance('tarefalv', $instance->id);
        $context = context_module::instance($cm->id);

        $tarefalv = new tarefalv($context, $cm, $course);

        $student1 = self::getDataGenerator()->create_user();
        $student2 = self::getDataGenerator()->create_user();
        $studentrole = $DB->get_record('role', array('shortname'=>'student'));
        $this->getDataGenerator()->enrol_user($student1->id,
                                              $course->id,
                                              $studentrole->id);
        $this->getDataGenerator()->enrol_user($student2->id,
                                              $course->id,
                                              $studentrole->id);
        $teacher = self::getDataGenerator()->create_user();
        $teacherrole = $DB->get_record('role', array('shortname'=>'teacher'));
        $this->getDataGenerator()->enrol_user($teacher->id,
                                              $course->id,
                                              $teacherrole->id);

        // Create a student1 with an online text submission.
        // Simulate a submission.
        $this->setUser($student1);
        $submission = $tarefalv->get_user_submission($student1->id, true);
        $data = new stdClass();
        $data->onlinetext_editor = array('itemid'=>file_get_unused_draft_itemid(),
                                         'text'=>'Submission text',
                                         'format'=>FORMAT_MOODLE);
        $plugin = $tarefalv->get_submission_plugin_by_type('onlinetext');
        $plugin->save($submission, $data);

        // Ready to test.
        $this->setUser($teacher);
        $students = array($student1->id, $student2->id);
        $result = mod_tarefalv_external::lock_submissions($instance->id, $students);
        $result = external_api::clean_returnvalue(mod_tarefalv_external::lock_submissions_returns(), $result);

        // Check for 0 warnings.
        $this->assertEquals(0, count($result));

        $result = mod_tarefalv_external::unlock_submissions($instance->id, $students);
        $result = external_api::clean_returnvalue(mod_tarefalv_external::unlock_submissions_returns(), $result);

        // Check for 0 warnings.
        $this->assertEquals(0, count($result));

        $this->setUser($student2);
        $submission = $tarefalv->get_user_submission($student2->id, true);
        $data = new stdClass();
        $data->onlinetext_editor = array('itemid'=>file_get_unused_draft_itemid(),
                                         'text'=>'Submission text',
                                         'format'=>FORMAT_MOODLE);
        $notices = array();
        $tarefalv->save_submission($data, $notices);
    }

    /**
     * Test submit_for_grading
     */
    public function test_submit_for_grading() {
        global $DB, $USER;

        $this->resetAfterTest(true);
        // Create a course and assignment and users.
        $course = self::getDataGenerator()->create_course();

        set_config('submissionreceipts', 0, 'tarefalv');
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_tarefalv');
        $params['course'] = $course->id;
        $params['tarefalvsubmission_onlinetext_enabled'] = 1;
        $params['submissiondrafts'] = 1;
        $params['sendnotifications'] = 0;
        $params['requiresubmissionstatement'] = 1;
        $instance = $generator->create_instance($params);
        $cm = get_coursemodule_from_instance('tarefalv', $instance->id);
        $context = context_module::instance($cm->id);

        $tarefalv = new tarefalv($context, $cm, $course);

        $student1 = self::getDataGenerator()->create_user();
        $studentrole = $DB->get_record('role', array('shortname'=>'student'));
        $this->getDataGenerator()->enrol_user($student1->id,
                                              $course->id,
                                              $studentrole->id);

        // Create a student1 with an online text submission.
        // Simulate a submission.
        $this->setUser($student1);
        $submission = $tarefalv->get_user_submission($student1->id, true);
        $data = new stdClass();
        $data->onlinetext_editor = array('itemid'=>file_get_unused_draft_itemid(),
                                         'text'=>'Submission text',
                                         'format'=>FORMAT_MOODLE);
        $plugin = $tarefalv->get_submission_plugin_by_type('onlinetext');
        $plugin->save($submission, $data);

        $result = mod_tarefalv_external::submit_for_grading($instance->id, false);
        $result = external_api::clean_returnvalue(mod_tarefalv_external::submit_for_grading_returns(), $result);

        // Should be 1 fail because the submission statement was not aceptted.
        $this->assertEquals(1, count($result));

        $result = mod_tarefalv_external::submit_for_grading($instance->id, true);
        $result = external_api::clean_returnvalue(mod_tarefalv_external::submit_for_grading_returns(), $result);

        // Check for 0 warnings.
        $this->assertEquals(0, count($result));

        $submission = $tarefalv->get_user_submission($student1->id, false);

        $this->assertEquals(TAREFALV_SUBMISSION_STATUS_SUBMITTED, $submission->status);
    }

    /**
     * Test save_user_extensions
     */
    public function test_save_user_extensions() {
        global $DB, $USER;

        $this->resetAfterTest(true);
        // Create a course and assignment and users.
        $course = self::getDataGenerator()->create_course();

        $teacher = self::getDataGenerator()->create_user();
        $teacherrole = $DB->get_record('role', array('shortname'=>'teacher'));
        $this->getDataGenerator()->enrol_user($teacher->id,
                                              $course->id,
                                              $teacherrole->id);
        $this->setUser($teacher);

        $now = time();
        $yesterday = $now - 24*60*60;
        $tomorrow = $now + 24*60*60;
        set_config('submissionreceipts', 0, 'tarefalv');
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_tarefalv');
        $params['course'] = $course->id;
        $params['submissiondrafts'] = 1;
        $params['sendnotifications'] = 0;
        $params['duedate'] = $yesterday;
        $params['cutoffdate'] = $now - 10;
        $instance = $generator->create_instance($params);
        $cm = get_coursemodule_from_instance('tarefalv', $instance->id);
        $context = context_module::instance($cm->id);

        $tarefalv = new tarefalv($context, $cm, $course);

        $student1 = self::getDataGenerator()->create_user();
        $studentrole = $DB->get_record('role', array('shortname'=>'student'));
        $this->getDataGenerator()->enrol_user($student1->id,
                                              $course->id,
                                              $studentrole->id);

        $this->setUser($student1);
        $result = mod_tarefalv_external::submit_for_grading($instance->id, true);
        $result = external_api::clean_returnvalue(mod_tarefalv_external::submit_for_grading_returns(), $result);

        // Check for 0 warnings.
        $this->assertEquals(1, count($result));

        $this->setUser($teacher);
        $result = mod_tarefalv_external::save_user_extensions($instance->id, array($student1->id), array($now, $tomorrow));
        $result = external_api::clean_returnvalue(mod_tarefalv_external::save_user_extensions_returns(), $result);
        $this->assertEquals(1, count($result));

        $this->setUser($teacher);
        $result = mod_tarefalv_external::save_user_extensions($instance->id, array($student1->id), array($yesterday - 10));
        $result = external_api::clean_returnvalue(mod_tarefalv_external::save_user_extensions_returns(), $result);
        $this->assertEquals(1, count($result));

        $this->setUser($teacher);
        $result = mod_tarefalv_external::save_user_extensions($instance->id, array($student1->id), array($tomorrow));
        $result = external_api::clean_returnvalue(mod_tarefalv_external::save_user_extensions_returns(), $result);
        $this->assertEquals(0, count($result));

        $this->setUser($student1);
        $result = mod_tarefalv_external::submit_for_grading($instance->id, true);
        $result = external_api::clean_returnvalue(mod_tarefalv_external::submit_for_grading_returns(), $result);
        $this->assertEquals(0, count($result));

        $this->setUser($student1);
        $result = mod_tarefalv_external::save_user_extensions($instance->id, array($student1->id), array($now, $tomorrow));
        $result = external_api::clean_returnvalue(mod_tarefalv_external::save_user_extensions_returns(), $result);

    }

    /**
     * Test reveal_identities
     *
     * @expectedException required_capability_exception
     */
    public function test_reveal_identities() {
        global $DB, $USER;

        $this->resetAfterTest(true);
        // Create a course and assignment and users.
        $course = self::getDataGenerator()->create_course();

        $teacher = self::getDataGenerator()->create_user();
        $teacherrole = $DB->get_record('role', array('shortname'=>'teacher'));
        $this->getDataGenerator()->enrol_user($teacher->id,
                                              $course->id,
                                              $teacherrole->id);
        $this->setUser($teacher);

        $generator = $this->getDataGenerator()->get_plugin_generator('mod_tarefalv');
        $params['course'] = $course->id;
        $params['submissiondrafts'] = 1;
        $params['sendnotifications'] = 0;
        $params['blindmarking'] = 1;
        $instance = $generator->create_instance($params);
        $cm = get_coursemodule_from_instance('tarefalv', $instance->id);
        $context = context_module::instance($cm->id);

        $tarefalv = new tarefalv($context, $cm, $course);

        $student1 = self::getDataGenerator()->create_user();
        $studentrole = $DB->get_record('role', array('shortname'=>'student'));
        $this->getDataGenerator()->enrol_user($student1->id,
                                              $course->id,
                                              $studentrole->id);

        $this->setUser($student1);
        $result = mod_tarefalv_external::reveal_identities($instance->id);
        $result = external_api::clean_returnvalue(mod_tarefalv_external::reveal_identities_returns(), $result);
        $this->assertEquals(1, count($result));
        $this->assertEquals(true, $tarefalv->is_blind_marking());

        $this->setUser($teacher);
        $result = mod_tarefalv_external::reveal_identities($instance->id);
        $result = external_api::clean_returnvalue(mod_tarefalv_external::reveal_identities_returns(), $result);
        $this->assertEquals(0, count($result));
        $this->assertEquals(false, $tarefalv->is_blind_marking());

        $generator = $this->getDataGenerator()->get_plugin_generator('mod_tarefalv');
        $params['course'] = $course->id;
        $params['submissiondrafts'] = 1;
        $params['sendnotifications'] = 0;
        $params['blindmarking'] = 0;
        $instance = $generator->create_instance($params);
        $cm = get_coursemodule_from_instance('tarefalv', $instance->id);
        $context = context_module::instance($cm->id);

        $tarefalv = new tarefalv($context, $cm, $course);
        $result = mod_tarefalv_external::reveal_identities($instance->id);
        $result = external_api::clean_returnvalue(mod_tarefalv_external::reveal_identities_returns(), $result);
        $this->assertEquals(1, count($result));
        $this->assertEquals(false, $tarefalv->is_blind_marking());

    }

    /**
     * Test revert_submissions_to_draft
     */
    public function test_revert_submissions_to_draft() {
        global $DB, $USER;

        $this->resetAfterTest(true);
        set_config('submissionreceipts', 0, 'tarefalv');
        // Create a course and assignment and users.
        $course = self::getDataGenerator()->create_course();

        $generator = $this->getDataGenerator()->get_plugin_generator('mod_tarefalv');
        $params['course'] = $course->id;
        $params['sendnotifications'] = 0;
        $params['submissiondrafts'] = 1;
        $instance = $generator->create_instance($params);
        $cm = get_coursemodule_from_instance('tarefalv', $instance->id);
        $context = context_module::instance($cm->id);

        $tarefalv = new tarefalv($context, $cm, $course);

        $student1 = self::getDataGenerator()->create_user();
        $student2 = self::getDataGenerator()->create_user();
        $studentrole = $DB->get_record('role', array('shortname'=>'student'));
        $this->getDataGenerator()->enrol_user($student1->id,
                                              $course->id,
                                              $studentrole->id);
        $this->getDataGenerator()->enrol_user($student2->id,
                                              $course->id,
                                              $studentrole->id);
        $teacher = self::getDataGenerator()->create_user();
        $teacherrole = $DB->get_record('role', array('shortname'=>'teacher'));
        $this->getDataGenerator()->enrol_user($teacher->id,
                                              $course->id,
                                              $teacherrole->id);

        // Create a student1 with an online text submission.
        // Simulate a submission.
        $this->setUser($student1);
        $result = mod_tarefalv_external::submit_for_grading($instance->id, true);
        $result = external_api::clean_returnvalue(mod_tarefalv_external::submit_for_grading_returns(), $result);
        $this->assertEquals(0, count($result));

        // Ready to test.
        $this->setUser($teacher);
        $students = array($student1->id, $student2->id);
        $result = mod_tarefalv_external::revert_submissions_to_draft($instance->id, array($student1->id));
        $result = external_api::clean_returnvalue(mod_tarefalv_external::revert_submissions_to_draft_returns(), $result);

        // Check for 0 warnings.
        $this->assertEquals(0, count($result));

    }

    /**
     * Test save_submission
     */
    public function test_save_submission() {
        global $DB, $USER;

        $this->resetAfterTest(true);
        // Create a course and assignment and users.
        $course = self::getDataGenerator()->create_course();

        $teacher = self::getDataGenerator()->create_user();
        $teacherrole = $DB->get_record('role', array('shortname'=>'teacher'));
        $this->getDataGenerator()->enrol_user($teacher->id,
                                              $course->id,
                                              $teacherrole->id);
        $this->setUser($teacher);

        $generator = $this->getDataGenerator()->get_plugin_generator('mod_tarefalv');
        $params['course'] = $course->id;
        $params['tarefalvsubmission_onlinetext_enabled'] = 1;
        $params['tarefalvsubmission_file_enabled'] = 1;
        $params['tarefalvsubmission_file_maxfiles'] = 5;
        $params['tarefalvsubmission_file_maxsizebytes'] = 1024*1024;
        $instance = $generator->create_instance($params);
        $cm = get_coursemodule_from_instance('tarefalv', $instance->id);
        $context = context_module::instance($cm->id);

        $tarefalv = new tarefalv($context, $cm, $course);

        $student1 = self::getDataGenerator()->create_user();
        $student2 = self::getDataGenerator()->create_user();
        $studentrole = $DB->get_record('role', array('shortname'=>'student'));
        $this->getDataGenerator()->enrol_user($student1->id,
                                              $course->id,
                                              $studentrole->id);
        $this->getDataGenerator()->enrol_user($student2->id,
                                              $course->id,
                                              $studentrole->id);
        // Create a student1 with an online text submission.
        // Simulate a submission.
        $this->setUser($student1);

        // Create a file in a draft area.
        $draftidfile = file_get_unused_draft_itemid();

        $usercontext = context_user::instance($student1->id);
        $filerecord = array(
            'contextid' => $usercontext->id,
            'component' => 'user',
            'filearea'  => 'draft',
            'itemid'    => $draftidfile,
            'filepath'  => '/',
            'filename'  => 'testtext.txt',
        );

        $fs = get_file_storage();
        $fs->create_file_from_string($filerecord, 'text contents');

        // Create another file in a different draft area.
        $draftidonlinetext = file_get_unused_draft_itemid();

        $filerecord = array(
            'contextid' => $usercontext->id,
            'component' => 'user',
            'filearea'  => 'draft',
            'itemid'    => $draftidonlinetext,
            'filepath'  => '/',
            'filename'  => 'shouldbeanimage.txt',
        );

        $fs->create_file_from_string($filerecord, 'image contents (not really)');

        // Now try a submission.
        $submissionpluginparams = array();
        $submissionpluginparams['files_filemanager'] = $draftidfile;
        $onlinetexteditorparams = array('text' => '<p>Yeeha!</p>',
                                        'format'=>1,
                                        'itemid'=>$draftidonlinetext);
        $submissionpluginparams['onlinetext_editor'] = $onlinetexteditorparams;
        $result = mod_tarefalv_external::save_submission($instance->id, $submissionpluginparams);
        $result = external_api::clean_returnvalue(mod_tarefalv_external::save_submission_returns(), $result);

        $this->assertEquals(0, count($result));

        // Set up a due and cutoff passed date.
        $instance->duedate = time() - WEEKSECS;
        $instance->cutoffdate = time() - WEEKSECS;
        $DB->update_record('tarefalv', $instance);

        $result = mod_tarefalv_external::save_submission($instance->id, $submissionpluginparams);
        $result = external_api::clean_returnvalue(mod_tarefalv_external::save_submission_returns(), $result);

        $this->assertCount(1, $result);
        $this->assertEquals(get_string('duedatereached', 'tarefalv'), $result[0]['item']);
    }

    /**
     * Test save_grade
     */
    public function test_save_grade() {
        global $DB, $USER;

        $this->resetAfterTest(true);
        // Create a course and assignment and users.
        $course = self::getDataGenerator()->create_course();

        $teacher = self::getDataGenerator()->create_user();
        $teacherrole = $DB->get_record('role', array('shortname'=>'teacher'));
        $this->getDataGenerator()->enrol_user($teacher->id,
                                              $course->id,
                                              $teacherrole->id);
        $this->setUser($teacher);

        $generator = $this->getDataGenerator()->get_plugin_generator('mod_tarefalv');
        $params['course'] = $course->id;
        $params['tarefalvfeedback_file_enabled'] = 1;
        $params['tarefalvfeedback_comments_enabled'] = 1;
        $instance = $generator->create_instance($params);
        $cm = get_coursemodule_from_instance('tarefalv', $instance->id);
        $context = context_module::instance($cm->id);

        $tarefalv = new tarefalv($context, $cm, $course);

        $student1 = self::getDataGenerator()->create_user();
        $student2 = self::getDataGenerator()->create_user();
        $studentrole = $DB->get_record('role', array('shortname' => 'student'));
        $this->getDataGenerator()->enrol_user($student1->id,
                                              $course->id,
                                              $studentrole->id);
        $this->getDataGenerator()->enrol_user($student2->id,
                                              $course->id,
                                              $studentrole->id);
        // Simulate a grade.
        $this->setUser($teacher);

        // Create a file in a draft area.
        $draftidfile = file_get_unused_draft_itemid();

        $usercontext = context_user::instance($teacher->id);
        $filerecord = array(
            'contextid' => $usercontext->id,
            'component' => 'user',
            'filearea'  => 'draft',
            'itemid'    => $draftidfile,
            'filepath'  => '/',
            'filename'  => 'testtext.txt',
        );

        $fs = get_file_storage();
        $fs->create_file_from_string($filerecord, 'text contents');

        // Now try a grade.
        $feedbackpluginparams = array();
        $feedbackpluginparams['files_filemanager'] = $draftidfile;
        $feedbackeditorparams = array('text' => 'Yeeha!',
                                        'format' => 1);
        $feedbackpluginparams['tarefalvfeedbackcomments_editor'] = $feedbackeditorparams;
        $result = mod_tarefalv_external::save_grade($instance->id,
                                                  $student1->id,
                                                  50.0,
                                                  -1,
                                                  true,
                                                  'released',
                                                  false,
                                                  $feedbackpluginparams);
        // No warnings.
        $this->assertNull($result);

        $result = mod_tarefalv_external::get_grades(array($instance->id));
        $result = external_api::clean_returnvalue(mod_tarefalv_external::get_grades_returns(), $result);

        $this->assertEquals($result['assignments'][0]['grades'][0]['grade'], '50.0');
    }

    /**
     * Test save grades with advanced grading data
     */
    public function test_save_grades_with_advanced_grading() {
        global $DB, $USER;

        $this->resetAfterTest(true);
        // Create a course and assignment and users.
        $course = self::getDataGenerator()->create_course();

        $teacher = self::getDataGenerator()->create_user();
        $teacherrole = $DB->get_record('role', array('shortname' => 'teacher'));
        $this->getDataGenerator()->enrol_user($teacher->id,
                                              $course->id,
                                              $teacherrole->id);

        $generator = $this->getDataGenerator()->get_plugin_generator('mod_tarefalv');
        $params['course'] = $course->id;
        $params['tarefalvfeedback_file_enabled'] = 0;
        $params['tarefalvfeedback_comments_enabled'] = 0;
        $instance = $generator->create_instance($params);
        $cm = get_coursemodule_from_instance('tarefalv', $instance->id);
        $context = context_module::instance($cm->id);

        $tarefalv = new tarefalv($context, $cm, $course);

        $student1 = self::getDataGenerator()->create_user();
        $student2 = self::getDataGenerator()->create_user();
        $studentrole = $DB->get_record('role', array('shortname' => 'student'));
        $this->getDataGenerator()->enrol_user($student1->id,
                                              $course->id,
                                              $studentrole->id);
        $this->getDataGenerator()->enrol_user($student2->id,
                                              $course->id,
                                              $studentrole->id);

        $this->setUser($teacher);

        $feedbackpluginparams = array();
        $feedbackpluginparams['files_filemanager'] = 0;
        $feedbackeditorparams = array('text' => '', 'format' => 1);
        $feedbackpluginparams['tarefalvfeedbackcomments_editor'] = $feedbackeditorparams;

        // Create advanced grading data.
        // Create grading area.
        $gradingarea = array(
            'contextid' => $context->id,
            'component' => 'mod_tarefalv',
            'areaname' => 'submissions',
            'activemethod' => 'rubric'
        );
        $areaid = $DB->insert_record('grading_areas', $gradingarea);

        // Create a rubric grading definition.
        $rubricdefinition = array (
            'areaid' => $areaid,
            'method' => 'rubric',
            'name' => 'test',
            'status' => 20,
            'copiedfromid' => 1,
            'timecreated' => 1,
            'usercreated' => $teacher->id,
            'timemodified' => 1,
            'usermodified' => $teacher->id,
            'timecopied' => 0
        );
        $definitionid = $DB->insert_record('grading_definitions', $rubricdefinition);

        // Create a criterion with a level.
        $rubriccriteria = array (
             'definitionid' => $definitionid,
             'sortorder' => 1,
             'description' => 'Demonstrate an understanding of disease control',
             'descriptionformat' => 0
        );
        $criterionid = $DB->insert_record('gradingform_rubric_criteria', $rubriccriteria);
        $rubriclevel1 = array (
            'criterionid' => $criterionid,
            'score' => 50,
            'definition' => 'pass',
            'definitionformat' => 0
        );
        $rubriclevel2 = array (
            'criterionid' => $criterionid,
            'score' => 100,
            'definition' => 'excellent',
            'definitionformat' => 0
        );
        $rubriclevel3 = array (
            'criterionid' => $criterionid,
            'score' => 0,
            'definition' => 'fail',
            'definitionformat' => 0
        );
        $levelid1 = $DB->insert_record('gradingform_rubric_levels', $rubriclevel1);
        $levelid2 = $DB->insert_record('gradingform_rubric_levels', $rubriclevel2);
        $levelid3 = $DB->insert_record('gradingform_rubric_levels', $rubriclevel3);

        // Create the filling.
        $student1filling = array (
            'criterionid' => $criterionid,
            'levelid' => $levelid1,
            'remark' => 'well done you passed',
            'remarkformat' => 0
        );

        $student2filling = array (
            'criterionid' => $criterionid,
            'levelid' => $levelid2,
            'remark' => 'Excellent work',
            'remarkformat' => 0
        );

        $student1criteria = array(array('criterionid' => $criterionid, 'fillings' => array($student1filling)));
        $student1advancedgradingdata = array('rubric' => array('criteria' => $student1criteria));

        $student2criteria = array(array('criterionid' => $criterionid, 'fillings' => array($student2filling)));
        $student2advancedgradingdata = array('rubric' => array('criteria' => $student2criteria));

        $grades = array();
        $student1gradeinfo = array();
        $student1gradeinfo['userid'] = $student1->id;
        $student1gradeinfo['grade'] = 0; // Ignored since advanced grading is being used.
        $student1gradeinfo['attemptnumber'] = -1;
        $student1gradeinfo['addattempt'] = true;
        $student1gradeinfo['workflowstate'] = 'released';
        $student1gradeinfo['plugindata'] = $feedbackpluginparams;
        $student1gradeinfo['advancedgradingdata'] = $student1advancedgradingdata;
        $grades[] = $student1gradeinfo;

        $student2gradeinfo = array();
        $student2gradeinfo['userid'] = $student2->id;
        $student2gradeinfo['grade'] = 0; // Ignored since advanced grading is being used.
        $student2gradeinfo['attemptnumber'] = -1;
        $student2gradeinfo['addattempt'] = true;
        $student2gradeinfo['workflowstate'] = 'released';
        $student2gradeinfo['plugindata'] = $feedbackpluginparams;
        $student2gradeinfo['advancedgradingdata'] = $student2advancedgradingdata;
        $grades[] = $student2gradeinfo;

        $result = mod_tarefalv_external::save_grades($instance->id, false, $grades);
        $this->assertNull($result);

        $student1grade = $DB->get_record('tarefalv_grades',
                                         array('userid' => $student1->id, 'assignment' => $instance->id),
                                         '*',
                                         MUST_EXIST);
        $this->assertEquals($student1grade->grade, '50.0');

        $student2grade = $DB->get_record('tarefalv_grades',
                                         array('userid' => $student2->id, 'assignment' => $instance->id),
                                         '*',
                                         MUST_EXIST);
        $this->assertEquals($student2grade->grade, '100.0');
    }

    /**
     * Test save grades for a team submission
     *
     * @expectedException invalid_parameter_exception
     */
    public function test_save_grades_with_group_submission() {
        global $DB, $USER, $CFG;
        require_once($CFG->dirroot . '/group/lib.php');

        $this->resetAfterTest(true);
        // Create a course and assignment and users.
        $course = self::getDataGenerator()->create_course();

        $teacher = self::getDataGenerator()->create_user();
        $teacherrole = $DB->get_record('role', array('shortname' => 'teacher'));
        $this->getDataGenerator()->enrol_user($teacher->id,
                                              $course->id,
                                              $teacherrole->id);

        $groupingdata = array();
        $groupingdata['courseid'] = $course->id;
        $groupingdata['name'] = 'Group assignment grouping';

        $grouping = self::getDataGenerator()->create_grouping($groupingdata);

        $group1data = array();
        $group1data['courseid'] = $course->id;
        $group1data['name'] = 'Team 1';
        $group2data = array();
        $group2data['courseid'] = $course->id;
        $group2data['name'] = 'Team 2';

        $group1 = self::getDataGenerator()->create_group($group1data);
        $group2 = self::getDataGenerator()->create_group($group2data);

        groups_tarefalv_grouping($grouping->id, $group1->id);
        groups_tarefalv_grouping($grouping->id, $group2->id);

        $generator = $this->getDataGenerator()->get_plugin_generator('mod_tarefalv');
        $params['course'] = $course->id;
        $params['teamsubmission'] = 1;
        $params['teamsubmissiongroupingid'] = $grouping->id;
        $instance = $generator->create_instance($params);
        $cm = get_coursemodule_from_instance('tarefalv', $instance->id);
        $context = context_module::instance($cm->id);

        $tarefalv = new tarefalv($context, $cm, $course);

        $student1 = self::getDataGenerator()->create_user();
        $student2 = self::getDataGenerator()->create_user();
        $student3 = self::getDataGenerator()->create_user();
        $student4 = self::getDataGenerator()->create_user();
        $studentrole = $DB->get_record('role', array('shortname' => 'student'));
        $this->getDataGenerator()->enrol_user($student1->id,
                                              $course->id,
                                              $studentrole->id);
        $this->getDataGenerator()->enrol_user($student2->id,
                                              $course->id,
                                              $studentrole->id);
        $this->getDataGenerator()->enrol_user($student3->id,
                                              $course->id,
                                              $studentrole->id);
        $this->getDataGenerator()->enrol_user($student4->id,
                                              $course->id,
                                              $studentrole->id);

        groups_add_member($group1->id, $student1->id);
        groups_add_member($group1->id, $student2->id);
        groups_add_member($group1->id, $student3->id);
        groups_add_member($group2->id, $student4->id);
        $this->setUser($teacher);

        $feedbackpluginparams = array();
        $feedbackpluginparams['files_filemanager'] = 0;
        $feedbackeditorparams = array('text' => '', 'format' => 1);
        $feedbackpluginparams['tarefalvfeedbackcomments_editor'] = $feedbackeditorparams;

        $grades1 = array();
        $student1gradeinfo = array();
        $student1gradeinfo['userid'] = $student1->id;
        $student1gradeinfo['grade'] = 50;
        $student1gradeinfo['attemptnumber'] = -1;
        $student1gradeinfo['addattempt'] = true;
        $student1gradeinfo['workflowstate'] = 'released';
        $student1gradeinfo['plugindata'] = $feedbackpluginparams;
        $grades1[] = $student1gradeinfo;

        $student2gradeinfo = array();
        $student2gradeinfo['userid'] = $student2->id;
        $student2gradeinfo['grade'] = 75;
        $student2gradeinfo['attemptnumber'] = -1;
        $student2gradeinfo['addattempt'] = true;
        $student2gradeinfo['workflowstate'] = 'released';
        $student2gradeinfo['plugindata'] = $feedbackpluginparams;
        $grades1[] = $student2gradeinfo;

        // Expect an exception since 2 grades have been submitted for the same team.
        $result = mod_tarefalv_external::save_grades($instance->id, true, $grades1);
        $result = external_api::clean_returnvalue(mod_tarefalv_external::save_grades_returns(), $result);

        $grades2 = array();
        $student3gradeinfo = array();
        $student3gradeinfo['userid'] = $student3->id;
        $student3gradeinfo['grade'] = 50;
        $student3gradeinfo['attemptnumber'] = -1;
        $student3gradeinfo['addattempt'] = true;
        $student3gradeinfo['workflowstate'] = 'released';
        $student3gradeinfo['plugindata'] = $feedbackpluginparams;
        $grades2[] = $student3gradeinfo;

        $student4gradeinfo = array();
        $student4gradeinfo['userid'] = $student4->id;
        $student4gradeinfo['grade'] = 75;
        $student4gradeinfo['attemptnumber'] = -1;
        $student4gradeinfo['addattempt'] = true;
        $student4gradeinfo['workflowstate'] = 'released';
        $student4gradeinfo['plugindata'] = $feedbackpluginparams;
        $grades2[] = $student4gradeinfo;
        $result = mod_tarefalv_external::save_grades($instance->id, true, $grades2);
        $result = external_api::clean_returnvalue(mod_tarefalv_external::save_grades_returns(), $result);
        // There should be no warnings.
        $this->assertEquals(0, count($result));

        $student3grade = $DB->get_record('tarefalv_grades',
                                         array('userid' => $student3->id, 'assignment' => $instance->id),
                                         '*',
                                         MUST_EXIST);
        $this->assertEquals($student3grade->grade, '50.0');

        $student4grade = $DB->get_record('tarefalv_grades',
                                         array('userid' => $student4->id, 'assignment' => $instance->id),
                                         '*',
                                         MUST_EXIST);
        $this->assertEquals($student4grade->grade, '75.0');
    }

    /**
     * Test copy_previous_attempt
     */
    public function test_copy_previous_attempt() {
        global $DB, $USER;

        $this->resetAfterTest(true);
        // Create a course and assignment and users.
        $course = self::getDataGenerator()->create_course();

        $teacher = self::getDataGenerator()->create_user();
        $teacherrole = $DB->get_record('role', array('shortname'=>'teacher'));
        $this->getDataGenerator()->enrol_user($teacher->id,
                                              $course->id,
                                              $teacherrole->id);
        $this->setUser($teacher);

        $generator = $this->getDataGenerator()->get_plugin_generator('mod_tarefalv');
        $params['course'] = $course->id;
        $params['tarefalvsubmission_onlinetext_enabled'] = 1;
        $params['tarefalvsubmission_file_enabled'] = 0;
        $params['tarefalvfeedback_file_enabled'] = 0;
        $params['attemptreopenmethod'] = 'manual';
        $params['maxattempts'] = 5;
        $instance = $generator->create_instance($params);
        $cm = get_coursemodule_from_instance('tarefalv', $instance->id);
        $context = context_module::instance($cm->id);

        $tarefalv = new tarefalv($context, $cm, $course);

        $student1 = self::getDataGenerator()->create_user();
        $studentrole = $DB->get_record('role', array('shortname'=>'student'));
        $this->getDataGenerator()->enrol_user($student1->id,
                                              $course->id,
                                              $studentrole->id);
        // Now try a submission.
        $this->setUser($student1);
        $draftidonlinetext = file_get_unused_draft_itemid();
        $submissionpluginparams = array();
        $onlinetexteditorparams = array('text'=>'Yeeha!',
                                        'format'=>1,
                                        'itemid'=>$draftidonlinetext);
        $submissionpluginparams['onlinetext_editor'] = $onlinetexteditorparams;
        $submissionpluginparams['files_filemanager'] = file_get_unused_draft_itemid();
        $result = mod_tarefalv_external::save_submission($instance->id, $submissionpluginparams);
        $result = external_api::clean_returnvalue(mod_tarefalv_external::save_submission_returns(), $result);

        $this->setUser($teacher);
        // Add a grade and reopen the attempt.
        // Now try a grade.
        $feedbackpluginparams = array();
        $feedbackpluginparams['files_filemanager'] = file_get_unused_draft_itemid();
        $feedbackeditorparams = array('text'=>'Yeeha!',
                                        'format'=>1);
        $feedbackpluginparams['tarefalvfeedbackcomments_editor'] = $feedbackeditorparams;
        $result = mod_tarefalv_external::save_grade($instance->id,
                                                  $student1->id,
                                                  50.0,
                                                  -1,
                                                  true,
                                                  'released',
                                                  false,
                                                  $feedbackpluginparams);
        $this->assertNull($result);

        $this->setUser($student1);
        // Now copy the previous attempt.
        $result = mod_tarefalv_external::copy_previous_attempt($instance->id);
        $result = external_api::clean_returnvalue(mod_tarefalv_external::copy_previous_attempt_returns(), $result);
        // No warnings.
        $this->assertEquals(0, count($result));

        $this->setUser($teacher);
        $result = mod_tarefalv_external::get_submissions(array($instance->id));
        $result = external_api::clean_returnvalue(mod_tarefalv_external::get_submissions_returns(), $result);

        // Check we are now on the second attempt.
        $this->assertEquals($result['assignments'][0]['submissions'][0]['attemptnumber'], 1);
        // Check the plugins data is not empty.
        $this->assertNotEmpty($result['assignments'][0]['submissions'][0]['plugins']);

    }

    /**
     * Test set_user_flags
     */
    public function test_set_user_flags() {
        global $DB, $USER;

        $this->resetAfterTest(true);
        // Create a course and assignment.
        $coursedata['idnumber'] = 'idnumbercourse';
        $coursedata['fullname'] = 'Lightwork Course';
        $coursedata['summary'] = 'Lightwork Course description';
        $coursedata['summaryformat'] = FORMAT_MOODLE;
        $course = self::getDataGenerator()->create_course($coursedata);

        $tarefalvdata['course'] = $course->id;
        $tarefalvdata['name'] = 'lightwork assignment';

        $tarefalv = self::getDataGenerator()->create_module('tarefalv', $tarefalvdata);

        // Create a manual enrolment record.
        $manualenroldata['enrol'] = 'manual';
        $manualenroldata['status'] = 0;
        $manualenroldata['courseid'] = $course->id;
        $enrolid = $DB->insert_record('enrol', $manualenroldata);

        // Create a teacher and give them capabilities.
        $context = context_course::instance($course->id);
        $roleid = $this->tarefalvUserCapability('moodle/course:viewparticipants', $context->id, 3);
        $context = context_module::instance($tarefalv->cmid);
        $this->tarefalvUserCapability('mod/tarefalv:grade', $context->id, $roleid);

        // Create the teacher's enrolment record.
        $userenrolmentdata['status'] = 0;
        $userenrolmentdata['enrolid'] = $enrolid;
        $userenrolmentdata['userid'] = $USER->id;
        $DB->insert_record('user_enrolments', $userenrolmentdata);

        // Create a student.
        $student = self::getDataGenerator()->create_user();

        // Create test user flags record.
        $userflags = array();
        $userflag['userid'] = $student->id;
        $userflag['workflowstate'] = 'inmarking';
        $userflag['allocatedmarker'] = $USER->id;
        $userflags = array($userflag);

        $createduserflags = mod_tarefalv_external::set_user_flags($tarefalv->id, $userflags);
        // We need to execute the return values cleaning process to simulate the web service server.
        $createduserflags = external_api::clean_returnvalue(mod_tarefalv_external::set_user_flags_returns(), $createduserflags);

        $this->assertEquals($student->id, $createduserflags[0]['userid']);
        $createduserflag = $DB->get_record('tarefalv_user_flags', array('id' => $createduserflags[0]['id']));

        // Confirm that all data was inserted correctly.
        $this->assertEquals($student->id,  $createduserflag->userid);
        $this->assertEquals($tarefalv->id, $createduserflag->assignment);
        $this->assertEquals(0, $createduserflag->locked);
        $this->assertEquals(2, $createduserflag->mailed);
        $this->assertEquals(0, $createduserflag->extensionduedate);
        $this->assertEquals('inmarking', $createduserflag->workflowstate);
        $this->assertEquals($USER->id, $createduserflag->allocatedmarker);

        // Create update data.
        $userflags = array();
        $userflag['userid'] = $createduserflag->userid;
        $userflag['workflowstate'] = 'readyforreview';
        $userflags = array($userflag);

        $updateduserflags = mod_tarefalv_external::set_user_flags($tarefalv->id, $userflags);
        // We need to execute the return values cleaning process to simulate the web service server.
        $updateduserflags = external_api::clean_returnvalue(mod_tarefalv_external::set_user_flags_returns(), $updateduserflags);

        $this->assertEquals($student->id, $updateduserflags[0]['userid']);
        $updateduserflag = $DB->get_record('tarefalv_user_flags', array('id' => $updateduserflags[0]['id']));

        // Confirm that all data was updated correctly.
        $this->assertEquals($student->id,  $updateduserflag->userid);
        $this->assertEquals($tarefalv->id, $updateduserflag->assignment);
        $this->assertEquals(0, $updateduserflag->locked);
        $this->assertEquals(2, $updateduserflag->mailed);
        $this->assertEquals(0, $updateduserflag->extensionduedate);
        $this->assertEquals('readyforreview', $updateduserflag->workflowstate);
        $this->assertEquals($USER->id, $updateduserflag->allocatedmarker);
    }

    /**
     * Test view_grading_table
     *
     * @expectedException dml_missing_record_exception
     */
    public function test_view_grading_table_invalid_instance() {
        global $DB;

        $this->resetAfterTest(true);

        // Setup test data.
        $course = $this->getDataGenerator()->create_course();
        $tarefalv = $this->getDataGenerator()->create_module('tarefalv', array('course' => $course->id));
        $context = context_module::instance($tarefalv->cmid);
        $cm = get_coursemodule_from_instance('tarefalv', $tarefalv->id);

        // Test invalid instance id.
        mod_tarefalv_external::view_grading_table(0);
    }

    /**
     * Test view_grading_table
     *
     * @expectedException require_login_exception
     */
    public function test_view_grading_table_not_enrolled() {
        global $DB;

        $this->resetAfterTest(true);

        // Setup test data.
        $course = $this->getDataGenerator()->create_course();
        $tarefalv = $this->getDataGenerator()->create_module('tarefalv', array('course' => $course->id));
        $context = context_module::instance($tarefalv->cmid);
        $cm = get_coursemodule_from_instance('tarefalv', $tarefalv->id);

        // Test not-enrolled user.
        $user = self::getDataGenerator()->create_user();
        $this->setUser($user);

        mod_tarefalv_external::view_grading_table($tarefalv->id);
    }

    /**
     * Test view_grading_table
     */
    public function test_view_grading_table_correct() {
        global $DB;

        $this->resetAfterTest(true);

        // Setup test data.
        $course = $this->getDataGenerator()->create_course();
        $tarefalv = $this->getDataGenerator()->create_module('tarefalv', array('course' => $course->id));
        $context = context_module::instance($tarefalv->cmid);
        $cm = get_coursemodule_from_instance('tarefalv', $tarefalv->id);

        // Test user with full capabilities.
        $user = self::getDataGenerator()->create_user();
        $this->setUser($user);
        $teacherrole = $DB->get_record('role', array('shortname' => 'teacher'));
        $this->getDataGenerator()->enrol_user($user->id, $course->id, $teacherrole->id);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();

        $result = mod_tarefalv_external::view_grading_table($tarefalv->id);
        $result = external_api::clean_returnvalue(mod_tarefalv_external::view_grading_table_returns(), $result);

        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = array_shift($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\mod_tarefalv\event\grading_table_viewed', $event);
        $this->assertEquals($context, $event->get_context());
        $moodleurl = new \moodle_url('/mod/tarefalv/view.php', array('id' => $cm->id));
        $this->assertEquals($moodleurl, $event->get_url());
        $this->assertEventContextNotUsed($event);
        $this->assertNotEmpty($event->get_name());
    }

    /**
     * Test view_grading_table
     *
     * @expectedException        require_login_exception
     * @expectedExceptionMessage Course or activity not accessible. (Activity is hidden)
     */
    public function test_view_grading_table_without_capability() {
        global $DB;

        $this->resetAfterTest(true);

        // Setup test data.
        $course = $this->getDataGenerator()->create_course();
        $tarefalv = $this->getDataGenerator()->create_module('tarefalv', array('course' => $course->id));
        $context = context_module::instance($tarefalv->cmid);
        $cm = get_coursemodule_from_instance('tarefalv', $tarefalv->id);

        // Test user with no capabilities.
        $user = self::getDataGenerator()->create_user();
        $this->setUser($user);
        $teacherrole = $DB->get_record('role', array('shortname' => 'teacher'));
        $this->getDataGenerator()->enrol_user($user->id, $course->id, $teacherrole->id);

        // We need a explicit prohibit since this capability is only defined in authenticated user and guest roles.
        $teacherrole = $DB->get_record('role', array('shortname' => 'teacher'));
        tarefalv_capability('mod/tarefalv:view', CAP_PROHIBIT, $teacherrole->id, $context->id);
        // Empty all the caches that may be affected by this change.
        accesslib_clear_all_caches_for_unit_testing();
        course_modinfo::clear_instance_cache();

        mod_tarefalv_external::view_grading_table($tarefalv->id);
    }

    /**
     * Test subplugins availability
     */
    public function test_subplugins_availability() {
        global $CFG;

        require_once($CFG->dirroot . '/mod/tarefalv/adminlib.php');
        $this->resetAfterTest(true);

        // Hide assignment file submissiong plugin.
        $pluginmanager = new tarefalv_plugin_manager('tarefalvsubmission');
        $pluginmanager->hide_plugin('file');
        $parameters = mod_tarefalv_external::save_submission_parameters();

        $this->assertTrue(!isset($parameters->keys['plugindata']->keys['files_filemanager']));

        // Show it again and check that the value is returned as optional.
        $pluginmanager->show_plugin('file');
        $parameters = mod_tarefalv_external::save_submission_parameters();
        $this->assertTrue(isset($parameters->keys['plugindata']->keys['files_filemanager']));
        $this->assertEquals(VALUE_OPTIONAL, $parameters->keys['plugindata']->keys['files_filemanager']->required);

        // Hide feedback file submissiong plugin.
        $pluginmanager = new tarefalv_plugin_manager('tarefalvfeedback');
        $pluginmanager->hide_plugin('file');

        $parameters = mod_tarefalv_external::save_grade_parameters();

        $this->assertTrue(!isset($parameters->keys['plugindata']->keys['files_filemanager']));

        // Show it again and check that the value is returned as optional.
        $pluginmanager->show_plugin('file');
        $parameters = mod_tarefalv_external::save_grade_parameters();

        $this->assertTrue(isset($parameters->keys['plugindata']->keys['files_filemanager']));
        $this->assertEquals(VALUE_OPTIONAL, $parameters->keys['plugindata']->keys['files_filemanager']->required);

        // Check a different one.
        $pluginmanager->show_plugin('comments');
        $this->assertTrue(isset($parameters->keys['plugindata']->keys['tarefalvfeedbackcomments_editor']));
        $this->assertEquals(VALUE_OPTIONAL, $parameters->keys['plugindata']->keys['tarefalvfeedbackcomments_editor']->required);
    }

    /**
     * Test test_view_submission_status
     */
    public function test_view_submission_status() {
        global $DB;

        $this->resetAfterTest(true);

        $this->setAdminUser();
        // Setup test data.
        $course = $this->getDataGenerator()->create_course();
        $tarefalv = $this->getDataGenerator()->create_module('tarefalv', array('course' => $course->id));
        $context = context_module::instance($tarefalv->cmid);
        $cm = get_coursemodule_from_instance('tarefalv', $tarefalv->id);

        // Test invalid instance id.
        try {
            mod_tarefalv_external::view_submission_status(0);
            $this->fail('Exception expected due to invalid mod_tarefalv instance id.');
        } catch (moodle_exception $e) {
            $this->assertEquals('invalidrecord', $e->errorcode);
        }

        // Test not-enrolled user.
        $user = self::getDataGenerator()->create_user();
        $this->setUser($user);
        try {
            mod_tarefalv_external::view_submission_status($tarefalv->id);
            $this->fail('Exception expected due to not enrolled user.');
        } catch (moodle_exception $e) {
            $this->assertEquals('requireloginerror', $e->errorcode);
        }

        // Test user with full capabilities.
        $studentrole = $DB->get_record('role', array('shortname' => 'student'));
        $this->getDataGenerator()->enrol_user($user->id, $course->id, $studentrole->id);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();

        $result = mod_tarefalv_external::view_submission_status($tarefalv->id);
        $result = external_api::clean_returnvalue(mod_tarefalv_external::view_submission_status_returns(), $result);

        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = array_shift($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\mod_tarefalv\event\submission_status_viewed', $event);
        $this->assertEquals($context, $event->get_context());
        $moodleurl = new \moodle_url('/mod/tarefalv/view.php', array('id' => $cm->id));
        $this->assertEquals($moodleurl, $event->get_url());
        $this->assertEventContextNotUsed($event);
        $this->assertNotEmpty($event->get_name());

        // Test user with no capabilities.
        // We need a explicit prohibit since this capability is only defined in authenticated user and guest roles.
        tarefalv_capability('mod/tarefalv:view', CAP_PROHIBIT, $studentrole->id, $context->id);
        accesslib_clear_all_caches_for_unit_testing();
        course_modinfo::clear_instance_cache();

        try {
            mod_tarefalv_external::view_submission_status($tarefalv->id);
            $this->fail('Exception expected due to missing capability.');
        } catch (moodle_exception $e) {
            $this->assertEquals('requireloginerror', $e->errorcode);
        }
    }

    /**
     * Create a submission for testing the get_submission_status function.
     * @param  boolean $submitforgrading whether to submit for grading the submission
     * @return array an array containing all the required data for testing
     */
    private function create_submission_for_testing_status($submitforgrading = false) {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/mod/tarefalv/tests/base_test.php');

        // Create a course and assignment and users.
        $course = self::getDataGenerator()->create_course();

        $generator = $this->getDataGenerator()->get_plugin_generator('mod_tarefalv');
        $params = array(
            'course' => $course->id,
            'tarefalvsubmission_file_maxfiles' => 1,
            'tarefalvsubmission_file_maxsizebytes' => 1024 * 1024,
            'tarefalvsubmission_onlinetext_enabled' => 1,
            'tarefalvsubmission_file_enabled' => 1,
            'submissiondrafts' => 1,
            'tarefalvfeedback_file_enabled' => 1,
            'tarefalvfeedback_comments_enabled' => 1,
            'attemptreopenmethod' => TAREFALV_ATTEMPT_REOPEN_METHOD_MANUAL,
            'sendnotifications' => 0
        );

        set_config('submissionreceipts', 0, 'tarefalv');

        $instance = $generator->create_instance($params);
        $cm = get_coursemodule_from_instance('tarefalv', $instance->id);
        $context = context_module::instance($cm->id);

        $tarefalv = new testable_tarefalv($context, $cm, $course);

        $student1 = self::getDataGenerator()->create_user();
        $student2 = self::getDataGenerator()->create_user();
        $studentrole = $DB->get_record('role', array('shortname' => 'student'));
        $this->getDataGenerator()->enrol_user($student1->id, $course->id, $studentrole->id);
        $this->getDataGenerator()->enrol_user($student2->id, $course->id, $studentrole->id);
        $teacher = self::getDataGenerator()->create_user();
        $teacherrole = $DB->get_record('role', array('shortname' => 'teacher'));
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, $teacherrole->id);

        $this->setUser($student1);

        // Create a student1 with an online text submission.
        // Simulate a submission.
        $submission = $tarefalv->get_user_submission($student1->id, true);

        $data = new stdClass();
        $data->onlinetext_editor = array('itemid' => file_get_unused_draft_itemid(),
                                         'text' => 'Submission text with a <a href="@@PLUGINFILE@@/intro.txt">link</a>',
                                         'format' => FORMAT_MOODLE);

        $draftidfile = file_get_unused_draft_itemid();
        $usercontext = context_user::instance($student1->id);
        $filerecord = array(
            'contextid' => $usercontext->id,
            'component' => 'user',
            'filearea'  => 'draft',
            'itemid'    => $draftidfile,
            'filepath'  => '/',
            'filename'  => 't.txt',
        );
        $fs = get_file_storage();
        $fs->create_file_from_string($filerecord, 'text contents');

        $data->files_filemanager = $draftidfile;

        $notices = array();
        $tarefalv->save_submission($data, $notices);

        if ($submitforgrading) {
            // Now, submit the draft for grading.
            $notices = array();

            $data = new stdClass;
            $data->userid = $student1->id;
            $tarefalv->submit_for_grading($data, $notices);
        }

        return array($tarefalv, $instance, $student1, $student2, $teacher);
    }

    /**
     * Test get_submission_status for a draft submission.
     */
    public function test_get_submission_status_in_draft_status() {
        $this->resetAfterTest(true);

        list($tarefalv, $instance, $student1, $student2, $teacher) = $this->create_submission_for_testing_status();
        $studentsubmission = $tarefalv->get_user_submission($student1->id, true);

        $result = mod_tarefalv_external::get_submission_status($tarefalv->get_instance()->id);
        // We expect debugging because of the $PAGE object, this won't happen in a normal WS request.
        $this->assertDebuggingCalled();

        $result = external_api::clean_returnvalue(mod_tarefalv_external::get_submission_status_returns(), $result);

        // The submission is now in draft mode.
        $this->assertCount(0, $result['warnings']);
        $this->assertFalse(isset($result['gradingsummary']));
        $this->assertFalse(isset($result['feedback']));
        $this->assertFalse(isset($result['previousattempts']));

        $this->assertTrue($result['lastattempt']['submissionsenabled']);
        $this->assertTrue($result['lastattempt']['canedit']);
        $this->assertTrue($result['lastattempt']['cansubmit']);
        $this->assertFalse($result['lastattempt']['locked']);
        $this->assertFalse($result['lastattempt']['graded']);
        $this->assertEmpty($result['lastattempt']['extensionduedate']);
        $this->assertFalse($result['lastattempt']['blindmarking']);
        $this->assertCount(0, $result['lastattempt']['submissiongroupmemberswhoneedtosubmit']);
        $this->assertEquals('notgraded', $result['lastattempt']['gradingstatus']);

        $this->assertEquals($student1->id, $result['lastattempt']['submission']['userid']);
        $this->assertEquals(0, $result['lastattempt']['submission']['attemptnumber']);
        $this->assertEquals('draft', $result['lastattempt']['submission']['status']);
        $this->assertEquals(0, $result['lastattempt']['submission']['groupid']);
        $this->assertEquals($tarefalv->get_instance()->id, $result['lastattempt']['submission']['assignment']);
        $this->assertEquals(1, $result['lastattempt']['submission']['latest']);

        // Map plugins based on their type - we can't rely on them being in a
        // particular order, especially if 3rd party plugins are installed.
        $submissionplugins = array();
        foreach ($result['lastattempt']['submission']['plugins'] as $plugin) {
            $submissionplugins[$plugin['type']] = $plugin;
        }

        // Format expected online text.
        $onlinetext = 'Submission text with a <a href="@@PLUGINFILE@@/intro.txt">link</a>';
        list($expectedtext, $expectedformat) = external_format_text($onlinetext, FORMAT_HTML, $tarefalv->get_context()->id,
                'tarefalvsubmission_onlinetext', TAREFALVSUBMISSION_ONLINETEXT_FILEAREA, $studentsubmission->id);

        $this->assertEquals($expectedtext, $submissionplugins['onlinetext']['editorfields'][0]['text']);
        $this->assertEquals($expectedformat, $submissionplugins['onlinetext']['editorfields'][0]['format']);
        $this->assertEquals('/', $submissionplugins['file']['fileareas'][0]['files'][0]['filepath']);
        $this->assertEquals('t.txt', $submissionplugins['file']['fileareas'][0]['files'][0]['filename']);
    }

    /**
     * Test get_submission_status for a submitted submission.
     */
    public function test_get_submission_status_in_submission_status() {
        $this->resetAfterTest(true);

        list($tarefalv, $instance, $student1, $student2, $teacher) = $this->create_submission_for_testing_status(true);

        $result = mod_tarefalv_external::get_submission_status($tarefalv->get_instance()->id);
        // We expect debugging because of the $PAGE object, this won't happen in a normal WS request.
        $this->assertDebuggingCalled();
        $result = external_api::clean_returnvalue(mod_tarefalv_external::get_submission_status_returns(), $result);

        $this->assertCount(0, $result['warnings']);
        $this->assertFalse(isset($result['gradingsummary']));
        $this->assertFalse(isset($result['feedback']));
        $this->assertFalse(isset($result['previousattempts']));

        $this->assertTrue($result['lastattempt']['submissionsenabled']);
        $this->assertFalse($result['lastattempt']['canedit']);
        $this->assertFalse($result['lastattempt']['cansubmit']);
        $this->assertFalse($result['lastattempt']['locked']);
        $this->assertFalse($result['lastattempt']['graded']);
        $this->assertEmpty($result['lastattempt']['extensionduedate']);
        $this->assertFalse($result['lastattempt']['blindmarking']);
        $this->assertCount(0, $result['lastattempt']['submissiongroupmemberswhoneedtosubmit']);
        $this->assertEquals('notgraded', $result['lastattempt']['gradingstatus']);

    }

    /**
     * Test get_submission_status using the teacher role.
     */
    public function test_get_submission_status_in_submission_status_for_teacher() {
        $this->resetAfterTest(true);

        list($tarefalv, $instance, $student1, $student2, $teacher) = $this->create_submission_for_testing_status(true);

        // Now, as teacher, see the grading summary.
        $this->setUser($teacher);
        $result = mod_tarefalv_external::get_submission_status($tarefalv->get_instance()->id);
        // We expect debugging because of the $PAGE object, this won't happen in a normal WS request.
        $this->assertDebuggingCalled();
        $result = external_api::clean_returnvalue(mod_tarefalv_external::get_submission_status_returns(), $result);

        $this->assertCount(0, $result['warnings']);
        $this->assertFalse(isset($result['lastattempt']));
        $this->assertFalse(isset($result['feedback']));
        $this->assertFalse(isset($result['previousattempts']));

        $this->assertEquals(2, $result['gradingsummary']['participantcount']);
        $this->assertEquals(0, $result['gradingsummary']['submissiondraftscount']);
        $this->assertEquals(1, $result['gradingsummary']['submissionsenabled']);
        $this->assertEquals(1, $result['gradingsummary']['submissionssubmittedcount']);
        $this->assertEquals(1, $result['gradingsummary']['submissionsneedgradingcount']);
        $this->assertFalse($result['gradingsummary']['warnofungroupedusers']);
    }

    /**
     * Test get_submission_status for a reopened submission.
     */
    public function test_get_submission_status_in_reopened_status() {
        global $USER;

        $this->resetAfterTest(true);

        list($tarefalv, $instance, $student1, $student2, $teacher) = $this->create_submission_for_testing_status(true);
        $studentsubmission = $tarefalv->get_user_submission($student1->id, true);

        $this->setUser($teacher);
        // Grade and reopen.
        $feedbackpluginparams = array();
        $feedbackpluginparams['files_filemanager'] = file_get_unused_draft_itemid();
        $feedbackeditorparams = array('text' => 'Yeeha!',
                                        'format' => 1);
        $feedbackpluginparams['tarefalvfeedbackcomments_editor'] = $feedbackeditorparams;
        $result = mod_tarefalv_external::save_grade($instance->id,
                                                  $student1->id,
                                                  50.0,
                                                  -1,
                                                  false,
                                                  'released',
                                                  false,
                                                  $feedbackpluginparams);
        $USER->ignoresesskey = true;
        $tarefalv->testable_process_add_attempt($student1->id);

        $this->setUser($student1);

        $result = mod_tarefalv_external::get_submission_status($tarefalv->get_instance()->id);
        // We expect debugging because of the $PAGE object, this won't happen in a normal WS request.
        $this->assertDebuggingCalled();
        $result = external_api::clean_returnvalue(mod_tarefalv_external::get_submission_status_returns(), $result);

        $this->assertCount(0, $result['warnings']);
        $this->assertFalse(isset($result['gradingsummary']));

        $this->assertTrue($result['lastattempt']['submissionsenabled']);
        $this->assertTrue($result['lastattempt']['canedit']);
        $this->assertFalse($result['lastattempt']['cansubmit']);
        $this->assertFalse($result['lastattempt']['locked']);
        $this->assertFalse($result['lastattempt']['graded']);
        $this->assertEmpty($result['lastattempt']['extensionduedate']);
        $this->assertFalse($result['lastattempt']['blindmarking']);
        $this->assertCount(0, $result['lastattempt']['submissiongroupmemberswhoneedtosubmit']);
        $this->assertEquals('notgraded', $result['lastattempt']['gradingstatus']);

        // Check new attempt reopened.
        $this->assertEquals($student1->id, $result['lastattempt']['submission']['userid']);
        $this->assertEquals(1, $result['lastattempt']['submission']['attemptnumber']);
        $this->assertEquals('reopened', $result['lastattempt']['submission']['status']);
        $this->assertEquals(0, $result['lastattempt']['submission']['groupid']);
        $this->assertEquals($tarefalv->get_instance()->id, $result['lastattempt']['submission']['assignment']);
        $this->assertEquals(1, $result['lastattempt']['submission']['latest']);
        $this->assertCount(3, $result['lastattempt']['submission']['plugins']);

        // Now see feedback and the attempts history (remember, is a submission reopened).
        // Only 2 fields (no grade, no plugins data).
        $this->assertCount(2, $result['feedback']);

        // One previous attempt.
        $this->assertCount(1, $result['previousattempts']);
        $this->assertEquals(0, $result['previousattempts'][0]['attemptnumber']);
        $this->assertEquals(50, $result['previousattempts'][0]['grade']['grade']);
        $this->assertEquals($teacher->id, $result['previousattempts'][0]['grade']['grader']);
        $this->assertEquals($student1->id, $result['previousattempts'][0]['grade']['userid']);

        // Map plugins based on their type - we can't rely on them being in a
        // particular order, especially if 3rd party plugins are installed.
        $feedbackplugins = array();
        foreach ($result['previousattempts'][0]['feedbackplugins'] as $plugin) {
            $feedbackplugins[$plugin['type']] = $plugin;
        }
        $this->assertEquals('Yeeha!', $feedbackplugins['comments']['editorfields'][0]['text']);

        $submissionplugins = array();
        foreach ($result['previousattempts'][0]['submission']['plugins'] as $plugin) {
            $submissionplugins[$plugin['type']] = $plugin;
        }
        // Format expected online text.
        $onlinetext = 'Submission text with a <a href="@@PLUGINFILE@@/intro.txt">link</a>';
        list($expectedtext, $expectedformat) = external_format_text($onlinetext, FORMAT_HTML, $tarefalv->get_context()->id,
                'tarefalvsubmission_onlinetext', TAREFALVSUBMISSION_ONLINETEXT_FILEAREA, $studentsubmission->id);

        $this->assertEquals($expectedtext, $submissionplugins['onlinetext']['editorfields'][0]['text']);
        $this->assertEquals($expectedformat, $submissionplugins['onlinetext']['editorfields'][0]['format']);
        $this->assertEquals('/', $submissionplugins['file']['fileareas'][0]['files'][0]['filepath']);
        $this->assertEquals('t.txt', $submissionplugins['file']['fileareas'][0]['files'][0]['filename']);

    }

    /**
     * Test access control for get_submission_status.
     *
     * @expectedException required_capability_exception
     */
    public function test_get_submission_status_access_control() {
        $this->resetAfterTest(true);

        list($tarefalv, $instance, $student1, $student2, $teacher) = $this->create_submission_for_testing_status();

        $this->setUser($student2);

        // Access control test.
        mod_tarefalv_external::get_submission_status($tarefalv->get_instance()->id, $student1->id);

    }

    /**
     * get_participant should throw an excaption if the requested assignment doesn't exist.
     *
     * @expectedException moodle_exception
     */
    public function test_get_participant_no_assignment() {
        $this->resetAfterTest(true);
        mod_tarefalv_external::get_participant('-1', '-1', false);
    }

    /**
     * get_participant should throw a require_login_exception if the user doesn't have access
     * to view assignments.
     *
     * @expectedException require_login_exception
     */
    public function test_get_participant_no_view_capability() {
        global $DB;
        $this->resetAfterTest(true);

        $result = $this->create_tarefalv_with_student_and_teacher();
        $tarefalv = $result['tarefalv'];
        $student = $result['student'];
        $course = $result['course'];
        $context = context_course::instance($course->id);
        $studentrole = $DB->get_record('role', array('shortname' => 'student'));

        $this->setUser($student);
        tarefalv_capability('mod/tarefalv:view', CAP_PROHIBIT, $studentrole->id, $context->id, true);

        mod_tarefalv_external::get_participant($tarefalv->id, $student->id, false);
    }

    /**
     * get_participant should throw a required_capability_exception if the user doesn't have access
     * to view assignment grades.
     *
     * @expectedException required_capability_exception
     */
    public function test_get_participant_no_grade_capability() {
        global $DB;
        $this->resetAfterTest(true);

        $result = $this->create_tarefalv_with_student_and_teacher();
        $tarefalv = $result['tarefalv'];
        $student = $result['student'];
        $teacher = $result['teacher'];
        $course = $result['course'];
        $context = context_course::instance($course->id);
        $teacherrole = $DB->get_record('role', array('shortname' => 'teacher'));

        $this->setUser($teacher);
        tarefalv_capability('mod/tarefalv:viewgrades', CAP_PROHIBIT, $teacherrole->id, $context->id, true);
        tarefalv_capability('mod/tarefalv:grade', CAP_PROHIBIT, $teacherrole->id, $context->id, true);
        accesslib_clear_all_caches_for_unit_testing();

        mod_tarefalv_external::get_participant($tarefalv->id, $student->id, false);
    }

    /**
     * get_participant should throw an exception if the user isn't enrolled in the course.
     *
     * @expectedException moodle_exception
     */
    public function test_get_participant_no_participant() {
        global $DB;
        $this->resetAfterTest(true);

        $result = $this->create_tarefalv_with_student_and_teacher(array('blindmarking' => true));
        $student = $this->getDataGenerator()->create_user();
        $tarefalv = $result['tarefalv'];
        $teacher = $result['teacher'];

        $this->setUser($teacher);

        $result = mod_tarefalv_external::get_participant($tarefalv->id, $student->id, false);
        $result = external_api::clean_returnvalue(mod_tarefalv_external::get_participant_returns(), $result);
    }

    /**
     * get_participant should return a summarised list of details with a different fullname if blind
     * marking is on for the requested assignment.
     */
    public function test_get_participant_blind_marking() {
        global $DB;
        $this->resetAfterTest(true);

        $result = $this->create_tarefalv_with_student_and_teacher(array('blindmarking' => true));
        $tarefalv = $result['tarefalv'];
        $student = $result['student'];
        $teacher = $result['teacher'];
        $course = $result['course'];
        $context = context_course::instance($course->id);
        $teacherrole = $DB->get_record('role', array('shortname' => 'teacher'));

        $this->setUser($teacher);

        $result = mod_tarefalv_external::get_participant($tarefalv->id, $student->id, true);
        $result = external_api::clean_returnvalue(mod_tarefalv_external::get_participant_returns(), $result);
        $this->assertEquals($student->id, $result['id']);
        $this->assertFalse(fullname($student) == $result['fullname']);
        $this->assertFalse($result['submitted']);
        $this->assertFalse($result['requiregrading']);
        $this->assertFalse($result['grantedextension']);
        $this->assertTrue($result['blindmarking']);
        // Make sure we don't get any additional info.
        $this->assertArrayNotHasKey('user', $result);
    }

    /**
     * get_participant should return a summarised list of details if requested.
     */
    public function test_get_participant_no_user() {
        global $DB;
        $this->resetAfterTest(true);

        $result = $this->create_tarefalv_with_student_and_teacher();
        $tarefalvmodule = $result['tarefalv'];
        $student = $result['student'];
        $teacher = $result['teacher'];
        $course = $result['course'];
        $context = context_course::instance($course->id);
        $teacherrole = $DB->get_record('role', array('shortname' => 'teacher'));

        // Create an tarefalv instance to save a submission.
        set_config('submissionreceipts', 0, 'tarefalv');

        $cm = get_coursemodule_from_instance('tarefalv', $tarefalvmodule->id);
        $context = context_module::instance($cm->id);

        $tarefalv = new tarefalv($context, $cm, $course);

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

        $this->setUser($teacher);

        $result = mod_tarefalv_external::get_participant($tarefalvmodule->id, $student->id, false);
        $result = external_api::clean_returnvalue(mod_tarefalv_external::get_participant_returns(), $result);
        $this->assertEquals($student->id, $result['id']);
        $this->assertEquals(fullname($student), $result['fullname']);
        $this->assertTrue($result['submitted']);
        $this->assertTrue($result['requiregrading']);
        $this->assertFalse($result['grantedextension']);
        $this->assertFalse($result['blindmarking']);
        // Make sure we don't get any additional info.
        $this->assertArrayNotHasKey('user', $result);
    }

    /**
     * get_participant should return user details if requested.
     */
    public function test_get_participant_full_details() {
        global $DB;
        $this->resetAfterTest(true);

        $result = $this->create_tarefalv_with_student_and_teacher();
        $tarefalv = $result['tarefalv'];
        $student = $result['student'];
        $teacher = $result['teacher'];
        $course = $result['course'];
        $context = context_course::instance($course->id);
        $teacherrole = $DB->get_record('role', array('shortname' => 'teacher'));

        $this->setUser($teacher);

        $result = mod_tarefalv_external::get_participant($tarefalv->id, $student->id, true);
        $result = external_api::clean_returnvalue(mod_tarefalv_external::get_participant_returns(), $result);
        // Check some of the extended properties we get when requesting the user.
        $this->assertEquals($student->id, $result['id']);
        // We should get user infomation back.
        $user = $result['user'];
        $this->assertFalse(empty($user));
        $this->assertEquals($student->firstname, $user['firstname']);
        $this->assertEquals($student->lastname, $user['lastname']);
        $this->assertEquals($student->email, $user['email']);
    }

    /**
     * get_participant should return group details if a group submission was
     * submitted.
     */
    public function test_get_participant_group_submission() {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/mod/tarefalv/tests/base_test.php');

        $this->resetAfterTest(true);

        $result = $this->create_tarefalv_with_student_and_teacher(array(
            'tarefalvsubmission_onlinetext_enabled' => 1,
            'teamsubmission' => 1
        ));
        $tarefalvmodule = $result['tarefalv'];
        $student = $result['student'];
        $teacher = $result['teacher'];
        $course = $result['course'];
        $context = context_course::instance($course->id);
        $teacherrole = $DB->get_record('role', array('shortname' => 'teacher'));
        $group = $this->getDataGenerator()->create_group(array('courseid' => $course->id));
        $cm = get_coursemodule_from_instance('tarefalv', $tarefalvmodule->id);
        $context = context_module::instance($cm->id);
        $tarefalv = new testable_tarefalv($context, $cm, $course);

        groups_add_member($group, $student);

        $this->setUser($student);
        $submission = $tarefalv->get_group_submission($student->id, $group->id, true);
        $submission->status = TAREFALV_SUBMISSION_STATUS_SUBMITTED;
        $tarefalv->testable_update_submission($submission, $student->id, true, false);
        $data = new stdClass();
        $data->onlinetext_editor = array('itemid' => file_get_unused_draft_itemid(),
                                         'text' => 'Submission text',
                                         'format' => FORMAT_MOODLE);
        $plugin = $tarefalv->get_submission_plugin_by_type('onlinetext');
        $plugin->save($submission, $data);

        $this->setUser($teacher);

        $result = mod_tarefalv_external::get_participant($tarefalvmodule->id, $student->id, false);
        $result = external_api::clean_returnvalue(mod_tarefalv_external::get_participant_returns(), $result);
        // Check some of the extended properties we get when not requesting a summary.
        $this->assertEquals($student->id, $result['id']);
        $this->assertEquals($group->id, $result['groupid']);
        $this->assertEquals($group->name, $result['groupname']);
    }

    /**
     * Test for mod_tarefalv_external::list_participants().
     *
     * @throws coding_exception
     */
    public function test_list_participants_user_info_with_special_characters() {
        global $CFG, $DB;
        $this->resetAfterTest(true);
        $CFG->showuseridentity = 'idnumber,email,phone1,phone2,department,institution';

        $data = $this->create_tarefalv_with_student_and_teacher();
        $assignment = $data['tarefalv'];
        $teacher = $data['teacher'];

        // Set data for student info that contain special characters.
        $student = $data['student'];
        $student->idnumber = '<\'"1am@wesome&c00l"\'>';
        $student->phone1 = '+63 (999) 888-7777';
        $student->phone2 = '(011) [15]4-123-4567';
        $student->department = 'Arts & Sciences & \' " ¢ £ © € ¥ ® < >';
        $student->institution = 'University of Awesome People & \' " ¢ £ © € ¥ ® < >';
        // Assert that we have valid user data.
        $this->assertTrue(core_user::validate($student));
        // Update the user record.
        $DB->update_record('user', $student);

        $this->setUser($teacher);
        $participants = mod_tarefalv_external::list_participants($assignment->id, 0, '', 0, 0, false, true);
        $participants = external_api::clean_returnvalue(mod_tarefalv_external::list_participants_returns(), $participants);
        $this->assertCount(1, $participants);

        // Asser that we have a valid response data.
        $response = external_api::clean_returnvalue(mod_tarefalv_external::list_participants_returns(), $participants);
        $this->assertEquals($response, $participants);

        // Check participant data.
        $participant = $participants[0];
        $this->assertEquals($student->idnumber, $participant['idnumber']);
        $this->assertEquals($student->email, $participant['email']);
        $this->assertEquals($student->phone1, $participant['phone1']);
        $this->assertEquals($student->phone2, $participant['phone2']);
        $this->assertEquals($student->department, $participant['department']);
        $this->assertEquals($student->institution, $participant['institution']);
        $this->assertArrayHasKey('enrolledcourses', $participant);

        $participants = mod_tarefalv_external::list_participants($assignment->id, 0, '', 0, 0, false, false);
        $participants = external_api::clean_returnvalue(mod_tarefalv_external::list_participants_returns(), $participants);
        // Check that the list of courses the participant is enrolled is not returned.
        $participant = $participants[0];
        $this->assertArrayNotHasKey('enrolledcourses', $participant);
    }

    /**
     * Test for the type of the user-related properties in mod_tarefalv_external::list_participants_returns().
     */
    public function test_list_participants_returns_user_property_types() {
        // Get user properties.
        $userdesc = core_user_external::user_description();
        $this->assertTrue(isset($userdesc->keys));
        $userproperties = array_keys($userdesc->keys);

        // Get returns description for mod_tarefalv_external::list_participants_returns().
        $listreturns = mod_tarefalv_external::list_participants_returns();
        $this->assertTrue(isset($listreturns->content));
        $listreturnsdesc = $listreturns->content->keys;

        // Iterate over list returns description's keys.
        foreach ($listreturnsdesc as $key => $desc) {
            // Check if key exists in user properties and the description has a type attribute.
            if (in_array($key, $userproperties) && isset($desc->type)) {
                try {
                    // The core_user::get_property_type() method might throw a coding_exception since
                    // core_user_external::user_description() might contain properties that are not yet included in
                    // core_user's $propertiescache.
                    $propertytype = core_user::get_property_type($key);

                    // Assert that user-related property types match those of the defined in core_user.
                    $this->assertEquals($propertytype, $desc->type);
                } catch (coding_exception $e) {
                    // All good.
                }
            }
        }
    }

    /**
     * Create a a course, assignment module instance, student and teacher and enrol them in
     * the course.
     *
     * @param array $params parameters to be provided to the assignment module creation
     * @return array containing the course, assignment module, student and teacher
     */
    private function create_tarefalv_with_student_and_teacher($params = array()) {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $params = array_merge(array(
            'course' => $course->id,
            'name' => 'assignment',
            'intro' => 'assignment intro text',
        ), $params);

        // Create a course and assignment and users.
        $tarefalv = $this->getDataGenerator()->create_module('tarefalv', $params);

        $cm = get_coursemodule_from_instance('tarefalv', $tarefalv->id);
        $context = context_module::instance($cm->id);

        $student = $this->getDataGenerator()->create_user();
        $studentrole = $DB->get_record('role', array('shortname' => 'student'));
        $this->getDataGenerator()->enrol_user($student->id, $course->id, $studentrole->id);
        $teacher = $this->getDataGenerator()->create_user();
        $teacherrole = $DB->get_record('role', array('shortname' => 'teacher'));
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, $teacherrole->id);

        tarefalv_capability('mod/tarefalv:view', CAP_ALLOW, $teacherrole->id, $context->id, true);
        tarefalv_capability('mod/tarefalv:viewgrades', CAP_ALLOW, $teacherrole->id, $context->id, true);
        tarefalv_capability('mod/tarefalv:grade', CAP_ALLOW, $teacherrole->id, $context->id, true);
        accesslib_clear_all_caches_for_unit_testing();

        return array(
            'course' => $course,
            'tarefalv' => $tarefalv,
            'student' => $student,
            'teacher' => $teacher
        );
    }

    /**
     * Test test_view_tarefalv
     */
    public function test_view_tarefalv() {
        global $CFG;

        $CFG->enablecompletion = 1;
        $this->resetAfterTest();

        $this->setAdminUser();
        // Setup test data.
        $course = $this->getDataGenerator()->create_course(array('enablecompletion' => 1));
        $tarefalv = $this->getDataGenerator()->create_module('tarefalv', array('course' => $course->id),
                                                            array('completion' => 2, 'completionview' => 1));
        $context = context_module::instance($tarefalv->cmid);
        $cm = get_coursemodule_from_instance('tarefalv', $tarefalv->id);

        $result = mod_tarefalv_external::view_tarefalv($tarefalv->id);
        $result = external_api::clean_returnvalue(mod_tarefalv_external::view_tarefalv_returns(), $result);
        $this->assertTrue($result['status']);
        $this->assertEmpty($result['warnings']);

        // Check completion status.
        $completion = new completion_info($course);
        $completiondata = $completion->get_data($cm);
        $this->assertEquals(1, $completiondata->completionstate);
    }
}
