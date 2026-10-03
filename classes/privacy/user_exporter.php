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
 * Export of one user's data in one peer review activity.
 *
 * Modelled on mod/assign/classes/privacy/provider.php (advanced grading items) and
 * mod/workshop/classes/privacy/provider.php (reviewer and reviewee roles).
 *
 * @package    mod_peerreview
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_peerreview\privacy;

use core_privacy\local\request\content_writer;
use core_privacy\local\request\helper;
use core_privacy\local\request\transform;
use core_privacy\local\request\writer;
use mod_peerreview\local\allocation\manager;

/**
 * Writes what one user has in one activity: the reviews they gave and received, their own grade override, the overrides
 * they set as a teacher and how many allocations they made.
 *
 * Split out of the privacy provider, whose export_user_data() hands each approved activity to this class. The order of
 * the writer calls is the same as before the split.
 *
 * @package    mod_peerreview
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class user_exporter {
    /**
     * Constructor.
     *
     * @param \stdClass $peerreview Activity record.
     * @param \context $context Module context of the activity.
     * @param \stdClass $user The user whose data is exported.
     */
    public function __construct(
        /** @var \stdClass Activity record. */
        private readonly \stdClass $peerreview,
        /** @var \context Module context of the activity. */
        private readonly \context $context,
        /** @var \stdClass The user whose data is exported. */
        private readonly \stdClass $user
    ) {
    }

    /**
     * Export the user's data in this activity.
     */
    public function export(): void {
        $writer = writer::with_context($this->context);
        $general = helper::get_context_data($this->context, $this->user);
        helper::export_context_files($this->context, $this->user);

        $this->export_reviews_given($writer);
        $this->export_reviews_received($writer);
        $this->export_own_override($writer);
        $this->export_overrides_set($writer);
        $this->add_allocations_made($general);
        $writer->export_data([], $general);
    }

    /**
     * Reviews the user wrote: everything, including drafts, with the rubric or guide filling.
     *
     * @param content_writer $writer The writer.
     */
    private function export_reviews_given(content_writer $writer): void {
        global $DB;

        $given = $DB->get_records(
            'peerreview_alloc',
            ['peerreviewid' => $this->peerreview->id, 'reviewerid' => $this->user->id],
            'id'
        );
        foreach ($given as $alloc) {
            $subcontext = [get_string('privacy:reviewsgiven', 'mod_peerreview'), $alloc->id];
            $data = $this->review_data($alloc, true);
            $data->reviewee = fullname(\core_user::get_user($alloc->revieweeid) ?: (object) ['firstname' => '',
                'lastname' => '']);
            $writer->export_data($subcontext, $data);
            \core_grading\privacy\provider::export_item_data($this->context, (int) $alloc->id, $subcontext);
        }
    }

    /**
     * Reviews the user received: submitted ones only (a draft is still the reviewer's own work). The reviewer is named
     * only when the activity is not anonymous.
     *
     * @param content_writer $writer The writer.
     */
    private function export_reviews_received(content_writer $writer): void {
        global $DB;

        $received = $DB->get_records('peerreview_alloc', ['peerreviewid' => $this->peerreview->id,
            'revieweeid' => $this->user->id], 'id');
        foreach ($received as $alloc) {
            if ($alloc->reviewerid == $this->user->id) {
                continue; // A self-review is already exported with the reviews given.
            }
            $subcontext = [get_string('privacy:reviewsreceived', 'mod_peerreview'), $alloc->id];
            $submitted = (int) $alloc->status === manager::STATUS_SUBMITTED;
            $data = $this->review_data($alloc, $submitted);
            if (!$this->peerreview->anonymous) {
                $reviewer = \core_user::get_user($alloc->reviewerid);
                $data->reviewer = $reviewer ? fullname($reviewer) : '';
            }
            $writer->export_data($subcontext, $data);
            if ($submitted) {
                \core_grading\privacy\provider::export_item_data($this->context, (int) $alloc->id, $subcontext);
            }
        }
    }

    /**
     * The user's own grade override.
     *
     * @param content_writer $writer The writer.
     */
    private function export_own_override(content_writer $writer): void {
        global $DB;

        $override = $DB->get_record('peerreview_override', [
            'peerreviewid' => $this->peerreview->id,
            'userid' => $this->user->id,
        ]);
        if ($override) {
            $writer->export_data([get_string('privacy:override', 'mod_peerreview')], $this->override_data($override));
        }
    }

    /**
     * Overrides the user set as a teacher, without naming the students.
     *
     * @param content_writer $writer The writer.
     */
    private function export_overrides_set(content_writer $writer): void {
        global $DB;

        $overrides = $DB->get_records('peerreview_override', ['peerreviewid' => $this->peerreview->id,
            'overriddenby' => $this->user->id], 'id');
        if ($overrides) {
            $writer->export_data([get_string('privacy:overridesgiven', 'mod_peerreview')], (object) [
                'overrides' => array_values(array_map(fn($o) => $this->override_data($o), $overrides)),
            ]);
        }
    }

    /**
     * Allocations the user made as a teacher, as a count only, added to the activity's general data.
     *
     * @param \stdClass $general The general context data, extended in place.
     */
    private function add_allocations_made(\stdClass $general): void {
        global $DB;

        $allocated = $DB->count_records('peerreview_alloc', ['peerreviewid' => $this->peerreview->id,
            'allocatedby' => $this->user->id]);
        if ($allocated) {
            $general->allocationsmade = $allocated;
        }
    }

    /**
     * The exportable fields of one grade override.
     *
     * @param \stdClass $override Override row.
     * @return \stdClass
     */
    private function override_data(\stdClass $override): \stdClass {
        return (object) [
            'grade' => format_float($override->grade, 2),
            'note' => $override->note,
            'timemodified' => transform::datetime($override->timemodified),
        ];
    }

    /**
     * The exportable fields of one review.
     *
     * @param \stdClass $alloc Allocation row.
     * @param bool $withcontent Include grade and comment.
     * @return \stdClass
     */
    private function review_data(\stdClass $alloc, bool $withcontent): \stdClass {
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
                ['context' => $this->context]
            );
        }
        return $data;
    }
}
