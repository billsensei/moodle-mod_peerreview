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
 * Teacher override of a student's received grade.
 *
 * @package    mod_peerreview
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_peerreview\local\grade;

/**
 * Sets and reverts overrides. The override lives in its own table so the peer data is never touched and reverting is
 * just deleting the row.
 *
 * @package    mod_peerreview
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class override {
    /**
     * Constructor.
     *
     * @param \stdClass $peerreview Activity record.
     * @param \context_module $context Module context.
     */
    public function __construct(
        /** @var \stdClass Activity record. */
        private readonly \stdClass $peerreview,
        /** @var \context_module Module context. */
        private readonly \context_module $context
    ) {
    }

    /**
     * Set or change an override. Requires mod/peerreview:overridegrade.
     *
     * @param int $userid The student.
     * @param float $grade New received grade: from 0 to the activity maximum, or the position of a scale item.
     * @param string $note Why (shown to teachers).
     * @param int $byuserid Teacher making the change.
     * @throws \moodle_exception When the grade is outside the range.
     */
    public function set(int $userid, float $grade, string $note, int $byuserid): void {
        global $DB;

        require_capability('mod/peerreview:overridegrade', $this->context);
        $range = grade_range::for_activity($this->peerreview);
        if (!$range->accepts($grade)) {
            throw new \moodle_exception($range->get_error_code(), 'mod_peerreview', '', $range->max());
        }
        $existing = $DB->get_record('peerreview_override', ['peerreviewid' => $this->peerreview->id, 'userid' => $userid]);
        $record = (object) [
            'peerreviewid' => $this->peerreview->id,
            'userid' => $userid,
            'grade' => $grade,
            'note' => $note,
            'overriddenby' => $byuserid,
            'timemodified' => time(),
        ];
        if ($existing) {
            $record->id = $existing->id;
            $DB->update_record('peerreview_override', $record);
        } else {
            $DB->insert_record('peerreview_override', $record);
        }
        $this->fire($userid, $grade, false);
    }

    /**
     * Remove an override, so the aggregate of the peer reviews applies again. Requires mod/peerreview:overridegrade.
     *
     * @param int $userid The student.
     * @return bool True if there was an override to remove.
     */
    public function revert(int $userid): bool {
        global $DB;

        require_capability('mod/peerreview:overridegrade', $this->context);
        $conditions = ['peerreviewid' => $this->peerreview->id, 'userid' => $userid];
        $existing = $DB->get_record('peerreview_override', $conditions);
        if (!$existing) {
            return false;
        }
        $DB->delete_records('peerreview_override', $conditions);
        $this->fire($userid, (float) $existing->grade, true);
        return true;
    }

    /**
     * Fetch the override of a student.
     *
     * @param int $userid The student.
     * @return \stdClass|null
     */
    public function get(int $userid): ?\stdClass {
        global $DB;
        return $DB->get_record('peerreview_override', ['peerreviewid' => $this->peerreview->id, 'userid' => $userid]) ?: null;
    }

    /**
     * Log the change.
     *
     * @param int $userid The student.
     * @param float $grade The grade set, or the grade that was removed.
     * @param bool $reverted True when the override was removed.
     */
    private function fire(int $userid, float $grade, bool $reverted): void {
        \mod_peerreview\event\grade_overridden::create([
            'objectid' => $this->peerreview->id,
            'context' => $this->context,
            'relateduserid' => $userid,
            'other' => ['grade' => $grade, 'reverted' => $reverted],
        ])->trigger();
    }
}
