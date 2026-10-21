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

/**
 * FredKI course import plugin file.
 *
 * @package    mod_fredkicourseimport
 * @copyright  2026 lernsteine.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

function xmldb_fredkicourseimport_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 20260422070) {
        $table = new xmldb_table('fredkicourseimport_map');
        $field = new xmldb_field('lastresult', XMLDB_TYPE_CHAR, '20', null, null, null, null, 'status');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 20260422070, 'fredkicourseimport');
    }

    if ($oldversion < 20260427000) {
        $table = new xmldb_table('fredkicourseimport');

        $fields = [
            new xmldb_field('agentid', XMLDB_TYPE_CHAR, '64', null, null, null, null),
            new xmldb_field('fredkiembedcode', XMLDB_TYPE_TEXT, null, null, null, null, null),
        ];

        foreach ($fields as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }

        upgrade_mod_savepoint(true, 20260427000, 'fredkicourseimport');
    }

    if ($oldversion < 20260428006) {
        $table = new xmldb_table('fredkicourseimport');

        $fields = [
            new xmldb_field('agentid', XMLDB_TYPE_CHAR, '64', null, null, null, null),
            new xmldb_field('fredkiembedcode', XMLDB_TYPE_TEXT, null, null, null, null, null),
            new xmldb_field('agentcoursevisible', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0'),
        ];

        foreach ($fields as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }

        upgrade_mod_savepoint(true, 20260428006, 'fredkicourseimport');
    }


    if ($oldversion < 2026042810) {
        $table = new xmldb_table('fredkicourseimport');
        $field = new xmldb_field('fredkichaturl', XMLDB_TYPE_TEXT, null, null, null, null, null, 'fredkiembedcode');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2026042810, 'fredkicourseimport');
    }

    if ($oldversion < 202607290001) {
        $table = new xmldb_table('fredkicourseimport');
        $authorized = new xmldb_field('authorizedbyuserid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'knowledgebasename');
        if (!$dbman->field_exists($table, $authorized)) {
            $dbman->add_field($table, $authorized);
        }
        foreach (['fredkipassword', 'fredkiusername', 'authorizedbyname'] as $fieldname) {
            $field = new xmldb_field($fieldname);
            if ($dbman->field_exists($table, $field)) {
                $dbman->drop_field($table, $field);
            }
        }

        $authtable = new xmldb_table('fredkicourseimport_auth');
        if (!$dbman->table_exists($authtable)) {
            $authtable->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $authtable->add_field('activityid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $authtable->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $authtable->add_field('fredkiusername', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL);
            $authtable->add_field('token', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL);
            $authtable->add_field('tokenexpires', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $authtable->add_field('lastused', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $authtable->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $authtable->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $authtable->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $authtable->add_key('activity_fk', XMLDB_KEY_FOREIGN, ['activityid'], 'fredkicourseimport', ['id']);
            $authtable->add_key('user_fk', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
            $authtable->add_index('useractivity_uix', XMLDB_INDEX_UNIQUE, ['userid', 'activityid']);
            $authtable->add_index('expiry_ix', XMLDB_INDEX_NOTUNIQUE, ['tokenexpires']);
            $dbman->create_table($authtable);
        }

        $maptable = new xmldb_table('fredkicourseimport_map');
        $index = new xmldb_index('pathnamehash_uix', XMLDB_INDEX_UNIQUE, ['activityid', 'pathnamehash']);
        if ($dbman->index_exists($maptable, $index)) {
            $dbman->drop_index($maptable, $index);
        }
        foreach ([['pathnamehash', '40'], ['contenthash', '40']] as [$fieldname, $length]) {
            $field = new xmldb_field($fieldname, XMLDB_TYPE_CHAR, $length, null, XMLDB_NOTNULL, null, null);
            if ($dbman->field_exists($maptable, $field)) {
                $dbman->change_field_precision($maptable, $field);
                $dbman->change_field_default($maptable, $field);
            }
        }
        $filenamefield = new xmldb_field('filename', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        if ($dbman->field_exists($maptable, $filenamefield)) {
            $dbman->change_field_default($maptable, $filenamefield);
        }
        $dbman->add_index($maptable, $index);
        upgrade_mod_savepoint(true, 202607290001, 'fredkicourseimport');
    }


    if ($oldversion < 202607290003) {
        // Regression-recovery release. No database changes are required.
        upgrade_mod_savepoint(true, 202607290003, 'fredkicourseimport');
    }


    if ($oldversion < 202607310001) {
        // Capability, request validation, and security hardening release.
        upgrade_mod_savepoint(true, 202607310001, 'fredkicourseimport');
    }


    if ($oldversion < 202607310003) {
        $table = new xmldb_table('fredkicourseimport_map');
        $sourcecmidfield = new xmldb_field(
            'sourcecmid',
            XMLDB_TYPE_INTEGER,
            '10',
            null,
            XMLDB_NOTNULL,
            null,
            '0',
            'activityid'
        );
        if (!$dbman->field_exists($table, $sourcecmidfield)) {
            $dbman->add_field($table, $sourcecmidfield);
        }
        $field = new xmldb_field(
            'contentmode',
            XMLDB_TYPE_CHAR,
            '20',
            null,
            XMLDB_NOTNULL,
            null,
            'knowledge',
            'filesize'
        );
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_mod_savepoint(true, 202607310003, 'fredkicourseimport');
    }


    if ($oldversion < 202607310005) {
        // Document titles are localised using the course language. Existing FredKI
        // documents must be recreated once so that previously generated German
        // prefixes are replaced by the language configured for each Moodle course.
        $sql = "status <> :deleted AND status <> :removed";
        $params = [
            'deleted' => 'deleted',
            'removed' => 'removed',
        ];
        $DB->set_field_select('fredkicourseimport_map', 'status', 'pending', $sql, $params);
        $DB->set_field_select('fredkicourseimport_map', 'lastresult', 'updated', $sql, $params);

        upgrade_mod_savepoint(true, 202607310005, 'fredkicourseimport');
    }

    if ($oldversion < 202609250300) {
        // Reference is the safe default for every newly created upload/mapping.
        // Explicitly selected Complete/knowledge modes remain unchanged.
        $table = new xmldb_table('fredkicourseimport_map');
        $field = new xmldb_field(
            'contentmode',
            XMLDB_TYPE_CHAR,
            '20',
            null,
            XMLDB_NOTNULL,
            null,
            'reference',
            'filesize'
        );
        if ($dbman->field_exists($table, $field)) {
            $dbman->change_field_default($table, $field);
        }
        upgrade_mod_savepoint(true, 202609250300, 'fredkicourseimport');
    }

    if ($oldversion < 202609250600) {
        $table = new xmldb_table('fredkicourseimport_selection');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table->add_field('activityid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('sourcecmid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('selected', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('contentmode', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'reference');
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('activity_fk', XMLDB_KEY_FOREIGN, ['activityid'], 'fredkicourseimport', ['id']);
            $table->add_index('activitysource_uix', XMLDB_INDEX_UNIQUE, ['activityid', 'sourcecmid']);
            $dbman->create_table($table);
        }

        // Preserve the effective selection of existing installations. A source is considered
        // selected when at least one of its current mappings is still active.
        $maps = $DB->get_records('fredkicourseimport_map', null, 'activityid ASC, sourcecmid ASC, timemodified ASC, id ASC');
        $states = [];
        foreach ($maps as $map) {
            $activityid = (int)$map->activityid;
            $sourcecmid = (int)($map->sourcecmid ?? 0);
            $key = $activityid . ':' . $sourcecmid;
            if (!isset($states[$key])) {
                $states[$key] = [
                    'activityid' => $activityid,
                    'sourcecmid' => $sourcecmid,
                    'selected' => 0,
                    'contentmode' => 'reference',
                ];
            }
            if (!in_array((string)$map->status, ['deleted', 'removed'], true)) {
                $states[$key]['selected'] = 1;
            }
            if ($sourcecmid > 0) {
                $states[$key]['contentmode'] = ($map->contentmode ?? 'reference') === 'knowledge'
                    ? 'knowledge'
                    : 'reference';
            }
        }
        $now = time();
        foreach ($states as $state) {
            if (!$DB->record_exists('fredkicourseimport_selection', [
                'activityid' => $state['activityid'],
                'sourcecmid' => $state['sourcecmid'],
            ])) {
                $DB->insert_record('fredkicourseimport_selection', (object)[
                    'activityid' => $state['activityid'],
                    'sourcecmid' => $state['sourcecmid'],
                    'selected' => $state['selected'],
                    'contentmode' => $state['contentmode'],
                    'timecreated' => $now,
                    'timemodified' => $now,
                ]);
            }
        }

        upgrade_mod_savepoint(true, 202609250600, 'fredkicourseimport');
    }

    if ($oldversion < 202609250800) {
        $table = new xmldb_table('fredkicourseimport_map');
        $field = new xmldb_field(
            'metadatahash',
            XMLDB_TYPE_CHAR,
            '40',
            null,
            null,
            null,
            null,
            'contenthash'
        );
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Establish a metadata baseline for existing mappings at upgrade time.
        // This prevents the first selective refresh after upgrading from re-uploading
        // every document merely because older rows did not yet have a fingerprint.
        $maps = $DB->get_records('fredkicourseimport_map', null, 'id ASC');
        foreach ($maps as $map) {
            $sourcecmid = (int)($map->sourcecmid ?? 0);
            if ($sourcecmid <= 0) {
                $map->metadatahash = sha1('section-text');
            } else {
                $map->metadatahash = \mod_fredkicourseimport\local\file_sync_helper::calculate_source_metadata_hash($sourcecmid);
            }
            $DB->update_record('fredkicourseimport_map', $map);
        }

        upgrade_mod_savepoint(true, 202609250800, 'fredkicourseimport');
    }


    if ($oldversion < 202609260100) {
        // Search-oriented document format v2:
        // - real activity title as paragraph title
        // - compact metadata block
        // - deterministic linked questions via MaxKB paragraph problem_list.
        // No schema change is required. file_sync_helper's importformat marker
        // makes existing active mappings refresh once on the next synchronization.
        upgrade_mod_savepoint(true, 202609260100, 'fredkicourseimport');
    }

    if ($oldversion < 202609260300) {
        // Search precision v4:
        // Generic linked questions repeated across many materials are removed.
        // Only the title-specific lookup question remains. The importformat marker
        // in file_sync_helper causes active mappings to refresh once so obsolete
        // MaxKB problem_list entries are replaced by the precise version.
        upgrade_mod_savepoint(true, 202609260300, 'fredkicourseimport');
    }


    if ($oldversion < 202609270100) {
        // Search precision v5:
        // CourseImport no longer creates MaxKB linked questions (paragraph.problem_list).
        // The file_sync_helper import-format marker changes as well, so existing active
        // mappings are recreated once. Replacing the old FredKI document removes the
        // previously importer-generated linked questions together with that old document.
        upgrade_mod_savepoint(true, 202609270100, 'fredkicourseimport');
    }


    if ($oldversion < 202610050008) {
        // Repair/bridge upgrade for installations that came from the 1.4.x branch.
        // That branch used a higher build number than the original 1.6.x schema
        // migrations, so Moodle legitimately skipped those older-numbered steps.
        // Reconcile the schema explicitly and idempotently here.

        $maptable = new xmldb_table('fredkicourseimport_map');

        $metadatahash = new xmldb_field(
            'metadatahash',
            XMLDB_TYPE_CHAR,
            '40',
            null,
            null,
            null,
            null,
            'contenthash'
        );
        if (!$dbman->field_exists($maptable, $metadatahash)) {
            $dbman->add_field($maptable, $metadatahash);
        }

        // Ensure contentmode exists even on unusual intermediate installations.
        $contentmode = new xmldb_field(
            'contentmode',
            XMLDB_TYPE_CHAR,
            '20',
            null,
            XMLDB_NOTNULL,
            null,
            'reference',
            'filesize'
        );
        if (!$dbman->field_exists($maptable, $contentmode)) {
            $dbman->add_field($maptable, $contentmode);
        } else {
            $dbman->change_field_default($maptable, $contentmode);
        }

        $sourcecmid = new xmldb_field(
            'sourcecmid',
            XMLDB_TYPE_INTEGER,
            '10',
            null,
            XMLDB_NOTNULL,
            null,
            '0',
            'activityid'
        );
        if (!$dbman->field_exists($maptable, $sourcecmid)) {
            $dbman->add_field($maptable, $sourcecmid);
        }

        $selectiontable = new xmldb_table('fredkicourseimport_selection');
        if (!$dbman->table_exists($selectiontable)) {
            $selectiontable->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $selectiontable->add_field('activityid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $selectiontable->add_field('sourcecmid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $selectiontable->add_field('selected', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
            $selectiontable->add_field('contentmode', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'reference');
            $selectiontable->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $selectiontable->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $selectiontable->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $selectiontable->add_key('activity_fk', XMLDB_KEY_FOREIGN, ['activityid'], 'fredkicourseimport', ['id']);
            $selectiontable->add_index('activitysource_uix', XMLDB_INDEX_UNIQUE, ['activityid', 'sourcecmid']);
            $dbman->create_table($selectiontable);
        }

        // Rebuild persisted selections from existing mappings where no explicit
        // selection exists yet. Preserve each mapping's effective content mode.
        $maps = $DB->get_records('fredkicourseimport_map', null, 'activityid ASC, sourcecmid ASC, timemodified ASC, id ASC');
        $states = [];
        foreach ($maps as $map) {
            $activityid = (int)$map->activityid;
            $sourcecmidvalue = (int)($map->sourcecmid ?? 0);
            $key = $activityid . ':' . $sourcecmidvalue;

            if (!isset($states[$key])) {
                $states[$key] = [
                    'activityid' => $activityid,
                    'sourcecmid' => $sourcecmidvalue,
                    'selected' => 0,
                    'contentmode' => 'reference',
                ];
            }

            if (!in_array((string)$map->status, ['deleted', 'removed'], true)) {
                $states[$key]['selected'] = 1;
            }

            if ($sourcecmidvalue > 0) {
                $states[$key]['contentmode'] = (($map->contentmode ?? 'reference') === 'knowledge')
                    ? 'knowledge'
                    : 'reference';
            }
        }

        $now = time();
        foreach ($states as $state) {
            $conditions = [
                'activityid' => $state['activityid'],
                'sourcecmid' => $state['sourcecmid'],
            ];
            if (!$DB->record_exists('fredkicourseimport_selection', $conditions)) {
                $DB->insert_record('fredkicourseimport_selection', (object)[
                    'activityid' => $state['activityid'],
                    'sourcecmid' => $state['sourcecmid'],
                    'selected' => $state['selected'],
                    'contentmode' => $state['contentmode'],
                    'timecreated' => $now,
                    'timemodified' => $now,
                ]);
            }
        }

        // Existing rows from the 1.4.x branch have no metadata fingerprint yet.
        // Leave metadatahash NULL so the normal synchronization logic refreshes
        // those mappings safely on the next run.

        upgrade_mod_savepoint(true, 202610050008, 'fredkicourseimport');
    }

    if ($oldversion < 202610110001) {
        $table = new xmldb_table('fredkicourseimport_cleanup');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('activityid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('knowledgeid', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL);
            $table->add_field('workspace', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL, null, 'default');
            $table->add_field('documentid', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_index('course_ix', XMLDB_INDEX_NOTUNIQUE, ['courseid']);
            $dbman->create_table($table);
        }
        upgrade_mod_savepoint(true, 202610110001, 'fredkicourseimport');
    }

    if ($oldversion < 202610200001) {
        $table = new xmldb_table('fredkicourseimport_auth');
        $field = new xmldb_field('passwordencrypted', XMLDB_TYPE_TEXT, null, null, null, null, null, 'tokenexpires');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        // Intentionally do not alter or remove existing connections or knowledge mappings.
        upgrade_mod_savepoint(true, 202610200001, 'fredkicourseimport');
    }

    if ($oldversion < 202610200002) {
        // Glossary full exports are now one document per approved entry, with
        // stable filenames and direct source links. No schema migration is needed:
        // the next normal sync creates per-entry documents and retires the old
        // aggregated glossary document through the existing mapping cleanup.
        upgrade_mod_savepoint(true, 202610200002, 'fredkicourseimport');
    }

    if ($oldversion < 202610210010) {
        // Backward-compatible upgrade even from RC7/RC8/RC9. Remove only the
        // deprecated and unsafe automatic visibility deletion tasks.
        $component = 'mod_fredkicourseimport';
        foreach (['\\mod_fredkicourseimport\\task\\reconcile_visibility',
                   '\\mod_fredkicourseimport\\task\\delete_revoked_documents'] as $classname) {
            foreach ([$classname, ltrim($classname, '\\')] as $variant) {
                $DB->delete_records('task_scheduled', ['component' => $component, 'classname' => $variant]);
                $DB->delete_records('task_adhoc', ['component' => $component, 'classname' => $variant]);
            }
        }
        upgrade_mod_savepoint(true, 202610210010, 'fredkicourseimport');
    }
    return true;
}
