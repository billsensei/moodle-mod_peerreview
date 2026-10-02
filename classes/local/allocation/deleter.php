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
 * Deleting allocations, with the protection of started reviews.
 *
 * @package    mod_peerreview
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_peerreview\local\allocation;

/**
 * Deletes allocations of one peer review activity and cleans up what hangs off them.
 *
 * Split out of the allocation manager, which still offers delete() and count_started() and hands them to this class.
 *
 * @package    mod_peerreview
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class deleter {
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
    }

    /**
     * How many of the given allocations have a saved or submitted review.
     *
     * @param int[] $ids Allocation ids.
     * @return int
     */
    public function count_started(array $ids): int {
        global $DB;

        if (!$ids) {
            return 0;
        }
        [$insql, $params] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED);
        return $DB->count_records_select(
            'peerreview_alloc',
            "peerreviewid = :pr AND status > :new AND id $insql",
            $params + ['pr' => $this->peerreview->id, 'new' => manager::STATUS_NEW]
        );
    }

    /**
     * Delete allocations. Started reviews are never deleted unless the caller says it has confirmed.
     *
     * @param int[] $ids Allocation ids (ids of other activities are ignored).
     * @param bool $confirmedstarted The teacher confirmed deleting saved or submitted reviews.
     * @return int Number deleted.
     * @throws \moodle_exception If started reviews are included and not confirmed.
     */
    public function delete(array $ids, bool $confirmedstarted): int {
        global $DB;

        if (!$ids) {
            return 0;
        }
        if (!$confirmedstarted && $this->count_started($ids) > 0) {
            throw new \moodle_exception('errorstartedreviews', 'mod_peerreview');
        }
        [$insql, $params] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED);
        $records = $DB->get_records_select(
            'peerreview_alloc',
            "peerreviewid = :pr AND id $insql",
            $params + ['pr' => $this->peerreview->id]
        );
        if ($records) {
            // Remove the advanced grading instances (rubric/guide fillings) of these allocations.
            \core_grading\privacy\provider::delete_data_for_instances($this->context, array_keys($records));
        }
        foreach ($records as $record) {
            $DB->delete_records('peerreview_alloc', ['id' => $record->id]);
            \mod_peerreview\event\allocation_deleted::create([
                'objectid' => $record->id,
                'context' => $this->context,
                'relateduserid' => $record->revieweeid,
                'other' => ['reviewerid' => $record->reviewerid],
            ])->trigger();
        }
        // A reviewer whose remaining reviews are all submitted may now be complete (or has no work left at all).
        \mod_peerreview\local\completion::update($this->peerreview, array_column((array) $records, 'reviewerid'));
        return count($records);
    }
}
