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
 * The range of the received grade: points from 0 to a maximum, or the items of a scale.
 *
 * Modelled on how mod/assign treats its grade field (a negative value is minus the scale id) and on make_grades_menu()
 * in lib/gradelib.php.
 *
 * @package    mod_peerreview
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_peerreview\local\grade;

/**
 * Points (0 to the maximum) or a scale (item positions 1 to the number of items) for the received grade.
 *
 * Reviews, overrides and the aggregate are all kept as numbers in this range, so a scale only changes how values are
 * chosen (a dropdown), rounded (to a whole item) and shown (the item name).
 *
 * @package    mod_peerreview
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class grade_range {
    /** @var string[]|null Scale items by position (1 based), loaded on first use. */
    private ?array $items = null;

    /**
     * Constructor.
     *
     * @param int $grade The activity's grade setting: points when positive, minus the scale id when negative.
     */
    public function __construct(
        /** @var int The activity's grade setting. */
        private readonly int $grade
    ) {
    }

    /**
     * The range of an activity.
     *
     * @param \stdClass $peerreview Activity record.
     * @return self
     */
    public static function for_activity(\stdClass $peerreview): self {
        return new self((int) $peerreview->grade);
    }

    /**
     * Whether the grade is a scale.
     *
     * @return bool
     */
    public function is_scale(): bool {
        return $this->grade < 0;
    }

    /**
     * The scale id, 0 for points.
     *
     * @return int
     */
    public function get_scale_id(): int {
        return $this->is_scale() ? -$this->grade : 0;
    }

    /**
     * The lowest value: 0 points, or the first scale item (position 1).
     *
     * @return int
     */
    public function min(): int {
        return $this->is_scale() ? 1 : 0;
    }

    /**
     * The highest value: the maximum points, or the position of the last scale item.
     *
     * @return int
     */
    public function max(): int {
        return $this->is_scale() ? count($this->get_items()) : $this->grade;
    }

    /**
     * Scale items by position (1 based); empty for points.
     *
     * @return string[]
     */
    public function get_items(): array {
        global $CFG;

        if ($this->items === null) {
            $this->items = [];
            if ($this->is_scale()) {
                require_once($CFG->libdir . '/gradelib.php');
                $scale = \grade_scale::fetch(['id' => $this->get_scale_id()]);
                if ($scale) {
                    foreach (array_values($scale->load_items()) as $index => $name) {
                        $this->items[$index + 1] = trim($name);
                    }
                }
            }
        }
        return $this->items;
    }

    /**
     * The menu core uses to configure grading forms: position => label.
     *
     * @return array
     */
    public function get_menu(): array {
        global $CFG;

        require_once($CFG->libdir . '/gradelib.php');
        return make_grades_menu($this->grade);
    }

    /**
     * Whether a value may be given as a review score or an override.
     *
     * @param mixed $value Candidate value.
     * @return bool Points: a number from 0 to the maximum. Scale: the position of an item.
     */
    public function accepts($value): bool {
        if (!is_numeric($value)) {
            return false;
        }
        if ($this->is_scale()) {
            return (float) $value === (float) (int) $value && (int) $value >= 1 && (int) $value <= $this->max();
        }
        return $value >= 0 && $value <= $this->max();
    }

    /**
     * Whether values stored in this range stay meaningful when the range is replaced by another one.
     *
     * Points to other points is fine (the maximum may change), points to a scale or back is not, and a scale may only be
     * replaced by one with the same number of items.
     *
     * @param self $new The new range.
     * @return bool
     */
    public function fits(self $new): bool {
        if ($this->is_scale() !== $new->is_scale()) {
            return false;
        }
        return !$new->is_scale() || $this->max() === $new->max();
    }

    /**
     * Bring a value to what the gradebook stores: points unchanged, a scale position rounded to a whole item.
     *
     * @param float|null $value A grade in this range.
     * @return float|null
     */
    public function for_gradebook(?float $value): ?float {
        if ($value === null || !$this->is_scale()) {
            return $value;
        }
        return (float) min($this->max(), max(1, (int) round($value)));
    }

    /**
     * A value as text: points with two decimals, a scale as the name of the nearest item.
     *
     * @param float $value A grade in this range.
     * @param bool $stripzeros Drop trailing zeros of points (75 instead of 75.00).
     * @return string
     */
    public function format(float $value, bool $stripzeros = false): string {
        if ($this->is_scale()) {
            $items = $this->get_items();
            $position = min($this->max(), max(1, (int) round($value)));
            return $items[$position] ?? (string) $position;
        }
        return format_float($value, 2, true, $stripzeros);
    }

    /**
     * A value for display with its range: "75 / 100" for points, the item name for a scale.
     *
     * @param float $value A grade in this range.
     * @return string
     */
    public function format_with_max(float $value): string {
        if ($this->is_scale()) {
            return $this->format($value);
        }
        return get_string('gradeoutof', 'mod_peerreview', (object) ['grade' => $this->format($value, true), 'max' => $this->max()]);
    }

    /**
     * The message for a value that is not accepted.
     *
     * @return string
     */
    public function get_error(): string {
        return get_string($this->get_error_code(), 'mod_peerreview', $this->max());
    }

    /**
     * The language string key (and exception code) for a value that is not accepted.
     *
     * @return string
     */
    public function get_error_code(): string {
        return $this->is_scale() ? 'errorscaleitem' : 'errorscorerange';
    }

    /**
     * Grade item settings for grade_update().
     *
     * @return array
     */
    public function get_item_params(): array {
        if ($this->is_scale()) {
            return ['gradetype' => GRADE_TYPE_SCALE, 'scaleid' => $this->get_scale_id()];
        }
        return ['gradetype' => GRADE_TYPE_VALUE, 'grademax' => $this->grade, 'grademin' => 0];
    }
}
