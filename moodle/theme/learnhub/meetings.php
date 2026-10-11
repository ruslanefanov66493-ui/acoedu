<?php
/**
 * Student list of online meetings, grouped by date and time.
 *
 * @package theme_learnhub
 */

require(__DIR__ . '/../../config.php');

require_login();
if (isguestuser()) {
    redirect(new moodle_url('/login/index.php'));
}

$kind = \theme_learnhub\roles::kind();
if ($kind === 'teacher') {
    redirect(new moodle_url('/theme/learnhub/teacher/meetings.php'));
}
if ($kind === 'admin') {
    redirect(new moodle_url('/theme/learnhub/builder/index.php'));
}

$courses = enrol_get_all_users_courses($USER->id, true, 'id, fullname, shortname, visible, summary');
$rows = \theme_learnhub\meetings::rows($courses, (int) $USER->id, true, false);

$PAGE->set_url(new moodle_url('/theme/learnhub/meetings.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('standard');
$PAGE->set_title('Онлайн-встречи');
$PAGE->set_heading('Онлайн-встречи');
$PAGE->add_body_class('lh-meetings');

echo $OUTPUT->header();
echo html_writer::start_div('lh-teach');
echo html_writer::tag('h1', 'Онлайн-встречи');
echo html_writer::tag('p', 'Встречи ваших курсов по дате и времени. Подключиться можно из этого аккаунта, когда подойдёт время. Ссылку переслать нельзя.', ['class' => 'lh-teach__lead']);

if (!$rows) {
    echo html_writer::div('Пока нет онлайн-встреч. Когда преподаватель назначит встречу, она появится здесь.', 'lh-teach__empty');
} else {
    echo \theme_learnhub\meetings::render_board($rows, false);
}
echo html_writer::end_div();
echo $OUTPUT->footer();
