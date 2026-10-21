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
 * tests/glossary_entry_export_test.php for the FredKI CourseImport activity.
 *
 * @package    mod_fredkicourseimport
 * @copyright  2026 lernsteine.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_fredkicourseimport;

use mod_fredkicourseimport\local\glossary_entry_helper;
use mod_fredkicourseimport\local\course_content_importer;
use mod_fredkicourseimport\local\manual_sync_service;

/** Verifies the real Moodle glossary document export and direct-entry routing. */
final class glossary_entry_export_test extends \advanced_testcase {
    /**
     * Test filename and direct url for FredKI CourseImport.
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
        $this->assertNull(glossary_entry_helper::parse_export_filename('/glossaries/', 'cm42-glossary-Gesamt.html'));
        $this->assertStringContainsString(
            '/mod/glossary/showentry.php?eid=789',
            glossary_entry_helper::direct_url(789)
        );
    }

    /**
     * Test complete mode builds one document per approved entry with direct links for FredKI CourseImport.
     */
    public function test_complete_mode_builds_one_document_per_approved_entry_with_direct_links(): void {
        global $DB, $USER;
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $glossary = $this->getDataGenerator()->create_module('glossary', ['course' => $course->id]);
        $sourcecm = get_fast_modinfo($course)->cms[$glossary->cmid];
        $approved = [];
        foreach (
            [
            ['Beitrittserklärung für Mitglieder', '<p>Formular für neue Mitglieder</p>', 1],
            ['Anmeldung', '<p>Formular zur Anmeldung</p>', 1],
            ['Versteckter Entwurf', 'Nicht anzeigen', 0],
            ] as [$concept, $definition, $approvedflag]
        ) {
            $approved[] = $DB->insert_record('glossary_entries', (object)[
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
             * Make glossary for FredKI CourseImport.
             *
             * @param \stdClass $course Source course.
             * @param \cm_info $sourcecm Source activity.
             * @param \stdClass $glossary Glossary data.
             * @return array Generated files and count.
             */
            public function make_glossary($course, $sourcecm, $glossary): array {
                $count = $this->add_glossary_exports($course, $sourcecm, $glossary, '');
                return ['count' => $count, 'files' => $this->textfiles];
            }
        };
        $export = $importer->make_glossary($course, $sourcecm, $glossary);
        $this->assertSame(2, $export['count']);
        $this->assertCount(2, $export['files']);
        $entryfilename = glossary_entry_helper::export_filename((int)$sourcecm->id, (int)$approved[0]);
        $this->assertArrayHasKey('/glossaries/' . $entryfilename, $export['files']);
        $content = $export['files']['/glossaries/' . $entryfilename]['content'];
        $this->assertStringContainsString('<h2>Beitrittserklärung für Mitglieder</h2>', $content);
        $this->assertStringContainsString('Formular für neue Mitglieder', $content);
        $this->assertStringContainsString('Direktlink zum Glossareintrag', $content);
        $this->assertStringContainsString('showentry.php?eid=' . $approved[0], $content);
        $this->assertStringNotContainsString('Versteckter Entwurf', implode(' ', array_column($export['files'], 'content')));

        // The FredKI document's source link and title must be specific to the entry.
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
        $entryurl = glossary_entry_helper::direct_url((int)$approved[0]);
        $metadata = $method->invoke(null, $file, $activity, $course, $entryurl);
        $this->assertSame('Beitrittserklärung für Mitglieder', $metadata['title']);
        $this->assertSame('Beitrittserklärung für Mitglieder', $metadata['documenttitle']);
        $this->assertSame($entryurl, $metadata['activitylink']);
    }
}
