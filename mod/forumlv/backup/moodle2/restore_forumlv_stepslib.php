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

/**
 * Define all the restore steps that will be used by the restore_forumlv_activity_task
 */

/**
 * Structure step to restore one forumlv activity
 */
class restore_forumlv_activity_structure_step extends restore_activity_structure_step {

    protected function define_structure() {

        $paths = array();
        $userinfo = $this->get_setting_value('userinfo');

        $paths[] = new restore_path_element('forumlv', '/activity/forumlv');
        if ($userinfo) {
            $paths[] = new restore_path_element('forumlv_discussion', '/activity/forumlv/discussions/discussion');
            $paths[] = new restore_path_element('forumlv_post', '/activity/forumlv/discussions/discussion/posts/post');
            $paths[] = new restore_path_element('forumlv_discussion_sub', '/activity/forumlv/discussions/discussion/discussion_subs/discussion_sub');
            $paths[] = new restore_path_element('forumlv_rating', '/activity/forumlv/discussions/discussion/posts/post/ratings/rating');
            $paths[] = new restore_path_element('forumlv_subscription', '/activity/forumlv/subscriptions/subscription');
            $paths[] = new restore_path_element('forumlv_digest', '/activity/forumlv/digests/digest');
            $paths[] = new restore_path_element('forumlv_read', '/activity/forumlv/readposts/read');
            $paths[] = new restore_path_element('forumlv_track', '/activity/forumlv/trackedprefs/track');
        }

        // Return the paths wrapped into standard activity structure
        return $this->prepare_activity_structure($paths);
    }

    protected function process_forumlv($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->course = $this->get_courseid();

        $data->assesstimestart = $this->apply_date_offset($data->assesstimestart);
        $data->assesstimefinish = $this->apply_date_offset($data->assesstimefinish);
        if ($data->scale < 0) { // scale found, get mapping
            $data->scale = -($this->get_mappingid('scale', abs($data->scale)));
        }

        $newitemid = $DB->insert_record('forumlv', $data);
        $this->apply_activity_instance($newitemid);
    }

    protected function process_forumlv_discussion($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->course = $this->get_courseid();

        $data->forumlv = $this->get_new_parentid('forumlv');
        $data->timemodified = $this->apply_date_offset($data->timemodified);
        $data->timestart = $this->apply_date_offset($data->timestart);
        $data->timeend = $this->apply_date_offset($data->timeend);
        $data->userid = $this->get_mappingid('user', $data->userid);
        $data->groupid = $this->get_mappingid('group', $data->groupid);
        $data->usermodified = $this->get_mappingid('user', $data->usermodified);

        $newitemid = $DB->insert_record('forumlv_discussions', $data);
        $this->set_mapping('forumlv_discussion', $oldid, $newitemid);
    }

    protected function process_forumlv_post($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->discussion = $this->get_new_parentid('forumlv_discussion');
        $data->created = $this->apply_date_offset($data->created);
        $data->modified = $this->apply_date_offset($data->modified);
        $data->userid = $this->get_mappingid('user', $data->userid);
        // If post has parent, map it (it has been already restored)
        if (!empty($data->parent)) {
            $data->parent = $this->get_mappingid('forumlv_post', $data->parent);
        }

        $newitemid = $DB->insert_record('forumlv_posts', $data);
        $this->set_mapping('forumlv_post', $oldid, $newitemid, true);

        // If !post->parent, it's the 1st post. Set it in discussion
        if (empty($data->parent)) {
            $DB->set_field('forumlv_discussions', 'firstpost', $newitemid, array('id' => $data->discussion));
        }
    }

    protected function process_forumlv_rating($data) {
        global $DB;

        $data = (object)$data;

        // Cannot use ratings API, cause, it's missing the ability to specify times (modified/created)
        $data->contextid = $this->task->get_contextid();
        $data->itemid    = $this->get_new_parentid('forumlv_post');
        if ($data->scaleid < 0) { // scale found, get mapping
            $data->scaleid = -($this->get_mappingid('scale', abs($data->scaleid)));
        }
        $data->rating = $data->value;
        $data->userid = $this->get_mappingid('user', $data->userid);
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        // We need to check that component and ratingarea are both set here.
        if (empty($data->component)) {
            $data->component = 'mod_forumlv';
        }
        if (empty($data->ratingarea)) {
            $data->ratingarea = 'post';
        }

        $newitemid = $DB->insert_record('rating', $data);
    }

    protected function process_forumlv_subscription($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->forumlv = $this->get_new_parentid('forumlv');
        $data->userid = $this->get_mappingid('user', $data->userid);

        $newitemid = $DB->insert_record('forumlv_subscriptions', $data);
        $this->set_mapping('forumlv_subscription', $oldid, $newitemid, true);

    }

    protected function process_forumlv_discussion_sub($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->discussion = $this->get_new_parentid('forumlv_discussion');
        $data->forumlv = $this->get_new_parentid('forumlv');
        $data->userid = $this->get_mappingid('user', $data->userid);

        $newitemid = $DB->insert_record('forumlv_discussion_subs', $data);
        $this->set_mapping('forumlv_discussion_sub', $oldid, $newitemid, true);
    }

    protected function process_forumlv_digest($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->forumlv = $this->get_new_parentid('forumlv');
        $data->userid = $this->get_mappingid('user', $data->userid);

        $newitemid = $DB->insert_record('forumlv_digests', $data);
    }

    protected function process_forumlv_read($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->forumlvid = $this->get_new_parentid('forumlv');
        $data->discussionid = $this->get_mappingid('forumlv_discussion', $data->discussionid);
        $data->postid = $this->get_mappingid('forumlv_post', $data->postid);
        $data->userid = $this->get_mappingid('user', $data->userid);

        $newitemid = $DB->insert_record('forumlv_read', $data);
    }

    protected function process_forumlv_track($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->forumlvid = $this->get_new_parentid('forumlv');
        $data->userid = $this->get_mappingid('user', $data->userid);

        $newitemid = $DB->insert_record('forumlv_track_prefs', $data);
    }

    protected function after_execute() {
        // Add forumlv related files, no need to match by itemname (just internally handled context)
        $this->add_related_files('mod_forumlv', 'intro', null);

        // Add post related files, matching by itemname = 'forumlv_post'
        $this->add_related_files('mod_forumlv', 'post', 'forumlv_post');
        $this->add_related_files('mod_forumlv', 'attachment', 'forumlv_post');
    }

    protected function after_restore() {
        global $DB;

        // If the forumlv is of type 'single' and no discussion has been ignited
        // (non-userinfo backup/restore) create the discussion here, using forumlv
        // information as base for the initial post.
        $forumlvid = $this->task->get_activityid();
        $forumlvrec = $DB->get_record('forumlv', array('id' => $forumlvid));
        if ($forumlvrec->type == 'single' && !$DB->record_exists('forumlv_discussions', array('forumlv' => $forumlvid))) {
            // Create single discussion/lead post from forumlv data
            $sd = new stdClass();
            $sd->course   = $forumlvrec->course;
            $sd->forumlv    = $forumlvrec->id;
            $sd->name     = $forumlvrec->name;
            $sd->assessed = $forumlvrec->assessed;
            $sd->message  = $forumlvrec->intro;
            $sd->messageformat = $forumlvrec->introformat;
            $sd->messagetrust  = true;
            $sd->mailnow  = false;
            $sdid = forumlv_add_discussion($sd, null, null, $this->task->get_userid());
            // Mark the post as mailed
            $DB->set_field ('forumlv_posts','mailed', '1', array('discussion' => $sdid));
            // Copy all the files from mod_foum/intro to mod_forumlv/post
            $fs = get_file_storage();
            $files = $fs->get_area_files($this->task->get_contextid(), 'mod_forumlv', 'intro');
            foreach ($files as $file) {
                $newfilerecord = new stdClass();
                $newfilerecord->filearea = 'post';
                $newfilerecord->itemid   = $DB->get_field('forumlv_discussions', 'firstpost', array('id' => $sdid));
                $fs->create_file_from_storedfile($newfilerecord, $file);
            }
        }
    }
}
