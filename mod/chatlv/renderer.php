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
 * Chatlv module rendering methods
 *
 * @package    mod_chatlv
 * @copyright  2012 Andrew Davis
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/* @lvs dependências dos lvs  */
use uab\ifce\lvs\business\Item;
use uab\ifce\lvs\avaliacao\NotasLvFactory;
use uab\ifce\lvs\moodle2\business\Moodle2CursoLv;
use uab\ifce\lvs\moodle2\business\ChatsLv;
use uab\ifce\lvs\moodle2\business\Chatlv;


require_once($CFG->dirroot.'/blocks/lvs/biblioteca/lib.php'); // @lvs inclusão do loader dos lvs
/* --- fim ---- */

/**
 * Chatlv module renderer class
 *
 * @copyright 2012 Andrew Davis
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_chatlv_renderer extends plugin_renderer_base {

    /**
     * Render and event_message instance
     *
     * @param event_message $eventmessage The event_message instance to render
     * @return string HTML representing the event_message instance
     */
    protected function render_event_message(event_message $eventmessage) {
        global $CFG;

        if (file_exists($CFG->dirroot . '/mod/chatlv/gui_ajax/theme/'.$eventmessage->theme.'/config.php')) {
            include($CFG->dirroot . '/mod/chatlv/gui_ajax/theme/'.$eventmessage->theme.'/config.php');
        }

        $patterns = array();
        $patterns[] = '___senderprofile___';
        $patterns[] = '___sender___';
        $patterns[] = '___time___';
        $patterns[] = '___event___';

        $replacements = array();
        $replacements[] = $eventmessage->senderprofile;
        $replacements[] = $eventmessage->sendername;
        $replacements[] = $eventmessage->time;
        $replacements[] = $eventmessage->event;

        return str_replace($patterns, $replacements, $chatlvtheme_cfg->event_message);
    }

    /**
     * Render a user message
     *
     * @param user_message $usermessage the user message to display
     * @return string html representation of a user_message instance
     */
    protected function render_user_message(user_message $usermessage) {
        global $CFG;

        if (file_exists($CFG->dirroot . '/mod/chatlv/gui_ajax/theme/'.$usermessage->theme.'/config.php')) {
            include($CFG->dirroot . '/mod/chatlv/gui_ajax/theme/'.$usermessage->theme.'/config.php');
        }

        $patterns = array();
        $patterns[] = '___avatar___';
        $patterns[] = '___sender___';
        $patterns[] = '___senderprofile___';
        $patterns[] = '___time___';
        $patterns[] = '___message___';
        $patterns[] = '___mymessageclass___';

        $replacements = array();
        $replacements[] = $usermessage->avatar;
        $replacements[] = $usermessage->sendername;
        $replacements[] = $usermessage->senderprofile;
        $replacements[] = $usermessage->time;
        
        /** @lvs form de avaliação chatlv */
        global $cm;
        $lvs_output = '';
        
        if (has_capability("mod/$cm->modname:rate", get_context_instance(CONTEXT_MODULE, $cm->id))) {
        	$itemlv = new Item('chatlv', 'message', $usermessage->messagelv);
        	$gerenciadorNotas = NotasLvFactory::criarGerenciador('moodle2');
        	$gerenciadorNotas->setModulo( new Chatlv($usermessage->messagelv->chatlvid) );
        	unset($itemlv->getItem()->picture);
        	unset($itemlv->getItem()->message); 

        	$lvs_output = $gerenciadorNotas->avaliacaoAtual($itemlv) . $gerenciadorNotas->avaliadoPor($itemlv) . $gerenciadorNotas->formAvaliacaoAjax($itemlv);
        }
        
//         $replacements[] = $lvs_output;
        // fim lvs

        $replacements[] = $lvs_output . '<br/>' . $usermessage->message;
        $replacements[] = $usermessage->mymessageclass;
//         print_r($replacements);
        $output = null;

        if (!empty($chatlvtheme_cfg->avatar) and !empty($chatlvtheme_cfg->align)) {
            if (!empty($usermessage->mymessageclass)) {
                $output = str_replace($patterns, $replacements, $chatlvtheme_cfg->user_message_right);
            } else {
                $output = str_replace($patterns, $replacements, $chatlvtheme_cfg->user_message_left);
            }
        } else {
            $output = str_replace($patterns, $replacements, $chatlvtheme_cfg->user_message);
        }
        
//         print_r($output);

        return $output;
    }
}
