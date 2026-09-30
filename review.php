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
 * A reviewer writes a review of one reviewee; teachers can open any review read-only.
 *
 * Modelled on mod/assign grading form handling (get_grading_instance, save_grade) and mod/assign/view.php.
 *
 * @package    mod_peerreview
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

use core\output\notification;
use mod_peerreview\form\review_form;
use mod_peerreview\local\allocation\manager;
use mod_peerreview\local\review\service;

$id = required_param('id', PARAM_INT); // Course module id.
$allocid = required_param('alloc', PARAM_INT);

[$course, $cm] = get_course_and_cm_from_cmid($id, 'peerreview');
$peerreview = $DB->get_record('peerreview', ['id' => $cm->instance], '*', MUST_EXIST);
require_login($course, false, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/peerreview:view', $context);

$service = new service($peerreview, $context);
$alloc = $service->get_allocation($allocid);
$viewurl = new moodle_url('/mod/peerreview/view.php', ['id' => $cm->id]);
$pageurl = new moodle_url('/mod/peerreview/review.php', ['id' => $cm->id, 'alloc' => $alloc->id]);

$isreviewer = (int) $alloc->reviewerid === (int) $USER->id;
if (!$isreviewer) {
    require_capability('mod/peerreview:viewallreviews', $context);
}

// Editing is for the reviewer, inside the open window; everybody else sees the submitted review read-only.
$notice = '';
$readonly = false;
try {
    $service->require_can_edit($alloc, (int) $USER->id);
} catch (moodle_exception $e) {
    $readonly = true;
    $notice = $isreviewer ? get_string($e->errorcode, 'mod_peerreview') : '';
}

$instance = $readonly
    ? $service->get_submitted_instance($alloc)
    : $service->get_instance($alloc, (int) $USER->id, optional_param('advancedgradinginstanceid', null, PARAM_INT));
$unavailable = $readonly ? '' : $service->get_unavailable_message();

$submitted = (int) $alloc->status === manager::STATUS_SUBMITTED;
$customdata = [
    'cmid' => $cm->id,
    'allocid' => $alloc->id,
    'instance' => $instance,
    'maxgrade' => $peerreview->grade,
    'context' => $context,
    'candraft' => !$submitted && $service->supports_drafts(),
    'submitted' => $submitted,
    'readonly' => $readonly,
    'score' => $alloc->grade,
    'feedback' => $alloc->feedback ?? '',
    'feedbackformat' => $alloc->feedbackformat,
];
$form = new review_form($pageurl, $customdata);

if ($form->is_cancelled()) {
    redirect($viewurl);
}

if (!$readonly && ($data = $form->get_data())) {
    $values = [
        'advancedgrading' => $data->advancedgrading ?? [],
        'advancedgradinginstanceid' => $data->advancedgradinginstanceid ?? null,
        'score' => $data->score ?? null,
        'feedback' => $data->feedback_editor['text'],
        'feedbackformat' => $data->feedback_editor['format'],
    ];
    if (review_form::is_draft_request()) {
        $service->save_draft($alloc, (int) $USER->id, $values);
        redirect($viewurl, get_string('draftsaved', 'mod_peerreview'), null, notification::NOTIFY_SUCCESS);
    }
    try {
        $service->submit($alloc, (int) $USER->id, $values);
    } catch (moodle_exception $e) {
        redirect($pageurl, $e->getMessage(), null, notification::NOTIFY_ERROR);
    }
    redirect(
        $viewurl,
        get_string($submitted ? 'reviewupdated' : 'reviewsubmitted', 'mod_peerreview'),
        null,
        notification::NOTIFY_SUCCESS
    );
}

$reviewee = core_user::get_user($alloc->revieweeid, '*', MUST_EXIST);
$PAGE->set_url($pageurl);
$PAGE->set_title(format_string($peerreview->name));
$PAGE->set_heading(format_string($course->fullname));

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($peerreview->name), 2);
echo $OUTPUT->heading(html_writer::tag('span', $OUTPUT->user_picture($reviewee, ['size' => 48, 'courseid' => $course->id]))
    . ' ' . get_string('reviewof', 'mod_peerreview', fullname($reviewee)), 3);
if (!$isreviewer) {
    $reviewer = core_user::get_user($alloc->reviewerid, '*', MUST_EXIST);
    echo $OUTPUT->notification(get_string('reviewedby', 'mod_peerreview', fullname($reviewer)), notification::NOTIFY_INFO);
}
if ($notice) {
    echo $OUTPUT->notification($notice, notification::NOTIFY_INFO);
}
if ($unavailable) {
    echo $OUTPUT->notification($unavailable, notification::NOTIFY_WARNING);
}
$form->display();
echo $OUTPUT->footer();
