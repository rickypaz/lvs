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
 * This file contains all necessary code to define and process an edit form
 *
 * @package mod_wikilv
 * @copyright 2009 Marc Alier, Jordi Piguillem marc.alier@upc.edu
 * @copyright 2009 Universitat Politecnica de Catalunya http://www.upc.edu
 *
 * @author Josep Arus
 *
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}

require_once($CFG->dirroot . '/mod/wikilv/editors/wikilveditor.php');

class mod_wikilv_edit_form extends moodleform {

    protected function definition() {
        global $CFG;

        $mform = $this->_form;
        // BEWARE HACK: In order for things to work we need to override the form id and set it to mform1.
        // The first form to be instantiated never gets displayed so this should be safe.
        $mform->updateAttributes(array('id' => 'mform1'));

        $version = $this->_customdata['version'];
        $format = $this->_customdata['format'];

        if (empty($this->_customdata['contextid'])) {
            // Hack alert
            // This is being done ONLY to aid those who may have created there own wikilv pages. It should be removed sometime
            // after the release of 2.3 (not creating an issue because this whole thing should be reviewed)
            debugging('You must always provide mod_wikilv_edit_form with a contextid in its custom data', DEBUG_DEVELOPER);
            global $PAGE;
            $contextid = $PAGE->context->id;
        } else {
            $contextid = $this->_customdata['contextid'];
        }

        if (isset($this->_customdata['pagetitle'])) {
            // Page title must be formatted properly here as this is output and not an element.
            $pagetitle = get_string('editingpage', 'wikilv', format_string($this->_customdata['pagetitle'], true, array('context' => context::instance_by_id($contextid, MUST_EXIST))));
        } else {
            $pagetitle = get_string('editing', 'wikilv');
        }

        //editor
        $mform->addElement('header', 'general', $pagetitle);

        $fieldname = get_string('format' . $format, 'wikilv');
        if ($format != 'html') {
            // Use wikilv editor
            $extensions = file_get_typegroup('extension', 'web_image');
            $fs = get_file_storage();
            $tree = $fs->get_area_tree($contextid, 'mod_wikilv', $this->_customdata['filearea'], $this->_customdata['fileitemid']);
            $files = array();
            foreach ($tree['files'] as $file) {
                $filename = $file->get_filename();
                foreach ($extensions as $ext) {
                    if (preg_match('#'.$ext.'$#i', $filename)) {
                        $files[] = $filename;
                    }
                }
            }
            $mform->addElement('wikilveditor', 'newcontent', $fieldname, array('cols' => 100, 'rows' => 20, 'wikilv_format' => $format, 'files'=>$files));
            $mform->addHelpButton('newcontent', 'format'.$format, 'wikilv');
            $mform->setType('newcontent', PARAM_RAW); // processed by trust text or cleaned before the display
        } else {
            $mform->addElement('editor', 'newcontent_editor', $fieldname, null, page_wikilv_edit::$attachmentoptions);
            $mform->addHelpButton('newcontent_editor', 'formathtml', 'wikilv');
            $mform->setType('newcontent_editor', PARAM_RAW); // processed by trust text or cleaned before the display
        }

        //hiddens
        if ($version >= 0) {
            $mform->addElement('hidden', 'version', $version);
            $mform->setType('version', PARAM_FLOAT);
        }

        $mform->addElement('hidden', 'contentformat', $format);
        $mform->setType('contentformat', PARAM_ALPHANUMEXT);

        if (core_tag_tag::is_enabled('mod_wikilv', 'wikilv_pages')) {
            $mform->addElement('header', 'tagshdr', get_string('tags', 'tag'));
        }
        $mform->addElement('tags', 'tags', get_string('tags'),
                array('itemtype' => 'wikilv_pages', 'component' => 'mod_wikilv'));

        $buttongroup = array();
        $buttongroup[] = $mform->createElement('submit', 'editoption', get_string('save', 'wikilv'), array('id' => 'save'));
        $buttongroup[] = $mform->createElement('submit', 'editoption', get_string('preview'), array('id' => 'preview'));
        $buttongroup[] = $mform->createElement('submit', 'editoption', get_string('cancel'), array('id' => 'cancel'));

        $mform->addGroup($buttongroup, 'buttonar', '', array(' '), false);
        $mform->closeHeaderBefore('buttonar');
    }

}
