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

require_once('../../config.php');
require_once($CFG->dirroot . '/mod/fredkicourseimport/classes/local/course_content_importer.php');
require_once($CFG->dirroot . '/mod/fredkicourseimport/classes/local/file_sync_helper.php');
require_once($CFG->dirroot . '/mod/fredkicourseimport/classes/local/manual_sync_service.php');
require_once($CFG->dirroot . '/mod/fredkicourseimport/classes/local/selection_manager.php');

$id = required_param('id', PARAM_INT);
$importall = optional_param('importall', 0, PARAM_BOOL);
$refreshcurrent = optional_param('refreshcurrent', 0, PARAM_BOOL);
$selectcontent = optional_param('selectcontent', 0, PARAM_BOOL);
$selectionmode = optional_param('selectionmode', 'visible', PARAM_ALPHA);
if (!in_array($selectionmode, ['visible', 'all', 'none'], true)) {
    $selectionmode = 'visible';
}

$cm = get_coursemodule_from_id('fredkicourseimport', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$activity = $DB->get_record('fredkicourseimport', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/fredkicourseimport:view', $context);

$isconfigured = !empty($activity->knowledgeid)
    && !empty($activity->authorizedbyuserid)
    && \mod_fredkicourseimport\local\token_manager::has_credentials(
        (int)$activity->id,
        (int)$activity->authorizedbyuserid
    );

/**
 * Fredkicourseimport execute sync.
 *
 * @param stdClass $course The course record.
 * @param stdClass $cm The course module.
 * @param stdClass $activity The FredKI activity record.
 * @param array $selectedcmids The selected course module ids.
 * @param bool $includesectiontexts Whether to include section texts.
 * @param array $contentmodes The content modes per module.
 * @param bool $persistselection The persistselection.
 * @param bool $reloadselection The reloadselection.
 * @return array The result.
 */
function fredkicourseimport_execute_sync(
    stdClass $course,
    stdClass $cm,
    stdClass $activity,
    array $selectedcmids,
    bool $includesectiontexts,
    array $contentmodes,
    bool $persistselection = false,
    bool $reloadselection = false
): array {
    global $DB;

    // A second trainer must not clear the generated-file area or alter mappings
    // while the first import is running. The lock spans import, upload and deletion.
    $lockfactory = \core\lock\lock_config::get_lock_factory('mod_fredkicourseimport_sync');
    $lock = $lockfactory->get_lock('activity:' . (int)$activity->id, 5, 3600);
    if (!$lock) {
        throw new moodle_exception('syncalreadyrunning', 'mod_fredkicourseimport');
    }

    try {
        // Selection and synchronisation share the same lock. Without this,
        // concurrent editors could overwrite the saved selection while an
        // earlier upload is still in progress.
        if ($reloadselection) {
            $selection = \mod_fredkicourseimport\local\selection_manager::get_current_selection(
                (int)$activity->id
            );
            $selectedcmids = $selection['selectedcmids'];
            $includesectiontexts = $selection['includesectiontexts'];
            $contentmodes = $selection['contentmodes'];
        } else if ($persistselection) {
            \mod_fredkicourseimport\local\selection_manager::save(
                $course,
                (int)$cm->id,
                (int)$activity->id,
                $selectedcmids,
                $includesectiontexts,
                $contentmodes
            );
        }

        // Fresh authentication before a write operation prevents a seemingly valid
        // locally cached token from failing after a FredKI restart or key rotation.
        // Existing legacy tokens (without renewable credentials) remain usable
        // until expiry; their owners can reauthorise without recreating mappings.
        $auth = $DB->get_record('fredkicourseimport_auth', [
            'activityid' => $activity->id,
            'userid' => (int)$activity->authorizedbyuserid,
        ]);
        if (
            $auth && !empty($auth->passwordcipher) &&
                !\mod_fredkicourseimport\local\token_manager::renew(
                    (int)$activity->id,
                    (int)$activity->authorizedbyuserid
                )
        ) {
            throw new moodle_exception('renewalfailed', 'mod_fredkicourseimport');
        }

        // Result counters describe only this synchronization run.
        $DB->set_field('fredkicourseimport_map', 'lastresult', null, ['activityid' => $activity->id]);

        $importer = new \mod_fredkicourseimport\local\course_content_importer();
        $counts = $importer->import_course_content(
            $course,
            $cm,
            $activity,
            $selectedcmids,
            $includesectiontexts,
            $contentmodes
        );

        // Changed Moodle metadata (including custom-field tags) is detected by
        // file_sync_helper via its metadata fingerprint. Only genuinely changed
        // source activities are therefore queued for FredKI.

        $pending = $DB->get_records(
            'fredkicourseimport_map',
            ['activityid' => $activity->id, 'status' => 'pending'],
            'timecreated ASC'
        );
        foreach ($pending as $record) {
            \mod_fredkicourseimport\local\manual_sync_service::sync_record($activity, $record);
        }

        $deleted = $DB->get_records(
            'fredkicourseimport_map',
            ['activityid' => $activity->id, 'status' => 'deleted'],
            'timemodified ASC'
        );
        foreach ($deleted as $record) {
            \mod_fredkicourseimport\local\manual_sync_service::delete_record($activity, $record);
        }

        return $counts;
    } finally {
        $lock->release();
    }
}

if ($importall || $refreshcurrent) {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        throw new moodle_exception('invalidrequestmethod', 'mod_fredkicourseimport');
    }
    require_sesskey();
    require_capability('mod/fredkicourseimport:sync', $context);
    if (!$isconfigured) {
        throw new moodle_exception('missingvalidtoken', 'mod_fredkicourseimport');
    }
}

if ($importall) {
    $selectedcmids = optional_param_array('selectedcmid', [], PARAM_INT);
    $serializedcmids = optional_param('selectedcmids_serialized', '', PARAM_RAW_TRIMMED);
    if ($serializedcmids !== '') {
        $selectedcmids = array_filter(array_map('intval', preg_split('/[^0-9]+/', $serializedcmids)));
    }
    $selectedcmids = array_values(array_unique(array_filter(array_map('intval', $selectedcmids))));
    $includesectiontexts = optional_param('includesectiontexts', 0, PARAM_BOOL);

    // Read modes for every selectable activity, not only checked ones, so the trainer's
    // complete selection state survives reopening the page.
    $contentmodes = [];
    $allsupportedcmids = \mod_fredkicourseimport\local\selection_manager::get_supported_source_cmids(
        $course,
        (int)$cm->id
    );
    foreach ($allsupportedcmids as $sourcecmid) {
        $mode = optional_param('contentmode_' . $sourcecmid, 'reference', PARAM_ALPHA);
        $contentmodes[$sourcecmid] = $mode === 'knowledge' ? 'knowledge' : 'reference';
    }

    $counts = fredkicourseimport_execute_sync(
        $course,
        $cm,
        $activity,
        $selectedcmids,
        !empty($includesectiontexts),
        $contentmodes,
        true
    );

    $message = get_string('importdone', 'mod_fredkicourseimport', (object)$counts);
    redirect(new moodle_url('/mod/fredkicourseimport/view.php', ['id' => $cm->id]), $message);
}

if ($refreshcurrent) {
    // Resolve the saved selection only after the activity lock was acquired.
    $counts = fredkicourseimport_execute_sync(
        $course,
        $cm,
        $activity,
        [],
        false,
        [],
        false,
        true
    );

    $message = get_string('refreshdone', 'mod_fredkicourseimport', (object)$counts);
    redirect(new moodle_url('/mod/fredkicourseimport/view.php', ['id' => $cm->id]), $message);
}

$PAGE->set_url('/mod/fredkicourseimport/view.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($activity->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->requires->css('/mod/fredkicourseimport/styles.css');
$targetcm = get_fast_modinfo($course)->get_cm($cm->id);

/**
 * Fredkicourseimport render activity selection.
 *
 * @param stdClass $course The course record.
 * @param cm_info $targetcm The targetcm.
 * @param stdClass $activity The FredKI activity record.
 * @param string $selectionmode The selectionmode.
 * @return string The result.
 */
function fredkicourseimport_render_activity_selection(
    stdClass $course,
    cm_info $targetcm,
    stdClass $activity,
    string $selectionmode = 'visible'
): string {
    global $DB, $OUTPUT, $PAGE;

    $savedselection = \mod_fredkicourseimport\local\selection_manager::get_records((int)$activity->id);
    $hassavedselection = !empty($savedselection);

    // Fallback for installations upgraded from versions that did not persist checkbox state.
    $existingmodes = [];
    $existingselected = [];
    $existingrecords = $DB->get_records('fredkicourseimport_map', ['activityid' => $activity->id]);
    foreach ($existingrecords as $existingrecord) {
        $sourcecmid = (int)($existingrecord->sourcecmid ?? 0);
        if (!isset($existingselected[$sourcecmid])) {
            $existingselected[$sourcecmid] = false;
        }
        if (!in_array((string)$existingrecord->status, ['deleted', 'removed'], true)) {
            $existingselected[$sourcecmid] = true;
        }
        if ($sourcecmid > 0) {
            $existingmodes[$sourcecmid] = ($existingrecord->contentmode ?? 'reference') === 'reference'
                ? 'reference'
                : 'knowledge';
        }
    }

    $modinfo = get_fast_modinfo($course);
    $sections = $modinfo->get_section_info_all();
    $html = '';

    $html .= html_writer::start_tag('form', [
        'method' => 'post',
        'action' => new moodle_url('/mod/fredkicourseimport/view.php'),
        'class' => 'fredki-selection-form',
        'id' => 'fredki-selection-form',
        'data-processing-label' => get_string('processingupload', 'mod_fredkicourseimport'),
        'data-ready-label' => get_string('ready', 'mod_fredkicourseimport'),
    ]);
    $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $targetcm->id]);
    $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'importall', 'value' => 1]);
    $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'selectionsubmitted', 'value' => 1]);
    $html .= html_writer::empty_tag(
        'input',
        ['type' => 'hidden', 'name' => 'selectedcmids_serialized', 'value' => '', 'id' => 'fredki-selected-cmids-serialized']
    );

    $html .= html_writer::div(get_string('selectcontentintro', 'mod_fredkicourseimport'), 'fredki-selection-intro');

    $selectionbaseurl = new moodle_url('/mod/fredkicourseimport/view.php', [
        'id' => $targetcm->id,
        'selectcontent' => 1,
    ]);
    $html .= html_writer::start_div('fredki-selection-toolbar');
    $html .= html_writer::tag(
        'span',
        get_string('selection', 'mod_fredkicourseimport') . ':',
        ['class' => 'fredki-selection-toolbar-label']
    );
    foreach (
        [
        'visible' => get_string('selectvisible', 'mod_fredkicourseimport'),
        'all' => get_string('selectall', 'mod_fredkicourseimport'),
        'none' => get_string('deselectall', 'mod_fredkicourseimport'),
        ] as $mode => $label
    ) {
        if ($mode !== 'visible') {
            $html .= html_writer::tag('span', '|', ['class' => 'fredki-selection-separator']);
        }
        $modeurl = new moodle_url($selectionbaseurl, ['selectionmode' => $mode]);
        $classes = 'fredki-selection-link' . ($selectionmode === $mode ? ' is-active' : '');
        $html .= html_writer::link($modeurl, $label, [
            'class' => $classes,
            'data-selection-mode' => $mode,
            'aria-current' => $selectionmode === $mode ? 'page' : null,
        ]);
    }
    $html .= html_writer::end_div();

    $bulkcompleteid = 'fredki-bulk-contentmode-complete';
    $bulkreferenceid = 'fredki-bulk-contentmode-reference';
    $html .= html_writer::start_div('fredki-contentmode-bulk');
    $html .= html_writer::tag(
        'span',
        get_string('applytoselected', 'mod_fredkicourseimport') . ':',
        ['class' => 'fredki-contentmode-bulk-label']
    );
    $html .= html_writer::tag(
        'label',
        html_writer::empty_tag('input', [
            'type' => 'radio',
            'name' => 'fredki_bulk_contentmode',
            'value' => 'knowledge',
            'id' => $bulkcompleteid,
            ]) . ' ' . get_string('contentmodeknowledge', 'mod_fredkicourseimport'),
        ['for' => $bulkcompleteid, 'class' => 'fredki-contentmode-option']
    );
    $html .= html_writer::tag(
        'label',
        html_writer::empty_tag('input', [
            'type' => 'radio',
            'name' => 'fredki_bulk_contentmode',
            'value' => 'reference',
            'id' => $bulkreferenceid,
            'checked' => 'checked',
        ]) . ' ' . get_string('contentmodereference', 'mod_fredkicourseimport'),
        ['for' => $bulkreferenceid, 'class' => 'fredki-contentmode-option']
    );
    $html .= html_writer::tag(
        'button',
        get_string('apply', 'mod_fredkicourseimport'),
        ['type' => 'button', 'class' => 'btn btn-secondary btn-sm', 'id' => 'fredki-apply-contentmode']
    );
    $html .= html_writer::end_div();

    $sectioncheckboxattrs = [
        'type' => 'checkbox',
        'name' => 'includesectiontexts',
        'value' => 1,
        'class' => 'fredki-select-item',
    ];
    if (isset($savedselection[0])) {
        if (!empty($savedselection[0]->selected)) {
            $sectioncheckboxattrs['checked'] = 'checked';
        }
    } else if (!$hassavedselection) {
        // Once an explicit selection exists, new/untracked items stay off until selected.
        if (array_key_exists(0, $existingselected)) {
            if (!empty($existingselected[0])) {
                $sectioncheckboxattrs['checked'] = 'checked';
            }
        } else if ($selectionmode !== 'none') {
            $sectioncheckboxattrs['checked'] = 'checked';
        }
    }
    $html .= html_writer::start_div('fredki-section-card fredki-section-card-special');
    $html .= html_writer::tag(
        'label',
        html_writer::empty_tag('input', $sectioncheckboxattrs) .
        html_writer::tag('span', get_string('coursesectiontexts', 'mod_fredkicourseimport'), ['class' => 'fredki-selection-title']),
        ['class' => 'fredki-selection-row fredki-selection-row-section']
    );
    $html .= html_writer::end_div();

    foreach ($sections as $section) {
        $sectioncms = [];
        foreach ($modinfo->cms as $sourcecm) {
            if ((int)$sourcecm->sectionnum !== (int)$section->section) {
                continue;
            }
            if ((int)$sourcecm->id === (int)$targetcm->id || $sourcecm->deletioninprogress) {
                continue;
            }
            $importablemodules = [
                'resource', 'folder', 'url', 'glossary', 'page', 'label', 'book', 'lesson', 'h5pactivity', 'scorm',
            ];
            if (!in_array($sourcecm->modname, $importablemodules, true)) {
                continue;
            }
            $sectioncms[] = $sourcecm;
        }
        if (!$sectioncms) {
            continue;
        }

        $sectionname = get_section_name($course, $section);
        $html .= html_writer::start_div('fredki-section-card');
        $html .= html_writer::tag('div', s($sectionname), ['class' => 'fredki-section-heading']);
        foreach ($sectioncms as $sourcecm) {
            $icon = $sourcecm->get_icon_url() ? html_writer::empty_tag(
                'img',
                ['src' => $sourcecm->get_icon_url(), 'alt' => '', 'class' => 'fredki-selection-icon']
            ) : '';
            $modlabel = get_string('pluginname', 'mod_' . $sourcecm->modname);
            $isvisibleforparticipants = !empty($sourcecm->visible);
            $checkboxattrs = [
                'type' => 'checkbox',
                'name' => 'selectedcmid[]',
                'value' => $sourcecm->id,
                'class' => 'fredki-select-item fredki-select-activity' .
                    ($isvisibleforparticipants ? ' fredki-visible-participant' : ' fredki-hidden-participant'),
                'data-visible-participant' => $isvisibleforparticipants ? '1' : '0',
            ];
            $sourcecmid = (int)$sourcecm->id;
            if (isset($savedselection[$sourcecmid])) {
                $shouldcheck = !empty($savedselection[$sourcecmid]->selected);
            } else if ($hassavedselection) {
                $shouldcheck = false;
            } else if (array_key_exists($sourcecmid, $existingselected)) {
                $shouldcheck = !empty($existingselected[$sourcecmid]);
            } else {
                $shouldcheck = $selectionmode === 'all'
                    || ($selectionmode === 'visible' && $isvisibleforparticipants);
            }
            if ($shouldcheck) {
                $checkboxattrs['checked'] = 'checked';
            }
            $hiddennote = $isvisibleforparticipants ? '' : html_writer::tag(
                'span',
                get_string('hiddenforparticipants', 'mod_fredkicourseimport'),
                ['class' => 'fredki-hidden-participant-note']
            );
            $rowclass = 'fredki-selection-row' . ($isvisibleforparticipants ? '' : ' fredki-selection-row-hidden');
            $html .= html_writer::start_div($rowclass);
            $documentlabel = html_writer::tag(
                'span',
                $icon . html_writer::tag(
                    'span',
                    format_string($sourcecm->name),
                    ['class' => 'fredki-selection-title']
                ) . $hiddennote,
                ['class' => 'fredki-selection-document']
            );
            $html .= html_writer::tag(
                'label',
                html_writer::empty_tag('input', $checkboxattrs) .
                    html_writer::tag(
                        'span',
                        $documentlabel . html_writer::tag(
                            'span',
                            s($modlabel),
                            ['class' => 'fredki-selection-type']
                        ),
                        ['class' => 'fredki-selection-content']
                    ),
                ['class' => 'fredki-selection-main']
            );
            // Every supported activity can be transferred as complete content or as a reference.
            {
                $savedrecord = $savedselection[(int)$sourcecm->id] ?? null;
                $currentmode = $savedrecord
                    ? (($savedrecord->contentmode ?? 'reference') === 'knowledge' ? 'knowledge' : 'reference')
                    : ($existingmodes[(int)$sourcecm->id] ?? 'reference');
                $knowledgeid = 'fredki-contentmode-knowledge-' . $sourcecm->id;
                $referenceid = 'fredki-contentmode-reference-' . $sourcecm->id;
                $radioname = 'contentmode_' . $sourcecm->id;
                $modecontrols = html_writer::tag(
                    'label',
                    html_writer::empty_tag('input', [
                        'type' => 'radio',
                        'name' => $radioname,
                        'value' => 'knowledge',
                        'id' => $knowledgeid,
                        'checked' => $currentmode === 'knowledge' ? 'checked' : null,
                        'class' => 'fredki-contentmode-radio',
                    ]) . ' ' . get_string('contentmodeknowledge', 'mod_fredkicourseimport'),
                    ['for' => $knowledgeid, 'class' => 'fredki-contentmode-option']
                ) .
                html_writer::tag(
                    'label',
                    html_writer::empty_tag('input', [
                        'type' => 'radio',
                        'name' => $radioname,
                        'value' => 'reference',
                        'id' => $referenceid,
                        'checked' => $currentmode === 'reference' ? 'checked' : null,
                        'class' => 'fredki-contentmode-radio',
                    ]) . ' ' . get_string('contentmodereference', 'mod_fredkicourseimport'),
                    ['for' => $referenceid, 'class' => 'fredki-contentmode-option']
                );
                $html .= html_writer::div($modecontrols, 'fredki-contentmode');
            }
            $html .= html_writer::end_div();
        }
        $html .= html_writer::end_div();
    }

    $html .= html_writer::start_div('fredki-actions-inline');
    $html .= html_writer::tag(
        'button',
        get_string('startselectedimport', 'mod_fredkicourseimport'),
        ['type' => 'submit', 'class' => 'btn btn-primary fredki-btn-sync fredki-btn-main', 'id' => 'fredki-import-link']
    );
    $html .= html_writer::link(
        new moodle_url('/mod/fredkicourseimport/view.php', ['id' => $targetcm->id]),
        get_string('cancel'),
        ['class' => 'btn btn-secondary']
    );
    $html .= html_writer::div(
        '<span class="fredki-mini-live-dot is-ready" id="fredki-mini-live-dot"></span>' .
        '<span class="fredki-mini-live-text" id="fredki-mini-live-text">' . get_string(
            'ready',
            'mod_fredkicourseimport'
        ) . '</span>',
        'fredki-mini-live-inline'
    );
    $html .= html_writer::end_div();

    $html .= html_writer::end_tag('form');
    $PAGE->requires->js_call_amd('mod_fredkicourseimport/selection', 'init');
    return $html;
}

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($activity->name));

if (!empty($activity->intro)) {
    echo $OUTPUT->box(
        format_module_intro('fredkicourseimport', $activity, $cm->id),
        'generalbox mod_introbox',
        'fredkicourseimportintro'
    );
}

$fredkiembed = !empty($activity->agentcoursevisible) ? fredkicourseimport_clean_embed_code($activity->fredkiembedcode ?? '') : '';
if ($fredkiembed !== '') {
    $src = fredkicourseimport_extract_embed_src($activity->fredkiembedcode ?? '');
    if ($src !== '') {
        $PAGE->requires->js(new moodle_url($src), true);
    }
    echo html_writer::div($fredkiembed, 'fredki-course-embed fredki-activity-embed');
}

if (has_capability('mod/fredkicourseimport:sync', $context)) {
    echo html_writer::start_div('fredki-summary');
    echo html_writer::div(
        '<span class="fredki-meta-label">' . get_string('knowledgebase', 'mod_fredkicourseimport') . ':</span> ' .
        '<span class="fredki-meta-main">' . s($activity->knowledgebasename ?: '-') . '</span>' .
        ' <span class="fredki-meta-sep">•</span> ' .
        '<span class="fredki-meta-id">ID: ' . s($activity->knowledgeid ?: '-') . '</span>' .
        ' <span class="fredki-meta-sep">•</span> ' .
        '<span class="fredki-meta-author">' . get_string(
            'authorizedby',
            'mod_fredkicourseimport'
        ) . ': ' .
        s($activity->authorizedbyuserid ? fullname(core_user::get_user($activity->authorizedbyuserid)) : '-') . '</span>',
        'fredki-summary-inline'
    );
    echo html_writer::end_div();
}

if (!$isconfigured) {
    echo $OUTPUT->notification(get_string('configureactivityfirst', 'mod_fredkicourseimport'), 'warning');
    echo $OUTPUT->footer();
    exit;
}

if ($selectcontent && has_capability('mod/fredkicourseimport:sync', $context)) {
    echo fredkicourseimport_render_activity_selection($course, $targetcm, $activity, $selectionmode);
    echo $OUTPUT->footer();
    exit;
}

if (has_capability('mod/fredkicourseimport:sync', $context)) {
    $selecturl = new moodle_url('/mod/fredkicourseimport/view.php', ['id' => $cm->id, 'selectcontent' => 1]);
    echo html_writer::start_div('fredki-actions-inline');

    echo html_writer::link(
        $selecturl,
        get_string('choosecontentforimport', 'mod_fredkicourseimport'),
        ['class' => 'btn btn-primary fredki-btn-sync fredki-btn-main']
    );

    echo html_writer::start_tag('form', [
        'method' => 'post',
        'action' => new moodle_url('/mod/fredkicourseimport/view.php'),
        'class' => 'fredki-refresh-form',
    ]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $cm->id]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'refreshcurrent', 'value' => 1]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::tag(
        'button',
        '<span aria-hidden="true">↻</span>',
        [
            'type' => 'submit',
            'class' => 'fredki-refresh-round',
            'title' => get_string('updatechangesonly', 'mod_fredkicourseimport'),
            'aria-label' => get_string('updatechangesonly', 'mod_fredkicourseimport'),
        ]
    );
    echo html_writer::tag('span', get_string('updatechangesonly', 'mod_fredkicourseimport'), [
        'class' => 'fredki-refresh-label',
    ]);
    echo html_writer::end_tag('form');
    echo html_writer::div(
        '<span class="fredki-mini-live-dot is-ready"></span><span class="fredki-mini-live-text">' .
            get_string('ready', 'mod_fredkicourseimport') . '</span>',
        'fredki-mini-live-inline'
    );
    echo html_writer::end_div();
}

if (!has_capability('mod/fredkicourseimport:sync', $context)) {
    echo $OUTPUT->footer();
    exit;
}

$fs = get_file_storage();
$files = $fs->get_area_files($context->id, 'mod_fredkicourseimport', 'generated_files', 0, 'filepath, filename', false);
$records = $DB->get_records('fredkicourseimport_map', ['activityid' => $activity->id], 'filename ASC');

$statuscounts = ['pending' => 0, 'synced' => 0, 'deleted' => 0, 'removed' => 0, 'error' => 0];
$resultcounts = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'deleted' => 0, 'error' => 0];
foreach ($records as $record) {
    if (array_key_exists($record->status, $statuscounts)) {
        $statuscounts[$record->status]++;
    }
    if (!empty($record->lastresult) && array_key_exists($record->lastresult, $resultcounts)) {
        $resultcounts[$record->lastresult]++;
    }
}

echo html_writer::start_div('fredki-overview-grid');
$statcards = [
    [get_string('processedcontentcount', 'mod_fredkicourseimport'), count($files)],
    [get_string('createddocumentscount', 'mod_fredkicourseimport'), $resultcounts['created']],
    [get_string('updateddocumentscount', 'mod_fredkicourseimport'), $resultcounts['updated']],
    [get_string('skippeddocumentscount', 'mod_fredkicourseimport'), $resultcounts['skipped']],
    [get_string('deleteddocumentscount', 'mod_fredkicourseimport'), $resultcounts['deleted']],
    [get_string('errordocumentscount', 'mod_fredkicourseimport'), $resultcounts['error']],
];
foreach ($statcards as [$label, $value]) {
    echo html_writer::div(
        html_writer::tag(
            'div',
            $label,
            ['class' => 'fredki-stat-label']
        ) . html_writer::tag('div', (string)$value, ['class' => 'fredki-stat-value']),
        'fredki-stat-card'
    );
}
echo html_writer::end_div();

$filescontent = '';
if (empty($files)) {
    $filescontent .= $OUTPUT->notification(get_string('nofiles', 'mod_fredkicourseimport'), 'info');
} else {
    $table = new html_table();
    $table->head = [
        get_string('generatedfilepath', 'mod_fredkicourseimport'),
        get_string('generatedfilename', 'mod_fredkicourseimport'),
        get_string('generatedfilesize', 'mod_fredkicourseimport'),
    ];
    foreach ($files as $file) {
        $url = moodle_url::make_pluginfile_url(
            $context->id,
            'mod_fredkicourseimport',
            'generated_files',
            0,
            $file->get_filepath(),
            $file->get_filename()
        );
        $table->data[] = [
            s($file->get_filepath()),
            html_writer::link($url, s($file->get_filename())),
            display_size($file->get_filesize()),
        ];
    }
    $filescontent .= html_writer::table($table);
}

$statuscontent = '';
if (empty($records)) {
    $statuscontent .= $OUTPUT->notification(get_string('nosyncentries', 'mod_fredkicourseimport'), 'info');
} else {
    $table = new html_table();
    $table->head = [
        get_string('file'),
        get_string('status'),
        get_string('fredkidocumentid', 'mod_fredkicourseimport'),
        get_string('lastsync', 'mod_fredkicourseimport'),
        get_string('error'),
    ];
    foreach ($records as $record) {
        $map = [
            'pending' => get_string('status_pending', 'mod_fredkicourseimport'),
            'synced' => get_string('status_synced', 'mod_fredkicourseimport'),
            'deleted' => get_string('status_deleted', 'mod_fredkicourseimport'),
            'removed' => get_string('status_removed', 'mod_fredkicourseimport'),
            'error' => get_string('status_error', 'mod_fredkicourseimport'),
        ];
        $table->data[] = [
            s($record->filename),
            s($map[$record->status] ?? $record->status),
            s($record->fredki_document_id ?? ''),
            !empty($record->timesynced) ? userdate($record->timesynced) : '-',
            !empty($record->lasterror) ? s($record->lasterror) : '',
        ];
    }
    $statuscontent .= html_writer::table($table);
}

echo html_writer::start_div('fredki-toggle-group');
echo html_writer::start_tag('details', ['class' => 'fredki-toggle']);
echo html_writer::tag('summary', get_string('files', 'mod_fredkicourseimport'), ['class' => 'fredki-toggle-link']);
echo html_writer::div($filescontent, 'fredki-toggle-content');
echo html_writer::end_tag('details');
echo html_writer::start_tag('details', ['class' => 'fredki-toggle']);
echo html_writer::tag('summary', get_string('syncstatus', 'mod_fredkicourseimport'), ['class' => 'fredki-toggle-link']);
echo html_writer::div($statuscontent, 'fredki-toggle-content');
echo html_writer::end_tag('details');
echo html_writer::end_div();

echo $OUTPUT->footer();
