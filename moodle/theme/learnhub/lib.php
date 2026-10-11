<?php
/**
 * LearnHub theme lib – SCSS callbacks.
 *
 * @package theme_learnhub
 */

defined( 'MOODLE_INTERNAL' ) || die();

/**
 * Returns the main SCSS content for the theme.
 *
 * @param theme_config $theme The theme object.
 * @return string SCSS content.
 */
function theme_learnhub_get_main_scss_content( $theme ) {
    global $CFG;
    // Load Boost base SCSS first, then append LearnHub overrides so our rules win.
    $scss = '';
    if ( function_exists( 'theme_boost_get_main_scss_content' ) ) {
        $scss .= theme_boost_get_main_scss_content( $theme );
    }

    $learnhubscss = file_get_contents( $CFG->dirroot . '/theme/learnhub/scss/learnhub.scss' );
    if ( $learnhubscss !== false ) {
        $scss .= "\n" . $learnhubscss;
    }

    // Allow admin-injected post-SCSS if present.
    if ( ! empty( $theme->settings->scss ) ) {
        $scss .= "\n" . $theme->settings->scss;
    }

    return $scss;
}

/**
 * Returns pre-SCSS variables injected before the main SCSS.
 *
 * @param theme_config $theme The theme object.
 * @return string SCSS variables.
 */
function theme_learnhub_get_pre_scss( $theme ) {
    // Start with Boost pre-SCSS variables/callback output.
    $scss = function_exists( 'theme_boost_get_pre_scss' ) ? theme_boost_get_pre_scss( $theme ) : '';
    $settings = $theme->settings;

    $brand  = ! empty( $settings->brandcolor ) ? $settings->brandcolor : '#4F46E5';
    $scss  .= '$brand-primary: ' . $brand . ";\n";
    $scss  .= '$font-family-sans-serif: "Inter", "Segoe UI", Roboto, Arial, sans-serif;' . "\n";
    $scss  .= '$font-size-base: 1rem;' . "\n";
    $scss  .= '$body-bg: #F9FAFB;' . "\n";

    return $scss;
}

/**
 * Strip extra user-menu items and send roles to the right cabinet.
 *
 * @param \moodle_page $page
 */
function theme_learnhub_page_init($page) {
    global $CFG;

    if (!isloggedin() || isguestuser()) {
        return;
    }
    if ((defined('AJAX_SCRIPT') && AJAX_SCRIPT) || (defined('CLI_SCRIPT') && CLI_SCRIPT)) {
        return;
    }

    $kind = \theme_learnhub\roles::kind();
    if ($kind === 'student' || $kind === 'teacher') {
        $CFG->customusermenuitems = '';
    }

    $pagetype = (string) $page->pagetype;
    if (($kind === 'student' || $kind === 'teacher') && $pagetype === 'user-preferences') {
        redirect(new moodle_url('/theme/learnhub/account.php'));
    }
    if ($kind === 'teacher' && in_array($page->pagelayout, ['mydashboard', 'mycourses', 'frontpage'], true)) {
        redirect(new moodle_url('/theme/learnhub/teacher/index.php'));
    }
    if ($kind === 'student' && $page->pagelayout === 'frontpage') {
        redirect(new moodle_url('/my/'));
    }
    if ($kind === 'admin' && in_array($page->pagelayout, ['mydashboard', 'mycourses', 'frontpage'], true)) {
        redirect(new moodle_url('/theme/learnhub/builder/index.php'));
    }
    if ($kind === 'admin'
        && preg_match('/^course-view/', $pagetype)
        && empty(optional_param('lhpreview', 0, PARAM_BOOL))
        && !empty($page->course->id)
        && (int) $page->course->id !== (int) SITEID
    ) {
        redirect(new moodle_url('/theme/learnhub/builder/index.php', ['id' => $page->course->id]));
    }
}

/**
 * Serve a file from a curator conversation.
 *
 * @param stdClass $course
 * @param stdClass $cm
 * @param context $context
 * @param string $filearea
 * @param array $args
 * @param bool $forcedownload
 * @param array $options
 * @return bool
 */
function theme_learnhub_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    global $USER;

    if ($context->contextlevel !== CONTEXT_COURSE || $filearea !== \theme_learnhub\curator::FILEAREA) {
        return false;
    }

    require_login($course, false);
    if (isguestuser()) {
        return false;
    }

    $itemid = (int) array_shift($args);
    $filename = array_pop($args);
    if (!$itemid || !is_string($filename) || $filename === '') {
        return false;
    }
    $filepath = $args ? '/' . implode('/', $args) . '/' : '/';
    if ($filepath !== '/') {
        return false;
    }
    if (!\theme_learnhub\curator::user_can_see_file((int) $course->id, $itemid, (int) $USER->id)) {
        return false;
    }

    $fs = get_file_storage();
    $file = $fs->get_file($context->id, \theme_learnhub\curator::COMPONENT, $filearea, $itemid, '/', $filename);
    if (!$file || $file->is_directory()) {
        return false;
    }

    send_stored_file($file, 0, 0, $forcedownload, $options);
}
