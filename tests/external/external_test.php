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
 * Tests for the mod_peerreview web services.
 *
 * @package    mod_peerreview
 * @category   test
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_peerreview\external;

use core_external\external_api;
use mod_peerreview\tests\activity_trait;
use mod_peerreview\local\allocation\manager;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the mod_peerreview web services.
 *
 * @package    mod_peerreview
 * @category   test
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(get_progress::class)]
#[CoversClass(set_feedback_release::class)]
final class external_test extends \advanced_testcase {
    use activity_trait;

    /**
     * The progress figures come back in the declared structure.
     */
    public function test_get_progress(): void {
        $this->resetAfterTest();
        $this->create_activity(3);
        $this->allocate(1, 2, manager::STATUS_SUBMITTED, 80);
        $this->allocate(1, 3);
        $this->setUser($this->teacher);

        $result = external_api::clean_returnvalue(
            get_progress::execute_returns(),
            get_progress::execute($this->cm->id)
        );
        $this->assertSame(3, $result['students']);
        $this->assertSame(2, $result['allocations']);
        $this->assertSame(1, $result['submitted']);
        $rows = array_column($result['rows'], null, 'userid');
        $this->assertSame(1, $rows[$this->students[1]->id]['givendone']);
        $this->assertSame(2, $rows[$this->students[1]->id]['giventotal']);
        $this->assertSame(50, $rows[$this->students[1]->id]['participation']);
        $this->assertSame('80.00', $rows[$this->students[2]->id]['grade']);
        $this->assertSame('', $rows[$this->students[3]->id]['grade']);
        $this->assertSame(-1, $rows[$this->students[2]->id]['participation'], 'nothing assigned to give');
    }

    /**
     * Students cannot read the progress of the class.
     */
    public function test_get_progress_requires_capability(): void {
        $this->resetAfterTest();
        $this->create_activity(2);
        $this->setUser($this->students[1]);
        $this->expectException(\required_capability_exception::class);
        get_progress::execute($this->cm->id);
    }

    /**
     * A teacher limited to their own group gets only that group, whatever group id is asked for.
     */
    public function test_get_progress_respects_groups(): void {
        $this->resetAfterTest();
        $this->create_activity(4, [], SEPARATEGROUPS);
        $generator = $this->getDataGenerator();
        $mine = $generator->create_group(['courseid' => $this->course->id]);
        $other = $generator->create_group(['courseid' => $this->course->id]);
        $generator->create_group_member(['groupid' => $mine->id, 'userid' => $this->students[1]->id]);
        $generator->create_group_member(['groupid' => $other->id, 'userid' => $this->students[2]->id]);
        $noneditor = $this->create_group_limited_teacher();
        $generator->create_group_member(['groupid' => $mine->id, 'userid' => $noneditor->id]);

        $this->setUser($noneditor);
        foreach ([0, (int) $other->id] as $asked) {
            $result = get_progress::execute($this->cm->id, $asked);
            // The limited teacher is enrolled as a student too (they need a role without accessallgroups), so appears.
            $this->assertEqualsCanonicalizing(
                [(int) $this->students[1]->id, (int) $noneditor->id],
                array_column($result['rows'], 'userid'),
                "asked for group $asked"
            );
        }
        $this->setUser($this->teacher);
        $this->assertSame(5, get_progress::execute($this->cm->id, 0)['students']);
    }

    /**
     * Releasing and hiding feedback: capability, state, and an event carrying the new state.
     */
    public function test_set_feedback_release(): void {
        $this->resetAfterTest();
        global $DB;
        $this->create_activity(2);
        $sink = $this->redirectEvents();
        $this->setUser($this->teacher);

        $this->assertTrue(set_feedback_release::execute($this->cm->id, true)['released']);
        $this->assertEquals(1, $DB->get_field('peerreview', 'feedbackreleased', ['id' => $this->peerreview->id]));
        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(\mod_peerreview\event\feedback_released::class, $events[0]);
        $this->assertTrue($events[0]->other['released']);

        $sink->clear();
        set_feedback_release::execute($this->cm->id, true);
        $this->assertCount(0, $sink->get_events(), 'no change, no event');

        $this->assertFalse(set_feedback_release::execute($this->cm->id, false)['released']);
        $this->assertFalse($sink->get_events()[0]->other['released']);
    }

    /**
     * Students cannot release feedback; neither can a teacher lacking the capability.
     */
    public function test_set_feedback_release_requires_capability(): void {
        $this->resetAfterTest();
        global $DB;
        $this->create_activity(2);
        $this->setUser($this->students[1]);
        try {
            set_feedback_release::execute($this->cm->id, true);
            $this->fail('Expected a capability exception');
        } catch (\required_capability_exception $e) {
            $this->assertEquals(0, $DB->get_field('peerreview', 'feedbackreleased', ['id' => $this->peerreview->id]));
        }
    }
}
