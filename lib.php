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
 * Library of interface functions for mod_peerreview.
 *
 * Modelled on mod/workshop/lib.php and mod/assign/lib.php.
 *
 * @package    mod_peerreview
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use mod_peerreview\grades\gradeitems;

require_once($CFG->libdir . '/gradelib.php');

/**
 * Which Moodle features this module supports.
 *
 * @param string $feature FEATURE_xx constant.
 * @return mixed True if the feature is supported, null if unknown.
 */
function peerreview_supports($feature) {
    switch ($feature) {
        case FEATURE_MOD_INTRO:
        case FEATURE_SHOW_DESCRIPTION:
        case FEATURE_GRADE_HAS_GRADE:
        case FEATURE_ADVANCED_GRADING:
        case FEATURE_GROUPS:
        case FEATURE_GROUPINGS:
            return true;
        case FEATURE_MOD_PURPOSE:
            return MOD_PURPOSE_ASSESSMENT;
        default:
            return null;
    }
}

/**
 * Save the module settings from the form into the database.
 *
 * @param stdClass $data Form data.
 * @param mod_peerreview_mod_form|null $mform The form.
 * @return int The new instance id.
 */
function peerreview_add_instance(stdClass $data, ?mod_peerreview_mod_form $mform = null): int {
    global $DB;

    $data->timecreated = time();
    $data->timemodified = $data->timecreated;
    $data->gradeparticipation = (int) ($data->gradeparticipation ?? 0);
    $data->id = $DB->insert_record('peerreview', $data);

    peerreview_grade_item_update($data);

    return $data->id;
}

/**
 * Update an existing instance.
 *
 * @param stdClass $data Form data.
 * @param mod_peerreview_mod_form|null $mform The form.
 * @return bool
 */
function peerreview_update_instance(stdClass $data, ?mod_peerreview_mod_form $mform = null): bool {
    global $DB;

    $data->id = $data->instance;
    $data->timemodified = time();
    $data->gradeparticipation = (int) ($data->gradeparticipation ?? 0);
    $DB->update_record('peerreview', $data);

    peerreview_grade_item_update($data);

    return true;
}

/**
 * Delete an instance and everything attached to it.
 *
 * @param int $id Instance id.
 * @return bool
 */
function peerreview_delete_instance($id): bool {
    global $DB;

    $peerreview = $DB->get_record('peerreview', ['id' => $id]);
    if (!$peerreview) {
        return false;
    }

    $DB->delete_records('peerreview_override', ['peerreviewid' => $id]);
    $DB->delete_records('peerreview_alloc', ['peerreviewid' => $id]);
    $DB->delete_records('peerreview', ['id' => $id]);

    peerreview_grade_item_delete($peerreview);

    return true;
}

/**
 * Create or update the gradebook items (received and, optionally, participation).
 *
 * @param stdClass $peerreview Instance record (with ->cmidnumber if known).
 * @param array|string|null $grades Grades to send, or 'reset'.
 * @return int GRADE_UPDATE_xx.
 */
function peerreview_grade_item_update(stdClass $peerreview, $grades = null): int {
    $received = [
        'itemname' => clean_param($peerreview->name, PARAM_NOTAGS),
        'gradetype' => GRADE_TYPE_VALUE,
        'grademax' => $peerreview->grade,
        'grademin' => 0,
    ];
    if (isset($peerreview->cmidnumber)) {
        $received['idnumber'] = $peerreview->cmidnumber;
    }
    if ($grades === 'reset') {
        $received['reset'] = true;
        $grades = null;
    }
    $result = grade_update(
        'mod/peerreview',
        $peerreview->course,
        'mod',
        'peerreview',
        $peerreview->id,
        gradeitems::ITEM_RECEIVED,
        $grades,
        $received
    );

    if ($peerreview->gradeparticipation > 0) {
        $participation = [
            'itemname' => clean_param($peerreview->name, PARAM_NOTAGS) . ' - ' .
                get_string('gradeitem:participation', 'mod_peerreview'),
            'gradetype' => GRADE_TYPE_VALUE,
            'grademax' => $peerreview->gradeparticipation,
            'grademin' => 0,
        ];
        grade_update(
            'mod/peerreview',
            $peerreview->course,
            'mod',
            'peerreview',
            $peerreview->id,
            gradeitems::ITEM_PARTICIPATION,
            null,
            $participation
        );
    } else {
        grade_update(
            'mod/peerreview',
            $peerreview->course,
            'mod',
            'peerreview',
            $peerreview->id,
            gradeitems::ITEM_PARTICIPATION,
            null,
            ['deleted' => 1]
        );
    }

    return $result;
}

/**
 * Delete the gradebook items.
 *
 * @param stdClass $peerreview Instance record.
 * @return int GRADE_UPDATE_xx.
 */
function peerreview_grade_item_delete(stdClass $peerreview): int {
    $result = grade_update(
        'mod/peerreview',
        $peerreview->course,
        'mod',
        'peerreview',
        $peerreview->id,
        gradeitems::ITEM_RECEIVED,
        null,
        ['deleted' => 1]
    );
    grade_update(
        'mod/peerreview',
        $peerreview->course,
        'mod',
        'peerreview',
        $peerreview->id,
        gradeitems::ITEM_PARTICIPATION,
        null,
        ['deleted' => 1]
    );
    return $result;
}

/**
 * Push grades to the gradebook. Aggregation arrives in phase 6; until then only the items are (re)created.
 *
 * @param stdClass $peerreview Instance record.
 * @param int $userid Specific user only, 0 for all.
 * @param bool $nullifnone Unused until phase 6.
 */
function peerreview_update_grades(stdClass $peerreview, int $userid = 0, bool $nullifnone = true): void {
    peerreview_grade_item_update($peerreview);
}
