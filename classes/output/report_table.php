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
 * Renderable: the teacher report table (with live refresh controls).
 *
 * @package    mod_peerreview
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_peerreview\output;

/**
 * The progress table.
 *
 * @package    mod_peerreview
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report_table implements \renderable, \templatable {
    /** @var int Seconds between automatic refreshes. */
    public const REFRESH_SECONDS = 15;

    /**
     * Constructor.
     *
     * @param int $cmid Course module id.
     * @param int $groupid Group shown (0 all).
     * @param \stdClass[] $rows From progress::get_rows().
     * @param bool $autorefresh Whether automatic refresh is on for this user.
     * @param int $maxgrade Activity maximum grade.
     */
    public function __construct(
        /** @var int Course module id. */
        private readonly int $cmid,
        /** @var int Group id. */
        private readonly int $groupid,
        /** @var \stdClass[] Rows. */
        private readonly array $rows,
        /** @var bool Auto refresh preference. */
        private readonly bool $autorefresh,
        /** @var int Maximum grade. */
        private readonly int $maxgrade
    ) {
    }

    /**
     * Export for the template.
     *
     * @param \renderer_base $output
     * @return \stdClass
     */
    public function export_for_template(\renderer_base $output): \stdClass {
        $rows = [];
        foreach ($this->rows as $row) {
            $rows[] = [
                'userid' => $row->userid,
                'name' => fullname($row->user),
                'url' => (new \moodle_url('/mod/peerreview/report.php', ['id' => $this->cmid, 'user' => $row->userid]))->out(false),
                'given' => $row->given_done . ' / ' . $row->given_total,
                'received' => $row->received_done . ' / ' . $row->received_total,
                'grade' => $row->grade === null ? '' : format_float($row->grade, 2),
                'overridden' => (bool) $row->overridden,
                'participation' => $row->participation === null ? '' : $row->participation . '%',
            ];
        }
        return (object) [
            'cmid' => $this->cmid,
            'groupid' => $this->groupid,
            'rows' => $rows,
            'hasrows' => !empty($rows),
            'autorefresh' => $this->autorefresh,
            'interval' => self::REFRESH_SECONDS,
            'maxgrade' => $this->maxgrade,
        ];
    }
}
