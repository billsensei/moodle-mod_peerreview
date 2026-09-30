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
 * Tests for the module library callbacks: course reset, deletion and course module info.
 *
 * @package    mod_peerreview
 * @category   test
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_peerreview;

use mod_peerreview\local\allocation\manager;
use mod_peerreview\local\review\service;
use mod_peerreview\tests\activity_trait;
use PHPUnit\Framework\Attributes\CoversFunction;

/**
 * Tests for the module library callbacks.
 *
 * @package    mod_peerreview
 * @category   test
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversFunction('peerreview_reset_userdata')]
#[CoversFunction('peerreview_get_coursemodule_info')]
final class lib_test extends \advanced_testcase {
    use activity_trait;

    /**
     * Load the module library.
     */
    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        require_once($CFG->dirroot . '/mod/peerreview/lib.php');
    }

    /**
     * Course reset removes allocations, reviews (with their rubric data), overrides and the release flag, and clears
     * the gradebook; the activity itself stays.
     */
    public function test_reset_userdata(): void {
        $this->resetAfterTest();
        global $DB;
        $this->create_activity(3, ['feedbackreleased' => 1]);
        $alloc = $this->allocate(1, 2);
        $this->setAdminUser();
        $rubricgen = $this->getDataGenerator()->get_plugin_generator('gradingform_rubric');
        $controller = $rubricgen->get_test_rubric($this->context, 'mod_peerreview', 'received');
        $this->setUser(0);
        (new service($this->peerreview, $this->context))->submit($alloc, (int) $this->students[1]->id, [
            'advancedgrading' => $rubricgen->get_test_form_data($controller, $alloc->id, 2, 'a', 2, 'b'),
        ]);
        $DB->insert_record('peerreview_override', (object) [
            'peerreviewid' => $this->peerreview->id,
            'userid' => $this->students[2]->id,
            'grade' => 10,
            'overriddenby' => $this->teacher->id,
            'timemodified' => time(),
        ]);
        peerreview_update_grades($this->peerreview);
        $this->assertGreaterThan(0, $DB->count_records('grading_instances', ['itemid' => $alloc->id]));

        $status = peerreview_reset_userdata((object) [
            'courseid' => $this->course->id,
            'reset_peerreview_reviews' => 1,
            'reset_peerreview_gradebook' => 1,
        ]);

        $this->assertCount(2, $status);
        $this->assertFalse($status[0]['error']);
        $this->assertSame(0, $DB->count_records('peerreview_alloc'));
        $this->assertSame(0, $DB->count_records('peerreview_override'));
        $this->assertSame(0, $DB->count_records('grading_instances', ['itemid' => $alloc->id]));
        $this->assertSame(0, $DB->count_records('gradingform_rubric_fillings'));
        $this->assertEquals(0, $DB->get_field('peerreview', 'feedbackreleased', ['id' => $this->peerreview->id]));
        $this->assertTrue($DB->record_exists('peerreview', ['id' => $this->peerreview->id]), 'the activity stays');
        $grades = grade_get_grades($this->course->id, 'mod', 'peerreview', $this->peerreview->id, $this->students[2]->id);
        $this->assertNull($grades->items[0]->grades[$this->students[2]->id]->grade ?? null);
    }

    /**
     * Reset does nothing unless asked to.
     */
    public function test_reset_options_are_respected(): void {
        $this->resetAfterTest();
        global $DB;
        $this->create_activity(2);
        $this->allocate(1, 2);
        $status = peerreview_reset_userdata((object) ['courseid' => $this->course->id]);
        $this->assertSame([], $status);
        $this->assertSame(1, $DB->count_records('peerreview_alloc'));
    }

    /**
     * Deleting the activity removes its allocations, overrides and grade items.
     */
    public function test_delete_instance(): void {
        $this->resetAfterTest();
        global $DB;
        $this->create_activity(2);
        $this->allocate(1, 2, manager::STATUS_SUBMITTED, 50);
        course_delete_module($this->cm->id);
        $this->assertSame(0, $DB->count_records('peerreview'));
        $this->assertSame(0, $DB->count_records('peerreview_alloc'));
        $this->assertSame(0, $DB->count_records('grade_items', ['itemmodule' => 'peerreview']));
    }

    /**
     * Course module info carries the custom completion rule only when completion is tracked automatically.
     */
    public function test_coursemodule_info_exposes_the_rule(): void {
        $this->resetAfterTest();
        $this->create_activity(1, [
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionallreviews' => 1,
        ]);
        $cm = get_coursemodule_from_id('peerreview', $this->cm->id, 0, false, MUST_EXIST);
        $info = peerreview_get_coursemodule_info($cm);
        $this->assertSame(1, (int) $info->customdata['customcompletionrules']['completionallreviews']);

        $cm->completion = COMPLETION_TRACKING_NONE;
        $this->assertArrayNotHasKey('customcompletionrules', (array) (peerreview_get_coursemodule_info($cm)->customdata ?? []));
    }
}
