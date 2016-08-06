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
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.

/**
 * Library of functions and constants for module wikilv
 *
 * It contains the great majority of functions defined by Moodle
 * that are mandatory to develop a module.
 *
 * @package mod-wikilv-2.0
 * @copyrigth 2009 Marc Alier, Jordi Piguillem marc.alier@upc.edu
 * @copyrigth 2009 Universitat Politecnica de Catalunya http://www.upc.edu
 *
 * @author Jordi Piguillem
 * @author Marc Alier
 * @author David Jimenez
 * @author Josep Arus
 * @author Kenneth Riba
 *
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
/** @lvs dependência lvs */
use uab\ifce\lvs\moodle2\business\WikisLv;
use uab\ifce\lvs\moodle2\business\Moodle2CursoLv;

require_once($CFG->dirroot.'/blocks/lvs/biblioteca/lib.php'); // @lvs inclusão do loader dos lvs
/** fim das dependências */


/**
 * Given an object containing all the necessary data,
 * (defined by the form in mod.html) this function
 * will create a new instance and return the id number
 * of the new instance.
 *
 * @param object $instance An object from the form in mod.html
 * @return int The id of the newly inserted wikilv record
 **/
function wikilv_add_instance($wikilv) {
    global $DB;

    $wikilv->timemodified = time();
    # May have to add extra stuff in here #
    
    //	@lvs adicionando período de avaliação lv
    if (empty($wikilv->forceformat)) {
    	$wikilv->forceformat = 0;
    }
    
    /** @lvs adição configuração wikilv */
    if (empty($wikilv->ratingtime)) {
    	$wikilv->assesstimestart  = 0;
    	$wikilv->assesstimefinish = 0;
    }
    /** fim lvs */
    
    //@lvs se o checkbox estiver desmarcado setar para 0
    $wikilv->exibir = (isset($wikilv->exibir)) ? 1 : 0;
    
    $wikilvid = $DB->insert_record('wikilv', $wikilv);
    
    return $wikilvid;
}

/**
 * Given an object containing all the necessary data,
 * (defined by the form in mod.html) this function
 * will update an existing instance with new data.
 *
 * @param object $instance An object from the form in mod.html
 * @return boolean Success/Fail
 **/
function wikilv_update_instance($wikilv) {
    global $DB;

    $wikilv->timemodified = time();
    $wikilv->id = $wikilv->instance;
    if (empty($wikilv->forceformat)) {
        $wikilv->forceformat = 0;
    }

    # May have to add extra stuff in here #
    /** @lvs adição configuração wikilv */
    if (empty($wikilv->ratingtime)) {
    	$wikilv->assesstimestart  = 0;
    	$wikilv->assesstimefinish = 0;
    }
    
//     if ($atual->fator_multiplicativo != $configuracao->fator_multiplicativo) {
//     	$wikilv = new Wikilv($configuracao->id);
//     	$estudantes = $wikilv->recalcularNotas();
//     	$this->_cursolv->atualizarCurso($estudantes);
//     }
    // lvs
    
    //@lvs se o checkbox estiver desmarcado setar para 0
    $wikilv->exibir = (isset($wikilv->exibir)) ? 1 : 0;
    
    return $DB->update_record('wikilv', $wikilv);
}

/**
 * Given an ID of an instance of this module,
 * this function will permanently delete the instance
 * and any data that depends on it.
 *
 * @param int $id Id of the module instance
 * @return boolean Success/Failure
 **/
function wikilv_delete_instance($id) {
    global $DB;

    if (!$wikilv = $DB->get_record('wikilv', array('id' => $id))) {
        return false;
    }

    $result = true;

    # Get subwikilv information #
    $subwikilvs = $DB->get_records('wikilv_subwikilvs', array('wikilvid' => $wikilv->id));

    foreach ($subwikilvs as $subwikilv) {
        # Get existing links, and delete them #
        if (!$DB->delete_records('wikilv_links', array('subwikilvid' => $subwikilv->id), IGNORE_MISSING)) {
            $result = false;
        }

        # Get existing pages #
        if ($pages = $DB->get_records('wikilv_pages', array('subwikilvid' => $subwikilv->id))) {
            foreach ($pages as $page) {
                # Get locks, and delete them #
                if (!$DB->delete_records('wikilv_locks', array('pageid' => $page->id), IGNORE_MISSING)) {
                    $result = false;
                }

                # Get versions, and delete them #
                if (!$DB->delete_records('wikilv_versions', array('pageid' => $page->id), IGNORE_MISSING)) {
                    $result = false;
                }
            }

            # Delete pages #
            if (!$DB->delete_records('wikilv_pages', array('subwikilvid' => $subwikilv->id), IGNORE_MISSING)) {
                $result = false;
            }
        }

        # Get existing synonyms, and delete them #
        if (!$DB->delete_records('wikilv_synonyms', array('subwikilvid' => $subwikilv->id), IGNORE_MISSING)) {
            $result = false;
        }

        # Delete any subwikilvs #
        if (!$DB->delete_records('wikilv_subwikilvs', array('id' => $subwikilv->id), IGNORE_MISSING)) {
            $result = false;
        }
    }

    # Delete any dependent records here #
    /** @lvs remove notas, avaliações e configuração do wikilv */
    $cursolv = new Moodle2CursoLv($wikilv->course);
    $gerenciadorWikis = new WikisLv( new Moodle2CursoLv($wikilv->course) );
    $cursolv->getGerenciador('wikilv')->removerAtividade($wikilv->id);
    // lvs fim
    
    if (!$DB->delete_records('wikilv', array('id' => $wikilv->id))) {
        $result = false;
    }

    return $result;
}

function wikilv_reset_userdata($data) {
    global $CFG,$DB;
    require_once($CFG->dirroot . '/mod/wikilv/pagelib.php');
    require_once($CFG->dirroot . '/tag/lib.php');

    $componentstr = get_string('modulenameplural', 'wikilv');
    $status = array();

    //get the wikilv(s) in this course.
    if (!$wikilvs = $DB->get_records('wikilv', array('course' => $data->courseid))) {
        return false;
    }
    $errors = false;
    foreach ($wikilvs as $wikilv) {

        // remove all comments
        if (!empty($data->reset_wikilv_comments)) {
            if (!$cm = get_coursemodule_from_instance('wikilv', $wikilv->id)) {
                continue;
            }
            $context = context_module::instance($cm->id);
            $DB->delete_records_select('comments', "contextid = ? AND commentarea='wikilv_page'", array($context->id));
            $status[] = array('component'=>$componentstr, 'item'=>get_string('deleteallcomments'), 'error'=>false);
        }

        if (!empty($data->reset_wikilv_tags)) {
            # Get subwikilv information #
            $subwikilvs = $DB->get_records('wikilv_subwikilvs', array('wikilvid' => $wikilv->id));

            foreach ($subwikilvs as $subwikilv) {
                if ($pages = $DB->get_records('wikilv_pages', array('subwikilvid' => $subwikilv->id))) {
                    foreach ($pages as $page) {
                        $tags = tag_get_tags_array('wikilv_pages', $page->id);
                        foreach ($tags as $tagid => $tagname) {
                            // Delete the related tag_instances related to the wikilv page.
                            $errors = tag_delete_instance('wikilv_pages', $page->id, $tagid);
                            $status[] = array('component' => $componentstr, 'item' => get_string('tagsdeleted', 'wikilv'), 'error' => $errors);
                        }
                    }
                }
            }
        }
    }
    return $status;
}


function wikilv_reset_course_form_definition(&$mform) {
    $mform->addElement('header', 'wikilvheader', get_string('modulenameplural', 'wikilv'));
    $mform->addElement('advcheckbox', 'reset_wikilv_tags', get_string('removeallwikilvtags', 'wikilv'));
    $mform->addElement('advcheckbox', 'reset_wikilv_comments', get_string('deleteallcomments'));
}

/**
 * Return a small object with summary information about what a
 * user has done with a given particular instance of this module
 * Used for user activity reports.
 * $return->time = the time they did it
 * $return->info = a short text description
 *
 * @return null
 * @todo Finish documenting this function
 **/
function wikilv_user_outline($course, $user, $mod, $wikilv) {
    $return = NULL;
    return $return;
}

/**
 * Print a detailed representation of what a user has done with
 * a given particular instance of this module, for user activity reports.
 *
 * @return boolean
 * @todo Finish documenting this function
 **/
function wikilv_user_complete($course, $user, $mod, $wikilv) {
    return true;
}

/**
 * Indicates API features that the wikilv supports.
 *
 * @uses FEATURE_GROUPS
 * @uses FEATURE_GROUPINGS
 * @uses FEATURE_GROUPMEMBERSONLY
 * @uses FEATURE_MOD_INTRO
 * @uses FEATURE_COMPLETION_TRACKS_VIEWS
 * @uses FEATURE_COMPLETION_HAS_RULES
 * @uses FEATURE_GRADE_HAS_GRADE
 * @uses FEATURE_GRADE_OUTCOMES
 * @param string $feature
 * @return mixed True if yes (some features may use other values)
 */
function wikilv_supports($feature) {
    switch ($feature) {
    case FEATURE_GROUPS:
        return true;
    case FEATURE_GROUPINGS:
        return true;
    case FEATURE_GROUPMEMBERSONLY:
        return true;
    case FEATURE_MOD_INTRO:
        return true;
    case FEATURE_COMPLETION_TRACKS_VIEWS:
        return true;
    case FEATURE_GRADE_HAS_GRADE:
        return false;
    case FEATURE_GRADE_OUTCOMES:
        return false;
    case FEATURE_RATE:
        return false;
    case FEATURE_BACKUP_MOODLE2:
        return true;
    case FEATURE_SHOW_DESCRIPTION:
        return true;

    default:
        return null;
    }
}

/**
 * Given a course and a time, this module should find recent activity
 * that has occurred in wikilv activities and print it out.
 * Return true if there was output, or false is there was none.
 *
 * @global $CFG
 * @global $DB
 * @uses CONTEXT_MODULE
 * @uses VISIBLEGROUPS
 * @param object $course
 * @param bool $viewfullnames capability
 * @param int $timestart
 * @return boolean
 **/
function wikilv_print_recent_activity($course, $viewfullnames, $timestart) {
    global $CFG, $DB, $OUTPUT;

    $sql = "SELECT p.*, w.id as wikilvid, sw.groupid
            FROM {wikilv_pages} p
                JOIN {wikilv_subwikilvs} sw ON sw.id = p.subwikilvid
                JOIN {wikilv} w ON w.id = sw.wikilvid
            WHERE p.timemodified > ? AND w.course = ?
            ORDER BY p.timemodified ASC";
    if (!$pages = $DB->get_records_sql($sql, array($timestart, $course->id))) {
        return false;
    }
    $modinfo = get_fast_modinfo($course);

    $wikilvs = array();

    $modinfo = get_fast_modinfo($course);

    foreach ($pages as $page) {
        if (!isset($modinfo->instances['wikilv'][$page->wikilvid])) {
            // not visible
            continue;
        }
        $cm = $modinfo->instances['wikilv'][$page->wikilvid];
        if (!$cm->uservisible) {
            continue;
        }
        $context = context_module::instance($cm->id);

        if (!has_capability('mod/wikilv:viewpage', $context)) {
            continue;
        }

        $groupmode = groups_get_activity_groupmode($cm, $course);

        if ($groupmode) {
            if ($groupmode == SEPARATEGROUPS and !has_capability('mod/wikilv:managewikilv', $context)) {
                // separate mode
                if (isguestuser()) {
                    // shortcut
                    continue;
                }

                if (is_null($modinfo->groups)) {
                    $modinfo->groups = groups_get_user_groups($course->id); // load all my groups and cache it in modinfo
                    }

                if (!in_array($page->groupid, $modinfo->groups[0])) {
                    continue;
                }
            }
        }
        $wikilvs[] = $page;
    }
    unset($pages);

    if (!$wikilvs) {
        return false;
    }
    echo $OUTPUT->heading(get_string("updatedwikilvpages", 'wikilv') . ':', 3);
    foreach ($wikilvs as $wikilv) {
        $cm = $modinfo->instances['wikilv'][$wikilv->wikilvid];
        $link = $CFG->wwwroot . '/mod/wikilv/view.php?pageid=' . $wikilv->id;
        print_recent_activity_note($wikilv->timemodified, $wikilv, $cm->name, $link, false, $viewfullnames);
    }

    return true; //  True if anything was printed, otherwise false
}
/**
 * Function to be run periodically according to the moodle cron
 * This function searches for things that need to be done, such
 * as sending out mail, toggling flags etc ...
 *
 * @uses $CFG
 * @return boolean
 * @todo Finish documenting this function
 **/
function wikilv_cron() {
    global $CFG;

    return true;
}

/**
 * Must return an array of grades for a given instance of this module,
 * indexed by user.  It also returns a maximum allowed grade.
 *
 * Example:
 *    $return->grades = array of grades;
 *    $return->maxgrade = maximum allowed grade;
 *
 *    return $return;
 *
 * @param int $wikilvid ID of an instance of this module
 * @return mixed Null or object with an array of grades and with the maximum grade
 **/
function wikilv_grades($wikilvid) {
    return null;
}

/**
 * This function returns if a scale is being used by one wikilv
 * it it has support for grading and scales. Commented code should be
 * modified if necessary. See forum, glossary or journal modules
 * as reference.
 *
 * @param int $wikilvid ID of an instance of this module
 * @return mixed
 * @todo Finish documenting this function
 **/
function wikilv_scale_used($wikilvid, $scaleid) {
    $return = false;

    //$rec = get_record("wikilv","id","$wikilvid","scale","-$scaleid");
    //
    //if (!empty($rec)  && !empty($scaleid)) {
    //    $return = true;
    //}

    return $return;
}

/**
 * Checks if scale is being used by any instance of wikilv.
 * This function was added in 1.9
 *
 * This is used to find out if scale used anywhere
 * @param $scaleid int
 * @return boolean True if the scale is used by any wikilv
 */
function wikilv_scale_used_anywhere($scaleid) {
    global $DB;

    //if ($scaleid and $DB->record_exists('wikilv', array('grade' => -$scaleid))) {
    //    return true;
    //} else {
    //    return false;
    //}

    return false;
}

/**
 * file serving callback
 *
 * @copyright Josep Arus
 * @package  mod_wikilv
 * @category files
 * @param stdClass $course course object
 * @param stdClass $cm course module object
 * @param stdClass $context context object
 * @param string $filearea file area
 * @param array $args extra arguments
 * @param bool $forcedownload whether or not force download
 * @param array $options additional options affecting the file serving
 * @return bool false if the file was not found, just send the file otherwise and do not return anything
 */
function wikilv_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options=array()) {
    global $CFG;

    if ($context->contextlevel != CONTEXT_MODULE) {
        return false;
    }

    require_login($course, true, $cm);

    require_once($CFG->dirroot . "/mod/wikilv/locallib.php");

    if ($filearea == 'attachments') {
        $swid = (int) array_shift($args);

        if (!$subwikilv = wikilv_get_subwikilv($swid)) {
            return false;
        }

        require_capability('mod/wikilv:viewpage', $context);

        $relativepath = implode('/', $args);

        $fullpath = "/$context->id/mod_wikilv/attachments/$swid/$relativepath";

        $fs = get_file_storage();
        if (!$file = $fs->get_file_by_hash(sha1($fullpath)) or $file->is_directory()) {
            return false;
        }

        $lifetime = isset($CFG->filelifetime) ? $CFG->filelifetime : 86400;

        send_stored_file($file, $lifetime, 0, $options);
    }
}

function wikilv_search_form($cm, $search = '') {
    global $CFG, $OUTPUT;

    $output = '<div class="wikilvsearch">';
    $output .= '<form method="post" action="' . $CFG->wwwroot . '/mod/wikilv/search.php" style="display:inline">';
    $output .= '<fieldset class="invisiblefieldset">';
    $output .= '<legend class="accesshide">'. get_string('searchwikilvs', 'wikilv') .'</legend>';
    $output .= '<label class="accesshide" for="searchwikilv">' . get_string("searchterms", "wikilv") . '</label>';
    $output .= '<input id="searchwikilv" name="searchstring" type="text" size="18" value="' . s($search, true) . '" alt="search" />';
    $output .= '<input name="courseid" type="hidden" value="' . $cm->course . '" />';
    $output .= '<input name="cmid" type="hidden" value="' . $cm->id . '" />';
    $output .= '<input name="searchwikilvcontent" type="hidden" value="1" />';
    $output .= '<input value="' . get_string('searchwikilvs', 'wikilv') . '" type="submit" />';
    $output .= '</fieldset>';
    $output .= '</form>';
    $output .= '</div>';

    return $output;
}
function wikilv_extend_navigation(navigation_node $navref, $course, $module, $cm) {
    global $CFG, $PAGE, $USER;

    require_once($CFG->dirroot . '/mod/wikilv/locallib.php');

    $context = context_module::instance($cm->id);
    $url = $PAGE->url;
    $userid = 0;
    if ($module->wikilvmode == 'individual') {
        $userid = $USER->id;
    }

    if (!$wikilv = wikilv_get_wikilv($cm->instance)) {
        return false;
    }

    if (!$gid = groups_get_activity_group($cm)) {
        $gid = 0;
    }
    if (!$subwikilv = wikilv_get_subwikilv_by_group($cm->instance, $gid, $userid)) {
        return null;
    } else {
        $swid = $subwikilv->id;
    }

    $pageid = $url->param('pageid');
    $cmid = $url->param('id');
    if (empty($pageid) && !empty($cmid)) {
        // wikilv main page
        $page = wikilv_get_page_by_title($swid, $wikilv->firstpagetitle);
        $pageid = $page->id;
    }

    if (has_capability('mod/wikilv:createpage', $context)) {
        $link = new moodle_url('/mod/wikilv/create.php', array('action' => 'new', 'swid' => $swid));
        $node = $navref->add(get_string('newpage', 'wikilv'), $link, navigation_node::TYPE_SETTING);
    }

    if (is_numeric($pageid)) {

        if (has_capability('mod/wikilv:viewpage', $context)) {
            $link = new moodle_url('/mod/wikilv/view.php', array('pageid' => $pageid));
            $node = $navref->add(get_string('view', 'wikilv'), $link, navigation_node::TYPE_SETTING);
        }

        if (wikilv_user_can_edit($subwikilv)) {
            $link = new moodle_url('/mod/wikilv/edit.php', array('pageid' => $pageid));
            $node = $navref->add(get_string('edit', 'wikilv'), $link, navigation_node::TYPE_SETTING);
        }

        if (has_capability('mod/wikilv:viewcomment', $context)) {
            $link = new moodle_url('/mod/wikilv/comments.php', array('pageid' => $pageid));
            $node = $navref->add(get_string('comments', 'wikilv'), $link, navigation_node::TYPE_SETTING);
        }

        if (has_capability('mod/wikilv:viewpage', $context)) {
            $link = new moodle_url('/mod/wikilv/history.php', array('pageid' => $pageid));
            $node = $navref->add(get_string('history', 'wikilv'), $link, navigation_node::TYPE_SETTING);
        }

        if (has_capability('mod/wikilv:viewpage', $context)) {
            $link = new moodle_url('/mod/wikilv/map.php', array('pageid' => $pageid));
            $node = $navref->add(get_string('map', 'wikilv'), $link, navigation_node::TYPE_SETTING);
        }

        if (has_capability('mod/wikilv:viewpage', $context)) {
            $link = new moodle_url('/mod/wikilv/files.php', array('pageid' => $pageid));
            $node = $navref->add(get_string('files', 'wikilv'), $link, navigation_node::TYPE_SETTING);
        }

        if (has_capability('mod/wikilv:managewikilv', $context)) {
            $link = new moodle_url('/mod/wikilv/admin.php', array('pageid' => $pageid));
            $node = $navref->add(get_string('admin', 'wikilv'), $link, navigation_node::TYPE_SETTING);
        }
    }
}
/**
 * Returns all other caps used in wikilv module
 *
 * @return array
 */
function wikilv_get_extra_capabilities() {
    return array('moodle/comment:view', 'moodle/comment:post', 'moodle/comment:delete');
}

/**
 * Running addtional permission check on plugin, for example, plugins
 * may have switch to turn on/off comments option, this callback will
 * affect UI display, not like pluginname_comment_validate only throw
 * exceptions.
 * Capability check has been done in comment->check_permissions(), we
 * don't need to do it again here.
 *
 * @package  mod_wikilv
 * @category comment
 *
 * @param stdClass $comment_param {
 *              context  => context the context object
 *              courseid => int course id
 *              cm       => stdClass course module object
 *              commentarea => string comment area
 *              itemid      => int itemid
 * }
 * @return array
 */
function wikilv_comment_permissions($comment_param) {
    return array('post'=>true, 'view'=>true);
}

/**
 * Validate comment parameter before perform other comments actions
 *
 * @param stdClass $comment_param {
 *              context  => context the context object
 *              courseid => int course id
 *              cm       => stdClass course module object
 *              commentarea => string comment area
 *              itemid      => int itemid
 * }
 *
 * @package  mod_wikilv
 * @category comment
 *
 * @return boolean
 */
function wikilv_comment_validate($comment_param) {
    global $DB, $CFG;
    require_once($CFG->dirroot . '/mod/wikilv/locallib.php');
    // validate comment area
    if ($comment_param->commentarea != 'wikilv_page') {
        throw new comment_exception('invalidcommentarea');
    }
    // validate itemid
    if (!$record = $DB->get_record('wikilv_pages', array('id'=>$comment_param->itemid))) {
        throw new comment_exception('invalidcommentitemid');
    }
    if (!$subwikilv = wikilv_get_subwikilv($record->subwikilvid)) {
        throw new comment_exception('invalidsubwikilvid');
    }
    if (!$wikilv = wikilv_get_wikilv_from_pageid($comment_param->itemid)) {
        throw new comment_exception('invalidid', 'data');
    }
    if (!$course = $DB->get_record('course', array('id'=>$wikilv->course))) {
        throw new comment_exception('coursemisconf');
    }
    if (!$cm = get_coursemodule_from_instance('wikilv', $wikilv->id, $course->id)) {
        throw new comment_exception('invalidcoursemodule');
    }
    $context = context_module::instance($cm->id);
    // group access
    if ($subwikilv->groupid) {
        $groupmode = groups_get_activity_groupmode($cm, $course);
        if ($groupmode == SEPARATEGROUPS and !has_capability('moodle/site:accessallgroups', $context)) {
            if (!groups_is_member($subwikilv->groupid)) {
                throw new comment_exception('notmemberofgroup');
            }
        }
    }
    // validate context id
    if ($context->id != $comment_param->context->id) {
        throw new comment_exception('invalidcontext');
    }
    // validation for comment deletion
    if (!empty($comment_param->commentid)) {
        if ($comment = $DB->get_record('comments', array('id'=>$comment_param->commentid))) {
            if ($comment->commentarea != 'wikilv_page') {
                throw new comment_exception('invalidcommentarea');
            }
            if ($comment->contextid != $context->id) {
                throw new comment_exception('invalidcontext');
            }
            if ($comment->itemid != $comment_param->itemid) {
                throw new comment_exception('invalidcommentitemid');
            }
        } else {
            throw new comment_exception('invalidcommentid');
        }
    }
    return true;
}

/**
 * Return a list of page types
 * @param string $pagetype current page type
 * @param stdClass $parentcontext Block's parent context
 * @param stdClass $currentcontext Current context of block
 */
function wikilv_page_type_list($pagetype, $parentcontext, $currentcontext) {
    $module_pagetype = array(
        'mod-wikilv-*'=>get_string('page-mod-wikilv-x', 'wikilv'),
        'mod-wikilv-view'=>get_string('page-mod-wikilv-view', 'wikilv'),
        'mod-wikilv-comments'=>get_string('page-mod-wikilv-comments', 'wikilv'),
        'mod-wikilv-history'=>get_string('page-mod-wikilv-history', 'wikilv'),
        'mod-wikilv-map'=>get_string('page-mod-wikilv-map', 'wikilv')
    );
    return $module_pagetype;
}
