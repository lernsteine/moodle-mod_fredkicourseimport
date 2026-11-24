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

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    // Where to get a FredKI server: hosted by lernsteine.com or self-hosted from GitHub.
    $offerlinks = html_writer::link(
        'https://fredki.com/fredki_moodle_hosting/',
        get_string('getfredkihosted', 'mod_fredkicourseimport'),
        ['class' => 'btn btn-primary me-2 mb-2', 'target' => '_blank', 'rel' => 'noopener']
    ) . html_writer::link(
        'https://github.com/lernsteine/FredKI',
        get_string('getfredkiselfhost', 'mod_fredkicourseimport'),
        ['class' => 'btn btn-outline-secondary mb-2', 'target' => '_blank', 'rel' => 'noopener']
    );
    $settings->add(new admin_setting_heading(
        'mod_fredkicourseimport/getfredki',
        get_string('getfredki', 'mod_fredkicourseimport'),
        html_writer::tag('p', get_string('getfredki_desc', 'mod_fredkicourseimport')) . html_writer::div($offerlinks)
    ));

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
}
