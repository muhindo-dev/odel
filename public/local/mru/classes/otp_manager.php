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
 * OTP manager for MRU ODEL registration.
 *
 * Handles OTP generation, hashing, verification, rate limiting, and email delivery.
 *
 * @package    local_mru
 * @copyright  2026 Mutesa I Royal University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mru;

defined('MOODLE_INTERNAL') || die();

class otp_manager {

    /** @var int OTP expiry: 3 hours in seconds. */
    const OTP_EXPIRY = 10800;

    /** @var int Maximum OTP verification attempts. */
    const MAX_ATTEMPTS = 5;

    /** @var int Minimum seconds between OTP resends. */
    const RESEND_COOLDOWN = 60;

    /** @var int OTP digit length. */
    const OTP_LENGTH = 6;

    /**
     * Generate a new OTP and store its hash in the session.
     *
     * @param \stdClass $session The registration session record.
     * @return string The plain-text OTP (for sending via email).
     */
    public function generate(\stdClass $session): string {
        $otp = $this->generate_code();

        $session->otp_hash = password_hash($otp, PASSWORD_DEFAULT);
        $session->otp_expires = time() + self::OTP_EXPIRY;
        $session->otp_attempts = 0;
        $session->timemodified = time();

        global $DB;
        $DB->update_record(registration_manager::TABLE, $session);

        return $otp;
    }

    /**
     * Verify an OTP against the stored hash.
     *
     * @param \stdClass $session The registration session.
     * @param string $code The OTP code to verify.
     * @return array ['valid' => bool, 'error' => string|null]
     */
    public function verify(\stdClass $session, string $code): array {
        global $DB;

        // Check attempts before doing anything else.
        if ($session->otp_attempts >= self::MAX_ATTEMPTS) {
            return [
                'valid' => false,
                'error' => get_string('reg:otp_max_attempts', 'local_mru'),
            ];
        }

        // Check expiry before burning an attempt — expired OTPs should not consume tries.
        if (empty($session->otp_expires) || time() > $session->otp_expires) {
            return [
                'valid' => false,
                'error' => get_string('reg:otp_expired', 'local_mru'),
            ];
        }

        // Now increment the attempt counter.
        $session->otp_attempts++;
        $session->timemodified = time();
        $DB->update_record(registration_manager::TABLE, $session);

        // Check hash is set.
        if (empty($session->otp_hash)) {
            return [
                'valid' => false,
                'error' => get_string('reg:otp_not_sent', 'local_mru'),
            ];
        }

        // Verify against hash.
        if (!password_verify($code, $session->otp_hash)) {
            $remaining = self::MAX_ATTEMPTS - $session->otp_attempts;
            return [
                'valid' => false,
                'error' => get_string('reg:otp_invalid', 'local_mru', $remaining),
            ];
        }

        // Success — mark email verified.
        $session->email_verified = 1;
        $session->timemodified = time();
        $DB->update_record(registration_manager::TABLE, $session);

        return ['valid' => true, 'error' => null];
    }

    /**
     * Check if a resend is allowed (cooldown period).
     *
     * @param \stdClass $session The registration session.
     * @return bool True if resend is allowed.
     */
    public function can_resend(\stdClass $session): bool {
        if (empty($session->otp_expires)) {
            return true;
        }
        // OTP was generated at (expires - expiry period).
        $generated_at = $session->otp_expires - self::OTP_EXPIRY;
        return (time() - $generated_at) >= self::RESEND_COOLDOWN;
    }

    /**
     * Send OTP email to the user.
     *
     * @param string $email The recipient email.
     * @param string $otp The plain-text OTP code.
     * @return bool True if email was sent.
     */
    public function send_otp_email(string $email, string $otp): bool {
        $noreplyuser = \core_user::get_noreply_user();

        // Create a temporary user object for email_to_user.
        $touser = new \stdClass();
        $touser->id = -1;
        $touser->email = $email;
        $touser->firstname = 'MRU';
        $touser->lastname = 'Student';
        $touser->firstnamephonetic = '';
        $touser->lastnamephonetic = '';
        $touser->middlename = '';
        $touser->alternatename = '';
        $touser->maildisplay = 1;
        $touser->mailformat = 1;
        $touser->auth = 'manual';
        $touser->deleted = 0;
        $touser->emailstop = 0;

        $subject = get_string('reg:otp_email_subject', 'local_mru');

        $expiry_hours = self::OTP_EXPIRY / 3600;
        $sitename = format_string(get_site()->shortname);
        $messagetext = get_string('reg:otp_email_body', 'local_mru', [
            'otp' => $otp,
            'expiry' => $expiry_hours,
            'site' => $sitename,
        ]);

        $messagehtml = get_string('reg:otp_email_body_html', 'local_mru', [
            'otp' => $otp,
            'expiry' => $expiry_hours,
            'site' => $sitename,
        ]);

        return email_to_user($touser, $noreplyuser, $subject, $messagetext, $messagehtml);
    }

    /**
     * Generate a random numeric OTP.
     *
     * @return string The OTP code, zero-padded.
     */
    private function generate_code(): string {
        $min = (int) pow(10, self::OTP_LENGTH - 1);
        $max = (int) pow(10, self::OTP_LENGTH) - 1;
        return (string) random_int($min, $max);
    }
}
