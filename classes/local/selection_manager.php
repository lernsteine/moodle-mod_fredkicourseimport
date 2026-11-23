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
 * FredKI course import plugin file.
 *
 * @package    mod_fredkicourseimport
 * @copyright  2026 lernsteine.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_fredkicourseimport\local;

/**
 * Persists the trainer's course-content selection independently from generated files.
 *
 * sourcecmid = 0 is reserved for the course section texts option.
 */
class selection_manager {
    /** @var string[] Module names whose content can be imported. */
    private const SUPPORTED_MODNAMES = [
        'resource', 'folder', 'url', 'glossary', 'page', 'label', 'book',
        'lesson', 'h5pactivity', 'scorm',
    ];

    /**
     * Return all selectable source course-module IDs for the course.
     *
     * @param \stdClass $course Course record.
     * @param int $targetcmid Course module id of this FredKI activity.
     * @return int[]
     */
    public static function get_supported_source_cmids(\stdClass $course, int $targetcmid): array {
        $modinfo = get_fast_modinfo($course);
        $cmids = [];
        foreach ($modinfo->cms as $sourcecm) {
            if ((int)$sourcecm->id === $targetcmid || $sourcecm->deletioninprogress) {
                continue;
            }
            if (!in_array($sourcecm->modname, self::SUPPORTED_MODNAMES, true)) {
                continue;
            }
            $cmids[] = (int)$sourcecm->id;
        }
        return $cmids;
    }

    /**
     * Whether participants can currently see this course module.
     *
     * Hidden activities and activities in hidden sections are never transferred to
     * FredKI, independent of the saved selection and of the editing user's own rights.
     *
     * @param \cm_info $cm Course module.
     * @return bool
     */
    public static function is_visible_for_participants(\cm_info $cm): bool {
        if (empty($cm->visible)) {
            return false;
        }
        $section = $cm->get_section_info();
        return !$section || !empty($section->visible);
    }

    /**
     * Records keyed by sourcecmid. An empty result means no explicit selection has been saved yet.
     *
     * @param int $activityid FredKI course import activity instance id.
     * @return array<int,\stdClass>
     */
    public static function get_records(int $activityid): array {
        global $DB;
        $records = $DB->get_records('fredkicourseimport_selection', ['activityid' => $activityid]);
        $result = [];
        foreach ($records as $record) {
            $result[(int)$record->sourcecmid] = $record;
        }
        return $result;
    }

    /**
     * Persist every selectable checkbox, including unchecked items.
     *
     * @param \stdClass $course Course record.
     * @param int $targetcmid Course module id of this FredKI activity.
     * @param int $activityid FredKI course import activity instance id.
     * @param array $selectedcmids Selected source course module ids.
     * @param bool $includesectiontexts Whether section texts are included.
     * @param array $contentmodes Content mode per source course module id.
     */
    public static function save(
        \stdClass $course,
        int $targetcmid,
        int $activityid,
        array $selectedcmids,
        bool $includesectiontexts,
        array $contentmodes
    ): void {
        global $DB;

        $selected = array_fill_keys(array_map('intval', $selectedcmids), true);
        $now = time();
        $cmids = self::get_supported_source_cmids($course, $targetcmid);

        // Section texts use sourcecmid 0.
        self::upsert($activityid, 0, $includesectiontexts, 'reference', $now);

        foreach ($cmids as $sourcecmid) {
            $mode = (($contentmodes[$sourcecmid] ?? 'reference') === 'knowledge') ? 'knowledge' : 'reference';
            self::upsert($activityid, $sourcecmid, isset($selected[$sourcecmid]), $mode, $now);
        }

        // Remove preferences for course modules that no longer exist/selectable.
        $validcmids = array_fill_keys(array_merge([0], $cmids), true);
        $existing = $DB->get_records('fredkicourseimport_selection', ['activityid' => $activityid]);
        foreach ($existing as $record) {
            if (!isset($validcmids[(int)$record->sourcecmid])) {
                $DB->delete_records('fredkicourseimport_selection', ['id' => $record->id]);
            }
        }
    }

    /**
     * Resolve the effective selection: the single rule used by the selection page and by every sync.
     *
     * - A saved choice of the trainer always wins, also for content hidden from participants.
     *   A hidden activity is therefore transferred only if the trainer ticked it, and the tick
     *   stays until the trainer removes it.
     * - Without a saved choice (new activity, or no selection saved yet), an activity is selected
     *   only if it is visible to participants. Hidden content is never selected by default.
     *
     * @param \stdClass $course Course record.
     * @param int $targetcmid Course module id of this FredKI activity.
     * @param int $activityid FredKI course import activity instance id.
     * @return array{selected:array<int,bool>,selectedcmids:int[],includesectiontexts:bool,contentmodes:array<int,string>}
     */
    public static function resolve(\stdClass $course, int $targetcmid, int $activityid): array {
        global $DB;

        $records = self::get_records($activityid);

        // Content modes and section texts of installations that never saved a selection
        // are taken from what is currently active in FredKI.
        $mapmodes = [];
        $mapsectiontexts = null;
        if (!$records) {
            $maps = $DB->get_records('fredkicourseimport_map', ['activityid' => $activityid], 'timemodified ASC, id ASC');
            foreach ($maps as $map) {
                $sourcecmid = (int)($map->sourcecmid ?? 0);
                $isactive = !in_array((string)$map->status, ['deleted', 'removed'], true);
                if ($sourcecmid === 0) {
                    $mapsectiontexts = $mapsectiontexts || $isactive;
                } else {
                    $mapmodes[$sourcecmid] = ($map->contentmode ?? 'reference') === 'knowledge' ? 'knowledge' : 'reference';
                }
            }
        }

        $modinfo = get_fast_modinfo($course);
        $selected = [];
        $contentmodes = [];
        foreach (self::get_supported_source_cmids($course, $targetcmid) as $sourcecmid) {
            if (isset($records[$sourcecmid])) {
                $selected[$sourcecmid] = !empty($records[$sourcecmid]->selected);
                $mode = $records[$sourcecmid]->contentmode ?? 'reference';
            } else {
                $selected[$sourcecmid] = self::is_visible_for_participants($modinfo->get_cm($sourcecmid));
                $mode = $mapmodes[$sourcecmid] ?? 'reference';
            }
            $contentmodes[$sourcecmid] = $mode === 'knowledge' ? 'knowledge' : 'reference';
        }

        if (isset($records[0])) {
            $includesectiontexts = !empty($records[0]->selected);
        } else {
            $includesectiontexts = $mapsectiontexts ?? true;
        }

        $selectedcmids = array_keys(array_filter($selected));
        sort($selectedcmids);
        return compact('selected', 'selectedcmids', 'includesectiontexts', 'contentmodes');
    }

    /**
     * Upsert.
     *
     * @param int $activityid The activity id.
     * @param int $sourcecmid The sourcecmid.
     * @param bool $selected The selected.
     * @param string $contentmode The contentmode.
     * @param int $now The now.
     */
    private static function upsert(
        int $activityid,
        int $sourcecmid,
        bool $selected,
        string $contentmode,
        int $now
    ): void {
        global $DB;

        $existing = $DB->get_record('fredkicourseimport_selection', [
            'activityid' => $activityid,
            'sourcecmid' => $sourcecmid,
        ]);
        if ($existing) {
            $existing->selected = $selected ? 1 : 0;
            $existing->contentmode = $contentmode;
            $existing->timemodified = $now;
            $DB->update_record('fredkicourseimport_selection', $existing);
            return;
        }

        $DB->insert_record('fredkicourseimport_selection', (object)[
            'activityid' => $activityid,
            'sourcecmid' => $sourcecmid,
            'selected' => $selected ? 1 : 0,
            'contentmode' => $contentmode,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
    }
}
