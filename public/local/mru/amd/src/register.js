/**
 * MRU ODEL Registration Wizard — client-side logic.
 *
 * Handles: OTP input focus management, password strength meter,
 * password toggle, confirm match indicator, form validation.
 *
 * @module     local_mru/register
 * @copyright  2026 Mutesa I Royal University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {
    return {
        init: function() {
            this.initOtpInputs();
            this.initPasswordStrength();
            this.initPasswordToggle();
            this.initPasswordMatch();
            this.initFormValidation();
            this.initCancelConfirm();
        },

        /**
         * OTP single input: strip non-digits, auto-focus on load.
         */
        initOtpInputs: function() {
            var input = document.getElementById('reg-otp');
            if (!input) {
                return;
            }

            input.addEventListener('input', function() {
                this.value = this.value.replace(/[^0-9]/g, '').substring(0, 6);
            });

            input.addEventListener('paste', function(e) {
                e.preventDefault();
                var paste = (e.clipboardData || window.clipboardData).getData('text');
                this.value = paste.replace(/[^0-9]/g, '').substring(0, 6);
            });

            input.focus();
        },

        /**
         * Password strength meter with live requirement checks.
         */
        initPasswordStrength: function() {
            var input = document.getElementById('reg-password');
            var meter = document.getElementById('pwd-strength');
            if (!input || !meter) {
                return;
            }

            var bar = meter.querySelector('.pwd-bar span');
            var reqLength = meter.querySelector('[data-req="length"]');

            input.addEventListener('input', function() {
                var val = input.value;
                var met = val.length >= 4;

                if (reqLength) {
                    reqLength.classList.toggle('met', met);
                    reqLength.classList.toggle('unmet', !met && val.length > 0);
                }

                // Simple bar: red below 4, green at 4+, brighter green for longer.
                var pct = 0;
                var color = '#dc3545';
                if (val.length >= 4) {
                    pct = Math.min(40 + (val.length - 4) * 7, 100);
                    color = pct >= 80 ? '#198754' : '#CB9C2C';
                } else if (val.length > 0) {
                    pct = (val.length / 4) * 38;
                }

                if (bar) {
                    bar.style.width = pct + '%';
                    bar.style.backgroundColor = color;
                }
            });
        },

        /**
         * Password toggle visibility.
         */
        initPasswordToggle: function() {
            var toggles = document.querySelectorAll('.pwd-toggle');
            toggles.forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var group = btn.closest('.mru-input-icon');
                    var input = group ? group.querySelector('input') : null;
                    if (!input) { return; }
                    var icon = btn.querySelector('i');
                    if (input.type === 'password') {
                        input.type = 'text';
                        if (icon) { icon.className = 'fa fa-eye-slash'; }
                    } else {
                        input.type = 'password';
                        if (icon) { icon.className = 'fa fa-eye'; }
                    }
                });
            });
        },

        /**
         * Password confirmation match indicator.
         */
        initPasswordMatch: function() {
            var pass = document.getElementById('reg-password');
            var confirm = document.getElementById('reg-password-confirm');
            var msg = document.getElementById('pwd-match-msg');
            if (!pass || !confirm || !msg) { return; }

            function check() {
                if (confirm.value === '') {
                    msg.textContent = '';
                    msg.className = 'pwd-match-msg';
                    return;
                }
                if (pass.value === confirm.value) {
                    msg.textContent = 'Passwords match';
                    msg.className = 'pwd-match-msg match-ok';
                } else {
                    msg.textContent = 'Passwords do not match';
                    msg.className = 'pwd-match-msg match-fail';
                }
            }

            pass.addEventListener('input', check);
            confirm.addEventListener('input', check);
        },

        /**
         * Client-side form validation before submit.
         */
        initFormValidation: function() {
            var form = document.getElementById('create-account-form');
            if (!form) { return; }

            form.addEventListener('submit', function(e) {
                var pass = document.getElementById('reg-password');
                var confirm = document.getElementById('reg-password-confirm');
                var firstname = document.getElementById('reg-firstname');
                var lastname = document.getElementById('reg-lastname');
                var errors = [];

                if (firstname && firstname.value.trim() === '') {
                    errors.push('First name is required.');
                }
                if (lastname && lastname.value.trim() === '') {
                    errors.push('Last name is required.');
                }
                if (pass && pass.value.length < 4) {
                    errors.push('Password must be at least 4 characters.');
                }
                if (pass && confirm && pass.value !== confirm.value) {
                    errors.push('Passwords do not match.');
                }

                if (errors.length > 0) {
                    e.preventDefault();
                    // Show errors inline.
                    var existing = form.querySelector('.reg-client-errors');
                    if (existing) { existing.remove(); }
                    var div = document.createElement('div');
                    div.className = 'reg-client-errors alert alert-danger';
                    div.textContent = errors.join(' ');
                    form.insertBefore(div, form.firstChild);
                    div.scrollIntoView({behavior: 'smooth', block: 'center'});
                }
            });
        },

        /**
         * Cancel registration — ask the user to confirm before submitting.
         */
        initCancelConfirm: function() {
            var btn = document.querySelector('.btn-wizard-cancel');
            if (!btn) { return; }

            btn.addEventListener('click', function(e) {
                if (!window.confirm('Cancel registration?\n\nYour progress will be lost and you will be returned to the login page.')) {
                    e.preventDefault();
                }
            });
        }
    };
});
