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
 * Assignment upload type implementation
 *
 * @package   mod-tarefalv
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__).'/upload_form.php');
require_once($CFG->libdir . '/portfoliolib.php');
require_once($CFG->dirroot . '/mod/tarefalv/lib.php');

/** @lvs dependências lvs em tarefalv.class */
use uab\ifce\lvs\moodle2\business\Tarefalv;
use uab\ifce\lvs\avaliacao\NotasLvFactory;
use uab\ifce\lvs\business\Item;
use uab\ifce\lvs\Carinhas;
use uab\ifce\lvs\moodle2\avaliacao\Moodle2NotasLv;

define('TAREFALV_STATUS_SUBMITTED', 'submitted'); // student thinks it is finished
define('TAREFALV_STATUS_CLOSED', 'closed');       // teacher prevents more submissions

/**
 * Extend the base tarefalv class for tarefalvs where you upload a single file
 *
 */
class tarefalv_upload extends tarefalv_base {

    function tarefalv_upload($cmid='staticonly', $tarefalv=NULL, $cm=NULL, $course=NULL) {
        parent::tarefalv_base($cmid, $tarefalv, $cm, $course);
        $this->type = 'upload';
    }

    function view() {
        global $USER, $OUTPUT;

        require_capability('mod/tarefalv:view', $this->context);
        $cansubmit = has_capability('mod/tarefalv:submit', $this->context);

        add_to_log($this->course->id, 'tarefalv', 'view', "view.php?id={$this->cm->id}", $this->tarefalv->id, $this->cm->id);

        $this->view_header();

        if ($this->tarefalv->timeavailable > time()
          and !has_capability('mod/tarefalv:grade', $this->context)      // grading user can see it anytime
          and $this->tarefalv->var3) {                                   // force hiding before available date
            echo $OUTPUT->box_start('generalbox boxaligncenter', 'intro');
            print_string('notavailableyet', 'tarefalv');
            echo $OUTPUT->box_end();
        } else {
            $this->view_intro();
        }

        $this->view_dates();

        if (is_enrolled($this->context, $USER)) {
            if ($submission = $this->get_submission($USER->id)) {
                if ($submission->timemarked) {
                    $this->view_feedback($submission);
                    $this->view_notalv($submission);
                }

                $filecount = $this->count_user_files($submission->id);
            } else {
                $filecount = 0;
            }
            if ($cansubmit or !empty($filecount)) { //if a user has submitted files using a previous role we should still show the files

                if (!$this->drafts_tracked() or !$this->isopen() or $this->is_finalized($submission)) {
                    echo $OUTPUT->heading(get_string('submission', 'tarefalv'), 3);
                } else {
                    echo $OUTPUT->heading(get_string('submissiondraft', 'tarefalv'), 3);
                }

                if ($filecount and $submission) {
                    echo $OUTPUT->box($this->print_user_files($USER->id, true), 'generalbox boxaligncenter', 'userfiles');
                } else {
                    if (!$this->isopen() or $this->is_finalized($submission)) {
                        echo $OUTPUT->box(get_string('nofiles', 'tarefalv'), 'generalbox boxaligncenter nofiles', 'userfiles');
                    } else {
                        echo $OUTPUT->box(get_string('nofilesyet', 'tarefalv'), 'generalbox boxaligncenter nofiles', 'userfiles');
                    }
                }

                $this->view_upload_form();

                if ($this->notes_allowed()) {
                    echo $OUTPUT->heading(get_string('notes', 'tarefalv'), 3);
                    $this->view_notes();
                }

                $this->view_final_submission();
            }
        }
        $this->view_footer();
    }


	function view_notalv($submission) {
		 global $DB, $USER, $OUTPUT;
		 
		echo $OUTPUT->box_start();
		echo $OUTPUT->heading('Nota LV', 3);
		
		$gerenciadorNotas = new Moodle2NotasLv();
		$gerenciadorNotas->setModulo(new Tarefalv($submission->tarefalv));
		$item = new Item('tarefalv', 'submission', $submission);
		$avaliacao = $gerenciadorNotas->getAvaliacao($item);
		$avaliacao->setCarinhasEstendido(true);
		echo $gerenciadorNotas->avaliacaoAtual($item);
		echo $gerenciadorNotas->avaliadoPor($item);
		
		echo $OUTPUT->box_end();
		 		
	}

    /**
     * Display the response file to the student
     *
     * This default method prints the response file
     *
     * @param object $submission The submission object
     */
    function view_responsefile($submission) {
        $fs = get_file_storage();
        $noresponsefiles = $fs->is_area_empty($this->context->id, 'mod_tarefalv', 'response', $submission->id);
        if (!$noresponsefiles) {
            echo '<tr>';
            echo '<td class="left side">&nbsp;</td>';
            echo '<td class="content">';
            echo $this->print_responsefiles($submission->userid);
            echo '</td></tr>';
        }
    }

    function view_upload_form() {
        global $CFG, $USER, $OUTPUT;

        $submission = $this->get_submission($USER->id);

        if ($this->is_finalized($submission)) {
            // no uploading
            return;
        }

        if ($this->can_upload_file($submission)) {
            $fs = get_file_storage();
            // edit files in another page
            if ($submission) {
                if ($files = $fs->get_area_files($this->context->id, 'mod_tarefalv', 'submission', $submission->id, "timemodified", false)) {
                    $str = get_string('editthesefiles', 'tarefalv');
                } else {
                    $str = get_string('uploadfiles', 'tarefalv');
                }
            } else {
                $str = get_string('uploadfiles', 'tarefalv');
            }
            echo $OUTPUT->single_button(new moodle_url('/mod/tarefalv/type/upload/upload.php', array('contextid'=>$this->context->id, 'userid'=>$USER->id)), $str, 'get');
        }
    }

    function view_notes() {
        global $USER, $OUTPUT;

        if ($submission = $this->get_submission($USER->id)
          and !empty($submission->data1)) {
            echo $OUTPUT->box(format_text($submission->data1, FORMAT_HTML, array('overflowdiv'=>true)), 'generalbox boxaligncenter boxwidthwide');
        } else {
            echo $OUTPUT->box(get_string('notesempty', 'tarefalv'), 'generalbox boxaligncenter');
        }
        if ($this->can_update_notes($submission)) {
            $options = array ('id'=>$this->cm->id, 'action'=>'editnotes');
            echo '<div style="text-align:center">';
            echo $OUTPUT->single_button(new moodle_url('upload.php', $options), get_string('edit'));
            echo '</div>';
        }
    }

    function view_final_submission() {
        global $CFG, $USER, $OUTPUT;

        $submission = $this->get_submission($USER->id);

        if ($this->isopen() and $this->can_finalize($submission)) {
            //print final submit button
            echo $OUTPUT->heading(get_string('submitformarking','tarefalv'), 3);
            echo '<div style="text-align:center">';
            echo '<form method="post" action="upload.php">';
            echo '<fieldset class="invisiblefieldset">';
            echo '<input type="hidden" name="id" value="'.$this->cm->id.'" />';
            echo '<input type="hidden" name="sesskey" value="'.sesskey().'" />';
            echo '<input type="hidden" name="action" value="finalize" />';
            echo '<input type="submit" name="formarking" value="'.get_string('sendformarking', 'tarefalv').'" />';
            echo '</fieldset>';
            echo '</form>';
            echo '</div>';
        } else if (!$this->isopen()) {
            if ($this->tarefalv->timeavailable < time()) {
                echo $OUTPUT->heading(get_string('closedtarefalv','tarefalv'), 3);
            } else {
                echo $OUTPUT->heading(get_string('futureatarefalv','tarefalv'), 3);
            }
        } else if ($this->drafts_tracked() and $state = $this->is_finalized($submission)) {
            if ($state == TAREFALV_STATUS_SUBMITTED) {
                echo $OUTPUT->heading(get_string('submitedformarking','tarefalv'), 3);
            } else {
                echo $OUTPUT->heading(get_string('nomoresubmissions','tarefalv'), 3);
            }
        } else {
            //no submission yet
        }
    }


    /**
     * Return true if var3 == hide description till available day
     *
     *@return boolean
     */
    function description_is_hidden() {
        return ($this->tarefalv->var3 && (time() <= $this->tarefalv->timeavailable));
    }

    function print_student_answer($userid, $return=false){
        global $CFG, $OUTPUT, $PAGE;

        $submission = $this->get_submission($userid);

        $output = '';

        if ($this->drafts_tracked() and $this->isopen() and !$this->is_finalized($submission)) {
            $output .= '<strong>'.get_string('draft', 'tarefalv').':</strong> ';
        }

        if ($this->notes_allowed() and !empty($submission->data1)) {
            $link = new moodle_url("/mod/tarefalv/type/upload/notes.php", array('id'=>$this->cm->id, 'userid'=>$userid));
            $action = new popup_action('click', $link, 'notes', array('height' => 500, 'width' => 780));
            $output .= $OUTPUT->action_link($link, get_string('notes', 'tarefalv'), $action, array('title'=>get_string('notes', 'tarefalv')));

            $output .= '&nbsp;';
        }


        $renderer = $PAGE->get_renderer('mod_tarefalv');
        $output = $OUTPUT->box_start('files').$output;
        $output .= $renderer->tarefalv_files($this->context, $submission->id);
        $output .= $OUTPUT->box_end();

        return $output;
    }


    /**
     * Produces a list of links to the files uploaded by a user
     *
     * @param $userid int optional id of the user. If 0 then $USER->id is used.
     * @param $return boolean optional defaults to false. If true the list is returned rather than printed
     * @return string optional
     */
    function print_user_files($userid=0, $return=false) {
        global $CFG, $USER, $OUTPUT, $PAGE;

        $mode    = optional_param('mode', '', PARAM_ALPHA);
        $offset  = optional_param('offset', 0, PARAM_INT);

        if (!$userid) {
            if (!isloggedin()) {
                return '';
            }
            $userid = $USER->id;
        }

        $output = $OUTPUT->box_start('files');

        $submission = $this->get_submission($userid);

        // only during grading
        if ($this->drafts_tracked() and $this->isopen() and !$this->is_finalized($submission) and !empty($mode)) {
            $output .= '<strong>'.get_string('draft', 'tarefalv').':</strong><br />';
        }

        if ($this->notes_allowed() and !empty($submission->data1) and !empty($mode)) { // only during grading

            $npurl = $CFG->wwwroot."/mod/tarefalv/type/upload/notes.php?id={$this->cm->id}&amp;userid=$userid&amp;offset=$offset&amp;mode=single";
            $output .= '<a href="'.$npurl.'">'.get_string('notes', 'tarefalv').'</a><br />';

        }

        if ($this->drafts_tracked() and $this->isopen() and has_capability('mod/tarefalv:grade', $this->context) and $mode != '') { // we do not want it on view.php page
            if ($this->can_unfinalize($submission)) {
                //$options = array ('id'=>$this->cm->id, 'userid'=>$userid, 'action'=>'unfinalize', 'mode'=>$mode, 'offset'=>$offset);
                $output .= '<br /><input type="submit" name="unfinalize" value="'.get_string('unfinalize', 'tarefalv').'" />';
                $output .=  $OUTPUT->help_icon('unfinalize', 'tarefalv');

            } else if ($this->can_finalize($submission)) {
                //$options = array ('id'=>$this->cm->id, 'userid'=>$userid, 'action'=>'finalizeclose', 'mode'=>$mode, 'offset'=>$offset);
                $output .= '<br /><input type="submit" name="finalize" value="'.get_string('finalize', 'tarefalv').'" />';
            }
        }

        if ($submission) {
            $renderer = $PAGE->get_renderer('mod_tarefalv');
            $output .= $renderer->tarefalv_files($this->context, $submission->id);
        }
        $output .= $OUTPUT->box_end();

        if ($return) {
            return $output;
        }
        echo $output;
    }

    function submissions($mode) {
        // redirects out of form to process (un)finalizing.
        $unfinalize = optional_param('unfinalize', FALSE, PARAM_TEXT);
        $finalize = optional_param('finalize', FALSE, PARAM_TEXT);
        if ($unfinalize) {
            $this->unfinalize('single');
        } else if ($finalize) {
            $this->finalize('single');
        }
        if ($unfinalize || $finalize) {
            $mode = 'singlenosave';
        }
        parent::submissions($mode);
    }

    function process_feedback($formdata=null) {
        if (!$feedback = data_submitted() or !confirm_sesskey()) {      // No incoming data?
            return false;
        }
        $userid = required_param('userid', PARAM_INT);
        $offset = required_param('offset', PARAM_INT);
        $mform = $this->display_submission($offset, $userid, false);
        parent::process_feedback($mform);
    }

    /**
     * Counts all complete (real) tarefalv submissions by enrolled students. This overrides tarefalv_base::count_real_submissions().
     * This is necessary for tracked advanced file uploads where we need to check that the data2 field is equal to TAREFALV_STATUS_SUBMITTED
     * to determine if a submission is complete.
     *
     * @param  int $groupid (optional) If nonzero then count is restricted to this group
     * @return int          The number of submissions
     */
    function count_real_submissions($groupid=0) {
        global $DB;

        // Grab the context assocated with our course module
        $context = context_module::instance($this->cm->id);

        // Get ids of users enrolled in the given course.
        list($enroledsql, $params) = get_enrolled_sql($context, 'mod/tarefalv:view', $groupid);
        $params['tarefalvid'] = $this->cm->instance;

        $query = '';
        if ($this->drafts_tracked() and $this->isopen()) {
            $query = ' AND ' . $DB->sql_compare_text('s.data2') . " = '"  . TAREFALV_STATUS_SUBMITTED . "'";
        } else {
            // Count on submissions with files actually uploaded
            $query = " AND s.numfiles > 0";
        }
        return $DB->count_records_sql("SELECT COUNT('x')
                                         FROM {tarefalv_submissions} s
                                    LEFT JOIN {tarefalv} a ON a.id = s.tarefalv
                                   INNER JOIN ($enroledsql) u ON u.id = s.userid
                                        WHERE s.tarefalv = :tarefalvid" .
                                              $query, $params);
    }

    function print_responsefiles($userid, $return=false) {
        global $OUTPUT, $PAGE;

        $output = $OUTPUT->box_start('responsefiles');

        if ($submission = $this->get_submission($userid)) {
            $renderer = $PAGE->get_renderer('mod_tarefalv');
            $output .= $renderer->tarefalv_files($this->context, $submission->id, 'response');
        }
        $output .= $OUTPUT->box_end();

        if ($return) {
            return $output;
        }
        echo $output;
    }

    /**
     * Upload files
     * upload_file function requires moodle form instance and file manager options
     * @param object $mform
     * @param array $options
     */
    function upload($mform = null, $filemanager_options = null) {
        $action = required_param('action', PARAM_ALPHA);
        switch ($action) {
            case 'finalize':
                $this->finalize();
                break;
            case 'finalizeclose':
                $this->finalizeclose();
                break;
            case 'unfinalize':
                $this->unfinalize();
                break;
            case 'uploadresponse':
                $this->upload_responsefile($mform, $filemanager_options);
                break;
            case 'uploadfile':
                $this->upload_file($mform, $filemanager_options);
            case 'savenotes':
            case 'editnotes':
                $this->upload_notes();
            default:
                print_error('unknowuploadaction', '', '', $action);
        }
    }

    function upload_notes() {
        global $CFG, $USER, $OUTPUT, $DB;

        $action = required_param('action', PARAM_ALPHA);

        $returnurl  = new moodle_url('/mod/tarefalv/view.php', array('id'=>$this->cm->id));

        $mform = new mod_tarefalv_upload_notes_form();

        $defaults = new stdClass();
        $defaults->id = $this->cm->id;

        if ($submission = $this->get_submission($USER->id)) {
            $defaults->text = clean_text($submission->data1);
        } else {
            $defaults->text = '';
        }

        $mform->set_data($defaults);

        if ($mform->is_cancelled()) {
            $returnurl  = new moodle_url('/mod/tarefalv/view.php', array('id'=>$this->cm->id));
            redirect($returnurl);
        }

        if (!$this->can_update_notes($submission)) {
            $this->view_header(get_string('upload'));
            echo $OUTPUT->notification(get_string('uploaderror', 'tarefalv'));
            echo $OUTPUT->continue_button($returnurl);
            $this->view_footer();
            die;
        }

        if ($data = $mform->get_data() and $action == 'savenotes') {
            $submission = $this->get_submission($USER->id, true); // get or create submission
            $updated = new stdClass();
            $updated->id           = $submission->id;
            $updated->timemodified = time();
            $updated->data1        = $data->text;

            $DB->update_record('tarefalv_submissions', $updated);
            add_to_log($this->course->id, 'tarefalv', 'upload', 'view.php?a='.$this->tarefalv->id, $this->tarefalv->id, $this->cm->id);
            redirect($returnurl);
            $submission = $this->get_submission($USER->id);
            $this->update_grade($submission);
        }

        /// show notes edit form
        $this->view_header(get_string('notes', 'tarefalv'));

        echo $OUTPUT->heading(get_string('notes', 'tarefalv'));

        $mform->display();

        $this->view_footer();
        die;
    }

    function upload_responsefile($mform, $options) {
        global $CFG, $USER, $OUTPUT, $PAGE;

        $userid = required_param('userid', PARAM_INT);
        $mode   = required_param('mode', PARAM_ALPHA);
        $offset = required_param('offset', PARAM_INT);

        $returnurl = new moodle_url("submissions.php", array('id'=>$this->cm->id,'userid'=>$userid,'mode'=>$mode,'offset'=>$offset)); //not xhtml, just url.

        if ($formdata = $mform->get_data() and $this->can_manage_responsefiles()) {
            $fs = get_file_storage();
            $submission = $this->get_submission($userid, true, true);
            if ($formdata = file_postupdate_standard_filemanager($formdata, 'files', $options, $this->context, 'mod_tarefalv', 'response', $submission->id)) {
                $returnurl = new moodle_url("/mod/tarefalv/submissions.php", array('id'=>$this->cm->id,'userid'=>$formdata->userid,'mode'=>$formdata->mode,'offset'=>$formdata->offset));
                redirect($returnurl->out(false));
            }
        }
        $PAGE->set_title(get_string('upload'));
        echo $OUTPUT->header();
        echo $OUTPUT->notification(get_string('uploaderror', 'tarefalv'));
        echo $OUTPUT->continue_button($returnurl->out(true));
        echo $OUTPUT->footer();
        die;
    }

    function upload_file($mform, $options) {
        global $CFG, $USER, $DB, $OUTPUT;

        $returnurl  = new moodle_url('/mod/tarefalv/view.php', array('id'=>$this->cm->id));
        $submission = $this->get_submission($USER->id);

        if (!$this->can_upload_file($submission)) {
            $this->view_header(get_string('upload'));
            echo $OUTPUT->notification(get_string('uploaderror', 'tarefalv'));
            echo $OUTPUT->continue_button($returnurl);
            $this->view_footer();
            die;
        }

        if ($formdata = $mform->get_data()) {
            $fs = get_file_storage();
            $submission = $this->get_submission($USER->id, true); //create new submission if needed
            $fs->delete_area_files($this->context->id, 'mod_tarefalv', 'submission', $submission->id);
            $formdata = file_postupdate_standard_filemanager($formdata, 'files', $options, $this->context, 'mod_tarefalv', 'submission', $submission->id);
            $updates = new stdClass();
            $updates->id = $submission->id;
            $updates->numfiles = count($fs->get_area_files($this->context->id, 'mod_tarefalv', 'submission', $submission->id, 'sortorder', false));
            $updates->timemodified = time();
            $DB->update_record('tarefalv_submissions', $updates);
            add_to_log($this->course->id, 'tarefalv', 'upload',
                    'view.php?a='.$this->tarefalv->id, $this->tarefalv->id, $this->cm->id);
            $this->update_grade($submission);
            if (!$this->drafts_tracked()) {
                $this->email_teachers($submission);
            }

            // send files to event system
            $files = $fs->get_area_files($this->context->id, 'mod_tarefalv', 'submission', $submission->id);
            // Let Moodle know that assessable files were  uploaded (eg for plagiarism detection)
            $eventdata = new stdClass();
            $eventdata->modulename   = 'tarefalv';
            $eventdata->cmid         = $this->cm->id;
            $eventdata->itemid       = $submission->id;
            $eventdata->courseid     = $this->course->id;
            $eventdata->userid       = $USER->id;
            if ($files) {
                $eventdata->files        = $files; // This is depreceated - please use pathnamehashes instead!
            }
            $eventdata->pathnamehashes = array_keys($files);
            events_trigger('assessable_file_uploaded', $eventdata);
            $returnurl  = new moodle_url('/mod/tarefalv/view.php', array('id'=>$this->cm->id));
            redirect($returnurl);
        }

        $this->view_header(get_string('upload'));
        echo $OUTPUT->notification(get_string('uploaderror', 'tarefalv'));
        echo $OUTPUT->continue_button($returnurl);
        $this->view_footer();
        die;
    }

    function send_file($filearea, $args, $forcedownload, array $options=array()) {
        global $CFG, $DB, $USER;
        require_once($CFG->libdir.'/filelib.php');

        require_login($this->course, false, $this->cm);

        if ($filearea === 'submission') {
            $submissionid = (int)array_shift($args);

            if (!$submission = $DB->get_record('tarefalv_submissions', array('tarefalv'=>$this->tarefalv->id, 'id'=>$submissionid))) {
                return false;
            }

            if ($USER->id != $submission->userid and !has_capability('mod/tarefalv:grade', $this->context)) {
                return false;
            }

            $relativepath = implode('/', $args);
            $fullpath = "/{$this->context->id}/mod_tarefalv/submission/$submission->id/$relativepath";

            $fs = get_file_storage();
            if (!$file = $fs->get_file_by_hash(sha1($fullpath)) or $file->is_directory()) {
                return false;
            }

            send_stored_file($file, 0, 0, true, $options); // download MUST be forced - security!

        } else if ($filearea === 'response') {
            $submissionid = (int)array_shift($args);

            if (!$submission = $DB->get_record('tarefalv_submissions', array('tarefalv'=>$this->tarefalv->id, 'id'=>$submissionid))) {
                return false;
            }

            if ($USER->id != $submission->userid and !has_capability('mod/tarefalv:grade', $this->context)) {
                return false;
            }

            $relativepath = implode('/', $args);
            $fullpath = "/{$this->context->id}/mod_tarefalv/response/$submission->id/$relativepath";

            $fs = get_file_storage();
            if (!$file = $fs->get_file_by_hash(sha1($fullpath)) or $file->is_directory()) {
                return false;
            }
            send_stored_file($file, 0, 0, true, $options);
        }

        return false;
    }

    function finalize($forcemode=null) {
        global $USER, $DB, $OUTPUT;
        $userid = optional_param('userid', $USER->id, PARAM_INT);
        $offset = optional_param('offset', 0, PARAM_INT);
        $confirm    = optional_param('confirm', 0, PARAM_BOOL);
        $returnurl  = new moodle_url('/mod/tarefalv/view.php', array('id'=>$this->cm->id));
        $submission = $this->get_submission($userid);

        if ($forcemode!=null) {
            $returnurl  = new moodle_url('/mod/tarefalv/submissions.php',
                array('id'=>$this->cm->id,
                    'userid'=>$userid,
                    'mode'=>$forcemode,
                    'offset'=>$offset
                ));
        }

        if (!$this->can_finalize($submission)) {
            redirect($returnurl->out(false)); // probably already graded, redirect to tarefalv page, the reason should be obvious
        }

        if ($forcemode==null) {
            if (!data_submitted() or !$confirm or !confirm_sesskey()) {
                $optionsno = array('id'=>$this->cm->id);
                $optionsyes = array ('id'=>$this->cm->id, 'confirm'=>1, 'action'=>'finalize', 'sesskey'=>sesskey());
                $this->view_header(get_string('submitformarking', 'tarefalv'));
                echo $OUTPUT->heading(get_string('submitformarking', 'tarefalv'));
                echo $OUTPUT->confirm(get_string('oncetarefalvsent', 'tarefalv'), new moodle_url('upload.php', $optionsyes),new moodle_url( 'view.php', $optionsno));
                $this->view_footer();
                die;
            }
        }
        $updated = new stdClass();
        $updated->id           = $submission->id;
        $updated->data2        = TAREFALV_STATUS_SUBMITTED;
        $updated->timemodified = time();

        $DB->update_record('tarefalv_submissions', $updated);
        add_to_log($this->course->id, 'tarefalv', 'upload', //TODO: add finalize action to log
                'view.php?a='.$this->tarefalv->id, $this->tarefalv->id, $this->cm->id);
        $submission = $this->get_submission($userid);
        $this->update_grade($submission);
        $this->email_teachers($submission);

        // Trigger assessable_files_done event to show files are complete
        $eventdata = new stdClass();
        $eventdata->modulename   = 'tarefalv';
        $eventdata->cmid         = $this->cm->id;
        $eventdata->itemid       = $submission->id;
        $eventdata->courseid     = $this->course->id;
        $eventdata->userid       = $userid;
        events_trigger('assessable_files_done', $eventdata);

        if ($forcemode==null) {
            redirect($returnurl->out(false));
        }
    }

    function finalizeclose() {
        global $DB;

        $userid    = optional_param('userid', 0, PARAM_INT);
        $mode      = required_param('mode', PARAM_ALPHA);
        $offset    = required_param('offset', PARAM_INT);
        $returnurl  = new moodle_url('/mod/tarefalv/submissions.php', array('id'=>$this->cm->id, 'userid'=>$userid, 'mode'=>$mode, 'offset'=>$offset, 'forcerefresh'=>1));

        // create but do not add student submission date
        $submission = $this->get_submission($userid, true, true);

        if (!data_submitted() or !$this->can_finalize($submission) or !confirm_sesskey()) {
            redirect($returnurl); // probably closed already
        }

        $updated = new stdClass();
        $updated->id    = $submission->id;
        $updated->data2 = TAREFALV_STATUS_CLOSED;

        $DB->update_record('tarefalv_submissions', $updated);
        add_to_log($this->course->id, 'tarefalv', 'upload', //TODO: add finalize action to log
                'view.php?a='.$this->tarefalv->id, $this->tarefalv->id, $this->cm->id);
        $submission = $this->get_submission($userid, false, true);
        $this->update_grade($submission);
        redirect($returnurl);
    }

    function unfinalize($forcemode=null) {
        global $DB;

        $userid = required_param('userid', PARAM_INT);
        $mode   = required_param('mode', PARAM_ALPHA);
        $offset = required_param('offset', PARAM_INT);

        if ($forcemode!=null) {
            $mode=$forcemode;
        }
        $returnurl = new moodle_url('/mod/tarefalv/submissions.php', array('id'=>$this->cm->id, 'userid'=>$userid, 'mode'=>$mode, 'offset'=>$offset, 'forcerefresh'=>1) );
        if (data_submitted()
          and $submission = $this->get_submission($userid)
          and $this->can_unfinalize($submission)
          and confirm_sesskey()) {

            $updated = new stdClass();
            $updated->id = $submission->id;
            $updated->data2 = '';
            $DB->update_record('tarefalv_submissions', $updated);
            //TODO: add unfinalize action to log
            add_to_log($this->course->id, 'tarefalv', 'view submission', 'submissions.php?id='.$this->cm->id.'&userid='.$userid.'&mode='.$mode.'&offset='.$offset, $this->tarefalv->id, $this->cm->id);
            $submission = $this->get_submission($userid);
            $this->update_grade($submission);
        }

        if ($forcemode==null) {
            redirect($returnurl);
        }
    }


    function delete() {
        $action   = optional_param('action', '', PARAM_ALPHA);

        switch ($action) {
            case 'response':
                $this->delete_responsefile();
                break;
            default:
                $this->delete_file();
        }
        die;
    }


    function delete_responsefile() {
        global $CFG, $OUTPUT,$PAGE;

        $file     = required_param('file', PARAM_FILE);
        $userid   = required_param('userid', PARAM_INT);
        $mode     = required_param('mode', PARAM_ALPHA);
        $offset   = required_param('offset', PARAM_INT);
        $confirm  = optional_param('confirm', 0, PARAM_BOOL);

        $returnurl  = new moodle_url('/mod/tarefalv/submissions.php', array('id'=>$this->cm->id, 'userid'=>$userid, 'mode'=>$mode, 'offset'=>$offset));

        if (!$this->can_manage_responsefiles()) {
           redirect($returnurl);
        }

        $urlreturn = 'submissions.php';
        $optionsreturn = array('id'=>$this->cm->id, 'offset'=>$offset, 'mode'=>$mode, 'userid'=>$userid);

        if (!data_submitted() or !$confirm or !confirm_sesskey()) {
            $optionsyes = array ('id'=>$this->cm->id, 'file'=>$file, 'userid'=>$userid, 'confirm'=>1, 'action'=>'response', 'mode'=>$mode, 'offset'=>$offset, 'sesskey'=>sesskey());
            $PAGE->set_title(get_string('delete'));
            echo $OUTPUT->header();
            echo $OUTPUT->heading(get_string('delete'));
            echo $OUTPUT->confirm(get_string('confirmdeletefile', 'tarefalv', $file), new moodle_url('delete.php', $optionsyes), new moodle_url($urlreturn, $optionsreturn));
            echo $OUTPUT->footer();
            die;
        }

        if ($submission = $this->get_submission($userid)) {
            $fs = get_file_storage();
            if ($file = $fs->get_file($this->context->id, 'mod_tarefalv', 'response', $submission->id, '/', $file)) {
                $file->delete();
            }
        }
        redirect($returnurl);
    }


    function delete_file() {
        global $CFG, $DB, $OUTPUT, $PAGE;

        $file     = required_param('file', PARAM_FILE);
        $userid   = required_param('userid', PARAM_INT);
        $confirm  = optional_param('confirm', 0, PARAM_BOOL);
        $mode     = optional_param('mode', '', PARAM_ALPHA);
        $offset   = optional_param('offset', 0, PARAM_INT);

        require_login($this->course, false, $this->cm);

        if (empty($mode)) {
            $urlreturn = 'view.php';
            $optionsreturn = array('id'=>$this->cm->id);
            $returnurl  = new moodle_url('/mod/tarefalv/view.php', array('id'=>$this->cm->id));
        } else {
            $urlreturn = 'submissions.php';
            $optionsreturn = array('id'=>$this->cm->id, 'offset'=>$offset, 'mode'=>$mode, 'userid'=>$userid);
            $returnurl  = new moodle_url('/mod/tarefalv/submissions.php', array('id'=>$this->cm->id, 'offset'=>$offset, 'userid'=>$userid));
        }

        if (!$submission = $this->get_submission($userid) // incorrect submission
          or !$this->can_delete_files($submission)) {     // can not delete
            $this->view_header(get_string('delete'));
            echo $OUTPUT->notification(get_string('cannotdeletefiles', 'tarefalv'));
            echo $OUTPUT->continue_button($returnurl);
            $this->view_footer();
            die;
        }

        if (!data_submitted() or !$confirm or !confirm_sesskey()) {
            $optionsyes = array ('id'=>$this->cm->id, 'file'=>$file, 'userid'=>$userid, 'confirm'=>1, 'sesskey'=>sesskey(), 'mode'=>$mode, 'offset'=>$offset, 'sesskey'=>sesskey());
            if (empty($mode)) {
                $this->view_header(get_string('delete'));
            } else {
                $PAGE->set_title(get_string('delete'));
                echo $OUTPUT->header();
            }
            echo $OUTPUT->heading(get_string('delete'));
            echo $OUTPUT->confirm(get_string('confirmdeletefile', 'tarefalv', $file), new moodle_url('delete.php', $optionsyes), new moodle_url($urlreturn, $optionsreturn));
            if (empty($mode)) {
                $this->view_footer();
            } else {
                echo $OUTPUT->footer();
            }
            die;
        }

        $fs = get_file_storage();
        if ($file = $fs->get_file($this->context->id, 'mod_tarefalv', 'submission', $submission->id, '/', $file)) {
            $file->delete();
            $submission->timemodified = time();
            $DB->update_record('tarefalv_submissions', $submission);
            add_to_log($this->course->id, 'tarefalv', 'upload', //TODO: add delete action to log
                    'view.php?a='.$this->tarefalv->id, $this->tarefalv->id, $this->cm->id);
            $this->update_grade($submission);
        }
        redirect($returnurl);
    }


    function can_upload_file($submission) {
        global $USER;

        if (is_enrolled($this->context, $USER, 'mod/tarefalv:submit')
          and $this->isopen()                                                 // tarefalv not closed yet
          and (empty($submission) or ($submission->userid == $USER->id))        // his/her own submission
          and !$this->is_finalized($submission)) {                            // no uploading after final submission
            return true;
        } else {
            return false;
        }
    }

    function can_manage_responsefiles() {
        if (has_capability('mod/tarefalv:grade', $this->context)) {
            return true;
        } else {
            return false;
        }
    }

    function can_delete_files($submission) {
        global $USER;

        if (has_capability('mod/tarefalv:grade', $this->context)) {
            return true;
        }

        if (is_enrolled($this->context, $USER, 'mod/tarefalv:submit')
          and $this->isopen()                                      // tarefalv not closed yet
          and $this->tarefalv->resubmit                          // deleting allowed
          and $USER->id == $submission->userid                     // his/her own submission
          and !$this->is_finalized($submission)) {                 // no deleting after final submission
            return true;
        } else {
            return false;
        }
    }

    function drafts_tracked() {
        return !empty($this->tarefalv->var4);
    }

    /**
     * Returns submission status
     * @param object $submission - may be empty
     * @return string submission state - empty, TAREFALV_STATUS_SUBMITTED or TAREFALV_STATUS_CLOSED
     */
    function is_finalized($submission) {
        if (!$this->drafts_tracked()) {
            return '';

        } else if (empty($submission)) {
            return '';

        } else if ($submission->data2 == TAREFALV_STATUS_SUBMITTED or $submission->data2 == TAREFALV_STATUS_CLOSED) {
            return $submission->data2;

        } else {
            return '';
        }
    }

    function can_unfinalize($submission) {
        if(is_bool($submission)) {
            return false;
        }

        if (!$this->drafts_tracked()) {
            return false;
        }

        if (has_capability('mod/tarefalv:grade', $this->context)
          and $this->isopen()
          and $this->is_finalized($submission)) {
            return true;
        } else {
            return false;
        }
    }

    function can_finalize($submission) {
        global $USER;

        if(is_bool($submission)) {
            return false;
        }

        if (!$this->drafts_tracked()) {
            return false;
        }

        if ($this->is_finalized($submission)) {
            return false;
        }

        if (has_capability('mod/tarefalv:grade', $this->context)) {
            return true;

        } else if (is_enrolled($this->context, $USER, 'mod/tarefalv:submit')
          and $this->isopen()                                                 // tarefalv not closed yet
          and !empty($submission)                                             // submission must exist
          and $submission->userid == $USER->id                                // his/her own submission
          and ($this->count_user_files($submission->id)
            or ($this->notes_allowed() and !empty($submission->data1)))) {    // something must be submitted

            return true;
        } else {
            return false;
        }
    }

    function can_update_notes($submission) {
        global $USER;

        if (is_enrolled($this->context, $USER, 'mod/tarefalv:submit')
          and $this->notes_allowed()                                          // notesd must be allowed
          and $this->isopen()                                                 // tarefalv not closed yet
          and (empty($submission) or $USER->id == $submission->userid)        // his/her own submission
          and !$this->is_finalized($submission)) {                            // no updateingafter final submission
            return true;
        } else {
            return false;
        }
    }

    function notes_allowed() {
        return (boolean)$this->tarefalv->var2;
    }

    function count_responsefiles($userid) {
        if ($submission = $this->get_submission($userid)) {
            $fs = get_file_storage();
            $files = $fs->get_area_files($this->context->id, 'mod_tarefalv', 'response', $submission->id, "id", false);
            return count($files);
        } else {
            return 0;
        }
    }

    function setup_elements(&$mform) {
        global $CFG, $COURSE;

        $ynoptions = array( 0 => get_string('no'), 1 => get_string('yes'));

        $choices = get_max_upload_sizes($CFG->maxbytes, $COURSE->maxbytes);
        $mform->addElement('select', 'maxbytes', get_string('maximumsize', 'tarefalv'), $choices);
        $mform->setDefault('maxbytes', $CFG->tarefalv_maxbytes);

        $mform->addElement('select', 'resubmit', get_string('allowdeleting', 'tarefalv'), $ynoptions);
        $mform->addHelpButton('resubmit', 'allowdeleting', 'tarefalv');
        $mform->setDefault('resubmit', 1);

        $options = array();
        for($i = 1; $i <= 20; $i++) {
            $options[$i] = $i;
        }
        $mform->addElement('select', 'var1', get_string('allowmaxfiles', 'tarefalv'), $options);
        $mform->addHelpButton('var1', 'allowmaxfiles', 'tarefalv');
        $mform->setDefault('var1', 3);

        $mform->addElement('select', 'var2', get_string('allownotes', 'tarefalv'), $ynoptions);
        $mform->addHelpButton('var2', 'allownotes', 'tarefalv');
        $mform->setDefault('var2', 0);

        $mform->addElement('select', 'var3', get_string('hideintro', 'tarefalv'), $ynoptions);
        $mform->addHelpButton('var3', 'hideintro', 'tarefalv');
        $mform->setDefault('var3', 0);

        $mform->addElement('select', 'emailteachers', get_string('emailteachers', 'tarefalv'), $ynoptions);
        $mform->addHelpButton('emailteachers', 'emailteachers', 'tarefalv');
        $mform->setDefault('emailteachers', 0);

        $mform->addElement('select', 'var4', get_string('trackdrafts', 'tarefalv'), $ynoptions);
        $mform->addHelpButton('var4', 'trackdrafts', 'tarefalv');
        $mform->setDefault('var4', 1);

        $course_context = context_course::instance($COURSE->id);
        plagiarism_get_form_elements_module($mform, $course_context, 'mod_tarefalv');
    }

    function portfolio_exportable() {
        return true;
    }

    function extend_settings_navigation($node) {
        global $CFG, $USER, $OUTPUT;

        // get users submission if there is one
        $submission = $this->get_submission();
        if (is_enrolled($this->context, $USER, 'mod/tarefalv:submit')) {
            $editable = $this->isopen() && (!$submission || $this->tarefalv->resubmit || !$submission->timemarked);
        } else {
            $editable = false;
        }

        // If the user has submitted something add some related links and data
        if (isset($submission->data2) AND $submission->data2 == 'submitted') {
            // Add a view link to the settings nav
            $link = new moodle_url('/mod/tarefalv/view.php', array('id'=>$this->cm->id));
            $node->add(get_string('viewmysubmission', 'tarefalv'), $link, navigation_node::TYPE_SETTING);
            if (!empty($submission->timemodified)) {
                $submittednode = $node->add(get_string('submitted', 'tarefalv') . ' ' . userdate($submission->timemodified));
                $submittednode->text = preg_replace('#([^,])\s#', '$1&nbsp;', $submittednode->text);
                $submittednode->add_class('note');
                if ($submission->timemodified <= $this->tarefalv->timedue || empty($this->tarefalv->timedue)) {
                    $submittednode->add_class('early');
                } else {
                    $submittednode->add_class('late');
                }
            }
        }

        // Check if the user has uploaded any files, if so we can add some more stuff to the settings nav
        if ($submission && is_enrolled($this->context, $USER, 'mod/tarefalv:submit') && $this->count_user_files($submission->id)) {
            $fs = get_file_storage();
            if ($files = $fs->get_area_files($this->context->id, 'mod_tarefalv', 'submission', $submission->id, "timemodified", false)) {
                if (!$this->drafts_tracked() or !$this->isopen() or $this->is_finalized($submission)) {
                    $filenode = $node->add(get_string('submission', 'tarefalv'));
                } else {
                    $filenode = $node->add(get_string('submissiondraft', 'tarefalv'));
                }
                foreach ($files as $file) {
                    $filename = $file->get_filename();
                    $mimetype = $file->get_mimetype();
                    $link = file_encode_url($CFG->wwwroot.'/pluginfile.php', '/'.$this->context->id.'/mod_tarefalv/submission/'.$submission->id.'/'.$filename);
                    $filenode->add($filename, $link, navigation_node::TYPE_SETTING, null, null, new pix_icon(file_file_icon($file),''));
                }
            }
        }

        // Show a notes link if they are enabled
        if ($this->notes_allowed()) {
            $link = new moodle_url('/mod/tarefalv/upload.php', array('id'=>$this->cm->id, 'action'=>'editnotes', 'sesskey'=>sesskey()));
            $node->add(get_string('notes', 'tarefalv'), $link);
        }
    }

    /**
     * creates a zip of all tarefalv submissions and sends a zip to the browser
     */
    public function download_submissions() {
        global $CFG,$DB;
        require_once($CFG->libdir.'/filelib.php');
        $submissions = $this->get_submissions('','');
        if (empty($submissions)) {
            print_error('errornosubmissions', 'tarefalv', new moodle_url('/mod/tarefalv/submissions.php', array('id'=>$this->cm->id)));
        }
        $filesforzipping = array();
        $fs = get_file_storage();

        $groupmode = groups_get_activity_groupmode($this->cm);
        $groupid = 0;   // All users
        $groupname = '';
        if ($groupmode) {
            $groupid = groups_get_activity_group($this->cm, true);
            $groupname = groups_get_group_name($groupid).'-';
        }
        $filename = str_replace(' ', '_', clean_filename($this->course->shortname.'-'.$this->tarefalv->name.'-'.$groupname.$this->tarefalv->id.".zip")); //name of new zip file.
        foreach ($submissions as $submission) {
            // If tarefalv is open and submission is not finalized and marking button enabled then don't add it to zip.
            $submissionstatus = $this->is_finalized($submission);
            if ($this->isopen() && empty($submissionstatus) && !empty($this->tarefalv->var4)) {
                continue;
            }
            $a_userid = $submission->userid; //get userid
            if ((groups_is_member($groupid,$a_userid)or !$groupmode or !$groupid)) {
                $a_assignid = $submission->tarefalv; //get name of this tarefalv for use in the file names.
                $a_user = $DB->get_record("user", array("id"=>$a_userid),'id,username,firstname,lastname'); //get user firstname/lastname

                $files = $fs->get_area_files($this->context->id, 'mod_tarefalv', 'submission', $submission->id, "timemodified", false);
                foreach ($files as $file) {
                    //get files new name.
                    $fileext = strstr($file->get_filename(), '.');
                    $fileoriginal = str_replace($fileext, '', $file->get_filename());
                    $fileforzipname =  clean_filename(fullname($a_user) . "_" . $fileoriginal."_".$a_userid.$fileext);
                    //save file name to array for zipping.
                    $filesforzipping[$fileforzipname] = $file;
                }
            }
        } // end of foreach loop

        // Throw error if no files are added.
        if (empty($filesforzipping)) {
            print_error('errornosubmissions', 'tarefalv', new moodle_url('/mod/tarefalv/submissions.php', array('id'=>$this->cm->id)));
        }

        if ($zipfile = tarefalv_pack_files($filesforzipping)) {
            send_temp_file($zipfile, $filename); //send file and delete after sending.
        }
    }

    /**
     * Check the given submission is complete. Preliminary rows are often created in the tarefalv_submissions
     * table before a submission actually takes place. This function checks to see if the given submission has actually
     * been submitted.
     *
     * @param  stdClass $submission The submission we want to check for completion
     * @return bool                 Indicates if the submission was found to be complete
     */
    public function is_submitted_with_required_data($submission) {
        if ($this->drafts_tracked()) {
            $submitted = $submission->timemodified > 0 &&
                         $submission->data2 == TAREFALV_STATUS_SUBMITTED;
        } else {
            $submitted = $submission->numfiles > 0;
        }
        return $submitted;
    }
}

class mod_tarefalv_upload_notes_form extends moodleform {

    function get_data() {
        $data = parent::get_data();
        if ($data) {
            $data->format = $data->text['format'];
            $data->text = $data->text['text'];
        }
        return $data;
    }

    function set_data($data) {
        if (!isset($data->format)) {
            $data->format = FORMAT_HTML;
        }
        if (isset($data->text)) {
            $data->text = array('text'=>$data->text, 'format'=>$data->format);
        }
        parent::set_data($data);
    }

    function definition() {
        $mform = $this->_form;

        // visible elements
        $mform->addElement('editor', 'text', get_string('notes', 'tarefalv'), null, null);
        $mform->setType('text', PARAM_RAW); // to be cleaned before display

        // hidden params
        $mform->addElement('hidden', 'id', 0);
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'action', 'savenotes');
        $mform->setType('action', PARAM_ALPHA);

        // buttons
        $this->add_action_buttons();
    }
}

class mod_tarefalv_upload_response_form extends moodleform {
    function definition() {
        $mform = $this->_form;
        $instance = $this->_customdata;

        // visible elements
        $mform->addElement('filemanager', 'files_filemanager', get_string('uploadafile'), null, $instance->options);

        // hidden params
        $mform->addElement('hidden', 'id', $instance->cm->id);
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'contextid', $instance->contextid);
        $mform->setType('contextid', PARAM_INT);
        $mform->addElement('hidden', 'action', 'uploadresponse');
        $mform->setType('action', PARAM_ALPHA);
        $mform->addElement('hidden', 'mode', $instance->mode);
        $mform->setType('mode', PARAM_ALPHA);
        $mform->addElement('hidden', 'offset', $instance->offset);
        $mform->setType('offset', PARAM_INT);
        $mform->addElement('hidden', 'forcerefresh' , $instance->forcerefresh);
        $mform->setType('forcerefresh', PARAM_INT);
        $mform->addElement('hidden', 'userid', $instance->userid);
        $mform->setType('userid', PARAM_INT);

        // buttons
        $this->add_action_buttons(false, get_string('uploadthisfile'));
    }
}




