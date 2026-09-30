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
 * The release / hide feedback button.
 *
 * @package    mod_peerreview
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_peerreview\output;

/**
 * Builds the single button that posts to action.php.
 *
 * @package    mod_peerreview
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class release_button {
    /**
     * Make the button.
     *
     * @param int $cmid Course module id.
     * @param bool $released Whether feedback is currently released.
     * @param string $returnto 'view' or 'report'.
     * @return \single_button
     */
    public static function make(int $cmid, bool $released, string $returnto = 'view'): \single_button {
        return new \single_button(
            new \moodle_url('/mod/peerreview/action.php', [
                'id' => $cmid,
                'action' => $released ? 'hide' : 'release',
                'returnto' => $returnto,
            ]),
            get_string($released ? 'hidefeedback' : 'releasefeedback', 'mod_peerreview'),
            'post',
            $released ? \single_button::BUTTON_SECONDARY : \single_button::BUTTON_PRIMARY
        );
    }
}
