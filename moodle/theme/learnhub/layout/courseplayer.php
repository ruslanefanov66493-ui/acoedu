<?php
/**
 * Student course player. Editors keep Boost drawers so they can assemble the programme.
 *
 * Moodle requires the rendered layout to contain the main_content() placeholder token.
 * Never buffer-and-discard that call.
 *
 * @package theme_learnhub
 */

defined('MOODLE_INTERNAL') || die();

global $CFG, $COURSE, $PAGE, $OUTPUT, $SITE;

$preview = optional_param('lhpreview', 0, PARAM_BOOL);
$iseditor = is_siteadmin() && !$preview;

if ($iseditor || !isloggedin() || isguestuser() || empty($COURSE) || (int) $COURSE->id === (int) SITEID) {
    require($CFG->dirroot . '/theme/boost/layout/drawers.php');
    return;
}

$cm = $PAGE->cm ?? null;
if ((!$cm || empty($cm->id)) && $PAGE->context && (int) $PAGE->context->contextlevel === CONTEXT_MODULE) {
    try {
        $cm = get_fast_modinfo($COURSE)->get_cm((int) $PAGE->context->instanceid);
    } catch (Exception $e) {
        $cm = null;
    }
}
$path = $PAGE->url ? (string) $PAGE->url->get_path() : '';
$oncurator = strpos($path, '/theme/learnhub/curator.php') !== false;
$player = new \theme_learnhub\course_player($COURSE, $oncurator ? null : $cm);
$mode = (!$oncurator && $cm) ? 'activity' : 'home';

// Boost drawers.php loads this; the custom player layout must too, or the
// account dropdown (data-toggle="dropdown") never opens.
$PAGE->requires->js_amd_inline(
    "M.util.js_pending('theme_boost/loader');" .
    "require(['theme_boost/loader'], function() { M.util.js_complete('theme_boost/loader'); });"
);

$primary = new \core\navigation\output\primary($PAGE);
$renderer = $PAGE->get_renderer('core');
$primarymenu = $primary->export_for_template($renderer);
$navcontext = [
    'output' => $OUTPUT,
    'usermenu' => $primarymenu['user'],
    'langmenu' => $primarymenu['lang'] ?? false,
    'mobileprimarynav' => $primarymenu['mobileprimarynav'] ?? [],
    'sitename' => format_string($SITE->shortname, true, ['context' => context_course::instance(SITEID), 'escape' => false]),
];

echo $OUTPUT->doctype();
?>
<html <?php echo $OUTPUT->htmlattributes(); ?>>
<head>
    <title><?php echo $OUTPUT->page_title(); ?></title>
    <link rel="shortcut icon" href="<?php echo $OUTPUT->favicon(); ?>" />
    <link rel="apple-touch-icon" href="<?php echo $OUTPUT->image_url('favicon', 'theme'); ?>" />
    <?php echo $OUTPUT->standard_head_html(); ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body <?php echo $OUTPUT->body_attributes(['lh-player']); ?>>
<?php echo $OUTPUT->render_from_template('core/local/toast/wrapper', []); ?>
<?php echo $OUTPUT->standard_top_of_body_html(); ?>
<?php echo $OUTPUT->render_from_template('theme_boost/navbar', $navcontext); ?>
<div id="page-wrapper" class="aco-page-wrapper">
    <div id="page">
        <div id="topofscroll" class="main-inner">
            <div id="page-content" class="pb-4">
                <div id="region-main-box">
                    <section id="region-main" aria-label="<?php echo get_string('content'); ?>">
                        <?php
                        echo $player->render_shell_start($mode);
                        if ($oncurator) {
                            echo '<div class="aco-player__core aco-player__core--curator">';
                        } else if ($cm) {
                            echo $player->render_toolbar();
                            if ($player->content_blocked()) {
                                echo $player->render_gate();
                                echo '<div class="aco-player__moodle is-gated">';
                            } else {
                                $review = $player->render_assign_review();
                                echo $review;
                                echo '<div class="aco-player__moodle' . ($review !== '' ? ' has-review' : '') . '">';
                            }
                        } else {
                            echo $player->render_home_panel();
                            echo '<div class="aco-player__core">';
                        }
                        echo $OUTPUT->main_content();
                        echo '</div>';
                        if ($cm && !$oncurator) {
                            echo $player->render_pager();
                        }
                        echo '<footer class="lh-player-foot">';
                        echo $OUTPUT->learnhub_legal_nav();
                        echo '<p>АЦО — Академия цифрового образования</p>';
                        echo '</footer>';
                        echo $player->render_shell_end();
                        ?>
                    </section>
                </div>
            </div>
        </div>
    </div>
</div>
<?php
echo $OUTPUT->standard_end_of_body_html();
?>
</body>
</html>
