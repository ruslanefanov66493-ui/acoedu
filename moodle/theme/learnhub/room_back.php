<?php
/**
 * Landing page after a meeting ends.
 *
 * Jitsi and BigBlueButton both send the browser here. The person is already
 * logged in, so this only picks the right cabinet list.
 *
 * @package theme_learnhub
 */

require(__DIR__ . '/../../config.php');

require_login();
if (isguestuser()) {
    redirect(new moodle_url('/login/index.php'));
}

$kind = \theme_learnhub\roles::kind();
$cmid = optional_param('id', 0, PARAM_INT);
$staff = false;
if ($cmid) {
    $cm = get_coursemodule_from_id('bigbluebuttonbn', $cmid, 0, false, IGNORE_MISSING);
    $staff = $cm && (string) $cm->idnumber === \theme_learnhub\meetings::STAFF;
}
if ($kind === 'teacher') {
    redirect(new moodle_url('/theme/learnhub/teacher/meetings.php'));
}
if ($kind === 'admin') {
    redirect(new moodle_url($staff
        ? '/theme/learnhub/staff/meetings.php'
        : '/theme/learnhub/builder/index.php'));
}
redirect(new moodle_url('/theme/learnhub/meetings.php'));
