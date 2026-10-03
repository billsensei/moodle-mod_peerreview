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
 * Access to the advanced grading controllers and instances of an activity.
 *
 * Modelled on the grading instance handling in mod/assign/locallib.php (get_grading_instance).
 *
 * @package    mod_peerreview
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_peerreview\local\review;

use mod_peerreview\local\grade\grade_range;

/**
 * Finds the grading controller and the grading instances for the reviews of one peer review activity.
 *
 * Split out of the review service, which still offers these methods and hands them to this class. It is also where
 * the grading libraries get loaded: pages do not include them, and unit tests that use core generators hide a missing
 * include (see learn.md).
 *
 * @package    mod_peerreview
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class grading_access {
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
        $controller->set_grade_range(grade_range::for_activity($this->peerreview)->get_menu(), true);
        return $controller;
    }

    /**
     * The grading method that was active when a review was stored, if it is no longer the active one.
     *
     * Core keeps one definition per method and area, and each grading instance belongs to a definition, so reviews
     * written before the teacher switched methods still point at the old method's form.
     *
     * @param \stdClass $alloc Allocation record.
     * @return \gradingform_instance|null The stored instance of the old method, null when there is none or its method
     *         cannot be loaded any more.
     */
    private function get_instance_of_other_method(\stdClass $alloc): ?\gradingform_instance {
        global $CFG, $DB;

        $manager = $this->get_grading_manager();
        require_once($CFG->dirroot . '/grade/grading/form/lib.php'); // Pages only load it with a controller.
        $active = $manager->get_active_method();
        $sql = "SELECT gi.*, gd.method
                  FROM {grading_instances} gi
                  JOIN {grading_definitions} gd ON gd.id = gi.definitionid
                  JOIN {grading_areas} ga ON ga.id = gd.areaid
                 WHERE gi.itemid = :itemid AND ga.contextid = :contextid AND ga.component = :component
                       AND ga.areaname = :areaname AND gi.status IN (:active, :needupdate)
              ORDER BY gi.timemodified DESC, gi.id DESC";
        $records = $DB->get_records_sql($sql, [
            'itemid' => $alloc->id,
            'contextid' => $this->context->id,
            'component' => 'mod_peerreview',
            'areaname' => 'received',
            'active' => \gradingform_instance::INSTANCE_STATUS_ACTIVE,
            'needupdate' => \gradingform_instance::INSTANCE_STATUS_NEEDUPDATE,
        ], 0, 5);
        foreach ($records as $record) {
            if ($record->method === $active) {
                continue;
            }
            try {
                $controller = $manager->get_controller($record->method);
            } catch (\moodle_exception $e) {
                continue; // The method plugin is disabled or gone.
            }
            if ($controller->is_form_available()) {
                $controller->set_grade_range(grade_range::for_activity($this->peerreview)->get_menu(), true);
                return $controller->get_current_instance($alloc->reviewerid, $alloc->id);
            }
        }
        return null;
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
     * When the review was written with another grading method than the active one (the teacher switched afterwards),
     * its stored instance of that method is returned, so it is shown as it was filled in.
     *
     * @param \stdClass $alloc Allocation record.
     * @return \gradingform_instance|null
     */
    public function get_submitted_instance(\stdClass $alloc): ?\gradingform_instance {
        return $this->get_controller()?->get_current_instance($alloc->reviewerid, $alloc->id)
            ?? $this->get_instance_of_other_method($alloc);
    }

    /**
     * The grading method (for example 'rubric') a stored instance was written with, when it is not the active one.
     *
     * @param \gradingform_instance $instance The instance.
     * @return string|null Null when the instance belongs to the active method.
     */
    public function get_other_method(\gradingform_instance $instance): ?string {
        // The method plugin name is part of the controller class name (gradingform_rubric_controller).
        if (!preg_match('/^gradingform_(\w+)_controller$/', get_class($instance->get_controller()), $matches)) {
            return null;
        }
        return $matches[1] !== $this->get_grading_manager()->get_active_method() ? $matches[1] : null;
    }
}
