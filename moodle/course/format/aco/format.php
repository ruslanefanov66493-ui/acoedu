<?php
defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/filelib.php');
require_once($CFG->libdir . '/completionlib.php');

$format = course_get_format($course);
$course = $format->get_course();
$context = context_course::instance($course->id);

course_create_sections_if_missing($course, 0);

$canedit = is_siteadmin();
$preview = optional_param('lhpreview', 0, PARAM_BOOL);

// Students and teachers get the theme player. Site admins edit in the Skillbox-like builder.
if ($canedit && !$preview) {
    redirect(new moodle_url('/theme/learnhub/builder/index.php', ['id' => $course->id]));
}
if (!$canedit || $preview) {
    return;
}
