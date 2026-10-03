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
 * Tests for the allocation manager (database level).
 *
 * @package    mod_peerreview
 * @category   test
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_peerreview\local\allocation;

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the scope of an automatic allocation method.
 *
 * @package    mod_peerreview
 * @category   test
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(pool_scope::class)]
final class pool_scope_test extends \basic_testcase {
    /**
     * Allocation records as the database returns them (string ids and status).
     *
     * @return \stdClass[] id => record
     */
    private function allocations(): array {
        $rows = [
            [10, 1, 2, 0], // In scope, not started.
            [11, 1, 3, 0], // In scope (users of different pools count), not started.
            [12, 2, 3, 1], // In scope, draft.
            [13, 3, 1, 2], // In scope, submitted.
            [14, 4, 1, 0], // Reviewer outside the pools.
            [15, 1, 4, 2], // Reviewee outside the pools.
        ];
        $result = [];
        foreach ($rows as [$id, $reviewer, $reviewee, $status]) {
            $result[$id] = (object) [
                'id' => (string) $id, 'reviewerid' => (string) $reviewer, 'revieweeid' => (string) $reviewee,
                'status' => (string) $status,
            ];
        }
        return $result;
    }

    /**
     * Only not-started allocations inside the pools are replaceable, and their ids are kept as keys.
     */
    public function test_replaceable(): void {
        $scope = new pool_scope([1 => [1, 2], 2 => [3]]);
        $this->assertSame([10, 11], array_keys($scope->replaceable($this->allocations())));
    }

    /**
     * Saved and submitted allocations inside the pools are counted as started.
     */
    public function test_count_started(): void {
        $scope = new pool_scope([1 => [1, 2], 2 => [3]]);
        $this->assertSame(2, $scope->count_started($this->allocations()));
    }

    /**
     * With no pools nothing is in scope.
     */
    public function test_empty_pools(): void {
        $scope = new pool_scope([]);
        $this->assertSame([], $scope->replaceable($this->allocations()));
        $this->assertSame(0, $scope->count_started($this->allocations()));
    }
}
