<?php
// This file is part of FredKI CourseImport for Moodle.
// Copyright (c) 2026 lernsteine.com
// SPDX-License-Identifier: GPL-3.0-or-later

namespace mod_fredkicourseimport\task;

defined('MOODLE_INTERNAL') || die();

/**
 * Conservative reconciliation of physical Moodle deletions, never visibility.
 * Fail-closed for uncertain sources and bulk deletions.
 */
final class reconcile_deleted_sources extends \core\task\scheduled_task {
    public function get_name(): string {
        return get_string('taskreconciledeleted', 'mod_fredkicourseimport');
    }

    public function execute(): void {
        global $DB;
        // Explicit upper bound: if we cannot inspect the whole bounded register,
        // do nothing. This prevents processing a small subset of a mass deletion.
        $rows = $DB->get_records_select('fredkicourseimport_map',
            "fredki_document_id IS NOT NULL AND fredki_document_id <> :empty " .
            "AND status IN (:synced, :deleted, :pending, :error)",
            ['empty' => '', 'synced' => 'synced', 'deleted' => 'deleted',
             'pending' => 'pending', 'error' => 'error'],
            'activityid ASC, id ASC', '*', 0, 5001);
        if (count($rows) > 5000) {
            debugging('FredKI deletion reconciliation deferred: register exceeds safety limit', DEBUG_DEVELOPER);
            return;
        }
        $candidates = [];
        $activities = [];
        $blocked = [];
        // Verify the entire snapshot BEFORE sending the first delete request.
        foreach ($rows as $row) {
            $activityid = (int)$row->activityid;
            if (isset($blocked[$activityid])) {
                continue;
            }
            try {
                if (!isset($activities[$activityid])) {
                    $activities[$activityid] = $DB->get_record('fredkicourseimport', ['id' => $activityid]);
                }
                $activity = $activities[$activityid];
                if (!$activity || empty($activity->knowledgeid)) {
                    continue;
                }
                if (\mod_fredkicourseimport\local\source_deletion_guard::original_missing($activity, $row)) {
                    $candidates[$activityid][] = (int)$row->id;
                }
            } catch (\Throwable $e) {
                // If one source check fails, do not delete *any* documents from this activity.
                $blocked[$activityid] = true;
                debugging('FredKI source verification failed for activity ' . $activityid . ': ' .
                    $e->getMessage(), DEBUG_DEVELOPER);
            }
        }
        foreach ($candidates as $activityid => $ids) {
            if (isset($blocked[$activityid]) ||
                    !\mod_fredkicourseimport\local\source_deletion_guard::automatic_batch_allowed(
                        count($ids), count($rows)
                    )) {
                // Persist a visible warning; all remote IDs and mapping statuses remain intact.
                foreach ($ids as $mapid) {
                    $DB->set_field('fredkicourseimport_map', 'lastresult', 'error', ['id' => $mapid]);
                    $DB->set_field('fredkicourseimport_map', 'lasterror',
                        get_string('deletionbatchblocked', 'mod_fredkicourseimport', count($ids)),
                        ['id' => $mapid]);
                }
                debugging('FredKI bulk removal blocked for activity ' . $activityid .
                    ', candidates: ' . count($ids), DEBUG_DEVELOPER);
                continue;
            }
            $activity = $activities[$activityid];
            $factory = \core\lock\lock_config::get_lock_factory('mod_fredkicourseimport_sync');
            $lock = $factory->get_lock('activity:' . $activityid, 0, 120);
            if (!$lock) {
                continue;
            }
            try {
                foreach ($ids as $mapid) {
                    $current = $DB->get_record('fredkicourseimport_map', ['id' => $mapid]);
                    if (!$current || (int)$current->activityid !== $activityid ||
                            empty($current->fredki_document_id)) {
                        continue;
                    }
                    try {
                        if (!\mod_fredkicourseimport\local\source_deletion_guard::original_missing(
                                $activity, $current)) {
                            continue;
                        }
                        // Only the individual, previously mapped document ID may be removed.
                        $current->status = 'deleted';
                        $DB->update_record('fredkicourseimport_map', $current);
                        \mod_fredkicourseimport\local\manual_sync_service::delete_record($activity, $current);
                    } catch (\Throwable $e) {
                        debugging('FredKI verified removal deferred for map ' . $mapid . ': ' .
                            $e->getMessage(), DEBUG_DEVELOPER);
                    }
                }
            } finally {
                $lock->release();
            }
        }
    }
}
