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
 * Return FredKI agents for the activity configuration form.
 *
 * @package    mod_fredkicourseimport
 * @copyright  2026 lernsteine.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

use mod_fredkicourseimport\local\ajax_helper;
use mod_fredkicourseimport\local\fredki_client;

// The helper repeats the login, session key and capability checks for the course.
require_login(required_param('courseid', PARAM_INT));
ajax_helper::require_course_access();

try {
    if (optional_param('usesaved', 0, PARAM_BOOL)) {
        // Reuse the remembered FredKI login of the current user; the password stays on the server.
        $saved = \mod_fredkicourseimport\local\token_manager::get_saved_login((int)$USER->id);
        if (!$saved) {
            throw new moodle_exception('nosavedlogin', 'mod_fredkicourseimport');
        }
        $username = $saved->username;
        $password = $saved->password;
    } else {
        $username = required_param('username', PARAM_TEXT);
        $password = required_param('password', PARAM_RAW_TRIMMED);
    }
    $workspace = optional_param('workspace', 'default', PARAM_ALPHANUMEXT);
    $config = get_config('mod_fredkicourseimport');

    if (empty($config->baseurl)) {
        throw new moodle_exception('missingbaseurl', 'mod_fredkicourseimport');
    }

    $client = new fredki_client(
        $config->baseurl,
        $workspace ?: 'default',
        $username,
        $password,
        $config->loginpath ?? '/admin/api/user/login',
        !empty($config->debuglog)
    );

    ajax_helper::send_json([
        'success' => true,
        'items' => $client->list_agents(),
        'error' => '',
    ]);
} catch (\Throwable $exception) {
    debugging('[mod_fredkicourseimport] Agent request failed: ' . get_class($exception), DEBUG_DEVELOPER);
    ajax_helper::send_json([
        'success' => false,
        'items' => [],
        'error' => get_string('externalapierror', 'mod_fredkicourseimport'),
    ], 400);
}
