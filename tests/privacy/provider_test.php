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
 * Privacy provider tests for mod_peerreview.
 *
 * Modelled on mod/assign/tests/privacy/provider_test.php and mod/workshop/tests/privacy/provider_test.php.
 *
 * @package    mod_peerreview
 * @category   test
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_peerreview\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use mod_peerreview\local\allocation\manager;
use mod_peerreview\local\grade\override;
use mod_peerreview\local\review\service;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Privacy provider tests.
 *
 * Fixture: student 1 reviews student 2 with a rubric (submitted), student 3 has a draft review of student 1,
 * student 2 has a grade override set by the teacher, the teacher made all allocations. A second activity in the
 * same course holds one more review so deletions can be shown to stay inside their context.
 *
 * @package    mod_peerreview
 * @category   test
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(provider::class)]
final class provider_test extends \core_privacy\tests\provider_testcase {
    use \mod_peerreview\tests\activity_trait;

    /** @var \stdClass Submitted rubric review, student 1 of student 2. */
    private \stdClass $submitted;

    /** @var \stdClass Draft review, student 3 of student 1. */
    private \stdClass $draft;

    /** @var \context_module Context of the second activity. */
    private \context_module $othercontext;

    /**
     * Build the fixture.
     *
     * @param array $activity Extra activity fields.
     */
    private function setup_data(array $activity = []): void {
        global $DB;

        $this->create_activity(4, $activity);
        $this->setAdminUser(); // The rubric generator must run as a user.
        $controller = $this->getDataGenerator()->get_plugin_generator('gradingform_rubric')
            ->get_test_rubric($this->context, 'mod_peerreview', 'received');
        $this->setUser(0);

        $service = new service($this->peerreview, $this->context);
        $alloc = $this->allocate(1, 2);
        $instance = $service->get_instance($alloc, $this->students[1]->id);
        $data = $this->getDataGenerator()->get_plugin_generator('gradingform_rubric')
            ->get_test_form_data($controller, $alloc->id, 2, 'clear slides', 1, 'one picture');
        $service->submit($alloc, $this->students[1]->id, [
            'advancedgrading' => $data,
            'advancedgradinginstanceid' => $instance->get_id(),
            'feedback' => 'Well presented',
            'feedbackformat' => FORMAT_HTML,
        ]);
        $this->submitted = $DB->get_record('peerreview_alloc', ['id' => $alloc->id], '*', MUST_EXIST);
        $this->draft = $this->allocate(3, 1, manager::STATUS_DRAFT, 40.0, 'unfinished thoughts');

        $this->setUser($this->teacher);
        (new override($this->peerreview, $this->context))->set(
            $this->students[2]->id,
            55.0,
            'Presented twice',
            $this->teacher->id
        );
        $this->setUser(0);
        set_user_preference('mod_peerreview_autorefresh', 0, $this->teacher->id);

        // A second activity in the same course with a review by student 1 of student 2.
        $other = $this->getDataGenerator()->create_module('peerreview', ['course' => $this->course->id]);
        $othercm = get_coursemodule_from_id('peerreview', $other->cmid, 0, false, MUST_EXIST);
        $this->othercontext = \context_module::instance($othercm->id);
        $othermanager = new manager($DB->get_record('peerreview', ['id' => $other->id]), $othercm, $this->othercontext);
        $othermanager->add_manual($this->students[1]->id, [$this->students[2]->id], $this->teacher->id);
    }

    /**
     * Count grading instances of an allocation.
     *
     * @param int $allocid Allocation id (the grading itemid).
     * @return int
     */
    private function count_grading_instances(int $allocid): int {
        global $DB;
        return $DB->count_records_sql(
            "SELECT COUNT(1)
                                          FROM {grading_instances} gi
                                          JOIN {grading_definitions} gd ON gd.id = gi.definitionid
                                          JOIN {grading_areas} ga ON ga.id = gd.areaid
                                         WHERE ga.contextid = :contextid AND gi.itemid = :itemid",
            ['contextid' => $this->context->id, 'itemid' => $allocid]
        );
    }

    /**
     * Export path of the rubric filling of the submitted review (the rubric plugin adds the instance id).
     *
     * @param array $subcontext The review's subcontext.
     * @return array
     */
    private function rubric_path(array $subcontext): array {
        global $DB;
        $instanceid = $DB->get_field('grading_instances', 'id', [
            'itemid' => $this->submitted->id,
            'status' => \gradingform_instance::INSTANCE_STATUS_ACTIVE,
        ], MUST_EXIST);
        return array_merge($subcontext, [get_string('rubric', 'gradingform_rubric'), $instanceid]);
    }

    /**
     * Metadata covers both tables, the grading subsystem and the preference.
     */
    public function test_get_metadata(): void {
        $items = provider::get_metadata(new collection('mod_peerreview'))->get_collection();
        $names = array_map(fn($item) => $item->get_name(), $items);
        $this->assertEqualsCanonicalizing(
            ['peerreview_alloc', 'peerreview_override', 'core_grading', 'mod_peerreview_autorefresh'],
            $names
        );
    }

    /**
     * Every role (reviewer, reviewee, overridden student, teacher) finds the context; an outsider does not.
     */
    public function test_get_contexts_for_userid(): void {
        $this->resetAfterTest();
        $this->setup_data();
        $outsider = $this->getDataGenerator()->create_user();

        foreach ([1, 2, 3] as $i) {
            $ids = provider::get_contexts_for_userid($this->students[$i]->id)->get_contextids();
            $this->assertContainsEquals($this->context->id, $ids, "student $i");
        }
        $this->assertEquals([], provider::get_contexts_for_userid($this->students[4]->id)->get_contextids());
        $teacherids = provider::get_contexts_for_userid($this->teacher->id)->get_contextids();
        $this->assertEqualsCanonicalizing([$this->context->id, $this->othercontext->id], $teacherids);
        $this->assertEquals([], provider::get_contexts_for_userid($outsider->id)->get_contextids());
    }

    /**
     * The userlist holds reviewers, reviewees and the teacher, not students without data.
     */
    public function test_get_users_in_context(): void {
        $this->resetAfterTest();
        $this->setup_data();

        $userlist = new userlist($this->context, 'mod_peerreview');
        provider::get_users_in_context($userlist);
        $expected = [$this->students[1]->id, $this->students[2]->id, $this->students[3]->id, $this->teacher->id];
        $this->assertEqualsCanonicalizing($expected, $userlist->get_userids());

        $userlist = new userlist(\context_course::instance($this->course->id), 'mod_peerreview');
        provider::get_users_in_context($userlist);
        $this->assertCount(0, $userlist);
    }

    /**
     * The reviewer gets the review they wrote, with the rubric filling, and their draft.
     */
    public function test_export_reviewer(): void {
        $this->resetAfterTest();
        $this->setup_data();

        $this->export_context_data_for_user($this->students[1]->id, $this->context, 'mod_peerreview');
        $writer = writer::with_context($this->context);
        $this->assertTrue($writer->has_any_data());

        $given = [get_string('privacy:reviewsgiven', 'mod_peerreview'), $this->submitted->id];
        $data = $writer->get_data($given);
        $this->assertEquals(get_string('statussubmitted', 'mod_peerreview'), $data->status);
        $this->assertEquals(fullname($this->students[2]), $data->reviewee);
        $this->assertStringContainsString('Well presented', $data->feedback);
        $this->assertEquals(format_float($this->submitted->grade, 2), $data->grade);
        $rubric = $writer->get_data($this->rubric_path($given));
        $this->assertNotEmpty((array) $rubric, 'the rubric filling is exported');

        // Student 1 received a draft: only its status, no grade or comment.
        $received = $writer->get_data([get_string('privacy:reviewsreceived', 'mod_peerreview'), $this->draft->id]);
        $this->assertEquals(get_string('statusdraft', 'mod_peerreview'), $received->status);
        $this->assertObjectNotHasProperty('feedback', $received);
        $this->assertObjectNotHasProperty('grade', $received);
    }

    /**
     * The reviewee gets the review and their override; the reviewer is hidden while the activity is anonymous.
     */
    public function test_export_reviewee_anonymous(): void {
        $this->resetAfterTest();
        $this->setup_data(['anonymous' => 1]);

        $this->export_context_data_for_user($this->students[2]->id, $this->context, 'mod_peerreview');
        $writer = writer::with_context($this->context);
        $received = [get_string('privacy:reviewsreceived', 'mod_peerreview'), $this->submitted->id];
        $data = $writer->get_data($received);
        $this->assertStringContainsString('Well presented', $data->feedback);
        $this->assertObjectNotHasProperty('reviewer', $data);
        $this->assertStringNotContainsString(fullname($this->students[1]), json_encode($data));
        $this->assertNotEmpty((array) $writer->get_data($this->rubric_path($received)));

        $override = $writer->get_data([get_string('privacy:override', 'mod_peerreview')]);
        $this->assertEquals(format_float(55, 2), $override->grade);
        $this->assertEquals('Presented twice', $override->note);
    }

    /**
     * Without anonymity the reviewee sees who reviewed them.
     */
    public function test_export_reviewee_named(): void {
        $this->resetAfterTest();
        $this->setup_data(['anonymous' => 0]);

        $this->export_context_data_for_user($this->students[2]->id, $this->context, 'mod_peerreview');
        $data = writer::with_context($this->context)
            ->get_data([get_string('privacy:reviewsreceived', 'mod_peerreview'), $this->submitted->id]);
        $this->assertEquals(fullname($this->students[1]), $data->reviewer);
    }

    /**
     * The grading filling of a draft is the reviewer's own work: the reviewee gets the status of a received draft but
     * not the rubric, while the reviewer gets both.
     */
    public function test_received_draft_rubric_is_not_exported(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setup_data();

        $service = new service($this->peerreview, $this->context);
        $alloc = $this->allocate(4, 1);
        $instance = $service->get_instance($alloc, $this->students[4]->id);
        $data = $this->getDataGenerator()->get_plugin_generator('gradingform_rubric')
            ->get_test_form_data($service->get_controller(), $alloc->id, 2, 'half done', 1, 'maybe');
        $service->save_draft($alloc, $this->students[4]->id, [
            'advancedgrading' => $data,
            'advancedgradinginstanceid' => $instance->get_id(),
            'feedback' => 'not final',
            'feedbackformat' => FORMAT_HTML,
        ]);
        $instanceid = $DB->get_field('grading_instances', 'id', ['itemid' => $alloc->id], MUST_EXIST);
        $filling = [get_string('rubric', 'gradingform_rubric'), $instanceid];
        $given = [get_string('privacy:reviewsgiven', 'mod_peerreview'), $alloc->id];
        $received = [get_string('privacy:reviewsreceived', 'mod_peerreview'), $alloc->id];

        $this->export_context_data_for_user($this->students[4]->id, $this->context, 'mod_peerreview');
        $this->assertNotEmpty((array) writer::with_context($this->context)->get_data(array_merge($given, $filling)));

        writer::reset();
        $this->export_context_data_for_user($this->students[1]->id, $this->context, 'mod_peerreview');
        $writer = writer::with_context($this->context);
        $this->assertEquals(get_string('statusdraft', 'mod_peerreview'), $writer->get_data($received)->status);
        $this->assertEmpty((array) $writer->get_data(array_merge($received, $filling)));
    }

    /**
     * The teacher gets the overrides they set (without student names), the allocation count and the preference.
     */
    public function test_export_teacher(): void {
        $this->resetAfterTest();
        $this->setup_data();

        $this->export_context_data_for_user($this->teacher->id, $this->context, 'mod_peerreview');
        $writer = writer::with_context($this->context);
        $given = $writer->get_data([get_string('privacy:overridesgiven', 'mod_peerreview')]);
        $this->assertCount(1, $given->overrides);
        $this->assertStringNotContainsString(fullname($this->students[2]), json_encode($given));
        $this->assertEquals(2, $writer->get_data([])->allocationsmade);

        provider::export_user_preferences($this->teacher->id);
        $prefs = writer::with_context(\context_system::instance())->get_user_preferences('mod_peerreview');
        $this->assertEquals(get_string('no'), $prefs->mod_peerreview_autorefresh->value);
    }

    /**
     * Deleting one user removes their reviews (both directions) with grading data, keeps others, anonymises teachers.
     */
    public function test_delete_data_for_user(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setup_data();
        $this->assertEquals(1, $this->count_grading_instances($this->submitted->id));

        // Student 2: the review they received goes, including the rubric filling; so does the override.
        $contextlist = new approved_contextlist($this->students[2], 'mod_peerreview', [$this->context->id]);
        provider::delete_data_for_user($contextlist);
        $this->assertFalse($DB->record_exists('peerreview_alloc', ['id' => $this->submitted->id]));
        $this->assertEquals(0, $this->count_grading_instances($this->submitted->id));
        $this->assertFalse($DB->record_exists('peerreview_override', ['userid' => $this->students[2]->id]));
        $this->assertTrue($DB->record_exists('peerreview_alloc', ['id' => $this->draft->id]));
        $this->assertEquals(
            1,
            $DB->count_records('peerreview_alloc', ['revieweeid' => $this->students[2]->id]),
            'the other activity is untouched'
        );

        // Teacher: rows stay, authorship is cleared.
        $contextlist = new approved_contextlist($this->teacher, 'mod_peerreview', [$this->context->id]);
        provider::delete_data_for_user($contextlist);
        $this->assertEquals(0, $DB->get_field('peerreview_alloc', 'allocatedby', ['id' => $this->draft->id]));
        $this->assertEquals(
            1,
            $DB->count_records('peerreview_alloc', ['allocatedby' => $this->teacher->id]),
            'the other activity keeps its author'
        );
    }

    /**
     * Deleting a list of users in one context.
     */
    public function test_delete_data_for_users(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setup_data();

        $userlist = new approved_userlist($this->context, 'mod_peerreview', [$this->students[3]->id, $this->teacher->id]);
        provider::delete_data_for_users($userlist);
        $this->assertFalse($DB->record_exists('peerreview_alloc', ['id' => $this->draft->id]));
        $this->assertTrue($DB->record_exists('peerreview_alloc', ['id' => $this->submitted->id]));
        $this->assertEquals(1, $this->count_grading_instances($this->submitted->id));
        $override = $DB->get_record('peerreview_override', ['userid' => $this->students[2]->id], '*', MUST_EXIST);
        $this->assertEquals(0, $override->overriddenby);
    }

    /**
     * Deleting a whole context removes every review, grading instance and override there, and nothing elsewhere.
     */
    public function test_delete_data_for_all_users_in_context(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setup_data();

        provider::delete_data_for_all_users_in_context($this->context);
        $this->assertEquals(0, $DB->count_records('peerreview_alloc', ['peerreviewid' => $this->peerreview->id]));
        $this->assertEquals(0, $DB->count_records('peerreview_override', ['peerreviewid' => $this->peerreview->id]));
        $this->assertEquals(0, $this->count_grading_instances($this->submitted->id));
        $this->assertEquals(1, $DB->count_records('peerreview_alloc'), 'the other activity is untouched');
        $this->assertTrue(
            $DB->record_exists('grading_areas', ['contextid' => $this->context->id]),
            'the rubric itself belongs to the teacher and stays'
        );
    }
}
