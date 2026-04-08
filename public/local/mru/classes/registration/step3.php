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
            // Save the portal data to the session so Step 4 can pre-fill name fields.
            $persontype = optional_param('person_type', '', PARAM_ALPHA);
            $mruid      = optional_param('mru_id', '', PARAM_RAW);
            $firstname  = optional_param('info_firstname', '', PARAM_TEXT);
            $lastname   = optional_param('info_lastname', '', PARAM_TEXT);
            $programme  = optional_param('info_programme', '', PARAM_TEXT);

            if (!empty($mruid)) {
                $this->session->core_data = json_encode([
                    'person_type'  => $persontype,
                    'mru_id'       => $mruid,
                    'firstname'    => $firstname,
                    'lastname'     => $lastname,
                    'programme'    => $programme,
                ]);
                $this->session->user_type = $persontype ?: 'student';
            }

            if (!empty($firstname)) {
                $this->session->firstname = $firstname;
            }
            if (!empty($lastname)) {
                $this->session->lastname = $lastname;
            }

            $this->regmanager->save_session($this->session);
            $this->regmanager->advance_step($this->session, 4);
            $this->redirect_to_wizard();

        } else if ($action === 'manuallookup') {
            // User typed their student/staff number manually.
            $idnumber = optional_param('id_number', '', PARAM_RAW);
            $idnumber = trim($idnumber);

            if (empty($idnumber)) {
                $this->redirect_to_wizard();
                return;
            }

            // Store the manual lookup ID in core_data JSON so get_template_data can use it.
            $coredata = !empty($this->session->core_data) ? json_decode($this->session->core_data, true) : [];
            $coredata['manual_lookup_id'] = $idnumber;
            $this->session->core_data = json_encode($coredata);
            $this->regmanager->save_session($this->session);
            $this->redirect_to_wizard();
        }
    }

    public function get_template_data(): array {
        $email = $this->session->email ?? '';
        $coredata = !empty($this->session->core_data) ? json_decode($this->session->core_data, true) : [];
        $manuallookupid = $coredata['manual_lookup_id'] ?? '';
        $data  = [
            'info_found'      => false,
            'info_firstname'  => '',
            'info_lastname'   => '',
            'info_student_no' => '',
            'info_programme'  => '',
            'info_status'     => '',
            'info_gender'     => '',
            'info_person_type' => '',
            'info_campus'     => '',
            'api_error'       => false,
            'api_error_message' => '',
            'api_not_configured' => false,
            'show_manual_lookup' => false,
            'manual_lookup_failed' => false,
            'manual_lookup_id' => $manuallookupid,
            'email'           => $email,
        ];

        if (empty($email)) {
            return $data;
        }

        $api = new api_client();
        if (!$api->is_configured()) {
            $data['api_not_configured'] = true;
            return $data;
        }

        // If user submitted a manual student/staff number, look them up by that.
        if (!empty($manuallookupid)) {
            try {
                // Use verify_student first (lightweight check).
                $result = $api->verify_student($manuallookupid);
                if (!empty($result['verified']) || !empty($result['full_name'])) {
                    // Get richer data via search by student_no.
                    try {
                        $searchresult = $api->search_students($manuallookupid, 'student_no', 1);
                        $results = $searchresult['results'] ?? [];
                        if (!empty($results[0])) {
                            $this->populate_student_data($data, $results[0], $email);
                        } else {
                            // Fallback to basic verify data.
                            $this->populate_from_verify($data, $result, $manuallookupid, $email);
                        }
                    } catch (\Exception $e2) {
                        $this->populate_from_verify($data, $result, $manuallookupid, $email);
                    }
                } else {
                    $data['manual_lookup_failed'] = true;
                    $data['show_manual_lookup'] = true;
                }
            } catch (\Exception $e) {
                $data['manual_lookup_failed'] = true;
                $data['show_manual_lookup'] = true;
            }

            // Clear the manual lookup ID from core_data after use.
            if (!empty($data['info_found'])) {
                // Keep the ID for reference but remove the lookup trigger.
                unset($coredata['manual_lookup_id']);
            }
            // If lookup failed, keep the ID so the form can show it again.
            $this->session->core_data = json_encode($coredata);
            $this->regmanager->save_session($this->session);

            return $data;
        }

        // Automatic lookup by email.
        try {
            $result = $api->lookup_person($email);

            if (!empty($result['found'])) {
                $person = $result['data'] ?? $result;
                $this->populate_person_data($data, $person, $result, $email);
            }
        } catch (\Exception $e) {
            // The lookup endpoint has a known DB bug (studemail column).
            // Fallback: extract the name part from the email and search by name.
            try {
                $localpart = explode('@', $email)[0];
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
            } catch (\Exception $e2) {
                // Both automatic methods failed — show manual lookup form.
            }
        }

        // If automatic lookup didn't find anything, show manual lookup form.
        if (empty($data['info_found'])) {
            $data['show_manual_lookup'] = true;
        }

        return $data;
    }

    /**
     * Populate template data from a person lookup result.
     */
    private function populate_person_data(array &$data, array $person, array $result, string $email): void {
        $data['info_found']       = true;
        $data['info_person_type'] = $result['person_type'] ?? 'student';
        $data['info_mru_id']      = $result['mru_id'] ?? ($person['regno'] ?? '');
        $data['info_firstname']   = $person['firstname'] ?? '';
        $data['info_lastname']    = $person['othername'] ?? ($person['surname'] ?? '');
        $data['info_student_no']  = $person['regno'] ?? ($result['mru_id'] ?? '');
        $data['info_programme']   = $person['programme'] ?? '';
        $data['info_progcode']    = $person['progcode'] ?? '';
        $data['info_status']      = $person['status'] ?? '';
        $data['info_gender']      = $person['gender'] ?? '';
        $data['info_campus']      = $person['campus'] ?? '';
        $data['info_email']       = $person['email'] ?? $email;
        $data['info_phone']       = $person['phone'] ?? '';
        $data['is_student']       = ($data['info_person_type'] === 'student');
        $data['is_staff']         = ($data['info_person_type'] === 'staff');
    }

    /**
     * Populate template data from a student search/profile result.
     */
    private function populate_student_data(array &$data, array $student, string $email): void {
        $data['info_found']       = true;
        $data['info_person_type'] = 'student';
        $data['info_mru_id']      = $student['regno'] ?? '';
        $data['info_firstname']   = $student['firstname'] ?? '';
        $data['info_lastname']    = $student['othername'] ?? ($student['surname'] ?? '');
        $data['info_student_no']  = $student['regno'] ?? '';
        $data['info_programme']   = $student['programme'] ?? ($student['progcode'] ?? '');
        $data['info_progcode']    = $student['progcode'] ?? '';
        $data['info_status']      = $student['status'] ?? '';
        $data['info_gender']      = $student['gender'] ?? '';
        $data['info_campus']      = $student['campus'] ?? '';
        $data['info_email']       = $student['email'] ?? $email;
        $data['info_phone']       = $student['phone'] ?? '';
        $data['is_student']       = true;
        $data['is_staff']         = false;
    }

    /**
     * Populate template data from verify_student result (less detail).
     */
    private function populate_from_verify(array &$data, array $result, string $id, string $email): void {
        $data['info_found']       = true;
        $data['info_person_type'] = 'student';
        $data['info_mru_id']      = $id;
        $data['info_student_no']  = $id;
        $fullname = $result['full_name'] ?? '';
        $nameparts = explode(' ', $fullname, 2);
        $data['info_firstname']   = $nameparts[0] ?? '';
        $data['info_lastname']    = $nameparts[1] ?? '';
        $data['info_programme']   = $result['programme'] ?? '';
        $data['info_status']      = $result['status'] ?? '';
        $data['info_email']       = $email;
        $data['is_student']       = true;
        $data['is_staff']         = false;
    }
}
