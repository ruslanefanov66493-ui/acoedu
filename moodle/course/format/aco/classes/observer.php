<?php
namespace format_aco;

defined('MOODLE_INTERNAL') || die();

/**
 * Rebuild sequential unlocks when the customer edits the programme.
 *
 * @package format_aco
 */
class observer {

    /**
     * @param \core\event\base $event
     */
    public static function module_changed(\core\event\base $event): void {
        global $CFG;
        require_once($CFG->dirroot . '/course/format/aco/lib.php');

        $courseid = (int) $event->courseid;
        if ($courseid <= 1) {
            return;
        }
        $course = get_course($courseid);
        if ($course->format !== 'aco') {
            return;
        }
        format_aco_rebuild_sequence($courseid);
    }
}
