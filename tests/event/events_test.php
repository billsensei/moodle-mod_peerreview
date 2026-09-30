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
 * Event tests for mod_peerreview.
 *
 * Modelled on mod/workshop/tests/event/events_test.php.
 *
 * @package    mod_peerreview
 * @category   test
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_peerreview\event;

use mod_peerreview\local\feedback;
use mod_peerreview\local\grade\override;
use mod_peerreview\local\review\service;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Every event is triggered by the real code path, carries the right data and can be described, linked and restored.
 *
 * @package    mod_peerreview
 * @category   test
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(allocation_created::class)]
#[CoversClass(allocation_deleted::class)]
#[CoversClass(course_module_instance_list_viewed::class)]
#[CoversClass(course_module_viewed::class)]
#[CoversClass(feedback_released::class)]
#[CoversClass(grade_overridden::class)]
#[CoversClass(review_submitted::class)]
#[CoversClass(review_updated::class)]
final class events_test extends \advanced_testcase {
    use \mod_peerreview\tests\activity_trait;

    /**
     * Common checks: the single captured event, its context, description, URL, name and restore mappings.
     *
     * @param \phpunit_event_sink $sink Sink holding the events.
     * @param string $class Expected class.
     * @param \context $context Expected context.
     * @return \core\event\base The event.
     */
    private function assert_single_event(\phpunit_event_sink $sink, string $class, \context $context): \core\event\base {
        $events = array_values(array_filter($sink->get_events(), fn($e) => $e instanceof $class));
        $this->assertCount(1, $events, $class);
        $event = $events[0];
        $this->assertEquals($context->id, $event->contextid);
        $this->assertNotEmpty($event->get_description());
        $this->assertNotEmpty($class::get_name());
        $this->assertInstanceOf(\moodle_url::class, $event->get_url());
        $this->assertEventContextNotUsed($event);
        $this->assert_valid_restore_mapping($event);
        return $event;
    }

    /**
     * Assert the objectid mapping is one the restore step creates (or the activity itself).
     *
     * @param \core\event\base $event The event.
     */
    private function assert_valid_restore_mapping(\core\event\base $event): void {
        if ($event->objecttable === null) {
            return; // Nothing to map (core's instance list event).
        }
        $mapping = $event::get_objectid_mapping();
        $this->assertContains($mapping['restore'], ['peerreview', 'peerreview_alloc']);
        $this->assertEquals($event->objecttable, $mapping['db']);
    }

    /**
     * Viewing the activity and the course index.
     */
    public function test_view_events(): void {
        $this->resetAfterTest();
        $this->create_activity(2);
        $sink = $this->redirectEvents();

        course_module_viewed::create(['objectid' => $this->peerreview->id, 'context' => $this->context])->trigger();
        $event = $this->assert_single_event($sink, course_module_viewed::class, $this->context);
        $this->assertEquals($this->peerreview->id, $event->objectid);
        $this->assertEquals('r', $event->crud);

        $sink->clear();
        $coursecontext = \context_course::instance($this->course->id);
        course_module_instance_list_viewed::create(['context' => $coursecontext])->trigger();
        $this->assert_single_event($sink, course_module_instance_list_viewed::class, $coursecontext);
    }

    /**
     * Creating and deleting an allocation.
     */
    public function test_allocation_events(): void {
        $this->resetAfterTest();
        $this->create_activity(3);
        $sink = $this->redirectEvents();

        $alloc = $this->allocate(1, 2);
        $event = $this->assert_single_event($sink, allocation_created::class, $this->context);
        $this->assertEquals($alloc->id, $event->objectid);
        $this->assertEquals($this->students[2]->id, $event->relateduserid);
        $this->assertEquals($this->students[1]->id, $event->other['reviewerid']);
        $this->assertEquals(['reviewerid' => ['db' => 'user', 'restore' => 'user']], allocation_created::get_other_mapping());

        $sink->clear();
        $this->manager->delete([$alloc->id], false);
        $event = $this->assert_single_event($sink, allocation_deleted::class, $this->context);
        $this->assertEquals($alloc->id, $event->objectid);
        $this->assertEquals('d', $event->crud);
        $this->assertEquals($this->students[1]->id, $event->other['reviewerid']);
    }

    /**
     * First submit fires review_submitted, later ones review_updated; the reviewer is the actor.
     */
    public function test_review_events(): void {
        $this->resetAfterTest();
        $this->create_activity(3);
        $alloc = $this->allocate(1, 2);
        $service = new service($this->peerreview, $this->context);
        $this->setUser($this->students[1]);
        $sink = $this->redirectEvents();

        $service->submit($alloc, $this->students[1]->id, ['score' => '70', 'feedback' => '', 'feedbackformat' => FORMAT_HTML]);
        $event = $this->assert_single_event($sink, review_submitted::class, $this->context);
        $this->assertEquals($this->students[1]->id, $event->userid);
        $this->assertEquals($this->students[2]->id, $event->relateduserid);
        $this->assertEquals($alloc->id, $event->objectid);

        $sink->clear();
        $service->submit(
            $service->get_allocation($alloc->id),
            $this->students[1]->id,
            ['score' => '80', 'feedback' => '', 'feedbackformat' => FORMAT_HTML]
        );
        $event = $this->assert_single_event($sink, review_updated::class, $this->context);
        $this->assertEquals('u', $event->crud);
    }

    /**
     * Releasing and hiding feedback.
     */
    public function test_feedback_released(): void {
        $this->resetAfterTest();
        $this->create_activity(2);
        $this->setUser($this->teacher);
        $sink = $this->redirectEvents();

        feedback::set_released($this->peerreview, $this->context, true);
        $event = $this->assert_single_event($sink, feedback_released::class, $this->context);
        $this->assertTrue((bool) $event->other['released']);
        $this->assertStringContainsString('released', $event->get_description());

        $sink->clear();
        feedback::set_released($this->peerreview, $this->context, false);
        $event = $this->assert_single_event($sink, feedback_released::class, $this->context);
        $this->assertStringContainsString('hid', $event->get_description());
    }

    /**
     * Setting and removing a grade override.
     */
    public function test_grade_overridden(): void {
        $this->resetAfterTest();
        $this->create_activity(2);
        $this->setUser($this->teacher);
        $override = new override($this->peerreview, $this->context);
        $sink = $this->redirectEvents();

        $override->set($this->students[1]->id, 65.0, 'note', $this->teacher->id);
        $event = $this->assert_single_event($sink, grade_overridden::class, $this->context);
        $this->assertEquals($this->students[1]->id, $event->relateduserid);
        $this->assertEquals(65.0, $event->other['grade']);
        $this->assertFalse((bool) $event->other['reverted']);

        $sink->clear();
        $override->revert($this->students[1]->id);
        $event = $this->assert_single_event($sink, grade_overridden::class, $this->context);
        $this->assertTrue((bool) $event->other['reverted']);
        $this->assertStringContainsString('removed', $event->get_description());
    }

    /**
     * Events refuse to be created without their required data.
     *
     * @param string $class Event class.
     * @param array $data Creation data apart from the context.
     */
    #[DataProvider('invalid_event_provider')]
    public function test_validation(string $class, array $data): void {
        $this->resetAfterTest();
        $this->create_activity(1);
        $this->expectException(\coding_exception::class);
        $class::create($data + ['context' => $this->context]);
    }

    /**
     * Invalid creation data per event.
     *
     * @return array[]
     */
    public static function invalid_event_provider(): array {
        return [
            'allocation_created without reviewer' => [allocation_created::class, ['objectid' => 1, 'relateduserid' => 2]],
            'allocation_deleted without reviewer' => [allocation_deleted::class, ['objectid' => 1, 'relateduserid' => 2]],
            'review_submitted without reviewee' => [review_submitted::class, ['objectid' => 1]],
            'review_updated without reviewee' => [review_updated::class, ['objectid' => 1]],
            'feedback_released without flag' => [feedback_released::class, ['objectid' => 1]],
            'grade_overridden without grade' => [grade_overridden::class, ['objectid' => 1, 'relateduserid' => 2]],
            'grade_overridden without student' => [grade_overridden::class,
                ['objectid' => 1, 'other' => ['grade' => 1, 'reverted' => false]]],
        ];
    }
}
