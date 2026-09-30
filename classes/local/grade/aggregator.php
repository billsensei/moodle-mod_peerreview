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
 * Aggregation of received reviews into one grade per student.
 *
 * @package    mod_peerreview
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_peerreview\local\grade;

use mod_peerreview\local\allocation\manager;

/**
 * Computes the received grade: mean or median of submitted peer reviews, or the teacher's override.
 *
 * Self-reviews are stored and shown but never counted. Students with no counted review get no grade (null, not zero).
 *
 * @package    mod_peerreview
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class aggregator {
    /** @var int Arithmetic mean. */
    public const MEAN = 0;

    /** @var int Median (mean of the two middle values for an even count). */
    public const MEDIAN = 1;

    /**
     * Constructor.
     *
     * @param \stdClass $peerreview Activity record.
     */
    public function __construct(
        /** @var \stdClass Activity record. */
        private readonly \stdClass $peerreview
    ) {
    }

    /**
     * Combine grades.
     *
     * @param float[] $grades Grades to combine.
     * @param int $method self::MEAN or self::MEDIAN.
     * @return float|null Null when there are no grades.
     */
    public static function aggregate(array $grades, int $method): ?float {
        $grades = array_values($grades);
        $count = count($grades);
        if ($count === 0) {
            return null;
        }
        if ($method === self::MEDIAN) {
            sort($grades);
            $middle = intdiv($count, 2);
            return $count % 2 ? (float) $grades[$middle] : ($grades[$middle - 1] + $grades[$middle]) / 2;
        }
        return array_sum($grades) / $count;
    }

    /**
     * Submitted, non-self review grades per reviewee.
     *
     * @param int[] $userids Reviewees to include, empty for all.
     * @return float[][] userid => grades.
     */
    public function get_received_grades(array $userids = []): array {
        global $DB;

        $select = 'peerreviewid = :pr AND status = :status AND reviewerid <> revieweeid AND grade IS NOT NULL';
        $params = ['pr' => $this->peerreview->id, 'status' => manager::STATUS_SUBMITTED];
        if ($userids) {
            [$insql, $inparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
            $select .= " AND revieweeid $insql";
            $params += $inparams;
        }
        $grades = [];
        $rs = $DB->get_recordset_select('peerreview_alloc', $select, $params, 'id', 'id, revieweeid, grade');
        foreach ($rs as $row) {
            $grades[$row->revieweeid][] = (float) $row->grade;
        }
        $rs->close();
        return $grades;
    }

    /**
     * Teacher overrides.
     *
     * @param int[] $userids Users to include, empty for all.
     * @return \stdClass[] userid => override record.
     */
    public function get_overrides(array $userids = []): array {
        global $DB;

        $conditions = ['peerreviewid' => $this->peerreview->id];
        $records = $DB->get_records('peerreview_override', $conditions, '', 'userid, id, grade, note, overriddenby, timemodified');
        return $userids ? array_intersect_key($records, array_flip($userids)) : $records;
    }

    /**
     * Received grade per user (override wins over the aggregate).
     *
     * @param int[] $userids Users to include, empty for all users that have a review or an override.
     * @return array userid => ['grade' => float|null, 'overridden' => bool, 'count' => int].
     */
    public function get_grades(array $userids = []): array {
        $received = $this->get_received_grades($userids);
        $overrides = $this->get_overrides($userids);
        $result = [];
        foreach (array_unique(array_merge(array_keys($received), array_keys($overrides), $userids)) as $userid) {
            $count = count($received[$userid] ?? []);
            if (isset($overrides[$userid])) {
                $grade = (float) $overrides[$userid]->grade;
            } else {
                $grade = self::aggregate($received[$userid] ?? [], (int) $this->peerreview->aggregation);
            }
            $result[$userid] = ['grade' => $grade, 'overridden' => isset($overrides[$userid]), 'count' => $count];
        }
        return $result;
    }
}
