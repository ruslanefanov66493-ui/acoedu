<?php
/**
 * Shared guards and stats for the teacher cabinet.
 *
 * @package theme_learnhub
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Require a teacher/editingteacher (not site admin cabinet).
 *
 * @return \stdClass[] teacher courses
 */
function theme_learnhub_require_teacher() {
    require_login();
    if (isguestuser()) {
        redirect(new moodle_url('/login/index.php'));
    }
    if (\theme_learnhub\roles::kind() === 'admin') {
        redirect(new moodle_url('/theme/learnhub/builder/index.php'));
    }
    if (\theme_learnhub\roles::kind() !== 'teacher') {
        throw new moodle_exception('nopermissions', 'error', '', 'teacher');
    }
    return \theme_learnhub\roles::teacher_courses();
}

/**
 * @param int $courseid
 * @return \stdClass
 */
function theme_learnhub_require_teacher_course($courseid) {
    $courses = theme_learnhub_require_teacher();
    foreach ($courses as $course) {
        if ((int) $course->id === (int) $courseid) {
            $ctx = context_course::instance($course->id);
            require_capability('mod/assign:grade', $ctx);
            return $course;
        }
    }
    throw new moodle_exception('invalidcourseid');
}

/**
 * Enrolled students (not graders) in a course.
 *
 * @param \stdClass $course
 * @return \stdClass[]
 */
function theme_learnhub_course_students($course) {
    $ctx = context_course::instance($course->id);
    $users = get_enrolled_users(
        $ctx,
        'mod/assign:submit',
        0,
        'u.id, u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic, u.middlename, u.alternatename, u.email, u.picture, u.imagealt'
    );
    $out = [];
    foreach ($users as $user) {
        if (has_capability('mod/assign:grade', $ctx, $user)) {
            continue;
        }
        $out[] = $user;
    }
    return $out;
}

/**
 * Assignment cms that look like homework.
 *
 * @param \stdClass $course
 * @return \cm_info[]
 */
function theme_learnhub_homework_cms($course) {
    $modinfo = get_fast_modinfo($course);
    $out = [];
    foreach ($modinfo->get_cms() as $cm) {
        if ($cm->modname !== 'assign' || !$cm->uservisible) {
            continue;
        }
        $out[] = $cm;
    }
    return $out;
}

/**
 * Human label + CSS modifier for an assignment submission.
 *
 * @param \stdClass|null $sub
 * @param \stdClass|null $grade
 * @return array{label:string,mod:string}
 */
function theme_learnhub_hw_state($sub, $grade) {
    if (!$sub || $sub->status === ASSIGN_SUBMISSION_STATUS_NEW) {
        return ['label' => 'Не сдано', 'mod' => 'none'];
    }
    if ($grade && $grade->grade !== null && (float) $grade->grade >= 0) {
        if ((float) $grade->grade > 0) {
            return ['label' => 'Принято', 'mod' => 'ok'];
        }
        return ['label' => 'Доработка', 'mod' => 'rework'];
    }
    if ($sub->status === ASSIGN_SUBMISSION_STATUS_SUBMITTED) {
        return ['label' => 'На проверке', 'mod' => 'wait'];
    }
    return ['label' => (string) $sub->status, 'mod' => 'none'];
}

/**
 * HTML pill for homework state.
 *
 * @param array{label:string,mod:string} $state
 * @return string
 */
function theme_learnhub_hw_badge(array $state) {
    return html_writer::span(s($state['label']), 'lh-teach__badge lh-teach__badge--' . $state['mod']);
}

/**
 * Course switcher used on students/homework screens.
 *
 * @param \stdClass[] $courses
 * @param int $currentid
 * @param string $page students|homework|curator
 * @return string
 */
function theme_learnhub_teacher_course_switcher(array $courses, $currentid, $page) {
    if (count($courses) < 2) {
        return '';
    }
    $html = html_writer::start_div('lh-teach__switch');
    $html .= html_writer::tag('span', 'Курс', ['class' => 'lh-teach__switch-label']);
    foreach ($courses as $course) {
        $url = new moodle_url('/theme/learnhub/teacher/' . $page . '.php', ['id' => $course->id]);
        $class = 'lh-teach__chip';
        if ((int) $course->id === (int) $currentid) {
            $class .= ' is-active';
        }
        $html .= html_writer::link($url, format_string($course->fullname), ['class' => $class]);
    }
    $html .= html_writer::end_div();
    return $html;
}

/**
 * Overview numbers + pending queue for the teacher home.
 *
 * @param \stdClass[] $courses
 * @return array
 */
function theme_learnhub_teacher_overview(array $courses) {
    global $CFG;
    require_once($CFG->dirroot . '/mod/assign/locallib.php');

    $stats = [
        'courses' => count($courses),
        'students' => 0,
        'pending' => 0,
        'questions' => \theme_learnhub\curator::unread_teacher_total($courses),
    ];
    $cards = [];
    $queue = [];

    foreach ($courses as $course) {
        $students = theme_learnhub_course_students($course);
        $nstudents = count($students);
        $stats['students'] += $nstudents;
        $assigns = theme_learnhub_homework_cms($course);
        $pendinghere = 0;

        foreach ($assigns as $cm) {
            $assign = new assign(context_module::instance($cm->id), $cm, $course);
            foreach ($students as $student) {
                $sub = $assign->get_user_submission($student->id, false);
                $grade = $assign->get_user_grade($student->id, false);
                $state = theme_learnhub_hw_state($sub, $grade);
                if ($state['mod'] !== 'wait') {
                    continue;
                }
                $pendinghere++;
                $stats['pending']++;
                if (count($queue) < 12) {
                    $queue[] = [
                        'student' => fullname($student),
                        'course' => format_string($course->fullname),
                        'task' => $cm->get_formatted_name(),
                        'url' => new moodle_url('/theme/learnhub/teacher/homework.php', [
                            'id' => $course->id,
                            'cmid' => $cm->id,
                            'userid' => $student->id,
                        ]),
                    ];
                }
            }
        }

        $progressavg = 0;
        if ($nstudents) {
            $sum = 0;
            foreach ($students as $student) {
                try {
                    $pct = \core_completion\progress::get_course_progress_percentage($course, $student->id);
                    $sum += $pct !== null ? (int) round($pct) : 0;
                } catch (Exception $e) {
                    $sum += 0;
                }
            }
            $progressavg = (int) round($sum / $nstudents);
        }

        $cards[] = [
            'course' => $course,
            'students' => $nstudents,
            'pending' => $pendinghere,
            'progress' => $progressavg,
        ];
    }

    return ['stats' => $stats, 'cards' => $cards, 'queue' => $queue];
}

/**
 * @param int $courseid
 * @param int $cmid
 * @param int $userid
 * @param string $filter
 * @return moodle_url
 */
function theme_learnhub_hw_url($courseid, $cmid = 0, $userid = 0, $filter = 'wait') {
    $params = ['id' => (int) $courseid, 'filter' => $filter];
    if ($cmid) {
        $params['cmid'] = (int) $cmid;
    }
    if ($userid) {
        $params['userid'] = (int) $userid;
    }
    return new moodle_url('/theme/learnhub/teacher/homework.php', $params);
}

/**
 * @param string $filter
 * @return string
 */
function theme_learnhub_hw_normalize_filter($filter) {
    $filter = (string) $filter;
    return in_array($filter, ['wait', 'rework', 'ok', 'all'], true) ? $filter : 'wait';
}

/**
 * @param \stdClass|null $sub
 * @return string
 */
function theme_learnhub_hw_when_label($sub) {
    if (!$sub || empty($sub->timemodified) || $sub->status === ASSIGN_SUBMISSION_STATUS_NEW) {
        return '';
    }
    return userdate($sub->timemodified, '%d.%m.%Y, %H:%M');
}

/**
 * Teacher comment stored by the assign feedback plugin.
 *
 * @param assign $assign
 * @param \stdClass|null $grade
 * @return string
 */
function theme_learnhub_hw_comment($assign, $grade) {
    global $DB;

    if (!$grade || empty($grade->id)) {
        return '';
    }
    $row = $DB->get_record('assignfeedback_comments', [
        'assignment' => $assign->get_instance()->id,
        'grade' => $grade->id,
    ]);
    if (!$row || $row->commenttext === '') {
        return '';
    }
    return trim(html_to_text($row->commenttext, 0, false));
}

/**
 * Every student × assignment row for the teacher inbox.
 *
 * @param \stdClass $course
 * @param \cm_info[] $assigns
 * @param \stdClass[] $students
 * @return array
 */
function theme_learnhub_hw_entries($course, array $assigns, array $students) {
    global $CFG;
    require_once($CFG->dirroot . '/mod/assign/locallib.php');

    $out = [];
    foreach ($assigns as $cm) {
        $assign = new assign(context_module::instance($cm->id), $cm, $course);
        foreach ($students as $student) {
            $sub = $assign->get_user_submission($student->id, false);
            $grade = $assign->get_user_grade($student->id, false);
            $state = theme_learnhub_hw_state($sub, $grade);
            $out[] = [
                'cmid' => (int) $cm->id,
                'userid' => (int) $student->id,
                'student' => $student,
                'task' => $cm->get_formatted_name(),
                'sub' => $sub,
                'grade' => $grade,
                'state' => $state,
                'when' => ($sub && !empty($sub->timemodified) && $sub->status !== ASSIGN_SUBMISSION_STATUS_NEW)
                    ? (int) $sub->timemodified : 0,
            ];
        }
    }
    return $out;
}

/**
 * @param array $entries
 * @return array{wait:int,rework:int,ok:int,all:int}
 */
function theme_learnhub_hw_counts(array $entries) {
    $counts = ['wait' => 0, 'rework' => 0, 'ok' => 0, 'all' => 0];
    foreach ($entries as $entry) {
        $mod = $entry['state']['mod'];
        if (isset($counts[$mod])) {
            $counts[$mod]++;
        }
        if ($mod !== 'none') {
            $counts['all']++;
        }
    }
    return $counts;
}

/**
 * @param array $entries
 * @param string $filter
 * @return array
 */
function theme_learnhub_hw_filter(array $entries, $filter) {
    $filter = theme_learnhub_hw_normalize_filter($filter);
    $out = [];
    foreach ($entries as $entry) {
        $mod = $entry['state']['mod'];
        if ($filter === 'all') {
            if ($mod !== 'none') {
                $out[] = $entry;
            }
            continue;
        }
        if ($mod === $filter) {
            $out[] = $entry;
        }
    }
    usort($out, function ($a, $b) use ($filter) {
        if ($filter === 'wait') {
            return ($a['when'] <=> $b['when']);
        }
        return ($b['when'] <=> $a['when']);
    });
    return $out;
}

/**
 * Next submitted work still waiting after the current review.
 *
 * @param array $entries
 * @param int $cmid
 * @param int $userid
 * @return array|null
 */
function theme_learnhub_hw_next_pending(array $entries, $cmid, $userid) {
    foreach ($entries as $entry) {
        if ($entry['state']['mod'] !== 'wait') {
            continue;
        }
        if ((int) $entry['cmid'] === (int) $cmid && (int) $entry['userid'] === (int) $userid) {
            continue;
        }
        return $entry;
    }
    return null;
}
