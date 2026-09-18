<?php
/**
 * Mark a content lesson complete (manual tracking only).
 *
 * Quizzes and assignments stay on automatic completion: they close when the
 * student submits, not when they press this button.
 *
 * @package theme_learnhub
 */

define('AJAX_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/completionlib.php');

require_login();
require_sesskey();

$cmid = required_param('cmid', PARAM_INT);
$cm = get_coursemodule_from_id(null, $cmid, 0, false, MUST_EXIST);
$course = get_course($cm->course);
require_login($course, false, $cm);

$context = context_module::instance($cm->id);
require_capability('moodle/course:togglecompletion', $context);

$modinfo = get_fast_modinfo($course);
$cminfo = $modinfo->get_cm($cm->id);
if (!$cminfo->uservisible) {
    throw new moodle_exception('activityiscurrentlyhidden');
}

$manualmods = ['page', 'resource', 'url', 'book', 'lesson', 'folder'];
if (!in_array($cminfo->modname, $manualmods, true)) {
    throw new moodle_exception('cannotmanualctrack', 'error');
}

$completion = new completion_info($course);
if (!$completion->is_enabled($cminfo) || (int) $cminfo->completion !== COMPLETION_TRACKING_MANUAL) {
    throw new moodle_exception('completionnotenabled', 'completion');
}

// Do not pass $override=true: that needs moodle/course:overridecompletion (teachers only).
$completion->update_state($cminfo, COMPLETION_COMPLETE);

echo json_encode(['ok' => true, 'cmid' => (int) $cmid]);
