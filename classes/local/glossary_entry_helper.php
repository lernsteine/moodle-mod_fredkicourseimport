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
 * classes/local/glossary_entry_helper.php for the FredKI CourseImport activity.
 *
 * @package    mod_fredkicourseimport
 * @copyright  2026 lernsteine.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_fredkicourseimport\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Stable file identity and direct Moodle source URLs for individual glossary entries.
 *
 * One generated file is mapped to exactly one entry. The entry concept is deliberately
 * NOT part of the filename: edits then refresh the same FredKI mapping rather than
 * creating a new one with a different pathname hash.
 */
final class glossary_entry_helper {
    public static function export_filename(int $cmid, int $entryid): string {
        if ($cmid <= 0 || $entryid <= 0) {
            throw new \InvalidArgumentException('Glossary course-module and entry IDs must be positive.');
        }
        return 'cm' . $cmid . '-glossary-eid' . $entryid . '.html';
    }

    /**
     * Document the parameters and result.
     *
     * @param string $filepath Parameter.
     * @param string $filename Parameter.
     * @return array{cmid:int,entryid:int}|null
     */
    public static function parse_export_filename(string $filepath, string $filename): ?array {
        if ($filepath !== '/glossaries/' ||
                !preg_match('/^cm([1-9][0-9]*)-glossary-eid([1-9][0-9]*)\.html$/D', $filename, $parts)) {
            return null;
        }
        return ['cmid' => (int)$parts[1], 'entryid' => (int)$parts[2]];
    }

    public static function direct_url(int $entryid): string {
        if ($entryid <= 0) {
            throw new \InvalidArgumentException('Glossary entry ID must be positive.');
        }
        return (new \moodle_url('/mod/glossary/showentry.php', ['eid' => $entryid]))->out(false);
    }
}
