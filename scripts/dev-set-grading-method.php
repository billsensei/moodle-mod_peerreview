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
 * Development helper: sets the active grading method of a peer review: a 3 criteria rubric, a 2 criteria marking guide,
 * or none (simple points and comment). Reuses core's test helper classes.
 *
 * Usage:   source scripts/env.sh && php scripts/dev-set-grading-method.php CMID rubric|guide|none
 * Exit codes: 0 success, 1 bad usage.
 *
 * @package    mod_peerreview
 * @copyright  2026 Bill <wrwjpn@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);
require((getenv('MOODLE_DIR') ?: dirname(__DIR__, 3)) . '/config.php');
require_once($CFG->dirroot . '/grade/grading/lib.php');
require_once($CFG->libdir . '/filelib.php');
require_once($CFG->dirroot . '/grade/grading/form/rubric/tests/generator/rubric.php');
require_once($CFG->dirroot . '/grade/grading/form/rubric/tests/generator/criterion.php');
require_once($CFG->dirroot . '/grade/grading/form/guide/tests/generator/guide.php');
require_once($CFG->dirroot . '/grade/grading/form/guide/tests/generator/criterion.php');

use tests\gradingform_guide\generator\criterion as guidecriterion;
use tests\gradingform_guide\generator\guide;
use tests\gradingform_rubric\generator\criterion;
use tests\gradingform_rubric\generator\rubric;

$cmid = (int) ($argv[1] ?? 0);
$method = $argv[2] ?? 'rubric';
if (!$cmid || !in_array($method, ['rubric', 'guide', 'none'], true)) {
    fwrite(STDERR, "Usage: php scripts/dev-set-grading-method.php CMID rubric|guide|none\n");
    exit(1);
}
\core\session\manager::set_user(get_admin());

$context = context_module::instance($cmid);
$manager = get_grading_manager($context, 'mod_peerreview', 'received');
if ($method === 'none') {
    $manager->set_active_method(null);
    echo "Grading method cleared for cmid $cmid (simple points and comment)\n";
    exit(0);
}
$manager->set_active_method($method);
$controller = $manager->get_controller($method);

if ($method === 'rubric') {
    $definition = new rubric('Presentation rubric', 'Rate the talk you just saw.');
    foreach (['Clarity' => 'clear', 'Structure' => 'structured', 'Delivery' => 'delivered'] as $name => $word) {
        $definition->add_criteria(new criterion($name, [
            "Not $word" => 0,
            "Partly $word" => 1,
            "Well $word" => 2,
            "Very well $word" => 3,
        ]));
    }
} else {
    $definition = new guide('Presentation guide', 'Mark the talk you just saw.');
    $definition->add_criteria(new guidecriterion('Content', 'Accurate and relevant.', 'Deduct for errors.', 20));
    $definition->add_criteria(new guidecriterion('Delivery', 'Clear voice and pace.', 'Deduct for mumbling.', 10));
}
$controller->update_definition($definition->get_definition());
echo "$method set for cmid $cmid (definition status ready)\n";
