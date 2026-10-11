<?php
namespace theme_learnhub;

defined('MOODLE_INTERNAL') || die();

/**
 * Per-course Q&A between a student and the course curator.
 *
 * @package theme_learnhub
 */
class curator {

    public const THREAD = 'theme_learnhub_cthread';
    public const MSG = 'theme_learnhub_cmsg';
    public const MAXLEN = 4000;
    public const MAXFILES = 5;
    public const FILEAREA = 'curator';
    public const COMPONENT = 'theme_learnhub';

    /** @var string[] */
    private const FILEEXT = [
        'jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf',
        'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
        'txt', 'csv', 'zip', 'mp3', 'mp4',
    ];

    /** @var string[] */
    private const DANGEROUS = [
        'php', 'phtml', 'phar', 'exe', 'js', 'html', 'htm', 'svg', 'shtml',
    ];

    /** @var bool */
    private static $tablesready = false;

    /**
     * Create storage if this Moodle has not seen the feature yet.
     */
    public static function ensure_tables(): void {
        global $DB;

        if (self::$tablesready) {
            return;
        }

        $dbman = $DB->get_manager();

        $thread = new \xmldb_table(self::THREAD);
        if (!$dbman->table_exists($thread)) {
            $thread->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $thread->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $thread->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $thread->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $thread->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $thread->add_field('lastmessage', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $thread->add_field('unreadteacher', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
            $thread->add_field('unreadstudent', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
            $thread->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $thread->add_key('courseuser', XMLDB_KEY_UNIQUE, ['courseid', 'userid']);
            $thread->add_index('courseid', XMLDB_INDEX_NOTUNIQUE, ['courseid']);
            $dbman->create_table($thread);
        }

        $msg = new \xmldb_table(self::MSG);
        if (!$dbman->table_exists($msg)) {
            $msg->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $msg->add_field('threadid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $msg->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $msg->add_field('isteacher', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
            $msg->add_field('message', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
            $msg->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $msg->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $msg->add_index('threadid', XMLDB_INDEX_NOTUNIQUE, ['threadid']);
            $dbman->create_table($msg);
        }

        self::$tablesready = true;
    }

    /**
     * Existing thread for this student in the course, or null.
     *
     * @param int $courseid
     * @param int $userid
     * @return \stdClass|null
     */
    public static function get_thread(int $courseid, int $userid): ?\stdClass {
        global $DB;

        self::ensure_tables();
        $thread = $DB->get_record(self::THREAD, ['courseid' => $courseid, 'userid' => $userid]);
        return $thread ?: null;
    }

    /**
     * @param int $courseid
     * @param int $userid
     * @return \stdClass
     */
    public static function get_or_create_thread(int $courseid, int $userid): \stdClass {
        global $DB;

        self::ensure_tables();
        $thread = $DB->get_record(self::THREAD, ['courseid' => $courseid, 'userid' => $userid]);
        if ($thread) {
            return $thread;
        }
        $now = time();
        $id = $DB->insert_record(self::THREAD, (object) [
            'courseid' => $courseid,
            'userid' => $userid,
            'timecreated' => $now,
            'timemodified' => $now,
            'lastmessage' => '',
            'unreadteacher' => 0,
            'unreadstudent' => 0,
        ]);
        return $DB->get_record(self::THREAD, ['id' => $id], '*', MUST_EXIST);
    }

    /**
     * @param \stdClass $thread
     * @param int $userid
     * @param string $message
     * @param bool $fromteacher
     * @return \stdClass thread
     */
    /**
     * Text, files, or both. Files are stored in the course file area.
     *
     * @param \stdClass $thread
     * @param int $userid
     * @param string $message
     * @param bool $fromteacher
     * @param array $files items with tmp and name
     * @param int $contextid course context, required when $files is not empty
     * @return \stdClass thread
     */
    public static function post(
        \stdClass $thread,
        int $userid,
        string $message,
        bool $fromteacher,
        array $files = [],
        int $contextid = 0
    ): \stdClass {
        global $DB;

        $message = trim($message);
        if ($message === '' && !$files) {
            throw new \moodle_exception('invalidparameter');
        }
        if (\core_text::strlen($message) > self::MAXLEN) {
            $message = \core_text::substr($message, 0, self::MAXLEN);
        }
        if ($files && $contextid <= 0) {
            throw new \moodle_exception('invalidparameter');
        }

        self::ensure_tables();
        $now = time();
        $transaction = $DB->start_delegated_transaction();
        $msgid = $DB->insert_record(self::MSG, (object) [
            'threadid' => (int) $thread->id,
            'userid' => $userid,
            'isteacher' => $fromteacher ? 1 : 0,
            'message' => $message,
            'timecreated' => $now,
        ]);

        $stored = [];
        $fs = get_file_storage();
        foreach ($files as $file) {
            $name = self::unique_filename($stored, (string) $file['name']);
            $stored[] = $name;
            $fs->create_file_from_pathname([
                'contextid' => $contextid,
                'component' => self::COMPONENT,
                'filearea' => self::FILEAREA,
                'itemid' => $msgid,
                'filepath' => '/',
                'filename' => $name,
                'userid' => $userid,
            ], $file['tmp']);
        }

        if ($message !== '') {
            $preview = \core_text::strlen($message) > 140
                ? \core_text::substr($message, 0, 140) . '…'
                : $message;
        } else if (count($stored) === 1) {
            $preview = 'Файл: ' . $stored[0];
        } else {
            $preview = 'Файлы: ' . count($stored);
        }

        $thread->timemodified = $now;
        $thread->lastmessage = $preview;
        if ($fromteacher) {
            $thread->unreadstudent = 1;
            $thread->unreadteacher = 0;
        } else {
            $thread->unreadteacher = 1;
            $thread->unreadstudent = 0;
        }
        $DB->update_record(self::THREAD, $thread);
        $transaction->allow_commit();
        return $thread;
    }

    /**
     * @param \stdClass $thread
     * @param int $userid
     * @param string $message
     * @param bool $fromteacher
     * @return \stdClass thread
     */
    public static function add_message(\stdClass $thread, int $userid, string $message, bool $fromteacher): \stdClass {
        return self::post($thread, $userid, $message, $fromteacher);
    }

    /**
     * Read the composer file input. Returns an error string or a list of files.
     *
     * @return array{error:string,files:array}
     */
    public static function take_uploads(): array {
        $empty = ['error' => '', 'files' => []];
        if (empty($_FILES['files']) || !is_array($_FILES['files']['name'] ?? null)) {
            return $empty;
        }

        $names = $_FILES['files']['name'];
        $tmp = $_FILES['files']['tmp_name'];
        $sizes = $_FILES['files']['size'];
        $errors = $_FILES['files']['error'];
        $picked = [];
        $total = 0;
        $max = self::max_bytes();

        foreach ($names as $i => $rawname) {
            $err = (int) ($errors[$i] ?? UPLOAD_ERR_NO_FILE);
            if ($err === UPLOAD_ERR_NO_FILE || $rawname === '') {
                continue;
            }
            if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) {
                return ['error' => 'Файл «' . clean_param($rawname, PARAM_FILE) . '» больше допустимого размера.', 'files' => []];
            }
            if ($err !== UPLOAD_ERR_OK) {
                return ['error' => 'Не удалось загрузить файл. Попробуйте ещё раз.', 'files' => []];
            }
            $picked[] = [
                'name' => (string) $rawname,
                'tmp' => (string) ($tmp[$i] ?? ''),
                'size' => (int) ($sizes[$i] ?? 0),
            ];
        }

        if (!$picked) {
            return $empty;
        }
        if (count($picked) > self::MAXFILES) {
            return ['error' => 'Можно прикрепить не больше ' . self::MAXFILES . ' файлов за раз.', 'files' => []];
        }

        $ready = [];
        foreach ($picked as $file) {
            $name = clean_param($file['name'], PARAM_FILE);
            if ($name === '' || $name === '.') {
                return ['error' => 'У одного из файлов неподходящее имя.', 'files' => []];
            }
            if ($file['size'] <= 0) {
                return ['error' => 'Файл «' . $name . '» пустой.', 'files' => []];
            }
            if ($file['size'] > $max) {
                return ['error' => 'Файл «' . $name . '» больше ' . self::format_size($max) . '.', 'files' => []];
            }
            $total += $file['size'];
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if (!in_array($ext, self::FILEEXT, true) || self::has_dangerous_part($name)) {
                return ['error' => 'Файл «' . $name . '» этого типа отправить нельзя.', 'files' => []];
            }
            if (!is_uploaded_file($file['tmp'])) {
                return ['error' => 'Не удалось загрузить файл.', 'files' => []];
            }
            if (self::mime_is_dangerous($file['tmp'])) {
                return ['error' => 'Файл «' . $name . '» этого типа отправить нельзя.', 'files' => []];
            }
            $ready[] = ['tmp' => $file['tmp'], 'name' => $name, 'size' => $file['size']];
        }

        $postmax = self::ini_bytes((string) ini_get('post_max_size'));
        if ($postmax > 0 && $total > ($postmax - 262144)) {
            return ['error' => 'Файлы вместе слишком большие. Отправьте их по частям.', 'files' => []];
        }
        return ['error' => '', 'files' => $ready];
    }

    /**
     * @param int $courseid
     * @param int $messageid
     * @param int $userid
     * @return bool
     */
    public static function user_can_see_file(int $courseid, int $messageid, int $userid): bool {
        global $DB;

        self::ensure_tables();
        $msg = $DB->get_record(self::MSG, ['id' => $messageid]);
        if (!$msg) {
            return false;
        }
        $thread = $DB->get_record(self::THREAD, ['id' => $msg->threadid, 'courseid' => $courseid]);
        if (!$thread) {
            return false;
        }
        if ((int) $thread->userid === $userid) {
            return true;
        }
        $context = \context_course::instance($courseid);
        return has_capability('mod/assign:grade', $context, $userid);
    }

    /**
     * @return int
     */
    public static function max_bytes(): int {
        $cap = 20 * 1024 * 1024;
        $upload = self::ini_bytes((string) ini_get('upload_max_filesize'));
        if ($upload > 0) {
            $cap = min($cap, $upload);
        }
        return $cap;
    }

    /**
     * @param int $bytes
     * @return string
     */
    public static function format_size(int $bytes): string {
        if ($bytes < 1024) {
            return $bytes . ' Б';
        }
        if ($bytes < 1048576) {
            return (string) max(1, (int) round($bytes / 1024)) . ' КБ';
        }
        $mb = $bytes / 1048576;
        $text = $mb >= 10 ? (string) (int) round($mb) : number_format($mb, 1, ',', '');
        return $text . ' МБ';
    }

    /**
     * @param string $name
     * @return string
     */
    public static function initials(string $name): string {
        $parts = preg_split('/\s+/u', trim($name)) ?: [];
        $letters = '';
        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }
            $letters .= \core_text::substr($part, 0, 1);
            if (\core_text::strlen($letters) >= 2) {
                break;
            }
        }
        $letters = \core_text::strtoupper($letters);
        return $letters !== '' ? $letters : '•';
    }

    /**
     * @param int $ts
     * @return string
     */
    public static function short_when(int $ts): string {
        $tz = \core_date::get_user_timezone_object();
        $day = (new \DateTimeImmutable('@' . $ts))->setTimezone($tz)->format('Y-m-d');
        $today = (new \DateTimeImmutable('now', $tz))->format('Y-m-d');
        if ($day === $today) {
            return userdate($ts, '%H:%M');
        }
        return userdate($ts, '%d.%m');
    }

    /**
     * Message log and the composer under it.
     *
     * @param int $courseid
     * @param \stdClass[] $messages
     * @param \moodle_url $action
     * @param string $draft
     * @param string $placeholder
     * @param string $error
     * @return string
     */
    public static function render_dialog(
        int $courseid,
        array $messages,
        bool $vieweristeacher,
        \moodle_url $action,
        string $draft,
        string $placeholder,
        string $error = ''
    ): string {
        $context = \context_course::instance($courseid);
        $files = self::files_by_message($context);

        $html = \html_writer::start_div('lh-chat__log', ['data-lh-chat-log' => '1']);
        if (!$messages) {
            $html .= \html_writer::div(
                'Здесь будет переписка. Напишите сообщение или прикрепите файл — фото, документ, архив.',
                'lh-chat__empty'
            );
        } else {
            $tz = \core_date::get_user_timezone_object();
            $lastday = '';
            foreach ($messages as $msg) {
                $day = (new \DateTimeImmutable('@' . (int) $msg->timecreated))->setTimezone($tz)->format('Y-m-d');
                if ($day !== $lastday) {
                    $html .= \html_writer::div(self::day_label((int) $msg->timecreated), 'lh-chat__day');
                    $lastday = $day;
                }
                $msgfiles = $files[(int) $msg->id] ?? [];
                if (trim((string) $msg->message) === '' && !$msgfiles) {
                    continue;
                }
                $mine = !empty($msg->isteacher) === $vieweristeacher;
                $html .= self::render_bubble($context, $msg, $msgfiles, $mine);
            }
        }
        $html .= \html_writer::end_div();
        $html .= self::render_composer($action, $draft, $placeholder, $error);
        return $html;
    }

    /**
     * @param int $threadid
     * @return \stdClass[]
     */
    public static function messages(int $threadid): array {
        global $DB;

        self::ensure_tables();
        return $DB->get_records(self::MSG, ['threadid' => $threadid], 'timecreated ASC, id ASC');
    }

    /**
     * @param int $courseid
     * @return \stdClass[]
     */
    public static function course_threads(int $courseid): array {
        global $DB;

        self::ensure_tables();
        return $DB->get_records_select(
            self::THREAD,
            'courseid = ? AND lastmessage IS NOT NULL AND lastmessage <> ?',
            [$courseid, ''],
            'unreadteacher DESC, timemodified DESC'
        );
    }

    /**
     * @param \stdClass $thread
     * @param bool $asteacher
     */
    public static function mark_read(\stdClass $thread, bool $asteacher): void {
        global $DB;

        if ($asteacher && (int) $thread->unreadteacher === 1) {
            $thread->unreadteacher = 0;
            $DB->update_record(self::THREAD, $thread);
        } else if (!$asteacher && (int) $thread->unreadstudent === 1) {
            $thread->unreadstudent = 0;
            $DB->update_record(self::THREAD, $thread);
        }
    }

    /**
     * @param int $courseid
     * @return int
     */
    public static function unread_teacher_count(int $courseid): int {
        global $DB;

        if ($courseid <= 0) {
            return 0;
        }
        try {
            self::ensure_tables();
            return (int) $DB->count_records(self::THREAD, ['courseid' => $courseid, 'unreadteacher' => 1]);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Unread teacher questions across all given courses.
     *
     * @param \stdClass[] $courses
     * @return int
     */
    public static function unread_teacher_total(array $courses): int {
        $n = 0;
        foreach ($courses as $course) {
            $n += self::unread_teacher_count((int) $course->id);
        }
        return $n;
    }

    /**
     * @param int $courseid
     * @param int $userid
     * @return int 0|1
     */
    public static function unread_student(int $courseid, int $userid): int {
        global $DB;

        if ($courseid <= 0 || $userid <= 0) {
            return 0;
        }
        try {
            self::ensure_tables();
            $thread = $DB->get_record(self::THREAD, ['courseid' => $courseid, 'userid' => $userid]);
            return $thread ? (int) $thread->unreadstudent : 0;
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * @param \context $context
     * @return array<int, \stored_file[]>
     */
    private static function files_by_message(\context $context): array {
        $fs = get_file_storage();
        $grouped = [];
        $files = $fs->get_area_files($context->id, self::COMPONENT, self::FILEAREA, false, 'itemid, filename', false);
        foreach ($files as $file) {
            $grouped[(int) $file->get_itemid()][] = $file;
        }
        return $grouped;
    }

    /**
     * @param \context_course $context
     * @param \stdClass $msg
     * @param \stored_file[] $files
     * @param bool $mine
     * @return string
     */
    private static function render_bubble(\context_course $context, \stdClass $msg, array $files, bool $mine): string {
        $class = 'lh-chat__msg ' . ($mine ? 'is-mine' : 'is-theirs');
        $html = \html_writer::start_div($class);
        $html .= \html_writer::start_div('lh-chat__bubble');
        if (trim((string) $msg->message) !== '') {
            $html .= \html_writer::tag('p', s($msg->message));
        }
        usort($files, static function (\stored_file $a, \stored_file $b): int {
            $rank = static function (\stored_file $file): int {
                $ext = strtolower(pathinfo($file->get_filename(), PATHINFO_EXTENSION));
                return in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true) ? 0 : 1;
            };
            $diff = $rank($a) <=> $rank($b);
            return $diff !== 0 ? $diff : strcmp($a->get_filename(), $b->get_filename());
        });
        foreach ($files as $file) {
            $html .= self::render_file($context, (int) $msg->id, $file);
        }
        $html .= \html_writer::tag('time', userdate((int) $msg->timecreated, '%H:%M'), [
            'datetime' => date('c', (int) $msg->timecreated),
        ]);
        $html .= \html_writer::end_div();
        $html .= \html_writer::end_div();
        return $html;
    }

    /**
     * @param \context_course $context
     * @param int $messageid
     * @param \stored_file $file
     * @return string
     */
    private static function render_file(\context_course $context, int $messageid, \stored_file $file): string {
        $name = $file->get_filename();
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $isimage = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true);
        $url = \moodle_url::make_pluginfile_url(
            $context->id,
            self::COMPONENT,
            self::FILEAREA,
            $messageid,
            '/',
            $name,
            !$isimage
        )->out(false);

        if ($isimage) {
            $img = \html_writer::empty_tag('img', [
                'src' => $url,
                'alt' => $name,
                'loading' => 'lazy',
            ]);
            return \html_writer::link($url, $img, [
                'class' => 'lh-chat__photo',
                'target' => '_blank',
                'rel' => 'noopener',
            ]);
        }

        $label = \core_text::strtoupper($ext !== '' ? $ext : 'FILE');
        if (\core_text::strlen($label) > 4) {
            $label = \core_text::substr($label, 0, 4);
        }
        $inner = \html_writer::span($label, 'lh-chat__file-ext');
        $inner .= \html_writer::start_span('lh-chat__file-meta');
        $inner .= \html_writer::tag('b', s($name));
        $inner .= \html_writer::tag('small', self::format_size((int) $file->get_filesize()));
        $inner .= \html_writer::end_span();
        return \html_writer::link($url, $inner, ['class' => 'lh-chat__file']);
    }

    /**
     * @param \moodle_url $action
     * @param string $draft
     * @param string $placeholder
     * @param string $error
     * @return string
     */
    private static function render_composer(\moodle_url $action, string $draft, string $placeholder, string $error): string {
        $max = self::max_bytes();
        $accept = '.' . implode(',.', self::FILEEXT);
        $html = \html_writer::start_tag('form', [
            'method' => 'post',
            'enctype' => 'multipart/form-data',
            'class' => 'lh-chat__compose',
            'action' => $action->out(false),
            'data-max-bytes' => (string) $max,
            'data-max-files' => (string) self::MAXFILES,
        ]);
        $html .= \html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        if ($error !== '') {
            $html .= \html_writer::div(s($error), 'lh-chat__warn', ['role' => 'alert']);
        } else {
            $html .= \html_writer::div('', 'lh-chat__warn', ['role' => 'alert', 'hidden' => 'hidden']);
        }
        $html .= \html_writer::div('', 'lh-chat__picks', ['hidden' => 'hidden']);
        $html .= \html_writer::start_div('lh-chat__bar');
        $html .= \html_writer::start_tag('label', ['class' => 'lh-chat__clip']);
        $html .= \html_writer::tag('span', 'Прикрепить файл', ['class' => 'visually-hidden']);
        $html .= \html_writer::empty_tag('input', [
            'type' => 'file',
            'name' => 'files[]',
            'multiple' => 'multiple',
            'accept' => $accept,
        ]);
        $html .= self::icon_clip();
        $html .= \html_writer::end_tag('label');
        $html .= \html_writer::tag('label', 'Сообщение', ['class' => 'visually-hidden', 'for' => 'lh-chat-text']);
        $html .= \html_writer::tag('textarea', s($draft), [
            'id' => 'lh-chat-text',
            'name' => 'message',
            'rows' => 1,
            'maxlength' => (string) self::MAXLEN,
            'placeholder' => $placeholder,
        ]);
        $html .= \html_writer::tag('button', self::icon_send() . \html_writer::tag('span', 'Отправить', ['class' => 'visually-hidden']), [
            'type' => 'submit',
            'class' => 'lh-chat__send',
        ]);
        $html .= \html_writer::end_div();
        $html .= \html_writer::tag(
            'p',
            'Enter — отправить, Shift+Enter — новая строка. До ' . self::MAXFILES . ' файлов, каждый до ' . self::format_size($max) . '.',
            ['class' => 'lh-chat__hint']
        );
        $html .= \html_writer::end_tag('form');
        $src = (new \moodle_url('/theme/learnhub/javascript/curator.js', ['v' => 2026101002]))->out(false);
        $html .= \html_writer::tag('script', '', ['src' => $src]);
        return $html;
    }

    /**
     * @param int $ts
     * @return string
     */
    private static function day_label(int $ts): string {
        $tz = \core_date::get_user_timezone_object();
        $day = (new \DateTimeImmutable('@' . $ts))->setTimezone($tz)->format('Y-m-d');
        $today = (new \DateTimeImmutable('now', $tz))->format('Y-m-d');
        $yesterday = (new \DateTimeImmutable('yesterday', $tz))->format('Y-m-d');
        if ($day === $today) {
            return 'Сегодня';
        }
        if ($day === $yesterday) {
            return 'Вчера';
        }
        return userdate($ts, '%d %B');
    }

    /**
     * @param string[] $used
     * @param string $name
     * @return string
     */
    private static function unique_filename(array $used, string $name): string {
        $candidate = $name;
        $n = 2;
        while (in_array($candidate, $used, true)) {
            $dot = strrpos($name, '.');
            if ($dot) {
                $candidate = substr($name, 0, $dot) . '_' . $n . substr($name, $dot);
            } else {
                $candidate = $name . '_' . $n;
            }
            $n++;
        }
        return $candidate;
    }

    /**
     * @param string $name
     * @return bool
     */
    private static function has_dangerous_part(string $name): bool {
        $parts = explode('.', strtolower($name));
        if (count($parts) < 2) {
            return true;
        }
        array_pop($parts);
        foreach ($parts as $part) {
            if (in_array($part, self::DANGEROUS, true)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param string $path
     * @return bool
     */
    private static function mime_is_dangerous(string $path): bool {
        if (!class_exists('finfo')) {
            return false;
        }
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($path);
        if (!is_string($mime) || $mime === '') {
            return false;
        }
        return in_array($mime, [
            'text/html',
            'application/xhtml+xml',
            'image/svg+xml',
            'application/javascript',
            'text/javascript',
            'application/x-php',
            'application/x-httpd-php',
        ], true);
    }

    /**
     * @param string $val
     * @return int
     */
    private static function ini_bytes(string $val): int {
        $val = trim($val);
        if ($val === '') {
            return 0;
        }
        $unit = strtolower(substr($val, -1));
        $num = (float) $val;
        if ($unit === 'g') {
            return (int) ($num * 1073741824);
        }
        if ($unit === 'm') {
            return (int) ($num * 1048576);
        }
        if ($unit === 'k') {
            return (int) ($num * 1024);
        }
        return (int) $num;
    }

    /**
     * @return string
     */
    private static function icon_clip(): string {
        return '<svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true">'
            . '<path fill="currentColor" d="M16.5 6.75v8.7a4.25 4.25 0 0 1-8.5 0V7.1a2.85 2.85 0 0 1 5.7 0v7.7a1.45 1.45 0 0 1-2.9 0V8.2H9.3v6.6a2.95 2.95 0 0 0 5.9 0V7.1a4.35 4.35 0 0 0-8.7 0v8.35a5.75 5.75 0 0 0 11.5 0v-8.7h-1.5z"/>'
            . '</svg>';
    }

    /**
     * @return string
     */
    private static function icon_send(): string {
        return '<svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true">'
            . '<path fill="currentColor" d="M3.2 11.4 19.6 4.2c.7-.3 1.4.4 1.1 1.1l-7.2 15.4c-.3.7-1.3.8-1.7.1l-2.8-4.6-4.6-2.8c-.7-.4-.6-1.4.1-1.6l-.3-.4z"/>'
            . '</svg>';
    }
}
