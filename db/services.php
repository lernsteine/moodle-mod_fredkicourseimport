<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

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
