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

namespace local_mru\registration;

use local_mru\api_client;

/**
 * Step 3: Identity verification — looks up the verified email in the MRU portal API.
 *
 * @package    local_mru
 * @copyright  2026 Mutesa I Royal University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class step3 extends base_step {

    public function get_step_number(): int {
        return 3;
    }

    public function get_template(): string {
        return 'local_mru/register_step3';
    }

    public function handle_action(string $action): void {
        if ($action === 'confirminfo') {
            $this->handle_confirminfo();
        } else if ($action === 'manuallookup') {
            $this->handle_manuallookup();
        }
    }

    /**
     * User confirmed their identity — save portal data to session and advance.
     */
    private function handle_confirminfo(): void {
        $persontype = optional_param('person_type', '', PARAM_ALPHA);
        // Never trust the client-supplied MRU ID; only accept characters valid in student numbers.
        $mruid      = optional_param('mru_id', '', PARAM_ALPHANUMEXT);
        $firstname  = optional_param('info_firstname', '', PARAM_TEXT);
        $lastname   = optional_param('info_lastname', '', PARAM_TEXT);
        $programme  = optional_param('info_programme', '', PARAM_TEXT);

        if (!empty($mruid)) {
            $this->session->core_data = json_encode([
                'person_type' => $persontype,
                'mru_id'      => $mruid,
                'firstname'   => $firstname,
                'lastname'    => $lastname,
                'programme'   => $programme,
            ]);
            $this->session->user_type = $persontype ?: 'student';
        }

        if (!empty($firstname)) {
            $this->session->firstname = $firstname;
        }
        if (!empty($lastname)) {
            $this->session->lastname = $lastname;
        }

        $this->regmanager->update_session($this->session);
        $this->regmanager->advance_step($this->session, 4);
        $this->redirect_to_wizard();
    }

    /**
     * User submitted a manual student/staff number lookup.
     */
    private function handle_manuallookup(): void {
        $idnumber = trim(optional_param('id_number', '', PARAM_ALPHANUMEXT));

        if (empty($idnumber)) {
            $this->redirect_to_wizard();
            return;
        }

        $coredata = !empty($this->session->core_data)
            ? (json_decode($this->session->core_data, true) ?? [])
            : [];
        $coredata['manual_lookup_id'] = $idnumber;
        $this->session->core_data = json_encode($coredata);
        $this->regmanager->update_session($this->session);
        $this->redirect_to_wizard();
    }

    public function get_template_data(): array {
        $email = $this->session->email ?? '';
        $coredata = !empty($this->session->core_data)
            ? (json_decode($this->session->core_data, true) ?? [])
            : [];
        $manuallookupid = $coredata['manual_lookup_id'] ?? '';

        $data = [
            'info_found'           => false,
            'info_firstname'       => '',
            'info_lastname'        => '',
            'info_student_no'      => '',
            'info_programme'       => '',
            'info_status'          => '',
            'info_gender'          => '',
            'info_person_type'     => '',
            'info_campus'          => '',
            'api_error'            => false,
            'api_error_message'    => '',
            'api_not_configured'   => false,
            'show_manual_lookup'   => false,
            'manual_lookup_failed' => false,
            'manual_lookup_id'     => $manuallookupid,
            'email'                => $email,
        ];

        if (empty($email)) {
            return $data;
        }

        $api = new api_client();
        if (!$api->is_configured()) {
            $data['api_not_configured'] = true;
            $data['show_manual_lookup'] = true;
            return $data;
        }

        // Manual lookup by student/staff number takes priority over email lookup.
        if (!empty($manuallookupid)) {
            return $this->resolve_by_id($api, $manuallookupid, $email, $coredata, $data);
        }

        // Automatic lookup by email.
        return $this->resolve_by_email($api, $email, $data);
    }

    /**
     * Resolve identity by an explicit student/staff number.
     */
    private function resolve_by_id(
        api_client $api,
        string $idnumber,
        string $email,
        array $coredata,
        array $data
    ): array {
        try {
            $result = $api->verify_student($idnumber);
            if (!empty($result['verified']) || !empty($result['full_name'])) {
                // Try a richer search by student number first.
                try {
                    $searchresult = $api->search_students($idnumber, 'student_no', 1);
                    $results = $searchresult['results'] ?? [];
                    if (!empty($results[0])) {
                        $this->populate_student_data($data, $results[0], $email);
                    } else {
                        $this->populate_from_verify($data, $result, $idnumber, $email);
                    }
                } catch (\Throwable) {
                    $this->populate_from_verify($data, $result, $idnumber, $email);
                }
            } else {
                $data['manual_lookup_failed'] = true;
                $data['show_manual_lookup']   = true;
            }
        } catch (\Throwable $e) {
            debugging('MRU manual ID lookup failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            $data['manual_lookup_failed'] = true;
            $data['show_manual_lookup']   = true;
            $data['api_error']            = true;
            $data['api_error_message']    = get_string('reg:api_lookup_failed', 'local_mru');
        }

        // Clear lookup trigger from core_data once consumed.
        if (!empty($data['info_found'])) {
            unset($coredata['manual_lookup_id']);
        }
        $this->session->core_data = json_encode($coredata);
        $this->regmanager->update_session($this->session);

        return $data;
    }

    /**
     * Resolve identity automatically from the verified email address.
     */
    private function resolve_by_email(api_client $api, string $email, array $data): array {
        try {
            $result = $api->lookup_person($email);
            if (!empty($result['found'])) {
                $person = $result['data'] ?? $result;
                $this->populate_person_data($data, $person, $result, $email);
            }
        } catch (\Throwable $e) {
            debugging('MRU email lookup failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            // Fallback: derive a name from the email prefix and search.
            try {
                $localpart  = explode('@', $email)[0];
                $searchterm = str_replace(['.', '_', '-'], ' ', $localpart);
                $searchresult = $api->search_students($searchterm, 'name', 5);
                $results = $searchresult['results'] ?? [];
                $match = null;
                foreach ($results as $student) {
                    if (isset($student['email']) && strcasecmp($student['email'], $email) === 0) {
                        $match = $student;
                        break;
                    }
                }
                if ($match) {
                    $this->populate_student_data($data, $match, $email);
                }
            } catch (\Throwable $e2) {
                debugging('MRU name-search fallback failed: ' . $e2->getMessage(), DEBUG_DEVELOPER);
                $data['api_error']         = true;
                $data['api_error_message'] = get_string('reg:api_lookup_failed', 'local_mru');
            }
        }

        // If no record was found by any means, show the manual lookup form.
        if (empty($data['info_found'])) {
            $data['show_manual_lookup'] = true;
        }

        return $data;
    }

    // ─── Data population helpers ───

    private function populate_person_data(array &$data, array $person, array $result, string $email): void {
        $data['info_found']        = true;
        $data['info_person_type']  = $result['person_type'] ?? 'student';
        $data['info_mru_id']       = $result['mru_id'] ?? ($person['regno'] ?? '');
        $data['info_firstname']    = $person['firstname'] ?? '';
        $data['info_lastname']     = $person['othername'] ?? ($person['surname'] ?? '');
        $data['info_student_no']   = $person['regno'] ?? ($result['mru_id'] ?? '');
        $data['info_programme']    = $person['programme'] ?? '';
        $data['info_progcode']     = $person['progcode'] ?? '';
        $data['info_status']       = $person['status'] ?? '';
        $data['info_gender']       = $person['gender'] ?? '';
        $data['info_campus']       = $person['campus'] ?? '';
        $data['info_email']        = $person['email'] ?? $email;
        $data['info_phone']        = $person['phone'] ?? '';
        $data['is_student']        = ($data['info_person_type'] === 'student');
        $data['is_staff']          = ($data['info_person_type'] === 'staff');
    }

    private function populate_student_data(array &$data, array $student, string $email): void {
        $data['info_found']        = true;
        $data['info_person_type']  = 'student';
        $data['info_mru_id']       = $student['regno'] ?? '';
        $data['info_firstname']    = $student['firstname'] ?? '';
        $data['info_lastname']     = $student['othername'] ?? ($student['surname'] ?? '');
        $data['info_student_no']   = $student['regno'] ?? '';
        $data['info_programme']    = $student['programme'] ?? ($student['progcode'] ?? '');
        $data['info_progcode']     = $student['progcode'] ?? '';
        $data['info_status']       = $student['status'] ?? '';
        $data['info_gender']       = $student['gender'] ?? '';
        $data['info_campus']       = $student['campus'] ?? '';
        $data['info_email']        = $student['email'] ?? $email;
        $data['info_phone']        = $student['phone'] ?? '';
        $data['is_student']        = true;
        $data['is_staff']          = false;
    }

    private function populate_from_verify(array &$data, array $result, string $id, string $email): void {
        $data['info_found']        = true;
        $data['info_person_type']  = 'student';
        $data['info_mru_id']       = $id;
        $data['info_student_no']   = $id;
        $fullname  = $result['full_name'] ?? '';
        $nameparts = explode(' ', $fullname, 2);
        $data['info_firstname']    = $nameparts[0] ?? '';
        $data['info_lastname']     = $nameparts[1] ?? '';
        $data['info_programme']    = $result['programme'] ?? '';
        $data['info_status']       = $result['status'] ?? '';
        $data['info_email']        = $email;
        $data['is_student']        = true;
        $data['is_staff']          = false;
    }
}
