<?php
// This file is part of FredKI CourseImport for Moodle.
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

namespace mod_fredkicourseimport\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Verifies that one concrete Moodle source has really ceased to exist.
 * A missing generated FredKI export is NEVER proof of deletion.
 */
final class source_deletion_guard {
    /**
     * Return true only for positively identified and now physically absent Moodle originals.
     * Hiding activities, changing groups, access conditions or exporting zero files never qualifies.
     */
    public static function original_missing(\stdClass $activity, \stdClass $map): bool {
        global $DB;

        $sourcecmid = (int)($map->sourcecmid ?? 0);
        if ($sourcecmid <= 0 || empty($activity->course) || empty($map->timesynced)) {
            return false; // Never automatically remove course section summaries on missing output.
        }
        // The course must still exist. When a whole course is deleted, use the separate
        // administrative cleanup register, not an unbounded bulk delete.
        if (!$DB->record_exists('course', ['id' => (int)$activity->course])) {
            return false;
        }
        $cm = $DB->get_record('course_modules', ['id' => $sourcecmid], 'id,course,instance,module', IGNORE_MISSING);
        if (!$cm) {
            return true; // Specific source course module was deleted in Moodle.
        }
        if ((int)$cm->course !== (int)$activity->course) {
            return false; // Mismatch is not permission to delete any FredKI document.
        }
        $filename = (string)($map->filename ?? '');
        if (
            preg_match('/^cm([1-9][0-9]*)-glossary-eid([1-9][0-9]*)\.html$/D', $filename, $parts)
                && (int)$parts[1] === $sourcecmid
        ) {
            // Check an individual entry independently of the generated glossary export.
            $glossary = $DB->get_record('glossary', ['id' => (int)$cm->instance], 'id', IGNORE_MISSING);
            if (!$glossary) {
                return false; // Inconsistent database, not sufficiently reliable for deletion.
            }
            return !$DB->record_exists('glossary_entries', [
                'id' => (int)$parts[2], 'glossaryid' => (int)$cm->instance,
            ]);
        }

        $module = $DB->get_field('modules', 'name', ['id' => (int)$cm->module], IGNORE_MISSING);
        if (!in_array($module, ['resource', 'folder'], true)) {
            return false; // Still-existing activity; title/mode/visibility changes do not delete.
        }
        if (!$DB->record_exists($module, ['id' => (int)$cm->instance])) {
            return false; // Source database inconsistency: fail closed.
        }
        // Determine the exact original filename. Reference mode uses a generated HTML name.
        $originalname = null;
        if (
            preg_match('/^cm([1-9][0-9]*)-(resource|folder)-(.+)-info\.html$/D', $filename, $parts)
                && (int)$parts[1] === $sourcecmid && $parts[2] === $module
        ) {
            $originalname = $parts[3];
        } else if ((string)($map->contentmode ?? '') === 'knowledge' && $filename !== '') {
            $originalname = $filename;
        }
        if ($originalname === null) {
            return false; // Unknown/legacy mapping, do not guess.
        }
        $context = \context_module::instance($sourcecmid, IGNORE_MISSING);
        if (!$context) {
            return false; // No reliable storage context -> no remote deletion.
        }
        $files = get_file_storage()->get_area_files(
            $context->id,
            'mod_' . $module,
            'content',
            0,
            'filepath, filename',
            false
        );
        // Deletion of a file in a still-existing activity must follow a Moodle edit.
        // Without a newer source timestamp, absence could reflect storage trouble.
        $originalrecord = $DB->get_record($module, ['id' => (int)$cm->instance], '*', IGNORE_MISSING);
        if (
            !$originalrecord || empty($originalrecord->timemodified) || empty($map->timesynced) ||
                (int)$originalrecord->timemodified < (int)$map->timesynced
        ) {
            return false;
        }
        foreach ($files as $file) {
            if ($file->is_directory()) {
                continue;
            }
            // The importer sanitises reference names with clean_filename().
            $comparison = (string)($map->contentmode ?? '') === 'reference'
                ? clean_filename('cm' . $sourcecmid . '-' . $module . '-' . $file->get_filename() . '-info.html')
                : $file->get_filename();
            $expected = (string)($map->contentmode ?? '') === 'reference'
                ? $filename : $originalname;
            if ($comparison === $expected) {
                return false;
            }
        }
        return true; // The identified original file is absent from Moodle storage.
    }

    /**
     * Guard against mass deletion caused by broken imports or unexpected mappings.
     * Applies to entire remote-delete batch, not per record. Maximum 3 without review.
     */
    public static function automatic_batch_allowed(int $candidatecount, int $activecount): bool {
        return $candidatecount >= 0 && $candidatecount <= 3;
    }
}
