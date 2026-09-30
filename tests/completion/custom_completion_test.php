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
 * Tests for the "submit all assigned reviews" completion rule.
 *
 * @package    mod_peerreview
 * @category   test
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_peerreview\completion;

use mod_peerreview\local\allocation\manager;
use mod_peerreview\local\review\service;
use mod_peerreview\tests\activity_trait;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the "submit all assigned reviews" completion rule.
 *
 * @package    mod_peerreview
 * @category   test
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(custom_completion::class)]
#[CoversClass(\mod_peerreview\local\completion::class)]
final class custom_completion_test extends \advanced_testcase {
    use activity_trait;

    /**
     * Load the completion library (defines the COMPLETION_ constants).
     */
    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        require_once($CFG->libdir . '/completionlib.php');
    }

    /**
     * Create an activity that tracks completion with the rule switched on.
     *
     * @param int $nstudents Students.
     */
    private function create_tracked_activity(int $nstudents): void {
        $this->create_activity($nstudents, [
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionallreviews' => 1,
            'allowselfreview' => 1,
        ]);
    }

    /**
     * Rule state as the completion API reports it.
     *
     * @param int $student Student index.
     * @return int COMPLETION_xxx state.
     */
    private function state(int $student): int {
        $course = get_course($this->course->id);
        $cm = get_fast_modinfo($course)->get_cm($this->cm->id);
        return (new custom_completion($cm, (int) $this->students[$student]->id))->get_state('completionallreviews');
    }

    /**
     * Rule definition and descriptions.
     */
    public function test_rule_definition(): void {
        $this->resetAfterTest();
        $this->create_tracked_activity(1);
        $this->assertSame(['completionallreviews'], custom_completion::get_defined_custom_rules());
        $cm = get_fast_modinfo(get_course($this->course->id))->get_cm($this->cm->id);
        $descriptions = (new custom_completion($cm, (int) $this->students[1]->id))->get_custom_rule_descriptions();
        $this->assertSame(get_string('completionallreviews_desc', 'mod_peerreview'), $descriptions['completionallreviews']);
    }

    /**
     * With nothing assigned the rule is not met (otherwise students complete before the allocation).
     */
    public function test_not_met_without_assignments(): void {
        $this->resetAfterTest();
        $this->create_tracked_activity(2);
        $this->assertSame(COMPLETION_INCOMPLETE, $this->state(1));
    }

    /**
     * Met only when every assigned review is submitted; drafts do not count.
     */
    public function test_met_when_all_submitted(): void {
        $this->resetAfterTest();
        $this->create_tracked_activity(3);
        $this->allocate(1, 2, manager::STATUS_SUBMITTED, 70);
        $this->allocate(1, 3, manager::STATUS_DRAFT, 30);
        $this->assertSame(COMPLETION_INCOMPLETE, $this->state(1));

        $this->allocate(1, 3, manager::STATUS_SUBMITTED, 30);
        $this->assertSame(COMPLETION_COMPLETE, $this->state(1));
        $this->assertSame(COMPLETION_INCOMPLETE, $this->state(2), 'student 2 was given nothing to review');
    }

    /**
     * Submitting the last review completes the activity for the reviewer, through the service, and a newly added
     * allocation makes it incomplete again.
     */
    public function test_completion_follows_submits_and_new_allocations(): void {
        $this->resetAfterTest();
        $this->create_tracked_activity(3);
        $alloc = $this->allocate(1, 2);
        $course = get_course($this->course->id);
        $completion = new \completion_info($course);
        $cm = get_coursemodule_from_id('peerreview', $this->cm->id);
        $userid = (int) $this->students[1]->id;
        $this->assertEquals(COMPLETION_INCOMPLETE, $completion->get_data($cm, false, $userid)->completionstate);

        (new service($this->peerreview, $this->context))->submit($alloc, $userid, ['score' => 80]);
        $this->assertEquals(COMPLETION_COMPLETE, $completion->get_data($cm, false, $userid)->completionstate);

        $this->manager->add_manual($userid, [(int) $this->students[3]->id], (int) $this->teacher->id);
        $this->assertEquals(COMPLETION_INCOMPLETE, $completion->get_data($cm, false, $userid)->completionstate);
    }

    /**
     * Without the rule enabled nothing is updated (and nothing breaks).
     */
    public function test_no_update_when_rule_disabled(): void {
        $this->resetAfterTest();
        $this->create_activity(2, ['completionallreviews' => 0]);
        $alloc = $this->allocate(1, 2);
        (new service($this->peerreview, $this->context))->submit($alloc, (int) $this->students[1]->id, ['score' => 10]);
        $cm = get_coursemodule_from_id('peerreview', $this->cm->id);
        $data = (new \completion_info(get_course($this->course->id)))->get_data($cm, false, (int) $this->students[1]->id);
        $this->assertEquals(COMPLETION_INCOMPLETE, $data->completionstate);
    }
}
