<?php
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
