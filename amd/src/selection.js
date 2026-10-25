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
 * Client-side helpers for the course-content selection form.
 *
 * @module     mod_fredkicourseimport/selection
 * @copyright  2026 lernsteine.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

export const init = () => {
    const form = document.getElementById('fredki-selection-form');
    if (!form || form.dataset.selectionInitialised === '1') {
        return;
    }
    form.dataset.selectionInitialised = '1';

    const links = form.querySelectorAll('.fredki-selection-link[data-selection-mode]');
    const activityCheckboxes = form.querySelectorAll('.fredki-select-activity');
    const sectionCheckbox = form.querySelector('input[name="includesectiontexts"]');
    const statusDot = document.getElementById('fredki-mini-live-dot');
    const statusText = document.getElementById('fredki-mini-live-text');
    const submitButton = document.getElementById('fredki-import-link');

    function setUploadStatus(state, message) {
        if (statusText) {
            statusText.textContent = message;
        }
        if (statusDot) {
            statusDot.classList.remove('is-ready', 'is-working', 'is-success', 'is-error');
            statusDot.classList.add(state);
        }
    }

    form.addEventListener('submit', function() {
        setUploadStatus('is-working', form.dataset.processingLabel || 'Processing…');
        form.setAttribute('aria-busy', 'true');
        if (submitButton) {
            submitButton.disabled = true;
        }
    });

    // Browsers may restore this page from the back/forward cache with the old DOM state.
    // Never leave the control visually stuck on Processing after navigation completed.
    window.addEventListener('pageshow', function(event) {
        if (!event.persisted) {
            return;
        }
        form.removeAttribute('aria-busy');
        if (submitButton) {
            submitButton.disabled = false;
        }
        setUploadStatus('is-ready', form.dataset.readyLabel || 'Ready');
    });

    function updateActiveLink(mode) {
        links.forEach(function(link) {
            const active = link.dataset.selectionMode === mode;
            link.classList.toggle('is-active', active);
            if (active) {
                link.setAttribute('aria-current', 'page');
            } else {
                link.removeAttribute('aria-current');
            }
        });
    }

    function applySelectionMode(mode) {
        activityCheckboxes.forEach(function(checkbox) {
            if (mode === 'all') {
                checkbox.checked = true;
            } else if (mode === 'none') {
                checkbox.checked = false;
            } else {
                checkbox.checked = checkbox.dataset.visibleParticipant === '1';
            }
        });
        if (sectionCheckbox) {
            sectionCheckbox.checked = mode !== 'none';
        }
        updateActiveLink(mode);
    }

    // Highlight the toolbar mode that matches the current checkboxes (none for a custom selection).
    function matchesMode(mode) {
        if (sectionCheckbox && sectionCheckbox.checked !== (mode !== 'none')) {
            return false;
        }
        return Array.from(activityCheckboxes).every(function(checkbox) {
            const expected = mode === 'all' || (mode === 'visible' && checkbox.dataset.visibleParticipant === '1');
            return checkbox.checked === expected;
        });
    }

    function refreshActiveLink() {
        const mode = ['visible', 'all', 'none'].find(matchesMode) || '';
        updateActiveLink(mode);
    }

    form.querySelectorAll('.fredki-select-item').forEach(function(checkbox) {
        checkbox.addEventListener('change', refreshActiveLink);
    });

    links.forEach(function(link) {
        link.addEventListener('click', function(event) {
            const mode = link.dataset.selectionMode;
            if (!['visible', 'all', 'none'].includes(mode)) {
                return;
            }
            event.preventDefault();
            applySelectionMode(mode);
        });
    });

    const applyButton = document.getElementById('fredki-apply-contentmode');
    if (applyButton) {
        applyButton.addEventListener('click', function() {
            const selectedMode = form.querySelector('input[name="fredki_bulk_contentmode"]:checked');
            if (!selectedMode) {
                return;
            }
            activityCheckboxes.forEach(function(checkbox) {
                if (!checkbox.checked) {
                    return;
                }
                const row = checkbox.closest('.fredki-selection-row');
                if (!row) {
                    return;
                }
                const target = row.querySelector(
                    '.fredki-contentmode-radio[value="' + selectedMode.value + '"]'
                );
                if (target) {
                    target.checked = true;
                }
            });
        });
    }
};
