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
 * Grade item mappings for mod_peerreview.
 *
 * Modelled on mod/workshop/classes/grades/gradeitems.php and mod/assign/classes/grades/gradeitems.php.
 *
 * @package    mod_peerreview
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace mod_peerreview\grades;

use core_grades\local\gradeitem\advancedgrading_mapping;
use core_grades\local\gradeitem\fieldname_mapping;
use core_grades\local\gradeitem\itemnumber_mapping;

/**
 * Grade item mappings: item 0 is the received grade, item 1 the optional participation grade.
 *
 * Advanced grading has a single area, "peer", whose item ids are allocation ids. It is not a gradebook item.
 *
 * @package    mod_peerreview
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class gradeitems implements advancedgrading_mapping, fieldname_mapping, itemnumber_mapping {
    /** @var int Item number of the received grade. */
    public const ITEM_RECEIVED = 0;

    /** @var int Item number of the participation grade. */
    public const ITEM_PARTICIPATION = 1;

    /**
     * Return the list of grade item mappings.
     *
     * @return array
     */
    public static function get_itemname_mapping_for_component(): array {
        return [
            self::ITEM_RECEIVED => 'received',
            self::ITEM_PARTICIPATION => 'participation',
        ];
    }

    /**
     * Get the suffixed field name for an activity field mapped from its itemnumber.
     *
     * Item 0 keeps the legacy names (grade, gradepass); item 1 appends "participation" (gradeparticipation).
     *
     * @param string $component The component the grade item belongs to.
     * @param int $itemnumber The grade itemnumber.
     * @param string $fieldname The name of the field to be rewritten.
     * @return string The translated field name.
     */
    public static function get_field_name_for_itemnumber(string $component, int $itemnumber, string $fieldname): string {
        if ($itemnumber === self::ITEM_PARTICIPATION) {
            return $fieldname . 'participation';
        }
        return $fieldname;
    }

    /**
     * Get the list of advanced grading item names for this component.
     *
     * @return array
     */
    public static function get_advancedgrading_itemnames(): array {
        return ['peer'];
    }
}
