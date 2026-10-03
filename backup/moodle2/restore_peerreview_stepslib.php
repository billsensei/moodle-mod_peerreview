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
 * Restore structure step for mod_peerreview.
 *
 * Modelled on mod/choice/backup/moodle2/restore_choice_stepslib.php and, for the grading item mapping,
 * mod/assign/backup/moodle2/restore_assign_stepslib.php (process_assign_grade).
 *
 * @package    mod_peerreview
 * @category   backup
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Restore the activity, its allocations (reviews) and grade overrides.
 *
 * @package    mod_peerreview
 * @category   backup
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_peerreview_activity_structure_step extends restore_activity_structure_step {
    /**
     * Paths to process.
     *
     * @return restore_path_element[]
     */
    protected function define_structure() {
        $paths = [
            new restore_path_element('peerreview', '/activity/peerreview'),
            new restore_path_element('peerreview_reminder', '/activity/peerreview/reminders/reminder'),
        ];
        if ($this->get_setting_value('userinfo')) {
            $paths[] = new restore_path_element('peerreview_alloc', '/activity/peerreview/allocations/allocation');
            $paths[] = new restore_path_element('peerreview_override', '/activity/peerreview/overrides/override');
        }
        return $this->prepare_activity_structure($paths);
    }

    /**
     * Restore the activity record.
     *
     * @param array $data Parsed data.
     */
    protected function process_peerreview($data) {
        global $DB;

        $data = (object) $data;
        $data->course = $this->get_courseid();
        $data->timeopen = $this->apply_date_offset($data->timeopen);
        $data->timeclose = $this->apply_date_offset($data->timeclose);
        if ($data->grade < 0) {
            // A scale: the grade setting is minus the scale id, which may have a new id in the target course.
            $data->grade = -($this->get_mappingid('scale', abs($data->grade)));
        }
        if (!$this->get_setting_value('userinfo')) {
            // No reviews come with the activity, so there is nothing released.
            $data->feedbackreleased = 0;
        }
        // Backups from 0.12 and 0.13 hold the one reminder in the activity itself (reminderlead, in seconds).
        $legacylead = (int) ($data->reminderlead ?? 0);
        unset($data->reminderlead, $data->remindersentfor);
        $newitemid = $DB->insert_record('peerreview', $data);
        if ($legacylead > 0) {
            (new \mod_peerreview\local\reminder_schedule($newitemid))->save([$legacylead]);
        }
        $this->apply_activity_instance($newitemid);
    }

    /**
     * Restore one automatic reminder. A restored activity has not sent it yet.
     *
     * @param array $data Parsed data.
     */
    protected function process_peerreview_reminder($data) {
        $data = (object) $data;
        $schedule = new \mod_peerreview\local\reminder_schedule($this->get_new_parentid('peerreview'));
        $schedule->save(array_merge($schedule->get_leads(), [(int) $data->leadtime]));
    }

    /**
     * Restore one allocation. Its id is the advanced grading itemid, so both mappings are set.
     *
     * @param array $data Parsed data.
     */
    protected function process_peerreview_alloc($data) {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;
        $data->peerreviewid = $this->get_new_parentid('peerreview');
        $data->reviewerid = $this->get_mappingid('user', $data->reviewerid);
        $data->revieweeid = $this->get_mappingid('user', $data->revieweeid);
        if (!$data->reviewerid || !$data->revieweeid) {
            return; // A user who is not in the target site; the review cannot be kept.
        }
        $data->allocatedby = $this->get_mappingid('user', $data->allocatedby, 0);
        $data->groupid = $data->groupid ? $this->get_mappingid('group', $data->groupid, 0) : 0;

        $newitemid = $DB->insert_record('peerreview_alloc', $data);
        $this->set_mapping('peerreview_alloc', $oldid, $newitemid);
        $this->set_mapping(restore_gradingform_plugin::itemid_mapping('received'), $oldid, $newitemid);
    }

    /**
     * Restore one grade override.
     *
     * @param array $data Parsed data.
     */
    protected function process_peerreview_override($data) {
        global $DB;

        $data = (object) $data;
        $data->peerreviewid = $this->get_new_parentid('peerreview');
        $data->userid = $this->get_mappingid('user', $data->userid);
        if (!$data->userid) {
            return;
        }
        $data->overriddenby = $this->get_mappingid('user', $data->overriddenby, 0);
        $DB->insert_record('peerreview_override', $data);
    }

    /**
     * Restore the intro files.
     */
    protected function after_execute() {
        $this->add_related_files('mod_peerreview', 'intro', null);
    }
}
