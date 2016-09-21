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
 * Wikilv module external API.
 *
 * @package    mod_wikilv
 * @category   external
 * @copyright  2015 Dani Palou <dani@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.1
 */

defined('MOODLE_INTERNAL') || die;

require_once($CFG->libdir . '/externallib.php');
require_once($CFG->dirroot . '/mod/wikilv/lib.php');
require_once($CFG->dirroot . '/mod/wikilv/locallib.php');

/**
 * Wikilv module external functions.
 *
 * @package    mod_wikilv
 * @category   external
 * @copyright  2015 Dani Palou <dani@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.1
 */
class mod_wikilv_external extends external_api {

    /**
     * Describes the parameters for get_wikilvs_by_courses.
     *
     * @return external_function_parameters
     * @since Moodle 3.1
     */
    public static function get_wikilvs_by_courses_parameters() {
        return new external_function_parameters (
            array(
                'courseids' => new external_multiple_structure(
                    new external_value(PARAM_INT, 'Course ID'), 'Array of course ids.', VALUE_DEFAULT, array()
                ),
            )
        );
    }

    /**
     * Returns a list of wikilvs in a provided list of courses,
     * if no list is provided all wikilvs that the user can view will be returned.
     *
     * @param array $courseids The courses IDs.
     * @return array Containing a list of warnings and a list of wikilvs.
     * @since Moodle 3.1
     */
    public static function get_wikilvs_by_courses($courseids = array()) {

        $returnedwikilvs = array();
        $warnings = array();

        $params = self::validate_parameters(self::get_wikilvs_by_courses_parameters(), array('courseids' => $courseids));

        $mycourses = array();
        if (empty($params['courseids'])) {
            $mycourses = enrol_get_my_courses();
            $params['courseids'] = array_keys($mycourses);
        }

        // Ensure there are courseids to loop through.
        if (!empty($params['courseids'])) {

            list($courses, $warnings) = external_util::validate_courses($params['courseids'], $mycourses);

            // Get the wikilvs in this course, this function checks users visibility permissions.
            // We can avoid then additional validate_context calls.
            $wikilvs = get_all_instances_in_courses('wikilv', $courses);

            foreach ($wikilvs as $wikilv) {

                $context = context_module::instance($wikilv->coursemodule);

                // Entry to return.
                $module = array();

                // First, we return information that any user can see in (or can deduce from) the web interface.
                $module['id'] = $wikilv->id;
                $module['coursemodule'] = $wikilv->coursemodule;
                $module['course'] = $wikilv->course;
                $module['name']  = external_format_string($wikilv->name, $context->id);

                $viewablefields = [];
                if (has_capability('mod/wikilv:viewpage', $context)) {
                    list($module['intro'], $module['introformat']) =
                        external_format_text($wikilv->intro, $wikilv->introformat, $context->id, 'mod_wikilv', 'intro', $wikilv->id);

                    $viewablefields = array('firstpagetitle', 'wikilvmode', 'defaultformat', 'forceformat', 'editbegin', 'editend',
                                            'section', 'visible', 'groupmode', 'groupingid');
                }

                // Check additional permissions for returning optional private settings.
                if (has_capability('moodle/course:manageactivities', $context)) {
                    $additionalfields = array('timecreated', 'timemodified');
                    $viewablefields = array_merge($viewablefields, $additionalfields);
                }

                foreach ($viewablefields as $field) {
                    $module[$field] = $wikilv->{$field};
                }

                // Check if user can add new pages.
                $module['cancreatepages'] = wikilv_can_create_pages($context);

                $returnedwikilvs[] = $module;
            }
        }

        $result = array();
        $result['wikilvs'] = $returnedwikilvs;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Describes the get_wikilvs_by_courses return value.
     *
     * @return external_single_structure
     * @since Moodle 3.1
     */
    public static function get_wikilvs_by_courses_returns() {

        return new external_single_structure(
            array(
                'wikilvs' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'id' => new external_value(PARAM_INT, 'Wikilv ID.'),
                            'coursemodule' => new external_value(PARAM_INT, 'Course module ID.'),
                            'course' => new external_value(PARAM_INT, 'Course ID.'),
                            'name' => new external_value(PARAM_RAW, 'Wikilv name.'),
                            'intro' => new external_value(PARAM_RAW, 'Wikilv intro.', VALUE_OPTIONAL),
                            'introformat' => new external_format_value('Wikilv intro format.', VALUE_OPTIONAL),
                            'timecreated' => new external_value(PARAM_INT, 'Time of creation.', VALUE_OPTIONAL),
                            'timemodified' => new external_value(PARAM_INT, 'Time of last modification.', VALUE_OPTIONAL),
                            'firstpagetitle' => new external_value(PARAM_RAW, 'First page title.', VALUE_OPTIONAL),
                            'wikilvmode' => new external_value(PARAM_TEXT, 'Wikilv mode (individual, collaborative).', VALUE_OPTIONAL),
                            'defaultformat' => new external_value(PARAM_TEXT, 'Wikilv\'s default format (html, creole, nwikilv).',
                                                                            VALUE_OPTIONAL),
                            'forceformat' => new external_value(PARAM_INT, '1 if format is forced, 0 otherwise.',
                                                                            VALUE_OPTIONAL),
                            'editbegin' => new external_value(PARAM_INT, 'Edit begin.', VALUE_OPTIONAL),
                            'editend' => new external_value(PARAM_INT, 'Edit end.', VALUE_OPTIONAL),
                            'section' => new external_value(PARAM_INT, 'Course section ID.', VALUE_OPTIONAL),
                            'visible' => new external_value(PARAM_INT, '1 if visible, 0 otherwise.', VALUE_OPTIONAL),
                            'groupmode' => new external_value(PARAM_INT, 'Group mode.', VALUE_OPTIONAL),
                            'groupingid' => new external_value(PARAM_INT, 'Group ID.', VALUE_OPTIONAL),
                            'cancreatepages' => new external_value(PARAM_BOOL, 'True if user can create pages.'),
                        ), 'Wikilvs'
                    )
                ),
                'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Describes the parameters for view_wikilv.
     *
     * @return external_function_parameters
     * @since Moodle 3.1
     */
    public static function view_wikilv_parameters() {
        return new external_function_parameters (
            array(
                'wikilvid' => new external_value(PARAM_INT, 'Wikilv instance ID.')
            )
        );
    }

    /**
     * Trigger the course module viewed event and update the module completion status.
     *
     * @param int $wikilvid The wikilv instance ID.
     * @return array of warnings and status result.
     * @since Moodle 3.1
     */
    public static function view_wikilv($wikilvid) {

        $params = self::validate_parameters(self::view_wikilv_parameters(),
                                            array(
                                                'wikilvid' => $wikilvid
                                            ));
        $warnings = array();

        // Get wikilv instance.
        if (!$wikilv = wikilv_get_wikilv($params['wikilvid'])) {
            throw new moodle_exception('incorrectwikilvid', 'wikilv');
        }

        // Permission validation.
        list($course, $cm) = get_course_and_cm_from_instance($wikilv, 'wikilv');
        $context = context_module::instance($cm->id);
        self::validate_context($context);

        // Check if user can view this wikilv.
        // We don't use wikilv_user_can_view because it requires to have a valid subwikilv for the user.
        if (!has_capability('mod/wikilv:viewpage', $context)) {
            throw new moodle_exception('cannotviewpage', 'wikilv');
        }

        // Trigger course_module_viewed event and completion.
        wikilv_view($wikilv, $course, $cm, $context);

        $result = array();
        $result['status'] = true;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Describes the view_wikilv return value.
     *
     * @return external_single_structure
     * @since Moodle 3.1
     */
    public static function view_wikilv_returns() {
        return new external_single_structure(
            array(
                'status' => new external_value(PARAM_BOOL, 'Status: true if success.'),
                'warnings' => new external_warnings()
            )
        );
    }

    /**
     * Describes the parameters for view_page.
     *
     * @return external_function_parameters
     * @since Moodle 3.1
     */
    public static function view_page_parameters() {
        return new external_function_parameters (
            array(
                'pageid' => new external_value(PARAM_INT, 'Wikilv page ID.'),
            )
        );
    }

    /**
     * Trigger the page viewed event and update the module completion status.
     *
     * @param int $pageid The page ID.
     * @return array of warnings and status result.
     * @since Moodle 3.1
     * @throws moodle_exception if page is not valid.
     */
    public static function view_page($pageid) {

        $params = self::validate_parameters(self::view_page_parameters(),
                                            array(
                                                'pageid' => $pageid
                                            ));
        $warnings = array();

        // Get wikilv page.
        if (!$page = wikilv_get_page($params['pageid'])) {
            throw new moodle_exception('incorrectpageid', 'wikilv');
        }

        // Get wikilv instance.
        if (!$wikilv = wikilv_get_wikilv_from_pageid($params['pageid'])) {
            throw new moodle_exception('incorrectwikilvid', 'wikilv');
        }

        // Permission validation.
        list($course, $cm) = get_course_and_cm_from_instance($wikilv, 'wikilv');
        $context = context_module::instance($cm->id);
        self::validate_context($context);

        // Check if user can view this wikilv.
        if (!$subwikilv = wikilv_get_subwikilv($page->subwikilvid)) {
            throw new moodle_exception('incorrectsubwikilvid', 'wikilv');
        }
        if (!wikilv_user_can_view($subwikilv, $wikilv)) {
            throw new moodle_exception('cannotviewpage', 'wikilv');
        }

        // Trigger page_viewed event and completion.
        wikilv_page_view($wikilv, $page, $course, $cm, $context);

        $result = array();
        $result['status'] = true;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Describes the view_page return value.
     *
     * @return external_single_structure
     * @since Moodle 3.1
     */
    public static function view_page_returns() {
        return new external_single_structure(
            array(
                'status' => new external_value(PARAM_BOOL, 'Status: true if success.'),
                'warnings' => new external_warnings()
            )
        );
    }

    /**
     * Describes the parameters for get_subwikilvs.
     *
     * @return external_function_parameters
     * @since Moodle 3.1
     */
    public static function get_subwikilvs_parameters() {
        return new external_function_parameters (
            array(
                'wikilvid' => new external_value(PARAM_INT, 'Wikilv instance ID.')
            )
        );
    }

    /**
     * Returns the list of subwikilvs the user can see in a specific wikilv.
     *
     * @param int $wikilvid The wikilv instance ID.
     * @return array Containing a list of warnings and a list of subwikilvs.
     * @since Moodle 3.1
     */
    public static function get_subwikilvs($wikilvid) {
        global $USER;

        $warnings = array();

        $params = self::validate_parameters(self::get_subwikilvs_parameters(), array('wikilvid' => $wikilvid));

        // Get wikilv instance.
        if (!$wikilv = wikilv_get_wikilv($params['wikilvid'])) {
            throw new moodle_exception('incorrectwikilvid', 'wikilv');
        }

        // Validate context and capabilities.
        list($course, $cm) = get_course_and_cm_from_instance($wikilv, 'wikilv');
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/wikilv:viewpage', $context);

        $returnedsubwikilvs = wikilv_get_visible_subwikilvs($wikilv, $cm, $context);
        foreach ($returnedsubwikilvs as $subwikilv) {
            $subwikilv->canedit = wikilv_user_can_edit($subwikilv);
        }

        $result = array();
        $result['subwikilvs'] = $returnedsubwikilvs;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Describes the get_subwikilvs return value.
     *
     * @return external_single_structure
     * @since Moodle 3.1
     */
    public static function get_subwikilvs_returns() {
        return new external_single_structure(
            array(
                'subwikilvs' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'id' => new external_value(PARAM_INT, 'Subwikilv ID.'),
                            'wikilvid' => new external_value(PARAM_INT, 'Wikilv ID.'),
                            'groupid' => new external_value(PARAM_RAW, 'Group ID.'),
                            'userid' => new external_value(PARAM_INT, 'User ID.'),
                            'canedit' => new external_value(PARAM_BOOL, 'True if user can edit the subwikilv.'),
                        ), 'Subwikilvs'
                    )
                ),
                'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Describes the parameters for get_subwikilv_pages.
     *
     * @return external_function_parameters
     * @since Moodle 3.1
     */
    public static function get_subwikilv_pages_parameters() {
        return new external_function_parameters (
            array(
                'wikilvid' => new external_value(PARAM_INT, 'Wikilv instance ID.'),
                'groupid' => new external_value(PARAM_INT, 'Subwikilv\'s group ID, -1 means current group. It will be ignored'
                                        . ' if the wikilv doesn\'t use groups.', VALUE_DEFAULT, -1),
                'userid' => new external_value(PARAM_INT, 'Subwikilv\'s user ID, 0 means current user. It will be ignored'
                                        .' in collaborative wikilvs.', VALUE_DEFAULT, 0),
                'options' => new external_single_structure(
                            array(
                                    'sortby' => new external_value(PARAM_ALPHA,
                                            'Field to sort by (id, title, ...).', VALUE_DEFAULT, 'title'),
                                    'sortdirection' => new external_value(PARAM_ALPHA,
                                            'Sort direction: ASC or DESC.', VALUE_DEFAULT, 'ASC'),
                                    'includecontent' => new external_value(PARAM_INT,
                                            'Include each page contents or just the contents size.', VALUE_DEFAULT, 1),
                            ), 'Options', VALUE_DEFAULT, array()),
            )
        );
    }

    /**
     * Returns the list of pages from a specific subwikilv.
     *
     * @param int $wikilvid The wikilv instance ID.
     * @param int $groupid The group ID. If not defined, use current group.
     * @param int $userid The user ID. If not defined, use current user.
     * @param array $options Several options like sort by, sort direction, ...
     * @return array Containing a list of warnings and a list of pages.
     * @since Moodle 3.1
     */
    public static function get_subwikilv_pages($wikilvid, $groupid = -1, $userid = 0, $options = array()) {

        $returnedpages = array();
        $warnings = array();

        $params = self::validate_parameters(self::get_subwikilv_pages_parameters(),
                                            array(
                                                'wikilvid' => $wikilvid,
                                                'groupid' => $groupid,
                                                'userid' => $userid,
                                                'options' => $options
                                                )
            );

        // Get wikilv instance.
        if (!$wikilv = wikilv_get_wikilv($params['wikilvid'])) {
            throw new moodle_exception('incorrectwikilvid', 'wikilv');
        }
        list($course, $cm) = get_course_and_cm_from_instance($wikilv, 'wikilv');
        $context = context_module::instance($cm->id);
        self::validate_context($context);

        // Determine groupid and userid to use.
        list($groupid, $userid) = self::determine_group_and_user($cm, $wikilv, $params['groupid'], $params['userid']);

        // Get subwikilv and validate it.
        $subwikilv = wikilv_get_subwikilv_by_group_and_user_with_validation($wikilv, $groupid, $userid);

        if ($subwikilv === false) {
            throw new moodle_exception('cannotviewpage', 'wikilv');
        } else if ($subwikilv->id != -1) {

            // Set sort param.
            $options = $params['options'];
            if (!empty($options['sortby'])) {
                if ($options['sortdirection'] != 'ASC' && $options['sortdirection'] != 'DESC') {
                    // Invalid sort direction. Use default.
                    $options['sortdirection'] = 'ASC';
                }
                $sort = $options['sortby'] . ' ' . $options['sortdirection'];
            }

            $pages = wikilv_get_page_list($subwikilv->id, $sort);
            $caneditpages = wikilv_user_can_edit($subwikilv);
            $firstpage = wikilv_get_first_page($subwikilv->id);

            foreach ($pages as $page) {
                $retpage = array(
                        'id' => $page->id,
                        'subwikilvid' => $page->subwikilvid,
                        'title' => external_format_string($page->title, $context->id),
                        'timecreated' => $page->timecreated,
                        'timemodified' => $page->timemodified,
                        'timerendered' => $page->timerendered,
                        'userid' => $page->userid,
                        'pageviews' => $page->pageviews,
                        'readonly' => $page->readonly,
                        'caneditpage' => $caneditpages,
                        'firstpage' => $page->id == $firstpage->id
                    );

                // Refresh page cached content if needed.
                if ($page->timerendered + WIKILV_REFRESH_CACHE_TIME < time()) {
                    if ($content = wikilv_refresh_cachedcontent($page)) {
                        $page = $content['page'];
                    }
                }
                list($cachedcontent, $contentformat) = external_format_text(
                            $page->cachedcontent, FORMAT_HTML, $context->id, 'mod_wikilv', 'attachments', $subwikilv->id);

                if ($options['includecontent']) {
                    // Return the page content.
                    $retpage['cachedcontent'] = $cachedcontent;
                    $retpage['contentformat'] = $contentformat;
                } else {
                    // Return the size of the content.
                    if (function_exists('mb_strlen') && ((int)ini_get('mbstring.func_overload') & 2)) {
                        $retpage['contentsize'] = mb_strlen($cachedcontent, '8bit');
                    } else {
                        $retpage['contentsize'] = strlen($cachedcontent);
                    }
                }

                $returnedpages[] = $retpage;
            }
        }

        $result = array();
        $result['pages'] = $returnedpages;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Describes the get_subwikilv_pages return value.
     *
     * @return external_single_structure
     * @since Moodle 3.1
     */
    public static function get_subwikilv_pages_returns() {

        return new external_single_structure(
            array(
                'pages' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'id' => new external_value(PARAM_INT, 'Page ID.'),
                            'subwikilvid' => new external_value(PARAM_INT, 'Page\'s subwikilv ID.'),
                            'title' => new external_value(PARAM_RAW, 'Page title.'),
                            'timecreated' => new external_value(PARAM_INT, 'Time of creation.'),
                            'timemodified' => new external_value(PARAM_INT, 'Time of last modification.'),
                            'timerendered' => new external_value(PARAM_INT, 'Time of last renderization.'),
                            'userid' => new external_value(PARAM_INT, 'ID of the user that last modified the page.'),
                            'pageviews' => new external_value(PARAM_INT, 'Number of times the page has been viewed.'),
                            'readonly' => new external_value(PARAM_INT, '1 if readonly, 0 otherwise.'),
                            'caneditpage' => new external_value(PARAM_BOOL, 'True if user can edit the page.'),
                            'firstpage' => new external_value(PARAM_BOOL, 'True if it\'s the first page.'),
                            'cachedcontent' => new external_value(PARAM_RAW, 'Page contents.', VALUE_OPTIONAL),
                            'contentformat' => new external_format_value('cachedcontent', VALUE_OPTIONAL),
                            'contentsize' => new external_value(PARAM_INT, 'Size of page contents in bytes (doesn\'t include'.
                                                                            ' size of attached files).', VALUE_OPTIONAL),
                        ), 'Pages'
                    )
                ),
                'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Describes the parameters for get_page_contents.
     *
     * @return external_function_parameters
     * @since Moodle 3.1
     */
    public static function get_page_contents_parameters() {
        return new external_function_parameters (
            array(
                'pageid' => new external_value(PARAM_INT, 'Page ID.')
            )
        );
    }

    /**
     * Get a page contents.
     *
     * @param int $pageid The page ID.
     * @return array of warnings and page data.
     * @since Moodle 3.1
     */
    public static function get_page_contents($pageid) {

        $params = self::validate_parameters(self::get_page_contents_parameters(),
                                            array(
                                                'pageid' => $pageid
                                            )
            );
        $warnings = array();

        // Get wikilv page.
        if (!$page = wikilv_get_page($params['pageid'])) {
            throw new moodle_exception('incorrectpageid', 'wikilv');
        }

        // Get wikilv instance.
        if (!$wikilv = wikilv_get_wikilv_from_pageid($params['pageid'])) {
            throw new moodle_exception('incorrectwikilvid', 'wikilv');
        }

        // Permission validation.
        $cm = get_coursemodule_from_instance('wikilv', $wikilv->id, $wikilv->course);
        $context = context_module::instance($cm->id);
        self::validate_context($context);

        // Check if user can view this wikilv.
        if (!$subwikilv = wikilv_get_subwikilv($page->subwikilvid)) {
            throw new moodle_exception('incorrectsubwikilvid', 'wikilv');
        }
        if (!wikilv_user_can_view($subwikilv, $wikilv)) {
            throw new moodle_exception('cannotviewpage', 'wikilv');
        }

        $returnedpage = array();
        $returnedpage['id'] = $page->id;
        $returnedpage['wikilvid'] = $wikilv->id;
        $returnedpage['subwikilvid'] = $page->subwikilvid;
        $returnedpage['groupid'] = $subwikilv->groupid;
        $returnedpage['userid'] = $subwikilv->userid;
        $returnedpage['title'] = $page->title;

        // Refresh page cached content if needed.
        if ($page->timerendered + WIKILV_REFRESH_CACHE_TIME < time()) {
            if ($content = wikilv_refresh_cachedcontent($page)) {
                $page = $content['page'];
            }
        }

        list($returnedpage['cachedcontent'], $returnedpage['contentformat']) = external_format_text(
                            $page->cachedcontent, FORMAT_HTML, $context->id, 'mod_wikilv', 'attachments', $subwikilv->id);
        $returnedpage['caneditpage'] = wikilv_user_can_edit($subwikilv);

        $result = array();
        $result['page'] = $returnedpage;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Describes the get_page_contents return value.
     *
     * @return external_single_structure
     * @since Moodle 3.1
     */
    public static function get_page_contents_returns() {
        return new external_single_structure(
            array(
                'page' => new external_single_structure(
                    array(
                        'id' => new external_value(PARAM_INT, 'Page ID.'),
                        'wikilvid' => new external_value(PARAM_INT, 'Page\'s wikilv ID.'),
                        'subwikilvid' => new external_value(PARAM_INT, 'Page\'s subwikilv ID.'),
                        'groupid' => new external_value(PARAM_INT, 'Page\'s group ID.'),
                        'userid' => new external_value(PARAM_INT, 'Page\'s user ID.'),
                        'title' => new external_value(PARAM_RAW, 'Page title.'),
                        'cachedcontent' => new external_value(PARAM_RAW, 'Page contents.'),
                        'contentformat' => new external_format_value('cachedcontent', VALUE_OPTIONAL),
                        'caneditpage' => new external_value(PARAM_BOOL, 'True if user can edit the page.')
                    ), 'Page'
                ),
                'warnings' => new external_warnings()
            )
        );
    }

    /**
     * Describes the parameters for get_subwikilv_files.
     *
     * @return external_function_parameters
     * @since Moodle 3.1
     */
    public static function get_subwikilv_files_parameters() {
        return new external_function_parameters (
            array(
                'wikilvid' => new external_value(PARAM_INT, 'Wikilv instance ID.'),
                'groupid' => new external_value(PARAM_INT, 'Subwikilv\'s group ID, -1 means current group. It will be ignored'
                                        . ' if the wikilv doesn\'t use groups.', VALUE_DEFAULT, -1),
                'userid' => new external_value(PARAM_INT, 'Subwikilv\'s user ID, 0 means current user. It will be ignored'
                                        .' in collaborative wikilvs.', VALUE_DEFAULT, 0)
            )
        );
    }

    /**
     * Returns the list of files from a specific subwikilv.
     *
     * @param int $wikilvid The wikilv instance ID.
     * @param int $groupid The group ID. If not defined, use current group.
     * @param int $userid The user ID. If not defined, use current user.
     * @return array Containing a list of warnings and a list of files.
     * @since Moodle 3.1
     * @throws moodle_exception
     */
    public static function get_subwikilv_files($wikilvid, $groupid = -1, $userid = 0) {

        $returnedfiles = array();
        $warnings = array();

        $params = self::validate_parameters(self::get_subwikilv_files_parameters(),
                                            array(
                                                'wikilvid' => $wikilvid,
                                                'groupid' => $groupid,
                                                'userid' => $userid
                                                )
            );

        // Get wikilv instance.
        if (!$wikilv = wikilv_get_wikilv($params['wikilvid'])) {
            throw new moodle_exception('incorrectwikilvid', 'wikilv');
        }
        list($course, $cm) = get_course_and_cm_from_instance($wikilv, 'wikilv');
        $context = context_module::instance($cm->id);
        self::validate_context($context);

        // Determine groupid and userid to use.
        list($groupid, $userid) = self::determine_group_and_user($cm, $wikilv, $params['groupid'], $params['userid']);

        // Get subwikilv and validate it.
        $subwikilv = wikilv_get_subwikilv_by_group_and_user_with_validation($wikilv, $groupid, $userid);

        // Get subwikilv based on group and user.
        if ($subwikilv === false) {
            throw new moodle_exception('cannotviewfiles', 'wikilv');
        } else if ($subwikilv->id != -1) {
            // The subwikilv exists, let's get the files.
            $fs = get_file_storage();
            if ($files = $fs->get_area_files($context->id, 'mod_wikilv', 'attachments', $subwikilv->id, 'filename', false)) {
                foreach ($files as $file) {
                    $filename = $file->get_filename();
                    $fileurl = moodle_url::make_webservice_pluginfile_url(
                                    $context->id, 'mod_wikilv', 'attachments', $subwikilv->id, '/', $filename);

                    $returnedfiles[] = array(
                        'filename' => $filename,
                        'mimetype' => $file->get_mimetype(),
                        'fileurl'  => $fileurl->out(false),
                        'filepath' => $file->get_filepath(),
                        'filesize' => $file->get_filesize(),
                        'timemodified' => $file->get_timemodified()
                    );
                }
            }
        }

        $result = array();
        $result['files'] = $returnedfiles;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Describes the get_subwikilv_pages return value.
     *
     * @return external_single_structure
     * @since Moodle 3.1
     */
    public static function get_subwikilv_files_returns() {

        return new external_single_structure(
            array(
                'files' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'filename' => new external_value(PARAM_FILE, 'File name.'),
                            'filepath' => new external_value(PARAM_PATH, 'File path.'),
                            'filesize' => new external_value(PARAM_INT, 'File size.'),
                            'fileurl' => new external_value(PARAM_URL, 'Downloadable file url.'),
                            'timemodified' => new external_value(PARAM_INT, 'Time modified.'),
                            'mimetype' => new external_value(PARAM_RAW, 'File mime type.'),
                        ), 'Files'
                    )
                ),
                'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Utility function for determining the groupid and userid to use.
     *
     * @param stdClass $cm The course module.
     * @param stdClass $wikilv The wikilv.
     * @param int $groupid Group ID. If not defined, use current group.
     * @param int $userid User ID. If not defined, use current user.
     * @return array Array containing the courseid and userid.
     * @since  Moodle 3.1
     */
    protected static function determine_group_and_user($cm, $wikilv, $groupid = -1, $userid = 0) {
        global $USER;

        $currentgroup = groups_get_activity_group($cm);
        if ($currentgroup === false) {
            // Activity doesn't use groups.
            $groupid = 0;
        } else if ($groupid == -1) {
            // Use current group.
            $groupid = !empty($currentgroup) ? $currentgroup : 0;
        }

        // Determine user.
        if ($wikilv->wikilvmode == 'collaborative') {
            // Collaborative wikilvs don't use userid in subwikilvs.
            $userid = 0;
        } else if (empty($userid)) {
            // Use current user.
            $userid = $USER->id;
        }

        return array($groupid, $userid);
    }

    /**
     * Describes the parameters for get_page_for_editing.
     *
     * @return external_function_parameters
     * @since Moodle 3.1
     */
    public static function get_page_for_editing_parameters() {
        return new external_function_parameters (
            array(
                'pageid' => new external_value(PARAM_INT, 'Page ID to edit.'),
                'section' => new external_value(PARAM_RAW, 'Section page title.', VALUE_DEFAULT, null)
            )
        );
    }

    /**
     * Locks and retrieves info of page-section to be edited.
     *
     * @param int $pageid The page ID.
     * @param string $section Section page title.
     * @return array of warnings and page data.
     * @since Moodle 3.1
     */
    public static function get_page_for_editing($pageid, $section = null) {
        global $USER;

        $params = self::validate_parameters(self::get_page_for_editing_parameters(),
                                            array(
                                                'pageid' => $pageid,
                                                'section' => $section
                                            )
            );

        $warnings = array();

        // Get wikilv page.
        if (!$page = wikilv_get_page($params['pageid'])) {
            throw new moodle_exception('incorrectpageid', 'wikilv');
        }

        // Get wikilv instance.
        if (!$wikilv = wikilv_get_wikilv_from_pageid($params['pageid'])) {
            throw new moodle_exception('incorrectwikilvid', 'wikilv');
        }

        // Get subwikilv instance.
        if (!$subwikilv = wikilv_get_subwikilv($page->subwikilvid)) {
            throw new moodle_exception('incorrectsubwikilvid', 'wikilv');
        }

        // Permission validation.
        $cm = get_coursemodule_from_instance('wikilv', $wikilv->id, $wikilv->course);
        $context = context_module::instance($cm->id);
        self::validate_context($context);

        if (!wikilv_user_can_edit($subwikilv)) {
            throw new moodle_exception('cannoteditpage', 'wikilv');
        }

        if (!wikilv_set_lock($params['pageid'], $USER->id, $params['section'], true)) {
            throw new moodle_exception('pageislocked', 'wikilv');
        }

        $version = wikilv_get_current_version($page->id);
        if (empty($version)) {
            throw new moodle_exception('versionerror', 'wikilv');
        }

        if (!is_null($params['section'])) {
            $content = wikilv_parser_proxy::get_section($version->content, $version->contentformat, $params['section']);
        } else {
            $content = $version->content;
        }

        $pagesection = array();
        $pagesection['content'] = $content;
        $pagesection['contentformat'] = $version->contentformat;
        $pagesection['version'] = $version->version;

        $result = array();
        $result['pagesection'] = $pagesection;
        $result['warnings'] = $warnings;
        return $result;

    }

    /**
     * Describes the get_page_for_editing return value.
     *
     * @return external_single_structure
     * @since Moodle 3.1
     */
    public static function get_page_for_editing_returns() {
        return new external_single_structure(
            array(
                'pagesection' => new external_single_structure(
                    array(
                        'content' => new external_value(PARAM_RAW, 'The contents of the page-section to be edited.'),
                        'contentformat' => new external_value(PARAM_TEXT, 'Format of the original content of the page.'),
                        'version' => new external_value(PARAM_INT, 'Latest version of the page.'),
                        'warnings' => new external_warnings()
                    )
                )
            )
        );
    }

    /**
     * Describes the parameters for new_page.
     *
     * @return external_function_parameters
     * @since Moodle 3.1
     */
    public static function new_page_parameters() {
        return new external_function_parameters (
            array(
                'title' => new external_value(PARAM_TEXT, 'New page title.'),
                'content' => new external_value(PARAM_RAW, 'Page contents.'),
                'contentformat' => new external_value(PARAM_TEXT, 'Page contents format. If an invalid format is provided, default
                    wikilv format is used.', VALUE_DEFAULT, null),
                'subwikilvid' => new external_value(PARAM_INT, 'Page\'s subwikilv ID.', VALUE_DEFAULT, null),
                'wikilvid' => new external_value(PARAM_INT, 'Page\'s wikilv ID. Used if subwikilv does not exists.', VALUE_DEFAULT,
                    null),
                'userid' => new external_value(PARAM_INT, 'Subwikilv\'s user ID. Used if subwikilv does not exists.', VALUE_DEFAULT,
                    null),
                'groupid' => new external_value(PARAM_INT, 'Subwikilv\'s group ID. Used if subwikilv does not exists.', VALUE_DEFAULT,
                    null)
            )
        );
    }

    /**
     * Creates a new page.
     *
     * @param string $title New page title.
     * @param string $content Page contents.
     * @param int $contentformat Page contents format. If an invalid format is provided, default wikilv format is used.
     * @param int $subwikilvid The Subwikilv ID where to store the page.
     * @param int $wikilvid Page\'s wikilv ID. Used if subwikilv does not exists.
     * @param int $userid Subwikilv\'s user ID. Used if subwikilv does not exists.
     * @param int $groupid Subwikilv\'s group ID. Used if subwikilv does not exists.
     * @return array of warnings and page data.
     * @since Moodle 3.1
     */
    public static function new_page($title, $content, $contentformat = null, $subwikilvid = null, $wikilvid = null, $userid = null,
        $groupid = null) {
        global $USER;

        $params = self::validate_parameters(self::new_page_parameters(),
                                            array(
                                                'title' => $title,
                                                'content' => $content,
                                                'contentformat' => $contentformat,
                                                'subwikilvid' => $subwikilvid,
                                                'wikilvid' => $wikilvid,
                                                'userid' => $userid,
                                                'groupid' => $groupid
                                            )
            );

        $warnings = array();

        // Get wikilv and subwikilv instances.
        if (!empty($params['subwikilvid'])) {
            if (!$subwikilv = wikilv_get_subwikilv($params['subwikilvid'])) {
                throw new moodle_exception('incorrectsubwikilvid', 'wikilv');
            }

            if (!$wikilv = wikilv_get_wikilv($subwikilv->wikilvid)) {
                throw new moodle_exception('incorrectwikilvid', 'wikilv');
            }

            // Permission validation.
            $cm = get_coursemodule_from_instance('wikilv', $wikilv->id, $wikilv->course);
            $context = context_module::instance($cm->id);
            self::validate_context($context);

        } else {
            if (!$wikilv = wikilv_get_wikilv($params['wikilvid'])) {
                throw new moodle_exception('incorrectwikilvid', 'wikilv');
            }

            // Permission validation.
            $cm = get_coursemodule_from_instance('wikilv', $wikilv->id, $wikilv->course);
            $context = context_module::instance($cm->id);
            self::validate_context($context);

            // Determine groupid and userid to use.
            list($groupid, $userid) = self::determine_group_and_user($cm, $wikilv, $params['groupid'], $params['userid']);

            // Get subwikilv and validate it.
            $subwikilv = wikilv_get_subwikilv_by_group_and_user_with_validation($wikilv, $groupid, $userid);

            if ($subwikilv === false) {
                // User cannot view page.
                throw new moodle_exception('cannoteditpage', 'wikilv');
            } else if ($subwikilv->id < 0) {
                // Subwikilv needed to check edit permissions.
                if (!wikilv_user_can_edit($subwikilv)) {
                    throw new moodle_exception('cannoteditpage', 'wikilv');
                }

                // Subwikilv does not exists and it can be created.
                $swid = wikilv_add_subwikilv($wikilv->id, $groupid, $userid);
                if (!$subwikilv = wikilv_get_subwikilv($swid)) {
                    throw new moodle_exception('incorrectsubwikilvid', 'wikilv');
                }
            }
        }

        // Subwikilv needed to check edit permissions.
        if (!wikilv_user_can_edit($subwikilv)) {
            throw new moodle_exception('cannoteditpage', 'wikilv');
        }

        if ($page = wikilv_get_page_by_title($subwikilv->id, $params['title'])) {
            throw new moodle_exception('pageexists', 'wikilv');
        }

        // Ignore invalid formats and use default instead.
        if (!$params['contentformat'] || $wikilv->forceformat) {
            $params['contentformat'] = $wikilv->defaultformat;
        } else {
            $formats = wikilv_get_formats();
            if (!in_array($params['contentformat'], $formats)) {
                $params['contentformat'] = $wikilv->defaultformat;
            }
        }

        $newpageid = wikilv_create_page($subwikilv->id, $params['title'], $params['contentformat'], $USER->id);

        if (!$page = wikilv_get_page($newpageid)) {
            throw new moodle_exception('incorrectpageid', 'wikilv');
        }

        // Save content.
        $save = wikilv_save_page($page, $params['content'], $USER->id);

        if (!$save) {
            throw new moodle_exception('savingerror', 'wikilv');
        }

        $result = array();
        $result['pageid'] = $page->id;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Describes the new_page return value.
     *
     * @return external_single_structure
     * @since Moodle 3.1
     */
    public static function new_page_returns() {
        return new external_single_structure(
            array(
                'pageid' => new external_value(PARAM_INT, 'New page id.'),
                'warnings' => new external_warnings()
            )
        );
    }

    /**
     * Describes the parameters for edit_page.
     *
     * @return external_function_parameters
     * @since Moodle 3.1
     */
    public static function edit_page_parameters() {
        return new external_function_parameters (
            array(
                'pageid' => new external_value(PARAM_INT, 'Page ID.'),
                'content' => new external_value(PARAM_RAW, 'Page contents.'),
                'section' => new external_value(PARAM_RAW, 'Section page title.', VALUE_DEFAULT, null)
            )
        );
    }

    /**
     * Edit a page contents.
     *
     * @param int $pageid The page ID.
     * @param string $content Page contents.
     * @param int $section Section to be edited.
     * @return array of warnings and page data.
     * @since Moodle 3.1
     */
    public static function edit_page($pageid, $content, $section = null) {
        global $USER;

        $params = self::validate_parameters(self::edit_page_parameters(),
                                            array(
                                                'pageid' => $pageid,
                                                'content' => $content,
                                                'section' => $section
                                            )
            );
        $warnings = array();

        // Get wikilv page.
        if (!$page = wikilv_get_page($params['pageid'])) {
            throw new moodle_exception('incorrectpageid', 'wikilv');
        }

        // Get wikilv instance.
        if (!$wikilv = wikilv_get_wikilv_from_pageid($params['pageid'])) {
            throw new moodle_exception('incorrectwikilvid', 'wikilv');
        }

        // Get subwikilv instance.
        if (!$subwikilv = wikilv_get_subwikilv($page->subwikilvid)) {
            throw new moodle_exception('incorrectsubwikilvid', 'wikilv');
        }

        // Permission validation.
        $cm = get_coursemodule_from_instance('wikilv', $wikilv->id, $wikilv->course);
        $context = context_module::instance($cm->id);
        self::validate_context($context);

        if (!wikilv_user_can_edit($subwikilv)) {
            throw new moodle_exception('cannoteditpage', 'wikilv');
        }

        if (wikilv_is_page_section_locked($page->id, $USER->id, $params['section'])) {
            throw new moodle_exception('pageislocked', 'wikilv');
        }

        // Save content.
        if (!is_null($params['section'])) {
            $version = wikilv_get_current_version($page->id);
            $content = wikilv_parser_proxy::get_section($version->content, $version->contentformat, $params['section'], false);
            if (!$content) {
                throw new moodle_exception('invalidsection', 'wikilv');
            }

            $save = wikilv_save_section($page, $params['section'], $params['content'], $USER->id);
        } else {
            $save = wikilv_save_page($page, $params['content'], $USER->id);
        }

        wikilv_delete_locks($page->id, $USER->id, $params['section']);

        if (!$save) {
            throw new moodle_exception('savingerror', 'wikilv');
        }

        $result = array();
        $result['pageid'] = $page->id;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Describes the edit_page return value.
     *
     * @return external_single_structure
     * @since Moodle 3.1
     */
    public static function edit_page_returns() {
        return new external_single_structure(
            array(
                'pageid' => new external_value(PARAM_INT, 'Edited page id.'),
                'warnings' => new external_warnings()
            )
        );
    }

}
