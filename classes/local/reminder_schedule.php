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
 * The automatic reminders of one activity.
 *
 * @package    mod_peerreview
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_peerreview\local;

/**
 * The lead times of an activity's automatic reminders, and which of them were already sent.
 *
 * Each reminder is a row of peerreview_reminder: how long before the close date it is sent (leadtime, in seconds) and the
 * close date it was sent for (sentfor, 0 while it has not been sent). Changing the list keeps the rows whose lead time did
 * not change, so a reminder that was already sent is not sent again; a new close date arms every reminder again.
 *
 * @package    mod_peerreview
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class reminder_schedule {
    /** @var int The most reminders one activity can have. */
    public const MAX = 5;

    /** @var int The shortest lead time in seconds: the reminder task runs once an hour. */
    public const MIN_LEAD = HOURSECS;

    /** @var int The longest lead time in seconds. */
    public const MAX_LEAD = 52 * WEEKSECS;

    /**
     * Constructor.
     *
     * @param int $peerreviewid Activity id.
     */
    public function __construct(
        /** @var int Activity id. */
        private readonly int $peerreviewid
    ) {
    }

    /**
     * Clean a list of lead times: whole seconds, no zero or negative values, no repeats, longest first.
     *
     * @param array $leads Lead times in seconds (fractions are rounded).
     * @return int[]
     */
    public static function normalise(array $leads): array {
        $clean = [];
        foreach ($leads as $lead) {
            $seconds = (int) round((float) $lead);
            if ($seconds > 0) {
                $clean[$seconds] = $seconds;
            }
        }
        rsort($clean);
        return $clean;
    }

    /**
     * Problems with the lead times entered in the settings form.
     *
     * Empty entries (0) are ignored. An entry is refused when it is shorter than MIN_LEAD, longer than MAX_LEAD, a repeat
     * of an earlier one, or beyond the MAX-th.
     *
     * @param array $leads Entered lead times in seconds, keyed by their position in the form.
     * @return string[] Lang string identifier (component mod_peerreview) keyed by the position of the entry with the problem.
     */
    public static function check(array $leads): array {
        $errors = [];
        $seen = [];
        foreach ($leads as $position => $lead) {
            if (empty($lead)) {
                continue;
            }
            $seconds = (int) round((float) $lead);
            if ($seconds < self::MIN_LEAD) {
                $errors[$position] = 'reminderleadtoosmall';
            } else if ($seconds > self::MAX_LEAD) {
                $errors[$position] = 'reminderleadtoolarge';
            } else if (isset($seen[$seconds])) {
                $errors[$position] = 'reminderleadduplicate';
            } else if (count($seen) >= self::MAX) {
                $errors[$position] = 'remindertoomany';
            } else {
                $seen[$seconds] = true;
            }
        }
        return $errors;
    }

    /**
     * The lead times of the activity, longest first.
     *
     * @return int[]
     */
    public function get_leads(): array {
        global $DB;

        $leads = $DB->get_fieldset_sql(
            'SELECT leadtime FROM {peerreview_reminder} WHERE peerreviewid = :id ORDER BY leadtime DESC',
            ['id' => $this->peerreviewid]
        );
        return array_map('intval', $leads);
    }

    /**
     * Make the activity's reminders match a list of lead times.
     *
     * A lead time that is already there keeps its row, and with it whether it was sent. New lead times are added as not
     * sent, and lead times that are no longer in the list are removed.
     *
     * @param array $leads Lead times in seconds; see normalise().
     * @throws \coding_exception When there are more than MAX.
     */
    public function save(array $leads): void {
        global $DB;

        $leads = self::normalise($leads);
        if (count($leads) > self::MAX) {
            throw new \coding_exception('An activity can have at most ' . self::MAX . ' automatic reminders');
        }
        $existing = $DB->get_records('peerreview_reminder', ['peerreviewid' => $this->peerreviewid], '', 'leadtime, id');
        foreach ($existing as $leadtime => $row) {
            if (!in_array((int) $leadtime, $leads, true)) {
                $DB->delete_records('peerreview_reminder', ['id' => $row->id]);
            }
        }
        foreach ($leads as $lead) {
            if (!isset($existing[$lead])) {
                $DB->insert_record('peerreview_reminder', (object) [
                    'peerreviewid' => $this->peerreviewid,
                    'leadtime' => $lead,
                    'sentfor' => 0,
                ]);
            }
        }
    }

    /**
     * Arm every reminder again (the close date changed).
     */
    public function rearm(): void {
        global $DB;

        $DB->set_field('peerreview_reminder', 'sentfor', 0, ['peerreviewid' => $this->peerreviewid]);
    }

    /**
     * Remove all the activity's reminders.
     */
    public function delete(): void {
        global $DB;

        $DB->delete_records('peerreview_reminder', ['peerreviewid' => $this->peerreviewid]);
    }
}
