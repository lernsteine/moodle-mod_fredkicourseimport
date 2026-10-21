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
 * tests/deselection_guard_test.php for the FredKI CourseImport activity.
 *
 * @package    mod_fredkicourseimport
 * @copyright  2026 lernsteine.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_fredkicourseimport;

defined('MOODLE_INTERNAL') || die();

use mod_fredkicourseimport\local\deselection_guard;

/** Test that human confirmation is scoped to concrete deselected sources. */
final class deselection_guard_test extends \advanced_testcase {
    public function test_deselecting_one_source_does_not_authorise_other_sources(): void {
        $this->assertSame([15], deselection_guard::removed_sources([14, 15, 16], [14, 16],
            [14, 15, 16], false, false));
        $maps = [(object)['id' => 1, 'sourcecmid' => 14, 'fredki_document_id' => 'keep', 'status' => 'synced'],
                 (object)['id' => 2, 'sourcecmid' => 15, 'fredki_document_id' => 'delete', 'status' => 'synced']];
        $this->assertSame(['2' => 'delete'], array_map('strval',
            deselection_guard::remote_documents($maps, [15])['documents']));
    }

    public function test_missing_course_module_does_not_trigger_manual_deselection(): void {
        $this->assertSame([], deselection_guard::removed_sources([17], [], [10, 11], false, false));
    }

    public function test_section_text_removal_is_independent(): void {
        $this->assertSame([0], deselection_guard::removed_sources([5], [5], [5], true, false));
    }

    public function test_unmapped_legacy_files_are_not_section_summaries(): void {
        $unmapped = (object)['sourcecmid' => 0, 'filename' => 'unknown.html'];
        $section = (object)['sourcecmid' => 0, 'filename' => 'abschnitt-4.html'];
        $this->assertFalse(deselection_guard::in_manual_scope($unmapped, [0]));
        $this->assertTrue(deselection_guard::in_manual_scope($section, [0]));
    }

    public function test_approval_rejects_changed_mappings_or_modes(): void {
        $maps = [(object)['id' => 3, 'sourcecmid' => 15, 'fredki_document_id' => 'old', 'status' => 'synced']];
        $create = static fn(array $mappings, array $modes) => deselection_guard::approval_token(
            2, 7, [14, 15], [14], [14, 15], false, false, $modes, $mappings, 'test-session-key');
        $expected = $create($maps, [14 => 'reference', 15 => 'knowledge']);
        $this->assertSame($expected, $create($maps, [14 => 'reference', 15 => 'knowledge']));
        $this->assertNotSame($expected, $create($maps, [14 => 'knowledge', 15 => 'knowledge']));
        $maps[0]->fredki_document_id = 'new';
        $this->assertNotSame($expected, $create($maps, [14 => 'reference', 15 => 'knowledge']));
    }
}
