<?php
// Copyright (c) 2026 lernsteine.com
// SPDX-License-Identifier: GPL-3.0-or-later
/**
 * Reauthorise a configured FredKI knowledge base without leaving activity settings.
 * The endpoint never creates, uploads, synchronises or deletes FredKI documents.
 *
 * @package mod_fredkicourseimport
 */
define('AJAX_SCRIPT', true);
require_once(__DIR__ . '/../../config.php');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

$respond = static function(bool $success, string $message, int $status = 200): void {
    http_response_code($status);
    echo json_encode(['success' => $success, 'message' => $message],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
};

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    $respond(false, get_string('reauthajaxfailed', 'mod_fredkicourseimport'), 405);
}

try {
    $cmid = required_param('id', PARAM_INT);
    $cm = get_coursemodule_from_id('fredkicourseimport', $cmid, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
    $activity = $DB->get_record('fredkicourseimport', ['id' => $cm->instance], '*', MUST_EXIST);
    require_login($course, true, $cm);
    require_sesskey();
    $context = context_module::instance($cm->id);
    require_capability('mod/fredkicourseimport:view', $context);
    require_capability('mod/fredkicourseimport:sync', $context);
    // The inline credentials area belongs to the edit-activity form.
    require_capability('moodle/course:manageactivities', context_course::instance($course->id));

    $username = trim(required_param('fredkiusername', PARAM_TEXT));
    $password = required_param('fredkipassword', PARAM_RAW);
    $remember = optional_param('fredkiautorenew', 0, PARAM_BOOL);
    if ($username === '' || $password === '') {
        $respond(false, get_string('reauthmissingcredentials', 'mod_fredkicourseimport'), 400);
    }
    if (empty($activity->knowledgeid)) {
        $respond(false, get_string('configureactivityfirst', 'mod_fredkicourseimport'), 400);
    }

    $config = get_config('mod_fredkicourseimport');
    $client = new \mod_fredkicourseimport\local\fredki_client(
        (string)($config->baseurl ?? ''), (string)($activity->workspace ?? 'default'),
        $username, $password, (string)($config->loginpath ?? '/admin/api/user/login'),
        !empty($config->debuglog)
    );
    $found = false;
    // list_knowledge_bases logs in before querying. Verify the exact mapped ID.
    foreach ($client->list_knowledge_bases() as $knowledge) {
        if ((string)$knowledge['id'] === (string)$activity->knowledgeid) {
            $found = true;
            break;
        }
    }
    if (!$found) {
        $respond(false, get_string('reauthknowledgeinaccessible', 'mod_fredkicourseimport'), 403);
    }
    // Log in once more so that the STORED token is the most recently issued
    // token (some FredKI installations invalidate previous tokens on login).
    $token = $client->login();

    // Update Moodle authorisation only after all remote checks pass.
    $transaction = $DB->start_delegated_transaction();
    \mod_fredkicourseimport\local\token_manager::store(
        (int)$activity->id, (int)$USER->id, $username, $token, $password, (bool)$remember
    );
    $DB->set_field('fredkicourseimport', 'authorizedbyuserid', (int)$USER->id,
        ['id' => (int)$activity->id]);
    if (!empty($activity->authorizedbyuserid) && (int)$activity->authorizedbyuserid !== (int)$USER->id) {
        // Remove only the previous Moodle-side credentials, never remote documents.
        $DB->delete_records('fredkicourseimport_auth', [
            'activityid' => (int)$activity->id,
            'userid' => (int)$activity->authorizedbyuserid,
        ]);
    }
    $transaction->allow_commit();
    $respond(true, get_string('reauthsuccess', 'mod_fredkicourseimport'));
} catch (\Throwable $exception) {
    // Never echo credentials, tokens or raw external error bodies to the browser.
    $respond(false, get_string('reauthajaxfailed', 'mod_fredkicourseimport'), 400);
}
