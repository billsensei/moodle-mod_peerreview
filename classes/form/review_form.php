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
 * The review form: rubric, marking guide or simple points and comment.
 *
 * Modelled on the grading form handling in mod/assign/gradeform.php and mod/assign/locallib.php.
 *
 * @package    mod_peerreview
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_peerreview\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * The review form.
 *
 * Custom data: cmid, allocid, instance (gradingform_instance|null), range (grade_range), context, candraft, submitted, readonly,
 * score, feedback, feedbackformat.
 *
 * @package    mod_peerreview
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class review_form extends \moodleform {
    /**
     * Whether the draft button was pressed. Drafts skip the "complete every criterion" validation.
     *
     * @return bool
     */
    public static function is_draft_request(): bool {
        return optional_param('savedraft', null, PARAM_RAW) !== null;
    }

    /**
     * Define the form.
     */
    protected function definition(): void {
        $mform = $this->_form;
        $data = $this->_customdata;

        $mform->addElement('hidden', 'id', $data['cmid']);
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'alloc', $data['allocid']);
        $mform->setType('alloc', PARAM_INT);

        if ($data['instance']) {
            $mform->addElement('hidden', 'advancedgradinginstanceid', $data['instance']->get_id());
            $mform->setType('advancedgradinginstanceid', PARAM_INT);
            $mform->addElement('grading', 'advancedgrading', '', ['gradinginstance' => $data['instance']]);
        } else {
            if ($data['range']->is_scale()) {
                $mform->addElement('select', 'score', get_string('score', 'mod_peerreview'), ['' => get_string('choosedots')]
                    + $data['range']->get_menu());
                $mform->setType('score', PARAM_RAW);
                $mform->addHelpButton('score', 'scorescale', 'mod_peerreview');
                $mform->setDefault('score', $data['score'] === null ? '' : (string) (int) round((float) $data['score']));
            } else {
                $mform->addElement('text', 'score', get_string('score', 'mod_peerreview'), ['size' => 6]);
                $mform->setType('score', PARAM_LOCALISEDFLOAT);
                $mform->addHelpButton('score', 'score', 'mod_peerreview');
                $mform->setDefault('score', $data['score'] === null ? '' : format_float((float) $data['score'], -1));
            }
        }

        $mform->addElement('editor', 'feedback_editor', get_string('overallcomment', 'mod_peerreview'), null, [
            'maxfiles' => 0,
            'context' => $data['context'],
            'trusttext' => false,
        ]);
        $mform->setType('feedback_editor', PARAM_RAW);
        $mform->setDefault('feedback_editor', ['text' => $data['feedback'], 'format' => $data['feedbackformat']]);

        if ($data['readonly']) {
            $mform->hardFreezeAllVisibleExcept([]);
            return;
        }

        $buttons = [];
        if ($data['candraft']) {
            $buttons[] = $mform->createElement('submit', 'savedraft', get_string('savedraft', 'mod_peerreview'), [
                'class' => 'btn-secondary',
            ]);
        }
        $buttons[] = $mform->createElement(
            'submit',
            'submitreview',
            get_string($data['submitted'] ? 'updatereview' : 'submitreview', 'mod_peerreview')
        );
        $buttons[] = $mform->createElement('cancel');
        $mform->addGroup($buttons, 'buttonar', '', ' ', false);
        $mform->closeHeaderBefore('buttonar');
    }

    /**
     * Validate the defined fields. A draft may be incomplete, so the "every criterion filled in" rule that the grading
     * element registers (again on every submission update) is dropped just before validation.
     *
     * @param bool $validateonnosubmit Validate even for no-submit buttons.
     * @return bool
     */
    public function validate_defined_fields($validateonnosubmit = false) {
        if (self::is_draft_request()) {
            unset($this->_form->_rules['advancedgrading']);
        }
        return parent::validate_defined_fields($validateonnosubmit);
    }

    /**
     * Validate the simple score (advanced grading elements validate themselves).
     *
     * @param array $data Submitted data.
     * @param array $files Submitted files.
     * @return array
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        if (!$this->_customdata['instance']) {
            $score = $data['score'] ?? '';
            $draft = self::is_draft_request();
            if ($score === '' || $score === null) {
                if (!$draft) {
                    $errors['score'] = get_string('required');
                }
            } else if (!$this->_customdata['range']->accepts($score)) {
                $errors['score'] = $this->_customdata['range']->get_error();
            }
        }
        return $errors;
    }
}
