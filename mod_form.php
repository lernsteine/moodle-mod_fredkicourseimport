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
 * FredKI course import plugin file.
 *
 * @package    mod_fredkicourseimport
 * @copyright  2026 lernsteine.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');
require_once($CFG->dirroot . '/mod/fredkicourseimport/classes/local/fredki_client.php');

/**
 * Mod fredkicourseimport mod form class.
 *
 * @package    mod_fredkicourseimport
 * @copyright  2026 lernsteine.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_fredkicourseimport_mod_form extends moodleform_mod {
    /**
     * Definition.
     */
    public function definition() {
        $mform = $this->_form;
        $config = get_config('mod_fredkicourseimport');
        $current = $this->current;
        $courseid = (int)$this->get_course()->id;

        $locked = !empty($current->knowledgeid);
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

        if ($locked) {
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
            $mform->addElement(
                'static',
                'fredkireconnecthelp',
                '',
                html_writer::div(get_string('reconnecthelp', 'mod_fredkicourseimport'), 'alert alert-info')
            );
            $mform->addElement(
                'text',
                'fredkiusername',
                get_string('fredkiusername', 'mod_fredkicourseimport'),
                [
                    'size' => 40,
                    'autocomplete' => 'off',
                ]
            );
            $mform->setType('fredkiusername', PARAM_TEXT);
            // Legacy expired tokens are still in the auth table; only prefill username.
            $auth = null;
            if (!empty($current->id ?? $current->instance ?? 0) && !empty($current->authorizedbyuserid)) {
                global $DB;
                $auth = $DB->get_record('fredkicourseimport_auth', [
                    'activityid' => (int)($current->id ?? $current->instance),
                    'userid' => (int)$current->authorizedbyuserid,
                ]);
            }
            $remembered = 0;
            if ($auth) {
                $mform->setDefault('fredkiusername', $auth->fredkiusername);
                $remembered = empty($auth->passwordcipher) ? 0 : 1;
            }
            $mform->addElement(
                'passwordunmask',
                'fredkipassword',
                get_string('fredkipassword', 'mod_fredkicourseimport'),
                [
                    'size' => 40,
                    'autocomplete' => 'new-password',
                ]
            );
            $mform->setType('fredkipassword', PARAM_RAW_TRIMMED);
            $mform->addElement(
                'advcheckbox',
                'fredkirememberlogin',
                get_string('fredkirememberlogin', 'mod_fredkicourseimport'),
                get_string('fredkirememberlogin_desc', 'mod_fredkicourseimport')
            );
            $mform->addHelpButton('fredkirememberlogin', 'fredkirememberlogin', 'mod_fredkicourseimport');
            $mform->setDefault('fredkirememberlogin', $remembered);
        } else {
            global $USER;
            $savedlogin = \mod_fredkicourseimport\local\token_manager::get_saved_login((int)$USER->id);
            if ($savedlogin) {
                $mform->addElement(
                    'advcheckbox',
                    'fredkiusesaved',
                    get_string('fredkiusesaved', 'mod_fredkicourseimport'),
                    get_string('fredkiusesaved_desc', 'mod_fredkicourseimport', s($savedlogin->username))
                );
                $mform->addHelpButton('fredkiusesaved', 'fredkiusesaved', 'mod_fredkicourseimport');
                $mform->setDefault('fredkiusesaved', 1);
            }
            $mform->addElement('text', 'fredkiusername', get_string('fredkiusername', 'mod_fredkicourseimport'), [
                'size' => 40,
                'autocomplete' => 'off',
            ]);
            $mform->setType('fredkiusername', PARAM_TEXT);
            if (!$savedlogin) {
                $mform->addRule('fredkiusername', null, 'required', null, 'client');
            }

            $mform->addElement('passwordunmask', 'fredkipassword', get_string('fredkipassword', 'mod_fredkicourseimport'), [
                'size' => 40,
                'autocomplete' => 'new-password',
            ]);
            $mform->setType('fredkipassword', PARAM_RAW_TRIMMED);
            if (!$savedlogin) {
                $mform->addRule('fredkipassword', null, 'required', null, 'client');
            }
            $mform->addElement(
                'advcheckbox',
                'fredkirememberlogin',
                get_string('fredkirememberlogin', 'mod_fredkicourseimport'),
                get_string('fredkirememberlogin_desc', 'mod_fredkicourseimport')
            );
            $mform->addHelpButton('fredkirememberlogin', 'fredkirememberlogin', 'mod_fredkicourseimport');
            $mform->setDefault('fredkirememberlogin', 1);
            if ($savedlogin) {
                foreach (['fredkiusername', 'fredkipassword', 'fredkirememberlogin'] as $hidden) {
                    $mform->hideIf($hidden, 'fredkiusesaved', 'checked');
                }
            }

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

            if (!$locked && optional_param('fredkiusesaved', 0, PARAM_BOOL)) {
                global $USER;
                $savedforoptions = \mod_fredkicourseimport\local\token_manager::get_saved_login((int)$USER->id);
                if ($savedforoptions) {
                    $usernameforoptions = $savedforoptions->username;
                    $passwordforoptions = $savedforoptions->password;
                }
            }

            if ($usernameforoptions !== '' && $passwordforoptions !== '') {
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
            $choose = get_string('chooseknowledgebase', 'mod_fredkicourseimport');
            $entercredentials = get_string('entercredentialstoload', 'mod_fredkicourseimport');
            $loading = get_string('knowledgebasesloading', 'mod_fredkicourseimport');
            $loadingerror = get_string('knowledgebasesloaderror', 'mod_fredkicourseimport');
            $loadedsuffix = get_string('knowledgebasesloaded', 'mod_fredkicourseimport');

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
            $mform->addElement(
                'select',
                'knowledgeid',
                get_string('availableknowledgebases', 'mod_fredkicourseimport'),
                $knowledgeoptions
            );
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
            $none = get_string('noknowledgebasechosen', 'mod_fredkicourseimport');
            $selectedprefix = get_string('selectedknowledgebase', 'mod_fredkicourseimport');
            $choosebutton = get_string('selectknowledgebasebutton', 'mod_fredkicourseimport');
            $selectedbutton = get_string('selectedknowledgebasebutton', 'mod_fredkicourseimport');

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

            global $PAGE;
            $PAGE->requires->js_call_amd('mod_fredkicourseimport/knowledgebases', 'init', [[
                'buttonId' => $buttonid,
                'statusId' => $statusid,
                'knowledgeFieldId' => $knowledgefieldid,
                'usernameId' => $usernameid,
                'passwordId' => $passwordid,
                'workspaceId' => $workspaceid,
                'endpoint' => $endpoint,
                'sesskey' => $sesskey,
                'courseId' => $courseid,
                'strings' => [
                    'choose' => $choose,
                    'none' => $none,
                    'selectedPrefix' => $selectedprefix,
                    'selectedButton' => $selectedbutton,
                    'chooseButton' => $choosebutton,
                    'subtitle' => get_string('knowledgebase', 'mod_fredkicourseimport'),
                    'enterCredentials' => $entercredentials,
                    'loading' => $loading,
                    'loadError' => $loadingerror,
                    'loadedSuffix' => $loadedsuffix,
                ],
            ]]);
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
     * Load knowledge base options.
     *
     * @param mixed $config The config.
     * @param string $username The FredKI username.
     * @param string $password The FredKI password.
     * @param string $workspace The FredKI workspace.
     * @return array The result.
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
            debugging('[mod_fredkicourseimport] FredKI request failed: ' . get_class($e), DEBUG_DEVELOPER);
        }

        return $options;
    }

    /**
     * Load agent options.
     *
     * @param mixed $config The config.
     * @param string $username The FredKI username.
     * @param string $password The FredKI password.
     * @param string $workspace The FredKI workspace.
     * @return array The result.
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
            debugging('[mod_fredkicourseimport] FredKI request failed: ' . get_class($e), DEBUG_DEVELOPER);
        }

        return $options;
    }

    /**
     * Validation.
     *
     * @param mixed $data The form data.
     * @param mixed $files The submitted files.
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        $reconnectusername = trim((string)($data['fredkiusername'] ?? ''));
        $reconnectpassword = (string)($data['fredkipassword'] ?? '');
        if ($reconnectpassword !== '' && $reconnectusername === '') {
            $errors['fredkiusername'] =
                get_string('reconnectbothrequired', 'mod_fredkicourseimport');
        }

        if (empty($this->current->knowledgeid) && !empty($data['fredkiusesaved'])) {
            global $USER;
            if (!\mod_fredkicourseimport\local\token_manager::get_saved_login((int)$USER->id)) {
                $errors['fredkiusesaved'] = get_string('nosavedlogin', 'mod_fredkicourseimport');
            }
        } else if (empty($this->current->knowledgeid) && ($reconnectusername === '' || $reconnectpassword === '')) {
            $errors['fredkiusername'] = get_string('reconnectbothrequired', 'mod_fredkicourseimport');
        }

        $postedknowledgeid = optional_param('knowledgeid', '', PARAM_TEXT);
        if (empty($this->current->knowledgeid) && empty($data['knowledgeid']) && $postedknowledgeid === '') {
            $errors['knowledgeid'] = get_string('knowledgeidrequired', 'mod_fredkicourseimport');
        }

        return $errors;
    }
}
