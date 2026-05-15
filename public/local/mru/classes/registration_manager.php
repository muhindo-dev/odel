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
 * Registration wizard manager for MRU ODEL.
 *
 * Handles multi-step registration: session management, step progression,
 * account creation, and wizard state persistence.
 *
 * @package    local_mru
 * @copyright  2026 Mutesa I Royal University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mru;

defined('MOODLE_INTERNAL') || die();

class registration_manager {

    /** @var string Cookie name for session token. */
    const COOKIE_NAME = 'mru_reg_token';

    /** @var int Cookie lifetime: 24 hours. */
    const COOKIE_LIFETIME = 86400;

    /** @var string DB table name. */
    const TABLE = 'local_mru_registrations';

    /** @var int Total wizard steps. */
    const TOTAL_STEPS = 5;

    /** @var string MRU email domain. */
    const EMAIL_DOMAIN = '@mru.ac.ug';

    /**
     * Get or create a registration session.
     *
     * @return \stdClass The registration record.
     */
    public function get_or_create_session(): \stdClass {
        global $DB;

        $token = $this->get_session_token();

        if ($token) {
            $record = $DB->get_record(self::TABLE, ['session_token' => $token, 'completed' => 0]);
            if ($record) {
                return $record;
            }
        }

        // Create new session.
        return $this->create_session();
    }

    /**
     * Create a new registration session.
     *
     * @return \stdClass The new registration record.
     */
    public function create_session(): \stdClass {
        global $DB;

        $token = $this->generate_token();
        $now = time();

        $record = new \stdClass();
        $record->session_token = $token;
        $record->email = '';
        $record->otp_attempts = 0;
        $record->email_verified = 0;
        $record->current_step = 1;
        $record->completed = 0;
        $record->ip_address = getremoteaddr();
        $record->timecreated = $now;
        $record->timemodified = $now;

        $record->id = $DB->insert_record(self::TABLE, $record);

        $this->set_session_cookie($token);

        return $record;
    }

    /**
     * Get the current session from the cookie token.
     *
     * @return \stdClass|null The registration record or null.
     */
    public function get_session(): ?\stdClass {
        global $DB;

        $token = $this->get_session_token();
        if (!$token) {
            return null;
        }

        $record = $DB->get_record(self::TABLE, ['session_token' => $token, 'completed' => 0]);
        return $record ?: null;
    }

    /**
     * Update the session record.
     *
     * @param \stdClass $record The registration record with updated fields.
     */
    public function update_session(\stdClass $record): void {
        global $DB;

        $record->timemodified = time();
        $DB->update_record(self::TABLE, $record);
    }

    /**
     * Advance to the next step.
     *
     * @param \stdClass $record The registration record.
     * @param int $step The step to advance to.
     */
    public function advance_step(\stdClass $record, int $step): void {
        if ($step < 1 || $step > self::TOTAL_STEPS) {
            return;
        }
        $record->current_step = $step;
        $this->update_session($record);
    }

    /**
     * Validate that an email is a valid MRU email.
     *
     * @param string $email The email to validate.
     * @return bool True if valid MRU email.
     */
    public function validate_mru_email(string $email): bool {
        $email = trim(strtolower($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        return str_ends_with($email, self::EMAIL_DOMAIN);
    }

    /**
     * Check if an email is already registered in Moodle.
     *
     * @param string $email The email address.
     * @return bool True if already registered.
     */
    public function email_already_registered(string $email): bool {
        global $DB;
        return $DB->record_exists('user', ['email' => strtolower(trim($email)), 'deleted' => 0]);
    }

    /**
     * Create the Moodle user account.
     *
     * @param \stdClass $session The registration session with all data.
     * @param string $password The plain-text password.
     * @return \stdClass The created user object.
     * @throws \moodle_exception If creation fails.
     */
    public function create_account(\stdClass $session, string $password): \stdClass {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/user/lib.php');

        // Build username from email prefix.
        $email = strtolower(trim($session->email));
        $username = strstr($email, '@', true);

        // Ensure unique username.
        $base = $username;
        $i = 1;
        while ($DB->record_exists('user', ['username' => $username])) {
            $username = $base . $i;
            $i++;
        }

        $user = new \stdClass();
        $user->auth = 'manual';
        $user->confirmed = 1;
        $user->mnethostid = $CFG->mnet_localhost_id;
        $user->username = $username;
        $user->password = hash_internal_user_password($password);
        $user->firstname = !empty($session->firstname) ? $session->firstname : 'New';
        $user->lastname = !empty($session->lastname) ? $session->lastname : 'User';
        $user->email = $email;
        $user->timecreated = time();
        $user->timemodified = time();

        $user->id = user_create_user($user, false, true);

        // Mark session complete.
        $session->userid = $user->id;
        $session->completed = 1;
        $session->current_step = 5;
        $this->update_session($session);

        return $DB->get_record('user', ['id' => $user->id]);
    }

    /**
     * Validate password meets MRU requirements.
     *
     * Min 8 chars, at least 1 uppercase, 1 lowercase, 1 number.
     *
     * @param string $password The password to validate.
     * @return array Array of error strings (empty = valid).
     */
    public function validate_password(string $password): array {
        $errors = [];

        if (strlen($password) < 8) {
            $errors[] = get_string('reg:pwd_min_length', 'local_mru');
        }
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = get_string('reg:pwd_uppercase', 'local_mru');
        }
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = get_string('reg:pwd_lowercase', 'local_mru');
        }
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = get_string('reg:pwd_number', 'local_mru');
        }

        return $errors;
    }

    /**
     * Complete login for the newly created user.
     *
     * @param \stdClass $user The user object.
     */
    public function login_user(\stdClass $user): void {
        global $CFG, $USER;
        require_once($CFG->dirroot . '/login/lib.php');

        complete_user_login($user);
    }

    /**
     * Destroy the registration session cookie.
     */
    public function destroy_session(): void {
        setcookie(self::COOKIE_NAME, '', [
            'expires'  => time() - 3600,
            'path'     => '/',
            'httponly' => true,
            'secure'   => is_https(),
            'samesite' => 'Lax',
        ]);
    }

    /**
     * Generate a cryptographically secure session token.
     *
     * @return string 64-char hex token.
     */
    private function generate_token(): string {
        return bin2hex(random_bytes(32));
    }

    /**
     * Get the session token from the cookie.
     *
     * @return string|null The token or null.
     */
    private function get_session_token(): ?string {
        $token = $_COOKIE[self::COOKIE_NAME] ?? null;
        if ($token && preg_match('/^[a-f0-9]{64}$/', $token)) {
            return $token;
        }
        return null;
    }

    /**
     * Set the session cookie.
     *
     * @param string $token The session token.
     */
    private function set_session_cookie(string $token): void {
        setcookie(self::COOKIE_NAME, $token, [
            'expires'  => time() + self::COOKIE_LIFETIME,
            'path'     => '/',
            'httponly' => true,
            'secure'   => is_https(),
            'samesite' => 'Lax',
        ]);
    }

    /**
     * Cleanup expired/incomplete sessions older than 48 hours.
     */
    public static function cleanup_expired(): void {
        global $DB;

        $cutoff = time() - (48 * 3600);
        $DB->delete_records_select(self::TABLE, 'completed = 0 AND timecreated < ?', [$cutoff]);
    }
}
