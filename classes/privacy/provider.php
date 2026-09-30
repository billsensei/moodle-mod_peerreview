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
 * Privacy provider for mod_peerreview.
 *
 * Modelled on mod/assign/classes/privacy/provider.php (advanced grading items) and
 * mod/workshop/classes/privacy/provider.php (reviewer and reviewee roles).
 *
 * @package    mod_peerreview
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_peerreview\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\helper;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use mod_peerreview\local\allocation\manager;

/**
 * Privacy provider.
 *
 * A user appears in an activity as a reviewer, a reviewee, a student with a grade override, or a teacher who
 * allocated reviews or set an override. Reviews are stored per allocation; the rubric or marking guide filling of a
 * review is a core_grading instance whose itemid is the allocation id.
 *
 * Deleting a reviewer's or reviewee's data removes the whole review (row and grading instances). A teacher's
 * authorship (allocatedby, overriddenby) is anonymised instead, because the rows belong to students.
 *
 * @package    mod_peerreview
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\user_preference_provider {
    /**
     * Describe the stored personal data.
     *
     * @param collection $collection The collection to add to.
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('peerreview_alloc', [
            'reviewerid' => 'privacy:metadata:peerreview_alloc:reviewerid',
            'revieweeid' => 'privacy:metadata:peerreview_alloc:revieweeid',
            'status' => 'privacy:metadata:peerreview_alloc:status',
            'grade' => 'privacy:metadata:peerreview_alloc:grade',
            'feedback' => 'privacy:metadata:peerreview_alloc:feedback',
            'allocatedby' => 'privacy:metadata:peerreview_alloc:allocatedby',
            'timecreated' => 'privacy:metadata:peerreview_alloc:timecreated',
            'timemodified' => 'privacy:metadata:peerreview_alloc:timemodified',
            'timesubmitted' => 'privacy:metadata:peerreview_alloc:timesubmitted',
        ], 'privacy:metadata:peerreview_alloc');

        $collection->add_database_table('peerreview_override', [
            'userid' => 'privacy:metadata:peerreview_override:userid',
            'grade' => 'privacy:metadata:peerreview_override:grade',
            'note' => 'privacy:metadata:peerreview_override:note',
            'overriddenby' => 'privacy:metadata:peerreview_override:overriddenby',
            'timemodified' => 'privacy:metadata:peerreview_override:timemodified',
        ], 'privacy:metadata:peerreview_override');

        $collection->add_subsystem_link('core_grading', [], 'privacy:metadata:core_grading');

        $collection->add_user_preference('mod_peerreview_autorefresh', 'privacy:metadata:preference:autorefresh');

        return $collection;
    }

    /**
     * Contexts that hold data of the user.
     *
     * @param int $userid The user.
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $base = "SELECT ctx.id
                   FROM {context} ctx
                   JOIN {course_modules} cm ON cm.id = ctx.instanceid AND ctx.contextlevel = :modlevel
                   JOIN {modules} m ON m.id = cm.module AND m.name = 'peerreview'
                   JOIN {peerreview} p ON p.id = cm.instance";
        $params = ['modlevel' => CONTEXT_MODULE, 'u1' => $userid, 'u2' => $userid, 'u3' => $userid];

        $contextlist->add_from_sql("$base
                   JOIN {peerreview_alloc} a ON a.peerreviewid = p.id
                  WHERE a.reviewerid = :u1 OR a.revieweeid = :u2 OR a.allocatedby = :u3", $params);
        $contextlist->add_from_sql("$base
                   JOIN {peerreview_override} o ON o.peerreviewid = p.id
                  WHERE o.userid = :u1 OR o.overriddenby = :u2", $params);

        return $contextlist;
    }

    /**
     * Users who have data in the context.
     *
     * @param userlist $userlist The userlist to fill.
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if (!$context instanceof \context_module) {
            return;
        }
        $params = ['cmid' => $context->instanceid];
        $base = "FROM {course_modules} cm
                 JOIN {modules} m ON m.id = cm.module AND m.name = 'peerreview'
                 JOIN {peerreview} p ON p.id = cm.instance";
        foreach (['reviewerid', 'revieweeid', 'allocatedby'] as $field) {
            $userlist->add_from_sql($field, "SELECT a.$field $base
                   JOIN {peerreview_alloc} a ON a.peerreviewid = p.id
                  WHERE cm.id = :cmid", $params);
        }
        foreach (['userid', 'overriddenby'] as $field) {
            $userlist->add_from_sql($field, "SELECT o.$field $base
                   JOIN {peerreview_override} o ON o.peerreviewid = p.id
                  WHERE cm.id = :cmid", $params);
        }
    }

    /**
     * Export the user's data in the approved contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts.
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        $user = $contextlist->get_user();
        foreach ($contextlist->get_contexts() as $context) {
            $peerreview = self::get_instance($context);
            if (!$peerreview) {
                continue;
            }
            $writer = writer::with_context($context);
            $general = helper::get_context_data($context, $user);
            helper::export_context_files($context, $user);

            // Reviews the user wrote: everything, including drafts, with the rubric or guide filling.
            $given = $DB->get_records(
                'peerreview_alloc',
                ['peerreviewid' => $peerreview->id, 'reviewerid' => $user->id],
                'id'
            );
            foreach ($given as $alloc) {
                $subcontext = [get_string('privacy:reviewsgiven', 'mod_peerreview'), $alloc->id];
                $data = self::review_data($alloc, $context, true);
                $data->reviewee = fullname(\core_user::get_user($alloc->revieweeid) ?: (object) ['firstname' => '',
                    'lastname' => '']);
                $writer->export_data($subcontext, $data);
                \core_grading\privacy\provider::export_item_data($context, (int) $alloc->id, $subcontext);
            }

            // Reviews the user received: submitted ones only (a draft is still the reviewer's own work). The
            // reviewer is named only when the activity is not anonymous.
            $received = $DB->get_records('peerreview_alloc', ['peerreviewid' => $peerreview->id,
                'revieweeid' => $user->id], 'id');
            foreach ($received as $alloc) {
                if ($alloc->reviewerid == $user->id) {
                    continue; // A self-review is already exported above.
                }
                $subcontext = [get_string('privacy:reviewsreceived', 'mod_peerreview'), $alloc->id];
                $submitted = (int) $alloc->status === manager::STATUS_SUBMITTED;
                $data = self::review_data($alloc, $context, $submitted);
                if (!$peerreview->anonymous) {
                    $reviewer = \core_user::get_user($alloc->reviewerid);
                    $data->reviewer = $reviewer ? fullname($reviewer) : '';
                }
                $writer->export_data($subcontext, $data);
                if ($submitted) {
                    \core_grading\privacy\provider::export_item_data($context, (int) $alloc->id, $subcontext);
                }
            }

            // The user's own grade override.
            $override = $DB->get_record('peerreview_override', ['peerreviewid' => $peerreview->id, 'userid' => $user->id]);
            if ($override) {
                $writer->export_data([get_string('privacy:override', 'mod_peerreview')], (object) [
                    'grade' => format_float($override->grade, 2),
                    'note' => $override->note,
                    'timemodified' => transform::datetime($override->timemodified),
                ]);
            }

            // Overrides the user set as a teacher, without naming the students.
            $overrides = $DB->get_records('peerreview_override', ['peerreviewid' => $peerreview->id,
                'overriddenby' => $user->id], 'id');
            if ($overrides) {
                $writer->export_data([get_string('privacy:overridesgiven', 'mod_peerreview')], (object) [
                    'overrides' => array_values(array_map(fn($o) => (object) [
                        'grade' => format_float($o->grade, 2),
                        'note' => $o->note,
                        'timemodified' => transform::datetime($o->timemodified),
                    ], $overrides)),
                ]);
            }

            // Allocations the user made as a teacher, as a count only.
            $allocated = $DB->count_records('peerreview_alloc', ['peerreviewid' => $peerreview->id,
                'allocatedby' => $user->id]);
            if ($allocated) {
                $general->allocationsmade = $allocated;
            }
            $writer->export_data([], $general);
        }
    }

    /**
     * Export the user's preferences.
     *
     * @param int $userid The user.
     */
    public static function export_user_preferences(int $userid): void {
        $value = get_user_preferences('mod_peerreview_autorefresh', null, $userid);
        if ($value !== null) {
            writer::export_user_preference(
                'mod_peerreview',
                'mod_peerreview_autorefresh',
                transform::yesno($value),
                get_string('privacy:metadata:preference:autorefresh', 'mod_peerreview')
            );
        }
    }

    /**
     * Delete all data in the context.
     *
     * @param \context $context The context.
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;

        $peerreview = self::get_instance($context);
        if (!$peerreview) {
            return;
        }
        \core_grading\privacy\provider::delete_instance_data($context);
        $DB->delete_records('peerreview_alloc', ['peerreviewid' => $peerreview->id]);
        $DB->delete_records('peerreview_override', ['peerreviewid' => $peerreview->id]);
    }

    /**
     * Delete the user's data in the approved contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        $userid = (int) $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            self::delete_users_in_context($context, [$userid]);
        }
    }

    /**
     * Delete the data of several users in one context.
     *
     * @param approved_userlist $userlist The approved users.
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        self::delete_users_in_context($userlist->get_context(), array_map('intval', $userlist->get_userids()));
    }

    /**
     * Remove the reviews given and received by the users, their overrides, and anonymise what they did as teachers.
     *
     * @param \context $context The context.
     * @param int[] $userids The users.
     */
    private static function delete_users_in_context(\context $context, array $userids): void {
        global $DB;

        $peerreview = self::get_instance($context);
        if (!$peerreview || !$userids) {
            return;
        }
        [$insql1, $params1] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'u1');
        [$insql2, $params2] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'u2');
        $params = ['peerreviewid' => $peerreview->id] + $params1 + $params2;
        $select = "peerreviewid = :peerreviewid AND (reviewerid $insql1 OR revieweeid $insql2)";

        $allocids = $DB->get_fieldset_select('peerreview_alloc', 'id', $select, $params);
        if ($allocids) {
            \core_grading\privacy\provider::delete_data_for_instances($context, array_map('intval', $allocids));
            $DB->delete_records_select('peerreview_alloc', $select, $params);
        }

        [$insql, $inparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $params = ['peerreviewid' => $peerreview->id] + $inparams;
        $DB->delete_records_select('peerreview_override', "peerreviewid = :peerreviewid AND userid $insql", $params);
        $DB->set_field_select(
            'peerreview_override',
            'overriddenby',
            0,
            "peerreviewid = :peerreviewid AND overriddenby $insql",
            $params
        );
        $DB->set_field_select(
            'peerreview_alloc',
            'allocatedby',
            0,
            "peerreviewid = :peerreviewid AND allocatedby $insql",
            $params
        );
    }

    /**
     * The activity record behind a module context, or null when the context is not a peer review.
     *
     * @param \context $context The context.
     * @return \stdClass|null
     */
    private static function get_instance(\context $context): ?\stdClass {
        global $DB;

        if (!$context instanceof \context_module) {
            return null;
        }
        $cm = get_coursemodule_from_id('peerreview', $context->instanceid);
        if (!$cm) {
            return null;
        }
        return $DB->get_record('peerreview', ['id' => $cm->instance]) ?: null;
    }

    /**
     * The exportable fields of one review.
     *
     * @param \stdClass $alloc Allocation row.
     * @param \context $context Module context.
     * @param bool $withcontent Include grade and comment.
     * @return \stdClass
     */
    private static function review_data(\stdClass $alloc, \context $context, bool $withcontent): \stdClass {
        $statuses = [
            manager::STATUS_NEW => 'statusnew',
            manager::STATUS_DRAFT => 'statusdraft',
            manager::STATUS_SUBMITTED => 'statussubmitted',
        ];
        $data = (object) [
            'status' => get_string($statuses[(int) $alloc->status] ?? 'statusnew', 'mod_peerreview'),
            'timecreated' => transform::datetime($alloc->timecreated),
            'timemodified' => transform::datetime($alloc->timemodified),
            'timesubmitted' => $alloc->timesubmitted ? transform::datetime($alloc->timesubmitted) : null,
        ];
        if ($withcontent) {
            $data->grade = $alloc->grade === null ? null : format_float($alloc->grade, 2);
            $data->feedback = format_text(
                (string) $alloc->feedback,
                (int) $alloc->feedbackformat,
                ['context' => $context]
            );
        }
        return $data;
    }
}
