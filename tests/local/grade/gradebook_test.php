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
 * Tests for pushing grades to the gradebook.
 *
 * @package    mod_peerreview
 * @category   test
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_peerreview\local\grade;

use mod_peerreview\grades\gradeitems;
use mod_peerreview\local\allocation\manager;
use mod_peerreview\tests\activity_trait;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for pushing grades to the gradebook.
 *
 * @package    mod_peerreview
 * @category   test
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(gradebook::class)]
final class gradebook_test extends \advanced_testcase {
    use activity_trait;

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
     * What the gradebook holds for a student.
     *
     * @param int $itemnumber Grade item number.
     * @param int $userid Student id.
     * @return float|null The final grade, null when there is none.
     */
    private function gradebook_grade(int $itemnumber, int $userid): ?float {
        $grades = grade_get_grades($this->course->id, 'mod', 'peerreview', $this->peerreview->id, $userid);
        $item = $grades->items[$itemnumber] ?? null;
        if (!$item || !isset($item->grades[$userid]) || $item->grades[$userid]->grade === null) {
            return null;
        }
        return (float) $item->grades[$userid]->grade;
    }

    /**
     * The received grade item is created with the activity maximum; the participation item only when enabled.
     */
    public function test_grade_items(): void {
        $this->resetAfterTest();
        global $DB;
        $this->create_activity(2, ['grade' => 80, 'gradeparticipation' => 0]);
        $params = ['courseid' => $this->course->id, 'itemtype' => 'mod', 'itemmodule' => 'peerreview',
            'iteminstance' => $this->peerreview->id];
        $received = \grade_item::fetch($params + ['itemnumber' => gradeitems::ITEM_RECEIVED]);
        $this->assertEquals(80, $received->grademax);
        $this->assertFalse(\grade_item::fetch($params + ['itemnumber' => gradeitems::ITEM_PARTICIPATION]));

        $this->peerreview->gradeparticipation = 20;
        $this->peerreview->grade = 80;
        peerreview_grade_item_update($this->peerreview);
        $participation = \grade_item::fetch($params + ['itemnumber' => gradeitems::ITEM_PARTICIPATION]);
        $this->assertEquals(20, $participation->grademax);
        $this->assertSame(2, $DB->count_records('grade_items', $params));
    }

    /**
     * Received grades are the mean of submitted reviews; nobody is sent a zero for having no reviews.
     */
    public function test_push_received_grades(): void {
        $this->resetAfterTest();
        $this->create_activity(4, ['allowselfreview' => 1]);
        $this->allocate(1, 2, manager::STATUS_SUBMITTED, 80);
        $this->allocate(3, 2, manager::STATUS_SUBMITTED, 60);
        $this->allocate(4, 2, manager::STATUS_DRAFT, 10);      // Draft: not counted.
        $this->allocate(2, 2, manager::STATUS_SUBMITTED, 100); // Self review: not counted.
        $this->allocate(2, 3, manager::STATUS_SUBMITTED, 90);

        peerreview_update_grades($this->peerreview);

        $this->assertEquals(70.0, $this->gradebook_grade(gradeitems::ITEM_RECEIVED, $this->students[2]->id));
        $this->assertEquals(90.0, $this->gradebook_grade(gradeitems::ITEM_RECEIVED, $this->students[3]->id));
        $this->assertNull($this->gradebook_grade(gradeitems::ITEM_RECEIVED, $this->students[1]->id), 'no grade, not zero');
        $this->assertNull($this->gradebook_grade(gradeitems::ITEM_RECEIVED, $this->students[4]->id), 'only a draft received');
    }

    /**
     * An override replaces the calculated grade in the gradebook; removing it brings the calculated grade back.
     */
    public function test_override_reaches_gradebook_and_can_be_reverted(): void {
        $this->resetAfterTest();
        $this->create_activity(3);
        $this->allocate(1, 2, manager::STATUS_SUBMITTED, 50);
        $this->allocate(3, 2, manager::STATUS_SUBMITTED, 70);
        $userid = (int) $this->students[2]->id;
        peerreview_update_grades($this->peerreview);
        $this->assertEquals(60.0, $this->gradebook_grade(gradeitems::ITEM_RECEIVED, $userid));

        $this->setUser($this->teacher);
        $override = new override($this->peerreview, $this->context);
        $override->set($userid, 95, 'presented twice', (int) $this->teacher->id);
        peerreview_update_grades($this->peerreview);
        $this->assertEquals(95.0, $this->gradebook_grade(gradeitems::ITEM_RECEIVED, $userid));

        $this->assertTrue($override->revert($userid));
        peerreview_update_grades($this->peerreview);
        $this->assertEquals(60.0, $this->gradebook_grade(gradeitems::ITEM_RECEIVED, $userid));
    }

    /**
     * A student whose grade stops applying (all their reviews removed) is cleared, not left with the old grade.
     */
    public function test_grade_is_cleared_when_it_no_longer_applies(): void {
        $this->resetAfterTest();
        $this->create_activity(2);
        $alloc = $this->allocate(1, 2, manager::STATUS_SUBMITTED, 88);
        $userid = (int) $this->students[2]->id;
        peerreview_update_grades($this->peerreview);
        $this->assertEquals(88.0, $this->gradebook_grade(gradeitems::ITEM_RECEIVED, $userid));

        $this->manager->delete([$alloc->id], true);
        peerreview_update_grades($this->peerreview);
        $this->assertNull($this->gradebook_grade(gradeitems::ITEM_RECEIVED, $userid));
    }

    /**
     * Participation: percentage of assigned reviews submitted, scaled to the participation maximum.
     */
    public function test_participation_grades(): void {
        $this->resetAfterTest();
        $this->create_activity(4, ['gradeparticipation' => 20]);
        $this->allocate(1, 2, manager::STATUS_SUBMITTED, 70);
        $this->allocate(1, 3, manager::STATUS_SUBMITTED, 70);
        $this->allocate(1, 4, manager::STATUS_SUBMITTED, 70);
        $this->allocate(2, 1, manager::STATUS_SUBMITTED, 40);
        $this->allocate(2, 3);
        $this->allocate(2, 4);

        peerreview_update_grades($this->peerreview);

        $this->assertEqualsWithDelta(20.0, $this->gradebook_grade(gradeitems::ITEM_PARTICIPATION, $this->students[1]->id), 0.001);
        $this->assertEqualsWithDelta(
            20 / 3,
            $this->gradebook_grade(gradeitems::ITEM_PARTICIPATION, $this->students[2]->id),
            0.001
        );
        $this->assertNull(
            $this->gradebook_grade(gradeitems::ITEM_PARTICIPATION, $this->students[3]->id),
            'nothing assigned to give, so no participation grade'
        );
    }

    /**
     * Pushing one user leaves the others untouched.
     */
    public function test_push_single_user(): void {
        $this->resetAfterTest();
        $this->create_activity(3);
        $this->allocate(1, 2, manager::STATUS_SUBMITTED, 40);
        $this->allocate(1, 3, manager::STATUS_SUBMITTED, 60);
        (new gradebook($this->peerreview))->push((int) $this->students[2]->id);
        $this->assertEquals(40.0, $this->gradebook_grade(gradeitems::ITEM_RECEIVED, $this->students[2]->id));
        $this->assertNull($this->gradebook_grade(gradeitems::ITEM_RECEIVED, $this->students[3]->id));
    }
}
