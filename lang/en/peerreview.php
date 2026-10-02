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

$string['actionscol'] = 'Actions';
$string['activityclosed'] = 'This activity is closed. Reviews you submitted can be viewed but not changed.';
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
$string['allowselfreview_help'] = 'If enabled, a teacher can allocate a student to review themselves (the Self-assessment tab of the allocation page does it for every student at once). Self-reviews are not counted in the received grade; when feedback is released, students see their self-assessment next to their peers\'.';
$string['anonymous'] = 'Reviewer identity shown to reviewees';
$string['anonymous_anonymous'] = 'Anonymous';
$string['anonymous_byname'] = 'By name';
$string['anonymous_help'] = 'Teachers always see the real names.';
$string['anonymousreviewer'] = 'Anonymous';
$string['autorefresh'] = 'Refresh automatically every {$a} seconds';
$string['backtoreport'] = 'Back to the report';
$string['calculatedgrade'] = 'Calculated from the reviews';
$string['changesettings'] = 'Change settings';
$string['closeson'] = 'Closes: {$a}';
$string['completionallreviews'] = 'Student must submit all assigned reviews';
$string['completionallreviews_desc'] = 'Submit all assigned reviews';
$string['confirmallocation'] = 'Confirm and save';
$string['confirmdelete'] = 'Delete {$a} selected allocations?';
$string['confirmdeletestarted'] = 'Delete {$a->count} selected allocations? {$a->started} of them already have a saved or submitted review. Those reviews will be permanently deleted.';
$string['criterion'] = 'Criterion';
$string['criterionaverage'] = 'Average score';
$string['criterionmax'] = 'Highest possible';
$string['criterionscored'] = 'Reviews scored';
$string['criterionstats'] = 'Average by criterion';
$string['criterionstats_desc'] = 'Submitted reviews of the students in this view, not counting self-reviews. Scores are in the points of the rubric or marking guide, before they are converted to the grade.';
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
$string['errorgradetypelocked'] = 'The grade cannot change between points and a scale, or to a scale with another number of items, once reviews or overrides exist.';
$string['errornodrafts'] = 'Drafts are not available for this grading method.';
$string['errornogroups'] = 'There are no groups with students to allocate.';
$string['errornotopenyet'] = 'This activity is not open yet.';
$string['errornotreleased'] = 'The teacher has not released the feedback yet.';
$string['errornotstudent'] = 'Not an enrolled student.';
$string['errornotyourfeedback'] = 'This is not a review you received.';
$string['errornotyourreview'] = 'You are not the reviewer for this review.';
$string['errorpositive'] = 'Enter a number of 1 or more.';
$string['errorreviewincomplete'] = 'The review is empty or incomplete. Fill in every criterion before submitting.';
$string['errorscaleitem'] = 'Choose one of the scale items.';
$string['errorscorerange'] = 'Enter a number between 0 and {$a}.';
$string['errorselfnotallowed'] = 'Self-review is not allowed in this activity ({$a}).';
$string['errorshiftzero'] = 'The shift cannot be 0.';
$string['errorstartedchanged'] = 'More of the selected reviews were started while you were on this page. Nothing was deleted; please check again.';
$string['errorstartedreviews'] = 'These allocations include saved or submitted reviews and can only be deleted after confirmation.';
$string['eventallocation_created'] = 'Allocation created';
$string['eventallocation_deleted'] = 'Allocation deleted';
$string['eventcoursemoduleinstancelistviewed'] = 'Course module instance list viewed';
$string['eventcoursemoduleviewed'] = 'Course module viewed';
$string['eventfeedback_released'] = 'Feedback released or hidden';
$string['eventgrade_overridden'] = 'Received grade overridden';
$string['eventreminders_sent'] = 'Review reminders sent';
$string['eventreview_submitted'] = 'Review submitted';
$string['eventreview_updated'] = 'Review updated';
$string['exportreviews'] = 'Export all reviews';
$string['feedbackfrom'] = 'Feedback from {$a}';
$string['feedbackishidden'] = 'Feedback is hidden from students.';
$string['feedbackisreleased'] = 'Feedback is visible to students.';
$string['feedbackreceived'] = 'Feedback I received';
$string['feedbackreleased'] = 'Reviewees can see received feedback';
$string['feedbackwashidden'] = 'Feedback is now hidden from students.';
$string['feedbackwasreleased'] = 'Feedback is now visible to students.';
$string['grade_participation_name'] = 'Participation';
$string['grade_received_name'] = 'Received from peers';
$string['gradecol'] = 'Grade';
$string['gradeitem:participation'] = 'Participation';
$string['gradeitem:received'] = 'Received grade (review criteria)';
$string['gradeoutof'] = '{$a->grade} / {$a->max}';
$string['gradeparticipation'] = 'Participation grade';
$string['gradeparticipation_help'] = 'Optional second grade: the percentage of assigned reviews the student completed. Set to "None" to disable it.';
$string['gradespushed'] = 'Grades were sent to the gradebook.';
$string['hidefeedback'] = 'Hide feedback';
$string['manualskipped'] = '{$a} pairs were skipped (not enrolled students, or a self-review that is not allowed).';
$string['messageprovider:reminder'] = 'Reminder to finish peer reviews';
$string['methodhelp_csv'] = 'Upload a CSV file with the columns reviewer and reviewee (usernames or email addresses). You will see a preview before anything is saved.';
$string['methodhelp_group'] = 'Every member of a group reviews every other member of the same group. Good for assessing contribution in group work.';
$string['methodhelp_manual'] = 'Choose a reviewer and the students they will review. Existing pairs are left alone.';
$string['methodhelp_random'] = 'Each student reviews N classmates, chosen at random. The reviews are shared out evenly, nobody reviews themselves, and reviews stay inside groups unless you allow otherwise.';
$string['methodhelp_rotation'] = 'Students are put in a list and each reviews the student K places further down (the last wraps round to the first). Useful when students present in a known order.';
$string['methodhelp_self'] = 'Every student reviews themselves, so they can compare their own assessment with their classmates\' on the feedback page. Needs "Allow self-review" in the activity settings. Students who already have a self-review are left alone.';
$string['modulename'] = 'Peer review';
$string['modulename_help'] = 'Students assess each other in class using a rubric or marking guide. The teacher decides who reviews whom, and a grade based on the reviews received is sent to the gradebook.';
$string['modulenameplural'] = 'Peer reviews';
$string['noallocations'] = 'Nobody has been allocated yet.';
$string['noallocationsyet'] = 'Nobody has been allocated yet. Use Allocate reviewers to decide who reviews whom.';
$string['nogradeyet'] = 'No grade yet';
$string['nonegiven'] = 'This student has no reviews to give.';
$string['nonereceived'] = 'Nobody is assigned to review this student.';
$string['nooverridetorevert'] = 'There was no override to remove.';
$string['nopeerreviews'] = 'There are no peer review activities in this course.';
$string['noreviews'] = 'There are no reviews to show yet.';
$string['noreviewsassigned'] = 'You have no reviews to do yet. Your teacher will assign them.';
$string['noreviewsreceived'] = 'No reviews have been submitted for you yet.';
$string['nostudents'] = 'No enrolled students can review this activity yet.';
$string['nostudentsshown'] = 'No students to show.';
$string['nothingselected'] = 'Nothing was selected.';
$string['notopenyet'] = 'This activity is not open yet.';
$string['openreview'] = 'Open';
$string['opensat'] = 'Opens: {$a}';
$string['overallcomment'] = 'Overall comment';
$string['overridegrade'] = 'Override the received grade';
$string['overridegradescale'] = 'Grade';
$string['overridegradevalue'] = 'Grade (0 to {$a})';
$string['overridenote'] = 'Note (why)';
$string['overridereverted'] = 'Override removed. The grade is calculated from the reviews again.';
$string['overridesaved'] = 'Override saved. Push grades to send it to the gradebook.';
$string['participation'] = 'Participation';
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
$string['pointsonly'] = 'The participation grade supports points only, not scales.';
$string['preview'] = 'Preview';
$string['previewdeletes'] = '{$a} allocations that have not been started will be removed first.';
$string['previewkeptstarted'] = '{$a} allocations that already have a saved or submitted review are kept.';
$string['previewsummary'] = 'This will create {$a->new} new allocations. The largest difference in reviews received between two students is {$a->spread}. Nothing is saved until you confirm.';
$string['privacy:metadata:core_grading'] = 'The rubric or marking guide a reviewer fills in is stored by the advanced grading subsystem, one grading instance per review.';
$string['privacy:metadata:peerreview_alloc'] = 'Who reviews whom, and the review itself.';
$string['privacy:metadata:peerreview_alloc:allocatedby'] = 'The teacher who created the allocation.';
$string['privacy:metadata:peerreview_alloc:feedback'] = 'The overall comment of the reviewer.';
$string['privacy:metadata:peerreview_alloc:grade'] = 'The grade the reviewer gave.';
$string['privacy:metadata:peerreview_alloc:revieweeid'] = 'The student being reviewed.';
$string['privacy:metadata:peerreview_alloc:reviewerid'] = 'The student writing the review.';
$string['privacy:metadata:peerreview_alloc:status'] = 'Whether the review is not started, a draft or submitted.';
$string['privacy:metadata:peerreview_alloc:timecreated'] = 'When the allocation was created.';
$string['privacy:metadata:peerreview_alloc:timemodified'] = 'When the review was last changed.';
$string['privacy:metadata:peerreview_alloc:timesubmitted'] = 'When the review was submitted.';
$string['privacy:metadata:peerreview_override'] = 'Received grades set by a teacher instead of the peer aggregate.';
$string['privacy:metadata:peerreview_override:grade'] = 'The grade set by the teacher.';
$string['privacy:metadata:peerreview_override:note'] = 'The teacher\'s note about the override.';
$string['privacy:metadata:peerreview_override:overriddenby'] = 'The teacher who set the override.';
$string['privacy:metadata:peerreview_override:timemodified'] = 'When the override was set.';
$string['privacy:metadata:peerreview_override:userid'] = 'The student whose grade was overridden.';
$string['privacy:metadata:preference:autorefresh'] = 'Whether the progress report refreshes itself.';
$string['privacy:override'] = 'Grade override';
$string['privacy:overridesgiven'] = 'Grade overrides set';
$string['privacy:reviewsgiven'] = 'Reviews given';
$string['privacy:reviewsreceived'] = 'Reviews received';
$string['pushgrades'] = 'Push grades to gradebook';
$string['random'] = 'Random';
$string['receivedgrade'] = 'Received grade';
$string['refreshnow'] = 'Refresh now';
$string['releasefeedback'] = 'Release feedback';
$string['remark'] = 'Remark';
$string['reminderconfirm'] = 'Send a message to every student in this view who has reviews left to do?';
$string['reminderlead'] = 'Remind students before the close date';
$string['reminderlead_help'] = 'Moodle sends one message to each student who still has peer reviews to do, this long before the close date. It needs a close date and the Moodle cron, and is sent only once for each close date; changing the close date or this setting arms it again. The teacher\'s button on the report works as before.';
$string['reminderleadday'] = '1 day before';
$string['reminderleaddays'] = '{$a} days before';
$string['reminderleadoff'] = 'Do not send automatic reminders';
$string['reminderleadweek'] = '1 week before';
$string['remindermessage'] = 'Hello {$a->firstname},

You still have {$a->remaining} of {$a->total} peer reviews to finish in {$a->activity}.

Open the activity: {$a->url}';
$string['remindernotopen'] = 'Reminders can only be sent while the activity is open.';
$string['remindersent'] = '{$a} students were sent a reminder.';
$string['remindersmall'] = '{$a->remaining} peer reviews left in {$a->activity}';
$string['remindersnone'] = 'Nobody in this view has reviews left to do, so no reminders were sent.';
$string['remindersubject'] = 'Peer reviews to finish: {$a}';
$string['replaceunstarted'] = 'Replace allocations that have not been started';
$string['replaceunstarted_help'] = 'Removes allocations in scope where no review has been saved yet, then creates new ones. Allocations with a saved or submitted review are always kept.';
$string['report'] = 'Report';
$string['resetgradebook'] = 'Delete gradebook grades';
$string['resetgradebook_help'] = 'Clears the grades this activity has sent to the gradebook.';
$string['resetreviews'] = 'Delete allocations, reviews and grade overrides';
$string['revertoverride'] = 'Remove override';
$string['review0'] = 'Start review';
$string['review1'] = 'Continue review';
$string['review2'] = 'Edit review';
$string['reviewedby'] = 'Written by {$a}';
$string['reviewee'] = 'Reviewee';
$string['reviewees'] = 'Reviewees';
$string['reviewer'] = 'Reviewer';
$string['reviewof'] = 'Review of {$a}';
$string['reviewoldmethod'] = 'This review was written with the grading method "{$a}", which is no longer the active one. It is shown as it was filled in.';
$string['reviewsdone'] = '{$a->done} of {$a->total} reviews done';
$string['reviewsgiven'] = 'Reviews to give';
$string['reviewsgivencol'] = 'Reviews given';
$string['reviewspersstudent'] = 'Reviews per student';
$string['reviewsreceived'] = 'Reviews to receive';
$string['reviewsreceivedcol'] = 'Reviews received';
$string['reviewsreceivedtitle'] = 'Reviews of this student';
$string['reviewstodo'] = 'Reviews to do';
$string['reviewsubmitted'] = 'Review submitted.';
$string['reviewupdated'] = 'Review updated.';
$string['rotationorder'] = 'Order students by';
$string['rotationshift'] = 'Shift (K)';
$string['rotationusernames'] = 'Explicit order (usernames)';
$string['rotationusernames_help'] = 'Optional. One username per line, in presentation order. If filled in, only these students are used and the order above is ignored.';
$string['savedraft'] = 'Save draft';
$string['saveoverride'] = 'Save override';
$string['score'] = 'Score';
$string['score_help'] = 'Give a score from 0 up to the maximum for this activity. No rubric or marking guide has been set up, so a simple score is used.';
$string['scorescale'] = 'Score';
$string['scorescale_help'] = 'Choose the scale item that fits this work best. No rubric or marking guide has been set up, so a single scale item is used.';
$string['selfcomparedifferent'] = 'Your self-assessment and your peers\' grade are different.';
$string['selfcompareheading'] = 'Your self-assessment and your peers';
$string['selfcomparehigher'] = 'You rated yourself {$a} points higher than your peers did.';
$string['selfcomparelower'] = 'You rated yourself {$a} points lower than your peers did.';
$string['selfcomparepeers'] = 'Your peers';
$string['selfcomparepeersaverage'] = 'Your peers (average)';
$string['selfcomparesame'] = 'Your self-assessment and your peers\' grade agree.';
$string['selfcompareself'] = 'Your self-assessment';
$string['selfcompareyou'] = 'You';
$string['selfreview'] = 'You (self-review)';
$string['sendreminders'] = 'Remind students with reviews to do';
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
$string['tabself'] = 'Self-assessment';
$string['tasksendreminders'] = 'Send automatic peer review reminders';
$string['teachersummary'] = '{$a} students can review this activity.';
$string['timeclose'] = 'Close date';
$string['timeopen'] = 'Open date';
$string['timesubmitted'] = 'Time submitted';
$string['unknownuser'] = 'Unknown user';
$string['updatedat'] = 'Updated {$a}';
$string['updatefailed'] = 'Could not refresh. Trying again.';
$string['updatereview'] = 'Update review';
$string['viewdetails'] = 'View details';
$string['viewreview'] = 'View review';
$string['warngrouptoosmall'] = 'Group {$a} has fewer than 2 students and was skipped.';
$string['warnnreduced'] = 'Group or pool {$a->pool} has {$a->size} students, so only {$a->n} reviews each are possible.';
$string['warnpairdropped'] = 'Some reviews could not be placed without a repeat or self-review (user {$a}).';
$string['warnpooltoosmall'] = 'Group or pool {$a} has fewer than 2 students and was skipped.';
$string['warnrotationinvalid'] = 'A shift of {$a->shift} does not work for {$a->size} students (it must not be a multiple of the list length, and at least 2 students are needed).';
$string['warnselfnotallowed'] = 'Self-review is not allowed in this activity, so nothing was proposed. Turn on "Allow self-review" in the activity settings first.';
$string['warnunknownusername'] = 'Username "{$a}" is not an enrolled student and was ignored.';
$string['yourgrade'] = 'Your grade:';
