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
 * Restore task for mod_peerreview.
 *
 * Modelled on mod/choice/backup/moodle2/restore_choice_activity_task.class.php. Core's
 * restore_activity_grading_structure_step (added after define_my_steps()) restores the rubric or guide and maps
 * each grading instance's itemid through the grading_item_received mapping set by the structure step.
 *
 * @package    mod_peerreview
 * @category   backup
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/peerreview/backup/moodle2/restore_peerreview_stepslib.php');

/**
 * Restore task: one structure step reading peerreview.xml.
 *
 * @package    mod_peerreview
 * @category   backup
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_peerreview_activity_task extends restore_activity_task {
    /**
     * No settings of its own.
     */
    protected function define_my_settings() {
    }

    /**
     * Add the structure step.
     */
    protected function define_my_steps() {
        $this->add_step(new restore_peerreview_activity_structure_step('peerreview_structure', 'peerreview.xml'));
    }

    /**
     * Content to decode links in.
     *
     * @return restore_decode_content[]
     */
    public static function define_decode_contents() {
        return [new restore_decode_content('peerreview', ['intro'], 'peerreview')];
    }

    /**
     * Link decoding rules.
     *
     * @return restore_decode_rule[]
     */
    public static function define_decode_rules() {
        return [
            new restore_decode_rule('PEERREVIEWVIEWBYID', '/mod/peerreview/view.php?id=$1', 'course_module'),
            new restore_decode_rule('PEERREVIEWINDEX', '/mod/peerreview/index.php?id=$1', 'course'),
        ];
    }

    /**
     * Legacy log rules: none, the module never wrote legacy logs.
     *
     * @return restore_log_rule[]
     */
    public static function define_restore_log_rules() {
        return [];
    }

    /**
     * Legacy course log rules: none.
     *
     * @return restore_log_rule[]
     */
    public static function define_restore_log_rules_for_course() {
        return [];
    }
}
