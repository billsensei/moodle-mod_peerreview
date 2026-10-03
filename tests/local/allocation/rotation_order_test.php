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
 * Tests for the order of students used by the rotation method.
 *
 * @package    mod_peerreview
 * @category   test
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(rotation_order::class)]
final class rotation_order_test extends \basic_testcase {
    /**
     * Four students whose ids, first names, last names and usernames all sort differently.
     *
     * @return rotation_order
     */
    private function order(): rotation_order {
        $students = [];
        $rows = [
            [1, 'dora', 'Smith', 'u3'],
            [2, 'adam', 'Smith', 'u4'],
            [3, 'cleo', 'Brown', 'u1'],
            [4, 'bart', 'Jones', 'u2'],
        ];
        foreach ($rows as [$id, $firstname, $lastname, $username]) {
            $students[$id] = (object) [
                'id' => (string) $id, 'firstname' => $firstname, 'lastname' => $lastname, 'username' => $username,
            ];
        }
        return new rotation_order($students);
    }

    /**
     * Sorting by last name (the default, also for an unknown field) breaks ties by id.
     */
    public function test_sorted_by_lastname(): void {
        $this->assertSame([[3, 4, 1, 2], []], $this->order()->build('lastname', 1));
        $this->assertSame([[3, 4, 1, 2], []], $this->order()->build('nonsense', 1));
    }

    /**
     * Sorting by first name or username.
     */
    public function test_sorted_by_firstname_and_username(): void {
        $this->assertSame([[2, 4, 3, 1], []], $this->order()->build('firstname', 1));
        $this->assertSame([[3, 4, 1, 2], []], $this->order()->build('username', 1));
    }

    /**
     * A random order is the same for the same seed and contains every student once.
     */
    public function test_random_is_reproducible(): void {
        [$first] = $this->order()->build('random', 42);
        [$second] = $this->order()->build('random', 42);
        $this->assertSame($first, $second);
        $this->assertEqualsCanonicalizing([1, 2, 3, 4], $first);
    }

    /**
     * An explicit list of usernames overrides the sort, ignores case, blank lines and spaces, and reports unknown names.
     */
    public function test_explicit_usernames(): void {
        $list = "U4\n\n  u1 \r\nghost\nu3";
        $this->assertSame([[2, 3, 1], ['ghost']], $this->order()->build('random', 1, $list));
    }
}
