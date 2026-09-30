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

/**
 * Review service for one peer review activity.
 *
 * @package    mod_peerreview
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class service {
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
     * The grading manager for this activity's grading area (loads the grading library, which pages do not include).
     *
     * @return \grading_manager
     */
    private function get_grading_manager(): \grading_manager {
        global $CFG;
        require_once($CFG->dirroot . '/grade/grading/lib.php');
        return get_grading_manager($this->context, 'mod_peerreview', 'received');
    }

    /**
     * The active advanced grading controller, or null when the simple points-and-comment form is used.
     *
     * @return \gradingform_controller|null
     */
    public function get_controller(): ?\gradingform_controller {
        $manager = $this->get_grading_manager();
        $method = $manager->get_active_method();
        if (!$method) {
            return null;
        }
        $controller = $manager->get_controller($method);
        if (!$controller->is_form_available()) {
            return null;
        }
        $controller->set_grade_range(make_grades_menu($this->peerreview->grade), true);
        return $controller;
    }

    /**
     * Message explaining why the active grading method cannot be used (form still a draft), if that is the case.
     *
     * @return string Empty when there is no problem.
     */
    public function get_unavailable_message(): string {
        $manager = $this->get_grading_manager();
        $method = $manager->get_active_method();
        if ($method) {
            $controller = $manager->get_controller($method);
            if (!$controller->is_form_available()) {
                return $controller->form_unavailable_notification() ?? '';
            }
        }
        return '';
    }

    /**
     * Whether drafts can be stored for the active grading method (rubric and marking guide only).
     *
     * @return bool
     */
    public function supports_drafts(): bool {
        $controller = $this->get_controller();
        return $controller === null
            || $controller instanceof \gradingform_rubric_controller
            || $controller instanceof \gradingform_guide_controller;
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
        $controller = $this->get_controller();
        return $controller?->get_or_create_instance($instanceid, $raterid, $alloc->id);
    }

    /**
     * The submitted (active) instance of a review, for read-only display.
     *
     * @param \stdClass $alloc Allocation record.
     * @return \gradingform_instance|null
     */
    public function get_submitted_instance(\stdClass $alloc): ?\gradingform_instance {
        return $this->get_controller()?->get_current_instance($alloc->reviewerid, $alloc->id);
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
            $value = $this->normalise_draft($instance, (array) ($data['advancedgrading'] ?? []));
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
        if (!is_numeric($score) || $score < 0 || $score > $this->peerreview->grade) {
            throw new \moodle_exception('errorscorerange', 'mod_peerreview', '', (int) $this->peerreview->grade);
        }
        return (float) $score;
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

    /**
     * Reduce a partly filled form to what the grading method can store.
     *
     * Rubric: criteria with a chosen level or a remark. Marking guide: criteria with a valid score (the score column is
     * mandatory, so a remark without a score cannot be kept in a draft).
     *
     * @param \gradingform_instance $instance The instance.
     * @param array $value Submitted advancedgrading value.
     * @return array Value with only storable criteria.
     */
    private function normalise_draft(\gradingform_instance $instance, array $value): array {
        $criteria = [];
        foreach ((array) ($value['criteria'] ?? []) as $criterionid => $record) {
            $remark = trim((string) ($record['remark'] ?? ''));
            if ($instance instanceof \gradingform_rubric_instance) {
                $levelid = isset($record['levelid']) && is_numeric($record['levelid']) ? (int) $record['levelid'] : null;
                if ($levelid !== null || $remark !== '') {
                    $criteria[$criterionid] = ['levelid' => $levelid, 'remark' => $remark];
                }
            } else {
                $definition = $instance->get_controller()->get_definition();
                $max = $definition->guide_criteria[$criterionid]['maxscore'] ?? null;
                $score = $record['score'] ?? '';
                if ($max !== null && is_numeric($score) && $score >= 0 && $score <= $max) {
                    $criteria[$criterionid] = ['score' => $score, 'remark' => $remark];
                }
            }
        }
        return ['criteria' => $criteria];
    }
}
