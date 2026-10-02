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
 * Forms for the allocation methods.
 *
 * @package    mod_peerreview
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_peerreview\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * One form class for all allocation methods: the method decides which fields appear.
 *
 * Custom data: method, cmid, students (userid => name), groups (groupid => name), groupmode (bool).
 *
 * @package    mod_peerreview
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class allocate_form extends \moodleform {
    /**
     * Define the fields for the current method.
     */
    protected function definition(): void {
        $mform = $this->_form;
        $data = $this->_customdata;

        $mform->addElement('hidden', 'id', $data['cmid']);
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'method', $data['method']);
        $mform->setType('method', PARAM_ALPHA);

        switch ($data['method']) {
            case 'manual':
                $mform->addElement('autocomplete', 'reviewerid', get_string('reviewer', 'mod_peerreview'), $data['students']);
                $mform->addRule('reviewerid', null, 'required', null, 'client');
                $mform->addElement(
                    'autocomplete',
                    'revieweeids',
                    get_string('reviewees', 'mod_peerreview'),
                    $data['students'],
                    ['multiple' => true]
                );
                $mform->addRule('revieweeids', null, 'required', null, 'client');
                break;
            case 'random':
                $mform->addElement('text', 'n', get_string('reviewspersstudent', 'mod_peerreview'), ['size' => 3]);
                $mform->setType('n', PARAM_INT);
                $mform->setDefault('n', 2);
                $mform->addRule('n', null, 'required', null, 'client');
                if ($data['groupmode']) {
                    $mform->addElement('advcheckbox', 'crossgroup', get_string('allowcrossgroup', 'mod_peerreview'));
                }
                $this->add_replace();
                break;
            case 'group':
                $mform->addElement(
                    'select',
                    'groupid',
                    get_string('group'),
                    [0 => get_string('allgroups', 'mod_peerreview')] + $data['groups']
                );
                $this->add_replace();
                break;
            case 'rotation':
                $mform->addElement('text', 'shift', get_string('rotationshift', 'mod_peerreview'), ['size' => 3]);
                $mform->setType('shift', PARAM_INT);
                $mform->setDefault('shift', 1);
                $mform->addRule('shift', null, 'required', null, 'client');
                $mform->addElement('select', 'sortby', get_string('rotationorder', 'mod_peerreview'), [
                    'lastname' => get_string('lastname'),
                    'firstname' => get_string('firstname'),
                    'username' => get_string('username'),
                    'random' => get_string('random', 'mod_peerreview'),
                ]);
                $mform->addElement('textarea', 'usernames', get_string('rotationusernames', 'mod_peerreview'), [
                    'rows' => 4,
                    'cols' => 30,
                ]);
                $mform->addHelpButton('usernames', 'rotationusernames', 'mod_peerreview');
                $mform->setType('usernames', PARAM_RAW);
                $this->add_replace();
                break;
            case 'self':
                // Nothing to choose: every student reviews themselves.
                break;
            case 'csv':
                $mform->addElement('filepicker', 'csvfile', get_string('csvfile', 'mod_peerreview'), null, [
                    'accepted_types' => ['.csv', '.txt'],
                ]);
                $mform->addRule('csvfile', null, 'required', null, 'client');
                $mform->addHelpButton('csvfile', 'csvfile', 'mod_peerreview');
                $mform->addElement('select', 'delimiter', get_string('csvdelimiter', 'mod_peerreview'), [
                    'comma' => ',',
                    'semicolon' => ';',
                    'tab' => "\t",
                ]);
                break;
        }

        $this->add_action_buttons(
            false,
            get_string($data['method'] === 'manual' ? 'addallocations' : 'preview', 'mod_peerreview')
        );
    }

    /**
     * The "replace not-started allocations" checkbox shared by the automatic methods.
     */
    private function add_replace(): void {
        $this->_form->addElement('advcheckbox', 'replace', get_string('replaceunstarted', 'mod_peerreview'));
        $this->_form->addHelpButton('replace', 'replaceunstarted', 'mod_peerreview');
    }

    /**
     * Validate.
     *
     * @param array $data Submitted data.
     * @param array $files Submitted files.
     * @return array
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        if ($data['method'] === 'random' && (int) $data['n'] < 1) {
            $errors['n'] = get_string('errorpositive', 'mod_peerreview');
        }
        if ($data['method'] === 'rotation' && (int) $data['shift'] === 0) {
            $errors['shift'] = get_string('errorshiftzero', 'mod_peerreview');
        }
        return $errors;
    }
}
