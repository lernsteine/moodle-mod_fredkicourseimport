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
 * Database definition for mod_fredkicourseimport.
 *
 * @package    mod_fredkicourseimport
 * @copyright  2026 lernsteine.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'mod_fredkicourseimport_get_knowledge_bases' => [
        'classname' => 'mod_fredkicourseimport\external\get_knowledge_bases',
        'methodname' => 'execute',
        'description' => 'Load FredKI knowledge bases.',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'mod/fredkicourseimport:addinstance',
    ],
    'mod_fredkicourseimport_get_agents' => [
        'classname' => 'mod_fredkicourseimport\external\get_agents',
        'methodname' => 'execute',
        'description' => 'Load FredKI agents.',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'mod/fredkicourseimport:addinstance',
    ],
];
