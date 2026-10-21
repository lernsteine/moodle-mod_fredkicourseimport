<?php
// This file is part of FredKI CourseImport for Moodle.
// Copyright (c) 2026 lernsteine.com
//
// This program is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
// See <https://www.gnu.org/licenses/>.

/**
 * FredKI course import plugin file.
 *
 * @package    mod_fredkicourseimport
 * @copyright  2026 lernsteine.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_fredkicourseimport\local;

defined('MOODLE_INTERNAL') || die();

/**
 * License handling for FredKI Course Import.
 *
 * The plugin validates the Moodle site domain against the external license server.
 */
class license_manager {

    private const LICENSE_ENDPOINT = 'https://bildungbleibt.de/generator/check.php';
    private const CACHE_TTL = 86400; // 24 hours.

    private const CONFIG_COMPONENT = 'fredkicourseimport';
    private const LEGACY_CONFIG_COMPONENT = 'mod_fredkicourseimport';

    public static function require_valid_license(bool $forcecheck = false): void {
        $status = self::get_license_status($forcecheck);
        if (empty($status['valid'])) {
            $message = $status['message'] ?? get_string('invalidlicense', 'mod_fredkicourseimport');
            throw new \moodle_exception('invalidlicense', 'mod_fredkicourseimport', '', null, $message);
        }
    }

    public static function is_valid_license(bool $forcecheck = false): bool {
        $status = self::get_license_status($forcecheck);
        return !empty($status['valid']);
    }

    public static function get_license_status(bool $forcecheck = false): array {
        global $CFG;

        $licensekey = self::get_license_key();
        $domain = self::get_current_domain();

        if ($licensekey === '') {
            self::clear_license_cache();
            return [
                'valid' => false,
                'message' => get_string('invalidlicense', 'mod_fredkicourseimport'),
                'domain' => $domain,
            ];
        }

        if ($domain === '') {
            self::clear_license_cache();
            return [
                'valid' => false,
                'message' => 'Die Moodle-Domain konnte nicht ermittelt werden.',
            ];
        }

        if (!$forcecheck) {
            $cached = self::get_cached_status($licensekey, $domain);
            if ($cached !== null) {
                return $cached;
            }
        }

        $response = self::check_remote_license($licensekey, $domain);

        if (!empty($response['valid'])) {
            $status = [
                'valid' => true,
                'message' => $response['message'] ?? 'License valid.',
                'expires' => $response['expires'] ?? null,
                'domain' => $domain,
                'cacheduntil' => time() + self::CACHE_TTL,
            ];
            self::set_cached_status($licensekey, $domain, $status);
            return $status;
        }

        self::clear_license_cache();

        return [
            'valid' => false,
            'message' => $response['message'] ?? 'Der Lizenzserver hat keine gültige Antwort geliefert.',
            'domain' => $domain,
        ];
    }

    public static function clear_license_cache(): void {
        unset_config('license_status_cache', self::CONFIG_COMPONENT);
        unset_config('license_valid_until', self::CONFIG_COMPONENT);
        unset_config('license_status_cache', self::LEGACY_CONFIG_COMPONENT);
        unset_config('license_valid_until', self::LEGACY_CONFIG_COMPONENT);
    }

    private static function get_license_key(): string {
        $licensekey = trim((string)get_config(self::CONFIG_COMPONENT, 'licensekey'));
        if ($licensekey !== '') {
            return $licensekey;
        }

        return trim((string)get_config(self::LEGACY_CONFIG_COMPONENT, 'licensekey'));
    }

    private static function get_current_domain(): string {
        global $CFG;

        $domain = strtolower((string)parse_url($CFG->wwwroot, PHP_URL_HOST));
        $domain = preg_replace('/^www\./', '', $domain);
        return trim((string)$domain);
    }

    private static function get_cached_status(string $licensekey, string $domain): ?array {
        $raw = get_config(self::CONFIG_COMPONENT, 'license_status_cache');
        if (empty($raw)) {
            $raw = get_config(self::LEGACY_CONFIG_COMPONENT, 'license_status_cache');
        }
        if (empty($raw)) {
            return null;
        }

        $cached = json_decode((string)$raw, true);
        if (!is_array($cached)) {
            self::clear_license_cache();
            return null;
        }

        if (($cached['licensekey'] ?? '') !== self::cache_key_hash($licensekey)) {
            self::clear_license_cache();
            return null;
        }

        if (($cached['domain'] ?? '') !== $domain) {
            self::clear_license_cache();
            return null;
        }

        if (empty($cached['validuntil']) || (int)$cached['validuntil'] <= time()) {
            self::clear_license_cache();
            return null;
        }

        return [
            'valid' => true,
            'message' => 'License valid from cache.',
            'expires' => $cached['expires'] ?? null,
            'domain' => $domain,
            'cacheduntil' => (int)$cached['validuntil'],
        ];
    }

    private static function set_cached_status(string $licensekey, string $domain, array $status): void {
        $validuntil = (int)($status['cacheduntil'] ?? (time() + self::CACHE_TTL));
        $cache = [
            'licensekey' => self::cache_key_hash($licensekey),
            'domain' => $domain,
            'validuntil' => $validuntil,
            'expires' => $status['expires'] ?? null,
        ];

        set_config('license_status_cache', json_encode($cache), self::CONFIG_COMPONENT);
        set_config('license_valid_until', $validuntil, self::CONFIG_COMPONENT);
    }

    private static function cache_key_hash(string $licensekey): string {
        return hash('sha256', $licensekey);
    }

    private static function check_remote_license(string $licensekey, string $domain): array {
        global $CFG;

        require_once($CFG->libdir . '/filelib.php');

        $curl = new \curl();
        $params = [
            'licensekey' => $licensekey,
            'domain' => $domain,
            'plugin' => 'mod_fredkicourseimport',
        ];

        $result = $curl->post(self::LICENSE_ENDPOINT, $params, [
            'CURLOPT_TIMEOUT' => 10,
            'CURLOPT_CONNECTTIMEOUT' => 5,
        ]);

        if (empty($result)) {
            return [
                'valid' => false,
                'message' => 'Der Lizenzserver ist nicht erreichbar.',
            ];
        }

        $data = json_decode($result, true);
        if (!is_array($data)) {
            return [
                'valid' => false,
                'message' => 'Der Lizenzserver hat keine gültige JSON-Antwort geliefert.',
            ];
        }

        return $data;
    }
}
