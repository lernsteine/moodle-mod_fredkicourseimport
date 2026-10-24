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

namespace mod_fredkicourseimport\local;

/**
 * Sync manager class.
 *
 * @package    mod_fredkicourseimport
 * @copyright  2026 lernsteine.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class sync_manager {
    /** @var fredki_client FredKI API client. */
    protected $client;

    /**
     * Constructor.
     *
     * @param fredki_client $client The client.
     */
    public function __construct(fredki_client $client) {
        $this->client = $client;
    }

    /**
     * Sync new or changed file.
     *
     * @param string $knowledgeid The knowledge base id.
     * @param string $filepath The filepath.
     * @param string $filename The file name.
     * @param ?string $olddocumentid The olddocumentid.
     * @param ?string $sourceurl The sourceurl.
     * @param ?string $coursename The coursename.
     * @param array $metadata The metadata.
     * @param ?string $sourcefilepath The source file path.
     * @return array The result.
     */
    public function sync_new_or_changed_file(
        string $knowledgeid,
        string $filepath,
        string $filename,
        ?string $olddocumentid = null,
        ?string $sourceurl = null,
        ?string $coursename = null,
        array $metadata = [],
        ?string $sourcefilepath = null
    ): array {
        $contentmode = ($metadata['contentmode'] ?? 'reference') === 'reference'
            ? 'reference'
            : 'knowledge';

        // Reference entries deliberately do not send the original binary document to FredKI.
        // Complete entries use FredKI's extraction endpoint and keep the extracted content.
        if ($contentmode === 'reference') {
            $splititem = ['content' => [], 'source_file_id' => null];
        } else {
            try {
                $splititem = $this->client->split_upload($knowledgeid, $filepath, $filename);
            } catch (\moodle_exception $e) {
                // FredKI's extractor rejects some otherwise valid, text-based PDFs.
                // In that case, extract the text locally and continue through the same
                // structured TXT pipeline. No silent metadata-only fallback is used.
                if (
                    (string)$e->errorcode !== 'documentextractfailed' ||
                        strtolower(pathinfo($filename, PATHINFO_EXTENSION)) !== 'pdf'
                ) {
                    throw $e;
                }
                $localtext = $this->extract_pdf_text_locally($filepath);
                if ($localtext === '') {
                    throw $e;
                }
                $splititem = [
                    'content' => [$localtext],
                    'source_file_id' => null,
                ];
            }
        }

        // Every Moodle activity and material is normalised to the same FredKI
        // information structure. This keeps the knowledge base consistent
        // regardless of the original Moodle module or file format.
        $entry = $this->build_document_entry(
            $splititem['content'] ?? [],
            $filename,
            $sourceurl,
            $coursename,
            $metadata,
            $filepath
        );
        $documentname = $entry['title'];

        // Large PDFs must not be converted into one oversized FredKI paragraph. Apart from
        // producing poor retrieval results, a single payload can exceed the split endpoint's
        // practical request limits. Only the complete PDF path uses this chunked pipeline;
        // reference mode and all other activity types retain their existing behaviour.
        if ($contentmode === 'knowledge' && strtolower(pathinfo($filename, PATHINFO_EXTENSION)) === 'pdf') {
            [$paragraphs, $sourcefileid] = $this->upload_structured_pdf_chunks(
                $knowledgeid,
                $entry['content'],
                $entry['title']
            );
        } else {
            $structuredpath = $this->create_temp_structured_file($entry['content'], $entry['title']);
            try {
                $structuredsplit = $this->client->split_upload(
                    $knowledgeid,
                    $structuredpath,
                    $this->clean_filename_for_upload($entry['title']) . '.txt'
                );
                $paragraphs = $this->force_single_text_paragraph(
                    $structuredsplit['content'] ?? [],
                    $entry['content']
                );
                $sourcefileid = $structuredsplit['source_file_id'] ?? '';
            } finally {
                @unlink($structuredpath);
            }
        }

        // Make every MaxKB paragraph distinct and searchable with the real activity title.
        // Deliberately do NOT create paragraph.problem_list entries: linked questions add a
        // separate search signal and can distort retrieval when generated automatically at scale.
        $paragraphs = $this->prepare_searchable_paragraphs(
            $paragraphs,
            $entry['paragraph_title'] ?? $documentname
        );

        $payload = [[
            'name' => $documentname,
            'paragraphs' => $paragraphs,
            'source_file_id' => $sourcefileid,
        ]];
        $create = $this->client->batch_create($knowledgeid, $payload);
        $document = $create['data'][0] ?? null;
        if (!$document || empty($document['id'])) {
            throw new \moodle_exception('externalapiinvalidresponse', 'mod_fredkicourseimport');
        }

        // Transfer Moodle activity custom fields as real FredKI tags. Do this before
        // deleting an older document so a tag/permission/API failure never destroys
        // the last known-good document.
        $fredkitags = $metadata['fredki_tags'] ?? [];
        if (is_array($fredkitags) && $fredkitags) {
            try {
                $tagids = $this->client->ensure_tags($knowledgeid, $fredkitags);
                if (!$tagids) {
                    throw new \moodle_exception('externalapiinvalidresponse', 'mod_fredkicourseimport');
                }
                $this->client->add_document_tags($knowledgeid, (string)$document['id'], $tagids);
            } catch (\Throwable $tagfailure) {
                // Avoid leaving an untagged duplicate if tag synchronisation fails.
                try {
                    $this->client->delete_document($knowledgeid, (string)$document['id']);
                } catch (\Throwable $cleanupfailure) {
                    debugging(
                        'FredKI failed to clean up a newly created untagged document: ' . $cleanupfailure->getMessage(),
                        DEBUG_DEVELOPER
                    );
                }
                debugging(
                    'FredKI tag synchronisation failed: ' . $tagfailure->getMessage(),
                    DEBUG_DEVELOPER
                );
                throw new \moodle_exception('externalapierror', 'mod_fredkicourseimport');
            }
        }

        // Delete the old document only after the replacement was created successfully.
        // Cleanup is deliberately best-effort: the new document is already complete at this
        // point, so a slow or non-conforming DELETE response must not keep Moodle's form
        // request in the Processing state or turn a successful transfer into an error.
        if (!empty($olddocumentid)) {
            try {
                $this->client->delete_document($knowledgeid, $olddocumentid);
            } catch (\Throwable $cleanupfailure) {
                debugging(
                    'FredKI old-document cleanup could not be confirmed: ' . $cleanupfailure->getMessage(),
                    DEBUG_DEVELOPER
                );
            }
        }

        return [
            'document_id' => $document['id'],
            'source_file_id' => $splititem['source_file_id'] ?? null,
            'name' => $documentname,
        ];
    }

    /**
     * Extract text from a PDF with a locally installed, non-interactive PDF utility.
     *
     * FredKI occasionally rejects valid PDFs at its split endpoint. Moodle servers
     * commonly provide Poppler's pdftotext; mutool is supported as a second option.
     * Commands are invoked only with escaped temporary paths and without a shell-built
     * filename originating from the user.
     *
     * @param string $filepath Absolute path to the temporary PDF.
     * @return string Extracted UTF-8 text, or an empty string when no safe converter exists.
     */
    protected function extract_pdf_text_locally(string $filepath): string {
        if (!function_exists('proc_open') || !is_readable($filepath)) {
            return '';
        }

        $outputpath = make_request_directory() . '/fredki-pdf-' . bin2hex(random_bytes(8)) . '.txt';
        $commands = [
            ['pdftotext', '-enc', 'UTF-8', '-layout', $filepath, $outputpath],
            ['mutool', 'draw', '-F', 'txt', '-o', $outputpath, $filepath],
        ];

        foreach ($commands as $command) {
            $executable = $this->find_executable($command[0]);
            if ($executable === '') {
                continue;
            }
            $command[0] = $executable;
            $descriptors = [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ];
            $process = @proc_open($command, $descriptors, $pipes);
            if (!is_resource($process)) {
                continue;
            }
            fclose($pipes[0]);
            stream_get_contents($pipes[1]);
            fclose($pipes[1]);
            stream_get_contents($pipes[2]);
            fclose($pipes[2]);
            $exitcode = proc_close($process);

            if ($exitcode === 0 && is_readable($outputpath)) {
                $text = (string)file_get_contents($outputpath);
                @unlink($outputpath);
                $text = preg_replace('/\x00/u', '', $text);
                $text = trim((string)$text);
                if ($text !== '') {
                    return $text;
                }
            }
            @unlink($outputpath);
        }

        return '';
    }

    /**
     * Find an executable in a small fixed set of standard server directories.
     *
     * @param string $name Executable basename.
     * @return string Absolute executable path or empty string.
     */
    protected function find_executable(string $name): string {
        foreach (['/usr/bin', '/usr/local/bin', '/bin'] as $directory) {
            $candidate = $directory . '/' . $name;
            if (is_file($candidate) && is_executable($candidate)) {
                return $candidate;
            }
        }
        return '';
    }

    /**
     * Upload a structured PDF as several bounded text parts and combine the returned
     * FredKI paragraph objects into one document.
     *
     * The metadata stays in the first part. Further parts contain only subsequent
     * document text. This avoids request-size failures while keeping one FredKI
     * document and one Moodle mapping record.
     *
     * @param string $knowledgeid FredKI knowledge base id.
     * @param string $content Structured Markdown content.
     * @param string $title Document title.
     * @return array Tuple containing paragraphs and the first source-file id.
     */
    protected function upload_structured_pdf_chunks(
        string $knowledgeid,
        string $content,
        string $title
    ): array {
        $chunks = $this->split_structured_pdf_content($content);
        $allparagraphs = [];
        $sourcefileid = '';
        $basename = $this->clean_filename_for_upload($title);

        foreach ($chunks as $index => $chunk) {
            $partnumber = $index + 1;
            $parttitle = count($chunks) > 1
                ? $basename . '-part-' . $partnumber . '-of-' . count($chunks)
                : $basename;
            $path = $this->create_temp_structured_file($chunk, $parttitle);
            try {
                $split = $this->client->split_upload(
                    $knowledgeid,
                    $path,
                    $parttitle . '.txt'
                );
                if ($sourcefileid === '') {
                    $sourcefileid = (string)($split['source_file_id'] ?? '');
                }
                $partparagraphs = $this->replace_paragraph_texts_with_chunks(
                    $split['content'] ?? [],
                    [$chunk]
                );
                foreach ($partparagraphs as $paragraph) {
                    $allparagraphs[] = $paragraph;
                }
            } finally {
                @unlink($path);
            }
        }

        if ($allparagraphs === []) {
            throw new \moodle_exception('externalapiinvalidresponse', 'mod_fredkicourseimport');
        }

        return [$allparagraphs, $sourcefileid];
    }

    /**
     * Split structured PDF text at semantic boundaries and enforce a safe maximum size.
     *
     * @param string $content Structured Markdown content.
     * @param int $maxchars Maximum UTF-8 characters per part.
     * @return array Non-empty chunks.
     */
    protected function split_structured_pdf_content(string $content, int $maxchars = 9000): array {
        $content = trim($content);
        if ($content === '') {
            return ["START EINTRAG\n\nENDE EINTRAG"];
        }

        // Paragraphs and Markdown headings are preferred split boundaries.
        $blocks = preg_split('/\n{2,}(?=\S)/u', $content) ?: [$content];
        $chunks = [];
        $current = '';

        foreach ($blocks as $block) {
            $block = trim($block);
            if ($block === '') {
                continue;
            }

            // A single long paragraph is split by sentences, then by hard character limit.
            $pieces = mb_strlen($block) > $maxchars
                ? $this->split_long_pdf_block($block, $maxchars)
                : [$block];

            foreach ($pieces as $piece) {
                $piece = trim($piece);
                if ($piece === '') {
                    continue;
                }
                $candidate = $current === '' ? $piece : $current . "\n\n" . $piece;
                if ($current !== '' && mb_strlen($candidate) > $maxchars) {
                    $chunks[] = $current;
                    $current = $piece;
                } else {
                    $current = $candidate;
                }
            }
        }

        if ($current !== '') {
            $chunks[] = $current;
        }

        return $chunks ?: [$content];
    }

    /**
     * Split an oversized PDF block without cutting in the middle of a sentence where possible.
     *
     * @param string $block Text block.
     * @param int $maxchars Maximum characters.
     * @return array Pieces.
     */
    protected function split_long_pdf_block(string $block, int $maxchars): array {
        $sentences = preg_split('/(?<=[.!?])\s+(?=\p{Lu}|\d)/u', $block) ?: [$block];
        $pieces = [];
        $current = '';

        foreach ($sentences as $sentence) {
            $sentence = trim($sentence);
            if ($sentence === '') {
                continue;
            }
            if (mb_strlen($sentence) > $maxchars) {
                if ($current !== '') {
                    $pieces[] = $current;
                    $current = '';
                }
                for ($offset = 0, $length = mb_strlen($sentence); $offset < $length; $offset += $maxchars) {
                    $pieces[] = mb_substr($sentence, $offset, $maxchars);
                }
                continue;
            }
            $candidate = $current === '' ? $sentence : $current . ' ' . $sentence;
            if ($current !== '' && mb_strlen($candidate) > $maxchars) {
                $pieces[] = $current;
                $current = $sentence;
            } else {
                $current = $candidate;
            }
        }
        if ($current !== '') {
            $pieces[] = $current;
        }
        return $pieces;
    }

    /**
     * Preserve FredKI's paragraph schema while replacing paragraph text.
     *
     * @param mixed $originalparagraphs Paragraph structure returned by split_upload.
     * @param array $texts Replacement text chunks.
     * @return array Paragraph list.
     */
    protected function replace_paragraph_texts_with_chunks($originalparagraphs, array $texts): array {
        $template = null;
        if (is_array($originalparagraphs) && $originalparagraphs !== []) {
            foreach (['content', 'text', 'paragraph'] as $textkey) {
                if (array_key_exists($textkey, $originalparagraphs)) {
                    $template = $originalparagraphs;
                    break;
                }
            }
            if ($template === null) {
                $first = reset($originalparagraphs);
                if (is_array($first)) {
                    $template = $first;
                }
            }
        }

        $result = [];
        foreach ($texts as $text) {
            $text = trim((string)$text);
            if ($text === '') {
                continue;
            }
            if ($template === null) {
                $result[] = $text;
                continue;
            }
            $paragraph = $template;
            $updated = false;
            foreach (['content', 'text', 'paragraph'] as $textkey) {
                if (array_key_exists($textkey, $paragraph)) {
                    $paragraph[$textkey] = $text;
                    $updated = true;
                    break;
                }
            }
            if (!$updated) {
                $paragraph['content'] = $text;
            }
            $result[] = $paragraph;
        }
        return $result;
    }

    /**
     * Add the Moodle source URL to every paragraph returned by FredKI's split endpoint.
     * This must happen after splitting, otherwise only the first chunk carries the source.
     *
     * @param mixed $paragraphs Paragraph list as returned by FredKI.
     * @param string|null $sourceurl Source URL.
     * @return mixed Paragraph list with source appended to each paragraph.
     */
    protected function add_source_to_each_paragraph($paragraphs, ?string $sourceurl) {
        $sourceurl = trim((string)$sourceurl);
        if ($sourceurl === '') {
            return $paragraphs;
        }

        $sourcetext = "\n\nQuelle in Moodle: " . $sourceurl;

        if (is_string($paragraphs)) {
            return $this->append_source_to_text($paragraphs, $sourcetext);
        }

        if (!is_array($paragraphs)) {
            return $paragraphs;
        }

        if ($paragraphs === []) {
            return [ltrim($sourcetext)];
        }

        // If this array itself is a paragraph object, append to its text field.
        foreach (['content', 'text', 'paragraph'] as $textkey) {
            if (array_key_exists($textkey, $paragraphs) && is_string($paragraphs[$textkey])) {
                $paragraphs[$textkey] = $this->append_source_to_text($paragraphs[$textkey], $sourcetext);
                return $paragraphs;
            }
        }

        // Otherwise treat it as a list of paragraphs and update each entry.
        foreach ($paragraphs as $key => $paragraph) {
            if (is_string($paragraph)) {
                $paragraphs[$key] = $this->append_source_to_text($paragraph, $sourcetext);
                continue;
            }

            if (is_array($paragraph)) {
                $updated = false;
                foreach (['content', 'text', 'paragraph'] as $textkey) {
                    if (array_key_exists($textkey, $paragraph) && is_string($paragraph[$textkey])) {
                        $paragraph[$textkey] = $this->append_source_to_text($paragraph[$textkey], $sourcetext);
                        $updated = true;
                        break;
                    }
                }

                if (!$updated) {
                    $paragraph['content'] = ltrim($sourcetext);
                }
                $paragraphs[$key] = $paragraph;
            }
        }

        return $paragraphs;
    }

    /**
     * Append source to text.
     *
     * @param string $text The text.
     * @param string $sourcetext The sourcetext.
     * @return string The result.
     */
    protected function append_source_to_text(string $text, string $sourcetext): string {
        if (strpos($text, 'Quelle in Moodle:') !== false) {
            return $text;
        }
        return rtrim($text) . $sourcetext;
    }

    /**
     * Is transformable document.
     *
     * @param string $filename The file name.
     * @return bool True on success.
     */
    protected function is_transformable_document(string $filename): bool {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        return in_array($ext, ['pdf', 'doc', 'docx'], true);
    }

    /**
     * Create temp structured file.
     *
     * @param string $content The content.
     * @param string $title The title.
     * @return string The result.
     */
    protected function create_temp_structured_file(string $content, string $title): string {
        $dir = make_request_directory();
        $path = $dir . '/' . $this->clean_filename_for_upload($title) . '.txt';
        file_put_contents($path, $content);
        return $path;
    }

    /**
     * Clean filename for upload.
     *
     * @param string $name The name.
     * @return string The result.
     */
    protected function clean_filename_for_upload(string $name): string {
        $name = trim($name);
        if ($name === '') {
            $name = 'fredki-eintrag';
        }
        $name = preg_replace('/[^\pL\pN _.-]+/u', ' ', $name);
        $name = preg_replace('/\s+/u', '-', $name);
        $name = trim($name, '.- _');
        if ($name === '') {
            $name = 'fredki-eintrag';
        }
        return mb_substr($name, 0, 80);
    }

    /**
     * Force single text paragraph.
     *
     * @param mixed $originalparagraphs The originalparagraphs.
     * @param string $text The text.
     */
    protected function force_single_text_paragraph($originalparagraphs, string $text) {
        // Preserve the shape FredKI returned for the freshly uploaded TXT file, but
        // force exactly one content paragraph. This prevents accidental document
        // fragmentation while keeping batch_create schema-compatible.
        $text = trim($text);
        if ($text === '') {
            $text = "START EINTRAG

ENDE EINTRAG";
        }
        return $this->make_single_paragraph_like_original($originalparagraphs, $text);
    }

    /**
     * Build one FredKI paragraph while preserving the paragraph shape returned by split_upload.
     * FredKI's batch_create endpoint is sensitive to the paragraph schema. Therefore we do not
     * send a plain arbitrary structure, but reuse the first extracted paragraph object if present.
     */
    protected function make_single_paragraph_like_original($originalparagraphs, string $text) {
        $text = trim($text);
        if ($text === '') {
            $text = 'START EINTRAG\n\nENDE EINTRAG';
        }

        if (is_string($originalparagraphs)) {
            return [$text];
        }

        if (!is_array($originalparagraphs) || $originalparagraphs === []) {
            return [$text];
        }

        foreach (['content', 'text', 'paragraph'] as $textkey) {
            if (array_key_exists($textkey, $originalparagraphs)) {
                $paragraph = $originalparagraphs;
                $paragraph[$textkey] = $text;
                return [$paragraph];
            }
        }

        $first = reset($originalparagraphs);
        if (is_string($first)) {
            return [$text];
        }
        if (is_array($first)) {
            $paragraph = $first;
            $updated = false;
            foreach (['content', 'text', 'paragraph'] as $textkey) {
                if (array_key_exists($textkey, $paragraph)) {
                    $paragraph[$textkey] = $text;
                    $updated = true;
                    break;
                }
            }
            if (!$updated) {
                $paragraph['content'] = $text;
            }
            return [$paragraph];
        }

        return [$text];
    }

    /**
     * Set meaningful paragraph titles and remove linked-question metadata.
     *
     * CourseImport intentionally does not create MaxKB paragraph.problem_list entries.
     * The material title, tags/custom fields, section hierarchy and description already
     * live in the searchable paragraph text. Keeping retrieval focused on that content
     * avoids extra, automatically generated question embeddings influencing ranking.
     *
     * Any problem_list returned by an upstream split response is removed as well, so
     * CourseImport guarantees that its documents contain no linked questions.
     *
     * @param mixed $paragraphs Paragraphs returned by FredKI split.
     * @param string $title Human-readable activity/material title.
     * @return array Normalised paragraph list without problem_list entries.
     */
    protected function prepare_searchable_paragraphs($paragraphs, string $title): array {
        $title = trim($title);
        if ($title === '') {
            $title = 'Material';
        }

        if (is_string($paragraphs)) {
            $paragraphs = [['content' => $paragraphs]];
        } else if (!is_array($paragraphs)) {
            $paragraphs = [];
        }

        // A split response can itself be one paragraph object rather than a list.
        foreach (['content', 'text', 'paragraph'] as $textkey) {
            if (array_key_exists($textkey, $paragraphs) && is_string($paragraphs[$textkey])) {
                $paragraphs = [$paragraphs];
                break;
            }
        }

        $result = [];
        foreach (array_values($paragraphs) as $index => $paragraph) {
            if (is_string($paragraph)) {
                $paragraph = ['content' => $paragraph];
            }
            if (!is_array($paragraph)) {
                continue;
            }

            // Never transfer or retain linked questions from CourseImport documents.
            unset($paragraph['problem_list']);

            if ($index === 0) {
                $paragraph['title'] = \core_text::substr($title, 0, 256);
            } else {
                $existingtitle = trim((string)($paragraph['title'] ?? ''));
                $generic = in_array(
                    \core_text::strtolower($existingtitle),
                    ['', 'basisinformationen', 'inhalt'],
                    true
                );
                if ($generic) {
                    $paragraph['title'] = \core_text::substr($title . ' – Teil ' . ($index + 1), 0, 256);
                }
            }
            $result[] = $paragraph;
        }

        // MaxKB requires at least one paragraph object for our structured documents.
        if (!$result) {
            $result[] = [
                'content' => $title,
                'title' => \core_text::substr($title, 0, 256),
            ];
        }

        return $result;
    }

    /**
     * Build document entry.
     *
     * @param mixed $paragraphs The paragraphs.
     * @param string $filename The file name.
     * @param ?string $sourceurl The sourceurl.
     * @param ?string $coursename The coursename.
     * @param array $metadata The metadata.
     * @param ?string $sourcefilepath The source file path.
     * @return array The result.
     */
    protected function build_document_entry(
        $paragraphs,
        string $filename,
        ?string $sourceurl,
        ?string $coursename,
        array $metadata = [],
        ?string $sourcefilepath = null
    ): array {
        $activityname = trim((string)($metadata['title'] ?? ''));
        if ($activityname === '') {
            $activityname = pathinfo($filename, PATHINFO_FILENAME);
        }

        $description = trim((string)($metadata['summary'] ?? $metadata['description'] ?? ''));
        $displayfilename = trim((string)($metadata['filename'] ?? $filename));
        $activitylink = trim((string)($metadata['activitylink'] ?? $sourceurl ?? ''));
        $course = trim((string)($metadata['course'] ?? $coursename ?? ''));
        $courselink = trim((string)($metadata['courselink'] ?? ''));
        $section = trim((string)($metadata['section'] ?? ''));
        $sectionparentsraw = $metadata['sectionparents'] ?? [];
        if (!is_array($sectionparentsraw)) {
            $sectionparentsraw = $sectionparentsraw !== '' ? [(string)$sectionparentsraw] : [];
        }
        $sectionparents = section_hierarchy_helper::format_parents($sectionparentsraw);
        $sectionpathraw = $metadata['sectionpath'] ?? [];
        if (!is_array($sectionpathraw)) {
            $sectionpathraw = $sectionpathraw !== '' ? [(string)$sectionpathraw] : [];
        }
        $sectioncontext = section_hierarchy_helper::format_parents($sectionpathraw);
        if ($sectioncontext === '') {
            $sectioncontext = $section;
        }
        $type = trim((string)($metadata['type'] ?? 'Material'));
        $typeprefix = trim((string)($metadata['typeprefix'] ?? ''));
        if ($typeprefix === '') {
            $typeprefix = preg_replace('/\s*\([^)]*\)\s*$/u', '', $type) ?? $type;
        }
        $contentmode = ($metadata['contentmode'] ?? 'reference') === 'reference'
            ? 'reference'
            : 'knowledge';

        $documenttitle = trim((string)($metadata['documenttitle'] ?? ''));
        if ($documenttitle === '') {
            $documenttitle = $activityname;
            if ($typeprefix !== '') {
                $documenttitle = $typeprefix . ': ' . $activityname;
            }
        }

        // Importer-generated HTML already contains exact direct Moodle links and source
        // filenames. It is converted to compact Markdown and then enriched with FredKI
        // custom-field metadata directly beneath the real activity heading.
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (in_array($extension, ['html', 'htm'], true) && $sourcefilepath !== null && is_readable($sourcefilepath)) {
            $htmlcontent = trim((string)file_get_contents($sourcefilepath));
            if ($htmlcontent !== '') {
                $textcontent = $this->generated_html_to_markdown($htmlcontent);
                $textcontent = $this->inject_fredki_tags_into_search_header(
                    $textcontent,
                    $metadata['fredki_tags'] ?? [],
                    $activityname
                );
                if ($contentmode === 'reference') {
                    $textcontent = preg_replace('/\n## Inhalt\b.*$/su', '', $textcontent) ?? $textcontent;
                }
                $textcontent = trim($textcontent);
                if ($textcontent !== '') {
                    return [
                        'title' => $documenttitle,
                        'paragraph_title' => $activityname,
                        'content' => $textcontent,
                    ];
                }
            }
        }

        // Search-oriented fallback for original PDFs/files. The individual metadata
        // values come first; repeated navigation/technical fields are intentionally
        // reduced so embeddings are driven by the material itself.
        $rows = ['## ' . $activityname];
        $this->add_fredki_tag_metadata_rows($rows, $metadata['fredki_tags'] ?? []);
        $this->add_metadata_text_row($rows, 'Beschreibung', $description);
        if ($sectionparents !== '') {
            $parentlabel = count($sectionparentsraw) > 1
                ? 'Übergeordnete Abschnitte'
                : 'Übergeordneter Abschnitt';
            $this->add_metadata_text_row($rows, $parentlabel, $sectionparents);
        }
        $this->add_metadata_text_row(
            $rows,
            $sectionparents !== '' ? 'Unterabschnitt' : 'Abschnitt',
            $section
        );
        $this->add_metadata_text_row($rows, 'Dateiname', $displayfilename);
        $this->add_metadata_text_row($rows, 'Aktivitätslink', $activitylink);
        $this->add_metadata_text_row($rows, 'Kurs', $course);
        $this->add_metadata_text_row($rows, 'Kurslink', $courselink);
        $this->add_metadata_text_row($rows, 'Typ', $type);

        if ($contentmode === 'knowledge') {
            $rawdocumentcontent = $this->flatten_text($paragraphs);
            $documentcontent = $extension === 'pdf'
                ? $this->clean_pdf_document_text($rawdocumentcontent)
                : $this->clean_document_text($rawdocumentcontent);
            if ($documentcontent !== '') {
                $rows[] = '## Inhalt';
                $rows[] = $documentcontent;
            }
        }

        return [
            'title' => $documenttitle,
            'paragraph_title' => $activityname,
            'content' => implode("\n\n", $rows),
        ];
    }

    /**
     * Convert importer-generated HTML into the same Markdown structure used for
     * PDF and Office files. This keeps books, lessons, pages, glossaries and
     * section texts visually consistent in FredKI without duplicating the title.
     */
    protected function generated_html_to_markdown(string $html): string {
        // The document title is transported separately as the FredKI document name.
        // Remove head/title and any legacy H1 before converting the body.
        $html = preg_replace('~<head\b[^>]*>.*?</head>~is', '', $html) ?? $html;
        $html = preg_replace('~<h1\b[^>]*>.*?</h1>~is', '', $html) ?? $html;

        $html = preg_replace_callback('~<h2\b[^>]*>(.*?)</h2>~is', static function (array $matches): string {
            $text = trim(html_entity_decode(strip_tags($matches[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            return $text === '' ? '' : "\n\n## " . $text . "\n\n";
        }, $html) ?? $html;
        $html = preg_replace_callback('~<h3\b[^>]*>(.*?)</h3>~is', static function (array $matches): string {
            $text = trim(html_entity_decode(strip_tags($matches[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            return $text === '' ? '' : "\n\n### " . $text . "\n\n";
        }, $html) ?? $html;
        $html = preg_replace_callback('~<strong\b[^>]*>(.*?)</strong>~is', static function (array $matches): string {
            $text = trim(html_entity_decode(strip_tags($matches[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            return $text === '' ? '' : '**' . $text . '**';
        }, $html) ?? $html;
        $html = preg_replace_callback('~<a\b[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)</a>~is', static function (array $matches): string {
            $label = trim(html_entity_decode(strip_tags($matches[2]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            $url = html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            return $label !== '' ? $label : $url;
        }, $html) ?? $html;

        $html = preg_replace('~<br\s*/?>~i', "\n", $html) ?? $html;
        $html = preg_replace('~</p\s*>~i', "\n\n", $html) ?? $html;
        $html = preg_replace('~</div\s*>~i', "\n\n", $html) ?? $html;
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[ \t]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/ *\n */u', "\n", $text) ?? $text;
        $text = preg_replace('/\n{3,}/u', "\n\n", $text) ?? $text;
        return trim($text);
    }

    /**
     * Add Moodle custom-field values directly below the searchable activity heading.
     *
     * Real FredKI tags are still assigned separately. Duplicating their human-readable
     * label/value here intentionally makes the metadata available to normal full-text,
     * vector and hybrid retrieval as document content.
     *
     * @param array $rows Markdown rows.
     * @param mixed $tags FredKI tag metadata.
     */
    protected function add_fredki_tag_metadata_rows(array &$rows, $tags): void {
        if (!is_array($tags)) {
            return;
        }
        $seen = [];
        foreach ($tags as $tag) {
            if (!is_array($tag)) {
                continue;
            }
            $label = trim((string)($tag['label'] ?? $tag['key'] ?? ''));
            $value = trim((string)($tag['value'] ?? ''));
            if ($label === '' || $value === '') {
                continue;
            }
            $signature = \core_text::strtolower($label . "\0" . $value);
            if (isset($seen[$signature])) {
                continue;
            }
            $seen[$signature] = true;
            $this->add_metadata_text_row($rows, $label, $value);
        }
    }

    /**
     * Insert FredKI tag metadata into importer-generated Markdown.
     *
     * @param string $text Markdown generated from the HTML import document.
     * @param mixed $tags FredKI tag metadata.
     * @return string
     */
    protected function inject_fredki_tags_into_search_header(
        string $text,
        $tags,
        string $activityname = ''
    ): string {
        $tagrows = [];
        $this->add_fredki_tag_metadata_rows($tagrows, $tags);
        $block = implode("\n\n", $tagrows);

        $activityname = trim($activityname);
        if ($activityname === '') {
            $activityname = 'Material';
        }

        // The importer now uses the real activity name as the first H2/Markdown heading.
        // Put custom fields immediately underneath so Materialtyp/Zielgruppe/Thema have
        // maximum influence on both full-text and vector retrieval.
        $pattern = '/^(## [^\n]+\n+)/u';
        if (preg_match($pattern, $text)) {
            if ($block === '') {
                return $text;
            }
            return preg_replace($pattern, '$1' . "\n" . $block . "\n\n", $text, 1) ?? $text;
        }

        $prefix = '## ' . $activityname;
        if ($block !== '') {
            $prefix .= "\n\n" . $block;
        }
        return $prefix . "\n\n" . ltrim($text);
    }

    /**
     * Add metadata text row.
     *
     * @param array $rows The rows.
     * @param string $label The label.
     * @param string $value The value.
     */
    protected function add_metadata_text_row(array &$rows, string $label, string $value): void {
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');
        if ($value !== '') {
            $rows[] = '**' . $label . ':** ' . $value;
        }
    }

    /**
     * Flatten text.
     *
     * @param mixed $value The value.
     * @return string The result.
     */
    protected function flatten_text($value): string {
        if (is_string($value)) {
            return $value;
        }
        if (!is_array($value)) {
            return '';
        }
        $parts = [];
        foreach ($value as $key => $item) {
            if (is_string($item)) {
                $parts[] = $item;
            } else if (is_array($item)) {
                foreach (['content', 'text', 'paragraph', 'title', 'name'] as $textkey) {
                    if (!empty($item[$textkey]) && is_string($item[$textkey])) {
                        $parts[] = $item[$textkey];
                    }
                }
                if ($parts === []) {
                    $parts[] = $this->flatten_text($item);
                }
            }
        }
        return implode("\n", array_filter($parts));
    }

    /**
     * Clean PDF text while retaining paragraph and heading boundaries needed for chunking.
     *
     * @param string $text Extracted PDF text.
     * @return string Clean structured text.
     */
    protected function clean_pdf_document_text(string $text): string {
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = $this->fix_mojibake($text);
        $text = str_replace(["\r\n", "\r", "\f"], ["\n", "\n", "\n\n"], $text);
        $text = preg_replace('/\x00/u', '', $text) ?? $text;
        $text = preg_replace('/Quelle\s+in\s+Moodle\s*:[^\n]*/iu', '', $text) ?? $text;
        $text = preg_replace('/https?:\/\/\S+/u', '', $text) ?? $text;
        $text = preg_replace('/pluginfile\.php[^\s]*/iu', '', $text) ?? $text;

        $lines = preg_split('/\n/u', $text) ?: [];
        $kept = [];
        $previousblank = true;
        foreach ($lines as $line) {
            $line = trim(preg_replace('/[ \t]+/u', ' ', $line) ?? '');
            if ($line === '') {
                if (!$previousblank) {
                    $kept[] = '';
                }
                $previousblank = true;
                continue;
            }
            // Remove standalone page numbers and common generated page markers.
            if (preg_match('/^(?:page|seite)?\s*\d+\s*(?:\/|of|von)?\s*\d*$/iu', $line)) {
                continue;
            }
            if (preg_match('/^[ivxlcdm]+$/iu', $line)) {
                continue;
            }
            // Promote likely chapter/section headings to Markdown boundaries.
            if (
                preg_match('/^(chapter|kapitel)\s+[\pL\d]+\b.*$/iu', $line) ||
                    preg_match('/^\d+(?:\.\d+){0,3}\s+\p{Lu}.*$/u', $line)
            ) {
                if (!$previousblank) {
                    $kept[] = '';
                }
                $line = '### ' . ltrim($line, '# ');
            }
            $kept[] = $line;
            $previousblank = false;
        }

        $text = implode("\n", $kept);
        $text = preg_replace('/\n{3,}/u', "\n\n", $text) ?? $text;
        return trim($text);
    }

    /**
     * Clean document text.
     *
     * @param string $text The text.
     * @return string The result.
     */
    protected function clean_document_text(string $text): string {
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = $this->fix_mojibake($text);
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/(?:加载失败|Copy|©|®|™|□|■|●|\.\.\.|…)/u', ' ', $text);
        $text = preg_replace('/Quelle\s+in\s+Moodle\s*:[^\n]*/iu', ' ', $text);
        $text = preg_replace('/https?:\/\/\S+/u', ' ', $text);
        $text = preg_replace('/\b[\w.%+\-]+@[\w.\-]+\.[A-Za-z]{2,}\b/u', ' ', $text);
        $text = preg_replace('/pluginfile\.php[^\s]*/iu', ' ', $text);
        $text = preg_replace('/mod_[a-z]+\/content\/\d+/iu', ' ', $text);
        $text = preg_replace('/\bFolie\s+\d+\s*[:\-]?/iu', ' ', $text);
        $text = preg_replace('/\bSeite\s+\d+\s*(?:von\s+\d+)?/iu', ' ', $text);
        $text = preg_replace('/\b\d+\s*\/\s*\d+\b/u', ' ', $text);
        $text = preg_replace('/[-_]{6,}|\.{6,}/u', ' ', $text);

        $lines = preg_split('/\n+/u', $text);
        $kept = [];
        $seen = [];
        foreach ($lines as $line) {
            $line = trim(preg_replace('/\s+/u', ' ', $line));
            if ($line === '') {
                continue;
            }
            if (preg_match('/^(tel\.?|telefon|fax|mobil|e-mail|mail|www\.|info@|kontakt|straße|strasse|plz|ort,?\s*datum)$/iu', $line)) {
                continue;
            }
            if (preg_match('/\b(?:Tel\.?|Telefon|Fax|Mobil)\b\s*[:\d]/iu', $line)) {
                continue;
            }
            if (mb_strlen($line) < 3) {
                continue;
            }
            $key = mb_strtolower($line);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $kept[] = $line;
        }

        $text = implode(' ', $kept);
        $text = preg_replace('/\s+/u', ' ', $text);
        return trim($text);
    }

    /**
     * Derive title.
     *
     * @param string $text The text.
     * @param string $filename The file name.
     * @return string The result.
     */
    protected function derive_title(string $text, string $filename): string {
        // Für PDF/DOCX niemals zufällige OCR-/PDF-Zeilen als Titel verwenden.
        // Der Dateiname ist bei Moodle-Materialien meist die stabilste Quelle.
        $filetitle = $this->clean_title(pathinfo($filename, PATHINFO_FILENAME));
        $lower = mb_strtolower($filetitle);

        $replacements = [
            '/\bsepa\b.*\blastschrift\b/iu' => 'SEPA-Lastschriftmandat für Mitgliedsbeiträge',
            '/beitritt.*(bundesverband|bv)/iu' => 'Beitrittserklärung zum Bundesverband',
            '/beitrittserkl/iu' => 'Beitrittserklärung',
            '/einverst.*eltern/iu' => 'Einverständniserklärung der Eltern',
            '/einverst.*foto|foto.*einverst/iu' => 'Einverständniserklärung für Fotos von Kindern',
            '/datenschutz.*mitglied/iu' => 'Datenschutzerklärung für Vereinsmitglieder',
            '/gemeinnützig/iu' => 'Beantragung der Gemeinnützigkeit',
            '/gründungsversammlung|gruendungsversammlung/iu' => 'Einladung zur Gründungsversammlung',
            '/mitgliederversammlung.*kreativ|kreativ.*mitgliederversammlung/iu' => 'Mitgliederversammlungen kreativ gestalten',
            '/checkliste.*satzung|satzung.*checkliste/iu' => 'Checkliste zur Satzung',
        ];
        foreach ($replacements as $pattern => $title) {
            if (preg_match($pattern, $lower . ' ' . mb_strtolower($filename))) {
                return $title;
            }
        }

        if ($filetitle !== '' && !$this->looks_like_bad_title($filetitle)) {
            return $filetitle;
        }

        // Fallback: nur sehr starke Kandidaten aus dem Text verwenden.
        foreach (preg_split('/\n+|(?<=[.!?])\s+/u', $text) as $line) {
            $line = $this->clean_title($line);
            if ($line === '' || mb_strlen($line) < 8 || mb_strlen($line) > 90) {
                continue;
            }
            if ($this->looks_like_bad_title($line)) {
                continue;
            }
            return $line;
        }
        return 'Moodle-Dokument';
    }

    /**
     * Looks like bad title.
     *
     * @param string $title The title.
     * @return bool True on success.
     */
    protected function looks_like_bad_title(string $title): bool {
        $title = trim($title);
        if ($title === '') {
            return true;
        }
        if (
            preg_match(
                '/^(am|datum|ort|name|straße|strasse|plz|telefon|tel|fax|mail|e-mail|iban|bic|unterschrift'
                . '|an das|sehr geehrte|hiermit|wir bitten)/iu',
                $title
            )
        ) {
            return true;
        }
        if (preg_match('/^[\d\W_]+$/u', $title)) {
            return true;
        }
        if (mb_strlen($title) < 5) {
            return true;
        }
        return false;
    }

    /**
     * Clean title.
     *
     * @param string $title The title.
     * @return string The result.
     */
    protected function clean_title(string $title): string {
        $title = html_entity_decode(strip_tags($title), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $title = $this->fix_mojibake($title);
        $title = preg_replace('/\.(pdf|docx?|pptx?)$/iu', '', $title);
        $title = str_replace(['_', '-'], ' ', $title);
        $title = preg_replace('/\b(?:final|neu|version|v\d+|kopie)\b/iu', ' ', $title);
        $title = preg_replace('/\s+/u', ' ', $title);
        return trim($title, " \t\n\r\0\x0B:-–—");
    }

    /**
     * Fix mojibake.
     *
     * @param string $text The text.
     * @return string The result.
     */
    protected function fix_mojibake(string $text): string {
        $map = [
            'Ã¤' => 'ä', 'Ã¶' => 'ö', 'Ã¼' => 'ü', 'Ã„' => 'Ä', 'Ã–' => 'Ö', 'Ãœ' => 'Ü', 'ÃŸ' => 'ß',
            'â€“' => '–', 'â€”' => '—', 'â€ž' => '„', 'â€œ' => '“', 'â€' => '“', 'â€˜' => '‘', 'â€™' => '’',
            'PrÃ¤sentation' => 'Präsentation', 'fÃ¼r' => 'für', 'Vereinsrecht' => 'Vereinsrecht',
        ];
        return strtr($text, $map);
    }

    /**
     * Derive type.
     *
     * @param string $text The text.
     * @param string $filename The file name.
     * @return string The result.
     */
    protected function derive_type(string $text, string $filename): string {
        $haystack = mb_strtolower($text . ' ' . $filename);
        if (preg_match('/checkliste|check list|prüfliste/u', $haystack)) {
            return 'Checkliste';
        }
        if (preg_match('/formular|vorlage|muster|beitrittserklärung|einverständniserklärung|lastschrift|mandat|antrag|datenschutzerklärung|einladung/u', $haystack)) {
            return 'Vorlage';
        }
        if (preg_match('/präsentation|workshop|folien|handout|arbeitsblatt|material/u', $haystack)) {
            return 'Material';
        }
        if (preg_match('/schritt für schritt|lernprogramm|kursmodul/u', $haystack)) {
            return 'Lernprogramm';
        }
        return 'Wissen';
    }

    /**
     * Derive category.
     *
     * @param string $text The text.
     * @param string $filename The file name.
     * @return string The result.
     */
    protected function derive_category(string $text, string $filename): string {
        $haystack = mb_strtolower($text . ' ' . $filename);
        $categories = [
            'Mitgliedschaft und Verwaltung' => '/mitglied|mitgliedschaft|beitrag|lastschrift|sepa|datenschutz|einverständnis|formular/u',
            'Vereinsgründung und Vereinsrecht' => '/vereinsgründ|vereinsrecht|satzung|gemeinnützig|vorstand|gründungsversammlung|register/u',
            'Lesestunden gestalten' => '/lesestunde|lesen|mentor|kind|grundschule|buch|emotion|gefühl/u',
            'Organisation und Kommunikation' => '/organisation|kommunikation|versammlung|einladung|protokoll/u',
        ];
        foreach ($categories as $category => $pattern) {
            if (preg_match($pattern, $haystack)) {
                return $category;
            }
        }
        return 'Allgemeines';
    }

    /**
     * Derive keywords.
     *
     * @param string $text The text.
     * @param string $filename The file name.
     * @param string $category The category.
     * @param string $type The type.
     * @return string The result.
     */
    protected function derive_keywords(string $text, string $filename, string $category, string $type): string {
        $keywords = [$type, $category];
        $map = [
            'Mitgliedschaft' => '/mitglied/u',
            'Beitragszahlung' => '/beitrag|lastschrift|sepa/u',
            'Datenschutz' => '/datenschutz|personenbezogen/u',
            'Vereinsgründung' => '/gründung|vereinsgründ/u',
            'Satzung' => '/satzung/u',
            'Vorstand' => '/vorstand/u',
            'Gemeinnützigkeit' => '/gemeinnützig/u',
            'Lesestunden' => '/lesestunde|lesen/u',
            'Emotionen' => '/emotion|gefühl/u',
            'Formular' => '/formular|unterschrift|iban|bic/u',
        ];
        $haystack = mb_strtolower($text . ' ' . $filename);
        foreach ($map as $label => $pattern) {
            if (preg_match($pattern, $haystack)) {
                $keywords[] = $label;
            }
        }
        $keywords = array_values(array_unique($keywords));
        return implode(', ', array_slice($keywords, 0, 8));
    }

    /**
     * Derive priority.
     *
     * @param string $type The type.
     * @param string $text The text.
     * @param string $filename The file name.
     * @return string The result.
     */
    protected function derive_priority(string $type, string $text, string $filename): string {
        if (in_array($type, ['Lernprogramm', 'Vorlage', 'Checkliste'], true)) {
            return 'hoch';
        }
        if ($type === 'Material') {
            return 'mittel';
        }
        return 'niedrig';
    }

    /**
     * Derive description.
     *
     * @param string $text The text.
     * @param string $title The title.
     * @param string $type The type.
     * @return string The result.
     */
    protected function derive_description(string $text, string $title, string $type): string {
        $sentences = $this->best_sentences($text, 2);
        if ($sentences !== '') {
            return $sentences;
        }
        if ($type === 'Vorlage') {
            return 'Dieses Dokument ist eine Vorlage oder ein Formular, das für organisatorische Aufgaben genutzt werden kann.';
        }
        return 'Dieses Dokument enthält Informationen und Materialien zum angegebenen Thema.';
    }

    /**
     * Derive benefit.
     *
     * @param string $type The type.
     * @param string $category The category.
     * @return string The result.
     */
    protected function derive_benefit(string $type, string $category): string {
        switch ($type) {
            case 'Vorlage':
                return 'Hilft dabei, wiederkehrende organisatorische Aufgaben schneller und einheitlich umzusetzen.';
            case 'Checkliste':
                return 'Unterstützt dabei, wichtige Schritte systematisch zu prüfen und nichts Wesentliches zu vergessen.';
            case 'Material':
                return 'Dient als unterstützendes Material für Vorbereitung, Durchführung oder Nachbereitung.';
            case 'Lernprogramm':
                return 'Bietet eine strukturierte Einführung und führt Schritt für Schritt durch das Thema.';
            default:
                return 'Hilft dabei, den Inhalt thematisch einzuordnen und bei Bedarf direkt aufzurufen.';
        }
    }

    /**
     * Derive content overview.
     *
     * @param string $text The text.
     * @param string $type The type.
     * @return string The result.
     */
    protected function derive_content_overview(string $text, string $type): string {
        $sentences = $this->best_sentences($text, 4);
        if ($sentences === '') {
            return 'Strukturierter Verweis auf das Moodle-Dokument.';
        }
        return $sentences;
    }

    /**
     * Best sentences.
     *
     * @param string $text The text.
     * @param int $limit The limit.
     * @return string The result.
     */
    protected function best_sentences(string $text, int $limit): string {
        $text = trim($text);
        if ($text === '') {
            return '';
        }
        $pieces = preg_split('/(?<=[.!?])\s+/u', $text);
        $sentences = [];
        foreach ($pieces as $piece) {
            $piece = trim($piece);
            if (mb_strlen($piece) < 35 || mb_strlen($piece) > 260) {
                continue;
            }
            if (preg_match('/^(an das|sehr geehrte|mit freundlichen grüßen|ort, datum|name|straße|plz|iban|bic|telefon|fax|mail)/iu', $piece)) {
                continue;
            }
            $sentences[] = $piece;
            if (count($sentences) >= $limit) {
                break;
            }
        }
        if ($sentences === []) {
            $snippet = mb_substr($text, 0, 420);
            return trim($snippet, " ,.;:\n\t") . (mb_strlen($text) > 420 ? '.' : '');
        }
        return implode(' ', $sentences);
    }
}
