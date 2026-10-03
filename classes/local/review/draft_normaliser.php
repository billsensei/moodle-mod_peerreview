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
 * Reducing a partly filled grading form to what can be stored as a draft.
 *
 * @package    mod_peerreview
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_peerreview\local\review;

/**
 * Keeps, from a partly filled rubric or marking guide, only the criteria the grading method can store.
 *
 * Split out of the review service.
 *
 * @package    mod_peerreview
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class draft_normaliser {
    /**
     * Reduce a partly filled form to what the grading method can store.
     *
     * Rubric: criteria with a chosen level or a remark. Marking guide: criteria with a valid score (the score column is
     * mandatory, so a remark without a score cannot be kept in a draft).
     *
     * @param \gradingform_instance $instance The instance.
     * @param array $value Submitted advancedgrading value.
     * @return array Value with only storable criteria.
     */
    public function normalise(\gradingform_instance $instance, array $value): array {
        $criteria = [];
        foreach ((array) ($value['criteria'] ?? []) as $criterionid => $record) {
            $remark = trim((string) ($record['remark'] ?? ''));
            $kept = $instance instanceof \gradingform_rubric_instance
                ? $this->rubric_criterion($record, $remark)
                : $this->guide_criterion($instance, $criterionid, $record, $remark);
            if ($kept !== null) {
                $criteria[$criterionid] = $kept;
            }
        }
        return ['criteria' => $criteria];
    }

    /**
     * A rubric criterion as it can be stored: kept when a level was chosen or a remark written.
     *
     * @param mixed $record Submitted criterion (levelid, remark).
     * @param string $remark The trimmed remark.
     * @return array|null The value to store, null to leave the criterion out.
     */
    private function rubric_criterion($record, string $remark): ?array {
        $levelid = isset($record['levelid']) && is_numeric($record['levelid']) ? (int) $record['levelid'] : null;
        return $levelid !== null || $remark !== '' ? ['levelid' => $levelid, 'remark' => $remark] : null;
    }

    /**
     * A marking guide criterion as it can be stored: kept when it has a score between 0 and the criterion maximum.
     *
     * @param \gradingform_instance $instance The instance.
     * @param mixed $criterionid Id of the criterion.
     * @param mixed $record Submitted criterion (score, remark).
     * @param string $remark The trimmed remark.
     * @return array|null The value to store, null to leave the criterion out.
     */
    private function guide_criterion(\gradingform_instance $instance, $criterionid, $record, string $remark): ?array {
        $definition = $instance->get_controller()->get_definition();
        $max = $definition->guide_criteria[$criterionid]['maxscore'] ?? null;
        $score = $record['score'] ?? '';
        if ($max !== null && is_numeric($score) && $score >= 0 && $score <= $max) {
            return ['score' => $score, 'remark' => $remark];
        }
        return null;
    }
}
