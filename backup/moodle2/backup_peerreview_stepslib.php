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
 * Backup structure step for mod_peerreview.
 *
 * Modelled on mod/choice/backup/moodle2/backup_choice_stepslib.php and mod/assign/backup/moodle2/backup_assign_stepslib.php.
 *
 * @package    mod_peerreview
 * @category   backup
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Structure: peerreview -> allocations/allocation, overrides/override (both only with user data).
 *
 * @package    mod_peerreview
 * @category   backup
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_peerreview_activity_structure_step extends backup_activity_structure_step {
    /**
     * Define the structure.
     *
     * @return backup_nested_element
     */
    protected function define_structure() {
        $userinfo = $this->get_setting_value('userinfo');

        $peerreview = new backup_nested_element('peerreview', ['id'], [
            'name', 'intro', 'introformat', 'grade', 'gradeparticipation', 'aggregation', 'anonymous',
            'allowselfreview', 'feedbackreleased', 'timeopen', 'timeclose', 'reminderlead', 'completionallreviews',
            'timecreated', 'timemodified',
        ]);

        $allocations = new backup_nested_element('allocations');
        $allocation = new backup_nested_element('allocation', ['id'], [
            'reviewerid', 'revieweeid', 'groupid', 'status', 'grade', 'feedback', 'feedbackformat',
            'allocatedby', 'timecreated', 'timemodified', 'timesubmitted',
        ]);

        $overrides = new backup_nested_element('overrides');
        $override = new backup_nested_element('override', ['id'], [
            'userid', 'grade', 'note', 'overriddenby', 'timemodified',
        ]);

        $peerreview->add_child($allocations);
        $allocations->add_child($allocation);
        $peerreview->add_child($overrides);
        $overrides->add_child($override);

        $peerreview->set_source_table('peerreview', ['id' => backup::VAR_ACTIVITYID]);
        if ($userinfo) {
            $allocation->set_source_table('peerreview_alloc', ['peerreviewid' => backup::VAR_PARENTID], 'id ASC');
            $override->set_source_table('peerreview_override', ['peerreviewid' => backup::VAR_PARENTID], 'id ASC');
        }

        $allocation->annotate_ids('user', 'reviewerid');
        $allocation->annotate_ids('user', 'revieweeid');
        $allocation->annotate_ids('user', 'allocatedby');
        $allocation->annotate_ids('group', 'groupid');
        $override->annotate_ids('user', 'userid');
        $override->annotate_ids('user', 'overriddenby');

        $peerreview->annotate_files('mod_peerreview', 'intro', null);

        return $this->prepare_activity_structure($peerreview);
    }
}
