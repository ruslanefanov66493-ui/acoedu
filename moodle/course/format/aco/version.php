<?php
defined('MOODLE_INTERNAL') || die();

$plugin->component = 'format_aco';
$plugin->version   = 2026090701;
$plugin->requires  = 2024100100;
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.0';
$plugin->dependencies = [
    'format_topics' => 2024100700,
];
