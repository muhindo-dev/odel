<?php
unset($CFG);
global $CFG;
$CFG = new stdClass();

//=========================================================================
// 1. DATABASE SETUP
//=========================================================================
$CFG->dbtype    = 'mysqli';
$CFG->dblibrary = 'native';
$CFG->dbhost    = 'localhost';
$CFG->dbname    = 'odel';
$CFG->dbuser    = 'root';
$CFG->dbpass    = 'root';
$CFG->prefix    = 'mdl_';
$CFG->dboptions = [
    'dbpersist' => false,
    'dbsocket'  => '/Applications/MAMP/tmp/mysql/mysql.sock',
    'dbport'    => '3306',
    'dbhandlesoptions' => false,
    'dbcollation' => 'utf8mb4_unicode_ci',
];

//=========================================================================
// 2. WEB SITE LOCATION
//=========================================================================
$CFG->wwwroot   = 'http://localhost:8888/odel';

//=========================================================================
// 3. DATA FILES LOCATION
//=========================================================================
$CFG->dataroot  = '/Applications/MAMP/htdocs/moodledata';

//=========================================================================
// 4. DATA FILES PERMISSIONS
//=========================================================================
$CFG->directorypermissions = 02777;

//=========================================================================
// 5. ADMIN DIRECTORY LOCATION
//=========================================================================
$CFG->admin = 'admin';

// Whether the Moodle router is fully configured.
$CFG->routerconfigured = false;

require_once(__DIR__ . '/lib/setup.php');
