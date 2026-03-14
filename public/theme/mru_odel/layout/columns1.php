<?php
// MRU ODEL Theme — Single column layout.
defined('MOODLE_INTERNAL') || die();

$bodyattributes = $OUTPUT->body_attributes([]);

$theme = theme_config::load('mru_odel');
$footertext = !empty($theme->settings->footertext) ? format_text($theme->settings->footertext, FORMAT_HTML) : '';

$templatecontext = [
    'sitename'       => format_string($SITE->shortname, true, ['context' => context_course::instance(SITEID), "escape" => false]),
    'output'         => $OUTPUT,
    'bodyattributes' => $bodyattributes,
    'footertext'     => $footertext,
    'siteyear'       => date('Y'),
];

if (empty($PAGE->layout_options['noactivityheader'])) {
    $header = $PAGE->activityheader;
    $renderer = $PAGE->get_renderer('core');
    $templatecontext['headercontent'] = $header->export_for_template($renderer);
}

echo $OUTPUT->render_from_template('theme_mru_odel/columns1', $templatecontext);
