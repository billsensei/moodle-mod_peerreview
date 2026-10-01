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
 * Tests for progress figures and group access.
 *
 * @package    mod_peerreview
 * @category   test
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_peerreview\local;

use mod_peerreview\tests\activity_trait;
use mod_peerreview\local\allocation\manager;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for progress figures and group access.
 *
 * @package    mod_peerreview
 * @category   test
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(progress::class)]
#[CoversClass(group_access::class)]
final class progress_test extends \advanced_testcase {
    use activity_trait;

    /**
     * Counts of reviews given and received, grade and participation.
     */
    public function test_rows(): void {
        $this->resetAfterTest();
        $this->create_activity(4, ['allowselfreview' => 1]);
        $this->allocate(1, 2, manager::STATUS_SUBMITTED, 80);
        $this->allocate(1, 3, manager::STATUS_DRAFT, 20);
        $this->allocate(1, 4);
        $this->allocate(2, 1, manager::STATUS_SUBMITTED, 60);
        $this->allocate(2, 2, manager::STATUS_SUBMITTED, 100); // Self review.

        $rows = (new progress($this->peerreview, $this->manager))->get_rows();
        $one = $rows[$this->students[1]->id];
        $this->assertSame(1, $one->given_done);
        $this->assertSame(3, $one->given_total);
        $this->assertSame(33, $one->participation);
        $this->assertSame(1, $one->received_done);
        $this->assertSame(1, $one->received_total);
        $this->assertEquals(60.0, $one->grade);

        $two = $rows[$this->students[2]->id];
        $this->assertSame(2, $two->given_done, 'self review counts as work done');
        $this->assertSame(1, $two->received_done, 'self review is not "received"');
        $this->assertSame(1, $two->received_total);
        $this->assertEquals(80.0, $two->grade);

        $four = $rows[$this->students[4]->id];
        $this->assertSame(0, $four->given_total);
        $this->assertNull($four->participation);
        $this->assertNull($four->grade);

        $summary = (new progress($this->peerreview, $this->manager))->summarise($rows);
        $this->assertSame(4, $summary->students);
        $this->assertSame(5, $summary->allocations);
        $this->assertSame(3, $summary->submitted);
    }

    /**
     * Allocations involving a suspended student are left out of everyone's figures.
     */
    public function test_suspended_students_are_ignored(): void {
        $this->resetAfterTest();
        $this->create_activity(3);
        $this->allocate(1, 2, manager::STATUS_SUBMITTED, 80);
        $this->allocate(1, 3, manager::STATUS_SUBMITTED, 40);
        $this->getDataGenerator()->enrol_user(
            $this->students[3]->id,
            $this->course->id,
            'student',
            'manual',
            0,
            0,
            ENROL_USER_SUSPENDED
        );

        $manager = new manager($this->peerreview, $this->cm, $this->context);
        $rows = (new progress($this->peerreview, $manager))->get_rows();
        $this->assertArrayNotHasKey($this->students[3]->id, $rows);
        $this->assertSame(1, $rows[$this->students[1]->id]->given_total);
        $this->assertSame(1, $rows[$this->students[1]->id]->given_done);
    }

    /**
     * Group filter, and -1 means nobody.
     */
    public function test_group_filter(): void {
        $this->resetAfterTest();
        $this->create_activity(4);
        $generator = $this->getDataGenerator();
        $group = $generator->create_group(['courseid' => $this->course->id]);
        $generator->create_group_member(['groupid' => $group->id, 'userid' => $this->students[1]->id]);
        $generator->create_group_member(['groupid' => $group->id, 'userid' => $this->students[2]->id]);
        $progress = new progress($this->peerreview, $this->manager);

        $this->assertCount(4, $progress->get_rows(0));
        $this->assertEqualsCanonicalizing(
            [$this->students[1]->id, $this->students[2]->id],
            array_keys($progress->get_rows($group->id))
        );
        $this->assertSame([], $progress->get_rows(-1));
    }

    /**
     * A teacher restricted to their groups can only look at those.
     */
    public function test_group_access_resolve(): void {
        $this->resetAfterTest();
        $this->create_activity(2, [], SEPARATEGROUPS);
        $generator = $this->getDataGenerator();
        $mine = $generator->create_group(['courseid' => $this->course->id]);
        $other = $generator->create_group(['courseid' => $this->course->id]);
        $noneditor = $this->create_group_limited_teacher();
        $generator->create_group_member(['groupid' => $mine->id, 'userid' => $noneditor->id]);

        $cm = get_fast_modinfo($this->course)->get_cm($this->cm->id);
        $this->setUser($noneditor);
        $this->assertSame((int) $mine->id, group_access::resolve($cm, $this->context, 0), 'all participants becomes own group');
        $this->assertSame((int) $mine->id, group_access::resolve($cm, $this->context, (int) $other->id), 'other group refused');
        $this->assertSame((int) $mine->id, group_access::resolve($cm, $this->context, (int) $mine->id));

        $this->setUser($this->teacher); // Editing teachers can access all groups.
        $this->assertSame(0, group_access::resolve($cm, $this->context, 0));
        $this->assertSame((int) $other->id, group_access::resolve($cm, $this->context, (int) $other->id));

        $loner = $this->create_group_limited_teacher();
        $this->setUser($loner);
        $this->assertSame(-1, group_access::resolve($cm, $this->context, 0), 'in no group: sees nobody');
    }

    /**
     * Visible users: null without restriction, otherwise members of the user's own groups only.
     */
    public function test_group_access_visible_userids(): void {
        $this->resetAfterTest();
        $this->create_activity(3, [], SEPARATEGROUPS);
        $generator = $this->getDataGenerator();
        $mine = $generator->create_group(['courseid' => $this->course->id]);
        $other = $generator->create_group(['courseid' => $this->course->id]);
        $limited = $this->create_group_limited_teacher();
        $generator->create_group_member(['groupid' => $mine->id, 'userid' => $limited->id]);
        $generator->create_group_member(['groupid' => $mine->id, 'userid' => $this->students[1]->id]);
        $generator->create_group_member(['groupid' => $mine->id, 'userid' => $this->students[2]->id]);
        $generator->create_group_member(['groupid' => $other->id, 'userid' => $this->students[3]->id]);
        $cm = get_fast_modinfo($this->course)->get_cm($this->cm->id);

        $this->setUser($limited);
        $visible = group_access::visible_userids($cm, $this->context);
        $this->assertEqualsCanonicalizing(
            [(int) $limited->id, (int) $this->students[1]->id, (int) $this->students[2]->id],
            $visible
        );
        $this->assertNotContains((int) $this->students[3]->id, $visible);

        $this->setUser($this->teacher); // Has moodle/site:accessallgroups.
        $this->assertNull(group_access::visible_userids($cm, $this->context));

        $loner = $this->create_group_limited_teacher();
        $this->setUser($loner);
        $this->assertSame([], group_access::visible_userids($cm, $this->context), 'in no group: sees nobody');
    }

    /**
     * Outside separate-groups mode nobody is restricted.
     */
    public function test_group_access_visible_userids_unrestricted(): void {
        $this->resetAfterTest();
        $this->create_activity(2, [], NOGROUPS);
        $cm = get_fast_modinfo($this->course)->get_cm($this->cm->id);
        $this->setUser($this->create_group_limited_teacher());
        $this->assertNull(group_access::visible_userids($cm, $this->context));
    }
}
