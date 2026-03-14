<?php
// MRU ODEL Theme — Maintenance layout.
defined('MOODLE_INTERNAL') || die();

$templatecontext = [
    'sitename' => format_string($SITE->shortname, true, ["escape" => false]),
    'output'   => $OUTPUT,
];

echo $OUTPUT->render_from_template('theme_boost/maintenance', $templatecontext);
