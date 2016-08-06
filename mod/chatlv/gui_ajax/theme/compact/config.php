<?php
$chatlvtheme_cfg = new stdClass();
$chatlvtheme_cfg->avatar = false;
$chatlvtheme_cfg->align  = false;
$chatlvtheme_cfg->event_message = <<<TEMPLATE
<div class="chatlv-event">
<span class="time">___time___</span>
<a target='_blank' href="___senderprofile___">___sender___</a>
<span class="event">___event___</span>
</div>
TEMPLATE;
$chatlvtheme_cfg->user_message = <<<TEMPLATE
<div class='chatlv-message'>
    <div class="chatlv-message-meta">
        <span class="time">___time___</span>
        <span class="user"><a href="___senderprofile___" target="_blank">___sender___</a></span>
    </div>
    <div class="text">
    ___message___
    </div>
	<div>
	___grade___
	</div>
</div>
TEMPLATE;
// @lvs o div da grade