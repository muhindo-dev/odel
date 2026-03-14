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
 * MRU ODEL — Custom frontpage layout.
 *
 * @package    theme_mru_odel
 * @copyright  2026 Muteesa I Royal University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG, $OUTPUT, $PAGE, $SITE, $USER, $DB;

require_once($CFG->libdir . '/behat/lib.php');
require_once($CFG->dirroot . '/course/lib.php');

// Standard drawer boilerplate.
$addblockbutton = $OUTPUT->addblockbutton();

if (isloggedin()) {
    $courseindexopen = (get_user_preferences('drawer-open-index', true) == true);
    $blockdraweropen = (get_user_preferences('drawer-open-block') == true);
} else {
    $courseindexopen = false;
    $blockdraweropen = false;
}

$extraclasses = ['uses-drawers'];
if ($courseindexopen) {
    $extraclasses[] = 'drawer-open-index';
}

$blockshtml = $OUTPUT->blocks('side-pre');
$hasblocks  = (strpos($blockshtml, 'data-block=') !== false || !empty($addblockbutton));
if (!$hasblocks) {
    $blockdraweropen = false;
}

$courseindex = core_course_drawer();
if (!$courseindex) {
    $courseindexopen = false;
}

$bodyattributes = $OUTPUT->body_attributes($extraclasses);
$forceblockdraweropen = $OUTPUT->firstview_fakeblocks();

$secondarynavigation = false;
$overflow = '';
if ($PAGE->has_secondary_navigation()) {
    $tablistnav = $PAGE->has_tablist_secondary_navigation();
    $moremenu = new \core\navigation\output\more_menu($PAGE->secondarynav, 'nav-tabs', true, $tablistnav);
    $secondarynavigation = $moremenu->export_for_template($OUTPUT);
    $overflowdata = $PAGE->secondarynav->get_overflow_menu_data();
    if (!is_null($overflowdata)) {
        $overflow = $overflowdata->export_for_template($OUTPUT);
    }
}

$primary = new core\navigation\output\primary($PAGE);
$renderer = $PAGE->get_renderer('core');
$primarymenu = $primary->export_for_template($renderer);
$buildregionmainsettings = !$PAGE->include_region_main_settings_in_header_actions()
    && !$PAGE->has_secondary_navigation();
$regionmainsettingsmenu = $buildregionmainsettings ? $OUTPUT->region_main_settings_menu() : false;
$headercontent = $PAGE->activityheader->export_for_template($renderer);

// Theme settings.
$theme     = theme_config::load('mru_odel');
$footertext = !empty($theme->settings->footertext) ? format_text($theme->settings->footertext, FORMAT_HTML) : '';
$loggedin   = isloggedin() && !isguestuser();
$firstname  = $loggedin ? $USER->firstname : '';

// ── Stats from database ──────────────────────────────────────────────────────
$totalcourses    = $DB->count_records('course') - 1;
$totalstudents   = $DB->count_records_select('user', "deleted = 0 AND suspended = 0 AND id > 2");
$totalcategories = $DB->count_records('course_categories');

// ── Faculties — real top-level categories ────────────────────────────────────
$topcats = $DB->get_records('course_categories', ['parent' => 0, 'visible' => 1], 'sortorder ASC', '*', 0, 8);
$facicons  = ['fa-graduation-cap', 'fa-balance-scale', 'fa-briefcase', 'fa-flask', 'fa-laptop', 'fa-heartbeat', 'fa-book', 'fa-paint-brush'];
$facdesc = [
    'Explore cutting-edge programmes designed for leadership and innovation.',
    'Build expertise with industry-relevant courses from experienced professionals.',
    'Gain practical skills and knowledge for a successful career path.',
    'Discover programmes blending academic rigour with real-world application.',
    'Develop competencies through comprehensive and flexible learning modules.',
    'Advance your career with specialised courses tailored to market demands.',
    'Master your field with depth, breadth, and professional development.',
    'Transform your future with quality education and hands-on training.',
];
$faculties = [];
$fi = 0;
foreach ($topcats as $cat) {
    $cc = $DB->count_records('course', ['category' => $cat->id, 'visible' => 1]);
    foreach ($DB->get_records('course_categories', ['parent' => $cat->id]) as $sc) {
        $cc += $DB->count_records('course', ['category' => $sc->id, 'visible' => 1]);
    }
    $faculties[] = [
        'name'        => $cat->name,
        'icon'        => $facicons[$fi % count($facicons)],
        'description' => $facdesc[$fi % count($facdesc)],
        'coursecount' => $cc,
        'url'         => (new moodle_url('/course/index.php', ['categoryid' => $cat->id]))->out(false),
    ];
    $fi++;
}

// ── Notices (sample academic content) ────────────────────────────────────────
$notices = [
    [
        'day' => '14', 'mon' => 'Mar', 'tag' => 'Examinations',
        'title' => 'Semester II 2025/2026 Examination Timetable Released',
        'desc'  => 'Download your examination timetable from the student portal. Exams begin 31st March 2026.',
    ],
    [
        'day' => '10', 'mon' => 'Mar', 'tag' => 'Admissions',
        'title' => 'Postgraduate Applications Now Open — 2025/2026 Intake',
        'desc'  => 'Apply online before 30th April 2026. Available in distance and blended learning modes.',
    ],
    [
        'day' => '05', 'mon' => 'Mar', 'tag' => 'Academic',
        'title' => 'Orientation for New Distance Learning Students',
        'desc'  => 'Join the virtual orientation on 25th March 2026 at 10:00 AM EAT via the ODEL portal.',
    ],
    [
        'day' => '28', 'mon' => 'Feb', 'tag' => 'Research',
        'title' => 'Call for Papers — MRU 4th Annual Research Symposium',
        'desc'  => 'Submit abstracts by 15th April 2026. Open to all faculties and postgraduate students.',
    ],
];

// ── Why choose MRU ───────────────────────────────────────────────────────────
$features = [
    ['icon' => 'fa-university', 'title' => 'Accredited Programmes',  'desc' => 'All programmes approved by NCHE and recognised internationally for academic excellence.'],
    ['icon' => 'fa-globe', 'title' => 'Study From Anywhere',     'desc' => 'Fully online delivery with live sessions, recorded lectures, and downloadable e-resources.'],
    ['icon' => 'fa-users', 'title' => 'Expert Faculty',         'desc' => 'Learn from qualified academics and industry professionals with real-world experience.'],
    ['icon' => 'fa-mobile', 'title' => 'Mobile-Friendly Platform', 'desc' => 'Access courses, assignments, and grades from any device — desktop, tablet, or phone.'],
    ['icon' => 'fa-trophy', 'title' => 'Career-Focused Curricula', 'desc' => 'Programmes designed with industry partners to maximise graduate employability.'],
    ['icon' => 'fa-comments', 'title' => 'Collaborative Learning',  'desc' => 'Interactive forums, group projects, and peer-to-peer support across all programmes.'],
];

$templatecontext = [
    // Moodle boilerplate.
    'sitename'               => format_string($SITE->shortname, true,
        ['context' => context_course::instance(SITEID), 'escape' => false]),
    'output'                 => $OUTPUT,
    'sidepreblocks'          => $blockshtml,
    'hasblocks'              => $hasblocks,
    'bodyattributes'         => $bodyattributes,
    'courseindexopen'         => $courseindexopen,
    'blockdraweropen'        => $blockdraweropen,
    'courseindex'             => $courseindex,
    'primarymoremenu'        => $primarymenu['moremenu'],
    'secondarymoremenu'      => $secondarynavigation ?: false,
    'mobileprimarynav'       => $primarymenu['mobileprimarynav'],
    'usermenu'               => $primarymenu['user'],
    'langmenu'               => $primarymenu['lang'],
    'forceblockdraweropen'   => $forceblockdraweropen,
    'regionmainsettingsmenu' => $regionmainsettingsmenu,
    'hasregionmainsettingsmenu' => !empty($regionmainsettingsmenu),
    'overflow'               => $overflow,
    'headercontent'          => $headercontent,
    'addblockbutton'         => $addblockbutton,
    'footertext'             => $footertext,
    'siteyear'               => date('Y'),

    // Frontpage-specific.
    'isloggedin'             => $loggedin,
    'firstname'              => $firstname,
    'wwwroot'                => $CFG->wwwroot,
    'maincontent'            => $OUTPUT->main_content(),
    'totalcourses'           => $totalcourses,
    'totalstudents'          => $totalstudents,
    'totalcategories'        => $totalcategories,
    'faculties'              => $faculties,
    'hasfaculties'           => !empty($faculties),
    'notices'                => $notices,
    'features'               => $features,
    'loginurl'               => (new moodle_url('/login/index.php'))->out(false),
    'coursesurl'             => (new moodle_url('/course/index.php'))->out(false),
    'dashboardurl'           => (new moodle_url('/my/'))->out(false),
];

echo $OUTPUT->render_from_template('theme_mru_odel/frontpage', $templatecontext);
