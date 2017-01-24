<?php

/**
 * Parser utils and default callbacks.
 *
 * @author Josep Arús
 *
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package mod_wikilv
 */

require_once($CFG->dirroot . "/lib/outputcomponents.php");
    
class parser_utils_lvs { // @LVS adição do sufixo lvs
        
    public static function h($tag, $text = null, $options = array(), $escape_text = false) {
        $tag = htmlentities($tag, ENT_COMPAT, 'UTF-8');
        if(!empty($text) && $escape_text) {
                $text = htmlentities($text, ENT_COMPAT, 'UTF-8');
            }
        return html_writer::tag($tag, $text, $options);
    }
    
    /**
     * Default link generator
     */

    public static function wikilv_parser_link_callback($link, $options) {
        $l = urlencode($link);
        if(!empty($options['anchor'])) {
            $l .= "#".urlencode($options['anchor']);
        }
        return array('content' => $link, 'url' => "http://".$l);
    }


    /**
     * Default table generator
     */

    public static function wikilv_parser_table_callback($table) {
        $html = "";
        $headers = $table[0];
        $columncount = count($headers);
        $headerhtml = "";
        foreach($headers as $h) {
            $text = trim($h[1]);
            if($h[0] == 'header') {
                $headerhtml .= "\n".parser_utils_lvs::h('th', $text)."\n"; // @LVS adição do sufixo lvs
                $hasheaders = true;
            }
            else if($h[0] == 'normal'){
                $headerhtml .= "\n".parser_utils_lvs::h("td", $text)."\n"; // @LVS adição do sufixo lvs
            }
        }
        $headerhtml = "\n".parser_utils_lvs::h('tr', $headerhtml)."\n"; // @LVS adição do sufixo lvs
        $bodyhtml = "";
        if(isset($hasheaders)) {
            $html = "\n".parser_utils_lvs::h('thead', $headerhtml)."\n"; // @LVS adição do sufixo lvs
        }
        else {
            $bodyhtml .= $headerhtml;
        }

        array_shift($table);
        foreach($table as $row) {
            $htmlrow = "";
            for($i = 0; $i < $columncount; $i++) {
                $text = "";
                if(!isset($row[$i])) {
                    $htmlrow .= "\n".parser_utils_lvs::h('td', $text)."\n"; // @LVS adição do sufixo lvs
                }
                else {
                    $text = trim($row[$i][1]);
                    if($row[$i][0] == 'header') {
                        $htmlrow .= "\n".parser_utils_lvs::h('th', $text)."\n"; // @LVS adição do sufixo lvs
                    }
                    else if($row[$i][0] == 'normal'){
                        $htmlrow .= "\n".parser_utils_lvs::h('td', $text)."\n"; // @LVS adição do sufixo lvs
                    }   
                }
            }
            $bodyhtml .= "\n".parser_utils_lvs::h('tr', $htmlrow)."\n"; // @LVS adição do sufixo lvs
        }

        $html .= "\n".parser_utils_lvs::h('tbody', $bodyhtml)."\n"; // @LVS adição do sufixo lvs
        return "\n".parser_utils_lvs::h('table', $html)."\n"; // @LVS adição do sufixo lvs
    }
    
    /**
     * Default path converter
     */
    
    public static function wikilv_parser_real_path($url) {
        return $url;
    }
}

