# MRU ODEL System — Comprehensive Analysis Report

**Date:** 2026-05-15  
**Scope:** Full codebase audit — `theme_mru_odel` and `local_mru` plugin  
**Moodle version:** 5.1.3+ (Build 20260306)  
**Reviewer:** Claude Code (automated deep analysis)

---

## Table of Contents

1. [Executive Summary](#1-executive-summary)
2. [System Architecture Overview](#2-system-architecture-overview)
3. [Critical Security Findings](#3-critical-security-findings)
4. [High-Severity Issues](#4-high-severity-issues)
5. [Medium-Severity Issues](#5-medium-severity-issues)
6. [Theme Analysis & Recommendations](#6-theme-analysis--recommendations)
7. [Plugin Deep-Dive Findings](#7-plugin-deep-dive-findings)
8. [Code Quality & Maintainability](#8-code-quality--maintainability)
9. [Architecture & Design Recommendations](#9-architecture--design-recommendations)
10. [Deployment & Operations](#10-deployment--operations)
11. [Priority Action Plan](#11-priority-action-plan)

---

## 1. Executive Summary

The MRU ODEL system is a well-structured, production-grade Moodle 5.1 installation with a custom child theme (`theme_mru_odel`) and a sophisticated local integration plugin (`local_mru`). The codebase demonstrates solid Moodle patterns — proper namespacing, capability-based access control, Mustache templates, and Moodle's XMLDB schema management.

**However, several issues ranging from critical to low severity need resolution before the system can be considered robust and secure for a university production environment.**

### Risk Summary

| Severity | Count | Status |
|---|---|---|
| Critical | 4 | Must fix immediately |
| High | 7 | Fix before next release |
| Medium | 9 | Fix within 30 days |
| Low / Quality | 12 | Fix in normal dev cycle |

---

## 2. System Architecture Overview

```
/Applications/MAMP/htdocs/odel/
├── public/                          ← Moodle web root
│   ├── theme/mru_odel/              ← Custom Boost child theme
│   │   ├── config.php               ← Theme layout config
│   │   ├── lib.php                  ← SCSS callbacks, file serving
│   │   ├── settings.php             ← Admin settings UI
│   │   ├── scss/pre.scss            ← Brand variables, component overrides
│   │   ├── scss/post.scss           ← Login styles, extended customisation
│   │   ├── style/moodle.css         ← PRE-COMPILED CSS (committed to git)
│   │   ├── amd/src/theme.js         ← Frontend JS module
│   │   └── templates/               ← Mustache layouts (login, drawers, etc.)
│   └── local/mru/                   ← MRU Integration plugin
│       ├── classes/
│       │   ├── api_client.php       ← Campus Dynamics API v2.2 HTTP client
│       │   ├── db_manager.php       ← Direct mysqli to mru_main database
│       │   ├── student_manager.php  ← Student verify + import
│       │   ├── marks_manager.php    ← Grade sync to MRU core
│       │   ├── course_manager.php   ← Course code mapping
│       │   ├── sync_manager.php     ← Full-sync orchestrator
│       │   ├── registration_manager.php ← 5-step wizard session state
│       │   ├── otp_manager.php      ← OTP generation/verification
│       │   ├── hook_callbacks.php   ← Email lock on profile edit
│       │   └── registration/
│       │       ├── base_step.php    ← Abstract wizard step
│       │       └── step1-5.php      ← Concrete step handlers
│       ├── db/
│       │   ├── install.xml          ← Schema: 6 custom tables
│       │   ├── upgrade.php          ← Standard Moodle upgrade
│       │   ├── access.php           ← 5 capabilities
│       │   ├── tasks.php            ← 2 scheduled tasks
│       │   ├── services.php         ← 3 web service functions
│       │   └── migrations/          ← Laravel-style migrations (non-standard)
│       └── templates/               ← 11 Mustache templates
└── .vps-credentials                 ← ⚠️ CRITICAL: production secrets in repo
```

### Data Flow

```
Student Browser → Moodle (local_mru) → Campus Dynamics API (eadmin.mru.ac.ug)
                                      → mru_main DB (direct mysqli)
                                      → Moodle DB (mdl_*)
```

---

## 3. Critical Security Findings

### C-1: Production Credentials in Git Repository

**File:** `/.vps-credentials`  
**Severity:** CRITICAL  
**Status:** File is in `.gitignore` but may already be in git history.

The file contains:
- Root SSH password to the production VPS
- Webuzo admin panel credentials
- Namecheap VPS account credentials

**Impact:** Anyone with access to the git repository (or its history) can gain full root access to the production server at `mruodel.com`.

**Actions required:**
1. Immediately rotate ALL credentials in this file (SSH password, Webuzo, Namecheap).
2. Purge the file from git history: `git filter-branch` or `git-filter-repo`.
3. Add pre-commit hooks to prevent future secrets commits (use `git-secrets` or `detect-secrets`).
4. Never store credentials in the repository — use environment variables or a secrets manager.

```bash
# Check if it's in history:
git log --all --full-history -- .vps-credentials

# Purge with git-filter-repo (preferred):
git filter-repo --path .vps-credentials --invert-paths
```

---

### C-2: API Auth Token Exposed in Server Logs via Query String

**File:** [classes/api_client.php](public/local/mru/classes/api_client.php#L420-L425)  
**Severity:** CRITICAL

The authentication token is appended to the URL as `?token=...`:

```php
$url .= '&token=' . urlencode($token);
```

This means every authenticated API call (student lookups, marks submissions, grade fetches) has the token written to:
- Apache/Nginx access logs
- Proxy server logs
- Browser history
- HTTP Referer headers on subsequent requests

**Impact:** Token theft allows impersonation of the system account on the MRU Campus Dynamics API, exposing all student and staff data.

**Fix:** Move the token to the `Authorization` HTTP header:

```php
$curl->setHeader([
    'Accept: application/json',
    'Authorization: Bearer ' . $token,
]);
// Remove token from URL entirely
$url = $this->baseurl . '/' . $endpoint . '?action=' . urlencode($action);
```

*(Requires the Campus Dynamics API to support Bearer token auth — if it only accepts query-string tokens, enable access log scrubbing on the server and document this as a known limitation of the upstream API.)*

---

### C-3: Email Immutability — Client-Side Only, No Server Enforcement

**File:** [classes/hook_callbacks.php](public/local/mru/classes/hook_callbacks.php)  
**Severity:** CRITICAL

The verified email lock is implemented entirely in JavaScript injected into the page head. A user can:
1. Open browser DevTools and remove the `readonly` attribute from the email field.
2. Or submit a raw `POST` to `/user/edit.php` directly, bypassing the JS entirely.

This means a verified student can change their Moodle account email to any arbitrary address, breaking the MRU identity link.

**Fix:** Add a Moodle event observer on `\core\event\user_updated` to re-enforce the locked email:

```php
// db/events.php — add observer
$observers = [
    [
        'eventname' => '\core\event\user_updated',
        'callback'  => '\local_mru\event\observer::on_user_updated',
        'includefile' => null,
        'internal'  => true,
    ],
];
```

```php
// classes/event/observer.php — add method
public static function on_user_updated(\core\event\user_updated $event): void {
    global $DB;
    $userid = $event->relateduserid ?? $event->objectid;
    $mapping = $DB->get_record('local_mru_user_map', ['userid' => $userid, 'verified' => 1]);
    if (!$mapping) {
        return;
    }
    $lockedemail = \local_mru_get_locked_verified_email($mapping, $userid);
    if (empty($lockedemail)) {
        return;
    }
    $user = $DB->get_record('user', ['id' => $userid]);
    if ($user && strtolower(trim($user->email)) !== $lockedemail) {
        // Silently revert the email change.
        $DB->set_field('user', 'email', $lockedemail, ['id' => $userid]);
    }
}
```

---

### C-4: Registration POST Actions Lack CSRF Protection

**Files:** [classes/registration/step2.php](public/local/mru/classes/registration/step2.php), [step3.php](public/local/mru/classes/registration/step3.php), [step4.php](public/local/mru/classes/registration/step4.php)  
**Severity:** CRITICAL

None of the POST action handlers call `confirm_sesskey()` or `require_sesskey()`. This exposes the registration wizard to Cross-Site Request Forgery attacks. An attacker could force a victim's browser to:
- Submit an OTP verification with the attacker's code.
- Advance wizard steps out of sequence.
- Create accounts with attacker-controlled credentials.

**Fix:** In `base_step.php`, enforce sesskey validation in `handle_action()`:

```php
// In base_step::handle_action() or at the top of register.php POST handling:
require_sesskey();
```

And in all registration Mustache templates, include the sesskey:
```mustache
<input type="hidden" name="sesskey" value="{{sesskey}}">
```

Pass `sesskey` to template data:
```php
// In register.php / base step template data:
'sesskey' => sesskey(),
```

---

## 4. High-Severity Issues

### H-1: Password Validation Inconsistency — 4-Char Minimum, No Complexity

**File:** [classes/registration_manager.php:222-229](public/local/mru/classes/registration_manager.php#L222)  
**Severity:** HIGH

The `validate_password()` method's docblock states *"Min 6 chars, at least 1 uppercase, 1 lowercase, 1 number"* but the implementation only checks for a minimum of **4 characters**. The lang file even defines strings for `reg:pwd_uppercase`, `reg:pwd_lowercase`, and `reg:pwd_number` — but these checks don't exist in the code.

```php
// Current (wrong):
if (strlen($password) < 4) {
    $errors[] = get_string('reg:pwd_min_length', 'local_mru');
}
```

**Fix:**
```php
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
```

Also update the lang string `reg:pwd_min_length` to say "8 characters" and update the min from 4 → 8.

---

### H-2: Imported Student Passwords Are Predictable

**File:** [classes/student_manager.php:275](public/local/mru/classes/student_manager.php#L275)  
**Severity:** HIGH

Bulk-imported students get the password `Mru@{studentno}{year}` — e.g., `Mru@2026/HD/001/2026`. Both the student number and year are public or semi-public information.

**Impact:** Any person who knows a student number can log in as that student the moment they're imported.

**Fix:** Generate cryptographically random initial passwords and deliver them via the student's institutional email:

```php
$initialpassword = bin2hex(random_bytes(8)); // 16-char random hex
$user->password = hash_internal_user_password($initialpassword);
// Queue a welcome email with $initialpassword to $user->email
```

Or, alternatively, force a password reset on first login using `$user->auth = 'manual'` with `$user->password = 'not cached'` and the force-change flag.

---

### H-3: Marks Sync — Success Recorded Regardless of API Response

**File:** [classes/marks_manager.php:154-181](public/local/mru/classes/marks_manager.php#L154)  
**Severity:** HIGH

The marks sync submits all marks in a single API batch call, then records every individual mark as `'synced'` in the database — regardless of what the API actually returned for each student. The API response (`$response`) is captured but never validated per-student:

```php
// Actual code:
if ($this->api->is_configured()) {
    $response = $this->api->submit_marks($apimarks);  // $response never inspected
}

// Then all marks recorded as 'synced':
$syncrecord->sync_status = 'synced';  // Always 'synced'
```

**Impact:** If the API partially rejects marks (wrong course code, student not found, deadline passed), Moodle records them all as `'synced'` — creating a false paper trail and losing actual sync state.

**Fix:** Validate the API response per-student and set status accordingly:

```php
$response = $this->api->submit_marks($apimarks);
// Build a lookup of API results by student_id:
$apiresults = [];
foreach ($response['results'] ?? [] as $apiresult) {
    $apiresults[$apiresult['student_id']] = $apiresult;
}

foreach ($marks as $mark) {
    $apistatus = $apiresults[$mark['mru_id']] ?? null;
    $syncrecord->sync_status = ($apistatus && !empty($apistatus['success'])) ? 'synced' : 'failed';
    $syncrecord->error_message = $apistatus['error'] ?? null;
    // ...
}
```

---

### H-4: Marks Sync Is Not Idempotent — Table Grows Unbounded

**File:** [classes/marks_manager.php:159-181](public/local/mru/classes/marks_manager.php#L159)  
**Severity:** HIGH

Every call to `sync_marks_to_core()` always `INSERT`s new rows into `local_mru_marks_sync`. There is no check whether the same (courseid, userid, grade_item_id, academic_year, semester) combination was already synced. Running a daily cron will duplicate every student's sync record daily.

**Impact:** The `local_mru_marks_sync` table grows without bound. Sync history becomes misleading. The dashboard's "pending syncs" count is never accurate.

**Fix:** Use an upsert pattern (check-then-update or INSERT ... ON DUPLICATE KEY UPDATE):

```php
$existing = $DB->get_record('local_mru_marks_sync', [
    'courseid'       => $mark['courseid'],
    'userid'         => $mark['userid'],
    'academic_year'  => $mark['academic_year'],
    'semester'       => $mark['semester'],
]);

if ($existing) {
    $existing->moodle_grade = $mark['moodle_grade'];
    $existing->sync_status = 'synced';
    $existing->timesynced = $now;
    $DB->update_record('local_mru_marks_sync', $existing);
} else {
    $DB->insert_record('local_mru_marks_sync', $syncrecord);
}
```

Also add a composite unique index on `(courseid, userid, academic_year, semester)` in `install.xml` and a migration.

---

### H-5: Registration Cookie Missing `secure` Flag in Production

**File:** [classes/registration_manager.php:283-290](public/local/mru/classes/registration_manager.php#L283)  
**Severity:** HIGH

```php
setcookie(self::COOKIE_NAME, $token, [
    'expires'  => time() + self::COOKIE_LIFETIME,
    'path'     => '/',
    'httponly'  => true,
    'samesite' => 'Lax',
    // ⚠️ Missing: 'secure' => true
]);
```

Without `secure: true`, the session token cookie is transmitted over plain HTTP. The production server at `mruodel.com` uses HTTPS, but if a user ever accesses the site over HTTP (e.g., misconfigured redirect, mixed content), the token leaks.

**Fix:**
```php
'secure'   => !empty($_SERVER['HTTPS']) || (defined('HTTPS') && HTTPS),
```

Or use Moodle's `is_https()` helper:
```php
'secure' => is_https(),
```

---

### H-6: `verify_on_login` and `verify_on_enrol` Settings Have No Implementation

**File:** [settings.php](public/local/mru/settings.php), [db/events.php](public/local/mru/db/events.php)  
**Severity:** HIGH

The admin settings panel shows two options:
- "Verify on login" — re-verify student status each time they log in
- "Verify on enrolment" — verify student status when they are enrolled in a course

These settings are configurable but **have no corresponding event observers or hook implementations**. The settings exist and are saveable, but nothing reads them to act on them.

**Impact:** Admins believe they have enabled automatic verification, but unverified or inactive students can continue to access the system indefinitely.

**Fix:** Add event observers in `db/events.php`:

```php
$observers = [
    [
        'eventname' => '\core\event\user_loggedin',
        'callback'  => '\local_mru\event\observer::on_user_loggedin',
    ],
    [
        'eventname' => '\core\event\user_enrolment_created',
        'callback'  => '\local_mru\event\observer::on_user_enrolment_created',
    ],
];
```

And implement the callbacks to call `student_manager::verify_student()` when the respective settings are enabled.

---

### H-7: `PARAM_RAW` Used for Student/Staff ID Input

**File:** [classes/registration/step3.php:71](public/local/mru/classes/registration/step3.php#L71)  
**Severity:** HIGH

```php
$idnumber = optional_param('id_number', '', PARAM_RAW);
```

`PARAM_RAW` bypasses all Moodle input sanitization. Student/staff numbers should only contain alphanumeric characters and possibly slashes or dashes (`2026/HD/001`).

**Fix:**
```php
$idnumber = optional_param('id_number', '', PARAM_ALPHANUMEXT);
// Or use a custom regex clean:
$idnumber = clean_param($idnumber, PARAM_NOTAGS);
$idnumber = preg_replace('/[^A-Za-z0-9\/\-_]/', '', trim($idnumber));
```

---

## 5. Medium-Severity Issues

### M-1: OTP Attempts Incremented Before Expiry Check

**File:** [classes/otp_manager.php:83-93](public/local/mru/classes/otp_manager.php#L83)  
**Severity:** MEDIUM

```php
// Increment attempts FIRST, then check expiry:
$session->otp_attempts++;
$DB->update_record(...);

// Check expiry AFTER burning an attempt:
if (time() > $session->otp_expires) {
    return ['valid' => false, 'error' => ...expired...];
}
```

A user with an expired OTP who requests a new one will have their attempt counter reset (since `generate()` resets it to 0). But if they don't request a new one and keep submitting the expired code, they burn attempts unnecessarily and get locked out of verifying even valid new codes.

**Fix:** Check expiry before incrementing:
```php
if (empty($session->otp_expires) || time() > $session->otp_expires) {
    return ['valid' => false, 'error' => get_string('reg:otp_expired', 'local_mru')];
}
// Only now increment attempts
$session->otp_attempts++;
```

---

### M-2: API Token Not Cached Across PHP Requests

**File:** [classes/api_client.php:71-97](public/local/mru/classes/api_client.php#L71)  
**Severity:** MEDIUM

The `api_client` stores the token in `private ?string $token = null`. Since PHP is stateless, each new HTTP request (cron execution, web service call, page load) creates a new `api_client` instance and immediately re-authenticates with the Campus Dynamics API.

**Impact:**
- Every API call in a new request = one extra authentication round-trip to the legacy server.
- High load on the authentication endpoint.
- Token churn that may exhaust API rate limits.

**Fix:** Use Moodle's application cache to persist the token:

```php
use cache;

public function authenticate(): string {
    if ($this->token !== null) {
        return $this->token;
    }
    $cache = cache::make('local_mru', 'apitokens');
    $cached = $cache->get('system_token');
    if ($cached && !empty($cached['token']) && time() < ($cached['expires'] ?? 0)) {
        $this->token = $cached['token'];
        return $this->token;
    }
    // ... perform auth ...
    $cache->set('system_token', ['token' => $this->token, 'expires' => time() + 3600]);
    return $this->token;
}
```

Define the cache in `db/caches.php`:
```php
$definitions = [
    'apitokens' => [
        'mode' => cache_store::MODE_APPLICATION,
        'ttl'  => 3600,
    ],
];
```

---

### M-3: 401 Retry Could Cause Infinite Recursion (Low Probability)

**File:** [classes/api_client.php:449-452](public/local/mru/classes/api_client.php#L449)  
**Severity:** MEDIUM

```php
if ($httpcode === 401 && $authenticated && $overridetoken === null && $this->token !== null) {
    $this->token = null;
    return $this->request($endpoint, $action, $params, true, null, $method);
}
```

If re-authentication succeeds but the new token immediately gets a 401 (e.g., account is disabled, IP blocked), the second `request()` call will see `$this->token !== null` again and retry indefinitely.

**Fix:** Add a retry counter parameter:
```php
private function request(string $endpoint, string $action, array $params = [],
        bool $authenticated = true, ?string $overridetoken = null,
        string $method = 'GET', int $retries = 0): array {
    // ...
    if ($httpcode === 401 && $authenticated && $overridetoken === null && $retries === 0) {
        $this->token = null;
        return $this->request($endpoint, $action, $params, true, null, $method, 1);
    }
    // ...
}
```

---

### M-4: Step 3 Silently Swallows All API Errors

**File:** [classes/registration/step3.php:161-193](public/local/mru/classes/registration/step3.php#L161)  
**Severity:** MEDIUM

The `get_template_data()` method contains triple-nested try/catch blocks where every exception is silently caught. If both the primary lookup and the fallback search fail, the student sees an empty form with no error message:

```php
try {
    $result = $api->lookup_person($email);
    // ...
} catch (\Exception $e) {
    try {
        // fallback search...
    } catch (\Exception $e2) {
        // Both failed — $e2 is silently discarded
    }
}
// User gets $data['show_manual_lookup'] = true with no error text
```

**Fix:** Log the API error and pass a user-friendly error message to the template:
```php
} catch (\Exception $e) {
    debugging('MRU API lookup failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
    $data['api_error'] = true;
    $data['api_error_message'] = get_string('reg:api_lookup_failed', 'local_mru');
    $data['show_manual_lookup'] = true;
}
```

---

### M-5: `local_mru_registrations.email` Declared NOT NULL But Set to Empty String

**File:** [classes/registration_manager.php:83](public/local/mru/classes/registration_manager.php#L83), [db/upgrade.php:44](public/local/mru/db/upgrade.php#L44)  
**Severity:** MEDIUM

In `create_session()`:
```php
$record->email = '';  // Empty string in a NOT NULL column
```

The XML schema declares `email` as `XMLDB_NOTNULL`. An empty string satisfies the NOT NULL constraint in MySQL, but it is semantically wrong — the email isn't known yet at session creation time.

**Fix:** Either:
1. Declare the column as nullable (`null` instead of `XMLDB_NOTNULL`) in the schema, or
2. Do not insert the record until step 2 when the email is captured, carrying the session via cookie only.

Option 2 is cleaner — only persist to DB once an email is entered.

---

### M-6: Lang String `reg:otp_sent` Has Placeholder But Is Called Without Parameter

**File:** [lang/en/local_mru.php:190](public/local/mru/lang/en/local_mru.php#L190), [classes/registration/step2.php:93](public/local/mru/classes/registration/step2.php#L93)  
**Severity:** MEDIUM

The language string:
```php
$string['reg:otp_sent'] = 'A 6-digit verification code has been sent to {$a}. Please check your inbox.';
```

But the call site:
```php
$this->redirect_to_wizard(get_string('reg:otp_sent', 'local_mru'), 'success');
// Missing: the email address as $a parameter
```

The user sees the literal text `"…has been sent to . Please check your inbox."` — missing the email address.

**Fix:**
```php
$this->redirect_to_wizard(get_string('reg:otp_sent', 'local_mru', $email), 'success');
```

Same issue with `reg:otp_resent` and `reg:otp_cooldown` — verify all lang string usages match their placeholders.

---

### M-7: `grade_item_id` Not Saved in Marks Sync Record

**File:** [classes/marks_manager.php:163-174](public/local/mru/classes/marks_manager.php#L163)  
**Severity:** MEDIUM

The `local_mru_marks_sync` table has a `grade_item_id` column (defined in `install.xml`), but `sync_marks_to_core()` never sets it when building the `$syncrecord` object. The field defaults to NULL for every sync record.

This makes it impossible to later query "which grade item was synced" and breaks any future logic that tries to do per-grade-item conflict detection.

**Fix:** In `collect_course_grades()`, include `$gradeitem->id` in the returned mark arrays, then set it in the sync record:
```php
// In collect_course_grades():
'grade_item_id' => $gradeitem->id,

// In sync_marks_to_core():
$syncrecord->grade_item_id = $mark['grade_item_id'];
```

---

### M-8: Bulk Student Import Has No Rate Limiting or Batching

**File:** [classes/student_manager.php:222-286](public/local/mru/classes/student_manager.php#L222)  
**Severity:** MEDIUM

`import_students()` fetches ALL active students from `mru_main` into a single PHP array in memory, then iterates them all synchronously. For a large university with thousands of active students, this will:
1. Exhaust PHP memory limit.
2. Lock the scheduled task queue for an extended period.
3. Hold a mysqli connection open for the entire duration.

**Fix:** Process in pages:
```php
$batchsize = 200;
$offset = 0;
do {
    $sql = "SELECT ... FROM acad_student s WHERE s.status = 'Active' LIMIT ? OFFSET ?";
    $students = $this->db->query($sql, 'ii', [$batchsize, $offset]);
    // Process batch...
    $offset += $batchsize;
} while (count($students) === $batchsize);
```

---

### M-9: Dual-Track Schema Management Creates Synchronisation Risk

**Files:** [db/upgrade.php](public/local/mru/db/upgrade.php), [db/migrations/](public/local/mru/db/migrations/)  
**Severity:** MEDIUM

The plugin uses **two separate schema management systems simultaneously**:
1. **Standard Moodle**: `db/upgrade.php` + `db/install.xml` — run by `php admin/cli/upgrade.php`.
2. **Custom Laravel-style**: `db/migrations/*.php` + `local_mru_migrations` table — run by `php local/mru/cli/migrate.php`.

These two systems are completely independent. A change applied via a Laravel migration won't appear in the Moodle upgrade path and vice versa. When deploying to production, it's easy to run only one system and have an inconsistent schema.

**Recommendation:** Pick one system. Moodle's `upgrade.php` is the correct mechanism. The Laravel-style migrations add complexity without meaningful benefit in a Moodle context. If the custom migration runner must be kept, add a Moodle upgrade step that automatically runs pending custom migrations, making the two systems aware of each other.

---

## 6. Theme Analysis & Recommendations

### T-1: Backup Files Committed to Repository

**Files:** `theme.js.bak`, `post.scss.bak`, `pre.scss.bak`, `frontpage.mustache.bak`  
**Severity:** LOW (but indicates poor hygiene)

Four `.bak` files are tracked in git. These are editor backup copies that should never be committed.

**Fix:** Add to `.gitignore`:
```
*.bak
*.orig
*.swp
*~
```

Then remove from git tracking:
```bash
git rm --cached public/theme/mru_odel/amd/src/theme.js.bak
git rm --cached public/theme/mru_odel/scss/pre.scss.bak
git rm --cached public/theme/mru_odel/scss/post.scss.bak
git rm --cached public/theme/mru_odel/templates/frontpage.mustache.bak
```

---

### T-2: Compiled CSS (`style/moodle.css`) Committed to Repository

**File:** `public/theme/mru_odel/style/moodle.css`  
**Severity:** MEDIUM

The precompiled CSS file is committed to the repository and is activated via the `precompiledcsscallback`:
```php
function theme_mru_odel_get_precompiled_css() {
    return file_get_contents($CFG->dirroot . '/theme/mru_odel/style/moodle.css');
}
```

**Problems:**
1. Any SCSS change requires a manual `grunt sass` compile and re-commit of `moodle.css` — easy to forget.
2. SCSS and compiled CSS can drift out of sync.
3. Huge binary diffs in git history.
4. The precompiled CSS is served even when the SCSS callback is defined — Moodle uses the precompiled CSS as a fallback when SCSS compilation is unavailable, but the callback being registered means it *always* uses the precompiled file on servers without a theme recompile.

**Recommendation:** Remove `precompiledcsscallback` registration from `config.php` and `moodle.css` from `.gitignore`. Let Moodle compile SCSS on-demand (which it caches internally). Only re-add the precompiled path if you explicitly need it for performance on a high-traffic server.

---

### T-3: SCSS Order Issue — Pre/Post in Both Main Content and Callbacks

**File:** [lib.php:33-58](public/theme/mru_odel/lib.php#L33)  
**Severity:** MEDIUM

The `theme_mru_odel_get_main_scss_content()` function manually concatenates pre and post SCSS:
```php
return $pre . "\n" . $scss . "\n" . $post;
```

But `config.php` also registers `prescsscallback` and `extrascsscallback`:
```php
$THEME->prescsscallback = 'theme_mru_odel_get_pre_scss';
$THEME->extrascsscallback = 'theme_mru_odel_get_extra_scss';
```

Moodle's SCSS compilation pipeline applies these callbacks **in addition to** the main SCSS content function's output. This means:
- Brand color variables injected by `get_pre_scss()` run before the main content.
- BUT `pre.scss` is also included inside `get_main_scss_content()` — so it runs **twice**.
- Similarly, `post.scss` content appears in main content AND potentially via extra SCSS.

**Fix:** Use only one approach. The correct Boost child theme pattern is:
```php
// lib.php - get_main_scss_content() should ONLY return the Boost preset:
function theme_mru_odel_get_main_scss_content($theme) {
    global $CFG;
    // Just return the preset - let callbacks handle pre/post
    return file_get_contents($CFG->dirroot . '/theme/boost/scss/preset/default.scss');
}

// pre.scss content goes into get_pre_scss()
// post.scss content goes into get_extra_scss()
```

---

### T-4: Background Image URL Injected Into SCSS Without Escaping

**File:** [lib.php:113-119](public/theme/mru_odel/lib.php#L113)  
**Severity:** LOW (trusted input source)

```php
$content .= "background-image: url('$imageurl');";
```

The URL comes from `$theme->setting_file_url()` which is Moodle's trusted internal method, so injection here is very unlikely. However, if the URL somehow contained a single quote, it would break the CSS. Use string escaping:

```php
$content .= "background-image: url('" . addcslashes($imageurl, "'\\") . "');";
```

---

### T-5: `rendererfactory` Set But No Custom Renderers Exist

**File:** [config.php:164](public/theme/mru_odel/config.php#L164)

```php
$THEME->rendererfactory = 'theme_overridden_renderer_factory';
```

This setting tells Moodle to look for custom renderer classes in the theme. None exist. This is harmless (Moodle falls back to core renderers) but creates false expectations. If you're not overriding any renderers, this line is noise. Either add a custom renderer or remove the line.

---

### T-6: Theme Settings Missing Language String for `footertext`

**File:** [settings.php](public/theme/mru_odel/settings.php)  

Audit all admin settings defined in `settings.php` to ensure every `$name` has a corresponding entry in `lang/en/theme_mru_odel.php`. Missing strings cause PHP notices on the settings page.

---

## 7. Plugin Deep-Dive Findings

### P-1: Step 3 Business Logic in Template Data Method

**File:** [classes/registration/step3.php:88-201](public/local/mru/classes/registration/step3.php#L88)  
**Severity:** MEDIUM (architecture)

The `get_template_data()` method in `step3` makes live API calls, performs MRU database lookups, and contains 100+ lines of business logic. Template data methods should only format data for display, not perform network operations.

**Recommendation:** Extract the lookup logic into a dedicated `identify_person(string $email, ?string $manualid)` method in `student_manager` or a new `identity_resolver` class. The step's `handle_action()` should call this and cache the result in the session, and `get_template_data()` should only read from the session.

---

### P-2: `register.php` — No Guard Against Logged-In Users

**File:** [register.php](public/local/mru/register.php)

The registration wizard is accessible to already-logged-in users. A logged-in student or admin can visit `/local/mru/register.php` and start a new registration session. This is at minimum confusing, and could allow account creation by logged-in admins in an unintended way.

**Fix:** Add a redirect at the top of `register.php`:
```php
if (isloggedin() && !isguestuser()) {
    redirect(new moodle_url('/my'));
}
```

---

### P-3: `course_manager::auto_map_courses()` Not Visible

The `auto_map_courses()` method is called by `sync_manager` but its implementation wasn't fully audited. Specifically, the query against `mru_main` for course details needs to validate that `$course->idnumber` (from Moodle) is sanitised before being used as a SQL parameter.

**Action:** Verify that all queries in `course_manager` use parameterised statements.

---

### P-4: Scheduled Tasks Don't Check `sync_enabled` Master Toggle

**File:** [classes/task/sync_all.php](public/local/mru/classes/task/sync_all.php)  

If `sync_enabled` is `false`, `sync_manager::run_full_sync()` respects it. But verify that the scheduled task itself exits gracefully when the master toggle is off, rather than running and logging nothing:

```php
public function execute(): void {
    if (!get_config('local_mru', 'sync_enabled')) {
        mtrace('MRU sync is disabled — skipping.');
        return;
    }
    // ...
}
```

---

### P-5: Privacy API — GDPR Compliance Gaps

**File:** [classes/privacy/provider.php](public/local/mru/classes/privacy/provider.php)

The plugin stores significant personal data across 6 tables including `core_data` JSON blobs containing full student profiles. The privacy provider exists, but verify it implements:
1. `export_user_data()` — exports all 6 tables' data for the given user.
2. `delete_data_for_user()` — deletes from all 6 tables.
3. `delete_data_for_users_in_context()` — batch deletion.
4. Correct metadata declarations for all `core_data` fields.

The current lang file only declares metadata for `local_mru_user_map` and `local_mru_marks_sync`. `local_mru_registrations` also contains personal data (email, IP address, OTP hash, firstname, lastname) that must be declared.

---

### P-6: OTP Email Uses Fake `$touser` Object with `id = -1`

**File:** [classes/otp_manager.php:148](public/local/mru/classes/otp_manager.php#L148)

```php
$touser->id = -1;
```

Moodle's `email_to_user()` function will work, but some Moodle plugins or event observers that hook into email sending may fail or log incorrectly when they encounter a user with `id = -1`. A safer approach is to use `$touser->id = 0` or create a noreply-equivalent dummy object following Moodle's documented pattern.

---

## 8. Code Quality & Maintainability

### Q-1: Plugin Maturity Declared as ALPHA in Production

**File:** [version.php](public/local/mru/version.php)

```php
$plugin->maturity = MATURITY_ALPHA;
```

If this plugin is running in production (which it is at `mruodel.com`), it should be `MATURITY_STABLE` or at minimum `MATURITY_BETA`. `MATURITY_ALPHA` generates admin warnings in Moodle's plugin management UI and may alarm administrators.

---

### Q-2: No Unit Tests or Behat Tests

The codebase has no `tests/` directory in either the theme or plugin. This means:
- Regression risk on any change.
- No automated verification of the registration wizard flow.
- No coverage of edge cases in `otp_manager`, `marks_manager`, or `api_client`.

**Priority tests to write:**
1. `otp_manager_test.php` — generation, verification, expiry, attempt limits.
2. `registration_manager_test.php` — session lifecycle, email validation, account creation.
3. `marks_manager_test.php` — grade collection, sync record creation.
4. `api_client_test.php` — mock HTTP responses, test error handling paths.

---

### Q-3: Missing `.gitignore` Entries

The repository should ignore:
```gitignore
*.bak
*.orig
public/theme/mru_odel/style/moodle.css
public/theme/mru_odel/amd/build/
node_modules/
vendor/
.vps-credentials
config.php
```

---

### Q-4: Hardcoded Table Name in `otp_manager`

**File:** [classes/otp_manager.php:60](public/local/mru/classes/otp_manager.php#L60)

```php
$DB->update_record(registration_manager::TABLE, $session);
```

This is acceptable since it uses the constant from `registration_manager::TABLE`. However, using Moodle's `$DB->update_record()` on a table name accessed via a constant in a different class is a tight coupling. Consider passing the updated session back to `registration_manager` to persist it, keeping DB writes encapsulated in one class.

---

### Q-5: `step3::handle_action('confirminfo')` — No Input Validation on Hidden Fields

**File:** [classes/registration/step3.php:40-46](public/local/mru/classes/registration/step3.php#L40)

```php
$mruid     = optional_param('mru_id', '', PARAM_RAW);
$firstname = optional_param('info_firstname', '', PARAM_TEXT);
$lastname  = optional_param('info_lastname', '', PARAM_TEXT);
```

`mru_id` uses `PARAM_RAW`. If a user manipulates the hidden form fields (which display data from the API), they can inject an arbitrary MRU ID into their session, bypassing the API verification entirely and associating themselves with someone else's student number.

**Fix:**
1. Use `PARAM_ALPHANUMEXT` for `mru_id`.
2. **Do not trust the form-submitted MRU ID at all.** Instead, re-fetch it from the API using the already-verified email (stored in the session). The session's email was OTP-verified; the form's MRU ID should be re-derived server-side, not accepted from the client.

---

### Q-6: Registration Session Uses Cookie Instead of Moodle Session

**File:** [classes/registration_manager.php](public/local/mru/classes/registration_manager.php)

The plugin implements its own cookie-based session management for the registration wizard. Moodle already provides `$SESSION` (PHP session wrapped in Moodle's session handler). Using a custom cookie:
- Creates duplicate session infrastructure.
- Requires its own CSRF mitigation.
- Complicates testing.

**Recommendation:** Store the registration state in `$SESSION->local_mru_registration` instead. This inherits Moodle's session security automatically.

---

## 9. Architecture & Design Recommendations

### A-1: Add a Central Error Handling / Retry Strategy for the API Client

Currently, if the Campus Dynamics API is down, every API call throws a `moodle_exception` immediately. There is no:
- Exponential backoff.
- Circuit breaker pattern (stop hammering a dead API).
- Graceful degradation (fall through to database-only mode).

The `student_manager::verify_student()` does have a DB fallback, but `marks_manager` and `course_manager` do not.

**Recommendation:** Create an API health check that runs once per request and short-circuits all API calls if the ping fails:
```php
if (!$this->api->ping()) {
    throw new moodle_exception('error:api_unavailable', 'local_mru');
}
```

And add Moodle cache-based circuit breaker state.

---

### A-2: Server-Side Email Immutability Should Also Block Profile Form Submission

Beyond the event observer (see C-3), also override the `\core_user\hook\before_user_updated` hook (Moodle 5.x) to validate the email field before the save occurs, providing a cleaner user-facing error rather than a silent revert.

---

### A-3: Add an Admin "Test Connection" Page

Admins can configure the API URL, username, password, and DB credentials, but there's no way to test them without triggering a real sync. Add a simple AJAX endpoint that:
1. Calls `api_client::ping()` and reports the result.
2. Calls `db_manager::test_connection()` and reports the result.

This saves hours of debugging when credentials are wrong.

---

### A-4: Marks Sync Should Support `conflict` Status

The `local_mru_marks_sync.sync_status` enum includes `conflict` but `marks_manager` never sets it. Define what a conflict means (e.g., Moodle grade differs from MRU core grade) and implement conflict detection on bidirectional sync. This is especially important if marks ever flow from MRU core back into Moodle.

---

### A-5: Add Pagination and Search to the Admin Dashboard

The dashboard fetches all sync logs, all course mappings, and all user mappings without limits. As the dataset grows, the dashboard page will slow significantly.

Use `$DB->get_records()` with `$limitfrom` and `$limitnum` parameters, and add a search/filter UI.

---

## 10. Deployment & Operations

### D-1: Disable Debug Mode for Production

**File:** Root `config.php`

The local development config has:
```php
$CFG->debug = E_ALL;
$CFG->debugdisplay = 1;
```

Ensure the production `config.php` (on the VPS) has:
```php
$CFG->debug = 0;
$CFG->debugdisplay = 0;
```

And also check that the `DEV MODE — Email failed. Your OTP code is: ...` message in `step2.php` is gated behind `$CFG->debugdeveloper` (it is — but verify this is false in production).

---

### D-2: Set Up Log Rotation for Access Logs

Given that API tokens currently appear in server access logs (see C-2), ensure Apache/Nginx logs are rotated and purged on a schedule (e.g., 7-day retention). Until C-2 is fixed, limit log exposure.

---

### D-3: Force HTTPS at Server Level

The Apache/Nginx config should enforce HTTPS with a 301 redirect for all HTTP traffic. Do not rely on Moodle's `$CFG->sslproxy` alone.

```apache
<VirtualHost *:80>
    ServerName mruodel.com
    Redirect permanent / https://mruodel.com/
</VirtualHost>
```

---

### D-4: Scheduled Task Configuration

Verify in Moodle admin that the two scheduled tasks are configured:
- `local_mru\task\sync_all` — consider running at 2am nightly (off-peak).
- `local_mru\task\verify_students` — run weekly or on demand.

Ensure `$CFG->cronclionly = true` in production config to prevent web-triggered cron.

---

### D-5: Database Indexes for Performance

Verify these indexes exist (from `install.xml`):
- `local_mru_marks_sync`: index on `(courseid, sync_status)` — needed for `get_pending_syncs()`.
- `local_mru_marks_sync`: index on `(userid)` — needed for student-level sync queries.
- `local_mru_user_map`: index on `(mru_id)` — currently unique, good.

Add composite index on `(courseid, userid, academic_year, semester)` for idempotent sync upserts (see H-4).

---

## 11. Priority Action Plan

### Immediate (Before Any Production Deployment)

| # | Item | File(s) | Fix Time |
|---|---|---|---|
| 1 | Rotate all `.vps-credentials` and purge from git history | `.vps-credentials` | 2 hours |
| 2 | Add `confirm_sesskey()` to all registration POST actions | `step2-4.php`, `register.php` | 1 hour |
| 3 | Add server-side email immutability event observer | `db/events.php`, new observer method | 2 hours |
| 4 | Move API token from query string to header | `api_client.php` | 1 hour |

### This Sprint (Next 2 Weeks)

| # | Item | File(s) | Fix Time |
|---|---|---|---|
| 5 | Fix password complexity validation | `registration_manager.php` | 30 min |
| 6 | Fix predictable imported student passwords | `student_manager.php` | 1 hour |
| 7 | Fix marks sync — validate API response per-student | `marks_manager.php` | 3 hours |
| 8 | Fix marks sync idempotency (upsert) | `marks_manager.php`, DB migration | 2 hours |
| 9 | Add `secure` flag to registration cookie | `registration_manager.php` | 15 min |
| 10 | Implement `verify_on_login` and `verify_on_enrol` | `db/events.php`, `event/observer.php` | 3 hours |
| 11 | Fix `PARAM_RAW` to `PARAM_ALPHANUMEXT` for student ID | `step3.php` | 15 min |
| 12 | Fix `reg:otp_sent` lang string call — pass email as `$a` | `step2.php`, `step2` resend | 15 min |
| 13 | Fix `grade_item_id` missing in sync record | `marks_manager.php` | 30 min |
| 14 | Fix OTP attempts increment order (check expiry first) | `otp_manager.php` | 15 min |

### Next Month

| # | Item | Fix Time |
|---|---|---|
| 15 | Implement API token caching via Moodle application cache | 2 hours |
| 16 | Add 401 retry max count to prevent infinite recursion | 30 min |
| 17 | Batch `import_students()` to avoid memory exhaustion | 2 hours |
| 18 | Remove precompiled CSS from repo; remove `precompiledcsscallback` | 1 hour |
| 19 | Fix SCSS double-application (pre/post in both main and callbacks) | 1 hour |
| 20 | Remove .bak files from git, update .gitignore | 15 min |
| 21 | Add guard to `register.php` to redirect logged-in users | 15 min |
| 22 | Sanitise `mru_id` from step3 form — re-derive from session | 2 hours |
| 23 | Add step3 API error message to template data | 30 min |
| 24 | Fix `local_mru_registrations.email` NOT NULL vs empty string | 30 min |
| 25 | Complete GDPR privacy provider for all 6 tables | 3 hours |
| 26 | Add admin connection-test page | 2 hours |
| 27 | Set plugin maturity to STABLE | 5 min |
| 28 | Write PHPUnit tests for OTP, registration, marks managers | 8 hours |

---

## Appendix: Strengths Worth Preserving

The following are well-implemented and should be kept as reference patterns:

- **OTP security:** `random_int()` for generation, `password_hash/verify()` for storage — correct.
- **Session token generation:** `bin2hex(random_bytes(32))` — cryptographically secure.
- **Token validation in `get_session_token()`:** strict regex `/^[a-f0-9]{64}$/` — good.
- **Capability system:** 5 capabilities across system and course contexts are correctly defined.
- **Audit logging:** `local_mru_sync_log` with start/finish timestamps, initiated_by, and error summaries — excellent for operations.
- **DB parameterisation:** `db_manager::query()` uses `bind_param()` — no SQL injection risk.
- **API client architecture:** Clean separation of endpoints into logical groupings (academic, finance, marks, campus info).
- **Registration wizard abstraction:** `base_step` + concrete steps is a clean pattern.
- **Moodle standards:** All files have GPL headers, `defined('MOODLE_INTERNAL') || die()` guards, and proper namespace declarations.
- **Error message localisation:** Comprehensive lang file with strings for all UI states.

---

*Report generated by automated deep analysis of all PHP, SCSS, JS, XML, and Mustache files in the project.*
