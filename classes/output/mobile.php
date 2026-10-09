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

namespace mod_peerreview\output;

use mod_peerreview\local\allocation\manager;
use mod_peerreview\local\group_access;
use mod_peerreview\local\progress;
use mod_peerreview\local\review\service;
use mod_peerreview\local\student_data;

/**
 * What the Moodle app shows for a peer review activity: reviews to do, the simple review form and received feedback for
 * students; the class progress and the feedback release switch for teachers.
 *
 * The app asks for the content with core_course_get_module-style args (cmid, courseid) and renders the returned
 * template with Angular. All text is produced here in the user's language, so the template needs no language strings.
 * Reviews that use a rubric or marking guide, the self-assessment comparison, the full report and the allocation open in the
 * browser.
 *
 * @package    mod_peerreview
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mobile {
    /**
     * The activity page.
     *
     * @param array $args Keys from the app: cmid, courseid, userid, ...
     * @return array Template, script and data in the format of tool_mobile_get_content.
     */
    public static function mobile_course_view(array $args): array {
        global $CFG, $DB, $USER;

        $cmid = (int) $args['cmid'];
        [$course, $cm] = get_course_and_cm_from_cmid($cmid, 'peerreview');
        $context = \context_module::instance($cm->id);
        require_login($course, false, $cm, true, true);
        require_capability('mod/peerreview:view', $context);

        $peerreview = $DB->get_record('peerreview', ['id' => $cm->instance], '*', MUST_EXIST);
        $state = self::build_state($peerreview, $cm, $context, (int) $USER->id);

        return [
            'templates' => [['id' => 'main', 'html' => file_get_contents($CFG->dirroot . '/mod/peerreview/mobile/main.html')]],
            'javascript' => file_get_contents($CFG->dirroot . '/mod/peerreview/mobile/main.js'),
            'otherdata' => ['data' => json_encode($state)],
            'files' => [],
        ];
    }

    /**
     * Everything the template shows, as plain data.
     *
     * @param \stdClass $peerreview Activity record.
     * @param \cm_info $cm Course module.
     * @param \context_module $context Module context.
     * @param int $userid The user viewing.
     * @return array
     */
    public static function build_state(\stdClass $peerreview, \cm_info $cm, \context_module $context, int $userid): array {
        $service = new service($peerreview, $context);
        $controller = $service->get_controller();
        $window = 'open';
        if ($peerreview->timeopen && time() < $peerreview->timeopen) {
            $window = 'notopen';
        } else if (!$service->is_open()) {
            $window = 'closed';
        }
        $dates = [];
        if ($peerreview->timeopen) {
            $dates[] = get_string('opensat', 'mod_peerreview', userdate($peerreview->timeopen));
        }
        if ($peerreview->timeclose) {
            $dates[] = get_string('closeson', 'mod_peerreview', userdate($peerreview->timeclose));
        }
        $webview = (new \moodle_url('/mod/peerreview/view.php', ['id' => $cm->id]))->out(false);

        $state = [
            'cmid' => (int) $cm->id,
            'name' => format_string($peerreview->name),
            'intro' => format_module_intro('peerreview', $peerreview, $cm->id),
            'dates' => implode(' · ', $dates),
            'window' => $window,
            'windowtext' => match ($window) {
                'notopen' => get_string('notopenyet', 'mod_peerreview'),
                'closed' => get_string('activityclosed', 'mod_peerreview'),
                default => '',
            },
            'canreview' => has_capability('mod/peerreview:review', $context),
            'teacher' => null,
            'webview' => $webview,
            'strings' => self::strings(),
            'todo' => [],
            'progress' => '',
            // True for a grading method the app cannot show (neither rubric nor marking guide): its reviews open in the browser.
            'advanced' => $controller !== null && !$controller instanceof \gradingform_rubric_controller
                && !$controller instanceof \gradingform_guide_controller,
            'showreceived' => false,
            'received' => [],
            'grade' => '',
        ];
        $manager = new manager($peerreview, $cm->get_course_module_record(), $context);
        $state['teacher'] = self::export_teacher($peerreview, $cm, $context, $manager);
        if (!$state['canreview']) {
            return $state;
        }

        $data = new student_data($peerreview, $manager);
        [$state['todo'], $state['progress']] = self::export_todo($data->get_todo($userid), $window, $cm->id);
        if ($peerreview->feedbackreleased) {
            $state['showreceived'] = true;
            $state['received'] = self::export_received($data->get_received($userid), $service, $context, $cm->id);
            $grade = $data->get_grade($userid);
            $state['grade'] = $grade === null ? '' : $service->get_range()->format_with_max($grade);
        }
        return $state;
    }

    /**
     * The teacher's part of the page: how far the class is, who is behind, and the feedback release switch.
     *
     * Same figures and the same group rules as the report page ({@see progress}, {@see group_access}).
     *
     * @param \stdClass $peerreview Activity record.
     * @param \cm_info $cm Course module.
     * @param \context_module $context Module context.
     * @param manager $manager Allocation manager.
     * @return array|null Null when the user has no teacher capability here.
     */
    private static function export_teacher(
        \stdClass $peerreview,
        \cm_info $cm,
        \context_module $context,
        manager $manager
    ): ?array {
        $canreport = has_capability('mod/peerreview:viewallreviews', $context);
        $canallocate = has_capability('mod/peerreview:allocate', $context);
        $canrelease = has_capability('mod/peerreview:releasefeedback', $context);
        if (!$canreport && !$canallocate && !$canrelease) {
            return null;
        }
        $progress = new progress($peerreview, $manager);
        $rows = $canreport ? $progress->get_rows(group_access::resolve($cm, $context, 0)) : [];
        $summary = $progress->summarise($rows);
        $range = $canreport ? (new service($peerreview, $context))->get_range() : null;

        usort($rows, static fn($a, $b) => [$a->user->lastname, $a->user->firstname] <=> [$b->user->lastname, $b->user->firstname]);
        $students = [];
        foreach ($rows as $row) {
            $students[] = [
                'name' => fullname($row->user),
                'given' => $row->given_done . ' / ' . $row->given_total,
                'received' => $row->received_done . ' / ' . $row->received_total,
                'grade' => $row->grade === null ? '' : $range->format($row->grade),
                'participation' => $row->participation === null ? '' : $row->participation . '%',
                'behind' => $row->given_done < $row->given_total,
            ];
        }
        $params = ['id' => $cm->id];
        return [
            'students' => $students,
            'nstudents' => $summary->students,
            'progress' => get_string('reviewsdone', 'mod_peerreview', (object) [
                'done' => $summary->submitted,
                'total' => $summary->allocations,
            ]),
            'canreport' => $canreport,
            'canrelease' => $canrelease,
            'released' => (bool) $peerreview->feedbackreleased,
            'reporturl' => $canreport ? (new \moodle_url('/mod/peerreview/report.php', $params))->out(false) : '',
            'allocateurl' => $canallocate ? (new \moodle_url('/mod/peerreview/allocate.php', $params))->out(false) : '',
        ];
    }

    /**
     * The reviews to do.
     *
     * @param \stdClass[] $todo From student_data::get_todo().
     * @param string $window 'open', 'notopen' or 'closed'.
     * @param int $cmid Course module id.
     * @return array [array[] cards, string progress text]
     */
    private static function export_todo(array $todo, string $window, int $cmid): array {
        $labels = [
            manager::STATUS_NEW => get_string('statusnew', 'mod_peerreview'),
            manager::STATUS_DRAFT => get_string('statusdraft', 'mod_peerreview'),
            manager::STATUS_SUBMITTED => get_string('statussubmitted', 'mod_peerreview'),
        ];
        $cards = [];
        $done = 0;
        foreach ($todo as $item) {
            $done += $item->status === manager::STATUS_SUBMITTED ? 1 : 0;
            $action = '';
            if ($window === 'open') {
                $action = get_string('review' . $item->status, 'mod_peerreview');
            } else if ($window === 'closed' && $item->status === manager::STATUS_SUBMITTED) {
                $action = get_string('viewreview', 'mod_peerreview');
            }
            $cards[] = [
                'allocid' => (int) $item->id,
                'name' => fullname($item->user),
                'status' => $labels[$item->status],
                'submitted' => $item->status === manager::STATUS_SUBMITTED,
                'action' => $action,
                'url' => (new \moodle_url('/mod/peerreview/review.php', ['id' => $cmid, 'alloc' => $item->id]))->out(false),
            ];
        }
        $progress = get_string('reviewsdone', 'mod_peerreview', (object) ['done' => $done, 'total' => count($cards)]);
        return [$cards, $progress];
    }

    /**
     * The reviews the student received.
     *
     * @param \stdClass[] $received From student_data::get_received().
     * @param service $service Review service.
     * @param \context_module $context Module context.
     * @param int $cmid Course module id.
     * @return array[]
     */
    private static function export_received(array $received, service $service, \context_module $context, int $cmid): array {
        $range = $service->get_range();
        $items = [];
        foreach ($received as $review) {
            if (!$review->reviewer) {
                $who = get_string('anonymousreviewer', 'mod_peerreview');
            } else {
                $who = $review->isself ? get_string('selfreview', 'mod_peerreview') : fullname($review->reviewer);
            }
            $items[] = [
                'who' => $who,
                'grade' => $range->format_with_max((float) $review->grade),
                'comment' => trim((string) $review->feedback) === ''
                    ? ''
                    : format_text($review->feedback, $review->feedbackformat, ['context' => $context]),
                'url' => (new \moodle_url('/mod/peerreview/feedback.php', ['id' => $cmid, 'alloc' => $review->id]))->out(false),
            ];
        }
        return $items;
    }

    /**
     * The fixed texts of the page, in the user's language.
     *
     * @return array
     */
    private static function strings(): array {
        return [
            'reviewstodo' => get_string('reviewstodo', 'mod_peerreview'),
            'noreviewsassigned' => get_string('noreviewsassigned', 'mod_peerreview'),
            'feedbackreceived' => get_string('feedbackreceived', 'mod_peerreview'),
            'noreviewsreceived' => get_string('noreviewsreceived', 'mod_peerreview'),
            'yourgrade' => get_string('yourgrade', 'mod_peerreview'),
            'submitreview' => get_string('submitreview', 'mod_peerreview'),
            'savedraft' => get_string('savedraft', 'mod_peerreview'),
            'draftsaved' => get_string('draftsaved', 'mod_peerreview'),
            'remark' => get_string('remark', 'mod_peerreview'),
            'insertcomment' => get_string('insertcomment', 'gradingform_guide'),
            'incomplete' => get_string('errorreviewincomplete', 'mod_peerreview'),
            'statusdraft' => get_string('statusdraft', 'mod_peerreview'),
            'cancel' => get_string('cancel'),
            'overallcomment' => get_string('overallcomment', 'mod_peerreview'),
            'score' => get_string('score', 'mod_peerreview'),
            'scorepoints' => get_string('mobilescorepoints', 'mod_peerreview'),
            'submitted' => get_string('reviewsubmitted', 'mod_peerreview'),
            'openbrowser' => get_string('mobileopenbrowser', 'mod_peerreview'),
            'advancedhint' => get_string('errormobileadvanced', 'mod_peerreview'),
            'teacherhint' => get_string('mobileteacherhint', 'mod_peerreview'),
            'feedbackreleased' => get_string('feedbackisreleased', 'mod_peerreview'),
            'feedbackhidden' => get_string('feedbackishidden', 'mod_peerreview'),
            'release' => get_string('releasefeedback', 'mod_peerreview'),
            'hide' => get_string('hidefeedback', 'mod_peerreview'),
            'report' => get_string('report', 'mod_peerreview'),
            'allocate' => get_string('allocate', 'mod_peerreview'),
            'given' => get_string('reviewsgivencol', 'mod_peerreview'),
            'received' => get_string('reviewsreceivedcol', 'mod_peerreview'),
            'gradecol' => get_string('gradecol', 'mod_peerreview'),
            'nostudents' => get_string('nostudentsshown', 'mod_peerreview'),
            'statussubmitted' => get_string('statussubmitted', 'mod_peerreview'),
            'reviewedit' => get_string('review2', 'mod_peerreview'),
            // Placeholders {done} and {total} are filled in by the script.
            'reviewsdone' => get_string('reviewsdone', 'mod_peerreview', (object) ['done' => '{done}', 'total' => '{total}']),
            'details' => get_string('mobiledetails', 'mod_peerreview'),
        ];
    }
}
