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
 * Sends the automatic reminders to the students who still have reviews left.
 *
 * A reminder (a row of peerreview_reminder) is due when the activity is open, its close date is no more than the lead time
 * away and it was not already sent for that close date. When several reminders of one activity are due in the same run (for
 * example the teacher added a reminder whose moment has passed, or the activity just opened), the students get one message,
 * not one for each. The close date is stored in sentfor before anything is sent, so a crash or a rerun never sends twice.
 * A new close date arms every reminder again, a new lead time arms that reminder (see peerreview_update_instance()).
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
     * Find the activities that have reminders due and remind their students.
     */
    public function execute(): void {
        global $DB;

        $now = time();
        $due = $DB->get_records_sql(
            'SELECT r.id, r.peerreviewid
               FROM {peerreview_reminder} r
               JOIN {peerreview} p ON p.id = r.peerreviewid
              WHERE p.timeclose > :now1 AND p.timeclose - r.leadtime <= :now2 AND r.sentfor <> p.timeclose
                    AND (p.timeopen = 0 OR p.timeopen <= :now3)
           ORDER BY r.peerreviewid, r.id',
            ['now1' => $now, 'now2' => $now, 'now3' => $now]
        );
        $byactivity = [];
        foreach ($due as $row) {
            $byactivity[$row->peerreviewid][] = $row->id;
        }
        foreach ($byactivity as $peerreviewid => $reminderids) {
            $peerreview = $DB->get_record('peerreview', ['id' => $peerreviewid], '*', MUST_EXIST);
            $cm = get_coursemodule_from_instance('peerreview', $peerreview->id, $peerreview->course, false, IGNORE_MISSING);
            if (!$cm || !$cm->visible || !empty($cm->deletioninprogress)) {
                continue;
            }
            [$insql, $params] = $DB->get_in_or_equal($reminderids);
            $DB->set_field_select('peerreview_reminder', 'sentfor', $peerreview->timeclose, "id $insql", $params);
            $sent = (new reminder($peerreview, $cm, \context_module::instance($cm->id)))->send_automatic(count($reminderids));
            mtrace("  peerreview {$peerreview->id}: reminded {$sent} students (" . count($reminderids) . ' reminders due)');
        }
    }
}
