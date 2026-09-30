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
 * Tests for grade aggregation.
 *
 * @package    mod_peerreview
 * @category   test
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_peerreview\local\grade;

use mod_peerreview\tests\activity_trait;
use mod_peerreview\local\allocation\manager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests for grade aggregation.
 *
 * @package    mod_peerreview
 * @category   test
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(aggregator::class)]
final class aggregator_test extends \advanced_testcase {
    use activity_trait;

    /**
     * Cases for the pure maths.
     *
     * @return array
     */
    public static function maths_provider(): array {
        return [
            'mean of one' => [[80.0], aggregator::MEAN, 80.0],
            'mean' => [[60.0, 70.0, 95.0], aggregator::MEAN, 75.0],
            'median odd' => [[10.0, 90.0, 40.0], aggregator::MEDIAN, 40.0],
            'median even' => [[10.0, 20.0, 30.0, 100.0], aggregator::MEDIAN, 25.0],
            'median ignores order' => [[100.0, 0.0], aggregator::MEDIAN, 50.0],
            'none is null, not zero (mean)' => [[], aggregator::MEAN, null],
            'none is null, not zero (median)' => [[], aggregator::MEDIAN, null],
        ];
    }

    /**
     * Mean and median.
     *
     * @param float[] $grades
     * @param int $method
     * @param float|null $expected
     */
    #[DataProvider('maths_provider')]
    public function test_aggregate(array $grades, int $method, ?float $expected): void {
        $this->assertEquals($expected, aggregator::aggregate($grades, $method));
    }

    /**
     * Only submitted, non-self reviews count, per reviewee.
     */
    public function test_only_submitted_non_self_reviews_count(): void {
        $this->resetAfterTest();
        $this->create_activity(4, ['allowselfreview' => 1]);
        $this->allocate(1, 2, manager::STATUS_SUBMITTED, 80, 'a');
        $this->allocate(3, 2, manager::STATUS_SUBMITTED, 60, 'b');
        $this->allocate(4, 2, manager::STATUS_DRAFT, 10);     // Draft: ignored.
        $this->allocate(2, 2, manager::STATUS_SUBMITTED, 100); // Self review: ignored.
        $this->allocate(1, 3);                                 // Not started.

        $received = (new aggregator($this->peerreview))->get_received_grades();
        $this->assertEqualsCanonicalizing([80.0, 60.0], $received[$this->students[2]->id]);
        $this->assertArrayNotHasKey($this->students[3]->id, $received);

        $grades = (new aggregator($this->peerreview))->get_grades([
            (int) $this->students[2]->id,
            (int) $this->students[3]->id,
        ]);
        $this->assertEquals(70.0, $grades[$this->students[2]->id]['grade']);
        $this->assertSame(2, $grades[$this->students[2]->id]['count']);
        $this->assertNull($grades[$this->students[3]->id]['grade'], 'no reviews means no grade, not zero');
    }

    /**
     * Median setting is honoured and a teacher override wins over the aggregate.
     */
    public function test_median_setting_and_override(): void {
        $this->resetAfterTest();
        global $DB;
        $this->create_activity(5, ['aggregation' => aggregator::MEDIAN]);
        $this->allocate(1, 5, manager::STATUS_SUBMITTED, 10);
        $this->allocate(2, 5, manager::STATUS_SUBMITTED, 90);
        $this->allocate(3, 5, manager::STATUS_SUBMITTED, 100);
        $aggregator = new aggregator($this->peerreview);
        $userid = (int) $this->students[5]->id;

        $this->assertEquals(90.0, $aggregator->get_grades([$userid])[$userid]['grade'], 'median, not mean 66.67');

        $DB->insert_record('peerreview_override', (object) [
            'peerreviewid' => $this->peerreview->id,
            'userid' => $userid,
            'grade' => 42.5,
            'note' => 'agreed with the class',
            'overriddenby' => $this->teacher->id,
            'timemodified' => time(),
        ]);
        $result = $aggregator->get_grades([$userid])[$userid];
        $this->assertEquals(42.5, $result['grade']);
        $this->assertTrue($result['overridden']);
        $this->assertSame(3, $result['count']);
    }

    /**
     * A student with only an override (and no reviews) still gets that grade.
     */
    public function test_override_without_reviews(): void {
        $this->resetAfterTest();
        global $DB;
        $this->create_activity(2);
        $DB->insert_record('peerreview_override', (object) [
            'peerreviewid' => $this->peerreview->id,
            'userid' => $this->students[1]->id,
            'grade' => 55,
            'overriddenby' => $this->teacher->id,
            'timemodified' => time(),
        ]);
        $grades = (new aggregator($this->peerreview))->get_grades();
        $this->assertEquals(55.0, $grades[$this->students[1]->id]['grade']);
    }
}
