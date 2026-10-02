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
 * Teacher report: progress per student, live refresh, and a drill-down into one student's reviews.
 *
 * Modelled on mod/workshop/view.php (grades report) and mod/assign/view.php (grading table).
 *
 * @package    mod_peerreview
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

use core\output\notification;
use mod_peerreview\form\override_form;
use mod_peerreview\local\allocation\manager;
use mod_peerreview\local\grade\aggregator;
use mod_peerreview\local\grade\grade_range;
use mod_peerreview\local\grade\override;
use mod_peerreview\local\group_access;
use mod_peerreview\local\progress;
use mod_peerreview\output\release_button;
use mod_peerreview\output\report_detail;
use mod_peerreview\output\report_table;

$id = required_param('id', PARAM_INT); // Course module id.
$userid = optional_param('user', 0, PARAM_INT);

[$course, $cm] = get_course_and_cm_from_cmid($id, 'peerreview');
$peerreview = $DB->get_record('peerreview', ['id' => $cm->instance], '*', MUST_EXIST);
require_login($course, false, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/peerreview:viewallreviews', $context);

$pageurl = new moodle_url('/mod/peerreview/report.php', ['id' => $cm->id]);
$PAGE->set_url($userid ? new moodle_url($pageurl, ['user' => $userid]) : $pageurl);
$PAGE->set_title(get_string('report', 'mod_peerreview'));
$PAGE->set_heading(format_string($course->fullname));
$renderer = $PAGE->get_renderer('mod_peerreview');

$manager = new manager($peerreview, $cm->get_course_module_record(), $context);
$progress = new progress($peerreview, $manager);
$groupid = group_access::resolve($cm, $context, (int) groups_get_activity_group($cm, true));
$rows = $progress->get_rows($groupid);
$range = grade_range::for_activity($peerreview);

// The grade override form is processed before any output so it can redirect.
$overrideform = null;
$overridecurrent = null;
if ($userid && isset($rows[$userid]) && has_capability('mod/peerreview:overridegrade', $context)) {
    $overrides = new override($peerreview, $context);
    $overridecurrent = $overrides->get($userid);
    $calculated = aggregator::aggregate(
        (new aggregator($peerreview))->get_received_grades([$userid])[$userid] ?? [],
        (int) $peerreview->aggregation
    );
    $overrideform = new override_form($PAGE->url, [
        'cmid' => $cm->id,
        'userid' => $userid,
        'range' => $range,
        'grade' => $overridecurrent ? $overridecurrent->grade : null,
        'note' => $overridecurrent ? $overridecurrent->note : '',
        'calculated' => $calculated,
    ]);
    if ($overrideform->is_cancelled()) {
        redirect($pageurl);
    }
    if ($data = $overrideform->get_data()) {
        $overrides->set($userid, (float) $data->overridegrade, (string) $data->overridenote, (int) $USER->id);
        redirect($PAGE->url, get_string('overridesaved', 'mod_peerreview'), null, notification::NOTIFY_SUCCESS);
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($peerreview->name) . ': ' . get_string('report', 'mod_peerreview'), 2);

if ($userid) {
    // Drill-down: every review to and from one student, opened read-only.
    if (!isset($rows[$userid])) {
        throw new moodle_exception('invaliduser', 'error');
    }
    $given = [];
    $received = [];
    $names = [];
    foreach ($manager->get_allocations() as $alloc) {
        if ((int) $alloc->reviewerid === $userid) {
            $given[] = $alloc;
        }
        if ((int) $alloc->revieweeid === $userid) {
            $received[] = $alloc;
        }
    }
    $names = $manager->get_students();
    echo $renderer->render(new report_detail($cm->id, $rows[$userid]->user, $given, $received, $names, $range));
    if ($overrideform) {
        $overrideform->display();
        if ($overridecurrent) {
            echo $OUTPUT->single_button(
                new moodle_url('/mod/peerreview/action.php', [
                    'id' => $cm->id,
                    'action' => 'revertoverride',
                    'user' => $userid,
                    'returnto' => 'report',
                ]),
                get_string('revertoverride', 'mod_peerreview'),
                'post'
            );
        }
    }
    echo $OUTPUT->footer();
    die();
}

groups_print_activity_menu($cm, $pageurl);
$PAGE->requires->js_call_amd('mod_peerreview/progress', 'init');
echo $renderer->render(new report_table(
    $cm->id,
    max(0, $groupid),
    $rows,
    (bool) get_user_preferences('mod_peerreview_autorefresh', true),
    $range
));

echo html_writer::start_div('d-flex flex-wrap gap-3 mt-3');
if (has_capability('mod/peerreview:releasefeedback', $context)) {
    echo $OUTPUT->render(release_button::make($cm->id, (bool) $peerreview->feedbackreleased, 'report'));
}
if (has_capability('mod/peerreview:overridegrade', $context)) {
    echo $OUTPUT->render(new \single_button(
        new moodle_url('/mod/peerreview/action.php', ['id' => $cm->id, 'action' => 'pushgrades', 'returnto' => 'report']),
        get_string('pushgrades', 'mod_peerreview'),
        'post',
        \single_button::BUTTON_PRIMARY
    ));
}
if (has_capability('mod/peerreview:export', $context)) {
    echo $OUTPUT->download_dataformat_selector(
        get_string('exportreviews', 'mod_peerreview'),
        new moodle_url('/mod/peerreview/export.php'),
        'dataformat',
        ['id' => $cm->id]
    );
}
echo html_writer::end_div();
echo $OUTPUT->footer();
