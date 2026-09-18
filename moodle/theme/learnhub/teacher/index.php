<?php
/**
 * Teacher cabinet home: stats, pending queue, courses.
 *
 * @package theme_learnhub
 */

require(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/lib.php');
require_once($CFG->libdir . '/completionlib.php');

$courses = theme_learnhub_require_teacher();
$overview = theme_learnhub_teacher_overview($courses);

$PAGE->set_url(new moodle_url('/theme/learnhub/teacher/index.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('standard');
$PAGE->set_title('Панель преподавателя');
$PAGE->set_heading('Панель преподавателя');

echo $OUTPUT->header();
echo html_writer::start_div('lh-teach');
echo html_writer::tag('h1', 'Панель преподавателя');
echo html_writer::tag('p', 'Проверка работ и прогресс по вашим курсам.', ['class' => 'lh-teach__lead']);

echo html_writer::start_div('lh-teach__stats');
echo html_writer::start_div('lh-teach__stat');
echo html_writer::tag('strong', (string) $overview['stats']['courses']);
echo html_writer::tag('span', 'курсов');
echo html_writer::end_div();
echo html_writer::start_div('lh-teach__stat');
echo html_writer::tag('strong', (string) $overview['stats']['students']);
echo html_writer::tag('span', 'студентов');
echo html_writer::end_div();
echo html_writer::start_div('lh-teach__stat' . ($overview['stats']['pending'] ? ' is-alert' : ''));
echo html_writer::tag('strong', (string) $overview['stats']['pending']);
echo html_writer::tag('span', 'работ на проверке');
echo html_writer::end_div();
echo html_writer::start_div('lh-teach__stat' . (!empty($overview['stats']['questions']) ? ' is-alert' : ''));
echo html_writer::tag('strong', (string) ($overview['stats']['questions'] ?? 0));
echo html_writer::tag('span', 'вопросов куратору');
echo html_writer::end_div();
echo html_writer::end_div();

if ($overview['queue']) {
    echo html_writer::tag('h2', 'Очередь проверки', ['class' => 'lh-teach__h2']);
    echo html_writer::start_tag('table', ['class' => 'lh-teach__table']);
    echo html_writer::tag('thead', html_writer::tag('tr',
        html_writer::tag('th', 'Студент') .
        html_writer::tag('th', 'Курс') .
        html_writer::tag('th', 'Задание') .
        html_writer::tag('th', '')
    ));
    echo html_writer::start_tag('tbody');
    foreach ($overview['queue'] as $row) {
        echo html_writer::tag('tr',
            html_writer::tag('td', s($row['student'])) .
            html_writer::tag('td', s($row['course'])) .
            html_writer::tag('td', s($row['task'])) .
            html_writer::tag('td', html_writer::link($row['url'], 'Открыть', ['class' => 'lh-teach__link']))
        );
    }
    echo html_writer::end_tag('tbody');
    echo html_writer::end_tag('table');
}

if (!$courses) {
    echo html_writer::div('Пока нет курсов, где вы преподаватель. Администратор назначает курс в панели WordPress.', 'lh-teach__empty');
} else {
    echo html_writer::tag('h2', 'Курсы', ['class' => 'lh-teach__h2']);
    echo html_writer::start_div('lh-teach__grid');
    foreach ($overview['cards'] as $card) {
        $course = $card['course'];
        $name = format_string($course->fullname, true, ['context' => context_course::instance($course->id)]);
        $studentsurl = new moodle_url('/theme/learnhub/teacher/students.php', ['id' => $course->id]);
        $hwurl = new moodle_url('/theme/learnhub/teacher/homework.php', ['id' => $course->id]);
        echo html_writer::start_div('lh-teach__card');
        echo html_writer::tag('h2', $name);
        echo html_writer::tag(
            'p',
            $card['students'] . ' студ. · прогресс ' . $card['progress'] . '%' .
            ($card['pending'] ? ' · ' . $card['pending'] . ' на проверке' : ''),
            ['class' => 'lh-teach__meta']
        );
        echo html_writer::start_div('lh-teach__actions');
        echo html_writer::link($studentsurl, 'Студенты', ['class' => 'lh-course-card__btn lh-course-card__btn--ghost']);
        $qurl = new moodle_url('/theme/learnhub/teacher/curator.php', ['id' => $course->id]);
        $qcount = \theme_learnhub\curator::unread_teacher_count((int) $course->id);
        $qlabel = $qcount ? 'Куратор (' . $qcount . ')' : 'Куратор';
        echo html_writer::link($qurl, $qlabel, ['class' => 'lh-course-card__btn lh-course-card__btn--ghost']);
        $hwlabel = $card['pending'] ? 'Домашки (' . $card['pending'] . ')' : 'Домашки';
        echo html_writer::link($hwurl, $hwlabel, ['class' => 'lh-course-card__btn lh-course-card__btn--primary']);
        echo html_writer::end_div();
        echo html_writer::end_div();
    }
    echo html_writer::end_div();
}
echo html_writer::end_div();
echo $OUTPUT->footer();
