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
 * Balanced random allocation, N reviews per student.
 *
 * See docs/DESIGN.md section 4.2 for the algorithm and the balance proof.
 *
 * @package    mod_peerreview
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_peerreview\local\allocation;

use Random\Randomizer;

/**
 * Balanced random allocation.
 *
 * @package    mod_peerreview
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class random_allocator {
    /** @var int Random matchings tried per amount of padding before adding more padding. */
    private const MATCH_ATTEMPTS = 6;

    /** @var Randomizer Source of randomness (seedable so a preview can be reproduced on confirm). */
    private Randomizer $random;

    /**
     * Constructor.
     *
     * @param Randomizer $random
     */
    public function __construct(Randomizer $random) {
        $this->random = $random;
    }

    /**
     * Build a randomizer seeded with an integer.
     *
     * @param int $seed
     * @return Randomizer
     */
    public static function seeded(int $seed): Randomizer {
        return new Randomizer(new \Random\Engine\Mt19937($seed));
    }

    /**
     * Generate new pairs.
     *
     * @param int[][] $pools Pools of eligible user ids; reviewers and reviewees stay inside their pool.
     * @param int $n Reviews each student should give and receive.
     * @param int[][] $existing Existing pairs, each [reviewerid, revieweeid].
     * @return proposal
     */
    public function generate(array $pools, int $n, array $existing = []): proposal {
        $proposal = new proposal();
        $existingset = [];
        foreach ($existing as [$reviewer, $reviewee]) {
            $existingset[$reviewer . '_' . $reviewee] = true;
        }

        foreach ($pools as $poolid => $members) {
            $members = array_values(array_unique($members));
            $size = count($members);
            if ($size < 2) {
                $proposal->warn('warnpooltoosmall', $poolid);
                continue;
            }
            $effective = min($n, $size - 1);
            if ($effective < $n) {
                $proposal->warn('warnnreduced', (object) ['pool' => $poolid, 'size' => $size, 'n' => $effective]);
            }

            $hasexisting = false;
            foreach ($members as $userid) {
                foreach ($members as $other) {
                    if (isset($existingset[$userid . '_' . $other])) {
                        $hasexisting = true;
                        break 2;
                    }
                }
            }

            if ($hasexisting) {
                $this->top_up($proposal, $members, $effective, $existingset);
            } else {
                $this->fresh($proposal, $members, $effective);
            }
        }
        return $proposal;
    }

    /**
     * Circulant construction: exactly N given and N received per student. Proof in docs/DESIGN.md 4.2.
     *
     * @param proposal $proposal
     * @param int[] $members
     * @param int $n
     */
    private function fresh(proposal $proposal, array $members, int $n): void {
        $size = count($members);
        $order = $this->random->shuffleArray($members);
        $offsets = $this->random->pickArrayKeys(range(1, $size - 1), $n);
        $offsets = array_map(static fn($key) => $key + 1, $offsets);
        foreach ($order as $position => $reviewer) {
            foreach ($offsets as $offset) {
                $proposal->add($reviewer, $order[($position + $offset) % $size]);
            }
        }
    }

    /**
     * Top up existing allocations so everyone gives and receives at least N.
     *
     * Slots: every reviewer below N gets a "give" slot per missing review, every reviewee below N a "receive"
     * slot. The shorter list is padded with the least-loaded members, then give and receive slots are matched
     * at random and conflicts (self, duplicates, existing pairs) are repaired by swapping targets.
     *
     * @param proposal $proposal
     * @param int[] $members
     * @param int $n
     * @param bool[] $existingset Keys "reviewer_reviewee".
     */
    private function top_up(proposal $proposal, array $members, int $n, array $existingset): void {
        $given = array_fill_keys($members, 0);
        $received = array_fill_keys($members, 0);
        foreach ($members as $reviewer) {
            foreach ($members as $reviewee) {
                if (isset($existingset[$reviewer . '_' . $reviewee])) {
                    $given[$reviewer]++;
                    $received[$reviewee]++;
                }
            }
        }

        $giveneeded = $this->slots($given, $n);
        $receiveneeded = $this->slots($received, $n);
        $base = max(count($giveneeded), count($receiveneeded));

        // If the only students still short of reviews are few (a late joiner), the slots may only match each other.
        // Pad with the least-loaded students, one more slot at a time, until every slot can be placed.
        $best = null;
        for ($count = $base; $count <= $base + count($members) && ($best === null || $best[2] > 0); $count++) {
            for ($attempt = 0; $attempt < self::MATCH_ATTEMPTS && ($best === null || $best[2] > 0); $attempt++) {
                $giveslots = $this->random->shuffleArray($this->pad($giveneeded, $given, $count));
                $receiveslots = $this->random->shuffleArray($this->pad($receiveneeded, $received, $count));
                $this->repair($giveslots, $receiveslots, $existingset);
                $dropped = count(array_filter($receiveslots, static fn($slot) => $slot === null));
                if ($best === null || $dropped < $best[2] || ($dropped === $best[2] && $count < $best[3])) {
                    $best = [$giveslots, $receiveslots, $dropped, $count];
                }
            }
        }
        [$giveslots, $receiveslots] = $best;

        foreach ($giveslots as $index => $reviewer) {
            $reviewee = $receiveslots[$index];
            if ($reviewee === null) {
                $proposal->warn('warnpairdropped', $reviewer);
                continue;
            }
            $proposal->add($reviewer, $reviewee);
        }
    }

    /**
     * One slot per missing review.
     *
     * @param int[] $counts userid => current count
     * @param int $n
     * @return int[] Slot list (userid repeated).
     */
    private function slots(array $counts, int $n): array {
        $slots = [];
        foreach ($counts as $userid => $count) {
            for ($i = $count; $i < $n; $i++) {
                $slots[] = $userid;
            }
        }
        return $slots;
    }

    /**
     * Pad a slot list to $count with the currently least-loaded users (ties broken at random).
     *
     * @param int[] $slots
     * @param int[] $counts userid => current count
     * @param int $count Target length.
     * @return int[]
     */
    private function pad(array $slots, array $counts, int $count): array {
        foreach ($slots as $userid) {
            $counts[$userid]++;
        }
        while (count($slots) < $count) {
            $min = min($counts);
            $candidates = array_keys(array_filter($counts, static fn($c) => $c === $min));
            $pick = $candidates[$this->random->getInt(0, count($candidates) - 1)];
            $slots[] = $pick;
            $counts[$pick]++;
        }
        return $slots;
    }

    /**
     * Swap receive slots until no position is illegal (self, existing pair or duplicate); drop what cannot be fixed.
     *
     * Each swap makes both positions legal, so the number of illegal positions strictly decreases.
     *
     * @param int[] $giveslots
     * @param array $receiveslots Modified in place (by reference); null marks a dropped pair.
     * @param bool[] $existingset
     */
    private function repair(array $giveslots, array &$receiveslots, array $existingset): void {
        $count = count($giveslots);
        $legal = function (int $index) use (&$giveslots, &$receiveslots, $existingset): bool {
            $reviewer = $giveslots[$index];
            $reviewee = $receiveslots[$index];
            if ($reviewee === null) {
                return true;
            }
            if ($reviewer === $reviewee || isset($existingset[$reviewer . '_' . $reviewee])) {
                return false;
            }
            foreach ($giveslots as $other => $otherreviewer) {
                if ($other !== $index && $otherreviewer === $reviewer && $receiveslots[$other] === $reviewee) {
                    return $other > $index; // The later duplicate is the illegal one.
                }
            }
            return true;
        };

        for ($index = 0; $index < $count; $index++) {
            if ($legal($index)) {
                continue;
            }
            $others = $this->random->shuffleArray(array_values(array_diff(range(0, $count - 1), [$index])));
            $fixed = false;
            foreach ($others as $other) {
                [$receiveslots[$index], $receiveslots[$other]] = [$receiveslots[$other], $receiveslots[$index]];
                if ($legal($index) && $legal($other)) {
                    $fixed = true;
                    break;
                }
                [$receiveslots[$index], $receiveslots[$other]] = [$receiveslots[$other], $receiveslots[$index]];
            }
            if (!$fixed) {
                $receiveslots[$index] = null;
            }
        }

        // A swap can, rarely, turn an earlier position into a duplicate; drop whatever is still illegal.
        for ($index = 0; $index < $count; $index++) {
            if (!$legal($index)) {
                $receiveslots[$index] = null;
            }
        }
    }
}
