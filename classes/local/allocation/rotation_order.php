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
 * The order of students for the rotation allocation method.
 *
 * @package    mod_peerreview
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_peerreview\local\allocation;

/**
 * Puts the eligible students of an activity in the order the rotation method shifts them by.
 *
 * Split out of the allocation manager, which passes it the eligible students.
 *
 * @package    mod_peerreview
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class rotation_order {
    /**
     * Constructor.
     *
     * @param \stdClass[] $students Eligible students, userid => user record (id, username, firstname, lastname).
     */
    public function __construct(
        /** @var \stdClass[] Eligible students, userid => user record. */
        private readonly array $students
    ) {
    }

    /**
     * Ordered list of students for the rotation method.
     *
     * @param string $sortby lastname, firstname, username or random.
     * @param int $seed Seed for random.
     * @param string $usernames Optional explicit order, one username per line (overrides $sortby).
     * @return array [int[] ordered user ids, string[] unknown usernames]
     */
    public function build(string $sortby, int $seed, string $usernames = ''): array {
        $usernames = array_filter(array_map('trim', preg_split('/\R/', $usernames)));
        if ($usernames) {
            return $this->from_usernames($usernames);
        }

        $list = array_values($this->students);
        if ($sortby === 'random') {
            $ids = random_allocator::seeded($seed)->shuffleArray(array_map(static fn($s) => (int) $s->id, $list));
            return [$ids, []];
        }
        $field = in_array($sortby, ['firstname', 'username'], true) ? $sortby : 'lastname';
        usort($list, static fn($a, $b) => \core_text::strtolower($a->$field) <=> \core_text::strtolower($b->$field)
            ?: $a->id <=> $b->id);
        return [array_map(static fn($s) => (int) $s->id, $list), []];
    }

    /**
     * The order given by the teacher as a list of usernames.
     *
     * @param string[] $usernames Trimmed, non-empty usernames in the wanted order.
     * @return array [int[] ordered user ids, string[] usernames that match no eligible student]
     */
    private function from_usernames(array $usernames): array {
        $byusername = [];
        foreach ($this->students as $student) {
            $byusername[\core_text::strtolower($student->username)] = (int) $student->id;
        }
        $order = [];
        $unknown = [];
        foreach ($usernames as $username) {
            $key = \core_text::strtolower($username);
            if (isset($byusername[$key])) {
                $order[] = $byusername[$key];
            } else {
                $unknown[] = $username;
            }
        }
        return [$order, $unknown];
    }
}
