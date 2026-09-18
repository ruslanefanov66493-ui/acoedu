<?php
// This file is part of Moodle - http://moodle.org/

namespace theme_learnhub;

defined('MOODLE_INTERNAL') || die();

/**
 * Student / teacher / site-admin detection for LearnHub chrome.
 *
 * @package theme_learnhub
 */
class roles {

    /**
     * @param int|null $userid
     * @return string admin|teacher|student
     */
    public static function kind($userid = null): string {
        global $DB, $USER;

        $userid = $userid ? (int) $userid : (int) ($USER->id ?? 0);
        if (!$userid || isguestuser($userid)) {
            return 'student';
        }
        if (is_siteadmin($userid)) {
            return 'admin';
        }

        static $cache = [];
        if (isset($cache[$userid])) {
            return $cache[$userid];
        }

        $roleids = $DB->get_fieldset_select('role', 'id', "shortname IN ('editingteacher', 'teacher', 'manager')");
        if ($roleids) {
            list($insql, $params) = $DB->get_in_or_equal($roleids, SQL_PARAMS_NAMED);
            $params['userid'] = $userid;
            if ($DB->record_exists_sql("SELECT ra.id FROM {role_assignments} ra WHERE ra.userid = :userid AND ra.roleid {$insql}", $params)) {
                return $cache[$userid] = 'teacher';
            }
        }

        return $cache[$userid] = 'student';
    }

    /**
     * Isolated ACO chrome (dark header + sidebar), not full Moodle admin.
     *
     * Site admins keep native Moodle on /admin/* and the programme builder
     * (own header). Everywhere else they get the same pit-free cabinet shell.
     *
     * @return bool
     */
    public static function isolated_chrome(): bool {
        global $PAGE;

        if (!isloggedin() || isguestuser()) {
            return false;
        }

        $skiplayouts = ['login', 'maintenance', 'embedded', 'popup', 'frametop', 'print', 'redirect', 'secure'];
        if (in_array($PAGE->pagelayout, $skiplayouts, true)) {
            return false;
        }

        $kind = self::kind();
        if ($kind === 'student' || $kind === 'teacher') {
            return true;
        }
        if ($kind !== 'admin') {
            return false;
        }

        if (in_array($PAGE->pagelayout, ['builder', 'admin'], true)) {
            return false;
        }

        $path = $PAGE->url ? (string) $PAGE->url->get_path() : '';
        if (strpos($path, '/admin/') !== false) {
            return false;
        }

        return true;
    }

    /**
     * Courses where the user can grade assignments.
     *
     * @param int|null $userid
     * @return \stdClass[]
     */
    public static function teacher_courses($userid = null): array {
        global $USER;

        $userid = $userid ? (int) $userid : (int) $USER->id;
        $out = [];
        foreach (enrol_get_all_users_courses($userid, true, 'id, fullname, shortname, visible, summary') as $course) {
            if ((int) $course->id === (int) SITEID || empty($course->visible)) {
                continue;
            }
            $ctx = \context_course::instance($course->id);
            if (has_capability('mod/assign:grade', $ctx, $userid)) {
                $out[] = $course;
            }
        }
        return $out;
    }

    /**
     * Sync name/password to the linked WordPress account.
     *
     * @param \stdClass $user Moodle user
     * @param array $fields firstname, lastname, password
     */
    public static function sync_wp_account($user, array $fields): void {
        $wp = rtrim((string) get_config('auth_edwiserbridge', 'wpsiteurl'), '/');
        $secret = (string) get_config('auth_edwiserbridge', 'sharedsecret');
        if ($wp === '' || $secret === '') {
            return;
        }

        $body = [
            'secret' => $secret,
            'moodle_user_id' => (int) $user->id,
            'email' => (string) $user->email,
        ];
        if (isset($fields['firstname'])) {
            $body['firstname'] = $fields['firstname'];
        }
        if (isset($fields['lastname'])) {
            $body['lastname'] = $fields['lastname'];
        }
        if (!empty($fields['password'])) {
            $body['password'] = $fields['password'];
        }

        $curl = new \curl();
        $curl->setopt(['CURLOPT_TIMEOUT' => 8, 'CURLOPT_CONNECTTIMEOUT' => 4]);
        $curl->post($wp . '/wp-json/aco/v1/moodle-account', $body);
    }
}
