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
 * PHPUnit tests for mod_fredkicourseimport.
 *
 * @package    mod_fredkicourseimport
 * @copyright  2026 lernsteine.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_fredkicourseimport;

/**
 * Regression coverage for release 1.6.7: durable knowledge base authorisation.
 */
final class token_manager_test extends \advanced_testcase {
    /**
     * Test missing token returns null.
     */
    public function test_missing_token_returns_null(): void {
        $this->resetAfterTest();
        $this->assertNull(\mod_fredkicourseimport\local\token_manager::get(999, 999));
        $this->assertFalse(\mod_fredkicourseimport\local\token_manager::has_credentials(999, 999));
    }

    /**
     * Test password is encrypted and never exposed by form access.
     */
    public function test_password_is_encrypted_and_never_exposed_by_form_access(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $activityid = $this->create_activity($course->id, $user->id);

        \mod_fredkicourseimport\local\token_manager::store(
            $activityid,
            $user->id,
            'fredki-user',
            'temporary-access-token',
            'secret-password'
        );
        $auth = $DB->get_record('fredkicourseimport_auth', [
            'activityid' => $activityid, 'userid' => $user->id,
        ], '*', MUST_EXIST);
        $this->assertNotSame('secret-password', $auth->passwordcipher);
        $this->assertSame('secret-password', \core\encryption::decrypt($auth->passwordcipher));
        $this->assertTrue(\mod_fredkicourseimport\local\token_manager::has_credentials($activityid, $user->id));
        $this->assertNotNull(\mod_fredkicourseimport\local\token_manager::get($activityid, $user->id));
    }

    /**
     * Test expired legacy token is not deleted.
     */
    public function test_expired_legacy_token_is_not_deleted(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $activityid = $this->create_activity($course->id, $user->id);

        \mod_fredkicourseimport\local\token_manager::store(
            $activityid,
            $user->id,
            'legacy-user',
            'expired-token'
        );
        $DB->set_field('fredkicourseimport_auth', 'tokenexpires', time() - 1, [
            'activityid' => $activityid, 'userid' => $user->id,
        ]);
        $this->assertFalse(\mod_fredkicourseimport\local\token_manager::has_credentials($activityid, $user->id));
        $this->assertNull(\mod_fredkicourseimport\local\token_manager::get($activityid, $user->id));
        $this->assertTrue($DB->record_exists('fredkicourseimport_auth', [
            'activityid' => $activityid, 'userid' => $user->id,
        ]));
        $this->assertEquals('fixed-knowledge-id', $DB->get_field('fredkicourseimport', 'knowledgeid', [
            'id' => $activityid,
        ]));
    }

    /**
     * Test saved login is reusable only when remembered.
     */
    public function test_saved_login_is_reusable_only_when_remembered(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->assertNull(\mod_fredkicourseimport\local\token_manager::get_saved_login($user->id));

        $first = $this->create_activity($course->id, $user->id);
        \mod_fredkicourseimport\local\token_manager::store($first, $user->id, 'fredki-user', 'token', 'secret-password', false);
        $this->assertNull(\mod_fredkicourseimport\local\token_manager::get_saved_login($user->id));

        \mod_fredkicourseimport\local\token_manager::store($first, $user->id, 'fredki-user', 'token', 'secret-password', true);
        $saved = \mod_fredkicourseimport\local\token_manager::get_saved_login($user->id);
        $this->assertSame('fredki-user', $saved->username);
        $this->assertSame('secret-password', $saved->password);

        $other = $this->getDataGenerator()->create_user();
        $this->assertNull(\mod_fredkicourseimport\local\token_manager::get_saved_login($other->id));
    }

    /**
     * Create activity.
     *
     * @param int $courseid The course id.
     * @param int $userid The Moodle user id.
     * @return int The result.
     */
    private function create_activity(int $courseid, int $userid): int {
        global $DB;
        return $DB->insert_record('fredkicourseimport', (object)[
            'course' => $courseid,
            'name' => 'Integration test',
            'knowledgeid' => 'fixed-knowledge-id',
            'knowledgebasename' => 'Test knowledge base',
            'authorizedbyuserid' => $userid,
            'workspace' => 'default',
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
    }
}
