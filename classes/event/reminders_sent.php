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
 * The mod_peerreview reminders_sent event.
 *
 * @package    mod_peerreview
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_peerreview\event;

/**
 * Reminders were sent to students with reviews left to do, by a teacher or (other['automatic']) by the scheduled task before
 * the close date. other['count'] is how many students got one; for an automatic one other['due'] is how many of the
 * activity's reminders it covered (several can fall due in the same run, and the students get a single message).
 *
 * @package    mod_peerreview
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class reminders_sent extends \core\event\base {
    /**
     * Initialise the event.
     */
    protected function init(): void {
        $this->data['crud'] = 'r';
        $this->data['edulevel'] = self::LEVEL_TEACHING;
        $this->data['objecttable'] = 'peerreview';
    }

    /**
     * Human readable name.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('eventreminders_sent', 'mod_peerreview');
    }

    /**
     * Description for the log.
     *
     * @return string
     */
    public function get_description(): string {
        if (!empty($this->other['automatic'])) {
            return "Automatic review reminders were sent to {$this->other['count']} students in the peer review with " .
                "course module id '{$this->contextinstanceid}'.";
        }
        return "The user with id '{$this->userid}' sent review reminders to {$this->other['count']} students in the peer " .
            "review with course module id '{$this->contextinstanceid}'.";
    }

    /**
     * Where the activity can be seen.
     *
     * @return \moodle_url
     */
    public function get_url(): \moodle_url {
        return new \moodle_url('/mod/peerreview/view.php', ['id' => $this->contextinstanceid]);
    }

    /**
     * Custom validation.
     *
     * @throws \coding_exception
     */
    protected function validate_data(): void {
        parent::validate_data();
        if (!isset($this->other['count'])) {
            throw new \coding_exception('The \'count\' value must be set in other.');
        }
        if ($this->contextlevel != CONTEXT_MODULE) {
            throw new \coding_exception('Context level must be CONTEXT_MODULE.');
        }
    }

    /**
     * Object id mapping for backup/restore.
     *
     * @return array
     */
    public static function get_objectid_mapping(): array {
        return ['db' => 'peerreview', 'restore' => 'peerreview'];
    }

    /**
     * Other data mapping for backup/restore (nothing to map).
     *
     * @return bool
     */
    public static function get_other_mapping(): bool {
        return false;
    }
}
