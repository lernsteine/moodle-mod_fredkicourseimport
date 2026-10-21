<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Shared helpers for the plugin JSON endpoints.
 *
 * @package    mod_fredkicourseimport
 * @copyright  2026 lernsteine.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_fredkicourseimport\local;

defined('MOODLE_INTERNAL') || die();

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
