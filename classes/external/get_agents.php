<?php
namespace mod_fredkicourseimport\external;
defined('MOODLE_INTERNAL') || die();
use external_api;
use external_function_parameters;
use external_multiple_structure;
use external_single_structure;
use external_value;

final class get_agents extends external_api {
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT),
            'username' => new external_value(PARAM_TEXT),
            'password' => new external_value(PARAM_RAW),
            'workspace' => new external_value(PARAM_TEXT),
        ]);
    }
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
        return $client->list_agents();
    }
    public static function execute_returns(): external_multiple_structure {
        return new external_multiple_structure(new external_single_structure([
            'id' => new external_value(PARAM_TEXT),
            'name' => new external_value(PARAM_TEXT),
        ]));
    }
}
