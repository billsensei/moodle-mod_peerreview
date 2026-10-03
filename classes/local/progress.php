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
 * Progress figures for the teacher report and live progress.
 *
 * @package    mod_peerreview
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_peerreview\local;

use mod_peerreview\local\allocation\manager;
use mod_peerreview\local\grade\aggregator;

/**
 * Per-student progress: reviews given and received, aggregated grade, participation.
 *
 * Only allocations where both people are current, active students are counted, so suspended or unenrolled users do not
 * distort the figures.
 *
 * @package    mod_peerreview
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class progress {
    /**
     * Constructor.
     *
     * @param \stdClass $peerreview Activity record.
     * @param manager $manager Allocation manager (source of eligible students).
     */
    public function __construct(
        /** @var \stdClass Activity record. */
        private readonly \stdClass $peerreview,
        /** @var manager Allocation manager. */
        private readonly manager $manager
    ) {
    }

    /**
     * One row per student.
     *
     * @param int $groupid Only members of this group (0 for everyone, -1 for nobody).
     * @return \stdClass[] userid => row with user, given_done, given_total, received_done, received_total, grade,
     *                     overridden, participation (percent or null).
     */
    public function get_rows(int $groupid = 0): array {
        if ($groupid < 0) {
            return [];
        }
        $students = $this->manager->get_students();
        $counts = $this->count_reviews($students);
        $inscope = $this->users_in_scope(array_keys($students), $groupid);
        $grades = (new aggregator($this->peerreview))->get_grades($inscope);

        $rows = [];
        foreach ($inscope as $userid) {
            $rows[$userid] = $this->build_row($userid, $students[$userid], $counts[$userid], $grades[$userid] ?? []);
        }
        return $rows;
    }

    /**
     * Reviews given and received by each student, counting only pairs of eligible students.
     *
     * A self-review counts as work given and done, but is not "received".
     *
     * @param \stdClass[] $students Eligible students, userid => user record.
     * @return int[][] userid => ['gd' given done, 'gt' given total, 'rd' received done, 'rt' received total].
     */
    private function count_reviews(array $students): array {
        $counts = [];
        foreach (array_keys($students) as $userid) {
            $counts[$userid] = ['gd' => 0, 'gt' => 0, 'rd' => 0, 'rt' => 0];
        }
        foreach ($this->manager->get_allocations() as $alloc) {
            if (!isset($students[$alloc->reviewerid], $students[$alloc->revieweeid])) {
                continue;
            }
            $done = (int) $alloc->status === manager::STATUS_SUBMITTED ? 1 : 0;
            $counts[$alloc->reviewerid]['gt']++;
            $counts[$alloc->reviewerid]['gd'] += $done;
            if ($alloc->reviewerid !== $alloc->revieweeid) {
                $counts[$alloc->revieweeid]['rt']++;
                $counts[$alloc->revieweeid]['rd'] += $done;
            }
        }
        return $counts;
    }

    /**
     * The students a report covers.
     *
     * @param int[] $studentids Ids of all eligible students.
     * @param int $groupid Only members of this group, 0 for everyone.
     * @return int[]
     */
    private function users_in_scope(array $studentids, int $groupid): array {
        if (!$groupid) {
            return $studentids;
        }
        $members = array_keys(groups_get_members($groupid, 'u.id'));
        return array_values(array_intersect($studentids, $members));
    }

    /**
     * One row of the report.
     *
     * @param int $userid The student.
     * @param \stdClass $user The student's user record.
     * @param int[] $count Counts from count_reviews() for this student.
     * @param array $grade Aggregated grade of this student: 'grade' and 'overridden' (empty when there is none).
     * @return \stdClass
     */
    private function build_row(int $userid, \stdClass $user, array $count, array $grade): \stdClass {
        return (object) [
            'userid' => $userid,
            'user' => $user,
            'given_done' => $count['gd'],
            'given_total' => $count['gt'],
            'received_done' => $count['rd'],
            'received_total' => $count['rt'],
            'grade' => $grade['grade'] ?? null,
            'overridden' => $grade['overridden'] ?? false,
            'participation' => $count['gt'] ? (int) round(100 * $count['gd'] / $count['gt']) : null,
        ];
    }

    /**
     * Totals over rows.
     *
     * @param \stdClass[] $rows Rows from get_rows().
     * @return \stdClass students, allocations, submitted.
     */
    public function summarise(array $rows): \stdClass {
        $summary = (object) ['students' => count($rows), 'allocations' => 0, 'submitted' => 0];
        foreach ($rows as $row) {
            $summary->allocations += $row->given_total;
            $summary->submitted += $row->given_done;
        }
        return $summary;
    }
}
