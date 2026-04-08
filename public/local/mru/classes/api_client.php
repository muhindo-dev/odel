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
 * Core API client for communicating with the MRU core system.
 *
 * Handles HTTP requests, authentication, and response parsing for
 * all interactions with the external MRU management system.
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

/**
 * HTTP client for the MRU core system REST API.
 */
class api_client {

    /** @var string Base URL of the core system API. */
    private string $baseurl;

    /** @var string API key for authentication. */
    private string $apikey;

    /** @var string API secret for authentication. */
    private string $apisecret;

    /** @var int Request timeout in seconds. */
    private int $timeout;

    /** @var string|null Cached auth token. */
    private ?string $token = null;

    /**
     * Constructor. Reads settings from plugin config.
     *
     * @param string|null $baseurl Override base URL (for testing).
     * @param string|null $apikey Override API key (for testing).
     * @param string|null $apisecret Override API secret (for testing).
     */
    public function __construct(?string $baseurl = null, ?string $apikey = null, ?string $apisecret = null) {
        $this->baseurl   = $baseurl   ?? get_config('local_mru', 'api_base_url');
        $this->apikey    = $apikey    ?? get_config('local_mru', 'api_key');
        $this->apisecret = $apisecret ?? get_config('local_mru', 'api_secret');
        $this->timeout   = (int) (get_config('local_mru', 'api_timeout') ?: 30);
    }

    /**
     * Check if the API client is configured.
     *
     * @return bool
     */
    public function is_configured(): bool {
        return !empty($this->baseurl) && !empty($this->apikey) && !empty($this->apisecret);
    }

    /**
     * Authenticate with the core system and obtain a token.
     *
     * @return string Bearer token.
     * @throws moodle_exception If authentication fails.
     */
    public function authenticate(): string {
        if ($this->token !== null) {
            return $this->token;
        }

        $response = $this->request('POST', '/auth/token', [
            'api_key'    => $this->apikey,
            'api_secret' => $this->apisecret,
        ], false);

        if (empty($response['token'])) {
            throw new moodle_exception('error:auth_failed', 'local_mru');
        }

        $this->token = $response['token'];
        return $this->token;
    }

    /**
     * Verify a student against the core system.
     *
     * @param string $mruid MRU student number.
     * @return array Student data from core system.
     * @throws moodle_exception
     */
    public function verify_student(string $mruid): array {
        return $this->request('GET', '/students/' . urlencode($mruid) . '/verify');
    }

    /**
     * Get student details from core system.
     *
     * @param string $mruid MRU student number.
     * @return array Student record.
     * @throws moodle_exception
     */
    public function get_student(string $mruid): array {
        return $this->request('GET', '/students/' . urlencode($mruid));
    }

    /**
     * Get multiple students by programme.
     *
     * @param string $programmecode MRU programme code.
     * @param string|null $academicyear Academic year filter.
     * @return array List of student records.
     * @throws moodle_exception
     */
    public function get_students_by_programme(string $programmecode, ?string $academicyear = null): array {
        $params = [];
        if ($academicyear !== null) {
            $params['academic_year'] = $academicyear;
        }
        return $this->request('GET', '/programmes/' . urlencode($programmecode) . '/students', $params);
    }

    /**
     * Submit marks/grades to the core system.
     *
     * @param array $marks Array of mark records with keys: student_id, course_code, mark, academic_year, semester.
     * @return array Response from core system.
     * @throws moodle_exception
     */
    public function submit_marks(array $marks): array {
        return $this->request('POST', '/marks/submit', ['marks' => $marks]);
    }

    /**
     * Get marks from the core system for a course.
     *
     * @param string $coursecode MRU course code.
     * @param string|null $academicyear Optional academic year filter.
     * @param int|null $semester Optional semester filter.
     * @return array List of mark records.
     * @throws moodle_exception
     */
    public function get_marks(string $coursecode, ?string $academicyear = null, ?int $semester = null): array {
        $params = [];
        if ($academicyear !== null) {
            $params['academic_year'] = $academicyear;
        }
        if ($semester !== null) {
            $params['semester'] = $semester;
        }
        return $this->request('GET', '/marks/' . urlencode($coursecode), $params);
    }

    /**
     * Get course details from core system.
     *
     * @param string $coursecode MRU course code.
     * @return array Course data.
     * @throws moodle_exception
     */
    public function get_course(string $coursecode): array {
        return $this->request('GET', '/courses/' . urlencode($coursecode));
    }

    /**
     * Get all programmes from core system.
     *
     * @return array List of programmes.
     * @throws moodle_exception
     */
    public function get_programmes(): array {
        return $this->request('GET', '/programmes');
    }

    /**
     * Ping the core system to check connectivity.
     *
     * @return bool True if reachable.
     */
    public function ping(): bool {
        try {
            $this->request('GET', '/ping', [], false);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Make an HTTP request to the core system API.
     *
     * @param string $method HTTP method (GET, POST, PUT, DELETE).
     * @param string $endpoint API endpoint path.
     * @param array $data Request data (query params for GET, body for POST).
     * @param bool $authenticated Whether to include auth token.
     * @return array Decoded JSON response.
     * @throws moodle_exception On failure.
     */
    private function request(string $method, string $endpoint, array $data = [], bool $authenticated = true): array {
        if (!$this->is_configured()) {
            throw new moodle_exception('error:not_configured', 'local_mru');
        }

        $url = rtrim($this->baseurl, '/') . $endpoint;

        $curl = new curl();
        $curl->setopt([
            'CURLOPT_TIMEOUT'        => $this->timeout,
            'CURLOPT_CONNECTTIMEOUT' => 10,
            'CURLOPT_RETURNTRANSFER' => true,
        ]);

        $headers = ['Accept: application/json'];

        if ($authenticated) {
            $token = $this->authenticate();
            $headers[] = 'Authorization: Bearer ' . $token;
        }

        if (in_array($method, ['POST', 'PUT'])) {
            $headers[] = 'Content-Type: application/json';
        }

        $curl->setHeader($headers);

        switch ($method) {
            case 'GET':
                if (!empty($data)) {
                    $url .= '?' . http_build_query($data);
                }
                $response = $curl->get($url);
                break;
            case 'POST':
                $response = $curl->post($url, json_encode($data));
                break;
            case 'PUT':
                $response = $curl->put($url, json_encode($data));
                break;
            case 'DELETE':
                $response = $curl->delete($url);
                break;
            default:
                throw new moodle_exception('error:invalid_method', 'local_mru', '', $method);
        }

        $httpcode = $curl->get_info()['http_code'] ?? 0;

        if ($httpcode === 401 && $authenticated && $this->token !== null) {
            // Token expired — retry once with fresh token.
            $this->token = null;
            return $this->request($method, $endpoint, $data, true);
        }

        if ($httpcode < 200 || $httpcode >= 300) {
            $decoded = json_decode($response, true);
            $msg = $decoded['message'] ?? "HTTP {$httpcode}: {$endpoint}";
            throw new moodle_exception('error:api_request_failed', 'local_mru', '', $msg);
        }

        $decoded = json_decode($response, true);
        if ($decoded === null && !empty($response)) {
            throw new moodle_exception('error:invalid_response', 'local_mru');
        }

        return $decoded ?? [];
    }
}
