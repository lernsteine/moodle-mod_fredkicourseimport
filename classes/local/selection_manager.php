<?php
// This file is part of FredKI CourseImport for Moodle.
// Copyright (c) 2026 lernsteine.com
//
// This program is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
// See <https://www.gnu.org/licenses/>.

namespace mod_fredkicourseimport\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Persists the trainer's course-content selection independently from generated files.
 *
 * sourcecmid = 0 is reserved for the course section texts option.
 */
class selection_manager {
    private const SUPPORTED_MODNAMES = [
        'resource', 'folder', 'url', 'glossary', 'page', 'label', 'book',
        'lesson', 'h5pactivity', 'scorm',
    ];

    /**
     * Return all selectable source course-module IDs for the course.
     *
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
     * Records keyed by sourcecmid. An empty result means no explicit selection has been saved yet.
     *
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
     * Resolve the selection used by the one-click "update changes" action.
     *
     * Explicit selection records take precedence. For upgraded installations that have not
     * saved a selection yet, active mapping records are used as a conservative fallback.
     *
     * @return array{selectedcmids:int[],includesectiontexts:bool,contentmodes:array<int,string>}
     */
    public static function get_current_selection(int $activityid): array {
        global $DB;

        $records = self::get_records($activityid);
        if ($records) {
            $selectedcmids = [];
            $contentmodes = [];
            $includesectiontexts = false;
            foreach ($records as $sourcecmid => $record) {
                if ($sourcecmid === 0) {
                    $includesectiontexts = !empty($record->selected);
                    continue;
                }
                $contentmodes[$sourcecmid] = ($record->contentmode ?? 'reference') === 'knowledge'
                    ? 'knowledge'
                    : 'reference';
                if (!empty($record->selected)) {
                    $selectedcmids[] = $sourcecmid;
                }
            }
            sort($selectedcmids);
            return compact('selectedcmids', 'includesectiontexts', 'contentmodes');
        }

        // Upgrade fallback: preserve what is currently active in FredKI; do not silently add
        // course activities that have never been selected before.
        $maps = $DB->get_records('fredkicourseimport_map', ['activityid' => $activityid], 'timemodified ASC, id ASC');
        $selectedstate = [];
        $contentmodes = [];
        foreach ($maps as $map) {
            $sourcecmid = (int)($map->sourcecmid ?? 0);
            $isactive = !in_array((string)$map->status, ['deleted', 'removed'], true);
            if (!isset($selectedstate[$sourcecmid])) {
                $selectedstate[$sourcecmid] = false;
            }
            if ($isactive) {
                $selectedstate[$sourcecmid] = true;
            }
            if ($sourcecmid > 0) {
                $contentmodes[$sourcecmid] = ($map->contentmode ?? 'reference') === 'knowledge'
                    ? 'knowledge'
                    : 'reference';
            }
        }
        $selectedcmids = [];
        foreach ($selectedstate as $sourcecmid => $isselected) {
            if ($sourcecmid > 0 && $isselected) {
                $selectedcmids[] = (int)$sourcecmid;
            }
        }
        sort($selectedcmids);
        $includesectiontexts = !empty($selectedstate[0]);
        return compact('selectedcmids', 'includesectiontexts', 'contentmodes');
    }

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
