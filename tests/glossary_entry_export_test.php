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
 * PHPUnit tests for the per-entry glossary export.
 *
 * @package    mod_fredkicourseimport
 * @copyright  2026 lernsteine.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_fredkicourseimport;

use mod_fredkicourseimport\local\course_content_importer;
use mod_fredkicourseimport\local\glossary_entry_helper;
use mod_fredkicourseimport\local\manual_sync_service;

/**
 * Complete glossaries become one FredKI document per approved entry.
 *
 * @covers \mod_fredkicourseimport\local\glossary_entry_helper
 * @covers \mod_fredkicourseimport\local\course_content_importer
 */
final class glossary_entry_export_test extends \advanced_testcase {
    /**
     * Filenames are id based and parse back; direct URLs point to the entry.
     */
    public function test_filename_and_direct_url(): void {
        $this->resetAfterTest();
        $filename = glossary_entry_helper::export_filename(42, 789);
        $this->assertSame('cm42-glossary-eid789.html', $filename);
        $this->assertSame(
            ['cmid' => 42, 'entryid' => 789],
            glossary_entry_helper::parse_export_filename('/glossaries/', $filename)
        );
        $this->assertNull(glossary_entry_helper::parse_export_filename('/books/', $filename));
        $this->assertNull(glossary_entry_helper::parse_export_filename('/glossaries/', 'cm42-glossary-All.html'));
        $this->assertStringContainsString('/mod/glossary/showentry.php?eid=789', glossary_entry_helper::direct_url(789));
    }

    /**
     * Complete mode builds one document per approved entry, titled with its concept.
     */
    public function test_complete_mode_builds_one_document_per_approved_entry(): void {
        global $DB, $USER;
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $glossary = $this->getDataGenerator()->create_module('glossary', ['course' => $course->id]);
        $sourcecm = get_fast_modinfo($course)->cms[$glossary->cmid];
        $ids = [];
        foreach (
            [
                ['Photosynthesis', '<p>Plants turn light into energy.</p>', 1],
                ['Osmosis', '<p>Water moves through a membrane.</p>', 1],
                ['Hidden draft', 'Not approved', 0],
            ] as [$concept, $definition, $approvedflag]
        ) {
            $ids[] = $DB->insert_record('glossary_entries', (object)[
                'glossaryid' => $glossary->id,
                'userid' => $USER->id,
                'concept' => $concept,
                'definition' => $definition,
                'definitionformat' => FORMAT_HTML,
                'approved' => $approvedflag,
                'timecreated' => time(),
                'timemodified' => time(),
            ]);
        }

        $importer = new class extends course_content_importer {
            /**
             * Expose the glossary export for testing.
             *
             * @param \stdClass $course Course record.
             * @param \cm_info $sourcecm Glossary course module.
             * @param \stdClass $glossary Glossary record.
             * @return array
             */
            public function make_glossary(\stdClass $course, \cm_info $sourcecm, \stdClass $glossary): array {
                $count = $this->add_glossary_exports($course, $sourcecm, $glossary, 'Shared glossary description');
                return ['count' => $count, 'files' => $this->textfiles];
            }
        };
        $glossaryrecord = $DB->get_record('glossary', ['id' => $glossary->id], '*', MUST_EXIST);
        $export = $importer->make_glossary($course, $sourcecm, $glossaryrecord);
        // Two approved entries plus one overview document carrying the glossary description.
        $this->assertSame(2, $export['count']);
        $this->assertCount(3, $export['files']);
        $overview = array_filter($export['files'], static function (array $file): bool {
            return glossary_entry_helper::parse_export_filename($file['filepath'], $file['filename']) === null;
        });
        $this->assertCount(1, $overview);
        $this->assertStringContainsString('Shared glossary description', reset($overview)['content']);

        $entryfilename = glossary_entry_helper::export_filename((int)$sourcecm->id, (int)$ids[0]);
        $this->assertArrayHasKey('/glossaries/' . $entryfilename, $export['files']);
        $content = $export['files']['/glossaries/' . $entryfilename]['content'];
        $this->assertStringContainsString('<h2>Photosynthesis</h2>', $content);
        $this->assertStringContainsString('Plants turn light into energy.', $content);
        $this->assertStringContainsString('showentry.php?eid=' . $ids[0], $content);
        $this->assertStringNotContainsString('Hidden draft', implode(' ', array_column($export['files'], 'content')));
        // The glossary description is not repeated in every entry.
        $this->assertStringNotContainsString('Shared glossary description', $content);

        // The FredKI document name and link are specific to the entry.
        $method = new \ReflectionMethod(manual_sync_service::class, 'get_source_metadata');
        $file = get_file_storage()->create_file_from_string([
            'contextid' => \context_module::instance($glossary->cmid)->id,
            'component' => 'mod_fredkicourseimport',
            'filearea' => 'generated_files',
            'itemid' => 0,
            'filepath' => '/glossaries/',
            'filename' => $entryfilename,
        ], $content);
        $activity = (object)['course' => $course->id];
        $entryurl = glossary_entry_helper::direct_url((int)$ids[0]);
        $metadata = $method->invoke(null, $file, $activity, $course, $entryurl);
        $this->assertSame('Photosynthesis', $metadata['title']);
        $this->assertSame('Photosynthesis', $metadata['documenttitle']);
        $this->assertSame($entryurl, $metadata['activitylink']);
        $this->assertArrayNotHasKey('summary', $metadata);
    }
}
