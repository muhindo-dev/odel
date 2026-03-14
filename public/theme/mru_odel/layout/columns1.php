<?php
// MRU ODEL Theme — Single column layout.
defined('MOODLE_INTERNAL') || die();

$bodyattributes = $OUTPUT->body_attributes([]);
$templatecontext = [
    'sitename'       => format_string($SITE->shortname, true, ['context' => context_course::instance(SITEID), "escape" => false]),
    'output'         => $OUTPUT,
    'bodyattributes' => $bodyattributes,
];

if (empty($PAGE->layout_options['noactivityheader'])) {
    $header = $PAGE->activityheader;
    $renderer = $PAGE->get_renderer('core');
    $templatecontext['headercontent'] = $header->export_for_template($renderer);
}

echo $OUTPUT->render_from_template('theme_boost/columns1', $templatecontext);
