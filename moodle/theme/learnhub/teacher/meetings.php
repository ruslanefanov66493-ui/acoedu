<?php
/**
 * Teacher list of online meetings.
 *
 * @package theme_learnhub
 */

require(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/lib.php');

$courses = theme_learnhub_require_teacher();
\theme_learnhub\meetings::ensure_host($courses, (int) $USER->id);

$notice = optional_param('notice', '', PARAM_ALPHA);
$sent = optional_param('sent', 0, PARAM_INT);
$asked = optional_param('asked', 0, PARAM_INT);
$noticetext = \theme_learnhub\meetings::notice_text($notice, $sent, $asked);

$PAGE->set_url(new moodle_url('/theme/learnhub/teacher/meetings.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('standard');
$PAGE->set_title('Онлайн-встречи');
$PAGE->set_heading('Онлайн-встречи');
$PAGE->add_body_class('lh-meetings');

$rows = \theme_learnhub\meetings::rows($courses, (int) $USER->id, false, true);
$staff = \theme_learnhub\meetings::staff_rows((int) $USER->id, true);
if ($staff) {
    $rows = array_merge($rows, $staff);
    usort($rows, function (array $a, array $b): int {
        $ao = $a['opening'] > 0 ? $a['opening'] : PHP_INT_MAX;
        $bo = $b['opening'] > 0 ? $b['opening'] : PHP_INT_MAX;
        return $ao <=> $bo;
    });
}

echo $OUTPUT->header();
echo html_writer::start_div('lh-teach');
echo html_writer::tag('nav',
    html_writer::link(new moodle_url('/theme/learnhub/teacher/index.php'), 'Панель') . ' › Встречи',
    ['class' => 'lh-crumb']
);
echo html_writer::tag('h1', 'Онлайн-встречи');
echo html_writer::tag('p', 'Расписание по вашим курсам и встречи, которые назначил администратор. Студент видит только те встречи, куда вы его пригласили.', ['class' => 'lh-teach__lead']);
if (!\theme_learnhub\meetings::server_ready()) {
    echo html_writer::div('Видеосервер ещё не подключён. Список и письма уже работают, кнопка «Подключиться» откроет комнату после подключения.', 'lh-teach__note');
} else if ($localnote = \theme_learnhub\meetings::local_note()) {
    echo html_writer::div($localnote, 'lh-teach__note');
}
if ($noticetext !== '') {
    echo html_writer::div($noticetext, 'lh-account-page__notice');
}
echo html_writer::div(
    html_writer::link(new moodle_url('/theme/learnhub/teacher/meeting.php'), 'Создать онлайн-встречу', ['class' => 'lh-teach__cta']),
    'lh-teach__hero lh-teach__hero--plain'
);

if (!$rows) {
    echo html_writer::div('Пока нет встреч. Создайте первую — студенты увидят её у себя по дате и времени.', 'lh-teach__empty');
} else {
    echo \theme_learnhub\meetings::render_board($rows, true);
}
echo html_writer::end_div();
echo $OUTPUT->footer();
