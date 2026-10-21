// Copyright (c) 2026 lernsteine.com
// SPDX-License-Identifier: GPL-3.0-or-later
// Inline settings accordion: independent POST, never submits the Moodle edit form.
(function() {
    'use strict';

    function initialise() {
        const panel = document.getElementById('fredki-credentials-panel');
        if (!panel) {
            return;
        }
        const button = document.getElementById('fredki-settings-submit');
        const username = document.getElementById('fredki-settings-user');
        const password = document.getElementById('fredki-settings-password');
        const remember = document.getElementById('fredki-settings-remember');
        const message = document.getElementById('fredki-settings-message');
        if (!button || !username || !password || !remember || !message) {
            return;
        }

        function showMessage(value, success) {
            message.textContent = value;
            message.className = 'fredki-credentials-message ' +
                (success ? 'text-success' : 'text-danger');
        }

        button.addEventListener('click', async function() {
            const user = username.value.trim();
            const pass = password.value;
            if (!user || !pass) {
                showMessage(panel.dataset.messageRequired || '', false);
                return;
            }
            button.disabled = true;
            message.textContent = '';
            try {
                const payload = new URLSearchParams({
                    id: panel.dataset.cmid,
                    sesskey: panel.dataset.sesskey,
                    fredkiusername: user,
                    fredkipassword: pass,
                    fredkiautorenew: remember.checked ? '1' : '0'
                });
                const response = await fetch(panel.dataset.endpoint, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
                    body: payload.toString()
                });
                const result = await response.json();
                if (!response.ok || !result.success) {
                    throw new Error(result.message || panel.dataset.messageFailed || '');
                }
                password.value = '';
                showMessage(result.message, true);
            } catch (error) {
                showMessage(error.message || panel.dataset.messageFailed || '', false);
            } finally {
                button.disabled = false;
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialise);
    } else {
        initialise();
    }
})();
