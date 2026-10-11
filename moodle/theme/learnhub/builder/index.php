<?php
/**
 * Course programme builder (list + Skillbox-like module editor).
 *
 * @package theme_learnhub
 */

require(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/lib.php');

$courseid = optional_param('id', 0, PARAM_INT);
$sectionnum = optional_param('section', 0, PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHANUMEXT);

if ($action && data_submitted()) {
    confirm_sesskey();
}

if ($action === 'create_course') {
    theme_learnhub_builder_require_admin(0);
    $fullname = trim(required_param('fullname', PARAM_TEXT));
    $summary = trim(optional_param('summary', '', PARAM_TEXT));
    $course = theme_learnhub_builder_create_course($fullname, $summary);
    redirect(
        new moodle_url('/theme/learnhub/builder/index.php', ['id' => $course->id]),
        'Курс создан. Соберите модули справа.',
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

$course = theme_learnhub_builder_require_admin($courseid);

if ($course && $action) {
    $course = theme_learnhub_builder_ensure_aco($course);
    $modinfo = get_fast_modinfo($course);

    if ($action === 'rename_course') {
        $course->fullname = trim(required_param('fullname', PARAM_TEXT));
        $course->summary = trim(optional_param('summary', '', PARAM_TEXT));
        $course->summaryformat = FORMAT_HTML;
        update_course($course);
        redirect(new moodle_url('/theme/learnhub/builder/index.php', ['id' => $course->id]), 'Название обновлено');
    }

    if ($action === 'add_module') {
        $name = trim(optional_param('name', '', PARAM_TEXT));
        $role = optional_param('role', 'module', PARAM_ALPHA);
        if ($name === '') {
            $name = 'Новый модуль';
        }
        if (!in_array($role, ['module', 'finaltest', 'final'], true)) {
            $role = 'module';
        }
        $created = theme_learnhub_builder_add_module($course, $name, $role);
        redirect(new moodle_url('/theme/learnhub/builder/index.php', [
            'id' => $course->id,
            'section' => $created->section,
        ]));
    }

    if ($action === 'rename_module') {
        $sid = required_param('sectionid', PARAM_INT);
        $name = trim(required_param('name', PARAM_TEXT));
        $role = optional_param('role', 'module', PARAM_ALPHA);
        $section = $modinfo->get_section_info_by_id($sid);
        $idnumber = '';
        if ($role === 'finaltest') {
            $idnumber = 'finaltest';
        } else if ($role === 'final') {
            $idnumber = 'final';
        }
        course_update_section($course, $section, ['name' => $name, 'idnumber' => $idnumber]);
        format_aco_rebuild_sequence($course->id);
        redirect(new moodle_url('/theme/learnhub/builder/index.php', [
            'id' => $course->id,
            'section' => $section->section,
        ]));
    }

    if ($action === 'delete_module') {
        $sid = required_param('sectionid', PARAM_INT);
        $deleted = theme_learnhub_builder_delete_module($course, $sid);
        $url = new moodle_url('/theme/learnhub/builder/index.php', ['id' => $course->id]);
        if ($deleted) {
            redirect($url, 'Модуль удалён', null, \core\output\notification::NOTIFY_SUCCESS);
        }
        redirect($url, 'Не удалось удалить модуль', null, \core\output\notification::NOTIFY_ERROR);
    }

    if ($action === 'move_module') {
        $snum = required_param('sectionnum', PARAM_INT);
        $dir = required_param('dir', PARAM_ALPHA);
        theme_learnhub_builder_move_section($course, $snum, $dir);
        redirect(new moodle_url('/theme/learnhub/builder/index.php', [
            'id' => $course->id,
            'section' => $dir === 'up' ? max(1, $snum - 1) : $snum + 1,
        ]));
    }

    if ($action === 'delete_item') {
        $cmid = required_param('cmid', PARAM_INT);
        $cm = $modinfo->get_cm($cmid);
        $keep = (int) $cm->sectionnum;
        course_delete_module($cmid);
        format_aco_rebuild_sequence($course->id);
        redirect(new moodle_url('/theme/learnhub/builder/index.php', [
            'id' => $course->id,
            'section' => $keep,
        ]));
    }

    if ($action === 'move_item') {
        $cmid = required_param('cmid', PARAM_INT);
        $dir = required_param('dir', PARAM_ALPHA);
        $cm = $modinfo->get_cm($cmid);
        theme_learnhub_builder_move_cm($course, $cmid, $dir);
        redirect(new moodle_url('/theme/learnhub/builder/index.php', [
            'id' => $course->id,
            'section' => (int) $cm->sectionnum,
        ]));
    }
}

if ($course) {
    $course = theme_learnhub_builder_ensure_aco($course);
    $modules = theme_learnhub_builder_modules($course);
    if (!$sectionnum && $modules) {
        $sectionnum = (int) $modules[0]['section']->section;
    }
    $current = null;
    foreach ($modules as $mod) {
        if ((int) $mod['section']->section === (int) $sectionnum) {
            $current = $mod;
            break;
        }
    }
    if (!$current && $modules) {
        $current = $modules[0];
        $sectionnum = (int) $current['section']->section;
    }

    $url = new moodle_url('/theme/learnhub/builder/index.php', ['id' => $course->id, 'section' => $sectionnum]);
    theme_learnhub_builder_setup_page($url, context_course::instance($course->id), 'Программа: ' . $course->fullname, $course);

    echo $OUTPUT->header();
    echo theme_learnhub_builder_header_html($course);
    echo html_writer::start_div('lh-build');

    echo html_writer::start_tag('aside', ['class' => 'lh-build__mods']);
    echo html_writer::tag('h2', 'Модули');

    echo html_writer::start_tag('form', [
        'method' => 'post',
        'action' => (new moodle_url('/theme/learnhub/builder/index.php'))->out(false),
        'class' => 'lh-build__addmod',
    ]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $course->id]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'add_module']);
    echo html_writer::empty_tag('input', [
        'type' => 'text',
        'name' => 'name',
        'placeholder' => 'Название модуля',
        'required' => 'required',
    ]);
    echo html_writer::select(
        ['module' => 'Обычный', 'finaltest' => 'Итоговая аттестация', 'final' => 'Финальная работа'],
        'role',
        'module',
        false
    );
    echo html_writer::tag('button', 'Добавить модуль', ['type' => 'submit', 'class' => 'lh-build__btn']);
    echo html_writer::end_tag('form');

    echo html_writer::start_tag('ul', ['class' => 'lh-build__modlist']);
    $lastnum = count($modules);
    foreach ($modules as $i => $mod) {
        $s = (int) $mod['section']->section;
        $active = $s === (int) $sectionnum;
        $li = $active ? 'lh-build__mod is-active' : 'lh-build__mod';
        echo html_writer::start_tag('li', ['class' => $li]);
        echo html_writer::link(
            new moodle_url('/theme/learnhub/builder/index.php', ['id' => $course->id, 'section' => $s]),
            html_writer::span($mod['num'] . '.', 'lh-build__n') . s($mod['name']),
            ['class' => 'lh-build__modlink']
        );
        echo html_writer::start_div('lh-build__modtools');
        if ($i > 0) {
            echo theme_learnhub_builder_post_btn($course->id, 'move_module', '↑', [
                'sectionnum' => $s,
                'dir' => 'up',
            ]);
        }
        if ($i < $lastnum - 1) {
            echo theme_learnhub_builder_post_btn($course->id, 'move_module', '↓', [
                'sectionnum' => $s,
                'dir' => 'down',
            ]);
        }
        echo theme_learnhub_builder_post_btn($course->id, 'delete_module', '×', [
            'sectionid' => $mod['section']->id,
        ], 'lh-build__moddel', 'Удалить модуль «' . $mod['name'] . '» вместе с уроками?');
        echo html_writer::end_div();
        echo html_writer::end_tag('li');
    }
    echo html_writer::end_tag('ul');
    echo html_writer::end_tag('aside');

    echo html_writer::start_div('lh-build__main');
    if (!$current) {
        echo html_writer::div('Добавьте первый модуль слева — внутри него появятся видео, тесты и домашки.', 'lh-build__empty');
    } else {
        $sec = $current['section'];
        echo html_writer::start_div('lh-build__head');
        echo html_writer::start_tag('form', [
            'method' => 'post',
            'class' => 'lh-build__rename',
        ]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $course->id]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'rename_module']);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sectionid', 'value' => $sec->id]);
        echo html_writer::empty_tag('input', [
            'type' => 'text',
            'name' => 'name',
            'value' => $current['name'],
            'class' => 'lh-build__hinput',
        ]);
        echo html_writer::select(
            ['module' => 'Обычный модуль', 'finaltest' => 'Итоговая аттестация', 'final' => 'Финальная работа'],
            'role',
            $current['role'],
            false
        );
        echo html_writer::tag('button', 'Сохранить', ['type' => 'submit', 'class' => 'lh-build__btn lh-build__btn--ghost']);
        echo html_writer::end_tag('form');
        echo theme_learnhub_builder_post_btn($course->id, 'delete_module', 'Удалить модуль', [
            'sectionid' => $sec->id,
        ], 'lh-build__btn lh-build__btn--danger', 'Удалить модуль вместе со всеми уроками?');
        echo html_writer::end_div();

    echo html_writer::tag('p', 'Сначала урок с видео, затем проверка — тест или домашка учителю.', ['class' => 'lh-build__hint']);

        $addbase = new moodle_url('/theme/learnhub/builder/item.php', [
            'id' => $course->id,
            'section' => $sec->section,
        ]);
        echo html_writer::start_div('lh-build__addrow');
        echo html_writer::link(new moodle_url($addbase, ['type' => 'video']), '+ Видео Kinescope', ['class' => 'lh-build__chip']);
        echo html_writer::link(new moodle_url($addbase, ['type' => 'page']), '+ Текст', ['class' => 'lh-build__chip']);
        echo html_writer::link(new moodle_url($addbase, ['type' => 'summary']), '+ Самопроверка', ['class' => 'lh-build__chip']);
        echo html_writer::link(new moodle_url($addbase, ['type' => 'quiz']), '+ Тест', ['class' => 'lh-build__chip']);
        echo html_writer::link(new moodle_url($addbase, ['type' => 'assign']), '+ Домашка', ['class' => 'lh-build__chip']);
        echo html_writer::link(new moodle_url($addbase, ['type' => 'meeting']), '+ Онлайн-встреча', ['class' => 'lh-build__chip']);
        echo html_writer::end_div();

        if (!$current['items']) {
            echo html_writer::div('В модуле пока пусто. Добавьте видео с кодом Kinescope или задание.', 'lh-build__empty');
        } else {
            echo html_writer::start_tag('ol', ['class' => 'lh-build__lessons']);
            $count = count($current['items']);
            foreach ($current['items'] as $idx => $row) {
                $cm = $row['cm'];
                $kind = $row['kind'];
                echo html_writer::start_tag('li', ['class' => 'lh-build__lesson']);
                echo html_writer::span($row['label'], 'lh-build__badge lh-build__badge--' . $kind);
                echo html_writer::tag('span', format_string($cm->name), ['class' => 'lh-build__lname']);
                echo html_writer::start_div('lh-build__ltools');
                if ($idx > 0) {
                    echo theme_learnhub_builder_post_btn($course->id, 'move_item', '↑', [
                        'cmid' => $cm->id,
                        'dir' => 'up',
                    ]);
                }
                if ($idx < $count - 1) {
                    echo theme_learnhub_builder_post_btn($course->id, 'move_item', '↓', [
                        'cmid' => $cm->id,
                        'dir' => 'down',
                    ]);
                }
                $edittype = $kind;
                if ($kind === 'finaltest') {
                    $edittype = 'quiz';
                } else if ($kind === 'final') {
                    $edittype = 'assign';
                } else if ($kind === 'practice') {
                    $edittype = 'assign';
                } else if ($kind === 'test') {
                    $edittype = 'quiz';
                } else if ($kind === 'meeting') {
                    $edittype = 'meeting';
                }
                echo html_writer::link(
                    new moodle_url('/theme/learnhub/builder/item.php', [
                        'id' => $course->id,
                        'section' => $sec->section,
                        'type' => $edittype,
                        'cmid' => $cm->id,
                    ]),
                    'Изменить'
                );
                echo theme_learnhub_builder_post_btn($course->id, 'delete_item', 'Удалить', [
                    'cmid' => $cm->id,
                ], 'lh-build__linkdanger', 'Удалить этот урок?');
                echo html_writer::end_div();
                echo html_writer::end_tag('li');
            }
            echo html_writer::end_tag('ol');
        }
    }
    echo html_writer::end_div();
    echo html_writer::end_div();
    echo $OUTPUT->footer();
    exit;
}

// Catalogue: all courses + create.
theme_learnhub_builder_setup_page(
    new moodle_url('/theme/learnhub/builder/index.php'),
    context_system::instance(),
    'Конструктор курсов'
);

$courses = get_courses('all', 'c.fullname ASC', 'c.id, c.fullname, c.shortname, c.visible, c.format');
echo $OUTPUT->header();
echo theme_learnhub_builder_header_html(null);
echo html_writer::start_div('lh-build lh-build--list');
echo html_writer::tag('h1', 'Конструктор курсов');
echo html_writer::tag('p', 'Соберите программу: модули, видео Kinescope, тесты и домашки. Публичная витрина живёт в панели WordPress.', ['class' => 'lh-build__hint']);

echo html_writer::start_tag('form', [
    'method' => 'post',
    'class' => 'lh-build__create',
]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'create_course']);
echo html_writer::tag('h2', 'Новый курс');
echo html_writer::empty_tag('input', [
    'type' => 'text',
    'name' => 'fullname',
    'placeholder' => 'Название, например Frontend-разработчик',
    'required' => 'required',
]);
echo html_writer::tag('textarea', '', [
    'name' => 'summary',
    'rows' => 3,
    'placeholder' => 'Краткое описание для студента (необязательно)',
]);
echo html_writer::tag('button', 'Создать курс', ['type' => 'submit', 'class' => 'lh-build__btn']);
echo html_writer::end_tag('form');

echo html_writer::start_div('lh-build__grid');
foreach ($courses as $c) {
    if ((int) $c->id === (int) SITEID || (string) $c->shortname === 'lh-staff') {
        continue;
    }
    $stats = theme_learnhub_builder_course_stats($c);
    $vis = $stats['visible'] ? 'опубликован' : 'скрыт';
    $meta = $stats['modules'] . ' мод. · ' . $stats['lessons'] . ' уроков · ' . $stats['students'] . ' студ. · ' . $vis;
    echo html_writer::start_div('lh-build__card');
    echo html_writer::tag('h3', format_string($c->fullname));
    echo html_writer::tag('p', $meta, ['class' => 'lh-build__meta']);
    echo html_writer::start_div('lh-build__card-actions');
    echo html_writer::link(
        new moodle_url('/theme/learnhub/builder/index.php', ['id' => $c->id]),
        'Программа',
        ['class' => 'lh-build__btn']
    );
    echo html_writer::link(
        new moodle_url('/course/view.php', ['id' => $c->id, 'lhpreview' => 1]),
        'Как студент',
        ['class' => 'lh-build__btn lh-build__btn--ghost', 'target' => '_blank']
    );
    echo html_writer::end_div();
    echo html_writer::end_div();
}
echo html_writer::end_div();
echo html_writer::end_div();
echo $OUTPUT->footer();

