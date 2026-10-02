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
 * Teacher page: allocate who reviews whom.
 *
 * Methods: manual, random (N per student), group (all-to-all), rotation, self (self-assessment), csv (import/export),
 * plus deleting pairs.
 * Automatic methods show a preview first and only write on confirmation.
 *
 * Modelled on mod/workshop/allocation.php (page structure) and admin/tool/uploaduser (CSV preview/confirm).
 *
 * @package    mod_peerreview
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->libdir . '/csvlib.class.php');

use mod_peerreview\form\allocate_form;
use mod_peerreview\local\allocation\csv;
use mod_peerreview\local\allocation\manager;
use mod_peerreview\output\allocation_list;
use mod_peerreview\output\allocation_preview;

$id = required_param('id', PARAM_INT); // Course module id.
$method = optional_param('method', 'overview', PARAM_ALPHA);

[$course, $cm] = get_course_and_cm_from_cmid($id, 'peerreview');
$peerreview = $DB->get_record('peerreview', ['id' => $cm->instance], '*', MUST_EXIST);
require_login($course, false, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/peerreview:allocate', $context);

$manager = new manager($peerreview, $cm->get_course_module_record(), $context);
$csv = new csv($peerreview, $manager);
$students = $manager->get_students();
$pageurl = new moodle_url('/mod/peerreview/allocate.php', ['id' => $cm->id]);
$overviewurl = new moodle_url($pageurl, ['method' => 'overview']);

if ($method === 'csvexport') {
    \core\dataformat::download_data(
        'peerreview_allocations',
        'csv',
        [
            'reviewer' => get_string('reviewer', 'mod_peerreview'),
            'reviewee' => get_string('reviewee', 'mod_peerreview'),
            'status' => get_string('status', 'mod_peerreview'),
        ],
        $csv->export_rows()
    );
    die();
}

$PAGE->set_url(new moodle_url($pageurl, ['method' => $method]));
$PAGE->set_title(get_string('allocate', 'mod_peerreview'));
$PAGE->set_heading(format_string($course->fullname));
$renderer = $PAGE->get_renderer('mod_peerreview');

// Print the page header with the method tabs.
$printheader = function (string $current) use ($OUTPUT, $pageurl, $peerreview): void {
    echo $OUTPUT->header();
    echo $OUTPUT->heading(format_string($peerreview->name) . ': ' . get_string('allocate', 'mod_peerreview'), 2);
    $tabs = [];
    foreach (['overview', 'manual', 'random', 'group', 'rotation', 'self', 'csv'] as $tab) {
        $tabs[] = new tabobject(
            $tab,
            new moodle_url($pageurl, ['method' => $tab]),
            get_string('tab' . $tab, 'mod_peerreview')
        );
    }
    echo $OUTPUT->tabtree($tabs, $current);
};

// Redirect to the overview with a notification.
$finish = function (string $message, string $type = \core\output\notification::NOTIFY_SUCCESS) use ($overviewurl): void {
    redirect($overviewurl, $message, null, $type);
};

$groups = [];
foreach ($manager->get_group_members() as $groupid => $members) {
    $groups[$groupid] = format_string(groups_get_group_name($groupid) ?: (string) $groupid) . ' (' . count($members) . ')';
}
$names = array_map(static fn($user) => fullname($user), $students);

$formcustom = [
    'method' => $method,
    'cmid' => $cm->id,
    'students' => $names,
    'groups' => $groups,
    'groupmode' => (int) $cm->groupmode !== NOGROUPS,
];

// Delete selected allocations (with confirmation; started reviews get an explicit warning).
if ($method === 'delete') {
    $ids = optional_param_array('delete', [], PARAM_INT);
    if (!$ids) {
        $finish(get_string('nothingselected', 'mod_peerreview'), \core\output\notification::NOTIFY_INFO);
    }
    $started = $manager->count_started($ids);
    if (optional_param('confirm', 0, PARAM_BOOL)) {
        require_sesskey();
        if ($manager->count_started($ids) > optional_param('expectedstarted', 0, PARAM_INT)) {
            $finish(get_string('errorstartedchanged', 'mod_peerreview'), \core\output\notification::NOTIFY_ERROR);
        }
        $deleted = $manager->delete($ids, true);
        $finish(get_string('allocationsdeleted', 'mod_peerreview', $deleted));
    }
    $printheader('overview');
    $message = get_string('confirmdelete', 'mod_peerreview', count($ids));
    if ($started) {
        $message = get_string('confirmdeletestarted', 'mod_peerreview', (object) ['count' => count($ids), 'started' => $started]);
    }
    echo $OUTPUT->confirm(
        $message,
        new single_button(
            new moodle_url($pageurl, ['method' => 'delete', 'delete' => $ids, 'confirm' => 1, 'expectedstarted' => $started]),
            get_string('delete'),
            'post',
            single_button::BUTTON_DANGER
        ),
        $overviewurl
    );
    echo $OUTPUT->footer();
    die();
}

if ($method === 'overview') {
    $printheader('overview');
    $allocations = $manager->get_allocations();
    $userids = [];
    foreach ($allocations as $alloc) {
        $userids[$alloc->reviewerid] = $alloc->reviewerid;
        $userids[$alloc->revieweeid] = $alloc->revieweeid;
    }
    $users = $students;
    $missing = array_diff($userids, array_keys($users));
    if ($missing) {
        $users += $DB->get_records_list('user', 'id', $missing, '', implode(',', \core_user\fields::get_name_fields()) . ',id');
    }
    if (!$students) {
        echo $OUTPUT->notification(get_string('nostudents', 'mod_peerreview'), \core\output\notification::NOTIFY_WARNING);
    }
    echo $renderer->render(new allocation_list($cm->id, $allocations, $users));
    echo $OUTPUT->single_button(
        new moodle_url($pageurl, ['method' => 'csvexport']),
        get_string('csvexport', 'mod_peerreview'),
        'get'
    );
    echo $OUTPUT->footer();
    die();
}

if (!in_array($method, ['manual', 'random', 'group', 'rotation', 'self', 'csv'], true)) {
    throw new moodle_exception('invalidparameter', 'debug');
}

// Print the preview page for a plan; the params of $confirmurl are posted by the confirm button.
$printpreview = function (
    stdClass $plan,
    moodle_url $confirmurl
) use (
    $printheader,
    $method,
    $renderer,
    $students,
    $pageurl,
    $OUTPUT
): void {
    $printheader($method);
    echo $renderer->render(new allocation_preview($plan, $students, $confirmurl, new moodle_url($pageurl, ['method' => $method])));
    echo $OUTPUT->footer();
};

// Parameters of the automatic methods, read from the request (confirm step) or from the form (preview step).
$readparams = static function (array|stdClass|null $data) use ($method): array {
    $data = (array) $data;
    return match ($method) {
        'random' => ['n' => (int) ($data['n'] ?? 2), 'crossgroup' => (int) ($data['crossgroup'] ?? 0),
            'replace' => (int) ($data['replace'] ?? 0)],
        'group' => ['groupid' => (int) ($data['groupid'] ?? 0), 'replace' => (int) ($data['replace'] ?? 0)],
        'rotation' => ['shift' => (int) ($data['shift'] ?? 1), 'sortby' => clean_param($data['sortby'] ?? 'lastname', PARAM_ALPHA),
            'usernames' => (string) ($data['usernames'] ?? ''), 'replace' => (int) ($data['replace'] ?? 0)],
        default => [],
    };
};

if (in_array($method, ['random', 'group', 'rotation', 'self'], true) && optional_param('confirm', 0, PARAM_BOOL)) {
    require_sesskey();
    $params = $readparams([
        'n' => optional_param('n', 2, PARAM_INT),
        'crossgroup' => optional_param('crossgroup', 0, PARAM_BOOL),
        'replace' => optional_param('replace', 0, PARAM_BOOL),
        'groupid' => optional_param('groupid', 0, PARAM_INT),
        'shift' => optional_param('shift', 1, PARAM_INT),
        'sortby' => optional_param('sortby', 'lastname', PARAM_ALPHA),
        'usernames' => optional_param('usernames', '', PARAM_RAW),
    ]);
    $plan = $manager->plan($method, $params, required_param('seed', PARAM_INT));
    $created = $manager->save($plan->proposal, $USER->id, $plan->deleteids);
    $finish(get_string('allocationssaved', 'mod_peerreview', (object) [
        'created' => $created,
        'deleted' => count($plan->deleteids),
    ]));
}

if ($method === 'csv' && ($iid = optional_param('iid', 0, PARAM_INT)) && optional_param('confirm', 0, PARAM_BOOL)) {
    require_sesskey();
    $reader = new csv_import_reader($iid, 'peerreview_alloc');
    $rows = [];
    $reader->init();
    while ($line = $reader->next()) {
        $rows[count($rows) + 2] = [$line[0], $line[1]];
    }
    $reader->close();
    $reader->cleanup(true);
    [$proposal] = $csv->build_proposal($rows);
    $created = $manager->save($proposal, $USER->id);
    $finish(get_string('allocationssaved', 'mod_peerreview', (object) ['created' => $created, 'deleted' => 0]));
}

$form = new allocate_form($pageurl, $formcustom);
if ($form->is_cancelled()) {
    redirect($overviewurl);
}

if ($data = $form->get_data()) {
    if ($method === 'manual') {
        require_sesskey();
        [$created, $errors] = $manager->add_manual((int) $data->reviewerid, (array) $data->revieweeids, $USER->id);
        $message = get_string('allocationssaved', 'mod_peerreview', (object) ['created' => $created, 'deleted' => 0]);
        if ($errors) {
            $message .= ' ' . get_string('manualskipped', 'mod_peerreview', count($errors));
        }
        $finish($message, $errors ? \core\output\notification::NOTIFY_WARNING : \core\output\notification::NOTIFY_SUCCESS);
    }

    if ($method === 'csv') {
        $content = $form->get_file_content('csvfile');
        $iid = csv_import_reader::get_new_iid('peerreview_alloc');
        $reader = new csv_import_reader($iid, 'peerreview_alloc');
        $loaded = $reader->load_csv_content($content, 'utf-8', $data->delimiter);
        $columns = $loaded === false ? [] : (array) $reader->get_columns();
        $normalised = array_map(static fn($c) => \core_text::strtolower(trim($c)), $columns);
        if (array_slice($normalised, 0, 2) !== ['reviewer', 'reviewee']) {
            $reader->cleanup(true);
            $finish(get_string('csvnoheader', 'mod_peerreview'), \core\output\notification::NOTIFY_ERROR);
        }
        $rows = [];
        $reader->init();
        while ($line = $reader->next()) {
            $rows[count($rows) + 2] = [$line[0], $line[1] ?? ''];
        }
        $reader->close();
        [$proposal, $errors, $duplicates] = $csv->build_proposal($rows);
        foreach ($errors as [$linenumber, $identifier, $a]) {
            $proposal->warn('csvlineerror', (object) [
                'line' => $linenumber,
                'message' => get_string($identifier, 'mod_peerreview', $a),
            ]);
        }
        if ($duplicates) {
            $proposal->warn('csvduplicates', $duplicates);
        }
        [$given, $received] = $proposal->totals($manager->get_pairs());
        $plan = (object) ['proposal' => $proposal, 'deleteids' => [], 'keptstarted' => 0, 'given' => $given,
            'received' => $received];
        $printpreview($plan, new moodle_url($pageurl, ['method' => 'csv', 'confirm' => 1, 'iid' => $iid]));
        die();
    }

    $params = $readparams($data);
    $seed = random_int(1, 2147483647);
    $plan = $manager->plan($method, $params, $seed);
    $printpreview($plan, new moodle_url($pageurl, ['method' => $method, 'confirm' => 1, 'seed' => $seed] + $params));
    die();
}

$printheader($method);
if (!$students) {
    echo $OUTPUT->notification(get_string('nostudents', 'mod_peerreview'), \core\output\notification::NOTIFY_WARNING);
}
echo $OUTPUT->box(get_string('methodhelp_' . $method, 'mod_peerreview'));
$form->display();
echo $OUTPUT->footer();
