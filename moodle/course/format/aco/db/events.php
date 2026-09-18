<?php
defined('MOODLE_INTERNAL') || die();

$observers = [
    [
        'eventname' => '\core\event\course_module_created',
        'callback' => '\\format_aco\\observer::module_changed',
    ],
    [
        'eventname' => '\core\event\course_module_updated',
        'callback' => '\\format_aco\\observer::module_changed',
    ],
    [
        'eventname' => '\core\event\course_module_deleted',
        'callback' => '\\format_aco\\observer::module_changed',
    ],
];
