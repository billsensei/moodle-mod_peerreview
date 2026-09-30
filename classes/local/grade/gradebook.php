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
 * Grades in the format the gradebook expects.
 *
 * Modelled on mod/assign/lib.php (assign_update_grades) and mod/workshop/lib.php (workshop_update_grades).
 *
 * @package    mod_peerreview
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_peerreview\local\grade;

use mod_peerreview\grades\gradeitems;
use mod_peerreview\local\allocation\manager;

/**
 * Builds the received and participation grades for the gradebook.
 *
 * Received grade: mean or median of submitted peer reviews (or the teacher override). A student with no counted review
 * and no override gets no grade at all (null), not zero.
 * Participation grade: the percentage of assigned reviews the student submitted, scaled to the maximum.
 *
 * @package    mod_peerreview
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class gradebook {
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
     * Received grades.
     *
     * @param int[] $userids Users to include (all users with a grade or a review when empty).
     * @return \stdClass[] userid => grade object for grade_update(): userid, rawgrade (float|null), dategraded.
     */
    public function get_received_grades(array $userids = []): array {
        $grades = [];
        foreach ((new aggregator($this->peerreview))->get_grades($userids) as $userid => $result) {
            $grades[$userid] = (object) [
                'userid' => $userid,
                'rawgrade' => $result['grade'],
                'dategraded' => time(),
            ];
        }
        return $grades;
    }

    /**
     * Participation grades: submitted / assigned as a percentage of the maximum participation grade.
     *
     * @param int[] $userids Users to include (everyone with an assigned review when empty).
     * @return \stdClass[] userid => grade object; rawgrade is null when nothing is assigned to the student.
     */
    public function get_participation_grades(array $userids = []): array {
        global $DB;

        if ($this->peerreview->gradeparticipation <= 0) {
            return [];
        }
        $select = 'peerreviewid = :pr';
        $params = ['pr' => $this->peerreview->id];
        if ($userids) {
            [$insql, $inparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
            $select .= " AND reviewerid $insql";
            $params += $inparams;
        }
        $assigned = [];
        $done = [];
        $rs = $DB->get_recordset_select('peerreview_alloc', $select, $params, 'id', 'id, reviewerid, status');
        foreach ($rs as $row) {
            $assigned[$row->reviewerid] = ($assigned[$row->reviewerid] ?? 0) + 1;
            $submitted = (int) $row->status === manager::STATUS_SUBMITTED ? 1 : 0;
            $done[$row->reviewerid] = ($done[$row->reviewerid] ?? 0) + $submitted;
        }
        $rs->close();

        $grades = [];
        foreach (array_unique(array_merge(array_keys($assigned), $userids)) as $userid) {
            $total = $assigned[$userid] ?? 0;
            $grades[$userid] = (object) [
                'userid' => $userid,
                'rawgrade' => $total ? $this->peerreview->gradeparticipation * ($done[$userid] ?? 0) / $total : null,
                'dategraded' => time(),
            ];
        }
        return $grades;
    }

    /**
     * Send the grades to the gradebook.
     *
     * @param int $userid One user, or 0 for every student in the activity.
     */
    public function push(int $userid = 0): void {
        $userids = $userid ? [$userid] : $this->get_student_ids();
        $received = $this->get_received_grades($userids);
        foreach ($userids as $id) {
            // Everyone we were asked about gets an entry, so a grade that no longer applies is cleared.
            $received[$id] ??= (object) ['userid' => $id, 'rawgrade' => null, 'dategraded' => time()];
        }
        $this->send(gradeitems::ITEM_RECEIVED, $received, $this->peerreview->grade);

        if ($this->peerreview->gradeparticipation > 0) {
            $participation = $this->get_participation_grades($userids);
            foreach ($userids as $id) {
                $participation[$id] ??= (object) ['userid' => $id, 'rawgrade' => null, 'dategraded' => time()];
            }
            $this->send(gradeitems::ITEM_PARTICIPATION, $participation, $this->peerreview->gradeparticipation);
        }
    }

    /**
     * Everyone who takes part: active students who hold the review capability.
     *
     * @return int[]
     */
    private function get_student_ids(): array {
        $cm = get_coursemodule_from_instance('peerreview', $this->peerreview->id, $this->peerreview->course, false, MUST_EXIST);
        $manager = new manager($this->peerreview, $cm, \context_module::instance($cm->id));
        return array_map('intval', array_keys($manager->get_students()));
    }

    /**
     * One grade_update() call.
     *
     * @param int $itemnumber Grade item number.
     * @param \stdClass[] $grades Grade objects.
     * @param int $grademax Maximum of the item.
     */
    private function send(int $itemnumber, array $grades, int $grademax): void {
        grade_update(
            'mod/peerreview',
            $this->peerreview->course,
            'mod',
            'peerreview',
            $this->peerreview->id,
            $itemnumber,
            $grades,
            ['gradetype' => GRADE_TYPE_VALUE, 'grademax' => $grademax, 'grademin' => 0]
        );
    }
}
