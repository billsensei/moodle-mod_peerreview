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
 * Behat steps and page shortcuts for mod_peerreview.
 *
 * Page resolution modelled on mod/assign/tests/behat/behat_mod_assign.php; the rubric step on
 * grade/grading/form/rubric/tests/behat/behat_gradingform_rubric.php (whose own filling step clicks a mod_assign-only
 * "toggle zoom" link in JavaScript mode, so it cannot be used on the peer review form).
 *
 * @package    mod_peerreview
 * @category   test
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// NOTE: no MOODLE_INTERNAL test here, this file may be required by behat before including /config.php.

require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');

use Behat\Gherkin\Node\TableNode;
use Behat\Mink\Exception\ExpectationException;

/**
 * Steps for mod_peerreview.
 *
 * @package    mod_peerreview
 * @category   test
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_mod_peerreview extends behat_base {
    /**
     * Pages of an activity, found by its name: "View", "Report", "Allocate", "Allocate random".
     *
     * Example: I am on the "Talk review" "mod_peerreview > Report" page logged in as "teacher1"
     *
     * @param string $type Page type.
     * @param string $identifier Activity name.
     * @return moodle_url
     */
    protected function resolve_page_instance_url(string $type, string $identifier): moodle_url {
        $cm = $this->get_cm_by_activity_name('peerreview', $identifier);
        return match (strtolower($type)) {
            'view' => new moodle_url('/mod/peerreview/view.php', ['id' => $cm->id]),
            'report' => new moodle_url('/mod/peerreview/report.php', ['id' => $cm->id]),
            'allocate' => new moodle_url('/mod/peerreview/allocate.php', ['id' => $cm->id]),
            'allocate random' => new moodle_url('/mod/peerreview/allocate.php', ['id' => $cm->id, 'method' => 'random']),
            default => throw new Exception('Unrecognised mod_peerreview page type "' . $type . '".'),
        };
    }

    /**
     * Fill in the rubric of the review form: one row per criterion with the points of the level and a remark.
     *
     * Example:
     *   I fill in the peer review rubric with:
     *     | Content  | 10 | Well researched |
     *     | Delivery | 5  | A bit fast      |
     *
     * @When /^I fill in the peer review rubric with:$/
     * @param TableNode $rubric Rows: criterion description, level points, remark.
     */
    public function i_fill_in_the_peer_review_rubric_with(TableNode $rubric): void {
        foreach ($rubric->getRows() as $row) {
            if (count($row) !== 3 || !is_numeric($row[1])) {
                throw new ExpectationException('Each row needs: | criterion | points | remark |', $this->getSession());
            }
            [$criterion, $points, $remark] = $row;
            $criterionxpath = "//tr[contains(concat(' ', normalize-space(@class), ' '), ' criterion ')]" .
                "[./descendant::td[@class='description'][text()=" . behat_context_helper::escape($criterion) . "]]";
            $levelxpath = $criterionxpath . "//td[contains(concat(' ', normalize-space(@class), ' '), ' level ')]" .
                "[./descendant::span[@class='scorevalue'][text()='$points']]";

            if ($this->running_javascript()) {
                // The rubric script hides the radios; clicking the level cell selects it.
                $level = $this->find('xpath', $levelxpath);
                if (!$level->hasClass('checked')) {
                    $level->click();
                }
            } else {
                $radio = $this->find('xpath', $levelxpath . "/descendant::input[@type='radio']");
                $radio->setValue($radio->getAttribute('value'));
            }
            $textarea = $this->find('xpath', $criterionxpath . "//textarea");
            $this->execute('behat_forms::i_set_the_field_to', [$textarea->getAttribute('name'), $remark]);
        }
    }

    /**
     * The page fits the window width (nothing to scroll sideways), as it must on a phone.
     *
     * @Then /^the peer review page should not scroll horizontally$/
     */
    public function the_page_should_not_scroll_horizontally(): void {
        $this->require_javascript();
        $sizes = $this->evaluate_script('return [document.documentElement.scrollWidth, window.innerWidth];');
        if ($sizes[0] > $sizes[1]) {
            throw new ExpectationException("The page is {$sizes[0]}px wide in a {$sizes[1]}px window.", $this->getSession());
        }
    }
}
