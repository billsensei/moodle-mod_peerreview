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
 * Matching of "give" slots to "receive" slots when topping up existing allocations.
 *
 * @package    mod_peerreview
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_peerreview\local\allocation;

use Random\Randomizer;

/**
 * Pairs the reviews that are still missing: who has to give one with who has to receive one.
 *
 * Split out of the random allocator, which hands it the current counts. It draws from the same randomizer as the
 * allocator, in the same order as before the split, so a seed gives the same proposal. See docs/DESIGN.md section 4.2.
 *
 * @package    mod_peerreview
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class slot_matcher {
    /** @var int Random matchings tried per amount of padding before adding more padding. */
    private const MATCH_ATTEMPTS = 6;

    /**
     * Constructor.
     *
     * @param Randomizer $random Source of randomness, shared with the allocator.
     */
    public function __construct(
        /** @var Randomizer Source of randomness (seedable so a preview can be reproduced on confirm). */
        private readonly Randomizer $random
    ) {
    }

    /**
     * Match give slots to receive slots so everyone gives and receives at least N.
     *
     * Slots: every reviewer below N gets a "give" slot per missing review, every reviewee below N a "receive"
     * slot. The shorter list is padded with the least-loaded members, then give and receive slots are matched
     * at random and conflicts (self, duplicates, existing pairs) are repaired by swapping targets.
     *
     * If the only students still short of reviews are few (a late joiner), the slots may only match each other.
     * Then more padding is added, one slot at a time, until every slot can be placed or the padding is used up.
     *
     * @param int[] $given Reviews each member already gives, userid => count (the keys are all the members).
     * @param int[] $received Reviews each member already receives, userid => count.
     * @param int $n Reviews each member should give and receive.
     * @param bool[] $existingset Keys "reviewer_reviewee".
     * @return array [int[] give slots, array receive slots (null marks a pair that could not be placed)]
     */
    public function match(array $given, array $received, int $n, array $existingset): array {
        $giveneeded = $this->slots($given, $n);
        $receiveneeded = $this->slots($received, $n);
        $base = max(count($giveneeded), count($receiveneeded));

        $best = null;
        $maxcount = $base + count($given);
        for ($count = $base; $count <= $maxcount && $this->unsettled($best); $count++) {
            for ($attempt = 0; $attempt < self::MATCH_ATTEMPTS && $this->unsettled($best); $attempt++) {
                $giveslots = $this->random->shuffleArray($this->pad($giveneeded, $given, $count));
                $receiveslots = $this->random->shuffleArray($this->pad($receiveneeded, $received, $count));
                $this->repair($giveslots, $receiveslots, $existingset);
                $dropped = count(array_filter($receiveslots, static fn($slot) => $slot === null));
                if ($this->is_better($best, $dropped, $count)) {
                    $best = [$giveslots, $receiveslots, $dropped, $count];
                }
            }
        }
        return [$best[0], $best[1]];
    }

    /**
     * Whether the best matching so far still leaves a pair unplaced (or there is none yet).
     *
     * @param array|null $best [give slots, receive slots, dropped, count] or null.
     * @return bool
     */
    private function unsettled(?array $best): bool {
        return $best === null || $best[2] > 0;
    }

    /**
     * Whether a new matching beats the best one: fewer dropped pairs, or as many with less padding.
     *
     * @param array|null $best [give slots, receive slots, dropped, count] or null.
     * @param int $dropped Pairs the new matching could not place.
     * @param int $count Slots (with padding) of the new matching.
     * @return bool
     */
    private function is_better(?array $best, int $dropped, int $count): bool {
        return $best === null || $dropped < $best[2] || ($dropped === $best[2] && $count < $best[3]);
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
        for ($have = count($slots); $have < $count; $have++) {
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
        for ($index = 0; $index < $count; $index++) {
            if ($this->is_legal($index, $giveslots, $receiveslots, $existingset)) {
                continue;
            }
            if (!$this->swap_to_legal($index, $giveslots, $receiveslots, $existingset)) {
                $receiveslots[$index] = null;
            }
        }

        // A swap can, rarely, turn an earlier position into a duplicate; drop whatever is still illegal.
        for ($index = 0; $index < $count; $index++) {
            if (!$this->is_legal($index, $giveslots, $receiveslots, $existingset)) {
                $receiveslots[$index] = null;
            }
        }
    }

    /**
     * Try to fix an illegal position by swapping its receive slot with another one (in random order).
     *
     * @param int $index The illegal position.
     * @param int[] $giveslots
     * @param array $receiveslots Modified in place; left as it was when no swap helps.
     * @param bool[] $existingset
     * @return bool Whether a swap made both positions legal.
     */
    private function swap_to_legal(int $index, array $giveslots, array &$receiveslots, array $existingset): bool {
        $others = array_values(array_diff(range(0, count($giveslots) - 1), [$index]));
        foreach ($this->random->shuffleArray($others) as $other) {
            [$receiveslots[$index], $receiveslots[$other]] = [$receiveslots[$other], $receiveslots[$index]];
            $legal = $this->is_legal($index, $giveslots, $receiveslots, $existingset);
            if ($legal && $this->is_legal($other, $giveslots, $receiveslots, $existingset)) {
                return true;
            }
            [$receiveslots[$index], $receiveslots[$other]] = [$receiveslots[$other], $receiveslots[$index]];
        }
        return false;
    }

    /**
     * Whether the pair at a position is allowed: not self, not an existing pair, and not a repeat of an earlier one.
     *
     * @param int $index Position in the slot lists.
     * @param int[] $giveslots
     * @param array $receiveslots Receive slots; null means the pair was dropped (always legal).
     * @param bool[] $existingset
     * @return bool
     */
    private function is_legal(int $index, array $giveslots, array $receiveslots, array $existingset): bool {
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
    }
}
