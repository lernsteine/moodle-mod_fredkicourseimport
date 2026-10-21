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
 * Shared helpers for the plugin JSON endpoints.
 *
 * @package    mod_fredkicourseimport
 * @copyright  2026 lernsteine.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_fredkicourseimport\local;


/**
 * Shared endpoint authorisation and response handling.
 */
final class ajax_helper {
    /**
     * Validate the request and return the relevant course context.
     *
     * @return \context_course
     */
    public static function require_course_access(): \context_course {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            throw new \moodle_exception('invalidrequestmethod', 'mod_fredkicourseimport');
        }

        $courseid = required_param('courseid', PARAM_INT);
        $course = get_course($courseid);
        require_login($course);
        require_sesskey();

        $context = \context_course::instance($course->id);
        require_capability('mod/fredkicourseimport:addinstance', $context);

        return $context;
    }

    /**
     * Send a JSON response and stop script execution.
     *
     * @param array $payload Response data.
     * @param int $statuscode HTTP status code.
     * @return never
     */
    public static function send_json(array $payload, int $statuscode = 200): void {
        http_response_code($statuscode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
