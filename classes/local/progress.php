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
        $students = $this->manager->get_students();
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

        $inscope = array_keys($students);
        if ($groupid < 0) {
            return [];
        }
        if ($groupid) {
            $members = array_keys(groups_get_members($groupid, 'u.id'));
            $inscope = array_values(array_intersect($inscope, $members));
        }
        $grades = (new aggregator($this->peerreview))->get_grades($inscope);

        $rows = [];
        foreach ($inscope as $userid) {
            $c = $counts[$userid];
            $rows[$userid] = (object) [
                'userid' => $userid,
                'user' => $students[$userid],
                'given_done' => $c['gd'],
                'given_total' => $c['gt'],
                'received_done' => $c['rd'],
                'received_total' => $c['rt'],
                'grade' => $grades[$userid]['grade'] ?? null,
                'overridden' => $grades[$userid]['overridden'] ?? false,
                'participation' => $c['gt'] ? (int) round(100 * $c['gd'] / $c['gt']) : null,
            ];
        }
        return $rows;
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
