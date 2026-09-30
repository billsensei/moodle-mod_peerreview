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
 * Shared fixture for mod_peerreview tests: a course, students, a teacher and one activity.
 *
 * @package    mod_peerreview
 * @category   test
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_peerreview\tests;

use mod_peerreview\local\allocation\manager;

/**
 * Fixture helper. Use inside an advanced_testcase.
 *
 * @package    mod_peerreview
 * @category   test
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait activity_trait {
    /** @var \stdClass Course. */
    protected \stdClass $course;

    /** @var \stdClass Activity record. */
    protected \stdClass $peerreview;

    /** @var \stdClass Course module record. */
    protected \stdClass $cm;

    /** @var \context_module Module context. */
    protected \context_module $context;

    /** @var \stdClass[] Students indexed from 1. */
    protected array $students = [];

    /** @var \stdClass Editing teacher. */
    protected \stdClass $teacher;

    /** @var manager Allocation manager. */
    protected manager $manager;

    /**
     * Create the fixture.
     *
     * @param int $nstudents Number of students.
     * @param array $activity Extra activity fields.
     * @param int $groupmode Group mode of the activity.
     */
    protected function create_activity(int $nstudents = 4, array $activity = [], int $groupmode = NOGROUPS): void {
        global $DB;
        $generator = $this->getDataGenerator();
        $this->course = $generator->create_course(['enablecompletion' => 1]);
        for ($i = 1; $i <= $nstudents; $i++) {
            $this->students[$i] = $generator->create_and_enrol($this->course, 'student', [
                'firstname' => 'First' . $i,
                'lastname' => sprintf('Last%02d', $i),
            ]);
        }
        $this->teacher = $generator->create_and_enrol($this->course, 'editingteacher');
        $instance = $generator->create_module('peerreview', ['course' => $this->course->id, 'groupmode' => $groupmode] + $activity);
        $this->cm = get_coursemodule_from_id('peerreview', $instance->cmid, 0, false, MUST_EXIST);
        $this->context = \context_module::instance($this->cm->id);
        $this->peerreview = $DB->get_record('peerreview', ['id' => $instance->id], '*', MUST_EXIST);
        $this->manager = new manager($this->peerreview, $this->cm, $this->context);
    }

    /**
     * Allocate reviewer to reviewee and return the allocation.
     *
     * @param int $reviewer Student index.
     * @param int $reviewee Student index.
     * @param int|null $status Status to set, null to leave not started.
     * @param float|null $grade Grade to set.
     * @param string $feedback Overall comment.
     * @return \stdClass Allocation record.
     */
    protected function allocate(
        int $reviewer,
        int $reviewee,
        ?int $status = null,
        ?float $grade = null,
        string $feedback = ''
    ): \stdClass {
        global $DB;
        $this->manager->add_manual($this->students[$reviewer]->id, [$this->students[$reviewee]->id], $this->teacher->id);
        $alloc = $DB->get_record('peerreview_alloc', [
            'peerreviewid' => $this->peerreview->id,
            'reviewerid' => $this->students[$reviewer]->id,
            'revieweeid' => $this->students[$reviewee]->id,
        ], '*', MUST_EXIST);
        if ($status !== null) {
            $alloc->status = $status;
            $alloc->grade = $grade;
            $alloc->feedback = $feedback;
            $alloc->timesubmitted = $status === manager::STATUS_SUBMITTED ? time() : null;
            $DB->update_record('peerreview_alloc', $alloc);
        }
        return $alloc;
    }

    /**
     * A teacher who can view all reviews but, unlike teacher roles, cannot access all groups.
     *
     * @return \stdClass User enrolled in the course.
     */
    protected function create_group_limited_teacher(): \stdClass {
        $generator = $this->getDataGenerator();
        $roleid = $generator->create_role();
        assign_capability('mod/peerreview:viewallreviews', CAP_ALLOW, $roleid, \context_system::instance()->id);
        $user = $generator->create_and_enrol($this->course, 'student');
        role_assign($roleid, $user->id, \context_course::instance($this->course->id)->id);
        return $user;
    }
}
