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
 * What a student sees: reviews to do and feedback received.
 *
 * @package    mod_peerreview
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_peerreview\local;

use mod_peerreview\local\allocation\manager;
use mod_peerreview\local\grade\aggregator;

/**
 * Data behind the student page.
 *
 * Anonymity is enforced here: when the activity is anonymous, received reviews never carry the reviewer's identity.
 *
 * @package    mod_peerreview
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class student_data {
    /**
     * Constructor.
     *
     * @param \stdClass $peerreview Activity record.
     * @param manager $manager Allocation manager.
     */
    public function __construct(
        /** @var \stdClass Activity record. */
        private readonly \stdClass $peerreview,
        /** @var manager Allocation manager. */
        private readonly manager $manager
    ) {
    }

    /**
     * Reviews the user has to do.
     *
     * @param int $userid The reviewer.
     * @return \stdClass[] Each: id, user (reviewee record), status.
     */
    public function get_todo(int $userid): array {
        $students = $this->manager->get_students();
        $todo = [];
        foreach ($this->manager->get_allocations() as $alloc) {
            if ((int) $alloc->reviewerid === $userid && isset($students[$alloc->revieweeid])) {
                $todo[] = (object) ['id' => $alloc->id, 'user' => $students[$alloc->revieweeid],
                    'status' => (int) $alloc->status];
            }
        }
        usort($todo, static fn($a, $b) => [$a->user->lastname, $a->user->firstname] <=> [$b->user->lastname, $b->user->firstname]);
        return $todo;
    }

    /**
     * Submitted reviews of the user, with the reviewer hidden when the activity is anonymous.
     *
     * @param int $userid The reviewee.
     * @return \stdClass[] Each: id, grade, feedback, feedbackformat, isself, reviewer (user record or null when hidden).
     */
    public function get_received(int $userid): array {
        global $DB;

        $received = [];
        $records = $DB->get_records('peerreview_alloc', [
            'peerreviewid' => $this->peerreview->id,
            'revieweeid' => $userid,
            'status' => manager::STATUS_SUBMITTED,
        ]);
        $students = $this->manager->get_students();
        foreach ($records as $alloc) {
            $isself = (int) $alloc->reviewerid === $userid;
            $reviewer = null;
            if ($isself || empty($this->peerreview->anonymous)) {
                $reviewer = $students[$alloc->reviewerid] ?? \core_user::get_user($alloc->reviewerid);
            }
            $received[] = (object) [
                'id' => $alloc->id,
                'grade' => $alloc->grade,
                'feedback' => $alloc->feedback,
                'feedbackformat' => $alloc->feedbackformat,
                'isself' => $isself,
                'reviewer' => $reviewer ?: null,
            ];
        }
        // A stable order unrelated to who reviewed, so the order cannot reveal reviewers.
        usort($received, static fn($a, $b) => crc32($a->id . '_' . $userid) <=> crc32($b->id . '_' . $userid));
        return $received;
    }

    /**
     * The user's received grade.
     *
     * @param int $userid The reviewee.
     * @return float|null Null when nothing counts yet.
     */
    public function get_grade(int $userid): ?float {
        return (new aggregator($this->peerreview))->get_grades([$userid])[$userid]['grade'] ?? null;
    }

    /**
     * The user's self-assessment next to what their peers gave.
     *
     * @param int $userid The reviewee.
     * @return \stdClass|null Null unless the user has a submitted self-review and at least one submitted peer review.
     *                        Otherwise: self (grade), peers (grade, combined as the activity combines them), count (peer reviews).
     */
    public function get_self_comparison(int $userid): ?\stdClass {
        global $DB;

        $self = $DB->get_record('peerreview_alloc', [
            'peerreviewid' => $this->peerreview->id,
            'reviewerid' => $userid,
            'revieweeid' => $userid,
            'status' => manager::STATUS_SUBMITTED,
        ]);
        if (!$self || $self->grade === null) {
            return null;
        }
        $peers = (new aggregator($this->peerreview))->get_received_grades([$userid])[$userid] ?? [];
        if (!$peers) {
            return null;
        }
        return (object) [
            'self' => (float) $self->grade,
            'peers' => aggregator::aggregate($peers, (int) $this->peerreview->aggregation),
            'count' => count($peers),
        ];
    }
}
