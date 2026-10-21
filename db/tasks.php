<?php
// Copyright (c) 2026 lernsteine.com
// SPDX-License-Identifier: GPL-3.0-or-later

defined('MOODLE_INTERNAL') || die();

$tasks = [
    [
        'classname' => '\mod_fredkicourseimport\task\reconcile_deleted_sources',
        'blocking' => 0,
        'minute' => '*/15',
        'hour' => '*',
        'day' => '*',
        'month' => '*',
        'dayofweek' => '*',
    ],
];
