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
 * Development helper: prints one line per peer review activity on the dev site with its data counts (allocations,
 * submitted reviews, overrides, advanced grading instances, active method, feedback released), to compare an
 * activity with its restored copy.
 *
 * Usage:   source scripts/env.sh && php scripts/dev-show-state.php
 * Arguments: none.  Exit codes: 0 success.
 * Example output:
 *   pr 1 course 5 cm 5 'Presentation peer review' allocs 12 submitted 3 overrides 0 gradinginst 3 method guide released 0
 *
 * @package    mod_peerreview
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);
require((getenv('MOODLE_DIR') ?: dirname(__DIR__, 3)) . '/config.php');

foreach ($DB->get_records('peerreview', null, 'id') as $p) {
    $cm = get_coursemodule_from_instance('peerreview', $p->id, $p->course, false, MUST_EXIST);
    $context = context_module::instance($cm->id);
    $instances = $DB->count_records_sql("SELECT COUNT(1)
                                           FROM {grading_instances} gi
                                           JOIN {grading_definitions} gd ON gd.id = gi.definitionid
                                           JOIN {grading_areas} ga ON ga.id = gd.areaid
                                          WHERE ga.contextid = ?", [$context->id]);
    $method = $DB->get_field('grading_areas', 'activemethod', ['contextid' => $context->id]);
    printf(
        "pr %d course %d cm %d '%s' allocs %d submitted %d overrides %d gradinginst %d method %s released %d\n",
        $p->id,
        $p->course,
        $cm->id,
        $p->name,
        $DB->count_records('peerreview_alloc', ['peerreviewid' => $p->id]),
        $DB->count_records('peerreview_alloc', ['peerreviewid' => $p->id, 'status' => 2]),
        $DB->count_records('peerreview_override', ['peerreviewid' => $p->id]),
        $instances,
        $method ?: '-',
        $p->feedbackreleased
    );
}
