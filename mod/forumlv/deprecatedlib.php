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
 * @package   mod_forumlv
 * @copyright 2014 Andrew Robert Nicols <andrew@nicols.co.uk>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Deprecated a very long time ago.

/**
 * How many posts by other users are unrated by a given user in the given discussion?
 *
 * @param int $discussionid
 * @param int $userid
 * @return mixed
 * @deprecated since Moodle 1.1 - please do not use this function any more.
 */
function forumlv_count_unrated_posts($discussionid, $userid) {
    global $CFG, $DB;
    debugging('forumlv_count_unrated_posts() is deprecated and will not be replaced.', DEBUG_DEVELOPER);

    $sql = "SELECT COUNT(*) as num
              FROM {forumlv_posts}
             WHERE parent > 0
               AND discussion = :discussionid
               AND userid <> :userid";
    $params = array('discussionid' => $discussionid, 'userid' => $userid);
    $posts = $DB->get_record_sql($sql, $params);
    if ($posts) {
        $sql = "SELECT count(*) as num
                  FROM {forumlv_posts} p,
                       {rating} r
                 WHERE p.discussion = :discussionid AND
                       p.id = r.itemid AND
                       r.userid = userid AND
                       r.component = 'mod_forumlv' AND
                       r.ratingarea = 'post'";
        $rated = $DB->get_record_sql($sql, $params);
        if ($rated) {
            if ($posts->num > $rated->num) {
                return $posts->num - $rated->num;
            } else {
                return 0;    // Just in case there was a counting error
            }
        } else {
            return $posts->num;
        }
    } else {
        return 0;
    }
}


// Since Moodle 1.5.

/**
 * Returns the count of records for the provided user and discussion.
 *
 * @global object
 * @global object
 * @param int $userid
 * @param int $discussionid
 * @return bool
 * @deprecated since Moodle 1.5 - please do not use this function any more.
 */
function forumlv_tp_count_discussion_read_records($userid, $discussionid) {
    debugging('forumlv_tp_count_discussion_read_records() is deprecated and will not be replaced.', DEBUG_DEVELOPER);

    global $CFG, $DB;

    $cutoffdate = isset($CFG->forumlv_oldpostdays) ? (time() - ($CFG->forumlv_oldpostdays*24*60*60)) : 0;

    $sql = 'SELECT COUNT(DISTINCT p.id) '.
           'FROM {forumlv_discussions} d '.
           'LEFT JOIN {forumlv_read} r ON d.id = r.discussionid AND r.userid = ? '.
           'LEFT JOIN {forumlv_posts} p ON p.discussion = d.id '.
                'AND (p.modified < ? OR p.id = r.postid) '.
           'WHERE d.id = ? ';

    return ($DB->count_records_sql($sql, array($userid, $cutoffdate, $discussionid)));
}

/**
 * Get all discussions started by a particular user in a course (or group)
 *
 * @global object
 * @global object
 * @param int $courseid
 * @param int $userid
 * @param int $groupid
 * @return array
 * @deprecated since Moodle 1.5 - please do not use this function any more.
 */
function forumlv_get_user_discussions($courseid, $userid, $groupid=0) {
    debugging('forumlv_get_user_discussions() is deprecated and will not be replaced.', DEBUG_DEVELOPER);

    global $CFG, $DB;
    $params = array($courseid, $userid);
    if ($groupid) {
        $groupselect = " AND d.groupid = ? ";
        $params[] = $groupid;
    } else  {
        $groupselect = "";
    }

    $allnames = get_all_user_name_fields(true, 'u');
    return $DB->get_records_sql("SELECT p.*, d.groupid, $allnames, u.email, u.picture, u.imagealt,
                                   f.type as forumlvtype, f.name as forumlvname, f.id as forumlvid
                              FROM {forumlv_discussions} d,
                                   {forumlv_posts} p,
                                   {user} u,
                                   {forumlv} f
                             WHERE d.course = ?
                               AND p.discussion = d.id
                               AND p.parent = 0
                               AND p.userid = u.id
                               AND u.id = ?
                               AND d.forumlv = f.id $groupselect
                          ORDER BY p.created DESC", $params);
}


// Since Moodle 1.6.

/**
 * Returns the count of posts for the provided forumlv and [optionally] group.
 * @global object
 * @global object
 * @param int $forumlvid
 * @param int|bool $groupid
 * @return int
 * @deprecated since Moodle 1.6 - please do not use this function any more.
 */
function forumlv_tp_count_forumlv_posts($forumlvid, $groupid=false) {
    debugging('forumlv_tp_count_forumlv_posts() is deprecated and will not be replaced.', DEBUG_DEVELOPER);

    global $CFG, $DB;
    $params = array($forumlvid);
    $sql = 'SELECT COUNT(*) '.
           'FROM {forumlv_posts} fp,{forumlv_discussions} fd '.
           'WHERE fd.forumlv = ? AND fp.discussion = fd.id';
    if ($groupid !== false) {
        $sql .= ' AND (fd.groupid = ? OR fd.groupid = -1)';
        $params[] = $groupid;
    }
    $count = $DB->count_records_sql($sql, $params);


    return $count;
}

/**
 * Returns the count of records for the provided user and forumlv and [optionally] group.
 * @global object
 * @global object
 * @param int $userid
 * @param int $forumlvid
 * @param int|bool $groupid
 * @return int
 * @deprecated since Moodle 1.6 - please do not use this function any more.
 */
function forumlv_tp_count_forumlv_read_records($userid, $forumlvid, $groupid=false) {
    debugging('forumlv_tp_count_forumlv_read_records() is deprecated and will not be replaced.', DEBUG_DEVELOPER);

    global $CFG, $DB;

    $cutoffdate = time() - ($CFG->forumlv_oldpostdays*24*60*60);

    $groupsel = '';
    $params = array($userid, $forumlvid, $cutoffdate);
    if ($groupid !== false) {
        $groupsel = "AND (d.groupid = ? OR d.groupid = -1)";
        $params[] = $groupid;
    }

    $sql = "SELECT COUNT(p.id)
              FROM  {forumlv_posts} p
                    JOIN {forumlv_discussions} d ON d.id = p.discussion
                    LEFT JOIN {forumlv_read} r   ON (r.postid = p.id AND r.userid= ?)
              WHERE d.forumlv = ?
                    AND (p.modified < $cutoffdate OR (p.modified >= ? AND r.id IS NOT NULL))
                    $groupsel";

    return $DB->get_field_sql($sql, $params);
}


// Since Moodle 1.7.

/**
 * Returns array of forumlv open modes.
 *
 * @return array
 * @deprecated since Moodle 1.7 - please do not use this function any more.
 */
function forumlv_get_open_modes() {
    debugging('forumlv_get_open_modes() is deprecated and will not be replaced.', DEBUG_DEVELOPER);
    return array();
}


// Since Moodle 1.9.

/**
 * Gets posts with all info ready for forumlv_print_post
 * We pass forumlvid in because we always know it so no need to make a
 * complicated join to find it out.
 *
 * @global object
 * @global object
 * @param int $parent
 * @param int $forumlvid
 * @return array
 * @deprecated since Moodle 1.9 MDL-13303 - please do not use this function any more.
 */
function forumlv_get_child_posts($parent, $forumlvid) {
    debugging('forumlv_get_child_posts() is deprecated.', DEBUG_DEVELOPER);

    global $CFG, $DB;

    $allnames = get_all_user_name_fields(true, 'u');
    return $DB->get_records_sql("SELECT p.*, $forumlvid AS forumlv, $allnames, u.email, u.picture, u.imagealt
                              FROM {forumlv_posts} p
                         LEFT JOIN {user} u ON p.userid = u.id
                             WHERE p.parent = ?
                          ORDER BY p.created ASC", array($parent));
}

/**
 * Gets posts with all info ready for forumlv_print_post
 * We pass forumlvid in because we always know it so no need to make a
 * complicated join to find it out.
 *
 * @global object
 * @global object
 * @return mixed array of posts or false
 * @deprecated since Moodle 1.9 MDL-13303 - please do not use this function any more.
 */
function forumlv_get_discussion_posts($discussion, $sort, $forumlvid) {
    debugging('forumlv_get_discussion_posts() is deprecated.', DEBUG_DEVELOPER);

    global $CFG, $DB;

    $allnames = get_all_user_name_fields(true, 'u');
    return $DB->get_records_sql("SELECT p.*, $forumlvid AS forumlv, $allnames, u.email, u.picture, u.imagealt
                              FROM {forumlv_posts} p
                         LEFT JOIN {user} u ON p.userid = u.id
                             WHERE p.discussion = ?
                               AND p.parent > 0 $sort", array($discussion));
}


// Since Moodle 2.0.

/**
 * Returns a list of ratings for a particular post - sorted.
 *
 * @param stdClass $context
 * @param int $postid
 * @param string $sort
 * @return array Array of ratings or false
 * @deprecated since Moodle 2.0 MDL-21657 - please do not use this function any more.
 */
function forumlv_get_ratings($context, $postid, $sort = "u.firstname ASC") {
    debugging('forumlv_get_ratings() is deprecated.', DEBUG_DEVELOPER);
    $options = new stdClass;
    $options->context = $context;
    $options->component = 'mod_forumlv';
    $options->ratingarea = 'post';
    $options->itemid = $postid;
    $options->sort = "ORDER BY $sort";

    $rm = new rating_manager();
    return $rm->get_all_ratings_for_item($options);
}

/**
 * Generate and return the track or no track link for a forumlv.
 *
 * @global object
 * @global object
 * @global object
 * @param object $forumlv the forumlv. Fields used are $forumlv->id and $forumlv->forcesubscribe.
 * @param array $messages
 * @param bool $fakelink
 * @return string
 * @deprecated since Moodle 2.0 MDL-14632 - please do not use this function any more.
 */
function forumlv_get_tracking_link($forumlv, $messages=array(), $fakelink=true) {
    debugging('forumlv_get_tracking_link() is deprecated.', DEBUG_DEVELOPER);

    global $CFG, $USER, $PAGE, $OUTPUT;

    static $strnotrackforumlv, $strtrackforumlv;

    if (isset($messages['trackforumlv'])) {
         $strtrackforumlv = $messages['trackforumlv'];
    }
    if (isset($messages['notrackforumlv'])) {
         $strnotrackforumlv = $messages['notrackforumlv'];
    }
    if (empty($strtrackforumlv)) {
        $strtrackforumlv = get_string('trackforumlv', 'forumlv');
    }
    if (empty($strnotrackforumlv)) {
        $strnotrackforumlv = get_string('notrackforumlv', 'forumlv');
    }

    if (forumlv_tp_is_tracked($forumlv)) {
        $linktitle = $strnotrackforumlv;
        $linktext = $strnotrackforumlv;
    } else {
        $linktitle = $strtrackforumlv;
        $linktext = $strtrackforumlv;
    }

    $link = '';
    if ($fakelink) {
        $PAGE->requires->js('/mod/forumlv/forumlv.js');
        $PAGE->requires->js_function_call('forumlv_produce_tracking_link', Array($forumlv->id, $linktext, $linktitle));
        // use <noscript> to print button in case javascript is not enabled
        $link .= '<noscript>';
    }
    $url = new moodle_url('/mod/forumlv/settracking.php', array(
            'id' => $forumlv->id,
            'sesskey' => sesskey(),
        ));
    $link .= $OUTPUT->single_button($url, $linktext, 'get', array('title'=>$linktitle));

    if ($fakelink) {
        $link .= '</noscript>';
    }

    return $link;
}

/**
 * Returns the count of records for the provided user and discussion.
 *
 * @global object
 * @global object
 * @param int $userid
 * @param int $discussionid
 * @return int
 * @deprecated since Moodle 2.0 MDL-14113 - please do not use this function any more.
 */
function forumlv_tp_count_discussion_unread_posts($userid, $discussionid) {
    debugging('forumlv_tp_count_discussion_unread_posts() is deprecated.', DEBUG_DEVELOPER);
    global $CFG, $DB;

    $cutoffdate = isset($CFG->forumlv_oldpostdays) ? (time() - ($CFG->forumlv_oldpostdays*24*60*60)) : 0;

    $sql = 'SELECT COUNT(p.id) '.
           'FROM {forumlv_posts} p '.
           'LEFT JOIN {forumlv_read} r ON r.postid = p.id AND r.userid = ? '.
           'WHERE p.discussion = ? '.
                'AND p.modified >= ? AND r.id is NULL';

    return $DB->count_records_sql($sql, array($userid, $discussionid, $cutoffdate));
}

/**
 * Converts a forumlv to use the Roles System
 *
 * @deprecated since Moodle 2.0 MDL-23479 - please do not use this function any more.
 */
function forumlv_convert_to_roles() {
    debugging('forumlv_convert_to_roles() is deprecated and will not be replaced.', DEBUG_DEVELOPER);
}

/**
 * Returns all records in the 'forumlv_read' table matching the passed keys, indexed
 * by userid.
 *
 * @global object
 * @param int $userid
 * @param int $postid
 * @param int $discussionid
 * @param int $forumlvid
 * @return array
 * @deprecated since Moodle 2.0 MDL-14113 - please do not use this function any more.
 */
function forumlv_tp_get_read_records($userid=-1, $postid=-1, $discussionid=-1, $forumlvid=-1) {
    debugging('forumlv_tp_get_read_records() is deprecated and will not be replaced.', DEBUG_DEVELOPER);

    global $DB;
    $select = '';
    $params = array();

    if ($userid > -1) {
        if ($select != '') $select .= ' AND ';
        $select .= 'userid = ?';
        $params[] = $userid;
    }
    if ($postid > -1) {
        if ($select != '') $select .= ' AND ';
        $select .= 'postid = ?';
        $params[] = $postid;
    }
    if ($discussionid > -1) {
        if ($select != '') $select .= ' AND ';
        $select .= 'discussionid = ?';
        $params[] = $discussionid;
    }
    if ($forumlvid > -1) {
        if ($select != '') $select .= ' AND ';
        $select .= 'forumlvid = ?';
        $params[] = $forumlvid;
    }

    return $DB->get_records_select('forumlv_read', $select, $params);
}

/**
 * Returns all read records for the provided user and discussion, indexed by postid.
 *
 * @global object
 * @param inti $userid
 * @param int $discussionid
 * @deprecated since Moodle 2.0 MDL-14113 - please do not use this function any more.
 */
function forumlv_tp_get_discussion_read_records($userid, $discussionid) {
    debugging('forumlv_tp_get_discussion_read_records() is deprecated and will not be replaced.', DEBUG_DEVELOPER);

    global $DB;
    $select = 'userid = ? AND discussionid = ?';
    $fields = 'postid, firstread, lastread';
    return $DB->get_records_select('forumlv_read', $select, array($userid, $discussionid), '', $fields);
}

// Deprecated in 2.3.

/**
 * This function gets run whenever user is enrolled into course
 *
 * @deprecated since Moodle 2.3 MDL-33166 - please do not use this function any more.
 * @param stdClass $cp
 * @return void
 */
function forumlv_user_enrolled($cp) {
    debugging('forumlv_user_enrolled() is deprecated. Please use forumlv_user_role_assigned instead.', DEBUG_DEVELOPER);
    global $DB;

    // NOTE: this has to be as fast as possible - we do not want to slow down enrolments!
    //       Originally there used to be 'mod/forumlv:initialsubscriptions' which was
    //       introduced because we did not have enrolment information in earlier versions...

    $sql = "SELECT f.id
              FROM {forumlv} f
         LEFT JOIN {forumlv_subscriptions} fs ON (fs.forumlv = f.id AND fs.userid = :userid)
             WHERE f.course = :courseid AND f.forcesubscribe = :initial AND fs.id IS NULL";
    $params = array('courseid'=>$cp->courseid, 'userid'=>$cp->userid, 'initial'=>FORUMLV_INITIALSUBSCRIBE);

    $forumlvs = $DB->get_records_sql($sql, $params);
    foreach ($forumlvs as $forumlv) {
        \mod_forumlv\subscriptions::subscribe_user($cp->userid, $forumlv);
    }
}


// Deprecated in 2.4.

/**
 * Checks to see if a user can view a particular post.
 *
 * @deprecated since Moodle 2.4 use forumlv_user_can_see_post() instead
 *
 * @param object $post
 * @param object $course
 * @param object $cm
 * @param object $forumlv
 * @param object $discussion
 * @param object $user
 * @return boolean
 */
function forumlv_user_can_view_post($post, $course, $cm, $forumlv, $discussion, $user=null){
    debugging('forumlv_user_can_view_post() is deprecated. Please use forumlv_user_can_see_post() instead.', DEBUG_DEVELOPER);
    return forumlv_user_can_see_post($forumlv, $discussion, $post, $user, $cm);
}


// Deprecated in 2.6.

/**
 * FORUMLV_TRACKING_ON - deprecated alias for FORUMLV_TRACKING_FORCED.
 * @deprecated since 2.6
 */
define('FORUMLV_TRACKING_ON', 2);

/**
 * @deprecated since Moodle 2.6
 * @see shorten_text()
 */
function forumlv_shorten_post($message) {
    throw new coding_exception('forumlv_shorten_post() can not be used any more. Please use shorten_text($message, $CFG->forumlv_shortpost) instead.');
}

// Deprecated in 2.8.

/**
 * @global object
 * @param int $userid
 * @param object $forumlv
 * @return bool
 * @deprecated since Moodle 2.8 use \mod_forumlv\subscriptions::is_subscribed() instead
 */
function forumlv_is_subscribed($userid, $forumlv) {
    global $DB;
    debugging("forumlv_is_subscribed() has been deprecated, please use \\mod_forumlv\\subscriptions::is_subscribed() instead.",
            DEBUG_DEVELOPER);

    // Note: The new function does not take an integer form of forumlv.
    if (is_numeric($forumlv)) {
        $forumlv = $DB->get_record('forumlv', array('id' => $forumlv));
    }

    return mod_forumlv\subscriptions::is_subscribed($userid, $forumlv);
}

/**
 * Adds user to the subscriber list
 *
 * @param int $userid
 * @param int $forumlvid
 * @param context_module|null $context Module context, may be omitted if not known or if called for the current module set in page.
 * @param boolean $userrequest Whether the user requested this change themselves. This has an effect on whether
 * discussion subscriptions are removed too.
 * @deprecated since Moodle 2.8 use \mod_forumlv\subscriptions::subscribe_user() instead
 */
function forumlv_subscribe($userid, $forumlvid, $context = null, $userrequest = false) {
    global $DB;
    debugging("forumlv_subscribe() has been deprecated, please use \\mod_forumlv\\subscriptions::subscribe_user() instead.",
            DEBUG_DEVELOPER);

    // Note: The new function does not take an integer form of forumlv.
    $forumlv = $DB->get_record('forumlv', array('id' => $forumlvid));
    \mod_forumlv\subscriptions::subscribe_user($userid, $forumlv, $context, $userrequest);
}

/**
 * Removes user from the subscriber list
 *
 * @param int $userid
 * @param int $forumlvid
 * @param context_module|null $context Module context, may be omitted if not known or if called for the current module set in page.
 * @param boolean $userrequest Whether the user requested this change themselves. This has an effect on whether
 * discussion subscriptions are removed too.
 * @deprecated since Moodle 2.8 use \mod_forumlv\subscriptions::unsubscribe_user() instead
 */
function forumlv_unsubscribe($userid, $forumlvid, $context = null, $userrequest = false) {
    global $DB;
    debugging("forumlv_unsubscribe() has been deprecated, please use \\mod_forumlv\\subscriptions::unsubscribe_user() instead.",
            DEBUG_DEVELOPER);

    // Note: The new function does not take an integer form of forumlv.
    $forumlv = $DB->get_record('forumlv', array('id' => $forumlvid));
    \mod_forumlv\subscriptions::unsubscribe_user($userid, $forumlv, $context, $userrequest);
}

/**
 * Returns list of user objects that are subscribed to this forumlv.
 *
 * @param stdClass $course the course
 * @param stdClass $forumlv the forumlv
 * @param int $groupid group id, or 0 for all.
 * @param context_module $context the forumlv context, to save re-fetching it where possible.
 * @param string $fields requested user fields (with "u." table prefix)
 * @param boolean $considerdiscussions Whether to take discussion subscriptions and unsubscriptions into consideration.
 * @return array list of users.
 * @deprecated since Moodle 2.8 use \mod_forumlv\subscriptions::fetch_subscribed_users() instead
  */
function forumlv_subscribed_users($course, $forumlv, $groupid = 0, $context = null, $fields = null) {
    debugging("forumlv_subscribed_users() has been deprecated, please use \\mod_forumlv\\subscriptions::fetch_subscribed_users() instead.",
            DEBUG_DEVELOPER);

    \mod_forumlv\subscriptions::fetch_subscribed_users($forumlv, $groupid, $context, $fields);
}

/**
 * Determine whether the forumlv is force subscribed.
 *
 * @param object $forumlv
 * @return bool
 * @deprecated since Moodle 2.8 use \mod_forumlv\subscriptions::is_forcesubscribed() instead
 */
function forumlv_is_forcesubscribed($forumlv) {
    debugging("forumlv_is_forcesubscribed() has been deprecated, please use \\mod_forumlv\\subscriptions::is_forcesubscribed() instead.",
            DEBUG_DEVELOPER);

    global $DB;
    if (!isset($forumlv->forcesubscribe)) {
       $forumlv = $DB->get_field('forumlv', 'forcesubscribe', array('id' => $forumlv));
    }

    return \mod_forumlv\subscriptions::is_forcesubscribed($forumlv);
}

/**
 * Set the subscription mode for a forumlv.
 *
 * @param int $forumlvid
 * @param mixed $value
 * @return bool
 * @deprecated since Moodle 2.8 use \mod_forumlv\subscriptions::set_subscription_mode() instead
 */
function forumlv_forcesubscribe($forumlvid, $value = 1) {
    debugging("forumlv_forcesubscribe() has been deprecated, please use \\mod_forumlv\\subscriptions::set_subscription_mode() instead.",
            DEBUG_DEVELOPER);

    return \mod_forumlv\subscriptions::set_subscription_mode($forumlvid, $value);
}

/**
 * Get the current subscription mode for the forumlv.
 *
 * @param int|stdClass $forumlvid
 * @param mixed $value
 * @return bool
 * @deprecated since Moodle 2.8 use \mod_forumlv\subscriptions::get_subscription_mode() instead
 */
function forumlv_get_forcesubscribed($forumlv) {
    debugging("forumlv_get_forcesubscribed() has been deprecated, please use \\mod_forumlv\\subscriptions::get_subscription_mode() instead.",
            DEBUG_DEVELOPER);

    global $DB;
    if (!isset($forumlv->forcesubscribe)) {
       $forumlv = $DB->get_field('forumlv', 'forcesubscribe', array('id' => $forumlv));
    }

    return \mod_forumlv\subscriptions::get_subscription_mode($forumlvid, $value);
}

/**
 * Get a list of forumlvs in the specified course in which a user can change
 * their subscription preferences.
 *
 * @param stdClass $course The course from which to find subscribable forumlvs.
 * @return array
 * @deprecated since Moodle 2.8 use \mod_forumlv\subscriptions::is_subscribed in combination wtih
 * \mod_forumlv\subscriptions::fill_subscription_cache_for_course instead.
 */
function forumlv_get_subscribed_forumlvs($course) {
    debugging("forumlv_get_subscribed_forumlvs() has been deprecated, please see " .
              "\\mod_forumlv\\subscriptions::is_subscribed::() " .
              " and \\mod_forumlv\\subscriptions::fill_subscription_cache_for_course instead.",
              DEBUG_DEVELOPER);

    global $USER, $CFG, $DB;
    $sql = "SELECT f.id
              FROM {forumlv} f
                   LEFT JOIN {forumlv_subscriptions} fs ON (fs.forumlv = f.id AND fs.userid = ?)
             WHERE f.course = ?
                   AND f.forcesubscribe <> ".FORUMLV_DISALLOWSUBSCRIBE."
                   AND (f.forcesubscribe = ".FORUMLV_FORCESUBSCRIBE." OR fs.id IS NOT NULL)";
    if ($subscribed = $DB->get_records_sql($sql, array($USER->id, $course->id))) {
        foreach ($subscribed as $s) {
            $subscribed[$s->id] = $s->id;
        }
        return $subscribed;
    } else {
        return array();
    }
}

/**
 * Returns an array of forumlvs that the current user is subscribed to and is allowed to unsubscribe from
 *
 * @return array An array of unsubscribable forumlvs
 * @deprecated since Moodle 2.8 use \mod_forumlv\subscriptions::get_unsubscribable_forumlvs() instead
 */
function forumlv_get_optional_subscribed_forumlvs() {
    debugging("forumlv_get_optional_subscribed_forumlvs() has been deprecated, please use \\mod_forumlv\\subscriptions::get_unsubscribable_forumlvs() instead.",
            DEBUG_DEVELOPER);

    return \mod_forumlv\subscriptions::get_unsubscribable_forumlvs();
}

/**
 * Get the list of potential subscribers to a forumlv.
 *
 * @param object $forumlvcontext the forumlv context.
 * @param integer $groupid the id of a group, or 0 for all groups.
 * @param string $fields the list of fields to return for each user. As for get_users_by_capability.
 * @param string $sort sort order. As for get_users_by_capability.
 * @return array list of users.
 * @deprecated since Moodle 2.8 use \mod_forumlv\subscriptions::get_potential_subscribers() instead
 */
function forumlv_get_potential_subscribers($forumlvcontext, $groupid, $fields, $sort = '') {
    debugging("forumlv_get_potential_subscribers() has been deprecated, please use \\mod_forumlv\\subscriptions::get_potential_subscribers() instead.",
            DEBUG_DEVELOPER);

    \mod_forumlv\subscriptions::get_potential_subscribers($forumlvcontext, $groupid, $fields, $sort);
}

/**
 * Builds and returns the body of the email notification in plain text.
 *
 * @uses CONTEXT_MODULE
 * @param object $course
 * @param object $cm
 * @param object $forumlv
 * @param object $discussion
 * @param object $post
 * @param object $userfrom
 * @param object $userto
 * @param boolean $bare
 * @param string $replyaddress The inbound address that a user can reply to the generated e-mail with. [Since 2.8].
 * @return string The email body in plain text format.
 * @deprecated since Moodle 3.0 use \mod_forumlv\output\forumlv_post_email instead
 */
function forumlv_make_mail_text($course, $cm, $forumlv, $discussion, $post, $userfrom, $userto, $bare = false, $replyaddress = null) {
    global $PAGE;
    $renderable = new \mod_forumlv\output\forumlv_post_email(
        $course,
        $cm,
        $forumlv,
        $discussion,
        $post,
        $userfrom,
        $userto,
        forumlv_user_can_post($forumlv, $discussion, $userto, $cm, $course)
        );

    $modcontext = context_module::instance($cm->id);
    $renderable->viewfullnames = has_capability('moodle/site:viewfullnames', $modcontext, $userto->id);

    if ($bare) {
        $renderer = $PAGE->get_renderer('mod_forumlv', 'emaildigestfull', 'textemail');
    } else {
        $renderer = $PAGE->get_renderer('mod_forumlv', 'email', 'textemail');
    }

    debugging("forumlv_make_mail_text() has been deprecated, please use the \mod_forumlv\output\forumlv_post_email renderable instead.",
            DEBUG_DEVELOPER);

    return $renderer->render($renderable);
}

/**
 * Builds and returns the body of the email notification in html format.
 *
 * @param object $course
 * @param object $cm
 * @param object $forumlv
 * @param object $discussion
 * @param object $post
 * @param object $userfrom
 * @param object $userto
 * @param string $replyaddress The inbound address that a user can reply to the generated e-mail with. [Since 2.8].
 * @return string The email text in HTML format
 * @deprecated since Moodle 3.0 use \mod_forumlv\output\forumlv_post_email instead
 */
function forumlv_make_mail_html($course, $cm, $forumlv, $discussion, $post, $userfrom, $userto, $replyaddress = null) {
    return forumlv_make_mail_post($course,
        $cm,
        $forumlv,
        $discussion,
        $post,
        $userfrom,
        $userto,
        forumlv_user_can_post($forumlv, $discussion, $userto, $cm, $course)
    );
}

/**
 * Given the data about a posting, builds up the HTML to display it and
 * returns the HTML in a string.  This is designed for sending via HTML email.
 *
 * @param object $course
 * @param object $cm
 * @param object $forumlv
 * @param object $discussion
 * @param object $post
 * @param object $userfrom
 * @param object $userto
 * @param bool $ownpost
 * @param bool $reply
 * @param bool $link
 * @param bool $rate
 * @param string $footer
 * @return string
 * @deprecated since Moodle 3.0 use \mod_forumlv\output\forumlv_post_email instead
 */
function forumlv_make_mail_post($course, $cm, $forumlv, $discussion, $post, $userfrom, $userto,
                              $ownpost=false, $reply=false, $link=false, $rate=false, $footer="") {
    global $PAGE;
    $renderable = new \mod_forumlv\output\forumlv_post_email(
        $course,
        $cm,
        $forumlv,
        $discussion,
        $post,
        $userfrom,
        $userto,
        $reply);

    $modcontext = context_module::instance($cm->id);
    $renderable->viewfullnames = has_capability('moodle/site:viewfullnames', $modcontext, $userto->id);

    // Assume that this is being used as a standard forumlv email.
    $renderer = $PAGE->get_renderer('mod_forumlv', 'email', 'htmlemail');

    debugging("forumlv_make_mail_post() has been deprecated, please use the \mod_forumlv\output\forumlv_post_email renderable instead.",
            DEBUG_DEVELOPER);

    return $renderer->render($renderable);
}
