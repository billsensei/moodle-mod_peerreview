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

namespace mod_peerreview\task;

use mod_peerreview\local\allocation\manager;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the automatic reminders before the close date.
 *
 * @package    mod_peerreview
 * @category   test
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(send_reminders::class)]
#[CoversClass(\mod_peerreview\local\reminder::class)]
final class send_reminders_test extends \advanced_testcase {
    use \mod_peerreview\tests\activity_trait;

    /**
     * Run the task and return the messages it sent.
     *
     * @return \stdClass[]
     */
    private function run_task(): array {
        $sink = $this->redirectMessages();
        ob_start();
        (new send_reminders())->execute();
        ob_end_clean();
        $messages = $sink->get_messages();
        $sink->close();
        return $messages;
    }

    /**
     * Inside the lead time, students with reviews left get one message from the no-reply user, and only once.
     */
    public function test_reminds_once_inside_the_lead_time(): void {
        $this->resetAfterTest();
        global $DB;
        $close = time() + 12 * HOURSECS;
        $this->create_activity(3, ['timeclose' => $close, 'reminderlead' => DAYSECS]);
        $this->allocate(1, 2, manager::STATUS_SUBMITTED, 50); // Finished.
        $this->allocate(2, 3);                                // One left.
        // Student 3 has nothing assigned.

        $eventsink = $this->redirectEvents();
        $messages = $this->run_task();
        $this->assertCount(1, $messages);
        $this->assertSame((int) $this->students[2]->id, (int) $messages[0]->useridto);
        $this->assertSame((int) \core_user::get_noreply_user()->id, (int) $messages[0]->useridfrom);
        $this->assertStringContainsString('/mod/peerreview/view.php?id=' . $this->cm->id, $messages[0]->fullmessage);
        $this->assertEquals($close, $DB->get_field('peerreview', 'remindersentfor', ['id' => $this->peerreview->id]));

        $events = array_values(array_filter(
            $eventsink->get_events(),
            fn($e) => $e instanceof \mod_peerreview\event\reminders_sent
        ));
        $this->assertCount(1, $events);
        $this->assertSame(1, $events[0]->other['count']);
        $this->assertTrue($events[0]->other['automatic']);
        $this->assertStringContainsString('Automatic', $events[0]->get_description());

        $this->assertCount(0, $this->run_task(), 'the same close date is never reminded twice');
    }

    /**
     * Nothing is sent before the lead time, with the lead time off, with no close date, after the close date or while
     * the activity has not opened.
     */
    public function test_not_due(): void {
        $this->resetAfterTest();
        global $DB;
        $this->create_activity(2, ['timeclose' => time() + 3 * DAYSECS, 'reminderlead' => DAYSECS]);
        $this->allocate(1, 2);
        $id = $this->peerreview->id;

        $this->assertCount(0, $this->run_task(), 'too early');
        $this->assertEquals(0, $DB->get_field('peerreview', 'remindersentfor', ['id' => $id]));

        $DB->update_record('peerreview', (object) ['id' => $id, 'timeclose' => time() + HOURSECS, 'reminderlead' => 0]);
        $this->assertCount(0, $this->run_task(), 'lead time off');

        $DB->update_record('peerreview', (object) ['id' => $id, 'timeclose' => 0, 'reminderlead' => DAYSECS]);
        $this->assertCount(0, $this->run_task(), 'no close date');

        $DB->update_record('peerreview', (object) ['id' => $id, 'timeclose' => time() - HOURSECS]);
        $this->assertCount(0, $this->run_task(), 'already closed');

        $DB->update_record('peerreview', (object) [
            'id' => $id, 'timeclose' => time() + HOURSECS, 'timeopen' => time() + 30 * MINSECS,
        ]);
        $this->assertCount(0, $this->run_task(), 'not open yet');
        $this->assertEquals(0, $DB->get_field('peerreview', 'remindersentfor', ['id' => $id]));
    }

    /**
     * A hidden activity is skipped and stays armed for when it becomes visible.
     */
    public function test_hidden_activity_is_skipped(): void {
        $this->resetAfterTest();
        global $DB;
        $this->create_activity(2, ['timeclose' => time() + HOURSECS, 'reminderlead' => DAYSECS]);
        $this->allocate(1, 2);
        set_coursemodule_visible($this->cm->id, 0);

        $this->assertCount(0, $this->run_task());
        $this->assertEquals(0, $DB->get_field('peerreview', 'remindersentfor', ['id' => $this->peerreview->id]));

        set_coursemodule_visible($this->cm->id, 1);
        $this->assertCount(1, $this->run_task());
    }

    /**
     * Moving the close date arms the reminder again.
     */
    public function test_new_close_date_reminds_again(): void {
        $this->resetAfterTest();
        global $CFG;
        require_once($CFG->dirroot . '/mod/peerreview/lib.php');
        $this->create_activity(2, ['timeclose' => time() + HOURSECS, 'reminderlead' => DAYSECS]);
        $this->allocate(1, 2);
        $this->assertCount(1, $this->run_task());
        $this->assertCount(0, $this->run_task());

        $data = clone $this->peerreview;
        $data->instance = $data->id;
        unset($data->remindersentfor);
        $data->timeclose = time() + 2 * HOURSECS;
        peerreview_update_instance($data);
        $this->assertCount(1, $this->run_task());
    }
}
