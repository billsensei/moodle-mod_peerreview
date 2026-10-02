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
 * Renderable: average score per criterion on the teacher report.
 *
 * @package    mod_peerreview
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_peerreview\output;

/**
 * The criterion statistics table.
 *
 * @package    mod_peerreview
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class criterion_table implements \renderable, \templatable {
    /**
     * Constructor.
     *
     * @param array[] $stats From criterion_stats::get_statistics().
     */
    public function __construct(
        /** @var array[] Statistics per criterion. */
        private readonly array $stats
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
        foreach ($this->stats as $stat) {
            $rows[] = [
                'name' => $stat['name'],
                'count' => $stat['count'],
                'average' => $stat['average'] === null ? '–' : format_float($stat['average'], 2),
                'max' => format_float($stat['max'], 2, true, true),
                'percent' => $stat['average'] === null || $stat['max'] <= 0
                    ? 0 : (int) round(100 * $stat['average'] / $stat['max']),
                'hasaverage' => $stat['average'] !== null,
            ];
        }
        return (object) ['rows' => $rows, 'hasrows' => !empty($rows)];
    }
}
