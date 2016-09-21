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
 * This file contains all necessary code to define a wikilv editor
 *
 * @package mod_wikilv
 * @copyright 2009 Marc Alier, Jordi Piguillem marc.alier@upc.edu
 * @copyright 2009 Universitat Politecnica de Catalunya http://www.upc.edu
 *
 * @author Josep Arus
 *
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once($CFG->dirroot.'/lib/formslib.php');
require_once($CFG->dirroot.'/lib/form/textarea.php');

class MoodleQuickForm_wikilveditor extends MoodleQuickForm_textarea {

    private $files;

    /**
     * Constructor
     *
     * @param string $elementName (optional) name of the text field
     * @param string $elementLabel (optional) text field label
     * @param string $attributes (optional) Either a typical HTML attribute string or an associative array
     */
    function __construct($elementName = null, $elementLabel = null, $attributes = null) {
        if (isset($attributes['wikilv_format'])) {
            $this->wikilvformat = $attributes['wikilv_format'];
            unset($attributes['wikilv_format']);
        }
        if (isset($attributes['files'])) {
            $this->files = $attributes['files'];
            unset($attributes['files']);
        }

        parent::__construct($elementName, $elementLabel, $attributes);
    }

    /**
     * Old syntax of class constructor. Deprecated in PHP7.
     *
     * @deprecated since Moodle 3.1
     */
    public function MoodleQuickForm_wikilveditor($elementName = null, $elementLabel = null, $attributes = null) {
        debugging('Use of class name as constructor is deprecated', DEBUG_DEVELOPER);
        self::__construct($elementName, $elementLabel, $attributes);
    }

    function setWikilvFormat($wikilvformat) {
        $this->wikilvformat = $wikilvformat;
    }

    function toHtml() {
        $textarea = parent::toHtml();

        return $this->{
            $this->wikilvformat."Editor"}
            ($textarea);
    }

    function creoleEditor($textarea) {
        return $this->printWikilvEditor($textarea);
    }

    function nwikilvEditor($textarea) {
        return $this->printWikilvEditor($textarea);
    }

    private function printWikilvEditor($textarea) {
        global $OUTPUT;

        $textarea = $OUTPUT->container_start().$textarea.$OUTPUT->container_end();

        $buttons = $this->getButtons();

        return $buttons.$textarea;
    }

    private function getButtons() {
        global $PAGE, $OUTPUT, $CFG;

        $editor = $this->wikilvformat;

        $tag = $this->getTokens($editor, 'bold');
        $wikilv_editor['bold'] = array('ed_bold.gif', get_string('wikilvboldtext', 'wikilv'), $tag[0], $tag[1], get_string('wikilvboldtext', 'wikilv'));

        $tag = $this->getTokens($editor, 'italic');
        $wikilv_editor['italic'] = array('ed_italic.gif', get_string('wikilvitalictext', 'wikilv'), $tag[0], $tag[1], get_string('wikilvitalictext', 'wikilv'));

        $imagetag = $this->getTokens($editor, 'image');
        $wikilv_editor['image'] = array('ed_img.gif', get_string('wikilvimage', 'wikilv'), $imagetag[0], $imagetag[1], get_string('wikilvimage', 'wikilv'));

        $tag = $this->getTokens($editor, 'link');
        $wikilv_editor['internal'] = array('ed_internal.gif', get_string('wikilvinternalurl', 'wikilv'), $tag[0], $tag[1], get_string('wikilvinternalurl', 'wikilv'));

        $tag = $this->getTokens($editor, 'url');
        $wikilv_editor['external'] = array('ed_external.gif', get_string('wikilvexternalurl', 'wikilv'), $tag, "", get_string('wikilvexternalurl', 'wikilv'));

        $tag = $this->getTokens($editor, 'list');
        $wikilv_editor['u_list'] = array('ed_ul.gif', get_string('wikilvunorderedlist', 'wikilv'), '\\n'.$tag[0], '', '');
        $wikilv_editor['o_list'] = array('ed_ol.gif', get_string('wikilvorderedlist', 'wikilv'), '\\n'.$tag[1], '', '');

        $tag = $this->getTokens($editor, 'header');
        $wikilv_editor['h1'] = array('ed_h1.gif', get_string('wikilvheader', 'wikilv', 1), '\\n'.$tag.' ', ' '.$tag.'\\n', get_string('wikilvheader', 'wikilv', 1));
        $wikilv_editor['h2'] = array('ed_h2.gif', get_string('wikilvheader', 'wikilv', 2), '\\n'.$tag.$tag.' ', ' '.$tag.$tag.'\\n', get_string('wikilvheader', 'wikilv', 2));
        $wikilv_editor['h3'] = array('ed_h3.gif', get_string('wikilvheader', 'wikilv', 3), '\\n'.$tag.$tag.$tag.' ', ' '.$tag.$tag.$tag.'\\n', get_string('wikilvheader', 'wikilv', 3));

        $tag = $this->getTokens($editor, 'line_break');
        $wikilv_editor['hr'] = array('ed_hr.gif', get_string('wikilvhr', 'wikilv'), '\\n'.$tag.'\\n', '', '');

        $tag = $this->getTokens($editor, 'nowikilv');
        $wikilv_editor['nowikilv'] = array('ed_nowikilv.gif', get_string('wikilvnowikilvtext', 'wikilv'), $tag[0], $tag[1], get_string('wikilvnowikilvtext', 'wikilv'));

        $PAGE->requires->js('/mod/wikilv/editors/wikilv/buttons.js');

        $html = '<div class="wikilveditor-toolbar">';
        foreach ($wikilv_editor as $button) {
            $html .= "<a href=\"javascript:insertTags";
            $html .= "('".$button[2]."','".$button[3]."','".$button[4]."');\">";
            $html .= html_writer::empty_tag('img', array('alt' => $button[1], 'src' => $CFG->wwwroot . '/mod/wikilv/editors/wikilv/images/' . $button[0]));
            $html .= "</a>";
        }
        $html .= "<label class='accesshide' for='addtags'>" . get_string('insertimage', 'wikilv')  . "</label>";
        $html .= "<select id='addtags' onchange=\"insertTags('{$imagetag[0]}', '{$imagetag[1]}', this.value)\">";
        $html .= "<option value='" . s(get_string('wikilvimage', 'wikilv')) . "'>" . get_string('insertimage', 'wikilv') . '</option>';
        foreach ($this->files as $filename) {
            $html .= "<option value='".s($filename)."'>";
            $html .= $filename;
            $html .= '</option>';
        }
        $html .= '</select>';
        $html .= $OUTPUT->help_icon('insertimage', 'wikilv');
        $html .= '</div>';

        return $html;
    }

    private function getTokens($format, $token) {
        $tokens = wikilv_parser_get_token($format, $token);

        if (is_array($tokens)) {
            foreach ($tokens as & $t) {
                $this->escapeToken($t);
            }
        } else {
            $this->escapeToken($tokens);
        }

        return $tokens;
    }

    private function escapeToken(&$token) {
        $token = urlencode(str_replace("'", "\'", $token));
    }
}

//register wikilveditor
MoodleQuickForm::registerElementType('wikilveditor', $CFG->dirroot."/mod/wikilv/editors/wikilveditor.php", 'MoodleQuickForm_wikilveditor');
