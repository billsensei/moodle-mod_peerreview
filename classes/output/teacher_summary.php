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
 * Renderable: the teacher's summary on the activity page.
 *
 * @package    mod_peerreview
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_peerreview\output;

/**
 * Teacher summary: counts, links and the release switch.
 *
 * @package    mod_peerreview
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class teacher_summary implements \renderable, \templatable {
    /**
     * Constructor.
     *
     * @param \stdClass $peerreview Activity record.
     * @param \context_module $context Module context.
     * @param int $cmid Course module id.
     * @param \stdClass $summary From progress::summarise().
     */
    public function __construct(
        /** @var \stdClass Activity record. */
        private readonly \stdClass $peerreview,
        /** @var \context_module Module context. */
        private readonly \context_module $context,
        /** @var int Course module id. */
        private readonly int $cmid,
        /** @var \stdClass Summary figures. */
        private readonly \stdClass $summary
    ) {
    }

    /**
     * Export for the template.
     *
     * @param \renderer_base $output
     * @return \stdClass
     */
    public function export_for_template(\renderer_base $output): \stdClass {
        $released = (bool) $this->peerreview->feedbackreleased;
        $percent = $this->summary->allocations
            ? (int) round(100 * $this->summary->submitted / $this->summary->allocations) : 0;
        $data = [
            'students' => $this->summary->students,
            'allocations' => $this->summary->allocations,
            'submitted' => $this->summary->submitted,
            'percent' => $percent,
            'released' => $released,
            'statusreleased' => get_string($released ? 'feedbackisreleased' : 'feedbackishidden', 'mod_peerreview'),
            'progresstext' => get_string('reviewsdone', 'mod_peerreview', (object) [
                'done' => $this->summary->submitted,
                'total' => $this->summary->allocations,
            ]),
            'reporturl' => '',
            'allocateurl' => '',
            'releasebutton' => '',
        ];
        if (has_capability('mod/peerreview:viewallreviews', $this->context)) {
            $data['reporturl'] = (new \moodle_url('/mod/peerreview/report.php', ['id' => $this->cmid]))->out(false);
        }
        if (has_capability('mod/peerreview:allocate', $this->context)) {
            $data['allocateurl'] = (new \moodle_url('/mod/peerreview/allocate.php', ['id' => $this->cmid]))->out(false);
        }
        if (has_capability('mod/peerreview:releasefeedback', $this->context)) {
            $data['releasebutton'] = $output->render(release_button::make($this->cmid, $released));
        }
        return (object) ($data + [
            'hasreport' => $data['reporturl'] !== '',
            'hasallocate' => $data['allocateurl'] !== '',
            'hasrelease' => $data['releasebutton'] !== '',
            'noallocations' => $this->summary->allocations === 0,
        ]);
    }
}
