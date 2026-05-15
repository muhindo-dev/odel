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
 * Campus Dynamics API v2.2 client for the MRU core system.
 *
 * Base URL: https://eadmin.mru.ac.ug/API/v2/{endpoint}.aspx?action={action}
 * Auth: username/password → token sent via Authorization: Bearer header by default.
 * Set admin setting api_token_in_header=0 to fall back to query-string if the
 * upstream API does not support bearer-header authentication.
 *
 * @package    local_mru
 * @copyright  2026 Mutesa I Royal University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mru;

use curl;
use moodle_exception;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/filelib.php');

class api_client {

    /** @var string Base URL (e.g. https://eadmin.mru.ac.ug/API/v2). */
    private string $baseurl;

    /** @var string Staff username for system-level API access. */
    private string $username;

    /** @var string Staff password. */
    private string $password;

    /** @var int Request timeout in seconds. */
    private int $timeout;

    /** @var string|null Cached auth token (in-request memory only). */
    private ?string $token = null;

    /**
     * Send the auth token as Authorization: Bearer rather than a query-string parameter.
     * Bearer header prevents the token from appearing in server access logs.
     * Disable via plugin setting api_token_in_header=0 if the upstream API requires QS only.
     */
    private bool $useheaderauth;

    public function __construct(?string $baseurl = null, ?string $username = null, ?string $password = null) {
        $this->baseurl  = rtrim($baseurl  ?? get_config('local_mru', 'api_base_url'), '/');
        $this->username = $username ?? get_config('local_mru', 'api_username');
        $this->password = $password ?? get_config('local_mru', 'api_password');
        $this->timeout  = (int) (get_config('local_mru', 'api_timeout') ?: 30);
        $headerauth = get_config('local_mru', 'api_token_in_header');
        // Default true: use header. Only disable when admin explicitly sets to '0'.
        $this->useheaderauth = ($headerauth === '0' || $headerauth === false) ? false : true;
    }

    public function is_configured(): bool {
        return !empty($this->baseurl) && !empty($this->username) && !empty($this->password);
    }

    // ─── Authentication ───

    public function authenticate(): string {
        if ($this->token !== null) {
            return $this->token;
        }

        // Check Moodle application cache first to avoid re-authenticating on every request.
        $cache = \cache::make('local_mru', 'apitokens');
        $cached = $cache->get('system_token');
        if ($cached && !empty($cached['token']) && time() < ($cached['expires'] ?? 0)) {
            $this->token = $cached['token'];
            return $this->token;
        }

        $url = $this->baseurl . '/auth.aspx?action=login';

        $curl = new curl();
        $curl->setopt([
            'CURLOPT_TIMEOUT'        => $this->timeout,
            'CURLOPT_CONNECTTIMEOUT' => 10,
            'CURLOPT_RETURNTRANSFER' => true,
        ]);
        $curl->setHeader(['Accept: application/json', 'Content-Type: application/x-www-form-urlencoded']);

        $postdata = 'username=' . urlencode($this->username) . '&password=' . urlencode($this->password);
        $response = $curl->post($url, $postdata);

        $decoded = json_decode($response, true);
        if (empty($decoded['success']) || empty($decoded['data']['token'])) {
            $msg = $decoded['message'] ?? 'Authentication failed';
            throw new moodle_exception('error:auth_failed', 'local_mru', '', $msg);
        }

        $this->token = $decoded['data']['token'];

        // Cache the token. Use a 50-minute TTL (tokens typically expire at 60 minutes).
        $cache->set('system_token', ['token' => $this->token, 'expires' => time() + 3000]);

        return $this->token;
    }

    public function validate_token(): bool {
        if ($this->token === null) {
            return false;
        }
        try {
            $result = $this->request('auth.aspx', 'validate', [], false);
            return !empty($result);
        } catch (\Throwable) {
            return false;
        }
    }

    public function ping(): bool {
        try {
            $url = $this->baseurl . '/auth.aspx?action=ping';
            $curl = new curl();
            $curl->setopt(['CURLOPT_TIMEOUT' => 10, 'CURLOPT_CONNECTTIMEOUT' => 5]);
            $response = $curl->get($url);
            $decoded = json_decode($response, true);
            return !empty($decoded['success']) || (!empty($decoded['data']['status']) && $decoded['data']['status'] === 'ok');
        } catch (\Throwable) {
            return false;
        }
    }

    // ─── ODEL Identity & Lookup ───

    public function lookup_person(string $email): array {
        return $this->request('student.aspx', 'lookup', ['email' => $email]);
    }

    public function verify_student(string $id): array {
        return $this->request('student.aspx', 'verify', ['id' => $id]);
    }

    public function search_students(string $query, string $type = 'any', int $limit = 50): array {
        return $this->request('student.aspx', 'search', ['q' => $query, 'type' => $type, 'limit' => $limit]);
    }

    public function get_students_by_programme(string $progcode, ?string $status = null,
            ?string $acadyear = null, int $page = 1, int $perpage = 100): array {
        $params = ['progcode' => $progcode, 'page' => $page, 'per_page' => $perpage];
        if ($status !== null) {
            $params['status'] = $status;
        }
        if ($acadyear !== null) {
            $params['acad_year'] = $acadyear;
        }
        return $this->request('student.aspx', 'by_programme', $params);
    }

    // ─── Student Profile ───

    public function get_student_profile(?string $token = null): array {
        return $this->request('student.aspx', 'profile', [], true, $token);
    }

    public function get_student_summary(?string $token = null): array {
        return $this->request('student.aspx', 'summary', [], true, $token);
    }

    public function get_student_lock_status(?string $token = null): array {
        return $this->request('student.aspx', 'lock_status', [], true, $token);
    }

    // ─── Staff ───

    public function get_staff_profile(?string $token = null): array {
        return $this->request('staff.aspx', 'profile', [], true, $token);
    }

    public function get_staff_courses(string $acadyear, int $semester, ?string $token = null): array {
        return $this->request('staff.aspx', 'my_courses', [
            'acad_year' => $acadyear, 'semester' => $semester,
        ], true, $token);
    }

    public function get_class_list(string $courseid, string $acadyear, int $semester): array {
        return $this->request('staff.aspx', 'class_list', [
            'course_id' => $courseid, 'acad_year' => $acadyear, 'semester' => $semester,
        ]);
    }

    // ─── Marks & Grading ───

    public function get_marks(string $courseid, string $acadyear, int $semester): array {
        return $this->request('staff.aspx', 'marks', [
            'course_id' => $courseid, 'acad_year' => $acadyear, 'semester' => $semester,
        ]);
    }

    public function submit_marks(array $marks): array {
        return $this->request('staff.aspx', 'submit_marks', $marks, true, null, 'POST');
    }

    public function get_mark_sheet(string $courseid, string $progid, string $acadyear,
            int $semester = 1, int $studyyear = 1): array {
        return $this->request('staff.aspx', 'mark_sheet', [
            'course_id' => $courseid, 'progid' => $progid, 'acad_year' => $acadyear,
            'semester' => $semester, 'study_year' => $studyyear,
        ]);
    }

    public function save_entry_marks(array $marks): array {
        return $this->request('staff.aspx', 'save_entry_marks', $marks, true, null, 'POST');
    }

    public function submit_for_approval(string $courseid, string $progid,
            string $acadyear, int $semester = 1): array {
        return $this->request('staff.aspx', 'submit_for_approval', [
            'course_id' => $courseid, 'progid' => $progid,
            'acad_year' => $acadyear, 'semester' => $semester,
        ], true, null, 'POST');
    }

    public function get_sheet_status(string $courseid, string $progid, string $acadyear): array {
        return $this->request('staff.aspx', 'sheet_status', [
            'course_id' => $courseid, 'progid' => $progid, 'acad_year' => $acadyear,
        ]);
    }

    public function get_deadlines(string $acadyear, int $semester): array {
        return $this->request('staff.aspx', 'deadlines', [
            'acad_year' => $acadyear, 'semester' => $semester,
        ]);
    }

    // ─── Academic ───

    public function get_results(?string $token = null, ?string $acadyear = null, ?int $semester = null): array {
        $params = [];
        if ($acadyear !== null) {
            $params['acad_year'] = $acadyear;
        }
        if ($semester !== null) {
            $params['semester'] = $semester;
        }
        return $this->request('academic.aspx', 'results', $params, true, $token);
    }

    public function get_transcript(?string $token = null): array {
        return $this->request('academic.aspx', 'transcript', [], true, $token);
    }

    public function get_registered_courses(string $acadyear, int $semester, ?string $token = null): array {
        return $this->request('academic.aspx', 'registered_courses', [
            'acad_year' => $acadyear, 'semester' => $semester,
        ], true, $token);
    }

    public function get_available_courses(string $acadyear, int $semester, ?string $token = null): array {
        return $this->request('academic.aspx', 'available_courses', [
            'acad_year' => $acadyear, 'semester' => $semester,
        ], true, $token);
    }

    public function get_course_details(string $coursecode): array {
        return $this->request('academic.aspx', 'course_details', ['course_code' => $coursecode]);
    }

    public function get_course_enrollments(string $coursecode, string $acadyear, int $semester): array {
        return $this->request('academic.aspx', 'course_enrollments', [
            'course_code' => $coursecode, 'acad_year' => $acadyear, 'semester' => $semester,
        ]);
    }

    public function get_programme_curriculum(string $progcode): array {
        return $this->request('academic.aspx', 'programme_curriculum', ['progcode' => $progcode]);
    }

    public function get_grading_scheme(): array {
        $url = $this->baseurl . '/academic.aspx?action=grading_scheme';
        $curl = new curl();
        $curl->setopt(['CURLOPT_TIMEOUT' => $this->timeout, 'CURLOPT_CONNECTTIMEOUT' => 10]);
        $response = $curl->get($url);
        $decoded = json_decode($response, true);
        return $decoded['data'] ?? [];
    }

    public function get_enrollment_status(?string $token = null, ?string $acadyear = null): array {
        $params = [];
        if ($acadyear !== null) {
            $params['acad_year'] = $acadyear;
        }
        return $this->request('academic.aspx', 'enrollment_status', $params, true, $token);
    }

    public function get_registration_history(?string $token = null): array {
        return $this->request('academic.aspx', 'registration_history', [], true, $token);
    }

    // ─── Finance ───

    public function get_fee_status(?string $regno = null, ?string $acadyear = null, ?int $semester = null): array {
        $params = [];
        if ($regno !== null) {
            $params['regno'] = $regno;
        }
        if ($acadyear !== null) {
            $params['acad_year'] = $acadyear;
        }
        if ($semester !== null) {
            $params['semester'] = $semester;
        }
        return $this->request('finance.aspx', 'fee_status', $params);
    }

    public function bulk_fee_check(array $students, ?string $acadyear = null): array {
        $params = ['students' => $students];
        if ($acadyear !== null) {
            $params['acad_year'] = $acadyear;
        }
        return $this->request('finance.aspx', 'bulk_fee_check', $params, true, null, 'POST');
    }

    public function get_ledger(?string $token = null): array {
        return $this->request('finance.aspx', 'ledger', [], true, $token);
    }

    public function get_balance(?string $token = null): array {
        return $this->request('finance.aspx', 'balance', [], true, $token);
    }

    public function get_billing_summary(?string $token = null): array {
        return $this->request('finance.aspx', 'billing_summary', [], true, $token);
    }

    // ─── Campus Info (no auth required) ───

    public function get_current_semester(): array {
        $url = $this->baseurl . '/campus.aspx?action=current_semester';
        $curl = new curl();
        $curl->setopt(['CURLOPT_TIMEOUT' => $this->timeout, 'CURLOPT_CONNECTTIMEOUT' => 10]);
        $response = $curl->get($url);
        $decoded = json_decode($response, true);
        return $decoded['data'] ?? [];
    }

    public function get_academic_years(): array {
        $url = $this->baseurl . '/campus.aspx?action=academic_years';
        $curl = new curl();
        $curl->setopt(['CURLOPT_TIMEOUT' => $this->timeout, 'CURLOPT_CONNECTTIMEOUT' => 10]);
        $response = $curl->get($url);
        $decoded = json_decode($response, true);
        return $decoded['data'] ?? [];
    }

    public function get_academic_calendar(?string $acadyear = null): array {
        $url = $this->baseurl . '/campus.aspx?action=academic_calendar';
        if ($acadyear !== null) {
            $url .= '&acad_year=' . urlencode($acadyear);
        }
        $curl = new curl();
        $curl->setopt(['CURLOPT_TIMEOUT' => $this->timeout, 'CURLOPT_CONNECTTIMEOUT' => 10]);
        $response = $curl->get($url);
        $decoded = json_decode($response, true);
        return $decoded['data'] ?? [];
    }

    public function get_campuses(): array {
        $url = $this->baseurl . '/campus.aspx?action=campuses';
        $curl = new curl();
        $curl->setopt(['CURLOPT_TIMEOUT' => $this->timeout, 'CURLOPT_CONNECTTIMEOUT' => 10]);
        $response = $curl->get($url);
        $decoded = json_decode($response, true);
        return $decoded['data'] ?? [];
    }

    public function get_faculties(): array {
        $url = $this->baseurl . '/campus.aspx?action=faculties';
        $curl = new curl();
        $curl->setopt(['CURLOPT_TIMEOUT' => $this->timeout, 'CURLOPT_CONNECTTIMEOUT' => 10]);
        $response = $curl->get($url);
        $decoded = json_decode($response, true);
        return $decoded['data'] ?? [];
    }

    public function get_programmes(?string $facultycode = null, ?string $level = null): array {
        $url = $this->baseurl . '/campus.aspx?action=programmes';
        if ($facultycode !== null) {
            $url .= '&faculty_code=' . urlencode($facultycode);
        }
        if ($level !== null) {
            $url .= '&level=' . urlencode($level);
        }
        $curl = new curl();
        $curl->setopt(['CURLOPT_TIMEOUT' => $this->timeout, 'CURLOPT_CONNECTTIMEOUT' => 10]);
        $response = $curl->get($url);
        $decoded = json_decode($response, true);
        return $decoded['data'] ?? [];
    }

    public function get_departments(?string $facultycode = null): array {
        $url = $this->baseurl . '/campus.aspx?action=departments';
        if ($facultycode !== null) {
            $url .= '&faculty_code=' . urlencode($facultycode);
        }
        $curl = new curl();
        $curl->setopt(['CURLOPT_TIMEOUT' => $this->timeout, 'CURLOPT_CONNECTTIMEOUT' => 10]);
        $response = $curl->get($url);
        $decoded = json_decode($response, true);
        return $decoded['data'] ?? [];
    }

    // ─── Core Request Method ───

    /**
     * Make an authenticated request to the Campus Dynamics API.
     *
     * @param string      $endpoint      Filename (e.g. 'student.aspx')
     * @param string      $action        Action parameter value
     * @param array       $params        Extra query/body parameters
     * @param bool        $authenticated Whether to include auth token
     * @param string|null $overridetoken Use a specific token instead of the system one
     * @param string      $method        HTTP method ('GET' or 'POST')
     * @param int         $retries       Internal retry counter — do not pass externally
     * @return array The 'data' portion of the JSON response
     * @throws moodle_exception On authentication failure or bad response
     */
    private function request(
        string $endpoint,
        string $action,
        array $params = [],
        bool $authenticated = true,
        ?string $overridetoken = null,
        string $method = 'GET',
        int $retries = 0
    ): array {
        $url = $this->baseurl . '/' . $endpoint . '?action=' . urlencode($action);

        $headers = ['Accept: application/json'];

        if ($authenticated) {
            $token = $overridetoken ?? $this->authenticate();

            if ($this->useheaderauth) {
                // Preferred: token in header keeps it out of server access logs.
                $headers[] = 'Authorization: Bearer ' . $token;
            } else {
                // Fallback for legacy APIs that only accept token in query string.
                $url .= '&token=' . urlencode($token);
            }
        }

        $curl = new curl();
        $curl->setopt([
            'CURLOPT_TIMEOUT'        => $this->timeout,
            'CURLOPT_CONNECTTIMEOUT' => 10,
            'CURLOPT_RETURNTRANSFER' => true,
        ]);

        if ($method === 'POST') {
            $headers[] = 'Content-Type: application/json';
            $curl->setHeader($headers);
            $response = $curl->post($url, json_encode($params));
        } else {
            $curl->setHeader($headers);
            foreach ($params as $key => $value) {
                $url .= '&' . urlencode((string)$key) . '=' . urlencode((string)$value);
            }
            $response = $curl->get($url);
        }

        $httpcode = (int) ($curl->get_info()['http_code'] ?? 0);

        // Token expired — retry once with a fresh token.
        if ($httpcode === 401 && $authenticated && $overridetoken === null && $retries === 0) {
            $this->token = null;
            // Also clear the cache so authenticate() fetches a new token.
            \cache::make('local_mru', 'apitokens')->delete('system_token');
            return $this->request($endpoint, $action, $params, true, null, $method, 1);
        }

        $decoded = json_decode($response, true);
        if ($decoded === null && !empty($response)) {
            throw new moodle_exception('error:invalid_response', 'local_mru');
        }

        if (isset($decoded['success']) && $decoded['success'] === false) {
            $msg  = $decoded['message'] ?? 'Unknown API error';
            $code = $decoded['error_code'] ?? 'UNKNOWN';
            throw new moodle_exception('error:api_request_failed', 'local_mru', '', "{$code}: {$msg}");
        }

        return $decoded['data'] ?? $decoded ?? [];
    }
}
