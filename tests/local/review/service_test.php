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
 * Tests for the review service (drafts, submit, edit, permissions) with rubric, guide and the simple fallback.
 *
 * @package    mod_peerreview
 * @category   test
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_peerreview\local\review;

use mod_peerreview\local\allocation\manager;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the review service.
 *
 * @package    mod_peerreview
 * @category   test
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(service::class)]
final class service_test extends \advanced_testcase {
    /** @var \stdClass Activity record. */
    private \stdClass $peerreview;

    /** @var \context_module Module context. */
    private \context_module $context;

    /** @var \stdClass[] Students indexed from 1. */
    private array $students;

    /** @var \stdClass Teacher. */
    private \stdClass $teacher;

    /** @var \stdClass Course. */
    private \stdClass $course;

    /** @var manager Allocation manager. */
    private manager $manager;

    /**
     * Create course, three students, a teacher, an activity and allocation 1 to 2.
     *
     * @param array $activity Extra activity fields.
     * @return \stdClass The allocation record (student 1 reviews student 2).
     */
    private function setup_activity(array $activity = []): \stdClass {
        global $DB;
        $generator = $this->getDataGenerator();
        $this->course = $generator->create_course();
        foreach ([1, 2, 3] as $i) {
            $this->students[$i] = $generator->create_and_enrol($this->course, 'student');
        }
        $this->teacher = $generator->create_and_enrol($this->course, 'editingteacher');
        $instance = $generator->create_module('peerreview', ['course' => $this->course->id] + $activity);
        $cm = get_coursemodule_from_id('peerreview', $instance->cmid, 0, false, MUST_EXIST);
        $this->context = \context_module::instance($cm->id);
        $this->peerreview = $DB->get_record('peerreview', ['id' => $instance->id], '*', MUST_EXIST);
        $this->manager = new manager($this->peerreview, $cm, $this->context);
        $this->manager->add_manual($this->students[1]->id, [$this->students[2]->id], $this->teacher->id);
        return $DB->get_record('peerreview_alloc', ['reviewerid' => $this->students[1]->id], '*', MUST_EXIST);
    }

    /**
     * Build the service.
     *
     * @return service
     */
    private function service(): service {
        return new service($this->peerreview, $this->context);
    }

    /**
     * Reload an allocation.
     *
     * @param \stdClass $alloc Allocation.
     * @return \stdClass
     */
    private function reload(\stdClass $alloc): \stdClass {
        global $DB;
        return $DB->get_record('peerreview_alloc', ['id' => $alloc->id], '*', MUST_EXIST);
    }

    /**
     * Create a rubric (max 4 points, 2 criteria) as the active method.
     *
     * @return \gradingform_rubric_controller
     */
    private function use_rubric(): \gradingform_rubric_controller {
        $this->setAdminUser(); // The core generator must run as a user.
        $controller = $this->getDataGenerator()->get_plugin_generator('gradingform_rubric')
            ->get_test_rubric($this->context, 'mod_peerreview', 'received');
        $this->setUser(0);
        return $controller;
    }

    /**
     * Create a marking guide (25 + 15 points) as the active method.
     *
     * @return \gradingform_guide_controller
     */
    private function use_guide(): \gradingform_guide_controller {
        $this->setAdminUser(); // The core generator must run as a user.
        $controller = $this->getDataGenerator()->get_plugin_generator('gradingform_guide')
            ->get_test_guide($this->context, 'mod_peerreview', 'received');
        $this->setUser(0);
        return $controller;
    }

    /**
     * Simple fallback: draft keeps the score without submitting, submit stores status, time and event, edit updates.
     */
    public function test_fallback_draft_submit_edit(): void {
        $this->resetAfterTest();
        $alloc = $this->setup_activity();
        $reviewer = $this->students[1]->id;
        $service = $this->service();
        $sink = $this->redirectEvents();

        $service->save_draft($alloc, $reviewer, ['score' => '40', 'feedback' => 'so far', 'feedbackformat' => FORMAT_HTML]);
        $row = $this->reload($alloc);
        $this->assertEquals(manager::STATUS_DRAFT, $row->status);
        $this->assertEquals(40, $row->grade);
        $this->assertSame('so far', $row->feedback);
        $this->assertCount(0, $sink->get_events());

        $service->submit($row, $reviewer, ['score' => '80', 'feedback' => 'good', 'feedbackformat' => FORMAT_HTML]);
        $row = $this->reload($alloc);
        $this->assertEquals(manager::STATUS_SUBMITTED, $row->status);
        $this->assertEquals(80, $row->grade);
        $this->assertNotEmpty($row->timesubmitted);
        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(\mod_peerreview\event\review_submitted::class, $events[0]);
        $this->assertEquals($this->students[2]->id, $events[0]->relateduserid);
        $first = $row->timesubmitted;

        $sink->clear();
        $service->submit($row, $reviewer, ['score' => '90', 'feedback' => 'better', 'feedbackformat' => FORMAT_HTML]);
        $row = $this->reload($alloc);
        $this->assertEquals(90, $row->grade);
        $this->assertEquals($first, $row->timesubmitted, 'first submission time is kept');
        $this->assertInstanceOf(\mod_peerreview\event\review_updated::class, $sink->get_events()[0]);

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('errordraftaftersubmit', 'mod_peerreview'));
        $service->save_draft($row, $reviewer, ['score' => '10']);
    }

    /**
     * Simple fallback: score is required to submit and must be inside 0..max.
     */
    public function test_fallback_score_validation(): void {
        $this->resetAfterTest();
        $alloc = $this->setup_activity(['grade' => 20]);
        $service = $this->service();
        $reviewer = $this->students[1]->id;

        foreach (['', null, 'abc', '-1', '21'] as $bad) {
            try {
                $service->submit($alloc, $reviewer, ['score' => $bad]);
                $this->fail('Expected an exception for ' . var_export($bad, true));
            } catch (\moodle_exception $e) {
                $this->assertContains($e->errorcode, ['errorreviewincomplete', 'errorscorerange']);
            }
        }
        $this->assertEquals(manager::STATUS_NEW, $this->reload($alloc)->status);
        $service->submit($alloc, $reviewer, ['score' => '20']);
        $this->assertEquals(20, $this->reload($alloc)->grade);
    }

    /**
     * Rubric: full submit gives the normalised grade and one active instance tied to the allocation.
     */
    public function test_rubric_submit(): void {
        $this->resetAfterTest();
        global $DB;
        $alloc = $this->setup_activity();
        $controller = $this->use_rubric();
        $service = $this->service();
        $reviewer = $this->students[1]->id;
        $this->assertTrue($service->supports_drafts());

        $instance = $service->get_instance($alloc, $reviewer);
        $this->assertInstanceOf(\gradingform_rubric_instance::class, $instance);
        $data = $this->getDataGenerator()->get_plugin_generator('gradingform_rubric')
            ->get_test_form_data($controller, $alloc->id, 2, 'perfect', 1, 'one picture');
        $service->submit($alloc, $reviewer, ['advancedgrading' => $data, 'advancedgradinginstanceid' => $instance->get_id()]);

        $row = $this->reload($alloc);
        $this->assertEquals(manager::STATUS_SUBMITTED, $row->status);
        $this->assertEqualsWithDelta(75.0, (float) $row->grade, 0.01, '3 of 4 rubric points on a 100 point activity');
        $active = $DB->get_records('grading_instances', [
            'itemid' => $alloc->id,
            'status' => \gradingform_instance::INSTANCE_STATUS_ACTIVE,
        ]);
        $this->assertCount(1, $active);
        $this->assertEquals($reviewer, reset($active)->raterid);
    }

    /**
     * Rubric: a half-filled draft is stored without a grade and picked up again; submit completes it.
     */
    public function test_rubric_draft_then_submit(): void {
        $this->resetAfterTest();
        global $DB;
        $alloc = $this->setup_activity();
        $controller = $this->use_rubric();
        $service = $this->service();
        $reviewer = $this->students[1]->id;
        $rubricgen = $this->getDataGenerator()->get_plugin_generator('gradingform_rubric');

        $full = $rubricgen->get_test_form_data($controller, $alloc->id, 1, 'spelling note', 2, 'pictures note');
        $partial = ['criteria' => [array_key_first($full['criteria']) => $full['criteria'][array_key_first($full['criteria'])]]];
        $partial['criteria'] += [array_key_last($full['criteria']) => ['remark' => 'only a remark, no level']];
        $service->save_draft($alloc, $reviewer, ['advancedgrading' => $partial, 'feedback' => 'draft note']);

        $row = $this->reload($alloc);
        $this->assertEquals(manager::STATUS_DRAFT, $row->status);
        $this->assertNull($row->grade);
        $this->assertSame('draft note', $row->feedback);

        $again = $service->get_instance($alloc, $reviewer);
        $this->assertEquals(\gradingform_instance::INSTANCE_STATUS_INCOMPLETE, $again->get_data('status'));
        $filling = $again->get_rubric_filling(true);
        $this->assertCount(2, $filling['criteria'], 'level choice and remark-only criterion both kept');

        $service->submit($row, $reviewer, ['advancedgrading' => $full, 'advancedgradinginstanceid' => $again->get_id()]);
        $row = $this->reload($alloc);
        $this->assertEquals(manager::STATUS_SUBMITTED, $row->status);
        $this->assertEqualsWithDelta(75.0, (float) $row->grade, 0.01);
        $this->assertEquals(1, $DB->count_records('grading_instances', [
            'itemid' => $alloc->id,
            'status' => \gradingform_instance::INSTANCE_STATUS_ACTIVE,
        ]));
    }

    /**
     * Rubric: editing after submit changes the grade; the old instance is archived, not duplicated as active.
     */
    public function test_rubric_edit_after_submit(): void {
        $this->resetAfterTest();
        global $DB;
        $alloc = $this->setup_activity();
        $controller = $this->use_rubric();
        $service = $this->service();
        $reviewer = $this->students[1]->id;
        $rubricgen = $this->getDataGenerator()->get_plugin_generator('gradingform_rubric');

        $service->submit($alloc, $reviewer, [
            'advancedgrading' => $rubricgen->get_test_form_data($controller, $alloc->id, 0, 'weak', 0, 'none'),
        ]);
        $this->assertEqualsWithDelta(0.0, (float) $this->reload($alloc)->grade, 0.01);

        $row = $this->reload($alloc);
        $service->submit($row, $reviewer, [
            'advancedgrading' => $rubricgen->get_test_form_data($controller, $alloc->id, 2, 'strong', 2, 'many'),
        ]);
        $row = $this->reload($alloc);
        $this->assertEqualsWithDelta(100.0, (float) $row->grade, 0.01);
        $this->assertEquals(1, $DB->count_records('grading_instances', [
            'itemid' => $alloc->id,
            'status' => \gradingform_instance::INSTANCE_STATUS_ACTIVE,
        ]));
        $this->assertEquals(1, $DB->count_records('grading_instances', [
            'itemid' => $alloc->id,
            'status' => \gradingform_instance::INSTANCE_STATUS_ARCHIVE,
        ]));
    }

    /**
     * An empty advanced form is refused instead of storing a meaningless grade.
     */
    public function test_empty_advanced_form_is_refused(): void {
        $this->resetAfterTest();
        $alloc = $this->setup_activity();
        $this->use_rubric();
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('errorreviewincomplete', 'mod_peerreview'));
        $this->service()->submit($alloc, $this->students[1]->id, ['advancedgrading' => ['criteria' => []]]);
    }

    /**
     * Marking guide: full submit scales points to the activity maximum; a draft keeps only scored criteria.
     */
    public function test_guide_draft_and_submit(): void {
        $this->resetAfterTest();
        $alloc = $this->setup_activity();
        $controller = $this->use_guide();
        $service = $this->service();
        $reviewer = $this->students[1]->id;
        $guidegen = $this->getDataGenerator()->get_plugin_generator('gradingform_guide');

        $this->assertInstanceOf(\gradingform_guide_instance::class, $service->get_instance($alloc, $reviewer));
        $partial = $guidegen->get_submitted_form_data($controller, $alloc->id, [
            'Spelling mistakes' => ['score' => 20, 'remark' => 'a few'],
            'Pictures' => ['score' => '', 'remark' => 'not scored yet'],
        ]);
        $service->save_draft($alloc, $reviewer, ['advancedgrading' => $partial]);
        $row = $this->reload($alloc);
        $this->assertEquals(manager::STATUS_DRAFT, $row->status);
        $this->assertNull($row->grade);
        $instance = $service->get_instance($alloc, $reviewer);
        $this->assertCount(1, $instance->get_guide_filling(true)['criteria']);

        $full = $guidegen->get_submitted_form_data($controller, $alloc->id, [
            'Spelling mistakes' => ['score' => 20, 'remark' => 'a few'],
            'Pictures' => ['score' => 10, 'remark' => 'two'],
        ]);
        $service->submit($row, $reviewer, ['advancedgrading' => $full, 'advancedgradinginstanceid' => $instance->get_id()]);
        $row = $this->reload($alloc);
        $this->assertEquals(manager::STATUS_SUBMITTED, $row->status);
        $this->assertEqualsWithDelta(75.0, (float) $row->grade, 0.01, '30 of 40 points');
    }

    /**
     * A marking guide draft with an out-of-range score drops that score instead of storing it.
     */
    public function test_guide_draft_ignores_invalid_scores(): void {
        $this->resetAfterTest();
        $alloc = $this->setup_activity();
        $controller = $this->use_guide();
        $service = $this->service();
        $reviewer = $this->students[1]->id;
        $data = $this->getDataGenerator()->get_plugin_generator('gradingform_guide')->get_submitted_form_data(
            $controller,
            $alloc->id,
            ['Spelling mistakes' => ['score' => 99, 'remark' => ''], 'Pictures' => ['score' => 'x', 'remark' => '']]
        );
        $service->save_draft($alloc, $reviewer, ['advancedgrading' => $data]);
        $this->assertCount(0, $service->get_instance($alloc, $reviewer)->get_guide_filling(true)['criteria']);
    }

    /**
     * Only the reviewer may write; other students and teachers cannot. Window: not open yet and closed.
     */
    public function test_permissions_and_window(): void {
        global $DB;
        $this->resetAfterTest();
        $alloc = $this->setup_activity();
        $service = $this->service();

        foreach ([$this->students[2]->id, $this->students[3]->id, $this->teacher->id] as $userid) {
            try {
                $service->submit($alloc, $userid, ['score' => 50]);
                $this->fail('Expected refusal');
            } catch (\moodle_exception $e) {
                $this->assertSame('errornotyourreview', $e->errorcode);
            }
        }

        $DB->set_field('peerreview', 'timeopen', time() + DAYSECS, ['id' => $this->peerreview->id]);
        $this->peerreview->timeopen = time() + DAYSECS;
        try {
            $service->submit($alloc, $this->students[1]->id, ['score' => 50]);
            $this->fail('Expected refusal before opening');
        } catch (\moodle_exception $e) {
            $this->assertSame('errornotopenyet', $e->errorcode);
        }

        $this->peerreview->timeopen = 0;
        $this->peerreview->timeclose = time() - 1;
        try {
            $service->submit($alloc, $this->students[1]->id, ['score' => 50]);
            $this->fail('Expected refusal after closing');
        } catch (\moodle_exception $e) {
            $this->assertSame('errorclosed', $e->errorcode);
        }
        $this->assertFalse($service->is_open());
        $this->assertTrue($service->is_open(time() - 2 * DAYSECS));
        $this->assertEquals(manager::STATUS_NEW, $this->reload($alloc)->status);
    }

    /**
     * Editing works up to the close time and stops at it.
     */
    public function test_edit_until_close(): void {
        $this->resetAfterTest();
        $alloc = $this->setup_activity();
        $this->peerreview->timeclose = time() + HOURSECS;
        $service = $this->service();
        $reviewer = $this->students[1]->id;
        $service->submit($alloc, $reviewer, ['score' => 30]);
        $service->submit($this->reload($alloc), $reviewer, ['score' => 35]);
        $this->assertEquals(35, $this->reload($alloc)->grade);

        $this->peerreview->timeclose = time() - 1;
        $this->expectException(\moodle_exception::class);
        $service->submit($this->reload($alloc), $reviewer, ['score' => 99]);
    }

    /**
     * An active method whose form is still a draft falls back to the simple form with a message.
     */
    public function test_unavailable_method_falls_back(): void {
        $this->resetAfterTest();
        $alloc = $this->setup_activity();
        $this->setAdminUser();
        $this->getDataGenerator()->get_plugin_generator('core_grading')
            ->create_instance($this->context, 'mod_peerreview', 'received', 'rubric');
        $this->setUser(0);
        $service = $this->service();
        $this->assertNull($service->get_controller());
        $this->assertNotSame('', $service->get_unavailable_message());
        $service->submit($alloc, $this->students[1]->id, ['score' => 60]);
        $this->assertEquals(60, $this->reload($alloc)->grade);
    }

    /**
     * Deleting an allocation removes its grading instances and rubric fillings.
     */
    public function test_delete_allocation_removes_grading_data(): void {
        $this->resetAfterTest();
        global $DB;
        $alloc = $this->setup_activity();
        $controller = $this->use_rubric();
        $service = $this->service();
        $reviewer = $this->students[1]->id;
        $service->submit($alloc, $reviewer, [
            'advancedgrading' => $this->getDataGenerator()->get_plugin_generator('gradingform_rubric')
                ->get_test_form_data($controller, $alloc->id, 2, 'a', 2, 'b'),
        ]);
        $this->assertGreaterThan(0, $DB->count_records('grading_instances', ['itemid' => $alloc->id]));
        $this->assertGreaterThan(0, $DB->count_records('gradingform_rubric_fillings'));

        $this->manager->delete([$alloc->id], true);
        $this->assertSame(0, $DB->count_records('peerreview_alloc'));
        $this->assertSame(0, $DB->count_records('grading_instances', ['itemid' => $alloc->id]));
        $this->assertSame(0, $DB->count_records('gradingform_rubric_fillings'));
    }

    /**
     * Changing the rubric after reviews exist, with regrade chosen, flags them for update (core behaviour); the reviewer
     * can re-submit against the new rubric and the stored grade follows.
     */
    public function test_rubric_changed_after_reviews_exist(): void {
        $this->resetAfterTest();
        global $DB;
        $alloc = $this->setup_activity();
        $controller = $this->use_rubric();
        $service = $this->service();
        $reviewer = $this->students[1]->id;
        $rubricgen = $this->getDataGenerator()->get_plugin_generator('gradingform_rubric');
        $service->submit($alloc, $reviewer, [
            'advancedgrading' => $rubricgen->get_test_form_data($controller, $alloc->id, 2, 'a', 2, 'b'),
        ]);
        $this->assertEqualsWithDelta(100.0, (float) $this->reload($alloc)->grade, 0.01);

        // The teacher replaces the criteria.
        $this->setAdminUser();
        $rubric = new \tests\gradingform_rubric\generator\rubric('Changed rubric', 'New criteria');
        $rubric->add_criteria(new \tests\gradingform_rubric\generator\criterion('Tone', ['Rude' => 0, 'Kind' => 1]));
        $definition = $rubric->get_definition();
        $definition->rubric['regrade'] = 1; // What the rubric editor sets when the teacher chooses to mark for regrade.
        $service->get_controller()->update_definition($definition);
        $this->setUser(0);

        $this->assertEquals(1, $DB->count_records('grading_instances', [
            'itemid' => $alloc->id,
            'status' => \gradingform_instance::INSTANCE_STATUS_NEEDUPDATE,
        ]));
        $this->assertEqualsWithDelta(100.0, (float) $this->reload($alloc)->grade, 0.01, 'stored grade is kept until re-review');

        $controller = $service->get_controller();
        $data = $rubricgen->get_submitted_form_data($controller, $alloc->id, ['Tone' => ['score' => 0, 'remark' => 'hm']]);
        $service->submit($this->reload($alloc), $reviewer, ['advancedgrading' => $data]);
        $row = $this->reload($alloc);
        $this->assertEqualsWithDelta(0.0, (float) $row->grade, 0.01);
        $this->assertEquals(manager::STATUS_SUBMITTED, $row->status);
        $this->assertEquals(1, $DB->count_records('grading_instances', [
            'itemid' => $alloc->id,
            'status' => \gradingform_instance::INSTANCE_STATUS_ACTIVE,
        ]));
    }

    /**
     * Switching the grading method after a review was submitted: the read-only view still returns the review as it was
     * filled in, with its own method, and the stored grade is untouched.
     */
    public function test_review_shown_with_the_method_it_was_written_in(): void {
        $this->resetAfterTest();
        global $CFG;
        require_once($CFG->dirroot . '/grade/grading/lib.php');
        $alloc = $this->setup_activity();
        $rubric = $this->use_rubric();
        $service = $this->service();
        $reviewer = $this->students[1]->id;
        $service->submit($alloc, $reviewer, [
            'advancedgrading' => $this->getDataGenerator()->get_plugin_generator('gradingform_rubric')
                ->get_test_form_data($rubric, $alloc->id, 2, 'a', 1, 'b'),
        ]);
        $grade = $this->reload($alloc)->grade;
        $this->assertNull($service->get_other_method($service->get_submitted_instance($alloc)));

        // Switch to a marking guide: the old review keeps its rubric.
        $this->use_guide();
        $instance = $service->get_submitted_instance($this->reload($alloc));
        $this->assertInstanceOf(\gradingform_rubric_instance::class, $instance);
        $this->assertSame('rubric', $service->get_other_method($instance));
        $this->assertEquals($grade, $this->reload($alloc)->grade);

        // A review without stored data under the new method is still shown with the old one only if it has some.
        $other = $this->reload($alloc);
        $other->id = -1;
        $this->assertNull($service->get_submitted_instance($other));

        // Switch to the simple form (no method): the rubric is still shown.
        $this->setAdminUser();
        get_grading_manager($this->context, 'mod_peerreview', 'received')->set_active_method(null);
        $this->setUser(0);
        $instance = $service->get_submitted_instance($this->reload($alloc));
        $this->assertInstanceOf(\gradingform_rubric_instance::class, $instance);
        $this->assertSame('rubric', $service->get_other_method($instance));
    }
}
