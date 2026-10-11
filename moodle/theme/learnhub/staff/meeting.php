<?php
/**
 * Plan or edit an administrator meeting with teachers.
 *
 * @package theme_learnhub
 */

require(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../teacher/lib.php');

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

$course = \theme_learnhub\meetings::staff_course(true);
$cmid = optional_param('cmid', 0, PARAM_INT);
$cm = null;
$instance = null;
if ($cmid) {
    $cm = \theme_learnhub\meetings::require_staff($course, $cmid);
    $instance = $DB->get_record('bigbluebuttonbn', ['id' => $cm->instance], '*', MUST_EXIST);
}

$durations = \theme_learnhub\meetings::duration_options();
$opening = $instance ? (int) $instance->openingtime : \theme_learnhub\meetings::default_opening();
$closing = $instance ? (int) $instance->closingtime : $opening + 3600;
$duration = (int) round(max(0, $closing - $opening) / 60);
if ($duration <= 0) {
    $duration = 60;
}
if (!isset($durations[$duration])) {
    $durations[$duration] = $duration . ' минут';
    ksort($durations);
}

$draft = [
    'name' => $instance->name ?? '',
    'start' => \theme_learnhub\meetings::input_value($opening),
    'duration' => $duration,
    'intro' => $instance ? trim(html_to_text($instance->intro ?? '', 0)) : '',
    'notify' => true,
    'teachers' => $cm ? \theme_learnhub\meetings::invited_ids($course, (int) $cm->id) : [],
];
$error = '';

if (data_submitted() && confirm_sesskey()) {
    $action = required_param('action', PARAM_ALPHA);
    if ($action === 'cancel') {
        \theme_learnhub\meetings::cancel_staff($cmid);
        redirect(new moodle_url('/theme/learnhub/staff/meetings.php', ['notice' => 'cancelled']));
    }

    $draft['name'] = trim(required_param('name', PARAM_TEXT));
    $draft['start'] = required_param('start', PARAM_RAW_TRIMMED);
    $draft['duration'] = required_param('duration', PARAM_INT);
    $draft['intro'] = trim(optional_param('intro', '', PARAM_TEXT));
    $draft['notify'] = optional_param('notify', 0, PARAM_BOOL);
    $draft['teachers'] = optional_param_array('teachers', [], PARAM_INT);

    $openingts = \theme_learnhub\meetings::parse_input($draft['start']);
    $allowedids = array_map('intval', array_keys(\theme_learnhub\meetings::staff_teachers()));
    $picked = array_values(array_intersect(array_map('intval', $draft['teachers']), $allowedids));

    if ($draft['name'] === '') {
        $error = 'Укажите название встречи.';
    } else if (!$openingts) {
        $error = 'Укажите дату и время начала.';
    } else if ($draft['duration'] < 15 || $draft['duration'] > 240) {
        $error = 'Длительность должна быть от 15 минут до 4 часов.';
    } else if (!$picked) {
        $error = 'Отметьте хотя бы одного преподавателя.';
    } else {
        try {
            $result = \theme_learnhub\meetings::save_staff(
                $draft['name'],
                $draft['intro'],
                $openingts,
                $openingts + ($draft['duration'] * 60),
                $picked,
                (bool) $draft['notify'],
                $USER,
                $cmid
            );
            redirect(new moodle_url('/theme/learnhub/staff/meetings.php', [
                'notice' => $cmid ? 'updated' : 'created',
                'sent' => $result['sent'],
                'asked' => $result['asked'],
            ]));
        } catch (Exception $e) {
            $error = 'Не удалось сохранить встречу. Проверьте поля и попробуйте ещё раз.';
        }
    }
}

$teachers = \theme_learnhub\meetings::staff_teachers();
$pickedmap = [];
if (data_submitted() || $cm) {
    foreach ($draft['teachers'] as $tid) {
        $pickedmap[(int) $tid] = true;
    }
} else {
    foreach ($teachers as $teacher) {
        $pickedmap[(int) $teacher->id] = true;
    }
}

$url = new moodle_url('/theme/learnhub/staff/meeting.php', ['cmid' => $cmid]);
$PAGE->set_url($url);
$PAGE->set_context(context_course::instance($course->id));
$PAGE->set_pagelayout('standard');
$PAGE->set_title($cm ? 'Изменить встречу' : 'Новая встреча');
$PAGE->set_heading($PAGE->title);
$PAGE->add_body_class('lh-meetings');
$PAGE->requires->js(new moodle_url('/theme/learnhub/javascript/meetings.js', ['v' => 2026101003]));

echo $OUTPUT->header();
echo html_writer::start_div('lh-teach');
echo html_writer::tag('nav',
    html_writer::link(new moodle_url('/theme/learnhub/builder/index.php'), 'Курсы')
    . ' › '
    . html_writer::link(new moodle_url('/theme/learnhub/staff/meetings.php'), 'Встречи')
    . ' › '
    . ($cm ? 'Изменить' : 'Новая'),
    ['class' => 'lh-crumb']
);
echo html_writer::tag('h1', $cm ? 'Изменить встречу' : 'Новая встреча');
echo html_writer::tag('p', 'Преподаватели увидят её во вкладке «Встречи» и получат письмо, если почта включена. Комната открывается из кабинета и ждёт администратора.', ['class' => 'lh-teach__lead']);
if (!\theme_learnhub\meetings::server_ready()) {
    echo html_writer::div('Видеосервер ещё не подключён. Расписание и приглашения уже работают, сама комната откроется после подключения.', 'lh-teach__note');
} else if ($localnote = \theme_learnhub\meetings::local_note()) {
    echo html_writer::div($localnote, 'lh-teach__note');
}
if ($error !== '') {
    echo html_writer::div($error, 'lh-account-page__error');
}

echo html_writer::start_tag('form', [
    'method' => 'post',
    'action' => $url->out(false),
    'class' => 'lh-teach__card lh-teach__form',
]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'save']);
if ($cm) {
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'cmid', 'value' => $cmid]);
}

echo html_writer::tag('label', 'Название' . html_writer::empty_tag('input', [
    'type' => 'text',
    'name' => 'name',
    'value' => $draft['name'],
    'required' => 'required',
    'maxlength' => 200,
    'class' => 'lh-teach__input',
    'placeholder' => 'Планёрка',
]));
echo html_writer::tag('label', 'Начало' . html_writer::empty_tag('input', [
    'type' => 'datetime-local',
    'name' => 'start',
    'value' => $draft['start'],
    'required' => 'required',
    'class' => 'lh-teach__input',
]));
echo html_writer::tag('label', 'Длительность' . html_writer::select(
    $durations,
    'duration',
    (int) $draft['duration'],
    false,
    ['class' => 'lh-teach__input']
));
echo html_writer::tag('label', 'О чём встреча' . html_writer::tag('textarea', s($draft['intro']), [
    'name' => 'intro',
    'rows' => 4,
    'class' => 'lh-teach__input',
    'placeholder' => 'Коротко: зачем собраться',
]));

echo html_writer::start_div('lh-meet-people');
echo html_writer::tag('span', 'Кого пригласить', ['class' => 'lh-meet-people__label']);
if (!$teachers) {
    echo html_writer::tag('p', 'Пока нет преподавателей. Назначьте преподавателя на курс, и он появится в этом списке.', ['class' => 'lh-teach__meta']);
} else {
    $allchecked = count($pickedmap) === count($teachers);
    echo html_writer::tag('label', html_writer::empty_tag('input', [
        'type' => 'checkbox',
        'id' => 'lh-meet-all',
        'checked' => $allchecked ? 'checked' : null,
    ]) . 'Все преподаватели', ['class' => 'lh-teach__check']);
    echo html_writer::start_div('lh-meet-people__list');
    foreach ($teachers as $teacher) {
        $checked = isset($pickedmap[(int) $teacher->id]);
        echo html_writer::tag('label', html_writer::empty_tag('input', [
            'type' => 'checkbox',
            'name' => 'teachers[]',
            'value' => (int) $teacher->id,
            'class' => 'lh-meet-student',
            'checked' => $checked ? 'checked' : null,
        ]) . theme_learnhub_invite_person($teacher), ['class' => 'lh-teach__check']);
    }
    echo html_writer::end_div();
}
echo html_writer::end_div();

echo html_writer::tag('label', html_writer::empty_tag('input', [
    'type' => 'checkbox',
    'name' => 'notify',
    'value' => 1,
    'checked' => $draft['notify'] ? 'checked' : null,
]) . 'Отправить приглашение на почту', ['class' => 'lh-teach__check']);

echo html_writer::start_div('lh-teach__form-actions');
echo html_writer::tag('button', $cm ? 'Сохранить' : 'Запланировать', [
    'type' => 'submit',
    'class' => 'lh-teach__cta',
]);
echo html_writer::link(new moodle_url('/theme/learnhub/staff/meetings.php'), 'К списку', [
    'class' => 'lh-teach__link',
]);
echo html_writer::end_div();
echo html_writer::end_tag('form');

if ($cm) {
    echo html_writer::start_tag('form', [
        'method' => 'post',
        'action' => $url->out(false),
        'class' => 'lh-meet-cancel',
    ]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'cancel']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'cmid', 'value' => $cmid]);
    echo html_writer::tag('button', 'Отменить встречу', [
        'type' => 'submit',
        'class' => 'lh-teach__danger',
        'data-lh-confirm' => 'Отменить встречу? Преподаватели больше её не увидят.',
    ]);
    echo html_writer::end_tag('form');
}

echo html_writer::end_div();
echo $OUTPUT->footer();
