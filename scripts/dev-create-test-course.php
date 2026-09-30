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
 * Development helper: creates course PR101 with 1 teacher, N students and one peer review activity.
 *
 * Usage:   source scripts/env.sh && php scripts/dev-create-test-course.php [students=6]
 * Needs:   MOODLE_DIR (set by env.sh) because __DIR__ resolves through the mod/peerreview symlink.
 * Exit codes: 0 success, 1 course PR101 already exists.
 * Dev sites only: passwords are weak by design.
 *
 * @package    mod_peerreview
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);
require((getenv('MOODLE_DIR') ?: '/home/bill/test/moodle') . '/config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/course/modlib.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->libdir . '/enrollib.php');

$nstudents = (int) ($argv[1] ?? 6);
if ($DB->record_exists('course', ['shortname' => 'PR101'])) {
    fwrite(STDERR, "Course PR101 already exists\n");
    exit(1);
}

$course = create_course((object) ['fullname' => 'Peer review test', 'shortname' => 'PR101', 'category' => 1,
    'format' => 'topics', 'numsections' => 1, 'enablecompletion' => 1]);
$manual = enrol_get_plugin('manual');
$instance = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'manual'], '*', MUST_EXIST);

$make = function (string $username, string $first, string $last, string $role) use ($DB, $manual, $instance) {
    $user = (object) ['username' => $username, 'password' => hash_internal_user_password('Test123!'),
        'firstname' => $first, 'lastname' => $last, 'email' => $username . '@example.com', 'confirmed' => 1,
        'mnethostid' => 1, 'auth' => 'manual'];
    $user->id = user_create_user($user, false, false);
    $manual->enrol_user($instance, $user->id, $DB->get_field('role', 'id', ['shortname' => $role]));
    return $user;
};
$make('teacher1', 'Tina', 'Teacher', 'editingteacher');
for ($i = 1; $i <= $nstudents; $i++) {
    $make('student' . $i, 'Student', 'Number' . $i, 'student');
}

$moduleid = $DB->get_field('modules', 'id', ['name' => 'peerreview'], MUST_EXIST);
$module = (object) ['modulename' => 'peerreview', 'module' => $moduleid, 'course' => $course->id, 'section' => 1, 'visible' => 1,
    'name' => 'Presentation peer review', 'cmidnumber' => '', 'intro' => 'Assess your classmates.', 'introformat' => FORMAT_HTML,
    'grade' => 100, 'gradeparticipation' => 0, 'aggregation' => 0, 'anonymous' => 1,
    'allowselfreview' => 0, 'feedbackreleased' => 0, 'timeopen' => 0, 'timeclose' => 0];
$cm = add_moduleinfo($module, $course);
echo "Created course {$course->id}, cmid {$cm->coursemodule}, {$nstudents} students (password Test123!)\n";
