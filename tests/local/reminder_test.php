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

namespace mod_peerreview\local;

use mod_peerreview\local\allocation\manager;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the reminders.
 *
 * @package    mod_peerreview
 * @category   test
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(reminder::class)]
#[CoversClass(\mod_peerreview\event\reminders_sent::class)]
final class reminder_test extends \advanced_testcase {
    use \mod_peerreview\tests\activity_trait;

    /**
     * Only students with reviews left get a message; the count, wording and event are right.
     */
    public function test_reminds_only_students_with_reviews_left(): void {
        $this->resetAfterTest();
        $this->create_activity(4);
        $this->allocate(1, 2, manager::STATUS_SUBMITTED, 50); // Student 1 is finished.
        $this->allocate(2, 3);                                // Student 2 has one left.
        $this->allocate(3, 1, manager::STATUS_DRAFT);         // Student 3 has a draft only.
        // Student 4 has nothing assigned.

        $sink = $this->redirectMessages();
        $eventsink = $this->redirectEvents();
        $reminder = new reminder($this->peerreview, $this->cm, $this->context);
        $this->setUser($this->teacher);
        $this->assertSame(2, $reminder->send(0, (int) $this->teacher->id));

        $messages = $sink->get_messages();
        $this->assertCount(2, $messages);
        $recipients = array_map(fn($m) => (int) $m->useridto, $messages);
        sort($recipients);
        $expected = [(int) $this->students[2]->id, (int) $this->students[3]->id];
        sort($expected);
        $this->assertSame($expected, $recipients);
        $this->assertSame((int) $this->teacher->id, (int) $messages[0]->useridfrom);
        $this->assertStringContainsString('1 of 1', $messages[0]->fullmessage);
        $this->assertStringContainsString('/mod/peerreview/view.php?id=' . $this->cm->id, $messages[0]->fullmessage);

        $events = array_values(array_filter(
            $eventsink->get_events(),
            fn($e) => $e instanceof \mod_peerreview\event\reminders_sent
        ));
        $this->assertCount(1, $events);
        $this->assertSame(2, $events[0]->other['count']);
    }

    /**
     * A group limits who is reminded.
     */
    public function test_group_limits_recipients(): void {
        $this->resetAfterTest();
        $this->create_activity(3);
        $this->allocate(1, 2);
        $this->allocate(2, 3);
        $group = $this->getDataGenerator()->create_group(['courseid' => $this->course->id]);
        $this->getDataGenerator()->create_group_member(['groupid' => $group->id, 'userid' => $this->students[1]->id]);

        $sink = $this->redirectMessages();
        $this->setUser($this->teacher);
        $reminder = new reminder($this->peerreview, $this->cm, $this->context);
        $this->assertSame(1, $reminder->send((int) $group->id, (int) $this->teacher->id));
        $this->assertSame((int) $this->students[1]->id, (int) $sink->get_messages()[0]->useridto);
        $this->assertSame(0, $reminder->send(-1, (int) $this->teacher->id));
    }

    /**
     * No reminders outside the open dates, and students may not send them.
     */
    public function test_closed_and_permissions(): void {
        $this->resetAfterTest();
        global $DB;
        $this->create_activity(2, ['timeclose' => time() - DAYSECS]);
        $this->allocate(1, 2);
        $this->setUser($this->teacher);
        $reminder = new reminder($this->peerreview, $this->cm, $this->context);
        $this->assertFalse($reminder->is_open());
        try {
            $reminder->send(0, (int) $this->teacher->id);
            $this->fail('A closed activity must not send reminders.');
        } catch (\moodle_exception $e) {
            $this->assertSame('remindernotopen', $e->errorcode);
        }

        $DB->set_field('peerreview', 'timeclose', 0, ['id' => $this->peerreview->id]);
        $this->peerreview->timeclose = 0;
        $this->setUser($this->students[1]);
        $this->expectException(\required_capability_exception::class);
        (new reminder($this->peerreview, $this->cm, $this->context))->send(0, (int) $this->students[1]->id);
    }
}
