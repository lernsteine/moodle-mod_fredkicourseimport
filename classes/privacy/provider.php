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
 * Privacy provider for the FredKI course import activity.
 *
 * @package    mod_fredkicourseimport
 * @copyright  2026 lernsteine.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_fredkicourseimport\privacy;


use context_module;
use core_privacy\local\metadata\collection;
use core_privacy\local\metadata\provider as metadata_provider;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\core_userlist_provider;
use core_privacy\local\request\plugin\provider as request_provider;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * provider for FredKI CourseImport.
 *
 * @package mod_fredkicourseimport
 */
final class provider implements core_userlist_provider, metadata_provider, request_provider {
    /**
     * Get metadata for FredKI CourseImport.
     *
     * @param collection $collection Parameter for this operation.
     * @return collection Operation result.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('fredkicourseimport', [
            'authorizedbyuserid' => 'privacy:metadata:activity:authorizedbyuserid',
        ], 'privacy:metadata:activity');
        $collection->add_database_table('fredkicourseimport_auth', [
            'userid' => 'privacy:metadata:auth:userid',
            'fredkiusername' => 'privacy:metadata:auth:username',
            'token' => 'privacy:metadata:auth:token',
            'tokenexpires' => 'privacy:metadata:auth:tokenexpires',
            'passwordencrypted' => 'privacy:metadata:auth:passwordencrypted',
            'lastused' => 'privacy:metadata:auth:lastused',
        ], 'privacy:metadata:auth');
        $collection->add_external_location_link('fredki', [
            'username' => 'privacy:metadata:fredki:username',
            'password' => 'privacy:metadata:fredki:password',
            'coursecontent' => 'privacy:metadata:fredki:coursecontent',
            'browserdata' => 'privacy:metadata:fredki:browserdata',
        ], 'privacy:metadata:fredki');
        // An independent entitlement endpoint validates service access. This sends
        // the configured service key, Moodle site hostname and plugin identifier.
        $collection->add_external_location_link('fredki_entitlement', [
            'licensekey' => 'privacy:metadata:entitlement:licensekey',
            'domain' => 'privacy:metadata:entitlement:domain',
            'plugin' => 'privacy:metadata:entitlement:plugin',
        ], 'privacy:metadata:entitlement');
        return $collection;
    }

    /**
     * Get contexts for userid for FredKI CourseImport.
     *
     * @param int $userid Parameter for this operation.
     * @return contextlist Operation result.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $list = new contextlist();
        $sql = "SELECT DISTINCT ctx.id
                  FROM {context} ctx
                  JOIN {course_modules} cm ON cm.id = ctx.instanceid
                  JOIN {modules} m ON m.id = cm.module
                  JOIN {fredkicourseimport} f ON f.id = cm.instance
                 WHERE ctx.contextlevel = :level AND m.name = :modname
                   AND (f.authorizedbyuserid = :owneruserid
                        OR EXISTS (SELECT 1 FROM {fredkicourseimport_auth} a
                                    WHERE a.activityid = f.id AND a.userid = :authuserid))";
        $list->add_from_sql($sql, [
            'level' => CONTEXT_MODULE,
            'modname' => 'fredkicourseimport',
            'owneruserid' => $userid,
            'authuserid' => $userid,
        ]);
        return $list;
    }

    /**
     * Export user data for FredKI CourseImport.
     *
     * @param approved_contextlist $contextlist Parameter for this operation.
     * @return void Operation result.
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof context_module) {
                continue;
            }
            $cm = get_coursemodule_from_id('fredkicourseimport', $context->instanceid, 0, false, IGNORE_MISSING);
            if (!$cm) {
                continue;
            }
            $auth = $DB->get_record('fredkicourseimport_auth', [
                'activityid' => $cm->instance,
                'userid' => $contextlist->get_user()->id,
            ]);
            $authorised = $DB->record_exists('fredkicourseimport', [
                'id' => $cm->instance,
                'authorizedbyuserid' => $contextlist->get_user()->id,
            ]);
            if ($auth || $authorised) {
                $data = (object)[
                    'authorised_import_for_activity' => $authorised ? 'yes' : 'no',
                ];
                if ($auth) {
                    // Never include access tokens in personal data exports.
                    $data->fredkiusername = $auth->fredkiusername;
                    $data->tokenexpires = transform::datetime($auth->tokenexpires);
                    $data->lastused = transform::datetime($auth->lastused);
                }
                writer::with_context($context)->export_data([], $data);
            }
        }
    }

    /**
     * Delete data for all users in context for FredKI CourseImport.
     *
     * @param \context $context Parameter for this operation.
     * @return void Operation result.
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;
        if (!$context instanceof context_module) {
            return;
        }
        $cm = get_coursemodule_from_id('fredkicourseimport', $context->instanceid, 0, false, IGNORE_MISSING);
        if ($cm) {
            $DB->delete_records('fredkicourseimport_auth', ['activityid' => $cm->instance]);
            $DB->set_field('fredkicourseimport', 'authorizedbyuserid', null, ['id' => $cm->instance]);
        }
    }

    /**
     * Delete data for user for FredKI CourseImport.
     *
     * @param approved_contextlist $contextlist Parameter for this operation.
     * @return void Operation result.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof context_module) {
                continue;
            }
            $cm = get_coursemodule_from_id('fredkicourseimport', $context->instanceid, 0, false, IGNORE_MISSING);
            if ($cm) {
                $DB->delete_records('fredkicourseimport_auth', [
                    'activityid' => $cm->instance,
                    'userid' => $contextlist->get_user()->id,
                ]);
                $DB->set_field_select(
                    'fredkicourseimport',
                    'authorizedbyuserid',
                    null,
                    'id = :activityid AND authorizedbyuserid = :userid',
                    ['activityid' => $cm->instance, 'userid' => $contextlist->get_user()->id]
                );
            }
        }
    }

    /**
     * Get users in context for FredKI CourseImport.
     *
     * @param userlist $userlist Parameter for this operation.
     * @return void Operation result.
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if (!$context instanceof context_module) {
            return;
        }
        $cm = get_coursemodule_from_id('fredkicourseimport', $context->instanceid, 0, false, IGNORE_MISSING);
        if ($cm) {
            $userlist->add_from_sql(
                'userid',
                'SELECT userid FROM {fredkicourseimport_auth} WHERE activityid = :activityid',
                ['activityid' => $cm->instance]
            );
            $userlist->add_from_sql(
                'userid',
                'SELECT authorizedbyuserid AS userid FROM {fredkicourseimport} ' .
                    'WHERE id = :activityid AND authorizedbyuserid IS NOT NULL',
                ['activityid' => $cm->instance]
            );
        }
    }

    /**
     * Delete data for users for FredKI CourseImport.
     *
     * @param approved_userlist $userlist Parameter for this operation.
     * @return void Operation result.
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;
        $context = $userlist->get_context();
        if (!$context instanceof context_module) {
            return;
        }
        $cm = get_coursemodule_from_id('fredkicourseimport', $context->instanceid, 0, false, IGNORE_MISSING);
        $userids = $userlist->get_userids();
        if (!$cm || !$userids) {
            return;
        }
        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $params['activityid'] = $cm->instance;
        $DB->delete_records_select(
            'fredkicourseimport_auth',
            "activityid = :activityid AND userid {$insql}",
            $params
        );
        $DB->set_field_select(
            'fredkicourseimport',
            'authorizedbyuserid',
            null,
            "id = :activityid AND authorizedbyuserid {$insql}",
            $params
        );
    }
}
