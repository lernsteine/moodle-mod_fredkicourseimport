<?php
/**
 * Tests for Moodle section hierarchy formatting.
 *
 * @package    mod_fredkicourseimport
 * @copyright  2026 lernsteine.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_fredkicourseimport;

use mod_fredkicourseimport\local\section_hierarchy_helper;

final class section_hierarchy_helper_test extends \advanced_testcase {
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

    public function test_empty_parent_path_stays_empty(): void {
        $this->assertSame('', section_hierarchy_helper::format_parents([]));
    }
}
