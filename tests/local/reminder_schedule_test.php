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

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the automatic reminders of an activity.
 *
 * @package    mod_peerreview
 * @category   test
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(reminder_schedule::class)]
final class reminder_schedule_test extends \advanced_testcase {
    use \mod_peerreview\tests\activity_trait;

    /**
     * Whole seconds, nothing empty or repeated, longest first.
     */
    public function test_normalise(): void {
        $this->assertSame([DAYSECS, 7200, 3600], reminder_schedule::normalise([3600.4, 0, -5, '7200', 7200, DAYSECS]));
        $this->assertSame([], reminder_schedule::normalise([0, 0.2, -1]));
        $this->assertSame([7201], reminder_schedule::normalise([7200.6]));
    }

    /**
     * Too short, too long, repeated and too many are refused; empty rows are ignored; positions are kept.
     */
    public function test_check(): void {
        $this->assertSame([], reminder_schedule::check([]));
        $this->assertSame([], reminder_schedule::check([0, HOURSECS, 0, DAYSECS]));
        $this->assertSame(
            [1 => 'reminderleadtoosmall', 2 => 'reminderleadduplicate', 3 => 'reminderleadtoolarge'],
            reminder_schedule::check([HOURSECS, 1800, HOURSECS, reminder_schedule::MAX_LEAD + 1])
        );
        $this->assertSame([], reminder_schedule::check([reminder_schedule::MIN_LEAD, reminder_schedule::MAX_LEAD]));
        $this->assertSame(['reminderleadduplicate'], array_values(reminder_schedule::check([3600.2, 3599.8])));

        $many = [];
        for ($i = 1; $i <= reminder_schedule::MAX + 2; $i++) {
            $many[] = $i * HOURSECS;
        }
        $errors = reminder_schedule::check($many);
        $this->assertSame([5 => 'remindertoomany', 6 => 'remindertoomany'], $errors);
    }

    /**
     * Saving keeps the sent state of lead times that stay, adds new ones unsent and removes the others.
     */
    public function test_save_get_rearm_and_delete(): void {
        $this->resetAfterTest();
        global $DB;
        $this->create_activity(1, ['timeclose' => time() + 9 * DAYSECS]);
        $schedule = new reminder_schedule($this->peerreview->id);
        $this->assertSame([], $schedule->get_leads());

        $schedule->save([DAYSECS, 7 * DAYSECS]);
        $this->assertSame([7 * DAYSECS, DAYSECS], $schedule->get_leads(), 'longest first');
        $DB->set_field('peerreview_reminder', 'sentfor', 1234, ['peerreviewid' => $this->peerreview->id]);

        $schedule->save([7 * DAYSECS, 3 * DAYSECS, 7 * DAYSECS]);
        $this->assertSame([7 * DAYSECS, 3 * DAYSECS], $schedule->get_leads());
        $sent = $DB->get_records_menu('peerreview_reminder', ['peerreviewid' => $this->peerreview->id], '', 'leadtime, sentfor');
        $this->assertEquals([7 * DAYSECS => 1234, 3 * DAYSECS => 0], $sent);

        $schedule->rearm();
        $this->assertEquals(0, $DB->count_records_select('peerreview_reminder', 'sentfor <> 0'));

        $other = new reminder_schedule($this->peerreview->id + 1000);
        $other->save([HOURSECS]);
        $schedule->delete();
        $this->assertSame([], $schedule->get_leads());
        $this->assertSame([HOURSECS], $other->get_leads(), 'another activity is not touched');
    }

    /**
     * More than the maximum is a programming error.
     */
    public function test_too_many_is_refused(): void {
        $this->resetAfterTest();
        $this->create_activity(1);
        $leads = [];
        for ($i = 1; $i <= reminder_schedule::MAX + 1; $i++) {
            $leads[] = $i * HOURSECS;
        }
        $this->expectException(\coding_exception::class);
        (new reminder_schedule($this->peerreview->id))->save($leads);
    }
}
