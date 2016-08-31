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
 * @package    mod_forumlv
 * @subpackage backup-moodle2
 * @copyright  2010 onwards Eloy Lafuente (stronk7) {@link http://stronk7.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/forumlv/backup/moodle2/restore_forumlv_stepslib.php'); // Because it exists (must)

/**
 * forumlv restore task that provides all the settings and steps to perform one
 * complete restore of the activity
 */
class restore_forumlv_activity_task extends restore_activity_task {

    /**
     * Define (add) particular settings this activity can have
     */
    protected function define_my_settings() {
        // No particular settings for this activity
    }

    /**
     * Define (add) particular steps this activity can have
     */
    protected function define_my_steps() {
        // Choice only has one structure step
        $this->add_step(new restore_forumlv_activity_structure_step('forumlv_structure', 'forumlv.xml'));
    }

    /**
     * Define the contents in the activity that must be
     * processed by the link decoder
     */
    static public function define_decode_contents() {
        $contents = array();

        $contents[] = new restore_decode_content('forumlv', array('intro'), 'forumlv');
        $contents[] = new restore_decode_content('forumlv_posts', array('message'), 'forumlv_post');

        return $contents;
    }

    /**
     * Define the decoding rules for links belonging
     * to the activity to be executed by the link decoder
     */
    static public function define_decode_rules() {
        $rules = array();

        // List of forumlvs in course
        $rules[] = new restore_decode_rule('FORUMLVINDEX', '/mod/forumlv/index.php?id=$1', 'course');
        // Forumlv by cm->id and forumlv->id
        $rules[] = new restore_decode_rule('FORUMLVVIEWBYID', '/mod/forumlv/view.php?id=$1', 'course_module');
        $rules[] = new restore_decode_rule('FORUMLVVIEWBYF', '/mod/forumlv/view.php?f=$1', 'forumlv');
        // Link to forumlv discussion
        $rules[] = new restore_decode_rule('FORUMLVDISCUSSIONVIEW', '/mod/forumlv/discuss.php?d=$1', 'forumlv_discussion');
        // Link to discussion with parent and with anchor posts
        $rules[] = new restore_decode_rule('FORUMLVDISCUSSIONVIEWPARENT', '/mod/forumlv/discuss.php?d=$1&parent=$2',
                                           array('forumlv_discussion', 'forumlv_post'));
        $rules[] = new restore_decode_rule('FORUMLVDISCUSSIONVIEWINSIDE', '/mod/forumlv/discuss.php?d=$1#$2',
                                           array('forumlv_discussion', 'forumlv_post'));

        return $rules;
    }

    /**
     * Define the restore log rules that will be applied
     * by the {@link restore_logs_processor} when restoring
     * forumlv logs. It must return one array
     * of {@link restore_log_rule} objects
     */
    static public function define_restore_log_rules() {
        $rules = array();

        $rules[] = new restore_log_rule('forumlv', 'add', 'view.php?id={course_module}', '{forumlv}');
        $rules[] = new restore_log_rule('forumlv', 'update', 'view.php?id={course_module}', '{forumlv}');
        $rules[] = new restore_log_rule('forumlv', 'view', 'view.php?id={course_module}', '{forumlv}');
        $rules[] = new restore_log_rule('forumlv', 'view forumlv', 'view.php?id={course_module}', '{forumlv}');
        $rules[] = new restore_log_rule('forumlv', 'mark read', 'view.php?f={forumlv}', '{forumlv}');
        $rules[] = new restore_log_rule('forumlv', 'start tracking', 'view.php?f={forumlv}', '{forumlv}');
        $rules[] = new restore_log_rule('forumlv', 'stop tracking', 'view.php?f={forumlv}', '{forumlv}');
        $rules[] = new restore_log_rule('forumlv', 'subscribe', 'view.php?f={forumlv}', '{forumlv}');
        $rules[] = new restore_log_rule('forumlv', 'unsubscribe', 'view.php?f={forumlv}', '{forumlv}');
        $rules[] = new restore_log_rule('forumlv', 'subscriber', 'subscribers.php?id={forumlv}', '{forumlv}');
        $rules[] = new restore_log_rule('forumlv', 'subscribers', 'subscribers.php?id={forumlv}', '{forumlv}');
        $rules[] = new restore_log_rule('forumlv', 'view subscribers', 'subscribers.php?id={forumlv}', '{forumlv}');
        $rules[] = new restore_log_rule('forumlv', 'add discussion', 'discuss.php?d={forumlv_discussion}', '{forumlv_discussion}');
        $rules[] = new restore_log_rule('forumlv', 'view discussion', 'discuss.php?d={forumlv_discussion}', '{forumlv_discussion}');
        $rules[] = new restore_log_rule('forumlv', 'move discussion', 'discuss.php?d={forumlv_discussion}', '{forumlv_discussion}');
        $rules[] = new restore_log_rule('forumlv', 'delete discussi', 'view.php?id={course_module}', '{forumlv}',
                                        null, 'delete discussion');
        $rules[] = new restore_log_rule('forumlv', 'delete discussion', 'view.php?id={course_module}', '{forumlv}');
        $rules[] = new restore_log_rule('forumlv', 'add post', 'discuss.php?d={forumlv_discussion}&parent={forumlv_post}', '{forumlv_post}');
        $rules[] = new restore_log_rule('forumlv', 'update post', 'discuss.php?d={forumlv_discussion}#p{forumlv_post}&parent={forumlv_post}', '{forumlv_post}');
        $rules[] = new restore_log_rule('forumlv', 'update post', 'discuss.php?d={forumlv_discussion}&parent={forumlv_post}', '{forumlv_post}');
        $rules[] = new restore_log_rule('forumlv', 'prune post', 'discuss.php?d={forumlv_discussion}', '{forumlv_post}');
        $rules[] = new restore_log_rule('forumlv', 'delete post', 'discuss.php?d={forumlv_discussion}', '[post]');

        return $rules;
    }

    /**
     * Define the restore log rules that will be applied
     * by the {@link restore_logs_processor} when restoring
     * course logs. It must return one array
     * of {@link restore_log_rule} objects
     *
     * Note this rules are applied when restoring course logs
     * by the restore final task, but are defined here at
     * activity level. All them are rules not linked to any module instance (cmid = 0)
     */
    static public function define_restore_log_rules_for_course() {
        $rules = array();

        $rules[] = new restore_log_rule('forumlv', 'view forumlvs', 'index.php?id={course}', null);
        $rules[] = new restore_log_rule('forumlv', 'subscribeall', 'index.php?id={course}', '{course}');
        $rules[] = new restore_log_rule('forumlv', 'unsubscribeall', 'index.php?id={course}', '{course}');
        $rules[] = new restore_log_rule('forumlv', 'user report', 'user.php?course={course}&id={user}&mode=[mode]', '{user}');
        $rules[] = new restore_log_rule('forumlv', 'search', 'search.php?id={course}&search=[searchenc]', '[search]');

        return $rules;
    }
}
