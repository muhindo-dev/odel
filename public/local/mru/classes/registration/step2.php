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

use local_mru\otp_manager;

/**
 * Step 2: Email verification — send OTP, verify code, resend.
 *
 * @package    local_mru
 * @copyright  2026 Mutesa I Royal University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class step2 extends base_step {

    /** @var otp_manager */
    private $otpmanager;

    public function __construct(\local_mru\registration_manager $regmanager, \stdClass $session) {
        parent::__construct($regmanager, $session);
        $this->otpmanager = new otp_manager();
    }

    public function get_step_number(): int {
        return 2;
    }

    public function get_template(): string {
        return 'local_mru/register_step2';
    }

    public function handle_action(string $action): void {
        switch ($action) {
            case 'sendotp':
                $this->handle_send_otp();
                break;
            case 'verifyotp':
                $this->handle_verify_otp();
                break;
            case 'resendotp':
                $this->handle_resend_otp();
                break;
        }
    }

    /**
     * Send OTP to the user's email.
     */
    private function handle_send_otp(): void {
        global $CFG;

        $email = required_param('email', PARAM_EMAIL);
        $email = strtolower(trim($email));

        if (!$this->regmanager->validate_mru_email($email)) {
            $this->redirect_to_wizard(get_string('reg:invalid_email', 'local_mru'), 'error');
        }

        if ($this->regmanager->email_already_registered($email)) {
            $this->redirect_to_wizard(get_string('reg:email_exists', 'local_mru'), 'error');
        }

        $this->session->email = $email;
        $this->regmanager->update_session($this->session);

        $otp = $this->otpmanager->generate($this->session);
        $this->session = $this->regmanager->get_session();

        $sent = $this->otpmanager->send_otp_email($email, $otp);

        if ($sent) {
            $this->redirect_to_wizard(get_string('reg:otp_sent', 'local_mru'), 'success');
        } else if ($CFG->debugdeveloper) {
            $this->redirect_to_wizard('DEV MODE — Email failed. Your OTP code is: ' . $otp, 'warning');
        } else {
            $this->redirect_to_wizard(get_string('reg:otp_send_failed', 'local_mru'), 'error');
        }
    }

    /**
     * Verify the OTP code.
     */
    private function handle_verify_otp(): void {
        $code = required_param('otp', PARAM_ALPHANUMEXT);
        $code = preg_replace('/[^0-9]/', '', $code);

        $result = $this->otpmanager->verify($this->session, $code);

        if ($result['valid']) {
            $this->session = $this->regmanager->get_session();
            $this->regmanager->advance_step($this->session, 3);
            $this->redirect_to_wizard(get_string('reg:email_verified', 'local_mru'), 'success');
        } else {
            $this->redirect_to_wizard($result['error'], 'error');
        }
    }

    /**
     * Resend the OTP code.
     */
    private function handle_resend_otp(): void {
        global $CFG;

        if (!$this->otpmanager->can_resend($this->session)) {
            $this->redirect_to_wizard(get_string('reg:otp_cooldown', 'local_mru'), 'warning');
        }

        $otp = $this->otpmanager->generate($this->session);
        $this->session = $this->regmanager->get_session();
        $sent = $this->otpmanager->send_otp_email($this->session->email, $otp);

        if ($sent) {
            $this->redirect_to_wizard(get_string('reg:otp_resent', 'local_mru'), 'success');
        } else if ($CFG->debugdeveloper) {
            $this->redirect_to_wizard('DEV MODE — Email failed. Your OTP code is: ' . $otp, 'warning');
        } else {
            $this->redirect_to_wizard(get_string('reg:otp_send_failed', 'local_mru'), 'error');
        }
    }

    public function get_template_data(): array {
        return [
            'email'          => $this->session->email ?? '',
            'otp_sent'       => !empty($this->session->otp_hash),
            'email_verified' => (bool) ($this->session->email_verified ?? false),
        ];
    }
}
