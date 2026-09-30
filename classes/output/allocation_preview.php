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
 * Renderable: preview of what an automatic allocation would do.
 *
 * @package    mod_peerreview
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_peerreview\output;

/**
 * Allocation preview: warnings, totals per student, and the confirm button.
 *
 * @package    mod_peerreview
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class allocation_preview implements \renderable, \templatable {
    /**
     * Constructor.
     *
     * @param \stdClass $plan Result of manager::plan().
     * @param \stdClass[] $users userid => user record.
     * @param \moodle_url $confirmurl Confirm action URL (params for a POST).
     * @param \moodle_url $backurl Where to go to change the settings.
     */
    public function __construct(
        /** @var \stdClass Plan. */
        private readonly \stdClass $plan,
        /** @var \stdClass[] Users. */
        private readonly array $users,
        /** @var \moodle_url Confirm URL. */
        private readonly \moodle_url $confirmurl,
        /** @var \moodle_url Back URL. */
        private readonly \moodle_url $backurl
    ) {
    }

    /**
     * Export for the template.
     *
     * @param \renderer_base $output
     * @return \stdClass
     */
    public function export_for_template(\renderer_base $output): \stdClass {
        $proposal = $this->plan->proposal;
        $newgiven = [];
        $newreceived = [];
        foreach ($proposal->pairs as [$reviewer, $reviewee]) {
            $newgiven[$reviewer] = ($newgiven[$reviewer] ?? 0) + 1;
            $newreceived[$reviewee] = ($newreceived[$reviewee] ?? 0) + 1;
        }

        $rows = [];
        foreach ($this->users as $userid => $user) {
            $rows[] = [
                'name' => fullname($user),
                'gives' => $this->plan->given[$userid] ?? 0,
                'givesnew' => $newgiven[$userid] ?? 0,
                'receives' => $this->plan->received[$userid] ?? 0,
                'receivesnew' => $newreceived[$userid] ?? 0,
            ];
        }

        $warnings = [];
        foreach ($proposal->warnings as [$identifier, $a]) {
            $warnings[] = ['text' => get_string($identifier, 'mod_peerreview', $a)];
        }

        $received = array_map(fn($user) => $this->plan->received[$user->id] ?? 0, $this->users);
        $spread = $received ? max($received) - min($received) : 0;

        $confirm = new \single_button(
            $this->confirmurl,
            get_string('confirmallocation', 'mod_peerreview'),
            'post',
            \single_button::BUTTON_PRIMARY
        );
        $back = new \single_button($this->backurl, get_string('changesettings', 'mod_peerreview'), 'get');

        return (object) [
            'newcount' => count($proposal->pairs),
            'deletecount' => count($this->plan->deleteids),
            'keptstarted' => $this->plan->keptstarted,
            'haskeptstarted' => $this->plan->keptstarted > 0,
            'hasdeletes' => !empty($this->plan->deleteids),
            'spread' => $spread,
            'warnings' => $warnings,
            'haswarnings' => !empty($warnings),
            'rows' => $rows,
            'canconfirm' => !empty($proposal->pairs) || !empty($this->plan->deleteids),
            'confirmbutton' => $output->render($confirm),
            'backbutton' => $output->render($back),
        ];
    }
}
