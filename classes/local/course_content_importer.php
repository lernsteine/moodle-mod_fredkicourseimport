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
 * classes/local/course_content_importer.php for the FredKI CourseImport activity.
 *
 * @package    mod_fredkicourseimport
 * @copyright  2026 lernsteine.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_fredkicourseimport\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Creates FredKI documents with metadata first and optional activity content.
 */
class course_content_importer {
    /** @var array<string,array{filepath:string,filename:string,content:string}> */
    protected $textfiles = [];

    /** Activity types whose actual learning content is transferred for selected activities. */
    private const CONTENT_CAPABLE = [
        'resource',
        'folder',
        'url',
        'glossary',
        'page',
        'label',
        'book',
        'lesson',
        'h5pactivity',
        'scorm',
    ];

    public function import_course_content(
        \stdClass $course,
        \stdClass $cm,
        \stdClass $activity,
        ?array $selectedcmids = null,
        bool $includesectiontexts = true,
        ?array $contentmodes = null
    ): array {
        $context = \context_module::instance($cm->id);
        $fs = get_file_storage();
        $this->clear_generated_area($context->id);
        $this->textfiles = [];

        $counters = [
            'files' => 0, 'sectiontexts' => 0, 'activitytexts' => 0, 'urls' => 0,
            'glossaries' => 0, 'pages' => 0, 'labels' => 0, 'books' => 0,
            'lessons' => 0, 'h5p' => 0, 'scorm' => 0,
        ];

        $selectedmap = $selectedcmids === null ? null : array_flip(array_map('intval', $selectedcmids));
        $contentmodes = $contentmodes ?? [];
        $modinfo = get_fast_modinfo($course);

        foreach ($modinfo->cms as $sourcecm) {
            if ($sourcecm->deletioninprogress) {
                continue;
            }
            if ($selectedmap !== null && !isset($selectedmap[(int)$sourcecm->id])) {
                continue;
            }
            // A teacher can explicitly select hidden Moodle materials for the shared bot.
            // Never expand an unrestricted or implicit import this way: it requires an
            // actual selected source and course-management permission.
            if (!$sourcecm->uservisible && ($selectedmap === null ||
                    !has_capability('moodle/course:manageactivities',
                        \context_course::instance((int)$course->id)))) {
                continue;
            }

            $record = $this->get_instance_record($sourcecm);
            $summary = $this->get_short_summary($record);
            $mode = $contentmodes[(int)$sourcecm->id] ?? 'reference';
            $includecontent = in_array($sourcecm->modname, self::CONTENT_CAPABLE, true)
                && $mode !== 'reference';

            switch ($sourcecm->modname) {
                case 'resource':
                case 'folder':
                    $counters['files'] += $this->add_file_exports($context->id, $course, $sourcecm, $summary, $includecontent);
                    break;
                case 'glossary':
                    $counters['glossaries'] += $includecontent
                        ? $this->add_glossary_exports($course, $sourcecm, $record, $summary)
                        : $this->add_single_metadata_export($course, $sourcecm, $summary, 'glossaries');
                    break;
                case 'page':
                    $this->add_single_content_export($course, $sourcecm, $record, $summary, $includecontent ? (string)($record->content ?? '') : '', $record->contentformat ?? FORMAT_HTML, 'pages');
                    $counters['pages']++;
                    break;
                case 'label':
                    if ($includecontent) {
                        $labelrawcontent = (string)($sourcecm->content ?? ($record->intro ?? ''));
                        $labeldescription = $this->content_to_plain_text(
                            $labelrawcontent,
                            FORMAT_HTML,
                            $sourcecm
                        );
                        $labeltitle = $this->get_label_export_title($sourcecm, $labeldescription);
                        $this->add_single_content_export(
                            $course,
                            $sourcecm,
                            $record,
                            '',
                            $labelrawcontent,
                            FORMAT_HTML,
                            'labels',
                            $labeltitle
                        );
                    } else {
                        $this->add_single_metadata_export(
                            $course,
                            $sourcecm,
                            $summary,
                            'labels'
                        );
                    }
                    $counters['labels']++;
                    break;
                case 'book':
                    $counters['books'] += $includecontent
                        ? $this->add_book_exports($course, $sourcecm, $record, $summary)
                        : $this->add_single_metadata_export($course, $sourcecm, $summary, 'books');
                    break;
                case 'lesson':
                    $counters['lessons'] += $includecontent
                        ? $this->add_lesson_exports($course, $sourcecm, $record, $summary)
                        : $this->add_single_metadata_export($course, $sourcecm, $summary, 'lessons');
                    break;
                case 'url':
                    $this->add_single_content_export(
                        $course,
                        $sourcecm,
                        $record,
                        $summary,
                        $includecontent ? (string)($record->intro ?? '') : '',
                        (int)($record->introformat ?? FORMAT_HTML),
                        'urls'
                    );
                    $counters['urls']++;
                    break;
                case 'h5pactivity':
                    $this->add_single_content_export(
                        $course,
                        $sourcecm,
                        $record,
                        $summary,
                        $includecontent ? (string)($record->intro ?? '') : '',
                        (int)($record->introformat ?? FORMAT_HTML),
                        'h5p'
                    );
                    $counters['h5p']++;
                    break;
                case 'scorm':
                    $this->add_single_content_export(
                        $course,
                        $sourcecm,
                        $record,
                        $summary,
                        $includecontent ? (string)($record->intro ?? '') : '',
                        (int)($record->introformat ?? FORMAT_HTML),
                        'scorm'
                    );
                    $counters['scorm']++;
                    break;
                default:
                    $this->add_single_metadata_export($course, $sourcecm, $summary, 'activity-information');
                    $this->increment_counter($counters, $sourcecm->modname);
                    break;
            }
        }

        if ($includesectiontexts) {
            $counters['sectiontexts'] += $this->add_section_exports($course);
        }

        foreach ($this->textfiles as $filedef) {
            $fs->create_file_from_string([
                'contextid' => $context->id,
                'component' => 'mod_fredkicourseimport',
                'filearea' => 'generated_files',
                'itemid' => 0,
                'filepath' => $filedef['filepath'],
                'filename' => $filedef['filename'],
            ], $filedef['content']);
        }

        $syncstats = file_sync_helper::scan_generated_files(
            $cm->id,
            $activity->id,
            $contentmodes
        );
        return array_merge($counters, $syncstats);
    }

    protected function add_section_exports(\stdClass $course): int {
        $modinfo = get_fast_modinfo($course);
        $count = 0;
        foreach ($modinfo->get_section_info_all() as $section) {
            // Never export sections hidden from participants, even when selected by a manager.
            if (empty($section->visible) || empty($section->uservisible)) {
                continue;
            }
            $rawsummary = trim((string)($section->summary ?? ''));
            if ($rawsummary === '') { continue; }
            $sectiondescription = $this->section_summary_to_plain_text(
                $course,
                $rawsummary,
                (int)($section->summaryformat ?? FORMAT_HTML)
            );
            $hierarchy = section_hierarchy_helper::for_section_number($course, (int)$section->section, $modinfo);
            $sectionname = trim((string)($hierarchy['current'] ?? ''));
            if ($sectionname === '') {
                $sectionname = get_section_name($course, (int)$section->section);
            }
            $sectionparents = section_hierarchy_helper::format_parents($hierarchy['parents'] ?? []);
            $url = (new \moodle_url('/course/view.php', ['id' => $course->id], 'section-' . (int)$section->section))->out(false);
            $html = '<!doctype html><html><head><meta charset="utf-8"><title>' . s($sectionname) . '</title></head><body>';
            $html .= '<h2>' . s($sectionname) . '</h2>';
            if ($sectiondescription !== '') {
                $html .= '<p><strong>Beschreibung:</strong> ' . s($sectiondescription) . '</p>';
            }
            if ($sectionparents !== '') {
                $parentlabel = count($hierarchy['parents'] ?? []) > 1
                    ? 'Übergeordnete Abschnitte'
                    : 'Übergeordneter Abschnitt';
                $html .= '<p><strong>' . s($parentlabel) . ':</strong> ' . s($sectionparents) . '</p>';
            }
            $html .= '<p><strong>Kurs:</strong> ' . s(format_string($course->fullname)) . '</p>';
            $html .= $this->render_html_link_row('Kurslink', (new \moodle_url('/course/view.php', ['id' => $course->id]))->out(false));
            $html .= '<p><strong>Typ:</strong> Kursabschnitt (section)</p>';
            $html .= $this->render_html_link_row('Aktivitätslink', $url);
            $html .= '</body></html>';
            $this->add_text_file('/section-texts/', 'abschnitt-' . (int)$section->section . '.html', $html);
            $count++;
        }
        return $count;
    }

    protected function clear_generated_area(int $contextid): void {
        get_file_storage()->delete_area_files($contextid, 'mod_fredkicourseimport', 'generated_files', 0);
    }

    protected function add_file_exports(int $targetcontextid, \stdClass $course, \cm_info $sourcecm, string $summary, bool $includecontent): int {
        $sourcecontext = \context_module::instance($sourcecm->id);
        $component = 'mod_' . $sourcecm->modname;
        $files = get_file_storage()->get_area_files($sourcecontext->id, $component, 'content', 0, 'filepath, filename', false);
        $count = 0;

        foreach ($files as $file) {
            if ($file->is_directory()) {
                continue;
            }
            $sourceurl = \moodle_url::make_pluginfile_url(
                $sourcecontext->id, $component, 'content', 0,
                $file->get_filepath(), $file->get_filename(), false
            )->out(false);

            if (!$includecontent) {
                $document = $this->render_document($course, $sourcecm, [
                    'filename' => $file->get_filename(),
                    'summary' => $summary,
                    'activityurl' => $sourceurl,
                    'sourceurl' => $sourceurl,
                ]);
                $this->add_text_file('/activity-information/', $this->build_safe_filename(
                    'cm' . $sourcecm->id . '-' . $sourcecm->modname . '-' . $file->get_filename() . '-info.html'
                ), $document);
            }

            if ($includecontent) {
                $filerecord = [
                    'contextid' => $targetcontextid,
                    'component' => 'mod_fredkicourseimport',
                    'filearea' => 'generated_files',
                    'itemid' => 0,
                    'filepath' => '/course-files/cm' . $sourcecm->id . $file->get_filepath(),
                    'filename' => $file->get_filename(),
                ];
                get_file_storage()->create_file_from_storedfile($filerecord, $file);
            }
            $count++;
        }

        // Empty folders still receive one navigational metadata document.
        if ($count === 0) {
            $this->add_single_metadata_export($course, $sourcecm, $summary, 'activity-information');
            return 1;
        }
        return $count;
    }

    protected function add_single_metadata_export(\stdClass $course, \cm_info $sourcecm, string $summary, string $folder): int {
        $url = $this->get_activity_url($course, $sourcecm);
        $document = $this->render_document($course, $sourcecm, [
            'summary' => $summary,
            'activityurl' => $url,
            'sourceurl' => $url,
        ]);
        $title = trim((string)$sourcecm->name) ?: $sourcecm->modname;
        $this->add_text_file('/' . trim($folder, '/') . '/', $this->build_safe_filename(
            'cm' . $sourcecm->id . '-' . $sourcecm->modname . '-' . $title . '.html'
        ), $document);
        return 1;
    }

    protected function add_single_content_export(\stdClass $course, \cm_info $sourcecm, ?\stdClass $record, string $summary, string $rawcontent, int $format, string $folder, ?string $exporttitle = null): void {
        $url = $this->get_activity_url($course, $sourcecm);
        $content = $this->content_to_plain_text($rawcontent, $format, $sourcecm);
        $document = $this->render_document($course, $sourcecm, [
            'title' => $exporttitle ?? format_string($sourcecm->name ?: $sourcecm->modname),
            'summary' => $summary,
            'activityurl' => $url,
            'sourceurl' => $url,
            'content' => $content,
        ]);
        unset($record);
        $title = trim((string)($exporttitle ?? $sourcecm->name)) ?: $sourcecm->modname;
        $this->add_text_file('/' . trim($folder, '/') . '/', $this->build_safe_filename(
            'cm' . $sourcecm->id . '-' . $sourcecm->modname . '-' . $title . '.html'
        ), $document);
    }

    protected function add_glossary_exports(\stdClass $course, \cm_info $sourcecm, ?\stdClass $glossary, string $summary): int {
        global $DB;
        if (!$glossary) {
            return $this->add_single_metadata_export($course, $sourcecm, $summary, 'glossaries');
        }
        // Each approved glossary entry gets its own FredKI document. An aggregated
        // glossary has only one source URL and can never link to the matching entry.
        // The ID-based filename stays stable when a teacher renames the concept.
        $entries = $DB->get_records(
            'glossary_entries',
            ['glossaryid' => $glossary->id, 'approved' => 1],
            'concept ASC, id ASC'
        );
        if (!$entries) {
            return $this->add_single_metadata_export($course, $sourcecm, $summary, 'glossaries');
        }
        $glossaryurl = $this->get_activity_url($course, $sourcecm);
        foreach ($entries as $entry) {
            $title = trim((string)$entry->concept) ?: get_string('untitledglossaryentry', 'mod_fredkicourseimport');
            $definition = $this->content_to_plain_text(
                (string)$entry->definition,
                (int)$entry->definitionformat,
                $sourcecm
            );
            $entryurl = glossary_entry_helper::direct_url((int)$entry->id);
            $document = $this->render_document($course, $sourcecm, [
                'title' => $title,
                'summary' => $summary,
                'activityurl' => $entryurl,
                'glossaryurl' => $glossaryurl,
                'content' => $definition,
            ]);
            $this->add_text_file(
                '/glossaries/',
                glossary_entry_helper::export_filename((int)$sourcecm->id, (int)$entry->id),
                $document
            );
        }
        return count($entries);
    }

    protected function add_book_exports(\stdClass $course, \cm_info $sourcecm, ?\stdClass $book, string $summary): int {
        global $DB;
        if (!$book || !$DB->get_manager()->table_exists('book_chapters')) {
            return $this->add_single_metadata_export($course, $sourcecm, $summary, 'books');
        }
        $chapters = $DB->get_records('book_chapters', ['bookid' => $book->id, 'hidden' => 0], 'pagenum ASC');
        $contentparts = [];
        foreach ($chapters as $chapter) {
            $chaptercontent = $this->content_to_plain_text((string)$chapter->content, (int)($chapter->contentformat ?? FORMAT_HTML), $sourcecm);
            if ($chaptercontent !== '') {
                $title = trim((string)$chapter->title) ?: ('Kapitel ' . $chapter->id);
                $contentparts[] = '## ' . $title . "\n\n" . $chaptercontent;
            }
        }
        $url = $this->get_activity_url($course, $sourcecm);
        $document = $this->render_document($course, $sourcecm, [
            'summary' => $summary, 'activityurl' => $url, 'sourceurl' => $url,
            'content' => implode("\n\n", $contentparts),
        ]);
        $title = trim((string)$sourcecm->name) ?: 'Buch';
        $this->add_text_file('/books/', $this->build_safe_filename('cm' . $sourcecm->id . '-book-' . $title . '.html'), $document);
        return 1;
    }

    protected function add_lesson_exports(\stdClass $course, \cm_info $sourcecm, ?\stdClass $lesson, string $summary): int {
        global $DB;
        if (!$lesson || !$DB->get_manager()->table_exists('lesson_pages')) {
            return $this->add_single_metadata_export($course, $sourcecm, $summary, 'lessons');
        }
        $pages = $DB->get_records('lesson_pages', ['lessonid' => $lesson->id], 'prevpageid ASC, id ASC');
        $contentparts = [];
        foreach ($pages as $page) {
            $pagecontent = $this->content_to_plain_text((string)($page->contents ?? ''), FORMAT_HTML, $sourcecm);
            if ($pagecontent !== '') {
                $title = trim((string)($page->title ?? '')) ?: ('Seite ' . $page->id);
                $contentparts[] = '## ' . $title . "\n\n" . $pagecontent;
            }
        }
        $url = $this->get_activity_url($course, $sourcecm);
        $document = $this->render_document($course, $sourcecm, [
            'summary' => $summary, 'activityurl' => $url, 'sourceurl' => $url,
            'content' => implode("\n\n", $contentparts),
        ]);
        $title = trim((string)$sourcecm->name) ?: 'Lektion';
        $this->add_text_file('/lessons/', $this->build_safe_filename('cm' . $sourcecm->id . '-lesson-' . $title . '.html'), $document);
        return 1;
    }

    /** Metadata is always rendered before optional learning content.      * @param \stdClass $course Parameter.
     * @param \cm_info $sourcecm Parameter.
     *
     * @param array $data Parameter.
     */
    protected function render_document(\stdClass $course, \cm_info $sourcecm, array $data): string {
        $title = trim((string)($data['title'] ?? format_string($sourcecm->name ?: $sourcecm->modname)));
        $filename = trim((string)($data['filename'] ?? ''));
        $summary = trim((string)($data['summary'] ?? ''));
        $activityurl = trim((string)($data['activityurl'] ?? $this->get_activity_url($course, $sourcecm)));
        $content = trim((string)($data['content'] ?? ''));
        $courselink = (new \moodle_url('/course/view.php', ['id' => $course->id]))->out(false);
        $hierarchy = section_hierarchy_helper::for_cmid($course, (int)$sourcecm->id);
        $sectionname = trim((string)($hierarchy['current'] ?? ''));
        $sectionparents = section_hierarchy_helper::format_parents($hierarchy['parents'] ?? []);
        if ($sectionname === '') {
            $sectionname = format_string($sourcecm->get_section_info()->name ?? '');
        }
        if ($sectionname === '') {
            $sectionname = get_section_name($course, $sourcecm->sectionnum);
        }

        // Keep the generated source compact and search-oriented. The real activity
        // title becomes the section heading; Moodle custom fields are injected later
        // by sync_manager immediately below this heading.
        $rows = [];
        if ($summary !== '') {
            $rows[] = '<p><strong>Beschreibung:</strong> ' . s($summary) . '</p>';
        }
        if ($sectionparents !== '') {
            $parentlabel = count($hierarchy['parents'] ?? []) > 1
                ? 'Übergeordnete Abschnitte'
                : 'Übergeordneter Abschnitt';
            $rows[] = '<p><strong>' . s($parentlabel) . ':</strong> ' . s($sectionparents) . '</p>';
        }
        if ($sectionname !== '') {
            $sectionlabel = $sectionparents !== '' ? 'Unterabschnitt' : 'Abschnitt';
            $rows[] = '<p><strong>' . s($sectionlabel) . ':</strong> ' . s($sectionname) . '</p>';
        }
        if ($filename !== '') {
            $rows[] = '<p><strong>Dateiname:</strong> ' . s($filename) . '</p>';
        }
        if (!empty($data['glossaryurl'])) {
            $rows[] = '<p><strong>Glossar:</strong> ' . s(format_string($sourcecm->name)) . '</p>';
            // The URL text itself is visible: FredKI's HTML-to-text pipeline
            // intentionally strips <a> tags while preserving their visible text.
            $rows[] = $this->render_html_link_row('Direktlink zum Glossareintrag', $activityurl);
            $rows[] = $this->render_html_link_row('Glossarübersicht', (string)$data['glossaryurl']);
        } else {
            $rows[] = $this->render_html_link_row('Aktivitätslink', $activityurl);
        }
        $rows[] = '<p><strong>Kurs:</strong> ' . s(format_string($course->fullname)) . '</p>';
        $rows[] = $this->render_html_link_row('Kurslink', $courselink);
        $rows[] = '<p><strong>Typ:</strong> ' . s($this->get_readable_activity_type($sourcecm->modname)) . '</p>';

        $html = '<!doctype html><html><head><meta charset="utf-8"><title>' . s($title) . '</title></head><body>';
        $html .= '<h2>' . s($title) . '</h2>' . implode("\n", array_filter($rows));
        if ($content !== '') {
            $html .= '<h2>Inhalt</h2><div>' . nl2br(s($content), false) . '</div>';
        }
        $html .= '</body></html>';
        return $html;
    }

    /** Returns a readable Moodle activity type while preserving the technical module name.      * @param string $modname Parameter.
     */
    protected function get_readable_activity_type(string $modname): string {
        $labels = [
            'resource' => 'Datei (resource)',
            'folder' => 'Ordner (folder)',
            'page' => 'Seite (page)',
            'label' => 'Textfeld / Text- und Medienbereich (label)',
            'glossary' => 'Glossar (glossary)',
            'book' => 'Buch (book)',
            'lesson' => 'Lektion (lesson)',
            'scorm' => 'SCORM (scorm)',
            'h5pactivity' => 'H5P (h5pactivity)',
            'url' => 'URL (url)',
        ];
        return $labels[$modname] ?? ucfirst($modname) . ' (' . $modname . ')';
    }

    protected function render_html_link_row(string $label, string $url): string {
        $url = trim($url);
        if ($url === '') { return ''; }
        return '<p><strong>' . s($label) . ':</strong> <a href="' . s($url) . '">' . s($url) . '</a></p>';
    }

    protected function content_to_plain_text(string $content, int $format, \cm_info $sourcecm): string {
        if (trim($content) === '') {
            return '';
        }
        $formatted = format_text($content, $format, ['context' => \context_module::instance($sourcecm->id)]);
        $plain = html_to_text($formatted, 0, false);
        $plain = preg_replace("/[ \t]+/u", ' ', (string)$plain);
        $plain = preg_replace("/\n{3,}/u", "\n\n", (string)$plain);
        return trim((string)$plain);
    }

    /**
     * Determine a useful title for a Moodle text field. Moodle may not have a
     * separately maintained title, so the first four content words are used as
     * a stable fallback.
     *
     * @param \cm_info $sourcecm Parameter.
     * @param string $plaincontent Parameter.
     */
    protected function get_label_export_title(\cm_info $sourcecm, string $plaincontent): string {
        $title = trim(format_string((string)($sourcecm->name ?? '')));
        if ($title !== '') {
            return $title;
        }

        $words = preg_split('/\s+/u', trim($plaincontent), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $fallback = implode(' ', array_slice($words, 0, 4));
        return $fallback !== '' ? $fallback : 'Textfeld';
    }

    /** Convert a course section description to clean plain text.      * @param \stdClass $course Parameter.
     * @param string $summary Parameter.
     *
     * @param int $format Parameter.
     */
    protected function section_summary_to_plain_text(\stdClass $course, string $summary, int $format): string {
        if (trim($summary) === '') {
            return '';
        }
        $formatted = format_text($summary, $format, [
            'context' => \context_course::instance($course->id),
            'overflowdiv' => false,
        ]);
        $plain = html_to_text($formatted, 0, false);
        $plain = preg_replace('/[ \t]+/u', ' ', (string)$plain);
        $plain = preg_replace('/\n{3,}/u', "\n\n", (string)$plain);
        return trim((string)$plain);
    }

    protected function get_instance_record(\cm_info $sourcecm): ?\stdClass {
        global $DB;
        $table = $sourcecm->modname;
        if (!$DB->get_manager()->table_exists($table)) {
            return null;
        }
        return $DB->get_record($table, ['id' => $sourcecm->instance], '*', IGNORE_MISSING) ?: null;
    }

    protected function get_short_summary(?\stdClass $record): string {
        if (!$record || !property_exists($record, 'intro')) {
            return '';
        }
        $text = html_to_text((string)$record->intro, 0, false);
        $text = preg_replace('/\s+/u', ' ', trim((string)$text));
        if ($text === '') {
            return '';
        }
        // Keep the complete Moodle activity description. In reference mode this
        // description is the central content transferred to FredKI and must not
        // be shortened.
        return $text;
    }

    protected function get_activity_url(\stdClass $course, \cm_info $sourcecm): string {
        if (!empty($sourcecm->url)) {
            return $sourcecm->url->out(false);
        }
        return (new \moodle_url('/course/view.php', ['id' => $course->id], 'module-' . $sourcecm->id))->out(false);
    }

    protected function increment_counter(array &$counters, string $modname): void {
        $map = ['url' => 'urls', 'glossary' => 'glossaries', 'page' => 'pages', 'label' => 'labels',
            'book' => 'books', 'lesson' => 'lessons', 'h5pactivity' => 'h5p', 'scorm' => 'scorm'];
        if (isset($map[$modname])) {
            $counters[$map[$modname]]++;
        } else {
            $counters['activitytexts']++;
        }
    }

    protected function add_text_file(string $filepath, string $filename, string $content): void {
        $this->textfiles[$filepath . $filename] = compact('filepath', 'filename', 'content');
    }

    protected function get_course_category_path(\stdClass $course): string {
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

    protected function build_safe_filename(string $name): string {
        $name = clean_filename($name);
        return $name !== '' ? $name : ('export-' . time() . '.txt');
    }
}
