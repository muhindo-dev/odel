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
 * Student status page — shows MRU verification status.
 *
 * @package    local_mru
 * @copyright  2026 Mutesa I Royal University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

require_login();
$context = context_system::instance();
require_capability('local/mru:viewownstatus', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/mru/status.php'));
$PAGE->set_title(get_string('status:title', 'local_mru'));
$PAGE->set_heading(get_string('status:title', 'local_mru'));
$PAGE->set_pagelayout('standard');

$studentmanager = new \local_mru\student_manager();
$mapping = $studentmanager->get_user_mapping($USER->id);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('status:title', 'local_mru'));

$templatedata = [
    'has_mapping'   => $mapping !== false,
    'mru_id'        => $mapping->mru_id ?? '',
    'user_type'     => $mapping->user_type ?? '',
    'verified'      => !empty($mapping->verified),
    'verified_at'   => !empty($mapping->verified_at) ? userdate($mapping->verified_at) : '',
    'fullname'      => fullname($USER),
];

echo $OUTPUT->render_from_template('local_mru/status', $templatedata);

echo $OUTPUT->footer();
