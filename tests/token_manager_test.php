<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace mod_fredkicourseimport;
final /**
 * Regression tests for token manager test.
 *
 * @copyright 2026 lernsteine.com
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class token_manager_test extends \advanced_testcase {
    public function test_missing_token_returns_null(): void {
        $this->resetAfterTest();
        $this->assertNull(\mod_fredkicourseimport\local\token_manager::get(999, 999));
    }
}
