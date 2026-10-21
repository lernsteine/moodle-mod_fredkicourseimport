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
 * tests/source_deletion_guard_test.php for the FredKI CourseImport activity.
 *
 * @package    mod_fredkicourseimport
 * @copyright  2026 lernsteine.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_fredkicourseimport;

defined('MOODLE_INTERNAL') || die();

/** The default protection must never authorise a 112-document mass deletion. */
final class source_deletion_guard_test extends \advanced_testcase {
    public function test_destructive_batch_is_bounded(): void {
        $this->assertTrue(\mod_fredkicourseimport\local\source_deletion_guard::automatic_batch_allowed(1, 112));
        $this->assertTrue(\mod_fredkicourseimport\local\source_deletion_guard::automatic_batch_allowed(3, 112));
        $this->assertFalse(\mod_fredkicourseimport\local\source_deletion_guard::automatic_batch_allowed(4, 112));
        $this->assertFalse(\mod_fredkicourseimport\local\source_deletion_guard::automatic_batch_allowed(112, 112));
    }
    public function test_section_summary_missing_never_authorises_delete(): void {
        $activity = (object)['course' => 1];
        $section = (object)['sourcecmid' => 0, 'filename' => 'abschnitt-1.html', 'timesynced' => time()];
        $this->assertFalse(\mod_fredkicourseimport\local\source_deletion_guard::original_missing(
            $activity, $section
        ));
    }
}
