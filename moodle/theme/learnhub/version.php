<?php
/**
 * LearnHub Moodle theme version.
 *
 * @package theme_learnhub
 */

defined( 'MOODLE_INTERNAL' ) || die();

$plugin->version   = 2026101107;
$plugin->requires  = 2022041900; // Moodle 4.0+
$plugin->component = 'theme_learnhub';
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.0.0';
$plugin->dependencies = [
    'theme_boost' => 2022041900,
];
