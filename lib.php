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

require_once(__DIR__ . '/classes/local/fredki_client.php');
require_once(__DIR__ . '/classes/local/license_manager.php');

/**
 * Report support for a Moodle activity feature.
 *
 * @param mixed $feature feature value.
 * @return mixed Result of the operation.
 */
function fredkicourseimport_supports($feature) {
    switch ($feature) {
        case FEATURE_MOD_PURPOSE:
            return MOD_PURPOSE_CONTENT;
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        default:
            return null;
    }
}

/**
 * Require a valid FredKI service licence.
 */
function fredkicourseimport_require_license(): void {
    \mod_fredkicourseimport\local\license_manager::require_valid_license();
}

/**
 * Resolve a FredKI knowledge base display name.
 *
 * @param mixed $data data value.
 * @return string Result of the operation.
 */
function fredkicourseimport_resolve_knowledge_base_name($data): string {
    $knowledgeid = trim((string)($data->knowledgeid ?? ''));
    if ($knowledgeid === '') {
        return '';
    }

    $username = trim((string)($data->fredkiusername ?? ''));
    $password = (string)($data->fredkipassword ?? '');
    $workspace = trim((string)($data->workspace ?? 'default')) ?: 'default';
    $config = get_config('mod_fredkicourseimport');

    if ($username === '' || $password === '' || empty($config->baseurl)) {
        return trim((string)($data->knowledgebasename ?? ''));
    }

    try {
        $client = new \mod_fredkicourseimport\local\fredki_client(
            $config->baseurl,
            $workspace,
            $username,
            $password,
            $config->loginpath ?? '/admin/api/user/login',
            !empty($config->debuglog)
        );

        foreach ($client->list_knowledge_bases() as $item) {
            if ((string)($item['id'] ?? '') === $knowledgeid) {
                return (string)($item['name'] ?? '');
            }
        }
    } catch (\Throwable $e) {
        // Saving should not fail if the name cannot be looked up again right now.
    }

    return trim((string)($data->knowledgebasename ?? ''));
}

/**
 * Extract the agent token from a FredKI chat URL.
 *
 * @param ?string $url url value.
 * @return string Result of the operation.
 */
function fredkicourseimport_extract_token_from_chat_url(?string $url): string {
    $url = html_entity_decode(trim((string)$url), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    if ($url === '') {
        return '';
    }
    $parts = parse_url($url);
    if (!empty($parts['query'])) {
        parse_str($parts['query'], $query);
        if (!empty($query['token']) && is_scalar($query['token'])) {
            return trim((string)$query['token']);
        }
    }
    if (!empty($parts['path']) && preg_match('~/(?:chat|application)/([^/?#]+)~', $parts['path'], $matches)) {
        return trim($matches[1]);
    }
    return '';
}

/**
 * Build the FredKI chatbot embed code from its public URL.
 *
 * @param ?string $url url value.
 * @return string Result of the operation.
 */
function fredkicourseimport_build_embed_code_from_chat_url(?string $url): string {
    $url = html_entity_decode(trim((string)$url), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $parts = parse_url($url);
    if (empty($parts['scheme']) || empty($parts['host'])) {
        return '';
    }
    $scheme = strtolower($parts['scheme']) === 'http' ? 'http' : 'https';
    $host = strtolower($parts['host']);
    $token = fredkicourseimport_extract_token_from_chat_url($url);
    if ($token === '') {
        return '';
    }
    $src = $scheme . '://' . $host . '/chat/api/embed?protocol=' . rawurlencode($scheme) . '&host=' . rawurlencode($host) . '&token=' . rawurlencode($token);
    return fredkicourseimport_clean_embed_code($src);
}

/**
 * Resolve the embed code for a selected FredKI agent.
 *
 * @param mixed $data data value.
 * @return string Result of the operation.
 */
function fredkicourseimport_resolve_agent_embed_code($data): string {
    $fromchaturl = fredkicourseimport_build_embed_code_from_chat_url($data->fredkichaturl ?? '');
    if ($fromchaturl !== '') {
        return $fromchaturl;
    }

    $current = fredkicourseimport_clean_embed_code($data->fredkiembedcode ?? '');
    if ($current !== '') {
        return $current;
    }

    $agentid = trim((string)($data->agentid ?? ''));
    $username = trim((string)($data->fredkiusername ?? ''));
    $password = (string)($data->fredkipassword ?? '');
    $workspace = trim((string)($data->workspace ?? 'default')) ?: 'default';
    $config = get_config('mod_fredkicourseimport');

    if ($agentid === '' || $username === '' || $password === '' || empty($config->baseurl)) {
        return '';
    }

    try {
        $client = new \mod_fredkicourseimport\local\fredki_client(
            $config->baseurl,
            $workspace,
            $username,
            $password,
            $config->loginpath ?? '/admin/api/user/login',
            !empty($config->debuglog)
        );
        return fredkicourseimport_clean_embed_code($client->get_agent_embed_code($agentid));
    } catch (\Throwable $e) {
        return '';
    }
}

/**
 * Preserve existing immutable FredKI activity mappings.
 *
 * @param stdClass $data data value.
 * @return stdClass Result of the operation.
 */
function fredkicourseimport_preserve_locked_mapping_fields(stdClass $data): stdClass {
    global $DB;

    if (empty($data->instance)) {
        return $data;
    }

    $existing = $DB->get_record('fredkicourseimport', ['id' => $data->instance], '*', IGNORE_MISSING);
    if (!$existing || empty($existing->knowledgeid)) {
        return $data;
    }

    $data->knowledgeid = $existing->knowledgeid;
    $data->knowledgebasename = $existing->knowledgebasename;
    $data->workspace = $existing->workspace;
    $data->authorizedbyuserid = $existing->authorizedbyuserid;

    return $data;
}


/**
 * Ensures that the FredKI Agent block is present when the activity is configured
 * to show the chatbot on the course page. Auto-created blocks are removed again
 * when no visible FredKI chatbot configuration remains in the course.
 *
 * @param int $courseid Course id.
 * @return void
 */

function fredkicourseimport_apply_form_post_fallbacks(stdClass $data): stdClass {
    $postedknowledgeid = optional_param('knowledgeid', '', PARAM_TEXT);
    $postedknowledgename = optional_param('knowledgebasename', '', PARAM_TEXT);
    if (trim((string)($data->knowledgeid ?? '')) === '' && $postedknowledgeid !== '') {
        $data->knowledgeid = $postedknowledgeid;
    }
    if (trim((string)($data->knowledgebasename ?? '')) === '' && $postedknowledgename !== '') {
        $data->knowledgebasename = $postedknowledgename;
    }
    return $data;
}

/**
 * Synchronise the FredKI agent block on the Moodle course.
 *
 * @param int $courseid courseid value.
 */
function fredkicourseimport_sync_agent_block_for_course(int $courseid): void {
    global $DB;

    if (!$DB->get_manager()->table_exists('block_instances')) {
        return;
    }

    // The block plugin is optional. Only create the instance when it is installed.
    if (!$DB->record_exists('block', ['name' => 'fredkiagent'])) {
        return;
    }

    $context = context_course::instance($courseid, IGNORE_MISSING);
    if (!$context) {
        return;
    }

    $hasvisibleagent = $DB->record_exists_select(
        'fredkicourseimport',
        'course = :courseid AND agentcoursevisible = 1 AND fredkiembedcode IS NOT NULL AND fredkiembedcode <> :empty',
        ['courseid' => $courseid, 'empty' => '']
    );

    $exists = $DB->record_exists('block_instances', [
        'blockname' => 'fredkiagent',
        'parentcontextid' => $context->id,
    ]);

    if ($hasvisibleagent) {
        if ($exists) {
            return;
        }

        $now = time();
        $config = (object)['autocreated' => 1];
        $pagetypepattern = ($courseid === (int)SITEID) ? 'site-index' : 'course-view-*';

        $record = (object)[
            'blockname' => 'fredkiagent',
            'parentcontextid' => $context->id,
            'showinsubcontexts' => 0,
            'pagetypepattern' => $pagetypepattern,
            'subpagepattern' => null,
            'defaultregion' => 'side-pre',
            'defaultweight' => -10,
            'configdata' => base64_encode(serialize($config)),
            'timecreated' => $now,
            'timemodified' => $now,
        ];

        $DB->insert_record('block_instances', $record);
        return;
    }

    // Remove only blocks that were auto-created by this plugin. Manually added
    // blocks are left untouched.
    $instances = $DB->get_records('block_instances', [
        'blockname' => 'fredkiagent',
        'parentcontextid' => $context->id,
    ]);

    foreach ($instances as $instance) {
        $config = @unserialize(base64_decode((string)$instance->configdata));
        if (is_object($config) && !empty($config->autocreated)) {
            blocks_delete_instance($instance);
        }
    }
}

/**
 * Create a FredKI Course Import activity instance.
 *
 * @param mixed $data data value.
 * @param mixed $mform mform value.
 * @return mixed Result of the operation.
 */
function fredkicourseimport_add_instance($data, $mform = null) {
    global $DB, $USER;

    fredkicourseimport_require_license();
    $data = fredkicourseimport_apply_form_post_fallbacks($data);

    $data->timecreated = time();
    $data->timemodified = time();
    $data->syncenabled = empty($data->syncenabled) ? 0 : 1;
    $fredkiusername = trim((string)($data->fredkiusername ?? ''));
    $fredkipassword = (string)($data->fredkipassword ?? '');
    $data->knowledgebasename = fredkicourseimport_resolve_knowledge_base_name($data);
    $data->fredkiembedcode = fredkicourseimport_resolve_agent_embed_code($data);
    $data->agentcoursevisible = empty($data->agentcoursevisible) ? 0 : 1;

    if (!empty($data->knowledgeid)) {
        $data->authorizedbyuserid = $USER->id;
    }
    // Remember the choice only for the token store; never insert it into the activity table.
    $autorenew = !empty($data->fredkiautorenew);
    unset($data->fredkiusername, $data->fredkipassword, $data->fredkiautorenew);
    $id = $DB->insert_record('fredkicourseimport', $data);
    if ($fredkiusername !== '' && $fredkipassword !== '') {
        $config = get_config('mod_fredkicourseimport');
        $client = new \mod_fredkicourseimport\local\fredki_client(
            $config->baseurl ?? '',
            $data->workspace ?? 'default',
            $fredkiusername,
            $fredkipassword,
            $config->loginpath ?? '/admin/api/user/login',
            !empty($config->debuglog)
        );
        \mod_fredkicourseimport\local\token_manager::store($id, $USER->id, $fredkiusername, $client->login(), $fredkipassword, $autorenew);
    }
    fredkicourseimport_sync_agent_block_for_course((int)$data->course);
    return $id;
}

/**
 * Update a FredKI Course Import activity instance.
 *
 * @param mixed $data data value.
 * @param mixed $mform mform value.
 * @return mixed Result of the operation.
 */
function fredkicourseimport_update_instance($data, $mform = null) {
    global $DB, $USER;

    fredkicourseimport_require_license();
    $data = fredkicourseimport_apply_form_post_fallbacks($data);

    $data = fredkicourseimport_preserve_locked_mapping_fields($data);
    $data->id = $data->instance;

    $existing = $DB->get_record('fredkicourseimport', ['id' => $data->id], '*', MUST_EXIST);

    // When the selected FredKI agent changes, the hidden embed/chat-url fields can
    // still contain the previous agent from the stored form defaults. Clear them
    // before resolving the new embed code so the course block is updated as well.
    if ((string)($existing->agentid ?? '') !== (string)($data->agentid ?? '')) {
        $data->fredkichaturl = '';
        $data->fredkiembedcode = '';
    }

    $data->timemodified = time();
    $data->syncenabled = empty($data->syncenabled) ? 0 : 1;
    $fredkiusername = trim((string)($data->fredkiusername ?? ''));
    $fredkipassword = (string)($data->fredkipassword ?? '');
    $data->knowledgebasename = fredkicourseimport_resolve_knowledge_base_name($data);
    $data->fredkiembedcode = fredkicourseimport_resolve_agent_embed_code($data);
    $data->agentcoursevisible = empty($data->agentcoursevisible) ? 0 : 1;

    if (empty($existing->authorizedbyuserid) && !empty($data->knowledgeid)) {
        $data->authorizedbyuserid = $USER->id;
    } else {
        $data->authorizedbyuserid = $existing->authorizedbyuserid ?? null;
    }
    // Remember the choice only for the token store; never insert it into the activity table.
    $autorenew = !empty($data->fredkiautorenew);
    unset($data->fredkiusername, $data->fredkipassword, $data->fredkiautorenew);

    $result = $DB->update_record('fredkicourseimport', $data);
    if ($fredkiusername !== '' && $fredkipassword !== '') {
        $config = get_config('mod_fredkicourseimport');
        $client = new \mod_fredkicourseimport\local\fredki_client(
            $config->baseurl ?? '',
            $data->workspace ?? 'default',
            $fredkiusername,
            $fredkipassword,
            $config->loginpath ?? '/admin/api/user/login',
            !empty($config->debuglog)
        );
        \mod_fredkicourseimport\local\token_manager::store($data->id, $USER->id, $fredkiusername, $client->login(), $fredkipassword, $autorenew);
    }
    fredkicourseimport_sync_agent_block_for_course((int)$data->course);
    return $result;
}

/**
 * Delete a FredKI Course Import activity instance.
 *
 * @param mixed $id id value.
 * @return mixed Result of the operation.
 */
function fredkicourseimport_delete_instance($id) {
    global $DB;

    if (!$activity = $DB->get_record('fredkicourseimport', ['id' => $id])) {
        return false;
    }

    $cm = get_coursemodule_from_instance('fredkicourseimport', $id, 0, false, IGNORE_MISSING);
    if ($cm) {
        $context = context_module::instance($cm->id);
        $fs = get_file_storage();
        $fs->delete_area_files($context->id, 'mod_fredkicourseimport', 'generated_files', 0);
    }

    // Preserve remote IDs before deleting the mappings and user-scoped token.
    // A Moodle activity deletion does not establish that FredKI deleted its documents.
    // This register stores no password, bearer token, or account credentials.
    $remotemaps = $DB->get_records_select(
        'fredkicourseimport_map',
        'activityid = :activityid AND fredki_document_id IS NOT NULL AND fredki_document_id <> :emptyid',
        ['activityid' => $activity->id, 'emptyid' => '']
    );
    foreach ($remotemaps as $mapping) {
        $DB->insert_record('fredkicourseimport_cleanup', (object)[
            'courseid' => (int)$activity->course,
            'activityid' => (int)$activity->id,
            'knowledgeid' => (string)$activity->knowledgeid,
            'workspace' => (string)$activity->workspace,
            'documentid' => (string)$mapping->fredki_document_id,
            'timecreated' => time(),
        ]);
    }

    \mod_fredkicourseimport\local\token_manager::delete_activity((int)$activity->id);
    $DB->delete_records('fredkicourseimport_map', ['activityid' => $activity->id]);
    $DB->delete_records('fredkicourseimport_selection', ['activityid' => $activity->id]);
    $DB->delete_records('fredkicourseimport', ['id' => $activity->id]);
    fredkicourseimport_sync_agent_block_for_course((int)$activity->course);
    return true;
}

/**
 * Sanitise the configured FredKI embed code.
 *
 * @param ?string $embedcode embedcode value.
 * @return string Result of the operation.
 */
function fredkicourseimport_clean_embed_code(?string $embedcode): string {
    $embedcode = trim((string)$embedcode);
    if ($embedcode === '') {
        return '';
    }

    $url = $embedcode;
    if (preg_match('/src\s*=\s*["\']([^"\']+)["\']/i', $embedcode, $matches)) {
        $url = html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    $parts = parse_url($url);
    if (empty($parts['scheme']) || empty($parts['host']) || empty($parts['path'])) {
        return '';
    }

    $scheme = strtolower($parts['scheme']);
    $host = strtolower($parts['host']);
    $path = $parts['path'];
    $allowedhosts = ['fredki.lernsteine.com'];

    $config = get_config('mod_fredkicourseimport');
    if (!empty($config->baseurl)) {
        $baseparts = parse_url($config->baseurl);
        if (!empty($baseparts['host'])) {
            $allowedhosts[] = strtolower($baseparts['host']);
        }
    }

    if ($scheme !== 'https' || !in_array($host, array_unique($allowedhosts), true) || $path !== '/chat/api/embed') {
        return '';
    }

    $query = !empty($parts['query']) ? '?' . $parts['query'] : '';
    $src = $scheme . '://' . $host . $path . $query;

    return html_writer::tag('script', '', [
        'async' => 'async',
        'defer' => 'defer',
        'src' => $src,
    ]);
}

/**
 * Extract the permitted script source from the embed code.
 *
 * @param ?string $embedcode embedcode value.
 * @return string Result of the operation.
 */
function fredkicourseimport_extract_embed_src(?string $embedcode): string {
    $clean = fredkicourseimport_clean_embed_code($embedcode);
    if ($clean === '') {
        return '';
    }
    if (preg_match('/src\s*=\s*["\']([^"\']+)["\']/i', $clean, $matches)) {
        return html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    return '';
}

/**
 * Update course-module information for the activity.
 *
 * @param cm_info $cm cm value.
 */
function fredkicourseimport_cm_info_view(cm_info $cm): void {
    global $DB;

    // Be defensive: this callback can run on the course page before an administrator
    // has completed the database upgrade. Avoid breaking the whole course page if
    // the new visibility field is not present yet.
    $dbman = $DB->get_manager();
    $table = new xmldb_table('fredkicourseimport');
    $visiblefield = new xmldb_field('agentcoursevisible');
    $embedfield = new xmldb_field('fredkiembedcode');

    if (!$dbman->field_exists($table, $visiblefield) || !$dbman->field_exists($table, $embedfield)) {
        return;
    }

    $activity = $DB->get_record('fredkicourseimport', ['id' => $cm->instance], 'id, fredkiembedcode, agentcoursevisible', IGNORE_MISSING);
    if (!$activity || empty($activity->agentcoursevisible)) {
        return;
    }

    $embed = fredkicourseimport_clean_embed_code($activity->fredkiembedcode ?? '');
    if ($embed !== '') {
        global $PAGE;
        $src = fredkicourseimport_extract_embed_src($activity->fredkiembedcode ?? '');
        if ($src !== '') {
            $PAGE->requires->js(new moodle_url($src), true);
        }
        $cm->set_content(html_writer::div($embed, 'fredki-course-embed'));
    }
}

/**
 * Serve an authorised file belonging to the activity.
 *
 * @param mixed $course course value.
 * @param mixed $cm cm value.
 * @param mixed $context context value.
 * @param mixed $filearea filearea value.
 * @param mixed $args args value.
 * @param mixed $forcedownload forcedownload value.
 * @param array $options options value.
 * @return mixed Result of the operation.
 */
function fredkicourseimport_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    if ($context->contextlevel != CONTEXT_MODULE) {
        return false;
    }
    require_login($course, true, $cm);
    // Generated import files are an administrative export of course content.
    // Only users who can manage FredKI imports may download them.
    require_capability('mod/fredkicourseimport:sync', $context);
    fredkicourseimport_require_license();

    if ($filearea !== 'generated_files') {
        return false;
    }

    $itemid = 0;
    $filename = array_pop($args);
    if (!$filename) {
        return false;
    }

    $filepath = '/';
    if (!empty($args)) {
        $filepath = '/' . trim(implode('/', $args), '/') . '/';
    }

    $fs = get_file_storage();
    $file = $fs->get_file($context->id, 'mod_fredkicourseimport', $filearea, $itemid, $filepath, $filename);
    if (!$file || $file->is_directory()) {
        return false;
    }

    send_stored_file($file, 0, 0, $forcedownload, $options);
}
