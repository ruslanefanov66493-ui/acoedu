<?php
namespace theme_learnhub;

defined('MOODLE_INTERNAL') || die();

use completion_info;
use context_course;
use html_writer;
use moodle_url;

/**
 * Builds the student course-player tree from Moodle sections and activities.
 *
 * Mapping for the customer (idnumber on the activity):
 *   video or video:12  — video lesson (optional duration in minutes)
 *   page               — text lesson
 *   practice           — assignment
 *   summary            — self-check / recap
 *   test               — quiz
 *   finaltest          — course final quiz
 *   file               — file resource
 *   link               — external URL
 *   final              — final project (assignment)
 *
 * @package theme_learnhub
 */
class course_player {

    /** @var \stdClass */
    private $course;

    /** @var int */
    private $userid;

    /** @var \cm_info|null */
    private $currentcm;

    /** @var \course_modinfo */
    private $modinfo;

    /** @var completion_info */
    private $completion;

    /** @var array */
    private $items = [];

    /** @var \stdClass|null */
    private $exportdata;

    /**
     * @param \stdClass $course
     * @param \cm_info|\stdClass|null $cm
     */
    public function __construct($course, $cm = null) {
        global $USER;

        $this->course = get_course($course->id);
        $this->userid = (int) $USER->id;
        self::repair_completion_tracking((int) $this->course->id);
        $this->modinfo = get_fast_modinfo($this->course, $this->userid);
        $this->completion = new completion_info($this->course);
        if ($cm) {
            $this->currentcm = $this->modinfo->get_cm((int) $cm->id);
        }
        $this->items = $this->apply_assign_gates($this->collect_items());
    }

    /**
     * Open player chrome (left programme nav). Layout must still echo main_content().
     *
     * @param string $mode home|activity
     * @return string
     */
    public function render_shell_start(string $mode): string {
        global $OUTPUT, $PAGE;

        $this->exportdata = $this->export($mode);
        $PAGE->requires->js(new moodle_url('/theme/learnhub/javascript/courseplayer.js', ['v' => 2026091104]));

        $d = $this->exportdata;
        $left = $OUTPUT->render_from_template('theme_learnhub/courseplayer_nav', $d);
        $line = 'Открыть модули и прогресс';
        if (!empty($d->hascurrent)) {
            $line = $d->lesson_index . ' · ' . $d->lesson_title;
        } else if (!empty($d->coursename)) {
            $line = $d->coursename;
        }

        $toggle = html_writer::start_tag('button', [
            'type' => 'button',
            'class' => 'aco-player__menu-btn',
            'data-aco-toggle' => '1',
            'aria-expanded' => 'false',
            'aria-controls' => 'aco-course-nav',
        ]);
        $toggle .= html_writer::tag('span', ((int) ($d->percent ?? 0)) . '%', ['class' => 'aco-player__menu-pct']);
        $toggle .= html_writer::start_div('aco-player__menu-txt');
        $toggle .= html_writer::tag('strong', 'Программа');
        $toggle .= html_writer::tag('span', $line);
        $toggle .= html_writer::end_div();
        $toggle .= html_writer::tag('span', '', ['class' => 'aco-player__menu-chev', 'aria-hidden' => 'true']);
        $toggle .= html_writer::end_tag('button');

        $type = 'home';
        if (!empty($d->lesson_typekey)) {
            $type = preg_replace('/[^a-z]/', '', (string) $d->lesson_typekey) ?: 'activity';
        } else if ($mode === 'activity') {
            $type = 'activity';
        }

        $html = html_writer::start_div('aco-player aco-player--' . $type, [
            'data-courseid' => $this->course->id,
            'data-sesskey' => sesskey(),
            'data-complete-url' => (new moodle_url('/theme/learnhub/ajax/complete.php'))->out(false),
        ]);
        $html .= $toggle;
        $html .= html_writer::div('', 'aco-player__scrim', ['data-aco-toggle' => '1']);
        $html .= html_writer::div($left, 'aco-player__nav', [
            'id' => 'aco-course-nav',
            'role' => 'navigation',
            'aria-label' => 'Программа курса',
        ]);
        $html .= html_writer::start_div('aco-player__main');
        return $html;
    }

    /**
     * @return string
     */
    public function render_home_panel(): string {
        global $OUTPUT;
        return $OUTPUT->render_from_template('theme_learnhub/courseplayer_home', $this->data());
    }

    /**
     * @return string
     */
    public function render_toolbar(): string {
        global $OUTPUT;
        return $OUTPUT->render_from_template('theme_learnhub/courseplayer_toolbar', $this->data());
    }

    /**
     * @return string
     */
    public function render_pager(): string {
        global $OUTPUT;
        return $OUTPUT->render_from_template('theme_learnhub/courseplayer_pager', $this->data());
    }

    /**
     * Later lessons stay closed until the teacher accepts the blocking assignment.
     *
     * @return bool
     */
    public function content_blocked(): bool {
        $cur = $this->current_item();
        return $cur && !empty($cur['locked']);
    }

    /**
     * Shown instead of the Moodle activity when the student opened a gated lesson.
     *
     * @return string
     */
    public function render_gate(): string {
        $cur = $this->current_item();
        $reason = ($cur && !empty($cur['lockreason']))
            ? $cur['lockreason']
            : 'Сначала преподаватель должен принять предыдущее задание';
        $html = html_writer::start_div('aco-gate');
        $html .= html_writer::tag('p', 'Урок закрыт', ['class' => 'aco-gate__kicker']);
        $html .= html_writer::tag('h2', 'Пока нельзя продолжить');
        $html .= html_writer::tag('p', s($reason), ['class' => 'aco-gate__text']);
        $html .= html_writer::end_div();
        return $html;
    }

    /**
     * Student-facing review card for homework: teacher decision + own answer.
     *
     * @return string
     */
    public function render_assign_review(): string {
        global $CFG, $DB, $PAGE;

        $cur = $this->current_item();
        if (!$cur || !in_array($cur['type'], ['practice', 'final'], true) || !empty($cur['locked'])) {
            return '';
        }
        if (!$this->currentcm || $this->currentcm->modname !== 'assign') {
            return '';
        }

        require_once($CFG->dirroot . '/mod/assign/locallib.php');

        $cm = $this->currentcm;
        $assign = new \assign(\context_module::instance((int) $cm->id), $cm, $this->course);
        $submission = $assign->get_user_submission($this->userid, false);
        $grade = $assign->get_user_grade($this->userid, false);
        $status = $cur['statuslabel'];

        $show = in_array($status, ['На проверке', 'Требуется доработка', 'Принято', 'Отправлено'], true);
        if (!$show && $submission && $submission->status !== ASSIGN_SUBMISSION_STATUS_NEW) {
            $show = true;
        }
        if (!$show) {
            return '';
        }

        $mod = 'wait';
        $title = 'Работа у преподавателя';
        $lead = 'Следующий урок откроется, когда работу примут.';
        if ($status === 'Требуется доработка') {
            $mod = 'rework';
            $title = 'Нужно исправить';
            $lead = 'Исправьте работу по комментарию и отправьте снова.';
        } else if ($status === 'Принято') {
            $mod = 'ok';
            $title = 'Работа принята';
            $lead = 'Можно переходить к следующему уроку.';
        } else if ($status === 'Не начато') {
            return '';
        }

        $comment = '';
        if ($grade && !empty($grade->id)) {
            $row = $DB->get_record('assignfeedback_comments', [
                'assignment' => $assign->get_instance()->id,
                'grade' => $grade->id,
            ]);
            if ($row && $row->commenttext !== '') {
                $comment = trim(html_to_text($row->commenttext, 0, false));
            }
        }

        $teacher = '';
        $when = '';
        if ($grade && !empty($grade->grader)) {
            $grader = \core_user::get_user($grade->grader);
            if ($grader) {
                $teacher = fullname($grader);
            }
        }
        if ($grade && !empty($grade->timemodified)) {
            $when = userdate($grade->timemodified, '%d.%m.%Y, %H:%M');
        }

        $onedit = $PAGE->url && strpos((string) $PAGE->url->get_path(), 'editsubmission') !== false;

        $html = html_writer::start_div('aco-review aco-review--' . $mod);
        $html .= html_writer::tag('p', s($status), ['class' => 'aco-review__kicker']);
        $html .= html_writer::tag('h2', $title);
        $html .= html_writer::tag('p', $lead, ['class' => 'aco-review__lead']);
        if ($comment !== '') {
            $html .= html_writer::tag('p', s($comment), ['class' => 'aco-review__comment']);
        }
        if ($teacher !== '' || $when !== '') {
            $meta = $teacher;
            if ($when !== '') {
                $meta .= ($meta !== '' ? ' · ' : '') . $when;
            }
            $html .= html_writer::tag('p', s($meta), ['class' => 'aco-review__meta']);
        }
        $html .= html_writer::end_div();

        if (!$onedit && $submission && $submission->status !== ASSIGN_SUBMISSION_STATUS_NEW) {
            $body = '';
            foreach ($assign->get_submission_plugins() as $plugin) {
                if (!$plugin->is_enabled() || !$plugin->is_visible() || $plugin->get_type() === 'comments') {
                    continue;
                }
                $pluginhtml = $plugin->view($submission);
                if (trim(html_to_text($pluginhtml, 0, false)) === '') {
                    continue;
                }
                $body .= html_writer::tag('div', $pluginhtml, ['class' => 'aco-review__plugin'], false);
            }
            if ($body !== '') {
                $html .= html_writer::start_div('aco-review-answer');
                $html .= html_writer::tag('h3', 'Ваш ответ');
                $html .= $body;
                $html .= html_writer::end_div();
            }
        }

        return $html;
    }

    /**
     * Close player chrome opened by render_shell_start().
     *
     * @return string
     */
    public function render_shell_end(): string {
        return html_writer::end_div() . html_writer::end_div();
    }

    /**
     * @return \stdClass
     */
    private function data(): \stdClass {
        if ($this->exportdata === null) {
            $this->exportdata = $this->export(!empty($this->currentcm) ? 'activity' : 'home');
        }
        return $this->exportdata;
    }

    /**
     * @param string $mode
     * @return \stdClass
     */
    public function export(string $mode): \stdClass {
        global $CFG, $PAGE;

        $ctx = context_course::instance($this->course->id);
        $counts = $this->counts();
        $percent = $counts['total'] ? (int) round(100 * $counts['done'] / $counts['total']) : 0;
        if ($percent >= 100) {
            $status = 'Завершён';
            $statusclass = 'done';
        } else if ($counts['done'] > 0) {
            $status = 'В процессе';
            $statusclass = 'progress';
        } else {
            $status = 'Не начат';
            $statusclass = 'idle';
        }

        $current = $this->current_item();
        $nav = $this->prev_next();

        $data = new \stdClass();
        $data->mode = $mode;
        $data->courseid = (int) $this->course->id;
        $data->coursename = format_string($this->course->fullname, true, ['context' => $ctx]);
        $data->coursesummary = format_text($this->course->summary, $this->course->summaryformat, ['context' => $ctx]);
        $plain = trim(html_to_text($data->coursesummary, 0));
        $data->haslead = ($plain !== '' && $plain !== $data->coursename);
        $data->cover = $this->cover_url();
        $data->hascover = !empty($data->cover);
        $data->hasfinal = $this->has_type('final');
        $data->percent = $percent;
        $data->status = $status;
        $data->statusclass = $statusclass;
        $data->video_done = $counts['video_done'];
        $data->video_total = $counts['video_total'];
        $data->practice_done = $counts['practice_done'];
        $data->practice_total = $counts['practice_total'];
        $data->test_done = $counts['test_done'];
        $data->test_total = $counts['test_total'];
        $data->modules = $this->export_modules();
        $data->finaltest = $this->export_special('finaltest');
        $data->finalwork = $this->export_special('final');
        $data->hasfinaltest = !empty($data->finaltest);
        $data->hasfinalwork = !empty($data->finalwork);
        $data->certificateurl = (new moodle_url('/theme/learnhub/certificate.php', ['id' => $this->course->id]))->out(false);
        $data->certificate_ready = ($percent >= 100);
        $data->certificate_hint = $data->certificate_ready
            ? 'Можно скачать сертификат'
            : 'Сертификат откроется после выполнения программы курса';
        $data->continueurl = $this->continue_url();
        $data->continuelabel = $counts['done'] ? 'Продолжить обучение' : 'Начать обучение';
        $data->curatorurl = (new moodle_url('/theme/learnhub/curator.php', ['id' => $this->course->id]))->out(false);
        $curatorunread = curator::unread_student((int) $this->course->id, $this->userid);
        $data->curator_unread = $curatorunread > 0;
        $pagepath = (!empty($PAGE->url)) ? (string) $PAGE->url->get_path() : '';
        $data->curator_current = strpos($pagepath, '/theme/learnhub/curator.php') !== false;
        $data->curatorlabel = $data->curator_unread ? 'Куратор (' . $curatorunread . ')' : 'Куратор';
        $data->curator_hint = $data->curator_unread ? 'Есть ответ куратора' : 'Вопрос по уроку или домашке';
        $data->crumb = $mode === 'activity' ? 'lesson' : 'course';
        $data->islesson = ($mode === 'activity');

        if ($current) {
            $data->hascurrent = true;
            $data->lesson_index = $current['index'];
            $data->lesson_title = $current['name'];
            $data->lesson_type = $current['typelabel'];
            $data->lesson_typekey = $current['type'];
            $data->lesson_duration = $current['duration'];
            $data->lesson_status = $current['statuslabel'];
            $data->cmid = $current['cmid'];
            $data->can_complete = $current['can_complete'] && !$current['complete'];
            $data->is_complete = $current['complete'];
            $data->locked_reason = $current['locked'] ? $current['lockreason'] : '';
            $data->complete_hint = '';
            if (!$data->can_complete && !$data->is_complete && !$current['locked']) {
                if (in_array($current['type'], ['test', 'finaltest'], true)) {
                    $data->complete_hint = 'Тест засчитается после отправки ответов. Модуль закроется, когда будут пройдены все уроки и тесты.';
                } else if (in_array($current['type'], ['practice', 'final'], true)) {
                    $data->complete_hint = $this->assign_wait_hint($current);
                }
            }
        } else {
            $data->hascurrent = false;
        }

        $data->hasprev = !empty($nav['prev']);
        $data->prevurl = $nav['prev']['url'] ?? '';
        $data->prevlabel = $nav['prev']['name'] ?? '';
        $data->hasnext = !empty($nav['next']);
        $data->nexturl = $nav['next']['url'] ?? '';
        $data->nextlabel = $nav['next']['name'] ?? '';
        $data->nextlocked = !empty($nav['next']['locked']);
        $data->nextlockreason = $nav['next']['lockreason'] ?? 'Сначала завершите текущий урок';
        if ($current && in_array($current['type'], ['practice', 'final'], true) && empty($current['complete'])) {
            $data->nextlocked = true;
            $data->nextlockreason = $this->assign_wait_hint($current);
        }

        return $data;
    }

    /**
     * @return array
     */
    private function collect_items(): array {
        $out = [];
        $modnum = 0;

        foreach ($this->modinfo->get_section_info_all() as $section) {
            if ((int) $section->section === 0) {
                continue;
            }
            $role = $this->section_role($section);
            if ($role === 'module') {
                $modnum++;
            }
            $seq = 0;
            foreach ($this->modinfo->get_cms() as $cm) {
                if ((int) $cm->sectionnum !== (int) $section->section) {
                    continue;
                }
                if ($cm->deletioninprogress || in_array($cm->modname, ['label', 'forum'], true)) {
                    continue;
                }
                $seq++;
                $type = $this->item_type($cm, $role);
                $complete = $this->is_complete($cm);
                $visible = (bool) $cm->uservisible;
                $lockreason = '';
                if (!$visible) {
                    $lockreason = trim(html_to_text($cm->availableinfo ?? '', 0));
                    if ($lockreason === '') {
                        $lockreason = $modnum > 1
                            ? 'Откроется после завершения предыдущего урока'
                            : 'Материал пока недоступен';
                    }
                }
                $index = ($role === 'module') ? $modnum . '.' . $seq : $this->special_index($role, $seq);
                $out[] = [
                    'cmid' => (int) $cm->id,
                    'section' => (int) $section->section,
                    'sectionid' => (int) $section->id,
                    'modnum' => $modnum,
                    'seq' => $seq,
                    'index' => $index,
                    'name' => $cm->get_formatted_name(),
                    'url' => $cm->url ? $cm->url->out(false) : '',
                    'modname' => $cm->modname,
                    'type' => $type,
                    'typelabel' => $this->type_label($type),
                    'duration' => $this->duration_label($cm, $type),
                    'role' => $role,
                    'complete' => $complete,
                    'locked' => !$visible,
                    'lockreason' => $lockreason,
                    'current' => $this->currentcm && (int) $this->currentcm->id === (int) $cm->id,
                    'can_complete' => $visible
                        && $this->is_manual_lesson_type($type)
                        && (int) $cm->completion === COMPLETION_TRACKING_MANUAL,
                    'statuslabel' => $this->status_label($cm, $complete, $visible, $type),
                    'sectionname' => $this->section_title($section, $modnum, $role),
                    'sectionsummary' => format_text($section->summary, $section->summaryformat),
                    'idnumber' => (string) $cm->idnumber,
                ];
            }
        }

        return $out;
    }

    /**
     * @param \section_info $section
     * @return string module|finaltest|final
     */
    private function section_role($section): string {
        $id = strtolower(trim((string) $section->idnumber));
        if (in_array($id, ['finaltest', 'final-test', 'exam'], true)) {
            return 'finaltest';
        }
        if (in_array($id, ['final', 'finalwork', 'project'], true)) {
            return 'final';
        }
        $name = core_text_lower($section->name ?? '');
        if ($name && preg_match('/итог|аттестац|экзамен/u', $name)) {
            return 'finaltest';
        }
        if ($name && preg_match('/финальн/u', $name)) {
            return 'final';
        }
        return 'module';
    }

    /**
     * @param \cm_info $cm
     * @param string $sectionrole
     * @return string
     */
    private function item_type($cm, string $sectionrole): string {
        $id = strtolower(trim((string) $cm->idnumber));
        if (strpos($id, 'video') === 0) {
            return 'video';
        }
        if (in_array($id, ['practice', 'assign', 'homework'], true)) {
            return 'practice';
        }
        if (in_array($id, ['summary', 'recap', 'selfcheck'], true)) {
            return 'summary';
        }
        if (in_array($id, ['finaltest', 'exam'], true) || $sectionrole === 'finaltest') {
            return 'finaltest';
        }
        if (in_array($id, ['final', 'project'], true) || $sectionrole === 'final') {
            return 'final';
        }
        if ($id === 'test' || $id === 'quiz') {
            return 'test';
        }
        if ($id === 'file' || $id === 'resource') {
            return 'file';
        }
        if ($id === 'link' || $id === 'url') {
            return 'link';
        }
        if ($id === 'page' || $id === 'lesson') {
            return 'page';
        }

        $name = core_text_lower($cm->name);
        if ($cm->modname === 'assign') {
            return (preg_match('/финальн/u', $name)) ? 'final' : 'practice';
        }
        if ($cm->modname === 'quiz') {
            return (preg_match('/итогов/u', $name)) ? 'finaltest' : 'test';
        }
        if ($cm->modname === 'resource') {
            return 'file';
        }
        if ($cm->modname === 'url') {
            $url = strtolower((string) ($cm->get_custom_data()['url'] ?? ''));
            if (preg_match('/youtube|youtu\.be|vimeo|\.mp4/', $url)) {
                return 'video';
            }
            return 'link';
        }
        if (preg_match('/итог|проверьте себя/u', $name)) {
            return 'summary';
        }
        if (in_array($cm->modname, ['page', 'lesson', 'book', 'h5pactivity', 'url'], true)) {
            return 'video';
        }
        return 'video';
    }

    /**
     * @param string $type
     * @return string
     */
    private function type_label(string $type): string {
        $map = [
            'video' => 'Видео',
            'page' => 'Урок',
            'practice' => 'Практическая работа',
            'test' => 'Тест',
            'summary' => 'Итоги / самопроверка',
            'file' => 'Файл',
            'link' => 'Внешняя ссылка',
            'finaltest' => 'Итоговое тестирование',
            'final' => 'Финальная работа',
        ];
        return $map[$type] ?? 'Материал';
    }

    /**
     * @param \cm_info $cm
     * @param string $type
     * @return string
     */
    private function duration_label($cm, string $type): string {
        if ($type !== 'video') {
            return '';
        }
        $id = (string) $cm->idnumber;
        if (preg_match('/video:(\d+)/', $id, $m)) {
            return $this->minutes_label((int) $m[1]);
        }
        if (preg_match('/(\d+)\s*мин/u', $cm->name, $m)) {
            return $this->minutes_label((int) $m[1]);
        }
        return '';
    }

    /**
     * @param int $mins
     * @return string
     */
    private function minutes_label(int $mins): string {
        $n = $mins % 100;
        $n1 = $mins % 10;
        if ($n > 10 && $n < 20) {
            $w = 'минут';
        } else if ($n1 === 1) {
            $w = 'минута';
        } else if ($n1 >= 2 && $n1 <= 4) {
            $w = 'минуты';
        } else {
            $w = 'минут';
        }
        return $mins . ' ' . $w;
    }

    /**
     * @param \cm_info $cm
     * @return bool
     */
    private function is_complete($cm): bool {
        if ($cm->modname === 'assign') {
            return $this->assign_accepted($cm);
        }
        if (!$this->completion->is_enabled($cm)) {
            return false;
        }
        $data = $this->completion->get_data($cm, false, $this->userid);
        return in_array((int) $data->completionstate, [COMPLETION_COMPLETE, COMPLETION_COMPLETE_PASS], true);
    }

    /**
     * Teacher accept is a positive grade. Submit alone is not enough.
     *
     * @param \cm_info $cm
     * @return bool
     */
    private function assign_accepted($cm): bool {
        $grade = $this->latest_assign_grade($cm);
        return $grade && $grade->grade !== null && (float) $grade->grade > 0;
    }

    /**
     * @param \cm_info $cm
     * @return \stdClass|null
     */
    private function latest_assign_grade($cm): ?\stdClass {
        global $DB;

        $grades = $DB->get_records('assign_grades', [
            'assignment' => $cm->instance,
            'userid' => $this->userid,
        ], 'attemptnumber DESC', '*', 0, 1);
        if (!$grades) {
            return null;
        }
        return reset($grades);
    }

    /**
     * Keep every lesson after an unaccepted assignment closed in the player.
     *
     * @param array $items
     * @return array
     */
    private function apply_assign_gates(array $items): array {
        $blocker = null;
        foreach ($items as $i => $item) {
            $isassign = in_array($item['type'], ['practice', 'final'], true);
            if ($blocker !== null) {
                $items[$i]['locked'] = true;
                $items[$i]['lockreason'] = 'Откроется, когда преподаватель примет задание «' . $blocker . '»';
                $items[$i]['can_complete'] = false;
                $items[$i]['statuslabel'] = 'Заблокирован';
            }
            if ($isassign && empty($item['complete']) && empty($items[$i]['locked'])) {
                $blocker = $item['name'];
            }
        }
        return $items;
    }

    /**
     * @param array $item
     * @return string
     */
    private function assign_wait_hint(array $item): string {
        switch ($item['statuslabel'] ?? '') {
            case 'На проверке':
                return 'Следующий урок откроется, когда преподаватель примет работу.';
            case 'Требуется доработка':
                return 'Исправьте работу по комментарию преподавателя и отправьте снова.';
            case 'Принято':
                return '';
            default:
                return 'Отправьте работу. Следующий урок откроется после оценки преподавателя.';
        }
    }

    /**
     * @param \cm_info $cm
     * @param bool $complete
     * @param bool $visible
     * @param string $type
     * @return string
     */
    private function status_label($cm, bool $complete, bool $visible, string $type): string {
        if (!$visible) {
            return 'Заблокирован';
        }
        if (in_array($type, ['practice', 'final'], true)) {
            return $this->assign_status($cm, $complete);
        }
        if (in_array($type, ['test', 'finaltest'], true)) {
            return $complete ? 'Пройден' : 'Не пройден';
        }
        if ($complete) {
            return 'Завершён';
        }
        return 'Не начат';
    }

    /**
     * @param \cm_info $cm
     * @param bool $complete
     * @return string
     */
    private function assign_status($cm, bool $complete): string {
        global $DB, $USER;

        if ($complete) {
            return 'Принято';
        }
        $sub = $DB->get_record('assign_submission', [
            'assignment' => $cm->instance,
            'userid' => $USER->id,
            'latest' => 1,
        ]);
        if (!$sub || $sub->status === 'new') {
            return 'Не начато';
        }
        if ($sub->status === 'submitted') {
            $grade = $DB->get_record('assign_grades', [
                'assignment' => $cm->instance,
                'userid' => $USER->id,
            ]);
            if ($grade && $grade->grade !== null && (float) $grade->grade >= 0) {
                return ((float) $grade->grade > 0) ? 'Принято' : 'Требуется доработка';
            }
            return 'На проверке';
        }
        if ($sub->status === 'draft') {
            return 'Отправлено';
        }
        return 'Не начато';
    }

    /**
     * @param \section_info $section
     * @param int $modnum
     * @param string $role
     * @return string
     */
    private function section_title($section, int $modnum, string $role): string {
        $ctx = context_course::instance($this->course->id);
        $name = format_string($section->name, true, ['context' => $ctx]);
        if ($name === '') {
            if ($role === 'finaltest') {
                return 'Итоговое тестирование';
            }
            if ($role === 'final') {
                return 'Финальная работа';
            }
            return 'Модуль ' . $modnum;
        }
        return $name;
    }

    /**
     * @param string $role
     * @param int $seq
     * @return string
     */
    private function special_index(string $role, int $seq): string {
        if ($role === 'finaltest') {
            return 'Т';
        }
        if ($role === 'final') {
            return 'Ф';
        }
        return (string) $seq;
    }

    /**
     * @return array
     */
    private function export_modules(): array {
        $grouped = [];
        foreach ($this->items as $item) {
            if ($item['role'] !== 'module' || in_array($item['type'], ['finaltest', 'final'], true)) {
                continue;
            }
            $sid = $item['section'];
            if (!isset($grouped[$sid])) {
                $lessons = [];
                $grouped[$sid] = [
                    'number' => $item['modnum'],
                    'name' => $item['sectionname'],
                    'summary' => trim(html_to_text($item['sectionsummary'], 0)),
                    'hassummary' => trim(html_to_text($item['sectionsummary'], 0)) !== '',
                    'status' => 'notstarted',
                    'statuslabel' => 'Не начат',
                    'expanded' => false,
                    'locked' => false,
                    'hascurrent' => false,
                    'done' => 0,
                    'total' => 0,
                    'hascount' => false,
                    'countlabel' => '',
                    'modpct' => 0,
                    'lessons' => [],
                ];
            }
            $lesson = $this->export_lesson($item);
            $grouped[$sid]['lessons'][] = $lesson;
            if ($item['current']) {
                $grouped[$sid]['expanded'] = true;
                $grouped[$sid]['hascurrent'] = true;
            }
        }

        foreach ($grouped as &$mod) {
            $total = count($mod['lessons']);
            $done = 0;
            $alllocked = $total > 0;
            foreach ($mod['lessons'] as $l) {
                if ($l['complete']) {
                    $done++;
                }
                if (!$l['locked']) {
                    $alllocked = false;
                }
            }
            $mod['done'] = $done;
            $mod['total'] = $total;
            $mod['hascount'] = $total > 0;
            $mod['countlabel'] = $done . ' из ' . $total;
            $mod['modpct'] = $total ? (int) round(100 * $done / $total) : 0;
            if ($alllocked) {
                $mod['status'] = 'locked';
                $mod['statuslabel'] = 'Заблокирован';
                $mod['locked'] = true;
            } else if ($total && $done === $total) {
                $mod['status'] = 'done';
                $mod['statuslabel'] = 'Завершён';
            } else if ($done > 0) {
                $mod['status'] = 'progress';
                $mod['statuslabel'] = 'В процессе';
            } else {
                $mod['status'] = 'notstarted';
                $mod['statuslabel'] = 'Не начат';
            }
            if (!$mod['expanded'] && $mod['status'] === 'progress') {
                $mod['expanded'] = true;
            }
            if ($mod['status'] === 'done' && empty($mod['hascurrent'])) {
                $mod['expanded'] = false;
            }
        }
        unset($mod);

        $grouped = array_filter($grouped, function ($mod) {
            return !empty($mod['lessons']);
        });

        if (!$this->currentcm) {
            foreach ($grouped as &$mod) {
                if ($mod['status'] === 'progress' || $mod['status'] === 'notstarted') {
                    $mod['expanded'] = true;
                    break;
                }
            }
            unset($mod);
        }

        return array_values($grouped);
    }

    /**
     * @param array $item
     * @return array
     */
    private function export_lesson(array $item): array {
        return [
            'cmid' => $item['cmid'],
            'index' => $item['index'],
            'name' => $item['name'],
            'url' => $item['url'],
            'type' => $item['type'],
            'typelabel' => $item['typelabel'],
            'duration' => $item['duration'],
            'hasduration' => $item['duration'] !== '',
            'complete' => $item['complete'],
            'locked' => $item['locked'],
            'lockreason' => $item['lockreason'],
            'current' => $item['current'],
            'statuslabel' => $item['statuslabel'],
        ];
    }

    /**
     * @param string $type
     * @return array|null
     */
    private function export_special(string $type): ?array {
        foreach ($this->items as $item) {
            if ($item['type'] === $type || $item['role'] === $type) {
                $row = $this->export_lesson($item);
                $row['openlabel'] = $item['locked'] ? 'Закрыто' : 'Открыть';
                if ($type === 'final') {
                    $row['blocktitle'] = 'Финальная работа курса «' .
                        format_string($this->course->fullname) . '»';
                    $row['blurb'] = $item['locked']
                        ? ($item['lockreason'] ?: 'Финальная работа откроется после завершения последнего модуля')
                        : 'Сдайте проект, чтобы завершить курс и получить сертификат.';
                } else {
                    $row['blocktitle'] = 'Итоговое тестирование';
                    $row['blurb'] = $item['locked']
                        ? ($item['lockreason'] ?: 'Откроется после завершения всех модулей')
                        : 'Проверьте знания по всей программе курса.';
                }
                return $row;
            }
        }
        return null;
    }

    /**
     * @param string $type
     * @return bool
     */
    private function has_type(string $type): bool {
        foreach ($this->items as $item) {
            if ($item['type'] === $type) {
                return true;
            }
        }
        return false;
    }

    /**
     * @return array|null
     */
    private function current_item(): ?array {
        foreach ($this->items as $item) {
            if ($item['current']) {
                return $item;
            }
        }
        return null;
    }

    /**
     * @return array{prev:?array,next:?array}
     */
    private function prev_next(): array {
        $prev = null;
        $next = null;
        $found = false;
        foreach ($this->items as $item) {
            if ($item['current']) {
                $found = true;
                continue;
            }
            if (!$found) {
                $prev = $item;
            } else {
                $next = $item;
                break;
            }
        }
        return ['prev' => $prev, 'next' => $next];
    }

    /**
     * @return string
     */
    private function continue_url(): string {
        foreach ($this->items as $item) {
            if (!$item['complete'] && !$item['locked'] && $item['url']) {
                return $item['url'];
            }
        }
        foreach ($this->items as $item) {
            if (!$item['locked'] && $item['url']) {
                return $item['url'];
            }
        }
        return (new moodle_url('/course/view.php', ['id' => $this->course->id]))->out(false);
    }

    /**
     * @return array
     */
    private function counts(): array {
        $c = [
            'video_done' => 0, 'video_total' => 0,
            'practice_done' => 0, 'practice_total' => 0,
            'test_done' => 0, 'test_total' => 0,
            'done' => 0, 'total' => 0,
        ];
        foreach ($this->items as $item) {
            $c['total']++;
            if ($item['complete']) {
                $c['done']++;
            }
            if (in_array($item['type'], ['video', 'page', 'summary', 'file', 'link'], true)) {
                $c['video_total']++;
                if ($item['complete']) {
                    $c['video_done']++;
                }
            }
            if (in_array($item['type'], ['practice', 'final'], true)) {
                $c['practice_total']++;
                if ($item['complete']) {
                    $c['practice_done']++;
                }
            }
            if (in_array($item['type'], ['test', 'finaltest'], true)) {
                $c['test_total']++;
                if ($item['complete']) {
                    $c['test_done']++;
                }
            }
        }
        return $c;
    }

    /**
     * Content lessons (video/page) use a manual «Отметить как пройденное» button.
     * Quizzes and assignments complete automatically after a real attempt/submit.
     *
     * @param string $type
     * @return bool
     */
    private function is_manual_lesson_type(string $type): bool {
        return in_array($type, ['video', 'page', 'summary', 'file', 'link'], true);
    }

    /**
     * Keep completion rules matching the player: manual for pages, automatic for quiz/assign.
     *
     * @param int $courseid
     */
    public static function repair_completion_tracking(int $courseid): void {
        global $CFG, $DB;

        if ($courseid <= 0) {
            return;
        }

        $modinfo = get_fast_modinfo($courseid);
        $changed = false;
        $resets = [];
        $manualmods = ['page', 'resource', 'url', 'book', 'lesson', 'folder'];

        foreach ($modinfo->get_cms() as $cm) {
            if ($cm->deletioninprogress) {
                continue;
            }

            if (in_array($cm->modname, $manualmods, true)) {
                if ((int) $cm->completion !== COMPLETION_TRACKING_MANUAL) {
                    $DB->set_field('course_modules', 'completion', COMPLETION_TRACKING_MANUAL, ['id' => $cm->id]);
                    $DB->set_field('course_modules', 'completionview', 0, ['id' => $cm->id]);
                    $changed = true;
                }
                continue;
            }

            if ($cm->modname === 'quiz') {
                if ((int) $cm->completion !== COMPLETION_TRACKING_AUTOMATIC) {
                    $DB->set_field('course_modules', 'completion', COMPLETION_TRACKING_AUTOMATIC, ['id' => $cm->id]);
                    $changed = true;
                }
                $gradeitem = $cm->completiongradeitemnumber ?? null;
                if ($gradeitem === null || $gradeitem === '') {
                    $DB->set_field('course_modules', 'completiongradeitemnumber', 0, ['id' => $cm->id]);
                    $changed = true;
                }
                $minattempts = (int) $DB->get_field('quiz', 'completionminattempts', ['id' => $cm->instance]);
                if ($minattempts < 1) {
                    $DB->set_field('quiz', 'completionminattempts', 1, ['id' => $cm->instance]);
                    $changed = true;
                }
                continue;
            }

            if ($cm->modname === 'assign') {
                $assignchanged = false;
                if ((int) $cm->completion !== COMPLETION_TRACKING_AUTOMATIC) {
                    $DB->set_field('course_modules', 'completion', COMPLETION_TRACKING_AUTOMATIC, ['id' => $cm->id]);
                    $assignchanged = true;
                }
                $submit = (int) $DB->get_field('assign', 'completionsubmit', ['id' => $cm->instance]);
                if ($submit !== 1) {
                    $DB->set_field('assign', 'completionsubmit', 1, ['id' => $cm->instance]);
                    $assignchanged = true;
                }
                $dbman = $DB->get_manager();
                if ($dbman->field_exists('course_modules', 'completionpassgrade')) {
                    $pass = (int) $DB->get_field('course_modules', 'completionpassgrade', ['id' => $cm->id]);
                    if ($pass !== 1) {
                        $DB->set_field('course_modules', 'completionpassgrade', 1, ['id' => $cm->id]);
                        $assignchanged = true;
                    }
                }
                $gradeitem = $cm->completiongradeitemnumber ?? null;
                if ($gradeitem === null || $gradeitem === '') {
                    $DB->set_field('course_modules', 'completiongradeitemnumber', 0, ['id' => $cm->id]);
                    $assignchanged = true;
                }
                $gi = $DB->get_record('grade_items', [
                    'itemtype' => 'mod',
                    'itemmodule' => 'assign',
                    'iteminstance' => $cm->instance,
                    'itemnumber' => 0,
                    'courseid' => $courseid,
                ]);
                if ($gi && (float) $gi->gradepass <= 0) {
                    $DB->set_field('grade_items', 'gradepass', 1, ['id' => $gi->id]);
                    $assignchanged = true;
                }
                $reopen = (string) $DB->get_field('assign', 'attemptreopenmethod', ['id' => $cm->instance]);
                if ($reopen === '' || $reopen === 'none') {
                    $DB->set_field('assign', 'attemptreopenmethod', 'untilpass', ['id' => $cm->instance]);
                    $DB->set_field('assign', 'maxattempts', -1, ['id' => $cm->instance]);
                    $assignchanged = true;
                }
                if ($assignchanged) {
                    $changed = true;
                    $resets[] = (int) $cm->id;
                }
            }
        }
        if ($changed) {
            rebuild_course_cache($courseid, true);
            if ($resets) {
                require_once($CFG->libdir . '/completionlib.php');
                $course = get_course($courseid);
                $completion = new completion_info($course);
                $fresh = get_fast_modinfo($course);
                foreach ($resets as $cmid) {
                    try {
                        $freshcm = $fresh->get_cm($cmid);
                    } catch (\Exception $e) {
                        continue;
                    }
                    if ($completion->is_enabled($freshcm)) {
                        $completion->reset_all_state($freshcm);
                    }
                }
            }
        }
    }

    /**
     * @deprecated since ACO completion split; use repair_completion_tracking().
     * @param int $courseid
     */
    public static function repair_quiz_completion(int $courseid): void {
        self::repair_completion_tracking($courseid);
    }

    /**
     * @return string
     */
    private function cover_url(): string {
        $el = new \core_course_list_element($this->course);
        foreach ($el->get_course_overviewfiles() as $file) {
            $isimage = $file->is_valid_image();
            if ($isimage) {
                $path = '/' . $file->get_contextid() . '/course/overviewfiles' .
                    $file->get_filepath() . $file->get_filename();
                return moodle_url::make_file_url('/pluginfile.php', $path)->out(false);
            }
        }
        return '';
    }
}

/**
 * core_text::strtolower wrapper that does not fatally fail if unavailable.
 *
 * @param string $value
 * @return string
 */
function core_text_lower(string $value): string {
    if (class_exists('core_text')) {
        return \core_text::strtolower($value);
    }
    return mb_strtolower($value, 'UTF-8');
}
