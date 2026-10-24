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
 * User-scoped FredKI authentication, with automatic renewal of short-lived tokens.
 *
 * The password is encrypted with Moodle's installation-specific key (stored in
 * moodledata). Never export credentials as part of activity backups or privacy data.
 *
 * @package mod_fredkicourseimport
 * @copyright 2026 lernsteine.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_fredkicourseimport\local;

/**
 * Token manager class.
 *
 * @package    mod_fredkicourseimport
 * @copyright  2026 lernsteine.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class token_manager {
    /**
     * @var int Token lifetime in seconds. Refresh proactively: FredKI can invalidate tokens before the old
     *      eight-hour assumption, and Moodle must not discard authorisation when a token expires.
     */
    public const DEFAULT_LIFETIME = 3600;

    /** @var bool[] Avoid repeated slow login attempts for many pending files in one request. */
    private static $failedrenewals = [];

    /**
     * Store an authenticated token and optionally encrypted renewal credentials.
     *
     * @param int $activityid Activity id.
     * @param int $userid Moodle user id.
     * @param string $username FredKI username.
     * @param string $token Access token.
     * @param string $password FredKI password, only used when $remember is not false.
     * @param bool|null $remember True/null: keep the password encrypted for automatic token renewal.
     *                            False: do not keep the password and remove any stored one.
     */
    public static function store(
        int $activityid,
        int $userid,
        string $username,
        string $token,
        string $password = '',
        ?bool $remember = null
    ): void {
        global $DB;
        $now = time();
        $record = $DB->get_record('fredkicourseimport_auth', ['activityid' => $activityid, 'userid' => $userid]);
        $data = (object)[
            'activityid' => $activityid,
            'userid' => $userid,
            'fredkiusername' => trim($username),
            'token' => $token,
            'tokenexpires' => $now + self::DEFAULT_LIFETIME,
            'lastused' => $now,
            'timemodified' => $now,
        ];
        if ($remember === false) {
            $data->passwordcipher = null;
        } else if ($password !== '') {
            // The core encryption API uses the Moodle instance key, outside the plugin tree.
            $data->passwordcipher = \core\encryption::encrypt($password);
        }
        if ($record) {
            $data->id = $record->id;
            $DB->update_record('fredkicourseimport_auth', $data);
        } else {
            $data->timecreated = $now;
            $DB->insert_record('fredkicourseimport_auth', $data);
        }
    }

    /**
     * Check whether this activity can authenticate without contacting FredKI.
     * Used for rendering view.php without a network request on page load.
     */
    public static function has_credentials(int $activityid, int $userid): bool {
        global $DB;
        if ($activityid <= 0 || $userid <= 0) {
            return false;
        }
        $record = $DB->get_record('fredkicourseimport_auth', [
            'activityid' => $activityid,
            'userid' => $userid,
        ]);
        return $record && (!empty($record->passwordcipher) ||
            (!empty($record->token) && (int)$record->tokenexpires > time()));
    }

    /**
     * Return a valid access token, renewing it when possible. Older installations
     * have no encrypted password yet: preserve their records so users can reconnect
     * without losing the target knowledge base or synchronized mapping data.
     */
    public static function get(int $activityid, int $userid): ?\stdClass {
        global $DB;
        if ($activityid <= 0 || $userid <= 0) {
            return null;
        }
        $record = $DB->get_record('fredkicourseimport_auth', ['activityid' => $activityid, 'userid' => $userid]);
        if (!$record) {
            return null;
        }
        if (!empty($record->token) && (int)$record->tokenexpires > time()) {
            $DB->set_field('fredkicourseimport_auth', 'lastused', time(), ['id' => $record->id]);
            return $record;
        }
        return self::renew($activityid, $userid);
    }

    /**
     * Force a new FredKI login (e.g. to recover an unexpectedly revoked token).
     */
    public static function renew(int $activityid, int $userid): ?\stdClass {
        global $DB;
        $key = $activityid . ':' . $userid;
        if (!empty(self::$failedrenewals[$key])) {
            return null;
        }
        $record = $DB->get_record('fredkicourseimport_auth', ['activityid' => $activityid, 'userid' => $userid]);
        if (!$record || empty($record->passwordcipher) || empty($record->fredkiusername)) {
            return null;
        }
        try {
            $password = \core\encryption::decrypt($record->passwordcipher);
            if ($password === '') {
                return null;
            }
            $activity = $DB->get_record('fredkicourseimport', ['id' => $activityid], 'id, workspace', IGNORE_MISSING);
            if (!$activity) {
                return null;
            }
            $config = get_config('mod_fredkicourseimport');
            $client = new fredki_client(
                $config->baseurl ?? '',
                $activity->workspace ?: 'default',
                $record->fredkiusername,
                $password,
                $config->loginpath ?? '/admin/api/user/login',
                !empty($config->debuglog)
            );
            $token = $client->login();
            // Do not rewrite encrypted password when renewing the token.
            self::store($activityid, $userid, $record->fredkiusername, $token);
            return $DB->get_record('fredkicourseimport_auth', ['activityid' => $activityid, 'userid' => $userid]);
        } catch (\Throwable $e) {
            self::$failedrenewals[$key] = true;
            // Wrong/rotated credentials or temporary FredKI failure: no destructive
            // local cleanup. An admin can reauthorise the unchanged activity.
            return null;
        }
    }

    /**
     * Remove a stored (encrypted) password so the user has to log in again once the token expires.
     *
     * @param int $activityid Activity id.
     * @param int $userid Moodle user id.
     */
    public static function forget_password(int $activityid, int $userid): void {
        global $DB;
        $DB->set_field(
            'fredkicourseimport_auth',
            'passwordcipher',
            null,
            ['activityid' => $activityid, 'userid' => $userid]
        );
    }

    /**
     * Whether an encrypted password is stored for automatic renewal.
     *
     * @param int $activityid Activity id.
     * @param int $userid Moodle user id.
     * @return bool
     */
    public static function has_stored_password(int $activityid, int $userid): bool {
        global $DB;
        $cipher = $DB->get_field(
            'fredkicourseimport_auth',
            'passwordcipher',
            ['activityid' => $activityid, 'userid' => $userid],
            IGNORE_MISSING
        );
        return !empty($cipher);
    }

    /**
     * Return the most recently used remembered FredKI login of a Moodle user.
     *
     * Lets a user create further activities without typing the FredKI credentials again.
     * The password is only returned to server-side code and never sent to the browser.
     *
     * @param int $userid Moodle user id.
     * @return \stdClass|null Object with username and password, or null if none is stored.
     */
    public static function get_saved_login(int $userid): ?\stdClass {
        global $DB;
        if ($userid <= 0) {
            return null;
        }
        try {
            $notempty = $DB->sql_isnotempty('fredkicourseimport_auth', 'passwordcipher', true, true);
            $records = $DB->get_records_select(
                'fredkicourseimport_auth',
                'userid = :userid AND ' . $notempty,
                ['userid' => $userid],
                'lastused DESC',
                '*',
                0,
                1
            );
            $record = $records ? reset($records) : null;
            if (!$record || empty($record->fredkiusername)) {
                return null;
            }
            $password = \core\encryption::decrypt($record->passwordcipher);
        } catch (\Throwable $e) {
            // Never break the activity form because of an unreadable saved login.
            debugging('[mod_fredkicourseimport] Saved login could not be read: ' . get_class($e), DEBUG_DEVELOPER);
            return null;
        }
        if ($password === '') {
            return null;
        }
        return (object)['username' => $record->fredkiusername, 'password' => $password];
    }

    /**
     * Delete activity.
     *
     * @param int $activityid The activity id.
     */
    public static function delete_activity(int $activityid): void {
        global $DB;
        $DB->delete_records('fredkicourseimport_auth', ['activityid' => $activityid]);
    }
}
