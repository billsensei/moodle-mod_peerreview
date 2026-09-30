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
 * Lists all peer review activities in a course.
 *
 * Modelled on mod/page/index.php.
 *
 * @package    mod_peerreview
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

$id = required_param('id', PARAM_INT); // Course id.

$course = get_course($id);
require_course_login($course, true);
$PAGE->set_pagelayout('incourse');

$event = \mod_peerreview\event\course_module_instance_list_viewed::create([
    'context' => context_course::instance($course->id),
]);
$event->add_record_snapshot('course', $course);
$event->trigger();

$PAGE->set_url('/mod/peerreview/index.php', ['id' => $course->id]);
$PAGE->set_title(get_string('modulenameplural', 'mod_peerreview'));
$PAGE->set_heading(format_string($course->fullname));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('modulenameplural', 'mod_peerreview'));

$instances = get_all_instances_in_course('peerreview', $course);
if (!$instances) {
    notice(get_string('nopeerreviews', 'mod_peerreview'), new moodle_url('/course/view.php', ['id' => $course->id]));
}

$table = new html_table();
$table->head = [get_string('name')];
foreach ($instances as $instance) {
    $link = html_writer::link(
        new moodle_url('/mod/peerreview/view.php', ['id' => $instance->coursemodule]),
        format_string($instance->name),
        $instance->visible ? [] : ['class' => 'dimmed']
    );
    $table->data[] = [$link];
}
echo html_writer::table($table);
echo $OUTPUT->footer();
