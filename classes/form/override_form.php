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
 * Form to override a student's received grade.
 *
 * @package    mod_peerreview
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_peerreview\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Override form. Custom data: cmid, userid, maxgrade, grade (current override or null), note, calculated (the peer grade).
 *
 * @package    mod_peerreview
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class override_form extends \moodleform {
    /**
     * Define the form.
     */
    protected function definition(): void {
        $mform = $this->_form;
        $data = $this->_customdata;

        $mform->addElement('hidden', 'id', $data['cmid']);
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'user', $data['userid']);
        $mform->setType('user', PARAM_INT);

        $mform->addElement('header', 'overrideheader', get_string('overridegrade', 'mod_peerreview'));
        $calculated = $data['calculated'] === null
            ? get_string('nogradeyet', 'mod_peerreview')
            : format_float($data['calculated'], 2);
        $mform->addElement('static', 'calculated', get_string('calculatedgrade', 'mod_peerreview'), $calculated);

        $mform->addElement('text', 'overridegrade', get_string('overridegradevalue', 'mod_peerreview', (int) $data['maxgrade']), [
            'size' => 6,
        ]);
        $mform->setType('overridegrade', PARAM_LOCALISEDFLOAT);
        $mform->addRule('overridegrade', null, 'required', null, 'client');
        if ($data['grade'] !== null) {
            $mform->setDefault('overridegrade', format_float((float) $data['grade'], -1));
        }

        $mform->addElement('textarea', 'overridenote', get_string('overridenote', 'mod_peerreview'), ['rows' => 3, 'cols' => 40]);
        $mform->setType('overridenote', PARAM_TEXT);
        $mform->setDefault('overridenote', $data['note'] ?? '');

        $this->add_action_buttons(false, get_string('saveoverride', 'mod_peerreview'));
    }

    /**
     * Validate the grade range.
     *
     * @param array $data Submitted data.
     * @param array $files Submitted files.
     * @return array
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        $grade = $data['overridegrade'] ?? '';
        if (!is_numeric($grade) || $grade < 0 || $grade > $this->_customdata['maxgrade']) {
            $errors['overridegrade'] = get_string('errorscorerange', 'mod_peerreview', (int) $this->_customdata['maxgrade']);
        }
        return $errors;
    }
}
