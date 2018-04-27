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
 * Unit tests for tarefalvfeedback_file
 *
 * @package    tarefalvfeedback_file
 * @copyright  2016 Adrian Greeve <adrian@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/tarefalv/tests/base_test.php');

/**
 * Unit tests for tarefalvfeedback_file
 *
 * @copyright  2016 Adrian Greeve <adrian@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tarefalvfeedback_file_testcase extends mod_tarefalv_base_testcase {

    /**
     * Create an tarefalv object and submit an online text submission.
     */
    protected function create_tarefalv_and_submit_text() {
        $tarefalv = $this->create_instance(array('tarefalvsubmission_onlinetext_enabled' => 1,
                                               'tarefalvfeedback_comments_enabled' => 1));

        $user = $this->students[0];
        $this->setUser($user);

        // Create an online text submission.
        $submission = $tarefalv->get_user_submission($user->id, true);

        $data = new stdClass();
        $data->onlinetext_editor = array(
                'text' => '<p>This is some text.</p>',
                'format' => 1,
                'itemid' => file_get_unused_draft_itemid());
        $plugin = $tarefalv->get_submission_plugin_by_type('onlinetext');
        $plugin->save($submission, $data);

        return $tarefalv;
    }

    /**
     * Test the is_feedback_modified() method for the file feedback.
     */
    public function test_is_feedback_modified() {
        $tarefalv = $this->create_tarefalv_and_submit_text();

        $this->setUser($this->teachers[0]);

        $fs = get_file_storage();
        $context = context_user::instance($this->teachers[0]->id);
        $draftitemid = file_get_unused_draft_itemid();
        file_prepare_draft_area($draftitemid, $context->id, 'tarefalvfeedback_file', 'feedback_files', 1);

        $dummy = array(
            'contextid' => $context->id,
            'component' => 'user',
            'filearea' => 'draft',
            'itemid' => $draftitemid,
            'filepath' => '/',
            'filename' => 'feedback1.txt'
        );

        $file = $fs->create_file_from_string($dummy, 'This is the first feedback file');

        // Create formdata.
        $data = new stdClass();
        $data->{'files_' . $this->students[0]->id . '_filemanager'} = $draftitemid;

        $grade = $tarefalv->get_user_grade($this->students[0]->id, true);

        // This is the first time that we are submitting feedback, so it is modified.
        $plugin = $tarefalv->get_feedback_plugin_by_type('file');
        $this->assertTrue($plugin->is_feedback_modified($grade, $data));
        // Save the feedback.
        $plugin->save($grade, $data);
        // Try again with the same data.
        $draftitemid = file_get_unused_draft_itemid();
        file_prepare_draft_area($draftitemid, $context->id, 'tarefalvfeedback_file', 'feedback_files', 1);

        $dummy['itemid'] = $draftitemid;

        $file = $fs->create_file_from_string($dummy, 'This is the first feedback file');

        // Create formdata.
        $data = new stdClass();
        $data->{'files_' . $this->students[0]->id . '_filemanager'} = $draftitemid;

        $this->assertFalse($plugin->is_feedback_modified($grade, $data));

        // Same name for the file but different content.
        $draftitemid = file_get_unused_draft_itemid();
        file_prepare_draft_area($draftitemid, $context->id, 'tarefalvfeedback_file', 'feedback_files', 1);

        $dummy['itemid'] = $draftitemid;

        $file = $fs->create_file_from_string($dummy, 'This is different feedback');

        // Create formdata.
        $data = new stdClass();
        $data->{'files_' . $this->students[0]->id . '_filemanager'} = $draftitemid;

        $this->assertTrue($plugin->is_feedback_modified($grade, $data));
        $plugin->save($grade, $data);

        // Add another file.
        $draftitemid = file_get_unused_draft_itemid();
        file_prepare_draft_area($draftitemid, $context->id, 'tarefalvfeedback_file', 'feedback_files', 1);

        $dummy['itemid'] = $draftitemid;

        $file = $fs->create_file_from_string($dummy, 'This is different feedback');
        $dummy['filename'] = 'feedback2.txt';
        $file = $fs->create_file_from_string($dummy, 'A second feedback file');

        // Create formdata.
        $data = new stdClass();
        $data->{'files_' . $this->students[0]->id . '_filemanager'} = $draftitemid;

        $this->assertTrue($plugin->is_feedback_modified($grade, $data));
        $plugin->save($grade, $data);

        // Deleting a file.
        $draftitemid = file_get_unused_draft_itemid();
        file_prepare_draft_area($draftitemid, $context->id, 'tarefalvfeedback_file', 'feedback_files', 1);

        $dummy['itemid'] = $draftitemid;

        $file = $fs->create_file_from_string($dummy, 'This is different feedback');

        // Create formdata.
        $data = new stdClass();
        $data->{'files_' . $this->students[0]->id . '_filemanager'} = $draftitemid;

        $this->assertTrue($plugin->is_feedback_modified($grade, $data));
        $plugin->save($grade, $data);

        // The file was moved to a folder.
        $draftitemid = file_get_unused_draft_itemid();
        file_prepare_draft_area($draftitemid, $context->id, 'tarefalvfeedback_file', 'feedback_files', 1);

        $dummy['itemid'] = $draftitemid;
        $dummy['filepath'] = '/testdir/';

        $file = $fs->create_file_from_string($dummy, 'This is different feedback');

        // Create formdata.
        $data = new stdClass();
        $data->{'files_' . $this->students[0]->id . '_filemanager'} = $draftitemid;

        $this->assertTrue($plugin->is_feedback_modified($grade, $data));
        $plugin->save($grade, $data);

        // No modification to the file in the folder.
        $draftitemid = file_get_unused_draft_itemid();
        file_prepare_draft_area($draftitemid, $context->id, 'tarefalvfeedback_file', 'feedback_files', 1);

        $dummy['itemid'] = $draftitemid;
        $dummy['filepath'] = '/testdir/';

        $file = $fs->create_file_from_string($dummy, 'This is different feedback');

        // Create formdata.
        $data = new stdClass();
        $data->{'files_' . $this->students[0]->id . '_filemanager'} = $draftitemid;

        $this->assertFalse($plugin->is_feedback_modified($grade, $data));
    }
}
