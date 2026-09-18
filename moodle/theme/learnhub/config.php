<?php
/**
 * LearnHub theme config.
 * Extends Boost; overrides layout templates and provides custom SCSS.
 *
 * @package theme_learnhub
 */

defined( 'MOODLE_INTERNAL' ) || die();

$THEME->name    = 'learnhub';
$THEME->parents = [ 'boost' ];
$THEME->sheets  = [ 'learnhub' ];
$THEME->editor_sheets      = [];
$THEME->usefallback        = true;
$THEME->enable_dock        = false;
$THEME->prescsscallback    = 'theme_learnhub_get_pre_scss';
$THEME->scss               = function( $theme ) { return theme_learnhub_get_main_scss_content( $theme ); };
$THEME->rendererfactory    = 'theme_overridden_renderer_factory';
$THEME->requiredblocks     = [];
$THEME->addblockposition   = BLOCK_ADDBLOCK_POSITION_FLATNAV;

$THEME->layouts = [
    // drawers.php is resolved from parent theme Boost — do not point at a missing child file.
    'base'          => [ 'file' => 'drawers.php', 'regions' => [ 'side-pre' ], 'defaultregion' => 'side-pre' ],
    'standard'      => [ 'file' => 'drawers.php', 'regions' => [ 'side-pre' ], 'defaultregion' => 'side-pre' ],
    'course'        => [ 'file' => 'courseplayer.php', 'regions' => [ 'side-pre' ], 'defaultregion' => 'side-pre' ],
    'coursecategory'=> [ 'file' => 'drawers.php', 'regions' => [ 'side-pre' ], 'defaultregion' => 'side-pre' ],
    'incourse'      => [ 'file' => 'courseplayer.php', 'regions' => [ 'side-pre' ], 'defaultregion' => 'side-pre' ],
    'frontpage'     => [ 'file' => 'frontpage.php', 'regions' => [ 'side-pre' ], 'defaultregion' => 'side-pre' ],
    'admin'         => [ 'file' => 'drawers.php', 'regions' => [ 'side-pre' ], 'defaultregion' => 'side-pre' ],
    // Reverted to Boost's own drawers.php (same reliable layout course pages already use).
    // The custom mydashboard.php/mustache layout caused a null addEventListener error
    // because it stripped DOM elements some core Moodle JS (session timeout / drawer)
    // expects. Keep the files in the theme for later re-work, just don't wire them in.
    'mydashboard'   => [ 'file' => 'drawers.php', 'regions' => [ 'side-pre' ], 'defaultregion' => 'side-pre', 'options' => [ 'nonavbar' => true, 'langmenu' => true ] ],
    'mycourses'     => [ 'file' => 'drawers.php', 'regions' => [ 'side-pre' ], 'defaultregion' => 'side-pre', 'options' => [ 'nonavbar' => true ] ],
    'mypublic'      => [ 'file' => 'drawers.php', 'regions' => [ 'side-pre' ], 'defaultregion' => 'side-pre' ],
    'login'         => [ 'file' => 'login.php',    'regions' => [] ],
    'popup'         => [ 'file' => 'columns1.php', 'regions' => [] ],
    'frametop'      => [ 'file' => 'columns1.php', 'regions' => [] ],
    'embedded'      => [ 'file' => 'embedded.php', 'regions' => [] ],
    'maintenance'   => [ 'file' => 'maintenance.php', 'regions' => [] ],
    'print'         => [ 'file' => 'columns1.php', 'regions' => [] ],
    'redirect'      => [ 'file' => 'embedded.php', 'regions' => [] ],
    'report'        => [ 'file' => 'drawers.php', 'regions' => [ 'side-pre' ], 'defaultregion' => 'side-pre' ],
    // Skillbox-like programme editor for site admins (no Boost drawers).
    'builder'       => [ 'file' => 'builder.php', 'regions' => [], 'options' => [ 'nofooter' => true, 'nonavbar' => true, 'nocontextheader' => true, 'noactivityheader' => true ] ],
];
