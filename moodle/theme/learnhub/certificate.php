<?php
/**
 * Student certificate view for a completed (or in-progress) course.
 *
 * @package theme_learnhub
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/completionlib.php');

$courseid = required_param('id', PARAM_INT);
$course = get_course($courseid);
require_login($course);

if (isguestuser()) {
    redirect(new moodle_url('/login/index.php'));
}

$PAGE->set_url(new moodle_url('/theme/learnhub/certificate.php', ['id' => $courseid]));
$PAGE->set_context(context_course::instance($courseid));
$PAGE->set_pagelayout('standard');
$PAGE->set_title('Сертификат');
$PAGE->set_heading('Сертификат');

$progress = 0;
try {
    $percentage = \core_completion\progress::get_course_progress_percentage($course, $USER->id);
    $progress = $percentage !== null ? (int) round($percentage) : 0;
} catch (Exception $e) {
    $progress = 0;
}

$displayname = fullname($USER);
$firstname = trim($USER->firstname ?? '');
$lastname = trim($USER->lastname ?? '');
if ($firstname !== '' && $firstname === $lastname) {
    $displayname = $firstname;
}

$coursename = format_string($course->fullname, true, ['context' => context_course::instance($courseid)]);
$issuedate = userdate(time(), '%d %B %Y');
$certid = 'EP-' . date('Y') . '-' . str_pad((string) ($courseid * 100 + $USER->id), 5, '0', STR_PAD_LEFT);

echo $OUTPUT->header();

if ($progress < 100) {
    echo html_writer::start_div('lh-cert lh-cert--pending');
    echo html_writer::tag('nav', 'Курс <span aria-hidden="true">›</span> Сертификат', ['class' => 'lh-crumb']);
    echo html_writer::tag('h1', 'Сертификат появится после окончания курса');
    echo html_writer::tag('p', 'Пройдите все уроки и тесты, чтобы получить сертификат по курсу «' . $coursename . '». Сейчас прогресс: ' . $progress . '%.');
    echo html_writer::link(new moodle_url('/course/view.php', ['id' => $courseid]), 'Вернуться к курсу', ['class' => 'lh-course-card__btn lh-course-card__btn--primary']);
    echo html_writer::end_div();
} else {
    echo html_writer::start_div('lh-cert');
    echo html_writer::tag('nav', 'Курс <span aria-hidden="true">›</span> Сертификат', ['class' => 'lh-crumb']);
    echo html_writer::tag('h1', 'Поздравляем с окончанием курса!');
    echo html_writer::start_div('lh-cert__card');
    echo html_writer::tag('p', 'СЕРТИФИКАТ', ['class' => 'lh-cert__kicker']);
    echo html_writer::tag('p', 'Настоящим подтверждается, что', ['class' => 'lh-cert__lead']);
    echo html_writer::tag('p', $displayname, ['class' => 'lh-cert__name']);
    echo html_writer::tag('p', 'успешно завершил курс', ['class' => 'lh-cert__lead']);
    echo html_writer::tag('p', '«' . $coursename . '»', ['class' => 'lh-cert__course']);
    echo html_writer::start_div('lh-cert__meta');
    echo html_writer::tag('span', $issuedate);
    echo html_writer::tag('span', 'ID: ' . $certid);
    echo html_writer::end_div();
    echo html_writer::end_div();
    echo html_writer::start_div('lh-cert__actions');
    echo html_writer::tag('button', 'Скачать PDF', ['type' => 'button', 'class' => 'lh-course-card__btn lh-course-card__btn--primary', 'onclick' => 'window.print()']);
    echo html_writer::tag('button', 'Печать', ['type' => 'button', 'class' => 'lh-course-card__btn lh-course-card__btn--ghost', 'onclick' => 'window.print()']);
    echo html_writer::end_div();
    echo html_writer::end_div();
}

echo $OUTPUT->footer();
