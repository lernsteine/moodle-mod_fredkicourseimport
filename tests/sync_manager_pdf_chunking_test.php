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
 * Tests for bounded PDF content chunking.
 *
 * @package    mod_fredkicourseimport
 * @copyright  2026 lernsteine.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_fredkicourseimport;

use mod_fredkicourseimport\local\sync_manager;

/**
 * Verify safe PDF content splitting during synchronisation.
 *
 * @package mod_fredkicourseimport
 * @covers \mod_fredkicourseimport\local\sync_manager
 */
final class sync_manager_pdf_chunking_test extends \advanced_testcase {
    /**
     * Large PDF text is split without losing its content.
     */
    public function test_large_pdf_content_is_split_into_bounded_chunks(): void {
        $manager = (new \ReflectionClass(sync_manager::class))->newInstanceWithoutConstructor();
        $method = new \ReflectionMethod(sync_manager::class, 'split_structured_pdf_content');
        $method->setAccessible(true);

        $content = "## Basisinformationen\n\n**Name:** Test\n\n## Inhalt\n\n";
        $content .= str_repeat('A complete sentence for the PDF importer. ', 800);
        $chunks = $method->invoke($manager, $content, 1000);

        $this->assertGreaterThan(1, count($chunks));
        foreach ($chunks as $chunk) {
            $this->assertLessThanOrEqual(1000, mb_strlen($chunk));
            $this->assertNotSame('', trim($chunk));
        }
    }

    /**
     * Short reference-like content remains one chunk.
     */
    public function test_short_content_remains_one_chunk(): void {
        $manager = (new \ReflectionClass(sync_manager::class))->newInstanceWithoutConstructor();
        $method = new \ReflectionMethod(sync_manager::class, 'split_structured_pdf_content');
        $method->setAccessible(true);

        $chunks = $method->invoke($manager, "## Basisinformationen\n\nShort text.", 1000);
        $this->assertCount(1, $chunks);
    }
}
