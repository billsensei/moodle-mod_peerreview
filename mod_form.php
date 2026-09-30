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
 * The main mod_peerreview configuration form.
 *
 * Modelled on mod/assign/mod_form.php and mod/workshop/mod_form.php.
 *
 * @package    mod_peerreview
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');

/**
 * Module settings form.
 *
 * @package    mod_peerreview
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_peerreview_mod_form extends moodleform_mod {
    /**
     * Define the form.
     */
    public function definition(): void {
        global $CFG;

        $mform = $this->_form;

        $mform->addElement('header', 'general', get_string('general', 'form'));
        $mform->addElement('text', 'name', get_string('peerreviewname', 'mod_peerreview'), ['size' => '64']);
        $mform->setType('name', empty($CFG->formatstringstriptags) ? PARAM_CLEANHTML : PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        $this->standard_intro_elements();

        $mform->addElement('header', 'reviewsettings', get_string('pluginname', 'mod_peerreview'));
        $mform->addElement('select', 'aggregation', get_string('aggregation', 'mod_peerreview'), [
            0 => get_string('aggregationmean', 'mod_peerreview'),
            1 => get_string('aggregationmedian', 'mod_peerreview'),
        ]);
        $mform->addHelpButton('aggregation', 'aggregation', 'mod_peerreview');
        $mform->addElement('select', 'anonymous', get_string('anonymous', 'mod_peerreview'), [
            1 => get_string('anonymous_anonymous', 'mod_peerreview'),
            0 => get_string('anonymous_byname', 'mod_peerreview'),
        ]);
        $mform->addHelpButton('anonymous', 'anonymous', 'mod_peerreview');
        $mform->addElement('advcheckbox', 'allowselfreview', get_string('allowselfreview', 'mod_peerreview'));
        $mform->addHelpButton('allowselfreview', 'allowselfreview', 'mod_peerreview');
        $mform->addElement('advcheckbox', 'feedbackreleased', get_string('feedbackreleased', 'mod_peerreview'));
        $mform->addElement('date_time_selector', 'timeopen', get_string('timeopen', 'mod_peerreview'), ['optional' => true]);
        $mform->addElement('date_time_selector', 'timeclose', get_string('timeclose', 'mod_peerreview'), ['optional' => true]);

        // The received grade (item 0) and the grading method selector are added by core.
        $this->standard_grading_coursemodule_elements();

        // The optional participation grade (item 1).
        $mform->addElement('modgrade', 'gradeparticipation', get_string('gradeparticipation', 'mod_peerreview'));
        $mform->addHelpButton('gradeparticipation', 'gradeparticipation', 'mod_peerreview');
        $mform->setDefault('gradeparticipation', 0);

        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }

    /**
     * Add the custom completion rule "submit all assigned reviews".
     *
     * Modelled on mod_assign_mod_form::add_completion_rules().
     *
     * @return array Names of the elements added.
     */
    public function add_completion_rules(): array {
        $mform = $this->_form;
        $element = 'completionallreviews' . $this->get_suffix();
        $mform->addElement('advcheckbox', $element, '', get_string('completionallreviews', 'mod_peerreview'));
        return [$element];
    }

    /**
     * Whether the custom completion rule is switched on in the submitted data.
     *
     * @param array $data Form data.
     * @return bool
     */
    public function completion_rule_enabled($data): bool {
        return !empty($data['completionallreviews' . $this->get_suffix()]);
    }

    /**
     * Validate the submitted data. Grades are points only in v1 (no scales).
     *
     * @param array $data Submitted data.
     * @param array $files Submitted files.
     * @return array Errors keyed by element name.
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        foreach (['grade', 'gradeparticipation'] as $field) {
            if (isset($data[$field]) && (int) $data[$field] < 0) {
                $errors[$field] = get_string('pointsonly', 'mod_peerreview');
            }
        }
        return $errors;
    }
}
