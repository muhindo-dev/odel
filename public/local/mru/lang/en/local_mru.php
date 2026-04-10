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
 * English language strings for local_mru.
 *
 * @package    local_mru
 * @copyright  2026 Mutesa I Royal University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Plugin.
$string['pluginname'] = 'MRU Integration';

// Settings - API.
$string['settings:api_heading'] = 'Campus Dynamics API';
$string['settings:api_heading_desc'] = 'Configure the connection to the MRU Campus Dynamics API v2 (eadmin.mru.ac.ug).';
$string['settings:api_base_url'] = 'API base URL';
$string['settings:api_base_url_desc'] = 'The base URL of the Campus Dynamics API (e.g. https://eadmin.mru.ac.ug/API/v2).';
$string['settings:api_username'] = 'API username';
$string['settings:api_username_desc'] = 'Staff username for system-level API access (used by Moodle to authenticate with the portal).';
$string['settings:api_password'] = 'API password';
$string['settings:api_password_desc'] = 'Password for the API staff account.';
$string['settings:api_timeout'] = 'Request timeout (seconds)';
$string['settings:api_timeout_desc'] = 'Maximum time to wait for API responses.';

// Settings - Database.
$string['settings:db_heading'] = 'MRU Database';
$string['settings:db_heading_desc'] = 'Configure direct database connection to the MRU core system for data import.';
$string['settings:db_host'] = 'Database host';
$string['settings:db_host_desc'] = 'Hostname or IP of the MRU database server.';
$string['settings:db_port'] = 'Database port';
$string['settings:db_port_desc'] = 'MySQL port number.';
$string['settings:db_name'] = 'Database name';
$string['settings:db_name_desc'] = 'Name of the MRU database (typically mru_main).';
$string['settings:db_user'] = 'Database username';
$string['settings:db_user_desc'] = 'Username for the MRU database connection.';
$string['settings:db_pass'] = 'Database password';
$string['settings:db_pass_desc'] = 'Password for the MRU database connection.';
$string['settings:db_socket'] = 'Database socket';
$string['settings:db_socket_desc'] = 'MySQL socket path (leave empty to use TCP).';

// Settings - Sync.
$string['settings:sync_heading'] = 'Synchronisation';
$string['settings:sync_heading_desc'] = 'Configure automatic data synchronisation between Moodle and the MRU core system.';
$string['settings:sync_enabled'] = 'Enable synchronisation';
$string['settings:sync_enabled_desc'] = 'Enable automatic data sync between Moodle and the MRU core system.';
$string['settings:sync_frequency'] = 'Sync frequency';
$string['settings:sync_frequency_desc'] = 'How often should automatic sync run.';
$string['settings:freq_hourly'] = 'Hourly';
$string['settings:freq_daily'] = 'Daily';
$string['settings:freq_weekly'] = 'Weekly';
$string['settings:sync_students'] = 'Sync students';
$string['settings:sync_students_desc'] = 'Import and update student records from the MRU core system.';
$string['settings:sync_marks'] = 'Sync marks';
$string['settings:sync_marks_desc'] = 'Synchronise grades/marks between Moodle and the core system.';
$string['settings:sync_courses'] = 'Sync courses';
$string['settings:sync_courses_desc'] = 'Automatically map Moodle courses to MRU course codes.';

// Settings - Verification.
$string['settings:verification_heading'] = 'Student Verification';
$string['settings:verification_heading_desc'] = 'Configure when and how student records are verified against the MRU core system.';
$string['settings:verify_on_login'] = 'Verify on login';
$string['settings:verify_on_login_desc'] = 'Re-verify student status each time they log in.';
$string['settings:verify_on_enrol'] = 'Verify on enrolment';
$string['settings:verify_on_enrol_desc'] = 'Verify student status when they are enrolled in a course.';

// Capabilities.
$string['mru:manage'] = 'Manage MRU integration';
$string['mru:viewreports'] = 'View MRU sync reports';
$string['mru:syncmarks'] = 'Sync marks to MRU core system';
$string['mru:verifystudents'] = 'Verify students against MRU core system';
$string['mru:viewownstatus'] = 'View own MRU verification status';

// Tasks.
$string['task:sync_all'] = 'Full MRU data sync';
$string['task:verify_students'] = 'Bulk student verification';

// Navigation.
$string['nav:mystatus'] = 'MRU Status';
$string['nav:course_sync'] = 'MRU Marks Sync';

// Dashboard.
$string['dashboard:title'] = 'MRU Integration Dashboard';
$string['dashboard:sync_disabled'] = 'Synchronisation is currently disabled.';
$string['dashboard:configure'] = 'Configure settings';
$string['dashboard:users_mapped'] = 'Users Mapped';
$string['dashboard:verified'] = 'Verified Students';
$string['dashboard:unverified'] = 'Unverified Students';
$string['dashboard:courses_mapped'] = 'Courses Mapped';
$string['dashboard:pending'] = 'Pending Syncs';
$string['dashboard:failed'] = 'Failed Syncs';
$string['dashboard:last_sync'] = 'Last sync';
$string['dashboard:run_sync'] = 'Run Full Sync Now';
$string['dashboard:settings'] = 'Settings';
$string['dashboard:recent_syncs'] = 'Recent Sync Operations';
$string['dashboard:no_logs'] = 'No sync operations have been performed yet.';
$string['dashboard:sync_complete'] = 'Full sync completed successfully.';
$string['dashboard:col_type'] = 'Type';
$string['dashboard:col_direction'] = 'Direction';
$string['dashboard:col_status'] = 'Status';
$string['dashboard:col_processed'] = 'Processed';
$string['dashboard:col_success'] = 'Success';
$string['dashboard:col_failed'] = 'Failed';
$string['dashboard:col_time'] = 'Time';

// Course sync.
$string['coursesync:title'] = 'MRU Marks Sync';
$string['coursesync:mapping'] = 'Course Mapping';
$string['coursesync:course_code'] = 'MRU Course Code';
$string['coursesync:programme'] = 'Programme';
$string['coursesync:credits'] = 'Credit Units';
$string['coursesync:semester'] = 'Semester';
$string['coursesync:academic_year'] = 'Academic Year';
$string['coursesync:sync_now'] = 'Sync Marks Now';
$string['coursesync:not_mapped'] = 'This course is not mapped to an MRU course code. Please contact an administrator.';
$string['coursesync:history'] = 'Sync History';
$string['coursesync:col_student'] = 'Student ID';
$string['coursesync:col_grade'] = 'Grade';
$string['coursesync:col_status'] = 'Status';
$string['coursesync:col_time'] = 'Time';
$string['coursesync:success'] = 'Successfully synced {$a} mark(s) to the core system.';
$string['coursesync:failed'] = 'Sync failed: {$a}';

// Status page.
$string['status:title'] = 'My MRU Status';
$string['status:heading'] = 'MRU Verification Status';
$string['status:mru_id'] = 'MRU Student/Staff Number';
$string['status:user_type'] = 'Account Type';
$string['status:verification'] = 'Verification Status';
$string['status:verified'] = 'Verified';
$string['status:not_verified'] = 'Not Verified';
$string['status:no_mapping'] = 'Your Moodle account is not yet linked to an MRU record. Please contact the administrator if you believe this is an error.';

// Verification messages.
$string['verification:success_api'] = 'Student verified successfully via core system API.';
$string['verification:success_db'] = 'Student verified successfully via MRU database.';
$string['verification:inactive'] = 'Student record found but status is not active.';
$string['verification:not_found'] = 'No student record found with this ID in the MRU system.';
$string['verification:error'] = 'An error occurred during verification. Please try again later.';

// Errors.
$string['error:auth_failed'] = 'Failed to authenticate with the MRU core system API.';
$string['error:not_configured'] = 'The MRU core system API is not configured. Please configure it in the plugin settings.';
$string['error:api_request_failed'] = 'API request failed: {$a}';
$string['error:invalid_response'] = 'Invalid response received from the MRU core system API.';
$string['error:invalid_method'] = 'Invalid HTTP method: {$a}';
$string['error:db_not_configured'] = 'The MRU database connection is not configured.';
$string['error:db_connect_failed'] = 'Failed to connect to the MRU database.';
$string['error:db_query_failed'] = 'MRU database query failed: {$a}';
$string['error:course_not_mapped'] = 'This course is not mapped to an MRU course code.';
$string['error:no_grade_item'] = 'No course total grade item found for this course.';

// Privacy.
$string['privacy:metadata:local_mru_user_map'] = 'Maps Moodle users to MRU core system identifiers for integration purposes.';
$string['privacy:metadata:local_mru_user_map:userid'] = 'The Moodle user ID.';
$string['privacy:metadata:local_mru_user_map:mru_id'] = 'The user\'s MRU student or staff number.';
$string['privacy:metadata:local_mru_user_map:verified'] = 'Whether the mapping has been verified.';
$string['privacy:metadata:local_mru_marks_sync'] = 'Tracks marks synchronisation between Moodle and the MRU core system.';
$string['privacy:metadata:local_mru_marks_sync:userid'] = 'The Moodle user ID.';
$string['privacy:metadata:local_mru_marks_sync:moodle_grade'] = 'The grade value from Moodle.';
$string['privacy:metadata:core_system'] = 'Data is shared with the MRU core management system for student verification and marks synchronisation.';
$string['privacy:metadata:core_system:student_id'] = 'The student\'s MRU number is sent for verification.';
$string['privacy:metadata:core_system:marks'] = 'Course grades are sent for marks submission.';

// ── Registration wizard ──
$string['reg:page_title'] = 'Create Account — MRU ODEL';
$string['reg:step1_name'] = 'Intro';
$string['reg:step2_name'] = 'Email';
$string['reg:step3_name'] = 'Identity';
$string['reg:step4_name'] = 'Account';
$string['reg:step5_name'] = 'Done';
$string['reg:invalid_email'] = 'Please enter a valid MRU email address ending with @mru.ac.ug.';
$string['reg:email_exists'] = 'An account with this email address already exists. Please log in instead.';
$string['reg:otp_sent'] = 'A 6-digit verification code has been sent to {$a}. Please check your inbox.';
$string['reg:otp_send_failed'] = 'We could not send the verification email. Please try again or contact ODEL support.';
$string['reg:otp_expired'] = 'This verification code has expired. Please request a new one.';
$string['reg:otp_invalid'] = 'The code you entered is incorrect. Please try again.';
$string['reg:otp_max_attempts'] = 'Too many failed attempts. Please request a new verification code.';
$string['reg:otp_not_sent'] = 'Please request a verification code first.';
$string['reg:otp_cooldown'] = 'Please wait {$a} seconds before requesting a new code.';
$string['reg:otp_resent'] = 'A new verification code has been sent to {$a}.';
$string['reg:email_verified'] = 'Your email has been verified. You can continue.';
$string['reg:pwd_min_length'] = 'Password must be at least 4 characters long.';
$string['reg:pwd_uppercase'] = 'Password must contain at least one uppercase letter.';
$string['reg:pwd_lowercase'] = 'Password must contain at least one lowercase letter.';
$string['reg:pwd_number'] = 'Password must contain at least one number.';
$string['reg:pwd_mismatch'] = 'Passwords do not match.';
$string['reg:firstname_required'] = 'First name is required.';
$string['reg:lastname_required'] = 'Last name is required.';
$string['reg:create_failed'] = 'Account creation failed: {$a}';
$string['reg:otp_email_subject'] = 'MRU ODEL — Your Verification Code';
$string['reg:otp_email_body'] = 'Your MRU ODEL verification code is: {$a->otp}

This code expires in 3 hours. If you did not request this, please ignore this email.';
$string['reg:otp_email_body_html'] = '<p>Your MRU ODEL verification code is:</p><p style="font-size:24px;font-weight:bold;letter-spacing:4px;">{$a->otp}</p><p>This code expires in 3 hours. If you did not request this, please ignore this email.</p>';
$string['reg:privacy_registrations'] = 'Stores registration session data including email addresses and OTP hashes during the registration process.';
$string['reg:privacy_registrations_email'] = 'The email address provided during registration.';
$string['reg:privacy_registrations_ip'] = 'The IP address recorded at registration time.';

// ── Migrations ──
$string['migrations:title'] = 'Database Migrations';
$string['migrations:run_pending'] = 'Run {$a} pending migration(s)';
$string['migrations:rollback_last'] = 'Rollback last batch';
$string['migrations:none_found'] = 'No migration files found. Create one with: php local/mru/cli/migrate.php --action=create --name=your_migration';
$string['migrations:col_migration'] = 'Migration';
$string['migrations:col_status'] = 'Status';
$string['migrations:col_batch'] = 'Batch';
$string['migrations:col_time'] = 'Duration';
$string['migrations:col_applied'] = 'Applied';
$string['migrations:col_description'] = 'Description';
$string['migrations:cli_hint_title'] = 'CLI Commands (recommended for production):';
$string['event:migration_executed'] = 'Migration action executed';
