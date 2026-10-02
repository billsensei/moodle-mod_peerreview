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
 * Web service: live progress for the teacher report.
 *
 * Modelled on mod/assign/classes/external/start_submission.php.
 *
 * @package    mod_peerreview
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_peerreview\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use mod_peerreview\local\allocation\manager;
use mod_peerreview\local\grade\grade_range;
use mod_peerreview\local\group_access;
use mod_peerreview\local\progress;

/**
 * Returns per-student progress figures.
 *
 * @package    mod_peerreview
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_progress extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
            'groupid' => new external_value(PARAM_INT, 'Group id, 0 for all participants', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Get progress.
     *
     * @param int $cmid Course module id.
     * @param int $groupid Group id, 0 for everyone.
     * @return array
     */
    public static function execute(int $cmid, int $groupid = 0): array {
        global $DB;

        ['cmid' => $cmid, 'groupid' => $groupid] = self::validate_parameters(
            self::execute_parameters(),
            ['cmid' => $cmid, 'groupid' => $groupid]
        );
        [, $cm] = get_course_and_cm_from_cmid($cmid, 'peerreview');
        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/peerreview:viewallreviews', $context);

        $peerreview = $DB->get_record('peerreview', ['id' => $cm->instance], '*', MUST_EXIST);
        $manager = new manager($peerreview, $cm->get_course_module_record(), $context);
        $progress = new progress($peerreview, $manager);
        $rows = $progress->get_rows(group_access::resolve($cm, $context, $groupid));
        $range = grade_range::for_activity($peerreview);

        $result = [];
        foreach ($rows as $row) {
            $result[] = [
                'userid' => $row->userid,
                'givendone' => $row->given_done,
                'giventotal' => $row->given_total,
                'receiveddone' => $row->received_done,
                'receivedtotal' => $row->received_total,
                'grade' => $row->grade === null ? '' : $range->format($row->grade),
                'overridden' => (bool) $row->overridden,
                'participation' => $row->participation ?? -1,
            ];
        }
        $summary = $progress->summarise($rows);
        return [
            'rows' => $result,
            'students' => $summary->students,
            'allocations' => $summary->allocations,
            'submitted' => $summary->submitted,
            'time' => time(),
        ];
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'rows' => new external_multiple_structure(new external_single_structure([
                'userid' => new external_value(PARAM_INT, 'User id'),
                'givendone' => new external_value(PARAM_INT, 'Reviews given (submitted)'),
                'giventotal' => new external_value(PARAM_INT, 'Reviews assigned to give'),
                'receiveddone' => new external_value(PARAM_INT, 'Reviews received (submitted)'),
                'receivedtotal' => new external_value(PARAM_INT, 'Reviews assigned to receive'),
                'grade' => new external_value(PARAM_RAW, 'Received grade, formatted; empty when none'),
                'overridden' => new external_value(PARAM_BOOL, 'The grade is a teacher override'),
                'participation' => new external_value(PARAM_INT, 'Participation percent, -1 when nothing assigned'),
            ])),
            'students' => new external_value(PARAM_INT, 'Students shown'),
            'allocations' => new external_value(PARAM_INT, 'Allocations among them'),
            'submitted' => new external_value(PARAM_INT, 'Submitted reviews among them'),
            'time' => new external_value(PARAM_INT, 'Server time of the figures'),
        ]);
    }
}
