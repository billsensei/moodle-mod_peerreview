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
 * The part of the existing allocations an automatic method works on.
 *
 * @package    mod_peerreview
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_peerreview\local\allocation;

/**
 * Tells which existing allocations lie inside the pools of an automatic method (both people are in scope).
 *
 * Split out of the allocation manager. An allocation is in scope when its reviewer and its reviewee are both members
 * of the pools.
 *
 * @package    mod_peerreview
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class pool_scope {
    /** @var bool[] User id => true for every user in the pools. */
    private array $inscope = [];

    /**
     * Constructor.
     *
     * @param int[][] $pools Pool id => user ids in scope.
     */
    public function __construct(array $pools) {
        foreach ($pools as $members) {
            foreach ($members as $userid) {
                $this->inscope[$userid] = true;
            }
        }
    }

    /**
     * Allocations that a "replace" run would remove: not started and inside the pools involved.
     *
     * @param \stdClass[] $allocations Allocation records, id => record.
     * @return \stdClass[] The records with status new that are in scope, keeping their ids as keys.
     */
    public function replaceable(array $allocations): array {
        return array_filter(
            $allocations,
            fn($alloc) => (int) $alloc->status === manager::STATUS_NEW && $this->contains($alloc)
        );
    }

    /**
     * Count of allocations in scope that have a saved or submitted review (they are always kept by "replace").
     *
     * @param \stdClass[] $allocations Allocation records, id => record.
     * @return int
     */
    public function count_started(array $allocations): int {
        return count(array_filter(
            $allocations,
            fn($alloc) => (int) $alloc->status > manager::STATUS_NEW && $this->contains($alloc)
        ));
    }

    /**
     * Whether both people of an allocation are in the pools.
     *
     * @param \stdClass $alloc Allocation record.
     * @return bool
     */
    private function contains(\stdClass $alloc): bool {
        return isset($this->inscope[$alloc->reviewerid], $this->inscope[$alloc->revieweeid]);
    }
}
