<?php
// MRU ODEL Theme — Embedded layout.
defined('MOODLE_INTERNAL') || die();

$fakeblockshtml = $OUTPUT->blocks('side-pre', [], 'aside', true);
$hasfakeblocks  = strpos($fakeblockshtml, 'data-block="_fake"') !== false;
$renderer       = $PAGE->get_renderer('core');

$templatecontext = [
    'output'         => $OUTPUT,
    'headercontent'  => $PAGE->activityheader->export_for_template($renderer),
    'hasfakeblocks'  => $hasfakeblocks,
    'fakeblocks'     => $fakeblockshtml,
];

echo $OUTPUT->render_from_template('theme_boost/embedded', $templatecontext);
