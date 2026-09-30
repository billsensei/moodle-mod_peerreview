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
 * Backup and restore round trip for mod_peerreview.
 *
 * Modelled on mod/quiz/tests/backup/repeated_restore_test.php.
 *
 * @package    mod_peerreview
 * @category   test
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_peerreview\backup;

use mod_peerreview\local\allocation\manager;
use mod_peerreview\local\grade\override;
use mod_peerreview\local\review\service;
use PHPUnit\Framework\Attributes\CoversClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

/**
 * Round trip tests: an activity with a rubric, reviews and an override is backed up and restored into another course.
 *
 * @package    mod_peerreview
 * @category   test
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\backup_peerreview_activity_structure_step::class)]
#[CoversClass(\restore_peerreview_activity_structure_step::class)]
final class backup_restore_test extends \advanced_testcase {
    use \mod_peerreview\tests\activity_trait;

    /** @var \stdClass Submitted rubric review, student 1 of student 2. */
    private \stdClass $submitted;

    /**
     * Activity with a rubric, one submitted rubric review (1 of 2), one not started (2 of 3), an override for
     * student 3 and released feedback.
     */
    private function setup_data(): void {
        global $DB;

        $this->create_activity(3, [
            'anonymous' => 0,
            'timeopen' => 1767225600,
            'timeclose' => 1798761600,
            'intro' => 'See <a href="https://example.com/mod/peerreview/view.php?id=1">it</a>',
        ]);
        $this->setAdminUser();
        $controller = $this->getDataGenerator()->get_plugin_generator('gradingform_rubric')
            ->get_test_rubric($this->context, 'mod_peerreview', 'received');

        $service = new service($this->peerreview, $this->context);
        $alloc = $this->allocate(1, 2);
        $instance = $service->get_instance($alloc, $this->students[1]->id);
        $data = $this->getDataGenerator()->get_plugin_generator('gradingform_rubric')
            ->get_test_form_data($controller, $alloc->id, 2, 'clear', 1, 'one picture');
        $service->submit($alloc, $this->students[1]->id, [
            'advancedgrading' => $data,
            'advancedgradinginstanceid' => $instance->get_id(),
            'feedback' => 'Well presented',
            'feedbackformat' => FORMAT_HTML,
        ]);
        $this->submitted = $DB->get_record('peerreview_alloc', ['id' => $alloc->id], '*', MUST_EXIST);
        $this->allocate(2, 3);
        (new override($this->peerreview, $this->context))->set($this->students[3]->id, 42.5, 'Absent', $this->teacher->id);
        $DB->set_field('peerreview', 'feedbackreleased', 1, ['id' => $this->peerreview->id]);
    }

    /**
     * Back up the activity and restore it into a new course.
     *
     * @param bool $userinfo Include user data.
     * @return array [peerreview record, module context] of the restored copy.
     */
    private function backup_and_restore(bool $userinfo): array {
        global $DB, $USER;

        $bc = new \backup_controller(
            \backup::TYPE_1ACTIVITY,
            $this->cm->id,
            \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $USER->id
        );
        $bc->get_plan()->get_setting('users')->set_value($userinfo);
        $backupid = $bc->get_backupid();
        $bc->execute_plan();
        $bc->destroy();

        $target = $this->getDataGenerator()->create_course();
        foreach ($this->students as $student) {
            $this->getDataGenerator()->enrol_user($student->id, $target->id, 'student');
        }
        $rc = new \restore_controller(
            $backupid,
            $target->id,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $USER->id,
            \backup::TARGET_EXISTING_ADDING
        );
        if ($userinfo) {
            $rc->get_plan()->get_setting('users')->set_value(true);
        }
        $this->assertTrue($rc->execute_precheck());
        $rc->execute_plan();
        $rc->destroy();

        $restored = $DB->get_record('peerreview', ['course' => $target->id], '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('peerreview', $restored->id, $target->id, false, MUST_EXIST);
        return [$restored, \context_module::instance($cm->id)];
    }

    /**
     * With user data: settings, reviews, rubric fillings (remapped to the new allocation ids) and overrides survive.
     */
    public function test_round_trip_with_user_data(): void {
        global $DB, $CFG;
        $this->resetAfterTest();
        $CFG->keeptempdirectoriesonbackup = true;
        $this->setup_data();

        [$restored, $newcontext] = $this->backup_and_restore(true);

        foreach (
            ['name', 'grade', 'gradeparticipation', 'aggregation', 'anonymous', 'allowselfreview',
                'timeopen', 'timeclose', 'completionallreviews', 'intro'] as $field
        ) {
            $this->assertEquals($this->peerreview->$field, $restored->$field, $field);
        }
        $this->assertEquals(1, $restored->feedbackreleased);

        $allocs = $DB->get_records('peerreview_alloc', ['peerreviewid' => $restored->id], 'id');
        $this->assertCount(2, $allocs);
        $copy = null;
        foreach ($allocs as $alloc) {
            if ($alloc->reviewerid == $this->students[1]->id) {
                $copy = $alloc;
            }
        }
        $this->assertNotNull($copy);
        $this->assertNotEquals($this->submitted->id, $copy->id);
        $this->assertEquals($this->students[2]->id, $copy->revieweeid);
        $this->assertEquals(manager::STATUS_SUBMITTED, $copy->status);
        $this->assertEqualsWithDelta((float) $this->submitted->grade, (float) $copy->grade, 0.00001);
        $this->assertEquals('Well presented', $copy->feedback);
        $this->assertEquals($this->submitted->timesubmitted, $copy->timesubmitted);
        $this->assertEquals($this->teacher->id, $copy->allocatedby);

        // The rubric and the review's filling come along, keyed to the new allocation.
        $area = $DB->get_record('grading_areas', ['contextid' => $newcontext->id, 'component' => 'mod_peerreview',
            'areaname' => 'received'], '*', MUST_EXIST);
        $this->assertEquals('rubric', $area->activemethod);
        $instances = $DB->get_records_sql(
            "SELECT gi.*
                                              FROM {grading_instances} gi
                                              JOIN {grading_definitions} gd ON gd.id = gi.definitionid
                                             WHERE gd.areaid = :areaid AND gi.status = :status",
            ['areaid' => $area->id, 'status' => \gradingform_instance::INSTANCE_STATUS_ACTIVE]
        );
        $this->assertCount(1, $instances);
        $instance = reset($instances);
        $this->assertEquals($copy->id, $instance->itemid);
        $this->assertEquals($this->students[1]->id, $instance->raterid);
        $this->assertEquals(2, $DB->count_records('gradingform_rubric_fillings', ['instanceid' => $instance->id]));

        // The restored review opens through the grading API.
        $service = new service($restored, $newcontext);
        $this->assertInstanceOf(\gradingform_rubric_instance::class, $service->get_submitted_instance($copy));

        $override = $DB->get_record('peerreview_override', ['peerreviewid' => $restored->id], '*', MUST_EXIST);
        $this->assertEquals($this->students[3]->id, $override->userid);
        $this->assertEqualsWithDelta(42.5, (float) $override->grade, 0.00001);
        $this->assertEquals('Absent', $override->note);
        $this->assertEquals($this->teacher->id, $override->overriddenby);

        // The original is untouched.
        $this->assertEquals(2, $DB->count_records('peerreview_alloc', ['peerreviewid' => $this->peerreview->id]));
    }

    /**
     * Without user data: settings and the rubric come along, reviews and overrides do not, feedback is not released.
     */
    public function test_round_trip_without_user_data(): void {
        global $DB, $CFG;
        $this->resetAfterTest();
        $CFG->keeptempdirectoriesonbackup = true;
        $this->setup_data();

        [$restored, $newcontext] = $this->backup_and_restore(false);

        $this->assertEquals($this->peerreview->name, $restored->name);
        $this->assertEquals(0, $restored->feedbackreleased);
        $this->assertEquals(0, $DB->count_records('peerreview_alloc', ['peerreviewid' => $restored->id]));
        $this->assertEquals(0, $DB->count_records('peerreview_override', ['peerreviewid' => $restored->id]));
        $area = $DB->get_record(
            'grading_areas',
            ['contextid' => $newcontext->id, 'areaname' => 'received'],
            '*',
            MUST_EXIST
        );
        $this->assertTrue($DB->record_exists('grading_definitions', ['areaid' => $area->id, 'method' => 'rubric']));
    }
}
