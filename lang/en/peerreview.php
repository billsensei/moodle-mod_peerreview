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
 * English strings for mod_peerreview.
 *
 * @package    mod_peerreview
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['addallocations'] = 'Add allocations';
$string['aggregation'] = 'Aggregation of received reviews';
$string['aggregation_help'] = 'How the reviews a student receives are combined into one grade.';
$string['aggregationmean'] = 'Mean';
$string['aggregationmedian'] = 'Median';
$string['allgroups'] = 'All groups';
$string['allocate'] = 'Allocate reviewers';
$string['allocationcount'] = '{$a} allocations';
$string['allocationsdeleted'] = '{$a} allocations deleted.';
$string['allocationssaved'] = '{$a->created} allocations created, {$a->deleted} removed.';
$string['allowcrossgroup'] = 'Allow reviews across groups';
$string['allowselfreview'] = 'Allow self-review';
$string['allowselfreview_help'] = 'If enabled, a teacher can allocate a student to review themselves. Self-reviews are not counted in the received grade.';
$string['anonymous'] = 'Reviewer identity shown to reviewees';
$string['anonymous_anonymous'] = 'Anonymous';
$string['anonymous_byname'] = 'By name';
$string['anonymous_help'] = 'Teachers always see the real names.';
$string['changesettings'] = 'Change settings';
$string['closeson'] = 'Closes: {$a}';
$string['completionallreviews'] = 'Student must submit all assigned reviews';
$string['completionallreviews_desc'] = 'Submit all assigned reviews';
$string['confirmallocation'] = 'Confirm and save';
$string['confirmdelete'] = 'Delete {$a} selected allocations?';
$string['confirmdeletestarted'] = 'Delete {$a->count} selected allocations? {$a->started} of them already have a saved or submitted review. Those reviews will be permanently deleted.';
$string['csvdelimiter'] = 'Delimiter';
$string['csvduplicates'] = '{$a} rows were already allocated and will be skipped.';
$string['csverrorunknown'] = 'No enrolled student matches "{$a}".';
$string['csvexport'] = 'Export allocations as CSV';
$string['csvfile'] = 'CSV file';
$string['csvfile_help'] = 'A CSV file with a header row "reviewer,reviewee". Each row gives a username or email address for the reviewer and for the reviewee.';
$string['csvlineerror'] = 'Line {$a->line}: {$a->message}';
$string['csvnoheader'] = 'The CSV file must have a header row starting with "reviewer,reviewee".';
$string['deletepair'] = 'Delete {$a->reviewer} reviewing {$a->reviewee}';
$string['deleteselected'] = 'Delete selected';
$string['draftsaved'] = 'Draft saved. You can come back and finish it later.';
$string['errorclosed'] = 'This activity is closed, so reviews can no longer be changed.';
$string['errordraftaftersubmit'] = 'This review was already submitted; submit it again to change it.';
$string['errornodrafts'] = 'Drafts are not available for this grading method.';
$string['errornogroups'] = 'There are no groups with students to allocate.';
$string['errornotopenyet'] = 'This activity is not open yet.';
$string['errornotstudent'] = 'Not an enrolled student.';
$string['errornotyourreview'] = 'You are not the reviewer for this review.';
$string['errorpositive'] = 'Enter a number of 1 or more.';
$string['errorreviewincomplete'] = 'The review is empty or incomplete. Fill in every criterion before submitting.';
$string['errorscorerange'] = 'Enter a number between 0 and {$a}.';
$string['errorselfnotallowed'] = 'Self-review is not allowed in this activity ({$a}).';
$string['errorshiftzero'] = 'The shift cannot be 0.';
$string['errorstartedchanged'] = 'More of the selected reviews were started while you were on this page. Nothing was deleted; please check again.';
$string['errorstartedreviews'] = 'These allocations include saved or submitted reviews and can only be deleted after confirmation.';
$string['eventallocation_created'] = 'Allocation created';
$string['eventallocation_deleted'] = 'Allocation deleted';
$string['eventcoursemoduleinstancelistviewed'] = 'Course module instance list viewed';
$string['eventcoursemoduleviewed'] = 'Course module viewed';
$string['eventreview_submitted'] = 'Review submitted';
$string['eventreview_updated'] = 'Review updated';
$string['feedbackreleased'] = 'Reviewees can see received feedback';
$string['gradeitem:participation'] = 'Participation';
$string['gradeitem:received'] = 'Received grade (review criteria)';
$string['gradeparticipation'] = 'Participation grade';
$string['gradeparticipation_help'] = 'Optional second grade: the percentage of assigned reviews the student completed. Set to "None" to disable it.';
$string['manualskipped'] = '{$a} pairs were skipped (not enrolled students, or a self-review that is not allowed).';
$string['methodhelp_csv'] = 'Upload a CSV file with the columns reviewer and reviewee (usernames or email addresses). You will see a preview before anything is saved.';
$string['methodhelp_group'] = 'Every member of a group reviews every other member of the same group. Good for assessing contribution in group work.';
$string['methodhelp_manual'] = 'Choose a reviewer and the students they will review. Existing pairs are left alone.';
$string['methodhelp_random'] = 'Each student reviews N classmates, chosen at random. The reviews are shared out evenly, nobody reviews themselves, and reviews stay inside groups unless you allow otherwise.';
$string['methodhelp_rotation'] = 'Students are put in a list and each reviews the student K places further down (the last wraps round to the first). Useful when students present in a known order.';
$string['modulename'] = 'Peer review';
$string['modulename_help'] = 'Students assess each other in class using a rubric or marking guide. The teacher decides who reviews whom, and a grade based on the reviews received is sent to the gradebook.';
$string['modulenameplural'] = 'Peer reviews';
$string['noallocations'] = 'Nobody has been allocated yet.';
$string['nopeerreviews'] = 'There are no peer review activities in this course.';
$string['noreviews'] = 'There are no reviews to show yet.';
$string['nostudents'] = 'No enrolled students can review this activity yet.';
$string['nothingselected'] = 'Nothing was selected.';
$string['opensat'] = 'Opens: {$a}';
$string['overallcomment'] = 'Overall comment';
$string['peerreview:addinstance'] = 'Add a new peer review';
$string['peerreview:allocate'] = 'Allocate reviewers';
$string['peerreview:export'] = 'Export reviews';
$string['peerreview:overridegrade'] = 'Override received grades';
$string['peerreview:releasefeedback'] = 'Release or hide feedback';
$string['peerreview:review'] = 'Review peers';
$string['peerreview:view'] = 'View peer reviews';
$string['peerreview:viewallreviews'] = 'View all reviews and reviewer names';
$string['peerreviewname'] = 'Peer review name';
$string['pluginadministration'] = 'Peer review administration';
$string['pluginname'] = 'Peer review';
$string['pointsonly'] = 'Only points are supported here, not scales.';
$string['preview'] = 'Preview';
$string['previewdeletes'] = '{$a} allocations that have not been started will be removed first.';
$string['previewkeptstarted'] = '{$a} allocations that already have a saved or submitted review are kept.';
$string['previewsummary'] = 'This will create {$a->new} new allocations. The largest difference in reviews received between two students is {$a->spread}. Nothing is saved until you confirm.';
$string['random'] = 'Random';
$string['replaceunstarted'] = 'Replace allocations that have not been started';
$string['replaceunstarted_help'] = 'Removes allocations in scope where no review has been saved yet, then creates new ones. Allocations with a saved or submitted review are always kept.';
$string['reviewedby'] = 'Written by {$a}';
$string['reviewee'] = 'Reviewee';
$string['reviewees'] = 'Reviewees';
$string['reviewer'] = 'Reviewer';
$string['reviewof'] = 'Review of {$a}';
$string['reviewsgiven'] = 'Reviews to give';
$string['reviewspersstudent'] = 'Reviews per student';
$string['reviewsreceived'] = 'Reviews to receive';
$string['reviewsubmitted'] = 'Review submitted.';
$string['reviewupdated'] = 'Review updated.';
$string['rotationorder'] = 'Order students by';
$string['rotationshift'] = 'Shift (K)';
$string['rotationusernames'] = 'Explicit order (usernames)';
$string['rotationusernames_help'] = 'Optional. One username per line, in presentation order. If filled in, only these students are used and the order above is ignored.';
$string['savedraft'] = 'Save draft';
$string['score'] = 'Score';
$string['score_help'] = 'Give a score from 0 up to the maximum for this activity. No rubric or marking guide has been set up, so a simple score is used.';
$string['status'] = 'Status';
$string['statusdraft'] = 'Draft saved';
$string['statusnew'] = 'Not started';
$string['statussubmitted'] = 'Submitted';
$string['student'] = 'Student';
$string['submitreview'] = 'Submit review';
$string['tabcsv'] = 'CSV';
$string['tabgroup'] = 'Within group';
$string['tabmanual'] = 'Manual';
$string['taboverview'] = 'Current allocations';
$string['tabrandom'] = 'Random';
$string['tabrotation'] = 'Rotation';
$string['timeclose'] = 'Close date';
$string['timeopen'] = 'Open date';
$string['unknownuser'] = 'Unknown user';
$string['updatereview'] = 'Update review';
$string['warngrouptoosmall'] = 'Group {$a} has fewer than 2 students and was skipped.';
$string['warnnreduced'] = 'Group or pool {$a->pool} has {$a->size} students, so only {$a->n} reviews each are possible.';
$string['warnpairdropped'] = 'Some reviews could not be placed without a repeat or self-review (user {$a}).';
$string['warnpooltoosmall'] = 'Group or pool {$a} has fewer than 2 students and was skipped.';
$string['warnrotationinvalid'] = 'A shift of {$a->shift} does not work for {$a->size} students (it must not be a multiple of the list length, and at least 2 students are needed).';
$string['warnunknownusername'] = 'Username "{$a}" is not an enrolled student and was ignored.';
