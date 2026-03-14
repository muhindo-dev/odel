<?php
// MRU ODEL Theme — Secure layout.
defined('MOODLE_INTERNAL') || die();

$blockshtml     = $OUTPUT->blocks('side-pre');
$hasblocks      = strpos($blockshtml, 'data-block=') !== false;
$bodyattributes = $OUTPUT->body_attributes([]);
$renderer       = $PAGE->get_renderer('core');

$templatecontext = [
    'sitename'       => format_string($SITE->shortname, true, ['context' => context_course::instance(SITEID), "escape" => false]),
    'output'         => $OUTPUT,
    'sidepreblocks'  => $blockshtml,
    'hasblocks'      => $hasblocks,
    'bodyattributes' => $bodyattributes,
    'headercontent'  => $PAGE->activityheader->export_for_template($renderer),
];

echo $OUTPUT->render_from_template('theme_boost/secure', $templatecontext);
