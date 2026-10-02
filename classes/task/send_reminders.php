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
 * Scheduled task: automatic reminders shortly before the close date.
 *
 * @package    mod_peerreview
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_peerreview\task;

use mod_peerreview\local\reminder;

/**
 * Sends one reminder per activity and close date to the students who still have reviews left.
 *
 * An activity is due when it has a lead time, is open, and the close date is no more than the lead time away. The close date
 * is stored in remindersentfor before anything is sent, so a crash or a rerun never sends twice. Changing the close date or
 * the lead time in the activity settings arms the reminder again (see peerreview_update_instance()).
 *
 * @package    mod_peerreview
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class send_reminders extends \core\task\scheduled_task {
    /**
     * Name shown in the scheduled tasks list.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('tasksendreminders', 'mod_peerreview');
    }

    /**
     * Find the activities that are due and remind their students.
     */
    public function execute(): void {
        global $DB;

        $now = time();
        $due = $DB->get_records_select(
            'peerreview',
            'reminderlead > 0 AND timeclose > :now1 AND timeclose - reminderlead <= :now2 AND remindersentfor <> timeclose '
                . 'AND (timeopen = 0 OR timeopen <= :now3)',
            ['now1' => $now, 'now2' => $now, 'now3' => $now]
        );
        foreach ($due as $peerreview) {
            $cm = get_coursemodule_from_instance('peerreview', $peerreview->id, $peerreview->course, false, IGNORE_MISSING);
            if (!$cm || !$cm->visible || !empty($cm->deletioninprogress)) {
                continue;
            }
            $DB->set_field('peerreview', 'remindersentfor', $peerreview->timeclose, ['id' => $peerreview->id]);
            $sent = (new reminder($peerreview, $cm, \context_module::instance($cm->id)))->send_automatic();
            mtrace("  peerreview {$peerreview->id}: reminded {$sent} students");
        }
    }
}
