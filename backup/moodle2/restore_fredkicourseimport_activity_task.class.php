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

class restore_fredkicourseimport_activity_task extends restore_activity_task {
    protected function define_my_settings() {
    }
    protected function define_my_steps() {
        $this->add_step(new restore_fredkicourseimport_activity_structure_step('fredkicourseimport_structure', 'fredkicourseimport.xml'));
    }
    public static function define_decode_contents() {
        return [];
    }
    public static function define_decode_rules() {
        return [];
    }
}
