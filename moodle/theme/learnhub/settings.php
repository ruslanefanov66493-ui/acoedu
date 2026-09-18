<?php
/**
 * LearnHub admin settings.
 *
 * @package theme_learnhub
 */

defined( 'MOODLE_INTERNAL' ) || die();

if ( $ADMIN->fulltree ) {
    $settings = new theme_boost_admin_settingspage_tabs( 'theme_learnhub', get_string( 'configtitle', 'theme_learnhub' ) );

    $page = new admin_settingpage( 'theme_learnhub_general', get_string( 'generalsettings', 'theme_learnhub' ) );

    // Brand colour.
    $name        = 'theme_learnhub/brandcolor';
    $title       = get_string( 'brandcolor', 'theme_learnhub' );
    $description = get_string( 'brandcolor_desc', 'theme_learnhub' );
    $default     = '#4F46E5';
    $setting     = new admin_setting_configcolourpicker( $name, $title, $description, $default );
    $setting->set_updatedcallback( 'theme_reset_all_caches' );
    $page->add( $setting );

    // Custom SCSS.
    $name        = 'theme_learnhub/scsspre';
    $title       = get_string( 'rawscsspre', 'theme_boost' );
    $description = get_string( 'rawscsspre_desc', 'theme_boost' );
    $default     = '';
    $setting     = new admin_setting_scsscode( $name, $title, $description, $default, PARAM_RAW );
    $setting->set_updatedcallback( 'theme_reset_all_caches' );
    $page->add( $setting );

    $settings->add( $page );
}
