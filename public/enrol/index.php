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
 * This page shows all course enrolment options for current user.
 *
 * @package    core_enrol
 * @copyright  2010 Petr Skoda {@link http://skodak.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../config.php');
require_once("$CFG->libdir/formslib.php");

$id = required_param('id', PARAM_INT);
$returnurl = optional_param('returnurl', null, PARAM_LOCALURL);

if (!isloggedin()) {
    $referer = get_local_referer();
    if (empty($referer)) {
        $SESSION->wantsurl = "$CFG->wwwroot/course/view.php?id=$id";
    }
    redirect(get_login_url());
}

$course = $DB->get_record('course', array('id'=>$id), '*', MUST_EXIST);
$context = context_course::instance($course->id, MUST_EXIST);

if ($course->id == SITEID) {
    redirect("$CFG->wwwroot/");
}

if (!$course->visible && !has_capability('moodle/course:viewhiddencourses', context_course::instance($course->id))) {
    throw new \moodle_exception('coursehidden');
}

$PAGE->set_course($course);
$PAGE->set_context($context->get_parent_context());
$PAGE->set_pagelayout('incourse');
$PAGE->set_url('/enrol/index.php', array('id'=>$course->id));
$PAGE->set_secondary_navigation(false);
$PAGE->add_body_class('limitedwidth mru-enrol-page');

if (\core\session\manager::is_loggedinas() and $USER->loginascontext->contextlevel == CONTEXT_COURSE) {
    throw new \moodle_exception('loginasnoenrol', '', $CFG->wwwroot.'/course/view.php?id='.$USER->loginascontext->instanceid);
}

if (!core_course_category::can_view_course_info($course) && !is_enrolled($context, $USER, '', true)) {
    throw new \moodle_exception('coursehidden', '', $CFG->wwwroot . '/');
}

// Get enrol widgets.
$enrols = enrol_get_plugins(true);
$enrolinstances = enrol_get_instances($course->id, true);
$widgets = [];
foreach($enrolinstances as $instance) {
    if (!isset($enrols[$instance->enrol])) {
        continue;
    }
    $widget = $enrols[$instance->enrol]->enrol_page_hook($instance);
    if ($widget) {
        $widgets[$instance->id] = $widget;
    }
}

// Check if already enrolled.
if (is_enrolled($context, $USER, '', true)) {
    if (!empty($SESSION->wantsurl)) {
        $destination = $SESSION->wantsurl;
        unset($SESSION->wantsurl);
    } else {
        $destination = "$CFG->wwwroot/course/view.php?id=$course->id";
    }
    redirect($destination);
}

// ── Gather rich course data ─────────────────────────────────────────────
$category = $DB->get_record('course_categories', ['id' => $course->category], 'id,name');
$categoryname = $category ? format_string($category->name) : '';

// Course image.
$courseimage = '';
$fs = get_file_storage();
$files = $fs->get_area_files($context->id, 'course', 'overviewfiles', 0, 'sortorder', false);
foreach ($files as $f) {
    if ($f->is_valid_image()) {
        $courseimage = moodle_url::make_pluginfile_url(
            $f->get_contextid(), $f->get_component(), $f->get_filearea(),
            null, $f->get_filepath(), $f->get_filename()
        )->out(false);
        break;
    }
}

// Enrolled count.
$enrolledcount = count_enrolled_users($context);

// Course contacts (teachers).
$coursecontacts = [];
$courseobj = new core_course_list_element($course);
foreach ($courseobj->get_course_contacts() as $uid => $contactdata) {
    $coursecontacts[] = $contactdata['username'];
}

// Format summary.
$summary = format_text($course->summary, $course->summaryformat, ['context' => $context]);

// Date info.
$startdate = $course->startdate ? userdate($course->startdate, get_string('strftimedatefull')) : '';
$enddate = $course->enddate ? userdate($course->enddate, get_string('strftimedatefull')) : '';

// Sections count (topics).
$sectioncount = $DB->count_records_select('course_sections', 'course = :cid AND section > 0 AND visible = 1', ['cid' => $course->id]);

// Activity count.
$activitycount = $DB->count_records_select('course_modules', 'course = :cid AND visible = 1 AND deletioninprogress = 0', ['cid' => $course->id]);

$PAGE->set_title($course->shortname . ' - Enrolment');
$PAGE->set_heading($course->fullname);
$PAGE->navbar->add(get_string('enrolmentoptions','enrol'));

echo $OUTPUT->header();
?>

<div class="mru-enrol-wrap">

    <!-- Hero Banner -->
    <div class="mru-enrol-hero" <?php if ($courseimage) echo 'style="background-image:url(\'' . s($courseimage) . '\')"'; ?>>
        <div class="mru-enrol-hero-overlay">
            <div class="mru-enrol-hero-content">
                <span class="mru-enrol-code"><?php echo s($course->shortname); ?></span>
                <h1 class="mru-enrol-course-title"><?php echo format_string($course->fullname); ?></h1>
                <?php if ($categoryname): ?>
                    <span class="mru-enrol-cat"><i class="fa fa-folder-o"></i> <?php echo $categoryname; ?></span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="mru-enrol-body">

        <!-- Left: Course Details -->
        <div class="mru-enrol-details">

            <!-- Quick Stats -->
            <div class="mru-enrol-stats-row">
                <div class="mru-enrol-stat">
                    <i class="fa fa-users"></i>
                    <div>
                        <strong><?php echo $enrolledcount; ?></strong>
                        <span>Enrolled</span>
                    </div>
                </div>
                <div class="mru-enrol-stat">
                    <i class="fa fa-list"></i>
                    <div>
                        <strong><?php echo $sectioncount; ?></strong>
                        <span>Sections</span>
                    </div>
                </div>
                <div class="mru-enrol-stat">
                    <i class="fa fa-puzzle-piece"></i>
                    <div>
                        <strong><?php echo $activitycount; ?></strong>
                        <span>Activities</span>
                    </div>
                </div>
            </div>

            <!-- About This Course -->
            <?php if (!empty($summary)): ?>
            <div class="mru-enrol-section">
                <h3><i class="fa fa-info-circle"></i> About This Course</h3>
                <div class="mru-enrol-summary"><?php echo $summary; ?></div>
            </div>
            <?php endif; ?>

            <!-- Course Details Table -->
            <div class="mru-enrol-section">
                <h3><i class="fa fa-th-list"></i> Course Details</h3>
                <table class="mru-enrol-info-table">
                    <tr>
                        <td class="mru-enrol-info-label">Course Code</td>
                        <td class="mru-enrol-info-value"><strong><?php echo s($course->shortname); ?></strong></td>
                    </tr>
                    <tr>
                        <td class="mru-enrol-info-label">Full Name</td>
                        <td class="mru-enrol-info-value"><?php echo format_string($course->fullname); ?></td>
                    </tr>
                    <?php if ($categoryname): ?>
                    <tr>
                        <td class="mru-enrol-info-label">Category</td>
                        <td class="mru-enrol-info-value"><?php echo $categoryname; ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if ($startdate): ?>
                    <tr>
                        <td class="mru-enrol-info-label">Start Date</td>
                        <td class="mru-enrol-info-value"><?php echo $startdate; ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if ($enddate): ?>
                    <tr>
                        <td class="mru-enrol-info-label">End Date</td>
                        <td class="mru-enrol-info-value"><?php echo $enddate; ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if (!empty($coursecontacts)): ?>
                    <tr>
                        <td class="mru-enrol-info-label">Instructor(s)</td>
                        <td class="mru-enrol-info-value"><?php echo implode(', ', array_map('s', $coursecontacts)); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if ($course->lang): ?>
                    <tr>
                        <td class="mru-enrol-info-label">Language</td>
                        <td class="mru-enrol-info-value"><?php echo s($course->lang ?: 'English'); ?></td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>

        <!-- Right: Enrolment Action Card -->
        <div class="mru-enrol-action">
            <div class="mru-enrol-action-card">
                <h3><i class="fa fa-sign-in"></i> Enrol in This Course</h3>

                <?php if (!empty($widgets)): ?>
                    <p class="mru-enrol-action-hint">Complete the form below to enrol yourself in this course.</p>
                    <?php foreach ($widgets as $widget): ?>
                        <div class="mru-enrol-widget"><?php echo $widget; ?></div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="mru-enrol-no-methods">
                        <i class="fa fa-lock"></i>
                        <p>Enrolment is not currently available for this course. Please contact your administrator or instructor.</p>
                    </div>
                <?php endif; ?>

                <div class="mru-enrol-back-link">
                    <a href="<?php echo (new moodle_url('/local/mru/mycourses.php', ['tab' => 'browse']))->out(); ?>">
                        <i class="fa fa-arrow-left"></i> Back to Course Browser
                    </a>
                </div>
            </div>
        </div>

    </div>
</div>

<?php
echo $OUTPUT->footer();
