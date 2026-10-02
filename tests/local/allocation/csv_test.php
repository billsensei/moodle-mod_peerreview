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
 * Tests for the CSV import and export of allocations (database level).
 *
 * @package    mod_peerreview
 * @category   test
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(csv::class)]
final class csv_test extends \advanced_testcase {
    /**
     * Create a course with students and one activity.
     *
     * @param int $nstudents Number of students.
     * @return array [manager, csv, students by index starting at 1, teacher]
     */
    private function setup_course(int $nstudents): array {
        global $DB;
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $students = [];
        for ($i = 1; $i <= $nstudents; $i++) {
            $students[$i] = $generator->create_and_enrol($course, 'student', ['lastname' => sprintf('Student%02d', $i)]);
        }
        $teacher = $generator->create_and_enrol($course, 'editingteacher');
        $instance = $generator->create_module('peerreview', ['course' => $course->id]);
        $cm = get_coursemodule_from_id('peerreview', $instance->cmid, 0, false, MUST_EXIST);
        $peerreview = $DB->get_record('peerreview', ['id' => $instance->id], '*', MUST_EXIST);
        $manager = new manager($peerreview, $cm, \context_module::instance($cm->id));
        return [$manager, new csv($peerreview, $manager), $students, $teacher];
    }

    /**
     * CSV rows: usernames and emails work, bad rows are reported with their line, duplicates counted.
     */
    public function test_csv_proposal(): void {
        $this->resetAfterTest();
        [$manager, $csv, $s, $teacher] = $this->setup_course(4);
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
        [$proposal, $errors, $duplicates] = $csv->build_proposal($rows);
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
        [$manager, $csv, $s, $teacher] = $this->setup_course(2);
        $manager->add_manual($s[1]->id, [$s[2]->id], $teacher->id);
        $rows = iterator_to_array($csv->export_rows(), false);
        $this->assertCount(1, $rows);
        $this->assertSame($s[1]->username, $rows[0]->reviewer);
        $this->assertSame($s[2]->username, $rows[0]->reviewee);
        $this->assertSame(get_string('statusnew', 'mod_peerreview'), $rows[0]->status);
    }
}
