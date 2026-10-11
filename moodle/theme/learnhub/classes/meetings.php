<?php
namespace theme_learnhub;

defined('MOODLE_INTERNAL') || die();

/**
 * Scheduled online meetings for the teacher cabinet and the student list.
 *
 * A meeting is a BigBlueButton activity in section 0, so it stays out of the
 * course programme and the sequential unlock chain. Only invited students
 * (plus the course teachers) can see it.
 *
 * @package theme_learnhub
 */
class meetings {

    /** Activity idnumber for meetings created from the teacher cabinet. */
    public const LIVE = 'live-meeting';

    /** Activity idnumber for meetings an administrator holds with teachers. */
    public const STAFF = 'staff-meeting';

    /** Hidden course that holds administrator–teacher meetings. */
    public const STAFF_COURSE = 'lh-staff';

    /**
     * @return bool
     */
    public static function server_ready(): bool {
        if (self::bbb_configured()) {
            return true;
        }
        return self::use_local_room();
    }

    /**
     * Local video room on this machine, used until a dedicated BBB server exists.
     *
     * @return bool
     */
    public static function use_local_room(): bool {
        if (self::bbb_configured()) {
            return false;
        }
        return self::local_base() !== '' && self::local_secret() !== '';
    }

    /**
     * The plugin stores the server address as a site setting, not a plugin setting.
     *
     * @return bool
     */
    private static function bbb_configured(): bool {
        return trim((string) \mod_bigbluebuttonbn\local\config::get('server_url')) !== '';
    }

    /**
     * @return string
     */
    public static function local_note(): string {
        if (!self::use_local_room()) {
            return '';
        }
        return 'Комната открывается на видеосервере на этом компьютере. На учебный сайт она не выходит.';
    }

    /**
     * @return string
     */
    public static function local_base(): string {
        $url = rtrim(trim((string) get_config('theme_learnhub', 'meet_base')), '/');
        if (!preg_match('#^https?://(127\.0\.0\.1|localhost)(:\d+)?$#', $url)) {
            return '';
        }
        return $url;
    }

    /**
     * @param int $cmid
     * @param string $displayname
     * @return string
     */
    public static function local_room_url(int $cmid, string $displayname): string {
        $room = 'aco' . substr(hash_hmac('sha256', (string) $cmid, self::local_secret()), 0, 20);
        $hash = 'userInfo.displayName=' . rawurlencode(json_encode($displayname, JSON_UNESCAPED_UNICODE))
            . '&config.prejoinConfig.enabled=true'
            . '&config.disableDeepLinking=true';
        return self::local_base() . '/' . $room . '#' . $hash;
    }

    /**
     * @return string
     */
    private static function local_secret(): string {
        $secret = trim((string) get_config('theme_learnhub', 'meet_secret'));
        if (!preg_match('/^[a-f0-9]{32,}$/', $secret)) {
            return '';
        }
        return $secret;
    }

    /**
     * @return array<int,string>
     */
    public static function duration_options(): array {
        return [
            30 => '30 минут',
            45 => '45 минут',
            60 => '1 час',
            90 => '1 час 30 минут',
            120 => '2 часа',
        ];
    }

    /**
     * @param \stdClass $course
     * @return \stdClass[]
     */
    public static function students($course): array {
        global $CFG;
        require_once($CFG->dirroot . '/theme/learnhub/teacher/lib.php');
        return theme_learnhub_course_students($course);
    }

    /**
     * @param string $raw YYYY-MM-DDTHH:MM in the user's timezone
     * @return int
     */
    public static function parse_input(string $raw): int {
        $raw = trim($raw);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $raw)) {
            return 0;
        }
        try {
            $tz = \core_date::get_user_timezone_object();
            $dt = new \DateTimeImmutable(str_replace('T', ' ', $raw) . ':00', $tz);
            return $dt->getTimestamp();
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * @param int $ts
     * @return string
     */
    public static function input_value(int $ts): string {
        if ($ts <= 0) {
            return '';
        }
        return self::user_dt($ts)->format('Y-m-d\TH:i');
    }

    /**
     * Next full hour in the user's timezone.
     *
     * @return int
     */
    public static function default_opening(): int {
        $tz = \core_date::get_user_timezone_object();
        $dt = new \DateTimeImmutable('now', $tz);
        $dt = $dt->setTime((int) $dt->format('G'), 0)->modify('+1 hour');
        return $dt->getTimestamp();
    }

    /**
     * @param \stdClass $course
     * @param int $cmid
     * @return \cm_info
     */
    public static function require_live($course, int $cmid): \cm_info {
        return self::require_kind($course, $cmid, self::LIVE);
    }

    /**
     * @param \stdClass $course
     * @param int $cmid
     * @return \cm_info
     */
    public static function require_staff($course, int $cmid): \cm_info {
        return self::require_kind($course, $cmid, self::STAFF);
    }

    /**
     * Hidden course for administrator–teacher meetings. Created on the first admin visit.
     *
     * @param bool $create
     * @return \stdClass|null
     */
    public static function staff_course(bool $create = false): ?\stdClass {
        global $DB, $CFG;

        $course = $DB->get_record('course', ['shortname' => self::STAFF_COURSE]);
        if ($course || !$create) {
            return $course ?: null;
        }

        require_once($CFG->dirroot . '/course/lib.php');
        require_once($CFG->dirroot . '/theme/learnhub/builder/lib.php');
        $course = create_course((object) [
            'fullname' => 'Служебные встречи',
            'shortname' => self::STAFF_COURSE,
            'category' => theme_learnhub_builder_default_category(),
            'visible' => 0,
            'format' => 'topics',
            'summary' => 'Встречи администратора с преподавателями. В программах курсов не показывается.',
            'summaryformat' => FORMAT_HTML,
            'newsitems' => 0,
            'startdate' => time(),
            'enablecompletion' => 0,
        ]);

        $plugin = enrol_get_plugin('manual');
        if ($plugin && !self::manual_enrol_instance($course)) {
            $plugin->add_instance($course);
        }

        $roleid = (int) $DB->get_field('role', 'id', ['shortname' => 'student']);
        if ($roleid) {
            assign_capability(
                'moodle/course:viewhiddencourses',
                CAP_ALLOW,
                $roleid,
                \context_course::instance($course->id)->id,
                true
            );
        }
        return get_course($course->id);
    }

    /**
     * Teachers an administrator can invite. Site admins are the hosts, not the guests.
     *
     * @return \stdClass[] keyed by user id
     */
    public static function staff_teachers(): array {
        global $DB;

        $roleids = $DB->get_fieldset_select('role', 'id', "shortname IN ('editingteacher', 'teacher')");
        if (!$roleids) {
            return [];
        }
        list($insql, $params) = $DB->get_in_or_equal($roleids, SQL_PARAMS_NAMED);
        $users = $DB->get_records_sql(
            "SELECT DISTINCT u.id, u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic,
                    u.middlename, u.alternatename, u.email
               FROM {user} u
               JOIN {role_assignments} ra ON ra.userid = u.id
              WHERE ra.roleid {$insql}
                AND u.deleted = 0
                AND u.suspended = 0
           ORDER BY u.lastname, u.firstname, u.id",
            $params
        );
        $out = [];
        foreach ($users as $user) {
            if (is_siteadmin($user->id)) {
                continue;
            }
            $out[(int) $user->id] = $user;
        }
        return $out;
    }

    /**
     * @param int $userid
     * @param bool $onlymember Hide meetings this person was not invited to.
     * @return array
     */
    public static function staff_rows(int $userid, bool $onlymember): array {
        $course = self::staff_course(false);
        if (!$course) {
            return [];
        }
        return self::rows([$course], $userid, $onlymember, true, true);
    }

    /**
     * @param int $userid
     * @param bool $onlymember
     * @return int
     */
    public static function staff_upcoming_count(int $userid, bool $onlymember): int {
        $n = 0;
        foreach (self::staff_rows($userid, $onlymember) as $row) {
            if ($row['state'] !== 'done') {
                $n++;
            }
        }
        return $n;
    }

    /**
     * @param string $name
     * @param string $introplain
     * @param int $opening
     * @param int $closing
     * @param int[] $teacherids
     * @param bool $notify
     * @param \stdClass $actor
     * @param int $cmid 0 to create
     * @return array{cmid:int,sent:int,asked:int}
     */
    public static function save_staff(string $name, string $introplain, int $opening, int $closing, array $teacherids, bool $notify, $actor, int $cmid = 0): array {
        global $DB, $CFG;

        if (!is_siteadmin($actor->id)) {
            throw new \moodle_exception('nopermissions', 'error', '', 'admin');
        }
        self::ensure_libs();
        $course = self::staff_course(true);
        $context = \context_course::instance($course->id);
        require_capability('moodle/course:manageactivities', $context);

        $allowed = self::staff_teachers();
        $picked = [];
        foreach ($teacherids as $tid) {
            $tid = (int) $tid;
            if (isset($allowed[$tid])) {
                $picked[$tid] = $allowed[$tid];
            }
        }
        if ($name === '' || $opening <= 0 || $closing <= $opening || !$picked) {
            throw new \moodle_exception('invalidparameter');
        }

        $introhtml = $introplain === '' ? '' : '<p>' . nl2br(s($introplain)) . '</p>';
        if ($cmid) {
            $cm = self::require_staff($course, $cmid);
            $DB->update_record('bigbluebuttonbn', (object) [
                'id' => $cm->instance,
                'name' => $name,
                'intro' => $introhtml,
                'introformat' => FORMAT_HTML,
                'openingtime' => $opening,
                'closingtime' => $closing,
                'wait' => 1,
                'guestallowed' => 0,
                'timemodified' => time(),
            ]);
            set_coursemodule_name($cm->id, $name);
            set_coursemodule_idnumber($cm->id, self::STAFF);
            $instanceid = (int) $cm->instance;
        } else {
            $created = theme_learnhub_builder_add_meeting($course, 0, $name, $introhtml, $opening, $closing);
            $cmid = (int) $created->coursemodule;
            $instanceid = (int) $created->instance;
            $DB->set_field('course_modules', 'completion', COMPLETION_TRACKING_NONE, ['id' => $cmid]);
            $DB->set_field('course_modules', 'completionview', 0, ['id' => $cmid]);
            set_coursemodule_idnumber($cmid, self::STAFF);
        }

        self::enrol_staff(array_keys($picked));
        $groupid = self::sync_audience($course, $cmid, array_keys($picked));
        self::prune_staff_enrolments();
        self::bind_calendar($instanceid, $groupid, $closing - $opening);

        $sent = 0;
        if ($notify) {
            $url = new \moodle_url('/theme/learnhub/teacher/meetings.php', [], 'm' . $cmid);
            $sent = self::notify(array_values($picked), $course, $name, $opening, $closing, $introplain, $url, $actor, true);
        }

        return [
            'cmid' => $cmid,
            'sent' => $sent,
            'asked' => $notify ? count($picked) : 0,
        ];
    }

    /**
     * @param int $cmid
     */
    public static function cancel_staff(int $cmid): void {
        $course = self::staff_course(false);
        if (!$course) {
            throw new \moodle_exception('invalidcoursemodule');
        }
        $cm = self::require_staff($course, $cmid);
        require_capability('moodle/course:manageactivities', \context_module::instance($cm->id));
        course_delete_module($cm->id);
        self::delete_audience((int) $course->id, $cmid);
        self::prune_staff_enrolments();
    }

    /**
     * User ids invited to a cabinet meeting, teachers excluded.
     *
     * @param \stdClass $course
     * @param int $cmid
     * @return int[]
     */
    public static function invited_ids($course, int $cmid): array {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/group/lib.php');
        $group = $DB->get_record('groups', [
            'courseid' => $course->id,
            'idnumber' => self::group_idnumber($cmid),
        ]);
        if (!$group) {
            return [];
        }
        $teachers = self::teacher_ids($course);
        $ids = [];
        foreach (groups_get_members($group->id, 'u.id') as $member) {
            $uid = (int) $member->id;
            if (!isset($teachers[$uid])) {
                $ids[] = $uid;
            }
        }
        return $ids;
    }

    /**
     * @param \stdClass $course
     * @param int $cmid 0 to create
     * @param string $name
     * @param string $introplain
     * @param int $opening
     * @param int $closing
     * @param int[] $studentids
     * @param bool $notify
     * @param \stdClass $actor
     * @return array{cmid:int,sent:int,asked:int}
     */
    public static function save($course, int $cmid, string $name, string $introplain, int $opening, int $closing, array $studentids, bool $notify, $actor): array {
        global $DB, $CFG;

        self::ensure_libs();
        $context = \context_course::instance($course->id);
        require_capability('moodle/course:manageactivities', $context);

        $name = trim($name);
        if ($name === '' || $opening <= 0 || $closing <= $opening) {
            throw new \moodle_exception('invalidparameter');
        }

        $allowed = [];
        foreach (self::students($course) as $student) {
            $allowed[(int) $student->id] = $student;
        }
        $picked = [];
        foreach ($studentids as $sid) {
            $sid = (int) $sid;
            if (isset($allowed[$sid])) {
                $picked[$sid] = $allowed[$sid];
            }
        }
        if (!$picked) {
            throw new \moodle_exception('invalidparameter');
        }

        $introhtml = $introplain === '' ? '' : '<p>' . nl2br(s($introplain)) . '</p>';
        if ($cmid) {
            $cm = self::require_live($course, $cmid);
            $DB->update_record('bigbluebuttonbn', (object) [
                'id' => $cm->instance,
                'name' => $name,
                'intro' => $introhtml,
                'introformat' => FORMAT_HTML,
                'openingtime' => $opening,
                'closingtime' => $closing,
                'wait' => 1,
                'guestallowed' => 0,
                'timemodified' => time(),
            ]);
            set_coursemodule_name($cm->id, $name);
            set_coursemodule_idnumber($cm->id, self::LIVE);
            $record = $DB->get_record('bigbluebuttonbn', ['id' => $cm->instance], '*', MUST_EXIST);
            $record->coursemodule = $cm->id;
            $record->course = $course->id;
            \mod_bigbluebuttonbn\local\helpers\mod_helper::process_post_save($record);
            $cmid = (int) $cm->id;
            $instanceid = (int) $cm->instance;
        } else {
            if (!$DB->get_field('modules', 'id', ['name' => 'bigbluebuttonbn', 'visible' => 1])) {
                throw new \moodle_exception('moduledisable', 'error', '', 'bigbluebuttonbn');
            }
            $created = theme_learnhub_builder_add_meeting($course, 0, $name, $introhtml, $opening, $closing);
            $cmid = (int) $created->coursemodule;
            $instanceid = (int) $created->instance;
            $DB->set_field('course_modules', 'completion', COMPLETION_TRACKING_NONE, ['id' => $cmid]);
            $DB->set_field('course_modules', 'completionview', 0, ['id' => $cmid]);
            set_coursemodule_idnumber($cmid, self::LIVE);
        }

        $groupid = self::sync_audience($course, $cmid, array_keys($picked));
        self::bind_calendar($instanceid, $groupid, $closing - $opening);

        $sent = 0;
        if ($notify) {
            $url = new \moodle_url('/theme/learnhub/meetings.php', [], 'm' . $cmid);
            $sent = self::notify(array_values($picked), $course, $name, $opening, $closing, $introplain, $url, $actor);
        }

        return [
            'cmid' => $cmid,
            'sent' => $sent,
            'asked' => $notify ? count($picked) : 0,
        ];
    }

    /**
     * @param \stdClass $course
     * @param int $cmid
     */
    public static function cancel($course, int $cmid): void {
        $cm = self::require_live($course, $cmid);
        require_capability('moodle/course:manageactivities', \context_module::instance($cm->id));
        course_delete_module($cm->id);
        self::delete_audience((int) $course->id, $cmid);
    }

    /**
     * Make sure the current teacher can open the room.
     *
     * @param \stdClass[] $courses
     * @param int $userid
     */
    public static function ensure_host(array $courses, int $userid): void {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/group/lib.php');

        foreach ($courses as $course) {
            $modinfo = get_fast_modinfo($course->id);
            foreach ($modinfo->get_cms() as $cm) {
                if ($cm->deletioninprogress || (string) $cm->idnumber !== self::LIVE) {
                    continue;
                }
                $group = $DB->get_record('groups', [
                    'courseid' => $course->id,
                    'idnumber' => self::group_idnumber((int) $cm->id),
                ]);
                if ($group && !groups_is_member($group->id, $userid)) {
                    groups_add_member($group->id, $userid);
                }
            }
        }
    }

    /**
     * @param \stdClass[] $courses
     * @param int $userid
     * @param bool $onlyvisible
     * @param bool $withaudience
     * @param bool $allowhidden Include the hidden staff course.
     * @return array
     */
    public static function rows(array $courses, int $userid, bool $onlyvisible, bool $withaudience = false, bool $allowhidden = false): array {
        global $DB;

        $rows = [];
        foreach ($courses as $course) {
            if ((int) $course->id === (int) SITEID) {
                continue;
            }
            if (empty($course->visible) && !$allowhidden) {
                continue;
            }
            if ((string) $course->shortname === self::STAFF_COURSE && !$allowhidden) {
                continue;
            }
            $instances = $DB->get_records('bigbluebuttonbn', ['course' => $course->id]);
            if (!$instances) {
                continue;
            }
            $modinfo = get_fast_modinfo($course->id, $userid);
            $coursectx = \context_course::instance($course->id);
            $coursename = format_string($course->fullname, true, ['context' => $coursectx, 'escape' => false]);
            foreach ($modinfo->get_cms() as $cm) {
                if ($cm->deletioninprogress || $cm->modname !== 'bigbluebuttonbn') {
                    continue;
                }
                if ($onlyvisible && !$cm->uservisible) {
                    continue;
                }
                if (!isset($instances[$cm->instance])) {
                    continue;
                }
                $instance = $instances[$cm->instance];
                $opening = (int) $instance->openingtime;
                $closing = (int) $instance->closingtime;
                $state = self::state($opening, $closing);
                $intro = trim(html_to_text($instance->intro ?? '', 0));
                if (\core_text::strlen($intro) > 180) {
                    $intro = \core_text::substr($intro, 0, 177) . '…';
                }
                $isstaff = (string) $cm->idnumber === self::STAFF;
                $editable = $isstaff ? is_siteadmin($userid) : (string) $cm->idnumber === self::LIVE;
                $audience = '';
                if ($withaudience) {
                    if ($isstaff) {
                        $audience = self::audience_label($course, (int) $cm->id, true);
                    } else {
                        $audience = $editable ? self::audience_label($course, (int) $cm->id) : 'весь курс';
                    }
                }
                $rows[] = [
                    'cmid' => (int) $cm->id,
                    'name' => format_string($instance->name, true, ['context' => $coursectx, 'escape' => false]),
                    'course' => $isstaff ? 'Администрация' : $coursename,
                    'courseid' => (int) $course->id,
                    'intro' => $intro,
                    'opening' => $opening,
                    'closing' => $closing,
                    'state' => $state,
                    'editable' => $editable,
                    'audience' => $audience,
                    'day' => $opening > 0 ? self::day_label($opening) : 'Без даты',
                    'daykey' => $opening > 0 ? self::user_dt($opening)->format('Y-m-d') : 'none',
                    'start' => $opening > 0 ? self::user_dt($opening)->format('H:i') : '—',
                    'end' => $closing > $opening ? self::user_dt($closing)->format('H:i') : '',
                    'joinurl' => self::join_url((int) $course->id, (int) $cm->id),
                    'editurl' => $isstaff
                        ? new \moodle_url('/theme/learnhub/staff/meeting.php', ['cmid' => $cm->id])
                        : new \moodle_url('/theme/learnhub/teacher/meeting.php', [
                            'id' => $course->id,
                            'cmid' => $cm->id,
                        ]),
                ];
            }
        }

        usort($rows, function (array $a, array $b): int {
            $ao = $a['opening'] > 0 ? $a['opening'] : PHP_INT_MAX;
            $bo = $b['opening'] > 0 ? $b['opening'] : PHP_INT_MAX;
            return $ao <=> $bo;
        });
        return $rows;
    }

    /**
     * @param \stdClass[] $courses
     * @param int $userid
     * @param bool $onlyvisible
     * @return int
     */
    public static function upcoming_count(array $courses, int $userid, bool $onlyvisible): int {
        $n = 0;
        foreach (self::rows($courses, $userid, $onlyvisible) as $row) {
            if ($row['state'] !== 'done') {
                $n++;
            }
        }
        return $n;
    }

    /**
     * @param array $rows
     * @param bool $manage
     * @return string
     */
    public static function render_board(array $rows, bool $manage): string {
        $upcoming = [];
        $past = [];
        foreach ($rows as $row) {
            if ($row['state'] === 'done') {
                $past[] = $row;
            } else {
                $upcoming[] = $row;
            }
        }
        $html = '';
        $html .= self::render_slice($upcoming, $manage, '');
        if ($past) {
            $html .= \html_writer::tag('h2', 'Прошедшие', ['class' => 'lh-teach__h2']);
            $html .= self::render_slice($past, $manage, '');
        }
        return $html;
    }

    /**
     * @param string $notice
     * @param int $sent
     * @param int $asked
     * @return string
     */
    public static function notice_text(string $notice, int $sent, int $asked): string {
        if ($notice === 'cancelled') {
            return 'Встреча отменена. Она пропала из списка студентов.';
        }
        if ($notice !== 'created' && $notice !== 'updated') {
            return '';
        }
        $text = $notice === 'created' ? 'Встреча запланирована.' : 'Встреча обновлена.';
        if ($asked > 0) {
            $text .= $sent > 0
                ? ' Приглашения отправлены: ' . $sent . ' из ' . $asked . '.'
                : ' Приглашения пока не ушли на почту — встреча в списке студента уже есть.';
        }
        return $text;
    }

    /**
     * @param int $opening
     * @param int $closing
     * @return string soon|live|done
     */
    public static function state(int $opening, int $closing): string {
        $now = time();
        if ($opening <= 0) {
            return 'soon';
        }
        if ($closing > 0 && $now > $closing) {
            return 'done';
        }
        $from = $opening - 900;
        if ($now >= $from && ($closing <= 0 || $now <= $closing)) {
            return 'live';
        }
        return 'soon';
    }

    /**
     * @param int $cmid
     * @return string
     */
    private static function group_idnumber(int $cmid): string {
        return 'lhmeet-' . $cmid;
    }

    /**
     * Cabinet join link. BigBlueButton opens in this tab, in the invited group,
     * so the teacher and the students enter the same room.
     *
     * @param int $courseid
     * @param int $cmid
     * @return \moodle_url
     */
    private static function join_url(int $courseid, int $cmid): \moodle_url {
        if (self::use_local_room()) {
            return new \moodle_url('/theme/learnhub/room.php', ['id' => $cmid]);
        }
        $params = ['action' => 'join', 'id' => $cmid];
        $groupid = self::meeting_group_id($courseid, $cmid);
        if ($groupid > 0) {
            $params['group'] = $groupid;
        }
        return new \moodle_url('/mod/bigbluebuttonbn/bbb_view.php', $params);
    }

    /**
     * @param int $courseid
     * @param int $cmid
     * @return int
     */
    private static function meeting_group_id(int $courseid, int $cmid): int {
        $idnumber = self::group_idnumber($cmid);
        foreach (groups_get_all_groups($courseid) as $group) {
            if ((string) $group->idnumber === $idnumber) {
                return (int) $group->id;
            }
        }
        return 0;
    }

    /**
     * @param int $ts
     * @return \DateTimeImmutable
     */
    private static function user_dt(int $ts): \DateTimeImmutable {
        $tz = \core_date::get_user_timezone_object();
        return (new \DateTimeImmutable('@' . $ts))->setTimezone($tz);
    }

    /**
     * @param int $ts
     * @return string
     */
    private static function day_label(int $ts): string {
        $dt = self::user_dt($ts);
        $weekdays = ['воскресенье', 'понедельник', 'вторник', 'среда', 'четверг', 'пятница', 'суббота'];
        $months = [
            1 => 'января', 'февраля', 'марта', 'апреля', 'мая', 'июня',
            'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря',
        ];
        $w = (int) $dt->format('w');
        $n = (int) $dt->format('n');
        return (int) $dt->format('j') . ' ' . $months[$n] . ', ' . $weekdays[$w];
    }

    /**
     * @param \stdClass $course
     * @return array<int,bool>
     */
    private static function teacher_ids($course): array {
        $users = get_enrolled_users(\context_course::instance($course->id), 'mod/assign:grade', 0, 'u.id');
        $ids = [];
        foreach ($users as $user) {
            $ids[(int) $user->id] = true;
        }
        return $ids;
    }

    /**
     * @param \stdClass $course
     * @param int $cmid
     * @param int[] $studentids
     * @return int
     */
    private static function sync_audience($course, int $cmid, array $studentids): int {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/group/lib.php');

        $idnumber = self::group_idnumber($cmid);
        $group = $DB->get_record('groups', ['courseid' => $course->id, 'idnumber' => $idnumber]);
        if (!$group) {
            $groupid = groups_create_group((object) [
                'courseid' => $course->id,
                'name' => 'Встреча ' . $cmid,
                'idnumber' => $idnumber,
                'description' => 'Участники онлайн-встречи',
                'descriptionformat' => FORMAT_HTML,
                'visibility' => GROUPS_VISIBILITY_ALL,
                'participation' => 1,
                'enablemessaging' => 0,
            ]);
        } else {
            $groupid = (int) $group->id;
        }

        $grouping = $DB->get_record('groupings', ['courseid' => $course->id, 'idnumber' => $idnumber]);
        if (!$grouping) {
            $groupingid = groups_create_grouping((object) [
                'courseid' => $course->id,
                'name' => 'Встреча ' . $cmid,
                'idnumber' => $idnumber,
                'description' => '',
                'descriptionformat' => FORMAT_HTML,
            ]);
        } else {
            $groupingid = (int) $grouping->id;
        }
        groups_assign_grouping($groupingid, $groupid);

        $wanted = [];
        foreach ($studentids as $sid) {
            $wanted[(int) $sid] = true;
        }
        foreach (self::teacher_ids($course) as $tid => $unused) {
            $wanted[$tid] = true;
        }

        $current = groups_get_members($groupid, 'u.id');
        foreach ($current as $member) {
            if (!isset($wanted[(int) $member->id])) {
                groups_remove_member($groupid, (int) $member->id);
            }
        }
        foreach (array_keys($wanted) as $uid) {
            if (!isset($current[$uid])) {
                groups_add_member($groupid, $uid);
            }
        }

        $availability = json_encode([
            'op' => '&',
            'c' => [['type' => 'group', 'id' => (int) $groupid]],
            'showc' => [false],
        ]);
        $DB->set_field('course_modules', 'availability', $availability, ['id' => $cmid]);
        $DB->set_field('course_modules', 'groupingid', $groupingid, ['id' => $cmid]);
        $DB->set_field('course_modules', 'groupmode', SEPARATEGROUPS, ['id' => $cmid]);
        rebuild_course_cache($course->id, true);
        return (int) $groupid;
    }

    /**
     * @param int $courseid
     * @param int $cmid
     */
    private static function delete_audience(int $courseid, int $cmid): void {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/group/lib.php');

        $idnumber = self::group_idnumber($cmid);
        $group = $DB->get_record('groups', ['courseid' => $courseid, 'idnumber' => $idnumber]);
        $grouping = $DB->get_record('groupings', ['courseid' => $courseid, 'idnumber' => $idnumber]);
        if ($group && $grouping) {
            groups_unassign_grouping($grouping->id, $group->id);
        }
        if ($group) {
            groups_delete_group($group->id);
        }
        if ($grouping) {
            groups_delete_grouping($grouping->id);
        }
    }

    /**
     * @param \stdClass $course
     * @param int $cmid
     * @param bool $staff
     * @return string
     */
    private static function audience_label($course, int $cmid, bool $staff = false): string {
        $ids = self::invited_ids($course, $cmid);
        $n = count($ids);
        if ($staff) {
            return $n . ' ' . self::people_word($n, 'преподаватель', 'преподавателя', 'преподавателей');
        }
        return $n . ' ' . self::students_word($n);
    }

    /**
     * @param int $n
     * @return string
     */
    private static function students_word(int $n): string {
        return self::people_word($n, 'студент', 'студента', 'студентов');
    }

    /**
     * @param int $n
     * @param string $one
     * @param string $few
     * @param string $many
     * @return string
     */
    private static function people_word(int $n, string $one, string $few, string $many): string {
        $n100 = $n % 100;
        $n10 = $n % 10;
        if ($n100 > 10 && $n100 < 20) {
            return $many;
        }
        if ($n10 === 1) {
            return $one;
        }
        if ($n10 >= 2 && $n10 <= 4) {
            return $few;
        }
        return $many;
    }

    /**
     * @param int $instanceid
     * @param int $groupid
     * @param int $duration
     */
    private static function bind_calendar(int $instanceid, int $groupid, int $duration): void {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/calendar/lib.php');

        $eventid = $DB->get_field('event', 'id', [
            'modulename' => 'bigbluebuttonbn',
            'instance' => $instanceid,
            'eventtype' => 'meeting_start',
        ]);
        if (!$eventid) {
            return;
        }
        try {
            $event = \calendar_event::load($eventid);
            $event->update((object) [
                'groupid' => $groupid,
                'timeduration' => max(0, $duration),
            ], false);
        } catch (\Exception $e) {
            // The meeting list does not depend on the calendar entry.
        }
    }

    /**
     * @param \stdClass[] $users
     * @param \stdClass $course
     * @param string $name
     * @param int $opening
     * @param int $closing
     * @param string $introplain
     * @param \moodle_url $url
     * @param \stdClass $from
     * @param bool $staff Administrator writing to teachers.
     * @return int
     */
    private static function notify(array $users, $course, string $name, int $opening, int $closing, string $introplain, \moodle_url $url, $from, bool $staff = false): int {
        global $CFG;
        require_once($CFG->libdir . '/messagelib.php');

        $when = self::day_label($opening) . ', ' . self::user_dt($opening)->format('H:i');
        if ($closing > $opening) {
            $when .= '–' . self::user_dt($closing)->format('H:i');
        }
        $coursename = format_string($course->fullname, true, [
            'context' => \context_course::instance($course->id),
            'escape' => false,
        ]);
        $sent = 0;
        foreach ($users as $user) {
            $lead = $staff
                ? 'Администратор назначил встречу для преподавателей.'
                : "Преподаватель назначил онлайн-встречу по курсу «{$coursename}».";
            $text = "Здравствуйте, " . fullname($user) . "!\n\n"
                . $lead . "\n\n"
                . $name . "\n"
                . $when . "\n"
                . ($introplain !== '' ? "\n" . $introplain . "\n" : '')
                . "\nОткройте встречу в кабинете. Ссылку переслать нельзя — вход только из вашего аккаунта:\n"
                . $url->out(false) . "\n";
            $ok = false;
            try {
                $ok = (bool) email_to_user($user, $from, 'Онлайн-встреча: ' . $name, $text);
            } catch (\Exception $e) {
                $ok = false;
            }
            if (!$ok) {
                try {
                    message_post_message($from, $user, $text, FORMAT_PLAIN);
                    $ok = true;
                } catch (\Exception $e) {
                    $ok = false;
                }
            }
            if ($ok) {
                $sent++;
            }
        }
        return $sent;
    }

    /**
     * @param array $rows
     * @param bool $manage
     * @param string $heading
     * @return string
     */
    private static function render_slice(array $rows, bool $manage, string $heading): string {
        if (!$rows) {
            return '';
        }
        $html = $heading !== '' ? \html_writer::tag('h2', $heading, ['class' => 'lh-teach__h2']) : '';
        $days = [];
        foreach ($rows as $row) {
            $days[$row['daykey']]['label'] = $row['day'];
            $days[$row['daykey']]['rows'][] = $row;
        }
        foreach ($days as $day) {
            $html .= \html_writer::tag('h3', s($day['label']), ['class' => 'lh-meet__day']);
            foreach ($day['rows'] as $row) {
                $html .= self::render_card($row, $manage);
            }
        }
        return $html;
    }

    /**
     * @param array $row
     * @param bool $manage
     * @return string
     */
    private static function render_card(array $row, bool $manage): string {
        $class = 'lh-meet__card';
        if ($row['state'] === 'live') {
            $class .= ' is-live';
        } else if ($row['state'] === 'done') {
            $class .= ' is-done';
        }
        $badges = [
            'live' => ['Сейчас', 'live'],
            'soon' => ['Скоро', 'wait'],
            'done' => ['Завершена', 'none'],
        ];
        $badge = $badges[$row['state']] ?? $badges['soon'];

        $meta = \html_writer::span($badge[0], 'lh-teach__badge lh-teach__badge--' . $badge[1]);
        $meta .= ' ' . s($row['course']);
        if ($manage && $row['audience'] !== '') {
            $meta .= ' · ' . s($row['audience']);
        }
        if ($row['end'] !== '') {
            $meta .= ' · до ' . s($row['end']);
        }

        $body = \html_writer::tag('h2', s($row['name']));
        $body .= \html_writer::tag('div', $meta, ['class' => 'lh-meet__meta']);
        if ($row['intro'] !== '') {
            $body .= \html_writer::tag('p', s($row['intro']), ['class' => 'lh-meet__intro']);
        }

        $canjoin = $manage ? $row['state'] !== 'done' : $row['state'] === 'live';
        $actions = '';
        if ($canjoin) {
            $actions .= \html_writer::link($row['joinurl'], 'Подключиться', ['class' => 'lh-teach__cta']);
        } else if ($row['state'] === 'soon') {
            $actions .= \html_writer::span('Откроется в ' . $row['start'], 'lh-meet__wait');
        }
        if ($manage && $row['editable'] && $row['state'] !== 'done') {
            $actions .= \html_writer::link($row['editurl'], 'Изменить', ['class' => 'lh-teach__link']);
        }

        return \html_writer::start_div($class, ['id' => 'm' . $row['cmid']])
            . \html_writer::tag('div', s($row['start']), ['class' => 'lh-meet__time'])
            . \html_writer::tag('div', $body, ['class' => 'lh-meet__body'])
            . \html_writer::tag('div', $actions, ['class' => 'lh-meet__actions'])
            . \html_writer::end_div();
    }

    /**
     * Loads builder helpers in global scope. Call only from a method that did global $CFG.
     */
    private static function ensure_libs(): void {
        global $CFG;
        require_once($CFG->dirroot . '/course/format/aco/lib.php');
        require_once($CFG->dirroot . '/theme/learnhub/builder/lib.php');
        require_once($CFG->dirroot . '/group/lib.php');
    }

    /**
     * @param \stdClass $course
     * @param int $cmid
     * @param string $idnumber
     * @return \cm_info
     */
    private static function require_kind($course, int $cmid, string $idnumber): \cm_info {
        $cm = get_fast_modinfo($course)->get_cm($cmid);
        if ($cm->modname !== 'bigbluebuttonbn' || (string) $cm->idnumber !== $idnumber) {
            throw new \moodle_exception('invalidcoursemodule');
        }
        return $cm;
    }

    /**
     * @param \stdClass $course
     * @return \stdClass|null
     */
    private static function manual_enrol_instance($course): ?\stdClass {
        foreach (enrol_get_instances($course->id, true) as $instance) {
            if ($instance->enrol === 'manual') {
                return $instance;
            }
        }
        return null;
    }

    /**
     * Enrol invited teachers so they can enter the hidden course.
     *
     * @param int[] $userids
     */
    private static function enrol_staff(array $userids): void {
        global $DB, $CFG;
        require_once($CFG->libdir . '/enrollib.php');

        $course = self::staff_course(true);
        $plugin = enrol_get_plugin('manual');
        $instance = self::manual_enrol_instance($course);
        if (!$plugin || !$instance) {
            throw new \moodle_exception('invalidparameter');
        }
        $roleid = (int) $DB->get_field('role', 'id', ['shortname' => 'student'], MUST_EXIST);
        $context = \context_course::instance($course->id);
        foreach ($userids as $userid) {
            $userid = (int) $userid;
            if ($userid <= 0 || is_siteadmin($userid) || is_enrolled($context, $userid)) {
                continue;
            }
            $plugin->enrol_user($instance, $userid, $roleid);
        }
    }

    /**
     * Drop teachers who are no longer invited to any staff meeting.
     */
    private static function prune_staff_enrolments(): void {
        global $CFG;
        require_once($CFG->libdir . '/enrollib.php');
        require_once($CFG->dirroot . '/group/lib.php');

        $course = self::staff_course(false);
        if (!$course) {
            return;
        }
        $plugin = enrol_get_plugin('manual');
        $instance = self::manual_enrol_instance($course);
        if (!$plugin || !$instance) {
            return;
        }
        $context = \context_course::instance($course->id);
        foreach (get_enrolled_users($context, '', 0, 'u.id') as $user) {
            if (is_siteadmin($user->id)) {
                continue;
            }
            $keep = false;
            foreach (groups_get_all_groups($course->id, (int) $user->id) as $group) {
                if (strpos((string) $group->idnumber, 'lhmeet-') === 0) {
                    $keep = true;
                    break;
                }
            }
            if (!$keep) {
                $plugin->unenrol_user($instance, (int) $user->id);
            }
        }
    }
}
