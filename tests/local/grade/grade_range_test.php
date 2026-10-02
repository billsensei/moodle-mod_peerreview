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

namespace mod_peerreview\local\grade;

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the points-or-scale grade range.
 *
 * @package    mod_peerreview
 * @category   test
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(grade_range::class)]
final class grade_range_test extends \advanced_testcase {
    /**
     * Create a four item scale and return its range.
     *
     * @return grade_range
     */
    private function scale_range(): grade_range {
        $scale = $this->getDataGenerator()->create_scale(['scale' => 'Poor, Fair, Good, Excellent']);
        return new grade_range(-(int) $scale->id);
    }

    /**
     * Points: 0 to the maximum, two decimals, values outside are refused.
     */
    public function test_points(): void {
        $this->resetAfterTest();
        $range = new grade_range(80);
        $this->assertFalse($range->is_scale());
        $this->assertSame(0, $range->get_scale_id());
        $this->assertSame(0, $range->min());
        $this->assertSame(80, $range->max());
        $this->assertTrue($range->accepts(0));
        $this->assertTrue($range->accepts('80'));
        $this->assertTrue($range->accepts(42.5));
        $this->assertFalse($range->accepts(-1));
        $this->assertFalse($range->accepts(80.5));
        $this->assertFalse($range->accepts('abc'));
        $this->assertSame('42.50', $range->format(42.5));
        $this->assertSame('42.5 / 80', $range->format_with_max(42.5));
        $this->assertSame(42.5, $range->for_gradebook(42.5));
        $this->assertNull($range->for_gradebook(null));
        $this->assertSame(['gradetype' => GRADE_TYPE_VALUE, 'grademax' => 80, 'grademin' => 0], $range->get_item_params());
    }

    /**
     * Scale: positions 1 to the number of items, whole items only, shown by name.
     */
    public function test_scale(): void {
        $this->resetAfterTest();
        $range = $this->scale_range();
        $this->assertTrue($range->is_scale());
        $this->assertSame(1, $range->min());
        $this->assertSame(4, $range->max());
        $this->assertSame([1 => 'Poor', 2 => 'Fair', 3 => 'Good', 4 => 'Excellent'], $range->get_items());
        $this->assertSame('Poor', $range->get_menu()[1]);
        $this->assertTrue($range->accepts(1));
        $this->assertTrue($range->accepts('4'));
        $this->assertFalse($range->accepts(0), 'a scale starts at 1');
        $this->assertFalse($range->accepts(5));
        $this->assertFalse($range->accepts(2.5), 'whole items only');
        $this->assertSame('Good', $range->format(3.0));
        $this->assertSame('Good', $range->format(2.6), 'nearest item');
        $this->assertSame('Fair', $range->format(2.4));
        $this->assertSame('Excellent', $range->format(9.0), 'clamped');
        $this->assertSame('Poor', $range->format_with_max(0.2));
        $this->assertSame(3.0, $range->for_gradebook(2.6));
        $this->assertSame(1.0, $range->for_gradebook(0.0));
        $this->assertSame(['gradetype' => GRADE_TYPE_SCALE, 'scaleid' => $range->get_scale_id()], $range->get_item_params());
    }

    /**
     * A stored grade survives a maximum change between points, but not a change of kind or of scale size.
     */
    public function test_fits(): void {
        $this->resetAfterTest();
        $scale = $this->scale_range();
        $other = $this->scale_range();
        $short = new grade_range(-(int) $this->getDataGenerator()->create_scale(['scale' => 'No, Yes'])->id);
        $this->assertTrue((new grade_range(100))->fits(new grade_range(50)));
        $this->assertFalse((new grade_range(100))->fits($scale));
        $this->assertFalse($scale->fits(new grade_range(100)));
        $this->assertTrue($scale->fits($other), 'same number of items');
        $this->assertFalse($scale->fits($short));
    }
}
