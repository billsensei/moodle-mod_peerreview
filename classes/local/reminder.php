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
 * Reminders: a message to each student who still has peer reviews to do.
 *
 * @package    mod_peerreview
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_peerreview\local;

use core\message\message;
use mod_peerreview\local\allocation\manager;

/**
 * Sends the reminders. Manual only: a teacher presses the button on the report, so nothing is sent behind their back.
 *
 * Modelled on how mod/assign sends notifications (assign::send_notification() with message_send()).
 *
 * @package    mod_peerreview
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class reminder {
    /**
     * Constructor.
     *
     * @param \stdClass $peerreview Activity record.
     * @param \stdClass $cm Course module record.
     * @param \context_module $context Module context.
     */
    public function __construct(
        /** @var \stdClass Activity record. */
        private readonly \stdClass $peerreview,
        /** @var \stdClass Course module record. */
        private readonly \stdClass $cm,
        /** @var \context_module Module context. */
        private readonly \context_module $context
    ) {
    }

    /**
     * Whether students can review right now (inside the open and close dates).
     *
     * @return bool
     */
    public function is_open(): bool {
        $now = time();
        return (!$this->peerreview->timeopen || $now >= $this->peerreview->timeopen)
            && (!$this->peerreview->timeclose || $now < $this->peerreview->timeclose);
    }

    /**
     * Remind every student in scope who has submitted fewer reviews than assigned. Requires mod/peerreview:allocate.
     *
     * @param int $groupid Only members of this group (0 for everyone, -1 for nobody).
     * @param int $fromuserid The teacher the messages come from.
     * @return int How many students were sent a message.
     * @throws \moodle_exception When the activity is not open.
     */
    public function send(int $groupid, int $fromuserid): int {
        global $DB;

        require_capability('mod/peerreview:allocate', $this->context);
        if (!$this->is_open()) {
            throw new \moodle_exception('remindernotopen', 'mod_peerreview');
        }
        $rows = (new progress($this->peerreview, new manager($this->peerreview, $this->cm, $this->context)))->get_rows($groupid);
        $from = \core_user::get_user($fromuserid, '*', MUST_EXIST);
        $url = new \moodle_url('/mod/peerreview/view.php', ['id' => $this->cm->id]);
        $sent = 0;
        foreach ($rows as $row) {
            $remaining = $row->given_total - $row->given_done;
            if ($remaining <= 0) {
                continue;
            }
            $a = (object) [
                'firstname' => $row->user->firstname,
                'activity' => format_string($this->peerreview->name, true, ['context' => $this->context]),
                'remaining' => $remaining,
                'total' => $row->given_total,
                'url' => $url->out(false),
            ];
            $message = new message();
            $message->component = 'mod_peerreview';
            $message->name = 'reminder';
            $message->userfrom = $from;
            $message->userto = $DB->get_record('user', ['id' => $row->userid], '*', MUST_EXIST);
            $message->subject = get_string('remindersubject', 'mod_peerreview', $a->activity);
            $message->fullmessage = get_string('remindermessage', 'mod_peerreview', $a);
            $message->fullmessageformat = FORMAT_PLAIN;
            $message->fullmessagehtml = text_to_html($message->fullmessage, false, false, true);
            $message->smallmessage = get_string('remindersmall', 'mod_peerreview', $a);
            $message->notification = 1;
            $message->contexturl = $url;
            $message->contexturlname = $a->activity;
            $message->courseid = $this->peerreview->course;
            if (message_send($message)) {
                $sent++;
            }
        }
        \mod_peerreview\event\reminders_sent::create([
            'objectid' => $this->peerreview->id,
            'context' => $this->context,
            'other' => ['count' => $sent, 'groupid' => $groupid],
        ])->trigger();
        return $sent;
    }
}
