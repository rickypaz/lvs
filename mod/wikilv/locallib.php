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
 * This contains functions and classes that will be used by scripts in wikilv module
 *
 * @package mod-wikilv-2.0
 * @copyrigth 2009 Marc Alier, Jordi Piguillem marc.alier@upc.edu
 * @copyrigth 2009 Universitat Politecnica de Catalunya http://www.upc.edu
 *
 * @author Jordi Piguillem
 * @author Marc Alier
 * @author David Jimenez
 * @author Josep Arus
 * @author Daniel Serrano
 * @author Kenneth Riba
 *
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once($CFG->dirroot . '/mod/wikilv/lib.php');
require_once($CFG->dirroot . '/mod/wikilv/parser/parser.php');
require_once($CFG->libdir . '/filelib.php');

define('WIKILV_REFRESH_CACHE_TIME', 30); // @TODO: To be deleted.
define('FORMAT_CREOLE', '37');
define('FORMAT_NWIKILV', '38');
define('NO_VALID_RATE', '-999');
define('IMPROVEMENT', '+');
define('EQUAL', '=');
define('WORST', '-');

define('LOCK_TIMEOUT', 30);

/**
 * Get a wikilv instance
 * @param int $wikilvid the instance id of wikilv
 */
function wikilv_get_wikilv($wikilvid) {
    global $DB;

    return $DB->get_record('wikilv', array('id' => $wikilvid));
}

/**
 * Get sub wikilv instances with same wikilv id
 * @param int $wikilvid
 */
function wikilv_get_subwikilvs($wikilvid) {
    global $DB;
    return $DB->get_records('wikilv_subwikilvs', array('wikilvid' => $wikilvid));
}

/**
 * Get a sub wikilv instance by wikilv id and group id
 * @param int $wikilvid
 * @param int $groupid
 * @return object
 */
function wikilv_get_subwikilv_by_group($wikilvid, $groupid, $userid = 0) {
    global $DB;
    return $DB->get_record('wikilv_subwikilvs', array('wikilvid' => $wikilvid, 'groupid' => $groupid, 'userid' => $userid));
}

/**
 * Get a sub wikilv instace by instance id
 * @param int $subwikilvid
 * @return object
 */
function wikilv_get_subwikilv($subwikilvid) {
    global $DB;
    return $DB->get_record('wikilv_subwikilvs', array('id' => $subwikilvid));

}

/**
 * Add a new sub wikilv instance
 * @param int $wikilvid
 * @param int $groupid
 * @return int $insertid
 */
function wikilv_add_subwikilv($wikilvid, $groupid, $userid = 0) {
    global $DB;

    $record = new StdClass();
    $record->wikilvid = $wikilvid;
    $record->groupid = $groupid;
    $record->userid = $userid;

    $insertid = $DB->insert_record('wikilv_subwikilvs', $record);
    return $insertid;
}

/**
 * Get a wikilv instance by pageid
 * @param int $pageid
 * @return object
 */
function wikilv_get_wikilv_from_pageid($pageid) {
    global $DB;

    $sql = "SELECT w.*
            FROM {wikilv} w, {wikilv_subwikilvs} s, {wikilv_pages} p
            WHERE p.id = ? AND
            p.subwikilvid = s.id AND
            s.wikilvid = w.id";

    return $DB->get_record_sql($sql, array($pageid));
}

/**
 * Get a wikilv page by pageid
 * @param int $pageid
 * @return object
 */
function wikilv_get_page($pageid) {
    global $DB;
    return $DB->get_record('wikilv_pages', array('id' => $pageid));
}

/**
 * Get latest version of wikilv page
 * @param int $pageid
 * @return object
 */
function wikilv_get_current_version($pageid) {
    global $DB;

    // @TODO: Fix this query
    $sql = "SELECT *
            FROM {wikilv_versions}
            WHERE pageid = ?
            ORDER BY version DESC";
    $records = $DB->get_records_sql($sql, array($pageid), 0, 1);
    return array_pop($records);

}

/**
 * Alias of wikilv_get_current_version
 * @TODO, does the exactly same thing as wikilv_get_current_version, should be removed
 * @param int $pageid
 * @return object
 */
function wikilv_get_last_version($pageid) {
    return wikilv_get_current_version($pageid);
}

/**
 * Get page section
 * @param int $pageid
 * @param string $section
 */
function wikilv_get_section_page($page, $section) {

    $version = wikilv_get_current_version($page->id);
    return wikilv_parser_proxy::get_section($version->content, $version->contentformat, $section);
}

/**
 * Get a wikilv page by page title
 * @param int $swid, sub wikilv id
 * @param string $title
 * @return object
 */
function wikilv_get_page_by_title($swid, $title) {
    global $DB;
    return $DB->get_record('wikilv_pages', array('subwikilvid' => $swid, 'title' => $title));
}

/**
 * Get a version record by record id
 * @param int $versionid, the version id
 * @return object
 */
function wikilv_get_version($versionid) {
    global $DB;
    return $DB->get_record('wikilv_versions', array('id' => $versionid));
}

/**
 * Get first page of wikilv instace
 * @param int $subwikilvid
 * @param int $module, wikilv instance object
 */
function wikilv_get_first_page($subwikilvd, $module = null) {
    global $DB, $USER;

    $sql = "SELECT p.*
            FROM {wikilv} w, {wikilv_subwikilvs} s, {wikilv_pages} p
            WHERE s.id = ? AND
            s.wikilvid = w.id AND
            w.firstpagetitle = p.title AND
            p.subwikilvid = s.id";
    return $DB->get_record_sql($sql, array($subwikilvd));
}

function wikilv_save_section($wikilvpage, $sectiontitle, $sectioncontent, $userid) {

    $wikilv = wikilv_get_wikilv_from_pageid($wikilvpage->id);
    $cm = get_coursemodule_from_instance('wikilv', $wikilv->id);
    $context = context_module::instance($cm->id);

    if (has_capability('mod/wikilv:editpage', $context)) {
        $version = wikilv_get_current_version($wikilvpage->id);
        $content = wikilv_parser_proxy::get_section($version->content, $version->contentformat, $sectiontitle, true);

        $newcontent = $content[0] . $sectioncontent . $content[2];

        return wikilv_save_page($wikilvpage, $newcontent, $userid);
    } else {
        return false;
    }
}

/**
 * Save page content
 * @param object $wikilvpage
 * @param string $newcontent
 * @param int $userid
 */
function wikilv_save_page($wikilvpage, $newcontent, $userid) {
    global $DB;

    $wikilv = wikilv_get_wikilv_from_pageid($wikilvpage->id);
    $cm = get_coursemodule_from_instance('wikilv', $wikilv->id);
    $context = context_module::instance($cm->id);

    if (has_capability('mod/wikilv:editpage', $context)) {
        $version = wikilv_get_current_version($wikilvpage->id);

        $version->content = $newcontent;
        $version->userid = $userid;
        $version->version++;
        $version->timecreated = time();
        $versionid = $DB->insert_record('wikilv_versions', $version);

        $wikilvpage->timemodified = $version->timecreated;
        $wikilvpage->userid = $userid;
        $return = wikilv_refresh_cachedcontent($wikilvpage, $newcontent);

        return $return;
    } else {
        return false;
    }
}

function wikilv_refresh_cachedcontent($page, $newcontent = null) {
    global $DB;

    $version = wikilv_get_current_version($page->id);
    if (empty($version)) {
        return null;
    }
    if (!isset($newcontent)) {
        $newcontent = $version->content;
    }

    $options = array('swid' => $page->subwikilvid, 'pageid' => $page->id);
    $parseroutput = wikilv_parse_content($version->contentformat, $newcontent, $options);
    $page->cachedcontent = $parseroutput['toc'] . $parseroutput['parsed_text'];
    $page->timerendered = time();
    $DB->update_record('wikilv_pages', $page);

    wikilv_refresh_page_links($page, $parseroutput['link_count']);

    return array('page' => $page, 'sections' => $parseroutput['repeated_sections'], 'version' => $version->version);
}
/**
 * Restore a page
 */
function wikilv_restore_page($wikilvpage, $newcontent, $userid) {
    $return = wikilv_save_page($wikilvpage, $newcontent, $userid);
    return $return['page'];
}

function wikilv_refresh_page_links($page, $links) {
    global $DB;

    $DB->delete_records('wikilv_links', array('frompageid' => $page->id));
    foreach ($links as $linkname => $linkinfo) {

        $newlink = new stdClass();
        $newlink->subwikilvid = $page->subwikilvid;
        $newlink->frompageid = $page->id;

        if ($linkinfo['new']) {
            $newlink->tomissingpage = $linkname;
        } else {
            $newlink->topageid = $linkinfo['pageid'];
        }

        try {
            $DB->insert_record('wikilv_links', $newlink);
        } catch (dml_exception $e) {
            debugging($e->getMessage());
        }

    }
}

/**
 * Create a new wikilv page, if the page exists, return existing pageid
 * @param int $swid
 * @param string $title
 * @param string $format
 * @param int $userid
 */
function wikilv_create_page($swid, $title, $format, $userid) {
    global $DB;
    $subwikilv = wikilv_get_subwikilv($swid);
    $cm = get_coursemodule_from_instance('wikilv', $subwikilv->wikilvid);
    $context = context_module::instance($cm->id);
    require_capability('mod/wikilv:editpage', $context);
    // if page exists
    if ($page = wikilv_get_page_by_title($swid, $title)) {
        return $page->id;
    }

    // Creating a new empty version
    $version = new stdClass();
    $version->content = '';
    $version->contentformat = $format;
    $version->version = 0;
    $version->timecreated = time();
    $version->userid = $userid;

    $versionid = null;
    $versionid = $DB->insert_record('wikilv_versions', $version);

    // Createing a new empty page
    $page = new stdClass();
    $page->subwikilvid = $swid;
    $page->title = $title;
    $page->cachedcontent = '';
    $page->timecreated = $version->timecreated;
    $page->timemodified = $version->timecreated;
    $page->timerendered = $version->timecreated;
    $page->userid = $userid;
    $page->pageviews = 0;
    $page->readonly = 0;

    $pageid = $DB->insert_record('wikilv_pages', $page);

    // Setting the pageid
    $version->id = $versionid;
    $version->pageid = $pageid;
    $DB->update_record('wikilv_versions', $version);

    wikilv_make_cache_expire($page->title);
    return $pageid;
}

function wikilv_make_cache_expire($pagename) {
    global $DB;

    $sql = "UPDATE {wikilv_pages}
            SET timerendered = 0
            WHERE id IN ( SELECT l.frompageid
                FROM {wikilv_links} l
                WHERE l.tomissingpage = ?
            )";
    $DB->execute ($sql, array($pagename));
}

/**
 * Get a specific version of page
 * @param int $pageid
 * @param int $version
 */
function wikilv_get_wikilv_page_version($pageid, $version) {
    global $DB;
    return $DB->get_record('wikilv_versions', array('pageid' => $pageid, 'version' => $version));
}

/**
 * Get version list
 * @param int $pageid
 * @param int $limitfrom
 * @param int $limitnum
 */
function wikilv_get_wikilv_page_versions($pageid, $limitfrom, $limitnum) {
    global $DB;
    return $DB->get_records('wikilv_versions', array('pageid' => $pageid), 'version DESC', '*', $limitfrom, $limitnum);
}

/**
 * Count the number of page version
 * @param int $pageid
 */
function wikilv_count_wikilv_page_versions($pageid) {
    global $DB;
    return $DB->count_records('wikilv_versions', array('pageid' => $pageid));
}

/**
 * Get linked from page
 * @param int $pageid
 */
function wikilv_get_linked_to_pages($pageid) {
    global $DB;
    return $DB->get_records('wikilv_links', array('frompageid' => $pageid));
}

/**
 * Get linked from page
 * @param int $pageid
 */
function wikilv_get_linked_from_pages($pageid) {
    global $DB;
    return $DB->get_records('wikilv_links', array('topageid' => $pageid));
}

/**
 * Get pages which user have been edited
 * @param int $swid
 * @param int $userid
 */
function wikilv_get_contributions($swid, $userid) {
    global $DB;

    $sql = "SELECT v.*
            FROM {wikilv_versions} v, {wikilv_pages} p
            WHERE p.subwikilvid = ? AND
            v.pageid = p.id AND
            v.userid = ?";

    return $DB->get_records_sql($sql, array($swid, $userid));
}

/**
 * Get missing or empty pages in wikilv
 * @param int $swid sub wikilv id
 */
function wikilv_get_missing_or_empty_pages($swid) {
    global $DB;

    $sql = "SELECT DISTINCT p.title, p.id, p.subwikilvid
            FROM {wikilv} w, {wikilv_subwikilvs} s, {wikilv_pages} p
            WHERE s.wikilvid = w.id and
            s.id = ? and
            w.firstpagetitle != p.title and
            p.subwikilvid = ? and
            1 =  (SELECT count(*)
                FROM {wikilv_versions} v
                WHERE v.pageid = p.id)
            UNION
            SELECT DISTINCT l.tomissingpage as title, 0 as id, l.subwikilvid
            FROM {wikilv_links} l
            WHERE l.subwikilvid = ? and
            l.topageid = 0";

    return $DB->get_records_sql($sql, array($swid, $swid, $swid));
}

/**
 * Get pages list in wikilv
 * @param int $swid sub wikilv id
 */
function wikilv_get_page_list($swid) {
    global $DB;
    $records = $DB->get_records('wikilv_pages', array('subwikilvid' => $swid), 'title ASC');
    return $records;
}

/**
 * Return a list of orphaned wikilvs for one specific subwikilv
 * @global object
 * @param int $swid sub wikilv id
 */
function wikilv_get_orphaned_pages($swid) {
    global $DB;

    $sql = "SELECT p.id, p.title
            FROM {wikilv_pages} p, {wikilv} w , {wikilv_subwikilvs} s
            WHERE p.subwikilvid = ?
            AND s.id = ?
            AND w.id = s.wikilvid
            AND p.title != w.firstpagetitle
            AND p.id NOT IN (SELECT topageid FROM {wikilv_links} WHERE subwikilvid = ?)";

    return $DB->get_records_sql($sql, array($swid, $swid, $swid));
}

/**
 * Search wikilv title
 * @param int $swid sub wikilv id
 * @param string $search
 */
function wikilv_search_title($swid, $search) {
    global $DB;

    return $DB->get_records_select('wikilv_pages', "subwikilvid = ? AND title LIKE ?", array($swid, '%'.$search.'%'));
}

/**
 * Search wikilv content
 * @param int $swid sub wikilv id
 * @param string $search
 */
function wikilv_search_content($swid, $search) {
    global $DB;

    return $DB->get_records_select('wikilv_pages', "subwikilvid = ? AND cachedcontent LIKE ?", array($swid, '%'.$search.'%'));
}

/**
 * Search wikilv title and content
 * @param int $swid sub wikilv id
 * @param string $search
 */
function wikilv_search_all($swid, $search) {
    global $DB;

    return $DB->get_records_select('wikilv_pages', "subwikilvid = ? AND (cachedcontent LIKE ? OR title LIKE ?)", array($swid, '%'.$search.'%', '%'.$search.'%'));
}

/**
 * Get user data
 */
function wikilv_get_user_info($userid) {
    global $DB;
    return $DB->get_record('user', array('id' => $userid));
}

/**
 * Increase page view nubmer
 * @param int $page, database record
 */
function wikilv_increment_pageviews($page) {
    global $DB;

    $page->pageviews++;
    $DB->update_record('wikilv_pages', $page);
}

//----------------------------------------------------------
//----------------------------------------------------------

/**
 * Text format supported by wikilv module
 */
function wikilv_get_formats() {
    return array('html', 'creole', 'nwikilv');
}

/**
 * Parses a string with the wikilv markup language in $markup.
 *
 * @return Array or false when something wrong has happened.
 *
 * Returned array contains the following fields:
 *     'parsed_text' => String. Contains the parsed wikilv content.
 *     'unparsed_text' => String. Constains the original wikilv content.
 *     'link_count' => Array of array('destination' => ..., 'new' => "is new?"). Contains the internal wikilv links found in the wikilv content.
 *      'deleted_sections' => the list of deleted sections.
 *              '' =>
 *
 * @author Josep Arús Pous
 **/
function wikilv_parse_content($markup, $pagecontent, $options = array()) {
    global $PAGE;

    $subwikilv = wikilv_get_subwikilv($options['swid']);
    $cm = get_coursemodule_from_instance("wikilv", $subwikilv->wikilvid);
    $context = context_module::instance($cm->id);

    $parser_options = array(
        'link_callback' => '/mod/wikilv/locallib.php:wikilv_parser_link',
        'link_callback_args' => array('swid' => $options['swid']),
        'table_callback' => '/mod/wikilv/locallib.php:wikilv_parser_table',
        'real_path_callback' => '/mod/wikilv/locallib.php:wikilv_parser_real_path',
        'real_path_callback_args' => array(
            'context' => $context,
            'component' => 'mod_wikilv',
            'filearea' => 'attachments',
            'subwikilvid'=> $subwikilv->id,
            'pageid' => $options['pageid']
        ),
        'pageid' => $options['pageid'],
        'pretty_print' => (isset($options['pretty_print']) && $options['pretty_print']),
        'printable' => (isset($options['printable']) && $options['printable'])
    );

    return wikilv_parser_proxy::parse($pagecontent, $markup, $parser_options);
}

/**
 * This function is the parser callback to parse wikilv links.
 *
 * It returns the necesary information to print a link.
 *
 * NOTE: Empty pages and non-existent pages must be print in red color.
 *
 * !!!!!! IMPORTANT !!!!!!
 * It is critical that you call format_string on the content before it is used.
 *
 * @param string|page_wikilv $link name of a page
 * @param array $options
 * @return array Array('content' => string, 'url' => string, 'new' => bool, 'link_info' => array)
 *
 * @TODO Doc return and options
 */
function wikilv_parser_link($link, $options = null) {
    global $CFG;

    if (is_object($link)) {
        $parsedlink = array('content' => $link->title, 'url' => $CFG->wwwroot . '/mod/wikilv/view.php?pageid=' . $link->id, 'new' => false, 'link_info' => array('link' => $link->title, 'pageid' => $link->id, 'new' => false));

        $version = wikilv_get_current_version($link->id);
        if ($version->version == 0) {
            $parsedlink['new'] = true;
        }
        return $parsedlink;
    } else {
        $swid = $options['swid'];

        if ($page = wikilv_get_page_by_title($swid, $link)) {
            $parsedlink = array('content' => $link, 'url' => $CFG->wwwroot . '/mod/wikilv/view.php?pageid=' . $page->id, 'new' => false, 'link_info' => array('link' => $link, 'pageid' => $page->id, 'new' => false));

            $version = wikilv_get_current_version($page->id);
            if ($version->version == 0) {
                $parsedlink['new'] = true;
            }

            return $parsedlink;

        } else {
            return array('content' => $link, 'url' => $CFG->wwwroot . '/mod/wikilv/create.php?swid=' . $swid . '&amp;title=' . urlencode($link) . '&amp;action=new', 'new' => true, 'link_info' => array('link' => $link, 'new' => true, 'pageid' => 0));
        }
    }
}

/**
 * Returns the table fully parsed (HTML)
 *
 * @return HTML for the table $table
 * @author Josep Arús Pous
 *
 **/
function wikilv_parser_table($table) {
    global $OUTPUT;

    $htmltable = new html_table();

    $headers = $table[0];
    $htmltable->head = array();
    foreach ($headers as $h) {
        $htmltable->head[] = $h[1];
    }

    array_shift($table);
    $htmltable->data = array();
    foreach ($table as $row) {
        $row_data = array();
        foreach ($row as $r) {
            $row_data[] = $r[1];
        }
        $htmltable->data[] = $row_data;
    }

    return html_writer::table($htmltable);
}

/**
 * Returns an absolute path link, unless there is no such link.
 *
 * @param string $url Link's URL or filename
 * @param stdClass $context filearea params
 * @param string $component The component the file is associated with
 * @param string $filearea The filearea the file is stored in
 * @param int $swid Sub wikilv id
 *
 * @return string URL for files full path
 */

function wikilv_parser_real_path($url, $context, $component, $filearea, $swid) {
    global $CFG;

    if (preg_match("/^(?:http|ftp)s?\:\/\//", $url)) {
        return $url;
    } else {

        $file = 'pluginfile.php';
        if (!$CFG->slasharguments) {
            $file = $file . '?file=';
        }
        $baseurl = "$CFG->wwwroot/$file/{$context->id}/$component/$filearea/$swid/";
        // it is a file in current file area
        return $baseurl . $url;
    }
}

/**
 * Returns the token used by a wikilv language to represent a given tag or "object" (bold -> **)
 *
 * @return A string when it has only one token at the beginning (f. ex. lists). An array composed by 2 strings when it has 2 tokens, one at the beginning and one at the end (f. ex. italics). Returns false otherwise.
 * @author Josep Arús Pous
 **/
function wikilv_parser_get_token($markup, $name) {

    return wikilv_parser_proxy::get_token($name, $markup);
}

/**
 * Checks if current user can view a subwikilv
 *
 * @param $subwikilv
 */
function wikilv_user_can_view($subwikilv) {
    global $USER;

    $wikilv = wikilv_get_wikilv($subwikilv->wikilvid);
    $cm = get_coursemodule_from_instance('wikilv', $wikilv->id);
    $context = context_module::instance($cm->id);

    // Working depending on activity groupmode
    switch (groups_get_activity_groupmode($cm)) {
    case NOGROUPS:

        if ($wikilv->wikilvmode == 'collaborative') {
            // Collaborative Mode:
            // There is one wikilv for all the class.
            //
            // Only view capbility needed
            return has_capability('mod/wikilv:viewpage', $context);
        } else if ($wikilv->wikilvmode == 'individual') {
            // Individual Mode:
            // Each person owns a wikilv.
            if ($subwikilv->userid == $USER->id) {
                // Only the owner of the wikilv can view it
                return has_capability('mod/wikilv:viewpage', $context);
            } else { // User has special capabilities
                // User must have:
                //      mod/wikilv:viewpage capability
                // and
                //      mod/wikilv:managewikilv capability
                $view = has_capability('mod/wikilv:viewpage', $context);
                $manage = has_capability('mod/wikilv:managewikilv', $context);

                return $view && $manage;
            }
        } else {
            //Error
            return false;
        }
    case SEPARATEGROUPS:
        // Collaborative and Individual Mode
        //
        // Collaborative Mode:
        //      There is one wikilv per group.
        // Individual Mode:
        //      Each person owns a wikilv.
        if ($wikilv->wikilvmode == 'collaborative' || $wikilv->wikilvmode == 'individual') {
            // Only members of subwikilv group could view that wikilv
            if (groups_is_member($subwikilv->groupid)) {
                // Only view capability needed
                return has_capability('mod/wikilv:viewpage', $context);

            } else { // User is not part of that group
                // User must have:
                //      mod/wikilv:managewikilv capability
                // or
                //      moodle/site:accessallgroups capability
                // and
                //      mod/wikilv:viewpage capability
                $view = has_capability('mod/wikilv:viewpage', $context);
                $manage = has_capability('mod/wikilv:managewikilv', $context);
                $access = has_capability('moodle/site:accessallgroups', $context);
                return ($manage || $access) && $view;
            }
        } else {
            //Error
            return false;
        }
    case VISIBLEGROUPS:
        // Collaborative and Individual Mode
        //
        // Collaborative Mode:
        //      There is one wikilv per group.
        // Individual Mode:
        //      Each person owns a wikilv.
        if ($wikilv->wikilvmode == 'collaborative' || $wikilv->wikilvmode == 'individual') {
            // Everybody can read all wikilvs
            //
            // Only view capability needed
            return has_capability('mod/wikilv:viewpage', $context);
        } else {
            //Error
            return false;
        }
    default: // Error
        return false;
    }
}

/**
 * Checks if current user can edit a subwikilv
 *
 * @param $subwikilv
 */
function wikilv_user_can_edit($subwikilv) {
    global $USER;

    $wikilv = wikilv_get_wikilv($subwikilv->wikilvid);
    $cm = get_coursemodule_from_instance('wikilv', $wikilv->id);
    $context = context_module::instance($cm->id);

    // Working depending on activity groupmode
    switch (groups_get_activity_groupmode($cm)) {
    case NOGROUPS:

        if ($wikilv->wikilvmode == 'collaborative') {
            // Collaborative Mode:
            // There is a wikilv for all the class.
            //
            // Only edit capbility needed
            return has_capability('mod/wikilv:editpage', $context);
        } else if ($wikilv->wikilvmode == 'individual') {
            // Individual Mode
            // There is a wikilv per user

            // Only the owner of that wikilv can edit it
            if ($subwikilv->userid == $USER->id) {
                return has_capability('mod/wikilv:editpage', $context);
            } else { // Current user is not the owner of that wikilv.

                // User must have:
                //      mod/wikilv:editpage capability
                // and
                //      mod/wikilv:managewikilv capability
                $edit = has_capability('mod/wikilv:editpage', $context);
                $manage = has_capability('mod/wikilv:managewikilv', $context);

                return $edit && $manage;
            }
        } else {
            //Error
            return false;
        }
    case SEPARATEGROUPS:
        if ($wikilv->wikilvmode == 'collaborative') {
            // Collaborative Mode:
            // There is one wikilv per group.
            //
            // Only members of subwikilv group could edit that wikilv
            if ($subwikilv->groupid == groups_get_activity_group($cm)) {
                // Only edit capability needed
                return has_capability('mod/wikilv:editpage', $context);
            } else { // User is not part of that group
                // User must have:
                //      mod/wikilv:managewikilv capability
                // and
                //      moodle/site:accessallgroups capability
                // and
                //      mod/wikilv:editpage capability
                $manage = has_capability('mod/wikilv:managewikilv', $context);
                $access = has_capability('moodle/site:accessallgroups', $context);
                $edit = has_capability('mod/wikilv:editpage', $context);
                return $manage && $access && $edit;
            }
        } else if ($wikilv->wikilvmode == 'individual') {
            // Individual Mode:
            // Each person owns a wikilv.
            //
            // Only the owner of that wikilv can edit it
            if ($subwikilv->userid == $USER->id) {
                return has_capability('mod/wikilv:editpage', $context);
            } else { // Current user is not the owner of that wikilv.
                // User must have:
                //      mod/wikilv:managewikilv capability
                // and
                //      moodle/site:accessallgroups capability
                // and
                //      mod/wikilv:editpage capability
                $manage = has_capability('mod/wikilv:managewikilv', $context);
                $access = has_capability('moodle/site:accessallgroups', $context);
                $edit = has_capability('mod/wikilv:editpage', $context);
                return $manage && $access && $edit;
            }
        } else {
            //Error
            return false;
        }
    case VISIBLEGROUPS:
        if ($wikilv->wikilvmode == 'collaborative') {
            // Collaborative Mode:
            // There is one wikilv per group.
            //
            // Only members of subwikilv group could edit that wikilv
            if (groups_is_member($subwikilv->groupid)) {
                // Only edit capability needed
                return has_capability('mod/wikilv:editpage', $context);
            } else { // User is not part of that group
                // User must have:
                //      mod/wikilv:managewikilv capability
                // and
                //      mod/wikilv:editpage capability
                $manage = has_capability('mod/wikilv:managewikilv', $context);
                $edit = has_capability('mod/wikilv:editpage', $context);
                return $manage && $edit;
            }
        } else if ($wikilv->wikilvmode == 'individual') {
            // Individual Mode:
            // Each person owns a wikilv.
            //
            // Only the owner of that wikilv can edit it
            if ($subwikilv->userid == $USER->id) {
                return has_capability('mod/wikilv:editpage', $context);
            } else { // Current user is not the owner of that wikilv.
                // User must have:
                //      mod/wikilv:managewikilv capability
                // and
                //      mod/wikilv:editpage capability
                $manage = has_capability('mod/wikilv:managewikilv', $context);
                $edit = has_capability('mod/wikilv:editpage', $context);
                return $manage && $edit;
            }
        } else {
            //Error
            return false;
        }
    default: // Error
        return false;
    }
}

//----------------
// Locks
//----------------

/**
 * Checks if a page-section is locked.
 *
 * @return true if the combination of section and page is locked, FALSE otherwise.
 */
function wikilv_is_page_section_locked($pageid, $userid, $section = null) {
    global $DB;

    $sql = "pageid = ? AND lockedat > ? AND userid != ?";
    $params = array($pageid, time(), $userid);

    if (!empty($section)) {
        $sql .= " AND (sectionname = ? OR sectionname IS null)";
        $params[] = $section;
    }

    return $DB->record_exists_select('wikilv_locks', $sql, $params);
}

/**
 * Inserts or updates a wikilv_locks record.
 */
function wikilv_set_lock($pageid, $userid, $section = null, $insert = false) {
    global $DB;

    if (wikilv_is_page_section_locked($pageid, $userid, $section)) {
        return false;
    }

    $params = array('pageid' => $pageid, 'userid' => $userid, 'sectionname' => $section);

    $lock = $DB->get_record('wikilv_locks', $params);

    if (!empty($lock)) {
        $DB->update_record('wikilv_locks', array('id' => $lock->id, 'lockedat' => time() + LOCK_TIMEOUT));
    } else if ($insert) {
        $DB->insert_record('wikilv_locks', array('pageid' => $pageid, 'sectionname' => $section, 'userid' => $userid, 'lockedat' => time() + 30));
    }

    return true;
}

/**
 * Deletes wikilv_locks that are not in use. (F.Ex. after submitting the changes). If no userid is present, it deletes ALL the wikilv_locks of a specific page.
 */
function wikilv_delete_locks($pageid, $userid = null, $section = null, $delete_from_db = true, $delete_section_and_page = false) {
    global $DB;

    $params = array('pageid' => $pageid);

    if (!empty($userid)) {
        $params['userid'] = $userid;
    }

    if (!empty($section)) {
        $params['sectionname'] = $section;
    }

    if ($delete_from_db) {
        $DB->delete_records('wikilv_locks', $params);
        if ($delete_section_and_page && !empty($section)) {
            $params['sectionname'] = null;
            $DB->delete_records('wikilv_locks', $params);
        }
    } else {
        $DB->set_field('wikilv_locks', 'lockedat', time(), $params);
    }
}

/**
 * Deletes wikilv_locks that expired 1 hour ago.
 */
function wikilv_delete_old_locks() {
    global $DB;

    $DB->delete_records_select('wikilv_locks', "lockedat < ?", array(time() - 3600));
}

/**
 * Deletes wikilv_links. It can be sepecific link or links attached in subwikilv
 *
 * @global mixed $DB database object
 * @param int $linkid id of the link to be deleted
 * @param int $topageid links to the specific page
 * @param int $frompageid links from specific page
 * @param int $subwikilvid links to subwikilv
 */
function wikilv_delete_links($linkid = null, $topageid = null, $frompageid = null, $subwikilvid = null) {
    global $DB;
    $params = array();

    // if link id is givien then don't check for anything else
    if (!empty($linkid)) {
        $params['id'] = $linkid;
    } else {
        if (!empty($topageid)) {
            $params['topageid'] = $topageid;
        }
        if (!empty($frompageid)) {
            $params['frompageid'] = $frompageid;
        }
        if (!empty($subwikilvid)) {
            $params['subwikilvid'] = $subwikilvid;
        }
    }

    //Delete links if any params are passed, else nothing to delete.
    if (!empty($params)) {
        $DB->delete_records('wikilv_links', $params);
    }
}

/**
 * Delete wikilv synonyms related to subwikilvid or page
 *
 * @param int $subwikilvid id of sunbwikilv
 * @param int $pageid id of page
 */
function wikilv_delete_synonym($subwikilvid, $pageid = null) {
    global $DB;

    $params = array('subwikilvid' => $subwikilvid);
    if (!is_null($pageid)) {
        $params['pageid'] = $pageid;
    }
    $DB->delete_records('wikilv_synonyms', $params, IGNORE_MISSING);
}

/**
 * Delete pages and all related data
 *
 * @param mixed $context context in which page needs to be deleted.
 * @param mixed $pageids id's of pages to be deleted
 * @param int $subwikilvid id of the subwikilv for which all pages should be deleted
 */
function wikilv_delete_pages($context, $pageids = null, $subwikilvid = null) {
    global $DB;

    if (!empty($pageids) && is_int($pageids)) {
       $pageids = array($pageids);
    } else if (!empty($subwikilvid)) {
        $pageids = wikilv_get_page_list($subwikilvid);
    }

    //If there is no pageid then return as we can't delete anything.
    if (empty($pageids)) {
        return;
    }

    /// Delete page and all it's relevent data
    foreach ($pageids as $pageid) {
        if (is_object($pageid)) {
            $pageid = $pageid->id;
        }

        //Delete page comments
        $comments = wikilv_get_comments($context->id, $pageid);
        foreach ($comments as $commentid => $commentvalue) {
            wikilv_delete_comment($commentid, $context, $pageid);
        }

        //Delete page tags
        $tags = tag_get_tags_array('wikilv_pages', $pageid);
        foreach ($tags as $tagid => $tagvalue) {
            tag_delete_instance('wikilv_pages', $pageid, $tagid);
        }

        //Delete Synonym
        wikilv_delete_synonym($subwikilvid, $pageid);

        //Delete all page versions
        wikilv_delete_page_versions(array($pageid=>array(0)));

        //Delete all page locks
        wikilv_delete_locks($pageid);

        //Delete all page links
        wikilv_delete_links(null, $pageid);

        //Delete page
        $params = array('id' => $pageid);
        $DB->delete_records('wikilv_pages', $params);
    }
}

/**
 * Delete specificed versions of a page or versions created by users
 * if version is 0 then it will remove all versions of the page
 *
 * @param array $deleteversions delete versions for a page
 */
function wikilv_delete_page_versions($deleteversions) {
    global $DB;

    /// delete page-versions
    foreach ($deleteversions as $id => $versions) {
        foreach ($versions as $version) {
            $params = array('pageid' => $id);
            //If version = 0, then remove all versions of this page, else remove
            //specified version
            if ($version != 0) {
                $params['version'] = $version;
            }
            $DB->delete_records('wikilv_versions', $params, IGNORE_MISSING);
        }
    }
}

function wikilv_get_comment($commentid){
    global $DB;
    return $DB->get_record('comments', array('id' => $commentid));
}

/**
 * Returns all comments by context and pageid
 *
 * @param $context. Current context
 * @param $pageid. Current pageid
 **/
function wikilv_get_comments($contextid, $pageid) {
    global $DB;

    return $DB->get_records('comments', array('contextid' => $contextid, 'itemid' => $pageid, 'commentarea' => 'wikilv_page'));
}

/**
 * Add comments ro database
 *
 * @param object $context. Current context
 * @param int $pageid. Current pageid
 * @param string $content. Content of the comment
 * @param string editor. Version of editor we are using.
 **/
function wikilv_add_comment($context, $pageid, $content, $editor) {
    global $CFG;
    require_once($CFG->dirroot . '/comment/lib.php');

    list($context, $course, $cm) = get_context_info_array($context->id);
    $cmt = new stdclass();
    $cmt->context = $context;
    $cmt->itemid = $pageid;
    $cmt->area = 'wikilv_page';
    $cmt->course = $course;
    $cmt->component = 'mod_wikilv';

    $manager = new comment($cmt);

    if ($editor == 'creole') {
        $manager->add($content, FORMAT_CREOLE);
    } else if ($editor == 'html') {
        $manager->add($content, FORMAT_HTML);
    } else if ($editor == 'nwikilv') {
        $manager->add($content, FORMAT_NWIKILV);
    }

}

/**
 * Delete comments from database
 *
 * @param $idcomment. Id of comment which will be deleted
 * @param $context. Current context
 * @param $pageid. Current pageid
 **/
function wikilv_delete_comment($idcomment, $context, $pageid) {
    global $CFG;
    require_once($CFG->dirroot . '/comment/lib.php');

    list($context, $course, $cm) = get_context_info_array($context->id);
    $cmt = new stdClass();
    $cmt->context = $context;
    $cmt->itemid = $pageid;
    $cmt->area = 'wikilv_page';
    $cmt->course = $course;
    $cmt->component = 'mod_wikilv';

    $manager = new comment($cmt);
    $manager->delete($idcomment);

}

/**
 * Delete al comments from wikilv
 *
 **/
function wikilv_delete_comments_wikilv() {
    global $PAGE, $DB;

    $cm = $PAGE->cm;
    $context = context_module::instance($cm->id);

    $table = 'comments';
    $select = 'contextid = ?';

    $DB->delete_records_select($table, $select, array($context->id));

}

function wikilv_add_progress($pageid, $oldversionid, $versionid, $progress) {
    global $DB;
    for ($v = $oldversionid + 1; $v <= $versionid; $v++) {
        $user = wikilv_get_wikilv_page_id($pageid, $v);

        $DB->insert_record('wikilv_progress', array('userid' => $user->userid, 'pageid' => $pageid, 'versionid' => $v, 'progress' => $progress));
    }
}

function wikilv_get_wikilv_page_id($pageid, $id) {
    global $DB;
    return $DB->get_record('wikilv_versions', array('pageid' => $pageid, 'id' => $id));
}

function wikilv_print_page_content($page, $context, $subwikilvid) {
    global $OUTPUT, $CFG;

    if ($page->timerendered + WIKILV_REFRESH_CACHE_TIME < time()) {
        $content = wikilv_refresh_cachedcontent($page);
        $page = $content['page'];
    }

    if (isset($content)) {
        $box = '';
        foreach ($content['sections'] as $s) {
            $box .= '<p>' . get_string('repeatedsection', 'wikilv', $s) . '</p>';
        }

        if (!empty($box)) {
            echo $OUTPUT->box($box);
        }
    }
    $html = file_rewrite_pluginfile_urls($page->cachedcontent, 'pluginfile.php', $context->id, 'mod_wikilv', 'attachments', $subwikilvid);
    $html = format_text($html, FORMAT_MOODLE, array('overflowdiv'=>true, 'allowid'=>true));
    echo $OUTPUT->box($html);

    if (!empty($CFG->usetags)) {
        $tags = tag_get_tags_array('wikilv_pages', $page->id);
        echo $OUTPUT->container_start('wikilv-tags');
        echo '<span class="wikilv-tags-title">'.get_string('tags').': </span>';
        $links = array();
        foreach ($tags as $tagid=>$tag) {
            $url = new moodle_url('/tag/index.php', array('tag'=>$tag));
            $links[] = html_writer::link($url, $tag, array('title'=>get_string('tagtitle', 'wikilv', $tag)));
        }
        echo join($links, ", ");
        echo $OUTPUT->container_end();
    }

    wikilv_increment_pageviews($page);
}

/**
 * This function trims any given text and returns it with some dots at the end
 *
 * @param string $text
 * @param string $limit
 *
 * @return string
 */
function wikilv_trim_string($text, $limit = 25) {

    if (textlib::strlen($text) > $limit) {
        $text = textlib::substr($text, 0, $limit) . '...';
    }

    return $text;
}

/**
 * Prints default edit form fields and buttons
 *
 * @param string $format Edit form format (html, creole...)
 * @param integer $version Version number. A negative number means no versioning.
 */

function wikilv_print_edit_form_default_fields($format, $pageid, $version = -1, $upload = false, $deleteuploads = array()) {
    global $CFG, $PAGE, $OUTPUT;

    echo '<input type="hidden" name="sesskey" value="' . sesskey() . '" />';

    if ($version >= 0) {
        echo '<input type="hidden" name="version" value="' . $version . '" />';
    }

    echo '<input type="hidden" name="format" value="' . $format . '"/>';

    //attachments
    require_once($CFG->dirroot . '/lib/form/filemanager.php');

    $filemanager = new MoodleQuickForm_filemanager('attachments', get_string('wikilvattachments', 'wikilv'), array('id' => 'attachments'), array('subdirs' => false, 'maxfiles' => 99, 'maxbytes' => $CFG->maxbytes));

    $value = file_get_submitted_draft_itemid('attachments');
    if (!empty($value) && !$upload) {
        $filemanager->setValue($value);
    }

    echo "<fieldset class=\"wikilv-upload-section clearfix\"><legend class=\"ftoggler\">" . get_string("uploadtitle", 'wikilv') . "</legend>";

    echo $OUTPUT->container_start('mdl-align wikilv-form-center aaaaa');
    print $filemanager->toHtml();
    echo $OUTPUT->container_end();

    $cm = $PAGE->cm;
    $context = context_module::instance($cm->id);

    echo $OUTPUT->container_start('mdl-align wikilv-form-center wikilv-upload-table');
    wikilv_print_upload_table($context, 'wikilv_upload', $pageid, $deleteuploads);
    echo $OUTPUT->container_end();

    echo "</fieldset>";

    echo '<input class="wikilv_button" type="submit" name="editoption" value="' . get_string('save', 'wikilv') . '"/>';
    echo '<input class="wikilv_button" type="submit" name="editoption" value="' . get_string('upload', 'wikilv') . '"/>';
    echo '<input class="wikilv_button" type="submit" name="editoption" value="' . get_string('preview') . '"/>';
    echo '<input class="wikilv_button" type="submit" name="editoption" value="' . get_string('cancel') . '" />';
}

/**
 * Prints a table with the files attached to a wikilv page
 * @param object $context
 * @param string $filearea
 * @param int $fileitemid
 * @param array deleteuploads
 */
function wikilv_print_upload_table($context, $filearea, $fileitemid, $deleteuploads = array()) {
    global $CFG, $OUTPUT;

    $htmltable = new html_table();

    $htmltable->head = array(get_string('deleteupload', 'wikilv'), get_string('uploadname', 'wikilv'), get_string('uploadactions', 'wikilv'));

    $fs = get_file_storage();
    $files = $fs->get_area_files($context->id, 'mod_wikilv', $filearea, $fileitemid); //TODO: this is weird (skodak)

    foreach ($files as $file) {
        if (!$file->is_directory()) {
            $checkbox = '<input type="checkbox" name="deleteupload[]", value="' . $file->get_pathnamehash() . '"';

            if (in_array($file->get_pathnamehash(), $deleteuploads)) {
                $checkbox .= ' checked="checked"';
            }

            $checkbox .= " />";

            $htmltable->data[] = array($checkbox, '<a href="' . file_encode_url($CFG->wwwroot . '/pluginfile.php', '/' . $context->id . '/wikilv_upload/' . $fileitemid . '/' . $file->get_filename()) . '">' . $file->get_filename() . '</a>', "");
        }
    }

    print '<h3 class="upload-table-title">' . get_string('uploadfiletitle', 'wikilv') . "</h3>";
    print html_writer::table($htmltable);
}

/**
 * Generate wikilv's page tree
 *
 * @param page_wikilv $page. A wikilv page object
 * @param navigation_node $node. Starting navigation_node
 * @param array $keys. An array to store keys
 * @return an array with all tree nodes
 */
function wikilv_build_tree($page, $node, &$keys) {
    $content = array();
    static $icon;
    $icon = new pix_icon('f/odt', '');
    $pages = wikilv_get_linked_pages($page->id);
    foreach ($pages as $p) {
        $key = $page->id . ':' . $p->id;
        if (in_array($key, $keys)) {
            break;
        }
        array_push($keys, $key);
        $l = wikilv_parser_link($p);
        $link = new moodle_url('/mod/wikilv/view.php', array('pageid' => $p->id));
        // navigation_node::get_content will format the title for us
        $nodeaux = $node->add($p->title, $link, null, null, null, $icon);
        if ($l['new']) {
            $nodeaux->add_class('wikilv_newentry');
        }
        wikilv_build_tree($p, $nodeaux, $keys);
    }
    $content[] = $node;
    return $content;
}

/**
 * Get linked pages from page
 * @param int $pageid
 */
function wikilv_get_linked_pages($pageid) {
    global $DB;

    $sql = "SELECT p.id, p.title
            FROM {wikilv_pages} p
            JOIN {wikilv_links} l ON l.topageid = p.id
            WHERE l.frompageid = ?
            ORDER BY p.title ASC";
    return $DB->get_records_sql($sql, array($pageid));
}

/**
 * Get updated pages from wikilv
 * @param int $pageid
 */
function wikilv_get_updated_pages_by_subwikilv($swid) {
    global $DB, $USER;

    $sql = "SELECT *
            FROM {wikilv_pages}
            WHERE subwikilvid = ? AND timemodified > ?
            ORDER BY timemodified DESC";
    return $DB->get_records_sql($sql, array($swid, $USER->lastlogin));
}
