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
 * The mod_peerreview allocation_created event.
 *
 * @package    mod_peerreview
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_peerreview\event;

/**
 * A reviewer to reviewee allocation was created. The related user is the reviewee, "reviewerid" is in other.
 *
 * @package    mod_peerreview
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class allocation_created extends \core\event\base {
    /**
     * Initialise the event.
     */
    protected function init(): void {
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_TEACHING;
        $this->data['objecttable'] = 'peerreview_alloc';
    }

    /**
     * Human readable name.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('eventallocation_created', 'mod_peerreview');
    }

    /**
     * Description for the log.
     *
     * @return string
     */
    public function get_description(): string {
        return "The user with id '{$this->userid}' created the allocation with id '{$this->objectid}' " .
            "(reviewer '{$this->other['reviewerid']}', reviewee '{$this->relateduserid}') in the peer review " .
            "with course module id '{$this->contextinstanceid}'.";
    }

    /**
     * Where the user can see the result.
     *
     * @return \moodle_url
     */
    public function get_url(): \moodle_url {
        return new \moodle_url('/mod/peerreview/allocate.php', ['id' => $this->contextinstanceid]);
    }

    /**
     * Custom validation.
     *
     * @throws \coding_exception
     */
    protected function validate_data(): void {
        parent::validate_data();
        if (!isset($this->other['reviewerid'])) {
            throw new \coding_exception('The \'reviewerid\' value must be set in other.');
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
        return ['db' => 'peerreview_alloc', 'restore' => 'peerreview_alloc'];
    }

    /**
     * Other data mapping for backup/restore.
     *
     * @return array
     */
    public static function get_other_mapping(): array {
        return ['reviewerid' => ['db' => 'user', 'restore' => 'user']];
    }
}
