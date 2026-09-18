<?php
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/format/topics/lib.php');

/**
 * ACO programme format: same editing UX as Topics, student player in the theme.
 *
 * @package format_aco
 */
class format_aco extends format_topics {

    public function get_default_section_name($section) {
        $section = $this->get_section($section);
        if ((int) $section->sectionnum === 0) {
            return get_string('section0name', 'format_aco');
        }
        return get_string('newsection', 'format_aco');
    }

    public function course_format_options($foreditform = false) {
        $options = parent::course_format_options($foreditform);

        $options['aco_sequential'] = [
            'default' => 1,
            'type' => PARAM_INT,
        ];
        $options['aco_certificate'] = [
            'default' => 1,
            'type' => PARAM_INT,
        ];

        if ($foreditform) {
            $options['aco_sequential'] += [
                'label' => get_string('sequential', 'format_aco'),
                'help' => 'sequential',
                'help_component' => 'format_aco',
                'element_type' => 'selectyesno',
            ];
            $options['aco_certificate'] += [
                'label' => get_string('certificate', 'format_aco'),
                'help' => 'certificate',
                'help_component' => 'format_aco',
                'element_type' => 'selectyesno',
            ];
        }

        return $options;
    }

    public function update_course_format_options($data, $oldcourse = null) {
        $changed = parent::update_course_format_options($data, $oldcourse);
        $data = (array) $data;
        if (!empty($data['aco_sequential'])) {
            format_aco_rebuild_sequence($this->courseid);
        }
        return $changed;
    }
}

/**
 * Chain availability: each activity unlocks after the previous one is complete.
 *
 * @param int $courseid
 */
function format_aco_rebuild_sequence(int $courseid): void {
    global $DB;

    static $running = false;
    if ($running) {
        return;
    }

    $course = get_course($courseid);
    $format = course_get_format($course);
    if ($format->get_format() !== 'aco') {
        return;
    }
    $opts = $format->get_format_options();
    if (empty($opts['aco_sequential'])) {
        return;
    }

    $running = true;
    $modinfo = get_fast_modinfo($courseid);
    $prevcmid = 0;

    foreach ($modinfo->get_section_info_all() as $section) {
        if ((int) $section->section === 0 || empty($section->visible)) {
            continue;
        }
        $cms = $modinfo->get_cms();
        foreach ($cms as $cm) {
            if ((int) $cm->sectionnum !== (int) $section->section) {
                continue;
            }
            if ($cm->deletioninprogress || $cm->modname === 'label') {
                continue;
            }
            $availability = null;
            if ($prevcmid) {
                $availability = json_encode([
                    'op' => '&',
                    'c' => [['type' => 'completion', 'cm' => (int) $prevcmid, 'e' => COMPLETION_COMPLETE]],
                    'showc' => [true],
                ]);
            }
            $DB->set_field('course_modules', 'availability', $availability, ['id' => $cm->id]);
            $prevcmid = (int) $cm->id;
        }
    }

    rebuild_course_cache($courseid, true);
    $running = false;
}
