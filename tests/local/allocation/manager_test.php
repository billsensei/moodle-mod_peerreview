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
 * Tests for the allocation manager (database level).
 *
 * @package    mod_peerreview
 * @category   test
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_peerreview\local\allocation;

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the allocation manager (database level).
 *
 * @package    mod_peerreview
 * @category   test
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(manager::class)]
final class manager_test extends \advanced_testcase {
    /**
     * Create a course with students and one activity.
     *
     * @param int $nstudents Number of students.
     * @param array $activity Extra activity fields.
     * @param int $groupmode Group mode of the activity.
     * @return array [manager, course, activity, students by index starting at 1, teacher]
     */
    private function setup_course(int $nstudents, array $activity = [], int $groupmode = NOGROUPS): array {
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $students = [];
        for ($i = 1; $i <= $nstudents; $i++) {
            $students[$i] = $generator->create_and_enrol($course, 'student', ['lastname' => sprintf('Student%02d', $i)]);
        }
        $teacher = $generator->create_and_enrol($course, 'editingteacher');
        $instance = $generator->create_module('peerreview', ['course' => $course->id, 'groupmode' => $groupmode] + $activity);
        return [$this->manager_for($instance), $course, $instance, $students, $teacher];
    }

    /**
     * Build a manager for an activity.
     *
     * @param \stdClass $instance Activity record with cmid.
     * @return manager
     */
    private function manager_for(\stdClass $instance): manager {
        global $DB;
        $cm = get_coursemodule_from_id('peerreview', $instance->cmid, 0, false, MUST_EXIST);
        $peerreview = $DB->get_record('peerreview', ['id' => $instance->id], '*', MUST_EXIST);
        return new manager($peerreview, $cm, \context_module::instance($cm->id));
    }

    /**
     * Only active enrolled students holding the review capability are eligible.
     */
    public function test_students_are_active_enrolled_reviewers(): void {
        $this->resetAfterTest();
        [$manager, $course, , $students, $teacher] = $this->setup_course(3);
        $suspended = $this->getDataGenerator()->create_and_enrol($course, 'student', [], 'manual', 0, 0, ENROL_USER_SUSPENDED);
        $eligible = array_keys($manager->get_students());
        $this->assertEqualsCanonicalizing(array_map(static fn($s) => $s->id, $students), $eligible);
        $this->assertNotContains((int) $teacher->id, $eligible);
        $this->assertNotContains((int) $suspended->id, $eligible);
    }

    /**
     * Manual add: created once, duplicates ignored, self-review refused unless allowed, events fired.
     */
    public function test_manual_add(): void {
        $this->resetAfterTest();
        global $DB;
        [$manager, , , $s, $teacher] = $this->setup_course(3);
        $sink = $this->redirectEvents();

        [$created, $errors] = $manager->add_manual($s[1]->id, [$s[2]->id, $s[3]->id, $s[1]->id], $teacher->id);
        $this->assertSame(2, $created);
        $this->assertEquals([$s[1]->id => 'errorselfnotallowed'], $errors);
        $events = array_filter($sink->get_events(), static fn($e) => $e instanceof \mod_peerreview\event\allocation_created);
        $this->assertCount(2, $events);

        [$created] = $manager->add_manual($s[1]->id, [$s[2]->id], $teacher->id);
        $this->assertSame(0, $created);
        $this->assertSame(2, $DB->count_records('peerreview_alloc'));
        $row = $DB->get_record('peerreview_alloc', ['reviewerid' => $s[1]->id, 'revieweeid' => $s[2]->id], '*', MUST_EXIST);
        $this->assertEquals(manager::STATUS_NEW, $row->status);
        $this->assertEquals($teacher->id, $row->allocatedby);
    }

    /**
     * Self-review can be added manually when the activity allows it.
     */
    public function test_manual_self_review_when_allowed(): void {
        $this->resetAfterTest();
        [$manager, , , $s, $teacher] = $this->setup_course(2, ['allowselfreview' => 1]);
        [$created, $errors] = $manager->add_manual($s[1]->id, [$s[1]->id], $teacher->id);
        $this->assertSame(1, $created);
        $this->assertSame([], $errors);
    }

    /**
     * Random allocation stays inside groups unless cross-group is allowed.
     */
    public function test_random_respects_groups(): void {
        $this->resetAfterTest();
        [$manager, $course, $instance, $s, $teacher] = $this->setup_course(8, [], SEPARATEGROUPS);
        $generator = $this->getDataGenerator();
        $g1 = $generator->create_group(['courseid' => $course->id]);
        $g2 = $generator->create_group(['courseid' => $course->id]);
        foreach ([1, 2, 3, 4] as $i) {
            $generator->create_group_member(['groupid' => $g1->id, 'userid' => $s[$i]->id]);
        }
        foreach ([5, 6, 7, 8] as $i) {
            $generator->create_group_member(['groupid' => $g2->id, 'userid' => $s[$i]->id]);
        }
        $manager = $this->manager_for($instance);

        $plan = $manager->plan('random', ['n' => 2, 'crossgroup' => 0, 'replace' => 0], 11);
        $this->assertSame([], $plan->proposal->warnings);
        $this->assertCount(16, $plan->proposal->pairs);
        foreach ($plan->proposal->pairs as [$reviewer, $reviewee]) {
            $this->assertSame($reviewer <= $s[4]->id, $reviewee <= $s[4]->id);
        }

        $cross = $manager->plan('random', ['n' => 1, 'crossgroup' => 1, 'replace' => 0], 12);
        $this->assertCount(8, $cross->proposal->pairs);

        $this->assertSame(16, $manager->save($plan->proposal, $teacher->id));
        $this->assertEquals($g1->id, $manager->get_primary_group($s[1]->id));
        $this->assertEquals($g2->id, array_values($manager->get_allocations())[15]->groupid);
    }

    /**
     * A student in no group forms a pool of one and is reported, not silently dropped.
     */
    public function test_student_without_group_is_reported(): void {
        $this->resetAfterTest();
        [$manager, $course, $instance, $s] = $this->setup_course(5, [], SEPARATEGROUPS);
        $generator = $this->getDataGenerator();
        $g1 = $generator->create_group(['courseid' => $course->id]);
        foreach ([1, 2, 3, 4] as $i) {
            $generator->create_group_member(['groupid' => $g1->id, 'userid' => $s[$i]->id]);
        }
        $manager = $this->manager_for($instance);
        $plan = $manager->plan('random', ['n' => 1, 'crossgroup' => 0, 'replace' => 0], 3);
        $this->assertCount(4, $plan->proposal->pairs);
        $this->assertSame('warnpooltoosmall', $plan->proposal->warnings[0][0]);
    }

    /**
     * All-to-all within groups (a student in two groups is not paired twice); rotation with order options.
     */
    public function test_group_and_rotation_plans(): void {
        $this->resetAfterTest();
        [$manager, $course, $instance, $s] = $this->setup_course(5);
        $generator = $this->getDataGenerator();
        $g1 = $generator->create_group(['courseid' => $course->id]);
        $g2 = $generator->create_group(['courseid' => $course->id]);
        foreach ([1, 2, 3] as $i) {
            $generator->create_group_member(['groupid' => $g1->id, 'userid' => $s[$i]->id]);
        }
        foreach ([3, 4, 5] as $i) {
            $generator->create_group_member(['groupid' => $g2->id, 'userid' => $s[$i]->id]);
        }
        $manager = $this->manager_for($instance);

        $all = $manager->plan('group', ['groupid' => 0, 'replace' => 0], 1);
        $this->assertCount(12, $all->proposal->pairs);
        $this->assertCount(12, array_unique(array_map('serialize', $all->proposal->pairs)));
        $one = $manager->plan('group', ['groupid' => $g1->id, 'replace' => 0], 1);
        $this->assertCount(6, $one->proposal->pairs);

        $rotation = $manager->plan('rotation', ['shift' => 1, 'sortby' => 'lastname', 'usernames' => '', 'replace' => 0], 1);
        $this->assertEquals([$s[1]->id, $s[2]->id], $rotation->proposal->pairs[0]);
        $this->assertEquals([$s[5]->id, $s[1]->id], $rotation->proposal->pairs[4]);

        $custom = "{$s[3]->username}\n{$s[1]->username}\nnobody\n{$s[2]->username}";
        $listed = $manager->plan('rotation', ['shift' => 1, 'sortby' => 'lastname', 'usernames' => $custom, 'replace' => 0], 1);
        $this->assertEquals([[$s[3]->id, $s[1]->id], [$s[1]->id, $s[2]->id], [$s[2]->id, $s[3]->id]], $listed->proposal->pairs);
        $this->assertSame('warnunknownusername', $listed->proposal->warnings[0][0]);
    }

    /**
     * A student who joins later is allocated without touching existing pairs.
     */
    public function test_late_joiner_leaves_existing_pairs_alone(): void {
        $this->resetAfterTest();
        global $DB;
        [$manager, $course, $instance, , $teacher] = $this->setup_course(6);
        $plan = $manager->plan('random', ['n' => 2, 'crossgroup' => 0, 'replace' => 0], 5);
        $manager->save($plan->proposal, $teacher->id);
        $before = $DB->get_records('peerreview_alloc', null, 'id', 'id, reviewerid, revieweeid');

        $late = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $manager = $this->manager_for($instance);
        $plan = $manager->plan('random', ['n' => 2, 'crossgroup' => 0, 'replace' => 0], 6);
        $manager->save($plan->proposal, $teacher->id);

        $after = $DB->get_records('peerreview_alloc', null, 'id', 'id, reviewerid, revieweeid');
        foreach ($before as $id => $row) {
            $this->assertEquals($row, $after[$id]);
        }
        $given = $DB->count_records('peerreview_alloc', ['reviewerid' => $late->id]);
        $received = $DB->count_records('peerreview_alloc', ['revieweeid' => $late->id]);
        $this->assertGreaterThanOrEqual(2, $given);
        $this->assertGreaterThanOrEqual(2, $received);
    }

    /**
     * "Replace" removes not-started allocations only; drafts and submitted reviews are kept and counted.
     */
    public function test_replace_keeps_started_reviews(): void {
        $this->resetAfterTest();
        global $DB;
        [$manager, , , $s, $teacher] = $this->setup_course(5);
        $manager->add_manual($s[1]->id, [$s[2]->id, $s[3]->id, $s[4]->id], $teacher->id);
        $draft = $DB->get_record('peerreview_alloc', ['reviewerid' => $s[1]->id, 'revieweeid' => $s[2]->id]);
        $submitted = $DB->get_record('peerreview_alloc', ['reviewerid' => $s[1]->id, 'revieweeid' => $s[3]->id]);
        $DB->set_field('peerreview_alloc', 'status', manager::STATUS_DRAFT, ['id' => $draft->id]);
        $DB->set_field('peerreview_alloc', 'status', manager::STATUS_SUBMITTED, ['id' => $submitted->id]);

        $plan = $manager->plan('random', ['n' => 1, 'crossgroup' => 0, 'replace' => 1], 9);
        $this->assertCount(1, $plan->deleteids, 'only the not-started pair is replaceable');
        $this->assertSame(2, $plan->keptstarted);
        $manager->save($plan->proposal, $teacher->id, $plan->deleteids);
        $this->assertTrue($DB->record_exists('peerreview_alloc', ['id' => $draft->id]));
        $this->assertTrue($DB->record_exists('peerreview_alloc', ['id' => $submitted->id]));
        $this->assertFalse($DB->record_exists('peerreview_alloc', ['reviewerid' => $s[1]->id, 'revieweeid' => $s[4]->id]));
    }

    /**
     * Started reviews are never deleted without confirmation; with it they are, and events fire.
     */
    public function test_delete_requires_confirmation_for_started(): void {
        $this->resetAfterTest();
        global $DB;
        [$manager, , , $s, $teacher] = $this->setup_course(3);
        $manager->add_manual($s[1]->id, [$s[2]->id, $s[3]->id], $teacher->id);
        $new = $DB->get_field('peerreview_alloc', 'id', ['reviewerid' => $s[1]->id, 'revieweeid' => $s[2]->id]);
        $started = $DB->get_field('peerreview_alloc', 'id', ['reviewerid' => $s[1]->id, 'revieweeid' => $s[3]->id]);
        $DB->set_field('peerreview_alloc', 'status', manager::STATUS_SUBMITTED, ['id' => $started]);

        $this->assertSame(1, $manager->count_started([$new, $started]));
        try {
            $manager->delete([$new, $started], false);
            $this->fail('Expected an exception');
        } catch (\moodle_exception $e) {
            $this->assertSame('errorstartedreviews', $e->errorcode);
        }
        $this->assertSame(2, $DB->count_records('peerreview_alloc'), 'nothing was deleted');

        $this->assertSame(1, $manager->delete([$new], false));
        $sink = $this->redirectEvents();
        $this->assertSame(1, $manager->delete([$started], true));
        $events = $sink->get_events();
        $this->assertInstanceOf(\mod_peerreview\event\allocation_deleted::class, $events[0]);
        $this->assertEquals($s[3]->id, $events[0]->relateduserid);
        $this->assertSame(0, $DB->count_records('peerreview_alloc'));
    }

    /**
     * Allocations of another activity cannot be deleted through this manager.
     */
    public function test_delete_ignores_other_activities(): void {
        $this->resetAfterTest();
        global $DB;
        [$manager, $course, , $s, $teacher] = $this->setup_course(3);
        $other = $this->getDataGenerator()->create_module('peerreview', ['course' => $course->id]);
        $othermanager = $this->manager_for($other);
        $othermanager->add_manual($s[1]->id, [$s[2]->id], $teacher->id);
        $id = $DB->get_field('peerreview_alloc', 'id', ['peerreviewid' => $other->id]);
        $this->assertSame(0, $manager->delete([$id], true));
        $this->assertTrue($DB->record_exists('peerreview_alloc', ['id' => $id]));
    }

    /**
     * CSV rows: usernames and emails work, bad rows are reported with their line, duplicates counted.
     */
    public function test_csv_proposal(): void {
        $this->resetAfterTest();
        [$manager, , , $s, $teacher] = $this->setup_course(4);
        $manager->add_manual($s[1]->id, [$s[2]->id], $teacher->id);
        $rows = [
            2 => [$s[1]->username, $s[3]->username],
            3 => [strtoupper($s[2]->email), $s[1]->username],
            4 => [$s[1]->username, $s[2]->username],
            5 => [$s[1]->username, 'ghost'],
            6 => [$s[4]->username, $s[4]->username],
            7 => [$s[3]->username, $s[4]->username],
            8 => [$s[3]->username, $s[4]->username],
        ];
        [$proposal, $errors, $duplicates] = $manager->build_csv_proposal($rows);
        $this->assertEqualsCanonicalizing(
            [[$s[1]->id, $s[3]->id], [$s[2]->id, $s[1]->id], [$s[3]->id, $s[4]->id]],
            $proposal->pairs
        );
        $this->assertSame(2, $duplicates, 'existing pair on line 4 and repeated pair on line 8');
        $this->assertSame([5, 'csverrorunknown', 'ghost'], $errors[0]);
        $this->assertSame([6, 'errorselfnotallowed', $s[4]->username], $errors[1]);
    }

    /**
     * Export rows: reviewer, reviewee, status text.
     */
    public function test_export_rows(): void {
        $this->resetAfterTest();
        [$manager, , , $s, $teacher] = $this->setup_course(2);
        $manager->add_manual($s[1]->id, [$s[2]->id], $teacher->id);
        $rows = iterator_to_array($manager->export_rows(), false);
        $this->assertCount(1, $rows);
        $this->assertSame($s[1]->username, $rows[0]->reviewer);
        $this->assertSame($s[2]->username, $rows[0]->reviewee);
        $this->assertSame(get_string('statusnew', 'mod_peerreview'), $rows[0]->status);
    }
}
