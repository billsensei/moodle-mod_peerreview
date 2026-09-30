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
        case FEATURE_BACKUP_MOODLE2:
        case FEATURE_GROUPS:
        case FEATURE_GROUPINGS:
        case FEATURE_COMPLETION_HAS_RULES:
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
 * Push grades to the gradebook: the received grade (mean/median of submitted reviews, or the teacher override) and,
 * when enabled, the participation grade. Students with no counted review get no grade rather than zero.
 *
 * Modelled on assign_update_grades() in mod/assign/lib.php.
 *
 * @param stdClass $peerreview Instance record.
 * @param int $userid Specific user only, 0 for all.
 * @param bool $nullifnone Not used: everyone asked about is always sent, with null when there is no grade.
 */
function peerreview_update_grades(stdClass $peerreview, int $userid = 0, bool $nullifnone = true): void {
    peerreview_grade_item_update($peerreview);
    (new \mod_peerreview\local\grade\gradebook($peerreview))->push($userid);
}

/**
 * Cached information for the course page, including the custom completion rule.
 *
 * Modelled on assign_get_coursemodule_info() in mod/assign/lib.php.
 *
 * @param stdClass $coursemodule Course module record.
 * @return cached_cm_info|false
 */
function peerreview_get_coursemodule_info($coursemodule) {
    global $DB;

    $fields = 'id, name, intro, introformat, completionallreviews';
    if (!$peerreview = $DB->get_record('peerreview', ['id' => $coursemodule->instance], $fields)) {
        return false;
    }
    $result = new cached_cm_info();
    $result->name = $peerreview->name;
    if ($coursemodule->showdescription) {
        $result->content = format_module_intro('peerreview', $peerreview, $coursemodule->id, false);
    }
    if ($coursemodule->completion == COMPLETION_TRACKING_AUTOMATIC) {
        $result->customdata['customcompletionrules']['completionallreviews'] = $peerreview->completionallreviews;
    }
    return $result;
}

/**
 * Add the reset options to the course reset form.
 *
 * Modelled on workshop_reset_course_form_definition() in mod/workshop/lib.php.
 *
 * @param MoodleQuickForm $mform The reset form.
 */
function peerreview_reset_course_form_definition($mform): void {
    $mform->addElement('header', 'peerreviewheader', get_string('modulenameplural', 'mod_peerreview'));
    $mform->addElement('advcheckbox', 'reset_peerreview_reviews', get_string('resetreviews', 'mod_peerreview'));
    $mform->addElement('advcheckbox', 'reset_peerreview_gradebook', get_string('resetgradebook', 'mod_peerreview'));
    $mform->addHelpButton('reset_peerreview_gradebook', 'resetgradebook', 'mod_peerreview');
}

/**
 * Defaults for the reset form.
 *
 * @param stdClass $course The course.
 * @return array
 */
function peerreview_reset_course_form_defaults($course): array {
    return ['reset_peerreview_reviews' => 1, 'reset_peerreview_gradebook' => 1];
}

/**
 * Reset the gradebook items of all peer reviews in a course.
 *
 * @param int $courseid Course id.
 * @param string $type Optional item type filter (unused).
 */
function peerreview_reset_gradebook($courseid, $type = ''): void {
    global $DB;

    foreach ($DB->get_records('peerreview', ['course' => $courseid]) as $peerreview) {
        peerreview_grade_item_update($peerreview, 'reset');
    }
}

/**
 * Course reset: delete allocations, reviews (with their rubric/guide data), overrides and hide the feedback again.
 *
 * @param stdClass $data Reset form data.
 * @return array Status rows for the reset report.
 */
function peerreview_reset_userdata($data): array {
    global $DB;

    $componentstr = get_string('modulenameplural', 'mod_peerreview');
    $status = [];

    if (!empty($data->reset_peerreview_reviews)) {
        foreach ($DB->get_records('peerreview', ['course' => $data->courseid]) as $peerreview) {
            $cm = get_coursemodule_from_instance('peerreview', $peerreview->id, $data->courseid);
            if (!$cm) {
                continue;
            }
            $context = context_module::instance($cm->id);
            \core_grading\privacy\provider::delete_data_for_instances($context);
            $DB->delete_records('peerreview_alloc', ['peerreviewid' => $peerreview->id]);
            $DB->delete_records('peerreview_override', ['peerreviewid' => $peerreview->id]);
            $DB->set_field('peerreview', 'feedbackreleased', 0, ['id' => $peerreview->id]);
        }
        $status[] = [
            'component' => $componentstr,
            'item' => get_string('resetreviews', 'mod_peerreview'),
            'error' => false,
        ];
    }

    if (!empty($data->reset_peerreview_gradebook)) {
        peerreview_reset_gradebook($data->courseid);
        $status[] = [
            'component' => $componentstr,
            'item' => get_string('resetgradebook', 'mod_peerreview'),
            'error' => false,
        ];
    }
    return $status;
}

/**
 * Add the teacher links to the activity settings navigation.
 *
 * Modelled on mod/workshop/lib.php workshop_extend_settings_navigation().
 *
 * @param settings_navigation $settingsnav Settings navigation.
 * @param navigation_node $node The activity's settings node.
 */
function peerreview_extend_settings_navigation(settings_navigation $settingsnav, navigation_node $node): void {
    global $PAGE;

    if (has_capability('mod/peerreview:allocate', $PAGE->cm->context)) {
        $node->add(
            get_string('allocate', 'mod_peerreview'),
            new moodle_url('/mod/peerreview/allocate.php', ['id' => $PAGE->cm->id]),
            navigation_node::TYPE_SETTING
        );
    }
}

/**
 * User preferences this module may store (whether the teacher report refreshes itself).
 *
 * Modelled on mod_workshop_user_preferences() in mod/workshop/lib.php.
 *
 * @return array[]
 */
function mod_peerreview_user_preferences(): array {
    return [
        'mod_peerreview_autorefresh' => [
            'type' => PARAM_BOOL,
            'null' => NULL_NOT_ALLOWED,
            'default' => true,
            'permissioncallback' => [core_user::class, 'is_current_user'],
        ],
    ];
}
