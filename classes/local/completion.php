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
 * Keeps activity completion up to date when reviews are submitted or allocations change.
 *
 * @package    mod_peerreview
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_peerreview\local;

/**
 * Completion refresh.
 *
 * @package    mod_peerreview
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class completion {
    /**
     * Re-evaluate completion for some users. Does nothing unless the activity tracks completion automatically.
     *
     * @param \stdClass $peerreview Activity record.
     * @param int[] $userids Users to re-evaluate.
     */
    public static function update(\stdClass $peerreview, array $userids): void {
        $cm = get_coursemodule_from_instance('peerreview', $peerreview->id, $peerreview->course);
        if (!$cm || empty($peerreview->completionallreviews) || (int) $cm->completion !== COMPLETION_TRACKING_AUTOMATIC) {
            return;
        }
        $course = get_course($peerreview->course);
        $completion = new \completion_info($course);
        if (!$completion->is_enabled($cm)) {
            return;
        }
        foreach (array_unique($userids) as $userid) {
            $completion->update_state($cm, COMPLETION_UNKNOWN, (int) $userid);
        }
    }
}
