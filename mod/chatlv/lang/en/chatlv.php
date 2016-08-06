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
 * Strings for component 'ChatLV', language 'en', branch 'MOODLE_20_STABLE'
 *
 * @package   ChatLV
 * @copyright 1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['activityoverview'] = 'You have upcoming ChatLV sessions';
$string['ajax'] = 'Version using Ajax';
$string['autoscroll'] = 'Auto scroll';
$string['beep'] = 'Beep';
$string['cantlogin'] = 'Could not log in to ChatLV room!!';
$string['composemessage'] = 'Compose a message';
$string['configmethod'] = 'The ajax ChatLV method provide an ajax based ChatLV interface, it contacts server regularly for update. The normal ChatLV method involves the clients regularly contacting the server for updates. It requires no configuration and works everywhere, but it can create a large load on the server with many ChatLVters.  Using a server daemon requires shell access to Unix, but it results in a fast scalable ChatLV environment.';
$string['confignormalupdatemode'] = 'ChatLVroom updates are normally served efficiently using the <em>Keep-Alive</em> feature of HTTP 1.1, but this is still quite heavy on the server. A more advanced method is to use the <em>Stream</em> strategy to feed updates to the users. Using <em>Stream</em> scales much better (similar to the ChatLVd method) but may not be supported by your server.';
$string['configoldping'] = 'What is the maximum time that may pass before we detect that a user has disconnected (in seconds)? This is just an upper limit, as usually disconnects are detected very quickly. Lower values will be more demanding on your server. If you are using the normal method, <strong>never</strong> set this lower than 2 * ChatLV_refresh_room.';
$string['configrefreshroom'] = 'How often should the ChatLV room itself be refreshed? (in seconds).  Setting this low will make the ChatLV room seem quicker, but it may place a higher load on your web server when many people are ChatLVting. If you are using <em>Stream</em> updates, you can select higher refresh frequencies -- try with 2.';
$string['configrefreshuserlist'] = 'How often should the list of users be refreshed? (in seconds)';
$string['configserverhost'] = 'The hostname of the computer where the server daemon is';
$string['configserverip'] = 'The numerical IP address that matches the above hostname';
$string['configservermax'] = 'Max number of clients allowed';
$string['configserverport'] = 'Port to use on the server for the daemon';
$string['currentchatlvs'] = 'Active ChatLV sessions';
$string['currentusers'] = 'Current users';
$string['deletesession'] = 'Delete this session';
$string['deletesessionsure'] = 'Are you sure you want to delete this session?';
$string['donotusechatlvtime'] = 'Don\'t publish any ChatLV times';
$string['enterchatlv'] = 'Click here to enter the ChatLV now';
$string['errornousers'] = 'Could not find any users!';
$string['explaingeneralconfig'] = 'These settings are <strong>always</strong> used';
$string['explainmethoddaemon'] = 'These settings matter <strong>only</strong> if you have selected "ChatLV server daemon" for ChatLV_method';
$string['explainmethodnormal'] = 'These settings matter <strong>only</strong> if you have selected "Normal method" for ChatLV_method';
$string['generalconfig'] = 'General configuration';
$string['chatlv:addinstance'] = 'Add a new ChatLV';
$string['chatlv:deletelog'] = 'Delete ChatLV logs';
$string['chatlv:exportparticipatedsession'] = 'Export ChatLV session which you took part in';
$string['chatlv:exportsession'] = 'Export any ChatLV session';
$string['chatlv:chatlv'] = 'Access a ChatLV room';
$string['chatlvintro'] = 'Description';
$string['chatlvname'] = 'Name of this ChatLV room';
$string['chatlv:readlog'] = 'Read ChatLV logs';
$string['chatlvreport'] = 'ChatLV sessions';
$string['chatlv:talk'] = 'Talk in a ChatLV';
$string['chatlvtime'] = 'Next ChatLV time';
$string['entermessage'] = "Enter your message";
$string['idle'] = 'Idle';
$string['inputarea'] = 'Input area';
$string['invalidid'] = 'Could not find that ChatLV room!';
$string['list_all_sessions'] = 'List all sessions.';
$string['list_complete_sessions'] = 'List just complete sessions.';
$string['listing_all_sessions'] = 'Listing all sessions.';
$string['messagebeepseveryone'] = '{$a} beeps everyone!';
$string['messagebeepsyou'] = '{$a} has just beeped you!';
$string['messageenter'] = '{$a} has just entered this ChatLV';
$string['messageexit'] = '{$a} has left this ChatLV';
$string['messages'] = 'Messages';
$string['messageyoubeep'] = 'You beeped {$a}';
$string['method'] = 'ChatLV method';
$string['methoddaemon'] = 'ChatLV server daemon';
$string['methodnormal'] = 'Normal method';
$string['methodajax'] = 'Ajax method';
$string['modulename'] = 'ChatLV';
$string['modulename_help'] = 'The ChatLV activity module enables participants to have text-based, real-time synchronous discussions.

The ChatLV may be a one-time activity or it may be repeated at the same time each day or each week. ChatLV sessions are saved and can be made available for everyone to view or restricted to users with the capability to view ChatLV session logs.

ChatLVs are especially useful when the group ChatLVting is not able to meet face-to-face, such as

* Regular meetings of students participating in online courses to enable them to share experiences with others in the same course but in a different location
* A student temporarily unable to attend in person ChatLVting with their teacher to catch up with work
* Students out on work experience getting together to discuss their experiences with each other and their teacher
* Younger children using ChatLV at home in the evenings as a controlled (monitored) introduction to the world of social networking
* A question and answer session with an invited speaker in a different location
* Sessions to help students prepare for tests where the teacher, or other students, would pose sample questions';
$string['modulename_link'] = 'mod/chatlv/view';
$string['modulenameplural'] = 'ChatLVs';
$string['neverdeletemessages'] = 'Never delete messages';
$string['nextsession'] = 'Next scheduled session';
$string['no_complete_sessions_found'] = 'No complete sessions found.';
$string['noguests'] = 'The ChatLV is not open to guests';
$string['nochatlv'] = 'No ChatLV found';
$string['nomessages'] = 'No messages yet';
$string['normalkeepalive'] = 'KeepAlive';
$string['normalstream'] = 'Stream';
$string['noscheduledsession'] = 'No scheduled session';
$string['notallowenter'] = 'You are not allowed to enter the ChatLV room.';
$string['notlogged'] = 'You are not logged in!';
$string['nopermissiontoseethechatlvlog'] = 'You don\'t have permission to see the ChatLV logs.';
$string['oldping'] = 'Disconnect timeout';
$string['page-mod-chatlv-x'] = 'Any ChatLV module page';
$string['pastchatlvs'] = 'Past ChatLV sessions';
$string['pluginadministration'] = 'ChatLV administration';
$string['pluginname'] = 'ChatLV';
$string['refreshroom'] = 'Refresh room';
$string['refreshuserlist'] = 'Refresh user list';
$string['removemessages'] = 'Remove all messages';
$string['repeatdaily'] = 'At the same time every day';
$string['repeatnone'] = 'No repeats - publish the specified time only';
$string['repeattimes'] = 'Repeat/publish session times';
$string['repeatweekly'] = 'At the same time every week';
$string['saidto'] = 'said to';
$string['savemessages'] = 'Save past sessions';
$string['seesession'] = 'See this session';
$string['send'] = 'Send';
$string['sending'] = 'Sending';
$string['serverhost'] = 'Server name';
$string['serverip'] = 'Server ip';
$string['servermax'] = 'Max users';
$string['serverport'] = 'Server port';
$string['sessions'] = 'ChatLV sessions';
$string['sessionstart'] = 'The next ChatLV session will start in {$a}';
$string['strftimemessage'] = '%H:%M';
$string['studentseereports'] = 'Everyone can view past sessions';
$string['studentseereports_help'] = 'If set to No, only users have mod/chatlv:readlog capability are able to see the ChatLV logs';
$string['talk'] = 'Talk';
$string['updatemethod'] = 'Update method';
$string['updaterate'] = 'Update rate:';
$string['userlist'] = 'User list';
$string['usingchatlv'] = 'Using ChatLV';
$string['usingchatlv_help'] = 'The ChatLV module contains some features to make ChatLVting a little nicer.

* Smilies - Any smiley faces (emoticons) that you can type elsewhere in Moodle can also be typed here, for example :-)
* Links - Website addresses will be turned into links automatically
* Emoting - You can start a line with "/me" or ":" to emote, for example if your name is Kim and you type ":laughs!" or "/me laughs!" then everyone will see "Kim laughs!"
* Beeps - You can send a sound to other participants by clicking the "beep" link next to their name. A useful shortcut to beep all the people in the ChatLV at once is to type "beep all".
* HTML - If you know some HTML code, you can use it in your text to do things like insert images, play sounds or create different coloured text';
$string['viewreport'] = 'View past ChatLV sessions';
