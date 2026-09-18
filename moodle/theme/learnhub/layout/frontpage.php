<?php
/**
 * Site home. Guests never see the public course list — they go to login.
 *
 * @package theme_learnhub
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;

if (!isloggedin() || isguestuser()) {
    redirect(new moodle_url('/login/index.php'));
}

require($CFG->dirroot . '/theme/boost/layout/drawers.php');
