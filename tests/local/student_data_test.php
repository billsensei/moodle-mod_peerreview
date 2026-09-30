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
 * Tests for what students see, in particular reviewer anonymity.
 *
 * @package    mod_peerreview
 * @category   test
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_peerreview\local;

use mod_peerreview\tests\activity_trait;
use mod_peerreview\local\allocation\manager;
use mod_peerreview\output\student_view;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for what students see, in particular reviewer anonymity.
 *
 * @package    mod_peerreview
 * @category   test
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(student_data::class)]
#[CoversClass(student_view::class)]
final class student_data_test extends \advanced_testcase {
    use activity_trait;

    /**
     * The plugin renderer on a page with a context.
     *
     * @return \mod_peerreview\output\renderer
     */
    private function get_renderer(): \mod_peerreview\output\renderer {
        global $PAGE;
        $PAGE->set_url('/mod/peerreview/view.php', ['id' => $this->cm->id]);
        $PAGE->set_context($this->context);
        return $PAGE->get_renderer('mod_peerreview');
    }

    /**
     * Reviews to do: only mine, with status, sorted by name.
     */
    public function test_todo(): void {
        $this->resetAfterTest();
        $this->create_activity(4);
        $this->allocate(1, 3, manager::STATUS_DRAFT);
        $this->allocate(1, 2);
        $this->allocate(2, 1);
        $todo = (new student_data($this->peerreview, $this->manager))->get_todo($this->students[1]->id);
        $this->assertCount(2, $todo);
        $this->assertEquals($this->students[2]->id, $todo[0]->user->id, 'sorted by last name');
        $this->assertSame(manager::STATUS_NEW, $todo[0]->status);
        $this->assertSame(manager::STATUS_DRAFT, $todo[1]->status);
    }

    /**
     * Anonymous activity: the reviewer is not in the data, in the exported context, or in the rendered page.
     */
    public function test_anonymous_feedback_never_carries_the_reviewer(): void {
        $this->resetAfterTest();
        $this->create_activity(3, ['anonymous' => 1, 'feedbackreleased' => 1]);
        $this->allocate(1, 2, manager::STATUS_SUBMITTED, 80, '<p>Good pace.</p>');
        $this->allocate(3, 2, manager::STATUS_SUBMITTED, 60, '<p>Too quiet.</p>');
        $data = new student_data($this->peerreview, $this->manager);

        $received = $data->get_received($this->students[2]->id);
        $this->assertCount(2, $received);
        foreach ($received as $review) {
            $this->assertNull($review->reviewer);
        }

        $view = new student_view($this->peerreview, $this->context, $this->cm->id, [], $received, 70.0, 'open');
        $this->setUser($this->students[2]);
        $renderer = $this->get_renderer();
        $context = $view->export_for_template($renderer);
        $encoded = json_encode($context) . $renderer->render($view);
        foreach ([1, 3] as $index) {
            $reviewer = $this->students[$index];
            $this->assertStringNotContainsString($reviewer->firstname, $encoded);
            $this->assertStringNotContainsString($reviewer->lastname, $encoded);
            $this->assertStringNotContainsString('"' . $reviewer->id . '"', $encoded);
        }
        $this->assertStringContainsString(get_string('anonymousreviewer', 'mod_peerreview'), $encoded);
        $this->assertStringContainsString('Good pace.', $encoded);
    }

    /**
     * By-name activity: reviewers are shown. Self review is flagged and never anonymous.
     */
    public function test_by_name_and_self_review(): void {
        $this->resetAfterTest();
        $this->create_activity(3, ['anonymous' => 0, 'allowselfreview' => 1]);
        $this->allocate(1, 2, manager::STATUS_SUBMITTED, 80);
        $this->allocate(2, 2, manager::STATUS_SUBMITTED, 100);
        $received = (new student_data($this->peerreview, $this->manager))->get_received($this->students[2]->id);
        $byreviewer = [];
        foreach ($received as $review) {
            $byreviewer[$review->reviewer->id] = $review->isself;
        }
        $this->assertEquals([$this->students[1]->id => false, $this->students[2]->id => true], $byreviewer);
    }

    /**
     * Unsubmitted reviews are not shown as received, and the grade excludes self reviews.
     */
    public function test_received_only_submitted_and_grade(): void {
        $this->resetAfterTest();
        $this->create_activity(3, ['allowselfreview' => 1]);
        $this->allocate(1, 2, manager::STATUS_DRAFT, 10);
        $this->allocate(3, 2, manager::STATUS_SUBMITTED, 64);
        $this->allocate(2, 2, manager::STATUS_SUBMITTED, 100);
        $data = new student_data($this->peerreview, $this->manager);
        $this->assertCount(2, $data->get_received($this->students[2]->id), 'draft hidden, self review listed');
        $this->assertEquals(64.0, $data->get_grade($this->students[2]->id));
        $this->assertNull($data->get_grade($this->students[1]->id));
    }

    /**
     * Cards: actions depend on the window; a closed activity only lets submitted reviews be viewed.
     */
    public function test_cards_by_window(): void {
        $this->resetAfterTest();
        $this->create_activity(3);
        $this->allocate(1, 2, manager::STATUS_SUBMITTED, 70);
        $this->allocate(1, 3);
        $todo = (new student_data($this->peerreview, $this->manager))->get_todo($this->students[1]->id);
        $this->setUser($this->students[1]);
        $renderer = $this->get_renderer();

        $open = (new student_view($this->peerreview, $this->context, $this->cm->id, $todo, null, null, 'open'))
            ->export_for_template($renderer);
        $this->assertSame(
            [get_string('review2', 'mod_peerreview'), get_string('review0', 'mod_peerreview')],
            array_column($open->cards, 'actionlabel')
        );
        $this->assertFalse($open->showreceived);
        $this->assertSame(1, $open->done);

        $closed = (new student_view($this->peerreview, $this->context, $this->cm->id, $todo, null, null, 'closed'))
            ->export_for_template($renderer);
        $this->assertSame([get_string('viewreview', 'mod_peerreview'), ''], array_column($closed->cards, 'actionlabel'));
        $this->assertSame('', $closed->cards[1]['url'], 'not started and closed: no link');

        $notopen = (new student_view($this->peerreview, $this->context, $this->cm->id, $todo, null, null, 'notopen'))
            ->export_for_template($renderer);
        $this->assertSame(['', ''], array_column($notopen->cards, 'url'));
        $this->assertTrue($notopen->notopen);
    }
}
