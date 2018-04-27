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
 * Definition of log events
 *
 * @package   mod_tarefalv
 * @copyright 2012 NetSpot {@link http://www.netspot.com.au}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$logs = array(
    array('module'=>'tarefalv', 'action'=>'add', 'mtable'=>'tarefalv', 'field'=>'name'),
    array('module'=>'tarefalv', 'action'=>'delete mod', 'mtable'=>'tarefalv', 'field'=>'name'),
    array('module'=>'tarefalv', 'action'=>'download all submissions', 'mtable'=>'tarefalv', 'field'=>'name'),
    array('module'=>'tarefalv', 'action'=>'grade submission', 'mtable'=>'tarefalv', 'field'=>'name'),
    array('module'=>'tarefalv', 'action'=>'lock submission', 'mtable'=>'tarefalv', 'field'=>'name'),
    array('module'=>'tarefalv', 'action'=>'reveal identities', 'mtable'=>'tarefalv', 'field'=>'name'),
    array('module'=>'tarefalv', 'action'=>'revert submission to draft', 'mtable'=>'tarefalv', 'field'=>'name'),
    array('module'=>'tarefalv', 'action'=>'set marking workflow state', 'mtable'=>'tarefalv', 'field'=>'name'),
    array('module'=>'tarefalv', 'action'=>'submission statement accepted', 'mtable'=>'tarefalv', 'field'=>'name'),
    array('module'=>'tarefalv', 'action'=>'submit', 'mtable'=>'tarefalv', 'field'=>'name'),
    array('module'=>'tarefalv', 'action'=>'submit for grading', 'mtable'=>'tarefalv', 'field'=>'name'),
    array('module'=>'tarefalv', 'action'=>'unlock submission', 'mtable'=>'tarefalv', 'field'=>'name'),
    array('module'=>'tarefalv', 'action'=>'update', 'mtable'=>'tarefalv', 'field'=>'name'),
    array('module'=>'tarefalv', 'action'=>'upload', 'mtable'=>'tarefalv', 'field'=>'name'),
    array('module'=>'tarefalv', 'action'=>'view', 'mtable'=>'tarefalv', 'field'=>'name'),
    array('module'=>'tarefalv', 'action'=>'view all', 'mtable'=>'course', 'field'=>'fullname'),
    array('module'=>'tarefalv', 'action'=>'view confirm submit assignment form', 'mtable'=>'tarefalv', 'field'=>'name'),
    array('module'=>'tarefalv', 'action'=>'view grading form', 'mtable'=>'tarefalv', 'field'=>'name'),
    array('module'=>'tarefalv', 'action'=>'view submission', 'mtable'=>'tarefalv', 'field'=>'name'),
    array('module'=>'tarefalv', 'action'=>'view submission grading table', 'mtable'=>'tarefalv', 'field'=>'name'),
    array('module'=>'tarefalv', 'action'=>'view submit assignment form', 'mtable'=>'tarefalv', 'field'=>'name'),
    array('module'=>'tarefalv', 'action'=>'view feedback', 'mtable'=>'tarefalv', 'field'=>'name'),
    array('module'=>'tarefalv', 'action'=>'view batch set marking workflow state', 'mtable'=>'tarefalv', 'field'=>'name'),
);
