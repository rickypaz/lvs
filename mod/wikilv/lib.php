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
 * @package mod_wikilv
 * @copyright 2009 Marc Alier, Jordi Piguillem marc.alier@upc.edu
 * @copyright 2009 Universitat Politecnica de Catalunya http://www.upc.edu
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

    return $DB->insert_record('wikilv', $wikilv);
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

    /** @lvs remove notas, avaliações e configuração do wikilv */
    $cursolv = new Moodle2CursoLv($wikilv->course);
    $gerenciadorWikis = new WikisLv( new Moodle2CursoLv($wikilv->course) );
    $cursolv->getGerenciador('wikilv')->removerAtividade($wikilv->id);
    // lvs fim

    # Delete any dependent records here #
    if (!$DB->delete_records('wikilv', array('id' => $wikilv->id))) {
        $result = false;
    }

    return $result;
}

/**
 * Implements callback to reset course
 *
 * @param stdClass $data
 * @return boolean|array
 */
function wikilv_reset_userdata($data) {
    global $CFG,$DB;
    require_once($CFG->dirroot . '/mod/wikilv/pagelib.php');
    require_once($CFG->dirroot . "/mod/wikilv/locallib.php");

    $componentstr = get_string('modulenameplural', 'wikilv');
    $status = array();

    //get the wikilv(s) in this course.
    if (!$wikilvs = $DB->get_records('wikilv', array('course' => $data->courseid))) {
        return false;
    }
    if (empty($data->reset_wikilv_comments) && empty($data->reset_wikilv_tags) && empty($data->reset_wikilv_pages)) {
        return $status;
    }

    foreach ($wikilvs as $wikilv) {
        if (!$cm = get_coursemodule_from_instance('wikilv', $wikilv->id, $data->courseid)) {
            continue;
        }
        $context = context_module::instance($cm->id);

        // Remove tags or all pages.
        if (!empty($data->reset_wikilv_pages) || !empty($data->reset_wikilv_tags)) {

            // Get subwikilv information.
            $subwikilvs = wikilv_get_subwikilvs($wikilv->id);

            foreach ($subwikilvs as $subwikilv) {
                // Get existing pages.
                if ($pages = wikilv_get_page_list($subwikilv->id)) {
                    // If the wikilv page isn't selected then we are only removing tags.
                    if (empty($data->reset_wikilv_pages)) {
                        // Go through each page and delete the tags.
                        foreach ($pages as $page) {
                            core_tag_tag::remove_all_item_tags('mod_wikilv', 'wikilv_pages', $page->id);
                        }
                    } else {
                        // Otherwise we are removing pages and tags.
                        wikilv_delete_pages($context, $pages, $subwikilv->id);
                    }
                }
                if (!empty($data->reset_wikilv_pages)) {
                    // Delete any subwikilvs.
                    $DB->delete_records('wikilv_subwikilvs', array('id' => $subwikilv->id), IGNORE_MISSING);

                    // Delete any attached files.
                    $fs = get_file_storage();
                    $fs->delete_area_files($context->id, 'mod_wikilv', 'attachments');
                }
            }

            if (!empty($data->reset_wikilv_pages)) {
                $status[] = array('component' => $componentstr, 'item' => get_string('deleteallpages', 'wikilv'),
                    'error' => false);
            }
            if (!empty($data->reset_wikilv_tags)) {
                $status[] = array('component' => $componentstr, 'item' => get_string('tagsdeleted', 'wikilv'), 'error' => false);
            }
        }

        // Remove all comments.
        if (!empty($data->reset_wikilv_comments) || !empty($data->reset_wikilv_pages)) {
            $DB->delete_records_select('comments', "contextid = ? AND commentarea='wikilv_page'", array($context->id));
            if (!empty($data->reset_wikilv_comments)) {
                $status[] = array('component' => $componentstr, 'item' => get_string('deleteallcomments'), 'error' => false);
            }
        }
    }
    return $status;
}


function wikilv_reset_course_form_definition(&$mform) {
    $mform->addElement('header', 'wikilvheader', get_string('modulenameplural', 'wikilv'));
    $mform->addElement('advcheckbox', 'reset_wikilv_pages', get_string('deleteallpages', 'wikilv'));
    $mform->addElement('advcheckbox', 'reset_wikilv_tags', get_string('removeallwikilvtags', 'wikilv'));
    $mform->addElement('advcheckbox', 'reset_wikilv_comments', get_string('deleteallcomments'));
}

/**
 * Indicates API features that the wikilv supports.
 *
 * @uses FEATURE_GROUPS
 * @uses FEATURE_GROUPINGS
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

    $sql = "SELECT p.id, p.timemodified, p.subwikilvid, sw.wikilvid, w.wikilvmode, sw.userid, sw.groupid
            FROM {wikilv_pages} p
                JOIN {wikilv_subwikilvs} sw ON sw.id = p.subwikilvid
                JOIN {wikilv} w ON w.id = sw.wikilvid
            WHERE p.timemodified > ? AND w.course = ?
            ORDER BY p.timemodified ASC";
    if (!$pages = $DB->get_records_sql($sql, array($timestart, $course->id))) {
        return false;
    }
    require_once($CFG->dirroot . "/mod/wikilv/locallib.php");

    $wikilvs = array();

    $modinfo = get_fast_modinfo($course);

    $subwikilvvisible = array();
    foreach ($pages as $page) {
        if (!isset($subwikilvvisible[$page->subwikilvid])) {
            $subwikilv = (object)array('id' => $page->subwikilvid, 'wikilvid' => $page->wikilvid,
                'groupid' => $page->groupid, 'userid' => $page->userid);
            $wikilv = (object)array('id' => $page->wikilvid, 'course' => $course->id, 'wikilvmode' => $page->wikilvmode);
            $subwikilvvisible[$page->subwikilvid] = wikilv_user_can_view($subwikilv, $wikilv);
        }
        if ($subwikilvvisible[$page->subwikilvid]) {
            $wikilvs[] = $page;
        }
    }
    unset($subwikilvvisible);
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

        send_stored_file($file, null, 0, $options);
    }
}

function wikilv_search_form($cm, $search = '', $subwikilv = null) {
    global $CFG, $OUTPUT;

    $output = '<div class="wikilvsearch">';
    $output .= '<form method="post" action="' . $CFG->wwwroot . '/mod/wikilv/search.php" style="display:inline">';
    $output .= '<fieldset class="invisiblefieldset">';
    $output .= '<legend class="accesshide">'. get_string('searchwikilvs', 'wikilv') .'</legend>';
    $output .= '<label class="accesshide" for="searchwikilv">' . get_string("searchterms", "wikilv") . '</label>';
    $output .= '<input id="searchwikilv" name="searchstring" type="text" size="18" value="' . s($search, true) . '" alt="search" />';
    $output .= '<input name="courseid" type="hidden" value="' . $cm->course . '" />';
    $output .= '<input name="cmid" type="hidden" value="' . $cm->id . '" />';
    if (!empty($subwikilv->id)) {
        $output .= '<input name="subwikilvid" type="hidden" value="' . $subwikilv->id . '" />';
    }
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

    if (wikilv_can_create_pages($context)) {
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

/**
 * Mark the activity completed (if required) and trigger the course_module_viewed event.
 *
 * @param  stdClass $wikilv       Wikilv object.
 * @param  stdClass $course     Course object.
 * @param  stdClass $cm         Course module object.
 * @param  stdClass $context    Context object.
 * @since Moodle 3.1
 */
function wikilv_view($wikilv, $course, $cm, $context) {
    // Trigger course_module_viewed event.
    $params = array(
        'context' => $context,
        'objectid' => $wikilv->id
    );
    $event = \mod_wikilv\event\course_module_viewed::create($params);
    $event->add_record_snapshot('course_modules', $cm);
    $event->add_record_snapshot('course', $course);
    $event->add_record_snapshot('wikilv', $wikilv);
    $event->trigger();

    // Completion.
    $completion = new completion_info($course);
    $completion->set_module_viewed($cm);
}

/**
 * Mark the activity completed (if required) and trigger the page_viewed event.
 *
 * @param  stdClass $wikilv       Wikilv object.
 * @param  stdClass $page       Page object.
 * @param  stdClass $course     Course object.
 * @param  stdClass $cm         Course module object.
 * @param  stdClass $context    Context object.
 * @param  int $uid             Optional User ID.
 * @param  array $other         Optional Other params: title, wikilv ID, group ID, groupanduser, prettyview.
 * @param  stdClass $subwikilv    Optional Subwikilv.
 * @since Moodle 3.1
 */
function wikilv_page_view($wikilv, $page, $course, $cm, $context, $uid = null, $other = null, $subwikilv = null) {

    // Trigger course_module_viewed event.
    $params = array(
        'context' => $context,
        'objectid' => $page->id
    );
    if ($uid != null) {
        $params['relateduserid'] = $uid;
    }
    if ($other != null) {
        $params['other'] = $other;
    }

    $event = \mod_wikilv\event\page_viewed::create($params);

    $event->add_record_snapshot('wikilv_pages', $page);
    $event->add_record_snapshot('course_modules', $cm);
    $event->add_record_snapshot('course', $course);
    $event->add_record_snapshot('wikilv', $wikilv);
    if ($subwikilv != null) {
        $event->add_record_snapshot('wikilv_subwikilvs', $subwikilv);
    }
    $event->trigger();

    // Completion.
    $completion = new completion_info($course);
    $completion->set_module_viewed($cm);
}
