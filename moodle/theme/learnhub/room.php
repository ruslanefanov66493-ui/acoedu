<?php
/**
 * Opens the local video room for one scheduled meeting.
 *
 * The room name is derived on the server and is not shown in the meeting list.
 * A student can enter only while the meeting is live; a teacher can enter until it ends.
 *
 * @package theme_learnhub
 */

require(__DIR__ . '/../../config.php');

require_login();
if (isguestuser()) {
    redirect(new moodle_url('/login/index.php'));
}

$id = required_param('id', PARAM_INT);
$cm = get_coursemodule_from_id('bigbluebuttonbn', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
require_login($course, false, $cm);

$modinfo = get_fast_modinfo($course);
$cminfo = $modinfo->get_cm($cm->id);
$coursectx = context_course::instance($course->id);
$manage = has_capability('moodle/course:manageactivities', $coursectx);
if (!$manage && !$cminfo->uservisible) {
    throw new moodle_exception('nopermissions', 'error', '', 'view');
}

$instance = $DB->get_record('bigbluebuttonbn', ['id' => $cm->instance], '*', MUST_EXIST);
$state = \theme_learnhub\meetings::state((int) $instance->openingtime, (int) $instance->closingtime);
$allowed = \theme_learnhub\meetings::use_local_room() && ($manage ? $state !== 'done' : $state === 'live');
$back = $manage
    ? new moodle_url('/theme/learnhub/teacher/meetings.php')
    : new moodle_url('/theme/learnhub/meetings.php');

$PAGE->set_url(new moodle_url('/theme/learnhub/room.php', ['id' => $cm->id]));
$PAGE->set_context($coursectx);
$PAGE->set_pagelayout('standard');
$PAGE->set_title('Онлайн-встреча');
$PAGE->set_heading($course->fullname);
$PAGE->add_body_class('lh-meetings');

echo $OUTPUT->header();
echo html_writer::start_div('lh-teach');

if (!$allowed) {
    if ($state === 'done') {
        $text = 'Эта встреча уже завершена.';
    } else if ($state === 'soon') {
        $text = 'Комната откроется в ' . userdate((int) $instance->openingtime, '%H:%M') . '.';
    } else {
        $text = 'Видеосервер на этом компьютере ещё не запущен.';
    }
    echo html_writer::tag('h1', 'Онлайн-встреча');
    echo html_writer::tag('p', $text, ['class' => 'lh-teach__lead']);
    echo html_writer::link($back, 'К списку встреч', ['class' => 'lh-teach__cta']);
    echo html_writer::end_div();
    echo $OUTPUT->footer();
    exit;
}

$url = \theme_learnhub\meetings::local_room_url($cm->id, fullname($USER));
echo html_writer::tag('h1', format_string($instance->name, true, ['context' => $coursectx]));
echo html_writer::tag('p', 'Открываем комнату на этом компьютере. Браузер спросит разрешение на камеру и микрофон.', ['class' => 'lh-teach__lead']);
echo html_writer::link($url, 'Войти в комнату', ['class' => 'lh-teach__cta']);
echo html_writer::script('location.replace(' . json_encode($url) . ');');
echo html_writer::end_div();
echo $OUTPUT->footer();
