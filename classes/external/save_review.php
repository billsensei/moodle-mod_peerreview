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
 * Web service: submit a review with the simple points or scale form (called by the Moodle app).
 *
 * Modelled on mod/assign/classes/external/submit_grading_form.php. Everything goes through
 * {@see service::submit()}, so the rules and the events are the same as on review.php.
 *
 * @package    mod_peerreview
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_peerreview\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use mod_peerreview\local\review\service;

/**
 * Stores a submitted review.
 *
 * @package    mod_peerreview
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class save_review extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
            'allocid' => new external_value(PARAM_INT, 'Allocation (review) id'),
            'score' => new external_value(PARAM_RAW, 'Points, or the position of the scale item'),
            'feedback' => new external_value(PARAM_TEXT, 'Overall comment as plain text', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Submit the review.
     *
     * @param int $cmid Course module id.
     * @param int $allocid Allocation id.
     * @param string $score Points or scale position.
     * @param string $feedback Overall comment.
     * @return array
     */
    public static function execute(int $cmid, int $allocid, string $score, string $feedback = ''): array {
        global $DB, $USER;

        $params = self::validate_parameters(
            self::execute_parameters(),
            ['cmid' => $cmid, 'allocid' => $allocid, 'score' => $score, 'feedback' => $feedback]
        );
        [, $cm] = get_course_and_cm_from_cmid($params['cmid'], 'peerreview');
        $context = \context_module::instance($cm->id);
        self::validate_context($context);

        $peerreview = $DB->get_record('peerreview', ['id' => $cm->instance], '*', MUST_EXIST);
        $service = new service($peerreview, $context);
        $alloc = $service->get_allocation($params['allocid']);
        // The reviewer, inside the open window (the service checks both).
        $service->require_can_edit($alloc, (int) $USER->id);
        get_review::require_simple_form($service);

        $service->submit($alloc, (int) $USER->id, [
            'score' => trim($params['score']),
            'feedback' => $params['feedback'],
            'feedbackformat' => FORMAT_PLAIN,
        ]);
        return ['status' => true];
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'status' => new external_value(PARAM_BOOL, 'Always true: failures throw'),
        ]);
    }
}
