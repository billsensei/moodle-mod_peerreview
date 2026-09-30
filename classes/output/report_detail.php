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
 * Renderable: one student's reviews, given and received (teacher drill-down).
 *
 * @package    mod_peerreview
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_peerreview\output;

use mod_peerreview\local\allocation\manager;

/**
 * Drill-down for one student: teachers see real names and can open each review read-only.
 *
 * @package    mod_peerreview
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report_detail implements \renderable, \templatable {
    /**
     * Constructor.
     *
     * @param int $cmid Course module id.
     * @param \stdClass $user The student.
     * @param \stdClass[] $given Allocations where the student reviews.
     * @param \stdClass[] $received Allocations where the student is reviewed.
     * @param \stdClass[] $users userid => name fields, for both sides.
     */
    public function __construct(
        /** @var int Course module id. */
        private readonly int $cmid,
        /** @var \stdClass The student. */
        private readonly \stdClass $user,
        /** @var \stdClass[] Given. */
        private readonly array $given,
        /** @var \stdClass[] Received. */
        private readonly array $received,
        /** @var \stdClass[] Users. */
        private readonly array $users
    ) {
    }

    /**
     * Export for the template.
     *
     * @param \renderer_base $output
     * @return \stdClass
     */
    public function export_for_template(\renderer_base $output): \stdClass {
        $statuses = [
            manager::STATUS_NEW => get_string('statusnew', 'mod_peerreview'),
            manager::STATUS_DRAFT => get_string('statusdraft', 'mod_peerreview'),
            manager::STATUS_SUBMITTED => get_string('statussubmitted', 'mod_peerreview'),
        ];
        $build = function (array $allocs, string $otherfield) use ($statuses): array {
            $rows = [];
            foreach ($allocs as $alloc) {
                $other = $this->users[$alloc->$otherfield] ?? null;
                $rows[] = [
                    'name' => $other ? fullname($other) : get_string('unknownuser', 'mod_peerreview'),
                    'status' => $statuses[$alloc->status],
                    'grade' => $alloc->grade === null || (int) $alloc->status !== manager::STATUS_SUBMITTED
                        ? '' : format_float((float) $alloc->grade, 2),
                    'url' => (new \moodle_url('/mod/peerreview/review.php', [
                        'id' => $this->cmid,
                        'alloc' => $alloc->id,
                    ]))->out(false),
                    'viewable' => (int) $alloc->status > manager::STATUS_NEW,
                ];
            }
            usort($rows, static fn($a, $b) => $a['name'] <=> $b['name']);
            return $rows;
        };
        return (object) [
            'name' => fullname($this->user),
            'picture' => $output->user_picture($this->user, ['size' => 64, 'link' => false, 'includefullname' => false]),
            'given' => $build($this->given, 'revieweeid'),
            'hasgiven' => !empty($this->given),
            'received' => $build($this->received, 'reviewerid'),
            'hasreceived' => !empty($this->received),
            'backurl' => (new \moodle_url('/mod/peerreview/report.php', ['id' => $this->cmid]))->out(false),
        ];
    }
}
