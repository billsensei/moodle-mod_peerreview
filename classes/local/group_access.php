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
 * Group visibility rules for the teacher pages and web services.
 *
 * @package    mod_peerreview
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_peerreview\local;

/**
 * Which group a teacher may look at.
 *
 * @package    mod_peerreview
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class group_access {
    /**
     * Turn a requested group into one the user may see.
     *
     * @param \stdClass|\cm_info $cm Course module.
     * @param \context_module $context Module context.
     * @param int $groupid Requested group id (0 = all participants).
     * @return int The group id to use: 0 for everyone, -1 when the user may see nobody, otherwise a group id.
     */
    public static function resolve(\stdClass|\cm_info $cm, \context_module $context, int $groupid): int {
        $mode = (int) groups_get_activity_groupmode($cm); // Comes back as a string from the database.
        if ($mode !== SEPARATEGROUPS || has_capability('moodle/site:accessallgroups', $context)) {
            return max(0, $groupid);
        }
        $allowed = array_keys(groups_get_activity_allowed_groups($cm));
        if (!$allowed) {
            return -1;
        }
        return in_array($groupid, $allowed, true) ? $groupid : (int) reset($allowed);
    }

    /**
     * Ids of the users the current user may see, or null when not restricted.
     *
     * Restricted means separate groups without moodle/site:accessallgroups: only members of the user's own groups.
     *
     * @param \stdClass|\cm_info $cm Course module.
     * @param \context_module $context Module context.
     * @return int[]|null Visible user ids, null for no restriction.
     */
    public static function visible_userids(\stdClass|\cm_info $cm, \context_module $context): ?array {
        $separate = (int) groups_get_activity_groupmode($cm) === SEPARATEGROUPS;
        if (!$separate || has_capability('moodle/site:accessallgroups', $context)) {
            return null;
        }
        $userids = [];
        foreach (array_keys(groups_get_activity_allowed_groups($cm)) as $groupid) {
            foreach (array_keys(groups_get_members($groupid, 'u.id')) as $userid) {
                $userids[(int) $userid] = (int) $userid;
            }
        }
        return array_values($userids);
    }
}
