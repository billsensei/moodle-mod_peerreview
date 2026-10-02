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
 * The activity page: students see their reviews to do and the feedback received; teachers see a summary.
 *
 * Modelled on mod/workshop/view.php and mod/assign/view.php.
 *
 * @package    mod_peerreview
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

use mod_peerreview\local\allocation\manager;
use mod_peerreview\local\criterion_stats;
use mod_peerreview\local\progress;
use mod_peerreview\local\review\service;
use mod_peerreview\local\student_data;
use mod_peerreview\output\student_view;
use mod_peerreview\output\teacher_summary;

$id = required_param('id', PARAM_INT); // Course module id.

[$course, $cm] = get_course_and_cm_from_cmid($id, 'peerreview');
$peerreview = $DB->get_record('peerreview', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/peerreview:view', $context);

$event = \mod_peerreview\event\course_module_viewed::create([
    'objectid' => $peerreview->id,
    'context' => $context,
]);
$event->add_record_snapshot('course', $course);
$event->add_record_snapshot('peerreview', $peerreview);
$event->trigger();

$PAGE->set_url('/mod/peerreview/view.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($peerreview->name));
$PAGE->set_heading(format_string($course->fullname));
$renderer = $PAGE->get_renderer('mod_peerreview');

$manager = new manager($peerreview, $cm->get_course_module_record(), $context);
$window = 'open';
if ($peerreview->timeopen && time() < $peerreview->timeopen) {
    $window = 'notopen';
} else if (!(new service($peerreview, $context))->is_open()) {
    $window = 'closed';
}

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($peerreview->name));
if (!empty($peerreview->intro)) {
    echo $OUTPUT->box(format_module_intro('peerreview', $peerreview, $cm->id), 'generalbox mod_introbox');
}
$dates = [];
if ($peerreview->timeopen) {
    $dates[] = get_string('opensat', 'mod_peerreview', userdate($peerreview->timeopen));
}
if ($peerreview->timeclose) {
    $dates[] = get_string('closeson', 'mod_peerreview', userdate($peerreview->timeclose));
}
if ($dates) {
    echo $OUTPUT->box(implode(' &middot; ', array_map('s', $dates)), 'mod_peerreview-dates text-muted mb-3');
}

$isteacher = has_any_capability(
    ['mod/peerreview:viewallreviews', 'mod/peerreview:allocate', 'mod/peerreview:releasefeedback'],
    $context
);
if ($isteacher) {
    $progress = new progress($peerreview, $manager);
    echo $renderer->render(new teacher_summary(
        $peerreview,
        $context,
        $cm->id,
        $progress->summarise($progress->get_rows())
    ));
}

if (has_capability('mod/peerreview:review', $context)) {
    $data = new student_data($peerreview, $manager);
    $received = $peerreview->feedbackreleased ? $data->get_received((int) $USER->id) : null;
    $grade = $peerreview->feedbackreleased ? $data->get_grade((int) $USER->id) : null;
    $comparison = null;
    if ($peerreview->feedbackreleased) {
        $comparison = $data->get_self_comparison((int) $USER->id);
        if ($comparison) {
            $comparison->criteria = (new criterion_stats($peerreview, $context))->get_comparison((int) $USER->id);
        }
    }
    echo $renderer->render(new student_view(
        $peerreview,
        $context,
        $cm->id,
        $data->get_todo((int) $USER->id),
        $received,
        $grade,
        $window,
        $comparison
    ));
}
echo $OUTPUT->footer();
