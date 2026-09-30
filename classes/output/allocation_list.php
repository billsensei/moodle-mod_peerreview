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
 * Renderable: the table of current allocations with delete checkboxes.
 *
 * @package    mod_peerreview
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_peerreview\output;

use mod_peerreview\local\allocation\manager;

/**
 * The current allocations.
 *
 * @package    mod_peerreview
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class allocation_list implements \renderable, \templatable {
    /**
     * Constructor.
     *
     * @param int $cmid Course module id.
     * @param \stdClass[] $allocations Allocation records.
     * @param \stdClass[] $users userid => user record (must include name fields).
     */
    public function __construct(
        /** @var int Course module id. */
        private readonly int $cmid,
        /** @var \stdClass[] Allocation records. */
        private readonly array $allocations,
        /** @var \stdClass[] User records. */
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
        $rows = [];
        foreach ($this->allocations as $alloc) {
            $reviewer = $this->users[$alloc->reviewerid] ?? null;
            $reviewee = $this->users[$alloc->revieweeid] ?? null;
            $rows[] = [
                'id' => $alloc->id,
                'reviewer' => $reviewer ? fullname($reviewer) : get_string('unknownuser', 'mod_peerreview'),
                'reviewee' => $reviewee ? fullname($reviewee) : get_string('unknownuser', 'mod_peerreview'),
                'label' => get_string('deletepair', 'mod_peerreview', (object) [
                    'reviewer' => $reviewer ? fullname($reviewer) : '',
                    'reviewee' => $reviewee ? fullname($reviewee) : '',
                ]),
                'status' => $statuses[$alloc->status],
                'started' => (int) $alloc->status > manager::STATUS_NEW,
            ];
        }
        usort($rows, static fn($a, $b) => [$a['reviewer'], $a['reviewee']] <=> [$b['reviewer'], $b['reviewee']]);

        return (object) [
            'actionurl' => (new \moodle_url('/mod/peerreview/allocate.php'))->out(false),
            'cmid' => $this->cmid,
            'sesskey' => sesskey(),
            'rows' => $rows,
            'hasrows' => !empty($rows),
            'count' => count($rows),
        ];
    }
}
