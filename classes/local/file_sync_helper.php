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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

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
 * Handles Moodle resource file synchronization.
 *
 * @copyright 2026 lernsteine.com
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class file_sync_helper {
    public static function scan_generated_files(
        int $cmid,
        int $activityid,
        array $contentmodes = []
    ): array {
        global $DB;

        $context = \context_module::instance($cmid);
        $fs = get_file_storage();
        $files = $fs->get_area_files($context->id, 'mod_fredkicourseimport', 'generated_files', 0, 'filepath, filename', false);
        $currenthashes = [];
        $stats = [
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'deleted' => 0,
        ];

        foreach ($files as $file) {
            if ($file->is_directory()) {
                continue;
            }
            $pathnamehash = $file->get_pathnamehash();
            $currenthashes[] = $pathnamehash;
            $existing = $DB->get_record(
                'fredkicourseimport_map',
                ['activityid' => $activityid, 'pathnamehash' => $pathnamehash]
            );
            $sourcecmid = 0;
            if (
                preg_match('~^/course-files/cm(\d+)~', $file->get_filepath(), $matches) ||
                    preg_match('~cm(\d+)~', $file->get_filename(), $matches)
            ) {
                $sourcecmid = (int)$matches[1];
            }
            $contentmode = ($contentmodes[$sourcecmid] ?? 'reference') === 'reference'
                ? 'reference'
                : 'knowledge';
            $metadatahash = self::calculate_source_metadata_hash($sourcecmid);

            if (!$existing) {
                $record = (object)[
                    'cmid' => $cmid,
                    'activityid' => $activityid,
                    'sourcecmid' => $sourcecmid,
                    'pathnamehash' => $pathnamehash,
                    'filename' => $file->get_filename(),
                    'contenthash' => $file->get_contenthash(),
                    'metadatahash' => $metadatahash,
                    'filesize' => $file->get_filesize(),
                    'contentmode' => $contentmode,
                    'status' => 'pending',
                    'lastresult' => 'created',
                    'timecreated' => time(),
                    'timemodified' => time(),
                ];
                $DB->insert_record('fredkicourseimport_map', $record);
                $stats['created']++;
                continue;
            }

            // Compare both the generated/source file and Moodle-side metadata.
            // This makes the one-click refresh selective: changing a tag, title,
            // description or section only queues documents that belong to that source activity.
            $storedmetadatahash = property_exists($existing, 'metadatahash') ? (string)$existing->metadatahash : '';
            $metadatachanged = $storedmetadatahash !== '' && $storedmetadatahash !== $metadatahash;
            $changed = $existing->contenthash !== $file->get_contenthash() ||
                       $existing->filename !== $file->get_filename() ||
                       (int)$existing->filesize !== (int)$file->get_filesize() ||
                       ($existing->contentmode ?? 'reference') !== $contentmode ||
                       (int)($existing->sourcecmid ?? 0) !== $sourcecmid ||
                       $metadatachanged ||
                       $existing->status === 'error';

            if ($existing->status === 'removed' || $existing->status === 'deleted') {
                $existing->filename = $file->get_filename();
                $existing->contenthash = $file->get_contenthash();
                $existing->metadatahash = $metadatahash;
                $existing->filesize = $file->get_filesize();
                $existing->sourcecmid = $sourcecmid;
                $existing->contentmode = $contentmode;
                $existing->status = 'pending';
                $existing->lastresult = 'created';
                $existing->lasterror = null;
                $existing->timemodified = time();
                $DB->update_record('fredkicourseimport_map', $existing);
                $stats['created']++;
            } else if ($changed) {
                $existing->filename = $file->get_filename();
                $existing->contenthash = $file->get_contenthash();
                $existing->metadatahash = $metadatahash;
                $existing->filesize = $file->get_filesize();
                $existing->sourcecmid = $sourcecmid;
                $existing->contentmode = $contentmode;
                $existing->status = 'pending';
                $existing->lastresult = 'updated';
                $existing->lasterror = null;
                $existing->timemodified = time();
                $DB->update_record('fredkicourseimport_map', $existing);
                $stats['updated']++;
            } else {
                $needsupdate = false;
                // Legacy rows from versions before 1.5.6 have no metadata fingerprint yet.
                // Establish the baseline without forcing a needless FredKI re-upload.
                if ($storedmetadatahash === '') {
                    $existing->metadatahash = $metadatahash;
                    $needsupdate = true;
                }
                if (empty($existing->lastresult)) {
                    $existing->lastresult = 'skipped';
                    $needsupdate = true;
                }
                if ($needsupdate) {
                    $existing->timemodified = time();
                    $DB->update_record('fredkicourseimport_map', $existing);
                }
                $stats['skipped']++;
            }
        }

        $known = $DB->get_records('fredkicourseimport_map', ['activityid' => $activityid]);
        foreach ($known as $record) {
            if (!in_array($record->pathnamehash, $currenthashes, true) && !in_array($record->status, ['deleted', 'removed'], true)) {
                $record->status = 'deleted';
                $record->lastresult = 'deleted';
                $record->timemodified = time();
                $DB->update_record('fredkicourseimport_map', $record);
                $stats['deleted']++;
            }
        }

        return $stats;
    }
    /**
     * Fingerprint Moodle metadata that is not necessarily part of the source file.
     *
     * A tag/custom-field change must be detectable even for complete uploads where
     * the original PDF/file bytes are unchanged. The fingerprint intentionally
     * includes the activity metadata used by FredKI and all visible custom fields.
     * @param int $sourcecmid Moodle source course-module ID.
     */
    public static function calculate_source_metadata_hash(int $sourcecmid): string {
        global $DB;

        if ($sourcecmid <= 0) {
            return sha1('section-text-search-v2');
        }

        try {
            $cm = get_coursemodule_from_id(null, $sourcecmid, 0, false, IGNORE_MISSING);
            if (!$cm) {
                return sha1('missing:' . $sourcecmid);
            }

            $course = $DB->get_record('course', ['id' => $cm->course], 'id,fullname,category', IGNORE_MISSING);
            $modrecord = null;
            if ($DB->get_manager()->table_exists($cm->modname)) {
                $modrecord = $DB->get_record($cm->modname, ['id' => $cm->instance], '*', IGNORE_MISSING);
            }

            $sectionname = '';
            $sectionparents = [];
            $sectionpath = [];
            if ($course) {
                $hierarchy = section_hierarchy_helper::for_cmid($course, $sourcecmid);
                $sectionname = trim((string)($hierarchy['current'] ?? ''));
                $sectionparents = $hierarchy['parents'] ?? [];
                $sectionpath = $hierarchy['path'] ?? [];
                if ($sectionname === '') {
                    $modinfo = get_fast_modinfo($course);
                    if (isset($modinfo->cms[$sourcecmid])) {
                        $sectionname = get_section_name($course, $modinfo->cms[$sourcecmid]->sectionnum);
                    }
                }
            }

            $tags = self::read_custom_field_tags($sourcecmid);
            usort($tags, static function (array $a, array $b): int {
                return strcmp(($a['key'] ?? '') . "\0" . ($a['value'] ?? ''), ($b['key'] ?? '') . "\0" . ($b['value'] ?? ''));
            });

            $data = [
                // Bump this marker whenever the FredKI search-document structure changes.
                // Existing mappings then refresh once, while later refreshes stay selective.
                'importformat' => 'search-v5-no-linked-questions',
                'sourcecmid' => $sourcecmid,
                'name' => (string)($cm->name ?? ''),
                'modname' => (string)($cm->modname ?? ''),
                'intro' => $modrecord && property_exists($modrecord, 'intro') ? (string)$modrecord->intro : '',
                'section' => $sectionname,
                'sectionparents' => $sectionparents,
                'sectionpath' => $sectionpath,
                'course' => $course ? (string)$course->fullname : '',
                'category' => $course ? (int)$course->category : 0,
                'tags' => $tags,
            ];
            return sha1(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        } catch (\Throwable $e) {
            debugging('FredKI metadata fingerprint failed for cmid ' . $sourcecmid . ': ' . $e->getMessage(), DEBUG_DEVELOPER);
            return sha1('error:' . $sourcecmid);
        }
    }

    /**
     * Read visible custom fields in a stable, serialisable form.
     *
     * @param int $cmid cmid parameter.
     * @return array<int,array{key:string,value:string,label:string}>
     */
    private static function read_custom_field_tags(int $cmid): array {
        if ($cmid <= 0 || !class_exists('\\local_modcustomfields\\customfield\\mod_handler')) {
            return [];
        }
        try {
            $handler = \local_modcustomfields\customfield\mod_handler::create();
            $fieldcontrollers = $handler->get_fields();
            if (!$fieldcontrollers) {
                return [];
            }
            $datacontrollers = \core_customfield\api::get_instance_fields_data($fieldcontrollers, $cmid);
            $tags = [];
            foreach ($datacontrollers as $data) {
                $field = $data->get_field();
                if (method_exists($handler, 'can_view') && !$handler->can_view($field, $cmid)) {
                    continue;
                }
                $key = trim((string)$field->get('shortname'));
                if ($key === '') {
                    $key = trim((string)$field->get('name'));
                }
                if ($key === '') {
                    continue;
                }
                $datafield = $data->datafield();
                $raw = $data->get($datafield);
                if ($raw === null || $raw === '' || (is_array($raw) && !$raw)) {
                    continue;
                }
                $value = $raw;
                if (method_exists($data, 'export_value')) {
                    try {
                        $value = $data->export_value();
                    } catch (\Throwable $e) {
                        $value = $raw;
                    }
                }
                if (is_array($value)) {
                    $value = implode(', ', array_map('strval', $value));
                } else if (is_object($value)) {
                    $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                }
                $value = trim((string)$value);
                if ($value === '') {
                    continue;
                }
                $label = trim((string)$field->get('name'));
                if ($label === '') {
                    $label = $key;
                }
                $tags[] = ['key' => $key, 'value' => $value, 'label' => $label];
            }
            return $tags;
        } catch (\Throwable $e) {
            debugging('FredKI custom-field fingerprint read failed for cmid ' . $cmid . ': ' . $e->getMessage(), DEBUG_DEVELOPER);
            return [];
        }
    }
}
