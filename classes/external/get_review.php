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
 * Web service: one review as the reviewer sees it (called by the Moodle app).
 *
 * Only the simple points or scale form is served. A rubric or marking guide is refused: the app opens review.php.
 * Modelled on mod/assign/classes/external/get_submission_status.php for the shape, and on review.php for the rules.
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
use mod_peerreview\local\review\service;

/**
 * Returns the state of a review for the reviewer.
 *
 * @package    mod_peerreview
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_review extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
            'allocid' => new external_value(PARAM_INT, 'Allocation (review) id'),
        ]);
    }

    /**
     * Get the review.
     *
     * @param int $cmid Course module id.
     * @param int $allocid Allocation id.
     * @return array
     */
    public static function execute(int $cmid, int $allocid): array {
        global $DB, $USER;

        ['cmid' => $cmid, 'allocid' => $allocid] = self::validate_parameters(
            self::execute_parameters(),
            ['cmid' => $cmid, 'allocid' => $allocid]
        );
        [, $cm] = get_course_and_cm_from_cmid($cmid, 'peerreview');
        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/peerreview:view', $context);

        $peerreview = $DB->get_record('peerreview', ['id' => $cm->instance], '*', MUST_EXIST);
        $service = new service($peerreview, $context);
        $alloc = $service->get_allocation($allocid);
        if ((int) $alloc->reviewerid !== (int) $USER->id) {
            throw new \moodle_exception('errornotyourreview', 'mod_peerreview');
        }
        $method = self::require_supported_form($service);

        $notice = '';
        $canedit = true;
        try {
            $service->require_can_edit($alloc, (int) $USER->id);
        } catch (\moodle_exception $e) {
            $canedit = false;
            $notice = get_string($e->errorcode, 'mod_peerreview');
        }

        $range = $service->get_range();
        $items = [];
        $criteria = $method === 'rubric' ? self::export_rubric($service, $alloc, $canedit) : [];
        foreach ($range->get_items() as $value => $label) {
            $items[] = ['value' => (int) $value, 'label' => $label];
        }
        $reviewee = \core_user::get_user($alloc->revieweeid, '*', MUST_EXIST);
        return [
            'allocid' => (int) $alloc->id,
            'reviewee' => fullname($reviewee),
            'submitted' => (int) $alloc->status === manager::STATUS_SUBMITTED,
            'canedit' => $canedit,
            'notice' => $notice,
            'isscale' => $range->is_scale(),
            'max' => $range->is_scale() ? count($items) : $range->max(),
            'items' => $items,
            'method' => $method,
            'candraft' => $method !== '' && $canedit && (int) $alloc->status !== manager::STATUS_SUBMITTED
                && $service->supports_drafts(),
            'criteria' => $criteria,
            'score' => $alloc->grade === null ? '' : (string) (float) $alloc->grade,
            'feedback' => self::plain_comment($alloc),
        ];
    }

    /**
     * Throw when the activity uses a grading method the app cannot show (a marking guide), and say which one is used.
     *
     * @param service $service Review service.
     * @return string '' for the simple points or scale form, 'rubric' for a rubric.
     * @throws \moodle_exception
     */
    public static function require_supported_form(service $service): string {
        $controller = $service->get_controller();
        if ($controller === null) {
            return '';
        }
        if (!$controller instanceof \gradingform_rubric_controller) {
            throw new \moodle_exception('errormobileadvanced', 'mod_peerreview');
        }
        return 'rubric';
    }

    /**
     * The criteria of the rubric with the levels and what this review has chosen so far.
     *
     * The submitted instance is shown when the review cannot be changed, like review.php does.
     *
     * @param service $service Review service.
     * @param \stdClass $alloc Allocation record.
     * @param bool $canedit Whether the reviewer can change the review.
     * @return array[]
     */
    private static function export_rubric(service $service, \stdClass $alloc, bool $canedit): array {
        global $USER;

        $instance = $canedit ? $service->get_instance($alloc, (int) $USER->id) : $service->get_submitted_instance($alloc);
        $definition = $service->get_controller()->get_definition();
        $filling = $instance instanceof \gradingform_rubric_instance ? $instance->get_rubric_filling()['criteria'] : [];
        $criteria = [];
        foreach ($definition->rubric_criteria as $id => $criterion) {
            $levels = [];
            foreach ($criterion['levels'] as $levelid => $level) {
                $levels[] = [
                    'id' => (int) $levelid,
                    'score' => (float) $level['score'],
                    'definition' => trim(html_to_text(format_text($level['definition'], $level['definitionformat']), 0, false)),
                ];
            }
            $criteria[] = [
                'id' => (int) $id,
                'description' => trim(html_to_text(
                    format_text($criterion['description'], $criterion['descriptionformat']),
                    0,
                    false
                )),
                'levels' => $levels,
                'levelid' => (int) ($filling[$id]['levelid'] ?? 0),
                'remark' => (string) ($filling[$id]['remark'] ?? ''),
            ];
        }
        return $criteria;
    }

    /**
     * The overall comment as plain text, which is what the app edits (comments written in the browser are HTML).
     *
     * @param \stdClass $alloc Allocation record.
     * @return string
     */
    private static function plain_comment(\stdClass $alloc): string {
        $text = (string) $alloc->feedback;
        if ((int) $alloc->feedbackformat === FORMAT_PLAIN || trim($text) === '') {
            return $text;
        }
        return trim(html_to_text($text, 0, false));
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'allocid' => new external_value(PARAM_INT, 'Allocation id'),
            'reviewee' => new external_value(PARAM_TEXT, 'Full name of the person reviewed'),
            'submitted' => new external_value(PARAM_BOOL, 'The review is submitted'),
            'canedit' => new external_value(PARAM_BOOL, 'The review can be changed now'),
            'notice' => new external_value(PARAM_TEXT, 'Why it cannot be changed, empty when it can'),
            'isscale' => new external_value(PARAM_BOOL, 'The grade is a scale'),
            'max' => new external_value(PARAM_INT, 'Maximum points, or the number of scale items'),
            'items' => new external_multiple_structure(new external_single_structure([
                'value' => new external_value(PARAM_INT, 'Position of the scale item'),
                'label' => new external_value(PARAM_TEXT, 'Name of the scale item'),
            ]), 'Scale items, empty for points'),
            'method' => new external_value(PARAM_ALPHA, 'Grading method: empty for the simple form, rubric'),
            'candraft' => new external_value(PARAM_BOOL, 'A draft can be saved'),
            'criteria' => new external_multiple_structure(new external_single_structure([
                'id' => new external_value(PARAM_INT, 'Criterion id'),
                'description' => new external_value(PARAM_TEXT, 'Criterion text'),
                'levels' => new external_multiple_structure(new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'Level id'),
                    'score' => new external_value(PARAM_FLOAT, 'Points of the level'),
                    'definition' => new external_value(PARAM_TEXT, 'Level text'),
                ])),
                'levelid' => new external_value(PARAM_INT, 'Chosen level, 0 when none'),
                'remark' => new external_value(PARAM_TEXT, 'Remark of the reviewer'),
            ]), 'Rubric criteria, empty for the simple form'),
            'score' => new external_value(PARAM_RAW, 'Score so far, empty when none'),
            'feedback' => new external_value(PARAM_RAW, 'Overall comment as plain text'),
        ]);
    }
}
