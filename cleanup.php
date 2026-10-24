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
 * Read-only register of remote documents needing administrative review.
 *
 * @package    mod_fredkicourseimport
 * @copyright  2026 lernsteine.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_login();
require_capability('moodle/site:config', \context_system::instance());

$PAGE->set_context(\context_system::instance());
$PAGE->set_url(new moodle_url('/mod/fredkicourseimport/cleanup.php'));
$PAGE->set_title(get_string('cleanupregister', 'mod_fredkicourseimport'));
$PAGE->set_heading(get_string('cleanupregister', 'mod_fredkicourseimport'));
$PAGE->set_pagelayout('admin');

$records = $DB->get_records('fredkicourseimport_cleanup', [], 'timecreated DESC', '*', 0, 1000);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('cleanupregister', 'mod_fredkicourseimport'));
echo $OUTPUT->notification(get_string('cleanupregisterwarning', 'mod_fredkicourseimport'), 'warning');

$table = new html_table();
$table->head = [
    get_string('cleanupcourseid', 'mod_fredkicourseimport'),
    get_string('cleanupworkspace', 'mod_fredkicourseimport'),
    get_string('cleanupknowledgeid', 'mod_fredkicourseimport'),
    get_string('cleanupdocumentid', 'mod_fredkicourseimport'),
    get_string('cleanupregistered', 'mod_fredkicourseimport'),
];
foreach ($records as $record) {
    $table->data[] = [
        (int)$record->courseid,
        s($record->workspace),
        s($record->knowledgeid),
        s($record->documentid),
        userdate($record->timecreated),
    ];
}
if ($records) {
    echo html_writer::table($table);
} else {
    echo $OUTPUT->notification(get_string('cleanupempty', 'mod_fredkicourseimport'), 'info');
}
echo $OUTPUT->footer();
