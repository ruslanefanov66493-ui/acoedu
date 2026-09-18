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
$course = theme_learnhub_require_teacher_course($courseid);
$allcourses = \theme_learnhub\roles::teacher_courses();
$ctx = context_course::instance($course->id);

$PAGE->set_url(new moodle_url('/theme/learnhub/teacher/curator.php', ['id' => $courseid, 'thread' => $threadid]));
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

if ($thread && data_submitted() && confirm_sesskey()) {
    $reply = trim(optional_param('reply', '', PARAM_TEXT));
    if ($reply === '') {
        $error = 'Напишите ответ.';
    } else {
        \theme_learnhub\curator::add_message($thread, (int) $USER->id, $reply, true);
        redirect(new moodle_url('/theme/learnhub/teacher/curator.php', ['id' => $courseid, 'thread' => $thread->id]));
    }
}

if ($thread) {
    \theme_learnhub\curator::mark_read($thread, true);
    $messages = \theme_learnhub\curator::messages((int) $thread->id);
    $student = core_user::get_user($thread->userid, '*', MUST_EXIST);
} else {
    $messages = [];
    $student = null;
}

$threads = \theme_learnhub\curator::course_threads($courseid);

echo $OUTPUT->header();
echo html_writer::start_div('lh-teach lh-curator-admin');
echo html_writer::tag(
    'nav',
    html_writer::link(new moodle_url('/theme/learnhub/teacher/index.php'), 'Панель') . ' › ' . s($coursename),
    ['class' => 'lh-crumb']
);
echo html_writer::tag('h1', 'Вопросы куратору');
echo theme_learnhub_teacher_course_switcher($allcourses, $courseid, 'curator');
echo html_writer::tag('p', 'Студенты пишут со вкладки «Куратор» в своём курсе. Ответ появится у них в той же переписке.', ['class' => 'lh-teach__lead']);

if ($error) {
    echo html_writer::div(s($error), 'lh-account-page__error');
}

if ($thread && $student) {
    echo html_writer::tag(
        'p',
        html_writer::link(
            new moodle_url('/theme/learnhub/teacher/curator.php', ['id' => $courseid]),
            '← Все вопросы'
        ),
        ['class' => 'lh-teach__inline']
    );
    echo html_writer::tag('h2', 'Переписка: ' . fullname($student), ['class' => 'lh-teach__h2']);
    echo html_writer::start_div('lh-curator__chat');
    if (!$messages) {
        echo html_writer::div('Сообщений пока нет.', 'lh-curator__empty');
    } else {
        foreach ($messages as $msg) {
            $fromteacher = !empty($msg->isteacher);
            $who = $fromteacher ? 'Вы' : fullname($student);
            $class = 'lh-curator__bubble' . ($fromteacher ? ' is-mine' : ' is-curator');
            echo html_writer::start_div($class);
            echo html_writer::tag('span', s($who) . ' · ' . userdate($msg->timecreated, '%d.%m %H:%M'), ['class' => 'lh-curator__meta']);
            echo html_writer::tag('p', nl2br(s($msg->message)));
            echo html_writer::end_div();
        }
    }
    echo html_writer::end_div();

    echo html_writer::start_tag('form', [
        'method' => 'post',
        'class' => 'lh-curator__form',
        'action' => (new moodle_url('/theme/learnhub/teacher/curator.php', [
            'id' => $courseid,
            'thread' => $thread->id,
        ]))->out(false),
    ]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::tag('textarea', s($reply), [
        'name' => 'reply',
        'rows' => 4,
        'required' => 'required',
        'maxlength' => (string) \theme_learnhub\curator::MAXLEN,
        'placeholder' => 'Ответ студенту',
    ]);
    echo html_writer::tag('button', 'Ответить', ['type' => 'submit', 'class' => 'lh-course-card__btn lh-course-card__btn--primary']);
    echo html_writer::end_tag('form');
    echo html_writer::script("document.querySelector('.lh-curator__chat')&&(document.querySelector('.lh-curator__chat').scrollTop=document.querySelector('.lh-curator__chat').scrollHeight);");
} else {
    if (!$threads) {
        echo html_writer::div('Пока никто не писал. Когда студент задаст вопрос, он появится здесь.', 'lh-teach__empty');
    } else {
        echo html_writer::start_div('lh-teach__tablewrap');
        echo html_writer::start_tag('table', ['class' => 'lh-teach__table']);
        echo html_writer::tag('thead', html_writer::tag('tr',
            html_writer::tag('th', 'Студент') .
            html_writer::tag('th', 'Последнее сообщение') .
            html_writer::tag('th', 'Статус') .
            html_writer::tag('th', '')
        ));
        echo html_writer::start_tag('tbody');
        foreach ($threads as $row) {
            $u = core_user::get_user($row->userid);
            $name = $u ? fullname($u) : 'Студент #' . $row->userid;
            $status = ((int) $row->unreadteacher === 1)
                ? html_writer::span('Ждёт ответа', 'lh-teach__badge lh-teach__badge--wait')
                : html_writer::span('Отвечено', 'lh-teach__badge lh-teach__badge--ok');
            $open = new moodle_url('/theme/learnhub/teacher/curator.php', [
                'id' => $courseid,
                'thread' => $row->id,
            ]);
            echo html_writer::tag('tr',
                html_writer::tag('td', s($name)) .
                html_writer::tag('td', s($row->lastmessage ?: '—') . '<br><span class="lh-curator__time">' .
                    userdate($row->timemodified, '%d.%m.%Y %H:%M') . '</span>') .
                html_writer::tag('td', $status) .
                html_writer::tag('td', html_writer::link($open, 'Открыть', ['class' => 'lh-teach__link']))
            );
        }
        echo html_writer::end_tag('tbody');
        echo html_writer::end_tag('table');
        echo html_writer::end_div();
    }
}

echo html_writer::end_div();
echo $OUTPUT->footer();
