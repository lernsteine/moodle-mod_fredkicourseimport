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

if ($ADMIN->fulltree) {
    $settings->add(new admin_setting_heading(
        'mod_fredkicourseimport/cleanupregister',
        get_string('cleanupregister', 'mod_fredkicourseimport'),
        html_writer::link(
            new moodle_url('/mod/fredkicourseimport/cleanup.php'),
            get_string('cleanupregisteropen', 'mod_fredkicourseimport')
        )
    ));

    $settings->add(new admin_setting_configtext(
        'mod_fredkicourseimport/baseurl',
        get_string('baseurl', 'mod_fredkicourseimport'),
        '',
        '',
        PARAM_URL
    ));
    $settings->add(new admin_setting_configtext(
        'mod_fredkicourseimport/loginpath',
        get_string('loginpath', 'mod_fredkicourseimport'),
        '',
        '/admin/api/user/login',
        PARAM_TEXT
    ));
    $settings->add(new admin_setting_configcheckbox(
        'mod_fredkicourseimport/debuglog',
        get_string('debuglog', 'mod_fredkicourseimport'),
        '',
        0
    ));
    $settings->add(new admin_setting_configtext(
        'mod_fredkicourseimport/licensekey',
        get_string('licensekey', 'mod_fredkicourseimport'),
        get_string('licensekey_desc', 'mod_fredkicourseimport'),
        '',
        PARAM_TEXT
    ));
}
