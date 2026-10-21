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
 * FredKI course import plugin file.
 *
 * @package    mod_fredkicourseimport
 * @copyright  2026 lernsteine.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// This file is part of Moodle - http://moodle.org/.
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Manual sync service class for FredKI course import.
 *
 * @package    mod_fredkicourseimport
 * @copyright  2026 lernsteine.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_fredkicourseimport\local;


/**
 * manual sync service for FredKI CourseImport.
 *
 * @package mod_fredkicourseimport
 */
class manual_sync_service {
    /**
     * Sync record for FredKI CourseImport.
     *
     * @param \stdClass $activity Parameter for this operation.
     * @param \stdClass $maprecord Parameter for this operation.
     * @return void Operation result.
     */
    public static function sync_record(\stdClass $activity, \stdClass $maprecord): void {
        global $DB;

        license_manager::require_valid_license();

        if ($maprecord->status !== 'pending') {
            return;
        }

        $fs = get_file_storage();
        $file = $fs->get_file_by_hash($maprecord->pathnamehash);

        if (!$file || $file->is_directory()) {
            $maprecord->status = 'error';
            $maprecord->lastresult = 'error';
            $maprecord->lasterror = get_string('filenotfound', 'mod_fredkicourseimport');
            $maprecord->timemodified = time();
            $DB->update_record('fredkicourseimport_map', $maprecord);
            return;
        }

        $tempdir = make_request_directory();
        $temppath = $tempdir . '/' . $file->get_filename();
        $file->copy_content_to($temppath);

        try {
            $client = token_manager::client_for_activity($activity);
            $manager = new sync_manager($client);
            $course = $DB->get_record('course', ['id' => $activity->course], 'id, fullname, category, lang', IGNORE_MISSING);
            $sourceurl = self::get_moodle_source_url($file, $activity);
            $metadata = self::get_source_metadata($file, $activity, $course, $sourceurl);
            $metadata['contentmode'] = ($maprecord->contentmode ?? 'reference') === 'reference'
                ? 'reference'
                : 'knowledge';
            $sourcecmid = (int)($maprecord->sourcecmid ?? 0);
            if ($sourcecmid > 0) {
                $metadata['fredki_tags'] = self::get_fredki_tags_for_cmid($sourcecmid);
            }
            $result = $manager->sync_new_or_changed_file(
                $activity->knowledgeid,
                $temppath,
                $file->get_filename(),
                !empty($maprecord->fredki_document_id) ? $maprecord->fredki_document_id : null,
                $sourceurl,
                $course ? format_string($course->fullname) : null,
                $metadata
            );

            $maprecord->fredki_document_id = $result['document_id'] ?? null;
            $maprecord->fredki_source_file_id = $result['source_file_id'] ?? null;
            $maprecord->status = 'synced';
            if (empty($maprecord->lastresult)) {
                $maprecord->lastresult = 'updated';
            }
            $maprecord->lasterror = null;
            $maprecord->timesynced = time();
            $maprecord->timemodified = time();
            $DB->update_record('fredkicourseimport_map', $maprecord);
        } catch (\Throwable $e) {
            $maprecord->status = 'error';
            $maprecord->lastresult = 'error';
            $errorcode = ($e instanceof \moodle_exception) ? (string)$e->errorcode : '';
            $maprecord->lasterror = $errorcode === 'documentextractfailed'
                ? get_string('documentextractfailed', 'mod_fredkicourseimport')
                : get_string('externalapierror', 'mod_fredkicourseimport');
            $maprecord->timemodified = time();
            $DB->update_record('fredkicourseimport_map', $maprecord);
        }

        @unlink($temppath);
    }


    /**
     * Build the Moodle metadata transferred with every FredKI entry.
     *
     * @param \stored_file $file Generated file.
     * @param \stdClass $activity Plugin activity.
     * @param \stdClass|null $course Course record.
     * @param string $sourceurl Moodle source URL.
     * @return array
     */
    protected static function get_source_metadata(
        \stored_file $file,
        \stdClass $activity,
        ?\stdClass $course,
        string $sourceurl
    ): array {
        global $DB;

        $metadata = [
            'title' => pathinfo($file->get_filename(), PATHINFO_FILENAME),
            'filename' => $file->get_filename(),
            'activitylink' => $sourceurl,
            'course' => $course ? format_string($course->fullname) : '',
            'courselink' => (new \moodle_url('/course/view.php', ['id' => $activity->course]))->out(false),
            'coursecategory' => $course ? self::get_course_category_path($course) : '',
            'section' => '',
            'sectionparents' => [],
            'sectionpath' => [],
            'type' => self::get_localized_string('documenttype_material', $course),
            'typeprefix' => self::get_localized_string('documenttype_material', $course),
        ];

        $sourcecmid = 0;
        if (
            preg_match('~^/course-files/cm(\d+)~', $file->get_filepath(), $matches) ||
                preg_match('~cm(\d+)~', $file->get_filename(), $matches)
        ) {
            $sourcecmid = (int)$matches[1];
        }
        $requestedglossaryentry = glossary_entry_helper::parse_export_filename(
            $file->get_filepath(),
            $file->get_filename()
        );
        if ($requestedglossaryentry !== null) {
            // A removed course module must not lead to exporting stale generated content.
            $sourcecmid = $requestedglossaryentry['cmid'];
            $verifiedcm = get_coursemodule_from_id(null, $sourcecmid, 0, false, IGNORE_MISSING);
            if (!$verifiedcm || $verifiedcm->modname !== 'glossary') {
                throw new \moodle_exception('invalidglossaryentry', 'mod_fredkicourseimport');
            }
        }

        if ($sourcecmid > 0) {
            $sourcecm = get_coursemodule_from_id(null, $sourcecmid, 0, false, IGNORE_MISSING);
            if ($sourcecm) {
                $activitytitle = format_string($sourcecm->name);
                $metadata['title'] = $activitytitle;
                $metadata['type'] = self::get_module_type_name($sourcecm->modname, $course);
                $metadata['typeprefix'] = self::get_module_type_prefix($sourcecm->modname, $course);

                // Every file from a Moodle folder must have its own FredKI document name.
                // Keep the folder activity name in the Basisinformationen, but use
                // "Verzeichnis: <Dateiname>" as the knowledge-base document title.
                if (
                    $sourcecm->modname === 'folder' &&
                        preg_match('~^cm\d+-folder-(.+)-info\.html$~u', $file->get_filename(), $foldermatches)
                ) {
                    $folderfilename = clean_filename($foldermatches[1]);
                    if ($folderfilename !== '') {
                        $metadata['documenttitle'] = self::get_module_type_prefix('folder', $course) . ': ' . $folderfilename;
                    }
                }
                $metadata['activitylink'] = $sourceurl;
                // A complete glossary exports one file per entry. Use the entry's
                // actual heading as BOTH the searchable paragraph title and the
                // FredKI document name, never the shared glossary activity title.
                $glossaryentry = $requestedglossaryentry;
                if ($glossaryentry !== null) {
                    if (
                        $sourcecm->modname !== 'glossary' ||
                            (int)$sourcecm->id !== $glossaryentry['cmid']
                    ) {
                        throw new \moodle_exception('invalidglossaryentry', 'mod_fredkicourseimport');
                    }
                    // Revalidate at upload time: an entry might have been deleted
                    // or unapproved between export and synchronisation.
                    $entry = $DB->get_record('glossary_entries', [
                        'id' => $glossaryentry['entryid'],
                        'glossaryid' => (int)$sourcecm->instance,
                        'approved' => 1,
                    ], 'id, concept', IGNORE_MISSING);
                    if (!$entry) {
                        throw new \moodle_exception('invalidglossaryentry', 'mod_fredkicourseimport');
                    }
                    $entrytitle = trim((string)$entry->concept);
                    if ($entrytitle === '') {
                        $entrytitle = self::get_localized_string('untitledglossaryentry', $course);
                    }
                    $metadata['title'] = $entrytitle;
                    $metadata['documenttitle'] = $entrytitle;
                    $metadata['activitylink'] = glossary_entry_helper::direct_url((int)$entry->id);
                }
                $modrecord = $DB->get_record($sourcecm->modname, ['id' => $sourcecm->instance], '*', IGNORE_MISSING);
                if ($modrecord && property_exists($modrecord, 'intro')) {
                    $description = trim(preg_replace('/\s+/u', ' ', html_to_text((string)$modrecord->intro, 0, false)) ?? '');
                    if ($description !== '') {
                        // Keep the full activity description for reference imports.
                        $metadata['summary'] = $description;
                    }
                }

                if ($course) {
                    $hierarchy = section_hierarchy_helper::for_cmid($course, $sourcecmid);
                    $metadata['section'] = trim((string)($hierarchy['current'] ?? ''));
                    $metadata['sectionparents'] = $hierarchy['parents'] ?? [];
                    $metadata['sectionpath'] = $hierarchy['path'] ?? [];
                    if ($metadata['section'] === '') {
                        $modinfo = get_fast_modinfo($course);
                        if (isset($modinfo->cms[$sourcecmid])) {
                            $sourcecm = $modinfo->cms[$sourcecmid];
                            $metadata['section'] = get_section_name($course, $sourcecm->sectionnum);
                        }
                    }
                }
            }
        } else if (
            $file->get_filepath() === '/section-texts/' &&
                preg_match('~abschnitt-(\d+)\.html$~', $file->get_filename(), $matches) && $course
        ) {
            $sectionnum = (int)$matches[1];
            $hierarchy = section_hierarchy_helper::for_section_number($course, $sectionnum);
            $metadata['title'] = trim((string)($hierarchy['current'] ?? ''));
            if ($metadata['title'] === '') {
                $metadata['title'] = get_section_name($course, $sectionnum);
            }
            $metadata['section'] = $metadata['title'];
            $metadata['sectionparents'] = $hierarchy['parents'] ?? [];
            $metadata['sectionpath'] = $hierarchy['path'] ?? [];
            $sectionprefix = self::get_localized_string('documenttype_section', $course);
            $metadata['type'] = $sectionprefix . ' (section)';
            $metadata['typeprefix'] = $sectionprefix;
        }

        return $metadata;
    }

    /**
     * Read the activity custom fields provided by local_modcustomfields and
     * convert all non-empty values into FredKI key/value tags.
     *
     * The custom-field shortname is used as stable tag key; the exported
     * human-readable value is used as tag value. If the helper plugin is not
     * installed, the course import continues without tags.
     *
     * @param int $cmid Source course-module id.
     * @return array<int,array{key:string,value:string,label:string}>
     */
    protected static function get_fredki_tags_for_cmid(int $cmid): array {
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
                // Use the same visibility rules as the companion webservice.
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
            debugging(
                'FredKI custom-field tags could not be read for cmid ' . $cmid . ': ' . $e->getMessage(),
                DEBUG_DEVELOPER
            );
            return [];
        }
    }

    /**
     * Get module type name for FredKI CourseImport.
     *
     * @param string $modname Parameter for this operation.
     * @param ?\stdClass $course Parameter for this operation.
     * @return string Operation result.
     */
    protected static function get_module_type_name(string $modname, ?\stdClass $course = null): string {
        $component = 'mod_' . $modname;
        $lang = self::get_course_language($course);
        if (get_string_manager()->string_exists('modulename', $component)) {
            return get_string_manager()->get_string('modulename', $component, null, $lang) . ' (' . $modname . ')';
        }
        return ucfirst($modname);
    }

    /**
     * Get module type prefix for FredKI CourseImport.
     *
     * @param string $modname Parameter for this operation.
     * @param ?\stdClass $course Parameter for this operation.
     * @return string Operation result.
     */
    protected static function get_module_type_prefix(string $modname, ?\stdClass $course = null): string {
        $map = [
            'resource' => 'documenttype_resource',
            'folder' => 'documenttype_folder',
            'url' => 'documenttype_url',
            'glossary' => 'documenttype_glossary',
            'page' => 'documenttype_page',
            'label' => 'documenttype_label',
            'book' => 'documenttype_book',
            'lesson' => 'documenttype_lesson',
            'h5pactivity' => 'documenttype_h5p',
            'scorm' => 'documenttype_scorm',
        ];
        if (isset($map[$modname])) {
            return self::get_localized_string($map[$modname], $course);
        }
        return ucfirst($modname);
    }

    /**
     * Get localized string for FredKI CourseImport.
     *
     * @param string $identifier Parameter for this operation.
     * @param ?\stdClass $course Parameter for this operation.
     * @return string Operation result.
     */
    protected static function get_localized_string(string $identifier, ?\stdClass $course = null): string {
        return get_string_manager()->get_string(
            $identifier,
            'mod_fredkicourseimport',
            null,
            self::get_course_language($course)
        );
    }

    /**
     * Get course language for FredKI CourseImport.
     *
     * @param ?\stdClass $course Parameter for this operation.
     * @return string Operation result.
     */
    protected static function get_course_language(?\stdClass $course): string {
        global $CFG;
        if ($course && !empty($course->lang)) {
            return clean_param($course->lang, PARAM_LANG);
        }
        return clean_param($CFG->lang ?? 'en', PARAM_LANG);
    }

    /**
     * Get course category path for FredKI CourseImport.
     *
     * @param \stdClass $course Parameter for this operation.
     * @return string Operation result.
     */
    protected static function get_course_category_path(\stdClass $course): string {
        global $DB;

        if (empty($course->category)) {
            return '';
        }
        $category = $DB->get_record('course_categories', ['id' => (int)$course->category], 'id, name, path', IGNORE_MISSING);
        if (!$category) {
            return '';
        }
        $ids = array_values(array_filter(array_map('intval', explode('/', trim((string)$category->path, '/')))));
        if (!$ids) {
            return format_string($category->name);
        }
        $categories = $DB->get_records_list('course_categories', 'id', $ids, '', 'id, name');
        $names = [];
        foreach ($ids as $id) {
            if (isset($categories[$id])) {
                $names[] = format_string($categories[$id]->name);
            }
        }
        return implode(' / ', $names);
    }

    /**
     * Get moodle source url for FredKI CourseImport.
     *
     * @param \stored_file $file Parameter for this operation.
     * @param \stdClass $activity Parameter for this operation.
     * @return string Operation result.
     */
    protected static function get_moodle_source_url(\stored_file $file, \stdClass $activity): string {
        $filepath = $file->get_filepath();
        $filename = $file->get_filename();

        // Files copied from Moodle activities keep the original cm id in their generated filepath.
        // Link back to the original Moodle file, not to this plugin's generated_files area.
        if (preg_match('~^/course-files/cm(\\d+)(/.*)$~', $filepath, $matches)) {
            $sourcecmid = (int)$matches[1];
            $sourcefilepath = $matches[2];
            $sourcecm = get_coursemodule_from_id(null, $sourcecmid, 0, false, IGNORE_MISSING);
            if (!$sourcecm) {
                return '';
            }

            $sourcecontext = \context_module::instance($sourcecm->id, IGNORE_MISSING);
            if (!$sourcecontext) {
                return '';
            }

            $url = \moodle_url::make_pluginfile_url(
                $sourcecontext->id,
                'mod_' . $sourcecm->modname,
                'content',
                0,
                $sourcefilepath,
                $filename,
                false
            );

            return $url->out(false);
        }

        // The full glossary mode exports an individual, stable document per entry.
        // This URL is also stored as the FredKI document's source link.
        $glossaryentry = glossary_entry_helper::parse_export_filename($filepath, $filename);
        if ($glossaryentry !== null) {
            return glossary_entry_helper::direct_url($glossaryentry['entryid']);
        }

        // Generated text files must never point back to this plugin's internal generated_files area.
        // Instead, point to the original Moodle activity or course section.
        if (preg_match('~cm(\\d+)~', $filename, $matches)) {
            $sourcecmid = (int)$matches[1];
            $sourcecm = get_coursemodule_from_id(null, $sourcecmid, 0, false, IGNORE_MISSING);
            if ($sourcecm) {
                $url = new \moodle_url('/mod/' . $sourcecm->modname . '/view.php', ['id' => $sourcecm->id]);
                return $url->out(false);
            }
        }

        if ($filepath === '/section-texts/' && preg_match('~abschnitt-(\\d+)\\.html$~', $filename, $matches)) {
            $sectionnum = (int)$matches[1];
            $url = new \moodle_url('/course/view.php', ['id' => $activity->course], 'section-' . $sectionnum);
            return $url->out(false);
        }

        // Last safe fallback for generated content: course page. This avoids invalid links to hidden
        // internal generated files while still giving the chatbot a real Moodle source.
        $url = new \moodle_url('/course/view.php', ['id' => $activity->course]);
        return $url->out(false);
    }

    /**
     * Delete record for FredKI CourseImport.
     *
     * @param \stdClass $activity Parameter for this operation.
     * @param \stdClass $maprecord Parameter for this operation.
     * @return void Operation result.
     */
    public static function delete_record(\stdClass $activity, \stdClass $maprecord): void {
        global $DB;

        // Removal of already exported data must not depend on a paid service licence.

        if ($maprecord->status !== 'deleted') {
            return;
        }
        if (empty($maprecord->fredki_document_id)) {
            $DB->delete_records('fredkicourseimport_map', ['id' => $maprecord->id]);
            return;
        }

        try {
            $client = token_manager::client_for_activity($activity);
            $client->delete_document($activity->knowledgeid, $maprecord->fredki_document_id);
            $maprecord->status = 'removed';
            $maprecord->lastresult = 'deleted';
            $maprecord->fredki_document_id = null;
            $maprecord->fredki_source_file_id = null;
            $maprecord->lasterror = null;
            $maprecord->timesynced = time();
            $maprecord->timemodified = time();
            $DB->update_record('fredkicourseimport_map', $maprecord);
        } catch (\Throwable $e) {
            // Keep a retryable deletion state and its remote ID after API errors.
            $maprecord->status = 'deleted';
            $maprecord->lastresult = 'error';
            $errorcode = ($e instanceof \moodle_exception) ? (string)$e->errorcode : '';
            $maprecord->lasterror = $errorcode === 'documentextractfailed'
                ? get_string('documentextractfailed', 'mod_fredkicourseimport')
                : get_string('externalapierror', 'mod_fredkicourseimport');
            $maprecord->timemodified = time();
            $DB->update_record('fredkicourseimport_map', $maprecord);
        }
    }
}
