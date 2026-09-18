<?php
/**
 * JSON endpoint for the LearnHub dashboard course grid.
 *
 * @package theme_learnhub
 */

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/completionlib.php');
require_once($CFG->dirroot . '/course/lib.php');

require_login(null, false);

header('Content-Type: application/json; charset=utf-8');

if (isguestuser()) {
    echo json_encode(['courses' => []]);
    die();
}

/**
 * Russian plural helper.
 *
 * @param int    $n
 * @param string $one
 * @param string $few
 * @param string $many
 * @return string
 */
function theme_learnhub_plural($n, $one, $few, $many) {
    $n = abs((int) $n);
    $mod10 = $n % 10;
    $mod100 = $n % 100;
    if ($mod10 === 1 && $mod100 !== 11) {
        return $n . ' ' . $one;
    }
    if ($mod10 >= 2 && $mod10 <= 4 && ($mod100 < 10 || $mod100 >= 20)) {
        return $n . ' ' . $few;
    }
    return $n . ' ' . $many;
}

$courses = enrol_get_all_users_courses($USER->id, true, 'id, fullname, summary, shortname, visible');
$result = [];

foreach ($courses as $course) {
    if ((int) $course->id === (int) SITEID || empty($course->visible)) {
        continue;
    }

    $coursecontext = \context_course::instance($course->id);

    $teachername = '';
    $teachers = get_enrolled_users($coursecontext, 'moodle/course:update', 0, 'u.id, u.firstname, u.lastname', null, 0, 1);
    foreach ($teachers as $teacher) {
        $teachername = fullname($teacher);
        break;
    }

    $activitycount = 0;
    $progress = 0;
    $continueurl = (new \moodle_url('/course/view.php', ['id' => $course->id]))->out(false);
    try {
        $player = new \theme_learnhub\course_player($course);
        $pdata = $player->export('home');
        $activitycount = (int) $pdata->video_total + (int) $pdata->practice_total + (int) $pdata->test_total;
        $progress = (int) $pdata->percent;
        if (!empty($pdata->continueurl)) {
            $continueurl = $pdata->continueurl;
        }
    } catch (\Throwable $e) {
        try {
            $modinfo = get_fast_modinfo($course, $USER->id);
            foreach ($modinfo->get_cms() as $cm) {
                if ($cm->deletioninprogress || in_array($cm->modname, ['label', 'forum'], true)) {
                    continue;
                }
                $activitycount++;
            }
        } catch (\Exception $e2) {
            $activitycount = 0;
        }
        try {
            $percentage = \core_completion\progress::get_course_progress_percentage($course, $USER->id);
            $progress = $percentage !== null ? (int) round($percentage) : 0;
        } catch (\Exception $e2) {
            $progress = 0;
        }
    }

    $image = '';
    try {
        $listelement = new \core_course_list_element($course);
        foreach ($listelement->get_course_overviewfiles() as $file) {
            if ($file->is_valid_image()) {
                $image = \moodle_url::make_pluginfile_url(
                    $file->get_contextid(),
                    $file->get_component(),
                    $file->get_filearea(),
                    null,
                    $file->get_filepath(),
                    $file->get_filename()
                )->out(false);
                break;
            }
        }
    } catch (\Exception $e) {
        $image = '';
    }

    $hours = max(1, (int) round($activitycount * 0.6));

    $result[] = [
        'id'            => (int) $course->id,
        'fullname'      => format_string($course->fullname, true, ['context' => $coursecontext]),
        'teacher'       => $teachername,
        'hours'         => $hours,
        'hourslabel'    => theme_learnhub_plural($hours, 'час', 'часа', 'часов'),
        'lessons'       => $activitycount,
        'lessonslabel'  => theme_learnhub_plural($activitycount, 'урок', 'урока', 'уроков'),
        'progress'      => $progress,
        'completed'     => $progress >= 100,
        'image'         => $image,
        'url'           => $continueurl,
        'programurl'    => (new \moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
        'continueurl'   => $continueurl,
        'certificate'   => (new \moodle_url('/theme/learnhub/certificate.php', ['id' => $course->id]))->out(false),
        'curatorurl'    => (new \moodle_url('/theme/learnhub/curator.php', ['id' => $course->id]))->out(false),
    ];
}

echo json_encode(['courses' => $result]);
