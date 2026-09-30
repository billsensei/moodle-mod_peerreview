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
 * Renderer for mod_peerreview.
 *
 * @package    mod_peerreview
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_peerreview\output;

/**
 * Renderer for mod_peerreview.
 *
 * @package    mod_peerreview
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renderer extends \plugin_renderer_base {
    /**
     * Render the list of allocations.
     *
     * @param allocation_list $list
     * @return string
     */
    protected function render_allocation_list(allocation_list $list): string {
        return $this->render_from_template('mod_peerreview/allocation_list', $list->export_for_template($this));
    }

    /**
     * Render the allocation preview.
     *
     * @param allocation_preview $preview
     * @return string
     */
    protected function render_allocation_preview(allocation_preview $preview): string {
        return $this->render_from_template('mod_peerreview/allocation_preview', $preview->export_for_template($this));
    }
}
