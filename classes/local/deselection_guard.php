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
 * classes/local/deselection_guard.php for the FredKI CourseImport activity.
 *
 * @package    mod_fredkicourseimport
 * @copyright  2026 lernsteine.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_fredkicourseimport\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Explicitly confirm the exact source selection (and remote mapping snapshot) being revoked.
 * A missing generated export or an unrelated deleted mapping never grants deletion consent.
 */
final class deselection_guard {
    /**
     * Document the parameters and result.
     *
     * @param array $previous Parameter.
     * @param array $requested Parameter.
     * @param array $available Parameter.
     * @param bool $previoussections Parameter.
     * @param bool $requestedsections Parameter.
     * @return int[] Source CM ids removed from a saved selection, or 0 for section summaries.
     */
    public static function removed_sources(
        array $previous,
        array $requested,
        array $available,
        bool $previoussections,
        bool $requestedsections
    ): array {
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
     *
     * @param \stdClass $map Parameter.
     * @param array $removed Parameter.
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

    /**
     * Document the parameters and result.
     *
     * @param array $maps Parameter.
     * @param array $removed Parameter.
     * @return array{count:int,documents:array<int,string>}
     */
    public static function remote_documents(array $maps, array $removed): array {
        $scopes = array_fill_keys(array_map('intval', $removed), true);
        $documents = [];
        foreach ($maps as $map) {
            $id = (int)($map->id ?? 0);
            $source = (int)($map->sourcecmid ?? 0);
            $documentid = (string)($map->fredki_document_id ?? '');
            if (
                !$id || !isset($scopes[$source]) || !self::in_manual_scope($map, $removed) ||
                    $documentid === '' || (string)($map->status ?? '') === 'removed'
            ) {
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
     *
     * @param int $activityid Parameter.
     * @param int $userid Parameter.
     *
     * @param array $previous Parameter.
     * @param array $requested Parameter.
     *
     * @param array $available Parameter.
     * @param bool $previoussections Parameter.
     *
     * @param bool $requestedsections Parameter.
     * @param array $contentmodes Parameter.
     *
     * @param array $maps Parameter.
     * @param string $sesskey Parameter.
     */
    public static function approval_token(
        int $activityid,
        int $userid,
        array $previous,
        array $requested,
        array $available,
        bool $previoussections,
        bool $requestedsections,
        array $contentmodes,
        array $maps,
        string $sesskey
    ): string {
        $removed = self::removed_sources(
            $previous,
            $requested,
            $available,
            $previoussections,
            $requestedsections
        );
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
