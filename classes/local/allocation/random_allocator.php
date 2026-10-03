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
        $existingset = $this->existing_set($existing);

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

            if ($this->has_existing($members, $existingset)) {
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
     * Existing pairs as a lookup.
     *
     * @param int[][] $existing Existing pairs, each [reviewerid, revieweeid].
     * @return bool[] Keys "reviewer_reviewee".
     */
    private function existing_set(array $existing): array {
        $existingset = [];
        foreach ($existing as [$reviewer, $reviewee]) {
            $existingset[$reviewer . '_' . $reviewee] = true;
        }
        return $existingset;
    }

    /**
     * Whether any pair among the members already exists.
     *
     * @param int[] $members
     * @param bool[] $existingset Keys "reviewer_reviewee".
     * @return bool
     */
    private function has_existing(array $members, array $existingset): bool {
        foreach ($members as $userid) {
            foreach ($members as $other) {
                if (isset($existingset[$userid . '_' . $other])) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Top up existing allocations so everyone gives and receives at least N.
     *
     * Counts what each member already gives and receives, lets the slot matcher pair up the missing reviews and
     * adds the pairs it could place to the proposal (a warning for each it could not).
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

        [$giveslots, $receiveslots] = (new slot_matcher($this->random))->match($given, $received, $n, $existingset);
        foreach ($giveslots as $index => $reviewer) {
            $reviewee = $receiveslots[$index];
            if ($reviewee === null) {
                $proposal->warn('warnpairdropped', $reviewer);
                continue;
            }
            $proposal->add($reviewer, $reviewee);
        }
    }
}
