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
 * @package    mod_wikilv
 * @subpackage backup-moodle2
 * @copyright 2010 onwards Eloy Lafuente (stronk7) {@link http://stronk7.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Define all the backup steps that will be used by the backup_wikilv_activity_task
 */

/**
 * Define the complete wikilv structure for backup, with file and id annotations
 */
class backup_wikilv_activity_structure_step extends backup_activity_structure_step {

    protected function define_structure() {

        // To know if we are including userinfo
        $userinfo = $this->get_setting_value('userinfo');

        // Define each element separated
        $wikilv = new backup_nested_element('wikilv', array('id'), array('name', 'intro', 'introformat', 'timecreated', 'timemodified', 'firstpagetitle', 'wikilvmode', 'defaultformat', 'forceformat', 'editbegin', 'editend'));

        $subwikilvs = new backup_nested_element('subwikilvs');

        $subwikilv = new backup_nested_element('subwikilv', array('id'), array('groupid', 'userid'));

        $pages = new backup_nested_element('pages');

        $page = new backup_nested_element('page', array('id'), array('title', 'cachedcontent', 'timecreated', 'timemodified', 'timerendered', 'userid', 'pageviews', 'readonly'));

        $synonyms = new backup_nested_element('synonyms');

        $synonym = new backup_nested_element('synonym', array('id'), array('pageid', 'pagesynonym'));

        $links = new backup_nested_element('links');

        $link = new backup_nested_element('link', array('id'), array('frompageid', 'topageid', 'tomissingpage'));

        $versions = new backup_nested_element('versions');

        $version = new backup_nested_element('version', array('id'), array('content', 'contentformat', 'version', 'timecreated', 'userid'));

        $tags = new backup_nested_element('tags');

        $tag = new backup_nested_element('tag', array('id'), array('name', 'rawname'));

        // Build the tree
        $wikilv->add_child($subwikilvs);
        $subwikilvs->add_child($subwikilv);

        $subwikilv->add_child($pages);
        $pages->add_child($page);

        $subwikilv->add_child($synonyms);
        $synonyms->add_child($synonym);

        $subwikilv->add_child($links);
        $links->add_child($link);

        $page->add_child($versions);
        $versions->add_child($version);

        $page->add_child($tags);
        $tags->add_child($tag);

        // Define sources
        $wikilv->set_source_table('wikilv', array('id' => backup::VAR_ACTIVITYID));

        // All these source definitions only happen if we are including user info
        if ($userinfo) {
            $subwikilv->set_source_sql('
                SELECT *
                  FROM {wikilv_subwikilvs}
                 WHERE wikilvid = ?', array(backup::VAR_PARENTID));

            $page->set_source_table('wikilv_pages', array('subwikilvid' => backup::VAR_PARENTID));

            $synonym->set_source_table('wikilv_synonyms', array('subwikilvid' => backup::VAR_PARENTID));

            $link->set_source_table('wikilv_links', array('subwikilvid' => backup::VAR_PARENTID));

            $version->set_source_table('wikilv_versions', array('pageid' => backup::VAR_PARENTID));

            $tag->set_source_sql('SELECT t.id, t.name, t.rawname
                                    FROM {tag} t
                                    JOIN {tag_instance} ti ON ti.tagid = t.id
                                   WHERE ti.itemtype = ?
                                     AND ti.component = ?
                                     AND ti.itemid = ?', array(
                                         backup_helper::is_sqlparam('wikilv_pages'),
                                         backup_helper::is_sqlparam('mod_wikilv'),
                                         backup::VAR_PARENTID));
        }

        // Define id annotations
        $subwikilv->annotate_ids('group', 'groupid');

        $subwikilv->annotate_ids('user', 'userid');

        $page->annotate_ids('user', 'userid');

        $version->annotate_ids('user', 'userid');

        // Define file annotations
        $wikilv->annotate_files('mod_wikilv', 'intro', null); // This file area hasn't itemid
        $subwikilv->annotate_files('mod_wikilv', 'attachments', 'id'); // This file area hasn't itemid

        // Return the root element (wikilv), wrapped into standard activity structure
        return $this->prepare_activity_structure($wikilv);
    }

}
