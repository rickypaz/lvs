<?php
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
        <span class="picture">___avatar___</span>
        <span class="text triangle-border left">___message___</span>
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
