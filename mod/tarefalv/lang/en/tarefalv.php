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
 * Strings for component 'TarefaLV', language 'en', branch 'MOODLE_20_STABLE'
 *
 * @package   TarefaLV
 * @copyright 1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['activityoverview'] = 'You have TarefaLVs that need attention';
$string['allowdeleting'] = 'Allow deleting';
$string['allowdeleting_help'] = 'If enabled, students may delete uploaded files at any time before submitting for grading.';
$string['allowmaxfiles'] = 'Maximum number of uploaded files';
$string['allowmaxfiles_help'] = 'The maximum number of files which may be uploaded. As this figure is not displayed anywhere, it is suggested that it is mentioned in the TarefaLV description.';
$string['allownotes'] = 'Allow notes';
$string['allownotes_help'] = 'If enabled, students may enter notes into a text area, as in an online text TarefaLV.';
$string['allowresubmit'] = 'Allow resubmitting';
$string['allowresubmit_help'] = 'If enabled, students will be allowed to resubmit TarefaLVs after they have been graded (for them to be re-graded).';
$string['alreadygraded'] = 'Your TarefaLV has already been graded and resubmission is not allowed.';
$string['tarefalv:addinstance'] = 'Add a new TarefaLV';
$string['tarefalvdetails'] = 'TarefaLV details';
$string['tarefalv:exportownsubmission'] = 'Export own submission';
$string['tarefalv:exportsubmission'] = 'Export submission';
$string['tarefalv:grade'] = 'Grade TarefaLV';
$string['tarefalvmail'] = '{$a->teacher} has posted some feedback on your
TarefaLV submission for \'{$a->tarefalv}\'

You can see it appended to your TarefaLV submission:

    {$a->url}';
$string['tarefalvmailhtml'] = '{$a->teacher} has posted some feedback on your
TarefaLV submission for \'<i>{$a->tarefalv}</i>\'<br /><br />
You can see it appended to your <a href="{$a->url}">TarefaLV submission</a>.';
$string['tarefalvmailsmall'] = '{$a->teacher} has posted some feedback on your
TarefaLV submission for \'{$a->tarefalv}\' You can see it appended to your submission';
$string['tarefalvname'] = 'TarefaLV name';
$string['tarefalv:submit'] = 'Submit TarefaLV';
$string['tarefalvsubmission'] = 'TarefaLV submissions';
$string['tarefalvtype'] = 'TarefaLV type';
$string['tarefalv:view'] = 'View TarefaLV';
$string['availabledate'] = 'Available from';
$string['cannotdeletefiles'] = 'An error occurred and files could not be deleted';
$string['cannotviewtarefalv'] = 'You can not view this TarefaLV';
$string['changegradewarning'] = 'This TarefaLV has graded submissions and changing the grade will not automatically re-calculate existing submission grades. You must re-grade all existing submissions, if you wish to change the grade.';
$string['closedtarefalv'] = 'This TarefaLV is closed, as the submission deadline has passed.';
$string['comment'] = 'Comment';
$string['commentinline'] = 'Comment inline';
$string['commentinline_help'] = 'If enabled, the submission text will be copied into the feedback comment field during grading, making it easier to comment inline (using a different colour, perhaps) or to edit the original text.';
$string['configitemstocount'] = 'Nature of items to be counted for student submissions in online TarefaLVs.';
$string['configmaxbytes'] = 'Default maximum TarefaLV size for all TarefaLVs on the site (subject to course limits and other local settings)';
$string['configshowrecentsubmissions'] = 'Everyone can see notifications of submissions in recent activity reports.';
$string['confirmdeletefile'] = 'Are you absolutely sure you want to delete this file?<br /><strong>{$a}</strong>';
$string['coursemisconf'] = 'Course is misconfigured';
$string['currentgrade'] = 'Current grade in gradebook';
$string['deleteallsubmissions'] = 'Delete all submissions';
$string['deletefilefailed'] = 'Deleting of file failed.';
$string['description'] = 'Description';
$string['downloadall'] = 'Download all TarefaLVs as a zip';
$string['draft'] = 'Draft';
$string['due'] = 'TarefaLV due';
$string['duedate'] = 'Due date';
$string['duedateno'] = 'No due date';
$string['early'] = '{$a} early';
$string['editmysubmission'] = 'Edit my submission';
$string['editthesefiles'] = 'Edit these files';
$string['editthisfile'] = 'Update this file';
$string['addsubmission'] = 'Add submission';
$string['emailstudents'] = 'Email alerts to students';
$string['emailteachermail'] = '{$a->username} has updated their TarefaLV submission
for \'{$a->tarefalv}\' at {$a->timeupdated}

It is available here:

    {$a->url}';
$string['emailteachermailhtml'] = '{$a->username} has updated their TarefaLV submission
for <i>\'{$a->tarefalv}\'  at {$a->timeupdated}</i><br /><br />
It is <a href="{$a->url}">available on the web site</a>.';
$string['emailteachers'] = 'Email alerts to teachers';
$string['emailteachers_help'] = 'If enabled, teachers receive email notification whenever students add or update an TarefaLV submission.

Only teachers who are able to grade the particular TarefaLV are notified. So, for example, if the course uses separate groups, teachers restricted to particular groups won\'t receive notification about students in other groups.';
$string['emptysubmission'] = 'You have not submitted anything yet';
$string['enablenotification'] = 'Send notifications';
$string['enablenotification_help'] = 'If enabled, students will be notified when their TarefaLV submissions are graded.';
$string['errornosubmissions'] = 'There are no submissions to download';
$string['existingfiledeleted'] = 'Existing file has been deleted: {$a}';
$string['failedupdatefeedback'] = 'Failed to update submission feedback for user {$a}';
$string['feedback'] = 'Feedback';
$string['feedbackfromteacher'] = 'Feedback from {$a}';
$string['feedbackupdated'] = 'Submissions feedback updated for {$a} people';
$string['finalize'] = 'Prevent submission updates';
$string['finalizeerror'] = 'An error occurred and that submission could not be finalised';
$string['futureatarefalv'] = 'This TarefaLV is not yet available.';
$string['graded'] = 'Graded';
$string['guestnosubmit'] = 'Sorry, guests are not allowed to submit an TarefaLV. You have to log in/ register before you can submit your answer.';
$string['guestnoupload'] = 'Sorry, guests are not allowed to upload';
$string['helpoffline'] = '<p>This is useful when the TarefaLV is performed outside of Moodle.  It could be
   something elsewhere on the web or face-to-face.</p><p>Students can see a description of the TarefaLV,
   but can\'t upload files or anything.  Grading works normally, and students will get notifications of
   their grades.</p>';
$string['helponline'] = '<p>This TarefaLV type asks users to edit a text, using the normal
   editing tools.  Teachers can grade them online, and even add inline comments or changes.</p>
   <p>(If you are familiar with older versions of Moodle, this TarefaLV
   type does the same thing as the old Journal module used to do.)</p>';
$string['helpupload'] = '<p>This type of TarefaLV allows each participant to upload one or more files in any format.
   These might be a Word processor documents, images, a zipped web site, or anything you ask them to submit.</p>
   <p>This type also allows you to upload multiple response files. Response files can be also uploaded before submission which
   can be used to give each participant different file to work with.</p>
   <p>Participants may also enter notes describing the submitted files, progress status or any other text information.</p>
   <p>Submission of this type of TarefaLV must be manually finalised by the participant. You can review the current status
   at any time, unfinished TarefaLVs are marked as Draft. You can revert any ungraded TarefaLV back to draft status.</p>';
$string['helpuploadsingle'] = '<p>This type of TarefaLV allows each participant to upload a
   single file, of any type.</p> <p>This might be a Word processor document, an image,
   a zipped web site, or anything you ask them to submit.</p>';
$string['hideintro'] = 'Hide description before available date';
$string['hideintro_help'] = 'If enabled, the TarefaLV description is hidden before the "Available from" date. Only the TarefaLV name is displayed.';
$string['invalidtarefalv'] = 'Invalid TarefaLV';
$string['invalidfileandsubmissionid'] = 'Missing file or submission ID';
$string['invalidid'] = 'Invalid TarefaLV ID';
$string['invalidsubmissionid'] = 'Invalid submission ID';
$string['invalidtype'] = 'Invalid TarefaLV type';
$string['invaliduserid'] = 'Invalid user ID';
$string['itemstocount'] = 'Count';
$string['lastgrade'] = 'Last grade';
$string['late'] = '{$a} late';
$string['maximumgrade'] = 'Maximum grade';
$string['maximumsize'] = 'Maximum size';
$string['maxpublishstate'] = 'Maximum visibility for blog entry before due date';
$string['messageprovider:tarefalv_updates'] = 'TarefaLV (2.2) notifications';
$string['modulename'] = 'TarefaLV (2.2)';
$string['modulename_help'] = 'TarefaLVs enable the teacher to specify a task either on or offline which can then be graded.';
$string['modulenameplural'] = 'TarefaLVs (2.2)';
$string['newsubmissions'] = 'TarefaLVs submitted';
$string['notarefalvs'] = 'There are no TarefaLVs yet';
$string['noattempts'] = 'No attempts have been made on this TarefaLV';
$string['noblogs'] = 'You have no blog entries to submit!';
$string['nofiles'] = 'No files were submitted';
$string['nofilesyet'] = 'No files submitted yet';
$string['nomoresubmissions'] = 'No further submissions are allowed.';
$string['notavailableyet'] = 'Sorry, this TarefaLV is not yet available.<br />TarefaLV instructions will be displayed here on the date given below.';
$string['notes'] = 'Notes';
$string['notesempty'] = 'No entry';
$string['notesupdateerror'] = 'Error when updating notes';
$string['notgradedyet'] = 'Not graded yet';
$string['norequiregrading'] = 'There are no TarefaLVs that require grading';
$string['nosubmisson'] = 'No TarefaLVs have been submit';
$string['notsubmittedyet'] = 'Not submitted yet';
$string['oncetarefalvsent'] = 'Once the TarefaLV is sent for marking, you will no longer be able to delete or attach file(s). Do you want to continue?';
$string['operation'] = 'Operation';
$string['optionalsettings'] = 'Optional settings';
$string['overwritewarning'] = 'Warning: uploading again will REPLACE your current submission';
$string['page-mod-tarefalv-x'] = 'Any TarefaLV module page';
$string['page-mod-tarefalv-view'] = 'TarefaLV module main page';
$string['page-mod-tarefalv-submissions'] = 'TarefaLV module submission page';
$string['pagesize'] = 'Submissions shown per page';
$string['popupinnewwindow'] = 'Open in a popup window';
$string['pluginadministration'] = 'TarefaLV administration';
$string['pluginname'] = 'TarefaLV (2.2)';
$string['preventlate'] = 'Prevent late submissions';
$string['quickgrade'] = 'Allow quick grading';
$string['quickgrade_help'] = 'If enabled, multiple TarefaLVs can be graded on one page. Add grades and comments then click the "Save all my feedback" button to save all changes for that page.';
$string['requiregrading'] = 'Require grading';
$string['responsefiles'] = 'Response files';
$string['reviewed'] = 'Reviewed';
$string['saveallfeedback'] = 'Save all my feedback';
$string['selectblog'] = 'Select which blog entry you wish to submit';
$string['sendformarking'] = 'Send for marking';
$string['showrecentsubmissions'] = 'Show recent submissions';
$string['submission'] = 'Submission';
$string['submissiondraft'] = 'Submission draft';
$string['submissionfeedback'] = 'Submission feedback';
$string['submissions'] = 'Submissions';
$string['submissionsaved'] = 'Your changes have been saved';
$string['submissionsnotgraded'] = '{$a} submissions not graded';
$string['submittarefalv'] = 'Submit your TarefaLV using this form';
$string['submitedformarking'] = 'TarefaLV was already submitted for marking and can not be updated';
$string['submitformarking'] = 'Final submission for TarefaLV marking';
$string['submitted'] = 'Submitted';
$string['submittedfiles'] = 'Submitted files';
$string['subplugintype_tarefalv'] = 'TarefaLV type';
$string['subplugintype_tarefalv_plural'] = 'TarefaLV types';
$string['trackdrafts'] = 'Enable "Send for marking" button';
$string['trackdrafts_help'] = 'The "Send for marking" button allows students to indicate to the teacher that they have finished working on an TarefaLV. The teacher may choose to revert the TarefaLV to draft status (if it requires further work, for example).';
$string['typeblog'] = 'Blog post';
$string['typeoffline'] = 'Offline activity';
$string['typeonline'] = 'Online text';
$string['typeupload'] = 'Advanced uploading of files';
$string['typeuploadsingle'] = 'Upload a single file';
$string['unfinalize'] = 'Revert to draft';
$string['unfinalize_help'] = 'Reverting to draft enables the student to make further updates to their TarefaLV';
$string['unfinalizeerror'] = 'An error occurred and that submission could not be reverted to draft';
$string['upgradenotification'] = 'This activity is based on an older TarefaLV module.';
$string['uploadafile'] = 'Upload a file';
$string['uploadfiles'] = 'Upload files';
$string['uploadbadname'] = 'This filename contained strange characters and couldn\'t be uploaded';
$string['uploadedfiles'] = 'uploaded files';
$string['uploaderror'] = 'An error happened while saving the file on the server';
$string['uploadfailnoupdate'] = 'File was uploaded OK but could not update your submission!';
$string['uploadfiletoobig'] = 'Sorry, but that file is too big (limit is {$a} bytes)';
$string['uploadnofilefound'] = 'No file was found - are you sure you selected one to upload?';
$string['uploadnotregistered'] = '\'{$a}\' was uploaded OK but submission did not register!';
$string['uploadsuccess'] = 'Uploaded \'{$a}\' successfully';
$string['usermisconf'] = 'User is misconfigured';
$string['usernosubmit'] = 'Sorry, you are not allowed to submit an TarefaLV.';
$string['viewtarefalvupgradetool'] = 'View the TarefaLV upgrade tool';
$string['viewfeedback'] = 'View TarefaLV grades and feedback';
$string['viewmysubmission'] = 'View my submission';
$string['viewsubmissions'] = 'View {$a} submitted TarefaLVs';
$string['yoursubmission'] = 'Your submission';
