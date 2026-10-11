<?php
/**
 * Student: talk with the course curator.
 *
 * @package theme_learnhub
 */

require(__DIR__ . '/../../config.php');

$courseid = required_param('id', PARAM_INT);
$course = get_course($courseid);
require_login($course);

if (isguestuser()) {
    redirect(new moodle_url('/login/index.php'));
}

$kind = \theme_learnhub\roles::kind();
if ($kind === 'teacher') {
    redirect(new moodle_url('/theme/learnhub/teacher/curator.php', ['id' => $courseid]));
}
if ($kind === 'admin') {
    redirect(new moodle_url('/theme/learnhub/builder/index.php', ['id' => $courseid]));
}

$ctx = context_course::instance($courseid);
if (!is_enrolled($ctx, $USER, '', true)) {
    redirect(new moodle_url('/enrol/index.php', ['id' => $courseid]));
}

$PAGE->set_url(new moodle_url('/theme/learnhub/curator.php', ['id' => $courseid]));
$PAGE->set_context($ctx);
$PAGE->set_pagelayout('course');
$PAGE->set_title('Связь с куратором');
$PAGE->set_heading('Связь с куратором');
if (isset($PAGE->activityheader)) {
    $PAGE->activityheader->disable();
}

$coursename = format_string($course->fullname, true, ['context' => $ctx]);
$error = '';
$text = '';
$thread = \theme_learnhub\curator::get_thread($courseid, (int) $USER->id);

if (data_submitted() && confirm_sesskey()) {
    $text = trim(optional_param('message', '', PARAM_TEXT));
    $taken = \theme_learnhub\curator::take_uploads();
    if ($taken['error'] !== '') {
        $error = $taken['error'];
    } else if ($text === '' && !$taken['files']) {
        $error = 'Напишите сообщение или прикрепите файл.';
    } else {
        $thread = \theme_learnhub\curator::get_or_create_thread($courseid, (int) $USER->id);
        \theme_learnhub\curator::post($thread, (int) $USER->id, $text, false, $taken['files'], $ctx->id);
        redirect(new moodle_url('/theme/learnhub/curator.php', ['id' => $courseid]));
    }
}

$messages = [];
if ($thread) {
    \theme_learnhub\curator::mark_read($thread, false);
    $messages = \theme_learnhub\curator::messages((int) $thread->id);
}

$action = new moodle_url('/theme/learnhub/curator.php', ['id' => $courseid]);

echo $OUTPUT->header();
echo html_writer::start_div('lh-chatpane');
echo html_writer::start_div('lh-chat__head');
echo html_writer::span('К', 'lh-chat__avatar');
echo html_writer::start_div('lh-chat__who');
echo html_writer::tag('strong', 'Куратор');
echo html_writer::tag('span', $coursename);
echo html_writer::end_div();
echo html_writer::end_div();
echo \theme_learnhub\curator::render_dialog($courseid, $messages, false, $action, $text, 'Напишите куратору…', $error);
echo html_writer::end_div();
echo $OUTPUT->footer();
