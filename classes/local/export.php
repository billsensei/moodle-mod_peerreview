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
 * Export of all reviews, including criterion-level scores for rubrics and marking guides.
 *
 * @package    mod_peerreview
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_peerreview\local;

use mod_peerreview\local\allocation\manager;
use mod_peerreview\local\review\service;

/**
 * Builds the columns and rows for the dataformat export.
 *
 * @package    mod_peerreview
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class export {
    /** @var \gradingform_controller|null Active advanced grading controller. */
    private ?\gradingform_controller $controller;

    /**
     * Constructor.
     *
     * @param \stdClass $peerreview Activity record.
     * @param \context_module $context Module context.
     */
    public function __construct(
        /** @var \stdClass Activity record. */
        private readonly \stdClass $peerreview,
        /** @var \context_module Module context. */
        private readonly \context_module $context
    ) {
        $this->controller = (new service($peerreview, $context))->get_controller();
    }

    /**
     * Criteria of the active method as id => short label.
     *
     * @return string[]
     */
    private function get_criteria(): array {
        $criteria = [];
        if ($this->controller instanceof \gradingform_rubric_controller) {
            foreach ($this->controller->get_definition()->rubric_criteria as $id => $criterion) {
                $criteria[$id] = $criterion['description'];
            }
        } else if ($this->controller instanceof \gradingform_guide_controller) {
            foreach ($this->controller->get_definition()->guide_criteria as $id => $criterion) {
                $criteria[$id] = $criterion['shortname'];
            }
        }
        return array_map(static fn($text) => shorten_text(trim(strip_tags($text)), 40), $criteria);
    }

    /**
     * Column keys and headings.
     *
     * @return string[]
     */
    public function get_columns(): array {
        $columns = [
            'reviewer' => get_string('reviewer', 'mod_peerreview'),
            'reviewee' => get_string('reviewee', 'mod_peerreview'),
            'status' => get_string('status', 'mod_peerreview'),
            'grade' => get_string('gradecol', 'mod_peerreview'),
            'submitted' => get_string('timesubmitted', 'mod_peerreview'),
            'comment' => get_string('overallcomment', 'mod_peerreview'),
        ];
        foreach ($this->get_criteria() as $id => $label) {
            $columns["c{$id}_score"] = $label . ' (' . get_string('score', 'mod_peerreview') . ')';
            $columns["c{$id}_remark"] = $label . ' (' . get_string('remark', 'mod_peerreview') . ')';
        }
        return $columns;
    }

    /**
     * Rows, one per allocation.
     *
     * @return \Generator Arrays keyed like get_columns().
     */
    public function get_rows(): \Generator {
        global $DB;

        $statuses = [
            manager::STATUS_NEW => get_string('statusnew', 'mod_peerreview'),
            manager::STATUS_DRAFT => get_string('statusdraft', 'mod_peerreview'),
            manager::STATUS_SUBMITTED => get_string('statussubmitted', 'mod_peerreview'),
        ];
        $sql = "SELECT a.*, r.firstname AS rfirst, r.lastname AS rlast, r.username AS rusername,
                       e.firstname AS efirst, e.lastname AS elast, e.username AS eusername
                  FROM {peerreview_alloc} a
                  JOIN {user} r ON r.id = a.reviewerid
                  JOIN {user} e ON e.id = a.revieweeid
                 WHERE a.peerreviewid = :pr
              ORDER BY r.lastname, r.firstname, e.lastname, e.firstname, a.id";
        $rs = $DB->get_recordset_sql($sql, ['pr' => $this->peerreview->id]);
        foreach ($rs as $alloc) {
            $row = [
                'reviewer' => $alloc->rfirst . ' ' . $alloc->rlast . ' (' . $alloc->rusername . ')',
                'reviewee' => $alloc->efirst . ' ' . $alloc->elast . ' (' . $alloc->eusername . ')',
                'status' => $statuses[$alloc->status],
                'grade' => $alloc->grade !== null && (int) $alloc->status === manager::STATUS_SUBMITTED
                    ? format_float((float) $alloc->grade, 5, false) : '',
                'submitted' => $alloc->timesubmitted ? userdate($alloc->timesubmitted, '%Y-%m-%d %H:%M') : '',
                'comment' => trim(strip_tags((string) $alloc->feedback)),
            ];
            if ((int) $alloc->status === manager::STATUS_SUBMITTED) {
                $row += $this->get_criterion_values($alloc);
            }
            yield $row;
        }
        $rs->close();
    }

    /**
     * Criterion scores and remarks of a submitted review.
     *
     * @param \stdClass $alloc Allocation.
     * @return array Keys "c{id}_score" and "c{id}_remark".
     */
    private function get_criterion_values(\stdClass $alloc): array {
        $values = [];
        $instance = $this->controller?->get_current_instance($alloc->reviewerid, $alloc->id);
        if ($instance instanceof \gradingform_rubric_instance) {
            $definition = $this->controller->get_definition();
            foreach ($instance->get_rubric_filling(true)['criteria'] as $id => $filling) {
                $level = $definition->rubric_criteria[$id]['levels'][$filling['levelid'] ?? 0] ?? null;
                $values["c{$id}_score"] = $level ? format_float((float) $level['score'], 5, false) : '';
                $values["c{$id}_remark"] = trim(strip_tags((string) ($filling['remark'] ?? '')));
            }
        } else if ($instance instanceof \gradingform_guide_instance) {
            foreach ($instance->get_guide_filling(true)['criteria'] as $id => $filling) {
                $values["c{$id}_score"] = format_float((float) $filling['score'], 5, false);
                $values["c{$id}_remark"] = trim(strip_tags((string) ($filling['remark'] ?? '')));
            }
        }
        return $values;
    }
}
