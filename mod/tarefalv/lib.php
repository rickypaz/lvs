<?PHP

use uab\ifce\lvs\moodle2\business\Moodle2CursoLv;

use uab\ifce\lvs\moodle2\business\CursoLv;

use uab\ifce\lvs\moodle2\avaliacao\Moodle2NotasLv;

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
 * tarefalv_base is the base class for tarefalv types
 *
 * This class provides all the functionality for an tarefalv
 *
 * @package   mod-tarefalv
 * @copyright 1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/** Include eventslib.php */
require_once($CFG->libdir.'/eventslib.php');
/** Include formslib.php */
require_once($CFG->libdir.'/formslib.php');
/** Include calendar/lib.php */
require_once($CFG->dirroot.'/calendar/lib.php');

/* @lvs dependências dos lvs  */
use uab\ifce\lvs\Carinhas;
use uab\ifce\lvs\EscalaLikert;
use uab\ifce\lvs\avaliacao\AvaliacaoLv;
use uab\ifce\lvs\avaliacao\NotasLvFactory;
use uab\ifce\lvs\moodle2\business\Tarefalv;
use uab\ifce\lvs\business\Item;

require_once($CFG->dirroot.'/blocks/lvs/biblioteca/lib.php'); // @lvs inclusão do loader dos lvs
/* --- fim ---- */

/** TAREFALV_COUNT_WORDS = 1 */
define('TAREFALV_COUNT_WORDS', 1);
/** TAREFALV_COUNT_LETTERS = 2 */
define('TAREFALV_COUNT_LETTERS', 2);

/**
 * Standard base class for all tarefalv submodules (tarefalv types).
 *
 * @package   mod-tarefalv
 * @copyright 1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
*/
class tarefalv_base {

	const FILTER_ALL             = 0;
	const FILTER_SUBMITTED       = 1;
	const FILTER_REQUIRE_GRADING = 2;

	/** @var object */
	var $cm;
	/** @var object */
	var $course;
	/** @var stdClass */
	var $coursecontext;
	/** @var object */
	var $tarefalv;
	/** @var string */
	var $strtarefalv;
	/** @var string */
	var $strtarefalvs;
	/** @var string */
	var $strsubmissions;
	/** @var string */
	var $strlastmodified;
	/** @var string */
	var $pagetitle;
	/** @var bool */
	var $usehtmleditor;
	/**
	 * @todo document this var
	 */
	var $defaultformat;
	/**
	 * @todo document this var
	 */
	var $context;
	/** @var string */
	var $type;

	/**
	 * Constructor for the base tarefalv class
	 *
	 * Constructor for the base tarefalv class.
	 * If cmid is set create the cm, course, tarefalv objects.
	 * If the tarefalv is hidden and the user is not a teacher then
	 * this prints a page header and notice.
	 *
	 * @global object
	 * @global object
	 * @param int $cmid the current course module id - not set for new tarefalvs
	 * @param object $tarefalv usually null, but if we have it we pass it to save db access
	 * @param object $cm usually null, but if we have it we pass it to save db access
	 * @param object $course usually null, but if we have it we pass it to save db access
	 */
	function tarefalv_base($cmid='staticonly', $tarefalv=NULL, $cm=NULL, $course=NULL) {
		global $COURSE, $DB;

		if ($cmid == 'staticonly') {
			//use static functions only!
			return;
		}

		global $CFG;

		if ($cm) {
			$this->cm = $cm;
		} else if (! $this->cm = get_coursemodule_from_id('tarefalv', $cmid)) {
			print_error('invalidcoursemodule');
		}

		$this->context = context_module::instance($this->cm->id);

		if ($course) {
			$this->course = $course;
		} else if ($this->cm->course == $COURSE->id) {
			$this->course = $COURSE;
		} else if (! $this->course = $DB->get_record('course', array('id'=>$this->cm->course))) {
			print_error('invalidid', 'tarefalv');
		}
		$this->coursecontext = context_course::instance($this->course->id);
		$courseshortname = format_text($this->course->shortname, true, array('context' => $this->coursecontext));

		if ($tarefalv) {
			$this->tarefalv = $tarefalv;
		} else if (! $this->tarefalv = $DB->get_record('tarefalv', array('id'=>$this->cm->instance))) {
			print_error('invalidid', 'tarefalv');
		}

		$this->tarefalv->cmidnumber = $this->cm->idnumber; // compatibility with modedit tarefalv obj
		$this->tarefalv->courseid   = $this->course->id; // compatibility with modedit tarefalv obj

		$this->strtarefalv = get_string('modulename', 'tarefalv');
		$this->strtarefalvs = get_string('modulenameplural', 'tarefalv');
		$this->strsubmissions = get_string('submissions', 'tarefalv');
		$this->strlastmodified = get_string('lastmodified');
		$this->pagetitle = strip_tags($courseshortname.': '.$this->strtarefalv.': '.format_string($this->tarefalv->name, true, array('context' => $this->context)));

		// visibility handled by require_login() with $cm parameter
		// get current group only when really needed

		/// Set up things for a HTML editor if it's needed
		$this->defaultformat = editors_get_preferred_format();
	}

	/**
	 * Display the tarefalv, used by view.php
	 *
	 * This in turn calls the methods producing individual parts of the page
	 */
	function view() {

		$context = context_module::instance($this->cm->id);
		require_capability('mod/tarefalv:view', $context);

		add_to_log($this->course->id, "tarefalv", "view", "view.php?id={$this->cm->id}",
		$this->tarefalv->id, $this->cm->id);

		$this->view_header();

		$this->view_intro();

		$this->view_dates();

		$this->view_feedback();

		$this->view_footer();
	}

	/**
	 * Display the header and top of a page
	 *
	 * (this doesn't change much for tarefalv types)
	 * This is used by the view() method to print the header of view.php but
	 * it can be used on other pages in which case the string to denote the
	 * page in the navigation trail should be passed as an argument
	 *
	 * @global object
	 * @param string $subpage Description of subpage to be used in navigation trail
	 */
	function view_header($subpage='') {
		global $CFG, $PAGE, $OUTPUT;

		if ($subpage) {
			$PAGE->navbar->add($subpage);
		}

		$PAGE->set_title($this->pagetitle);
		$PAGE->set_heading($this->course->fullname);

		echo $OUTPUT->header();

		groups_print_activity_menu($this->cm, $CFG->wwwroot . '/mod/tarefalv/view.php?id=' . $this->cm->id);

		echo '<div class="reportlink">'.$this->submittedlink().'</div>';
		echo '<div class="clearer"></div>';

		if (has_capability('moodle/site:config', context_system::instance())) {
			echo $OUTPUT->notification(get_string('upgradenotification', 'tarefalv'));
			$adminurl = new moodle_url('/admin/tool/tarefalvupgrade/listnotupgraded.php');
			echo $OUTPUT->single_button($adminurl, get_string('viewtarefalvupgradetool', 'tarefalv'));
		}
	}


	/**
	 * Display the tarefalv intro
	 *
	 * This will most likely be extended by tarefalv type plug-ins
	 * The default implementation prints the tarefalv description in a box
	 */
	function view_intro() {
		global $OUTPUT;
		echo $OUTPUT->box_start('generalbox boxaligncenter', 'intro');
		echo format_module_intro('tarefalv', $this->tarefalv, $this->cm->id);
		echo $OUTPUT->box_end();
		echo plagiarism_print_disclosure($this->cm->id);
	}

	/**
	 * Display the tarefalv dates
	 *
	 * Prints the tarefalv start and end dates in a box.
	 * This will be suitable for most tarefalv types
	 */
	function view_dates() {
		global $OUTPUT;
		if (!$this->tarefalv->timeavailable && !$this->tarefalv->timedue) {
			return;
		}

		echo $OUTPUT->box_start('generalbox boxaligncenter', 'dates');
		echo '<table>';
		if ($this->tarefalv->timeavailable) {
			echo '<tr><td class="c0">'.get_string('availabledate','tarefalv').':</td>';
			echo '    <td class="c1">'.userdate($this->tarefalv->timeavailable).'</td></tr>';
		}
		if ($this->tarefalv->timedue) {
			echo '<tr><td class="c0">'.get_string('duedate','tarefalv').':</td>';
			echo '    <td class="c1">'.userdate($this->tarefalv->timedue).'</td></tr>';
		}
		echo '</table>';
		echo $OUTPUT->box_end();
	}


	/**
	 * Display the bottom and footer of a page
	 *
	 * This default method just prints the footer.
	 * This will be suitable for most tarefalv types
	 */
	function view_footer() {
		global $OUTPUT;
		echo $OUTPUT->footer();
	}

	/**
	 * Display the feedback to the student
	 *
	 * This default method prints the teacher picture and name, date when marked,
	 * grade and teacher submissioncomment.
	 * If advanced grading is used the method render_grade from the
	 * advanced grading controller is called to display the grade.
	 *
	 * @global object
	 * @global object
	 * @global object
	 * @param object $submission The submission object or NULL in which case it will be loaded
	 * @return bool
	 */
	function view_feedback($submission=NULL) {
		global $USER, $CFG, $DB, $OUTPUT, $PAGE;
		require_once($CFG->libdir.'/gradelib.php');
		require_once("$CFG->dirroot/grade/grading/lib.php");

		if (!$submission) { /// Get submission for this tarefalv
			$userid = $USER->id;
			$submission = $this->get_submission($userid);
		} else {
			$userid = $submission->userid;
		}
		// Check the user can submit
		$canviewfeedback = ($userid == $USER->id && has_capability('mod/tarefalv:submit', $this->context, $USER->id, false));
		// If not then check if the user still has the view cap and has a previous submission
		$canviewfeedback = $canviewfeedback || (!empty($submission) && $submission->userid == $USER->id && has_capability('mod/tarefalv:view', $this->context));
		// Or if user can grade (is a teacher or admin)
		$canviewfeedback = $canviewfeedback || has_capability('mod/tarefalv:grade', $this->context);

		if (!$canviewfeedback) {
			// can not view or submit tarefalvs -> no feedback
			return false;
		}

		$grading_info = grade_get_grades($this->course->id, 'mod', 'tarefalv', $this->tarefalv->id, $userid);
		$item = $grading_info->items[0];
		$grade = $item->grades[$userid];

		if ($grade->hidden or $grade->grade === false) { // hidden or error
			return false;
		}

		if ($grade->grade === null and empty($grade->str_feedback)) { // No grade to show yet
			// If sumbission then check if feedback is avaiable to show else return.
			if (!$submission) {
				return false;
			}

			$fs = get_file_storage();
			$noresponsefiles = $fs->is_area_empty($this->context->id, 'mod_tarefalv', 'response', $submission->id);
			if (empty($submission->submissioncomment) && $noresponsefiles) { // Nothing to show yet
				return false;
			}

			// We need the teacher info
			if (!$teacher = $DB->get_record('user', array('id'=>$submission->teacher))) {
				print_error('cannotfindteacher');
			}

			$feedbackdate = $submission->timemarked;
			$feedback = format_text($submission->submissioncomment, $submission->format);
			$strlonggrade = '-';
		}
		else {
			// We need the teacher info
			if (!$teacher = $DB->get_record('user', array('id'=>$grade->usermodified))) {
				print_error('cannotfindteacher');
			}

			$feedbackdate = $grade->dategraded;
			$feedback = $grade->str_feedback;
			$strlonggrade = $grade->str_long_grade;
		}

		// Print the feedback
		echo $OUTPUT->heading(get_string('submissionfeedback', 'tarefalv'), 3);

		echo '<table cellspacing="0" class="feedback">';

		echo '<tr>';
		echo '<td class="left picture">';
		if ($teacher) {
			echo $OUTPUT->user_picture($teacher);
		}
		echo '</td>';
		echo '<td class="topic">';
		echo '<div class="from">';
		if ($teacher) {
			echo '<div class="fullname">'.fullname($teacher).'</div>';
		}
		echo '<div class="time">'.userdate($feedbackdate).'</div>';
		echo '</div>';
		echo '</td>';
		echo '</tr>';

		echo '<tr>';
		echo '<td class="left side">&nbsp;</td>';
		echo '<td class="content">';

		if ($this->tarefalv->grade) {
			$gradestr = '<div class="grade">'. get_string("grade").': '.$strlonggrade. '</div>';
			if (!empty($submission) && $controller = get_grading_manager($this->context, 'mod_tarefalv', 'submission')->get_active_controller()) {
				$controller->set_grade_range(make_grades_menu($this->tarefalv->grade));
				echo $controller->render_grade($PAGE, $submission->id, $item, $gradestr, has_capability('mod/tarefalv:grade', $this->context));
			} else {
				echo $gradestr;
			}
			echo '<div class="clearer"></div>';
		}

		echo '<div class="comment">';
		echo $feedback;
		echo '</div>';
		echo '</tr>';
		if (method_exists($this, 'view_responsefile')) {
			$this->view_responsefile($submission);
		}
		echo '</table>';

		return true;
	}

	/**
	 * Returns a link with info about the state of the tarefalv submissions
	 *
	 * This is used by view_header to put this link at the top right of the page.
	 * For teachers it gives the number of submitted tarefalvs with a link
	 * For students it gives the time of their submission.
	 * This will be suitable for most tarefalv types.
	 *
	 * @global object
	 * @global object
	 * @param bool $allgroup print all groups info if user can access all groups, suitable for index.php
	 * @return string
	 */
	function submittedlink($allgroups=false) {
		global $USER;
		global $CFG;

		$submitted = '';
		$urlbase = "{$CFG->wwwroot}/mod/tarefalv/";

		$context = context_module::instance($this->cm->id);
		if (has_capability('mod/tarefalv:grade', $context)) {
			if ($allgroups and has_capability('moodle/site:accessallgroups', $context)) {
				$group = 0;
			} else {
				$group = groups_get_activity_group($this->cm);
			}
			if ($this->type == 'offline') {
				$submitted = '<a href="'.$urlbase.'submissions.php?id='.$this->cm->id.'">'.
						get_string('viewfeedback', 'tarefalv').'</a>';
			} else if ($count = $this->count_real_submissions($group)) {
				$submitted = '<a href="'.$urlbase.'submissions.php?id='.$this->cm->id.'">'.
						get_string('viewsubmissions', 'tarefalv', $count).'</a>';
			} else {
				$submitted = '<a href="'.$urlbase.'submissions.php?id='.$this->cm->id.'">'.
						get_string('noattempts', 'tarefalv').'</a>';
			}
		} else {
			if (isloggedin()) {
				if ($submission = $this->get_submission($USER->id)) {
					// If the submission has been completed
					if ($this->is_submitted_with_required_data($submission)) {
						if ($submission->timemodified <= $this->tarefalv->timedue || empty($this->tarefalv->timedue)) {
							$submitted = '<span class="early">'.userdate($submission->timemodified).'</span>';
						} else {
							$submitted = '<span class="late">'.userdate($submission->timemodified).'</span>';
						}
					}
				}
			}
		}

		return $submitted;
	}

	/**
	 * Returns whether the assigment supports lateness information
	 *
	 * @return bool This tarefalv type supports lateness (true, default) or no (false)
	 */
	function supports_lateness() {
		return true;
	}

	/**
	 * @todo Document this function
	 */
	function setup_elements(&$mform) {

	}

	/**
	 * Any preprocessing needed for the settings form for
	 * this tarefalv type
	 *
	 * @param array $default_values - array to fill in with the default values
	 *      in the form 'formelement' => 'value'
	 * @param object $form - the form that is to be displayed
	 * @return none
	 */
	function form_data_preprocessing(&$default_values, $form) {
	}

	/**
	 * Any extra validation checks needed for the settings
	 * form for this tarefalv type
	 *
	 * See lib/formslib.php, 'validation' function for details
	 */
	function form_validation($data, $files) {
		return array();
	}

	/**
	 * Create a new tarefalv activity
	 *
	 * Given an object containing all the necessary data,
	 * (defined by the form in mod_form.php) this function
	 * will create a new instance and return the id number
	 * of the new instance.
	 * The due data is added to the calendar
	 * This is common to all tarefalv types.
	 *
	 * @global object
	 * @global object
	 * @param object $tarefalv The data from the form on mod_form.php
	 * @return int The id of the tarefalv
	 */
	function add_instance($tarefalv) {
		global $COURSE, $DB;

		$tarefalv->timemodified = time();
		$tarefalv->courseid = $tarefalv->course;
        
        //@lvs se o checkbox estiver desmarcado setar para 0
        $tarefalv->exibir = (isset($tarefalv->exibir)) ? 1 : 0;
        
		$returnid = $DB->insert_record("tarefalv", $tarefalv);
		$tarefalv->id = $returnid;

		if ($tarefalv->timedue) {
			$event = new stdClass();
			$event->name        = $tarefalv->name;
			$event->description = format_module_intro('tarefalv', $tarefalv, $tarefalv->coursemodule);
			$event->courseid    = $tarefalv->course;
			$event->groupid     = 0;
			$event->userid      = 0;
			$event->modulename  = 'tarefalv';
			$event->instance    = $returnid;
			$event->eventtype   = 'due';
			$event->timestart   = $tarefalv->timedue;
			$event->timeduration = 0;

			calendar_event::create($event);
		}

		tarefalv_grade_item_update($tarefalv);

		return $returnid;
	}

	/**
	 * Deletes an tarefalv activity
	 *
	 * Deletes all database records, files and calendar events for this tarefalv.
	 *
	 * @global object
	 * @global object
	 * @param object $tarefalv The tarefalv to be deleted
	 * @return boolean False indicates error
	 */
	function delete_instance($tarefalv) {
		global $CFG, $DB;

		$tarefalv->courseid = $tarefalv->course;

		$result = true;

		// now get rid of all files
		$fs = get_file_storage();
		if ($cm = get_coursemodule_from_instance('tarefalv', $tarefalv->id)) {
			$context = context_module::instance($cm->id);
			$fs->delete_area_files($context->id);
		}

		if (! $DB->delete_records('tarefalv_submissions', array('tarefalv'=>$tarefalv->id))) {
			$result = false;
		}

		if (! $DB->delete_records('event', array('modulename'=>'tarefalv', 'instance'=>$tarefalv->id))) {
			$result = false;
		}

		if (! $DB->delete_records('tarefalv', array('id'=>$tarefalv->id))) {
			$result = false;
		}
		$mod = $DB->get_field('modules','id',array('name'=>'tarefalv'));

		tarefalv_grade_item_delete($tarefalv);

		return $result;
	}

	/**
	 * Updates a new tarefalv activity
	 *
	 * Given an object containing all the necessary data,
	 * (defined by the form in mod_form.php) this function
	 * will update the tarefalv instance and return the id number
	 * The due date is updated in the calendar
	 * This is common to all tarefalv types.
	 *
	 * @global object
	 * @global object
	 * @param object $tarefalv The data from the form on mod_form.php
	 * @return bool success
	 */
	function update_instance($tarefalv) {
		global $COURSE, $DB;

		$tarefalv->timemodified = time();

		$tarefalv->id = $tarefalv->instance;
		$tarefalv->courseid = $tarefalv->course;

        //@lvs se o checkbox estiver desmarcado setar para 0
        $tarefalv->exibir = (isset($tarefalv->exibir)) ? 1 : 0;

		$DB->update_record('tarefalv', $tarefalv);

		if ($tarefalv->timedue) {
			$event = new stdClass();

			if ($event->id = $DB->get_field('event', 'id', array('modulename'=>'tarefalv', 'instance'=>$tarefalv->id))) {

				$event->name        = $tarefalv->name;
				$event->description = format_module_intro('tarefalv', $tarefalv, $tarefalv->coursemodule);
				$event->timestart   = $tarefalv->timedue;

				$calendarevent = calendar_event::load($event->id);
				$calendarevent->update($event);
			} else {
				$event = new stdClass();
				$event->name        = $tarefalv->name;
				$event->description = format_module_intro('tarefalv', $tarefalv, $tarefalv->coursemodule);
				$event->courseid    = $tarefalv->course;
				$event->groupid     = 0;
				$event->userid      = 0;
				$event->modulename  = 'tarefalv';
				$event->instance    = $tarefalv->id;
				$event->eventtype   = 'due';
				$event->timestart   = $tarefalv->timedue;
				$event->timeduration = 0;

				calendar_event::create($event);
			}
		} else {
			$DB->delete_records('event', array('modulename'=>'tarefalv', 'instance'=>$tarefalv->id));
		}

		// get existing grade item
		tarefalv_grade_item_update($tarefalv);

		return true;
	}

	/**
	 * Update grade item for this submission.
	 *
	 * @param stdClass $submission The submission instance
	 */
	function update_grade($submission) {
		tarefalv_update_grades($this->tarefalv, $submission->userid);
	}

	/**
	 * Top-level function for handling of submissions called by submissions.php
	 *
	 * This is for handling the teacher interaction with the grading interface
	 * This should be suitable for most tarefalv types.
	 *
	 * @global object
	 * @param string $mode Specifies the kind of teacher interaction taking place
	 */
	function submissions($mode) {
		///The main switch is changed to facilitate
		///1) Batch fast grading
		///2) Skip to the next one on the popup
		///3) Save and Skip to the next one on the popup

		//make user global so we can use the id
		global $USER, $OUTPUT, $DB, $PAGE;

		$mailinfo = optional_param('mailinfo', null, PARAM_BOOL);

		if (optional_param('next', null, PARAM_BOOL)) {
			$mode='next';
		}
		if (optional_param('saveandnext', null, PARAM_BOOL)) {
			$mode='saveandnext';
		}

		if (is_null($mailinfo)) {
			if (optional_param('sesskey', null, PARAM_BOOL)) {
				set_user_preference('tarefalv_mailinfo', 0);
			} else {
				$mailinfo = get_user_preferences('tarefalv_mailinfo', 0);
			}
		} else {
			set_user_preference('tarefalv_mailinfo', $mailinfo);
		}

		if (!($this->validate_and_preprocess_feedback())) {
			// form was submitted ('Save' or 'Save and next' was pressed, but validation failed)
			$this->display_submission();
			return;
		}

		switch ($mode) {
			case 'grade':                         // We are in a main window grading
				if ($submission = $this->process_feedback()) {
					$this->display_submissions(get_string('changessaved'));
				} else {
					$this->display_submissions();
				}
				break;

			case 'single':                        // We are in a main window displaying one submission
				if ($submission = $this->process_feedback()) {
					$this->display_submissions(get_string('changessaved'));
				} else {
					$this->display_submission();
				}
				break;

			case 'all':                          // Main window, display everything
				$this->display_submissions();
				break;

			case 'fastgrade':
				///do the fast grading stuff  - this process should work for all 3 subclasses
				$grading    = false;
				$commenting = false;
				$col        = false;
				if (isset($_POST['submissioncomment'])) {
					$col = 'submissioncomment';
					$commenting = true;
				}
				if (isset($_POST['menu'])) {
					$col = 'menu';
					$grading = true;
				}
				if (!$col) {
					//both submissioncomment and grade columns collapsed..
					$this->display_submissions();
					break;
				}

				foreach ($_POST[$col] as $id => $unusedvalue){

					$id = (int)$id; //clean parameter name

					$this->process_outcomes($id);

					if (!$submission = $this->get_submission($id)) {
						$submission = $this->prepare_new_submission($id);
						$newsubmission = true;
					} else {
						$newsubmission = false;
					}
					unset($submission->data1);  // Don't need to update this.
					unset($submission->data2);  // Don't need to update this.

					//for fast grade, we need to check if any changes take place
					$updatedb = false;

					if ($grading) {
						$grade = $_POST['menu'][$id];
						$updatedb = $updatedb || ($submission->grade != $grade);
						$submission->grade = $grade;
					} else {
						if (!$newsubmission) {
							unset($submission->grade);  // Don't need to update this.
						}
					}
					if ($commenting) {
						$commentvalue = trim($_POST['submissioncomment'][$id]);
						$updatedb = $updatedb || ($submission->submissioncomment != $commentvalue);
						$submission->submissioncomment = $commentvalue;
					} else {
						unset($submission->submissioncomment);  // Don't need to update this.
					}

					$submission->teacher    = $USER->id;
					if ($updatedb) {
						$submission->mailed = (int)(!$mailinfo);
					}

					$submission->timemarked = time();

					//if it is not an update, we don't change the last modified time etc.
					//this will also not write into database if no submissioncomment and grade is entered.

					if ($updatedb){
						if ($newsubmission) {
							if (!isset($submission->submissioncomment)) {
								$submission->submissioncomment = '';
							}
							$sid = $DB->insert_record('tarefalv_submissions', $submission);
							$submission->id = $sid;
						} else {
							$DB->update_record('tarefalv_submissions', $submission);
						}

						// trigger grade event
						$this->update_grade($submission);

						//add to log only if updating
						add_to_log($this->course->id, 'tarefalv', 'update grades',
						'submissions.php?id='.$this->cm->id.'&user='.$submission->userid,
						$submission->userid, $this->cm->id);
					}

				}

				$message = $OUTPUT->notification(get_string('changessaved'), 'notifysuccess');

				$this->display_submissions($message);
				break;


			case 'saveandnext':
				///We are in pop up. save the current one and go to the next one.
				//first we save the current changes
				if ($submission = $this->process_feedback()) {
					//print_heading(get_string('changessaved'));
					//$extra_javascript = $this->update_main_listing($submission);
				}

			case 'next':
				/// We are currently in pop up, but we want to skip to next one without saving.
				///    This turns out to be similar to a single case
				/// The URL used is for the next submission.
				$offset = required_param('offset', PARAM_INT);
				$nextid = required_param('nextid', PARAM_INT);
				$id = required_param('id', PARAM_INT);
				$filter = optional_param('filter', self::FILTER_ALL, PARAM_INT);

				if ($mode == 'next' || $filter !== self::FILTER_REQUIRE_GRADING) {
					$offset = (int)$offset+1;
				}
				$redirect = new moodle_url('submissions.php',
						array('id' => $id, 'offset' => $offset, 'userid' => $nextid,
								'mode' => 'single', 'filter' => $filter));

				redirect($redirect);
				break;

				case 'singlenosave':
					$this->display_submission();
					break;

				default:
					echo "something seriously is wrong!!";
					break;
		}
	}

	/**
	 * Checks if grading method allows quickgrade mode. At the moment it is hardcoded
	 * that advanced grading methods do not allow quickgrade.
	 *
	 * Assignment type plugins are not allowed to override this method
	 *
	 * @return boolean
	 */
	public final function quickgrade_mode_allowed() {
		global $CFG;
		require_once("$CFG->dirroot/grade/grading/lib.php");
		if ($controller = get_grading_manager($this->context, 'mod_tarefalv', 'submission')->get_active_controller()) {
			return false;
		}
		return true;
	}

	/**
	 * Helper method updating the listing on the main script from popup using javascript
	 *
	 * @global object
	 * @global object
	 * @param $submission object The submission whose data is to be updated on the main page
	 */
	function update_main_listing($submission) {
		global $SESSION, $CFG, $OUTPUT;

		$output = '';

		$perpage = get_user_preferences('tarefalv_perpage', 10);

		$quickgrade = get_user_preferences('tarefalv_quickgrade', 0) && $this->quickgrade_mode_allowed();

		/// Run some Javascript to try and update the parent page
		$output .= '<script type="text/javascript">'."\n<!--\n";
		if (empty($SESSION->flextable['mod-tarefalv-submissions']->collapse['submissioncomment'])) {
			if ($quickgrade){
				$output.= 'opener.document.getElementById("submissioncomment'.$submission->userid.'").value="'
						.trim($submission->submissioncomment).'";'."\n";
			} else {
				$output.= 'opener.document.getElementById("com'.$submission->userid.
				'").innerHTML="'.shorten_text(trim(strip_tags($submission->submissioncomment)), 15)."\";\n";
			}
		}

		if (empty($SESSION->flextable['mod-tarefalv-submissions']->collapse['grade'])) {
			//echo optional_param('menuindex');
			if ($quickgrade){
				$output.= 'opener.document.getElementById("menumenu'.$submission->userid.
				'").selectedIndex="'.optional_param('menuindex', 0, PARAM_INT).'";'."\n";
			} else {
				$output.= 'opener.document.getElementById("g'.$submission->userid.'").innerHTML="'.
						$this->display_grade($submission->grade)."\";\n";
			}
		}
		//need to add student's tarefalvs in there too.
		if (empty($SESSION->flextable['mod-tarefalv-submissions']->collapse['timemodified']) &&
				$submission->timemodified) {
			$output.= 'opener.document.getElementById("ts'.$submission->userid.
			'").innerHTML="'.addslashes_js($this->print_student_answer($submission->userid)).userdate($submission->timemodified)."\";\n";
		}

		if (empty($SESSION->flextable['mod-tarefalv-submissions']->collapse['timemarked']) &&
				$submission->timemarked) {
			$output.= 'opener.document.getElementById("tt'.$submission->userid.
			'").innerHTML="'.userdate($submission->timemarked)."\";\n";
		}

		if (empty($SESSION->flextable['mod-tarefalv-submissions']->collapse['status'])) {
			$output.= 'opener.document.getElementById("up'.$submission->userid.'").className="s1";';
			$buttontext = get_string('update');
			$url = new moodle_url('/mod/tarefalv/submissions.php', array(
					'id' => $this->cm->id,
					'userid' => $submission->userid,
					'mode' => 'single',
					'offset' => (optional_param('offset', '', PARAM_INT)-1)));
			$button = $OUTPUT->action_link($url, $buttontext, new popup_action('click', $url, 'grade'.$submission->userid, array('height' => 450, 'width' => 700)), array('ttile'=>$buttontext));

			$output .= 'opener.document.getElementById("up'.$submission->userid.'").innerHTML="'.addslashes_js($button).'";';
		}

		$grading_info = grade_get_grades($this->course->id, 'mod', 'tarefalv', $this->tarefalv->id, $submission->userid);

		if (empty($SESSION->flextable['mod-tarefalv-submissions']->collapse['finalgrade'])) {
			$output.= 'opener.document.getElementById("finalgrade_'.$submission->userid.
			'").innerHTML="'.$grading_info->items[0]->grades[$submission->userid]->str_grade.'";'."\n";
		}

		if (!empty($CFG->enableoutcomes) and empty($SESSION->flextable['mod-tarefalv-submissions']->collapse['outcome'])) {

			if (!empty($grading_info->outcomes)) {
				foreach($grading_info->outcomes as $n=>$outcome) {
					if ($outcome->grades[$submission->userid]->locked) {
						continue;
					}

					if ($quickgrade){
						$output.= 'opener.document.getElementById("outcome_'.$n.'_'.$submission->userid.
						'").selectedIndex="'.$outcome->grades[$submission->userid]->grade.'";'."\n";

					} else {
						$options = make_grades_menu(-$outcome->scaleid);
						$options[0] = get_string('nooutcome', 'grades');
						$output.= 'opener.document.getElementById("outcome_'.$n.'_'.$submission->userid.'").innerHTML="'.$options[$outcome->grades[$submission->userid]->grade]."\";\n";
					}

				}
			}
		}

		$output .= "\n-->\n</script>";
		return $output;
	}

	/**
	 *  Return a grade in user-friendly form, whether it's a scale or not
	 *
	 * @global object
	 * @param mixed $grade
	 * @return string User-friendly representation of grade
	 */
	function display_grade($grade) {
		global $DB;

		static $scalegrades = array();   // Cache scales for each tarefalv - they might have different scales!!

		if ($this->tarefalv->grade >= 0) {    // Normal number
			if ($grade == -1) {
				return '-';
			} else {
				return $grade.' / '.$this->tarefalv->grade;
			}

		} else {                                // Scale
			if (empty($scalegrades[$this->tarefalv->id])) {
				if ($scale = $DB->get_record('scale', array('id'=>-($this->tarefalv->grade)))) {
					$scalegrades[$this->tarefalv->id] = make_menu_from_list($scale->scale);
				} else {
					return '-';
				}
			}
			if (isset($scalegrades[$this->tarefalv->id][$grade])) {
				return $scalegrades[$this->tarefalv->id][$grade];
			}
			return '-';
		}
	}

	/**
	 *  Display a single submission, ready for grading on a popup window
	 *
	 * This default method prints the teacher info and submissioncomment box at the top and
	 * the student info and submission at the bottom.
	 * This method also fetches the necessary data in order to be able to
	 * provide a "Next submission" button.
	 * Calls preprocess_submission() to give tarefalv type plug-ins a chance
	 * to process submissions before they are graded
	 * This method gets its arguments from the page parameters userid and offset
	 *
	 * @global object
	 * @global object
	 * @param string $extra_javascript
	 */
	function display_submission($offset=-1,$userid =-1, $display=true) {
		global $CFG, $DB, $PAGE, $OUTPUT, $USER;
		require_once($CFG->libdir.'/gradelib.php');
		require_once($CFG->libdir.'/tablelib.php');
		require_once("$CFG->dirroot/repository/lib.php");
		require_once("$CFG->dirroot/grade/grading/lib.php");
		if ($userid==-1) {
			$userid = required_param('userid', PARAM_INT);
		}
		if ($offset==-1) {
			$offset = required_param('offset', PARAM_INT);//offset for where to start looking for student.
		}
		$filter = optional_param('filter', 0, PARAM_INT);

		if (!$user = $DB->get_record('user', array('id'=>$userid))) {
			print_error('nousers');
		}

		if (!$submission = $this->get_submission($user->id)) {
			$submission = $this->prepare_new_submission($userid);
		}
		if ($submission->timemodified > $submission->timemarked) {
			$subtype = 'tarefalvnew';
		} else {
			$subtype = 'tarefalvold';
		}

		$grading_info = grade_get_grades($this->course->id, 'mod', 'tarefalv', $this->tarefalv->id, array($user->id));
		$gradingdisabled = $grading_info->items[0]->grades[$userid]->locked || $grading_info->items[0]->grades[$userid]->overridden;

		/// construct SQL, using current offset to find the data of the next student
		$course     = $this->course;
		$tarefalv = $this->tarefalv;
		$cm         = $this->cm;
		$context    = context_module::instance($cm->id);

		//reset filter to all for offline tarefalv
		if ($tarefalv->tarefalvtype == 'offline' && $filter == self::FILTER_SUBMITTED) {
			$filter = self::FILTER_ALL;
		}
		/// Get all ppl that can submit tarefalvs

		$currentgroup = groups_get_activity_group($cm);
		$users = get_enrolled_users($context, 'mod/tarefalv:submit', $currentgroup, 'u.id');
		if ($users) {
			$users = array_keys($users);
			// if groupmembersonly used, remove users who are not in any group
			if (!empty($CFG->enablegroupmembersonly) and $cm->groupmembersonly) {
				if ($groupingusers = groups_get_grouping_members($cm->groupingid, 'u.id', 'u.id')) {
					$users = array_intersect($users, array_keys($groupingusers));
				}
			}
		}

		$nextid = 0;
		$where = '';
		if($filter == self::FILTER_SUBMITTED) {
			$where .= 's.timemodified > 0 AND ';
		} else if($filter == self::FILTER_REQUIRE_GRADING) {
			$where .= 's.timemarked < s.timemodified AND ';
		}

		if ($users) {
			$userfields = user_picture::fields('u', array('lastaccess'));
			$select = "SELECT $userfields,
			s.id AS submissionid, s.grade, s.submissioncomment,
			s.timemodified, s.timemarked,
			CASE WHEN s.timemarked > 0 AND s.timemarked >= s.timemodified THEN 1
			ELSE 0 END AS status ";

			$sql = 'FROM {user} u '.
					'LEFT JOIN {tarefalv_submissions} s ON u.id = s.userid
							AND s.tarefalv = '.$this->tarefalv->id.' '.
							'WHERE '.$where.'u.id IN ('.implode(',', $users).') ';

			if ($sort = flexible_table::get_sort_for_table('mod-tarefalv-submissions')) {
				$sort = 'ORDER BY '.$sort.' ';
			}
			$auser = $DB->get_records_sql($select.$sql.$sort, null, $offset, 2);

			if (is_array($auser) && count($auser)>1) {
				$nextuser = next($auser);
				$nextid = $nextuser->id;
			}
		}

		if ($submission->teacher) {
			$teacher = $DB->get_record('user', array('id'=>$submission->teacher));
		} else {
			global $USER;
			$teacher = $USER;
		}

		$this->preprocess_submission($submission);

		$mformdata = new stdClass();
		$mformdata->context = $this->context;
		$mformdata->maxbytes = $this->course->maxbytes;
		$mformdata->courseid = $this->course->id;
		$mformdata->teacher = $teacher;
		$mformdata->tarefalv = $tarefalv;
		$mformdata->submission = $submission;
		$mformdata->lateness = $this->display_lateness($submission->timemodified);
		$mformdata->auser = $auser;
		$mformdata->user = $user;
		$mformdata->offset = $offset;
		$mformdata->userid = $userid;
		$mformdata->cm = $this->cm;
		$mformdata->grading_info = $grading_info;
		$mformdata->enableoutcomes = $CFG->enableoutcomes;
		$mformdata->grade = $this->tarefalv->grade;
		$mformdata->gradingdisabled = $gradingdisabled;
		$mformdata->nextid = $nextid;
		$mformdata->submissioncomment= $submission->submissioncomment;
		$mformdata->submissioncommentformat= FORMAT_HTML;
		$mformdata->submission_content= $this->print_user_files($user->id,true);
		$mformdata->filter = $filter;
		$mformdata->mailinfo = get_user_preferences('tarefalv_mailinfo', 0);
		if ($tarefalv->tarefalvtype == 'upload') {
			$mformdata->fileui_options = array('subdirs'=>1, 'maxbytes'=>$tarefalv->maxbytes, 'maxfiles'=>$tarefalv->var1, 'accepted_types'=>'*', 'return_types'=>FILE_INTERNAL);
		} elseif ($tarefalv->tarefalvtype == 'uploadsingle') {
			$mformdata->fileui_options = array('subdirs'=>0, 'maxbytes'=>$CFG->userquota, 'maxfiles'=>1, 'accepted_types'=>'*', 'return_types'=>FILE_INTERNAL);
		}
		$advancedgradingwarning = false;
		$gradingmanager = get_grading_manager($this->context, 'mod_tarefalv', 'submission');
		if ($gradingmethod = $gradingmanager->get_active_method()) {
			$controller = $gradingmanager->get_controller($gradingmethod);
			if ($controller->is_form_available()) {
				$itemid = null;
				if (!empty($submission->id)) {
					$itemid = $submission->id;
				}
				if ($gradingdisabled && $itemid) {
					$mformdata->advancedgradinginstance = $controller->get_current_instance($USER->id, $itemid);
				} else if (!$gradingdisabled) {
					$instanceid = optional_param('advancedgradinginstanceid', 0, PARAM_INT);
					$mformdata->advancedgradinginstance = $controller->get_or_create_instance($instanceid, $USER->id, $itemid);
				}
			} else {
				$advancedgradingwarning = $controller->form_unavailable_notification();
			}
		}

		$submitform = new tarefalv_grading_form( null, $mformdata );

		if (!$display) {
			$ret_data = new stdClass();
			$ret_data->mform = $submitform;
			if (isset($mformdata->fileui_options)) {
				$ret_data->fileui_options = $mformdata->fileui_options;
			}
			return $ret_data;
		}

		if ($submitform->is_cancelled()) {
			redirect('submissions.php?id='.$this->cm->id);
		}

		$submitform->set_data($mformdata);

		$PAGE->set_title($this->course->fullname . ': ' .get_string('feedback', 'tarefalv').' - '.fullname($user, true));
		$PAGE->set_heading($this->course->fullname);
		$PAGE->navbar->add(get_string('submissions', 'tarefalv'), new moodle_url('/mod/tarefalv/submissions.php', array('id'=>$cm->id)));
		$PAGE->navbar->add(fullname($user, true));

		echo $OUTPUT->header();
		echo $OUTPUT->heading(get_string('feedback', 'tarefalv').': '.fullname($user, true));

		// display mform here...
		if ($advancedgradingwarning) {
			echo $OUTPUT->notification($advancedgradingwarning, 'error');
		}
		$submitform->display();

		$customfeedback = $this->custom_feedbackform($submission, true);
		if (!empty($customfeedback)) {
			echo $customfeedback;
		}

		echo $OUTPUT->footer();
	}

	/**
	 *  Preprocess submission before grading
	 *
	 * Called by display_submission()
	 * The default type does nothing here.
	 *
	 * @param object $submission The submission object
	 */
	function preprocess_submission(&$submission) {
	}

	/**
	 *  Display all the submissions ready for grading
	 *
	 * @global object
	 * @global object
	 * @global object
	 * @global object
	 * @param string $message
	 * @return bool|void
	 */
	function display_submissions($message='') {
		global $CFG, $DB, $USER, $DB, $OUTPUT, $PAGE;
		require_once($CFG->libdir.'/gradelib.php');

		/* first we check to see if the form has just been submitted
		 * to request user_preference updates
		*/

		$filters = array(self::FILTER_ALL             => get_string('all'),
				self::FILTER_REQUIRE_GRADING => get_string('requiregrading', 'tarefalv'));

		$updatepref = optional_param('updatepref', 0, PARAM_BOOL);
		if ($updatepref) {
			$perpage = optional_param('perpage', 10, PARAM_INT);
			$perpage = ($perpage <= 0) ? 10 : $perpage ;
			$filter = optional_param('filter', 0, PARAM_INT);
			set_user_preference('tarefalv_perpage', $perpage);
			set_user_preference('tarefalv_quickgrade', optional_param('quickgrade', 0, PARAM_BOOL));
			set_user_preference('tarefalv_filter', $filter);
		}

		/* next we get perpage and quickgrade (allow quick grade) params
		 * from database
		*/
		$perpage    = get_user_preferences('tarefalv_perpage', 10);
		$quickgrade = get_user_preferences('tarefalv_quickgrade', 0) && $this->quickgrade_mode_allowed();
		$filter = get_user_preferences('tarefalv_filter', 0);
		$grading_info = grade_get_grades($this->course->id, 'mod', 'tarefalv', $this->tarefalv->id);

		if (!empty($CFG->enableoutcomes) and !empty($grading_info->outcomes)) {
			$uses_outcomes = true;
		} else {
			$uses_outcomes = false;
		}

		$page    = optional_param('page', 0, PARAM_INT);
		$strsaveallfeedback = get_string('saveallfeedback', 'tarefalv');

		/// Some shortcuts to make the code read better

		$course     = $this->course;
		$tarefalv = $this->tarefalv;
		$cm         = $this->cm;
		$hassubmission = false;

		// reset filter to all for offline tarefalv only.
		if ($tarefalv->tarefalvtype == 'offline') {
			if ($filter == self::FILTER_SUBMITTED) {
				$filter = self::FILTER_ALL;
			}
		} else {
			$filters[self::FILTER_SUBMITTED] = get_string('submitted', 'tarefalv');
		}

		$tabindex = 1; //tabindex for quick grading tabbing; Not working for dropdowns yet
		add_to_log($course->id, 'tarefalv', 'view submission', 'submissions.php?id='.$this->cm->id, $this->tarefalv->id, $this->cm->id);

		$PAGE->set_title(format_string($this->tarefalv->name,true));
		$PAGE->set_heading($this->course->fullname);
		echo $OUTPUT->header();

		echo '<div class="usersubmissions">';

		//hook to allow plagiarism plugins to update status/print links.
		echo plagiarism_update_status($this->course, $this->cm);

		$course_context = context_course::instance($course->id);
		if (has_capability('gradereport/grader:view', $course_context) && has_capability('moodle/grade:viewall', $course_context)) {
			echo '<div class="allcoursegrades"><a href="' . $CFG->wwwroot . '/grade/report/grader/index.php?id=' . $course->id . '">'
					. get_string('seeallcoursegrades', 'grades') . '</a></div>';
		}

		if (!empty($message)) {
			echo $message;   // display messages here if any
		}

		$context = context_module::instance($cm->id);

		/// Check to see if groups are being used in this tarefalv

		/// find out current groups mode
		$groupmode = groups_get_activity_groupmode($cm);
		$currentgroup = groups_get_activity_group($cm, true);
		groups_print_activity_menu($cm, $CFG->wwwroot . '/mod/tarefalv/submissions.php?id=' . $this->cm->id);

		/// Print quickgrade form around the table
		if ($quickgrade) {
			$formattrs = array();
			$formattrs['action'] = new moodle_url('/mod/tarefalv/submissions.php');
			$formattrs['id'] = 'fastg';
			$formattrs['method'] = 'post';

			echo html_writer::start_tag('form', $formattrs);
			echo html_writer::empty_tag('input', array('type'=>'hidden', 'name'=>'id',      'value'=> $this->cm->id));
			echo html_writer::empty_tag('input', array('type'=>'hidden', 'name'=>'mode',    'value'=> 'fastgrade'));
			echo html_writer::empty_tag('input', array('type'=>'hidden', 'name'=>'page',    'value'=> $page));
			echo html_writer::empty_tag('input', array('type'=>'hidden', 'name'=>'sesskey', 'value'=> sesskey()));
		}

		/// Get all ppl that are allowed to submit tarefalvs
		list($esql, $params) = get_enrolled_sql($context, 'mod/tarefalv:submit', $currentgroup);

		if ($filter == self::FILTER_ALL) {
			$sql = "SELECT u.id FROM {user} u ".
					"LEFT JOIN ($esql) eu ON eu.id=u.id ".
					"WHERE u.deleted = 0 AND eu.id=u.id ";
		} else {
			$wherefilter = ' AND s.tarefalv = '. $this->tarefalv->id;
			$tarefalvsubmission = "LEFT JOIN {tarefalv_submissions} s ON (u.id = s.userid) ";
			if($filter == self::FILTER_SUBMITTED) {
				$wherefilter .= ' AND s.timemodified > 0 ';
			} else if($filter == self::FILTER_REQUIRE_GRADING && $tarefalv->tarefalvtype != 'offline') {
				$wherefilter .= ' AND s.timemarked < s.timemodified ';
			} else { // require grading for offline tarefalv
				$tarefalvsubmission = "";
				$wherefilter = "";
			}

			$sql = "SELECT u.id FROM {user} u ".
					"LEFT JOIN ($esql) eu ON eu.id=u.id ".
					$tarefalvsubmission.
					"WHERE u.deleted = 0 AND eu.id=u.id ".
					$wherefilter;
		}

		$users = $DB->get_records_sql($sql, $params);
		if (!empty($users)) {
			if($tarefalv->tarefalvtype == 'offline' && $filter == self::FILTER_REQUIRE_GRADING) {
				//remove users who has submitted their tarefalv
				foreach ($this->get_submissions() as $submission) {
					if (array_key_exists($submission->userid, $users)) {
						unset($users[$submission->userid]);
					}
				}
			}
			$users = array_keys($users);
		}

		// if groupmembersonly used, remove users who are not in any group
		if ($users and !empty($CFG->enablegroupmembersonly) and $cm->groupmembersonly) {
			if ($groupingusers = groups_get_grouping_members($cm->groupingid, 'u.id', 'u.id')) {
				$users = array_intersect($users, array_keys($groupingusers));
			}
		}

		$extrafields = get_extra_user_fields($context);

		/** @lvs colunas no quadro de notas lvs */
		$tablecolumns = array_merge(array('picture', 'fullname'), array('grade',  'timemodified', 'status'));
		/** original
		 $tablecolumns = array_merge(array('picture', 'fullname'), $extrafields,
		 array('grade', 'submissioncomment', 'timemodified', 'timemarked', 'status', 'finalgrade'));
		*/
		if ($uses_outcomes) {
			$tablecolumns[] = 'outcome'; // no sorting based on outcomes column
		}

		$extrafieldnames = array();
		foreach ($extrafields as $field) {
			$extrafieldnames[] = get_user_field_name($field);
		}

		/** @lvs headers das colunas no quadro de notas em tarefalv */
		$tableheaders = array_merge(
				array('', get_string('fullnameuser')),
				array(
						get_string('grade'),
						get_string('lastmodified').' ('.get_string('submission', 'tarefalv').')',
						get_string('status')
				));
		/** original
		 $tableheaders = array_merge(
		 array('', get_string('fullnameuser')),
		 $extrafieldnames,
		 array(
		 get_string('grade'),
		 get_string('comment', 'tarefalv'),
		 get_string('lastmodified').' ('.get_string('submission', 'tarefalv').')',
		 get_string('lastmodified').' ('.get_string('grade').')',
		 get_string('status'),
		 get_string('finalgrade', 'grades'),
		 ));
		*/
		if ($uses_outcomes) {
			$tableheaders[] = get_string('outcome', 'grades');
		}

		require_once($CFG->libdir.'/tablelib.php');
		$table = new flexible_table('mod-tarefalv-submissions');

		$table->define_columns($tablecolumns);
		$table->define_headers($tableheaders);
		$table->define_baseurl($CFG->wwwroot.'/mod/tarefalv/submissions.php?id='.$this->cm->id.'&amp;currentgroup='.$currentgroup);

		$table->sortable(true, 'lastname');//sorted by lastname by default
		$table->collapsible(true);
		$table->initialbars(true);

		$table->column_suppress('picture');
		$table->column_suppress('fullname');

		$table->column_class('picture', 'picture');
		$table->column_class('fullname', 'fullname');
		foreach ($extrafields as $field) {
			$table->column_class($field, $field);
		}

		/** @lvs estilização das colunas no quadro de notas em tarefalv */
		$table->column_class('grade', 'grade');
		$table->column_class('status', 'status');
		/** original
		 $table->column_class('grade', 'grade');
		 $table->column_class('submissioncomment', 'comment');
		 $table->column_class('timemodified', 'timemodified');
		 $table->column_class('timemarked', 'timemarked');
		 $table->column_class('status', 'status');
		 $table->column_class('finalgrade', 'finalgrade');
		*/

		if ($uses_outcomes) {
			$table->column_class('outcome', 'outcome');
		}

		$table->set_attribute('cellspacing', '0');
		$table->set_attribute('id', 'attempts');
		$table->set_attribute('class', 'submissions');
		$table->set_attribute('width', '100%');

		$table->no_sorting('finalgrade');
		$table->no_sorting('outcome');
		$table->text_sorting('submissioncomment');

		// Start working -- this is necessary as soon as the niceties are over
		$table->setup();

		/// Construct the SQL
		list($where, $params) = $table->get_sql_where();
		if ($where) {
			$where .= ' AND ';
		}

		if ($filter == self::FILTER_SUBMITTED) {
			$where .= 's.timemodified > 0 AND ';
		} else if($filter == self::FILTER_REQUIRE_GRADING) {
			$where = '';
			if ($tarefalv->tarefalvtype != 'offline') {
				$where .= 's.timemarked < s.timemodified AND ';
			}
		}

		if ($sort = $table->get_sql_sort()) {
			$sort = ' ORDER BY '.$sort;
		}

		$ufields = user_picture::fields('u', $extrafields);
		if (!empty($users)) {
			$select = "SELECT $ufields,
			s.id AS submissionid, s.grade, s.submissioncomment,
			s.timemodified, s.timemarked,
			CASE WHEN s.timemarked > 0 AND s.timemarked >= s.timemodified THEN 1
			ELSE 0 END AS status ";

			$sql = 'FROM {user} u '.
					'LEFT JOIN {tarefalv_submissions} s ON u.id = s.userid
							AND s.tarefalv = '.$this->tarefalv->id.' '.
							'WHERE '.$where.'u.id IN ('.implode(',',$users).') ';

			$ausers = $DB->get_records_sql($select.$sql.$sort, $params, $table->get_page_start(), $table->get_page_size());

			$table->pagesize($perpage, count($users));

			///offset used to calculate index of student in that particular query, needed for the pop up to know who's next
			$offset = $page * $perpage;
			$strupdate = get_string('update');
			$strgrade  = get_string('grade');
			$strview  = get_string('view');
			$grademenu = make_grades_menu($this->tarefalv->grade);

			if ($ausers !== false) {
				$grading_info = grade_get_grades($this->course->id, 'mod', 'tarefalv', $this->tarefalv->id, array_keys($ausers));
				$endposition = $offset + $perpage;
				$currentposition = 0;
				foreach ($ausers as $auser) {
					if ($currentposition == $offset && $offset < $endposition) {
						$rowclass = null;
						$final_grade = $grading_info->items[0]->grades[$auser->id];
						$grademax = $grading_info->items[0]->grademax;
						$final_grade->formatted_grade = round($final_grade->grade,2) .' / ' . round($grademax,2);
						$locked_overridden = 'locked';
						if ($final_grade->overridden) {
							$locked_overridden = 'overridden';
						}

						// TODO add here code if advanced grading grade must be reviewed => $auser->status=0

						$picture = $OUTPUT->user_picture($auser);

						if (empty($auser->submissionid)) {
							$auser->grade = -1; //no submission yet
						}

						if (!empty($auser->submissionid)) {
							$hassubmission = true;
							///Prints student answer and student modified date
							///attach file or print link to student answer, depending on the type of the tarefalv.
							///Refer to print_student_answer in inherited classes.
							if ($auser->timemodified > 0) {
								$studentmodifiedcontent = $this->print_student_answer($auser->id)
								. userdate($auser->timemodified);
								if ($tarefalv->timedue && $auser->timemodified > $tarefalv->timedue && $this->supports_lateness()) {
									$studentmodifiedcontent .= $this->display_lateness($auser->timemodified);
									$rowclass = 'late';
								}
							} else {
								$studentmodifiedcontent = '&nbsp;';
							}
							$studentmodified = html_writer::tag('div', $studentmodifiedcontent, array('id' => 'ts' . $auser->id));
							///Print grade, dropdown or text
							if ($auser->timemarked > 0) {
								$teachermodified = '<div id="tt'.$auser->id.'">'.userdate($auser->timemarked).'</div>';

								if ($final_grade->locked or $final_grade->overridden) {
									$grade = '<div id="g'.$auser->id.'" class="'. $locked_overridden .'">'.$final_grade->formatted_grade.'</div>';
								} else if ($quickgrade) {
									$attributes = array();
									$attributes['tabindex'] = $tabindex++;
									$menu = html_writer::label(get_string('tarefalv:grade', 'tarefalv'), 'menumenu'. $auser->id, false, array('class' => 'accesshide'));
									$menu .= html_writer::select(make_grades_menu($this->tarefalv->grade), 'menu['.$auser->id.']', $auser->grade, array(-1=>get_string('nograde')), $attributes);
									$grade = '<div id="g'.$auser->id.'">'. $menu .'</div>';
								} else {
									/** @lvs exibindo carinha em vez da nota textual */
									$carinhaslvs = new Carinhas();
									$carinhaimg = $carinhaslvs->recuperarCarinhaHtml( floatval($auser->grade) );
									$grade = '<div id="g'.$auser->id.'">'.$carinhaimg.'</div>';
									/** original
									 * $grade = '<div id="g'.$auser->id.'">'.$this->display_grade($auser->grade).'</div>';
									 * fim lvs
									 */
								}

							} else {
								$teachermodified = '<div id="tt'.$auser->id.'">&nbsp;</div>';
								if ($final_grade->locked or $final_grade->overridden) {
									$grade = '<div id="g'.$auser->id.'" class="'. $locked_overridden .'">'.$final_grade->formatted_grade.'</div>';
								} else if ($quickgrade) {
									$attributes = array();
									$attributes['tabindex'] = $tabindex++;
									$menu = html_writer::label(get_string('tarefalv:grade', 'tarefalv'), 'menumenu'. $auser->id, false, array('class' => 'accesshide'));
									$menu .= html_writer::select(make_grades_menu($this->tarefalv->grade), 'menu['.$auser->id.']', $auser->grade, array(-1=>get_string('nograde')), $attributes);
									$grade = '<div id="g'.$auser->id.'">'.$menu.'</div>';
								} else {
									$grade = '<div id="g'.$auser->id.'">'.$this->display_grade($auser->grade).'</div>';
								}
							}
							///Print Comment
							if ($final_grade->locked or $final_grade->overridden) {
								$comment = '<div id="com'.$auser->id.'">'.shorten_text(strip_tags($final_grade->str_feedback),15).'</div>';

							} else if ($quickgrade) {
								$comment = '<div id="com'.$auser->id.'">'
										. '<textarea tabindex="'.$tabindex++.'" name="submissioncomment['.$auser->id.']" id="submissioncomment'
												. $auser->id.'" rows="2" cols="20" spellcheck="true">'.($auser->submissioncomment).'</textarea></div>';
							} else {
								$comment = '<div id="com'.$auser->id.'">'.shorten_text(strip_tags($auser->submissioncomment),15).'</div>';
							}
						} else {
							$studentmodified = '<div id="ts'.$auser->id.'">&nbsp;</div>';
							$teachermodified = '<div id="tt'.$auser->id.'">&nbsp;</div>';
							$status          = '<div id="st'.$auser->id.'">&nbsp;</div>';

							if ($final_grade->locked or $final_grade->overridden) {
								$grade = '<div id="g'.$auser->id.'">'.$final_grade->formatted_grade . '</div>';
								$hassubmission = true;
							} else if ($quickgrade) {   // allow editing
								$attributes = array();
								$attributes['tabindex'] = $tabindex++;
								$menu = html_writer::label(get_string('tarefalv:grade', 'tarefalv'), 'menumenu'. $auser->id, false, array('class' => 'accesshide'));
								$menu .= html_writer::select(make_grades_menu($this->tarefalv->grade), 'menu['.$auser->id.']', $auser->grade, array(-1=>get_string('nograde')), $attributes);
								$grade = '<div id="g'.$auser->id.'">'.$menu.'</div>';
								$hassubmission = true;
							} else {
								$grade = '<div id="g'.$auser->id.'">-</div>';
							}

							if ($final_grade->locked or $final_grade->overridden) {
								$comment = '<div id="com'.$auser->id.'">'.$final_grade->str_feedback.'</div>';
							} else if ($quickgrade) {
								$comment = '<div id="com'.$auser->id.'">'
										. '<textarea tabindex="'.$tabindex++.'" name="submissioncomment['.$auser->id.']" id="submissioncomment'
												. $auser->id.'" rows="2" cols="20" spellcheck="true">'.($auser->submissioncomment).'</textarea></div>';
							} else {
								$comment = '<div id="com'.$auser->id.'">&nbsp;</div>';
							}
						}

						if (empty($auser->status)) { /// Confirm we have exclusively 0 or 1
							$auser->status = 0;
						} else {
							$auser->status = 1;
						}

						$buttontext = ($auser->status == 1) ? $strupdate : $strgrade;
						if ($final_grade->locked or $final_grade->overridden) {
							$buttontext = $strview;
						}

						///No more buttons, we use popups ;-).
						$popup_url = '/mod/tarefalv/submissions.php?id='.$this->cm->id
						. '&amp;userid='.$auser->id.'&amp;mode=single'.'&amp;filter='.$filter.'&amp;offset='.$offset++;

						$button = $OUTPUT->action_link($popup_url, $buttontext);

						$status  = '<div id="up'.$auser->id.'" class="s'.$auser->status.'">'.$button.'</div>';

						$finalgrade = '<span id="finalgrade_'.$auser->id.'">'.$final_grade->str_grade.'</span>';

						$outcomes = '';

						if ($uses_outcomes) {

							foreach($grading_info->outcomes as $n=>$outcome) {
								$outcomes .= '<div class="outcome"><label for="'. 'outcome_'.$n.'_'.$auser->id .'">'.$outcome->name.'</label>';
								$options = make_grades_menu(-$outcome->scaleid);

								if ($outcome->grades[$auser->id]->locked or !$quickgrade) {
									$options[0] = get_string('nooutcome', 'grades');
									$outcomes .= ': <span id="outcome_'.$n.'_'.$auser->id.'">'.$options[$outcome->grades[$auser->id]->grade].'</span>';
								} else {
									$attributes = array();
									$attributes['tabindex'] = $tabindex++;
									$attributes['id'] = 'outcome_'.$n.'_'.$auser->id;
									$outcomes .= ' '.html_writer::select($options, 'outcome_'.$n.'['.$auser->id.']', $outcome->grades[$auser->id]->grade, array(0=>get_string('nooutcome', 'grades')), $attributes);
								}
								$outcomes .= '</div>';
							}
						}

						$userlink = '<a href="' . $CFG->wwwroot . '/user/view.php?id=' . $auser->id . '&amp;course=' . $course->id . '">' . fullname($auser, has_capability('moodle/site:viewfullnames', $this->context)) . '</a>';
						$extradata = array();
						foreach ($extrafields as $field) {
							$extradata[] = $auser->{$field};
						}

						/** @lvs quadro de notas em tarefalv */
						$row = array_merge(array($picture, $userlink), array($grade, $studentmodified, $status));
						/** original
						 $row = array_merge(array($picture, $userlink), $extradata,
						 array($grade, $comment, $studentmodified, $teachermodified,
						 $status, $finalgrade));
						*/
						 
						if ($uses_outcomes) {
							$row[] = $outcomes;
						}
						$table->add_data($row, $rowclass);
					}
					$currentposition++;
				}
				if ($hassubmission && method_exists($this, 'download_submissions')) {
					echo html_writer::start_tag('div', array('class' => 'mod-tarefalv-download-link'));
					echo html_writer::link(new moodle_url('/mod/tarefalv/submissions.php', array('id' => $this->cm->id, 'download' => 'zip')), get_string('downloadall', 'tarefalv'));
					echo html_writer::end_tag('div');
				}
				$table->print_html();  /// Print the whole table
			} else {
				if ($filter == self::FILTER_SUBMITTED) {
					echo html_writer::tag('div', get_string('nosubmisson', 'tarefalv'), array('class'=>'nosubmisson'));
				} else if ($filter == self::FILTER_REQUIRE_GRADING) {
					echo html_writer::tag('div', get_string('norequiregrading', 'tarefalv'), array('class'=>'norequiregrading'));
				}
			}
		}

		/// Print quickgrade form around the table
		if ($quickgrade && $table->started_output && !empty($users)){
			$mailinfopref = false;
			if (get_user_preferences('tarefalv_mailinfo', 1)) {
				$mailinfopref = true;
			}
			$emailnotification =  html_writer::checkbox('mailinfo', 1, $mailinfopref, get_string('enablenotification','tarefalv'));

			$emailnotification .= $OUTPUT->help_icon('enablenotification', 'tarefalv');
			echo html_writer::tag('div', $emailnotification, array('class'=>'emailnotification'));

			$savefeedback = html_writer::empty_tag('input', array('type'=>'submit', 'name'=>'fastg', 'value'=>get_string('saveallfeedback', 'tarefalv')));
			echo html_writer::tag('div', $savefeedback, array('class'=>'fastgbutton'));

			echo html_writer::end_tag('form');
		} else if ($quickgrade) {
			echo html_writer::end_tag('form');
		}

		echo '</div>';
		/// End of fast grading form

		/// Mini form for setting user preference

		$formaction = new moodle_url('/mod/tarefalv/submissions.php', array('id'=>$this->cm->id));
		$mform = new MoodleQuickForm('optionspref', 'post', $formaction, '', array('class'=>'optionspref'));

		$mform->addElement('hidden', 'updatepref');
		$mform->setDefault('updatepref', 1);
		$mform->addElement('header', 'qgprefs', get_string('optionalsettings', 'tarefalv'));
		$mform->addElement('select', 'filter', get_string('show'),  $filters);

		$mform->setDefault('filter', $filter);

		$mform->addElement('text', 'perpage', get_string('pagesize', 'tarefalv'), array('size'=>1));
		$mform->setDefault('perpage', $perpage);

		if ($this->quickgrade_mode_allowed()) {
			$mform->addElement('checkbox', 'quickgrade', get_string('quickgrade','tarefalv'));
			$mform->setDefault('quickgrade', $quickgrade);
			$mform->addHelpButton('quickgrade', 'quickgrade', 'tarefalv');
		}

		$mform->addElement('submit', 'savepreferences', get_string('savepreferences'));

		$mform->display();

		echo $OUTPUT->footer();
	}

	/**
	 * If the form was cancelled ('Cancel' or 'Next' was pressed), call cancel method
	 * from advanced grading (if applicable) and returns true
	 * If the form was submitted, validates it and returns false if validation did not pass.
	 * If validation passes, preprocess advanced grading (if applicable) and returns true.
	 *
	 * Note to the developers: This is NOT the correct way to implement advanced grading
	 * in grading form. The tarefalv grading was written long time ago and unfortunately
	 * does not fully use the mforms. Usually function is_validated() is called to
	 * validate the form and get_data() is called to get the data from the form.
	 *
	 * Here we have to push the calculated grade to $_POST['xgrade'] because further processing
	 * of the form gets the data not from form->get_data(), but from $_POST (using statement
	 * like  $feedback = data_submitted() )
	 */
	protected function validate_and_preprocess_feedback() {
		global $USER, $CFG;
		require_once($CFG->libdir.'/gradelib.php');
		if (!($feedback = data_submitted()) || !isset($feedback->userid) || !isset($feedback->offset)) {
			return true;      // No incoming data, nothing to validate
		}
		$userid = required_param('userid', PARAM_INT);
		$offset = required_param('offset', PARAM_INT);
		$gradinginfo = grade_get_grades($this->course->id, 'mod', 'tarefalv', $this->tarefalv->id, array($userid));
		$gradingdisabled = $gradinginfo->items[0]->grades[$userid]->locked || $gradinginfo->items[0]->grades[$userid]->overridden;
		if ($gradingdisabled) {
			return true;
		}
		$submissiondata = $this->display_submission($offset, $userid, false);
		$mform = $submissiondata->mform;
		$gradinginstance = $mform->use_advanced_grading();
		if (optional_param('cancel', false, PARAM_BOOL) || optional_param('next', false, PARAM_BOOL)) {
			// form was cancelled
			if ($gradinginstance) {
				$gradinginstance->cancel();
			}
		} else if ($mform->is_submitted()) {
			// form was submitted (= a submit button other than 'cancel' or 'next' has been clicked)
			if (!$mform->is_validated()) {
				return false;
			}
			// preprocess advanced grading here
			if ($gradinginstance) {
				$data = $mform->get_data();
				// create submission if it did not exist yet because we need submission->id for storing the grading instance
				$submission = $this->get_submission($userid, true);
				$_POST['xgrade'] = $gradinginstance->submit_and_get_grade($data->advancedgrading, $submission->id);
			}
		}
		return true;
	}

	/**
	 *  Process teacher feedback submission
	 *
	 * This is called by submissions() when a grading even has taken place.
	 * It gets its data from the submitted form.
	 *
	 * @global object
	 * @global object
	 * @global object
	 * @return object|bool The updated submission object or false
	 */
	function process_feedback($formdata=null) {
		global $CFG, $USER, $DB;
		require_once($CFG->libdir.'/gradelib.php');

		if (!$feedback = data_submitted() or !confirm_sesskey()) {      // No incoming data?
			return false;
		}

		$feedback->xgrade = $feedback->rating; // @lvs um assignment espera um xgrade, mas lv manda rating

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

		if (!($grading_info->items[0]->grades[$feedback->userid]->locked ||
				$grading_info->items[0]->grades[$feedback->userid]->overridden) ) {

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
				// $submission->timemodified = time();
			}

			$DB->update_record('tarefalv_submissions', $submission);

			/** @lvs salva a nota dada como avaliação lv */
			$gerenciadorNotas = new Moodle2NotasLv();
			$gerenciadorNotas->setCursoLv(new Moodle2CursoLv($this->tarefalv->course));
			$gerenciadorNotas->setModulo(new Tarefalv($this->tarefalv->id));
			
			$item = new Item('tarefalv', 'submission', $submission);
			$avaliacaolv = new AvaliacaoLv();

			$avaliacaolv->setAvaliador($USER->id);
			$avaliacaolv->setEstudante($submission->userid);
			$avaliacaolv->setItem($item);
			$avaliacaolv->setNota($submission->grade);

			$item->setAvaliacao($avaliacaolv);

			$gerenciadorNotas->salvarAvaliacao($avaliacaolv);
			/** fim dos lvs */

			// triger grade event
			$this->update_grade($submission);

			add_to_log($this->course->id, 'tarefalv', 'update grades',
			'submissions.php?id='.$this->cm->id.'&user='.$feedback->userid, $feedback->userid, $this->cm->id);
			if (!is_null($formdata)) {
				if ($this->type == 'upload' || $this->type == 'uploadsingle') {
					$mformdata = $formdata->mform->get_data();
					$mformdata = file_postupdate_standard_filemanager($mformdata, 'files', $formdata->fileui_options, $this->context, 'mod_tarefalv', 'response', $submission->id);
				}
			}
		}

		return $submission;

	}

	function process_outcomes($userid) {
		global $CFG, $USER;

		if (empty($CFG->enableoutcomes)) {
			return;
		}

		require_once($CFG->libdir.'/gradelib.php');

		if (!$formdata = data_submitted() or !confirm_sesskey()) {
			return;
		}

		$data = array();
		$grading_info = grade_get_grades($this->course->id, 'mod', 'tarefalv', $this->tarefalv->id, $userid);

		if (!empty($grading_info->outcomes)) {
			foreach($grading_info->outcomes as $n=>$old) {
				$name = 'outcome_'.$n;
				if (isset($formdata->{$name}[$userid]) and $old->grades[$userid]->grade != $formdata->{$name}[$userid]) {
					$data[$n] = $formdata->{$name}[$userid];
				}
			}
		}
		if (count($data) > 0) {
			grade_update_outcomes('mod/tarefalv', $this->course->id, 'mod', 'tarefalv', $this->tarefalv->id, $userid, $data);
		}

	}

	/**
	 * Load the submission object for a particular user
	 *
	 * @global object
	 * @global object
	 * @param int $userid int The id of the user whose submission we want or 0 in which case USER->id is used
	 * @param bool $createnew boolean optional Defaults to false. If set to true a new submission object will be created in the database
	 * @param bool $teachermodified student submission set if false
	 * @return object|bool The submission or false (if $createnew is false and there is no existing submission).
	 */
	function get_submission($userid=0, $createnew=false, $teachermodified=false) {
		global $USER, $DB;

		if (empty($userid)) {
			$userid = $USER->id;
		}

		$submission = $DB->get_record('tarefalv_submissions', array('tarefalv'=>$this->tarefalv->id, 'userid'=>$userid));

		if ($submission) {
			return $submission;
		} else if (!$createnew) {
			return false;
		}
		$newsubmission = $this->prepare_new_submission($userid, $teachermodified);
		$DB->insert_record("tarefalv_submissions", $newsubmission);

		return $DB->get_record('tarefalv_submissions', array('tarefalv'=>$this->tarefalv->id, 'userid'=>$userid));
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
		return $submission->timemodified;
	}

	/**
	 * Instantiates a new submission object for a given user
	 *
	 * Sets the tarefalv, userid and times, everything else is set to default values.
	 *
	 * @param int $userid The userid for which we want a submission object
	 * @param bool $teachermodified student submission set if false
	 * @return object The submission
	 */
	function prepare_new_submission($userid, $teachermodified=false) {
		$submission = new stdClass();
		$submission->tarefalv   = $this->tarefalv->id;
		$submission->userid       = $userid;
		$submission->timecreated = time();
		// teachers should not be modifying modified date, except offline tarefalvs
		if ($teachermodified) {
			$submission->timemodified = 0;
		} else {
			$submission->timemodified = $submission->timecreated;
		}
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

	/**
	 * Return all tarefalv submissions by ENROLLED students (even empty)
	 *
	 * @param string $sort optional field names for the ORDER BY in the sql query
	 * @param string $dir optional specifying the sort direction, defaults to DESC
	 * @return array The submission objects indexed by id
	 */
	function get_submissions($sort='', $dir='DESC') {
		return tarefalv_get_all_submissions($this->tarefalv, $sort, $dir);
	}

	/**
	 * Counts all complete (real) tarefalv submissions by enrolled students
	 *
	 * @param  int $groupid (optional) If nonzero then count is restricted to this group
	 * @return int          The number of submissions
	 */
	function count_real_submissions($groupid=0) {
		global $CFG;
		global $DB;

		// Grab the context assocated with our course module
		$context = context_module::instance($this->cm->id);

		// Get ids of users enrolled in the given course.
		list($enroledsql, $params) = get_enrolled_sql($context, 'mod/tarefalv:view', $groupid);
		$params['tarefalvid'] = $this->cm->instance;

		// Get ids of users enrolled in the given course.
		return $DB->count_records_sql("SELECT COUNT('x')
				FROM {tarefalv_submissions} s
				LEFT JOIN {tarefalv} a ON a.id = s.tarefalv
				INNER JOIN ($enroledsql) u ON u.id = s.userid
				WHERE s.tarefalv = :tarefalvid AND
				s.timemodified > 0", $params);
	}

	/**
	 * Alerts teachers by email of new or changed tarefalvs that need grading
	 *
	 * First checks whether the option to email teachers is set for this tarefalv.
	 * Sends an email to ALL teachers in the course (or in the group if using separate groups).
	 * Uses the methods email_teachers_text() and email_teachers_html() to construct the content.
	 *
	 * @global object
	 * @global object
	 * @param $submission object The submission that has changed
	 * @return void
	 */
	function email_teachers($submission) {
		global $CFG, $DB;

		if (empty($this->tarefalv->emailteachers)) {          // No need to do anything
			return;
		}

		$user = $DB->get_record('user', array('id'=>$submission->userid));

		if ($teachers = $this->get_graders($user)) {

			$strtarefalvs = get_string('modulenameplural', 'tarefalv');
			$strtarefalv  = get_string('modulename', 'tarefalv');
			$strsubmitted  = get_string('submitted', 'tarefalv');

			foreach ($teachers as $teacher) {
				$info = new stdClass();
				$info->username = fullname($user, true);
				$info->tarefalv = format_string($this->tarefalv->name,true);
				$info->url = $CFG->wwwroot.'/mod/tarefalv/submissions.php?id='.$this->cm->id;
				$info->timeupdated = userdate($submission->timemodified, '%c', $teacher->timezone);

				$postsubject = $strsubmitted.': '.$info->username.' -> '.$this->tarefalv->name;
				$posttext = $this->email_teachers_text($info);
				$posthtml = ($teacher->mailformat == 1) ? $this->email_teachers_html($info) : '';

				$eventdata = new stdClass();
				$eventdata->modulename       = 'tarefalv';
				$eventdata->userfrom         = $user;
				$eventdata->userto           = $teacher;
				$eventdata->subject          = $postsubject;
				$eventdata->fullmessage      = $posttext;
				$eventdata->fullmessageformat = FORMAT_PLAIN;
				$eventdata->fullmessagehtml  = $posthtml;
				$eventdata->smallmessage     = $postsubject;

				$eventdata->name            = 'tarefalv_updates';
				$eventdata->component       = 'mod_tarefalv';
				$eventdata->notification    = 1;
				$eventdata->contexturl      = $info->url;
				$eventdata->contexturlname  = $info->tarefalv;

				message_send($eventdata);
			}
		}
	}

	/**
	 * Sends a file
	 *
	 * @param string $filearea
	 * @param array $args
	 * @param bool $forcedownload whether or not force download
	 * @param array $options additional options affecting the file serving
	 * @return bool
	 */
	function send_file($filearea, $args, $forcedownload, array $options=array()) {
		debugging('plugin does not implement file sending', DEBUG_DEVELOPER);
		return false;
	}

	/**
	 * Returns a list of teachers that should be grading given submission
	 *
	 * @param object $user
	 * @return array
	 */
	function get_graders($user) {
		global $DB;

		//potential graders
		list($enrolledsql, $params) = get_enrolled_sql($this->context, 'mod/tarefalv:grade', 0, true);
		$sql = "SELECT u.*
		FROM {user} u
		JOIN ($enrolledsql) je ON je.id = u.id";
		$potgraders = $DB->get_records_sql($sql, $params);

		$graders = array();
		if (groups_get_activity_groupmode($this->cm) == SEPARATEGROUPS) {   // Separate groups are being used
			if ($groups = groups_get_all_groups($this->course->id, $user->id)) {  // Try to find all groups
				foreach ($groups as $group) {
					foreach ($potgraders as $t) {
						if ($t->id == $user->id) {
							continue; // do not send self
						}
						if (groups_is_member($group->id, $t->id)) {
							$graders[$t->id] = $t;
						}
					}
				}
			} else {
				// user not in group, try to find graders without group
				foreach ($potgraders as $t) {
					if ($t->id == $user->id) {
						continue; // do not send self
					}
					if (!groups_get_all_groups($this->course->id, $t->id)) { //ugly hack
						$graders[$t->id] = $t;
					}
				}
			}
		} else {
			foreach ($potgraders as $t) {
				if ($t->id == $user->id) {
					continue; // do not send self
				}
				$graders[$t->id] = $t;
			}
		}
		return $graders;
	}

	/**
	 * Creates the text content for emails to teachers
	 *
	 * @param $info object The info used by the 'emailteachermail' language string
	 * @return string
	 */
	function email_teachers_text($info) {
		$posttext  = format_string($this->course->shortname, true, array('context' => $this->coursecontext)).' -> '.
				$this->strtarefalvs.' -> '.
				format_string($this->tarefalv->name, true, array('context' => $this->context))."\n";
		$posttext .= '---------------------------------------------------------------------'."\n";
		$posttext .= get_string("emailteachermail", "tarefalv", $info)."\n";
		$posttext .= "\n---------------------------------------------------------------------\n";
		return $posttext;
	}

	/**
	 * Creates the html content for emails to teachers
	 *
	 * @param $info object The info used by the 'emailteachermailhtml' language string
	 * @return string
	 */
	function email_teachers_html($info) {
		global $CFG;
		$posthtml  = '<p><font face="sans-serif">'.
				'<a href="'.$CFG->wwwroot.'/course/view.php?id='.$this->course->id.'">'.format_string($this->course->shortname, true, array('context' => $this->coursecontext)).'</a> ->'.
				'<a href="'.$CFG->wwwroot.'/mod/tarefalv/index.php?id='.$this->course->id.'">'.$this->strtarefalvs.'</a> ->'.
				'<a href="'.$CFG->wwwroot.'/mod/tarefalv/view.php?id='.$this->cm->id.'">'.format_string($this->tarefalv->name, true, array('context' => $this->context)).'</a></font></p>';
		$posthtml .= '<hr /><font face="sans-serif">';
		$posthtml .= '<p>'.get_string('emailteachermailhtml', 'tarefalv', $info).'</p>';
		$posthtml .= '</font><hr />';
		return $posthtml;
	}

	/**
	 * Produces a list of links to the files uploaded by a user
	 *
	 * @param $userid int optional id of the user. If 0 then $USER->id is used.
	 * @param $return boolean optional defaults to false. If true the list is returned rather than printed
	 * @return string optional
	 */
	function print_user_files($userid=0, $return=false) {
		global $CFG, $USER, $OUTPUT;

		if (!$userid) {
			if (!isloggedin()) {
				return '';
			}
			$userid = $USER->id;
		}

		$output = '';

		$submission = $this->get_submission($userid);
		if (!$submission) {
			return $output;
		}

		$fs = get_file_storage();
		$files = $fs->get_area_files($this->context->id, 'mod_tarefalv', 'submission', $submission->id, "timemodified", false);
		if (!empty($files)) {
			require_once($CFG->dirroot . '/mod/tarefalv/locallib.php');
			if ($CFG->enableportfolios) {
				require_once($CFG->libdir.'/portfoliolib.php');
				$button = new portfolio_add_button();
			}
			foreach ($files as $file) {
				$filename = $file->get_filename();
				$mimetype = $file->get_mimetype();
				$path = file_encode_url($CFG->wwwroot.'/pluginfile.php', '/'.$this->context->id.'/mod_tarefalv/submission/'.$submission->id.'/'.$filename);
				$output .= '<a href="'.$path.'" >'.$OUTPUT->pix_icon(file_file_icon($file), get_mimetype_description($file), 'moodle', array('class' => 'icon')).s($filename).'</a>';
				if ($CFG->enableportfolios && $this->portfolio_exportable() && has_capability('mod/tarefalv:exportownsubmission', $this->context)) {
					$button->set_callback_options('tarefalv_portfolio_caller',
							array('id' => $this->cm->id, 'submissionid' => $submission->id, 'fileid' => $file->get_id()),
							'mod_tarefalv');
					$button->set_format_by_file($file);
					$output .= $button->to_html(PORTFOLIO_ADD_ICON_LINK);
				}

				if ($CFG->enableplagiarism) {
					require_once($CFG->libdir.'/plagiarismlib.php');
					$output .= plagiarism_get_links(array('userid'=>$userid, 'file'=>$file, 'cmid'=>$this->cm->id, 'course'=>$this->course, 'tarefalv'=>$this->tarefalv));
					$output .= '<br />';
				}
			}
			if ($CFG->enableportfolios && count($files) > 1  && $this->portfolio_exportable() && has_capability('mod/tarefalv:exportownsubmission', $this->context)) {
				$button->set_callback_options('tarefalv_portfolio_caller',
						array('id' => $this->cm->id, 'submissionid' => $submission->id),
						'mod_tarefalv');
				$output .= '<br />'  . $button->to_html(PORTFOLIO_ADD_TEXT_LINK);
			}
		}

		$output = '<div class="files">'.$output.'</div>';

		if ($return) {
			return $output;
		}
		echo $output;
	}

	/**
	 * Count the files uploaded by a given user
	 *
	 * @param $itemid int The submission's id as the file's itemid.
	 * @return int
	 */
	function count_user_files($itemid) {
		$fs = get_file_storage();
		$files = $fs->get_area_files($this->context->id, 'mod_tarefalv', 'submission', $itemid, "id", false);
		return count($files);
	}

	/**
	 * Returns true if the student is allowed to submit
	 *
	 * Checks that the tarefalv has started and, if the option to prevent late
	 * submissions is set, also checks that the tarefalv has not yet closed.
	 * @return boolean
	 */
	function isopen() {
		$time = time();
		if ($this->tarefalv->preventlate && $this->tarefalv->timedue) {
			return ($this->tarefalv->timeavailable <= $time && $time <= $this->tarefalv->timedue);
		} else {
			return ($this->tarefalv->timeavailable <= $time);
		}
	}


	/**
	 * Return true if is set description is hidden till available date
	 *
	 * This is needed by calendar so that hidden descriptions do not
	 * come up in upcoming events.
	 *
	 * Check that description is hidden till available date
	 * By default return false
	 * Assignments types should implement this method if needed
	 * @return boolen
	 */
	function description_is_hidden() {
		return false;
	}

	/**
	 * Return an outline of the user's interaction with the tarefalv
	 *
	 * The default method prints the grade and timemodified
	 * @param $grade object
	 * @return object with properties ->info and ->time
	 */
	function user_outline($grade) {

		$result = new stdClass();
		$result->info = get_string('grade').': '.$grade->str_long_grade;
		$result->time = $grade->dategraded;
		return $result;
	}

	/**
	 * Print complete information about the user's interaction with the tarefalv
	 *
	 * @param $user object
	 */
	function user_complete($user, $grade=null) {
		global $OUTPUT;

		if ($submission = $this->get_submission($user->id)) {

			$fs = get_file_storage();

			if ($files = $fs->get_area_files($this->context->id, 'mod_tarefalv', 'submission', $submission->id, "timemodified", false)) {
				$countfiles = count($files)." ".get_string("uploadedfiles", "tarefalv");
				foreach ($files as $file) {
					$countfiles .= "; ".$file->get_filename();
				}
			}

			echo $OUTPUT->box_start();
			echo get_string("lastmodified").": ";
			echo userdate($submission->timemodified);
			echo $this->display_lateness($submission->timemodified);

			$this->print_user_files($user->id);

			echo '<br />';

			$this->view_feedback($submission);

			echo $OUTPUT->box_end();

		} else {
			if ($grade) {
				echo $OUTPUT->container(get_string('grade').': '.$grade->str_long_grade);
				if ($grade->str_feedback) {
					echo $OUTPUT->container(get_string('feedback').': '.$grade->str_feedback);
				}
			}
			print_string("notsubmittedyet", "tarefalv");
		}
	}

	/**
	 * Return a string indicating how late a submission is
	 *
	 * @param $timesubmitted int
	 * @return string
	 */
	function display_lateness($timesubmitted) {
		return tarefalv_display_lateness($timesubmitted, $this->tarefalv->timedue);
	}

	/**
	 * Empty method stub for all delete actions.
	 */
	function delete() {
		//nothing by default
		redirect('view.php?id='.$this->cm->id);
	}

	/**
	 * Empty custom feedback grading form.
	 */
	function custom_feedbackform($submission, $return=false) {
		//nothing by default
		return '';
	}

	/**
	 * Add a get_coursemodule_info function in case any tarefalv type wants to add 'extra' information
	 * for the course (see resource).
	 *
	 * Given a course_module object, this function returns any "extra" information that may be needed
	 * when printing this activity in a course listing.  See get_array_of_activities() in course/lib.php.
	 *
	 * @param $coursemodule object The coursemodule object (record).
	 * @return cached_cm_info Object used to customise appearance on course page
	 */
	function get_coursemodule_info($coursemodule) {
		return null;
	}

	/**
	 * Plugin cron method - do not use $this here, create new tarefalv instances if needed.
	 * @return void
	 */
	function cron() {
		//no plugin cron by default - override if needed
	}

	/**
	 * Reset all submissions
	 */
	function reset_userdata($data) {
		global $CFG, $DB;

		if (!$DB->count_records('tarefalv', array('course'=>$data->courseid, 'tarefalvtype'=>$this->type))) {
			return array(); // no tarefalvs of this type present
		}

		$componentstr = get_string('modulenameplural', 'tarefalv');
		$status = array();

		$typestr = get_string('type'.$this->type, 'tarefalv');
		// ugly hack to support pluggable tarefalv type titles...
		if($typestr === '[[type'.$this->type.']]'){
			$typestr = get_string('type'.$this->type, 'tarefalv_'.$this->type);
		}

		if (!empty($data->reset_tarefalv_submissions)) {
			$tarefalvssql = "SELECT a.id
					FROM {tarefalv} a
					WHERE a.course=? AND a.tarefalvtype=?";
			$params = array($data->courseid, $this->type);

			// now get rid of all submissions and responses
			$fs = get_file_storage();
			if ($tarefalvs = $DB->get_records_sql($tarefalvssql, $params)) {
				foreach ($tarefalvs as $tarefalvid=>$unused) {
					if (!$cm = get_coursemodule_from_instance('tarefalv', $tarefalvid)) {
						continue;
					}
					$context = context_module::instance($cm->id);
					$fs->delete_area_files($context->id, 'mod_tarefalv', 'submission');
					$fs->delete_area_files($context->id, 'mod_tarefalv', 'response');
				}
			}

			$DB->delete_records_select('tarefalv_submissions', "tarefalv IN ($tarefalvssql)", $params);

			$status[] = array('component'=>$componentstr, 'item'=>get_string('deleteallsubmissions','tarefalv').': '.$typestr, 'error'=>false);

			if (empty($data->reset_gradebook_grades)) {
				// remove all grades from gradebook
				tarefalv_reset_gradebook($data->courseid, $this->type);
			}
		}

		/// updating dates - shift may be negative too
		if ($data->timeshift) {
			shift_course_mod_dates('tarefalv', array('timedue', 'timeavailable'), $data->timeshift, $data->courseid);
			$status[] = array('component'=>$componentstr, 'item'=>get_string('datechanged').': '.$typestr, 'error'=>false);
		}

		return $status;
	}


	function portfolio_exportable() {
		return false;
	}

	/**
	 * base implementation for backing up subtype specific information
	 * for one single module
	 *
	 * @param filehandle $bf file handle for xml file to write to
	 * @param mixed $preferences the complete backup preference object
	 *
	 * @return boolean
	 *
	 * @static
	 */
	static function backup_one_mod($bf, $preferences, $tarefalv) {
		return true;
	}

	/**
	 * base implementation for backing up subtype specific information
	 * for one single submission
	 *
	 * @param filehandle $bf file handle for xml file to write to
	 * @param mixed $preferences the complete backup preference object
	 * @param object $submission the tarefalv submission db record
	 *
	 * @return boolean
	 *
	 * @static
	 */
	static function backup_one_submission($bf, $preferences, $tarefalv, $submission) {
		return true;
	}

	/**
	 * base implementation for restoring subtype specific information
	 * for one single module
	 *
	 * @param array  $info the array representing the xml
	 * @param object $restore the restore preferences
	 *
	 * @return boolean
	 *
	 * @static
	 */
	static function restore_one_mod($info, $restore, $tarefalv) {
		return true;
	}

	/**
	 * base implementation for restoring subtype specific information
	 * for one single submission
	 *
	 * @param object $submission the newly created submission
	 * @param array  $info the array representing the xml
	 * @param object $restore the restore preferences
	 *
	 * @return boolean
	 *
	 * @static
	 */
	static function restore_one_submission($info, $restore, $tarefalv, $submission) {
		return true;
	}

} ////// End of the tarefalv_base class


class tarefalv_grading_form extends moodleform {
	/** @var stores the advaned grading instance (if used in grading) */
	private $advancegradinginstance;

	function definition() {
		global $OUTPUT;
		$mform =& $this->_form;

		if (isset($this->_customdata->advancedgradinginstance)) {
			$this->use_advanced_grading($this->_customdata->advancedgradinginstance);
		}

		$formattr = $mform->getAttributes();
		$formattr['id'] = 'submitform';
		$mform->setAttributes($formattr);
		// hidden params
		$mform->addElement('hidden', 'offset', ($this->_customdata->offset+1));
		$mform->setType('offset', PARAM_INT);
		$mform->addElement('hidden', 'userid', $this->_customdata->userid);
		$mform->setType('userid', PARAM_INT);
		$mform->addElement('hidden', 'nextid', $this->_customdata->nextid);
		$mform->setType('nextid', PARAM_INT);
		$mform->addElement('hidden', 'id', $this->_customdata->cm->id);
		$mform->setType('id', PARAM_INT);
		$mform->addElement('hidden', 'sesskey', sesskey());
		$mform->setType('sesskey', PARAM_ALPHANUM);
		$mform->addElement('hidden', 'mode', 'grade');
		$mform->setType('mode', PARAM_TEXT);
		$mform->addElement('hidden', 'menuindex', "0");
		$mform->setType('menuindex', PARAM_INT);
		$mform->addElement('hidden', 'saveuserid', "-1");
		$mform->setType('saveuserid', PARAM_INT);
		$mform->addElement('hidden', 'filter', "0");
		$mform->setType('filter', PARAM_INT);

		$mform->addElement('static', 'picture', $OUTPUT->user_picture($this->_customdata->user),
				fullname($this->_customdata->user, true) . '<br/>' .
				userdate($this->_customdata->submission->timemodified) .
				$this->_customdata->lateness );

		$this->add_submission_content();
		// $this->add_grades_section(); @lvs descomentado

		/** @lvs exibição das carinhas de avaliação lv */
		$submission = clone $this->_customdata->submission;
		$submission->id = isset($submission->id) ? $submission->id : 0;
		
		$this->_customdata->submission->itemlv = new Item('tarefalv', 'submission', $submission);

		$gerenciadorNotas = NotasLvFactory::criarGerenciador('moodle2');
		$gerenciadorNotas->setModulo( new Tarefalv($submission->tarefalv) );
		$avaliacao = $gerenciadorNotas->getAvaliacao($this->_customdata->submission->itemlv);
		$avaliacao->setCarinhasEstendido(true);

		if ($avaliacao->getNota() == -1) {
			$avaliacao->setNota(null);
		}

		$outputlv = $gerenciadorNotas->avaliacaoAtual($this->_customdata->submission->itemlv);
		$outputlv .= $gerenciadorNotas->avaliadoPor($this->_customdata->submission->itemlv);

		if ($gerenciadorNotas->podeAvaliar($this->_customdata->submission->itemlv)) {
			$carinhaslvs = new Carinhas();
			$outputlv .= $carinhaslvs->exibirHtmlEstendido( $this->_customdata->submission->itemlv->getAvaliacao()->getNota() );
		}

		$mform->addElement('header', 'Grades', get_string('grades', 'grades'));
        $mform->setExpanded('Grades');
		$mform->addElement('html', $outputlv);
		/** fim lvs */

		$this->add_feedback_section();

		if ($this->_customdata->submission->timemarked) {
			$datestring = userdate($this->_customdata->submission->timemarked)."&nbsp; (".format_time(time() - $this->_customdata->submission->timemarked).")";
			$mform->addElement('header', 'Last Grade', get_string('lastgrade', 'tarefalv'));
			$mform->addElement('static', 'picture', $OUTPUT->user_picture($this->_customdata->teacher) ,
					fullname($this->_customdata->teacher,true).
					'<br/>'.$datestring);
		}
		// buttons
		$this->add_action_buttons();

	}

	/**
	 * Gets or sets the instance for advanced grading
	 *
	 * @param gradingform_instance $gradinginstance
	 */
	public function use_advanced_grading($gradinginstance = false) {
		if ($gradinginstance !== false) {
			$this->advancegradinginstance = $gradinginstance;
		}
		return $this->advancegradinginstance;
	}

	/**
	 * Add the grades configuration section to the tarefalv configuration form
	 */
	function add_grades_section() {
		global $CFG;
		$mform =& $this->_form;
		$attributes = array();
		if ($this->_customdata->gradingdisabled) {
			$attributes['disabled'] ='disabled';
		}

		$mform->addElement('header', 'Grades', get_string('grades', 'grades'));

		$grademenu = make_grades_menu($this->_customdata->tarefalv->grade);
		if ($gradinginstance = $this->use_advanced_grading()) {
			$gradinginstance->get_controller()->set_grade_range($grademenu);
			$gradingelement = $mform->addElement('grading', 'advancedgrading', get_string('grade').':', array('gradinginstance' => $gradinginstance));
			if ($this->_customdata->gradingdisabled) {
				$gradingelement->freeze();
			} else {
				$mform->addElement('hidden', 'advancedgradinginstanceid', $gradinginstance->get_id());
			}
		} else {
			// use simple direct grading
			$grademenu['-1'] = get_string('nograde');

			$mform->addElement('select', 'xgrade', get_string('grade').':', $grademenu, $attributes);
			$mform->setDefault('xgrade', $this->_customdata->submission->grade ); //@fixme some bug when element called 'grade' makes it break
			$mform->setType('xgrade', PARAM_INT);
		}

		if (!empty($this->_customdata->enableoutcomes)) {
			foreach($this->_customdata->grading_info->outcomes as $n=>$outcome) {
				$options = make_grades_menu(-$outcome->scaleid);
				if ($outcome->grades[$this->_customdata->submission->userid]->locked) {
					$options[0] = get_string('nooutcome', 'grades');
					$mform->addElement('static', 'outcome_'.$n.'['.$this->_customdata->userid.']', $outcome->name.':',
							$options[$outcome->grades[$this->_customdata->submission->userid]->grade]);
				} else {
					$options[''] = get_string('nooutcome', 'grades');
					$attributes = array('id' => 'menuoutcome_'.$n );
					$mform->addElement('select', 'outcome_'.$n.'['.$this->_customdata->userid.']', $outcome->name.':', $options, $attributes );
					$mform->setType('outcome_'.$n.'['.$this->_customdata->userid.']', PARAM_INT);
					$mform->setDefault('outcome_'.$n.'['.$this->_customdata->userid.']', $outcome->grades[$this->_customdata->submission->userid]->grade );
				}
			}
		}
		$course_context = context_module::instance($this->_customdata->cm->id);
		if (has_capability('gradereport/grader:view', $course_context) && has_capability('moodle/grade:viewall', $course_context)) {
			$grade = '<a href="'.$CFG->wwwroot.'/grade/report/grader/index.php?id='. $this->_customdata->courseid .'" >'.
					$this->_customdata->grading_info->items[0]->grades[$this->_customdata->userid]->str_grade . '</a>';
		}else{
			$grade = $this->_customdata->grading_info->items[0]->grades[$this->_customdata->userid]->str_grade;
		}
		$mform->addElement('static', 'finalgrade', get_string('currentgrade', 'tarefalv').':' ,$grade);
		$mform->setType('finalgrade', PARAM_INT);
	}

	/**
	 *
	 * @global core_renderer $OUTPUT
	 */
	function add_feedback_section() {
		global $OUTPUT;
		$mform =& $this->_form;
		$mform->addElement('header', 'Feed Back', get_string('feedback', 'grades'));

		if ($this->_customdata->gradingdisabled) {
			$mform->addElement('static', 'disabledfeedback', $this->_customdata->grading_info->items[0]->grades[$this->_customdata->userid]->str_feedback );
		} else {
			// visible elements

			$mform->addElement('editor', 'submissioncomment_editor', get_string('feedback', 'tarefalv').':', null, $this->get_editor_options() );
			$mform->setType('submissioncomment_editor', PARAM_RAW); // to be cleaned before display
			$mform->setDefault('submissioncomment_editor', $this->_customdata->submission->submissioncomment);
			//$mform->addRule('submissioncomment', get_string('required'), 'required', null, 'client');
			switch ($this->_customdata->tarefalv->tarefalvtype) {
				case 'upload' :
				case 'uploadsingle' :
					$mform->addElement('filemanager', 'files_filemanager', get_string('responsefiles', 'tarefalv'). ':', null, $this->_customdata->fileui_options);
					break;
				default :
					break;
			}
			$mform->addElement('hidden', 'mailinfo_h', "0");
			$mform->setType('mailinfo_h', PARAM_INT);
			$mform->addElement('checkbox', 'mailinfo',get_string('enablenotification','tarefalv').
					$OUTPUT->help_icon('enablenotification', 'tarefalv') .':' );
			$mform->setType('mailinfo', PARAM_INT);
		}
	}

	function add_action_buttons($cancel = true, $submitlabel = NULL) {
		$mform =& $this->_form;
		//if there are more to be graded.
		if ($this->_customdata->nextid>0) {
			$buttonarray=array();
			$buttonarray[] = &$mform->createElement('submit', 'submitbutton', get_string('savechanges'));
			//@todo: fix accessibility: javascript dependency not necessary
			$buttonarray[] = &$mform->createElement('submit', 'saveandnext', get_string('saveandnext'));
			$buttonarray[] = &$mform->createElement('submit', 'next', get_string('next'));
			$buttonarray[] = &$mform->createElement('cancel');
		} else {
			$buttonarray=array();
			$buttonarray[] = &$mform->createElement('submit', 'submitbutton', get_string('savechanges'));
			$buttonarray[] = &$mform->createElement('cancel');
		}
		$mform->addGroup($buttonarray, 'grading_buttonar', '', array(' '), false);
		$mform->closeHeaderBefore('grading_buttonar');
		$mform->setType('grading_buttonar', PARAM_RAW);
	}

	function add_submission_content() {
		$mform =& $this->_form;
		$mform->addElement('header', 'Submission', get_string('submission', 'tarefalv'));
		$mform->addElement('static', '', '' , $this->_customdata->submission_content );
	}

	protected function get_editor_options() {
		$editoroptions = array();
		$editoroptions['component'] = 'mod_tarefalv';
		$editoroptions['filearea'] = 'feedback';
		$editoroptions['noclean'] = false;
		$editoroptions['maxfiles'] = 0; //TODO: no files for now, we need to first implement tarefalv_feedback area, integration with gradebook, files support in quickgrading, etc. (skodak)
		$editoroptions['maxbytes'] = $this->_customdata->maxbytes;
		$editoroptions['context'] = $this->_customdata->context;
		return $editoroptions;
	}

	public function set_data($data) {
		$editoroptions = $this->get_editor_options();
		if (!isset($data->text)) {
			$data->text = '';
		}
		if (!isset($data->format)) {
			$data->textformat = FORMAT_HTML;
		} else {
			$data->textformat = $data->format;
		}

		if (!empty($this->_customdata->submission->id)) {
			$itemid = $this->_customdata->submission->id;
		} else {
			$itemid = null;
		}

		switch ($this->_customdata->tarefalv->tarefalvtype) {
			case 'upload' :
			case 'uploadsingle' :
				$data = file_prepare_standard_filemanager($data, 'files', $editoroptions, $this->_customdata->context, 'mod_tarefalv', 'response', $itemid);
				break;
			default :
				break;
		}

		$data = file_prepare_standard_editor($data, 'submissioncomment', $editoroptions, $this->_customdata->context, $editoroptions['component'], $editoroptions['filearea'], $itemid);
		return parent::set_data($data);
	}

	public function get_data() {
		$data = parent::get_data();

		if (!empty($this->_customdata->submission->id)) {
			$itemid = $this->_customdata->submission->id;
		} else {
			$itemid = null; //TODO: this is wrong, itemid MUST be known when saving files!! (skodak)
		}

		if ($data) {
			$editoroptions = $this->get_editor_options();
			switch ($this->_customdata->tarefalv->tarefalvtype) {
				case 'upload' :
				case 'uploadsingle' :
					$data = file_postupdate_standard_filemanager($data, 'files', $editoroptions, $this->_customdata->context, 'mod_tarefalv', 'response', $itemid);
					break;
				default :
					break;
			}
			$data = file_postupdate_standard_editor($data, 'submissioncomment', $editoroptions, $this->_customdata->context, $editoroptions['component'], $editoroptions['filearea'], $itemid);
		}

		if ($this->use_advanced_grading() && !isset($data->advancedgrading)) {
			$data->advancedgrading = null;
		}

		return $data;
	}
}

/// OTHER STANDARD FUNCTIONS ////////////////////////////////////////////////////////

/**
 * Deletes an tarefalv instance
 *
 * This is done by calling the delete_instance() method of the tarefalv type class
 */
function tarefalv_delete_instance($id){
	global $CFG, $DB;

	if (! $tarefalv = $DB->get_record('tarefalv', array('id'=>$id))) {
		return false;
	}

	// fall back to base class if plugin missing
	$classfile = "$CFG->dirroot/mod/tarefalv/type/$tarefalv->tarefalvtype/tarefalv.class.php";
	if (file_exists($classfile)) {
		require_once($classfile);
		$tarefalvclass = "tarefalv_$tarefalv->tarefalvtype";

	} else {
		debugging("Missing tarefalv plug-in: {$tarefalv->tarefalvtype}. Using base class for deleting instead.");
		$tarefalvclass = "tarefalv_base";
	}

	$ass = new $tarefalvclass();
	return $ass->delete_instance($tarefalv);
}


/**
 * Updates an tarefalv instance
 *
 * This is done by calling the update_instance() method of the tarefalv type class
 */
function tarefalv_update_instance($tarefalv){
	global $CFG;

	$tarefalv->tarefalvtype = clean_param($tarefalv->tarefalvtype, PARAM_PLUGIN);

	require_once("$CFG->dirroot/mod/tarefalv/type/$tarefalv->tarefalvtype/tarefalv.class.php");
	$tarefalvclass = "tarefalv_$tarefalv->tarefalvtype";
	$ass = new $tarefalvclass();
	return $ass->update_instance($tarefalv);
}


/**
 * Adds an tarefalv instance
 *
 * This is done by calling the add_instance() method of the tarefalv type class
 *
 * @param stdClass $tarefalv
 * @param mod_tarefalv_mod_form $mform
 * @return int intance id
 */
function tarefalv_add_instance($tarefalv, $mform = null) {
	global $CFG;

	$tarefalv->tarefalvtype = clean_param($tarefalv->tarefalvtype, PARAM_PLUGIN);

	require_once("$CFG->dirroot/mod/tarefalv/type/$tarefalv->tarefalvtype/tarefalv.class.php");
	$tarefalvclass = "tarefalv_$tarefalv->tarefalvtype";
	$ass = new $tarefalvclass();
	return $ass->add_instance($tarefalv);
}


/**
 * Returns an outline of a user interaction with an tarefalv
 *
 * This is done by calling the user_outline() method of the tarefalv type class
 */
function tarefalv_user_outline($course, $user, $mod, $tarefalv) {
	global $CFG;

	require_once("$CFG->libdir/gradelib.php");
	require_once("$CFG->dirroot/mod/tarefalv/type/$tarefalv->tarefalvtype/tarefalv.class.php");
	$tarefalvclass = "tarefalv_$tarefalv->tarefalvtype";
	$ass = new $tarefalvclass($mod->id, $tarefalv, $mod, $course);
	$grades = grade_get_grades($course->id, 'mod', 'tarefalv', $tarefalv->id, $user->id);
	if (!empty($grades->items[0]->grades)) {
		return $ass->user_outline(reset($grades->items[0]->grades));
	} else {
		return null;
	}
}

/**
 * Prints the complete info about a user's interaction with an tarefalv
 *
 * This is done by calling the user_complete() method of the tarefalv type class
 */
function tarefalv_user_complete($course, $user, $mod, $tarefalv) {
	global $CFG;

	require_once("$CFG->libdir/gradelib.php");
	require_once("$CFG->dirroot/mod/tarefalv/type/$tarefalv->tarefalvtype/tarefalv.class.php");
	$tarefalvclass = "tarefalv_$tarefalv->tarefalvtype";
	$ass = new $tarefalvclass($mod->id, $tarefalv, $mod, $course);
	$grades = grade_get_grades($course->id, 'mod', 'tarefalv', $tarefalv->id, $user->id);
	if (empty($grades->items[0]->grades)) {
		$grade = false;
	} else {
		$grade = reset($grades->items[0]->grades);
	}
	return $ass->user_complete($user, $grade);
}

/**
 * Function to be run periodically according to the moodle cron
 *
 * Finds all tarefalv notifications that have yet to be mailed out, and mails them
 */
function tarefalv_cron () {
	global $CFG, $USER, $DB;

	/// first execute all crons in plugins
	if ($plugins = get_plugin_list('tarefalv')) {
		foreach ($plugins as $plugin=>$dir) {
			require_once("$dir/tarefalv.class.php");
			$tarefalvclass = "tarefalv_$plugin";
			$ass = new $tarefalvclass();
			$ass->cron();
		}
	}

	/// Notices older than 1 day will not be mailed.  This is to avoid the problem where
	/// cron has not been running for a long time, and then suddenly people are flooded
	/// with mail from the past few weeks or months

	$timenow   = time();
	$endtime   = $timenow - $CFG->maxeditingtime;
	$starttime = $endtime - 24 * 3600;   /// One day earlier

	if ($submissions = tarefalv_get_unmailed_submissions($starttime, $endtime)) {

		$realuser = clone($USER);

		foreach ($submissions as $key => $submission) {
			$DB->set_field("tarefalv_submissions", "mailed", "1", array("id"=>$submission->id));
		}

		$timenow = time();

		foreach ($submissions as $submission) {

			echo "Processing tarefalv submission $submission->id\n";

			if (! $user = $DB->get_record("user", array("id"=>$submission->userid))) {
				echo "Could not find user $user->id\n";
				continue;
			}

			if (! $course = $DB->get_record("course", array("id"=>$submission->course))) {
				echo "Could not find course $submission->course\n";
				continue;
			}

			/// Override the language and timezone of the "current" user, so that
			/// mail is customised for the receiver.
			cron_setup_user($user, $course);

			$coursecontext = context_course::instance($submission->course);
			$courseshortname = format_string($course->shortname, true, array('context' => $coursecontext));
			if (!is_enrolled($coursecontext, $user->id)) {
				echo fullname($user)." not an active participant in " . $courseshortname . "\n";
				continue;
			}

			if (! $teacher = $DB->get_record("user", array("id"=>$submission->teacher))) {
				echo "Could not find teacher $submission->teacher\n";
				continue;
			}

			if (! $mod = get_coursemodule_from_instance("tarefalv", $submission->tarefalv, $course->id)) {
				echo "Could not find course module for tarefalv id $submission->tarefalv\n";
				continue;
			}

			if (! $mod->visible) {    /// Hold mail notification for hidden tarefalvs until later
				continue;
			}

			$strtarefalvs = get_string("modulenameplural", "tarefalv");
			$strtarefalv  = get_string("modulename", "tarefalv");

			$tarefalvinfo = new stdClass();
			$tarefalvinfo->teacher = fullname($teacher);
			$tarefalvinfo->tarefalv = format_string($submission->name,true);
			$tarefalvinfo->url = "$CFG->wwwroot/mod/tarefalv/view.php?id=$mod->id";

			$postsubject = "$courseshortname: $strtarefalvs: ".format_string($submission->name,true);
			$posttext  = "$courseshortname -> $strtarefalvs -> ".format_string($submission->name,true)."\n";
			$posttext .= "---------------------------------------------------------------------\n";
			$posttext .= get_string("tarefalvmail", "tarefalv", $tarefalvinfo)."\n";
			$posttext .= "---------------------------------------------------------------------\n";

			if ($user->mailformat == 1) {  // HTML
				$posthtml = "<p><font face=\"sans-serif\">".
						"<a href=\"$CFG->wwwroot/course/view.php?id=$course->id\">$courseshortname</a> ->".
						"<a href=\"$CFG->wwwroot/mod/tarefalv/index.php?id=$course->id\">$strtarefalvs</a> ->".
						"<a href=\"$CFG->wwwroot/mod/tarefalv/view.php?id=$mod->id\">".format_string($submission->name,true)."</a></font></p>";
				$posthtml .= "<hr /><font face=\"sans-serif\">";
				$posthtml .= "<p>".get_string("tarefalvmailhtml", "tarefalv", $tarefalvinfo)."</p>";
				$posthtml .= "</font><hr />";
			} else {
				$posthtml = "";
			}

			$eventdata = new stdClass();
			$eventdata->modulename       = 'tarefalv';
			$eventdata->userfrom         = $teacher;
			$eventdata->userto           = $user;
			$eventdata->subject          = $postsubject;
			$eventdata->fullmessage      = $posttext;
			$eventdata->fullmessageformat = FORMAT_PLAIN;
			$eventdata->fullmessagehtml  = $posthtml;
			$eventdata->smallmessage     = get_string('tarefalvmailsmall', 'tarefalv', $tarefalvinfo);

			$eventdata->name            = 'tarefalv_updates';
			$eventdata->component       = 'mod_tarefalv';
			$eventdata->notification    = 1;
			$eventdata->contexturl      = $tarefalvinfo->url;
			$eventdata->contexturlname  = $tarefalvinfo->tarefalv;

			message_send($eventdata);
		}

		cron_setup_user();
	}

	return true;
}

/**
 * Return grade for given user or all users.
 *
 * @param stdClass $tarefalv An tarefalv instance
 * @param int $userid Optional user id, 0 means all users
 * @return array An array of grades, false if none
 */
function tarefalv_get_user_grades($tarefalv, $userid=0) {
	global $CFG, $DB;

	if ($userid) {
		$user = "AND u.id = :userid";
		$params = array('userid'=>$userid);
	} else {
		$user = "";
	}
	$params['aid'] = $tarefalv->id;

	$sql = "SELECT u.id, u.id AS userid, s.grade AS rawgrade, s.submissioncomment AS feedback, s.format AS feedbackformat,
	s.teacher AS usermodified, s.timemarked AS dategraded, s.timemodified AS datesubmitted
	FROM {user} u, {tarefalv_submissions} s
	WHERE u.id = s.userid AND s.tarefalv = :aid
	$user";

	return $DB->get_records_sql($sql, $params);
}

/**
 * Update activity grades
 *
 * @category grade
 * @param stdClass $tarefalv Assignment instance
 * @param int $userid specific user only, 0 means all
 * @param bool $nullifnone Not used
 */
function tarefalv_update_grades($tarefalv, $userid=0, $nullifnone=true) {
	global $CFG, $DB;
	require_once($CFG->libdir.'/gradelib.php');

	if ($tarefalv->grade == 0) {
		tarefalv_grade_item_update($tarefalv);

	} else if ($grades = tarefalv_get_user_grades($tarefalv, $userid)) {
		foreach($grades as $k=>$v) {
			if ($v->rawgrade == -1) {
				$grades[$k]->rawgrade = null;
			}
		}
		tarefalv_grade_item_update($tarefalv, $grades);

	} else {
		tarefalv_grade_item_update($tarefalv);
	}
}

/**
 * Update all grades in gradebook.
 */
function tarefalv_upgrade_grades() {
	global $DB;

	$sql = "SELECT COUNT('x')
			FROM {tarefalv} a, {course_modules} cm, {modules} m
			WHERE m.name='tarefalv' AND m.id=cm.module AND cm.instance=a.id";
	$count = $DB->count_records_sql($sql);

	$sql = "SELECT a.*, cm.idnumber AS cmidnumber, a.course AS courseid
			FROM {tarefalv} a, {course_modules} cm, {modules} m
			WHERE m.name='tarefalv' AND m.id=cm.module AND cm.instance=a.id";
	$rs = $DB->get_recordset_sql($sql);
	if ($rs->valid()) {
		// too much debug output
		$pbar = new progress_bar('tarefalvupgradegrades', 500, true);
		$i=0;
		foreach ($rs as $tarefalv) {
			$i++;
			upgrade_set_timeout(60*5); // set up timeout, may also abort execution
			tarefalv_update_grades($tarefalv);
			$pbar->update($i, $count, "Updating Assignment grades ($i/$count).");
		}
		upgrade_set_timeout(); // reset to default timeout
	}
	$rs->close();
}

/**
 * Create grade item for given tarefalv
 *
 * @category grade
 * @param stdClass $tarefalv An tarefalv instance with extra cmidnumber property
 * @param mixed $grades Optional array/object of grade(s); 'reset' means reset grades in gradebook
 * @return int 0 if ok, error code otherwise
 */
function tarefalv_grade_item_update($tarefalv, $grades=NULL) {
	global $CFG;
	require_once($CFG->libdir.'/gradelib.php');

	if (!isset($tarefalv->courseid)) {
		$tarefalv->courseid = $tarefalv->course;
	}

	$params = array('itemname'=>$tarefalv->name, 'idnumber'=>$tarefalv->cmidnumber);

	if ($tarefalv->grade > 0) {
		$params['gradetype'] = GRADE_TYPE_VALUE;
		$params['grademax']  = $tarefalv->grade;
		$params['grademin']  = 0;

	} else if ($tarefalv->grade < 0) {
		$params['gradetype'] = GRADE_TYPE_SCALE;
		$params['scaleid']   = -$tarefalv->grade;

	} else {
		$params['gradetype'] = GRADE_TYPE_TEXT; // allow text comments only
	}

	if ($grades  === 'reset') {
		$params['reset'] = true;
		$grades = NULL;
	}

	return grade_update('mod/tarefalv', $tarefalv->courseid, 'mod', 'tarefalv', $tarefalv->id, 0, $grades, $params);
}

/**
 * Delete grade item for given tarefalv
 *
 * @category grade
 * @param object $tarefalv object
 * @return object tarefalv
 */
function tarefalv_grade_item_delete($tarefalv) {
	global $CFG;
	require_once($CFG->libdir.'/gradelib.php');

	if (!isset($tarefalv->courseid)) {
		$tarefalv->courseid = $tarefalv->course;
	}

	return grade_update('mod/tarefalv', $tarefalv->courseid, 'mod', 'tarefalv', $tarefalv->id, 0, NULL, array('deleted'=>1));
}


/**
 * Serves tarefalv submissions and other files.
 *
 * @package  mod_tarefalv
 * @category files
 * @param stdClass $course course object
 * @param stdClass $cm course module object
 * @param stdClass $context context object
 * @param string $filearea file area
 * @param array $args extra arguments
 * @param bool $forcedownload whether or not force download
 * @param array $options additional options affecting the file serving
 * @return bool false if file not found, does not return if found - just send the file
 */
function tarefalv_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options=array()) {
	global $CFG, $DB;

	if ($context->contextlevel != CONTEXT_MODULE) {
		return false;
	}

	require_login($course, false, $cm);

	if (!$tarefalv = $DB->get_record('tarefalv', array('id'=>$cm->instance))) {
		return false;
	}

	require_once($CFG->dirroot.'/mod/tarefalv/type/'.$tarefalv->tarefalvtype.'/tarefalv.class.php');
	$tarefalvclass = 'tarefalv_'.$tarefalv->tarefalvtype;
	$tarefalvinstance = new $tarefalvclass($cm->id, $tarefalv, $cm, $course);

	return $tarefalvinstance->send_file($filearea, $args, $forcedownload, $options);
}
/**
 * Checks if a scale is being used by an tarefalv
 *
 * This is used by the backup code to decide whether to back up a scale
 * @param $tarefalvid int
 * @param $scaleid int
 * @return boolean True if the scale is used by the tarefalv
 */
function tarefalv_scale_used($tarefalvid, $scaleid) {
	global $DB;

	$return = false;

	$rec = $DB->get_record('tarefalv', array('id'=>$tarefalvid,'grade'=>-$scaleid));

	if (!empty($rec) && !empty($scaleid)) {
		$return = true;
	}

	return $return;
}

/**
 * Checks if scale is being used by any instance of tarefalv
 *
 * This is used to find out if scale used anywhere
 * @param $scaleid int
 * @return boolean True if the scale is used by any tarefalv
 */
function tarefalv_scale_used_anywhere($scaleid) {
	global $DB;

	if ($scaleid and $DB->record_exists('tarefalv', array('grade'=>-$scaleid))) {
		return true;
	} else {
		return false;
	}
}

/**
 * Make sure up-to-date events are created for all tarefalv instances
 *
 * This standard function will check all instances of this module
 * and make sure there are up-to-date events created for each of them.
 * If courseid = 0, then every tarefalv event in the site is checked, else
 	* only tarefalv events belonging to the course specified are checked.
 * This function is used, in its new format, by restore_refresh_events()
 *
 * @param $courseid int optional If zero then all tarefalvs for all courses are covered
 * @return boolean Always returns true
 */
function tarefalv_refresh_events($courseid = 0) {
	global $DB;

	if ($courseid == 0) {
		if (! $tarefalvs = $DB->get_records("tarefalv")) {
			return true;
		}
	} else {
		if (! $tarefalvs = $DB->get_records("tarefalv", array("course"=>$courseid))) {
			return true;
		}
	}
	$moduleid = $DB->get_field('modules', 'id', array('name'=>'tarefalv'));

	foreach ($tarefalvs as $tarefalv) {
		$cm = get_coursemodule_from_id('tarefalv', $tarefalv->id);
		$event = new stdClass();
		$event->name        = $tarefalv->name;
		$event->description = format_module_intro('tarefalv', $tarefalv, $cm->id);
		$event->timestart   = $tarefalv->timedue;

		if ($event->id = $DB->get_field('event', 'id', array('modulename'=>'tarefalv', 'instance'=>$tarefalv->id))) {
			update_event($event);

		} else {
			$event->courseid    = $tarefalv->course;
			$event->groupid     = 0;
			$event->userid      = 0;
			$event->modulename  = 'tarefalv';
			$event->instance    = $tarefalv->id;
			$event->eventtype   = 'due';
			$event->timeduration = 0;
			$event->visible     = $DB->get_field('course_modules', 'visible', array('module'=>$moduleid, 'instance'=>$tarefalv->id));
			add_event($event);
		}

	}
	return true;
}

/**
 * Print recent activity from all tarefalvs in a given course
 *
 * This is used by the recent activity block
 */
function tarefalv_print_recent_activity($course, $viewfullnames, $timestart) {
	global $CFG, $USER, $DB, $OUTPUT;

	// do not use log table if possible, it may be huge

	if (!$submissions = $DB->get_records_sql("SELECT asb.id, asb.timemodified, cm.id AS cmid, asb.userid,
			u.firstname, u.lastname, u.email, u.picture
			FROM {tarefalv_submissions} asb
			JOIN {tarefalv} a      ON a.id = asb.tarefalv
			JOIN {course_modules} cm ON cm.instance = a.id
			JOIN {modules} md        ON md.id = cm.module
			JOIN {user} u            ON u.id = asb.userid
			WHERE asb.timemodified > ? AND
			a.course = ? AND
			md.name = 'tarefalv'
			ORDER BY asb.timemodified ASC", array($timestart, $course->id))) {
			return false;
	}

	$modinfo = get_fast_modinfo($course); // reference needed because we might load the groups
	$show    = array();
	$grader  = array();

	foreach($submissions as $submission) {
		if (!array_key_exists($submission->cmid, $modinfo->cms)) {
			continue;
		}
		$cm = $modinfo->cms[$submission->cmid];
		if (!$cm->uservisible) {
			continue;
		}
		if ($submission->userid == $USER->id) {
			$show[] = $submission;
			continue;
		}

		// the act of sumbitting of tarefalv may be considered private - only graders will see it if specified
		if (empty($CFG->tarefalv_showrecentsubmissions)) {
			if (!array_key_exists($cm->id, $grader)) {
				$grader[$cm->id] = has_capability('moodle/grade:viewall', context_module::instance($cm->id));
			}
			if (!$grader[$cm->id]) {
				continue;
			}
		}

		$groupmode = groups_get_activity_groupmode($cm, $course);

		if ($groupmode == SEPARATEGROUPS and !has_capability('moodle/site:accessallgroups', context_module::instance($cm->id))) {
			if (isguestuser()) {
				// shortcut - guest user does not belong into any group
				continue;
			}

			if (is_null($modinfo->groups)) {
				$modinfo->groups = groups_get_user_groups($course->id); // load all my groups and cache it in modinfo
			}

			// this will be slow - show only users that share group with me in this cm
			if (empty($modinfo->groups[$cm->id])) {
				continue;
			}
			$usersgroups =  groups_get_all_groups($course->id, $submission->userid, $cm->groupingid);
			if (is_array($usersgroups)) {
				$usersgroups = array_keys($usersgroups);
				$intersect = array_intersect($usersgroups, $modinfo->groups[$cm->id]);
				if (empty($intersect)) {
					continue;
				}
			}
		}
		$show[] = $submission;
	}

	if (empty($show)) {
		return false;
	}

	echo $OUTPUT->heading(get_string('newsubmissions', 'tarefalv').':', 3);

	foreach ($show as $submission) {
		$cm = $modinfo->cms[$submission->cmid];
		$link = $CFG->wwwroot.'/mod/tarefalv/view.php?id='.$cm->id;
		print_recent_activity_note($submission->timemodified, $submission, $cm->name, $link, false, $viewfullnames);
	}

	return true;
}


/**
 * Returns all tarefalvs since a given time in specified forum.
 */
function tarefalv_get_recent_mod_activity(&$activities, &$index, $timestart, $courseid, $cmid, $userid=0, $groupid=0)  {
	global $CFG, $COURSE, $USER, $DB;

	if ($COURSE->id == $courseid) {
		$course = $COURSE;
	} else {
		$course = $DB->get_record('course', array('id'=>$courseid));
	}

	$modinfo = get_fast_modinfo($course);

	$cm = $modinfo->cms[$cmid];

	$params = array();
	if ($userid) {
		$userselect = "AND u.id = :userid";
		$params['userid'] = $userid;
	} else {
		$userselect = "";
	}

	if ($groupid) {
		$groupselect = "AND gm.groupid = :groupid";
		$groupjoin   = "JOIN {groups_members} gm ON  gm.userid=u.id";
		$params['groupid'] = $groupid;
	} else {
		$groupselect = "";
		$groupjoin   = "";
	}

	$params['cminstance'] = $cm->instance;
	$params['timestart'] = $timestart;

	$userfields = user_picture::fields('u', null, 'userid');

	if (!$submissions = $DB->get_records_sql("SELECT asb.id, asb.timemodified,
			$userfields
			FROM {tarefalv_submissions} asb
			JOIN {tarefalv} a      ON a.id = asb.tarefalv
			JOIN {user} u            ON u.id = asb.userid
			$groupjoin
			WHERE asb.timemodified > :timestart AND a.id = :cminstance
			$userselect $groupselect
			ORDER BY asb.timemodified ASC", $params)) {
			return;
	}

	$groupmode       = groups_get_activity_groupmode($cm, $course);
	$cm_context      = context_module::instance($cm->id);
	$grader          = has_capability('moodle/grade:viewall', $cm_context);
	$accessallgroups = has_capability('moodle/site:accessallgroups', $cm_context);
	$viewfullnames   = has_capability('moodle/site:viewfullnames', $cm_context);

	if (is_null($modinfo->groups)) {
		$modinfo->groups = groups_get_user_groups($course->id); // load all my groups and cache it in modinfo
	}

	$show = array();

	foreach($submissions as $submission) {
		if ($submission->userid == $USER->id) {
			$show[] = $submission;
			continue;
		}
		// the act of submitting of tarefalv may be considered private - only graders will see it if specified
		if (empty($CFG->tarefalv_showrecentsubmissions)) {
			if (!$grader) {
				continue;
			}
		}

		if ($groupmode == SEPARATEGROUPS and !$accessallgroups) {
			if (isguestuser()) {
				// shortcut - guest user does not belong into any group
				continue;
			}

			// this will be slow - show only users that share group with me in this cm
			if (empty($modinfo->groups[$cm->id])) {
				continue;
			}
			$usersgroups = groups_get_all_groups($course->id, $cm->userid, $cm->groupingid);
			if (is_array($usersgroups)) {
				$usersgroups = array_keys($usersgroups);
				$intersect = array_intersect($usersgroups, $modinfo->groups[$cm->id]);
				if (empty($intersect)) {
					continue;
				}
			}
		}
		$show[] = $submission;
	}

	if (empty($show)) {
		return;
	}

	if ($grader) {
		require_once($CFG->libdir.'/gradelib.php');
		$userids = array();
		foreach ($show as $id=>$submission) {
			$userids[] = $submission->userid;

		}
		$grades = grade_get_grades($courseid, 'mod', 'tarefalv', $cm->instance, $userids);
	}

	$aname = format_string($cm->name,true);
	foreach ($show as $submission) {
		$tmpactivity = new stdClass();

		$tmpactivity->type         = 'tarefalv';
		$tmpactivity->cmid         = $cm->id;
		$tmpactivity->name         = $aname;
		$tmpactivity->sectionnum   = $cm->sectionnum;
		$tmpactivity->timestamp    = $submission->timemodified;

		if ($grader) {
			$tmpactivity->grade = $grades->items[0]->grades[$submission->userid]->str_long_grade;
		}

		$userfields = explode(',', user_picture::fields());
		foreach ($userfields as $userfield) {
			if ($userfield == 'id') {
				$tmpactivity->user->{$userfield} = $submission->userid; // aliased in SQL above
			} else {
				$tmpactivity->user->{$userfield} = $submission->{$userfield};
			}
		}
		$tmpactivity->user->fullname = fullname($submission, $viewfullnames);

		$activities[$index++] = $tmpactivity;
	}

	return;
}

/**
 * Print recent activity from all tarefalvs in a given course
 *
 * This is used by course/recent.php
 */
function tarefalv_print_recent_mod_activity($activity, $courseid, $detail, $modnames)  {
	global $CFG, $OUTPUT;

	echo '<table border="0" cellpadding="3" cellspacing="0" class="tarefalv-recent">';

	echo "<tr><td class=\"userpicture\" valign=\"top\">";
	echo $OUTPUT->user_picture($activity->user);
	echo "</td><td>";

	if ($detail) {
		$modname = $modnames[$activity->type];
		echo '<div class="title">';
		echo "<img src=\"" . $OUTPUT->pix_url('icon', 'tarefalv') . "\" ".
				"class=\"icon\" alt=\"$modname\">";
		echo "<a href=\"$CFG->wwwroot/mod/tarefalv/view.php?id={$activity->cmid}\">{$activity->name}</a>";
		echo '</div>';
	}

	if (isset($activity->grade)) {
		echo '<div class="grade">';
		echo get_string('grade').': ';
		echo $activity->grade;
		echo '</div>';
	}

	echo '<div class="user">';
	echo "<a href=\"$CFG->wwwroot/user/view.php?id={$activity->user->id}&amp;course=$courseid\">"
	."{$activity->user->fullname}</a>  - ".userdate($activity->timestamp);
	echo '</div>';

	echo "</td></tr></table>";
}

/// GENERIC SQL FUNCTIONS

/**
 * Fetch info from logs
 *
 * @param $log object with properties ->info (the tarefalv id) and ->userid
 * @return array with tarefalv name and user firstname and lastname
 */
function tarefalv_log_info($log) {
	global $CFG, $DB;

	return $DB->get_record_sql("SELECT a.name, u.firstname, u.lastname
			FROM {tarefalv} a, {user} u
			WHERE a.id = ? AND u.id = ?", array($log->info, $log->userid));
}

/**
 * Return list of marked submissions that have not been mailed out for currently enrolled students
 *
 * @return array
 */
function tarefalv_get_unmailed_submissions($starttime, $endtime) {
	global $CFG, $DB;

	return $DB->get_records_sql("SELECT s.*, a.course, a.name
			FROM {tarefalv_submissions} s,
			{tarefalv} a
			WHERE s.mailed = 0
			AND s.timemarked <= ?
			AND s.timemarked >= ?
			AND s.tarefalv = a.id", array($endtime, $starttime));
}

/**
 * Counts all complete (real) tarefalv submissions by enrolled students for the given course modeule.
 *
 * @deprecated                         Since Moodle 2.2 MDL-abc - Please do not use this function any more.
 * @param  cm_info $cm                 The course module that we wish to perform the count on.
 * @param  int     $groupid (optional) If nonzero then count is restricted to this group
 * @return int                         The number of submissions
 */
function tarefalv_count_real_submissions($cm, $groupid=0) {
	global $CFG, $DB;

	// Grab the tarefalv type for the given course module
	$tarefalvtype = $DB->get_field($cm->modname, 'tarefalvtype', array('id' => $cm->instance), MUST_EXIST);

	// Create the expected class file path and class name for the returned assignemnt type
	$filename = "{$CFG->dirroot}/mod/tarefalv/type/{$tarefalvtype}/tarefalv.class.php";
	$classname = "tarefalv_{$tarefalvtype}";

	// If the file exists and the class is not already loaded we require the class file
	if (file_exists($filename) && !class_exists($classname)) {
		require_once($filename);
	}
	// If the required class is still not loaded then we revert to tarefalv base
	if (!class_exists($classname)) {
		$classname = 'tarefalv_base';
	}
	$instance = new $classname;

	// Attach the course module to the tarefalv type instance and then call the method for counting submissions
	$instance->cm = $cm;
	return $instance->count_real_submissions($groupid);
}

/**
 * Return all tarefalv submissions by ENROLLED students (even empty)
 *
 * There are also tarefalv type methods get_submissions() wich in the default
 * implementation simply call this function.
 * @param $sort string optional field names for the ORDER BY in the sql query
 * @param $dir string optional specifying the sort direction, defaults to DESC
 * @return array The submission objects indexed by id
 */
function tarefalv_get_all_submissions($tarefalv, $sort="", $dir="DESC") {
	/// Return all tarefalv submissions by ENROLLED students (even empty)
	global $CFG, $DB;

	if ($sort == "lastname" or $sort == "firstname") {
		$sort = "u.$sort $dir";
	} else if (empty($sort)) {
		$sort = "a.timemodified DESC";
	} else {
		$sort = "a.$sort $dir";
	}

	/* not sure this is needed at all since tarefalv already has a course define, so this join?
	 $select = "s.course = '$tarefalv->course' AND";
	if ($tarefalv->course == SITEID) {
	$select = '';
	}*/
	$cm = get_coursemodule_from_instance('tarefalv', $tarefalv->id, $tarefalv->course);
	$context = context_module::instance($cm->id);
	list($enroledsql, $params) = get_enrolled_sql($context, 'mod/tarefalv:submit');
	$params['tarefalvid'] = $tarefalv->id;
	return $DB->get_records_sql("SELECT a.*
			FROM {tarefalv_submissions} a
			INNER JOIN (". $enroledsql .") u ON u.id = a.userid
			WHERE u.id = a.userid
			AND a.tarefalv = :tarefalvid
			ORDER BY $sort", $params);

}

/**
 * Add a get_coursemodule_info function in case any tarefalv type wants to add 'extra' information
 * for the course (see resource).
 *
 * Given a course_module object, this function returns any "extra" information that may be needed
 * when printing this activity in a course listing.  See get_array_of_activities() in course/lib.php.
 *
 * @param $coursemodule object The coursemodule object (record).
 * @return cached_cm_info An object on information that the courses will know about (most noticeably, an icon).
 */
function tarefalv_get_coursemodule_info($coursemodule) {
	global $CFG, $DB;

	if (! $tarefalv = $DB->get_record('tarefalv', array('id'=>$coursemodule->instance),
			'id, tarefalvtype, name, intro, introformat')) {
			return false;
	}

	$libfile = "$CFG->dirroot/mod/tarefalv/type/$tarefalv->tarefalvtype/tarefalv.class.php";

	if (file_exists($libfile)) {
		require_once($libfile);
		$tarefalvclass = "tarefalv_$tarefalv->tarefalvtype";
		$ass = new $tarefalvclass('staticonly');
		if (!($result = $ass->get_coursemodule_info($coursemodule))) {
			$result = new cached_cm_info();
			$result->name = $tarefalv->name;
		}
		if ($coursemodule->showdescription) {
			// Convert intro to html. Do not filter cached version, filters run at display time.
			$result->content = format_module_intro('tarefalv', $tarefalv, $coursemodule->id, false);
		}
		return $result;
	} else {
		debugging('Incorrect tarefalv type: '.$tarefalv->tarefalvtype);
		return false;
	}
}



/// OTHER GENERAL FUNCTIONS FOR TAREFALVS  ///////////////////////////////////////

/**
 * Returns an array of installed tarefalv types indexed and sorted by name
 *
 * @return array The index is the name of the tarefalv type, the value its full name from the language strings
 */
function tarefalv_types() {
	$types = array();
	$names = get_plugin_list('tarefalv');
	foreach ($names as $name=>$dir) {
		$types[$name] = get_string('type'.$name, 'tarefalv');

		// ugly hack to support pluggable tarefalv type titles..
		if ($types[$name] == '[[type'.$name.']]') {
			$types[$name] = get_string('type'.$name, 'tarefalv_'.$name);
		}
	}
	asort($types);
	return $types;
}

function tarefalv_print_overview($courses, &$htmlarray) {
	global $USER, $CFG, $DB;
	require_once($CFG->libdir.'/gradelib.php');

	if (empty($courses) || !is_array($courses) || count($courses) == 0) {
		return array();
	}

	if (!$tarefalvs = get_all_instances_in_courses('tarefalv',$courses)) {
		return;
	}

	$tarefalvids = array();

	// Do tarefalv_base::isopen() here without loading the whole thing for speed
	foreach ($tarefalvs as $key => $tarefalv) {
		$time = time();
		if ($tarefalv->timedue) {
			if ($tarefalv->preventlate) {
				$isopen = ($tarefalv->timeavailable <= $time && $time <= $tarefalv->timedue);
			} else {
				$isopen = ($tarefalv->timeavailable <= $time);
			}
		}
		if (empty($isopen) || empty($tarefalv->timedue)) {
			unset($tarefalvs[$key]);
		} else {
			$tarefalvids[] = $tarefalv->id;
		}
	}

	if (empty($tarefalvids)){
		// no tarefalvs to look at - we're done
		return true;
	}

	$strduedate = get_string('duedate', 'tarefalv');
	$strduedateno = get_string('duedateno', 'tarefalv');
	$strgraded = get_string('graded', 'tarefalv');
	$strnotgradedyet = get_string('notgradedyet', 'tarefalv');
	$strnotsubmittedyet = get_string('notsubmittedyet', 'tarefalv');
	$strsubmitted = get_string('submitted', 'tarefalv');
	$strtarefalv = get_string('modulename', 'tarefalv');
	$strreviewed = get_string('reviewed','tarefalv');


	// NOTE: we do all possible database work here *outside* of the loop to ensure this scales
	//
	list($sqltarefalvids, $tarefalvidparams) = $DB->get_in_or_equal($tarefalvids);

	// build up and array of unmarked submissions indexed by tarefalv id/ userid
	// for use where the user has grading rights on tarefalv
	$rs = $DB->get_recordset_sql("SELECT id, tarefalv, userid
			FROM {tarefalv_submissions}
			WHERE teacher = 0 AND timemarked = 0
			AND tarefalv $sqltarefalvids", $tarefalvidparams);

	$unmarkedsubmissions = array();
	foreach ($rs as $rd) {
		$unmarkedsubmissions[$rd->tarefalv][$rd->userid] = $rd->id;
	}
	$rs->close();


	// get all user submissions, indexed by tarefalv id
	$mysubmissions = $DB->get_records_sql("SELECT tarefalv, timemarked, teacher, grade
			FROM {tarefalv_submissions}
			WHERE userid = ? AND
			tarefalv $sqltarefalvids", array_merge(array($USER->id), $tarefalvidparams));

	foreach ($tarefalvs as $tarefalv) {
		$grading_info = grade_get_grades($tarefalv->course, 'mod', 'tarefalv', $tarefalv->id, $USER->id);
		$final_grade = $grading_info->items[0]->grades[$USER->id];

		$str = '<div class="tarefalv overview"><div class="name">'.$strtarefalv. ': '.
				'<a '.($tarefalv->visible ? '':' class="dimmed"').
				'title="'.$strtarefalv.'" href="'.$CFG->wwwroot.
				'/mod/tarefalv/view.php?id='.$tarefalv->coursemodule.'">'.
				$tarefalv->name.'</a></div>';
		if ($tarefalv->timedue) {
			$str .= '<div class="info">'.$strduedate.': '.userdate($tarefalv->timedue).'</div>';
		} else {
			$str .= '<div class="info">'.$strduedateno.'</div>';
		}
		$context = context_module::instance($tarefalv->coursemodule);
		if (has_capability('mod/tarefalv:grade', $context)) {

			// count how many people can submit
			$submissions = 0; // init
			if ($students = get_enrolled_users($context, 'mod/tarefalv:view', 0, 'u.id')) {
				foreach ($students as $student) {
					if (isset($unmarkedsubmissions[$tarefalv->id][$student->id])) {
						$submissions++;
					}
				}
			}

			if ($submissions) {
				$link = new moodle_url('/mod/tarefalv/submissions.php', array('id'=>$tarefalv->coursemodule));
				$str .= '<div class="details"><a href="'.$link.'">'.get_string('submissionsnotgraded', 'tarefalv', $submissions).'</a></div>';
			}
		} else {
			$str .= '<div class="details">';
			if (isset($mysubmissions[$tarefalv->id])) {

				$submission = $mysubmissions[$tarefalv->id];

				if ($submission->teacher == 0 && $submission->timemarked == 0 && !$final_grade->grade) {
					$str .= $strsubmitted . ', ' . $strnotgradedyet;
				} else if ($submission->grade <= 0 && !$final_grade->grade) {
					$str .= $strsubmitted . ', ' . $strreviewed;
				} else {
					$str .= $strsubmitted . ', ' . $strgraded;
				}
			} else {
				$str .= $strnotsubmittedyet . ' ' . tarefalv_display_lateness(time(), $tarefalv->timedue);
			}
			$str .= '</div>';
		}
		$str .= '</div>';
		if (empty($htmlarray[$tarefalv->course]['tarefalv'])) {
			$htmlarray[$tarefalv->course]['tarefalv'] = $str;
		} else {
			$htmlarray[$tarefalv->course]['tarefalv'] .= $str;
		}
	}
}

function tarefalv_display_lateness($timesubmitted, $timedue) {
	if (!$timedue) {
		return '';
	}
	$time = $timedue - $timesubmitted;
	if ($time < 0) {
		$timetext = get_string('late', 'tarefalv', format_time($time));
		return ' (<span class="late">'.$timetext.'</span>)';
	} else {
		$timetext = get_string('early', 'tarefalv', format_time($time));
		return ' (<span class="early">'.$timetext.'</span>)';
	}
}

function tarefalv_get_view_actions() {
	return array('view');
}

function tarefalv_get_post_actions() {
	return array('upload');
}

function tarefalv_get_types() {
	global $CFG;
	$types = array();

	$type = new stdClass();
	$type->modclass = MOD_CLASS_ACTIVITY;
	$type->type = "tarefalv_group_start";
	$type->typestr = '--'.get_string('modulenameplural', 'tarefalv');
	$types[] = $type;

	 
	/**
	 * @lvs original
	 *  $standardtarefalvs = array('upload','online','uploadsingle','offline');
	 */
	$standardtarefalvs = array('upload');
	foreach ($standardtarefalvs as $tarefalvtype) {
		$type = new stdClass();
		$type->modclass = MOD_CLASS_ACTIVITY;
		$type->type = "tarefalv&amp;type=$tarefalvtype";
		$type->typestr = get_string("type$tarefalvtype", 'tarefalv');
		$types[] = $type;
	}

	/// Drop-in extra tarefalv types
	$tarefalvtypes = get_list_of_plugins('mod/tarefalv/type');
	foreach ($tarefalvtypes as $tarefalvtype) {
		/** @lvs Retirado da exibição o tipo de tarefa uploadsingle */
		if (!empty($CFG->{'tarefalv_hide_'.$tarefalvtype}) || $tarefalvtype = 'uploadsingle') {  // Not wanted
			continue;
		}
		if (!in_array($tarefalvtype, $standardtarefalvs)) {
			$type = new stdClass();
			$type->modclass = MOD_CLASS_ACTIVITY;
			$type->type = "tarefalv&amp;type=$tarefalvtype";
			$type->typestr = get_string("type$tarefalvtype", 'tarefalv_'.$tarefalvtype);
			$types[] = $type;
		}
	}

	$type = new stdClass();
	$type->modclass = MOD_CLASS_ACTIVITY;
	$type->type = "tarefalv_group_end";
	$type->typestr = '--';
	$types[] = $type;

	return $types;
}

/**
 * Removes all grades from gradebook
 *
 * @param int $courseid The ID of the course to reset
 * @param string $type Optional type of tarefalv to limit the reset to a particular tarefalv type
 */
function tarefalv_reset_gradebook($courseid, $type='') {
	global $CFG, $DB;

	$params = array('courseid'=>$courseid);
	if ($type) {
		$type = "AND a.tarefalvtype= :type";
		$params['type'] = $type;
	}

	$sql = "SELECT a.*, cm.idnumber as cmidnumber, a.course as courseid
	FROM {tarefalv} a, {course_modules} cm, {modules} m
	WHERE m.name='tarefalv' AND m.id=cm.module AND cm.instance=a.id AND a.course=:courseid $type";

	if ($tarefalvs = $DB->get_records_sql($sql, $params)) {
		foreach ($tarefalvs as $tarefalv) {
			tarefalv_grade_item_update($tarefalv, 'reset');
		}
	}
}

/**
 * This function is used by the reset_course_userdata function in moodlelib.
 * This function will remove all posts from the specified tarefalv
 * and clean up any related data.
 * @param $data the data submitted from the reset course.
 * @return array status array
 */
function tarefalv_reset_userdata($data) {
	global $CFG;

	$status = array();
	foreach (get_plugin_list('tarefalv') as $type=>$dir) {
		require_once("$dir/tarefalv.class.php");
		$tarefalvclass = "tarefalv_$type";
		$ass = new $tarefalvclass();
		$status = array_merge($status, $ass->reset_userdata($data));
	}

	return $status;
}

/**
 * Implementation of the function for printing the form elements that control
 * whether the course reset functionality affects the tarefalv.
 * @param $mform form passed by reference
 */
function tarefalv_reset_course_form_definition(&$mform) {
	$mform->addElement('header', 'tarefalvheader', get_string('modulenameplural', 'tarefalv'));
	$mform->addElement('advcheckbox', 'reset_tarefalv_submissions', get_string('deleteallsubmissions','tarefalv'));
}

/**
 * Course reset form defaults.
 */
function tarefalv_reset_course_form_defaults($course) {
	return array('reset_tarefalv_submissions'=>1);
}

/**
 * Returns all other caps used in module
 */
function tarefalv_get_extra_capabilities() {
	return array('moodle/site:accessallgroups', 'moodle/site:viewfullnames', 'moodle/grade:managegradingforms');
}

/**
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed True if module supports feature, null if doesn't know
 */
function tarefalv_supports($feature) {
	switch($feature) {
		case FEATURE_GROUPS:                  return true;
		case FEATURE_GROUPINGS:               return false;  // @lvs default: true
		case FEATURE_GROUPMEMBERSONLY:        return false;  // @lvs default: true
		case FEATURE_MOD_INTRO:               return true;
		case FEATURE_COMPLETION_TRACKS_VIEWS: return true;
		case FEATURE_GRADE_HAS_GRADE:         return true;
		case FEATURE_GRADE_OUTCOMES:          return false;  // @lvs default: true
		case FEATURE_GRADE_HAS_GRADE:         return true;
		case FEATURE_BACKUP_MOODLE2:          return true;
		case FEATURE_SHOW_DESCRIPTION:        return true;
		case FEATURE_ADVANCED_GRADING:        return true;
		case FEATURE_PLAGIARISM:              return true;

		default: return null;
	}
}

/**
 * Adds module specific settings to the settings block
 *
 * @param settings_navigation $settings The settings navigation object
 * @param navigation_node $tarefalvnode The node to add module settings to
 */
function tarefalv_extend_settings_navigation(settings_navigation $settings, navigation_node $tarefalvnode) {
	global $PAGE, $DB, $USER, $CFG;

	$tarefalvrow = $DB->get_record("tarefalv", array("id" => $PAGE->cm->instance));
	require_once "$CFG->dirroot/mod/tarefalv/type/$tarefalvrow->tarefalvtype/tarefalv.class.php";

	$tarefalvclass = 'tarefalv_'.$tarefalvrow->tarefalvtype;
	$tarefalvinstance = new $tarefalvclass($PAGE->cm->id, $tarefalvrow, $PAGE->cm, $PAGE->course);

	$allgroups = false;

	// Add tarefalv submission information
	if (has_capability('mod/tarefalv:grade', $PAGE->cm->context)) {
		if ($allgroups && has_capability('moodle/site:accessallgroups', $PAGE->cm->context)) {
			$group = 0;
		} else {
			$group = groups_get_activity_group($PAGE->cm);
		}
		$link = new moodle_url('/mod/tarefalv/submissions.php', array('id'=>$PAGE->cm->id));
		if ($tarefalvrow->tarefalvtype == 'offline') {
			$string = get_string('viewfeedback', 'tarefalv');
		} else if ($count = $tarefalvinstance->count_real_submissions($group)) {
			$string = get_string('viewsubmissions', 'tarefalv', $count);
		} else {
			$string = get_string('noattempts', 'tarefalv');
		}
		$tarefalvnode->add($string, $link, navigation_node::TYPE_SETTING);
	}

	if (is_object($tarefalvinstance) && method_exists($tarefalvinstance, 'extend_settings_navigation')) {
		$tarefalvinstance->extend_settings_navigation($tarefalvnode);
	}
}

/**
 * generate zip file from array of given files
 * @param array $filesforzipping - array of files to pass into archive_to_pathname
 * @return path of temp file - note this returned file does not have a .zip extension - it is a temp file.
 */
function tarefalv_pack_files($filesforzipping) {
	global $CFG;
	//create path for new zip file.
	$tempzip = tempnam($CFG->tempdir.'/', 'tarefalv_');
	//zip files
	$zipper = new zip_packer();
	if ($zipper->archive_to_pathname($filesforzipping, $tempzip)) {
		return $tempzip;
	}
	return false;
}

/**
 * Lists all file areas current user may browse
 *
 * @package  mod_tarefalv
 * @category files
 * @param stdClass $course course object
 * @param stdClass $cm course module object
 * @param stdClass $context context object
 * @return array available file areas
 */
function tarefalv_get_file_areas($course, $cm, $context) {
	$areas = array();
	if (has_capability('moodle/course:managefiles', $context)) {
		$areas['submission'] = get_string('tarefalvsubmission', 'tarefalv');
	}
	return $areas;
}

/**
 * File browsing support for tarefalv module.
 *
 * @param file_browser $browser
 * @param array $areas
 * @param stdClass $course
 * @param cm_info $cm
 * @param context $context
 * @param string $filearea
 * @param int $itemid
 * @param string $filepath
 * @param string $filename
 * @return file_info_stored file_info_stored instance or null if not found
 */
function tarefalv_get_file_info($browser, $areas, $course, $cm, $context, $filearea, $itemid, $filepath, $filename) {
	global $CFG, $DB, $USER;

	if ($context->contextlevel != CONTEXT_MODULE || $filearea != 'submission') {
		return null;
	}
	if (!$submission = $DB->get_record('tarefalv_submissions', array('id' => $itemid))) {
		return null;
	}
	if (!(($submission->userid == $USER->id && has_capability('mod/tarefalv:view', $context))
			|| has_capability('mod/tarefalv:grade', $context))) {
		// no permission to view this submission
		return null;
	}

	$fs = get_file_storage();
	$filepath = is_null($filepath) ? '/' : $filepath;
	$filename = is_null($filename) ? '.' : $filename;
	if (!($storedfile = $fs->get_file($context->id, 'mod_tarefalv', $filearea, $itemid, $filepath, $filename))) {
		return null;
	}
	$urlbase = $CFG->wwwroot.'/pluginfile.php';
	return new file_info_stored($browser, $context, $storedfile, $urlbase, $filearea, $itemid, true, true, false);
}

/**
 * Return a list of page types
 * @param string $pagetype current page type
 * @param stdClass $parentcontext Block's parent context
 * @param stdClass $currentcontext Current context of block
 */
function tarefalv_page_type_list($pagetype, $parentcontext, $currentcontext) {
	$module_pagetype = array(
			'mod-tarefalv-*'=>get_string('page-mod-tarefalv-x', 'tarefalv'),
			'mod-tarefalv-view'=>get_string('page-mod-tarefalv-view', 'tarefalv'),
			'mod-tarefalv-submissions'=>get_string('page-mod-tarefalv-submissions', 'tarefalv')
	);
	return $module_pagetype;
}

/**
 * Lists all gradable areas for the advanced grading methods gramework
 *
 * @return array
 */
function tarefalv_grading_areas_list() {
	return array('submission' => get_string('submissions', 'mod_tarefalv'));
}

/**
 * @lvs
 * Return rating related permissions
 *
 * @param string $options the context id
 * @return array an associative array of the user's rating permissions
 */
function tarefalv_rating_permissions($contextid, $component, $ratingarea=null) {
	$context = get_context_instance_by_id($contextid, MUST_EXIST);
	if ($component != 'mod_tarefalv') {
		// We don't know about this component/ratingarea so just return null to get the
		// default restrictive permissions.
		return null;
	}
	return array(
			'view'    => has_capability('mod/tarefalv:viewrating', $context),
			'viewany' => has_capability('mod/tarefalv:viewanyrating', $context),
			'viewall' => has_capability('mod/tarefalv:viewallratings', $context),
			'rate'    => has_capability('mod/tarefalv:rate', $context)
	);
}