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
 * Feedback release: teachers switch whether reviewees can see the reviews they received.
 *
 * @package    mod_peerreview
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_peerreview\local;

/**
 * Feedback release switch.
 *
 * @package    mod_peerreview
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class feedback {
    /**
     * Release or hide feedback. Requires mod/peerreview:releasefeedback.
     *
     * @param \stdClass $peerreview Activity record (updated in place).
     * @param \context_module $context Module context.
     * @param bool $released New state.
     */
    public static function set_released(\stdClass $peerreview, \context_module $context, bool $released): void {
        global $DB;

        require_capability('mod/peerreview:releasefeedback', $context);
        if ((bool) $peerreview->feedbackreleased === $released) {
            return;
        }
        $DB->update_record('peerreview', (object) [
            'id' => $peerreview->id,
            'feedbackreleased' => (int) $released,
            'timemodified' => time(),
        ]);
        $peerreview->feedbackreleased = (int) $released;
        \mod_peerreview\event\feedback_released::create([
            'objectid' => $peerreview->id,
            'context' => $context,
            'other' => ['released' => $released],
        ])->trigger();
    }
}
