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
#[CoversFunction('peerreview_update_instance')]
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
     * Changing the maximum points rescales stored review grades and overrides, and refreshes grades already pushed to the
     * gradebook; a grade type change is left alone.
     */
    public function test_update_instance_rescales_grades(): void {
        $this->resetAfterTest();
        global $DB;
        $this->create_activity(3, ['grade' => 100]);
        $alloc = $this->allocate(1, 2, manager::STATUS_SUBMITTED, 80);
        $draft = $this->allocate(3, 2);
        $DB->insert_record('peerreview_override', (object) [
            'peerreviewid' => $this->peerreview->id,
            'userid' => $this->students[3]->id,
            'grade' => 60,
            'overriddenby' => $this->teacher->id,
            'timemodified' => time(),
        ]);
        peerreview_update_grades($this->peerreview);

        $data = clone $this->peerreview;
        $data->instance = $data->id;
        $data->grade = 10;
        $this->assertTrue(peerreview_update_instance($data));

        $this->assertEqualsWithDelta(8, (float) $DB->get_field('peerreview_alloc', 'grade', ['id' => $alloc->id]), 0.0001);
        $this->assertNull($DB->get_field('peerreview_alloc', 'grade', ['id' => $draft->id]));
        $this->assertEqualsWithDelta(
            6,
            (float) $DB->get_field('peerreview_override', 'grade', ['userid' => $this->students[3]->id]),
            0.0001
        );
        $gradeitem = \grade_item::fetch(['itemmodule' => 'peerreview', 'iteminstance' => $this->peerreview->id, 'itemnumber' => 0]);
        $this->assertEqualsWithDelta(10, (float) $gradeitem->grademax, 0.0001);
        $this->assertEqualsWithDelta(
            8,
            (float) $DB->get_field('grade_grades', 'finalgrade', ['itemid' => $gradeitem->id, 'userid' => $this->students[2]->id]),
            0.0001
        );

        // The same maximum again changes nothing.
        $data->grade = 10;
        peerreview_update_instance($data);
        $this->assertEqualsWithDelta(8, (float) $DB->get_field('peerreview_alloc', 'grade', ['id' => $alloc->id]), 0.0001);
    }

    /**
     * The sent state of an activity's reminders, lead time => the close date it was sent for.
     *
     * @param int $id Activity id.
     * @return int[]
     */
    private function sent_state(int $id): array {
        global $DB;
        $state = [];
        foreach ($DB->get_records('peerreview_reminder', ['peerreviewid' => $id], 'leadtime DESC') as $row) {
            $state[(int) $row->leadtime] = (int) $row->sentfor;
        }
        return $state;
    }

    /**
     * A new close date arms every reminder again, a new lead time arms that reminder, and saving the same values does
     * not arm anything.
     */
    public function test_update_instance_rearms_the_automatic_reminders(): void {
        $this->resetAfterTest();
        global $DB;
        $close = time() + 5 * DAYSECS;
        $this->create_activity(1, ['timeclose' => $close, 'reminderlead' => DAYSECS, 'remindersentfor' => $close]);
        $id = $this->peerreview->id;

        $data = clone $this->peerreview;
        $data->instance = $data->id;
        $data->reminderlead = [DAYSECS];
        peerreview_update_instance($data);
        $this->assertSame([DAYSECS => $close], $this->sent_state($id), 'the same values change nothing');

        // The close date moves: armed again.
        $data->timeclose = $close + DAYSECS;
        peerreview_update_instance($data);
        $this->assertSame([DAYSECS => 0], $this->sent_state($id));

        // A second lead time is added to a reminder that was sent: only the new one is armed.
        $DB->set_field('peerreview_reminder', 'sentfor', $data->timeclose, ['peerreviewid' => $id]);
        $data->reminderlead = [2 * DAYSECS, DAYSECS];
        peerreview_update_instance($data);
        $this->assertSame([2 * DAYSECS => 0, DAYSECS => (int) $data->timeclose], $this->sent_state($id));

        // A lead time that is replaced by another one starts unsent; the one that is gone is removed.
        $data->reminderlead = [3 * DAYSECS, DAYSECS];
        peerreview_update_instance($data);
        $this->assertSame([3 * DAYSECS => 0, DAYSECS => (int) $data->timeclose], $this->sent_state($id));

        // A form without the field (it is disabled while there is no close date) leaves the reminders alone.
        unset($data->reminderlead);
        peerreview_update_instance($data);
        $this->assertSame([3 * DAYSECS => 0, DAYSECS => (int) $data->timeclose], $this->sent_state($id));

        // Clearing the list removes them all.
        $data->reminderlead = [0, 0];
        peerreview_update_instance($data);
        $this->assertSame([], $this->sent_state($id));
    }

    /**
     * The duration field can deliver fractions of a second: lead times are stored in whole seconds, and a value that
     * rounds to the stored one keeps that reminder as it was.
     */
    public function test_reminder_lead_is_stored_in_whole_seconds(): void {
        $this->resetAfterTest();
        $close = time() + 5 * DAYSECS;
        $this->create_activity(1, ['timeclose' => $close, 'reminderlead' => 2 * HOURSECS, 'remindersentfor' => $close]);
        $id = $this->peerreview->id;

        $data = clone $this->peerreview;
        $data->instance = $data->id;
        $data->reminderlead = [2 * HOURSECS + 0.4];
        peerreview_update_instance($data);
        $this->assertSame([2 * HOURSECS => $close], $this->sent_state($id), 'still the same reminder, still sent');

        $data->reminderlead = [2 * HOURSECS + 0.6]; // Rounds to 7201: a new lead time, so unsent.
        peerreview_update_instance($data);
        $this->assertSame([2 * HOURSECS + 1 => 0], $this->sent_state($id));

        $new = (object) [
            'course' => $this->course->id, 'name' => 'Rounded', 'intro' => '', 'introformat' => FORMAT_HTML,
            'grade' => 100, 'reminderlead' => 5400.4, 'timeclose' => $close,
        ];
        $newid = peerreview_add_instance($new);
        $this->assertSame([5400 => 0], $this->sent_state($newid));
    }

    /**
     * Deleting the activity deletes its reminders.
     */
    public function test_delete_instance_removes_the_reminders(): void {
        $this->resetAfterTest();
        global $DB;
        $this->create_activity(1, ['timeclose' => time() + DAYSECS, 'reminderlead' => [HOURSECS, 2 * HOURSECS]]);
        $this->assertCount(2, $DB->get_records('peerreview_reminder', ['peerreviewid' => $this->peerreview->id]));

        peerreview_delete_instance($this->peerreview->id);
        $this->assertSame(0, $DB->count_records('peerreview_reminder'));
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
