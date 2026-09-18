<?php
/**
 * Add / edit a lesson: Kinescope video, text, quiz or homework.
 *
 * @package theme_learnhub
 */

require(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/lib.php');

$courseid = required_param('id', PARAM_INT);
$sectionnum = required_param('section', PARAM_INT);
$type = required_param('type', PARAM_ALPHA);
$cmid = optional_param('cmid', 0, PARAM_INT);

$course = theme_learnhub_builder_require_admin($courseid);
$course = theme_learnhub_builder_ensure_aco($course);
$modinfo = get_fast_modinfo($course);
$section = $modinfo->get_section_info($sectionnum);

$allowed = ['video', 'page', 'summary', 'quiz', 'assign'];
if (!in_array($type, $allowed, true)) {
    throw new moodle_exception('invalidparameter');
}

$cm = null;
if ($cmid) {
    $cm = $modinfo->get_cm($cmid);
    if ((int) $cm->sectionnum !== (int) $sectionnum) {
        throw new moodle_exception('invalidcoursemodule');
    }
}

$titles = [
    'video' => 'Видеоурок Kinescope',
    'page' => 'Текстовый урок',
    'summary' => 'Самопроверка',
    'quiz' => 'Тест',
    'assign' => 'Домашка учителю',
];

$back = new moodle_url('/theme/learnhub/builder/index.php', ['id' => $course->id, 'section' => $sectionnum]);
$url = new moodle_url('/theme/learnhub/builder/item.php', [
    'id' => $course->id,
    'section' => $sectionnum,
    'type' => $type,
    'cmid' => $cmid,
]);
theme_learnhub_builder_setup_page($url, context_course::instance($course->id), $titles[$type], $course);

$error = '';

if (data_submitted() && confirm_sesskey()) {
    $name = trim(required_param('name', PARAM_TEXT));
    if ($name === '') {
        $error = 'Укажите название.';
    } else {
        try {
            if ($type === 'video') {
                $embed = required_param('kinescope', PARAM_RAW);
                $notes = optional_param('notes', '', PARAM_TEXT);
                $mins = max(0, optional_param('minutes', 0, PARAM_INT));
                $html = theme_learnhub_builder_kinescope_html($embed);
                if ($html === '') {
                    $error = 'Нужен код iframe или ссылка kinescope.io';
                } else {
                    $html .= theme_learnhub_builder_notes_html($notes);
                    $idnumber = $mins ? ('video:' . $mins) : 'video';
                    if ($cm) {
                        theme_learnhub_builder_update_page($course, $cm, $name, $html, $idnumber);
                    } else {
                        theme_learnhub_builder_add_page($course, $sectionnum, $name, $html, $idnumber);
                    }
                }
            } else if ($type === 'page' || $type === 'summary') {
                $body = optional_param('body', '', PARAM_TEXT);
                $idnumber = $type === 'summary' ? 'summary' : 'page';
                $html = theme_learnhub_builder_notes_html($body);
                if ($html === '') {
                    $html = '<p></p>';
                }
                if ($cm) {
                    theme_learnhub_builder_update_page($course, $cm, $name, $html, $idnumber);
                } else {
                    theme_learnhub_builder_add_page($course, $sectionnum, $name, $html, $idnumber);
                }
            } else if ($type === 'assign') {
                $intro = trim(optional_param('intro', '', PARAM_TEXT));
                $introhtml = theme_learnhub_builder_notes_html($intro);
                $format = optional_param('submitformat', 'both', PARAM_ALPHA);
                if (!in_array($format, ['file', 'text', 'both'], true)) {
                    $format = 'both';
                }
                $maxfiles = optional_param('maxfiles', 3, PARAM_INT);
                $filetypes = optional_param('filetypes', '', PARAM_TEXT);
                $isfinal = optional_param('isfinal', 0, PARAM_BOOL);
                $idnumber = $isfinal ? 'final' : 'practice';
                if ($cm) {
                    theme_learnhub_builder_update_assign($course, $cm, $name, $introhtml, $idnumber, $format, $maxfiles, $filetypes);
                } else {
                    theme_learnhub_builder_add_assign($course, $sectionnum, $name, $introhtml, $idnumber, $format, $maxfiles, $filetypes);
                }
            } else if ($type === 'quiz') {
                $intro = trim(optional_param('intro', '', PARAM_TEXT));
                $introhtml = $intro !== '' ? '<p>' . s($intro) . '</p>' : '';
                $isfinal = optional_param('isfinal', 0, PARAM_BOOL);
                $idnumber = $isfinal ? 'finaltest' : 'test';
                $questions = theme_learnhub_builder_posted_questions();
                if ($cm) {
                    global $DB;
                    $DB->set_field('quiz', 'name', $name, ['id' => $cm->instance]);
                    $DB->set_field('quiz', 'intro', $introhtml, ['id' => $cm->instance]);
                    set_coursemodule_name($cm->id, $name);
                    set_coursemodule_idnumber($cm->id, $idnumber);
                    rebuild_course_cache($course->id, true);
                    if ($questions) {
                        theme_learnhub_builder_add_quiz_questions($cm, $questions);
                    }
                } else {
                    $created = theme_learnhub_builder_add_quiz($course, $sectionnum, $name, $introhtml, $idnumber);
                    if ($questions) {
                        theme_learnhub_builder_add_quiz_questions($created, $questions);
                    }
                }
            }
            if ($error === '') {
                redirect($back, 'Сохранено', null, \core\output\notification::NOTIFY_SUCCESS);
            }
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

$prefill = [
    'name' => '',
    'kinescope' => '',
    'notes' => '',
    'minutes' => '',
    'body' => '',
    'intro' => '',
    'submitformat' => 'both',
    'maxfiles' => 3,
    'filetypes' => '',
    'isfinal' => 0,
    'slots' => [],
];

if ($cm) {
    $prefill['name'] = $cm->name;
    if (in_array($cm->modname, ['page'], true)) {
        global $DB;
        $page = $DB->get_record('page', ['id' => $cm->instance]);
        if ($type === 'video') {
            [$src, $notes] = theme_learnhub_builder_extract_kinescope($page->content ?? '');
            $prefill['kinescope'] = $src;
            $prefill['notes'] = $notes;
            if (preg_match('/video:(\d+)/', (string) $cm->idnumber, $m)) {
                $prefill['minutes'] = $m[1];
            }
        } else {
            $prefill['body'] = html_to_text($page->content ?? '', 0, false);
        }
    } else if ($cm->modname === 'assign') {
        $st = theme_learnhub_builder_assign_state($cm);
        $prefill['name'] = $st['name'];
        $prefill['intro'] = $st['intro'];
        $prefill['submitformat'] = $st['format'];
        $prefill['maxfiles'] = $st['maxfiles'] ?: 3;
        $prefill['filetypes'] = $st['filetypes'];
        $prefill['isfinal'] = in_array(strtolower((string) $cm->idnumber), ['final', 'project'], true) ? 1 : 0;
    } else if ($cm->modname === 'quiz') {
        global $DB;
        $quiz = $DB->get_record('quiz', ['id' => $cm->instance]);
        $prefill['name'] = $quiz->name;
        $prefill['intro'] = html_to_text($quiz->intro, 0, false);
        $prefill['isfinal'] = in_array(strtolower((string) $cm->idnumber), ['finaltest', 'exam'], true) ? 1 : 0;
        $prefill['slots'] = theme_learnhub_builder_quiz_slots($quiz->id, $cm->id);
    }
}

echo $OUTPUT->header();
echo theme_learnhub_builder_header_html($course);
echo html_writer::start_div('lh-build lh-build--form');
echo html_writer::link($back, '← К программе', ['class' => 'lh-build__back']);
echo html_writer::tag('h1', ($cm ? 'Изменить: ' : 'Добавить: ') . $titles[$type]);

if ($error) {
    echo html_writer::div(s($error), 'lh-account-page__error');
}

echo html_writer::start_tag('form', ['method' => 'post', 'class' => 'lh-build__form', 'id' => 'lh-item-form']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $course->id]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'section', 'value' => $sectionnum]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'type', 'value' => $type]);
if ($cmid) {
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'cmid', 'value' => $cmid]);
}

echo html_writer::start_tag('label');
echo html_writer::tag('span', 'Название');
echo html_writer::empty_tag('input', [
    'type' => 'text',
    'name' => 'name',
    'value' => $prefill['name'],
    'required' => 'required',
    'placeholder' => $type === 'video' ? 'Например: Как устроена вёрстка' : '',
]);
echo html_writer::end_tag('label');

if ($type === 'video') {
    echo html_writer::start_tag('label');
    echo html_writer::tag('span', 'Код Kinescope или ссылка на ролик');
    echo html_writer::tag('textarea', s($prefill['kinescope']), [
        'name' => 'kinescope',
        'rows' => 5,
        'required' => 'required',
        'placeholder' => '<iframe src="https://kinescope.io/embed/…"></iframe>',
    ]);
    echo html_writer::end_tag('label');
    echo html_writer::tag('p', 'В Kinescope: «Поделиться → Код для вставки». В Allowed Domains укажите домен Moodle (' . s(parse_url($CFG->wwwroot, PHP_URL_HOST)) . '). API не нужен — достаточно iframe.', ['class' => 'lh-build__hint']);
    echo html_writer::start_tag('label');
    echo html_writer::tag('span', 'Длительность, минут (для студента)');
    echo html_writer::empty_tag('input', [
        'type' => 'number',
        'name' => 'minutes',
        'min' => 0,
        'value' => $prefill['minutes'],
        'placeholder' => '12',
    ]);
    echo html_writer::end_tag('label');
    echo html_writer::start_tag('label');
    echo html_writer::tag('span', 'Текст под видео (необязательно)');
    echo html_writer::tag('textarea', s($prefill['notes']), ['name' => 'notes', 'rows' => 4]);
    echo html_writer::end_tag('label');
}

if ($type === 'page' || $type === 'summary') {
    echo html_writer::start_tag('label');
    echo html_writer::tag('span', $type === 'summary' ? 'Чек-лист / вопросы для самопроверки' : 'Текст урока');
    echo html_writer::tag('textarea', s($prefill['body']), ['name' => 'body', 'rows' => 12]);
    echo html_writer::end_tag('label');
}

if ($type === 'assign') {
    echo html_writer::start_tag('label');
    echo html_writer::tag('span', 'Задание для студента');
    echo html_writer::tag('textarea', s($prefill['intro']), [
        'name' => 'intro',
        'rows' => 6,
        'placeholder' => 'Что сделать и что сдать. Учитель увидит работу в кабинете «Домашки».',
    ]);
    echo html_writer::end_tag('label');

    echo html_writer::start_tag('fieldset', ['class' => 'lh-build__fieldset']);
    echo html_writer::tag('legend', 'Как сдавать');
    $formats = [
        'file' => 'Только файлы',
        'text' => 'Только текст в окне',
        'both' => 'Файлы и текст',
    ];
    foreach ($formats as $val => $lab) {
        echo html_writer::start_tag('label', ['class' => 'lh-build__radio']);
        $radio = [
            'type' => 'radio',
            'name' => 'submitformat',
            'value' => $val,
        ];
        if ($prefill['submitformat'] === $val) {
            $radio['checked'] = 'checked';
        }
        echo html_writer::empty_tag('input', $radio);
        echo html_writer::span($lab);
        echo html_writer::end_tag('label');
    }
    echo html_writer::end_tag('fieldset');

    echo html_writer::start_div('lh-build__filespec');
    echo html_writer::start_tag('label');
    echo html_writer::tag('span', 'Максимум файлов');
    echo html_writer::empty_tag('input', [
        'type' => 'number',
        'name' => 'maxfiles',
        'min' => 1,
        'max' => 20,
        'value' => $prefill['maxfiles'],
    ]);
    echo html_writer::end_tag('label');
    echo html_writer::start_tag('label');
    echo html_writer::tag('span', 'Типы файлов');
    $presets = theme_learnhub_builder_file_presets();
    if ($prefill['filetypes'] !== '' && !isset($presets[$prefill['filetypes']])) {
        $presets[$prefill['filetypes']] = $prefill['filetypes'];
    }
    echo html_writer::select($presets, 'filetypes', $prefill['filetypes'], false);
    echo html_writer::end_tag('label');
    echo html_writer::end_div();

    echo html_writer::start_tag('label', ['class' => 'lh-build__check']);
    $cb = ['type' => 'checkbox', 'name' => 'isfinal', 'value' => 1];
    if ($prefill['isfinal']) {
        $cb['checked'] = 'checked';
    }
    echo html_writer::empty_tag('input', $cb);
    echo html_writer::span('Это финальная работа курса');
    echo html_writer::end_tag('label');
}

if ($type === 'quiz') {
    echo html_writer::start_tag('label');
    echo html_writer::tag('span', 'Короткое описание');
    echo html_writer::tag('textarea', s($prefill['intro']), ['name' => 'intro', 'rows' => 3]);
    echo html_writer::end_tag('label');
    echo html_writer::start_tag('label', ['class' => 'lh-build__check']);
    $cb = ['type' => 'checkbox', 'name' => 'isfinal', 'value' => 1];
    if ($prefill['isfinal']) {
        $cb['checked'] = 'checked';
    }
    echo html_writer::empty_tag('input', $cb);
    echo html_writer::span('Итоговый тест курса');
    echo html_writer::end_tag('label');

    if ($prefill['slots']) {
        echo html_writer::tag('h2', 'Уже в тесте');
        echo html_writer::start_tag('ol', ['class' => 'lh-build__slots']);
        foreach ($prefill['slots'] as $slot) {
            echo html_writer::tag('li', s($slot['name']) . ' · ' . s($slot['qtype']));
        }
        echo html_writer::end_tag('ol');
        if ($cm) {
            echo html_writer::link(
                new moodle_url('/mod/quiz/edit.php', ['cmid' => $cm->id]),
                'Полный редактор вопросов Moodle',
                ['class' => 'lh-build__hintlink']
            );
        }
    }

    echo html_writer::tag('h2', $cm ? 'Добавить вопросы' : 'Вопросы');
    echo html_writer::tag('p', 'Верно/неверно или один правильный из четырёх. Можно сохранить тест пустым и дописать вопросы позже.', ['class' => 'lh-build__hint']);
    echo html_writer::start_div('lh-build__qlist', ['data-q-list' => '1']);
    echo theme_learnhub_builder_question_row_html();
    echo html_writer::end_div();
    echo html_writer::tag('button', '+ Ещё вопрос', [
        'type' => 'button',
        'class' => 'lh-build__btn lh-build__btn--ghost',
        'data-add-q' => '1',
    ]);
}

echo html_writer::start_div('lh-build__formactions');
echo html_writer::tag('button', $cm ? 'Сохранить' : 'Добавить в модуль', ['type' => 'submit', 'class' => 'lh-build__btn']);
echo html_writer::link($back, 'Отмена', ['class' => 'lh-build__btn lh-build__btn--ghost']);
echo html_writer::end_div();
echo html_writer::end_tag('form');
echo html_writer::end_div();
echo $OUTPUT->footer();

/**
 * @return string
 */
function theme_learnhub_builder_question_row_html() {
    $html = html_writer::start_div('lh-build__q', ['data-q-row' => '1']);
    $html .= html_writer::start_tag('label');
    $html .= html_writer::tag('span', 'Тип');
    $html .= html_writer::select(
        ['truefalse' => 'Верно / неверно', 'multichoice' => 'Один из четырёх'],
        'qtype[]',
        'truefalse',
        false,
        ['data-q-type' => '1']
    );
    $html .= html_writer::end_tag('label');
    $html .= html_writer::start_tag('label');
    $html .= html_writer::tag('span', 'Вопрос');
    $html .= html_writer::tag('textarea', '', ['name' => 'qtext[]', 'rows' => 2, 'placeholder' => 'Формулировка']);
    $html .= html_writer::end_tag('label');
    $html .= html_writer::start_div('lh-build__qtf', ['data-q-tf' => '1']);
    $html .= html_writer::start_tag('label');
    $html .= html_writer::tag('span', 'Правильный ответ');
    $html .= html_writer::select(['1' => 'Верно', '0' => 'Неверно'], 'qtf[]', '1', false);
    $html .= html_writer::end_tag('label');
    $html .= html_writer::end_div();
    $html .= html_writer::start_div('lh-build__qmc is-hidden', ['data-q-mc' => '1']);
    for ($i = 0; $i < 4; $i++) {
        $html .= html_writer::start_tag('label');
        $html .= html_writer::tag('span', 'Вариант ' . ($i + 1));
        $html .= html_writer::empty_tag('input', [
            'type' => 'text',
            'name' => 'qans' . $i . '[]',
            'placeholder' => $i === 0 ? 'Правильный можно отметить ниже' : '',
        ]);
        $html .= html_writer::end_tag('label');
    }
    $html .= html_writer::start_tag('label');
    $html .= html_writer::tag('span', 'Номер правильного');
    $html .= html_writer::select(
        ['0' => '1', '1' => '2', '2' => '3', '3' => '4'],
        'qok[]',
        '0',
        false
    );
    $html .= html_writer::end_tag('label');
    $html .= html_writer::end_div();
    $html .= html_writer::end_div();
    return $html;
}
