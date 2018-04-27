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
 * This file contains the moodle hooks for the tarefalv module.
 *
 * It delegates most functions to the assignment class.
 *
 * @package   mod_tarefalv
 * @copyright 2012 NetSpot {@link http://www.netspot.com.au}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

/**
 * Adds an assignment instance
 *
 * This is done by calling the add_instance() method of the assignment type class
 * @param stdClass $data
 * @param mod_tarefalv_mod_form $form
 * @return int The instance id of the new assignment
 */
function tarefalv_add_instance(stdClass $data, mod_tarefalv_mod_form $form = null) {
    global $CFG;
    require_once($CFG->dirroot . '/mod/tarefalv/locallib.php');

    $assignment = new tarefalv(context_module::instance($data->coursemodule), null, null);
    return $assignment->add_instance($data, true);
}

/**
 * delete an assignment instance
 * @param int $id
 * @return bool
 */
function tarefalv_delete_instance($id) {
    global $CFG;
    require_once($CFG->dirroot . '/mod/tarefalv/locallib.php');
    $cm = get_coursemodule_from_instance('tarefalv', $id, 0, false, MUST_EXIST);
    $context = context_module::instance($cm->id);

    $assignment = new tarefalv($context, null, null);
    return $assignment->delete_instance();
}

/**
 * This function is used by the reset_course_userdata function in moodlelib.
 * This function will remove all assignment submissions and feedbacks in the database
 * and clean up any related data.
 *
 * @param stdClass $data the data submitted from the reset course.
 * @return array
 */
function tarefalv_reset_userdata($data) {
    global $CFG, $DB;
    require_once($CFG->dirroot . '/mod/tarefalv/locallib.php');

    $status = array();
    $params = array('courseid'=>$data->courseid);
    $sql = "SELECT a.id FROM {tarefalv} a WHERE a.course=:courseid";
    $course = $DB->get_record('course', array('id'=>$data->courseid), '*', MUST_EXIST);
    if ($tarefalvs = $DB->get_records_sql($sql, $params)) {
        foreach ($tarefalvs as $tarefalv) {
            $cm = get_coursemodule_from_instance('tarefalv',
                                                 $tarefalv->id,
                                                 $data->courseid,
                                                 false,
                                                 MUST_EXIST);
            $context = context_module::instance($cm->id);
            $assignment = new tarefalv($context, $cm, $course);
            $status = array_merge($status, $assignment->reset_userdata($data));
        }
    }
    return $status;
}

/**
 * This standard function will check all instances of this module
 * and make sure there are up-to-date events created for each of them.
 * If courseid = 0, then every assignment event in the site is checked, else
 * only assignment events belonging to the course specified are checked.
 *
 * @param int $courseid
 * @param int|stdClass $instance Tarefalv module instance or ID.
 * @param int|stdClass $cm Course module object or ID (not used in this module).
 * @return bool
 */
function tarefalv_refresh_events($courseid = 0, $instance = null, $cm = null) {
    global $CFG, $DB;
    require_once($CFG->dirroot . '/mod/tarefalv/locallib.php');

    // If we have instance information then we can just update the one event instead of updating all events.
    if (isset($instance)) {
        if (!is_object($instance)) {
            $instance = $DB->get_record('tarefalv', array('id' => $instance), '*', MUST_EXIST);
        }
        if (isset($cm)) {
            if (!is_object($cm)) {
                tarefalv_prepare_update_events($instance);
                return true;
            } else {
                $course = get_course($instance->course);
                tarefalv_prepare_update_events($instance, $course, $cm);
                return true;
            }
        }
    }

    if ($courseid) {
        // Make sure that the course id is numeric.
        if (!is_numeric($courseid)) {
            return false;
        }
        if (!$tarefalvs = $DB->get_records('tarefalv', array('course' => $courseid))) {
            return false;
        }
        // Get course from courseid parameter.
        if (!$course = $DB->get_record('course', array('id' => $courseid), '*')) {
            return false;
        }
    } else {
        if (!$tarefalvs = $DB->get_records('tarefalv')) {
            return false;
        }
    }
    foreach ($tarefalvs as $tarefalv) {
        tarefalv_prepare_update_events($tarefalv);
    }

    return true;
}

/**
 * This actually updates the normal and completion calendar events.
 *
 * @param  stdClass $tarefalv Assignment object (from DB).
 * @param  stdClass $course Course object.
 * @param  stdClass $cm Course module object.
 */
function tarefalv_prepare_update_events($tarefalv, $course = null, $cm = null) {
    global $DB;
    if (!isset($course)) {
        // Get course and course module for the assignment.
        list($course, $cm) = get_course_and_cm_from_instance($tarefalv->id, 'tarefalv', $tarefalv->course);
    }
    // Refresh the assignment's calendar events.
    $context = context_module::instance($cm->id);
    $assignment = new tarefalv($context, $cm, $course);
    $assignment->update_calendar($cm->id);
    // Refresh the calendar events also for the assignment overrides.
    $overrides = $DB->get_records('tarefalv_overrides', ['tarefalvid' => $tarefalv->id], '',
                                  'id, groupid, userid, duedate, sortorder');
    foreach ($overrides as $override) {
        if (empty($override->userid)) {
            unset($override->userid);
        }
        if (empty($override->groupid)) {
            unset($override->groupid);
        }
        tarefalv_update_events($assignment, $override);
    }
}

/**
 * Removes all grades from gradebook
 *
 * @param int $courseid The ID of the course to reset
 * @param string $type Optional type of assignment to limit the reset to a particular assignment type
 */
function tarefalv_reset_gradebook($courseid, $type='') {
    global $CFG, $DB;

    $params = array('moduletype'=>'tarefalv', 'courseid'=>$courseid);
    $sql = 'SELECT a.*, cm.idnumber as cmidnumber, a.course as courseid
            FROM {tarefalv} a, {course_modules} cm, {modules} m
            WHERE m.name=:moduletype AND m.id=cm.module AND cm.instance=a.id AND a.course=:courseid';

    if ($assignments = $DB->get_records_sql($sql, $params)) {
        foreach ($assignments as $assignment) {
            tarefalv_grade_item_update($assignment, 'reset');
        }
    }
}

/**
 * Implementation of the function for printing the form elements that control
 * whether the course reset functionality affects the assignment.
 * @param moodleform $mform form passed by reference
 */
function tarefalv_reset_course_form_definition(&$mform) {
    $mform->addElement('header', 'tarefalvheader', get_string('modulenameplural', 'tarefalv'));
    $name = get_string('deleteallsubmissions', 'tarefalv');
    $mform->addElement('advcheckbox', 'reset_tarefalv_submissions', $name);
    $mform->addElement('advcheckbox', 'reset_tarefalv_user_overrides',
        get_string('removealluseroverrides', 'tarefalv'));
    $mform->addElement('advcheckbox', 'reset_tarefalv_group_overrides',
        get_string('removeallgroupoverrides', 'tarefalv'));
}

/**
 * Course reset form defaults.
 * @param  object $course
 * @return array
 */
function tarefalv_reset_course_form_defaults($course) {
    return array('reset_tarefalv_submissions' => 1,
            'reset_tarefalv_group_overrides' => 1,
            'reset_tarefalv_user_overrides' => 1);
}

/**
 * Update an assignment instance
 *
 * This is done by calling the update_instance() method of the assignment type class
 * @param stdClass $data
 * @param stdClass $form - unused
 * @return object
 */
function tarefalv_update_instance(stdClass $data, $form) {
    global $CFG;
    require_once($CFG->dirroot . '/mod/tarefalv/locallib.php');
    $context = context_module::instance($data->coursemodule);
    $assignment = new tarefalv($context, null, null);
    return $assignment->update_instance($data);
}

/**
 * This function updates the events associated to the tarefalv.
 * If $override is non-zero, then it updates only the events
 * associated with the specified override.
 *
 * @param tarefalv $tarefalv the tarefalv object.
 * @param object $override (optional) limit to a specific override
 */
function tarefalv_update_events($tarefalv, $override = null) {
    global $CFG, $DB;

    require_once($CFG->dirroot . '/calendar/lib.php');

    $tarefalvinstance = $tarefalv->get_instance();

    // Load the old events relating to this tarefalv.
    $conds = array('modulename' => 'tarefalv', 'instance' => $tarefalvinstance->id);
    if (!empty($override)) {
        // Only load events for this override.
        if (isset($override->userid)) {
            $conds['userid'] = $override->userid;
        } else {
            $conds['groupid'] = $override->groupid;
        }
    }
    $oldevents = $DB->get_records('event', $conds, 'id ASC');

    // Now make a to-do list of all that needs to be updated.
    if (empty($override)) {
        // We are updating the primary settings for the assignment, so we need to add all the overrides.
        $overrides = $DB->get_records('tarefalv_overrides', array('tarefalvid' => $tarefalvinstance->id), 'id ASC');
        // It is necessary to add an empty stdClass to the beginning of the array as the $oldevents
        // list contains the original (non-override) event for the module. If this is not included
        // the logic below will end up updating the wrong row when we try to reconcile this $overrides
        // list against the $oldevents list.
        array_unshift($overrides, new stdClass());
    } else {
        // Just do the one override.
        $overrides = array($override);
    }

    if (!empty($tarefalv->get_course_module())) {
        $cmid = $tarefalv->get_course_module()->id;
    } else {
        $cmid = get_coursemodule_from_instance('tarefalv', $tarefalvinstance->id, $tarefalvinstance->course)->id;
    }

    foreach ($overrides as $current) {
        $groupid   = isset($current->groupid) ? $current->groupid : 0;
        $userid    = isset($current->userid) ? $current->userid : 0;
        $duedate = isset($current->duedate) ? $current->duedate : $tarefalvinstance->duedate;

        // Only add 'due' events for an override if they differ from the tarefalv default.
        $addclose = empty($current->id) || !empty($current->duedate);

        $event = new stdClass();
        $event->type = CALENDAR_EVENT_TYPE_ACTION;
        $event->description = format_module_intro('tarefalv', $tarefalvinstance, $cmid);
        // Events module won't show user events when the courseid is nonzero.
        $event->courseid    = ($userid) ? 0 : $tarefalvinstance->course;
        $event->groupid     = $groupid;
        $event->userid      = $userid;
        $event->modulename  = 'tarefalv';
        $event->instance    = $tarefalvinstance->id;
        $event->timestart   = $duedate;
        $event->timeduration = 0;
        $event->timesort    = $event->timestart + $event->timeduration;
        $event->visible     = instance_is_visible('tarefalv', $tarefalvinstance);
        $event->eventtype   = TAREFALV_EVENT_TYPE_DUE;
        $event->priority    = null;

        // Determine the event name and priority.
        if ($groupid) {
            // Group override event.
            $params = new stdClass();
            $params->tarefalv = $tarefalvinstance->name;
            $params->group = groups_get_group_name($groupid);
            if ($params->group === false) {
                // Group doesn't exist, just skip it.
                continue;
            }
            $eventname = get_string('overridegroupeventname', 'tarefalv', $params);
            // Set group override priority.
            if (isset($current->sortorder)) {
                $event->priority = $current->sortorder;
            }
        } else if ($userid) {
            // User override event.
            $params = new stdClass();
            $params->tarefalv = $tarefalvinstance->name;
            $eventname = get_string('overrideusereventname', 'tarefalv', $params);
            // Set user override priority.
            $event->priority = CALENDAR_EVENT_USER_OVERRIDE_PRIORITY;
        } else {
            // The parent event.
            $eventname = $tarefalvinstance->name;
        }

        if ($duedate && $addclose) {
            if ($oldevent = array_shift($oldevents)) {
                $event->id = $oldevent->id;
            } else {
                unset($event->id);
            }
            $event->name      = $eventname.' ('.get_string('duedate', 'tarefalv').')';
            calendar_event::create($event);
        }
    }

    // Delete any leftover events.
    foreach ($oldevents as $badevent) {
        $badevent = calendar_event::load($badevent);
        $badevent->delete();
    }
}

/**
 * Return the list if Moodle features this module supports
 *
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed True if module supports feature, null if doesn't know
 */
function tarefalv_supports($feature) {
    switch($feature) {
        case FEATURE_GROUPS:
            return true;
        case FEATURE_GROUPINGS:
            return true;
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return true;
        case FEATURE_COMPLETION_HAS_RULES:
            return true;
        case FEATURE_GRADE_HAS_GRADE:
            return true;
        case FEATURE_GRADE_OUTCOMES:
            return true;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        case FEATURE_ADVANCED_GRADING:
            return true;
        case FEATURE_PLAGIARISM:
            return true;
        case FEATURE_COMMENT:
            return true;

        default:
            return null;
    }
}

/**
 * Lists all gradable areas for the advanced grading methods gramework
 *
 * @return array('string'=>'string') An array with area names as keys and descriptions as values
 */
function tarefalv_grading_areas_list() {
    return array('submissions'=>get_string('submissions', 'tarefalv'));
}


/**
 * extend an assigment navigation settings
 *
 * @param settings_navigation $settings
 * @param navigation_node $navref
 * @return void
 */
function tarefalv_extend_settings_navigation(settings_navigation $settings, navigation_node $navref) {
    global $PAGE, $DB;

    // We want to add these new nodes after the Edit settings node, and before the
    // Locally tarefalved roles node. Of course, both of those are controlled by capabilities.
    $keys = $navref->get_children_key_list();
    $beforekey = null;
    $i = array_search('modedit', $keys);
    if ($i === false and array_key_exists(0, $keys)) {
        $beforekey = $keys[0];
    } else if (array_key_exists($i + 1, $keys)) {
        $beforekey = $keys[$i + 1];
    }

    $cm = $PAGE->cm;
    if (!$cm) {
        return;
    }

    $context = $cm->context;
    $course = $PAGE->course;

    if (!$course) {
        return;
    }

    if (has_capability('mod/tarefalv:manageoverrides', $PAGE->cm->context)) {
        $url = new moodle_url('/mod/tarefalv/overrides.php', array('cmid' => $PAGE->cm->id));
        $node = navigation_node::create(get_string('groupoverrides', 'tarefalv'),
            new moodle_url($url, array('mode' => 'group')),
            navigation_node::TYPE_SETTING, null, 'mod_tarefalv_groupoverrides');
        $navref->add_node($node, $beforekey);

        $node = navigation_node::create(get_string('useroverrides', 'tarefalv'),
            new moodle_url($url, array('mode' => 'user')),
            navigation_node::TYPE_SETTING, null, 'mod_tarefalv_useroverrides');
        $navref->add_node($node, $beforekey);
    }

    // Link to gradebook.
    if (has_capability('gradereport/grader:view', $cm->context) &&
            has_capability('moodle/grade:viewall', $cm->context)) {
        $link = new moodle_url('/grade/report/grader/index.php', array('id' => $course->id));
        $linkname = get_string('viewgradebook', 'tarefalv');
        $node = $navref->add($linkname, $link, navigation_node::TYPE_SETTING);
    }

    // Link to download all submissions.
    if (has_any_capability(array('mod/tarefalv:grade', 'mod/tarefalv:viewgrades'), $context)) {
        $link = new moodle_url('/mod/tarefalv/view.php', array('id' => $cm->id, 'action'=>'grading'));
        $node = $navref->add(get_string('viewgrading', 'tarefalv'), $link, navigation_node::TYPE_SETTING);

        $link = new moodle_url('/mod/tarefalv/view.php', array('id' => $cm->id, 'action'=>'downloadall'));
        $node = $navref->add(get_string('downloadall', 'tarefalv'), $link, navigation_node::TYPE_SETTING);
    }

    if (has_capability('mod/tarefalv:revealidentities', $context)) {
        $dbparams = array('id'=>$cm->instance);
        $assignment = $DB->get_record('tarefalv', $dbparams, 'blindmarking, revealidentities');

        if ($assignment && $assignment->blindmarking && !$assignment->revealidentities) {
            $urlparams = array('id' => $cm->id, 'action'=>'revealidentities');
            $url = new moodle_url('/mod/tarefalv/view.php', $urlparams);
            $linkname = get_string('revealidentities', 'tarefalv');
            $node = $navref->add($linkname, $url, navigation_node::TYPE_SETTING);
        }
    }
}

/**
 * Add a get_coursemodule_info function in case any assignment type wants to add 'extra' information
 * for the course (see resource).
 *
 * Given a course_module object, this function returns any "extra" information that may be needed
 * when printing this activity in a course listing.  See get_array_of_activities() in course/lib.php.
 *
 * @param stdClass $coursemodule The coursemodule object (record).
 * @return cached_cm_info An object on information that the courses
 *                        will know about (most noticeably, an icon).
 */
function tarefalv_get_coursemodule_info($coursemodule) {
    global $CFG, $DB;

    $dbparams = array('id'=>$coursemodule->instance);
    $fields = 'id, name, alwaysshowdescription, allowsubmissionsfromdate, intro, introformat, completionsubmit';
    if (! $assignment = $DB->get_record('tarefalv', $dbparams, $fields)) {
        return false;
    }

    $result = new cached_cm_info();
    $result->name = $assignment->name;
    if ($coursemodule->showdescription) {
        if ($assignment->alwaysshowdescription || time() > $assignment->allowsubmissionsfromdate) {
            // Convert intro to html. Do not filter cached version, filters run at display time.
            $result->content = format_module_intro('tarefalv', $assignment, $coursemodule->id, false);
        }
    }

    // Populate the custom completion rules as key => value pairs, but only if the completion mode is 'automatic'.
    if ($coursemodule->completion == COMPLETION_TRACKING_AUTOMATIC) {
        $result->customdata['customcompletionrules']['completionsubmit'] = $assignment->completionsubmit;
    }

    return $result;
}

/**
 * Callback which returns human-readable strings describing the active completion custom rules for the module instance.
 *
 * @param cm_info|stdClass $cm object with fields ->completion and ->customdata['customcompletionrules']
 * @return array $descriptions the array of descriptions for the custom rules.
 */
function mod_tarefalv_get_completion_active_rule_descriptions($cm) {
    // Values will be present in cm_info, and we assume these are up to date.
    if (empty($cm->customdata['customcompletionrules'])
        || $cm->completion != COMPLETION_TRACKING_AUTOMATIC) {
        return [];
    }

    $descriptions = [];
    foreach ($cm->customdata['customcompletionrules'] as $key => $val) {
        switch ($key) {
            case 'completionsubmit':
                if (empty($val)) {
                    continue;
                }
                $descriptions[] = get_string('completionsubmit', 'tarefalv');
                break;
            default:
                break;
        }
    }
    return $descriptions;
}

/**
 * Return a list of page types
 * @param string $pagetype current page type
 * @param stdClass $parentcontext Block's parent context
 * @param stdClass $currentcontext Current context of block
 */
function tarefalv_page_type_list($pagetype, $parentcontext, $currentcontext) {
    $modulepagetype = array(
        'mod-tarefalv-*' => get_string('page-mod-tarefalv-x', 'tarefalv'),
        'mod-tarefalv-view' => get_string('page-mod-tarefalv-view', 'tarefalv'),
    );
    return $modulepagetype;
}

/**
 * Print an overview of all assignments
 * for the courses.
 *
 * @deprecated since 3.3
 * @todo The final deprecation of this function will take place in Moodle 3.7 - see MDL-57487.
 * @param mixed $courses The list of courses to print the overview for
 * @param array $htmlarray The array of html to return
 * @return true
 */
function tarefalv_print_overview($courses, &$htmlarray) {
    global $CFG, $DB;

    debugging('The function tarefalv_print_overview() is now deprecated.', DEBUG_DEVELOPER);

    if (empty($courses) || !is_array($courses) || count($courses) == 0) {
        return true;
    }

    if (!$assignments = get_all_instances_in_courses('tarefalv', $courses)) {
        return true;
    }

    $assignmentids = array();

    // Do assignment_base::isopen() here without loading the whole thing for speed.
    foreach ($assignments as $key => $assignment) {
        $time = time();
        $isopen = false;
        if ($assignment->duedate) {
            $duedate = false;
            if ($assignment->cutoffdate) {
                $duedate = $assignment->cutoffdate;
            }
            if ($duedate) {
                $isopen = ($assignment->allowsubmissionsfromdate <= $time && $time <= $duedate);
            } else {
                $isopen = ($assignment->allowsubmissionsfromdate <= $time);
            }
        }
        if ($isopen) {
            $assignmentids[] = $assignment->id;
        }
    }

    if (empty($assignmentids)) {
        // No assignments to look at - we're done.
        return true;
    }

    // Definitely something to print, now include the constants we need.
    require_once($CFG->dirroot . '/mod/tarefalv/locallib.php');

    $strduedate = get_string('duedate', 'tarefalv');
    $strcutoffdate = get_string('nosubmissionsacceptedafter', 'tarefalv');
    $strnolatesubmissions = get_string('nolatesubmissions', 'tarefalv');
    $strduedateno = get_string('duedateno', 'tarefalv');
    $strassignment = get_string('modulename', 'tarefalv');

    // We do all possible database work here *outside* of the loop to ensure this scales.
    list($sqlassignmentids, $assignmentidparams) = $DB->get_in_or_equal($assignmentids);

    $mysubmissions = null;
    $unmarkedsubmissions = null;

    foreach ($assignments as $assignment) {

        // Do not show assignments that are not open.
        if (!in_array($assignment->id, $assignmentids)) {
            continue;
        }

        $context = context_module::instance($assignment->coursemodule);

        // Does the submission status of the assignment require notification?
        if (has_capability('mod/tarefalv:submit', $context, null, false)) {
            // Does the submission status of the assignment require notification?
            $submitdetails = tarefalv_get_mysubmission_details_for_print_overview($mysubmissions, $sqlassignmentids,
                    $assignmentidparams, $assignment);
        } else {
            $submitdetails = false;
        }

        if (has_capability('mod/tarefalv:grade', $context, null, false)) {
            // Does the grading status of the assignment require notification ?
            $gradedetails = tarefalv_get_grade_details_for_print_overview($unmarkedsubmissions, $sqlassignmentids,
                    $assignmentidparams, $assignment, $context);
        } else {
            $gradedetails = false;
        }

        if (empty($submitdetails) && empty($gradedetails)) {
            // There is no need to display this assignment as there is nothing to notify.
            continue;
        }

        $dimmedclass = '';
        if (!$assignment->visible) {
            $dimmedclass = ' class="dimmed"';
        }
        $href = $CFG->wwwroot . '/mod/tarefalv/view.php?id=' . $assignment->coursemodule;
        $basestr = '<div class="tarefalv overview">' .
               '<div class="name">' .
               $strassignment . ': '.
               '<a ' . $dimmedclass .
                   'title="' . $strassignment . '" ' .
                   'href="' . $href . '">' .
               format_string($assignment->name) .
               '</a></div>';
        if ($assignment->duedate) {
            $userdate = userdate($assignment->duedate);
            $basestr .= '<div class="info">' . $strduedate . ': ' . $userdate . '</div>';
        } else {
            $basestr .= '<div class="info">' . $strduedateno . '</div>';
        }
        if ($assignment->cutoffdate) {
            if ($assignment->cutoffdate == $assignment->duedate) {
                $basestr .= '<div class="info">' . $strnolatesubmissions . '</div>';
            } else {
                $userdate = userdate($assignment->cutoffdate);
                $basestr .= '<div class="info">' . $strcutoffdate . ': ' . $userdate . '</div>';
            }
        }

        // Show only relevant information.
        if (!empty($submitdetails)) {
            $basestr .= $submitdetails;
        }

        if (!empty($gradedetails)) {
            $basestr .= $gradedetails;
        }
        $basestr .= '</div>';

        if (empty($htmlarray[$assignment->course]['tarefalv'])) {
            $htmlarray[$assignment->course]['tarefalv'] = $basestr;
        } else {
            $htmlarray[$assignment->course]['tarefalv'] .= $basestr;
        }
    }
    return true;
}

/**
 * This api generates html to be displayed to students in print overview section, related to their submission status of the given
 * assignment.
 *
 * @deprecated since 3.3
 * @todo The final deprecation of this function will take place in Moodle 3.7 - see MDL-57487.
 * @param array $mysubmissions list of submissions of current user indexed by assignment id.
 * @param string $sqlassignmentids sql clause used to filter open assignments.
 * @param array $assignmentidparams sql params used to filter open assignments.
 * @param stdClass $assignment current assignment
 *
 * @return bool|string html to display , false if nothing needs to be displayed.
 * @throws coding_exception
 */
function tarefalv_get_mysubmission_details_for_print_overview(&$mysubmissions, $sqlassignmentids, $assignmentidparams,
                                                            $assignment) {
    global $USER, $DB;

    debugging('The function tarefalv_get_mysubmission_details_for_print_overview() is now deprecated.', DEBUG_DEVELOPER);

    if ($assignment->nosubmissions) {
        // Offline assignment. No need to display alerts for offline assignments.
        return false;
    }

    $strnotsubmittedyet = get_string('notsubmittedyet', 'tarefalv');

    if (!isset($mysubmissions)) {

        // Get all user submissions, indexed by assignment id.
        $dbparams = array_merge(array($USER->id), $assignmentidparams, array($USER->id));
        $mysubmissions = $DB->get_records_sql('SELECT a.id AS assignment,
                                                      a.nosubmissions AS nosubmissions,
                                                      g.timemodified AS timemarked,
                                                      g.grader AS grader,
                                                      g.grade AS grade,
                                                      s.status AS status
                                                 FROM {tarefalv} a, {tarefalv_submission} s
                                            LEFT JOIN {tarefalv_grades} g ON
                                                      g.assignment = s.assignment AND
                                                      g.userid = ? AND
                                                      g.attemptnumber = s.attemptnumber
                                                WHERE a.id ' . $sqlassignmentids . ' AND
                                                      s.latest = 1 AND
                                                      s.assignment = a.id AND
                                                      s.userid = ?', $dbparams);
    }

    $submitdetails = '';
    $submitdetails .= '<div class="details">';
    $submitdetails .= get_string('mysubmission', 'tarefalv');
    $submission = false;

    if (isset($mysubmissions[$assignment->id])) {
        $submission = $mysubmissions[$assignment->id];
    }

    if ($submission && $submission->status == TAREFALV_SUBMISSION_STATUS_SUBMITTED) {
        // A valid submission already exists, no need to notify students about this.
        return false;
    }

    // We need to show details only if a valid submission doesn't exist.
    if (!$submission ||
        !$submission->status ||
        $submission->status == TAREFALV_SUBMISSION_STATUS_DRAFT ||
        $submission->status == TAREFALV_SUBMISSION_STATUS_NEW
    ) {
        $submitdetails .= $strnotsubmittedyet;
    } else {
        $submitdetails .= get_string('submissionstatus_' . $submission->status, 'tarefalv');
    }
    if ($assignment->markingworkflow) {
        $workflowstate = $DB->get_field('tarefalv_user_flags', 'workflowstate', array('assignment' =>
                $assignment->id, 'userid' => $USER->id));
        if ($workflowstate) {
            $gradingstatus = 'markingworkflowstate' . $workflowstate;
        } else {
            $gradingstatus = 'markingworkflowstate' . TAREFALV_MARKING_WORKFLOW_STATE_NOTMARKED;
        }
    } else if (!empty($submission->grade) && $submission->grade !== null && $submission->grade >= 0) {
        $gradingstatus = TAREFALV_GRADING_STATUS_GRADED;
    } else {
        $gradingstatus = TAREFALV_GRADING_STATUS_NOT_GRADED;
    }
    $submitdetails .= ', ' . get_string($gradingstatus, 'tarefalv');
    $submitdetails .= '</div>';
    return $submitdetails;
}

/**
 * This api generates html to be displayed to teachers in print overview section, related to the grading status of the given
 * assignment's submissions.
 *
 * @deprecated since 3.3
 * @todo The final deprecation of this function will take place in Moodle 3.7 - see MDL-57487.
 * @param array $unmarkedsubmissions list of submissions of that are currently unmarked indexed by assignment id.
 * @param string $sqlassignmentids sql clause used to filter open assignments.
 * @param array $assignmentidparams sql params used to filter open assignments.
 * @param stdClass $assignment current assignment
 * @param context $context context of the assignment.
 *
 * @return bool|string html to display , false if nothing needs to be displayed.
 * @throws coding_exception
 */
function tarefalv_get_grade_details_for_print_overview(&$unmarkedsubmissions, $sqlassignmentids, $assignmentidparams,
                                                     $assignment, $context) {
    global $DB;

    debugging('The function tarefalv_get_grade_details_for_print_overview() is now deprecated.', DEBUG_DEVELOPER);

    if (!isset($unmarkedsubmissions)) {
        // Build up and array of unmarked submissions indexed by assignment id/ userid
        // for use where the user has grading rights on assignment.
        $dbparams = array_merge(array(TAREFALV_SUBMISSION_STATUS_SUBMITTED), $assignmentidparams);
        $rs = $DB->get_recordset_sql('SELECT s.assignment as assignment,
                                             s.userid as userid,
                                             s.id as id,
                                             s.status as status,
                                             g.timemodified as timegraded
                                        FROM {tarefalv_submission} s
                                   LEFT JOIN {tarefalv_grades} g ON
                                             s.userid = g.userid AND
                                             s.assignment = g.assignment AND
                                             g.attemptnumber = s.attemptnumber
                                   LEFT JOIN {tarefalv} a ON
                                             a.id = s.assignment
                                       WHERE
                                             ( g.timemodified is NULL OR
                                             s.timemodified >= g.timemodified OR
                                             g.grade IS NULL OR
                                             (g.grade = -1 AND
                                             a.grade < 0)) AND
                                             s.timemodified IS NOT NULL AND
                                             s.status = ? AND
                                             s.latest = 1 AND
                                             s.assignment ' . $sqlassignmentids, $dbparams);

        $unmarkedsubmissions = array();
        foreach ($rs as $rd) {
            $unmarkedsubmissions[$rd->assignment][$rd->userid] = $rd->id;
        }
        $rs->close();
    }

    // Count how many people can submit.
    $submissions = 0;
    if ($students = get_enrolled_users($context, 'mod/tarefalv:view', 0, 'u.id')) {
        foreach ($students as $student) {
            if (isset($unmarkedsubmissions[$assignment->id][$student->id])) {
                $submissions++;
            }
        }
    }

    if ($submissions) {
        $urlparams = array('id' => $assignment->coursemodule, 'action' => 'grading');
        $url = new moodle_url('/mod/tarefalv/view.php', $urlparams);
        $gradedetails = '<div class="details">' .
                '<a href="' . $url . '">' .
                get_string('submissionsnotgraded', 'tarefalv', $submissions) .
                '</a></div>';
        return $gradedetails;
    } else {
        return false;
    }

}

/**
 * Print recent activity from all assignments in a given course
 *
 * This is used by the recent activity block
 * @param mixed $course the course to print activity for
 * @param bool $viewfullnames boolean to determine whether to show full names or not
 * @param int $timestart the time the rendering started
 * @return bool true if activity was printed, false otherwise.
 */
function tarefalv_print_recent_activity($course, $viewfullnames, $timestart) {
    global $CFG, $USER, $DB, $OUTPUT;
    require_once($CFG->dirroot . '/mod/tarefalv/locallib.php');

    // Do not use log table if possible, it may be huge.

    $dbparams = array($timestart, $course->id, 'tarefalv', TAREFALV_SUBMISSION_STATUS_SUBMITTED);
    $namefields = user_picture::fields('u', null, 'userid');
    if (!$submissions = $DB->get_records_sql("SELECT asb.id, asb.timemodified, cm.id AS cmid, um.id as recordid,
                                                     $namefields
                                                FROM {tarefalv_submission} asb
                                                     JOIN {tarefalv} a      ON a.id = asb.assignment
                                                     JOIN {course_modules} cm ON cm.instance = a.id
                                                     JOIN {modules} md        ON md.id = cm.module
                                                     JOIN {user} u            ON u.id = asb.userid
                                                LEFT JOIN {tarefalv_user_mapping} um ON um.userid = u.id AND um.assignment = a.id
                                               WHERE asb.timemodified > ? AND
                                                     asb.latest = 1 AND
                                                     a.course = ? AND
                                                     md.name = ? AND
                                                     asb.status = ?
                                            ORDER BY asb.timemodified ASC", $dbparams)) {
         return false;
    }

    $modinfo = get_fast_modinfo($course);
    $show    = array();
    $grader  = array();

    $showrecentsubmissions = get_config('tarefalv', 'showrecentsubmissions');

    foreach ($submissions as $submission) {
        if (!array_key_exists($submission->cmid, $modinfo->get_cms())) {
            continue;
        }
        $cm = $modinfo->get_cm($submission->cmid);
        if (!$cm->uservisible) {
            continue;
        }
        if ($submission->userid == $USER->id) {
            $show[] = $submission;
            continue;
        }

        $context = context_module::instance($submission->cmid);
        // The act of submitting of assignment may be considered private -
        // only graders will see it if specified.
        if (empty($showrecentsubmissions)) {
            if (!array_key_exists($cm->id, $grader)) {
                $grader[$cm->id] = has_capability('moodle/grade:viewall', $context);
            }
            if (!$grader[$cm->id]) {
                continue;
            }
        }

        $groupmode = groups_get_activity_groupmode($cm, $course);

        if ($groupmode == SEPARATEGROUPS &&
                !has_capability('moodle/site:accessallgroups',  $context)) {
            if (isguestuser()) {
                // Shortcut - guest user does not belong into any group.
                continue;
            }

            // This will be slow - show only users that share group with me in this cm.
            if (!$modinfo->get_groups($cm->groupingid)) {
                continue;
            }
            $usersgroups =  groups_get_all_groups($course->id, $submission->userid, $cm->groupingid);
            if (is_array($usersgroups)) {
                $usersgroups = array_keys($usersgroups);
                $intersect = array_intersect($usersgroups, $modinfo->get_groups($cm->groupingid));
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
        $cm = $modinfo->get_cm($submission->cmid);
        $context = context_module::instance($submission->cmid);
        $tarefalv = new tarefalv($context, $cm, $cm->course);
        $link = $CFG->wwwroot.'/mod/tarefalv/view.php?id='.$cm->id;
        // Obscure first and last name if blind marking enabled.
        if ($tarefalv->is_blind_marking()) {
            $submission->firstname = get_string('participant', 'mod_tarefalv');
            if (empty($submission->recordid)) {
                $submission->recordid = $tarefalv->get_uniqueid_for_user($submission->userid);
            }
            $submission->lastname = $submission->recordid;
        }
        print_recent_activity_note($submission->timemodified,
                                   $submission,
                                   $cm->name,
                                   $link,
                                   false,
                                   $viewfullnames);
    }

    return true;
}

/**
 * Returns all assignments since a given time.
 *
 * @param array $activities The activity information is returned in this array
 * @param int $index The current index in the activities array
 * @param int $timestart The earliest activity to show
 * @param int $courseid Limit the search to this course
 * @param int $cmid The course module id
 * @param int $userid Optional user id
 * @param int $groupid Optional group id
 * @return void
 */
function tarefalv_get_recent_mod_activity(&$activities,
                                        &$index,
                                        $timestart,
                                        $courseid,
                                        $cmid,
                                        $userid=0,
                                        $groupid=0) {
    global $CFG, $COURSE, $USER, $DB;

    require_once($CFG->dirroot . '/mod/tarefalv/locallib.php');

    if ($COURSE->id == $courseid) {
        $course = $COURSE;
    } else {
        $course = $DB->get_record('course', array('id'=>$courseid));
    }

    $modinfo = get_fast_modinfo($course);

    $cm = $modinfo->get_cm($cmid);
    $params = array();
    if ($userid) {
        $userselect = 'AND u.id = :userid';
        $params['userid'] = $userid;
    } else {
        $userselect = '';
    }

    if ($groupid) {
        $groupselect = 'AND gm.groupid = :groupid';
        $groupjoin   = 'JOIN {groups_members} gm ON  gm.userid=u.id';
        $params['groupid'] = $groupid;
    } else {
        $groupselect = '';
        $groupjoin   = '';
    }

    $params['cminstance'] = $cm->instance;
    $params['timestart'] = $timestart;
    $params['submitted'] = TAREFALV_SUBMISSION_STATUS_SUBMITTED;

    $userfields = user_picture::fields('u', null, 'userid');

    if (!$submissions = $DB->get_records_sql('SELECT asb.id, asb.timemodified, ' .
                                                     $userfields .
                                             '  FROM {tarefalv_submission} asb
                                                JOIN {tarefalv} a ON a.id = asb.assignment
                                                JOIN {user} u ON u.id = asb.userid ' .
                                          $groupjoin .
                                            '  WHERE asb.timemodified > :timestart AND
                                                     asb.status = :submitted AND
                                                     a.id = :cminstance
                                                     ' . $userselect . ' ' . $groupselect .
                                            ' ORDER BY asb.timemodified ASC', $params)) {
         return;
    }

    $groupmode       = groups_get_activity_groupmode($cm, $course);
    $cmcontext      = context_module::instance($cm->id);
    $grader          = has_capability('moodle/grade:viewall', $cmcontext);
    $accessallgroups = has_capability('moodle/site:accessallgroups', $cmcontext);
    $viewfullnames   = has_capability('moodle/site:viewfullnames', $cmcontext);


    $showrecentsubmissions = get_config('tarefalv', 'showrecentsubmissions');
    $show = array();
    foreach ($submissions as $submission) {
        if ($submission->userid == $USER->id) {
            $show[] = $submission;
            continue;
        }
        // The act of submitting of assignment may be considered private -
        // only graders will see it if specified.
        if (empty($showrecentsubmissions)) {
            if (!$grader) {
                continue;
            }
        }

        if ($groupmode == SEPARATEGROUPS and !$accessallgroups) {
            if (isguestuser()) {
                // Shortcut - guest user does not belong into any group.
                continue;
            }

            // This will be slow - show only users that share group with me in this cm.
            if (!$modinfo->get_groups($cm->groupingid)) {
                continue;
            }
            $usersgroups =  groups_get_all_groups($course->id, $submission->userid, $cm->groupingid);
            if (is_array($usersgroups)) {
                $usersgroups = array_keys($usersgroups);
                $intersect = array_intersect($usersgroups, $modinfo->get_groups($cm->groupingid));
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
        foreach ($show as $id => $submission) {
            $userids[] = $submission->userid;
        }
        $grades = grade_get_grades($courseid, 'mod', 'tarefalv', $cm->instance, $userids);
    }

    $aname = format_string($cm->name, true);
    foreach ($show as $submission) {
        $activity = new stdClass();

        $activity->type         = 'tarefalv';
        $activity->cmid         = $cm->id;
        $activity->name         = $aname;
        $activity->sectionnum   = $cm->sectionnum;
        $activity->timestamp    = $submission->timemodified;
        $activity->user         = new stdClass();
        if ($grader) {
            $activity->grade = $grades->items[0]->grades[$submission->userid]->str_long_grade;
        }

        $userfields = explode(',', user_picture::fields());
        foreach ($userfields as $userfield) {
            if ($userfield == 'id') {
                // Aliased in SQL above.
                $activity->user->{$userfield} = $submission->userid;
            } else {
                $activity->user->{$userfield} = $submission->{$userfield};
            }
        }
        $activity->user->fullname = fullname($submission, $viewfullnames);

        $activities[$index++] = $activity;
    }

    return;
}

/**
 * Print recent activity from all assignments in a given course
 *
 * This is used by course/recent.php
 * @param stdClass $activity
 * @param int $courseid
 * @param bool $detail
 * @param array $modnames
 */
function tarefalv_print_recent_mod_activity($activity, $courseid, $detail, $modnames) {
    global $CFG, $OUTPUT;

    echo '<table border="0" cellpadding="3" cellspacing="0" class="assignment-recent">';

    echo '<tr><td class="userpicture" valign="top">';
    echo $OUTPUT->user_picture($activity->user);
    echo '</td><td>';

    if ($detail) {
        $modname = $modnames[$activity->type];
        echo '<div class="title">';
        echo $OUTPUT->image_icon('icon', $modname, 'tarefalv');
        echo '<a href="' . $CFG->wwwroot . '/mod/tarefalv/view.php?id=' . $activity->cmid . '">';
        echo $activity->name;
        echo '</a>';
        echo '</div>';
    }

    if (isset($activity->grade)) {
        echo '<div class="grade">';
        echo get_string('grade').': ';
        echo $activity->grade;
        echo '</div>';
    }

    echo '<div class="user">';
    echo "<a href=\"$CFG->wwwroot/user/view.php?id={$activity->user->id}&amp;course=$courseid\">";
    echo "{$activity->user->fullname}</a>  - " . userdate($activity->timestamp);
    echo '</div>';

    echo '</td></tr></table>';
}

/**
 * Checks if a scale is being used by an assignment.
 *
 * This is used by the backup code to decide whether to back up a scale
 * @param int $assignmentid
 * @param int $scaleid
 * @return boolean True if the scale is used by the assignment
 */
function tarefalv_scale_used($assignmentid, $scaleid) {
    global $DB;

    $return = false;
    $rec = $DB->get_record('tarefalv', array('id'=>$assignmentid, 'grade'=>-$scaleid));

    if (!empty($rec) && !empty($scaleid)) {
        $return = true;
    }

    return $return;
}

/**
 * Checks if scale is being used by any instance of assignment
 *
 * This is used to find out if scale used anywhere
 * @param int $scaleid
 * @return boolean True if the scale is used by any assignment
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
 * List the actions that correspond to a view of this module.
 * This is used by the participation report.
 *
 * Note: This is not used by new logging system. Event with
 *       crud = 'r' and edulevel = LEVEL_PARTICIPATING will
 *       be considered as view action.
 *
 * @return array
 */
function tarefalv_get_view_actions() {
    return array('view submission', 'view feedback');
}

/**
 * List the actions that correspond to a post of this module.
 * This is used by the participation report.
 *
 * Note: This is not used by new logging system. Event with
 *       crud = ('c' || 'u' || 'd') and edulevel = LEVEL_PARTICIPATING
 *       will be considered as post action.
 *
 * @return array
 */
function tarefalv_get_post_actions() {
    return array('upload', 'submit', 'submit for grading');
}

/**
 * Call cron on the tarefalv module.
 */
function tarefalv_cron() {
    global $CFG;

    require_once($CFG->dirroot . '/mod/tarefalv/locallib.php');
    tarefalv::cron();

    $plugins = core_component::get_plugin_list('tarefalvsubmission');

    foreach ($plugins as $name => $plugin) {
        $disabled = get_config('tarefalvsubmission_' . $name, 'disabled');
        if (!$disabled) {
            $class = 'tarefalv_submission_' . $name;
            require_once($CFG->dirroot . '/mod/tarefalv/submission/' . $name . '/locallib.php');
            $class::cron();
        }
    }
    $plugins = core_component::get_plugin_list('tarefalvfeedback');

    foreach ($plugins as $name => $plugin) {
        $disabled = get_config('tarefalvfeedback_' . $name, 'disabled');
        if (!$disabled) {
            $class = 'tarefalv_feedback_' . $name;
            require_once($CFG->dirroot . '/mod/tarefalv/feedback/' . $name . '/locallib.php');
            $class::cron();
        }
    }

    return true;
}

/**
 * Returns all other capabilities used by this module.
 * @return array Array of capability strings
 */
function tarefalv_get_extra_capabilities() {
    return array('gradereport/grader:view',
                 'moodle/grade:viewall',
                 'moodle/site:viewfullnames',
                 'moodle/site:config');
}

/**
 * Create grade item for given assignment.
 *
 * @param stdClass $tarefalv record with extra cmidnumber
 * @param array $grades optional array/object of grade(s); 'reset' means reset grades in gradebook
 * @return int 0 if ok, error code otherwise
 */
function tarefalv_grade_item_update($tarefalv, $grades=null) {
    global $CFG;
    require_once($CFG->libdir.'/gradelib.php');

    if (!isset($tarefalv->courseid)) {
        $tarefalv->courseid = $tarefalv->course;
    }

    $params = array('itemname'=>$tarefalv->name, 'idnumber'=>$tarefalv->cmidnumber);

    // Check if feedback plugin for gradebook is enabled, if yes then
    // gradetype = GRADE_TYPE_TEXT else GRADE_TYPE_NONE.
    $gradefeedbackenabled = false;

    if (isset($tarefalv->gradefeedbackenabled)) {
        $gradefeedbackenabled = $tarefalv->gradefeedbackenabled;
    } else if ($tarefalv->grade == 0) { // Grade feedback is needed only when grade == 0.
        require_once($CFG->dirroot . '/mod/tarefalv/locallib.php');
        $mod = get_coursemodule_from_instance('tarefalv', $tarefalv->id, $tarefalv->courseid);
        $cm = context_module::instance($mod->id);
        $assignment = new tarefalv($cm, null, null);
        $gradefeedbackenabled = $assignment->is_gradebook_feedback_enabled();
    }

    if ($tarefalv->grade > 0) {
        $params['gradetype'] = GRADE_TYPE_VALUE;
        $params['grademax']  = $tarefalv->grade;
        $params['grademin']  = 0;

    } else if ($tarefalv->grade < 0) {
        $params['gradetype'] = GRADE_TYPE_SCALE;
        $params['scaleid']   = -$tarefalv->grade;

    } else if ($gradefeedbackenabled) {
        // $tarefalv->grade == 0 and feedback enabled.
        $params['gradetype'] = GRADE_TYPE_TEXT;
    } else {
        // $tarefalv->grade == 0 and no feedback enabled.
        $params['gradetype'] = GRADE_TYPE_NONE;
    }

    if ($grades  === 'reset') {
        $params['reset'] = true;
        $grades = null;
    }

    return grade_update('mod/tarefalv',
                        $tarefalv->courseid,
                        'mod',
                        'tarefalv',
                        $tarefalv->id,
                        0,
                        $grades,
                        $params);
}

/**
 * Return grade for given user or all users.
 *
 * @param stdClass $tarefalv record of tarefalv with an additional cmidnumber
 * @param int $userid optional user id, 0 means all users
 * @return array array of grades, false if none
 */
function tarefalv_get_user_grades($tarefalv, $userid=0) {
    global $CFG;

    require_once($CFG->dirroot . '/mod/tarefalv/locallib.php');

    $cm = get_coursemodule_from_instance('tarefalv', $tarefalv->id, 0, false, MUST_EXIST);
    $context = context_module::instance($cm->id);
    $assignment = new tarefalv($context, null, null);
    $assignment->set_instance($tarefalv);
    return $assignment->get_user_grades_for_gradebook($userid);
}

/**
 * Update activity grades.
 *
 * @param stdClass $tarefalv database record
 * @param int $userid specific user only, 0 means all
 * @param bool $nullifnone - not used
 */
function tarefalv_update_grades($tarefalv, $userid=0, $nullifnone=true) {
    global $CFG;
    require_once($CFG->libdir.'/gradelib.php');

    if ($tarefalv->grade == 0) {
        tarefalv_grade_item_update($tarefalv);

    } else if ($grades = tarefalv_get_user_grades($tarefalv, $userid)) {
        foreach ($grades as $k => $v) {
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
 * List the file areas that can be browsed.
 *
 * @param stdClass $course
 * @param stdClass $cm
 * @param stdClass $context
 * @return array
 */
function tarefalv_get_file_areas($course, $cm, $context) {
    global $CFG;
    require_once($CFG->dirroot . '/mod/tarefalv/locallib.php');

    $areas = array(TAREFALV_INTROATTACHMENT_FILEAREA => get_string('introattachments', 'mod_tarefalv'));

    $assignment = new tarefalv($context, $cm, $course);
    foreach ($assignment->get_submission_plugins() as $plugin) {
        if ($plugin->is_visible()) {
            $pluginareas = $plugin->get_file_areas();

            if ($pluginareas) {
                $areas = array_merge($areas, $pluginareas);
            }
        }
    }
    foreach ($assignment->get_feedback_plugins() as $plugin) {
        if ($plugin->is_visible()) {
            $pluginareas = $plugin->get_file_areas();

            if ($pluginareas) {
                $areas = array_merge($areas, $pluginareas);
            }
        }
    }

    return $areas;
}

/**
 * File browsing support for tarefalv module.
 *
 * @param file_browser $browser
 * @param object $areas
 * @param object $course
 * @param object $cm
 * @param object $context
 * @param string $filearea
 * @param int $itemid
 * @param string $filepath
 * @param string $filename
 * @return object file_info instance or null if not found
 */
function tarefalv_get_file_info($browser,
                              $areas,
                              $course,
                              $cm,
                              $context,
                              $filearea,
                              $itemid,
                              $filepath,
                              $filename) {
    global $CFG;
    require_once($CFG->dirroot . '/mod/tarefalv/locallib.php');

    if ($context->contextlevel != CONTEXT_MODULE) {
        return null;
    }

    $urlbase = $CFG->wwwroot.'/pluginfile.php';
    $fs = get_file_storage();
    $filepath = is_null($filepath) ? '/' : $filepath;
    $filename = is_null($filename) ? '.' : $filename;

    // Need to find where this belongs to.
    $assignment = new tarefalv($context, $cm, $course);
    if ($filearea === TAREFALV_INTROATTACHMENT_FILEAREA) {
        if (!has_capability('moodle/course:managefiles', $context)) {
            // Students can not peak here!
            return null;
        }
        if (!($storedfile = $fs->get_file($assignment->get_context()->id,
                                          'mod_tarefalv', $filearea, 0, $filepath, $filename))) {
            return null;
        }
        return new file_info_stored($browser,
                        $assignment->get_context(),
                        $storedfile,
                        $urlbase,
                        $filearea,
                        $itemid,
                        true,
                        true,
                        false);
    }

    $pluginowner = null;
    foreach ($assignment->get_submission_plugins() as $plugin) {
        if ($plugin->is_visible()) {
            $pluginareas = $plugin->get_file_areas();

            if (array_key_exists($filearea, $pluginareas)) {
                $pluginowner = $plugin;
                break;
            }
        }
    }
    if (!$pluginowner) {
        foreach ($assignment->get_feedback_plugins() as $plugin) {
            if ($plugin->is_visible()) {
                $pluginareas = $plugin->get_file_areas();

                if (array_key_exists($filearea, $pluginareas)) {
                    $pluginowner = $plugin;
                    break;
                }
            }
        }
    }

    if (!$pluginowner) {
        return null;
    }

    $result = $pluginowner->get_file_info($browser, $filearea, $itemid, $filepath, $filename);
    return $result;
}

/**
 * Prints the complete info about a user's interaction with an assignment.
 *
 * @param stdClass $course
 * @param stdClass $user
 * @param stdClass $coursemodule
 * @param stdClass $tarefalv the database tarefalv record
 *
 * This prints the submission summary and feedback summary for this student.
 */
function tarefalv_user_complete($course, $user, $coursemodule, $tarefalv) {
    global $CFG;
    require_once($CFG->dirroot . '/mod/tarefalv/locallib.php');

    $context = context_module::instance($coursemodule->id);

    $assignment = new tarefalv($context, $coursemodule, $course);

    echo $assignment->view_student_summary($user, false);
}

/**
 * Rescale all grades for this activity and push the new grades to the gradebook.
 *
 * @param stdClass $course Course db record
 * @param stdClass $cm Course module db record
 * @param float $oldmin
 * @param float $oldmax
 * @param float $newmin
 * @param float $newmax
 */
function tarefalv_rescale_activity_grades($course, $cm, $oldmin, $oldmax, $newmin, $newmax) {
    global $DB;

    if ($oldmax <= $oldmin) {
        // Grades cannot be scaled.
        return false;
    }
    $scale = ($newmax - $newmin) / ($oldmax - $oldmin);
    if (($newmax - $newmin) <= 1) {
        // We would lose too much precision, lets bail.
        return false;
    }

    $params = array(
        'p1' => $oldmin,
        'p2' => $scale,
        'p3' => $newmin,
        'a' => $cm->instance
    );

    // Only rescale grades that are greater than or equal to 0. Anything else is a special value.
    $sql = 'UPDATE {tarefalv_grades} set grade = (((grade - :p1) * :p2) + :p3) where assignment = :a and grade >= 0';
    $dbupdate = $DB->execute($sql, $params);
    if (!$dbupdate) {
        return false;
    }

    // Now re-push all grades to the gradebook.
    $dbparams = array('id' => $cm->instance);
    $tarefalv = $DB->get_record('tarefalv', $dbparams);
    $tarefalv->cmidnumber = $cm->idnumber;

    tarefalv_update_grades($tarefalv);

    return true;
}

/**
 * Print the grade information for the assignment for this user.
 *
 * @param stdClass $course
 * @param stdClass $user
 * @param stdClass $coursemodule
 * @param stdClass $assignment
 */
function tarefalv_user_outline($course, $user, $coursemodule, $assignment) {
    global $CFG;
    require_once($CFG->libdir.'/gradelib.php');
    require_once($CFG->dirroot.'/grade/grading/lib.php');

    $gradinginfo = grade_get_grades($course->id,
                                        'mod',
                                        'tarefalv',
                                        $assignment->id,
                                        $user->id);

    $gradingitem = $gradinginfo->items[0];
    $gradebookgrade = $gradingitem->grades[$user->id];

    if (empty($gradebookgrade->str_long_grade)) {
        return null;
    }
    $result = new stdClass();
    $result->info = get_string('outlinegrade', 'tarefalv', $gradebookgrade->str_long_grade);
    $result->time = $gradebookgrade->dategraded;

    return $result;
}

/**
 * Obtains the automatic completion state for this module based on any conditions
 * in tarefalv settings.
 *
 * @param object $course Course
 * @param object $cm Course-module
 * @param int $userid User ID
 * @param bool $type Type of comparison (or/and; can be used as return value if no conditions)
 * @return bool True if completed, false if not, $type if conditions not set.
 */
function tarefalv_get_completion_state($course, $cm, $userid, $type) {
    global $CFG, $DB;
    require_once($CFG->dirroot . '/mod/tarefalv/locallib.php');

    $tarefalv = new tarefalv(null, $cm, $course);

    // If completion option is enabled, evaluate it and return true/false.
    if ($tarefalv->get_instance()->completionsubmit) {
        if ($tarefalv->get_instance()->teamsubmission) {
            $submission = $tarefalv->get_group_submission($userid, 0, false);
        } else {
            $submission = $tarefalv->get_user_submission($userid, false);
        }
        return $submission && $submission->status == TAREFALV_SUBMISSION_STATUS_SUBMITTED;
    } else {
        // Completion option is not enabled so just return $type.
        return $type;
    }
}

/**
 * Serves intro attachment files.
 *
 * @param mixed $course course or id of the course
 * @param mixed $cm course module or id of the course module
 * @param context $context
 * @param string $filearea
 * @param array $args
 * @param bool $forcedownload
 * @param array $options additional options affecting the file serving
 * @return bool false if file not found, does not return if found - just send the file
 */
function tarefalv_pluginfile($course,
                $cm,
                context $context,
                $filearea,
                $args,
                $forcedownload,
                array $options=array()) {
    global $CFG;

    if ($context->contextlevel != CONTEXT_MODULE) {
        return false;
    }

    require_login($course, false, $cm);
    if (!has_capability('mod/tarefalv:view', $context)) {
        return false;
    }

    require_once($CFG->dirroot . '/mod/tarefalv/locallib.php');
    $tarefalv = new tarefalv($context, $cm, $course);

    if ($filearea !== TAREFALV_INTROATTACHMENT_FILEAREA) {
        return false;
    }
    if (!$tarefalv->show_intro()) {
        return false;
    }

    $itemid = (int)array_shift($args);
    if ($itemid != 0) {
        return false;
    }

    $relativepath = implode('/', $args);

    $fullpath = "/{$context->id}/mod_tarefalv/$filearea/$itemid/$relativepath";

    $fs = get_file_storage();
    if (!$file = $fs->get_file_by_hash(sha1($fullpath)) or $file->is_directory()) {
        return false;
    }
    send_stored_file($file, 0, 0, $forcedownload, $options);
}

/**
 * Serve the grading panel as a fragment.
 *
 * @param array $args List of named arguments for the fragment loader.
 * @return string
 */
function mod_tarefalv_output_fragment_gradingpanel($args) {
    global $CFG;

    $context = $args['context'];

    if ($context->contextlevel != CONTEXT_MODULE) {
        return null;
    }
    require_once($CFG->dirroot . '/mod/tarefalv/locallib.php');
    $tarefalv = new tarefalv($context, null, null);

    $userid = clean_param($args['userid'], PARAM_INT);
    $attemptnumber = clean_param($args['attemptnumber'], PARAM_INT);
    $formdata = array();
    if (!empty($args['jsonformdata'])) {
        $serialiseddata = json_decode($args['jsonformdata']);
        parse_str($serialiseddata, $formdata);
    }
    $viewargs = array(
        'userid' => $userid,
        'attemptnumber' => $attemptnumber,
        'formdata' => $formdata
    );

    return $tarefalv->view('gradingpanel', $viewargs);
}

/**
 * Check if the module has any update that affects the current user since a given time.
 *
 * @param  cm_info $cm course module data
 * @param  int $from the time to check updates from
 * @param  array $filter  if we need to check only specific updates
 * @return stdClass an object with the different type of areas indicating if they were updated or not
 * @since Moodle 3.2
 */
function tarefalv_check_updates_since(cm_info $cm, $from, $filter = array()) {
    global $DB, $USER, $CFG;
    require_once($CFG->dirroot . '/mod/tarefalv/locallib.php');

    $updates = new stdClass();
    $updates = course_check_module_updates_since($cm, $from, array(TAREFALV_INTROATTACHMENT_FILEAREA), $filter);

    // Check if there is a new submission by the user or new grades.
    $select = 'assignment = :id AND userid = :userid AND (timecreated > :since1 OR timemodified > :since2)';
    $params = array('id' => $cm->instance, 'userid' => $USER->id, 'since1' => $from, 'since2' => $from);
    $updates->submissions = (object) array('updated' => false);
    $submissions = $DB->get_records_select('tarefalv_submission', $select, $params, '', 'id');
    if (!empty($submissions)) {
        $updates->submissions->updated = true;
        $updates->submissions->itemids = array_keys($submissions);
    }

    $updates->grades = (object) array('updated' => false);
    $grades = $DB->get_records_select('tarefalv_grades', $select, $params, '', 'id');
    if (!empty($grades)) {
        $updates->grades->updated = true;
        $updates->grades->itemids = array_keys($grades);
    }

    // Now, teachers should see other students updates.
    if (has_capability('mod/tarefalv:viewgrades', $cm->context)) {
        $params = array('id' => $cm->instance, 'since1' => $from, 'since2' => $from);
        $select = 'assignment = :id AND (timecreated > :since1 OR timemodified > :since2)';

        if (groups_get_activity_groupmode($cm) == SEPARATEGROUPS) {
            $groupusers = array_keys(groups_get_activity_shared_group_members($cm));
            if (empty($groupusers)) {
                return $updates;
            }
            list($insql, $inparams) = $DB->get_in_or_equal($groupusers, SQL_PARAMS_NAMED);
            $select .= ' AND userid ' . $insql;
            $params = array_merge($params, $inparams);
        }

        $updates->usersubmissions = (object) array('updated' => false);
        $submissions = $DB->get_records_select('tarefalv_submission', $select, $params, '', 'id');
        if (!empty($submissions)) {
            $updates->usersubmissions->updated = true;
            $updates->usersubmissions->itemids = array_keys($submissions);
        }

        $updates->usergrades = (object) array('updated' => false);
        $grades = $DB->get_records_select('tarefalv_grades', $select, $params, '', 'id');
        if (!empty($grades)) {
            $updates->usergrades->updated = true;
            $updates->usergrades->itemids = array_keys($grades);
        }
    }

    return $updates;
}

/**
 * Is the event visible?
 *
 * This is used to determine global visibility of an event in all places throughout Moodle. For example,
 * the TAREFALV_EVENT_TYPE_GRADINGDUE event will not be shown to students on their calendar.
 *
 * @param calendar_event $event
 * @return bool Returns true if the event is visible to the current user, false otherwise.
 */
function mod_tarefalv_core_calendar_is_event_visible(calendar_event $event) {
    global $CFG, $USER;

    require_once($CFG->dirroot . '/mod/tarefalv/locallib.php');

    $cm = get_fast_modinfo($event->courseid)->instances['tarefalv'][$event->instance];
    $context = context_module::instance($cm->id);

    $tarefalv = new tarefalv($context, $cm, null);

    if ($event->eventtype == TAREFALV_EVENT_TYPE_GRADINGDUE) {
        return $tarefalv->can_grade();
    } else {
        return true;
    }
}

/**
 * This function receives a calendar event and returns the action associated with it, or null if there is none.
 *
 * This is used by block_myoverview in order to display the event appropriately. If null is returned then the event
 * is not displayed on the block.
 *
 * @param calendar_event $event
 * @param \core_calendar\action_factory $factory
 * @return \core_calendar\local\event\entities\action_interface|null
 */
function mod_tarefalv_core_calendar_provide_event_action(calendar_event $event,
                                                       \core_calendar\action_factory $factory) {

    global $CFG, $USER;

    require_once($CFG->dirroot . '/mod/tarefalv/locallib.php');

    $cm = get_fast_modinfo($event->courseid)->instances['tarefalv'][$event->instance];
    $context = context_module::instance($cm->id);

    $tarefalv = new tarefalv($context, $cm, null);

    // Apply overrides.
    $tarefalv->update_effective_access($USER->id);

    if ($event->eventtype == TAREFALV_EVENT_TYPE_GRADINGDUE) {
        $name = get_string('grade');
        $url = new \moodle_url('/mod/tarefalv/view.php', [
            'id' => $cm->id,
            'action' => 'grader'
        ]);
        $itemcount = $tarefalv->count_submissions_need_grading();
        $actionable = $tarefalv->can_grade() && (time() >= $tarefalv->get_instance()->allowsubmissionsfromdate);
    } else {
        $usersubmission = $tarefalv->get_user_submission($USER->id, false);
        if ($usersubmission && $usersubmission->status === TAREFALV_SUBMISSION_STATUS_SUBMITTED) {
            // The user has already submitted.
            // We do not want to change the text to edit the submission, we want to remove the event from the Dashboard entirely.
            return null;
        }

        $participant = $tarefalv->get_participant($USER->id);

        if (!$participant) {
            // If the user is not a participant in the assignment then they have
            // no action to take. This will filter out the events for teachers.
            return null;
        }

        // The user has not yet submitted anything. Show the addsubmission link.
        $name = get_string('addsubmission', 'tarefalv');
        $url = new \moodle_url('/mod/tarefalv/view.php', [
            'id' => $cm->id,
            'action' => 'editsubmission'
        ]);
        $itemcount = 1;
        $actionable = $tarefalv->is_any_submission_plugin_enabled() && $tarefalv->can_edit_submission($USER->id);
    }

    return $factory->create_instance(
        $name,
        $url,
        $itemcount,
        $actionable
    );
}

/**
 * Callback function that determines whether an action event should be showing its item count
 * based on the event type and the item count.
 *
 * @param calendar_event $event The calendar event.
 * @param int $itemcount The item count associated with the action event.
 * @return bool
 */
function mod_tarefalv_core_calendar_event_action_shows_item_count(calendar_event $event, $itemcount = 0) {
    // List of event types where the action event's item count should be shown.
    $eventtypesshowingitemcount = [
        TAREFALV_EVENT_TYPE_GRADINGDUE
    ];
    // For mod_tarefalv, item count should be shown if the event type is 'gradingdue' and there is one or more item count.
    return in_array($event->eventtype, $eventtypesshowingitemcount) && $itemcount > 0;
}

/**
 * This function calculates the minimum and maximum cutoff values for the timestart of
 * the given event.
 *
 * It will return an array with two values, the first being the minimum cutoff value and
 * the second being the maximum cutoff value. Either or both values can be null, which
 * indicates there is no minimum or maximum, respectively.
 *
 * If a cutoff is required then the function must return an array containing the cutoff
 * timestamp and error string to display to the user if the cutoff value is violated.
 *
 * A minimum and maximum cutoff return value will look like:
 * [
 *     [1505704373, 'The due date must be after the sbumission start date'],
 *     [1506741172, 'The due date must be before the cutoff date']
 * ]
 *
 * If the event does not have a valid timestart range then [false, false] will
 * be returned.
 *
 * @param calendar_event $event The calendar event to get the time range for
 * @param stdClass $instance The module instance to get the range from
 * @return array
 */
function mod_tarefalv_core_calendar_get_valid_event_timestart_range(\calendar_event $event, \stdClass $instance) {
    global $CFG;

    require_once($CFG->dirroot . '/mod/tarefalv/locallib.php');

    $courseid = $event->courseid;
    $modulename = $event->modulename;
    $instanceid = $event->instance;
    $coursemodule = get_fast_modinfo($courseid)->instances[$modulename][$instanceid];
    $context = context_module::instance($coursemodule->id);
    $tarefalv = new tarefalv($context, null, null);
    $tarefalv->set_instance($instance);

    return $tarefalv->get_valid_calendar_event_timestart_range($event);
}

/**
 * This function will update the tarefalv module according to the
 * event that has been modified.
 *
 * @throws \moodle_exception
 * @param \calendar_event $event
 * @param stdClass $instance The module instance to get the range from
 */
function mod_tarefalv_core_calendar_event_timestart_updated(\calendar_event $event, \stdClass $instance) {
    global $CFG, $DB;

    require_once($CFG->dirroot . '/mod/tarefalv/locallib.php');

    if (empty($event->instance) || $event->modulename != 'tarefalv') {
        return;
    }

    if ($instance->id != $event->instance) {
        return;
    }

    if (!in_array($event->eventtype, [TAREFALV_EVENT_TYPE_DUE, TAREFALV_EVENT_TYPE_GRADINGDUE])) {
        return;
    }

    $courseid = $event->courseid;
    $modulename = $event->modulename;
    $instanceid = $event->instance;
    $modified = false;
    $coursemodule = get_fast_modinfo($courseid)->instances[$modulename][$instanceid];
    $context = context_module::instance($coursemodule->id);

    // The user does not have the capability to modify this activity.
    if (!has_capability('moodle/course:manageactivities', $context)) {
        return;
    }

    $tarefalv = new tarefalv($context, $coursemodule, null);
    $tarefalv->set_instance($instance);

    if ($event->eventtype == TAREFALV_EVENT_TYPE_DUE) {
        // This check is in here because due date events are currently
        // the only events that can be overridden, so we can save a DB
        // query if we don't bother checking other events.
        if ($tarefalv->is_override_calendar_event($event)) {
            // This is an override event so we should ignore it.
            return;
        }

        $newduedate = $event->timestart;

        if ($newduedate != $instance->duedate) {
            $instance->duedate = $newduedate;
            $modified = true;
        }
    } else if ($event->eventtype == TAREFALV_EVENT_TYPE_GRADINGDUE) {
        $newduedate = $event->timestart;

        if ($newduedate != $instance->gradingduedate) {
            $instance->gradingduedate = $newduedate;
            $modified = true;
        }
    }

    if ($modified) {
        $instance->timemodified = time();
        // Persist the tarefalv instance changes.
        $DB->update_record('tarefalv', $instance);
        $tarefalv->update_calendar($coursemodule->id);
        $event = \core\event\course_module_updated::create_from_cm($coursemodule, $context);
        $event->trigger();
    }
}
