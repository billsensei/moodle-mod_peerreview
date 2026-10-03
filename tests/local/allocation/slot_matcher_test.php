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
 * Tests for the matching of missing reviews when topping up allocations.
 *
 * @package    mod_peerreview
 * @category   test
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(slot_matcher::class)]
final class slot_matcher_test extends \basic_testcase {
    /**
     * Run the matcher and return the pairs it placed.
     *
     * @param int[] $given Reviews already given, userid => count.
     * @param int[] $received Reviews already received, userid => count.
     * @param int $n Reviews each member should give and receive.
     * @param int[][] $existing Existing pairs, each [reviewerid, revieweeid].
     * @param int $seed Seed of the randomizer.
     * @return int[][] Placed pairs, each [reviewerid, revieweeid]; a null reviewee marks a dropped pair.
     */
    private function match(array $given, array $received, int $n, array $existing, int $seed): array {
        $existingset = [];
        foreach ($existing as [$reviewer, $reviewee]) {
            $existingset[$reviewer . '_' . $reviewee] = true;
        }
        $matcher = new slot_matcher(random_allocator::seeded($seed));
        [$giveslots, $receiveslots] = $matcher->match($given, $received, $n, $existingset);
        $this->assertCount(count($giveslots), $receiveslots);
        $pairs = [];
        foreach ($giveslots as $index => $reviewer) {
            $pairs[] = [$reviewer, $receiveslots[$index]];
        }
        return $pairs;
    }

    /**
     * With nothing missing there is nothing to match.
     */
    public function test_nothing_missing(): void {
        $counts = [1 => 2, 2 => 2, 3 => 2];
        $this->assertSame([], $this->match($counts, $counts, 2, [], 1));
    }

    /**
     * Without existing pairs everybody gives and receives N, never to themselves, with no repeated pair.
     */
    public function test_fresh_slots_are_placed_legally(): void {
        $zero = array_fill_keys(range(1, 6), 0);
        for ($seed = 1; $seed <= 5; $seed++) {
            $pairs = $this->match($zero, $zero, 2, [], $seed);
            $this->assertCount(12, $pairs);
            $this->assertCount(12, array_unique(array_map(static fn($p) => $p[0] . '_' . $p[1], $pairs)));
            foreach ($pairs as [$reviewer, $reviewee]) {
                $this->assertNotNull($reviewee);
                $this->assertNotSame($reviewer, $reviewee);
            }
            $this->assertEquals(array_fill_keys(range(1, 6), 2), array_count_values(array_column($pairs, 0)));
            $this->assertEquals(array_fill_keys(range(1, 6), 2), array_count_values(array_column($pairs, 1)));
        }
    }

    /**
     * A late joiner's missing review can only be matched with itself, so the padding must add other members.
     */
    public function test_late_joiner_needs_padding(): void {
        $existing = [[1, 2], [2, 3], [3, 1]];
        $given = [1 => 1, 2 => 1, 3 => 1, 4 => 0];
        for ($seed = 1; $seed <= 5; $seed++) {
            $pairs = $this->match($given, $given, 1, $existing, $seed);
            $this->assertGreaterThan(1, count($pairs), 'padding adds slots beyond the one the late joiner is missing');
            $givers = [];
            $receivers = [];
            foreach ($pairs as [$reviewer, $reviewee]) {
                $this->assertNotNull($reviewee);
                $this->assertNotSame($reviewer, $reviewee);
                $this->assertNotContains([$reviewer, $reviewee], $existing);
                $givers[] = $reviewer;
                $receivers[] = $reviewee;
            }
            $this->assertContains(4, $givers);
            $this->assertContains(4, $receivers);
        }
    }
}
