<?php
/**
 * Student: ask the course curator a question.
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
    $text = trim(optional_param('question', '', PARAM_TEXT));
    if ($text === '') {
        $error = 'Напишите вопрос — пустое сообщение не отправится.';
    } else {
        $thread = \theme_learnhub\curator::get_or_create_thread($courseid, (int) $USER->id);
        \theme_learnhub\curator::add_message($thread, (int) $USER->id, $text, false);
        redirect(new moodle_url('/theme/learnhub/curator.php', ['id' => $courseid]));
    }
}

$messages = [];
if ($thread) {
    \theme_learnhub\curator::mark_read($thread, false);
    $messages = \theme_learnhub\curator::messages((int) $thread->id);
}

echo $OUTPUT->header();
echo html_writer::start_div('lh-curator lh-curator--inplayer');
echo html_writer::tag('p', 'Вопрос куратору', ['class' => 'aco-home__kicker']);
echo html_writer::tag('h1', 'Связь с куратором');
echo html_writer::tag(
    'p',
    'Вопросы по курсу «' . $coursename . '»: домашка, тест, доступ к уроку. Куратор ответит в этой переписке.',
    ['class' => 'lh-curator__lead']
);

if ($error) {
    echo html_writer::div(s($error), 'lh-account-page__error');
}

echo html_writer::start_div('lh-curator__chat');
if (!$messages) {
    echo html_writer::div('Пока сообщений нет. Напишите, если что-то непонятно в программе курса.', 'lh-curator__empty');
} else {
    foreach ($messages as $msg) {
        $mine = empty($msg->isteacher);
        $who = $mine ? 'Вы' : 'Куратор';
        $class = 'lh-curator__bubble' . ($mine ? ' is-mine' : ' is-curator');
        echo html_writer::start_div($class);
        echo html_writer::tag('span', $who . ' · ' . userdate($msg->timecreated, '%d.%m %H:%M'), ['class' => 'lh-curator__meta']);
        echo html_writer::tag('p', nl2br(s($msg->message)));
        echo html_writer::end_div();
    }
}
echo html_writer::end_div();

echo html_writer::start_tag('form', [
    'method' => 'post',
    'class' => 'lh-curator__form',
    'action' => (new moodle_url('/theme/learnhub/curator.php', ['id' => $courseid]))->out(false),
]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::tag('label', 'Ваш вопрос', ['for' => 'lh-curator-q', 'class' => 'sr-only']);
echo html_writer::tag('textarea', s($text), [
    'id' => 'lh-curator-q',
    'name' => 'question',
    'rows' => 4,
    'required' => 'required',
    'maxlength' => (string) \theme_learnhub\curator::MAXLEN,
    'placeholder' => 'Например: не открывается урок 1.2 после теста',
]);
echo html_writer::tag('button', 'Отправить', ['type' => 'submit', 'class' => 'lh-course-card__btn lh-course-card__btn--primary']);
echo html_writer::end_tag('form');
echo html_writer::script("document.querySelector('.lh-curator__chat')&&(document.querySelector('.lh-curator__chat').scrollTop=document.querySelector('.lh-curator__chat').scrollHeight);");

echo html_writer::end_div();
echo $OUTPUT->footer();
