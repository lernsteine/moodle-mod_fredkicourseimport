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
 * Tests for Moodle section hierarchy formatting.
 *
 * @package    mod_fredkicourseimport
 * @copyright  2026 lernsteine.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_fredkicourseimport;

use mod_fredkicourseimport\local\section_hierarchy_helper;

/**
 * Verify section hierarchy metadata generation.
 *
 * @package mod_fredkicourseimport
 * @covers \mod_fredkicourseimport\local\section_hierarchy_helper
 */
final class section_hierarchy_helper_test extends \advanced_testcase {
    /**
     * Test parent path is compact and ordered for FredKI CourseImport.
     */
    public function test_parent_path_is_compact_and_ordered(): void {
        $this->assertSame(
            'Büchertipps › Bücher für Grundschulkinder',
            section_hierarchy_helper::format_parents([
                ' Büchertipps ',
                '',
                'Bücher für Grundschulkinder',
            ])
        );
    }

    /**
     * Test empty parent path stays empty for FredKI CourseImport.
     */
    public function test_empty_parent_path_stays_empty(): void {
        $this->assertSame('', section_hierarchy_helper::format_parents([]));
    }
}
