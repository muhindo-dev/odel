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
 * MRU ODEL Registration Wizard — thin router.
 *
 * Delegates each step's logic and rendering to its own class
 * under \local_mru\registration\step{N}.
 *
 * @package    local_mru
 * @copyright  2026 Mutesa I Royal University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

// Set context early — needed before any format_string / email calls.
$PAGE->set_context(context_system::instance());

// Redirect already logged-in users (except for step 5 success page).
$forcestep = optional_param('step', 0, PARAM_INT);
if (isloggedin() && !isguestuser() && $forcestep !== 5) {
    redirect(new moodle_url('/my/'));
}

$regmanager = new \local_mru\registration_manager();

// Get or create session.
$session = $regmanager->get_or_create_session();

// Determine current step.
$step = (int) $session->current_step;
if ($forcestep === 5 && isloggedin() && !isguestuser()) {
    $step = 5;
}

// Map step number → handler class.
$stepclasses = [
    1 => \local_mru\registration\step1::class,
    2 => \local_mru\registration\step2::class,
    3 => \local_mru\registration\step3::class,
    4 => \local_mru\registration\step4::class,
    5 => \local_mru\registration\step5::class,
];

$stepclass = $stepclasses[$step] ?? $stepclasses[1];
$handler = new $stepclass($regmanager, $session);

// ── Handle POST actions ──
$action = optional_param('action', '', PARAM_ALPHA);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();
    $handler->handle_action($action);
}

// ── Render ──
$PAGE->set_url(new moodle_url('/local/mru/register.php'));
$PAGE->set_pagelayout('authpage');
$PAGE->set_title(get_string('reg:page_title', 'local_mru'));

// Build step indicator data (shared across all steps).
$stepnames = [
    1 => get_string('reg:step1_name', 'local_mru'),
    2 => get_string('reg:step2_name', 'local_mru'),
    3 => get_string('reg:step3_name', 'local_mru'),
    4 => get_string('reg:step4_name', 'local_mru'),
    5 => get_string('reg:step5_name', 'local_mru'),
];
$steps = [];
foreach ($stepnames as $num => $name) {
    $steps[] = [
        'num'      => $num,
        'name'     => $name,
        'active'   => ($num === $step),
        'done'     => ($num < $step),
        'upcoming' => ($num > $step),
    ];
}

// Merge shared data with step-specific data.
$data = array_merge([
    'wwwroot'    => $CFG->wwwroot,
    'sesskey'    => sesskey(),
    'step'       => $step,
    'steps'      => $steps,
    'totalsteps' => \local_mru\registration_manager::TOTAL_STEPS,
    'siteyear'   => date('Y'),
    'step_content' => $OUTPUT->render_from_template($handler->get_template(), array_merge([
        'wwwroot' => $CFG->wwwroot,
        'sesskey' => sesskey(),
    ], $handler->get_template_data())),
], $handler->get_template_data());

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_mru/register_wizard', $data);
echo $OUTPUT->footer();
