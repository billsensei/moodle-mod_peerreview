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
 * Web service definitions for mod_peerreview.
 *
 * @package    mod_peerreview
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'mod_peerreview_get_progress' => [
        'classname' => 'mod_peerreview\external\get_progress',
        'methodname' => 'execute',
        'description' => 'Per-student review progress for the teacher report (live refresh).',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'mod/peerreview:viewallreviews',
    ],
    'mod_peerreview_set_feedback_release' => [
        'classname' => 'mod_peerreview\external\set_feedback_release',
        'methodname' => 'execute',
        'description' => 'Release feedback to reviewees or hide it again.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'mod/peerreview:releasefeedback',
    ],
];
