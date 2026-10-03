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
 * Moodle app (mobile) support for mod_peerreview.
 *
 * The activity page is a remote template: the app asks {@see \mod_peerreview\output\mobile::mobile_course_view()}
 * for it. Modelled on the format described in https://moodledev.io/general/app/development/plugins-development-guide.
 *
 * @package    mod_peerreview
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$addons = [
    'mod_peerreview' => [
        'handlers' => [
            'peerreview' => [
                'displaydata' => [
                    'icon' => $CFG->wwwroot . '/mod/peerreview/pix/monologo.svg',
                    'class' => '',
                ],
                'delegate' => 'CoreCourseModuleDelegate',
                'method' => 'mobile_course_view',
            ],
        ],
        'lang' => [
            ['pluginname', 'mod_peerreview'],
        ],
    ],
];
