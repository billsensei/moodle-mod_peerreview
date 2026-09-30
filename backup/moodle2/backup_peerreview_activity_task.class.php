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
 * Backup task for mod_peerreview.
 *
 * Modelled on mod/choice/backup/moodle2/backup_choice_activity_task.class.php. The rubric or marking guide
 * (grading area, definition and, with user data, the grading instances) is backed up by core's
 * backup_activity_grading_structure_step, which backup_activity_task adds because the module supports
 * FEATURE_ADVANCED_GRADING.
 *
 * @package    mod_peerreview
 * @category   backup
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/peerreview/backup/moodle2/backup_peerreview_stepslib.php');

/**
 * Backup task: one structure step writing peerreview.xml.
 *
 * @package    mod_peerreview
 * @category   backup
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_peerreview_activity_task extends backup_activity_task {
    /**
     * No settings of its own.
     */
    protected function define_my_settings() {
    }

    /**
     * Add the structure step.
     */
    protected function define_my_steps() {
        $this->add_step(new backup_peerreview_activity_structure_step('peerreview_structure', 'peerreview.xml'));
    }

    /**
     * Encode links to the activity's pages.
     *
     * @param string $content HTML that may contain links.
     * @return string
     */
    public static function encode_content_links($content) {
        global $CFG;

        $base = preg_quote($CFG->wwwroot, '/');
        $search = '/(' . $base . '\/mod\/peerreview\/index.php\?id\=)([0-9]+)/';
        $content = preg_replace($search, '$@PEERREVIEWINDEX*$2@$', $content);
        $search = '/(' . $base . '\/mod\/peerreview\/view.php\?id\=)([0-9]+)/';
        $content = preg_replace($search, '$@PEERREVIEWVIEWBYID*$2@$', $content);
        return $content;
    }
}
