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
    public static function add_message(\stdClass $thread, int $userid, string $message, bool $fromteacher): \stdClass {
        global $DB;

        $message = trim($message);
        if ($message === '') {
            throw new \moodle_exception('invalidparameter');
        }
        if (\core_text::strlen($message) > self::MAXLEN) {
            $message = \core_text::substr($message, 0, self::MAXLEN);
        }

        self::ensure_tables();
        $now = time();
        $DB->insert_record(self::MSG, (object) [
            'threadid' => (int) $thread->id,
            'userid' => $userid,
            'isteacher' => $fromteacher ? 1 : 0,
            'message' => $message,
            'timecreated' => $now,
        ]);

        $preview = \core_text::strlen($message) > 140 ? \core_text::substr($message, 0, 140) . '…' : $message;
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
        return $thread;
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
}
