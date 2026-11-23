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
 * Hidden content is transferred only when the trainer ticked it, and such ticks persist.
 *
 * @covers \mod_fredkicourseimport\local\course_content_importer
 * @covers \mod_fredkicourseimport\local\selection_manager
 */
final class hidden_content_test extends \advanced_testcase {
    /**
     * Build a course with a visible page, a hidden page and a page in a hidden section.
     *
     * @return array
     */
    private function make_course(): array {
        global $CFG;
        require_once($CFG->dirroot . '/course/lib.php');
        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['numsections' => 2], ['createsections' => true]);
        $target = $generator->create_module('label', ['course' => $course->id, 'section' => 0]);
        $visible = $generator->create_module('page', ['course' => $course->id, 'section' => 1, 'name' => 'Visible page']);
        $hidden = $generator->create_module('page', ['course' => $course->id, 'section' => 1, 'name' => 'Hidden page']);
        $insection = $generator->create_module('page', ['course' => $course->id, 'section' => 2, 'name' => 'Page in hidden section']);
        set_coursemodule_visible($hidden->cmid, 0);
        set_section_visible($course->id, 2, 0);
        rebuild_course_cache($course->id, true);
        return [get_course($course->id), (int)$target->cmid, (int)$visible->cmid, (int)$hidden->cmid, (int)$insection->cmid];
    }

    /**
     * Export with the given selection and return the generated content.
     *
     * @param \stdClass $course Course record.
     * @param int $targetcmid Target course module id.
     * @param int $activityid Activity id.
     * @param array|null $selected Selected course module ids, or null for no explicit selection.
     * @return string
     */
    private function export(\stdClass $course, int $targetcmid, int $activityid, ?array $selected): string {
        $targetcm = get_coursemodule_from_id('label', $targetcmid, 0, false, MUST_EXIST);
        $importer = new course_content_importer();
        $importer->import_course_content($course, $targetcm, (object)['id' => $activityid], $selected, false, []);
        $files = get_file_storage()->get_area_files(
            \context_module::instance($targetcmid)->id,
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
        return $content;
    }

    /**
     * Without a saved selection, only content visible to participants is selected and exported.
     */
    public function test_default_selection_skips_hidden_content(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        [$course, $targetcmid, $visible, $hidden, $insection] = $this->make_course();

        $modinfo = get_fast_modinfo($course);
        $this->assertTrue(selection_manager::is_visible_for_participants($modinfo->get_cm($visible)));
        $this->assertFalse(selection_manager::is_visible_for_participants($modinfo->get_cm($hidden)));
        $this->assertFalse(selection_manager::is_visible_for_participants($modinfo->get_cm($insection)));

        $resolved = selection_manager::resolve($course, $targetcmid, 4711);
        $this->assertSame([$visible], $resolved['selectedcmids']);

        $content = $this->export($course, $targetcmid, 4711, $resolved['selectedcmids']);
        $this->assertStringContainsString('Visible page', $content);
        $this->assertStringNotContainsString('Hidden page', $content);
        $this->assertStringNotContainsString('Page in hidden section', $content);

        // No explicit selection at all: hidden content is skipped as well.
        $content = $this->export($course, $targetcmid, 4711, null);
        $this->assertStringNotContainsString('Hidden page', $content);
    }

    /**
     * A hidden activity ticked by the trainer is exported and stays ticked for later syncs.
     */
    public function test_ticked_hidden_content_is_exported_and_persists(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        [$course, $targetcmid, $visible, $hidden, $insection] = $this->make_course();

        selection_manager::save($course, $targetcmid, 4712, [$visible, $hidden], false, []);

        // "Update changes" resolves the same saved selection again.
        $resolved = selection_manager::resolve($course, $targetcmid, 4712);
        $this->assertSame([$visible, $hidden], $resolved['selectedcmids']);
        $this->assertFalse($resolved['selected'][$insection]);

        $content = $this->export($course, $targetcmid, 4712, $resolved['selectedcmids']);
        $this->assertStringContainsString('Visible page', $content);
        $this->assertStringContainsString('Hidden page', $content);
        $this->assertStringNotContainsString('Page in hidden section', $content);

        // Activities added after the last save follow the default rule; existing ticks stay.
        $generator = $this->getDataGenerator();
        $newvisible = $generator->create_module('page', ['course' => $course->id, 'section' => 1]);
        $newhidden = $generator->create_module('page', ['course' => $course->id, 'section' => 1, 'visible' => 0]);
        $resolved = selection_manager::resolve(get_course($course->id), $targetcmid, 4712);
        $this->assertTrue($resolved['selected'][$hidden]);
        $this->assertTrue($resolved['selected'][(int)$newvisible->cmid]);
        $this->assertFalse($resolved['selected'][(int)$newhidden->cmid]);
    }
}
