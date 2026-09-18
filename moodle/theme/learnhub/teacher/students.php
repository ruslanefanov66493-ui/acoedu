<?php
/**
 * Teacher: students and results in a course.
 *
 * @package theme_learnhub
 */

require(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/lib.php');
require_once($CFG->libdir . '/completionlib.php');
require_once($CFG->dirroot . '/mod/assign/locallib.php');

$courseid = required_param('id', PARAM_INT);
$course = theme_learnhub_require_teacher_course($courseid);
$allcourses = \theme_learnhub\roles::teacher_courses();
$ctx = context_course::instance($course->id);
$students = theme_learnhub_course_students($course);
$assigns = theme_learnhub_homework_cms($course);

$PAGE->set_url(new moodle_url('/theme/learnhub/teacher/students.php', ['id' => $courseid]));
$PAGE->set_context($ctx);
$PAGE->set_pagelayout('standard');
$PAGE->set_title('Студенты');
$PAGE->set_heading('Студенты');

$coursename = format_string($course->fullname, true, ['context' => $ctx]);

echo $OUTPUT->header();
echo html_writer::start_div('lh-teach');
echo html_writer::tag('nav', html_writer::link(new moodle_url('/theme/learnhub/teacher/index.php'), 'Панель') . ' › ' . s($coursename), ['class' => 'lh-crumb']);
echo html_writer::tag('h1', 'Студенты');
echo theme_learnhub_teacher_course_switcher($allcourses, $courseid, 'students');
echo html_writer::tag('p', html_writer::link(new moodle_url('/theme/learnhub/teacher/homework.php', ['id' => $courseid]), 'Перейти к домашкам', ['class' => 'lh-teach__inline']));

if (!$students) {
    echo html_writer::div('В курсе пока нет студентов.', 'lh-teach__empty');
} else {
    echo html_writer::start_div('lh-teach__tablewrap');
    echo html_writer::start_tag('table', ['class' => 'lh-teach__table']);
    echo html_writer::start_tag('thead');
    echo html_writer::tag('tr',
        html_writer::tag('th', 'Студент') .
        html_writer::tag('th', 'Прогресс') .
        html_writer::tag('th', 'Домашки')
    );
    echo html_writer::end_tag('thead');
    echo html_writer::start_tag('tbody');
    foreach ($students as $student) {
        $progress = 0;
        try {
            $pct = \core_completion\progress::get_course_progress_percentage($course, $student->id);
            $progress = $pct !== null ? (int) round($pct) : 0;
        } catch (Exception $e) {
            $progress = 0;
        }

        $hwbits = [];
        foreach ($assigns as $cm) {
            $assign = new assign(context_module::instance($cm->id), $cm, $course);
            $sub = $assign->get_user_submission($student->id, false);
            $grade = $assign->get_user_grade($student->id, false);
            $state = theme_learnhub_hw_state($sub, $grade);
            $hwbits[] = s($cm->name) . ' ' . theme_learnhub_hw_badge($state);
        }

        echo html_writer::tag('tr',
            html_writer::tag('td', s(fullname($student))) .
            html_writer::tag('td', $progress . '%') .
            html_writer::tag('td', $hwbits ? implode('<br>', $hwbits) : '—')
        );
    }
    echo html_writer::end_tag('tbody');
    echo html_writer::end_tag('table');
    echo html_writer::end_div();
}
echo html_writer::end_div();
echo $OUTPUT->footer();
