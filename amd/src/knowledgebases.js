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
 * Knowledge base picker of the FredKI activity settings form.
 *
 * @module     mod_fredkicourseimport/knowledgebases
 * @copyright  2026 lernsteine.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Initialise the knowledge base picker.
 *
 * @param {Object} config Element ids, endpoint and language strings passed from PHP.
 */
export const init = (config) => {
    const button = document.getElementById(config.buttonId);
    if (!button) {
        return;
    }

    const statusNode = document.getElementById(config.statusId);
    const selectNode = document.getElementById(config.knowledgeFieldId);
    const nameNode = document.getElementById('id_knowledgebasename');
    const selectedNode = document.getElementById('fredki-knowledgebase-selected');
    const cardsNode = document.getElementById('fredki-knowledgebase-cards');
    const usernameNode = document.getElementById(config.usernameId);
    const passwordNode = document.getElementById(config.passwordId);
    const workspaceNode = document.getElementById(config.workspaceId);

    const selectRow = selectNode ? selectNode.closest('.fitem, .form-group, .mb-3') : null;
    if (selectRow) {
        selectRow.style.display = 'none';
    }

    const setStatus = (message, cssclass) => {
        if (!statusNode) {
            return;
        }
        statusNode.className = cssclass || '';
        statusNode.textContent = message || '';
    };

    const getInitial = (name) => {
        const trimmed = (name || '').trim();
        return trimmed ? trimmed.charAt(0).toUpperCase() : '?';
    };

    const updateSelectedText = (name) => {
        if (!selectedNode) {
            return;
        }
        if (name) {
            selectedNode.textContent = config.strings.selectedPrefix + ' ';
            const strong = document.createElement('strong');
            strong.textContent = name;
            selectedNode.appendChild(strong);
        } else {
            selectedNode.textContent = config.strings.none;
        }
    };

    const syncNameFromSelect = () => {
        if (!selectNode || !nameNode) {
            return;
        }
        const selected = selectNode.options[selectNode.selectedIndex];
        const name = selected && selected.value ? selected.textContent : '';
        nameNode.value = name;
        updateSelectedText(name);
    };

    const renderCards = () => {
        if (!selectNode || !cardsNode) {
            return;
        }
        cardsNode.innerHTML = '';
        Array.from(selectNode.options).forEach((option) => {
            if (!option.value) {
                return;
            }
            const card = document.createElement('button');
            card.type = 'button';
            card.className = 'fredki-kb-card' + (option.selected ? ' is-selected' : '');

            const avatar = document.createElement('span');
            avatar.className = 'fredki-kb-avatar';
            avatar.textContent = getInitial(option.textContent);

            const body = document.createElement('span');
            body.className = 'fredki-kb-body';

            const title = document.createElement('span');
            title.className = 'fredki-kb-title';
            title.textContent = option.textContent;

            const subtitle = document.createElement('span');
            subtitle.className = 'fredki-kb-subtitle';
            subtitle.textContent = config.strings.subtitle;

            const action = document.createElement('span');
            action.className = 'fredki-kb-action';
            action.textContent = option.selected ? config.strings.selectedButton : config.strings.chooseButton;

            const brand = document.createElement('span');
            brand.className = 'fredki-kb-brand';
            brand.textContent = 'FredKI';

            body.appendChild(title);
            body.appendChild(subtitle);
            body.appendChild(action);
            card.appendChild(avatar);
            card.appendChild(body);
            card.appendChild(brand);
            card.addEventListener('click', () => {
                selectNode.value = option.value;
                syncNameFromSelect();
                renderCards();
            });
            cardsNode.appendChild(card);
        });
    };

    const resetOptions = () => {
        if (!selectNode) {
            return;
        }
        selectNode.innerHTML = '';
        const option = document.createElement('option');
        option.value = '';
        option.textContent = config.strings.choose;
        selectNode.appendChild(option);
        syncNameFromSelect();
        renderCards();
    };

    if (selectNode) {
        selectNode.addEventListener('change', () => {
            syncNameFromSelect();
            renderCards();
        });
    }
    syncNameFromSelect();
    renderCards();

    button.addEventListener('click', async() => {
        const username = usernameNode ? usernameNode.value.trim() : '';
        const password = passwordNode ? passwordNode.value : '';
        const workspace = workspaceNode && workspaceNode.value ? workspaceNode.value : 'default';
        const usesavedNode = document.getElementById('id_fredkiusesaved');
        const usesaved = !!(usesavedNode && usesavedNode.checked);

        if (!usesaved && (!username || !password)) {
            setStatus(config.strings.enterCredentials, 'alert alert-warning');
            return;
        }

        button.disabled = true;
        resetOptions();
        setStatus(config.strings.loading, 'alert alert-info');

        try {
            const response = await fetch(config.endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                },
                body: new URLSearchParams({
                    sesskey: config.sesskey,
                    courseid: String(config.courseId),
                    username: username,
                    password: password,
                    usesaved: usesaved ? '1' : '0',
                    workspace: workspace,
                }),
            });

            const data = await response.json();
            if (!response.ok || !data.success) {
                throw new Error(data.error || config.strings.loadError);
            }

            resetOptions();
            (data.items || []).forEach((item) => {
                const option = document.createElement('option');
                option.value = String(item.id || '');
                option.textContent = String(item.name || item.id || '');
                if (option.value) {
                    selectNode.appendChild(option);
                }
            });
            renderCards();
            setStatus((data.items || []).length + ' ' + config.strings.loadedSuffix, 'alert alert-success');
        } catch (error) {
            setStatus(error.message || config.strings.loadError, 'alert alert-danger');
        } finally {
            button.disabled = false;
        }
    });
};
