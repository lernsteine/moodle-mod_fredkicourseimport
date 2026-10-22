<?php
// This file is part of FredKI CourseImport for Moodle.
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
// along with this program. If not, see <https://www.gnu.org/licenses/>.
//
// SPDX-License-Identifier: GPL-3.0-or-later

/** Backup structure for FredKI course import.
 * @package mod_fredkicourseimport
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_fredkicourseimport_activity_structure_step extends backup_activity_structure_step {
    protected function define_structure() {
        $activity = new backup_nested_element('fredkicourseimport', ['id'], [
            // Never copy external knowledge mappings or public chat tokens to another course/site.
            // Editors must explicitly reconnect and choose the FredKI target after restore.
            'name', 'intro', 'introformat', 'agentcoursevisible', 'syncenabled', 'timecreated', 'timemodified',
        ]);
        $maps = new backup_nested_element('maps');
        $map = new backup_nested_element('map', ['id'], [
            'filename', 'contenthash', 'filesize', 'contentmode', 'status', 'lastresult', 'timecreated', 'timemodified', 'timesynced',
        ]);
        $activity->add_child($maps);
        $maps->add_child($map);
        $activity->set_source_table('fredkicourseimport', ['id' => backup::VAR_ACTIVITYID]);
        $map->set_source_table('fredkicourseimport_map', ['activityid' => backup::VAR_PARENTID]);
        $activity->annotate_files('mod_fredkicourseimport', 'generated_files', null);
        return $this->prepare_activity_structure($activity);
    }
}
