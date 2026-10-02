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
 * Import and export of allocations as CSV.
 *
 * @package    mod_peerreview
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_peerreview\local\allocation;

/**
 * CSV import (validation into a proposal) and export of the allocations of one activity.
 *
 * Split out of the allocation manager, which it still asks for the eligible students and the existing pairs.
 *
 * @package    mod_peerreview
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class csv {
    /**
     * Constructor.
     *
     * @param \stdClass $peerreview Activity record.
     * @param manager $manager Allocation manager of the same activity.
     */
    public function __construct(
        /** @var \stdClass Activity record. */
        private readonly \stdClass $peerreview,
        /** @var manager Allocation manager of the same activity. */
        private readonly manager $manager
    ) {
    }

    /**
     * Resolve a CSV cell (username or email) to an eligible student id.
     *
     * @param string $value
     * @return int|null
     */
    private function find_student(string $value): ?int {
        $value = \core_text::strtolower(trim($value));
        foreach ($this->manager->get_students() as $student) {
            if (\core_text::strtolower($student->username) === $value || \core_text::strtolower($student->email) === $value) {
                return (int) $student->id;
            }
        }
        return null;
    }

    /**
     * Validate CSV rows (reviewer, reviewee) and turn the good ones into a proposal.
     *
     * @param string[][] $rows Each [reviewer, reviewee] cell values, keyed by line number.
     * @return array [proposal $proposal, array[] $errors each [line, lang identifier, $a], int $duplicates]
     */
    public function build_proposal(array $rows): array {
        $proposal = new proposal();
        $errors = [];
        $duplicates = 0;
        $seen = [];
        foreach ($this->manager->get_pairs() as [$reviewer, $reviewee]) {
            $seen[$reviewer . '_' . $reviewee] = true;
        }
        foreach ($rows as $line => [$reviewercell, $revieweecell]) {
            $reviewerid = $this->find_student($reviewercell);
            $revieweeid = $this->find_student($revieweecell);
            if ($reviewerid === null) {
                $errors[] = [$line, 'csverrorunknown', $reviewercell];
            } else if ($revieweeid === null) {
                $errors[] = [$line, 'csverrorunknown', $revieweecell];
            } else if ($reviewerid === $revieweeid && empty($this->peerreview->allowselfreview)) {
                $errors[] = [$line, 'errorselfnotallowed', $reviewercell];
            } else if (isset($seen[$reviewerid . '_' . $revieweeid])) {
                $duplicates++;
            } else {
                $seen[$reviewerid . '_' . $revieweeid] = true;
                $proposal->add($reviewerid, $revieweeid);
            }
        }
        return [$proposal, $errors, $duplicates];
    }

    /**
     * Rows for the CSV export.
     *
     * @return \Generator Objects with reviewer, reviewee, status.
     */
    public function export_rows(): \Generator {
        global $DB;

        $sql = "SELECT a.id, a.status, r.username AS reviewer, e.username AS reviewee
                  FROM {peerreview_alloc} a
                  JOIN {user} r ON r.id = a.reviewerid
                  JOIN {user} e ON e.id = a.revieweeid
                 WHERE a.peerreviewid = :pr
              ORDER BY r.lastname, r.firstname, e.lastname, e.firstname, a.id";
        $statuses = [
            manager::STATUS_NEW => get_string('statusnew', 'mod_peerreview'),
            manager::STATUS_DRAFT => get_string('statusdraft', 'mod_peerreview'),
            manager::STATUS_SUBMITTED => get_string('statussubmitted', 'mod_peerreview'),
        ];
        $rs = $DB->get_recordset_sql($sql, ['pr' => $this->peerreview->id]);
        foreach ($rs as $row) {
            yield (object) [
                'reviewer' => $row->reviewer,
                'reviewee' => $row->reviewee,
                'status' => $statuses[$row->status],
            ];
        }
        $rs->close();
    }
}
