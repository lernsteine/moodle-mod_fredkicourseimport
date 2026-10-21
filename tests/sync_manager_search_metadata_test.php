<?php
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
