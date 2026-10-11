<?php
/**
 * ACO programme builder: Skillbox-like modules for site admins.
 *
 * Writes real Moodle page / quiz / assign records so the student player
 * and the teacher homework cabinet keep working.
 *
 * @package theme_learnhub
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/course/modlib.php');
require_once($CFG->libdir . '/resourcelib.php');
require_once($CFG->libdir . '/completionlib.php');
require_once($CFG->libdir . '/questionlib.php');
require_once($CFG->dirroot . '/question/engine/bank.php');
require_once($CFG->dirroot . '/mod/quiz/lib.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');
require_once($CFG->dirroot . '/mod/assign/locallib.php');
require_once($CFG->dirroot . '/course/format/aco/lib.php');

/**
 * @param int $courseid
 * @return \stdClass|null course or null when listing
 */
function theme_learnhub_builder_require_admin($courseid = 0) {
    require_login();
    if (isguestuser()) {
        redirect(new moodle_url('/login/index.php'));
    }
    if (!is_siteadmin()) {
        throw new moodle_exception('nopermissions', 'error', '', 'admin');
    }
    if (!$courseid) {
        return null;
    }
    $course = get_course($courseid);
    require_capability('moodle/course:update', context_course::instance($course->id));
    return $course;
}

/**
 * Switch a course to ACO format + sequential unlock.
 *
 * @param \stdClass $course
 * @return \stdClass
 */
function theme_learnhub_builder_ensure_aco($course) {
    $dirty = false;
    if ($course->format !== 'aco') {
        $course->format = 'aco';
        $dirty = true;
    }
    if (empty($course->enablecompletion)) {
        $course->enablecompletion = 1;
        $dirty = true;
    }
    if ($dirty) {
        update_course($course);
        $course = get_course($course->id);
    }
    course_get_format($course)->update_course_format_options([
        'hiddensections' => 0,
        'coursedisplay' => COURSE_DISPLAY_SINGLEPAGE,
        'aco_sequential' => 1,
    ]);
    return get_course($course->id);
}

/**
 * @param string $raw iframe or kinescope URL
 * @return string sanitized embed HTML or empty
 */
function theme_learnhub_builder_kinescope_html($raw) {
    $raw = trim((string) $raw);
    if ($raw === '') {
        return '';
    }
    $id = '';
    if (preg_match('#https://kinescope\.io/embed/([A-Za-z0-9_-]+)#i', $raw, $m)) {
        $id = $m[1];
    } else if (preg_match('#https://kinescope\.io/([A-Za-z0-9_-]+)#i', $raw, $m)) {
        $id = $m[1];
    }
    if ($id === '') {
        return '';
    }
    $src = 'https://kinescope.io/embed/' . $id;
    return '<div class="kinescope-embed"><iframe src="' . s($src) .
        '" allow="autoplay; fullscreen; picture-in-picture; encrypted-media; gyroscope; accelerometer; clipboard-write;" ' .
        'frameborder="0" allowfullscreen></iframe></div>';
}

/**
 * @param string $html
 * @return array{0:string,1:string} embed src, leftover notes
 */
function theme_learnhub_builder_extract_kinescope($html) {
    $src = '';
    if (preg_match('#https://kinescope\.io/embed/([A-Za-z0-9_-]+)#i', $html, $m)) {
        $src = 'https://kinescope.io/embed/' . $m[1];
    }
    $notes = preg_replace('#<div class="kinescope-embed">.*?</div>#si', '', (string) $html);
    $notes = trim(html_to_text($notes, 0, false));
    return [$src, $notes];
}

/**
 * @param string $notes
 * @return string
 */
function theme_learnhub_builder_notes_html($notes) {
    $notes = trim((string) $notes);
    if ($notes === '') {
        return '';
    }
    $paras = preg_split("/\n{2,}/", $notes) ?: [$notes];
    $out = '<div class="aco-lesson-notes">';
    foreach ($paras as $p) {
        $out .= '<p>' . nl2br(s(trim($p))) . '</p>';
    }
    return $out . '</div>';
}

/**
 * @param string $name
 * @return string latin shortname fragment
 */
function theme_learnhub_builder_slug($name) {
    $map = [
        'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'e',
        'ж' => 'zh', 'з' => 'z', 'и' => 'i', 'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm',
        'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u',
        'ф' => 'f', 'х' => 'h', 'ц' => 'ts', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'sch', 'ъ' => '',
        'ы' => 'y', 'ь' => '', 'э' => 'e', 'ю' => 'yu', 'я' => 'ya',
    ];
    $name = core_text::strtolower(trim($name));
    $out = '';
    $len = core_text::strlen($name);
    for ($i = 0; $i < $len; $i++) {
        $ch = core_text::substr($name, $i, 1);
        if (isset($map[$ch])) {
            $out .= $map[$ch];
        } else if (preg_match('/[a-z0-9]/', $ch)) {
            $out .= $ch;
        } else if ($ch === ' ' || $ch === '-' || $ch === '_') {
            $out .= '-';
        }
    }
    $out = trim(preg_replace('/-+/', '-', $out), '-');
    return $out !== '' ? $out : 'course';
}

/**
 * @return int
 */
function theme_learnhub_builder_default_category() {
    global $DB;

    $id = (int) $DB->get_field_sql('SELECT MIN(id) FROM {course_categories} WHERE visible = 1 AND id > 0');
    if ($id) {
        return $id;
    }
    return (int) $DB->get_field_sql('SELECT MIN(id) FROM {course_categories}');
}

/**
 * @param string $fullname
 * @param string $summary
 * @return \stdClass
 */
function theme_learnhub_builder_create_course($fullname, $summary = '') {
    global $DB;

    $fullname = trim($fullname);
    if ($fullname === '') {
        throw new moodle_exception('invalidparameter', 'error');
    }
    $base = core_text::substr(theme_learnhub_builder_slug($fullname), 0, 80);
    $short = $base;
    $n = 1;
    while ($DB->record_exists('course', ['shortname' => $short])) {
        $n++;
        $short = $base . '-' . $n;
    }

    $cfg = get_config('moodlecourse');
    $data = (object) [
        'category' => theme_learnhub_builder_default_category(),
        'fullname' => $fullname,
        'shortname' => $short,
        'summary' => $summary,
        'summaryformat' => FORMAT_HTML,
        'format' => 'aco',
        'numsections' => 1,
        'startdate' => time(),
        'enddate' => 0,
        'visible' => 1,
        'enablecompletion' => 1,
        'showcompletionconditions' => 1,
        'newsitems' => 0,
        'showgrades' => 1,
        'showreports' => 1,
        'maxbytes' => $cfg->maxbytes ?? 0,
        'groupmode' => 0,
        'groupmodeforce' => 0,
        'defaultgroupingid' => 0,
        'lang' => '',
        'theme' => '',
        'calendartype' => '',
        'requested' => 0,
        'completionnotify' => 0,
        'hiddensections' => 0,
        'coursedisplay' => COURSE_DISPLAY_SINGLEPAGE,
    ];
    $course = create_course($data);
    $course = theme_learnhub_builder_ensure_aco($course);
    theme_learnhub_builder_add_module($course, 'Модуль 1', 'module');
    return get_course($course->id);
}

/**
 * @param \stdClass $course
 * @return array
 */
function theme_learnhub_builder_modules($course) {
    $modinfo = get_fast_modinfo($course);
    $out = [];
    $modnum = 0;
    foreach ($modinfo->get_section_info_all() as $section) {
        if ((int) $section->section === 0) {
            continue;
        }
        $modnum++;
        $items = [];
        foreach ($modinfo->get_cms() as $cm) {
            if ((int) $cm->sectionnum !== (int) $section->section || $cm->deletioninprogress) {
                continue;
            }
            if (in_array($cm->modname, ['label', 'forum'], true)) {
                continue;
            }
            $kind = theme_learnhub_builder_item_kind($cm);
            $items[] = [
                'cm' => $cm,
                'kind' => $kind,
                'label' => theme_learnhub_builder_kind_label($kind),
            ];
        }
        $role = theme_learnhub_builder_section_role($section);
        $out[] = [
            'section' => $section,
            'num' => $modnum,
            'role' => $role,
            'name' => $section->name ?: ('Модуль ' . $modnum),
            'items' => $items,
        ];
    }
    return $out;
}

/**
 * @param \section_info|\stdClass $section
 * @return string module|finaltest|final
 */
function theme_learnhub_builder_section_role($section) {
    $id = strtolower(trim((string) ($section->idnumber ?? '')));
    if (in_array($id, ['finaltest', 'final-test', 'exam'], true)) {
        return 'finaltest';
    }
    if (in_array($id, ['final', 'finalwork', 'project'], true)) {
        return 'final';
    }
    return 'module';
}

/**
 * @param \cm_info $cm
 * @return string video|page|summary|practice|test|finaltest|final|file|link|meeting
 */
function theme_learnhub_builder_item_kind($cm) {
    $id = strtolower(trim((string) $cm->idnumber));
    if ($cm->modname === 'bigbluebuttonbn' || $id === 'meeting' || strpos($id, 'meeting') === 0) {
        return 'meeting';
    }
    if (strpos($id, 'video') === 0) {
        return 'video';
    }
    if (in_array($id, ['practice', 'assign', 'homework'], true)) {
        return 'practice';
    }
    if (in_array($id, ['summary', 'recap', 'selfcheck'], true)) {
        return 'summary';
    }
    if (in_array($id, ['finaltest', 'exam'], true)) {
        return 'finaltest';
    }
    if (in_array($id, ['final', 'project'], true)) {
        return 'final';
    }
    if ($id === 'test' || $id === 'quiz') {
        return 'test';
    }
    if ($id === 'page' || $id === 'lesson') {
        return 'page';
    }
    if ($cm->modname === 'assign') {
        return 'practice';
    }
    if ($cm->modname === 'quiz') {
        return 'test';
    }
    if ($cm->modname === 'page') {
        return 'page';
    }
    if ($cm->modname === 'url') {
        return 'link';
    }
    if ($cm->modname === 'resource') {
        return 'file';
    }
    return 'page';
}

/**
 * @param string $kind
 * @return string
 */
function theme_learnhub_builder_kind_label($kind) {
    $map = [
        'video' => 'Видео',
        'page' => 'Текст',
        'summary' => 'Самопроверка',
        'practice' => 'Домашка',
        'test' => 'Тест',
        'finaltest' => 'Итоговый тест',
        'final' => 'Финальная работа',
        'file' => 'Файл',
        'link' => 'Ссылка',
        'meeting' => 'Онлайн-встреча',
    ];
    return $map[$kind] ?? 'Материал';
}

/**
 * @param \stdClass $course
 * @param string $name
 * @param string $role module|finaltest|final
 * @return \stdClass section
 */
function theme_learnhub_builder_add_module($course, $name, $role = 'module') {
    $section = course_create_section($course, 0);
    $idnumber = '';
    if ($role === 'finaltest') {
        $idnumber = 'finaltest';
    } else if ($role === 'final') {
        $idnumber = 'final';
    }
    course_update_section($course, $section, [
        'name' => $name,
        'idnumber' => $idnumber,
    ]);
    rebuild_course_cache($course->id, true);
    return $section;
}

/**
 * Remove a numbered module (section) and every lesson inside it.
 *
 * @param \stdClass $course
 * @param int $sectionid course_sections.id
 * @return bool
 */
function theme_learnhub_builder_delete_module($course, $sectionid) {
    global $DB;

    $modinfo = get_fast_modinfo($course);
    $section = $modinfo->get_section_info_by_id((int) $sectionid);
    if (!$section || (int) $section->section < 1) {
        return false;
    }
    $sectionnum = (int) $section->section;
    foreach ($modinfo->get_cms() as $cm) {
        if ((int) $cm->sectionnum !== $sectionnum || $cm->deletioninprogress) {
            continue;
        }
        course_delete_module($cm->id);
    }
    rebuild_course_cache($course->id, true);
    $course = get_course($course->id);
    $ok = course_delete_section($course, $sectionnum, true);
    if (!$ok && $DB->record_exists('course_sections', ['id' => (int) $sectionid])) {
        $DB->delete_records('course_format_options', ['sectionid' => (int) $sectionid]);
        $DB->delete_records('course_sections', ['id' => (int) $sectionid]);
        rebuild_course_cache($course->id, true);
        $ok = true;
    }
    format_aco_rebuild_sequence($course->id);
    return $ok;
}

/**
 * @param \stdClass $course
 * @param int $sectionnum
 * @param string $dir up|down
 */
function theme_learnhub_builder_move_section($course, $sectionnum, $dir) {
    $sectionnum = (int) $sectionnum;
    if ($sectionnum < 1) {
        return;
    }
    $dest = $dir === 'up' ? $sectionnum - 1 : $sectionnum + 1;
    if ($dest < 1) {
        return;
    }
    move_section_to($course, $sectionnum, $dest);
    format_aco_rebuild_sequence($course->id);
}

/**
 * @param \stdClass $course
 * @param int $cmid
 * @param string $dir up|down
 */
function theme_learnhub_builder_move_cm($course, $cmid, $dir) {
    $modinfo = get_fast_modinfo($course);
    $cm = $modinfo->get_cm($cmid);
    $list = [];
    foreach ($modinfo->get_cms() as $c) {
        if ((int) $c->sectionnum !== (int) $cm->sectionnum || $c->deletioninprogress) {
            continue;
        }
        if (in_array($c->modname, ['label', 'forum'], true)) {
            continue;
        }
        $list[] = $c;
    }
    $idx = null;
    foreach ($list as $i => $c) {
        if ((int) $c->id === (int) $cmid) {
            $idx = $i;
            break;
        }
    }
    if ($idx === null) {
        return;
    }
    $section = $modinfo->get_section_info($cm->sectionnum);
    if ($dir === 'up') {
        if ($idx < 1) {
            return;
        }
        moveto_module($list[$idx], $section, $list[$idx - 1]);
    } else {
        if (!isset($list[$idx + 1])) {
            return;
        }
        $before = $list[$idx + 2] ?? null;
        moveto_module($list[$idx], $section, $before);
    }
    format_aco_rebuild_sequence($course->id);
}

/**
 * Shared add_moduleinfo defaults.
 *
 * @param \stdClass $course
 * @param int $sectionnum
 * @param string $modname
 * @param string $name
 * @param string $idnumber
 * @return \stdClass
 */
function theme_learnhub_builder_modinfo_base($course, $sectionnum, $modname, $name, $idnumber) {
    global $DB;

    $info = new stdClass();
    $info->modulename = $modname;
    $info->module = $DB->get_field('modules', 'id', ['name' => $modname], MUST_EXIST);
    $info->course = $course->id;
    $info->section = (int) $sectionnum;
    $info->visible = 1;
    $info->visibleoncoursepage = 1;
    $info->name = $name;
    $info->intro = '';
    $info->introformat = FORMAT_HTML;
    $info->showdescription = 0;
    $info->cmidnumber = $idnumber;
    $info->groupmode = 0;
    $info->groupingid = 0;
    $info->completionexpected = 0;
    return $info;
}

/**
 * @param \stdClass $course
 * @param int $sectionnum
 * @param string $name
 * @param string $content
 * @param string $idnumber
 * @param bool $manual
 * @return \stdClass
 */
/**
 * Live class in the programme. Uses Moodle's built-in BigBlueButton activity.
 * Join URLs stay inside Moodle: no shareable guest link, wait for teacher.
 *
 * @param \stdClass $course
 * @param int $sectionnum
 * @param string $name
 * @param string $intro
 * @param int $openingtime
 * @param int $closingtime
 * @return \stdClass
 */
function theme_learnhub_builder_add_meeting($course, $sectionnum, $name, $intro, $openingtime, $closingtime) {
    global $DB;

    if (!$DB->get_field('modules', 'id', ['name' => 'bigbluebuttonbn', 'visible' => 1])) {
        throw new moodle_exception('moduledisable', 'error', '', 'bigbluebuttonbn');
    }

    $info = theme_learnhub_builder_modinfo_base($course, $sectionnum, 'bigbluebuttonbn', $name, 'meeting');
    $info->instance = 0;
    $info->intro = $intro !== '' ? $intro : '<p>Вход только из аккаунта ученика этого курса. Ссылку переслать нельзя.</p>';
    $info->type = 0;
    $info->wait = 1;
    $info->record = 1;
    $info->recordallfromstart = 0;
    $info->recordhidebutton = 0;
    $info->welcome = '';
    $info->voicebridge = 0;
    $info->openingtime = max(0, (int) $openingtime);
    $info->closingtime = max(0, (int) $closingtime);
    $info->presentation = '';
    $info->participants = '[]';
    $info->userlimit = 0;
    $info->recordings_html = 0;
    $info->recordings_deleted = 1;
    $info->recordings_imported = 0;
    $info->recordings_preview = 0;
    $info->muteonstart = 1;
    $info->guestallowed = 0;
    $info->mustapproveuser = 1;
    $info->showpresentation = 1;
    $info->groupmode = SEPARATEGROUPS;
    $info->completion = COMPLETION_TRACKING_AUTOMATIC;
    $info->completionview = COMPLETION_VIEW_REQUIRED;
    $cm = add_moduleinfo($info, $course);
    format_aco_rebuild_sequence($course->id);
    return $cm;
}

/**
 * @param \stdClass $course
 * @param \cm_info $cm
 * @param string $name
 * @param string $intro
 * @param int $openingtime
 * @param int $closingtime
 */
function theme_learnhub_builder_update_meeting($course, $cm, $name, $intro, $openingtime, $closingtime) {
    global $DB;

    $DB->update_record('bigbluebuttonbn', (object) [
        'id' => $cm->instance,
        'name' => $name,
        'intro' => $intro,
        'introformat' => FORMAT_HTML,
        'wait' => 1,
        'guestallowed' => 0,
        'openingtime' => max(0, (int) $openingtime),
        'closingtime' => max(0, (int) $closingtime),
        'timemodified' => time(),
    ]);
    set_coursemodule_name($cm->id, $name);
    set_coursemodule_idnumber($cm->id, 'meeting');
    $DB->set_field('course_modules', 'groupmode', SEPARATEGROUPS, ['id' => $cm->id]);
    rebuild_course_cache($course->id, true);
}

function theme_learnhub_builder_add_page($course, $sectionnum, $name, $content, $idnumber, $manual = true) {
    $info = theme_learnhub_builder_modinfo_base($course, $sectionnum, 'page', $name, $idnumber);
    $info->content = $content;
    $info->contentformat = FORMAT_HTML;
    $info->display = RESOURCELIB_DISPLAY_OPEN;
    $info->printintro = 0;
    $info->printlastmodified = 0;
    $info->completion = $manual ? COMPLETION_TRACKING_MANUAL : COMPLETION_TRACKING_AUTOMATIC;
    $info->completionview = $manual ? COMPLETION_VIEW_NOT_REQUIRED : COMPLETION_VIEW_REQUIRED;
    $cm = add_moduleinfo($info, $course);
    format_aco_rebuild_sequence($course->id);
    return $cm;
}

/**
 * @param \stdClass $course
 * @param \cm_info $cm
 * @param string $name
 * @param string $content
 * @param string $idnumber
 */
function theme_learnhub_builder_update_page($course, $cm, $name, $content, $idnumber) {
    global $DB;

    $DB->set_field('page', 'name', $name, ['id' => $cm->instance]);
    $DB->set_field('page', 'content', $content, ['id' => $cm->instance]);
    $DB->set_field('page', 'timemodified', time(), ['id' => $cm->instance]);
    set_coursemodule_name($cm->id, $name);
    set_coursemodule_idnumber($cm->id, $idnumber);
    rebuild_course_cache($course->id, true);
}

/**
 * @param string $format file|text|both
 * @return array
 */
function theme_learnhub_builder_assign_plugins($format) {
    $file = ($format === 'file' || $format === 'both');
    $text = ($format === 'text' || $format === 'both');
    if (!$file && !$text) {
        $file = true;
        $text = true;
    }
    return [$file, $text];
}

/**
 * @param \stdClass $course
 * @param int $sectionnum
 * @param string $name
 * @param string $intro
 * @param string $idnumber
 * @param string $format file|text|both
 * @param int $maxfiles
 * @param string $filetypes
 * @return \stdClass
 */
function theme_learnhub_builder_add_assign($course, $sectionnum, $name, $intro, $idnumber, $format, $maxfiles, $filetypes) {
    global $DB;

    [$file, $text] = theme_learnhub_builder_assign_plugins($format);
    $info = theme_learnhub_builder_modinfo_base($course, $sectionnum, 'assign', $name, $idnumber);
    $info->intro = $intro;
    $info->alwaysshowdescription = 1;
    $info->submissiondrafts = 0;
    $info->requiresubmissionstatement = 0;
    $info->sendnotifications = 0;
    $info->sendlatenotifications = 0;
    $info->sendstudentnotifications = 1;
    $info->duedate = 0;
    $info->cutoffdate = 0;
    $info->gradingduedate = 0;
    $info->allowsubmissionsfromdate = 0;
    $info->grade = 100;
    $info->teamsubmission = 0;
    $info->requireallteammemberssubmit = 0;
    $info->blindmarking = 0;
    $info->hidegrader = 0;
    $info->markingworkflow = 0;
    $info->markingallocation = 0;
    $info->markinganonymous = 0;
    $info->attemptreopenmethod = 'untilpass';
    $info->maxattempts = -1;
    $info->assignsubmission_onlinetext_enabled = $text ? 1 : 0;
    $info->assignsubmission_onlinetext_wordlimit_enabled = 0;
    $info->assignsubmission_file_enabled = $file ? 1 : 0;
    $info->assignsubmission_file_maxfiles = max(1, min(20, (int) $maxfiles));
    $info->assignsubmission_file_maxsizebytes = 0;
    $info->assignsubmission_file_filetypes = $filetypes;
    $info->assignfeedback_comments_enabled = 1;
    $info->completion = COMPLETION_TRACKING_AUTOMATIC;
    $info->completionsubmit = 1;
    $info->completionpassgrade = 1;
    $info->completiongradeitemnumber = 0;
    $info->completionview = COMPLETION_VIEW_NOT_REQUIRED;
    $cm = add_moduleinfo($info, $course);
    $gi = $DB->get_record('grade_items', [
        'itemtype' => 'mod',
        'itemmodule' => 'assign',
        'iteminstance' => $cm->instance,
        'itemnumber' => 0,
        'courseid' => $course->id,
    ]);
    if ($gi) {
        $DB->set_field('grade_items', 'gradepass', 1, ['id' => $gi->id]);
    }
    format_aco_rebuild_sequence($course->id);
    return $cm;
}

/**
 * @param \stdClass $course
 * @param \cm_info $cm
 * @param string $name
 * @param string $intro
 * @param string $idnumber
 * @param string $format
 * @param int $maxfiles
 * @param string $filetypes
 */
function theme_learnhub_builder_update_assign($course, $cm, $name, $intro, $idnumber, $format, $maxfiles, $filetypes) {
    global $DB;

    $DB->set_field('assign', 'name', $name, ['id' => $cm->instance]);
    $DB->set_field('assign', 'intro', $intro, ['id' => $cm->instance]);
    $DB->set_field('assign', 'introformat', FORMAT_HTML, ['id' => $cm->instance]);
    set_coursemodule_name($cm->id, $name);
    set_coursemodule_idnumber($cm->id, $idnumber);

    [$file, $text] = theme_learnhub_builder_assign_plugins($format);
    $assign = new assign(context_module::instance($cm->id), $cm, $course);
    $fileplug = $assign->get_submission_plugin_by_type('file');
    $textplug = $assign->get_submission_plugin_by_type('onlinetext');
    if ($fileplug) {
        $fileplug->set_config('enabled', $file ? 1 : 0);
        $fileplug->set_config('maxfilesubmissions', max(1, min(20, (int) $maxfiles)));
        $fileplug->set_config('filetypeslist', $filetypes);
    }
    if ($textplug) {
        $textplug->set_config('enabled', $text ? 1 : 0);
    }
    $fb = $assign->get_feedback_plugin_by_type('comments');
    if ($fb) {
        $fb->set_config('enabled', 1);
    }
    rebuild_course_cache($course->id, true);
}

/**
 * @param \cm_info $cm
 * @return array{format:string,maxfiles:int,filetypes:string,intro:string,name:string}
 */
function theme_learnhub_builder_assign_state($cm) {
    global $DB;

    $assign = $DB->get_record('assign', ['id' => $cm->instance], '*', MUST_EXIST);
    $obj = new assign(context_module::instance($cm->id), $cm, get_course($cm->course));
    $fileplug = $obj->get_submission_plugin_by_type('file');
    $textplug = $obj->get_submission_plugin_by_type('onlinetext');
    $fileon = $fileplug && (int) $fileplug->get_config('enabled') === 1;
    $texton = $textplug && (int) $textplug->get_config('enabled') === 1;
    if ($fileon && $texton) {
        $format = 'both';
    } else if ($texton) {
        $format = 'text';
    } else {
        $format = 'file';
    }
    return [
        'name' => $assign->name,
        'intro' => html_to_text($assign->intro, 0, false),
        'format' => $format,
        'maxfiles' => $fileplug ? (int) $fileplug->get_config('maxfilesubmissions') : 3,
        'filetypes' => $fileplug ? (string) $fileplug->get_config('filetypeslist') : '',
    ];
}

/**
 * @param \stdClass $course
 * @param int $sectionnum
 * @param string $name
 * @param string $intro
 * @param string $idnumber
 * @return \stdClass
 */
function theme_learnhub_builder_add_quiz($course, $sectionnum, $name, $intro, $idnumber) {
    $info = theme_learnhub_builder_modinfo_base($course, $sectionnum, 'quiz', $name, $idnumber);
    $info->intro = $intro;
    $info->showdescription = 1;
    $info->timeopen = 0;
    $info->timeclose = 0;
    $info->timelimit = 0;
    $info->overduehandling = 'autoabandon';
    $info->graceperiod = 0;
    $info->preferredbehaviour = 'deferredfeedback';
    $info->canredoquestions = 0;
    $info->attempts = 0;
    $info->attemptonlast = 0;
    $info->grademethod = QUIZ_GRADEHIGHEST;
    $info->decimalpoints = 2;
    $info->questiondecimalpoints = -1;
    $info->questionsperpage = 0;
    $info->navmethod = 'free';
    $info->shuffleanswers = 1;
    $info->sumgrades = 0;
    $info->grade = 100;
    $info->browsersecurity = '-';
    $info->delay1 = 0;
    $info->delay2 = 0;
    $info->showuserpicture = 0;
    $info->showblocks = 0;
    $info->quizpassword = '';
    $info->subnet = '';
    foreach (['attempt', 'correctness', 'maxmarks', 'marks', 'specificfeedback', 'generalfeedback', 'rightanswer', 'overallfeedback'] as $f) {
        $info->{$f . 'immediately'} = 1;
        $info->{$f . 'open'} = 1;
        $info->{$f . 'closed'} = 1;
    }
    $info->completion = COMPLETION_TRACKING_AUTOMATIC;
    $info->completionview = COMPLETION_VIEW_NOT_REQUIRED;
    $info->completionusegrade = 1;
    $info->completionpassgrade = 0;
    $info->completionminattemptsenabled = 1;
    $info->completionminattempts = 1;
    $info->completionunlocked = 1;
    $cm = add_moduleinfo($info, $course);
    format_aco_rebuild_sequence($course->id);
    return $cm;
}

/**
 * @param int $cmid
 * @return \stdClass category
 */
function theme_learnhub_builder_quiz_category($cmid) {
    $modctx = context_module::instance($cmid);
    return question_make_default_categories([$modctx]);
}

/**
 * @param \stdClass $quizcm add_moduleinfo result or cm
 * @param array $questions
 */
function theme_learnhub_builder_add_quiz_questions($quizcm, array $questions) {
    global $DB;

    $cmid = (int) ($quizcm->coursemodule ?? $quizcm->id ?? 0);
    $instance = (int) ($quizcm->instance ?? 0);
    if (!$instance && $cmid) {
        $instance = (int) $DB->get_field('course_modules', 'instance', ['id' => $cmid]);
    }
    if (!$cmid || !$instance) {
        return;
    }
    $quiz = $DB->get_record('quiz', ['id' => $instance], '*', MUST_EXIST);
    $quiz->cmid = $cmid;
    $cat = theme_learnhub_builder_quiz_category($cmid);
    $ctx = context_module::instance($cmid);

    foreach ($questions as $q) {
        $text = trim((string) ($q['text'] ?? ''));
        if ($text === '') {
            continue;
        }
        $type = $q['type'] ?? 'truefalse';
        if ($type === 'multichoice') {
            $saved = theme_learnhub_builder_save_multichoice($cat, $ctx, $text, $q['answers'] ?? [], (int) ($q['correct'] ?? 0));
        } else {
            $saved = theme_learnhub_builder_save_truefalse($cat, $ctx, $text, !empty($q['true']));
        }
        if ($saved) {
            quiz_add_quiz_question($saved->id, $quiz);
        }
    }
    \mod_quiz\quiz_settings::create($quiz->id)->get_grade_calculator()->recompute_quiz_sumgrades();
}

/**
 * @param \stdClass $cat
 * @param \context $ctx
 * @param string $text
 * @param bool $correcttrue
 * @return \stdClass|null
 */
function theme_learnhub_builder_save_truefalse($cat, $ctx, $text, $correcttrue) {
    $qtype = question_bank::get_qtype('truefalse');
    $question = new stdClass();
    $question->qtype = 'truefalse';
    $form = new stdClass();
    $form->category = $cat->id . ',' . $ctx->id;
    $form->name = shorten_text(strip_tags($text), 80);
    $form->questiontext = ['text' => s($text), 'format' => FORMAT_HTML];
    $form->generalfeedback = ['text' => '', 'format' => FORMAT_HTML];
    $form->defaultmark = 1;
    $form->penalty = 1;
    $form->correctanswer = $correcttrue ? 1 : 0;
    $form->feedbacktrue = ['text' => '', 'format' => FORMAT_HTML];
    $form->feedbackfalse = ['text' => '', 'format' => FORMAT_HTML];
    $form->status = \core_question\local\bank\question_version_status::QUESTION_STATUS_READY;
    return $qtype->save_question($question, $form);
}

/**
 * @param \stdClass $cat
 * @param \context $ctx
 * @param string $text
 * @param array $answers
 * @param int $correct
 * @return \stdClass|null
 */
function theme_learnhub_builder_save_multichoice($cat, $ctx, $text, array $answers, $correct) {
    $clean = [];
    foreach ($answers as $a) {
        $a = trim((string) $a);
        if ($a !== '') {
            $clean[] = $a;
        }
    }
    if (count($clean) < 2) {
        return null;
    }
    $correct = max(0, min(count($clean) - 1, (int) $correct));
    $qtype = question_bank::get_qtype('multichoice');
    $question = new stdClass();
    $question->qtype = 'multichoice';
    $form = new stdClass();
    $form->category = $cat->id . ',' . $ctx->id;
    $form->name = shorten_text(strip_tags($text), 80);
    $form->questiontext = ['text' => s($text), 'format' => FORMAT_HTML];
    $form->generalfeedback = ['text' => '', 'format' => FORMAT_HTML];
    $form->defaultmark = 1;
    $form->penalty = 0.3333333;
    $form->status = \core_question\local\bank\question_version_status::QUESTION_STATUS_READY;
    $form->single = 1;
    $form->shuffleanswers = 1;
    $form->answernumbering = 'abc';
    $form->showstandardinstruction = 0;
    $empty = ['text' => '', 'format' => FORMAT_HTML];
    $form->correctfeedback = ['text' => get_string('correctfeedbackdefault', 'question'), 'format' => FORMAT_HTML];
    $form->partiallycorrectfeedback = $empty;
    $form->incorrectfeedback = ['text' => get_string('incorrectfeedbackdefault', 'question'), 'format' => FORMAT_HTML];
    $form->shownumcorrect = 0;
    $form->answer = [];
    $form->fraction = [];
    $form->feedback = [];
    foreach ($clean as $i => $a) {
        $form->answer[$i] = ['text' => s($a), 'format' => FORMAT_HTML];
        $form->fraction[$i] = ($i === $correct) ? 1.0 : 0.0;
        $form->feedback[$i] = $empty;
    }
    return $qtype->save_question($question, $form);
}

/**
 * Questions posted from the builder form.
 *
 * @return array
 */
function theme_learnhub_builder_posted_questions() {
    $types = optional_param_array('qtype', [], PARAM_ALPHA);
    $texts = optional_param_array('qtext', [], PARAM_TEXT);
    $tfs = optional_param_array('qtf', [], PARAM_INT);
    $oks = optional_param_array('qok', [], PARAM_INT);
    $a0 = optional_param_array('qans0', [], PARAM_TEXT);
    $a1 = optional_param_array('qans1', [], PARAM_TEXT);
    $a2 = optional_param_array('qans2', [], PARAM_TEXT);
    $a3 = optional_param_array('qans3', [], PARAM_TEXT);
    $out = [];
    foreach ($texts as $i => $text) {
        $text = trim((string) $text);
        if ($text === '') {
            continue;
        }
        $type = $types[$i] ?? 'truefalse';
        $out[] = [
            'type' => $type === 'multichoice' ? 'multichoice' : 'truefalse',
            'text' => $text,
            'true' => !empty($tfs[$i]),
            'answers' => [$a0[$i] ?? '', $a1[$i] ?? '', $a2[$i] ?? '', $a3[$i] ?? ''],
            'correct' => (int) ($oks[$i] ?? 0),
        ];
    }
    return $out;
}

/**
 * @param int $quizid
 * @param int $cmid
 * @return array
 */
function theme_learnhub_builder_quiz_slots($quizid, $cmid) {
    $slots = \mod_quiz\question\bank\qbank_helper::get_question_structure(
        $quizid,
        context_module::instance($cmid)
    );
    $out = [];
    foreach ($slots as $slot) {
        $out[] = [
            'name' => $slot->name ?? ('Вопрос ' . ($slot->slot ?? '')),
            'qtype' => $slot->qtype ?? '',
        ];
    }
    return $out;
}

/**
 * File-type presets for homework uploads.
 *
 * @return array
 */
function theme_learnhub_builder_file_presets() {
    return [
        '' => 'Любые файлы',
        '.pdf .doc .docx .ppt .pptx .odt .txt' => 'Документы',
        '.jpg .jpeg .png .gif .webp .svg' => 'Изображения',
        '.zip .rar .7z' => 'Архивы',
        '.html .css .js .ts .json .py .zip' => 'Код и архив',
        '.pdf .zip .png .jpg' => 'PDF, архив и картинки',
    ];
}

/**
 * @param \stdClass|null $course
 * @return string
 */
function theme_learnhub_builder_header_html($course = null) {
    global $CFG, $USER;

    $wp = rtrim((string) get_config('auth_edwiserbridge', 'wpsiteurl'), '/');
    $home = $wp !== '' ? $wp . '/' : $CFG->wwwroot . '/my/';
    $list = new moodle_url('/theme/learnhub/builder/index.php');
    $logout = new moodle_url('/login/logout.php', ['sesskey' => sesskey()]);

    $links = html_writer::link($list, 'Все курсы');
    $links .= html_writer::link($home, 'Сайт АЦО');
    if ($course) {
        $links .= html_writer::link(
            new moodle_url('/course/view.php', ['id' => $course->id, 'lhpreview' => 1]),
            'Как видит студент',
            ['target' => '_blank']
        );
        $links .= html_writer::link(new moodle_url('/user/index.php', ['id' => $course->id]), 'Участники');
        $links .= html_writer::link(new moodle_url('/grade/report/grader/index.php', ['id' => $course->id]), 'Оценки');
        $links .= html_writer::link(new moodle_url('/course/edit.php', ['id' => $course->id]), 'Настройки Moodle');
    }
    $links .= html_writer::link(new moodle_url('/admin/search.php'), 'Админка Moodle');

    $user = html_writer::span(s(fullname($USER)), 'lh-build__user');
    $user .= html_writer::link($logout, 'Выход');

    $html = html_writer::start_tag('header', ['class' => 'lh-build__top']);
    $html .= html_writer::link($home, 'АЦО', ['class' => 'lh-build__logo']);
    $html .= html_writer::tag('strong', $course ? format_string($course->fullname) : 'Конструктор курсов', ['class' => 'lh-build__title']);
    $html .= html_writer::div($links, 'lh-build__nav');
    $html .= html_writer::div($user, 'lh-build__right');
    $html .= html_writer::end_tag('header');
    return $html;
}

/**
 * Setup page chrome for builder screens.
 *
 * @param moodle_url $url
 * @param \context $ctx
 * @param string $title
 * @param \stdClass|null $course
 */
function theme_learnhub_builder_setup_page(moodle_url $url, $ctx, $title, $course = null) {
    global $PAGE;

    $PAGE->set_url($url);
    $PAGE->set_context($ctx);
    $PAGE->set_pagelayout('builder');
    $PAGE->set_title($title);
    $PAGE->set_heading($title);
    if ($course) {
        $PAGE->set_course($course);
    }
    $PAGE->add_body_class('lh-builder');
    $PAGE->requires->js(new moodle_url('/theme/learnhub/javascript/builder.js', ['v' => 2026090801]));
}

/**
 * Tiny POST button used for move/delete in the builder.
 *
 * @param int $courseid
 * @param string $action
 * @param string $label
 * @param array $extra
 * @param string $class
 * @param string $confirm
 * @return string
 */
function theme_learnhub_builder_post_btn($courseid, $action, $label, array $extra = [], $class = '', $confirm = '') {
    $attrs = ['method' => 'post', 'class' => 'lh-build__inline'];
    if ($confirm !== '') {
        $attrs['onsubmit'] = 'return confirm(' . json_encode($confirm, JSON_UNESCAPED_UNICODE) . ')';
    }
    $html = html_writer::start_tag('form', $attrs);
    $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $courseid]);
    $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => $action]);
    foreach ($extra as $k => $v) {
        $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => $k, 'value' => $v]);
    }
    $btnclass = (strpos($class, 'lh-build__btn') !== false)
        ? trim($class)
        : ('lh-build__iconbtn' . ($class !== '' ? ' ' . $class : ''));
    $html .= html_writer::tag('button', $label, [
        'type' => 'submit',
        'class' => $btnclass,
        'title' => $confirm !== '' ? $confirm : strip_tags($label),
    ]);
    $html .= html_writer::end_tag('form');
    return $html;
}

/**
 * Compact stats for a builder course card.
 *
 * @param \stdClass $course
 * @return array{students:int,modules:int,lessons:int,visible:bool}
 */
function theme_learnhub_builder_course_stats($course) {
    $ctx = context_course::instance($course->id);
    $students = 0;
    foreach (get_enrolled_users($ctx, 'mod/assign:submit', 0, 'u.id') as $user) {
        if (has_capability('mod/assign:grade', $ctx, $user)) {
            continue;
        }
        $students++;
    }

    $modules = 0;
    $lessons = 0;
    try {
        $modinfo = get_fast_modinfo($course);
        foreach ($modinfo->get_section_info_all() as $section) {
            if ((int) $section->section === 0) {
                continue;
            }
            $modules++;
        }
        foreach ($modinfo->get_cms() as $cm) {
            if ($cm->deletioninprogress) {
                continue;
            }
            $lessons++;
        }
    } catch (Exception $e) {
        $modules = 0;
        $lessons = 0;
    }

    return [
        'students' => $students,
        'modules' => $modules,
        'lessons' => $lessons,
        'visible' => !empty($course->visible),
    ];
}
