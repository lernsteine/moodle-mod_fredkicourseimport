<?php
// Copyright (c) 2026 lernsteine.com
// SPDX-License-Identifier: GPL-3.0-or-later
namespace mod_fredkicourseimport;

defined('MOODLE_INTERNAL') || die();

/** The default protection must never authorise a 112-document mass deletion. */
final class source_deletion_guard_test extends \advanced_testcase {
    public function test_destructive_batch_is_bounded(): void {
        $this->assertTrue(\mod_fredkicourseimport\local\source_deletion_guard::automatic_batch_allowed(1, 112));
        $this->assertTrue(\mod_fredkicourseimport\local\source_deletion_guard::automatic_batch_allowed(3, 112));
        $this->assertFalse(\mod_fredkicourseimport\local\source_deletion_guard::automatic_batch_allowed(4, 112));
        $this->assertFalse(\mod_fredkicourseimport\local\source_deletion_guard::automatic_batch_allowed(112, 112));
    }
    public function test_section_summary_missing_never_authorises_delete(): void {
        $activity = (object)['course' => 1];
        $section = (object)['sourcecmid' => 0, 'filename' => 'abschnitt-1.html', 'timesynced' => time()];
        $this->assertFalse(\mod_fredkicourseimport\local\source_deletion_guard::original_missing(
            $activity, $section
        ));
    }
}
