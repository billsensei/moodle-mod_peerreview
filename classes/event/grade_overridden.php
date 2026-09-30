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
 * The mod_peerreview grade_overridden event.
 *
 * @package    mod_peerreview
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_peerreview\event;

/**
 * A teacher overrode, or reverted the override of, a student's received grade. The related user is the student.
 *
 * @package    mod_peerreview
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class grade_overridden extends \core\event\base {
    /**
     * Initialise the event.
     */
    protected function init(): void {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_TEACHING;
        $this->data['objecttable'] = 'peerreview';
    }

    /**
     * Human readable name.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('eventgrade_overridden', 'mod_peerreview');
    }

    /**
     * Description for the log.
     *
     * @return string
     */
    public function get_description(): string {
        $what = !empty($this->other['reverted']) ? 'removed the grade override of' : 'overrode the received grade of';
        return "The user with id '{$this->userid}' $what the user with id '{$this->relateduserid}' " .
            "in the peer review with course module id '{$this->contextinstanceid}' (grade {$this->other['grade']}).";
    }

    /**
     * Where the change can be seen.
     *
     * @return \moodle_url
     */
    public function get_url(): \moodle_url {
        return new \moodle_url('/mod/peerreview/report.php', [
            'id' => $this->contextinstanceid,
            'user' => $this->relateduserid,
        ]);
    }

    /**
     * Custom validation.
     *
     * @throws \coding_exception
     */
    protected function validate_data(): void {
        parent::validate_data();
        if (!isset($this->other['grade']) || !isset($this->other['reverted'])) {
            throw new \coding_exception('The \'grade\' and \'reverted\' values must be set in other.');
        }
        if (empty($this->relateduserid)) {
            throw new \coding_exception('The student must be set as relateduserid.');
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
