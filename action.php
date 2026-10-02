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
 * State-changing actions from the teacher pages: release or hide feedback, push grades, revert a grade override, send reminders.
 *
 * @package    mod_peerreview
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once(__DIR__ . '/lib.php');

use core\output\notification;
use mod_peerreview\local\feedback;
use mod_peerreview\local\grade\override;
use mod_peerreview\local\group_access;
use mod_peerreview\local\reminder;

$id = required_param('id', PARAM_INT); // Course module id.
$action = required_param('action', PARAM_ALPHA);
$returnto = optional_param('returnto', 'view', PARAM_ALPHA);
$userid = optional_param('user', 0, PARAM_INT);
$groupid = optional_param('group', 0, PARAM_INT);

[$course, $cm] = get_course_and_cm_from_cmid($id, 'peerreview');
$peerreview = $DB->get_record('peerreview', ['id' => $cm->instance], '*', MUST_EXIST);
require_login($course, false, $cm);
$context = context_module::instance($cm->id);
require_sesskey();

// Each action needs a capability; the called classes check it again.
$actioncaps = [
    'release' => 'mod/peerreview:releasefeedback',
    'hide' => 'mod/peerreview:releasefeedback',
    'pushgrades' => 'mod/peerreview:overridegrade',
    'revertoverride' => 'mod/peerreview:overridegrade',
    'sendreminders' => 'mod/peerreview:allocate',
];
if (!isset($actioncaps[$action])) {
    throw new moodle_exception('invalidparameter', 'debug');
}
require_capability($actioncaps[$action], $context);

$target = new moodle_url($returnto === 'report' ? '/mod/peerreview/report.php' : '/mod/peerreview/view.php', ['id' => $cm->id]);
if ($userid && $returnto === 'report') {
    $target->param('user', $userid);
}

switch ($action) {
    case 'release':
    case 'hide':
        feedback::set_released($peerreview, $context, $action === 'release'); // Checks the capability.
        $message = get_string($action === 'release' ? 'feedbackwasreleased' : 'feedbackwashidden', 'mod_peerreview');
        break;
    case 'pushgrades':
        require_capability('mod/peerreview:overridegrade', $context);
        peerreview_update_grades($peerreview);
        $message = get_string('gradespushed', 'mod_peerreview');
        break;
    case 'revertoverride':
        $reverted = (new override($peerreview, $context))->revert($userid); // Checks the capability.
        $message = get_string($reverted ? 'overridereverted' : 'nooverridetorevert', 'mod_peerreview');
        break;
    case 'sendreminders':
        $sent = (new reminder($peerreview, $cm->get_course_module_record(), $context)) // Checks the capability.
            ->send(group_access::resolve($cm, $context, $groupid), (int) $USER->id);
        $message = $sent ? get_string('remindersent', 'mod_peerreview', $sent) : get_string('remindersnone', 'mod_peerreview');
        break;
    default:
        throw new moodle_exception('invalidparameter', 'debug');
}
redirect($target, $message, null, notification::NOTIFY_SUCCESS);
