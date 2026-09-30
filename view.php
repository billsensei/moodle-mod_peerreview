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
 * Prints the main page of a peer review activity. Phase 2 stub; the student and teacher views arrive in phase 5.
 *
 * Modelled on mod/page/view.php.
 *
 * @package    mod_peerreview
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

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

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($peerreview->name));
if (!empty($peerreview->intro)) {
    echo $OUTPUT->box(format_module_intro('peerreview', $peerreview, $cm->id), 'generalbox mod_introbox');
}
echo $OUTPUT->footer();
