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
 * Result of an allocation algorithm.
 *
 * @package    mod_peerreview
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_peerreview\local\allocation;

/**
 * A set of new reviewer/reviewee pairs plus warnings for the teacher. Pairs that already exist are never included.
 *
 * @package    mod_peerreview
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class proposal {
    /** @var int[][] New pairs, each [reviewerid, revieweeid]. */
    public array $pairs = [];

    /** @var array[] Warnings, each [lang string identifier, $a for get_string]. */
    public array $warnings = [];

    /**
     * Add a pair.
     *
     * @param int $reviewerid
     * @param int $revieweeid
     */
    public function add(int $reviewerid, int $revieweeid): void {
        $this->pairs[] = [$reviewerid, $revieweeid];
    }

    /**
     * Add a warning.
     *
     * @param string $identifier Lang string identifier in mod_peerreview.
     * @param mixed $a Value for the string.
     */
    public function warn(string $identifier, $a = null): void {
        $this->warnings[] = [$identifier, $a];
    }

    /**
     * How many reviews each user would give and receive once this proposal is added to existing pairs.
     *
     * @param int[][] $existing Existing pairs, each [reviewerid, revieweeid].
     * @return array [given, received], each userid => count.
     */
    public function totals(array $existing = []): array {
        $given = [];
        $received = [];
        foreach (array_merge($existing, $this->pairs) as [$reviewer, $reviewee]) {
            $given[$reviewer] = ($given[$reviewer] ?? 0) + 1;
            $received[$reviewee] = ($received[$reviewee] ?? 0) + 1;
        }
        return [$given, $received];
    }
}
