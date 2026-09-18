<?php
/**
 * Teacher: review assignment submissions and reply.
 *
 * @package theme_learnhub
 */

require(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/lib.php');
require_once($CFG->dirroot . '/mod/assign/locallib.php');
require_once($CFG->libdir . '/messagelib.php');

$courseid = required_param('id', PARAM_INT);
$cmid = optional_param('cmid', 0, PARAM_INT);
$userid = optional_param('userid', 0, PARAM_INT);
$filter = theme_learnhub_hw_normalize_filter(optional_param('filter', 'wait', PARAM_ALPHA));
$course = theme_learnhub_require_teacher_course($courseid);
\theme_learnhub\course_player::repair_completion_tracking((int) $course->id);
$allcourses = \theme_learnhub\roles::teacher_courses();
$ctx = context_course::instance($course->id);
$assigns = theme_learnhub_homework_cms($course);
$students = theme_learnhub_course_students($course);

$PAGE->set_url(theme_learnhub_hw_url($courseid, $cmid, $userid, $filter));
$PAGE->set_context($ctx);
$PAGE->set_pagelayout('standard');
$PAGE->set_title('Домашки');
$PAGE->set_heading('Домашки');

$coursename = format_string($course->fullname, true, ['context' => $ctx]);
$error = '';
$commentdraft = '';

if ($cmid && $userid && data_submitted() && confirm_sesskey()) {
    $cm = get_fast_modinfo($course)->get_cm($cmid);
    if ($cm->modname !== 'assign') {
        throw new moodle_exception('invalidcoursemodule');
    }
    $modctx = context_module::instance($cm->id);
    require_capability('mod/assign:grade', $modctx);
    $assign = new assign($modctx, $cm, $course);

    $decision = required_param('decision', PARAM_ALPHA);
    $comment = trim(optional_param('comment', '', PARAM_TEXT));
    $commentdraft = $comment;

    if ($decision !== 'accept' && $decision !== 'rework') {
        throw new moodle_exception('invalidparameter');
    }

    if ($decision === 'rework' && $comment === '') {
        $error = 'Напишите, что нужно исправить — студент увидит комментарий.';
    } else {
        $gradeval = ($decision === 'accept') ? 100.0 : 0.0;
        $data = (object) [
            'grade' => $gradeval,
            'attemptnumber' => -1,
            'addattempt' => false,
            'workflowstate' => '',
            'applytoall' => false,
            'assignfeedbackcomments_editor' => [
                'text' => $comment,
                'format' => FORMAT_HTML,
            ],
        ];
        $assign->save_grade($userid, $data);

        if ($comment !== '') {
            $touser = core_user::get_user($userid, '*', MUST_EXIST);
            try {
                message_post_message($USER, $touser, $comment, FORMAT_HTML);
            } catch (Exception $e) {
                // Messaging may be disabled.
            }
        }

        $entries = theme_learnhub_hw_entries($course, $assigns, $students);
        $next = theme_learnhub_hw_next_pending($entries, $cmid, $userid);
        $msg = ($decision === 'accept')
            ? 'Работа принята. Студент может продолжить курс.'
            : 'Отправлено на доработку.';
        if ($next) {
            $msg .= ' Открыта следующая работа в очереди.';
            redirect(theme_learnhub_hw_url($courseid, $next['cmid'], $next['userid'], 'wait'), $msg);
        }
        redirect(theme_learnhub_hw_url($courseid, 0, 0, 'wait'), $msg . ' Очередь проверки пуста.');
    }
}

$entries = theme_learnhub_hw_entries($course, $assigns, $students);
$counts = theme_learnhub_hw_counts($entries);

echo $OUTPUT->header();
echo html_writer::start_div('lh-teach lh-teach--hw');
echo html_writer::tag(
    'nav',
    html_writer::link(new moodle_url('/theme/learnhub/teacher/index.php'), 'Панель') . ' › ' . s($coursename),
    ['class' => 'lh-crumb']
);
echo html_writer::tag('h1', 'Домашки');
echo theme_learnhub_teacher_course_switcher($allcourses, $courseid, 'homework');

if ($error) {
    echo html_writer::div(s($error), 'lh-account-page__error');
}

if (!$assigns) {
    echo html_writer::div('В курсе нет заданий.', 'lh-teach__empty');
    echo html_writer::end_div();
    echo $OUTPUT->footer();
    exit;
}

if ($cmid && $userid) {
    $cm = get_fast_modinfo($course)->get_cm($cmid);
    if ($cm->modname !== 'assign') {
        throw new moodle_exception('invalidcoursemodule');
    }
    $modctx = context_module::instance($cm->id);
    require_capability('mod/assign:grade', $modctx);
    $assign = new assign($modctx, $cm, $course);
    $student = core_user::get_user($userid, '*', MUST_EXIST);
    $submission = $assign->get_user_submission($userid, false);
    $grade = $assign->get_user_grade($userid, false);
    $state = theme_learnhub_hw_state($submission, $grade);
    $when = theme_learnhub_hw_when_label($submission);
    $commentvalue = $commentdraft !== '' ? $commentdraft : theme_learnhub_hw_comment($assign, $grade);
    $canscore = $submission && $submission->status !== ASSIGN_SUBMISSION_STATUS_NEW;
    $waitingelse = 0;
    foreach ($entries as $entry) {
        if ($entry['state']['mod'] === 'wait'
            && !((int) $entry['cmid'] === (int) $cmid && (int) $entry['userid'] === (int) $userid)) {
            $waitingelse++;
        }
    }

    echo html_writer::tag(
        'p',
        html_writer::link(theme_learnhub_hw_url($courseid, 0, 0, $filter), '← К очереди проверки'),
        ['class' => 'lh-teach__lead lh-hw__back']
    );

    echo html_writer::start_div('lh-hw__review');
    echo html_writer::start_div('lh-hw__work');
    echo html_writer::tag('p', 'Задание', ['class' => 'lh-hw__kicker']);
    echo html_writer::tag('h2', $cm->get_formatted_name());
    echo html_writer::start_div('lh-hw__who');
    echo $OUTPUT->user_picture($student, ['size' => 56, 'link' => false, 'class' => 'lh-hw__avatar']);
    echo html_writer::start_div('lh-hw__who-txt');
    echo html_writer::tag('strong', s(fullname($student)));
    echo html_writer::tag('span', $when !== '' ? 'Отправлено ' . $when : 'Работа ещё не отправлена');
    echo html_writer::end_div();
    echo theme_learnhub_hw_badge($state);
    echo html_writer::end_div();

    echo html_writer::start_div('lh-hw__body');
    if (!$canscore) {
        echo html_writer::tag('p', 'Студент ещё не отправил работу.', ['class' => 'lh-hw__empty']);
    } else {
        $hasplugin = false;
        foreach ($assign->get_submission_plugins() as $plugin) {
            if (!$plugin->is_enabled() || !$plugin->is_visible()) {
                continue;
            }
            $html = $plugin->view($submission);
            if (trim(html_to_text($html, 0, false)) === '') {
                continue;
            }
            $hasplugin = true;
            echo html_writer::div($html, 'lh-hw__plugin');
        }
        if (!$hasplugin) {
            echo html_writer::tag('p', 'В отправке нет текста и файлов.', ['class' => 'lh-hw__empty']);
        }
    }
    echo html_writer::end_div();
    echo html_writer::end_div();

    echo html_writer::start_div('lh-hw__decide');
    echo html_writer::tag('h3', 'Решение');
    echo html_writer::tag(
        'p',
        'Принять — студент сможет открыть следующий урок. На доработку — нужно исправить и отправить снова.',
        ['class' => 'lh-hw__decide-lead']
    );
    if ($canscore) {
        echo html_writer::start_tag('form', [
            'method' => 'post',
            'action' => $PAGE->url->out(false),
            'class' => 'lh-hw__form',
        ]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'cmid', 'value' => $cmid]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'userid', 'value' => $userid]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'filter', 'value' => $filter]);
        echo html_writer::tag('label', 'Комментарий студенту', ['for' => 'lh-hw-comment']);
        echo html_writer::tag('textarea', $commentvalue, [
            'id' => 'lh-hw-comment',
            'name' => 'comment',
            'rows' => 7,
            'placeholder' => 'Коротко: что принято или что исправить',
        ]);
        echo html_writer::start_div('lh-hw__actions');
        echo html_writer::tag('button', 'Принять работу', [
            'type' => 'submit',
            'name' => 'decision',
            'value' => 'accept',
            'class' => 'lh-hw__btn lh-hw__btn--accept',
        ]);
        echo html_writer::tag('button', 'На доработку', [
            'type' => 'submit',
            'name' => 'decision',
            'value' => 'rework',
            'class' => 'lh-hw__btn lh-hw__btn--rework',
        ]);
        echo html_writer::end_div();
        if ($waitingelse) {
            echo html_writer::tag('p', 'После решения откроется следующая из ' . $waitingelse . ' в очереди.', ['class' => 'lh-hw__next']);
        }
        echo html_writer::end_tag('form');
    } else {
        echo html_writer::tag('p', 'Проверять нечего — дождитесь отправки.', ['class' => 'lh-hw__decide-lead']);
    }
    echo html_writer::end_div();
    echo html_writer::end_div();

    echo html_writer::end_div();
    echo $OUTPUT->footer();
    exit;
}

$scoped = $entries;
if ($cmid) {
    $cm = get_fast_modinfo($course)->get_cm($cmid);
    $scoped = array_values(array_filter($entries, function ($entry) use ($cmid) {
        return (int) $entry['cmid'] === (int) $cmid;
    }));
    $counts = theme_learnhub_hw_counts($scoped);
}

$visible = theme_learnhub_hw_filter($scoped, $filter);

echo html_writer::tag('p', 'Сначала работы, которые ждут оценки. Пока вы не примете задание, студент не перейдёт дальше по курсу.', ['class' => 'lh-teach__lead']);

echo html_writer::start_div('lh-hw__filters');
$tabs = [
    'wait' => 'На проверке',
    'rework' => 'Доработка',
    'ok' => 'Принято',
    'all' => 'Сданные',
];
foreach ($tabs as $key => $label) {
    $url = theme_learnhub_hw_url($courseid, $cmid, 0, $key);
    $class = 'lh-hw__tab';
    if ($filter === $key) {
        $class .= ' is-active';
    }
    echo html_writer::link($url, $label . ' · ' . (int) $counts[$key], ['class' => $class]);
}
echo html_writer::end_div();

if ($assigns) {
    echo html_writer::start_div('lh-hw__tasks');
    echo html_writer::link(
        theme_learnhub_hw_url($courseid, 0, 0, $filter),
        'Все задания',
        ['class' => 'lh-hw__task' . ($cmid ? '' : ' is-active')]
    );
    foreach ($assigns as $cm) {
        $waiting = 0;
        foreach ($entries as $entry) {
            if ((int) $entry['cmid'] === (int) $cm->id && $entry['state']['mod'] === 'wait') {
                $waiting++;
            }
        }
        $label = $cm->get_formatted_name();
        if ($waiting) {
            $label .= ' · ' . $waiting;
        }
        echo html_writer::link(
            theme_learnhub_hw_url($courseid, $cm->id, 0, $filter),
            $label,
            ['class' => 'lh-hw__task' . ((int) $cmid === (int) $cm->id ? ' is-active' : '')]
        );
    }
    echo html_writer::end_div();
}

if (!$visible) {
    $empty = [
        'wait' => 'Нет работ на проверке.',
        'rework' => 'Нет работ на доработке.',
        'ok' => 'Пока нет принятых работ.',
        'all' => 'Студенты ещё не отправляли работы.',
    ];
    echo html_writer::div($empty[$filter] ?? 'Пусто.', 'lh-teach__empty');
} else {
    echo html_writer::start_div('lh-hw__queue');
    foreach ($visible as $entry) {
        $open = theme_learnhub_hw_url($courseid, $entry['cmid'], $entry['userid'], $filter);
        $when = theme_learnhub_hw_when_label($entry['sub']);
        echo html_writer::start_tag('a', ['class' => 'lh-hw__card', 'href' => $open->out(false)]);
        echo $OUTPUT->user_picture($entry['student'], ['size' => 48, 'link' => false, 'class' => 'lh-hw__avatar']);
        echo html_writer::start_div('lh-hw__card-txt');
        echo html_writer::tag('strong', s(fullname($entry['student'])));
        echo html_writer::tag('span', $entry['task']);
        if ($when !== '') {
            echo html_writer::tag('em', $when);
        }
        echo html_writer::end_div();
        echo theme_learnhub_hw_badge($entry['state']);
        echo html_writer::end_tag('a');
    }
    echo html_writer::end_div();
}

echo html_writer::end_div();
echo $OUTPUT->footer();
