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
 * Review service: saving drafts and submitting reviews through the advanced grading API.
 *
 * Modelled on the grading instance handling in mod/assign/locallib.php (get_grading_instance, save_grade).
 *
 * @package    mod_peerreview
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_peerreview\local\review;

use mod_peerreview\local\allocation\manager;
use mod_peerreview\local\grade\grade_range;

/**
 * Review service for one peer review activity.
 *
 * @package    mod_peerreview
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class service {
    /** @var grading_access|null Grading controller and instance lookups, created on first use. */
    private ?grading_access $grading = null;

    /**
     * Constructor.
     *
     * @param \stdClass $peerreview Activity record.
     * @param \context_module $context Module context.
     */
    public function __construct(
        /** @var \stdClass Activity record. */
        private readonly \stdClass $peerreview,
        /** @var \context_module Module context. */
        private readonly \context_module $context
    ) {
    }

    /**
     * Fetch an allocation of this activity.
     *
     * @param int $id Allocation id.
     * @return \stdClass
     */
    public function get_allocation(int $id): \stdClass {
        global $DB;
        return $DB->get_record('peerreview_alloc', ['id' => $id, 'peerreviewid' => $this->peerreview->id], '*', MUST_EXIST);
    }

    /**
     * Whether the activity accepts reviews now (inside its optional open/close window).
     *
     * @param int|null $now Time to test, defaults to now.
     * @return bool
     */
    public function is_open(?int $now = null): bool {
        $now ??= time();
        return ($this->peerreview->timeopen == 0 || $now >= $this->peerreview->timeopen)
            && ($this->peerreview->timeclose == 0 || $now < $this->peerreview->timeclose);
    }

    /**
     * Throw unless the user may write this review right now.
     *
     * @param \stdClass $alloc Allocation record.
     * @param int $userid The user.
     * @throws \moodle_exception
     */
    public function require_can_edit(\stdClass $alloc, int $userid): void {
        if ((int) $alloc->reviewerid !== $userid || !has_capability('mod/peerreview:review', $this->context, $userid)) {
            throw new \moodle_exception('errornotyourreview', 'mod_peerreview');
        }
        if ($this->peerreview->timeopen && time() < $this->peerreview->timeopen) {
            throw new \moodle_exception('errornotopenyet', 'mod_peerreview');
        }
        if (!$this->is_open()) {
            throw new \moodle_exception('errorclosed', 'mod_peerreview');
        }
    }

    /**
     * The grading helper of this activity (created on first use).
     *
     * @return grading_access
     */
    private function grading(): grading_access {
        $this->grading ??= new grading_access($this->peerreview, $this->context);
        return $this->grading;
    }

    /**
     * The active advanced grading controller, or null when the simple points-and-comment form is used.
     *
     * @return \gradingform_controller|null
     */
    public function get_controller(): ?\gradingform_controller {
        return $this->grading()->get_controller();
    }

    /**
     * Message explaining why the active grading method cannot be used (form still a draft), if that is the case.
     *
     * @return string Empty when there is no problem.
     */
    public function get_unavailable_message(): string {
        return $this->grading()->get_unavailable_message();
    }

    /**
     * Whether drafts can be stored for the active grading method (rubric and marking guide only).
     *
     * @return bool
     */
    public function supports_drafts(): bool {
        return $this->grading()->supports_drafts();
    }

    /**
     * The grading instance a reviewer works on: their unfinished draft, or a copy of the current one for editing.
     *
     * @param \stdClass $alloc Allocation record.
     * @param int $raterid Reviewer id.
     * @param mixed $instanceid Instance id posted by the form (optional).
     * @return \gradingform_instance|null Null when no advanced method is usable.
     */
    public function get_instance(\stdClass $alloc, int $raterid, $instanceid = null): ?\gradingform_instance {
        return $this->grading()->get_instance($alloc, $raterid, $instanceid);
    }

    /**
     * The submitted (active) instance of a review, for read-only display.
     *
     * When the review was written with another grading method than the active one (the teacher switched afterwards),
     * its stored instance of that method is returned, so it is shown as it was filled in.
     *
     * @param \stdClass $alloc Allocation record.
     * @return \gradingform_instance|null
     */
    public function get_submitted_instance(\stdClass $alloc): ?\gradingform_instance {
        return $this->grading()->get_submitted_instance($alloc);
    }

    /**
     * The grading method (for example 'rubric') a stored instance was written with, when it is not the active one.
     *
     * @param \gradingform_instance $instance The instance.
     * @return string|null Null when the instance belongs to the active method.
     */
    public function get_other_method(\gradingform_instance $instance): ?string {
        return $this->grading()->get_other_method($instance);
    }

    /**
     * Save a draft. The grade is not computed for rubric and guide drafts (the review is incomplete).
     *
     * @param \stdClass $alloc Allocation record.
     * @param int $userid The reviewer.
     * @param array $data Keys: advancedgrading (array), advancedgradinginstanceid, score, feedback, feedbackformat.
     */
    public function save_draft(\stdClass $alloc, int $userid, array $data): void {
        global $DB;

        $this->require_can_edit($alloc, $userid);
        if ((int) $alloc->status === manager::STATUS_SUBMITTED) {
            throw new \moodle_exception('errordraftaftersubmit', 'mod_peerreview');
        }
        if (!$this->supports_drafts()) {
            throw new \moodle_exception('errornodrafts', 'mod_peerreview');
        }

        $update = new \stdClass();
        $update->id = $alloc->id;
        $instance = $this->get_instance($alloc, $userid, $data['advancedgradinginstanceid'] ?? null);
        if ($instance) {
            $value = (new draft_normaliser())->normalise($instance, (array) ($data['advancedgrading'] ?? []));
            if ($value['criteria']) {
                $value['itemid'] = $alloc->id;
                $instance->update($value);
            }
        } else if (isset($data['score']) && $data['score'] !== '') {
            $update->grade = $this->check_score($data['score']);
        }
        $this->apply_feedback($update, $data);
        $update->status = manager::STATUS_DRAFT;
        $update->timemodified = time();
        $DB->update_record('peerreview_alloc', $update);
    }

    /**
     * Submit (or re-submit) a review. Computes and stores the normalised grade.
     *
     * @param \stdClass $alloc Allocation record.
     * @param int $userid The reviewer.
     * @param array $data Keys: advancedgrading (array), advancedgradinginstanceid, score, feedback, feedbackformat.
     */
    public function submit(\stdClass $alloc, int $userid, array $data): void {
        global $DB;

        $this->require_can_edit($alloc, $userid);
        $update = new \stdClass();
        $update->id = $alloc->id;

        $instance = $this->get_instance($alloc, $userid, $data['advancedgradinginstanceid'] ?? null);
        if ($instance) {
            // The web form validates this itself; the web services have no form, so the service checks it too.
            if (!$instance->validate_grading_element((array) ($data['advancedgrading'] ?? []))) {
                throw new \moodle_exception('errorreviewincomplete', 'mod_peerreview');
            }
            $grade = $instance->submit_and_get_grade((array) ($data['advancedgrading'] ?? []), $alloc->id);
            if ($grade < 0) {
                throw new \moodle_exception('errorreviewincomplete', 'mod_peerreview');
            }
            $update->grade = (float) $grade;
        } else {
            if (!isset($data['score']) || $data['score'] === '') {
                throw new \moodle_exception('errorreviewincomplete', 'mod_peerreview');
            }
            $update->grade = $this->check_score($data['score']);
        }
        $this->apply_feedback($update, $data);

        $wassubmitted = (int) $alloc->status === manager::STATUS_SUBMITTED;
        $update->status = manager::STATUS_SUBMITTED;
        $update->timemodified = time();
        if (!$wassubmitted) {
            $update->timesubmitted = $update->timemodified;
        }
        $DB->update_record('peerreview_alloc', $update);

        $eventclass = $wassubmitted ? \mod_peerreview\event\review_updated::class : \mod_peerreview\event\review_submitted::class;
        $eventclass::create([
            'objectid' => $alloc->id,
            'context' => $this->context,
            'userid' => $userid,
            'relateduserid' => $alloc->revieweeid,
        ])->trigger();

        \mod_peerreview\local\completion::update($this->peerreview, [(int) $alloc->reviewerid]);
    }

    /**
     * Validate a simple-form score against the activity maximum.
     *
     * @param mixed $score Submitted value.
     * @return float
     * @throws \moodle_exception
     */
    public function check_score($score): float {
        $range = $this->get_range();
        if (!$range->accepts($score)) {
            throw new \moodle_exception($range->get_error_code(), 'mod_peerreview', '', $range->max());
        }
        return (float) $score;
    }

    /**
     * The range of the grade: points or a scale.
     *
     * @return grade_range
     */
    public function get_range(): grade_range {
        return grade_range::for_activity($this->peerreview);
    }

    /**
     * Copy the overall comment into an allocation update.
     *
     * @param \stdClass $update Update record.
     * @param array $data Submitted data.
     */
    private function apply_feedback(\stdClass $update, array $data): void {
        if (array_key_exists('feedback', $data)) {
            $update->feedback = $data['feedback'];
            $update->feedbackformat = $data['feedbackformat'] ?? FORMAT_HTML;
        }
    }
}
