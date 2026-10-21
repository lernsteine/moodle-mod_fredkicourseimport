<?php
// This file is part of FredKI CourseImport for Moodle.
// Copyright (c) 2026 lernsteine.com
// SPDX-License-Identifier: GPL-3.0-or-later

namespace mod_fredkicourseimport\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Explicitly confirm the exact source selection (and remote mapping snapshot) being revoked.
 * A missing generated export or an unrelated deleted mapping never grants deletion consent.
 */
final class deselection_guard {
    /** @return int[] Source CM ids removed from a saved selection, or 0 for section summaries. */
    public static function removed_sources(array $previous, array $requested, array $available,
            bool $previoussections, bool $requestedsections): array {
        $supported = array_fill_keys(array_map('intval', $available), true);
        $requestedset = array_fill_keys(array_map('intval', $requested), true);
        $removed = [];
        foreach (array_unique(array_map('intval', $previous)) as $id) {
            // A physically deleted Moodle course module is handled separately by the deletion task.
            if ($id > 0 && isset($supported[$id]) && !isset($requestedset[$id])) {
                $removed[] = $id;
            }
        }
        if ($previoussections && !$requestedsections) {
            $removed[] = 0;
        }
        sort($removed, SORT_NUMERIC);
        return $removed;
    }

    /**
     * A sourcecmid of zero is reserved for section summaries, but legacy imports can
     * contain unmapped files with the same value. Never approve those by accident.
     */
    public static function in_manual_scope(\stdClass $map, array $removed): bool {
        $sourceid = (int)($map->sourcecmid ?? 0);
        if (!in_array($sourceid, $removed, true)) {
            return false;
        }
        if ($sourceid === 0) {
            return (bool)preg_match('/^abschnitt-[0-9]+\.html$/D', (string)($map->filename ?? ''));
        }
        return true;
    }

    /** @return array{count:int,documents:array<int,string>} */
    public static function remote_documents(array $maps, array $removed): array {
        $scopes = array_fill_keys(array_map('intval', $removed), true);
        $documents = [];
        foreach ($maps as $map) {
            $id = (int)($map->id ?? 0);
            $source = (int)($map->sourcecmid ?? 0);
            $documentid = (string)($map->fredki_document_id ?? '');
            if (!$id || !isset($scopes[$source]) || !self::in_manual_scope($map, $removed) ||
                    $documentid === '' || (string)($map->status ?? '') === 'removed') {
                continue;
            }
            $documents[$id] = $documentid;
        }
        ksort($documents, SORT_NUMERIC);
        return ['count' => count($documents), 'documents' => $documents];
    }

    /**
     * Bound confirmation to the course activity, user, previous and requested selection,
     * content modes, and specific remote document identifiers. Prevents stale approvals.
     */
    public static function approval_token(int $activityid, int $userid, array $previous,
            array $requested, array $available, bool $previoussections, bool $requestedsections,
            array $contentmodes, array $maps, string $sesskey): string {
        $removed = self::removed_sources($previous, $requested, $available,
            $previoussections, $requestedsections);
        $modes = [];
        foreach ($contentmodes as $sourceid => $mode) {
            $modes[(int)$sourceid] = $mode === 'knowledge' ? 'knowledge' : 'reference';
        }
        ksort($modes, SORT_NUMERIC);
        $previous = array_values(array_unique(array_map('intval', $previous)));
        $requested = array_values(array_unique(array_map('intval', $requested)));
        sort($previous, SORT_NUMERIC);
        sort($requested, SORT_NUMERIC);
        $state = [
            'activityid' => $activityid, 'userid' => $userid,
            'previous' => $previous, 'requested' => $requested,
            'previoussections' => $previoussections, 'requestedsections' => $requestedsections,
            'removed' => $removed, 'modes' => $modes,
            'remote' => self::remote_documents($maps, $removed)['documents'],
        ];
        return hash_hmac('sha256', json_encode($state, JSON_THROW_ON_ERROR), $sesskey);
    }
}
