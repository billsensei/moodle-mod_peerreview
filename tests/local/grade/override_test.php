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
 * Tests for grade overrides.
 *
 * @package    mod_peerreview
 * @category   test
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_peerreview\local\grade;

use mod_peerreview\tests\activity_trait;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for grade overrides.
 *
 * @package    mod_peerreview
 * @category   test
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(override::class)]
final class override_test extends \advanced_testcase {
    use activity_trait;

    /**
     * Set, change and remove an override; each change is logged and the peer data is untouched.
     */
    public function test_set_change_revert(): void {
        $this->resetAfterTest();
        global $DB;
        $this->create_activity(2);
        $alloc = $this->allocate(1, 2, 2, 55);
        $this->setUser($this->teacher);
        $override = new override($this->peerreview, $this->context);
        $userid = (int) $this->students[2]->id;
        $sink = $this->redirectEvents();

        $override->set($userid, 90, 'first note', (int) $this->teacher->id);
        $row = $override->get($userid);
        $this->assertEquals(90, $row->grade);
        $this->assertSame('first note', $row->note);
        $this->assertEquals($this->teacher->id, $row->overriddenby);

        $override->set($userid, 72.5, 'changed', (int) $this->teacher->id);
        $this->assertSame(1, $DB->count_records('peerreview_override'), 'one row per student');
        $this->assertEquals(72.5, $override->get($userid)->grade);

        $this->assertTrue($override->revert($userid));
        $this->assertNull($override->get($userid));
        $this->assertFalse($override->revert($userid), 'nothing left to remove');

        $events = array_values(array_filter(
            $sink->get_events(),
            static fn($e) => $e instanceof \mod_peerreview\event\grade_overridden
        ));
        $this->assertCount(3, $events);
        $this->assertFalse($events[0]->other['reverted']);
        $this->assertTrue($events[2]->other['reverted']);
        $this->assertEquals($userid, $events[0]->relateduserid);

        $this->assertEquals(55, $DB->get_field('peerreview_alloc', 'grade', ['id' => $alloc->id]), 'peer data untouched');
    }

    /**
     * The grade must be inside 0 to the activity maximum.
     */
    public function test_range(): void {
        $this->resetAfterTest();
        $this->create_activity(2, ['grade' => 50]);
        $this->setUser($this->teacher);
        $override = new override($this->peerreview, $this->context);
        foreach ([-1, 50.5] as $bad) {
            try {
                $override->set((int) $this->students[1]->id, $bad, '', (int) $this->teacher->id);
                $this->fail("Expected a range error for $bad");
            } catch (\moodle_exception $e) {
                $this->assertSame('errorscorerange', $e->errorcode);
            }
        }
        $override->set((int) $this->students[1]->id, 50, '', (int) $this->teacher->id);
        $override->set((int) $this->students[1]->id, 0, '', (int) $this->teacher->id);
        $this->assertEquals(0, $override->get((int) $this->students[1]->id)->grade, 'zero is a real grade');
    }

    /**
     * Students cannot override grades.
     */
    public function test_requires_capability(): void {
        $this->resetAfterTest();
        global $DB;
        $this->create_activity(2);
        $this->setUser($this->students[1]);
        $override = new override($this->peerreview, $this->context);
        try {
            $override->set((int) $this->students[2]->id, 100, 'cheeky', (int) $this->students[1]->id);
            $this->fail('Expected a capability exception');
        } catch (\required_capability_exception $e) {
            $this->assertSame(0, $DB->count_records('peerreview_override'));
        }
        $this->expectException(\required_capability_exception::class);
        $override->revert((int) $this->students[2]->id);
    }
}
