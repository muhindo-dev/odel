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
 * Custom My Courses page for MRU ODEL.
 *
 * @package    local_mru
 * @copyright  2026 Mutesa I Royal University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/lib/enrollib.php');

require_login();

$tab = optional_param('tab', 'mycourses', PARAM_ALPHA);
$search = optional_param('search', '', PARAM_TEXT);
$categoryid = optional_param('categoryid', 0, PARAM_INT);

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url('/local/mru/mycourses.php', ['tab' => $tab]);
$PAGE->set_pagelayout('mycourses');
$PAGE->set_title(get_string('mycourses'));
$PAGE->set_heading(get_string('mycourses'));
$PAGE->add_body_classes(['limitedwidth', 'page-mycourses', 'mru-mycourses-page']);
$PAGE->force_lock_all_blocks();

// ── Gather enrolled courses ───────────────────────────────────────────────
$enrolledcourses = enrol_get_all_users_courses($USER->id, true, 'id,shortname,fullname,visible,enddate,startdate,summary,category');
$now = time();
$mycourses = [];
foreach ($enrolledcourses as $c) {
    $ctx = context_course::instance($c->id, IGNORE_MISSING);
    $courseimage = '';
    if ($ctx) {
        $fs = get_file_storage();
        $files = $fs->get_area_files($ctx->id, 'course', 'overviewfiles', 0, 'sortorder', false);
        foreach ($files as $f) {
            if ($f->is_valid_image()) {
                $courseimage = moodle_url::make_pluginfile_url(
                    $f->get_contextid(), $f->get_component(), $f->get_filearea(),
                    null, $f->get_filepath(), $f->get_filename()
                )->out(false);
                break;
            }
        }
    }

    $completion = new \completion_info($c);
    $progress = null;
    if ($completion->is_enabled()) {
        $progress = (int)\core_completion\progress::get_course_progress_percentage($c, $USER->id);
    }

    $status = 'inprogress';
    if ($c->enddate && $c->enddate < $now) {
        $status = 'completed';
    } else if ($c->startdate > $now) {
        $status = 'upcoming';
    }

    $mycourses[] = [
        'id'          => (int)$c->id,
        'fullname'    => format_string($c->fullname),
        'shortname'   => $c->shortname,
        'viewurl'     => (new moodle_url('/course/view.php', ['id' => $c->id]))->out(false),
        'courseimage'  => $courseimage,
        'hasprogress'  => $progress !== null,
        'progress'     => $progress ?? 0,
        'status'       => $status,
        'statuslabel'  => ucfirst($status === 'inprogress' ? 'In Progress' : ($status === 'completed' ? 'Completed' : 'Upcoming')),
    ];
}

// Counts.
$totalenrolled = count($mycourses);
$countinprogress = count(array_filter($mycourses, fn($c) => $c['status'] === 'inprogress'));
$countcompleted = count(array_filter($mycourses, fn($c) => $c['status'] === 'completed'));
$countupcoming = count(array_filter($mycourses, fn($c) => $c['status'] === 'upcoming'));

// ── Gather available courses for "Browse & Enrol" tab ─────────────────────
$availablecourses = [];
if ($tab === 'browse') {
    $enrolledids = array_column($mycourses, 'id');

    // Get categories for filter.
    $categories = $DB->get_records('course_categories', ['visible' => 1], 'sortorder', 'id,name,parent,depth');
    $categoryoptions = [];
    foreach ($categories as $cat) {
        $categoryoptions[] = [
            'id' => (int)$cat->id,
            'name' => format_string($cat->name),
            'selected' => ($categoryid == $cat->id),
        ];
    }

    // Fetch courses.
    $params = ['siteid' => SITEID];
    $where = 'c.id <> :siteid AND c.visible = 1';

    if ($categoryid > 0) {
        $where .= ' AND c.category = :catid';
        $params['catid'] = $categoryid;
    }

    if (!empty($search)) {
        $searchterm = '%' . $DB->sql_like_escape($search) . '%';
        $where .= ' AND (' . $DB->sql_like('c.fullname', ':search1', false) .
                  ' OR ' . $DB->sql_like('c.shortname', ':search2', false) . ')';
        $params['search1'] = $searchterm;
        $params['search2'] = $searchterm;
    }

    $sql = "SELECT c.id, c.shortname, c.fullname, c.summary, c.category
              FROM {course} c
             WHERE {$where}
          ORDER BY c.sortorder, c.fullname";
    $allcourses = $DB->get_records_sql($sql, $params, 0, 60);

    foreach ($allcourses as $c) {
        $isenrolled = in_array((int)$c->id, $enrolledids);
        $ctx = context_course::instance($c->id, IGNORE_MISSING);
        $courseimage = '';
        if ($ctx) {
            $fs = get_file_storage();
            $files = $fs->get_area_files($ctx->id, 'course', 'overviewfiles', 0, 'sortorder', false);
            foreach ($files as $f) {
                if ($f->is_valid_image()) {
                    $courseimage = moodle_url::make_pluginfile_url(
                        $f->get_contextid(), $f->get_component(), $f->get_filearea(),
                        null, $f->get_filepath(), $f->get_filename()
                    )->out(false);
                    break;
                }
            }
        }

        // Get category name.
        $catname = isset($categories[$c->category]) ? format_string($categories[$c->category]->name) : '';

        // Check enrol methods.
        $canenrol = false;
        $enrolurl = '';
        if (!$isenrolled && $ctx) {
            $enrolinstances = enrol_get_instances($c->id, true);
            foreach ($enrolinstances as $inst) {
                if (in_array($inst->enrol, ['self', 'manual', 'fee'])) {
                    $canenrol = true;
                    $enrolurl = (new moodle_url('/enrol/index.php', ['id' => $c->id]))->out(false);
                    break;
                }
            }
        }

        $availablecourses[] = [
            'id'           => (int)$c->id,
            'fullname'     => format_string($c->fullname),
            'shortname'    => $c->shortname,
            'courseimage'   => $courseimage,
            'categoryname'  => $catname,
            'isenrolled'    => $isenrolled,
            'canenrol'      => $canenrol,
            'enrolurl'      => $enrolurl,
            'viewurl'       => (new moodle_url('/course/view.php', ['id' => $c->id]))->out(false),
        ];
    }
}

// ── Build template data ───────────────────────────────────────────────────
$data = [
    'tab_mycourses'    => ($tab === 'mycourses'),
    'tab_browse'       => ($tab === 'browse'),
    'mycourses_url'    => (new moodle_url('/local/mru/mycourses.php', ['tab' => 'mycourses']))->out(false),
    'browse_url'       => (new moodle_url('/local/mru/mycourses.php', ['tab' => 'browse']))->out(false),
    'courses'          => array_values($mycourses),
    'hascourses'       => $totalenrolled > 0,
    'totalenrolled'    => $totalenrolled,
    'countinprogress'  => $countinprogress,
    'countcompleted'   => $countcompleted,
    'countupcoming'    => $countupcoming,
    'available_courses' => $availablecourses,
    'hasavailable'     => count($availablecourses) > 0,
    'search'           => $search,
    'categoryid'       => $categoryid,
    'categories'       => $categoryoptions ?? [],
    'hascategories'    => !empty($categoryoptions),
    'searchurl'        => (new moodle_url('/local/mru/mycourses.php', ['tab' => 'browse']))->out(false),
    'userfullname'     => fullname($USER),
    'userinitial'      => strtoupper(substr($USER->firstname, 0, 1)),
    'browse_enrol_url' => (new moodle_url('/local/mru/mycourses.php', ['tab' => 'browse']))->out(false),
];

// ── Render ────────────────────────────────────────────────────────────────
echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_mru/mycourses', $data);
echo $OUTPUT->footer();

// Trigger event.
$event = \core\event\mycourses_viewed::create(['context' => $context]);
$event->trigger();
