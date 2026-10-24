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
 * Resolve Moodle section/subsection hierarchy for searchable FredKI metadata.
 *
 * Moodle 4.5 introduced delegated sections for mod_subsection. Activities inside
 * such a subsection point to the delegated course section; that section in turn
 * identifies the subsection instance via component/itemid. By following the
 * subsection course module back to its own parent section we can reconstruct the
 * visible heading hierarchy without relying on a particular course-format plugin.
 *
 * @package    mod_fredkicourseimport
 * @copyright  2026 lernsteine.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_fredkicourseimport\local;

/**
 * Section hierarchy helper class.
 *
 * @package    mod_fredkicourseimport
 * @copyright  2026 lernsteine.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class section_hierarchy_helper {
    /**
     * Return the section hierarchy for one course module.
     *
     * @param \stdClass $course Course record.
     * @param int $cmid Course-module id.
     * @return array{current:string,parents:string[],path:string[]}
     */
    public static function for_cmid(\stdClass $course, int $cmid): array {
        if ($cmid <= 0) {
            return self::empty_result();
        }

        try {
            $modinfo = get_fast_modinfo($course);
            if (!isset($modinfo->cms[$cmid])) {
                return self::empty_result();
            }
            $cm = $modinfo->cms[$cmid];
            return self::for_section_number($course, (int)$cm->sectionnum, $modinfo);
        } catch (\Throwable $e) {
            debugging(
                'FredKI section hierarchy lookup failed for cmid ' . $cmid . ': ' . $e->getMessage(),
                DEBUG_DEVELOPER
            );
            return self::empty_result();
        }
    }

    /**
     * Return the hierarchy for one course section number.
     *
     * @param \stdClass $course Course record.
     * @param int $sectionnum Relative course section number.
     * @param \course_modinfo|null $modinfo Optional existing modinfo cache.
     * @return array{current:string,parents:string[],path:string[]}
     */
    public static function for_section_number(
        \stdClass $course,
        int $sectionnum,
        ?\course_modinfo $modinfo = null
    ): array {
        try {
            $modinfo = $modinfo ?? get_fast_modinfo($course);
            $section = $modinfo->get_section_info($sectionnum);
            if (!$section) {
                return self::empty_result();
            }
            return self::walk_hierarchy($course, $modinfo, $section);
        } catch (\Throwable $e) {
            debugging(
                'FredKI section hierarchy lookup failed for section ' . $sectionnum . ': ' . $e->getMessage(),
                DEBUG_DEVELOPER
            );
            return self::empty_result();
        }
    }

    /**
     * Format parent headings as one compact, human-readable path.
     *
     * @param string[] $parents Parent headings from outermost to innermost.
     * @return string
     */
    public static function format_parents(array $parents): string {
        $parents = array_values(array_filter(array_map(static function ($value): string {
            return trim((string)$value);
        }, $parents), static function (string $value): bool {
            return $value !== '';
        }));
        return implode(' › ', $parents);
    }

    /**
     * Walk delegated mod_subsection sections back to their visible parent sections.
     *
     * @param \stdClass $course Course record.
     * @param \course_modinfo $modinfo Course modinfo.
     * @param \section_info $section Starting section.
     * @return array{current:string,parents:string[],path:string[]}
     */
    protected static function walk_hierarchy(
        \stdClass $course,
        \course_modinfo $modinfo,
        \section_info $section
    ): array {
        $names = [];
        $visited = [];
        $current = $section;

        // Moodle core subsections are normally only one level deep, but following
        // recursively costs very little and also handles future/nested structures.
        for ($depth = 0; $depth < 12 && $current; $depth++) {
            $sectionid = (int)($current->id ?? 0);
            if ($sectionid > 0 && isset($visited[$sectionid])) {
                break;
            }
            if ($sectionid > 0) {
                $visited[$sectionid] = true;
            }

            $name = trim((string)get_section_name($course, (int)$current->section));
            if ($name !== '') {
                array_unshift($names, $name);
            }

            $component = trim((string)($current->component ?? ''));
            $itemid = (int)($current->itemid ?? 0);
            if ($component !== 'mod_subsection' || $itemid <= 0) {
                break;
            }

            // Delegated subsection sections store the subsection INSTANCE id in itemid.
            // The subsection course module itself is located in the parent section.
            $subsectioncmrecord = get_coursemodule_from_instance(
                'subsection',
                $itemid,
                (int)$course->id,
                false,
                IGNORE_MISSING
            );
            if (!$subsectioncmrecord || !isset($modinfo->cms[$subsectioncmrecord->id])) {
                break;
            }

            $subsectioncm = $modinfo->cms[$subsectioncmrecord->id];
            $parentsection = $modinfo->get_section_info((int)$subsectioncm->sectionnum);
            if (!$parentsection || (int)$parentsection->id === $sectionid) {
                break;
            }
            $current = $parentsection;
        }

        if (!$names) {
            return self::empty_result();
        }

        return [
            'current' => (string)end($names),
            'parents' => array_slice($names, 0, -1),
            'path' => $names,
        ];
    }

    /**
     * Return an empty section hierarchy result.
     *
     * @return array{current:string,parents:string[],path:string[]} The empty result.
     */
    protected static function empty_result(): array {
        return ['current' => '', 'parents' => [], 'path' => []];
    }
}
