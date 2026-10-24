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

require_once(__DIR__ . '/classes/local/fredki_client.php');

/**
 * Fredkicourseimport supports.
 *
 * @param mixed $feature The feature constant.
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
 * Fredkicourseimport resolve knowledge base name.
 *
 * @param mixed $data The form data.
 * @return string The result.
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
        debugging('[mod_fredkicourseimport] Knowledge base name lookup failed: ' . get_class($e), DEBUG_DEVELOPER);
    }

    return trim((string)($data->knowledgebasename ?? ''));
}

/**
 * Fredkicourseimport extract token from chat url.
 *
 * @param ?string $url The URL.
 * @return string The result.
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
 * Fredkicourseimport build embed code from chat url.
 *
 * @param ?string $url The URL.
 * @return string The result.
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
    $src = $scheme . '://' . $host . '/chat/api/embed?protocol=' . rawurlencode($scheme) .
        '&host=' . rawurlencode($host) . '&token=' . rawurlencode($token);
    return fredkicourseimport_clean_embed_code($src);
}

/**
 * Fredkicourseimport resolve agent embed code.
 *
 * @param mixed $data The form data.
 * @return string The result.
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
 * Fredkicourseimport preserve locked mapping fields.
 *
 * @param stdClass $data The form data.
 * @return stdClass The result.
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
 * Apply posted knowledge base values when the form did not deliver them.
 *
 * @param stdClass $data The form data.
 * @return stdClass The completed form data.
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
 * Ensure that the FredKI Agent block is present when the activity is configured
 * to show the chatbot on the course page. Auto-created blocks are removed again
 * when no visible FredKI chatbot configuration remains in the course.
 *
 * @param int $courseid Course id.
 * @return void
 */
function fredkicourseimport_sync_agent_block_for_course(int $courseid): void {
    global $DB, $CFG;

    require_once($CFG->libdir . '/blocklib.php');
    require_once($CFG->dirroot . '/course/lib.php');

    // The block plugin is optional. Only create the instance when it is installed.
    if (!core_component::get_plugin_directory('block', 'fredkiagent')) {
        return;
    }

    $course = $DB->get_record('course', ['id' => $courseid], '*', IGNORE_MISSING);
    $context = context_course::instance($courseid, IGNORE_MISSING);
    if (!$course || !$context) {
        return;
    }

    $hasvisibleagent = $DB->record_exists_select(
        'fredkicourseimport',
        'course = :courseid AND agentcoursevisible = 1 AND ' .
            $DB->sql_isnotempty('fredkicourseimport', 'fredkiembedcode', true, true),
        ['courseid' => $courseid]
    );

    $instances = $DB->get_records('block_instances', [
        'blockname' => 'fredkiagent',
        'parentcontextid' => $context->id,
    ]);

    if ($hasvisibleagent) {
        if ($instances) {
            return;
        }

        $showinsubcontexts = false;
        if ($courseid === (int)SITEID) {
            $pagetypepattern = 'site-index';
        } else if (course_get_format($course)->has_view_page()) {
            $pagetypepattern = 'course-view-*';
        } else {
            $pagetypepattern = '*';
            $showinsubcontexts = true;
        }

        // Create the block through the block API instead of writing to block_instances directly.
        $page = new moodle_page();
        $page->set_course($course);
        $page->blocks->add_regions(['side-pre'], false);
        $block = $page->blocks->add_block('fredkiagent', 'side-pre', -10, $showinsubcontexts, $pagetypepattern);
        if ($block) {
            // Remember that this instance was created automatically.
            $block->instance_config_save((object)['autocreated' => 1]);
        }
        return;
    }

    // Remove only blocks that were auto-created by this plugin. Manually added
    // blocks are left untouched.
    foreach ($instances as $instance) {
        $block = block_instance('fredkiagent', $instance);
        if ($block && !empty($block->config->autocreated)) {
            blocks_delete_instance($instance);
        }
    }
}

/**
 * Fredkicourseimport add instance.
 *
 * @param mixed $data The form data.
 * @param mixed $mform The Moodle form.
 */
function fredkicourseimport_add_instance($data, $mform = null) {
    global $DB, $USER;

    $data = fredkicourseimport_apply_form_post_fallbacks($data);

    // Verify credentials before inserting a partially configured activity.
    $fredkiusername = trim((string)($data->fredkiusername ?? ''));
    $fredkipassword = (string)($data->fredkipassword ?? '');
    $rememberlogin = !empty($data->fredkirememberlogin);
    if (!empty($data->fredkiusesaved)) {
        // Reuse the remembered login of this user instead of typed credentials.
        $saved = \mod_fredkicourseimport\local\token_manager::get_saved_login((int)$USER->id);
        if (!$saved) {
            throw new moodle_exception('nosavedlogin', 'mod_fredkicourseimport');
        }
        $fredkiusername = $saved->username;
        $fredkipassword = $saved->password;
        $rememberlogin = true;
    }
    $newtoken = null;
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
        $newtoken = $client->login();
    }

    $data->timecreated = time();
    $data->timemodified = time();
    $data->syncenabled = empty($data->syncenabled) ? 0 : 1;
    $data->knowledgebasename = fredkicourseimport_resolve_knowledge_base_name($data);
    $data->fredkiembedcode = fredkicourseimport_resolve_agent_embed_code($data);
    $data->agentcoursevisible = empty($data->agentcoursevisible) ? 0 : 1;

    if (!empty($data->knowledgeid)) {
        $data->authorizedbyuserid = $USER->id;
    }
    $forgetpassword = isset($data->fredkirememberlogin) && !$rememberlogin;
    unset($data->fredkiusername, $data->fredkipassword, $data->fredkirememberlogin, $data->fredkiusesaved);
    $id = $DB->insert_record('fredkicourseimport', $data);
    if ($newtoken !== null) {
        \mod_fredkicourseimport\local\token_manager::store(
            $id,
            $USER->id,
            $fredkiusername,
            $newtoken,
            $fredkipassword,
            $rememberlogin
        );
    }
    fredkicourseimport_sync_agent_block_for_course((int)$data->course);
    return $id;
}

/**
 * Fredkicourseimport update instance.
 *
 * @param mixed $data The form data.
 * @param mixed $mform The Moodle form.
 */
function fredkicourseimport_update_instance($data, $mform = null) {
    global $DB, $USER;

    $data = fredkicourseimport_apply_form_post_fallbacks($data);

    $data = fredkicourseimport_preserve_locked_mapping_fields($data);
    $data->id = $data->instance;

    $existing = $DB->get_record('fredkicourseimport', ['id' => $data->id], '*', MUST_EXIST);

    // Agent controls are deliberately disabled in this build. Hidden defaults
    // must not erase a previously configured chatbot when the activity is edited.
    if (!empty($existing->knowledgeid)) {
        foreach (['agentid', 'fredkichaturl', 'fredkiembedcode', 'agentcoursevisible'] as $field) {
            $data->$field = $existing->$field;
        }
    }

    // Re-authorise without losing the existing knowledge base, imports or mapping.
    $fredkiusername = trim((string)($data->fredkiusername ?? ''));
    $fredkipassword = (string)($data->fredkipassword ?? '');
    $rememberlogin = !empty($data->fredkirememberlogin);
    $newtoken = null;
    if ($fredkiusername !== '' && $fredkipassword !== '') {
        $config = get_config('mod_fredkicourseimport');
        $client = new \mod_fredkicourseimport\local\fredki_client(
            $config->baseurl ?? '',
            $existing->workspace ?: 'default',
            $fredkiusername,
            $fredkipassword,
            $config->loginpath ?? '/admin/api/user/login',
            !empty($config->debuglog)
        );
        $newtoken = $client->login();
    }

    // When the selected FredKI agent changes, the hidden embed/chat-url fields can
    // still contain the previous agent from the stored form defaults. Clear them
    // before resolving the new embed code so the course block is updated as well.
    if ((string)($existing->agentid ?? '') !== (string)($data->agentid ?? '')) {
        $data->fredkichaturl = '';
        $data->fredkiembedcode = '';
    }

    $data->timemodified = time();
    $data->syncenabled = empty($data->syncenabled) ? 0 : 1;
    $data->knowledgebasename = fredkicourseimport_resolve_knowledge_base_name($data);
    $data->fredkiembedcode = fredkicourseimport_resolve_agent_embed_code($data);
    $data->agentcoursevisible = empty($data->agentcoursevisible) ? 0 : 1;

    if ($newtoken !== null) {
        $data->authorizedbyuserid = $USER->id;
    } else {
        $data->authorizedbyuserid = $existing->authorizedbyuserid ?? null;
    }
    $forgetpassword = isset($data->fredkirememberlogin) && !$rememberlogin;
    unset($data->fredkiusername, $data->fredkipassword, $data->fredkirememberlogin, $data->fredkiusesaved);

    $result = $DB->update_record('fredkicourseimport', $data);
    if ($newtoken === null && $forgetpassword && !empty($existing->authorizedbyuserid)) {
        \mod_fredkicourseimport\local\token_manager::forget_password((int)$data->id, (int)$existing->authorizedbyuserid);
    }
    if ($newtoken !== null) {
        \mod_fredkicourseimport\local\token_manager::store(
            $data->id,
            $USER->id,
            $fredkiusername,
            $newtoken,
            $fredkipassword,
            $rememberlogin
        );
        if (!empty($existing->authorizedbyuserid) && (int)$existing->authorizedbyuserid !== (int)$USER->id) {
            // Only the newly authorised user should retain credentials.
            $DB->delete_records('fredkicourseimport_auth', [
                'activityid' => $data->id,
                'userid' => (int)$existing->authorizedbyuserid,
            ]);
        }
    }
    fredkicourseimport_sync_agent_block_for_course((int)$data->course);
    return $result;
}

/**
 * Fredkicourseimport delete instance.
 *
 * @param mixed $id The record id.
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
 * Fredkicourseimport clean embed code.
 *
 * @param ?string $embedcode The embed code.
 * @return string The result.
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
 * Fredkicourseimport extract embed src.
 *
 * @param ?string $embedcode The embed code.
 * @return string The result.
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
 * Fredkicourseimport cm info view.
 *
 * @param cm_info $cm The course module.
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

    $activity = $DB->get_record(
        'fredkicourseimport',
        ['id' => $cm->instance],
        'id, fredkiembedcode, agentcoursevisible',
        IGNORE_MISSING
    );
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
 * Fredkicourseimport pluginfile.
 *
 * @param mixed $course The course record.
 * @param mixed $cm The course module.
 * @param mixed $context The context.
 * @param mixed $filearea The file area.
 * @param mixed $args The file arguments.
 * @param mixed $forcedownload Whether to force a download.
 * @param array $options Additional options.
 */
function fredkicourseimport_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    if ($context->contextlevel != CONTEXT_MODULE) {
        return false;
    }
    require_login($course, true, $cm);
    // Generated import files are an administrative export of course content.
    // Only users who can manage FredKI imports may download them.
    require_capability('mod/fredkicourseimport:sync', $context);

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
