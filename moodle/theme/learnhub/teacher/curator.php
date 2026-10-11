<?php
/**
 * Teacher: answer student questions for a course.
 *
 * @package theme_learnhub
 */

require(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/lib.php');

$courseid = required_param('id', PARAM_INT);
$threadid = optional_param('thread', 0, PARAM_INT);
$studentid = optional_param('student', 0, PARAM_INT);
$course = theme_learnhub_require_teacher_course($courseid);
$allcourses = \theme_learnhub\roles::teacher_courses();
$ctx = context_course::instance($course->id);

$pageparams = ['id' => $courseid];
if ($threadid) {
    $pageparams['thread'] = $threadid;
} else if ($studentid) {
    $pageparams['student'] = $studentid;
}
$PAGE->set_url(new moodle_url('/theme/learnhub/teacher/curator.php', $pageparams));
$PAGE->set_context($ctx);
$PAGE->set_pagelayout('standard');
$PAGE->set_title('Вопросы куратору');
$PAGE->set_heading('Вопросы куратору');

$coursename = format_string($course->fullname, true, ['context' => $ctx]);
$error = '';
$reply = '';
$thread = null;
\theme_learnhub\curator::ensure_tables();

if ($threadid) {
    $thread = $DB->get_record(\theme_learnhub\curator::THREAD, ['id' => $threadid, 'courseid' => $courseid]);
    if (!$thread) {
        throw new moodle_exception('invalidrecord', 'error');
    }
}

$compose = null;
if (!$thread && $studentid) {
    foreach (theme_learnhub_course_students($course) as $enrolled) {
        if ((int) $enrolled->id === $studentid) {
            $compose = $enrolled;
            break;
        }
    }
    if (!$compose) {
        throw new moodle_exception('invaliduser');
    }
    $existing = \theme_learnhub\curator::get_thread($courseid, $studentid);
    if ($existing && trim((string) $existing->lastmessage) !== '') {
        redirect(new moodle_url('/theme/learnhub/teacher/curator.php', [
            'id' => $courseid,
            'thread' => $existing->id,
        ]));
    }
    if ($existing) {
        $thread = $existing;
    }
}

if (($thread || $compose) && data_submitted() && confirm_sesskey()) {
    $reply = trim(optional_param('message', '', PARAM_TEXT));
    $taken = \theme_learnhub\curator::take_uploads();
    if ($taken['error'] !== '') {
        $error = $taken['error'];
    } else if ($reply === '' && !$taken['files']) {
        $error = 'Напишите сообщение или прикрепите файл.';
    } else {
        if (!$thread) {
            $thread = \theme_learnhub\curator::get_or_create_thread($courseid, (int) $compose->id);
        }
        \theme_learnhub\curator::post($thread, (int) $USER->id, $reply, true, $taken['files'], $ctx->id);
        redirect(new moodle_url('/theme/learnhub/teacher/curator.php', [
            'id' => $courseid,
            'thread' => $thread->id,
        ]));
    }
}

if ($thread) {
    \theme_learnhub\curator::mark_read($thread, true);
    $messages = \theme_learnhub\curator::messages((int) $thread->id);
    $student = core_user::get_user($thread->userid, '*', MUST_EXIST);
} else if ($compose) {
    $messages = [];
    $student = $compose;
} else {
    $messages = [];
    $student = null;
}

$threads = \theme_learnhub\curator::course_threads($courseid);
$users = [];
if ($threads) {
    $users = $DB->get_records_list('user', 'id', array_map(function ($row) {
        return (int) $row->userid;
    }, $threads));
}

echo $OUTPUT->header();
echo html_writer::start_div('lh-teach lh-curator-admin');
echo html_writer::tag(
    'nav',
    html_writer::link(new moodle_url('/theme/learnhub/teacher/index.php'), 'Панель') . ' › ' . $coursename,
    ['class' => 'lh-crumb']
);
echo html_writer::tag('h1', 'Вопросы куратору');
echo theme_learnhub_teacher_course_switcher($allcourses, $courseid, 'curator');

if (!$threads && !$student) {
    echo html_writer::div(
        'Пока никто не писал. Откройте студента во вкладке «Студенты» и нажмите «Написать», либо дождитесь его сообщения.',
        'lh-teach__empty'
    );
    echo html_writer::end_div();
    echo $OUTPUT->footer();
    exit;
}

$boxclass = 'lh-chatbox' . ($student ? ' is-thread' : '');
echo html_writer::start_div($boxclass);
echo html_writer::start_tag('aside', ['class' => 'lh-chat__list', 'aria-label' => 'Переписки']);
echo html_writer::tag('div', 'Переписки', ['class' => 'lh-chat__list-title']);
if ($compose && !$thread) {
    $clabel = theme_learnhub_student_label($compose);
    $body = html_writer::span(\theme_learnhub\curator::initials($clabel['name']), 'lh-chat__avatar');
    $body .= html_writer::start_span('lh-chat__row-main');
    $body .= html_writer::start_span('lh-chat__row-top');
    $body .= html_writer::tag('b', s($clabel['name']));
    $body .= html_writer::tag('time', 'сейчас');
    $body .= html_writer::end_span();
    $body .= html_writer::tag('span', 'Новое сообщение', ['class' => 'lh-chat__row-preview']);
    $body .= html_writer::end_span();
    echo html_writer::link($PAGE->url, $body, ['class' => 'lh-chat__row is-active']);
}
foreach ($threads as $row) {
    $user = $users[$row->userid] ?? null;
    $name = $user ? theme_learnhub_student_label($user)['name'] : 'Студент';
    $open = new moodle_url('/theme/learnhub/teacher/curator.php', [
        'id' => $courseid,
        'thread' => $row->id,
    ]);
    $rowclass = 'lh-chat__row';
    if ($thread && (int) $thread->id === (int) $row->id) {
        $rowclass .= ' is-active';
    }
    if ((int) $row->unreadteacher === 1) {
        $rowclass .= ' is-unread';
    }
    $body = html_writer::span(\theme_learnhub\curator::initials($name), 'lh-chat__avatar');
    $body .= html_writer::start_span('lh-chat__row-main');
    $body .= html_writer::start_span('lh-chat__row-top');
    $body .= html_writer::tag('b', s($name));
    $body .= html_writer::tag('time', \theme_learnhub\curator::short_when((int) $row->timemodified));
    $body .= html_writer::end_span();
    $body .= html_writer::tag('span', s($row->lastmessage ?: 'Сообщение'), ['class' => 'lh-chat__row-preview']);
    $body .= html_writer::end_span();
    if ((int) $row->unreadteacher === 1) {
        $body .= html_writer::span('1', 'lh-chat__badge');
    }
    echo html_writer::link($open, $body, ['class' => $rowclass]);
}
echo html_writer::end_tag('aside');

echo html_writer::start_tag('section', ['class' => 'lh-chat__pane', 'aria-label' => 'Диалог']);
if ($student) {
    $slabel = theme_learnhub_student_label($student);
    $back = new moodle_url('/theme/learnhub/teacher/curator.php', ['id' => $courseid]);
    echo html_writer::start_div('lh-chat__head');
    echo html_writer::link($back, '←', ['class' => 'lh-chat__back', 'aria-label' => 'Все переписки']);
    echo html_writer::span(\theme_learnhub\curator::initials($slabel['name']), 'lh-chat__avatar');
    echo html_writer::start_div('lh-chat__who');
    echo html_writer::tag('strong', s($slabel['name']));
    echo html_writer::tag('span', 'Студент · ' . $coursename);
    echo html_writer::end_div();
    echo html_writer::end_div();
    $action = $thread
        ? new moodle_url('/theme/learnhub/teacher/curator.php', [
            'id' => $courseid,
            'thread' => $thread->id,
        ])
        : new moodle_url('/theme/learnhub/teacher/curator.php', [
            'id' => $courseid,
            'student' => $student->id,
        ]);
    echo \theme_learnhub\curator::render_dialog(
        $courseid,
        $messages,
        true,
        $action,
        $reply,
        $thread ? 'Напишите ответ…' : 'Напишите сообщение…',
        $error
    );
} else {
    echo html_writer::div('Выберите переписку слева.', 'lh-chat__placeholder');
}
echo html_writer::end_tag('section');
echo html_writer::end_div();

echo html_writer::end_div();
echo $OUTPUT->footer();
