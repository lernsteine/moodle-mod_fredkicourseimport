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
 * Tests for the privacy provider of mod_fredkicourseimport.
 *
 * @package    mod_fredkicourseimport
 * @copyright  2026 lernsteine.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_fredkicourseimport\privacy;

use core_privacy\local\metadata\collection;

/**
 * Privacy provider tests.
 *
 * @covers \mod_fredkicourseimport\privacy\provider
 */
final class provider_test extends \advanced_testcase {
    /**
     * The provider declares the stored tables and the external FredKI service.
     */
    public function test_get_metadata(): void {
        $collection = provider::get_metadata(new collection('mod_fredkicourseimport'));
        $names = array_map(static fn($item) => $item->get_name(), $collection->get_collection());

        $this->assertContains('fredkicourseimport', $names);
        $this->assertContains('fredkicourseimport_auth', $names);
        $this->assertContains('fredki', $names);
    }
}
