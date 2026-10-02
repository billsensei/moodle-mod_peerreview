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
 * Tests for the criterion statistics.
 *
 * @package    mod_peerreview
 * @category   test
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_peerreview\local;

use mod_peerreview\tests\activity_trait;
use mod_peerreview\local\review\service;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the criterion statistics.
 *
 * @package    mod_peerreview
 * @category   test
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(criterion_stats::class)]
final class criterion_stats_test extends \advanced_testcase {
    use activity_trait;

    /**
     * Submit a marking guide review.
     *
     * @param int $reviewer Student index.
     * @param int $reviewee Student index.
     * @param \gradingform_guide_controller $controller Guide of the activity.
     * @param int $spelling Score on "Spelling mistakes".
     * @param int $pictures Score on "Pictures".
     */
    private function submit_guide(int $reviewer, int $reviewee, $controller, int $spelling, int $pictures): void {
        $alloc = $this->allocate($reviewer, $reviewee);
        $guidegen = $this->getDataGenerator()->get_plugin_generator('gradingform_guide');
        $this->setUser(0);
        (new service($this->peerreview, $this->context))->submit($alloc, (int) $this->students[$reviewer]->id, [
            'advancedgrading' => $guidegen->get_submitted_form_data($controller, $alloc->id, [
                'Spelling mistakes' => ['score' => $spelling, 'remark' => ''],
                'Pictures' => ['score' => $pictures, 'remark' => ''],
            ]),
        ]);
    }

    /**
     * Without a rubric or marking guide there is nothing to average.
     */
    public function test_no_advanced_method(): void {
        $this->resetAfterTest();
        $this->create_activity(2);
        $this->allocate(1, 2, \mod_peerreview\local\allocation\manager::STATUS_SUBMITTED, 75);
        $this->assertSame([], (new criterion_stats($this->peerreview, $this->context))->get_statistics());
    }

    /**
     * Marking guide: the average and the maximum per criterion, over submitted reviews only.
     */
    public function test_guide_averages(): void {
        $this->resetAfterTest();
        $this->create_activity(3);
        $this->setAdminUser();
        $controller = $this->getDataGenerator()->get_plugin_generator('gradingform_guide')
            ->get_test_guide($this->context, 'mod_peerreview', 'received');
        $this->submit_guide(1, 2, $controller, 20, 10);
        $this->submit_guide(3, 2, $controller, 10, 0);
        $this->allocate(2, 1); // Not started: ignored.

        $stats = (new criterion_stats($this->peerreview, $this->context))->get_statistics();
        $this->assertCount(2, $stats);
        $byname = array_column($stats, null, 'name');
        $this->assertSame(2, $byname['Spelling mistakes']['count']);
        $this->assertEqualsWithDelta(15.0, $byname['Spelling mistakes']['average'], 0.001);
        $this->assertEqualsWithDelta(5.0, $byname['Pictures']['average'], 0.001);
        $this->assertEqualsWithDelta(25.0, $byname['Spelling mistakes']['max'], 0.001);
        $this->assertEqualsWithDelta(15.0, $byname['Pictures']['max'], 0.001);
    }

    /**
     * Before any review is submitted every criterion is listed with no average.
     */
    public function test_no_submitted_reviews(): void {
        $this->resetAfterTest();
        $this->create_activity(2);
        $this->setAdminUser();
        $this->getDataGenerator()->get_plugin_generator('gradingform_guide')
            ->get_test_guide($this->context, 'mod_peerreview', 'received');
        $this->allocate(1, 2);

        $stats = (new criterion_stats($this->peerreview, $this->context))->get_statistics();
        $this->assertCount(2, $stats);
        foreach ($stats as $stat) {
            $this->assertSame(0, $stat['count']);
            $this->assertNull($stat['average']);
        }
    }

    /**
     * Rubric: the score of the chosen level, and the highest level as the maximum.
     */
    public function test_rubric_averages(): void {
        $this->resetAfterTest();
        $this->create_activity(3);
        $alloc1 = $this->allocate(1, 2);
        $alloc2 = $this->allocate(3, 2);
        $this->setAdminUser();
        $rubricgen = $this->getDataGenerator()->get_plugin_generator('gradingform_rubric');
        $controller = $rubricgen->get_test_rubric($this->context, 'mod_peerreview', 'received');
        $this->setUser(0);
        $service = new service($this->peerreview, $this->context);
        $service->submit($alloc1, (int) $this->students[1]->id, [
            'advancedgrading' => $rubricgen->get_test_form_data($controller, $alloc1->id, 2, 'a', 1, 'b'),
            'feedback' => 'ok',
        ]);
        $service->submit($alloc2, (int) $this->students[3]->id, [
            'advancedgrading' => $rubricgen->get_test_form_data($controller, $alloc2->id, 0, 'c', 1, 'd'),
            'feedback' => 'ok',
        ]);

        $stats = (new criterion_stats($this->peerreview, $this->context))->get_statistics();
        $byname = array_column($stats, null, 'name');
        $this->assertEqualsWithDelta(1.0, $byname['Spelling is important']['average'], 0.001);
        $this->assertEqualsWithDelta(1.0, $byname['Pictures']['average'], 0.001);
        $this->assertSame(2, $byname['Pictures']['count']);
        $this->assertGreaterThan(0, $byname['Pictures']['max']);
    }

    /**
     * Only reviews of visible reviewees are counted.
     */
    public function test_visible_users_restrict_reviews(): void {
        $this->resetAfterTest();
        $this->create_activity(3);
        $this->setAdminUser();
        $controller = $this->getDataGenerator()->get_plugin_generator('gradingform_guide')
            ->get_test_guide($this->context, 'mod_peerreview', 'received');
        $this->submit_guide(1, 2, $controller, 20, 10);
        $this->submit_guide(1, 3, $controller, 0, 0);

        $visible = [(int) $this->students[2]->id];
        $stats = (new criterion_stats($this->peerreview, $this->context, $visible))->get_statistics();
        $byname = array_column($stats, null, 'name');
        $this->assertEqualsWithDelta(20.0, $byname['Spelling mistakes']['average'], 0.001);
        $this->assertSame(1, $byname['Spelling mistakes']['count']);

        $stats = (new criterion_stats($this->peerreview, $this->context, []))->get_statistics();
        $this->assertSame(0, $stats[0]['count']);
    }

    /**
     * Self-reviews are left out of the class averages, and the comparison puts the student's own scores next to their
     * peers' average.
     */
    public function test_self_review_comparison(): void {
        $this->resetAfterTest();
        $this->create_activity(3, ['allowselfreview' => 1]);
        $this->setAdminUser();
        $controller = $this->getDataGenerator()->get_plugin_generator('gradingform_guide')
            ->get_test_guide($this->context, 'mod_peerreview', 'received');
        $this->submit_guide(1, 2, $controller, 20, 10);
        $this->submit_guide(3, 2, $controller, 10, 0);
        $this->submit_guide(2, 2, $controller, 25, 15); // The self-review.
        $this->submit_guide(2, 1, $controller, 5, 5);

        $stats = (new criterion_stats($this->peerreview, $this->context))->get_statistics();
        $byname = array_column($stats, null, 'name');
        $this->assertSame(3, $byname['Spelling mistakes']['count'], 'the self-review is not counted');
        $this->assertEqualsWithDelta((20 + 10 + 5) / 3, $byname['Spelling mistakes']['average'], 0.001);

        $comparison = (new criterion_stats($this->peerreview, $this->context))->get_comparison((int) $this->students[2]->id);
        $byname = array_column($comparison, null, 'name');
        $this->assertCount(2, $comparison);
        $this->assertEqualsWithDelta(25.0, $byname['Spelling mistakes']['self'], 0.001);
        $this->assertEqualsWithDelta(15.0, $byname['Spelling mistakes']['peers'], 0.001);
        $this->assertEqualsWithDelta(15.0, $byname['Pictures']['self'], 0.001);
        $this->assertEqualsWithDelta(5.0, $byname['Pictures']['peers'], 0.001);
        $this->assertSame(2, $byname['Pictures']['count']);

        // No self-review, or no peer review yet: nothing to compare.
        $stats = new criterion_stats($this->peerreview, $this->context);
        $this->assertSame([], $stats->get_comparison((int) $this->students[3]->id));
        $this->assertSame([], $stats->get_comparison((int) $this->students[1]->id));
    }
}
