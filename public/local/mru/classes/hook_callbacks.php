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

namespace local_mru;

/**
 * Output hook callbacks for local_mru.
 *
 * @package    local_mru
 * @copyright  2026 Mutesa I Royal University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_callbacks {
    /**
     * Inject profile-edit UI guard for users with verified MRU mapping.
     *
     * Locks the email field client-side and shows MRU student/staff number.
     *
     * @param \core\hook\output\before_standard_head_html_generation $hook
     */
    public static function before_standard_head_html_generation(
        \core\hook\output\before_standard_head_html_generation $hook,
    ): void {
        global $DB, $PAGE, $USER;

        if (!isloggedin() || isguestuser() || empty($PAGE->url)) {
            return;
        }

        if (!str_ends_with($PAGE->url->get_path(), '/user/edit.php')) {
            return;
        }

        $userid = optional_param('id', $USER->id, PARAM_INT);
        $mapping = $DB->get_record('local_mru_user_map', ['userid' => $userid, 'verified' => 1]);
        if (!$mapping) {
            return;
        }

        $lockedemail = \local_mru_get_locked_verified_email($mapping, $userid);
        if (empty($lockedemail)) {
            return;
        }

        $lockedemailjs = json_encode($lockedemail);
        $mruidjs = json_encode((string)$mapping->mru_id);

        $hook->add_html('<style>
            .local-mru-profile-note {
                margin-top: .45rem;
                font-size: .8rem;
                color: #475569;
                line-height: 1.45;
            }
            .local-mru-profile-note strong { color: #0f172a; }
            .local-mru-email-locked {
                background: #f8fafc !important;
                cursor: not-allowed;
            }
        </style>
        <script>
            document.addEventListener("DOMContentLoaded", function() {
                var emailInput = document.getElementById("id_email") || document.querySelector("input[name=email]");
                if (!emailInput) {
                    return;
                }

                var lockedEmail = ' . $lockedemailjs . ';
                var mruId = ' . $mruidjs . ';

                emailInput.value = lockedEmail;
                emailInput.setAttribute("readonly", "readonly");
                emailInput.setAttribute("aria-readonly", "true");
                emailInput.classList.add("local-mru-email-locked");
                emailInput.title = "This verified email cannot be changed.";

                var fieldWrap = emailInput.closest(".fitem") || emailInput.closest(".form-group") || emailInput.parentElement;
                if (!fieldWrap) {
                    return;
                }

                if (!fieldWrap.querySelector(".local-mru-profile-note")) {
                    var note = document.createElement("div");
                    note.className = "local-mru-profile-note";
                    note.innerHTML = "<strong>MRU Student/Staff Number:</strong> " + mruId +
                        "<br><strong>Email status:</strong> Verified and locked.";
                    fieldWrap.appendChild(note);
                }
            });
        </script>');
    }
}
