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
 * PHPUnit tests: content hidden from participants is never transferred.
 *
 * @package    mod_fredkicourseimport
 * @copyright  2026 lernsteine.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_fredkicourseimport;

use mod_fredkicourseimport\local\course_content_importer;
use mod_fredkicourseimport\local\selection_manager;

/**
 * Hidden activities and activities in hidden sections are skipped even when selected.
 *
 * @covers \mod_fredkicourseimport\local\course_content_importer
 * @covers \mod_fredkicourseimport\local\selection_manager
 */
final class hidden_content_test extends \advanced_testcase {
    /**
     * Only the page visible to participants is exported.
     */
    public function test_hidden_content_is_not_exported(): void {
        global $CFG;
        require_once($CFG->dirroot . '/course/lib.php');
        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['numsections' => 2], ['createsections' => true]);
        $target = $generator->create_module('label', ['course' => $course->id, 'section' => 0]);
        $visible = $generator->create_module('page', ['course' => $course->id, 'section' => 1, 'name' => 'Visible page']);
        $hidden = $generator->create_module('page', ['course' => $course->id, 'section' => 1, 'name' => 'Hidden page']);
        $insection = $generator->create_module('page', ['course' => $course->id, 'section' => 2, 'name' => 'Page in hidden section']);
        set_coursemodule_visible($hidden->cmid, 0);
        set_section_visible($course->id, 2, 0);
        rebuild_course_cache($course->id, true);
        $course = get_course($course->id);

        $modinfo = get_fast_modinfo($course);
        $this->assertTrue(selection_manager::is_visible_for_participants($modinfo->get_cm($visible->cmid)));
        $this->assertFalse(selection_manager::is_visible_for_participants($modinfo->get_cm($hidden->cmid)));
        $this->assertFalse(selection_manager::is_visible_for_participants($modinfo->get_cm($insection->cmid)));

        $targetcm = get_coursemodule_from_id('label', $target->cmid, 0, false, MUST_EXIST);
        $selected = [(int)$visible->cmid, (int)$hidden->cmid, (int)$insection->cmid];
        $modes = array_fill_keys($selected, 'knowledge');
        $importer = new course_content_importer();
        $counts = $importer->import_course_content($course, $targetcm, (object)['id' => 987654], $selected, false, $modes);

        $this->assertSame(1, $counts['pages']);
        $files = get_file_storage()->get_area_files(
            \context_module::instance($target->cmid)->id,
            'mod_fredkicourseimport',
            'generated_files',
            0,
            'filename',
            false
        );
        $content = '';
        foreach ($files as $file) {
            $content .= $file->get_content();
        }
        $this->assertStringContainsString('Visible page', $content);
        $this->assertStringNotContainsString('Hidden page', $content);
        $this->assertStringNotContainsString('Page in hidden section', $content);
    }
}
