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
 * classes/external/get_knowledge_bases.php for the FredKI CourseImport activity.
 *
 * @package    mod_fredkicourseimport
 * @copyright  2026 lernsteine.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_fredkicourseimport\external;
defined('MOODLE_INTERNAL') || die();
use external_api;
use external_function_parameters;
use external_multiple_structure;
use external_single_structure;
use external_value;

/**
 * get_knowledge_bases class for FredKI CourseImport.
 *
 * @package mod_fredkicourseimport
 */
final class get_knowledge_bases extends external_api {
    /**
     * execute parameters operation.
     *
     * @return external_function_parameters Operation result.
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT),
            'username' => new external_value(PARAM_TEXT),
            'password' => new external_value(PARAM_RAW),
            'workspace' => new external_value(PARAM_TEXT),
        ]);
    }
    /**
     * execute operation.
     *
     * @param int $courseid Parameter for this operation.
     * @param string $username Parameter for this operation.
     * @param string $password Parameter for this operation.
     * @param string $workspace Parameter for this operation.
     * @return array Operation result.
     */
    public static function execute(int $courseid, string $username, string $password, string $workspace): array {
        $params = self::validate_parameters(self::execute_parameters(), compact('courseid', 'username', 'password', 'workspace'));
        $context = \context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('mod/fredkicourseimport:addinstance', $context);
        $config = get_config('mod_fredkicourseimport');
        $client = new \mod_fredkicourseimport\local\fredki_client(
            $config->baseurl ?? '',
            $params['workspace'],
            $params['username'],
            $params['password'],
            $config->loginpath ?? '/admin/api/user/login',
            false
        );
        return $client->list_knowledge_bases();
    }
    /**
     * execute returns operation.
     *
     * @return external_multiple_structure Operation result.
     */
    public static function execute_returns(): external_multiple_structure {
        return new external_multiple_structure(new external_single_structure([
            'id' => new external_value(PARAM_TEXT),
            'name' => new external_value(PARAM_TEXT),
        ]));
    }
}
