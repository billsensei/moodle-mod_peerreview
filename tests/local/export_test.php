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
 * Tests for the review export.
 *
 * @package    mod_peerreview
 * @category   test
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_peerreview\local;

use mod_peerreview\tests\activity_trait;
use mod_peerreview\local\allocation\manager;
use mod_peerreview\local\review\service;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the review export.
 *
 * @package    mod_peerreview
 * @category   test
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(export::class)]
final class export_test extends \advanced_testcase {
    use activity_trait;

    /**
     * Without an advanced method there are only the basic columns.
     */
    public function test_basic_columns(): void {
        $this->resetAfterTest();
        $this->create_activity(2);
        $this->allocate(1, 2, manager::STATUS_SUBMITTED, 75.5, '<p>Nice <b>talk</b></p>');
        $this->allocate(2, 1);
        $export = new export($this->peerreview, $this->context);

        $this->assertSame(['reviewer', 'reviewee', 'status', 'grade', 'submitted', 'comment'], array_keys($export->get_columns()));
        $rows = iterator_to_array($export->get_rows(), false);
        $this->assertCount(2, $rows);
        $this->assertStringContainsString($this->students[1]->username, $rows[0]['reviewer']);
        $this->assertSame('75.50000', $rows[0]['grade']);
        $this->assertSame('Nice talk', $rows[0]['comment'], 'html removed');
        $this->assertSame(get_string('statusnew', 'mod_peerreview'), $rows[1]['status']);
        $this->assertSame('', $rows[1]['grade']);
    }

    /**
     * Rubric: one score and one remark column per criterion, filled from the submitted instance.
     */
    public function test_rubric_criteria_columns(): void {
        $this->resetAfterTest();
        $this->create_activity(2);
        $alloc = $this->allocate(1, 2);
        $this->setAdminUser();
        $rubricgen = $this->getDataGenerator()->get_plugin_generator('gradingform_rubric');
        $controller = $rubricgen->get_test_rubric($this->context, 'mod_peerreview', 'received');
        $this->setUser(0);
        (new service($this->peerreview, $this->context))->submit($alloc, (int) $this->students[1]->id, [
            'advancedgrading' => $rubricgen->get_test_form_data($controller, $alloc->id, 2, 'no typos', 1, 'one picture'),
            'feedback' => 'ok',
        ]);

        $export = new export($this->peerreview, $this->context);
        $columns = $export->get_columns();
        $this->assertContains('Spelling is important (Score)', $columns);
        $this->assertContains('Pictures (Remark)', $columns);
        $row = iterator_to_array($export->get_rows(), false)[0];
        $this->assertSame(array_keys($columns), array_merge(array_keys($columns), []), 'keys line up');
        $values = array_values(array_filter($row, static fn($key) => (bool) preg_match('/^c\d+_/', $key), ARRAY_FILTER_USE_KEY));
        $this->assertEqualsCanonicalizing(['2.00000', 'no typos', '1.00000', 'one picture'], $values);
        $this->assertEqualsWithDelta(75.0, (float) $row['grade'], 0.01);
    }

    /**
     * Marking guide: scores and remarks per criterion.
     */
    public function test_guide_criteria_columns(): void {
        $this->resetAfterTest();
        $this->create_activity(2);
        $alloc = $this->allocate(1, 2);
        $this->setAdminUser();
        $guidegen = $this->getDataGenerator()->get_plugin_generator('gradingform_guide');
        $controller = $guidegen->get_test_guide($this->context, 'mod_peerreview', 'received');
        $this->setUser(0);
        (new service($this->peerreview, $this->context))->submit($alloc, (int) $this->students[1]->id, [
            'advancedgrading' => $guidegen->get_submitted_form_data($controller, $alloc->id, [
                'Spelling mistakes' => ['score' => 20, 'remark' => 'a few'],
                'Pictures' => ['score' => 10, 'remark' => 'two'],
            ]),
        ]);
        $export = new export($this->peerreview, $this->context);
        $this->assertContains('Spelling mistakes (Score)', $export->get_columns());
        $row = iterator_to_array($export->get_rows(), false)[0];
        $values = array_values(array_filter($row, static fn($key) => (bool) preg_match('/^c\d+_/', $key), ARRAY_FILTER_USE_KEY));
        $this->assertEqualsCanonicalizing(['20.00000', 'a few', '10.00000', 'two'], $values);
    }
}
