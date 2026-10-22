<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.
// Copyright (c) 2026 lernsteine.com
// SPDX-License-Identifier: GPL-3.0-or-later
// Access to the external FredKI service is separately licensed.

/**
 * FredKI token and optional encrypted credential storage.
 *
 * A token alone is not sufficient for unattended renewal. Moodle core encryption
 * is used only after an editor explicitly opts in to credential retention.
 *
 * @package mod_fredkicourseimport
 */
namespace mod_fredkicourseimport\local;

defined('MOODLE_INTERNAL') || die();

final class token_manager {
    public const DEFAULT_LIFETIME = 28800;
    private const RENEW_BEFORE = 300;

    /**
     * Persist a newly authenticated token and, optionally, an encrypted password.
     * Empty passwords are never stored. A manual reconnection without opt-in
     * removes any previously stored automatic-reconnection credentials.
     * @param mixed $activityid activityid parameter.
     * @param mixed $userid userid parameter.
     * @param mixed $username username parameter.
     * @param mixed $token token parameter.
     * @param mixed $password password parameter.
     * @param mixed $remember remember parameter.
     */
    public static function store(
        int $activityid,
        int $userid,
        string $username,
        string $token,
        ?string $password = null,
        bool $remember = false
    ): void {
        global $DB;
        $now = time();
        $record = $DB->get_record('fredkicourseimport_auth', ['activityid' => $activityid, 'userid' => $userid]);
        $encrypted = null;
        if ($remember && $password !== null && $password !== '') {
            $encrypted = \core\encryption::encrypt($password);
        }
        $data = (object)[
            'activityid' => $activityid,
            'userid' => $userid,
            'fredkiusername' => trim($username),
            'token' => $token,
            'tokenexpires' => $now + self::DEFAULT_LIFETIME,
            'passwordencrypted' => $encrypted,
            'lastused' => $now,
            'timemodified' => $now,
        ];
        if ($record) {
            $data->id = $record->id;
            $DB->update_record('fredkicourseimport_auth', $data);
        } else {
            $data->timecreated = $now;
            $DB->insert_record('fredkicourseimport_auth', $data);
        }
    }

    /**
     * A valid local token; do not delete an expired record because its encrypted credential may be needed.
     *
     * @param int $activityid activityid parameter.
     * @param int $userid userid parameter.
     */
    public static function get(int $activityid, int $userid): ?\stdClass {
        global $DB;
        if ($userid <= 0) {
            return null;
        }
        $record = $DB->get_record('fredkicourseimport_auth', ['activityid' => $activityid, 'userid' => $userid]);
        if (!$record || (int)$record->tokenexpires <= time() || empty($record->token)) {
            return null;
        }
        $DB->set_field('fredkicourseimport_auth', 'lastused', time(), ['id' => $record->id]);
        return $record;
    }

    /**
     * True when the current authorisation can recover without an interactive login.
     *
     * @param int $activityid activityid parameter.
     * @param int $userid userid parameter.
     */
    public static function can_renew(int $activityid, int $userid): bool {
        global $DB;
        if ($userid <= 0) {
            return false;
        }
        $record = $DB->get_record('fredkicourseimport_auth', ['activityid' => $activityid, 'userid' => $userid]);
        return $record && !empty($record->passwordencrypted) && !empty($record->fredkiusername);
    }

    /**
     * Re-login shortly before token expiry, with a lock to reduce concurrent logins.
     * Never fall back to an expired token after a failed login.
     * @param \stdClass $activity activity parameter.
     * @param mixed $force force parameter.
     * @param mixed $rejectedtoken rejectedtoken parameter.
     */
    public static function get_or_renew(
        \stdClass $activity,
        bool $force = false,
        ?string $rejectedtoken = null
    ): ?\stdClass {
        global $DB;
        $activityid = (int)$activity->id;
        $userid = (int)($activity->authorizedbyuserid ?? 0);
        if ($userid <= 0) {
            return null;
        }
        $record = $DB->get_record('fredkicourseimport_auth', ['activityid' => $activityid, 'userid' => $userid]);
        if (!$record) {
            return null;
        }
        // A concurrent request might already have replaced this rejected token.
        if (
            $force && $rejectedtoken !== null && (string)$record->token !== $rejectedtoken
                && (int)$record->tokenexpires > time()
        ) {
            return $record;
        }
        if (!$force && (int)$record->tokenexpires > time() + self::RENEW_BEFORE && !empty($record->token)) {
            return $record;
        }
        if (empty($record->passwordencrypted) || empty($record->fredkiusername)) {
            return !$force && (int)$record->tokenexpires > time() && !empty($record->token) ? $record : null;
        }

        $factory = \core\lock\lock_config::get_lock_factory('mod_fredkicourseimport_auth');
        $lock = $factory->get_lock('activity:' . $activityid . ':user:' . $userid, 10, 60);
        if (!$lock) {
            throw new \moodle_exception('authrenewbusy', 'mod_fredkicourseimport');
        }
        try {
            // Re-read: another request may have renewed the token while we waited.
            $latest = $DB->get_record('fredkicourseimport_auth', ['activityid' => $activityid, 'userid' => $userid]);
            if (!$latest) {
                return null;
            }
            if (
                $force && $rejectedtoken !== null && (string)$latest->token !== $rejectedtoken
                    && (int)$latest->tokenexpires > time()
            ) {
                return $latest;
            }
            if (!$force && (int)$latest->tokenexpires > time() + self::RENEW_BEFORE && !empty($latest->token)) {
                return $latest;
            }
            if (empty($latest->passwordencrypted)) {
                return null;
            }
            $password = \core\encryption::decrypt((string)$latest->passwordencrypted);
            if ($password === '') {
                return null;
            }
            $config = get_config('mod_fredkicourseimport');
            $client = new fredki_client(
                (string)($config->baseurl ?? ''),
                (string)($activity->workspace ?? 'default'),
                (string)$latest->fredkiusername,
                $password,
                (string)($config->loginpath ?? '/admin/api/user/login'),
                !empty($config->debuglog)
            );
            $newtoken = $client->login();
            // Only change the token; preserve the encrypted credential and user mapping.
            $DB->update_record('fredkicourseimport_auth', (object)[
                'id' => $latest->id,
                'token' => $newtoken,
                'tokenexpires' => time() + self::DEFAULT_LIFETIME,
                'lastused' => time(),
                'timemodified' => time(),
            ]);
            return $DB->get_record('fredkicourseimport_auth', ['id' => $latest->id]);
        } finally {
            $lock->release();
        }
    }

    /**
     * Invalidate the local expiry on HTTP 401 while keeping encrypted credentials for recovery.
     *
     * @param int $activityid activityid parameter.
     * @param int $userid userid parameter.
     */
    public static function invalidate(int $activityid, int $userid): void {
        global $DB;
        $DB->set_field(
            'fredkicourseimport_auth',
            'tokenexpires',
            0,
            ['activityid' => $activityid, 'userid' => $userid]
        );
    }

    /**
     * Client for an activity; renew on a rejected Bearer token exactly once per request.
     *
     * @param \stdClass $activity activity parameter.
     */
    public static function client_for_activity(\stdClass $activity): fredki_client {
        $auth = self::get_or_renew($activity);
        if (!$auth) {
            throw new \moodle_exception('missingvalidtoken', 'mod_fredkicourseimport');
        }
        $config = get_config('mod_fredkicourseimport');
        $client = new fredki_client(
            (string)($config->baseurl ?? ''),
            (string)($activity->workspace ?? 'default'),
            '',
            '',
            (string)($config->loginpath ?? '/admin/api/user/login'),
            !empty($config->debuglog)
        );
        $client->set_token((string)$auth->token);
        $client->set_auth_failure_callback(function () use ($activity): void {
            self::invalidate((int)$activity->id, (int)$activity->authorizedbyuserid);
        });
        if (self::can_renew((int)$activity->id, (int)$activity->authorizedbyuserid)) {
            $client->set_token_renewal_callback(function () use ($activity, $auth): string {
                $renewed = self::get_or_renew($activity, true, (string)$auth->token);
                if (!$renewed) {
                    throw new \moodle_exception('missingvalidtoken', 'mod_fredkicourseimport');
                }
                return (string)$renewed->token;
            });
        }
        return $client;
    }

    public static function delete_activity(int $activityid): void {
        global $DB;
        $DB->delete_records('fredkicourseimport_auth', ['activityid' => $activityid]);
    }
}
