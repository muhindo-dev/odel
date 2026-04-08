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

/**
 * Step 4: Account creation — name, password, create user, auto-login.
 *
 * @package    local_mru
 * @copyright  2026 Mutesa I Royal University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class step4 extends base_step {

    public function get_step_number(): int {
        return 4;
    }

    public function get_template(): string {
        return 'local_mru/register_step4';
    }

    public function handle_action(string $action): void {
        if ($action === 'createaccount') {
            $this->handle_create_account();
        }
    }

    private function handle_create_account(): void {
        $password  = required_param('password', PARAM_RAW);
        $confirm   = required_param('password_confirm', PARAM_RAW);
        $firstname = required_param('firstname', PARAM_TEXT);
        $lastname  = required_param('lastname', PARAM_TEXT);

        $errors = [];

        if (empty(trim($firstname))) {
            $errors[] = get_string('reg:firstname_required', 'local_mru');
        }
        if (empty(trim($lastname))) {
            $errors[] = get_string('reg:lastname_required', 'local_mru');
        }
        if ($password !== $confirm) {
            $errors[] = get_string('reg:pwd_mismatch', 'local_mru');
        }

        $pwderrors = $this->regmanager->validate_password($password);
        $errors = array_merge($errors, $pwderrors);

        if (!empty($errors)) {
            $this->redirect_to_wizard(implode(' ', $errors), 'error');
        }

        // Update session names.
        $this->session->firstname = trim($firstname);
        $this->session->lastname = trim($lastname);
        $this->regmanager->update_session($this->session);

        try {
            $user = $this->regmanager->create_account($this->session, $password);
            $this->regmanager->advance_step($this->session, 5);

            // Auto-login.
            $this->regmanager->login_user($user);
            $this->regmanager->destroy_session();

            redirect(new \moodle_url('/local/mru/register.php', ['step' => 5]));
        } catch (\Exception $e) {
            $this->redirect_to_wizard(
                get_string('reg:create_failed', 'local_mru') . ' ' . $e->getMessage(),
                'error'
            );
        }
    }

    public function get_template_data(): array {
        return [
            'email'     => $this->session->email ?? '',
            'firstname' => $this->session->firstname ?? '',
            'lastname'  => $this->session->lastname ?? '',
        ];
    }
}
