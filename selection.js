/*
 * Client-side helpers for the course-content selection form.
 *
 * @package    mod_fredkicourseimport
 */
(function() {
    'use strict';

    function initialise() {
        var form = document.getElementById('fredki-selection-form');
        if (!form || form.dataset.selectionInitialised === '1') {
            return;
        }
        form.dataset.selectionInitialised = '1';

        var links = form.querySelectorAll('.fredki-selection-link[data-selection-mode]');
        var activityCheckboxes = form.querySelectorAll('.fredki-select-activity');
        var sectionCheckbox = form.querySelector('input[name="includesectiontexts"]');
        var statusDot = document.getElementById('fredki-mini-live-dot');
        var statusText = document.getElementById('fredki-mini-live-text');
        var submitButton = document.getElementById('fredki-import-link');

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
                var active = link.dataset.selectionMode === mode;
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

        links.forEach(function(link) {
            link.addEventListener('click', function(event) {
                var mode = link.dataset.selectionMode;
                if (!['visible', 'all', 'none'].includes(mode)) {
                    return;
                }
                event.preventDefault();
                applySelectionMode(mode);
            });
        });

        var applyButton = document.getElementById('fredki-apply-contentmode');
        if (applyButton) {
            applyButton.addEventListener('click', function() {
                var selectedMode = form.querySelector('input[name="fredki_bulk_contentmode"]:checked');
                if (!selectedMode) {
                    return;
                }
                activityCheckboxes.forEach(function(checkbox) {
                    if (!checkbox.checked) {
                        return;
                    }
                    var row = checkbox.closest('.fredki-selection-row');
                    if (!row) {
                        return;
                    }
                    var target = row.querySelector(
                        '.fredki-contentmode-radio[value="' + selectedMode.value + '"]'
                    );
                    if (target) {
                        target.checked = true;
                    }
                });
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialise);
    } else {
        initialise();
    }
}());
