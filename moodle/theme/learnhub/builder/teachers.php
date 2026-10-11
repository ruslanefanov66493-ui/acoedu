<?php
/**
 * Site admin: teachers and conferences with them.
 *
 * @package theme_learnhub
 */

require(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/lib.php');
require_once($CFG->dirroot . '/theme/learnhub/teacher/lib.php');

theme_learnhub_builder_require_admin(0);

$teachers = \theme_learnhub\meetings::staff_teachers();
$coursesbyuser = [];
if ($teachers) {
    list($insql, $params) = $DB->get_in_or_equal(array_keys($teachers), SQL_PARAMS_NAMED);
    $params['siteid'] = SITEID;
    $params['staff'] = \theme_learnhub\meetings::STAFF_COURSE;
    $rows = $DB->get_records_sql(
        "SELECT " . $DB->sql_concat('ra.userid', "'-'", 'c.id') . " AS uniq, ra.userid, c.id, c.fullname
           FROM {course} c
           JOIN {context} ctx ON ctx.instanceid = c.id AND ctx.contextlevel = " . CONTEXT_COURSE . "
           JOIN {role_assignments} ra ON ra.contextid = ctx.id
           JOIN {role} r ON r.id = ra.roleid AND r.shortname IN ('editingteacher', 'teacher')
          WHERE ra.userid {$insql}
            AND c.id <> :siteid
            AND c.shortname <> :staff
       ORDER BY c.fullname",
        $params
    );
    foreach ($rows as $row) {
        $coursesbyuser[(int) $row->userid][] = $row->fullname;
    }
}

$meetings = \theme_learnhub\meetings::staff_rows((int) $USER->id, false);
$upcoming = [];
foreach ($meetings as $row) {
    if ($row['state'] === 'done') {
        continue;
    }
    $upcoming[] = $row;
    if (count($upcoming) >= 4) {
        break;
    }
}

theme_learnhub_builder_setup_page(
    new moodle_url('/theme/learnhub/builder/teachers.php'),
    context_system::instance(),
    'Преподаватели'
);

echo $OUTPUT->header();
echo theme_learnhub_builder_header_html(null);
echo html_writer::start_div('lh-build lh-build--list');

echo html_writer::start_div('lh-build__pagehead');
echo html_writer::start_div('lh-build__pagehead-text');
echo html_writer::tag('h1', 'Преподаватели');
echo html_writer::tag('p', 'Кто ведёт курсы и какие конференции с ними уже стоят в расписании. Гостевой ссылки нет: комната ждёт администратора.', ['class' => 'lh-build__hint']);
echo html_writer::end_div();
echo html_writer::link(
    new moodle_url('/theme/learnhub/staff/meeting.php'),
    'Создать конференцию',
    ['class' => 'lh-build__btn']
);
echo html_writer::end_div();

echo html_writer::start_div('lh-build__split');

echo html_writer::start_div('lh-build__panel');
echo html_writer::tag('h2', 'Состав');
if (!$teachers) {
    echo html_writer::div('Пока нет преподавателей. Назначьте роль в курсе — человек появится здесь.', 'lh-build__empty');
} else {
    echo html_writer::start_tag('ul', ['class' => 'lh-build__people']);
    foreach ($teachers as $teacher) {
        $label = theme_learnhub_student_label($teacher);
        $initial = \core_text::strtoupper(\core_text::substr($label['name'], 0, 1));
        $taught = $coursesbyuser[(int) $teacher->id] ?? [];
        echo html_writer::start_tag('li', ['class' => 'lh-build__person']);
        echo html_writer::span($initial, 'lh-build__avatar');
        echo html_writer::start_div('lh-build__person-main');
        echo html_writer::tag('strong', s($label['name']));
        if ($label['email'] !== '') {
            echo html_writer::tag('span', s($label['email']), ['class' => 'lh-build__person-mail']);
        }
        if ($taught) {
            echo html_writer::start_div('lh-build__tags');
            foreach ($taught as $coursename) {
                echo html_writer::span(s($coursename), 'lh-build__tag');
            }
            echo html_writer::end_div();
        } else {
            echo html_writer::tag('span', 'Курс не назначен', ['class' => 'lh-build__person-mail']);
        }
        echo html_writer::end_div();
        echo html_writer::end_tag('li');
    }
    echo html_writer::end_tag('ul');
}
echo html_writer::end_div();

echo html_writer::start_div('lh-build__panel');
echo html_writer::start_div('lh-build__panel-head');
echo html_writer::tag('h2', 'Ближайшие конференции');
echo html_writer::link(new moodle_url('/theme/learnhub/staff/meetings.php'), 'Все встречи', ['class' => 'lh-build__textlink']);
echo html_writer::end_div();
if (!$upcoming) {
    echo html_writer::div('Конференций пока нет. Создайте первую — преподаватели увидят её у себя во «Встречах».', 'lh-build__empty');
} else {
    echo html_writer::start_tag('ul', ['class' => 'lh-build__meets']);
    foreach ($upcoming as $row) {
        $when = trim($row['day'] . ' · ' . $row['start'] . ($row['end'] !== '' ? '–' . $row['end'] : ''));
        echo html_writer::start_tag('li');
        echo html_writer::link($row['editurl'], s($row['name']), ['class' => 'lh-build__meet-name']);
        echo html_writer::tag('span', s($when), ['class' => 'lh-build__meet-when']);
        if ($row['audience'] !== '') {
            echo html_writer::tag('span', s($row['audience']), ['class' => 'lh-build__meet-who']);
        }
        echo html_writer::end_tag('li');
    }
    echo html_writer::end_tag('ul');
}
echo html_writer::end_div();

echo html_writer::end_div();
echo html_writer::end_div();
echo $OUTPUT->footer();
