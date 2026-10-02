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

namespace mod_peerreview\local\grade;

use mod_peerreview\grades\gradeitems;
use mod_peerreview\local\allocation\manager;
use mod_peerreview\local\progress;
use mod_peerreview\local\review\service;
use mod_peerreview\tests\activity_trait;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * An activity graded with a scale: reviews, aggregation, override, gradebook and display.
 *
 * @package    mod_peerreview
 * @category   test
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(grade_range::class)]
#[CoversClass(gradebook::class)]
#[CoversClass(override::class)]
#[CoversClass(service::class)]
final class scale_test extends \advanced_testcase {
    use activity_trait;

    /** @var int Id of the four item scale (Poor, Fair, Good, Excellent). */
    private int $scaleid;

    /**
     * Load the module library (it defines the gradebook callbacks).
     */
    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        require_once($CFG->dirroot . '/mod/peerreview/lib.php');
        require_once($CFG->libdir . '/gradelib.php');
    }

    /**
     * Create an activity graded with a four item scale.
     *
     * @param int $nstudents Number of students.
     * @param array $activity Extra activity fields.
     */
    private function create_scale_activity(int $nstudents = 3, array $activity = []): void {
        $this->scaleid = (int) $this->getDataGenerator()->create_scale(['scale' => 'Poor, Fair, Good, Excellent'])->id;
        $this->create_activity($nstudents, ['grade' => -$this->scaleid] + $activity);
    }

    /**
     * The gradebook item is a scale item, and the mean of the reviews arrives as the nearest whole item.
     */
    public function test_gradebook_gets_the_nearest_item(): void {
        $this->resetAfterTest();
        $this->create_scale_activity(4);
        $this->allocate(1, 2, manager::STATUS_SUBMITTED, 2);
        $this->allocate(3, 2, manager::STATUS_SUBMITTED, 3);
        $this->allocate(4, 2, manager::STATUS_SUBMITTED, 3); // Mean 2.67: item 3, Good.
        $this->allocate(2, 3, manager::STATUS_SUBMITTED, 4);

        peerreview_update_grades($this->peerreview);

        $item = \grade_item::fetch([
            'courseid' => $this->course->id,
            'itemtype' => 'mod',
            'itemmodule' => 'peerreview',
            'iteminstance' => $this->peerreview->id,
            'itemnumber' => gradeitems::ITEM_RECEIVED,
        ]);
        $this->assertEquals(GRADE_TYPE_SCALE, $item->gradetype);
        $this->assertEquals($this->scaleid, $item->scaleid);
        $grades = grade_get_grades($this->course->id, 'mod', 'peerreview', $this->peerreview->id, [
            $this->students[2]->id,
            $this->students[3]->id,
            $this->students[1]->id,
        ]);
        $this->assertEquals(3, $grades->items[0]->grades[$this->students[2]->id]->grade);
        $this->assertEquals(4, $grades->items[0]->grades[$this->students[3]->id]->grade);
        $this->assertNull($grades->items[0]->grades[$this->students[1]->id]->grade);
    }

    /**
     * The median of two reviews can fall between two items; it is rounded to a whole item.
     */
    public function test_median_is_rounded(): void {
        $this->resetAfterTest();
        $this->create_scale_activity(3, ['aggregation' => aggregator::MEDIAN]);
        $this->allocate(1, 2, manager::STATUS_SUBMITTED, 1);
        $this->allocate(3, 2, manager::STATUS_SUBMITTED, 2);
        peerreview_update_grades($this->peerreview);
        $grades = grade_get_grades($this->course->id, 'mod', 'peerreview', $this->peerreview->id, $this->students[2]->id);
        $this->assertEquals(2, $grades->items[0]->grades[$this->students[2]->id]->grade, '1.5 rounds up');
    }

    /**
     * The simple form takes the position of a scale item; anything else is refused.
     */
    public function test_simple_review_with_a_scale(): void {
        $this->resetAfterTest();
        global $DB;
        $this->create_scale_activity();
        $alloc = $this->allocate(1, 2);
        $service = new service($this->peerreview, $this->context);
        $this->assertTrue($service->get_range()->is_scale());
        foreach ([0, 5, 2.5, 'x'] as $bad) {
            try {
                $service->submit($alloc, (int) $this->students[1]->id, ['score' => $bad]);
                $this->fail("Expected a refusal for $bad");
            } catch (\moodle_exception $e) {
                $this->assertSame('errorscaleitem', $e->errorcode);
            }
        }
        $service->submit($alloc, (int) $this->students[1]->id, ['score' => '3']);
        $this->assertEquals(3, $DB->get_field('peerreview_alloc', 'grade', ['id' => $alloc->id]));
    }

    /**
     * A rubric on an activity graded with a scale gives a position inside the scale.
     */
    public function test_rubric_with_a_scale(): void {
        $this->resetAfterTest();
        global $DB;
        $this->create_scale_activity();
        $alloc = $this->allocate(1, 2);
        $this->setAdminUser();
        $rubricgen = $this->getDataGenerator()->get_plugin_generator('gradingform_rubric');
        $controller = $rubricgen->get_test_rubric($this->context, 'mod_peerreview', 'received');
        $this->setUser(0);
        $service = new service($this->peerreview, $this->context);
        $service->submit($alloc, (int) $this->students[1]->id, [
            'advancedgrading' => $rubricgen->get_test_form_data($controller, $alloc->id, 2, 'a', 2, 'b'),
        ]);
        $grade = (float) $DB->get_field('peerreview_alloc', 'grade', ['id' => $alloc->id]);
        $this->assertEquals(4.0, $grade, 'top levels give the top item');
        $this->assertSame('Excellent', $service->get_range()->format($grade));
    }

    /**
     * Overrides are scale items too.
     */
    public function test_override_with_a_scale(): void {
        $this->resetAfterTest();
        $this->create_scale_activity();
        $this->setUser($this->teacher);
        $override = new override($this->peerreview, $this->context);
        $userid = (int) $this->students[1]->id;
        foreach ([0, 5, 1.5] as $bad) {
            try {
                $override->set($userid, $bad, '', (int) $this->teacher->id);
                $this->fail("Expected a refusal for $bad");
            } catch (\moodle_exception $e) {
                $this->assertSame('errorscaleitem', $e->errorcode);
            }
        }
        $override->set($userid, 4, 'excellent presentation', (int) $this->teacher->id);
        $this->assertEquals(4, $override->get($userid)->grade);
        $this->allocate(2, 1, manager::STATUS_SUBMITTED, 1);
        peerreview_update_grades($this->peerreview);
        $grades = grade_get_grades($this->course->id, 'mod', 'peerreview', $this->peerreview->id, $userid);
        $this->assertEquals(4, $grades->items[0]->grades[$userid]->grade, 'override wins');
    }

    /**
     * The progress table, the student page and the teacher report show item names, not numbers.
     */
    public function test_item_names_are_shown(): void {
        $this->resetAfterTest();
        $this->create_scale_activity(3, ['feedbackreleased' => 1]);
        $this->allocate(1, 2, manager::STATUS_SUBMITTED, 4);
        $range = grade_range::for_activity($this->peerreview);
        $rows = (new progress($this->peerreview, $this->manager))->get_rows(0);
        $this->assertSame('Excellent', $range->format($rows[$this->students[2]->id]->grade));

        $this->setUser($this->teacher);
        $table = new \mod_peerreview\output\report_table($this->cm->id, 0, $rows, false, $range);
        $data = $table->export_for_template($this->getMockBuilder(\renderer_base::class)->disableOriginalConstructor()->getMock());
        $byid = array_column($data->rows, null, 'userid');
        $this->assertSame('Excellent', $byid[$this->students[2]->id]['grade']);
        $this->assertSame(4, $data->maxgrade);
    }

    /**
     * Scales in use are reported to core so that they cannot be deleted from under the activity.
     */
    public function test_scale_used(): void {
        $this->resetAfterTest();
        $this->create_scale_activity();
        $this->assertTrue(peerreview_scale_used_anywhere($this->scaleid));
        $this->assertTrue(peerreview_scale_used($this->peerreview->id, $this->scaleid));
        $this->assertFalse(peerreview_scale_used($this->peerreview->id, $this->scaleid + 1000));
        $this->assertFalse(peerreview_scale_used_anywhere($this->scaleid + 1000));
        $this->assertFalse(peerreview_scale_used_anywhere(0));
    }
}
