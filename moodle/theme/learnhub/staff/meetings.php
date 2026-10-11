<?php
/**
 * Administrator: meetings with teachers.
 *
 * @package theme_learnhub
 */

require(__DIR__ . '/../../../config.php');

require_login();
if (isguestuser()) {
    redirect(new moodle_url('/login/index.php'));
}
if (\theme_learnhub\roles::kind() === 'teacher') {
    redirect(new moodle_url('/theme/learnhub/teacher/meetings.php'));
}
if (!is_siteadmin()) {
    throw new moodle_exception('nopermissions', 'error', '', 'admin');
}

\theme_learnhub\meetings::staff_course(true);

$notice = optional_param('notice', '', PARAM_ALPHA);
$sent = optional_param('sent', 0, PARAM_INT);
$asked = optional_param('asked', 0, PARAM_INT);
if ($notice === 'cancelled') {
    $noticetext = 'Встреча отменена. Она пропала из списка преподавателей.';
} else if ($notice === 'created' || $notice === 'updated') {
    $noticetext = $notice === 'created' ? 'Встреча запланирована.' : 'Встреча обновлена.';
    if ($asked > 0) {
        $noticetext .= $sent > 0
            ? ' Приглашения отправлены: ' . $sent . ' из ' . $asked . '.'
            : ' Приглашения пока не ушли на почту — встреча в списке преподавателя уже есть.';
    }
} else {
    $noticetext = '';
}

$PAGE->set_url(new moodle_url('/theme/learnhub/staff/meetings.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('standard');
$PAGE->set_title('Встречи с преподавателями');
$PAGE->set_heading('Встречи с преподавателями');
$PAGE->add_body_class('lh-meetings');

$rows = \theme_learnhub\meetings::staff_rows((int) $USER->id, false);

echo $OUTPUT->header();
echo html_writer::start_div('lh-teach');
echo html_writer::tag('nav',
    html_writer::link(new moodle_url('/theme/learnhub/builder/index.php'), 'Курсы') . ' › Встречи',
    ['class' => 'lh-crumb']
);
echo html_writer::tag('h1', 'Встречи с преподавателями');
echo html_writer::tag('p', 'Отметьте преподавателей. Они увидят встречу у себя во «Встречах». Гостевой ссылки нет: комната ждёт администратора.', ['class' => 'lh-teach__lead']);
if (!\theme_learnhub\meetings::server_ready()) {
    echo html_writer::div('Видеосервер ещё не подключён. Список и письма уже работают, кнопка «Подключиться» откроет комнату после подключения.', 'lh-teach__note');
} else if ($localnote = \theme_learnhub\meetings::local_note()) {
    echo html_writer::div($localnote, 'lh-teach__note');
}
if ($noticetext !== '') {
    echo html_writer::div($noticetext, 'lh-account-page__notice');
}
echo html_writer::div(
    html_writer::link(new moodle_url('/theme/learnhub/staff/meeting.php'), 'Создать встречу', ['class' => 'lh-teach__cta']),
    'lh-teach__hero lh-teach__hero--plain'
);

if (!$rows) {
    echo html_writer::div('Пока нет встреч. Создайте первую — преподаватели увидят её у себя по дате и времени.', 'lh-teach__empty');
} else {
    echo \theme_learnhub\meetings::render_board($rows, true);
}
echo html_writer::end_div();
echo $OUTPUT->footer();
