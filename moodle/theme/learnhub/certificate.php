<?php
/**
 * Certificates were removed from the platform.
 * Legacy URL kept so old links don't 404: redirect to the course page.
 *
 * @package theme_learnhub
 */

require(__DIR__ . '/../../config.php');

$courseid = optional_param('id', 0, PARAM_INT);

if ($courseid) {
    redirect(new moodle_url('/course/view.php', ['id' => $courseid]));
}
redirect(new moodle_url('/my/'));
