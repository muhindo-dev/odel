<?php
/**
 * CLI script to import MRU academic data into Moodle.
 *
 * Imports faculties as categories, programmes as sub-categories,
 * courses under programmes, lecturers as users, and enrols
 * lecturers in their allocated courses.
 *
 * Usage: php admin/cli/import_mru_data.php
 */

define('CLI_SCRIPT', true);

// Allow enough memory and time for large imports.
ini_set('memory_limit', '512M');
set_time_limit(0);

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->dirroot . '/lib/enrollib.php');

// Connect to the MRU source database.
$mrudb = new mysqli('localhost', 'root', 'root', 'mru_main', 3306, '/Applications/MAMP/tmp/mysql/mysql.sock');
if ($mrudb->connect_error) {
    cli_error('Failed to connect to mru_main database: ' . $mrudb->connect_error);
}
$mrudb->set_charset('utf8mb4');

cli_heading('MRU Data Import into Moodle');

// ============================================================
// STEP 1: Create Faculty Categories (top-level).
// ============================================================
cli_writeln('');
cli_heading('Step 1: Creating faculty categories');

$faculties = [];
$result = $mrudb->query("SELECT faculty_code, faculty_name, abbrev, faculty_dean FROM acad_faculty WHERE faculty_code != '00' ORDER BY faculty_code");
while ($row = $result->fetch_assoc()) {
    $faculties[$row['faculty_code']] = $row;
}
$result->free();

$faculty_category_map = []; // faculty_code => moodle category id

foreach ($faculties as $code => $fac) {
    $name = ucwords(strtolower(trim($fac['faculty_name'])));
    $idnumber = trim($fac['abbrev']);

    // Check if category already exists.
    $existing = $DB->get_record('course_categories', ['idnumber' => $idnumber]);
    if ($existing) {
        $faculty_category_map[$code] = $existing->id;
        cli_writeln("  [EXISTS] Faculty category: {$name} (id={$existing->id})");
        continue;
    }

    $data = new stdClass();
    $data->name = $name;
    $data->idnumber = $idnumber;
    $data->description = "Dean: " . trim($fac['faculty_dean']);
    $data->descriptionformat = FORMAT_HTML;
    $data->parent = 0;

    $category = core_course_category::create($data);
    $faculty_category_map[$code] = $category->id;
    cli_writeln("  [CREATED] Faculty category: {$name} (id={$category->id})");
}

// ============================================================
// STEP 2: Create Programme Sub-categories.
// ============================================================
cli_writeln('');
cli_heading('Step 2: Creating programme sub-categories');

$programmes = [];
$result = $mrudb->query("SELECT progcode, progname, faculty_code, abbrev, couselength, total_semesters FROM acad_programme WHERE faculty_code != '00' ORDER BY faculty_code, progname");
while ($row = $result->fetch_assoc()) {
    $programmes[$row['progcode']] = $row;
}
$result->free();

$programme_category_map = []; // progcode => moodle category id

foreach ($programmes as $pcode => $prog) {
    $fcode = $prog['faculty_code'];
    if (!isset($faculty_category_map[$fcode])) {
        cli_writeln("  [SKIP] Programme {$pcode}: faculty {$fcode} not found");
        continue;
    }
    $parentid = $faculty_category_map[$fcode];
    $name = ucwords(strtolower(trim($prog['progname'])));
    $idnumber = trim($pcode);

    $existing = $DB->get_record('course_categories', ['idnumber' => $idnumber]);
    if ($existing) {
        $programme_category_map[$pcode] = $existing->id;
        cli_writeln("  [EXISTS] Programme: {$idnumber} - {$name} (id={$existing->id})");
        continue;
    }

    $data = new stdClass();
    $data->name = $name;
    $data->idnumber = $idnumber;
    $data->description = "Duration: {$prog['couselength']} year(s), {$prog['total_semesters']} semesters";
    $data->descriptionformat = FORMAT_HTML;
    $data->parent = $parentid;

    $category = core_course_category::create($data);
    $programme_category_map[$pcode] = $category->id;
    cli_writeln("  [CREATED] Programme: {$idnumber} - {$name} (id={$category->id})");
}

// ============================================================
// STEP 3: Create Courses under Programmes.
// ============================================================
cli_writeln('');
cli_heading('Step 3: Creating courses');

// Get distinct course-programme mappings.
$result = $mrudb->query("
    SELECT DISTINCT pc.course_code, c.courseName, c.CreditUnit, pc.progcode, pc.study_year, pc.semester
    FROM acad_programmecourses pc
    JOIN acad_course c ON pc.course_code = c.courseID
    JOIN acad_programme p ON pc.progcode = p.progcode
    WHERE p.faculty_code != '00'
      AND TRIM(pc.course_code) != ''
      AND TRIM(c.courseName) != ''
    ORDER BY pc.progcode, pc.study_year, pc.semester
");

$courses_created = 0;
$courses_skipped = 0;
$course_id_map = []; // "progcode|course_code" => moodle course id

while ($row = $result->fetch_assoc()) {
    $pcode = trim($row['progcode']);
    $ccode = trim($row['course_code']);
    $cname = trim($row['courseName']);

    if (empty($ccode) || empty($cname)) {
        continue;
    }

    if (!isset($programme_category_map[$pcode])) {
        continue;
    }

    // Use progcode + course_code as idnumber to keep uniqueness per programme.
    $idnumber = $pcode . '_' . $ccode;
    $mapkey = $pcode . '|' . $ccode;

    // Check if course already exists.
    $existing = $DB->get_record('course', ['idnumber' => $idnumber]);
    if ($existing) {
        $course_id_map[$mapkey] = $existing->id;
        $courses_skipped++;
        continue;
    }

    $coursedata = new stdClass();
    $coursedata->fullname = ucwords(strtolower($cname));
    $coursedata->shortname = $idnumber;
    $coursedata->idnumber = $idnumber;
    $coursedata->category = $programme_category_map[$pcode];
    $coursedata->summary = "Course Code: {$ccode} | Credit Units: " . ($row['CreditUnit'] ?? 'N/A')
        . " | Year {$row['study_year']}, Semester {$row['semester']}";
    $coursedata->summaryformat = FORMAT_HTML;
    $coursedata->format = 'topics';
    $coursedata->numsections = 10;
    $coursedata->visible = 1;

    try {
        $newcourse = create_course($coursedata);
        $course_id_map[$mapkey] = $newcourse->id;
        $courses_created++;
    } catch (Exception $e) {
        cli_writeln("  [ERROR] Course {$idnumber}: " . $e->getMessage());
    }
}
$result->free();

cli_writeln("  Courses created: {$courses_created}, already existed: {$courses_skipped}");

// ============================================================
// STEP 4: Create Lecturer User Accounts.
// ============================================================
cli_writeln('');
cli_heading('Step 4: Creating lecturer user accounts');

$result = $mrudb->query("SELECT empID, emp_name, emp_email, emp_phone, emp_qualifications FROM hrm_employee WHERE EmpType = 'Academic' ORDER BY empID");

$lecturer_map = []; // empID => moodle user id
$lecturers_created = 0;
$lecturers_skipped = 0;

while ($row = $result->fetch_assoc()) {
    $empid = $row['empID'];
    $fullname = trim($row['emp_name']);
    $email = trim($row['emp_email']);
    $phone = trim($row['emp_phone']);

    if (empty($fullname) || $fullname === '-') {
        continue;
    }

    // Generate a username from the name.
    $username = 'mru_' . $empid;

    // Check if user already exists.
    $existing = $DB->get_record('user', ['username' => $username]);
    if ($existing) {
        $lecturer_map[$empid] = $existing->id;
        $lecturers_skipped++;
        continue;
    }

    // Split name into first and last.
    $nameparts = explode(' ', $fullname);
    // Remove titles.
    $titles = ['Prof.', 'Dr.', 'Assoc.', 'Prof', 'Dr', 'Mr.', 'Mrs.', 'Ms.', 'Rev.', 'Hon.'];
    $cleanparts = [];
    foreach ($nameparts as $part) {
        $part = trim($part);
        if (!empty($part) && !in_array($part, $titles)) {
            $cleanparts[] = $part;
        }
    }

    if (count($cleanparts) < 1) {
        continue;
    }

    $firstname = $cleanparts[0];
    $lastname = count($cleanparts) > 1 ? implode(' ', array_slice($cleanparts, 1)) : $firstname;

    // Validate email, use a generated one if invalid.
    if (empty($email) || $email === '-' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $email = $username . '@mru.ac.ug';
    }

    $user = new stdClass();
    $user->username = $username;
    $user->auth = 'manual';
    $user->confirmed = 1;
    $user->mnethostid = $CFG->mnet_localhost_id;
    $user->firstname = $firstname;
    $user->lastname = $lastname;
    $user->email = $email;
    $user->phone1 = ($phone && $phone !== '1' && $phone !== '-') ? $phone : '';
    $user->city = 'Kampala';
    $user->country = 'UG';
    $user->lang = 'en';
    $user->description = trim($row['emp_qualifications'] ?? '');
    $user->password = hash_internal_user_password('Mru@' . $empid . '2025');

    try {
        $user->id = $DB->insert_record('user', $user);
        $lecturer_map[$empid] = $user->id;
        $lecturers_created++;
    } catch (Exception $e) {
        cli_writeln("  [ERROR] User {$username} ({$fullname}): " . $e->getMessage());
    }
}
$result->free();

cli_writeln("  Lecturers created: {$lecturers_created}, already existed: {$lecturers_skipped}");

// ============================================================
// STEP 5: Enrol Lecturers in Their Courses.
// ============================================================
cli_writeln('');
cli_heading('Step 5: Enrolling lecturers in courses');

// Get the manual enrolment plugin instance and editing teacher role.
$enrolplugin = enrol_get_plugin('manual');
$teacherrole_id = $DB->get_field('role', 'id', ['shortname' => 'editingteacher']);

// Get DISTINCT lecturer-course pairs (not per academic year).
$result = $mrudb->query("
    SELECT DISTINCT ta.staffCode, ta.courseID, ta.progcode
    FROM acad_teaching_allocation ta
    JOIN acad_programme p ON ta.progcode = p.progcode
    WHERE p.faculty_code != '00'
      AND TRIM(ta.staffCode) != ''
      AND TRIM(ta.courseID) != ''
");

$enrolments_done = 0;
$enrolments_skipped = 0;
$enrolments_nomatch = 0;

// Cache manual enrol instances per course to avoid repeated lookups.
$enrol_instance_cache = [];

// Pre-load all existing user enrolments to avoid per-row DB queries.
$existing_enrolments = [];
$sql = "SELECT CONCAT(ue.userid, '_', e.courseid) as k
        FROM {user_enrolments} ue
        JOIN {enrol} e ON ue.enrolid = e.id";
$records = $DB->get_records_sql($sql);
foreach ($records as $rec) {
    $existing_enrolments[$rec->k] = true;
}
unset($records);

while ($row = $result->fetch_assoc()) {
    $empid = trim($row['staffCode']);
    $progcode = trim($row['progcode']);
    $coursecode = trim($row['courseID']);

    $mapkey = $progcode . '|' . $coursecode;

    if (!isset($lecturer_map[$empid]) || !isset($course_id_map[$mapkey])) {
        $enrolments_nomatch++;
        continue;
    }

    $userid = $lecturer_map[$empid];
    $courseid = $course_id_map[$mapkey];

    // Check if already enrolled using cached data.
    $enrolkey = $userid . '_' . $courseid;
    if (isset($existing_enrolments[$enrolkey])) {
        $enrolments_skipped++;
        continue;
    }

    // Get or create manual enrolment instance for this course.
    if (!isset($enrol_instance_cache[$courseid])) {
        $instances = $DB->get_records('enrol', ['courseid' => $courseid, 'enrol' => 'manual']);
        if ($instances) {
            $enrol_instance_cache[$courseid] = reset($instances);
        } else {
            $enrolid = $enrolplugin->add_instance(get_course($courseid));
            $enrol_instance_cache[$courseid] = $DB->get_record('enrol', ['id' => $enrolid]);
        }
    }
    $manualinstance = $enrol_instance_cache[$courseid];

    try {
        $enrolplugin->enrol_user($manualinstance, $userid, $teacherrole_id);
        $existing_enrolments[$enrolkey] = true;
        $enrolments_done++;
    } catch (Exception $e) {
        cli_writeln("  [ERROR] Enrol user {$userid} in course {$courseid}: " . $e->getMessage());
    }
}
$result->free();

cli_writeln("  Enrolments created: {$enrolments_done}, already enrolled: {$enrolments_skipped}, no match: {$enrolments_nomatch}");

// ============================================================
// STEP 6: Delete the default "Category 1" if empty.
// ============================================================
cli_writeln('');
cli_heading('Step 6: Cleanup');

$defaultcat = $DB->get_record('course_categories', ['id' => 1, 'name' => 'Category 1']);
if ($defaultcat) {
    $coursesinthat = $DB->count_records('course', ['category' => 1]);
    $subcats = $DB->count_records('course_categories', ['parent' => 1]);
    if ($coursesinthat == 0 && $subcats == 0) {
        $catobj = core_course_category::get($defaultcat->id);
        $catobj->delete_full(false);
        cli_writeln('  Deleted empty "Category 1" default category.');
    } else {
        cli_writeln('  "Category 1" is not empty, keeping it.');
    }
}

$mrudb->close();

cli_writeln('');
cli_heading('Import Complete!');
cli_writeln("  Faculty categories: " . count($faculty_category_map));
cli_writeln("  Programme sub-categories: " . count($programme_category_map));
cli_writeln("  Courses: {$courses_created} created");
cli_writeln("  Lecturers: {$lecturers_created} created");
cli_writeln("  Enrolments: {$enrolments_done} created");
