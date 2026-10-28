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
 * Stable file identity and direct Moodle URLs for individual glossary entries.
 *
 * @package    mod_fredkicourseimport
 * @copyright  2026 lernsteine.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_fredkicourseimport\local;

/**
 * Stable file identity and direct Moodle URLs for individual glossary entries.
 *
 * In complete mode every approved glossary entry becomes its own generated file and
 * therefore its own FredKI document. The entry concept is deliberately not part of
 * the filename: renaming a concept then updates the same FredKI document instead of
 * creating a new one.
 *
 * @package    mod_fredkicourseimport
 * @copyright  2026 lernsteine.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class glossary_entry_helper {
    /** @var string Generated file path used for glossary exports. */
    public const FILEPATH = '/glossaries/';

    /**
     * Build the generated filename for one glossary entry.
     *
     * @param int $cmid Glossary course module id.
     * @param int $entryid Glossary entry id.
     * @return string
     */
    public static function export_filename(int $cmid, int $entryid): string {
        if ($cmid <= 0 || $entryid <= 0) {
            throw new \coding_exception('Glossary course module and entry ids must be positive.');
        }
        return 'cm' . $cmid . '-glossary-eid' . $entryid . '.html';
    }

    /**
     * Parse a generated glossary entry filename.
     *
     * @param string $filepath Generated file path.
     * @param string $filename Generated file name.
     * @return array|null Array with keys cmid and entryid, or null if this is no entry export.
     */
    public static function parse_export_filename(string $filepath, string $filename): ?array {
        if (
            $filepath !== self::FILEPATH ||
                !preg_match('/^cm([1-9][0-9]*)-glossary-eid([1-9][0-9]*)\.html$/D', $filename, $parts)
        ) {
            return null;
        }
        return ['cmid' => (int)$parts[1], 'entryid' => (int)$parts[2]];
    }

    /**
     * Direct Moodle link to one glossary entry.
     *
     * @param int $entryid Glossary entry id.
     * @return string
     */
    public static function direct_url(int $entryid): string {
        if ($entryid <= 0) {
            throw new \coding_exception('Glossary entry id must be positive.');
        }
        return (new \moodle_url('/mod/glossary/showentry.php', ['eid' => $entryid]))->out(false);
    }
}
