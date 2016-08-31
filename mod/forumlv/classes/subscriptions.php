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
 * Forumlv subscription manager.
 *
 * @package    mod_forumlv
 * @copyright  2014 Andrew Nicols <andrew@nicols.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_forumlv;

defined('MOODLE_INTERNAL') || die();

/**
 * Forumlv subscription manager.
 *
 * @copyright  2014 Andrew Nicols <andrew@nicols.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class subscriptions {

    /**
     * The status value for an unsubscribed discussion.
     *
     * @var int
     */
    const FORUMLV_DISCUSSION_UNSUBSCRIBED = -1;

    /**
     * The subscription cache for forumlvs.
     *
     * The first level key is the user ID
     * The second level is the forumlv ID
     * The Value then is bool for subscribed of not.
     *
     * @var array[] An array of arrays.
     */
    protected static $forumlvcache = array();

    /**
     * The list of forumlvs which have been wholly retrieved for the forumlv subscription cache.
     *
     * This allows for prior caching of an entire forumlv to reduce the
     * number of DB queries in a subscription check loop.
     *
     * @var bool[]
     */
    protected static $fetchedforumlvs = array();

    /**
     * The subscription cache for forumlv discussions.
     *
     * The first level key is the user ID
     * The second level is the forumlv ID
     * The third level key is the discussion ID
     * The value is then the users preference (int)
     *
     * @var array[]
     */
    protected static $forumlvdiscussioncache = array();

    /**
     * The list of forumlvs which have been wholly retrieved for the forumlv discussion subscription cache.
     *
     * This allows for prior caching of an entire forumlv to reduce the
     * number of DB queries in a subscription check loop.
     *
     * @var bool[]
     */
    protected static $discussionfetchedforumlvs = array();

    /**
     * Whether a user is subscribed to this forumlv, or a discussion within
     * the forumlv.
     *
     * If a discussion is specified, then report whether the user is
     * subscribed to posts to this particular discussion, taking into
     * account the forumlv preference.
     *
     * If it is not specified then only the forumlv preference is considered.
     *
     * @param int $userid The user ID
     * @param \stdClass $forumlv The record of the forumlv to test
     * @param int $discussionid The ID of the discussion to check
     * @param $cm The coursemodule record. If not supplied, this will be calculated using get_fast_modinfo instead.
     * @return boolean
     */
    public static function is_subscribed($userid, $forumlv, $discussionid = null, $cm = null) {
        // If forumlv is force subscribed and has allowforcesubscribe, then user is subscribed.
        if (self::is_forcesubscribed($forumlv)) {
            if (!$cm) {
                $cm = get_fast_modinfo($forumlv->course)->instances['forumlv'][$forumlv->id];
            }
            if (has_capability('mod/forumlv:allowforcesubscribe', \context_module::instance($cm->id), $userid)) {
                return true;
            }
        }

        if ($discussionid === null) {
            return self::is_subscribed_to_forumlv($userid, $forumlv);
        }

        $subscriptions = self::fetch_discussion_subscription($forumlv->id, $userid);

        // Check whether there is a record for this discussion subscription.
        if (isset($subscriptions[$discussionid])) {
            return ($subscriptions[$discussionid] != self::FORUMLV_DISCUSSION_UNSUBSCRIBED);
        }

        return self::is_subscribed_to_forumlv($userid, $forumlv);
    }

    /**
     * Whether a user is subscribed to this forumlv.
     *
     * @param int $userid The user ID
     * @param \stdClass $forumlv The record of the forumlv to test
     * @return boolean
     */
    protected static function is_subscribed_to_forumlv($userid, $forumlv) {
        return self::fetch_subscription_cache($forumlv->id, $userid);
    }

    /**
     * Helper to determine whether a forumlv has it's subscription mode set
     * to forced subscription.
     *
     * @param \stdClass $forumlv The record of the forumlv to test
     * @return bool
     */
    public static function is_forcesubscribed($forumlv) {
        return ($forumlv->forcesubscribe == FORUMLV_FORCESUBSCRIBE);
    }

    /**
     * Helper to determine whether a forumlv has it's subscription mode set to disabled.
     *
     * @param \stdClass $forumlv The record of the forumlv to test
     * @return bool
     */
    public static function subscription_disabled($forumlv) {
        return ($forumlv->forcesubscribe == FORUMLV_DISALLOWSUBSCRIBE);
    }

    /**
     * Helper to determine whether the specified forumlv can be subscribed to.
     *
     * @param \stdClass $forumlv The record of the forumlv to test
     * @return bool
     */
    public static function is_subscribable($forumlv) {
        return (!\mod_forumlv\subscriptions::is_forcesubscribed($forumlv) &&
                !\mod_forumlv\subscriptions::subscription_disabled($forumlv));
    }

    /**
     * Set the forumlv subscription mode.
     *
     * By default when called without options, this is set to FORUMLV_FORCESUBSCRIBE.
     *
     * @param \stdClass $forumlv The record of the forumlv to set
     * @param int $status The new subscription state
     * @return bool
     */
    public static function set_subscription_mode($forumlvid, $status = 1) {
        global $DB;
        return $DB->set_field("forumlv", "forcesubscribe", $status, array("id" => $forumlvid));
    }

    /**
     * Returns the current subscription mode for the forumlv.
     *
     * @param \stdClass $forumlv The record of the forumlv to set
     * @return int The forumlv subscription mode
     */
    public static function get_subscription_mode($forumlv) {
        return $forumlv->forcesubscribe;
    }

    /**
     * Returns an array of forumlvs that the current user is subscribed to and is allowed to unsubscribe from
     *
     * @return array An array of unsubscribable forumlvs
     */
    public static function get_unsubscribable_forumlvs() {
        global $USER, $DB;

        // Get courses that $USER is enrolled in and can see.
        $courses = enrol_get_my_courses();
        if (empty($courses)) {
            return array();
        }

        $courseids = array();
        foreach($courses as $course) {
            $courseids[] = $course->id;
        }
        list($coursesql, $courseparams) = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'c');

        // Get all forumlvs from the user's courses that they are subscribed to and which are not set to forced.
        // It is possible for users to be subscribed to a forumlv in subscription disallowed mode so they must be listed
        // here so that that can be unsubscribed from.
        $sql = "SELECT f.id, cm.id as cm, cm.visible, f.course
                FROM {forumlv} f
                JOIN {course_modules} cm ON cm.instance = f.id
                JOIN {modules} m ON m.name = :modulename AND m.id = cm.module
                LEFT JOIN {forumlv_subscriptions} fs ON (fs.forumlv = f.id AND fs.userid = :userid)
                WHERE f.forcesubscribe <> :forcesubscribe
                AND fs.id IS NOT NULL
                AND cm.course
                $coursesql";
        $params = array_merge($courseparams, array(
            'modulename'=>'forumlv',
            'userid' => $USER->id,
            'forcesubscribe' => FORUMLV_FORCESUBSCRIBE,
        ));
        $forumlvs = $DB->get_recordset_sql($sql, $params);

        $unsubscribableforumlvs = array();
        foreach($forumlvs as $forumlv) {
            if (empty($forumlv->visible)) {
                // The forumlv is hidden - check if the user can view the forumlv.
                $context = \context_module::instance($forumlv->cm);
                if (!has_capability('moodle/course:viewhiddenactivities', $context)) {
                    // The user can't see the hidden forumlv to cannot unsubscribe.
                    continue;
                }
            }

            $unsubscribableforumlvs[] = $forumlv;
        }
        $forumlvs->close();

        return $unsubscribableforumlvs;
    }

    /**
     * Get the list of potential subscribers to a forumlv.
     *
     * @param context_module $context the forumlv context.
     * @param integer $groupid the id of a group, or 0 for all groups.
     * @param string $fields the list of fields to return for each user. As for get_users_by_capability.
     * @param string $sort sort order. As for get_users_by_capability.
     * @return array list of users.
     */
    public static function get_potential_subscribers($context, $groupid, $fields, $sort = '') {
        global $DB;

        // Only active enrolled users or everybody on the frontpage.
        list($esql, $params) = get_enrolled_sql($context, 'mod/forumlv:allowforcesubscribe', $groupid, true);
        if (!$sort) {
            list($sort, $sortparams) = users_order_by_sql('u');
            $params = array_merge($params, $sortparams);
        }

        $sql = "SELECT $fields
                FROM {user} u
                JOIN ($esql) je ON je.id = u.id
            ORDER BY $sort";

        return $DB->get_records_sql($sql, $params);
    }

    /**
     * Fetch the forumlv subscription data for the specified userid and forumlv.
     *
     * @param int $forumlvid The forumlv to retrieve a cache for
     * @param int $userid The user ID
     * @return boolean
     */
    public static function fetch_subscription_cache($forumlvid, $userid) {
        if (isset(self::$forumlvcache[$userid]) && isset(self::$forumlvcache[$userid][$forumlvid])) {
            return self::$forumlvcache[$userid][$forumlvid];
        }
        self::fill_subscription_cache($forumlvid, $userid);

        if (!isset(self::$forumlvcache[$userid]) || !isset(self::$forumlvcache[$userid][$forumlvid])) {
            return false;
        }

        return self::$forumlvcache[$userid][$forumlvid];
    }

    /**
     * Fill the forumlv subscription data for the specified userid and forumlv.
     *
     * If the userid is not specified, then all subscription data for that forumlv is fetched in a single query and used
     * for subsequent lookups without requiring further database queries.
     *
     * @param int $forumlvid The forumlv to retrieve a cache for
     * @param int $userid The user ID
     * @return void
     */
    public static function fill_subscription_cache($forumlvid, $userid = null) {
        global $DB;

        if (!isset(self::$fetchedforumlvs[$forumlvid])) {
            // This forumlv has not been fetched as a whole.
            if (isset($userid)) {
                if (!isset(self::$forumlvcache[$userid])) {
                    self::$forumlvcache[$userid] = array();
                }

                if (!isset(self::$forumlvcache[$userid][$forumlvid])) {
                    if ($DB->record_exists('forumlv_subscriptions', array(
                        'userid' => $userid,
                        'forumlv' => $forumlvid,
                    ))) {
                        self::$forumlvcache[$userid][$forumlvid] = true;
                    } else {
                        self::$forumlvcache[$userid][$forumlvid] = false;
                    }
                }
            } else {
                $subscriptions = $DB->get_recordset('forumlv_subscriptions', array(
                    'forumlv' => $forumlvid,
                ), '', 'id, userid');
                foreach ($subscriptions as $id => $data) {
                    if (!isset(self::$forumlvcache[$data->userid])) {
                        self::$forumlvcache[$data->userid] = array();
                    }
                    self::$forumlvcache[$data->userid][$forumlvid] = true;
                }
                self::$fetchedforumlvs[$forumlvid] = true;
                $subscriptions->close();
            }
        }
    }

    /**
     * Fill the forumlv subscription data for all forumlvs that the specified userid can subscribe to in the specified course.
     *
     * @param int $courseid The course to retrieve a cache for
     * @param int $userid The user ID
     * @return void
     */
    public static function fill_subscription_cache_for_course($courseid, $userid) {
        global $DB;

        if (!isset(self::$forumlvcache[$userid])) {
            self::$forumlvcache[$userid] = array();
        }

        $sql = "SELECT
                    f.id AS forumlvid,
                    s.id AS subscriptionid
                FROM {forumlv} f
                LEFT JOIN {forumlv_subscriptions} s ON (s.forumlv = f.id AND s.userid = :userid)
                WHERE f.course = :course
                AND f.forcesubscribe <> :subscriptionforced";

        $subscriptions = $DB->get_recordset_sql($sql, array(
            'course' => $courseid,
            'userid' => $userid,
            'subscriptionforced' => FORUMLV_FORCESUBSCRIBE,
        ));

        foreach ($subscriptions as $id => $data) {
            self::$forumlvcache[$userid][$id] = !empty($data->subscriptionid);
        }
        $subscriptions->close();
    }

    /**
     * Returns a list of user objects who are subscribed to this forumlv.
     *
     * @param stdClass $forumlv The forumlv record.
     * @param int $groupid The group id if restricting subscriptions to a group of users, or 0 for all.
     * @param context_module $context the forumlv context, to save re-fetching it where possible.
     * @param string $fields requested user fields (with "u." table prefix).
     * @param boolean $includediscussionsubscriptions Whether to take discussion subscriptions and unsubscriptions into consideration.
     * @return array list of users.
     */
    public static function fetch_subscribed_users($forumlv, $groupid = 0, $context = null, $fields = null,
            $includediscussionsubscriptions = false) {
        global $CFG, $DB;

        if (empty($fields)) {
            $allnames = get_all_user_name_fields(true, 'u');
            $fields ="u.id,
                      u.username,
                      $allnames,
                      u.maildisplay,
                      u.mailformat,
                      u.maildigest,
                      u.imagealt,
                      u.email,
                      u.emailstop,
                      u.city,
                      u.country,
                      u.lastaccess,
                      u.lastlogin,
                      u.picture,
                      u.timezone,
                      u.theme,
                      u.lang,
                      u.trackforumlvs,
                      u.mnethostid";
        }

        // Retrieve the forumlv context if it wasn't specified.
        $context = forumlv_get_context($forumlv->id, $context);

        if (self::is_forcesubscribed($forumlv)) {
            $results = \mod_forumlv\subscriptions::get_potential_subscribers($context, $groupid, $fields, "u.email ASC");

        } else {
            // Only active enrolled users or everybody on the frontpage.
            list($esql, $params) = get_enrolled_sql($context, '', $groupid, true);
            $params['forumlvid'] = $forumlv->id;

            if ($includediscussionsubscriptions) {
                $params['sforumlvid'] = $forumlv->id;
                $params['dsforumlvid'] = $forumlv->id;
                $params['unsubscribed'] = self::FORUMLV_DISCUSSION_UNSUBSCRIBED;

                $sql = "SELECT $fields
                        FROM (
                            SELECT userid FROM {forumlv_subscriptions} s
                            WHERE
                                s.forumlv = :sforumlvid
                                UNION
                            SELECT userid FROM {forumlv_discussion_subs} ds
                            WHERE
                                ds.forumlv = :dsforumlvid AND ds.preference <> :unsubscribed
                        ) subscriptions
                        JOIN {user} u ON u.id = subscriptions.userid
                        JOIN ($esql) je ON je.id = u.id
                        ORDER BY u.email ASC";

            } else {
                $sql = "SELECT $fields
                        FROM {user} u
                        JOIN ($esql) je ON je.id = u.id
                        JOIN {forumlv_subscriptions} s ON s.userid = u.id
                        WHERE
                          s.forumlv = :forumlvid
                        ORDER BY u.email ASC";
            }
            $results = $DB->get_records_sql($sql, $params);
        }

        // Guest user should never be subscribed to a forumlv.
        unset($results[$CFG->siteguest]);

        // Apply the activity module availability resetrictions.
        $cm = get_coursemodule_from_instance('forumlv', $forumlv->id, $forumlv->course);
        $modinfo = get_fast_modinfo($forumlv->course);
        $info = new \core_availability\info_module($modinfo->get_cm($cm->id));
        $results = $info->filter_user_list($results);

        return $results;
    }

    /**
     * Retrieve the discussion subscription data for the specified userid and forumlv.
     *
     * This is returned as an array of discussions for that forumlv which contain the preference in a stdClass.
     *
     * @param int $forumlvid The forumlv to retrieve a cache for
     * @param int $userid The user ID
     * @return array of stdClass objects with one per discussion in the forumlv.
     */
    public static function fetch_discussion_subscription($forumlvid, $userid = null) {
        self::fill_discussion_subscription_cache($forumlvid, $userid);

        if (!isset(self::$forumlvdiscussioncache[$userid]) || !isset(self::$forumlvdiscussioncache[$userid][$forumlvid])) {
            return array();
        }

        return self::$forumlvdiscussioncache[$userid][$forumlvid];
    }

    /**
     * Fill the discussion subscription data for the specified userid and forumlv.
     *
     * If the userid is not specified, then all discussion subscription data for that forumlv is fetched in a single query
     * and used for subsequent lookups without requiring further database queries.
     *
     * @param int $forumlvid The forumlv to retrieve a cache for
     * @param int $userid The user ID
     * @return void
     */
    public static function fill_discussion_subscription_cache($forumlvid, $userid = null) {
        global $DB;

        if (!isset(self::$discussionfetchedforumlvs[$forumlvid])) {
            // This forumlv hasn't been fetched as a whole yet.
            if (isset($userid)) {
                if (!isset(self::$forumlvdiscussioncache[$userid])) {
                    self::$forumlvdiscussioncache[$userid] = array();
                }

                if (!isset(self::$forumlvdiscussioncache[$userid][$forumlvid])) {
                    $subscriptions = $DB->get_recordset('forumlv_discussion_subs', array(
                        'userid' => $userid,
                        'forumlv' => $forumlvid,
                    ), null, 'id, discussion, preference');
                    foreach ($subscriptions as $id => $data) {
                        self::add_to_discussion_cache($forumlvid, $userid, $data->discussion, $data->preference);
                    }
                    $subscriptions->close();
                }
            } else {
                $subscriptions = $DB->get_recordset('forumlv_discussion_subs', array(
                    'forumlv' => $forumlvid,
                ), null, 'id, userid, discussion, preference');
                foreach ($subscriptions as $id => $data) {
                    self::add_to_discussion_cache($forumlvid, $data->userid, $data->discussion, $data->preference);
                }
                self::$discussionfetchedforumlvs[$forumlvid] = true;
                $subscriptions->close();
            }
        }
    }

    /**
     * Add the specified discussion and user preference to the discussion
     * subscription cache.
     *
     * @param int $forumlvid The ID of the forumlv that this preference belongs to
     * @param int $userid The ID of the user that this preference belongs to
     * @param int $discussion The ID of the discussion that this preference relates to
     * @param int $preference The preference to store
     */
    protected static function add_to_discussion_cache($forumlvid, $userid, $discussion, $preference) {
        if (!isset(self::$forumlvdiscussioncache[$userid])) {
            self::$forumlvdiscussioncache[$userid] = array();
        }

        if (!isset(self::$forumlvdiscussioncache[$userid][$forumlvid])) {
            self::$forumlvdiscussioncache[$userid][$forumlvid] = array();
        }

        self::$forumlvdiscussioncache[$userid][$forumlvid][$discussion] = $preference;
    }

    /**
     * Reset the discussion cache.
     *
     * This cache is used to reduce the number of database queries when
     * checking forumlv discussion subscription states.
     */
    public static function reset_discussion_cache() {
        self::$forumlvdiscussioncache = array();
        self::$discussionfetchedforumlvs = array();
    }

    /**
     * Reset the forumlv cache.
     *
     * This cache is used to reduce the number of database queries when
     * checking forumlv subscription states.
     */
    public static function reset_forumlv_cache() {
        self::$forumlvcache = array();
        self::$fetchedforumlvs = array();
    }

    /**
     * Adds user to the subscriber list.
     *
     * @param int $userid The ID of the user to subscribe
     * @param \stdClass $forumlv The forumlv record for this forumlv.
     * @param \context_module|null $context Module context, may be omitted if not known or if called for the current
     *      module set in page.
     * @param boolean $userrequest Whether the user requested this change themselves. This has an effect on whether
     *     discussion subscriptions are removed too.
     * @return bool|int Returns true if the user is already subscribed, or the forumlv_subscriptions ID if the user was
     *     successfully subscribed.
     */
    public static function subscribe_user($userid, $forumlv, $context = null, $userrequest = false) {
        global $DB;

        if (self::is_subscribed($userid, $forumlv)) {
            return true;
        }

        $sub = new \stdClass();
        $sub->userid  = $userid;
        $sub->forumlv = $forumlv->id;

        $result = $DB->insert_record("forumlv_subscriptions", $sub);

        if ($userrequest) {
            $discussionsubscriptions = $DB->get_recordset('forumlv_discussion_subs', array('userid' => $userid, 'forumlv' => $forumlv->id));
            $DB->delete_records_select('forumlv_discussion_subs',
                    'userid = :userid AND forumlv = :forumlvid AND preference <> :preference', array(
                        'userid' => $userid,
                        'forumlvid' => $forumlv->id,
                        'preference' => self::FORUMLV_DISCUSSION_UNSUBSCRIBED,
                    ));

            // Reset the subscription caches for this forumlv.
            // We know that the there were previously entries and there aren't any more.
            if (isset(self::$forumlvdiscussioncache[$userid]) && isset(self::$forumlvdiscussioncache[$userid][$forumlv->id])) {
                foreach (self::$forumlvdiscussioncache[$userid][$forumlv->id] as $discussionid => $preference) {
                    if ($preference != self::FORUMLV_DISCUSSION_UNSUBSCRIBED) {
                        unset(self::$forumlvdiscussioncache[$userid][$forumlv->id][$discussionid]);
                    }
                }
            }
        }

        // Reset the cache for this forumlv.
        self::$forumlvcache[$userid][$forumlv->id] = true;

        $context = forumlv_get_context($forumlv->id, $context);
        $params = array(
            'context' => $context,
            'objectid' => $result,
            'relateduserid' => $userid,
            'other' => array('forumlvid' => $forumlv->id),

        );
        $event  = event\subscription_created::create($params);
        if ($userrequest && $discussionsubscriptions) {
            foreach ($discussionsubscriptions as $subscription) {
                $event->add_record_snapshot('forumlv_discussion_subs', $subscription);
            }
            $discussionsubscriptions->close();
        }
        $event->trigger();

        return $result;
    }

    /**
     * Removes user from the subscriber list
     *
     * @param int $userid The ID of the user to unsubscribe
     * @param \stdClass $forumlv The forumlv record for this forumlv.
     * @param \context_module|null $context Module context, may be omitted if not known or if called for the current
     *     module set in page.
     * @param boolean $userrequest Whether the user requested this change themselves. This has an effect on whether
     *     discussion subscriptions are removed too.
     * @return boolean Always returns true.
     */
    public static function unsubscribe_user($userid, $forumlv, $context = null, $userrequest = false) {
        global $DB;

        $sqlparams = array(
            'userid' => $userid,
            'forumlv' => $forumlv->id,
        );
        $DB->delete_records('forumlv_digests', $sqlparams);

        if ($forumlvsubscription = $DB->get_record('forumlv_subscriptions', $sqlparams)) {
            $DB->delete_records('forumlv_subscriptions', array('id' => $forumlvsubscription->id));

            if ($userrequest) {
                $discussionsubscriptions = $DB->get_recordset('forumlv_discussion_subs', $sqlparams);
                $DB->delete_records('forumlv_discussion_subs',
                        array('userid' => $userid, 'forumlv' => $forumlv->id, 'preference' => self::FORUMLV_DISCUSSION_UNSUBSCRIBED));

                // We know that the there were previously entries and there aren't any more.
                if (isset(self::$forumlvdiscussioncache[$userid]) && isset(self::$forumlvdiscussioncache[$userid][$forumlv->id])) {
                    self::$forumlvdiscussioncache[$userid][$forumlv->id] = array();
                }
            }

            // Reset the cache for this forumlv.
            self::$forumlvcache[$userid][$forumlv->id] = false;

            $context = forumlv_get_context($forumlv->id, $context);
            $params = array(
                'context' => $context,
                'objectid' => $forumlvsubscription->id,
                'relateduserid' => $userid,
                'other' => array('forumlvid' => $forumlv->id),

            );
            $event = event\subscription_deleted::create($params);
            $event->add_record_snapshot('forumlv_subscriptions', $forumlvsubscription);
            if ($userrequest && $discussionsubscriptions) {
                foreach ($discussionsubscriptions as $subscription) {
                    $event->add_record_snapshot('forumlv_discussion_subs', $subscription);
                }
                $discussionsubscriptions->close();
            }
            $event->trigger();
        }

        return true;
    }

    /**
     * Subscribes the user to the specified discussion.
     *
     * @param int $userid The userid of the user being subscribed
     * @param \stdClass $discussion The discussion to subscribe to
     * @param \context_module|null $context Module context, may be omitted if not known or if called for the current
     *     module set in page.
     * @return boolean Whether a change was made
     */
    public static function subscribe_user_to_discussion($userid, $discussion, $context = null) {
        global $DB;

        // First check whether the user is subscribed to the discussion already.
        $subscription = $DB->get_record('forumlv_discussion_subs', array('userid' => $userid, 'discussion' => $discussion->id));
        if ($subscription) {
            if ($subscription->preference != self::FORUMLV_DISCUSSION_UNSUBSCRIBED) {
                // The user is already subscribed to the discussion. Ignore.
                return false;
            }
        }
        // No discussion-level subscription. Check for a forumlv level subscription.
        if ($DB->record_exists('forumlv_subscriptions', array('userid' => $userid, 'forumlv' => $discussion->forumlv))) {
            if ($subscription && $subscription->preference == self::FORUMLV_DISCUSSION_UNSUBSCRIBED) {
                // The user is subscribed to the forumlv, but unsubscribed from the discussion, delete the discussion preference.
                $DB->delete_records('forumlv_discussion_subs', array('id' => $subscription->id));
                unset(self::$forumlvdiscussioncache[$userid][$discussion->forumlv][$discussion->id]);
            } else {
                // The user is already subscribed to the forumlv. Ignore.
                return false;
            }
        } else {
            if ($subscription) {
                $subscription->preference = time();
                $DB->update_record('forumlv_discussion_subs', $subscription);
            } else {
                $subscription = new \stdClass();
                $subscription->userid  = $userid;
                $subscription->forumlv = $discussion->forumlv;
                $subscription->discussion = $discussion->id;
                $subscription->preference = time();

                $subscription->id = $DB->insert_record('forumlv_discussion_subs', $subscription);
                self::$forumlvdiscussioncache[$userid][$discussion->forumlv][$discussion->id] = $subscription->preference;
            }
        }

        $context = forumlv_get_context($discussion->forumlv, $context);
        $params = array(
            'context' => $context,
            'objectid' => $subscription->id,
            'relateduserid' => $userid,
            'other' => array(
                'forumlvid' => $discussion->forumlv,
                'discussion' => $discussion->id,
            ),

        );
        $event  = event\discussion_subscription_created::create($params);
        $event->trigger();

        return true;
    }
    /**
     * Unsubscribes the user from the specified discussion.
     *
     * @param int $userid The userid of the user being unsubscribed
     * @param \stdClass $discussion The discussion to unsubscribe from
     * @param \context_module|null $context Module context, may be omitted if not known or if called for the current
     *     module set in page.
     * @return boolean Whether a change was made
     */
    public static function unsubscribe_user_from_discussion($userid, $discussion, $context = null) {
        global $DB;

        // First check whether the user's subscription preference for this discussion.
        $subscription = $DB->get_record('forumlv_discussion_subs', array('userid' => $userid, 'discussion' => $discussion->id));
        if ($subscription) {
            if ($subscription->preference == self::FORUMLV_DISCUSSION_UNSUBSCRIBED) {
                // The user is already unsubscribed from the discussion. Ignore.
                return false;
            }
        }
        // No discussion-level preference. Check for a forumlv level subscription.
        if (!$DB->record_exists('forumlv_subscriptions', array('userid' => $userid, 'forumlv' => $discussion->forumlv))) {
            if ($subscription && $subscription->preference != self::FORUMLV_DISCUSSION_UNSUBSCRIBED) {
                // The user is not subscribed to the forumlv, but subscribed from the discussion, delete the discussion subscription.
                $DB->delete_records('forumlv_discussion_subs', array('id' => $subscription->id));
                unset(self::$forumlvdiscussioncache[$userid][$discussion->forumlv][$discussion->id]);
            } else {
                // The user is not subscribed from the forumlv. Ignore.
                return false;
            }
        } else {
            if ($subscription) {
                $subscription->preference = self::FORUMLV_DISCUSSION_UNSUBSCRIBED;
                $DB->update_record('forumlv_discussion_subs', $subscription);
            } else {
                $subscription = new \stdClass();
                $subscription->userid  = $userid;
                $subscription->forumlv = $discussion->forumlv;
                $subscription->discussion = $discussion->id;
                $subscription->preference = self::FORUMLV_DISCUSSION_UNSUBSCRIBED;

                $subscription->id = $DB->insert_record('forumlv_discussion_subs', $subscription);
            }
            self::$forumlvdiscussioncache[$userid][$discussion->forumlv][$discussion->id] = $subscription->preference;
        }

        $context = forumlv_get_context($discussion->forumlv, $context);
        $params = array(
            'context' => $context,
            'objectid' => $subscription->id,
            'relateduserid' => $userid,
            'other' => array(
                'forumlvid' => $discussion->forumlv,
                'discussion' => $discussion->id,
            ),

        );
        $event  = event\discussion_subscription_deleted::create($params);
        $event->trigger();

        return true;
    }

}
