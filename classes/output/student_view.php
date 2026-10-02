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
 * Renderable: the student's page (reviews to do and feedback received).
 *
 * @package    mod_peerreview
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_peerreview\output;

use mod_peerreview\local\allocation\manager;
use mod_peerreview\local\grade\grade_range;

/**
 * Student page: cards for the reviews to do and, when released, the feedback received.
 *
 * @package    mod_peerreview
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class student_view implements \renderable, \templatable {
    /**
     * Constructor.
     *
     * @param \stdClass $peerreview Activity record.
     * @param \context_module $context Module context.
     * @param int $cmid Course module id.
     * @param \stdClass[] $todo From student_data::get_todo().
     * @param \stdClass[]|null $received From student_data::get_received(), null when feedback is not released.
     * @param float|null $grade The student's received grade.
     * @param string $window 'open', 'notopen' or 'closed'.
     * @param \stdClass|null $comparison From student_data::get_self_comparison(), plus criteria from
     *                                    criterion_stats::get_comparison(); null when there is nothing to compare.
     */
    public function __construct(
        /** @var \stdClass Activity record. */
        private readonly \stdClass $peerreview,
        /** @var \context_module Module context. */
        private readonly \context_module $context,
        /** @var int Course module id. */
        private readonly int $cmid,
        /** @var \stdClass[] Reviews to do. */
        private readonly array $todo,
        /** @var \stdClass[]|null Reviews received. */
        private readonly ?array $received,
        /** @var float|null Received grade. */
        private readonly ?float $grade,
        /** @var string Window state. */
        private readonly string $window,
        /** @var \stdClass|null Self-assessment compared with the peers. */
        private readonly ?\stdClass $comparison = null
    ) {
    }

    /**
     * Export for the template.
     *
     * @param \renderer_base $output
     * @return \stdClass
     */
    public function export_for_template(\renderer_base $output): \stdClass {
        $range = grade_range::for_activity($this->peerreview);
        $statuses = [
            manager::STATUS_NEW => [get_string('statusnew', 'mod_peerreview'), 'bg-secondary'],
            manager::STATUS_DRAFT => [get_string('statusdraft', 'mod_peerreview'), 'bg-warning text-dark'],
            manager::STATUS_SUBMITTED => [get_string('statussubmitted', 'mod_peerreview'), 'bg-success'],
        ];
        $open = $this->window === 'open';

        $cards = [];
        $done = 0;
        foreach ($this->todo as $item) {
            $done += $item->status === manager::STATUS_SUBMITTED ? 1 : 0;
            $action = null;
            if ($open) {
                $action = ['review' . $item->status, 'mod_peerreview'];
            } else if ($this->window === 'closed' && $item->status === manager::STATUS_SUBMITTED) {
                $action = ['viewreview', 'mod_peerreview'];
            }
            $cards[] = [
                'name' => fullname($item->user),
                'picture' => $output->user_picture($item->user, ['size' => 64, 'link' => false, 'includefullname' => false]),
                'statuslabel' => $statuses[$item->status][0],
                'statusclass' => $statuses[$item->status][1],
                'actionlabel' => $action ? get_string(...$action) : '',
                'url' => $action ? (new \moodle_url('/mod/peerreview/review.php', [
                    'id' => $this->cmid,
                    'alloc' => $item->id,
                ]))->out(false) : '',
            ];
        }

        $received = null;
        if ($this->received !== null) {
            $received = [];
            foreach ($this->received as $review) {
                if ($review->reviewer) {
                    $who = fullname($review->reviewer);
                    if ($review->isself) {
                        $who = get_string('selfreview', 'mod_peerreview');
                    }
                } else {
                    $who = get_string('anonymousreviewer', 'mod_peerreview');
                }
                $received[] = [
                    'who' => $who,
                    'grade' => $range->format_with_max((float) $review->grade),
                    'hascomment' => trim((string) $review->feedback) !== '',
                    'comment' => format_text($review->feedback ?? '', $review->feedbackformat, ['context' => $this->context]),
                    'url' => (new \moodle_url('/mod/peerreview/feedback.php', [
                        'id' => $this->cmid,
                        'alloc' => $review->id,
                    ]))->out(false),
                ];
            }
        }

        $comparison = $this->comparison ? $this->export_comparison($this->comparison, $range) : null;

        $total = count($cards);
        return (object) [
            'hascards' => $total > 0,
            'cards' => $cards,
            'done' => $done,
            'total' => $total,
            'progresstext' => get_string('reviewsdone', 'mod_peerreview', (object) ['done' => $done, 'total' => $total]),
            'notopen' => $this->window === 'notopen',
            'closed' => $this->window === 'closed',
            'showreceived' => $received !== null,
            'received' => $received,
            'hasreceived' => !empty($received),
            'hasgrade' => $this->grade !== null,
            'grade' => $this->grade === null ? '' : $range->format_with_max($this->grade),
            'hascomparison' => $comparison !== null,
            'comparison' => $comparison,
        ];
    }

    /**
     * The self-assessment compared with the peers, ready for the template.
     *
     * @param \stdClass $comparison Overall grades (self, peers) and, for a rubric or marking guide, criteria.
     * @param grade_range $range Grade range of the activity.
     * @return \stdClass
     */
    private function export_comparison(\stdClass $comparison, grade_range $range): \stdClass {
        if ($range->is_scale()) {
            // A scale has no meaningful distance, only the same item or not.
            $same = $range->format($comparison->self) === $range->format($comparison->peers);
            $summary = get_string($same ? 'selfcomparesame' : 'selfcomparedifferent', 'mod_peerreview');
        } else {
            $difference = round($comparison->self - $comparison->peers, 1);
            $summary = match (true) {
                $difference > 0 => get_string('selfcomparehigher', 'mod_peerreview', format_float($difference, 1, true, true)),
                $difference < 0 => get_string('selfcomparelower', 'mod_peerreview', format_float(-$difference, 1, true, true)),
                default => get_string('selfcomparesame', 'mod_peerreview'),
            };
        }
        $criteria = [];
        foreach ($comparison->criteria ?? [] as $criterion) {
            $criteria[] = [
                'name' => $criterion['name'],
                'self' => format_float($criterion['self'], 2, true, true),
                'peers' => format_float($criterion['peers'], 2, true, true),
                'max' => format_float($criterion['max'], 2, true, true),
            ];
        }
        return (object) [
            'self' => $range->format_with_max($comparison->self),
            'peers' => $range->format_with_max($comparison->peers),
            'summary' => $summary,
            'hascriteria' => !empty($criteria),
            'criteria' => $criteria,
        ];
    }
}
