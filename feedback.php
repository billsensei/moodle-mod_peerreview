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
 * A reviewee reads one review they received (only after the teacher released feedback).
 *
 * The reviewer's identity is never sent when the activity is anonymous.
 *
 * @package    mod_peerreview
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

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

// Only the reviewee, only submitted reviews, only once released.
if ((int) $alloc->revieweeid !== (int) $USER->id || (int) $alloc->status !== manager::STATUS_SUBMITTED) {
    throw new moodle_exception('errornotyourfeedback', 'mod_peerreview', $viewurl);
}
if (!$peerreview->feedbackreleased) {
    throw new moodle_exception('errornotreleased', 'mod_peerreview', $viewurl);
}

$isself = (int) $alloc->reviewerid === (int) $USER->id;
if ($isself) {
    $who = get_string('selfreview', 'mod_peerreview');
} else if (empty($peerreview->anonymous)) {
    $who = fullname(core_user::get_user($alloc->reviewerid, '*', MUST_EXIST));
} else {
    $who = get_string('anonymousreviewer', 'mod_peerreview');
}

$PAGE->set_url('/mod/peerreview/feedback.php', ['id' => $cm->id, 'alloc' => $alloc->id]);
$PAGE->set_title(format_string($peerreview->name));
$PAGE->set_heading(format_string($course->fullname));

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($peerreview->name), 2);
echo $OUTPUT->heading(get_string('feedbackfrom', 'mod_peerreview', $who), 3);

$gradetext = get_string('gradeoutof', 'mod_peerreview', (object) [
    'grade' => format_float((float) $alloc->grade, 2, true, true),
    'max' => (int) $peerreview->grade,
]);
$controller = $service->get_controller();
$details = $controller ? $controller->render_grade($PAGE, $alloc->id, null, $gradetext, false) : $gradetext;
echo $OUTPUT->box($OUTPUT->heading($gradetext, 4) . ($controller ? $details : ''), 'mod_peerreview-feedback-detail mb-3');
if (trim((string) $alloc->feedback) !== '') {
    echo $OUTPUT->box(format_text($alloc->feedback, $alloc->feedbackformat, ['context' => $context]), 'mod_peerreview-comment');
}
echo $OUTPUT->single_button($viewurl, get_string('back'), 'get');
echo $OUTPUT->footer();
