<?php
/**
 * Teacher: students, progress, and the way into homework and curator.
 *
 * @package theme_learnhub
 */

require(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/lib.php');
require_once($CFG->libdir . '/completionlib.php');

$courseid = required_param('id', PARAM_INT);
$view = optional_param('view', 'all', PARAM_ALPHA);
if (!in_array($view, ['all', 'wait', 'rework'], true)) {
    $view = 'all';
}
$course = theme_learnhub_require_teacher_course($courseid);
$allcourses = \theme_learnhub\roles::teacher_courses();
$ctx = context_course::instance($course->id);
$students = theme_learnhub_course_students($course);
$assigns = theme_learnhub_homework_cms($course);
$entries = theme_learnhub_hw_entries($course, $assigns, $students);

$PAGE->set_url(new moodle_url('/theme/learnhub/teacher/students.php', $view === 'all'
    ? ['id' => $courseid]
    : ['id' => $courseid, 'view' => $view]));
$PAGE->set_context($ctx);
$PAGE->set_pagelayout('standard');
$PAGE->set_title('Студенты');
$PAGE->set_heading('Студенты');

$coursename = format_string($course->fullname, true, ['context' => $ctx]);

$byuser = [];
foreach ($entries as $entry) {
    $byuser[(int) $entry['userid']][] = $entry;
}

$threadby = [];
foreach (\theme_learnhub\curator::course_threads((int) $course->id) as $thread) {
    $threadby[(int) $thread->userid] = $thread;
}

$rank = ['wait' => 0, 'rework' => 1, 'ok' => 2, 'none' => 3];
$rows = [];
$waitworks = 0;
$reworkworks = 0;
$progresssum = 0;
foreach ($students as $student) {
    $items = $byuser[(int) $student->id] ?? [];
    usort($items, function ($a, $b) use ($rank) {
        return ($rank[$a['state']['mod']] ?? 9) <=> ($rank[$b['state']['mod']] ?? 9);
    });
    $counts = ['wait' => 0, 'rework' => 0, 'ok' => 0, 'none' => 0];
    foreach ($items as $item) {
        $mod = $item['state']['mod'];
        if (isset($counts[$mod])) {
            $counts[$mod]++;
        }
    }
    $waitworks += $counts['wait'];
    $reworkworks += $counts['rework'];
    $progress = 0;
    try {
        $pct = \core_completion\progress::get_course_progress_percentage($course, $student->id);
        $progress = $pct !== null ? (int) round($pct) : 0;
    } catch (Exception $e) {
        $progress = 0;
    }
    $progresssum += $progress;
    $rows[] = [
        'student' => $student,
        'label' => theme_learnhub_student_label($student),
        'items' => $items,
        'counts' => $counts,
        'progress' => $progress,
        'unread' => !empty($threadby[(int) $student->id]) && (int) $threadby[(int) $student->id]->unreadteacher === 1,
    ];
}

usort($rows, function ($a, $b) {
    $aband = $a['counts']['wait'] > 0 ? 0 : ($a['counts']['rework'] > 0 ? 1 : 2);
    $bband = $b['counts']['wait'] > 0 ? 0 : ($b['counts']['rework'] > 0 ? 1 : 2);
    if ($aband !== $bband) {
        return $aband <=> $bband;
    }
    if ($a['progress'] !== $b['progress']) {
        return $a['progress'] <=> $b['progress'];
    }
    return strcmp(
        core_text::strtolower($a['label']['name']),
        core_text::strtolower($b['label']['name'])
    );
});

$shown = array_values(array_filter($rows, function ($row) use ($view) {
    if ($view === 'wait') {
        return $row['counts']['wait'] > 0;
    }
    if ($view === 'rework') {
        return $row['counts']['rework'] > 0;
    }
    return true;
}));

$nstudents = count($rows);
$avg = $nstudents ? (int) round($progresssum / $nstudents) : 0;

$listurl = function ($next) use ($courseid) {
    $params = ['id' => $courseid];
    if ($next !== 'all') {
        $params['view'] = $next;
    }
    return new moodle_url('/theme/learnhub/teacher/students.php', $params);
};

echo $OUTPUT->header();
echo html_writer::start_div('lh-teach lh-teach--students');
echo html_writer::tag(
    'nav',
    html_writer::link(new moodle_url('/theme/learnhub/teacher/index.php'), 'Панель') . ' › ' . s($coursename),
    ['class' => 'lh-crumb']
);

echo html_writer::start_div('lh-stu__head');
echo html_writer::tag('h1', 'Студенты');
$queueurl = theme_learnhub_hw_url($courseid, 0, 0, $waitworks ? 'wait' : 'all');
echo html_writer::link(
    $queueurl,
    $waitworks ? 'Очередь проверки' : 'Домашки',
    ['class' => 'lh-teach__cta']
);
echo html_writer::end_div();
echo html_writer::tag(
    'p',
    'Кто идёт по курсу. «Проверить» открывает работу, «Написать» — диалог куратора.',
    ['class' => 'lh-teach__lead']
);

echo theme_learnhub_teacher_course_switcher($allcourses, $courseid, 'students');

if (!$rows) {
    echo html_writer::div('В курсе пока нет студентов.', 'lh-teach__empty');
    echo html_writer::end_div();
    echo $OUTPUT->footer();
    exit;
}

echo html_writer::start_div('lh-teach__stats');
$stat = function ($key, $strong, $caption, $alert) use ($view, $listurl) {
    $class = 'lh-teach__stat';
    if ($view === $key) {
        $class .= ' is-active';
    }
    if ($alert) {
        $class .= ' is-alert';
    }
    return html_writer::link(
        $listurl($key),
        html_writer::tag('strong', (string) $strong) . html_writer::tag('span', $caption),
        ['class' => $class]
    );
};
echo $stat('all', $nstudents, 'студентов', false);
echo html_writer::start_div('lh-teach__stat');
echo html_writer::tag('strong', $avg . '%');
echo html_writer::tag('span', 'средний прогресс');
echo html_writer::end_div();
echo $stat('wait', $waitworks, 'на проверке', $waitworks > 0);
echo $stat('rework', $reworkworks, 'на доработке', $reworkworks > 0);
echo html_writer::end_div();

if (!$shown) {
    $empty = [
        'wait' => 'Нет работ, которые ждут проверки.',
        'rework' => 'Никого не отправили на доработку.',
    ];
    echo html_writer::div($empty[$view] ?? 'Таких студентов нет.', 'lh-teach__empty');
} else {
    echo html_writer::start_div('lh-stu__list');
    foreach ($shown as $row) {
        $student = $row['student'];
        $label = $row['label'];
        $counts = $row['counts'];
        $cardclass = 'lh-stu';
        if ($counts['wait'] > 0) {
            $cardclass .= ' is-wait';
        } else if ($counts['rework'] > 0) {
            $cardclass .= ' is-rework';
        }

        $bits = [];
        if ($counts['wait']) {
            $bits[] = $counts['wait'] . ' на проверке';
        }
        if ($counts['rework']) {
            $bits[] = $counts['rework'] . ' на доработке';
        }
        if ($counts['ok']) {
            $bits[] = theme_learnhub_ru_n($counts['ok'], 'принята', 'приняты', 'принято');
        }
        if ($counts['none']) {
            $bits[] = theme_learnhub_ru_n($counts['none'], 'не сдана', 'не сданы', 'не сдано');
        }

        $focus = null;
        foreach ($row['items'] as $item) {
            if ($item['state']['mod'] === 'wait' || ($focus === null && $item['state']['mod'] === 'rework')) {
                $focus = $item;
                if ($item['state']['mod'] === 'wait') {
                    break;
                }
            }
        }

        echo html_writer::start_tag('article', ['class' => $cardclass]);
        echo html_writer::start_div('lh-stu__top');
        echo html_writer::span(\theme_learnhub\curator::initials($label['name']), 'lh-chat__avatar');
        echo html_writer::start_div('lh-stu__id');
        echo html_writer::tag('strong', s($label['name']));
        if ($label['email'] !== '') {
            echo html_writer::tag('span', s($label['email']));
        }
        echo html_writer::end_div();
        echo html_writer::start_div('lh-stu__actions');
        if ($focus) {
            $open = theme_learnhub_hw_url(
                $courseid,
                $focus['cmid'],
                $student->id,
                $focus['state']['mod']
            );
            $btn = $focus['state']['mod'] === 'wait' ? 'Проверить' : 'Доработка';
            echo html_writer::link($open, $btn, ['class' => 'lh-stu__btn lh-stu__btn--primary']);
        }
        $chat = new moodle_url('/theme/learnhub/teacher/curator.php', [
            'id' => $courseid,
            'student' => $student->id,
        ]);
        $chatlabel = 'Написать';
        if ($row['unread']) {
            $chatlabel .= html_writer::span('', 'lh-stu__dot');
        }
        echo html_writer::link($chat, $chatlabel, ['class' => 'lh-stu__btn lh-stu__btn--ghost']);
        echo html_writer::end_div();
        echo html_writer::end_div();

        echo html_writer::start_div('lh-stu__bar');
        echo html_writer::start_div('lh-course-card__progress');
        echo html_writer::tag('span', '', ['style' => 'width:' . max(0, min(100, $row['progress'])) . '%']);
        echo html_writer::end_div();
        echo html_writer::tag('strong', $row['progress'] . '%');
        echo html_writer::end_div();

        if ($bits) {
            echo html_writer::tag('p', implode(' · ', $bits), ['class' => 'lh-stu__sum']);
        }

        if ($row['items']) {
            $details = ['class' => 'lh-stu__tasks'];
            if ($counts['wait'] || $counts['rework']) {
                $details['open'] = 'open';
            }
            echo html_writer::start_tag('details', $details);
            echo html_writer::tag('summary', 'Задания');
            foreach ($row['items'] as $item) {
                $inner = html_writer::span(s($item['task']), 'lh-stu__task-name')
                    . theme_learnhub_hw_badge($item['state']);
                if ($item['state']['mod'] === 'none') {
                    echo html_writer::div($inner, 'lh-stu__task is-static');
                } else {
                    $url = theme_learnhub_hw_url($courseid, $item['cmid'], $student->id, $item['state']['mod']);
                    echo html_writer::link($url, $inner, ['class' => 'lh-stu__task']);
                }
            }
            echo html_writer::end_tag('details');
        }
        echo html_writer::end_tag('article');
    }
    echo html_writer::end_div();
}

echo html_writer::end_div();
echo $OUTPUT->footer();
