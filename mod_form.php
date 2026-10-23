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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * FredKI course import plugin file.
 *
 * @package    mod_fredkicourseimport
 * @copyright  2026 lernsteine.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');
require_once($CFG->dirroot . '/mod/fredkicourseimport/classes/local/fredki_client.php');
require_once($CFG->dirroot . '/mod/fredkicourseimport/classes/local/license_manager.php');

/**
 * Defines the Moodle settings form for the FredKI Course Import activity.
 *
 * @copyright 2026 lernsteine.com
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_fredkicourseimport_mod_form extends moodleform_mod {
    /**
     * Define the FredKI activity settings form.
     * @return mixed Result of the operation.
     */
    public function definition() {
        $mform = $this->_form;
        $config = get_config('mod_fredkicourseimport');
        $current = $this->current;
        $courseid = (int)$this->get_course()->id;

        $locked = !empty($current->knowledgeid);
        $licensestatus = \mod_fredkicourseimport\local\license_manager::get_license_status();
        $licensevalid = !empty($licensestatus['valid']);
        $knowledgeoptions = ['' => get_string('chooseknowledgebase', 'mod_fredkicourseimport')];

        $submittedusername = optional_param('fredkiusername', '', PARAM_TEXT);
        $submittedpassword = optional_param('fredkipassword', '', PARAM_RAW_TRIMMED);
        $submittedworkspace = optional_param('workspace', '', PARAM_TEXT);

        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('name'), ['size' => 64]);
        $mform->setType('name', PARAM_TEXT);
        $mform->setDefault('name', get_string('defaultactivityname', 'mod_fredkicourseimport'));
        $mform->addRule('name', null, 'required', null, 'client');

        $this->standard_intro_elements();

        $mform->addElement('hidden', 'workspace', 'default');
        $mform->setType('workspace', PARAM_TEXT);

        $mform->addElement('hidden', 'syncenabled', 1);
        $mform->setType('syncenabled', PARAM_INT);

        $mform->addElement('header', 'fredkisettingsheader', get_string('fredkisettingsheader', 'mod_fredkicourseimport'));

        if (!$licensevalid) {
            $message = get_string('licenseblockmessage', 'mod_fredkicourseimport');
            if (!empty($licensestatus['message'])) {
                $message .= '<br>' . s($licensestatus['message']);
            }
            $mform->addElement('static', 'fredkilicenseblocked', '', html_writer::div($message, 'alert alert-danger'));
        }

        if (!$licensevalid) {
            $mform->addElement('static', 'fredkisettingsdisabled', '', get_string('licenseconfigurefirst', 'mod_fredkicourseimport'));
        } else if ($locked) {
            $lockedinfo = get_string('activitymappinglockedinfo', 'mod_fredkicourseimport');
            if (!empty($current->knowledgebasename)) {
                $lockedinfo .= html_writer::tag(
                    'p',
                    get_string('selectedknowledgebase', 'mod_fredkicourseimport') . ' ' .
                    html_writer::tag('strong', s($current->knowledgebasename)),
                    ['class' => 'mt-2 mb-0']
                );
            } else if (!empty($current->knowledgeid)) {
                $lockedinfo .= html_writer::tag(
                    'p',
                    get_string('selectedknowledgebase', 'mod_fredkicourseimport') . ' ' .
                    html_writer::tag('strong', s($current->knowledgeid)),
                    ['class' => 'mt-2 mb-0']
                );
            }
            $mform->addElement('static', 'fredkisettingslockedinfo', '', $lockedinfo);

            // Keep credentials editable directly below the locked knowledge-base details.
            // This is a separate AJAX operation: editing the course activity must not
            // inadvertently re-authorise, revoke consent or touch the knowledge base.
            $targetcmid = (int)($current->coursemodule ?? 0);
            if (!$targetcmid && !empty($current->id)) {
                $targetcm = get_coursemodule_from_instance(
                    'fredkicourseimport',
                    (int)$current->id,
                    $courseid,
                    false,
                    IGNORE_MISSING
                );
                $targetcmid = $targetcm ? (int)$targetcm->id : 0;
            }
            if (
                $targetcmid && has_capability(
                    'mod/fredkicourseimport:sync',
                    \context_module::instance($targetcmid)
                )
            ) {
                global $DB, $USER, $PAGE;
                $reauthusername = '';
                $remember = false;
                if (
                    !empty($current->authorizedbyuserid) &&
                        (int)$current->authorizedbyuserid === (int)$USER->id
                ) {
                    $auth = $DB->get_record('fredkicourseimport_auth', [
                        'activityid' => (int)$current->id,
                        'userid' => (int)$USER->id,
                    ], 'id,fredkiusername,passwordencrypted');
                    if ($auth) {
                        $reauthusername = (string)$auth->fredkiusername;
                        $remember = !empty($auth->passwordencrypted);
                    }
                }
                $reauthurl = (new \moodle_url('/mod/fredkicourseimport/reauth_ajax.php'))->out(false);
                $panelattrs = [
                    'class' => 'fredki-credentials-panel',
                    'id' => 'fredki-credentials-panel',
                    'data-endpoint' => $reauthurl,
                    'data-cmid' => $targetcmid,
                    'data-sesskey' => sesskey(),
                    'data-message-required' => get_string('reauthmissingcredentials', 'mod_fredkicourseimport'),
                    'data-message-failed' => get_string('reauthajaxfailed', 'mod_fredkicourseimport'),
                ];
                $panel = \html_writer::start_tag('details', $panelattrs);
                $panel .= \html_writer::tag(
                    'summary',
                    get_string('reauthsettingssummary', 'mod_fredkicourseimport')
                );
                $panel .= \html_writer::start_div('fredki-credentials-fields');
                $panel .= \html_writer::tag(
                    'p',
                    get_string('reauthhelp', 'mod_fredkicourseimport'),
                    ['class' => 'text-muted']
                );
                $panel .= \html_writer::tag(
                    'label',
                    get_string('fredkiusername', 'mod_fredkicourseimport'),
                    ['for' => 'fredki-settings-user']
                );
                $panel .= \html_writer::empty_tag('input', [
                    'type' => 'text', 'id' => 'fredki-settings-user',
                    'value' => $reauthusername, 'autocomplete' => 'username',
                    'class' => 'form-control',
                ]);
                $panel .= \html_writer::tag(
                    'label',
                    get_string('fredkipassword', 'mod_fredkicourseimport'),
                    ['for' => 'fredki-settings-password']
                );
                $panel .= \html_writer::empty_tag('input', [
                    'type' => 'password', 'id' => 'fredki-settings-password',
                    'autocomplete' => 'current-password', 'class' => 'form-control',
                ]);
                // The new authoriser must always grant their own opt-in.
                $panel .= \html_writer::tag(
                    'label',
                    \html_writer::empty_tag('input', [
                        'type' => 'checkbox', 'id' => 'fredki-settings-remember',
                        'checked' => $remember ? 'checked' : null,
                    ]) . ' ' . get_string('reauthremember', 'mod_fredkicourseimport'),
                    ['class' => 'fredki-credentials-remember']
                );
                $panel .= \html_writer::tag('button', get_string('reauthbutton', 'mod_fredkicourseimport'), [
                    'type' => 'button', 'class' => 'btn btn-primary', 'id' => 'fredki-settings-submit',
                ]);
                $panel .= \html_writer::div('', 'fredki-credentials-message', [
                    'id' => 'fredki-settings-message', 'role' => 'status', 'aria-live' => 'polite',
                ]);
                $panel .= \html_writer::end_div();
                $panel .= \html_writer::end_tag('details');
                $mform->addElement(
                    'static',
                    'fredkireconnectsettings',
                    get_string('reauthsettingslabel', 'mod_fredkicourseimport'),
                    $panel
                );
                $PAGE->requires->js(new \moodle_url('/mod/fredkicourseimport/reconnect.js'));
            }
        } else {
            $mform->addElement('text', 'fredkiusername', get_string('fredkiusername', 'mod_fredkicourseimport'), [
                'size' => 40,
                'autocomplete' => 'off',
            ]);
            $mform->setType('fredkiusername', PARAM_TEXT);
            $mform->addRule('fredkiusername', null, 'required', null, 'client');

            $mform->addElement('passwordunmask', 'fredkipassword', get_string('fredkipassword', 'mod_fredkicourseimport'), [
                'size' => 40,
                'autocomplete' => 'new-password',
            ]);
            $mform->setType('fredkipassword', PARAM_RAW_TRIMMED);
            $mform->addRule('fredkipassword', null, 'required', null, 'client');

            $mform->addElement('advcheckbox', 'fredkiautorenew', get_string('reauthremember', 'mod_fredkicourseimport'));
            $mform->setDefault('fredkiautorenew', 0);

            $usernameforoptions = '';
            $passwordforoptions = '';
            $workspaceforoptions = (string)($current->workspace ?? 'default');

            if ($submittedusername !== '' && $submittedpassword !== '') {
                $usernameforoptions = $submittedusername;
                $passwordforoptions = $submittedpassword;
                if ($submittedworkspace !== '') {
                    $workspaceforoptions = $submittedworkspace;
                }
            }

            if ($licensevalid && $usernameforoptions !== '' && $passwordforoptions !== '') {
                $knowledgeoptions += $this->load_knowledge_base_options(
                    $config,
                    $usernameforoptions,
                    $passwordforoptions,
                    $workspaceforoptions
                );
            }

            $buttonid = 'id_fredki_load_knowledgebases';
            $statusid = 'fredki-knowledgebase-status';
            $knowledgefieldid = 'id_knowledgeid';
            $usernameid = 'id_fredkiusername';
            $passwordid = 'id_fredkipassword';
            $workspaceid = 'id_workspace';
            $endpoint = (new moodle_url('/mod/fredkicourseimport/knowledgebases.php'))->out(false);
            $sesskey = sesskey();
            $choose = addslashes(get_string('chooseknowledgebase', 'mod_fredkicourseimport'));
            $entercredentials = addslashes(get_string('entercredentialstoload', 'mod_fredkicourseimport'));
            $loading = addslashes(get_string('knowledgebasesloading', 'mod_fredkicourseimport'));
            $loadingerror = addslashes(get_string('knowledgebasesloaderror', 'mod_fredkicourseimport'));
            $loadedsuffix = addslashes(get_string('knowledgebasesloaded', 'mod_fredkicourseimport'));

            $mform->addElement('html', html_writer::div(
                html_writer::tag('button', get_string('loadknowledgebases', 'mod_fredkicourseimport'), [
                    'type' => 'button',
                    'class' => 'btn btn-primary w-100',
                    'id' => $buttonid,
                    'style' => 'width:100%;',
                ]) .
                html_writer::div('', 'mt-2', ['id' => $statusid]),
                'mb-3'
            ));

            // Wichtig: Das echte Moodle-Select bleibt im Formular. Es wird nur optisch
            // ausgeblendet und per Karten synchronisiert. Dadurch akzeptiert Moodle den
            // gespeicherten Wert genauso wie in der funktionierenden Ausgangsversion.
            $mform->addElement('select', 'knowledgeid', get_string('availableknowledgebases', 'mod_fredkicourseimport'), $knowledgeoptions);
            $mform->setType('knowledgeid', PARAM_TEXT);
            $mform->addRule('knowledgeid', null, 'required', null, 'client');
            $mform->addElement('hidden', 'knowledgebasename', (string)($current->knowledgebasename ?? ''));
            $mform->setType('knowledgebasename', PARAM_TEXT);

            $selectedlabel = '';
            $selectedid = (string)($current->knowledgeid ?? '');
            if ($selectedid !== '' && !empty($knowledgeoptions[$selectedid])) {
                $selectedlabel = (string)$knowledgeoptions[$selectedid];
            } else if (!empty($current->knowledgebasename)) {
                $selectedlabel = (string)$current->knowledgebasename;
            }
            $none = addslashes(get_string('noknowledgebasechosen', 'mod_fredkicourseimport'));
            $selectedprefix = addslashes(get_string('selectedknowledgebase', 'mod_fredkicourseimport'));
            $choosebutton = addslashes(get_string('selectknowledgebasebutton', 'mod_fredkicourseimport'));
            $selectedbutton = addslashes(get_string('selectedknowledgebasebutton', 'mod_fredkicourseimport'));

            $mform->addElement('html', html_writer::div(
                html_writer::div(
                    $selectedlabel !== ''
                    ? $selectedprefix . ' ' . html_writer::tag('strong', s($selectedlabel))
                    : $none,
                    'fredki-kb-selected-text',
                    ['id' => 'fredki-knowledgebase-selected']
                ) .
                html_writer::div('', 'fredki-kb-grid', ['id' => 'fredki-knowledgebase-cards']),
                'fredki-kb-picker mb-3'
            ));

            $script = <<<JS
<script>
document.addEventListener('DOMContentLoaded', function() {
    const button = document.getElementById('$buttonid');
    if (!button) {
        return;
    }

    const statusNode = document.getElementById('$statusid');
    const selectNode = document.getElementById('$knowledgefieldid');
    const nameNode = document.getElementById('id_knowledgebasename');
    const selectedNode = document.getElementById('fredki-knowledgebase-selected');
    const cardsNode = document.getElementById('fredki-knowledgebase-cards');
    const usernameNode = document.getElementById('$usernameid');
    const passwordNode = document.getElementById('$passwordid');
    const workspaceNode = document.getElementById('$workspaceid');

    const selectRow = selectNode ? selectNode.closest('.fitem, .form-group, .mb-3') : null;
    if (selectRow) {
        selectRow.style.display = 'none';
    }

    const setStatus = function(message, cssclass) {
        if (!statusNode) {
            return;
        }
        statusNode.className = cssclass || '';
        statusNode.textContent = message || '';
    };

    const getInitial = function(name) {
        const trimmed = (name || '').trim();
        return trimmed ? trimmed.charAt(0).toUpperCase() : '?';
    };

    const updateSelectedText = function(name) {
        if (!selectedNode) {
            return;
        }
        if (name) {
            selectedNode.innerHTML = '$selectedprefix ' + '<strong></strong>';
            selectedNode.querySelector('strong').textContent = name;
        } else {
            selectedNode.textContent = '$none';
        }
    };

    const syncNameFromSelect = function() {
        if (!selectNode || !nameNode) {
            return;
        }
        const selected = selectNode.options[selectNode.selectedIndex];
        const name = selected && selected.value ? selected.textContent : '';
        nameNode.value = name;
        updateSelectedText(name);
    };

    const renderCards = function() {
        if (!selectNode || !cardsNode) {
            return;
        }
        cardsNode.innerHTML = '';
        Array.from(selectNode.options).forEach(function(option) {
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
            subtitle.textContent = 'Wissensbasis';

            const action = document.createElement('span');
            action.className = 'fredki-kb-action';
            action.textContent = option.selected ? '$selectedbutton' : '$choosebutton';

            const brand = document.createElement('span');
            brand.className = 'fredki-kb-brand';
            brand.textContent = 'FredKI';

            body.appendChild(title);
            body.appendChild(subtitle);
            body.appendChild(action);
            card.appendChild(avatar);
            card.appendChild(body);
            card.appendChild(brand);
            card.addEventListener('click', function() {
                selectNode.value = option.value;
                syncNameFromSelect();
                renderCards();
            });
            cardsNode.appendChild(card);
        });
    };

    const resetOptions = function() {
        if (!selectNode) {
            return;
        }
        selectNode.innerHTML = '';
        const option = document.createElement('option');
        option.value = '';
        option.textContent = '$choose';
        selectNode.appendChild(option);
        syncNameFromSelect();
        renderCards();
    };

    if (selectNode) {
        selectNode.addEventListener('change', function() {
            syncNameFromSelect();
            renderCards();
        });
    }
    syncNameFromSelect();
    renderCards();

    button.addEventListener('click', async function() {
        const username = usernameNode ? usernameNode.value.trim() : '';
        const password = passwordNode ? passwordNode.value : '';
        const workspace = workspaceNode && workspaceNode.value ? workspaceNode.value : 'default';

        if (!username || !password) {
            setStatus('$entercredentials', 'alert alert-warning');
            return;
        }

        button.disabled = true;
        resetOptions();
        setStatus('$loading', 'alert alert-info');

        try {
            const response = await fetch('$endpoint', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                },
                body: new URLSearchParams({
                    sesskey: '$sesskey',
                    courseid: '$courseid',
                    username: username,
                    password: password,
                    workspace: workspace
                })
            });

            const data = await response.json();
            if (!response.ok || !data.success) {
                throw new Error(data.error || '$loadingerror');
            }

            resetOptions();
            (data.items || []).forEach(function(item) {
                const option = document.createElement('option');
                option.value = String(item.id || '');
                option.textContent = String(item.name || item.id || '');
                if (option.value) {
                    selectNode.appendChild(option);
                }
            });
            renderCards();
            setStatus((data.items || []).length + ' ' + '$loadedsuffix', 'alert alert-success');
        } catch (error) {
            setStatus(error.message || '$loadingerror', 'alert alert-danger');
        } finally {
            button.disabled = false;
        }
    });
});
</script>
JS;
            $mform->addElement('html', $script);
        }

        if (false && $licensevalid) {
            $mform->addElement('header', 'fredkiagentheader', get_string('fredkiagentheader', 'mod_fredkicourseimport'));

            $agentoptions = ['' => get_string('chooseagent', 'mod_fredkicourseimport')];
            $agentusernameforoptions = (string)($current->fredkiusername ?? '');
            $agentpasswordforoptions = '';
            $agentworkspaceforoptions = (string)($current->workspace ?? 'default');

            if (!$locked && $submittedusername !== '' && $submittedpassword !== '') {
                $agentusernameforoptions = $submittedusername;
                $agentpasswordforoptions = $submittedpassword;
                if ($submittedworkspace !== '') {
                    $agentworkspaceforoptions = $submittedworkspace;
                }
            }

            if ($agentusernameforoptions !== '' && $agentpasswordforoptions !== '') {
                $agentoptions += $this->load_agent_options(
                    $config,
                    $agentusernameforoptions,
                    $agentpasswordforoptions,
                    $agentworkspaceforoptions
                );
            }

            if (!empty($current->agentid) && !array_key_exists((string)$current->agentid, $agentoptions)) {
                $agentoptions[(string)$current->agentid] = (string)$current->agentid;
            }

            $agentbuttonid = 'id_fredki_load_agents';
            $agentstatusid = 'fredki-agent-status';
            $agentfieldid = 'id_agentid';
            $agentendpoint = (new moodle_url('/mod/fredkicourseimport/agents.php'))->out(false);
            $agentchoose = addslashes(get_string('chooseagent', 'mod_fredkicourseimport'));
            $agentsloading = addslashes(get_string('agentsloading', 'mod_fredkicourseimport'));
            $agentsloadingerror = addslashes(get_string('agentsloaderror', 'mod_fredkicourseimport'));
            $agentsloadedsuffix = addslashes(get_string('agentsloaded', 'mod_fredkicourseimport'));

            if (!$locked) {
                $mform->addElement('html', html_writer::div(
                    html_writer::tag('button', get_string('loadagents', 'mod_fredkicourseimport'), [
                        'type' => 'button',
                        'class' => 'btn btn-secondary w-100',
                        'id' => $agentbuttonid,
                        'style' => 'width:100%;',
                    ]) .
                    html_writer::div('', 'mt-2', ['id' => $agentstatusid]),
                    'mb-3'
                ));
            }

            $mform->addElement('select', 'agentid', get_string('availableagents', 'mod_fredkicourseimport'), $agentoptions);
            $mform->setType('agentid', PARAM_TEXT);
            $mform->addHelpButton('agentid', 'agentid', 'mod_fredkicourseimport');

            if (!$locked) {
                $agentscript = <<<JS
<script>
document.addEventListener('DOMContentLoaded', function() {
    const button = document.getElementById('$agentbuttonid');
    if (!button) {
        return;
    }

    const statusNode = document.getElementById('$agentstatusid');
    const selectNode = document.getElementById('$agentfieldid');
    const embedNode = document.getElementById('id_fredkiembedcode');
    const chatUrlNode = document.getElementById('id_fredkichaturl');

    const toggleChatUrlFallback = function() {
        if (!chatUrlNode) {
            return;
        }
        const row = chatUrlNode.closest('.fitem, .form-group, .mb-3');
        const infoRow = document.getElementById('fitem_id_fredkichaturlinfo');
        const hasUrl = chatUrlNode.value.trim() !== '';
        if (row) {
            row.style.display = hasUrl ? 'none' : '';
        }
        if (infoRow) {
            infoRow.style.display = hasUrl ? 'none' : '';
        }
    };

    const usernameNode = document.getElementById('$usernameid');
    const passwordNode = document.getElementById('$passwordid');
    const workspaceNode = document.getElementById('$workspaceid');

    const setStatus = function(message, cssclass) {
        if (!statusNode) {
            return;
        }
        statusNode.className = cssclass || '';
        statusNode.textContent = message || '';
    };

    const resetOptions = function() {
        if (!selectNode) {
            return;
        }
        selectNode.innerHTML = '';
        const option = document.createElement('option');
        option.value = '';
        option.textContent = '$agentchoose';
        option.dataset.embedcode = '';
        option.dataset.chaturl = '';
        selectNode.appendChild(option);
    };

    const updateEmbedCode = function() {
        if (!selectNode || !embedNode) {
            return;
        }
        const selected = selectNode.options[selectNode.selectedIndex];
        if (selected && selected.dataset) {
            embedNode.value = selected.dataset.embedcode || '';
            if (chatUrlNode) {
                chatUrlNode.value = selected.dataset.chaturl || '';
            }
        }
        toggleChatUrlFallback();
    };

    if (selectNode) {
        selectNode.addEventListener('change', updateEmbedCode);
    }
    toggleChatUrlFallback();

    button.addEventListener('click', async function() {
        const username = usernameNode ? usernameNode.value.trim() : '';
        const password = passwordNode ? passwordNode.value : '';
        const workspace = workspaceNode && workspaceNode.value ? workspaceNode.value : 'default';

        if (!username || !password) {
            setStatus('$entercredentials', 'alert alert-warning');
            return;
        }

        button.disabled = true;
        resetOptions();
        setStatus('$agentsloading', 'alert alert-info');

        try {
            const response = await fetch('$agentendpoint', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                },
                body: new URLSearchParams({
                    sesskey: '$sesskey',
                    courseid: '$courseid',
                    username: username,
                    password: password,
                    workspace: workspace
                })
            });

            const data = await response.json();
            if (!response.ok || !data.success) {
                throw new Error(data.error || '$agentsloadingerror');
            }

            resetOptions();
            (data.items || []).forEach(function(item) {
                const option = document.createElement('option');
                option.value = item.id;
                option.textContent = item.name;
                option.dataset.embedcode = item.embedcode || '';
                option.dataset.chaturl = item.chaturl || '';
                selectNode.appendChild(option);
            });

            updateEmbedCode();
            setStatus((data.items || []).length + ' ' + '$agentsloadedsuffix', 'alert alert-success');
        } catch (error) {
            setStatus(error.message || '$agentsloadingerror', 'alert alert-danger');
        } finally {
            button.disabled = false;
        }
    });
});
</script>
JS;
                $mform->addElement('html', $agentscript);
            }

            $mform->addElement('hidden', 'fredkichaturl');
            $mform->setType('fredkichaturl', PARAM_TEXT);

            $mform->addElement('hidden', 'fredkiembedcode');
            $mform->setType('fredkiembedcode', PARAM_RAW_TRIMMED);

            $mform->addElement('advcheckbox', 'agentcoursevisible', '', get_string('agentcoursevisible', 'mod_fredkicourseimport'));
            $mform->setType('agentcoursevisible', PARAM_BOOL);
            $mform->addHelpButton('agentcoursevisible', 'agentcoursevisible', 'mod_fredkicourseimport');
            $mform->setDefault('agentcoursevisible', 0);
        }

        // Agentenauswahl ist deaktiviert. Hidden-Felder verhindern undefinierte Werte
        // in der bestehenden Speicherlogik.
        $mform->addElement('hidden', 'agentid', '');
        $mform->setType('agentid', PARAM_TEXT);
        $mform->addElement('hidden', 'fredkichaturl', '');
        $mform->setType('fredkichaturl', PARAM_TEXT);
        $mform->addElement('hidden', 'fredkiembedcode', '');
        $mform->setType('fredkiembedcode', PARAM_RAW_TRIMMED);
        $mform->addElement('hidden', 'agentcoursevisible', 0);
        $mform->setType('agentcoursevisible', PARAM_BOOL);

        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }

    /**
     * Load the available FredKI knowledge bases for the form.
     *
     * @param mixed $config config value.
     * @param string $username username value.
     * @param string $password password value.
     * @param string $workspace workspace value.
     * @return array Result of the operation.
     */
    protected function load_knowledge_base_options($config, string $username, string $password, string $workspace): array {
        $options = [];

        if (empty($config->baseurl)) {
            return $options;
        }

        try {
            $client = new \mod_fredkicourseimport\local\fredki_client(
                $config->baseurl,
                $workspace ?: 'default',
                $username,
                $password,
                $config->loginpath ?? '/admin/api/user/login',
                !empty($config->debuglog)
            );

            foreach ($client->list_knowledge_bases() as $item) {
                $options[$item['id']] = $item['name'];
            }
        } catch (\Throwable $e) {
            // Keep form usable even if API is temporarily unreachable.
        }

        return $options;
    }

    /**
     * Load available FredKI agents for the form.
     *
     * @param mixed $config config value.
     * @param string $username username value.
     * @param string $password password value.
     * @param string $workspace workspace value.
     * @return array Result of the operation.
     */
    protected function load_agent_options($config, string $username, string $password, string $workspace): array {
        $options = [];

        if (empty($config->baseurl)) {
            return $options;
        }

        try {
            $client = new \mod_fredkicourseimport\local\fredki_client(
                $config->baseurl,
                $workspace ?: 'default',
                $username,
                $password,
                $config->loginpath ?? '/admin/api/user/login',
                !empty($config->debuglog)
            );

            foreach ($client->list_agents() as $item) {
                $options[$item['id']] = $item['name'];
            }
        } catch (\Throwable $e) {
            // Keep form usable even if API is temporarily unreachable.
        }

        return $options;
    }

    /**
     * Validate submitted FredKI activity settings.
     *
     * @param mixed $data data value.
     * @param mixed $files files value.
     * @return mixed Result of the operation.
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if (!\mod_fredkicourseimport\local\license_manager::is_valid_license()) {
            $errors['name'] = get_string('invalidlicense', 'mod_fredkicourseimport');
            return $errors;
        }

        $postedknowledgeid = optional_param('knowledgeid', '', PARAM_TEXT);
        if (empty($this->current->knowledgeid) && empty($data['knowledgeid']) && $postedknowledgeid === '') {
            $errors['knowledgeid'] = get_string('knowledgeidrequired', 'mod_fredkicourseimport');
        }

        return $errors;
    }
}
