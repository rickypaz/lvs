<?php

/**
 * Extend the base tarefalv class for offline tarefalvs
 *
 */
class tarefalv_offline extends tarefalv_base {

    function tarefalv_offline($cmid='staticonly', $tarefalv=NULL, $cm=NULL, $course=NULL) {
        parent::tarefalv_base($cmid, $tarefalv, $cm, $course);
        $this->type = 'offline';
    }

    function supports_lateness() {
        return false;
    }

    function display_lateness($timesubmitted) {
        return '';
    }
    function print_student_answer($studentid){
        return '';//does nothing!
    }

    function prepare_new_submission($userid, $teachermodified=false) {
        $submission = new stdClass();
        $submission->tarefalv   = $this->tarefalv->id;
        $submission->userid       = $userid;
        $submission->timecreated  = time(); // needed for offline tarefalvs
        $submission->timemodified = $submission->timecreated;
        $submission->numfiles     = 0;
        $submission->data1        = '';
        $submission->data2        = '';
        $submission->grade        = -1;
        $submission->submissioncomment      = '';
        $submission->format       = 0;
        $submission->teacher      = 0;
        $submission->timemarked   = 0;
        $submission->mailed       = 0;
        return $submission;
    }

    // needed for the timemodified override
    function process_feedback($formdata=null) {
        global $CFG, $USER, $DB;
        require_once($CFG->libdir.'/gradelib.php');

        if (!$feedback = data_submitted() or !confirm_sesskey()) {      // No incoming data?
            return false;
        }

        ///For save and next, we need to know the userid to save, and the userid to go
        ///We use a new hidden field in the form, and set it to -1. If it's set, we use this
        ///as the userid to store
        if ((int)$feedback->saveuserid !== -1){
            $feedback->userid = $feedback->saveuserid;
        }

        if (!empty($feedback->cancel)) {          // User hit cancel button
            return false;
        }

        $grading_info = grade_get_grades($this->course->id, 'mod', 'tarefalv', $this->tarefalv->id, $feedback->userid);

        // store outcomes if needed
        $this->process_outcomes($feedback->userid);

        $submission = $this->get_submission($feedback->userid, true);  // Get or make one

        if (!$grading_info->items[0]->grades[$feedback->userid]->locked and
            !$grading_info->items[0]->grades[$feedback->userid]->overridden) {

            $submission->grade      = $feedback->xgrade;
            $submission->submissioncomment    = $feedback->submissioncomment_editor['text'];
            $submission->teacher    = $USER->id;
            $mailinfo = get_user_preferences('tarefalv_mailinfo', 0);
            if (!$mailinfo) {
                $submission->mailed = 1;       // treat as already mailed
            } else {
                $submission->mailed = 0;       // Make sure mail goes out (again, even)
            }
            $submission->timemarked = time();

            unset($submission->data1);  // Don't need to update this.
            unset($submission->data2);  // Don't need to update this.

            if (empty($submission->timemodified)) {   // eg for offline tarefalvs
                $submission->timemodified = time();
            }

            $DB->update_record('tarefalv_submissions', $submission);

            // trigger grade event
            $this->update_grade($submission);

            add_to_log($this->course->id, 'tarefalv', 'update grades',
                       'submissions.php?id='.$this->tarefalv->id.'&user='.$feedback->userid, $feedback->userid, $this->cm->id);
        }

        return $submission;

    }

}


