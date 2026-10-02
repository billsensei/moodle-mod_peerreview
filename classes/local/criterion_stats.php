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
 * Class statistics: the average score on each criterion of the rubric or marking guide.
 *
 * @package    mod_peerreview
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_peerreview\local;

use mod_peerreview\local\allocation\manager;
use mod_peerreview\local\review\service;

/**
 * Averages per criterion over the submitted reviews, read the same way as the export (see export::get_criterion_values()).
 *
 * @package    mod_peerreview
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class criterion_stats {
    /** @var \gradingform_controller|null Active advanced grading controller. */
    private ?\gradingform_controller $controller;

    /**
     * Constructor.
     *
     * @param \stdClass $peerreview Activity record.
     * @param \context_module $context Module context.
     * @param int[]|null $visibleusers Count only reviews of these reviewees, null for no restriction.
     */
    public function __construct(
        /** @var \stdClass Activity record. */
        private readonly \stdClass $peerreview,
        /** @var \context_module Module context. */
        private readonly \context_module $context,
        /** @var int[]|null Only reviews of these reviewees, null for all (see group_access::visible_userids). */
        private readonly ?array $visibleusers = null
    ) {
        $this->controller = (new service($peerreview, $context))->get_controller();
    }

    /**
     * Criteria of the active method in display order.
     *
     * @return array[] id => ['name' => string, 'max' => float highest score the criterion can give].
     */
    private function get_criteria(): array {
        $criteria = [];
        if ($this->controller instanceof \gradingform_rubric_controller) {
            foreach ($this->controller->get_definition()->rubric_criteria as $id => $criterion) {
                $scores = array_map(static fn($level) => (float) $level['score'], $criterion['levels']);
                $criteria[$id] = ['name' => $criterion['description'], 'max' => $scores ? max($scores) : 0.0];
            }
        } else if ($this->controller instanceof \gradingform_guide_controller) {
            foreach ($this->controller->get_definition()->guide_criteria as $id => $criterion) {
                $criteria[$id] = ['name' => $criterion['shortname'], 'max' => (float) $criterion['maxscore']];
            }
        }
        foreach ($criteria as $id => $criterion) {
            $criteria[$id]['name'] = shorten_text(trim(strip_tags($criterion['name'])), 60);
        }
        return $criteria;
    }

    /**
     * Scores given on each criterion in one submitted review.
     *
     * A rubric criterion with no level chosen has no score and is left out.
     *
     * @param \stdClass $alloc Allocation.
     * @return float[] Criterion id => score.
     */
    private function get_scores(\stdClass $alloc): array {
        $scores = [];
        $instance = $this->controller?->get_current_instance($alloc->reviewerid, $alloc->id);
        if ($instance instanceof \gradingform_rubric_instance) {
            $definition = $this->controller->get_definition();
            foreach ($instance->get_rubric_filling(true)['criteria'] as $id => $filling) {
                $level = $definition->rubric_criteria[$id]['levels'][$filling['levelid'] ?? 0] ?? null;
                if ($level) {
                    $scores[$id] = (float) $level['score'];
                }
            }
        } else if ($instance instanceof \gradingform_guide_instance) {
            foreach ($instance->get_guide_filling(true)['criteria'] as $id => $filling) {
                $scores[$id] = (float) $filling['score'];
            }
        }
        return $scores;
    }

    /**
     * Average score per criterion over the submitted reviews.
     *
     * Empty when the activity has no rubric or marking guide. A criterion nobody has scored yet has count 0 and no average.
     *
     * @return array[] One per criterion: ['name' => string, 'max' => float, 'count' => int, 'average' => float|null].
     */
    public function get_statistics(): array {
        global $DB;

        $criteria = $this->get_criteria();
        if (!$criteria) {
            return [];
        }
        $sums = array_fill_keys(array_keys($criteria), 0.0);
        $counts = array_fill_keys(array_keys($criteria), 0);
        $rs = $DB->get_recordset('peerreview_alloc', [
            'peerreviewid' => $this->peerreview->id,
            'status' => manager::STATUS_SUBMITTED,
        ], 'id');
        foreach ($rs as $alloc) {
            if ($this->visibleusers !== null && !in_array((int) $alloc->revieweeid, $this->visibleusers, true)) {
                continue;
            }
            foreach ($this->get_scores($alloc) as $id => $score) {
                if (isset($counts[$id])) {
                    $sums[$id] += $score;
                    $counts[$id]++;
                }
            }
        }
        $rs->close();

        $stats = [];
        foreach ($criteria as $id => $criterion) {
            $stats[] = $criterion + [
                'count' => $counts[$id],
                'average' => $counts[$id] ? $sums[$id] / $counts[$id] : null,
            ];
        }
        return $stats;
    }
}
