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
 * Tests for search-oriented FredKI metadata without linked questions.
 *
 * @package    mod_fredkicourseimport
 * @copyright  2026 lernsteine.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_fredkicourseimport;

use mod_fredkicourseimport\local\sync_manager;

/**
 * Search metadata tests.
 */
final class sync_manager_search_metadata_test extends \advanced_testcase {
    /**
     * Test paragraphs get real titles without linked questions.
     */
    public function test_paragraphs_get_real_titles_without_linked_questions(): void {
        $manager = (new \ReflectionClass(sync_manager::class))->newInstanceWithoutConstructor();
        $method = new \ReflectionMethod(sync_manager::class, 'prepare_searchable_paragraphs');
        $method->setAccessible(true);

        $paragraphs = $method->invoke($manager, [
            [
                'title' => 'Basisinformationen',
                'content' => 'Materialtyp: Buch',
                'problem_list' => [['content' => 'Alte automatisch erzeugte Frage?']],
            ],
            [
                'title' => 'Inhalt',
                'content' => 'Weitere Informationen',
                'problem_list' => [['content' => 'Noch eine alte Frage?']],
            ],
        ], 'Wo ist mein Hut?');

        $this->assertSame('Wo ist mein Hut?', $paragraphs[0]['title']);
        $this->assertSame('Wo ist mein Hut? – Teil 2', $paragraphs[1]['title']);
        $this->assertArrayNotHasKey('problem_list', $paragraphs[0]);
        $this->assertArrayNotHasKey('problem_list', $paragraphs[1]);
    }

    /**
     * Test empty paragraph fallback has no linked questions.
     */
    public function test_empty_paragraph_fallback_has_no_linked_questions(): void {
        $manager = (new \ReflectionClass(sync_manager::class))->newInstanceWithoutConstructor();
        $method = new \ReflectionMethod(sync_manager::class, 'prepare_searchable_paragraphs');
        $method->setAccessible(true);

        $paragraphs = $method->invoke($manager, [], 'Ein Material');

        $this->assertCount(1, $paragraphs);
        $this->assertSame('Ein Material', $paragraphs[0]['title']);
        $this->assertArrayNotHasKey('problem_list', $paragraphs[0]);
    }
}
