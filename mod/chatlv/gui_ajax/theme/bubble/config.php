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

$chatlvtheme_cfg = new stdClass();
$chatlvtheme_cfg->avatar = true;
$chatlvtheme_cfg->align  = true;
$chatlvtheme_cfg->event_message = <<<TEMPLATE
<div class="chatlv-event">
<span class="time">___time___</span>
<a target='_blank' href="___senderprofile___">___sender___</a>
<span class="event">___event___</span>
</div>
TEMPLATE;
$chatlvtheme_cfg->user_message_left = <<<TEMPLATE
<div class='chatlv-message ___mymessageclass___'>
    <div class="left">
        <span class="text triangle-border left">___message___</span>
        <span class="picture">___avatar___</span>
    </div>
    <div class="chatlv-message-meta left">
        <span class="time">___time___</span>
        <span class="user">___sender___</span>
    </div>
</div>
TEMPLATE;
$chatlvtheme_cfg->user_message_right = <<<TEMPLATE
<div class='chatlv-message ___mymessageclass___'>
    <div class="right">
        <span class="text triangle-border right">___message___</span>
        <span class="picture">___avatar___</span>
    </div>
    <div class="chatlv-message-meta right">
        <span class="time">___time___</span>
        <span class="user">___sender___</span>
    </div>
</div>
TEMPLATE;
