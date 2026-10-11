<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace theme_learnhub\output;

defined('MOODLE_INTERNAL') || die();

/**
 * Core renderer for LearnHub theme.
 *
 * Extends Boost renderer so Boost-specific layout helpers
 * (e.g. firstview_fakeblocks()) remain available.
 *
 * @package   theme_learnhub
 */
class core_renderer extends \theme_boost\output\core_renderer {

    /**
     * Adds lh-admin / lh-student body class so the SCSS can strip Moodle
     * chrome for learners while admins and editors keep the full UI.
     *
     * @param array $additionalclasses
     * @return string
     */
    public function body_attributes($additionalclasses = []) {
        global $PAGE;

        $kind = \theme_learnhub\roles::kind();
        $path = (string) $PAGE->url->out(false);
        if (strpos($path, '/theme/learnhub/builder/') !== false || $PAGE->pagelayout === 'builder') {
            $additionalclasses[] = 'lh-builder';
        }
        if (\theme_learnhub\roles::isolated_chrome()) {
            $additionalclasses[] = 'lh-cabinet';
        }
        if ($kind === 'admin') {
            $additionalclasses[] = 'lh-admin';
            if (!empty(optional_param('lhpreview', 0, PARAM_BOOL))) {
                $additionalclasses[] = 'lh-player';
            }
        } else {
            $additionalclasses[] = 'lh-student';
            if ($kind === 'teacher') {
                $additionalclasses[] = 'lh-teacher';
            }
            if (in_array($PAGE->pagelayout, ['course', 'incourse'], true)) {
                $additionalclasses[] = 'lh-player';
            }
        }

        return parent::body_attributes($additionalclasses);
    }

    /**
     * Links to the public legal pages on www.acoedu.ru.
     *
     * @return string
     */
    public function learnhub_legal_nav() {
        $items = [
            'Документы' => 'https://www.acoedu.ru/legal/',
            'Оферта' => 'https://www.acoedu.ru/oferta/',
            'Конфиденциальность' => 'https://www.acoedu.ru/privacy/',
            'Персональные данные' => 'https://www.acoedu.ru/personal-data/',
            'Возврат' => 'https://www.acoedu.ru/refund/',
        ];
        $links = [];
        foreach ($items as $label => $url) {
            $links[] = \html_writer::link($url, $label);
        }
        return \html_writer::div(implode(' · ', $links), 'lh-moodle-legal');
    }

    /**
     * @return string
     */
    public function standard_footer_html() {
        return $this->learnhub_legal_nav() . parent::standard_footer_html();
    }

    /**
     * Used by the navbar Mustache as a section flag.
     *
     * @return bool
     */
    public function learnhub_student_chrome() {
        return $this->learnhub_is_isolated_student();
    }

    /**
     * ACO logo URL (theme pix, not a remote WP asset).
     *
     * @return string
     */
    public function learnhub_logo_url() {
        return $this->image_url('aco-logo', 'theme_learnhub')->out(false);
    }

    /**
     * Compact ACO login: no guest access, branded heading.
     *
     * @param \core_auth\output\login $form
     * @return string
     */
    public function render_login(\core_auth\output\login $form) {
        global $SITE;

        $context = $form->export_for_template($this);
        $context->canloginasguest = false;
        $context->errorformatted = $this->error_text($context->error ?? '');
        $context->logourl = $this->learnhub_logo_url();
        $context->sitename = format_string($SITE->fullname, true, [
            'context' => \context_course::instance(SITEID),
            'escape' => false,
        ]);
        return $this->render_from_template('core/loginform', $context);
    }

    /**
     * Logo click target: WordPress home when Edwiser has it, otherwise the cabinet.
     *
     * @return string
     */
    public function learnhub_logo_home_url() {
        global $CFG;

        $kind = \theme_learnhub\roles::kind();
        if ($kind === 'admin') {
            return $CFG->wwwroot . '/theme/learnhub/builder/index.php';
        }
        if ($kind === 'teacher') {
            return $CFG->wwwroot . '/theme/learnhub/teacher/index.php';
        }
        $wp = get_config('auth_edwiserbridge', 'wpsiteurl');
        if (!empty($wp)) {
            return rtrim($wp, '/') . '/';
        }
        return $CFG->wwwroot . '/my/';
    }

    /**
     * Injects the LearnHub persistent sidebar (and, on the dashboard, the
     * custom course grid loader script) right after <body>. This is a
     * supported Moodle extension point, so it never touches the drawer
     * markup/JS that theme_boost/drawers relies on.
     *
     * @return string HTML fragment appended to the standard output.
     */
    public function standard_top_of_body_html() {
        global $PAGE;

        $html = parent::standard_top_of_body_html();

        if (!$this->learnhub_is_isolated_student()) {
            return $html;
        }

        $html .= $this->learnhub_sidebar_html();

        if (\theme_learnhub\roles::kind() === 'student'
            && in_array($PAGE->pagelayout, ['mydashboard', 'mycourses'], true)) {
            $html .= $this->learnhub_dashboard_script();
        }

        return $html;
    }

    /**
     * Shared footer on cabinet pages (panel, course, curator).
     *
     * @return string
     */
    public function standard_end_of_body_html() {
        $html = '';
        $layout = $this->page->pagelayout ?? '';
        $onplayer = in_array($layout, ['course', 'incourse'], true);
        if ($this->learnhub_is_isolated_student() && !$onplayer) {
            $html .= '<footer class="lh-app-footer d-print-none">АЦО — Академия цифрового образования</footer>';
        }
        return $html . parent::standard_end_of_body_html();
    }

    /**
     * True when the current user should see the isolated LearnHub UI.
     *
     * @return bool
     */
    private function learnhub_is_isolated_student() {
        return \theme_learnhub\roles::isolated_chrome();
    }

    /**
     * Builds the left-hand navigation matching the cabinet mockup.
     *
     * @return string HTML.
     */
    private function learnhub_sidebar_html() {
        $links = '';
        foreach ($this->learnhub_nav_items() as $item) {
            $class = 'lh-side__item' . ($item['active'] ? ' lh-side__item--active' : '');
            $attrs = ['class' => $class];
            if (!empty($item['disabled'])) {
                $attrs['aria-disabled'] = 'true';
                $attrs['class'] .= ' lh-side__item--disabled';
            }
            $links .= \html_writer::link($item['url'], $item['label'], $attrs);
        }

        return \html_writer::tag('aside', $links, [
            'class' => 'lh-sidebar d-print-none',
            'data-region' => 'lh-sidebar',
            'aria-label' => 'Навигация по кабинету',
        ]);
    }

    /**
     * Nav items with destinations derived from the student's enrolment.
     *
     * @return array
     */
    private function learnhub_nav_items() {
        global $PAGE, $CFG, $USER;

        $kind = \theme_learnhub\roles::kind();
        if ($kind === 'admin') {
            return $this->learnhub_admin_nav_items();
        }
        if ($kind === 'teacher') {
            return $this->learnhub_teacher_nav_items();
        }

        $courseid = $this->learnhub_active_course_id();
        $targets = $this->learnhub_course_targets($courseid);

        $path = (string) parse_url($PAGE->url->out(false), PHP_URL_PATH);
        $pagetype = (string) $PAGE->pagetype;

        $onpanel = in_array($PAGE->pagelayout, ['mydashboard', 'mycourses'], true)
            && strpos($path, '/theme/learnhub/curator.php') === false;
        $oncurator = strpos($path, '/theme/learnhub/curator.php') !== false;
        $onquiz = strpos($pagetype, 'mod-quiz') === 0;
        $onlesson = (bool) preg_match('/^mod-(page|lesson|book|resource|url|h5pactivity)/', $pagetype);
        $oncourse = (strpos($pagetype, 'course-view') === 0) || ($pagetype === 'course-section') || $oncurator;

        if ($onquiz) {
            $oncourse = false;
            $onlesson = false;
        }
        if ($onlesson) {
            $oncourse = false;
        }
        if ($oncurator) {
            $onpanel = false;
            $onquiz = false;
            $onlesson = false;
            $oncourse = true;
        }

        $onmeetings = strpos($path, '/theme/learnhub/meetings.php') !== false;
        if (!$onmeetings && !empty($PAGE->cm) && $PAGE->cm->modname === 'bigbluebuttonbn') {
            $live = (string) $PAGE->cm->idnumber === 'live-meeting' || (int) $PAGE->cm->sectionnum === 0;
            if ($live) {
                $onmeetings = true;
            } else {
                $oncourse = true;
            }
        }
        if ($onmeetings) {
            $onpanel = false;
            $onquiz = false;
            $onlesson = false;
            $oncourse = false;
        }

        $fallback = $CFG->wwwroot . '/my/';

        $meetcount = 0;
        $enrolled = enrol_get_all_users_courses($USER->id, true, 'id, fullname, shortname, visible');
        if ($enrolled) {
            $meetcount = \theme_learnhub\meetings::upcoming_count($enrolled, (int) $USER->id, true);
        }
        $meetlabel = $meetcount ? 'Онлайн-встречи (' . $meetcount . ')' : 'Онлайн-встречи';

        return [
            ['label' => 'Панель', 'url' => $CFG->wwwroot . '/my/', 'active' => $onpanel, 'disabled' => false],
            [
                'label' => 'Курс',
                'url' => $targets['course'] ?: $fallback,
                'active' => $oncourse || $onquiz || $onlesson,
                'disabled' => empty($targets['course']),
            ],
            [
                'label' => $meetlabel,
                'url' => $CFG->wwwroot . '/theme/learnhub/meetings.php',
                'active' => $onmeetings,
                'disabled' => false,
            ],
        ];
    }

    /**
     * Course id for contextual nav: current course, else first enrolment.
     *
     * @return int
     */
    private function learnhub_active_course_id() {
        global $PAGE, $USER;

        if (!empty($PAGE->course) && (int) $PAGE->course->id !== (int) SITEID) {
            return (int) $PAGE->course->id;
        }

        $courses = enrol_get_all_users_courses($USER->id, true, 'id, visible');
        foreach ($courses as $course) {
            if ((int) $course->id === (int) SITEID || empty($course->visible)) {
                continue;
            }
            return (int) $course->id;
        }
        return 0;
    }

    /**
     * First quiz / lesson URLs for a course.
     *
     * @param int $courseid
     * @return array
     */
    private function learnhub_course_targets($courseid) {
        global $CFG, $USER;

        $out = ['course' => '', 'quiz' => '', 'lesson' => '', 'curator' => ''];
        if (!$courseid) {
            return $out;
        }

        $out['course'] = $CFG->wwwroot . '/course/view.php?id=' . $courseid;

        try {
            $modinfo = get_fast_modinfo($courseid, $USER->id);
            foreach ($modinfo->get_cms() as $cm) {
                if (!$cm->uservisible || !$cm->url) {
                    continue;
                }
                if ($cm->modname === 'quiz' && $out['quiz'] === '') {
                    $out['quiz'] = $cm->url->out(false);
                }
                if (in_array($cm->modname, ['page', 'lesson', 'book', 'resource'], true) && $out['lesson'] === '') {
                    $out['lesson'] = $cm->url->out(false);
                }
            }
        } catch (\Exception $e) {
            // Leave quiz/lesson empty; course URL still works.
        }

        $out['curator'] = $CFG->wwwroot . '/theme/learnhub/curator.php?id=' . $courseid;
        return $out;
    }

    /**
     * Teacher cabinet navigation.
     *
     * @return array
     */
    private function learnhub_teacher_nav_items() {
        global $PAGE, $CFG, $USER;

        $path = (string) parse_url($PAGE->url->out(false), PHP_URL_PATH);
        $courseid = optional_param('id', 0, PARAM_INT);
        if (!$courseid && !empty($PAGE->course) && (int) $PAGE->course->id !== (int) SITEID) {
            $courseid = (int) $PAGE->course->id;
        }
        if (!$courseid) {
            $owned = \theme_learnhub\roles::teacher_courses();
            if ($owned) {
                $courseid = (int) reset($owned)->id;
            }
        }

        $base = $CFG->wwwroot . '/theme/learnhub/teacher';
        $onaccount = strpos($path, '/theme/learnhub/account.php') !== false;
        $onstudents = strpos($path, '/theme/learnhub/teacher/students.php') !== false;
        $onhw = strpos($path, '/theme/learnhub/teacher/homework.php') !== false;
        $oncurator = strpos($path, '/theme/learnhub/teacher/curator.php') !== false;
        $onmeetings = strpos($path, '/theme/learnhub/teacher/meeting') !== false;
        if (!$onmeetings && !empty($PAGE->cm) && $PAGE->cm->modname === 'bigbluebuttonbn') {
            $onmeetings = true;
        }
        $onpanel = strpos($path, '/theme/learnhub/teacher/index.php') !== false
            || (!$onstudents && !$onhw && !$onaccount && !$oncurator && !$onmeetings);

        $studentsurl = $courseid ? $base . '/students.php?id=' . $courseid : $base . '/index.php';
        $hwurl = $courseid ? $base . '/homework.php?id=' . $courseid : $base . '/index.php';
        $curatorurl = $courseid ? $base . '/curator.php?id=' . $courseid : $base . '/index.php';
        $unread = $courseid ? \theme_learnhub\curator::unread_teacher_count($courseid) : 0;
        $curatorlabel = $unread ? 'Куратор (' . $unread . ')' : 'Куратор';
        $meetcount = \theme_learnhub\meetings::upcoming_count(\theme_learnhub\roles::teacher_courses(), (int) $USER->id, false);
        $meetcount += \theme_learnhub\meetings::staff_upcoming_count((int) $USER->id, true);
        $meetlabel = $meetcount ? 'Встречи (' . $meetcount . ')' : 'Встречи';

        return [
            ['label' => 'Панель', 'url' => $base . '/index.php', 'active' => $onpanel, 'disabled' => false],
            ['label' => 'Студенты', 'url' => $studentsurl, 'active' => $onstudents, 'disabled' => !$courseid],
            ['label' => 'Домашки', 'url' => $hwurl, 'active' => $onhw, 'disabled' => !$courseid],
            ['label' => $curatorlabel, 'url' => $curatorurl, 'active' => $oncurator, 'disabled' => !$courseid],
            ['label' => $meetlabel, 'url' => $base . '/meetings.php', 'active' => $onmeetings, 'disabled' => false],
        ];
    }

    /**
     * Site-admin cabinet navigation (not /admin/*).
     *
     * @return array
     */
    private function learnhub_admin_nav_items() {
        global $PAGE, $CFG;

        $path = (string) parse_url($PAGE->url->out(false), PHP_URL_PATH);

        $builder = $CFG->wwwroot . '/theme/learnhub/builder/index.php';
        $teachers = $CFG->wwwroot . '/theme/learnhub/builder/teachers.php';
        $onbuilder = strpos($path, '/theme/learnhub/builder/') !== false;
        $onteachers = strpos($path, '/theme/learnhub/builder/teachers.php') !== false;
        $onpreview = !empty(optional_param('lhpreview', 0, PARAM_BOOL));

        $onmeetings = strpos($path, '/theme/learnhub/staff/meeting') !== false;
        if (!$onmeetings && !empty($PAGE->cm) && $PAGE->cm->modname === 'bigbluebuttonbn') {
            $onmeetings = (string) $PAGE->cm->idnumber === \theme_learnhub\meetings::STAFF;
        }

        return [
            ['label' => 'Конструктор', 'url' => $builder, 'active' => $onbuilder && !$onteachers && !$onpreview, 'disabled' => false],
            ['label' => 'Преподаватели', 'url' => $teachers, 'active' => $onteachers || $onmeetings, 'disabled' => false],
            [
                'label' => 'Moodle',
                'url' => $CFG->wwwroot . '/admin/search.php',
                'active' => false,
                'disabled' => false,
            ],
        ];
    }

    /**
     * Loader script for the dashboard course grid.
     *
     * @return string <script> tag.
     */
    private function learnhub_dashboard_script() {
        global $CFG;

        $endpoint = $CFG->wwwroot . '/theme/learnhub/ajax/courses.php';

        static $ver = null;
        if ($ver === null) {
            $plugin = new \stdClass();
            include($CFG->dirroot . '/theme/learnhub/version.php');
            $ver = isset($plugin->version) ? $plugin->version : time();
        }

        return '<script>window.LH_COURSES_ENDPOINT = ' . json_encode($endpoint) . ';</script>' .
            '<script src="' . $CFG->wwwroot . '/theme/learnhub/javascript/dashboard.js?v=' . $ver . '" defer></script>';
    }
}
