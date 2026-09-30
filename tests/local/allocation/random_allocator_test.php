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
 * Tests for the balanced random allocator.
 *
 * @package    mod_peerreview
 * @category   test
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_peerreview\local\allocation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests for the balanced random allocator.
 *
 * @package    mod_peerreview
 * @category   test
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(random_allocator::class)]
#[CoversClass(proposal::class)]
final class random_allocator_test extends \basic_testcase {
    /**
     * Assert basic legality of a set of pairs.
     *
     * @param int[][] $pairs New pairs.
     * @param int[][] $existing Existing pairs.
     */
    private function assert_legal(array $pairs, array $existing = []): void {
        $seen = [];
        foreach ($existing as [$reviewer, $reviewee]) {
            $seen[$reviewer . '_' . $reviewee] = true;
        }
        foreach ($pairs as [$reviewer, $reviewee]) {
            $this->assertNotEquals($reviewer, $reviewee, 'self review');
            $this->assertArrayNotHasKey($reviewer . '_' . $reviewee, $seen, 'duplicate or existing pair');
            $seen[$reviewer . '_' . $reviewee] = true;
        }
    }

    /**
     * Sizes and N values for the balance check.
     *
     * @return array
     */
    public static function balance_provider(): array {
        $cases = [];
        foreach ([2, 3, 4, 7, 12, 25, 30] as $size) {
            foreach ([1, 2, 3, 5] as $n) {
                if ($n <= $size - 1) {
                    $cases["size $size, N $n"] = [$size, $n];
                }
            }
        }
        return $cases;
    }

    /**
     * Fresh allocation: every student gives exactly N and receives exactly N, never self, no duplicates.
     *
     * @param int $size
     * @param int $n
     */
    #[DataProvider('balance_provider')]
    public function test_fresh_is_exactly_balanced(int $size, int $n): void {
        $members = range(101, 100 + $size);
        for ($seed = 1; $seed <= 5; $seed++) {
            $proposal = (new random_allocator(random_allocator::seeded($seed)))->generate([0 => $members], $n);
            $this->assertSame([], $proposal->warnings);
            $this->assertCount($size * $n, $proposal->pairs);
            $this->assert_legal($proposal->pairs);
            [$given, $received] = $proposal->totals();
            foreach ($members as $userid) {
                $this->assertSame($n, $given[$userid]);
                $this->assertSame($n, $received[$userid]);
            }
        }
    }

    /**
     * The same seed gives the same proposal (needed so confirm reproduces the preview).
     */
    public function test_seed_is_reproducible(): void {
        $members = range(1, 15);
        $first = (new random_allocator(random_allocator::seeded(42)))->generate([0 => $members], 3);
        $second = (new random_allocator(random_allocator::seeded(42)))->generate([0 => $members], 3);
        $third = (new random_allocator(random_allocator::seeded(43)))->generate([0 => $members], 3);
        $this->assertSame($first->pairs, $second->pairs);
        $this->assertNotSame($first->pairs, $third->pairs);
    }

    /**
     * N larger than the pool allows is reduced with a warning.
     */
    public function test_n_is_reduced_with_warning(): void {
        $proposal = (new random_allocator(random_allocator::seeded(1)))->generate([0 => [1, 2, 3]], 5);
        $this->assertCount(6, $proposal->pairs);
        $this->assertSame('warnnreduced', $proposal->warnings[0][0]);
        $this->assertSame(2, $proposal->warnings[0][1]->n);
    }

    /**
     * A pool of one cannot be allocated.
     */
    public function test_tiny_pool_warns(): void {
        $proposal = (new random_allocator(random_allocator::seeded(1)))->generate([7 => [1], 8 => [2, 3, 4]], 1);
        $this->assertSame('warnpooltoosmall', $proposal->warnings[0][0]);
        $this->assertCount(3, $proposal->pairs);
    }

    /**
     * Reviewers stay inside their group pool.
     */
    public function test_pools_are_respected(): void {
        $pools = [1 => [1, 2, 3, 4, 5], 2 => [11, 12, 13, 14], 3 => [21, 22, 23]];
        for ($seed = 1; $seed <= 10; $seed++) {
            $proposal = (new random_allocator(random_allocator::seeded($seed)))->generate($pools, 2);
            $this->assert_legal($proposal->pairs);
            $poolof = [];
            foreach ($pools as $poolid => $members) {
                foreach ($members as $userid) {
                    $poolof[$userid] = $poolid;
                }
            }
            foreach ($proposal->pairs as [$reviewer, $reviewee]) {
                $this->assertSame($poolof[$reviewer], $poolof[$reviewee]);
            }
            [$given, $received] = $proposal->totals();
            foreach (array_keys($poolof) as $userid) {
                $this->assertSame(2, $given[$userid]);
                $this->assertSame(2, $received[$userid]);
            }
        }
    }

    /**
     * A student who joins after allocation is added without touching existing pairs and everyone stays balanced.
     */
    public function test_late_joiner_top_up(): void {
        $members = range(1, 10);
        for ($seed = 1; $seed <= 20; $seed++) {
            $first = (new random_allocator(random_allocator::seeded($seed)))->generate([0 => $members], 2);
            $members2 = array_merge($members, [11]);
            $second = (new random_allocator(random_allocator::seeded($seed + 100)))->generate([0 => $members2], 2, $first->pairs);

            $this->assertSame([], $second->warnings);
            $this->assert_legal($second->pairs, $first->pairs);
            [$given, $received] = (new proposal())->totals(array_merge($first->pairs, $second->pairs));
            $this->assertGreaterThanOrEqual(2, $given[11]);
            $this->assertGreaterThanOrEqual(2, $received[11]);
            $this->assertLessThanOrEqual(1, max($received) - min($received), 'received spread');
            $this->assertLessThanOrEqual(1, max($given) - min($given), 'given spread');
        }
    }

    /**
     * Existing manual pairs are respected and topped up.
     */
    public function test_top_up_with_manual_pairs(): void {
        $members = range(1, 12);
        $existing = [[1, 2], [2, 1], [3, 4], [5, 6], [5, 7]];
        for ($seed = 1; $seed <= 20; $seed++) {
            $proposal = (new random_allocator(random_allocator::seeded($seed)))->generate([0 => $members], 2, $existing);
            $this->assertSame([], $proposal->warnings);
            $this->assert_legal($proposal->pairs, $existing);
            [$given, $received] = $proposal->totals($existing);
            foreach ($members as $userid) {
                $this->assertGreaterThanOrEqual(2, $given[$userid] ?? 0, "user $userid gives");
                $this->assertGreaterThanOrEqual(2, $received[$userid] ?? 0, "user $userid receives");
            }
            $this->assertLessThanOrEqual(1, max($received) - min($received), 'received spread');
        }
    }

    /**
     * Messy top-ups (removed pairs, manual pairs, late joiners): always legal, nobody left below N, and nobody pushed
     * above N+1 or above what they already had. Spread above 1 can then only come from pre-existing manual pairs.
     */
    public function test_messy_top_ups_stay_legal_and_bounded(): void {
        mt_srand(7);
        for ($run = 0; $run < 150; $run++) {
            $size = mt_rand(3, 25);
            $n = mt_rand(1, min(4, $size - 1));
            $members = range(1, $size);
            $earlier = array_slice($members, 0, max(2, $size - mt_rand(0, 3)));
            $first = (new random_allocator(random_allocator::seeded($run)))->generate([0 => $earlier], $n)->pairs;
            $existing = array_values(array_filter($first, static fn() => mt_rand(0, 9) > 1));
            for ($k = 0; $k < mt_rand(0, 3); $k++) {
                $a = mt_rand(1, $size);
                $b = mt_rand(1, $size);
                if ($a !== $b && !in_array([$a, $b], $existing, true)) {
                    $existing[] = [$a, $b];
                }
            }

            $proposal = (new random_allocator(random_allocator::seeded($run + 5000)))->generate([0 => $members], $n, $existing);
            $this->assert_legal($proposal->pairs, $existing);
            if ($proposal->warnings) {
                $this->assertSame('warnpairdropped', $proposal->warnings[0][0]);
                continue;
            }
            [, $before] = (new proposal())->totals($existing);
            $cap = max(array_merge([$n + 1], $before ?: [0]));
            [$given, $received] = $proposal->totals($existing);
            foreach ($members as $userid) {
                $this->assertGreaterThanOrEqual($n, $given[$userid] ?? 0, "run $run user $userid gives");
                $this->assertGreaterThanOrEqual($n, $received[$userid] ?? 0, "run $run user $userid receives");
                $this->assertLessThanOrEqual($cap, $received[$userid] ?? 0, "run $run user $userid receives too many");
            }
        }
    }
}
