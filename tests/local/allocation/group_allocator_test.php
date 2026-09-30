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
 * Tests for the all-to-all and rotation allocators.
 *
 * @package    mod_peerreview
 * @category   test
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_peerreview\local\allocation;

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the all-to-all and rotation allocators.
 *
 * @package    mod_peerreview
 * @category   test
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(group_allocator::class)]
final class group_allocator_test extends \basic_testcase {
    /**
     * Everyone reviews every other member of their own group, and nobody else.
     */
    public function test_all_to_all(): void {
        $proposal = (new group_allocator())->all_to_all([1 => [1, 2, 3], 2 => [4, 5]]);
        $this->assertCount(6 + 2, $proposal->pairs);
        $this->assertSame([], $proposal->warnings);
        foreach ($proposal->pairs as [$reviewer, $reviewee]) {
            $this->assertNotEquals($reviewer, $reviewee);
            $this->assertSame($reviewer <= 3, $reviewee <= 3, 'crossed a group boundary');
        }
    }

    /**
     * Groups of one are skipped with a warning; existing pairs are not repeated.
     */
    public function test_all_to_all_skips_and_respects_existing(): void {
        $proposal = (new group_allocator())->all_to_all([1 => [1, 2, 3], 2 => [9]], [[1, 2]]);
        $this->assertCount(5, $proposal->pairs);
        $this->assertNotContains([1, 2], $proposal->pairs);
        $this->assertSame('warngrouptoosmall', $proposal->warnings[0][0]);
    }

    /**
     * Rotation: 1 to 2, 2 to 3, last to 1; one review given and received each.
     */
    public function test_rotation_by_one(): void {
        $proposal = (new group_allocator())->rotation([10, 20, 30, 40], 1);
        $this->assertSame([[10, 20], [20, 30], [30, 40], [40, 10]], $proposal->pairs);
    }

    /**
     * Rotation by K is a bijection for every valid K.
     */
    public function test_rotation_is_balanced_for_every_shift(): void {
        $order = range(1, 9);
        for ($shift = 1; $shift <= 8; $shift++) {
            $proposal = (new group_allocator())->rotation($order, $shift);
            [$given, $received] = $proposal->totals();
            foreach ($order as $userid) {
                $this->assertSame(1, $given[$userid]);
                $this->assertSame(1, $received[$userid]);
            }
        }
    }

    /**
     * A shift that maps everyone to themselves is refused, as is a list that is too short.
     */
    public function test_rotation_invalid_shift(): void {
        $proposal = (new group_allocator())->rotation([1, 2, 3], 3);
        $this->assertSame([], $proposal->pairs);
        $this->assertSame('warnrotationinvalid', $proposal->warnings[0][0]);
        $this->assertSame([], (new group_allocator())->rotation([1], 1)->pairs);
    }

    /**
     * A second rotation adds new pairs and skips those that already exist.
     */
    public function test_rotation_adds_to_existing(): void {
        $existing = (new group_allocator())->rotation([1, 2, 3, 4], 1)->pairs;
        $again = (new group_allocator())->rotation([1, 2, 3, 4], 1, $existing);
        $this->assertSame([], $again->pairs);
        $more = (new group_allocator())->rotation([1, 2, 3, 4], 2, $existing);
        $this->assertCount(4, $more->pairs);
    }

    /**
     * A late joiner appended to the order is picked up by a further rotation without touching earlier pairs.
     */
    public function test_rotation_late_joiner(): void {
        $existing = (new group_allocator())->rotation([1, 2, 3], 1)->pairs;
        $more = (new group_allocator())->rotation([1, 2, 3, 4], 1, $existing);
        $this->assertContains([3, 4], $more->pairs);
        $this->assertContains([4, 1], $more->pairs);
        $this->assertNotContains([1, 2], $more->pairs);
    }
}
