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

//=========================================================================
// 6. ENVIRONMENT OVERRIDES (Development)
//=========================================================================
// Allow MySQL 5.7 (MAMP) — bypass strict version check.
$CFG->upgraderunning = false;
// Suppress HTTPS warning for local development.
$CFG->sslproxy = false;
$CFG->loginhttps = false;
// Skip password policy for development.
$CFG->passwordpolicy = false;
// Disable update notifications and plugin check screens.
$CFG->disableupdatenotifications = true;
$CFG->disableupdateautodeploy = true;
// Auto-proceed with upgrades — skip confirmation pages.
$CFG->upgradekey = '';
$CFG->auto_update_plugin_types = '';
// Enable developer debug mode for local development.
@error_reporting(E_ALL | E_STRICT);
@ini_set('display_errors', '1');
$CFG->debug = (E_ALL | E_STRICT);
$CFG->debugdisplay = 1;

require_once(__DIR__ . '/lib/setup.php');
