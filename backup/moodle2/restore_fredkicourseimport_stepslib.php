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
 * Restore structure for FredKI course import.
 *
 * @package    mod_fredkicourseimport
 * @copyright  2026 lernsteine.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Restore fredkicourseimport activity structure step class.
 *
 * @package    mod_fredkicourseimport
 * @copyright  2026 lernsteine.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_fredkicourseimport_activity_structure_step extends restore_activity_structure_step {
    /**
     * Define structure.
     */
    protected function define_structure() {
        return [
            new restore_path_element('fredkicourseimport', '/activity/fredkicourseimport'),
            new restore_path_element('fredkicourseimport_map', '/activity/fredkicourseimport/maps/map'),
        ];
    }
    /**
     * Process fredkicourseimport.
     *
     * @param mixed $data The form data.
     */
    protected function process_fredkicourseimport($data) {
        global $DB;
        $data = (object)$data;
        $data->course = $this->get_courseid();
        $data->authorizedbyuserid = null;
        $data->knowledgeid = '';
        $data->knowledgebasename = null;
        $data->timecreated = time();
        $data->timemodified = time();
        $newid = $DB->insert_record('fredkicourseimport', $data);
        $this->apply_activity_instance($newid);
    }
    /**
     * Process fredkicourseimport map.
     *
     * @param mixed $data The form data.
     */
    protected function process_fredkicourseimport_map($data) {
        global $DB;
        $data = (object)$data;
        $data->activityid = $this->get_new_parentid('fredkicourseimport');
        $data->cmid = 0;
        $data->pathnamehash = sha1('restore:' . $data->activityid . ':' . $data->filename . ':' . $data->id);
        $data->fredki_document_id = null;
        $data->fredki_source_file_id = null;
        $data->status = 'pending';
        $data->lastresult = 'created';
        $data->lasterror = null;
        $data->timesynced = null;
        unset($data->id);
        $DB->insert_record('fredkicourseimport_map', $data);
    }
    /**
     * After execute.
     */
    protected function after_execute() {
        $this->add_related_files('mod_fredkicourseimport', 'generated_files', null);
    }
}
