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

namespace mod_peerreview\external;

use core_external\external_api;
use mod_peerreview\local\allocation\manager;
use mod_peerreview\output\mobile;
use mod_peerreview\tests\activity_trait;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the Moodle app support: the three web services and the data of the app page.
 *
 * @package    mod_peerreview
 * @category   test
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(get_review::class)]
#[CoversClass(save_review::class)]
#[CoversClass(set_feedback_release::class)]
#[CoversClass(view_peerreview::class)]
#[CoversClass(mobile::class)]
final class mobile_test extends \advanced_testcase {
    use activity_trait;

    /**
     * A review as its reviewer gets it: points form, empty so far.
     */
    public function test_get_review_points(): void {
        $this->resetAfterTest();
        $this->create_activity(3, ['grade' => 50]);
        $alloc = $this->allocate(1, 2);
        $this->setUser($this->students[1]);

        $result = external_api::clean_returnvalue(
            get_review::execute_returns(),
            get_review::execute($this->cm->id, $alloc->id)
        );
        $this->assertSame(fullname($this->students[2]), $result['reviewee']);
        $this->assertFalse($result['isscale']);
        $this->assertSame(50, $result['max']);
        $this->assertSame([], $result['items']);
        $this->assertSame('', $result['score']);
        $this->assertTrue($result['canedit']);
        $this->assertFalse($result['submitted']);
    }

    /**
     * A scale comes back as numbered items, and a stored comment written in the browser comes back as plain text.
     */
    public function test_get_review_scale_and_html_comment(): void {
        global $DB;
        $this->resetAfterTest();
        $scaleid = (int) $this->getDataGenerator()->create_scale(['scale' => 'Poor, Fair, Good, Excellent'])->id;
        $this->create_activity(3, ['grade' => -$scaleid]);
        $alloc = $this->allocate(1, 2, manager::STATUS_SUBMITTED, 3, '<p>Clear <strong>voice</strong></p>');
        $DB->set_field('peerreview_alloc', 'feedbackformat', FORMAT_HTML, ['id' => $alloc->id]);
        $this->setUser($this->students[1]);

        $result = external_api::clean_returnvalue(
            get_review::execute_returns(),
            get_review::execute($this->cm->id, $alloc->id)
        );
        $this->assertTrue($result['isscale']);
        $this->assertSame(4, $result['max']);
        $this->assertSame([1, 2, 3, 4], array_column($result['items'], 'value'));
        $this->assertSame('Good', $result['items'][2]['label']);
        $this->assertSame('3', $result['score']);
        $this->assertTrue($result['submitted']);
        $this->assertStringContainsString('Clear', $result['feedback']);
        $this->assertStringNotContainsString('<p>', $result['feedback']);
    }

    /**
     * Only the reviewer can read the review, even a teacher or the person reviewed cannot.
     */
    public function test_get_review_only_for_the_reviewer(): void {
        $this->resetAfterTest();
        $this->create_activity(3);
        $alloc = $this->allocate(1, 2);
        foreach ([$this->students[2], $this->students[3], $this->teacher] as $user) {
            $this->setUser($user);
            try {
                get_review::execute($this->cm->id, $alloc->id);
                $this->fail('Expected an exception for ' . $user->id);
            } catch (\moodle_exception $e) {
                $this->assertContains($e->errorcode, ['errornotyourreview', 'nopermissions']);
            }
        }
    }

    /**
     * A marking guide as its reviewer gets it, saved as a draft, refused while incomplete, then submitted.
     */
    public function test_marking_guide_review(): void {
        global $DB;

        $this->resetAfterTest();
        $this->create_activity(3);
        $alloc = $this->allocate(1, 2);
        $this->setAdminUser();
        $controller = $this->getDataGenerator()->get_plugin_generator('gradingform_guide')
            ->get_test_guide($this->context, 'mod_peerreview', 'received');
        // Frequently used comments, as the guide editor stores them.
        $definitionid = $controller->get_definition()->id;
        foreach (['Good', 'Check the spelling'] as $order => $text) {
            $DB->insert_record('gradingform_guide_comments', [
                'definitionid' => $definitionid,
                'sortorder' => $order + 1,
                'description' => $text,
                'descriptionformat' => FORMAT_HTML,
            ]);
        }
        $this->setUser($this->students[1]);

        $result = external_api::clean_returnvalue(
            get_review::execute_returns(),
            get_review::execute($this->cm->id, $alloc->id)
        );
        $this->assertSame('guide', $result['method']);
        $this->assertTrue($result['candraft']);
        $this->assertCount(2, $result['criteria']);
        $criteria = $result['criteria'];
        $this->assertStringContainsString('Spelling mistakes', $criteria[0]['description']);
        $this->assertStringContainsString('Deduct 5 points', $criteria[0]['description']);
        $this->assertEqualsWithDelta(25.0, $criteria[0]['maxscore'], 0.001);
        $this->assertSame('', $criteria[0]['score']);
        $this->assertSame(['Good', 'Check the spelling'], $result['comments']);

        // A draft with one score; the others stay empty.
        save_review::execute($this->cm->id, $alloc->id, '', '', [
            ['id' => $criteria[0]['id'], 'score' => '20', 'remark' => 'two slips'],
            ['id' => $criteria[1]['id'], 'score' => ''],
        ], true);
        $this->assertSame(manager::STATUS_DRAFT, (int) $this->reload($alloc)->status);
        $draft = get_review::execute($this->cm->id, $alloc->id)['criteria'];
        $this->assertSame('20', $draft[0]['score']);
        $this->assertSame('two slips', $draft[0]['remark']);
        $this->assertSame('', $draft[1]['score']);

        // Missing and too high scores are refused.
        $all = fn(string $second) => [
            ['id' => $criteria[0]['id'], 'score' => '20'],
            ['id' => $criteria[1]['id'], 'score' => $second],
        ];
        foreach ([$all(''), $all('99')] as $incomplete) {
            try {
                save_review::execute($this->cm->id, $alloc->id, '', '', $incomplete);
                $this->fail('An incomplete or too high marking guide must be refused');
            } catch (\moodle_exception $e) {
                $this->assertSame('errorreviewincomplete', $e->errorcode);
            }
        }
        $this->assertSame(manager::STATUS_DRAFT, (int) $this->reload($alloc)->status);

        save_review::execute($this->cm->id, $alloc->id, '', 'Fine', $all('10'));
        $stored = $this->reload($alloc);
        $this->assertSame(manager::STATUS_SUBMITTED, (int) $stored->status);
        $this->assertGreaterThan(0, (float) $stored->grade);
        $this->assertSame('10', get_review::execute($this->cm->id, $alloc->id)['criteria'][1]['score']);
    }

    /**
     * A rubric as its reviewer gets it: criteria with levels, nothing chosen so far, a draft can be saved.
     */
    public function test_get_review_rubric(): void {
        $this->resetAfterTest();
        $this->create_activity(3);
        $alloc = $this->allocate(1, 2);
        $this->setAdminUser();
        $this->getDataGenerator()->get_plugin_generator('gradingform_rubric')
            ->get_test_rubric($this->context, 'mod_peerreview', 'received');
        $this->setUser($this->students[1]);

        $result = external_api::clean_returnvalue(
            get_review::execute_returns(),
            get_review::execute($this->cm->id, $alloc->id)
        );
        $this->assertSame('rubric', $result['method']);
        $this->assertTrue($result['canedit']);
        $this->assertTrue($result['candraft']);
        $this->assertCount(2, $result['criteria']);
        $this->assertSame('Spelling is important', $result['criteria'][0]['description']);
        $this->assertCount(3, $result['criteria'][0]['levels']);
        $this->assertSame('No mistakes', $result['criteria'][0]['levels'][2]['definition']);
        $this->assertSame(0, $result['criteria'][0]['levelid']);
    }

    /**
     * A rubric review saved as a draft is returned as it was left, then submitted; an incomplete one is refused.
     */
    public function test_save_rubric_review(): void {
        $this->resetAfterTest();
        $this->create_activity(3);
        $alloc = $this->allocate(1, 2);
        $this->setAdminUser();
        $this->getDataGenerator()->get_plugin_generator('gradingform_rubric')
            ->get_test_rubric($this->context, 'mod_peerreview', 'received');
        $this->setUser($this->students[1]);
        $first = get_review::execute($this->cm->id, $alloc->id)['criteria'];

        // A draft with only the first criterion chosen.
        save_review::execute($this->cm->id, $alloc->id, '', 'Half way', [
            ['id' => $first[0]['id'], 'levelid' => $first[0]['levels'][2]['id'], 'remark' => 'clean'],
            ['id' => $first[1]['id'], 'levelid' => 0, 'remark' => ''],
        ], true);
        $this->assertSame(manager::STATUS_DRAFT, (int) $this->reload($alloc)->status);
        $draft = get_review::execute($this->cm->id, $alloc->id);
        $this->assertSame($first[0]['levels'][2]['id'], $draft['criteria'][0]['levelid']);
        $this->assertSame('clean', $draft['criteria'][0]['remark']);
        $this->assertSame(0, $draft['criteria'][1]['levelid']);

        // Submitting with a criterion left open is refused and nothing is submitted.
        try {
            save_review::execute($this->cm->id, $alloc->id, '', '', [
                ['id' => $first[0]['id'], 'levelid' => $first[0]['levels'][2]['id']],
                ['id' => $first[1]['id'], 'levelid' => 0],
            ]);
            $this->fail('An incomplete rubric must be refused');
        } catch (\moodle_exception $e) {
            $this->assertSame('errorreviewincomplete', $e->errorcode);
        }
        $this->assertSame(manager::STATUS_DRAFT, (int) $this->reload($alloc)->status);

        // Complete: both criteria at the top level gives the full grade.
        save_review::execute($this->cm->id, $alloc->id, '', 'Well done', [
            ['id' => $first[0]['id'], 'levelid' => $first[0]['levels'][2]['id']],
            ['id' => $first[1]['id'], 'levelid' => $first[1]['levels'][2]['id'], 'remark' => 'three'],
        ]);
        $stored = $this->reload($alloc);
        $this->assertSame(manager::STATUS_SUBMITTED, (int) $stored->status);
        $this->assertEqualsWithDelta(100.0, (float) $stored->grade, 0.01);
        $again = get_review::execute($this->cm->id, $alloc->id);
        $this->assertTrue($again['submitted']);
        $this->assertFalse($again['candraft']);
        $this->assertSame($first[1]['levels'][2]['id'], $again['criteria'][1]['levelid']);
    }

    /**
     * Submitting stores the score and the comment, marks the review submitted and triggers the event.
     */
    public function test_save_review(): void {
        $this->resetAfterTest();
        $this->create_activity(3, ['grade' => 50]);
        $alloc = $this->allocate(1, 2);
        $this->setUser($this->students[1]);
        $sink = $this->redirectEvents();

        $result = external_api::clean_returnvalue(
            save_review::execute_returns(),
            save_review::execute($this->cm->id, $alloc->id, '42.5', 'Good pace')
        );
        $this->assertTrue($result['status']);
        $stored = $this->reload($alloc);
        $this->assertSame(manager::STATUS_SUBMITTED, (int) $stored->status);
        $this->assertEquals(42.5, $stored->grade);
        $this->assertSame('Good pace', $stored->feedback);
        $this->assertEquals(FORMAT_PLAIN, $stored->feedbackformat);
        $this->assertNotEmpty($stored->timesubmitted);
        $events = array_filter($sink->get_events(), fn($e) => $e instanceof \mod_peerreview\event\review_submitted);
        $this->assertCount(1, $events);

        // A second save is an edit.
        save_review::execute($this->cm->id, $alloc->id, '44');
        $this->assertEquals(44, $this->reload($alloc)->grade);
    }

    /**
     * Scores outside the range, someone else's review, and a closed activity are refused and change nothing.
     */
    public function test_save_review_refusals(): void {
        $this->resetAfterTest();
        $this->create_activity(3, ['grade' => 50]);
        $alloc = $this->allocate(1, 2);

        $this->setUser($this->students[1]);
        foreach (['51', '-1', 'abc', ''] as $score) {
            try {
                save_review::execute($this->cm->id, $alloc->id, $score);
                $this->fail("Score '$score' should be refused");
            } catch (\moodle_exception $e) {
                $this->assertNotSame('', $e->errorcode);
            }
        }

        $this->setUser($this->students[3]);
        try {
            save_review::execute($this->cm->id, $alloc->id, '10');
            $this->fail('Someone else cannot submit');
        } catch (\moodle_exception $e) {
            $this->assertSame('errornotyourreview', $e->errorcode);
        }

        $this->setUser($this->students[1]);
        $this->set_window(time() - DAYSECS, time() - HOURSECS);
        try {
            save_review::execute($this->cm->id, $alloc->id, '10');
            $this->fail('A closed activity accepts no reviews');
        } catch (\moodle_exception $e) {
            $this->assertSame('errorclosed', $e->errorcode);
        }
        $this->assertSame(manager::STATUS_NEW, (int) $this->reload($alloc)->status);
        $result = get_review::execute($this->cm->id, $alloc->id);
        $this->assertFalse($result['canedit'], 'a closed activity is shown read-only');
        $this->assertNotSame('', $result['notice']);
    }

    /**
     * The view is logged.
     */
    public function test_view_peerreview(): void {
        $this->resetAfterTest();
        $this->create_activity(2);
        $this->setUser($this->students[1]);
        $sink = $this->redirectEvents();

        $result = external_api::clean_returnvalue(
            view_peerreview::execute_returns(),
            view_peerreview::execute($this->cm->id)
        );
        $this->assertTrue($result['status']);
        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(\mod_peerreview\event\course_module_viewed::class, $events[0]);
    }

    /**
     * The app page of a student: reviews to do with their status, and nothing received until feedback is released.
     */
    public function test_page_data_for_a_student(): void {
        $this->resetAfterTest();
        $this->create_activity(3, ['grade' => 50, 'anonymous' => 1]);
        $this->allocate(1, 2, manager::STATUS_SUBMITTED, 40, 'Nice');
        $this->allocate(1, 3);
        $this->allocate(3, 1, manager::STATUS_SUBMITTED, 30, 'Needs work');
        $this->setUser($this->students[1]);

        $state = $this->state($this->students[1]);
        $this->assertTrue($state['canreview']);
        $this->assertSame('open', $state['window']);
        $this->assertFalse($state['advanced']);
        $this->assertSame([fullname($this->students[2]), fullname($this->students[3])], array_column($state['todo'], 'name'));
        $this->assertSame([true, false], array_column($state['todo'], 'submitted'));
        $this->assertFalse($state['showreceived']);
        $this->assertSame([], $state['received']);
        $this->assertStringNotContainsString('Needs work', json_encode($state), 'not released yet');

        $this->release_feedback();
        $state = $this->state($this->students[1]);
        $this->assertTrue($state['showreceived']);
        $this->assertCount(1, $state['received']);
        $this->assertSame('Needs work', trim(strip_tags($state['received'][0]['comment'])));
        $this->assertSame(get_string('anonymousreviewer', 'mod_peerreview'), $state['received'][0]['who']);
        $this->assertStringNotContainsString(fullname($this->students[3]), json_encode($state['received']));
        $this->assertNotSame('', $state['grade']);
    }

    /**
     * A teacher gets the class progress, the release state and the browser links, and no review lists of their own.
     */
    public function test_page_data_for_a_teacher(): void {
        $this->resetAfterTest();
        $this->create_activity(3, ['grade' => 50]);
        $this->allocate(1, 2, manager::STATUS_SUBMITTED, 40);
        $this->allocate(1, 3);
        $this->setUser($this->teacher);

        $state = $this->state($this->teacher);
        $this->assertFalse($state['canreview']);
        $this->assertSame([], $state['todo']);
        $teacher = $state['teacher'];
        $this->assertSame(3, $teacher['nstudents']);
        $this->assertSame(get_string('reviewsdone', 'mod_peerreview', (object) ['done' => 1, 'total' => 2]), $teacher['progress']);
        $this->assertTrue($teacher['canrelease']);
        $this->assertFalse($teacher['released']);
        $this->assertSame(
            [fullname($this->students[1]), fullname($this->students[2]), fullname($this->students[3])],
            array_column($teacher['students'], 'name')
        );
        $rows = array_column($teacher['students'], null, 'name');
        $first = $rows[fullname($this->students[1])];
        $this->assertSame('1 / 2', $first['given']);
        $this->assertTrue($first['behind']);
        $this->assertSame('50%', $first['participation']);
        $second = $rows[fullname($this->students[2])];
        $this->assertSame('0 / 0', $second['given']);
        $this->assertFalse($second['behind'], 'nothing assigned to give');
        $this->assertSame('1 / 1', $second['received']);
        $this->assertNotSame('', $second['grade']);
        $this->assertStringContainsString('/mod/peerreview/report.php?id=' . $this->cm->id, $teacher['reporturl']);
        $this->assertStringContainsString('/mod/peerreview/allocate.php?id=' . $this->cm->id, $teacher['allocateurl']);

        // A student has no teacher part, and the figures of the class are not sent to them.
        $state = $this->state($this->students[1]);
        $this->assertNull($state['teacher']);
        $this->assertStringNotContainsString('"behind"', json_encode($state), 'no class figures in a student page');
    }

    /**
     * A teacher limited to their own group sees only that group in the app, as on the report page.
     */
    public function test_page_data_for_a_group_limited_teacher(): void {
        $this->resetAfterTest();
        $this->create_activity(4, [], SEPARATEGROUPS);
        $generator = $this->getDataGenerator();
        $mine = $generator->create_group(['courseid' => $this->course->id]);
        $other = $generator->create_group(['courseid' => $this->course->id]);
        $generator->create_group_member(['groupid' => $mine->id, 'userid' => $this->students[1]->id]);
        $generator->create_group_member(['groupid' => $other->id, 'userid' => $this->students[2]->id]);
        $limited = $this->create_group_limited_teacher();
        $generator->create_group_member(['groupid' => $mine->id, 'userid' => $limited->id]);
        $this->setUser($limited);

        $teacher = $this->state($limited)['teacher'];
        $names = array_column($teacher['students'], 'name');
        $this->assertContains(fullname($this->students[1]), $names);
        $this->assertNotContains(fullname($this->students[2]), $names);
        $this->assertFalse($teacher['canrelease'], 'this role cannot release feedback');
    }

    /**
     * The release switch of the app is the same web service as the website's: it changes the state and logs the event.
     */
    public function test_release_from_the_app(): void {
        global $DB;
        $this->resetAfterTest();
        $this->create_activity(2);
        $this->setUser($this->teacher);
        $sink = $this->redirectEvents();

        $result = external_api::clean_returnvalue(
            set_feedback_release::execute_returns(),
            set_feedback_release::execute($this->cm->id, true)
        );
        $this->assertTrue($result['released']);
        $this->assertSame(1, (int) $DB->get_field('peerreview', 'feedbackreleased', ['id' => $this->peerreview->id]));
        $this->assertTrue($this->state($this->teacher)['teacher']['released']);
        $this->assertCount(1, array_filter($sink->get_events(), fn($e) => $e instanceof \mod_peerreview\event\feedback_released));

        $this->setUser($this->students[1]);
        $this->expectException(\required_capability_exception::class);
        set_feedback_release::execute($this->cm->id, false);
    }

    /**
     * The window state and the browser links of an activity with a marking guide.
     */
    public function test_page_data_window_and_advanced(): void {
        $this->resetAfterTest();
        $this->create_activity(2);
        $alloc = $this->allocate(1, 2);
        $this->setAdminUser();
        $this->getDataGenerator()->get_plugin_generator('gradingform_guide')
            ->get_test_guide($this->context, 'mod_peerreview', 'received');

        $this->set_window(time() + DAYSECS, time() + 2 * DAYSECS);
        $state = $this->state($this->students[1]);
        $this->assertSame('notopen', $state['window']);
        $this->assertSame('', $state['todo'][0]['action'], 'no button before it opens');

        $this->set_window(0, 0);
        $state = $this->state($this->students[1]);
        $this->assertFalse($state['advanced'], 'a marking guide is handled in the app now');
        $this->assertSame('open', $state['window']);
        $this->assertStringContainsString("review.php?id={$this->cm->id}&alloc={$alloc->id}", $state['todo'][0]['url']);
    }

    /**
     * The app gets the page through the method named in db/mobile.php, and the files it reads exist.
     */
    public function test_course_view_and_declaration(): void {
        global $CFG;
        $this->resetAfterTest();
        $this->create_activity(2);
        $this->setUser($this->students[1]);

        $result = mobile::mobile_course_view(['cmid' => $this->cm->id, 'courseid' => $this->course->id]);
        $this->assertStringContainsString('state.todo', $result['templates'][0]['html']);
        $this->assertStringContainsString('mod_peerreview_save_review', $result['javascript']);
        $data = json_decode($result['otherdata']['data'], true);
        $this->assertSame((int) $this->cm->id, $data['cmid']);

        // The way the app really asks: through tool_mobile, with string arguments.
        $viaapp = external_api::clean_returnvalue(
            \tool_mobile\external::get_content_returns(),
            \tool_mobile\external::get_content('mod_peerreview', 'mobile_course_view', [
                ['name' => 'cmid', 'value' => (string) $this->cm->id],
                ['name' => 'courseid', 'value' => (string) $this->course->id],
            ])
        );
        $this->assertSame('data', $viaapp['otherdata'][0]['name']);
        $this->assertSame($data, json_decode($viaapp['otherdata'][0]['value'], true));

        $addons = [];
        require($CFG->dirroot . '/mod/peerreview/db/mobile.php');
        $handler = $addons['mod_peerreview']['handlers']['peerreview'];
        $this->assertTrue(method_exists(mobile::class, $handler['method']));

        $functions = [];
        require($CFG->dirroot . '/mod/peerreview/db/services.php');
        foreach (['view_peerreview', 'get_review', 'save_review'] as $name) {
            $this->assertContains(MOODLE_OFFICIAL_MOBILE_SERVICE, $functions["mod_peerreview_$name"]['services']);
        }
        $this->assertContains(MOODLE_OFFICIAL_MOBILE_SERVICE, $functions['mod_peerreview_set_feedback_release']['services']);
        $this->assertArrayNotHasKey('services', $functions['mod_peerreview_get_progress'], 'the page builds the list');
    }

    /**
     * The page data for a user (who is logged in for the call, as capabilities are checked for the current user).
     *
     * @param \stdClass $user The viewer.
     * @return array
     */
    private function state(\stdClass $user): array {
        global $DB;
        $this->setUser($user);
        $peerreview = $DB->get_record('peerreview', ['id' => $this->peerreview->id], '*', MUST_EXIST);
        $cm = get_fast_modinfo($this->course->id, $user->id)->get_cm($this->cm->id);
        return mobile::build_state($peerreview, $cm, $this->context, (int) $user->id);
    }

    /**
     * Set the open and close time.
     *
     * @param int $open Open time, 0 for none.
     * @param int $close Close time, 0 for none.
     */
    private function set_window(int $open, int $close): void {
        global $DB;
        $DB->update_record('peerreview', (object) ['id' => $this->peerreview->id, 'timeopen' => $open, 'timeclose' => $close]);
    }

    /**
     * Release the feedback.
     */
    private function release_feedback(): void {
        global $DB;
        $DB->set_field('peerreview', 'feedbackreleased', 1, ['id' => $this->peerreview->id]);
    }

    /**
     * Reload an allocation.
     *
     * @param \stdClass $alloc Allocation.
     * @return \stdClass
     */
    private function reload(\stdClass $alloc): \stdClass {
        global $DB;
        return $DB->get_record('peerreview_alloc', ['id' => $alloc->id], '*', MUST_EXIST);
    }
}
