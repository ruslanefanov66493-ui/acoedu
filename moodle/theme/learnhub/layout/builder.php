<?php
/**
 * Programme builder chrome: no Boost navbar, drawers, or empty header pits.
 *
 * @package theme_learnhub
 */

defined('MOODLE_INTERNAL') || die();

$bodyattributes = $OUTPUT->body_attributes(['lh-builder']);
?>
<?php echo $OUTPUT->doctype(); ?>
<html <?php echo $OUTPUT->htmlattributes(); ?>>
<head>
    <title><?php echo $OUTPUT->page_title(); ?></title>
    <link rel="shortcut icon" href="<?php echo $OUTPUT->favicon(); ?>" />
    <link rel="apple-touch-icon" href="<?php echo $OUTPUT->image_url('favicon', 'theme'); ?>" />
    <?php echo $OUTPUT->standard_head_html(); ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body <?php echo $bodyattributes; ?>>
<?php echo $OUTPUT->render_from_template('core/local/toast/wrapper', []); ?>
<?php echo $OUTPUT->standard_top_of_body_html(); ?>
<div id="page-wrapper" class="d-print-block lh-build-shell">
    <div id="page">
        <div id="page-content">
            <div id="region-main-box">
                <section id="region-main" aria-label="<?php echo get_string('content'); ?>">
                    <?php echo $OUTPUT->main_content(); ?>
                </section>
            </div>
        </div>
    </div>
</div>
<?php echo $OUTPUT->standard_end_of_body_html(); ?>
</body>
</html>
