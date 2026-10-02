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
 * Within-group all-to-all allocation and rotation allocation.
 *
 * @package    mod_peerreview
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_peerreview\local\allocation;

/**
 * Deterministic allocation methods: everyone reviews everyone in their group, rotation by K, and self-assessment.
 *
 * @package    mod_peerreview
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class group_allocator {
    /**
     * Every member reviews every other member of their group.
     *
     * @param int[][] $groups Group id => member user ids.
     * @param int[][] $existing Existing pairs, each [reviewerid, revieweeid].
     * @return proposal
     */
    public function all_to_all(array $groups, array $existing = []): proposal {
        $proposal = new proposal();
        $seen = self::pair_set($existing);
        foreach ($groups as $groupid => $members) {
            $members = array_values(array_unique($members));
            if (count($members) < 2) {
                $proposal->warn('warngrouptoosmall', $groupid);
                continue;
            }
            foreach ($members as $reviewer) {
                foreach ($members as $reviewee) {
                    if ($reviewer !== $reviewee && !isset($seen[$reviewer . '_' . $reviewee])) {
                        $seen[$reviewer . '_' . $reviewee] = true; // Users in two groups must not be paired twice.
                        $proposal->add($reviewer, $reviewee);
                    }
                }
            }
        }
        return $proposal;
    }

    /**
     * Rotation: the user at position i reviews the user at position (i + shift) mod n.
     *
     * Each shift is a bijection, so everyone gives exactly one review and receives exactly one.
     *
     * @param int[] $order Ordered user ids.
     * @param int $shift K.
     * @param int[][] $existing Existing pairs, each [reviewerid, revieweeid].
     * @return proposal
     */
    public function rotation(array $order, int $shift, array $existing = []): proposal {
        $proposal = new proposal();
        $order = array_values(array_unique($order));
        $size = count($order);
        if ($size < 2 || $shift % $size === 0) {
            $proposal->warn('warnrotationinvalid', (object) ['size' => $size, 'shift' => $shift]);
            return $proposal;
        }
        $existingset = self::pair_set($existing);
        $shift = (($shift % $size) + $size) % $size;
        foreach ($order as $position => $reviewer) {
            $reviewee = $order[($position + $shift) % $size];
            if (!isset($existingset[$reviewer . '_' . $reviewee])) {
                $proposal->add($reviewer, $reviewee);
            }
        }
        return $proposal;
    }

    /**
     * Self-assessment: every user reviews themselves.
     *
     * @param int[] $userids Users to give a self-review.
     * @param int[][] $existing Existing pairs, each [reviewerid, revieweeid].
     * @return proposal
     */
    public function self_assessment(array $userids, array $existing = []): proposal {
        $proposal = new proposal();
        $existingset = self::pair_set($existing);
        foreach (array_values(array_unique($userids)) as $userid) {
            if (!isset($existingset[$userid . '_' . $userid])) {
                $proposal->add($userid, $userid);
            }
        }
        return $proposal;
    }

    /**
     * Build a lookup of pairs.
     *
     * @param int[][] $pairs
     * @return bool[] Keys "reviewer_reviewee".
     */
    private static function pair_set(array $pairs): array {
        $set = [];
        foreach ($pairs as [$reviewer, $reviewee]) {
            $set[$reviewer . '_' . $reviewee] = true;
        }
        return $set;
    }
}
