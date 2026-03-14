<?php return array(
    'root' => array(
        'name' => 'moodle/moodle',
        'pretty_version' => 'dev-main',
        'version' => 'dev-main',
        'reference' => '11587b161d9550e613102e823f00b16f6f1ed747',
        'type' => 'moodle-core',
        'install_path' => __DIR__ . '/../../',
        'aliases' => array(),
        'dev' => false,
    ),
    'versions' => array(
        'moodle/lms' => array(
            'dev_requirement' => false,
            'provided' => array(
                0 => '5.1',
            ),
        ),
        'moodle/moodle' => array(
            'pretty_version' => 'dev-main',
            'version' => 'dev-main',
            'reference' => '11587b161d9550e613102e823f00b16f6f1ed747',
            'type' => 'moodle-core',
            'install_path' => __DIR__ . '/../../',
            'aliases' => array(),
            'dev_requirement' => false,
        ),
    ),
);
