<?php
/**
 * ACO LearnHub – theme functions.
 *
 * @package aco-theme
 */

defined( 'ABSPATH' ) || exit;

require_once get_template_directory() . '/inc/moodle-enrollment.php';
require_once get_template_directory() . '/inc/moodle-sso.php';
require_once get_template_directory() . '/inc/moodle-progress.php';
require_once get_template_directory() . '/inc/legal-content.php';
require_once get_template_directory() . '/inc/course-content.php';
require_once get_template_directory() . '/inc/teachers.php';
require_once get_template_directory() . '/inc/admin-panel.php';
require_once get_template_directory() . '/inc/mail.php';
require_once get_template_directory() . '/inc/phone-auth.php';

/* ── Setup ──────────────────────────────────────────────────────────── */
function aco_setup() {
    add_theme_support( 'title-tag' );
    add_theme_support( 'post-thumbnails' );
    add_theme_support( 'html5', [ 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script' ] );
    add_theme_support( 'custom-logo', [
        'height'      => 70,
        'width'       => 175,
        'flex-height' => true,
        'flex-width'  => true,
    ] );

    register_nav_menus( [
        'primary' => __( 'Primary menu', 'aco-theme' ),
        'footer'  => __( 'Footer menu',  'aco-theme' ),
    ] );

    add_theme_support( 'woocommerce' );
}
add_action( 'after_setup_theme', 'aco_setup' );

/* ── Hide WP admin bar on the frontend for everyone ─────────────────── */
add_filter( 'show_admin_bar', '__return_false' );

add_filter( 'xmlrpc_enabled', '__return_false' );

add_filter( 'wp_sitemaps_enabled', '__return_true' );

add_filter( 'rest_endpoints', function( $endpoints ) {
    if ( is_user_logged_in() ) {
        return $endpoints;
    }
    unset( $endpoints['/wp/v2/users'] );
    unset( $endpoints['/wp/v2/users/(?P<id>[\d]+)'] );
    return $endpoints;
} );

add_filter( 'robots_txt', function( $output ) {
    $lines = [
        'Disallow: /checkout/',
        'Disallow: /account/',
        'Disallow: /login/',
        'Disallow: /cart/',
        'Disallow: /author/',
        'Disallow: /wp-json/wp/v2/users',
        'Sitemap: ' . home_url( '/wp-sitemap.xml' ),
    ];
    return rtrim( $output ) . "\n" . implode( "\n", $lines ) . "\n";
}, 20 );

add_action( 'template_redirect', function() {
    if ( is_author() ) {
        wp_safe_redirect( home_url( '/' ), 301 );
        exit;
    }

    $path = rawurldecode( (string) ( $_SERVER['REQUEST_URI'] ?? '' ) );
    if ( str_contains( $path, 'backend-разработка' ) ) {
        wp_safe_redirect( home_url( '/product/backend-course/' ), 301 );
        exit;
    }
}, 0 );

/* ── Favicon: circular ACO mark (book in a ring), Google-style ──────── */
function aco_favicon_url( $size = 32 ) {
    $base = get_template_directory_uri() . '/assets';
    $ver  = '2';
    if ( (int) $size >= 180 ) {
        return $base . '/favicon-512.png?v=' . $ver;
    }
    if ( (int) $size >= 96 ) {
        return $base . '/favicon-192.png?v=' . $ver;
    }
    return $base . '/favicon-32.png?v=' . $ver;
}

add_filter( 'get_site_icon_url', static function ( $url, $size ) {
    return aco_favicon_url( $size );
}, 10, 2 );

add_action( 'do_faviconico', static function () {
    wp_redirect( get_template_directory_uri() . '/assets/favicon.ico?v=2' );
    exit;
}, 0 );

function aco_favicon_links() {
    if ( has_site_icon() ) {
        return;
    }
    $ico = get_template_directory_uri() . '/assets/favicon.ico';
    echo '<link rel="icon" href="' . esc_url( $ico ) . '" sizes="any">' . "\n";
    echo '<link rel="icon" type="image/png" sizes="32x32" href="' . esc_url( aco_favicon_url( 32 ) ) . '">' . "\n";
    echo '<link rel="icon" type="image/png" sizes="192x192" href="' . esc_url( aco_favicon_url( 192 ) ) . '">' . "\n";
    echo '<link rel="apple-touch-icon" href="' . esc_url( aco_favicon_url( 512 ) ) . '">' . "\n";
}
add_action( 'wp_head', 'aco_favicon_links', 1 );
add_action( 'admin_head', 'aco_favicon_links', 1 );
add_action( 'login_head', 'aco_favicon_links', 1 );

/* ── Course product meta (c1): duration / level / teacher ───────────── */
add_action( 'add_meta_boxes_product', function() {
    add_meta_box(
        'aco_course_details',
        'Параметры курса',
        'aco_render_course_details_box',
        'product',
        'normal',
        'high'
    );
} );

function aco_render_course_details_box( $post ) {
    wp_nonce_field( 'aco_course_details_save', 'aco_course_details_nonce' );

    $duration = get_post_meta( $post->ID, '_aco_course_duration', true );
    $level    = get_post_meta( $post->ID, '_aco_course_level', true );
    $tid      = (int) get_post_meta( $post->ID, '_aco_teacher_user_id', true );
    $video_id = aco_get_course_preview_video_id( $post->ID );
    $teachers = function_exists( 'aco_list_teachers' ) ? aco_list_teachers() : [];
    ?>
    <p>
        <label for="aco_course_duration">Длительность курса (например: 3 месяца)</label><br>
        <input type="text" id="aco_course_duration" name="aco_course_duration" value="<?php echo esc_attr( $duration ); ?>" style="width:100%;max-width:420px;">
    </p>
    <p>
        <label for="aco_course_level">Уровень (например: Junior / Middle / Senior)</label><br>
        <input type="text" id="aco_course_level" name="aco_course_level" value="<?php echo esc_attr( $level ); ?>" style="width:100%;max-width:420px;">
    </p>
    <p>
        <label for="aco_teacher_user_id">Преподаватель / куратор</label><br>
        <select id="aco_teacher_user_id" name="aco_teacher_user_id" style="width:100%;max-width:420px;">
            <option value="0">Команда АЦО</option>
            <?php foreach ( $teachers as $tuser ) : ?>
            <option value="<?php echo esc_attr( (string) $tuser->ID ); ?>" <?php selected( $tid, (int) $tuser->ID ); ?>>
                <?php echo esc_html( $tuser->display_name . ' (' . $tuser->user_email . ')' ); ?>
            </option>
            <?php endforeach; ?>
        </select>
    </p>
    <p>
        <label for="aco_preview_video_id">Превью-видео (ID вложения из Медиатеки)</label><br>
        <input type="number" id="aco_preview_video_id" name="aco_preview_video_id" value="<?php echo $video_id ? esc_attr( (string) $video_id ) : ''; ?>" min="0" step="1" style="width:100%;max-width:420px;">
    </p>
    <p>
        <label for="aco_program_text">Программа (первый ряд блока — название модуля, дальше пункты; блоки через пустую строку)</label><br>
        <textarea id="aco_program_text" name="aco_program_text" rows="8" style="width:100%;max-width:640px;"><?php echo esc_textarea( aco_program_to_textarea( aco_get_course_program( $post->ID ) ) ); ?></textarea>
    </p>
    <p>
        <label for="aco_benefits_text">Что получите (строка: Заголовок | Текст)</label><br>
        <textarea id="aco_benefits_text" name="aco_benefits_text" rows="5" style="width:100%;max-width:640px;"><?php echo esc_textarea( aco_benefits_to_textarea( aco_get_course_benefits( $post->ID ) ) ); ?></textarea>
    </p>
    <p>
        <label for="aco_faq_text">FAQ (строка: Вопрос || Ответ)</label><br>
        <textarea id="aco_faq_text" name="aco_faq_text" rows="5" style="width:100%;max-width:640px;"><?php echo esc_textarea( aco_faq_to_textarea( aco_get_course_faq( $post->ID ) ) ); ?></textarea>
    </p>
    <?php
    aco_render_moodle_course_field();
}

add_action( 'save_post_product', function( $post_id ) {
    if ( ! isset( $_POST['aco_course_details_nonce'] ) || ! wp_verify_nonce( $_POST['aco_course_details_nonce'], 'aco_course_details_save' ) ) {
        return;
    }
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }
    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }

    update_post_meta( $post_id, '_aco_course_duration', sanitize_text_field( $_POST['aco_course_duration'] ?? '' ) );
    update_post_meta( $post_id, '_aco_course_level', sanitize_text_field( $_POST['aco_course_level'] ?? '' ) );
    $teacher_id = absint( $_POST['aco_teacher_user_id'] ?? 0 );
    update_post_meta( $post_id, '_aco_teacher_user_id', $teacher_id );
    update_post_meta( $post_id, '_aco_course_teacher', $teacher_id ? aco_teacher_display_name( $post_id ) : sanitize_text_field( $_POST['aco_course_teacher'] ?? '' ) );
    aco_save_moodle_course_field( $post_id );
    aco_save_course_landing_meta(
        $post_id,
        aco_program_from_textarea( wp_unslash( $_POST['aco_program_text'] ?? '' ) ),
        aco_benefits_from_textarea( wp_unslash( $_POST['aco_benefits_text'] ?? '' ) ),
        aco_faq_from_textarea( wp_unslash( $_POST['aco_faq_text'] ?? '' ) ),
        absint( $_POST['aco_preview_video_id'] ?? 0 )
    );
} );

/* ── Enqueue ─────────────────────────────────────────────────────────── */
function aco_assets() {
    // Inter font is self-hosted via @font-face in style.css (Google Fonts CDN
    // can be slow/blocked for RU visitors), no separate enqueue needed.
    wp_enqueue_style(
        'aco-theme',
        get_stylesheet_uri(),
        [],
        wp_get_theme()->get( 'Version' ) . '.' . filemtime( get_stylesheet_directory() . '/style.css' )
    );

    if ( ! is_page_template( 'page-account.php' ) && ! is_page_template( 'page-aco-admin.php' ) ) {
        wp_enqueue_style(
            'aco-liquid-glass',
            get_template_directory_uri() . '/assets/liquid-glass.css',
            array( 'aco-theme' ),
            wp_get_theme()->get( 'Version' ) . '.' . filemtime( get_stylesheet_directory() . '/assets/liquid-glass.css' )
        );
        wp_enqueue_script(
            'aco-liquid-glass',
            get_template_directory_uri() . '/assets/liquid-glass.js',
            [],
            wp_get_theme()->get( 'Version' ) . '.' . filemtime( get_stylesheet_directory() . '/assets/liquid-glass.js' ),
            true
        );
    }

    wp_enqueue_script(
        'aco-nav',
        get_template_directory_uri() . '/assets/nav.js',
        [],
        wp_get_theme()->get( 'Version' ) . '.' . filemtime( get_stylesheet_directory() . '/assets/nav.js' ),
        true
    );

    wp_enqueue_script(
        'aco-accordion',
        get_template_directory_uri() . '/assets/accordion.js',
        [],
        wp_get_theme()->get( 'Version' ) . '.' . filemtime( get_stylesheet_directory() . '/assets/accordion.js' ),
        true
    );

    wp_enqueue_script(
        'aco-faq',
        get_template_directory_uri() . '/assets/faq.js',
        [],
        wp_get_theme()->get( 'Version' ) . '.' . filemtime( get_stylesheet_directory() . '/assets/faq.js' ),
        true
    );

    // Account dropdown — global, shown in header for logged-in users
    if ( is_user_logged_in() ) {
        wp_enqueue_script(
            'aco-account',
            get_template_directory_uri() . '/assets/account.js',
            [],
            wp_get_theme()->get( 'Version' ) . '.' . filemtime( get_stylesheet_directory() . '/assets/account.js' ),
            true
        );
    }

    // Account dashboard data rendering (Skillbox-like UX over Edwiser API)
    if ( is_page_template( 'page-account.php' ) ) {
        wp_enqueue_script(
            'aco-account-dashboard',
            get_template_directory_uri() . '/assets/account-dashboard.js',
            [],
            wp_get_theme()->get( 'Version' ) . '.' . filemtime( get_stylesheet_directory() . '/assets/account-dashboard.js' ),
            true
        );
        wp_enqueue_script(
            'aco-account-profile',
            get_template_directory_uri() . '/assets/account-profile.js',
            [],
            wp_get_theme()->get( 'Version' ) . '.' . filemtime( get_stylesheet_directory() . '/assets/account-profile.js' ),
            true
        );
    }

    if ( is_page_template( 'page-aco-admin.php' ) && aco_is_platform_admin() ) {
        wp_enqueue_media();
        wp_enqueue_style(
            'aco-admin-panel',
            get_template_directory_uri() . '/assets/admin-panel.css',
            [ 'aco-theme' ],
            wp_get_theme()->get( 'Version' ) . '.' . filemtime( get_stylesheet_directory() . '/assets/admin-panel.css' )
        );
        wp_enqueue_script(
            'aco-admin-panel',
            get_template_directory_uri() . '/assets/admin-panel.js',
            [ 'jquery' ],
            wp_get_theme()->get( 'Version' ) . '.' . filemtime( get_stylesheet_directory() . '/assets/admin-panel.js' ),
            true
        );
        wp_localize_script( 'aco-admin-panel', 'acoAdmin', [
            'ajax'   => admin_url( 'admin-ajax.php' ),
            'nonce'  => wp_create_nonce( 'aco_admin_panel' ),
            'home'   => aco_admin_home_url(),
            'moodle' => function_exists( 'aco_moodle_url' ) ? aco_moodle_url() : '',
            'sso'    => home_url( '/?aco_moodle_sso=1' ),
        ] );
    }

    if ( is_singular( 'product' ) ) {
        wp_enqueue_script(
            'aco-course-video',
            get_template_directory_uri() . '/assets/course-video.js',
            [],
            wp_get_theme()->get( 'Version' ) . '.' . filemtime( get_stylesheet_directory() . '/assets/course-video.js' ),
            true
        );
    }

    if ( is_front_page() ) {
        wp_enqueue_script(
            'aco-courses-carousel',
            get_template_directory_uri() . '/assets/courses-carousel.js',
            [],
            wp_get_theme()->get( 'Version' ) . '.' . filemtime( get_stylesheet_directory() . '/assets/courses-carousel.js' ),
            true
        );
    }

    // Auth JS — only on the auth template page
    if ( is_page_template( 'template-auth.php' ) ) {
        wp_enqueue_script(
            'aco-auth',
            get_template_directory_uri() . '/assets/auth.js',
            [],
            wp_get_theme()->get( 'Version' ) . '.' . filemtime( get_stylesheet_directory() . '/assets/auth.js' ),
            true
        );
        wp_localize_script( 'aco-auth', 'acoAuth', [
            'ajax_url'       => admin_url( 'admin-ajax.php' ),
            'email_domains'  => aco_allowed_email_domains(),
            'email_error'    => aco_public_email_error(),
        ] );
    }
}
add_action( 'wp_enqueue_scripts', 'aco_assets' );

/* ── Walker: render nav as lh-nav tabs ──────────────────────────────── */
class ACO_Nav_Walker extends Walker_Nav_Menu {
    public function start_el( &$output, $data_object, $depth = 0, $args = null, $current_object_id = 0 ) {
        $classes  = empty( $data_object->classes ) ? [] : (array) $data_object->classes;
        $active   = in_array( 'current-menu-item', $classes, true ) ? ' lh-nav__item--active' : '';
        $url      = esc_url( $data_object->url );
        $title    = esc_html( $data_object->title );

        $output .= '<li class="lh-nav__item' . $active . '">'
                 . '<a class="lh-nav__link" href="' . $url . '">' . $title . '</a>'
                 . '<span class="lh-nav__indicator" aria-hidden="true"></span>'
                 . '</li>';
    }
    public function end_el( &$output, $data_object, $depth = 0, $args = null ) {}
}

/* ── Redirect wp-login.php to custom auth page ──────────────────────── */
add_action( 'init', 'aco_redirect_login_page' );

function aco_redirect_login_page() {
    $login_page  = home_url( '/login/' );
    $page_viewed = basename( $_SERVER['REQUEST_URI'] );

    // Skip redirect for admin, AJAX, and cron
    if ( is_admin() || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) || ( defined( 'DOING_CRON' ) && DOING_CRON ) ) {
        return;
    }

    if ( $page_viewed === 'wp-login.php' || $page_viewed === 'wp-register.php' ) {
        // Registration link → tab=register
        $tab = ( isset( $_GET['action'] ) && $_GET['action'] === 'register' ) ? '?tab=register' : '';
        wp_safe_redirect( $login_page . $tab );
        exit;
    }
}

// Also intercept wp_login_url() calls throughout the site
add_filter( 'login_url', function( $url, $redirect, $reauth ) {
    return home_url( '/login/' ) . ( $redirect ? '?redirect_to=' . rawurlencode( $redirect ) : '' );
}, 10, 3 );

add_filter( 'register_url', function() {
    return home_url( '/login/?tab=register' );
} );

add_filter( 'lostpassword_url', function() {
    return home_url( '/login/?tab=recovery' );
} );

/* ── Purchase flow: only logged-in users can buy/order courses ──────── */
function aco_get_purchase_redirect_url( $target_url = '' ) {
    $login_url = home_url( '/login/' );
    if ( ! $target_url ) {
        $target_url = home_url( '/catalog/' );
    }
    return $login_url . '?redirect_to=' . rawurlencode( $target_url );
}

function aco_get_course_checkout_url( $product ) {
    if ( ! $product || ! function_exists( 'wc_get_checkout_url' ) ) {
        return '#';
    }

    return add_query_arg( 'add-to-cart', $product->get_id(), wc_get_checkout_url() );
}

/**
 * WooCommerce product IDs the user already has access to (orders + Edwiser enrolment).
 *
 * @param int $user_id WP user ID. Current user when 0.
 * @return int[]
 */
function aco_get_owned_product_ids( $user_id = 0 ) {
    static $cache = [];

    $user_id = $user_id ? (int) $user_id : get_current_user_id();
    if ( $user_id < 1 ) {
        return [];
    }
    if ( isset( $cache[ $user_id ] ) ) {
        return $cache[ $user_id ];
    }

    $ids = [];

    if ( function_exists( 'wc_get_orders' ) ) {
        $orders = wc_get_orders( [
            'customer_id' => $user_id,
            'status'      => [ 'processing', 'completed' ],
            'limit'       => -1,
            'return'      => 'objects',
        ] );
        foreach ( $orders as $order ) {
            foreach ( $order->get_items() as $item ) {
                $pid = (int) $item->get_product_id();
                if ( $pid ) {
                    $ids[] = $pid;
                }
            }
        }
    }

    $eb_fn = 'app\\wisdmlabs\\edwiserBridge\\eb_get_user_enrolled_courses';
    $eb_ids = function_exists( $eb_fn ) ? (array) $eb_fn( $user_id ) : [];

    $moodle_ids = [];
    foreach ( $eb_ids as $eb_id ) {
        $mdl = (int) get_post_meta( (int) $eb_id, 'moodle_course_id', true );
        if ( $mdl ) {
            $moodle_ids[] = $mdl;
        }
    }
    $moodle_ids = array_values( array_unique( $moodle_ids ) );

    if ( $moodle_ids ) {
        $linked = get_posts( [
            'post_type'              => 'product',
            'post_status'            => [ 'publish', 'private' ],
            'posts_per_page'         => -1,
            'fields'                 => 'ids',
            'no_found_rows'          => true,
            'update_post_term_cache' => false,
            'meta_query'             => [[
                'key'     => '_aco_moodle_course_id',
                'value'   => $moodle_ids,
                'compare' => 'IN',
                'type'    => 'NUMERIC',
            ]],
        ] );
        foreach ( $linked as $pid ) {
            $ids[] = (int) $pid;
        }
    }

    $ids = array_values( array_unique( array_filter( array_map( 'intval', $ids ) ) ) );
    $cache[ $user_id ] = $ids;
    return $ids;
}

function aco_user_owns_product( $product_id, $user_id = 0 ) {
    $product_id = (int) $product_id;
    if ( $product_id < 1 ) {
        return false;
    }
    return in_array( $product_id, aco_get_owned_product_ids( $user_id ), true );
}

function aco_get_course_learn_url( $product ) {
    $fallback = home_url( '/account/#courses' );
    if ( ! $product ) {
        return $fallback;
    }

    $mdl_id     = (int) get_post_meta( $product->get_id(), '_aco_moodle_course_id', true );
    $moodle_url = function_exists( 'aco_moodle_url' ) ? aco_moodle_url() : '';
    $moodle_uid = (int) get_user_meta( get_current_user_id(), 'moodle_user_id', true );

    if ( $mdl_id && $moodle_url && $moodle_uid ) {
        $course_url = $moodle_url . '/course/view.php?id=' . $mdl_id;
        return home_url( '/?aco_moodle_sso=1' ) . '&redirect=' . rawurlencode( $course_url );
    }

    return $fallback;
}

function aco_user_owns_current_product( $product ) {
    return $product && is_user_logged_in() && aco_user_owns_product( $product->get_id() );
}

/**
 * Products for the homepage “Популярные курсы” carousel.
 * Featured first, then remaining catalog items by sales.
 *
 * @return WC_Product[]
 */
function aco_get_homepage_courses( $limit = 12 ) {
    if ( ! function_exists( 'wc_get_products' ) ) {
        return [];
    }

    $limit    = max( 1, (int) $limit );
    $owned    = aco_get_owned_product_ids();
    $featured = wc_get_products( [
        'status'     => 'publish',
        'limit'      => $limit,
        'featured'   => true,
        'exclude'    => $owned,
        'visibility' => 'catalog',
        'orderby'    => 'date',
        'order'      => 'DESC',
        'return'     => 'objects',
    ] );

    if ( count( $featured ) >= $limit ) {
        return $featured;
    }

    $exclude = $owned;
    foreach ( $featured as $product ) {
        $exclude[] = $product->get_id();
    }

    $rest = wc_get_products( [
        'status'     => 'publish',
        'limit'      => $limit - count( $featured ),
        'exclude'    => $exclude,
        'visibility' => 'catalog',
        'orderby'    => 'popularity',
        'order'      => 'DESC',
        'return'     => 'objects',
    ] );

    return array_merge( $featured, $rest );
}

/**
 * WooCommerce always keeps a default product_cat (Uncategorized / Mist).
 * It is not a real course direction — hide it from the panel, homepage and catalog.
 *
 * @param WP_Term|int|null $term
 */
function aco_is_hidden_direction( $term ) {
    if ( is_numeric( $term ) ) {
        $term = get_term( (int) $term, 'product_cat' );
    }
    if ( ! $term instanceof WP_Term ) {
        return true;
    }
    if ( (int) $term->term_id === (int) get_option( 'default_product_cat' ) ) {
        return true;
    }
    return in_array( $term->slug, [ 'uncategorized', 'misc', 'mist', 'bez-rubriki' ], true );
}

/**
 * Directions for the homepage grid and catalog filters.
 * Same WooCommerce product categories the admin panel writes.
 * Empty categories stay visible so a newly created section appears immediately.
 *
 * @return WP_Term[]
 */
function aco_get_course_directions() {
    if ( ! taxonomy_exists( 'product_cat' ) ) {
        return [];
    }

    $terms = get_terms( [
        'taxonomy'   => 'product_cat',
        'hide_empty' => false,
        'orderby'    => 'name',
        'order'      => 'ASC',
    ] );

    if ( is_wp_error( $terms ) || empty( $terms ) ) {
        return [];
    }

    $out = [];

    foreach ( $terms as $term ) {
        if ( aco_is_hidden_direction( $term ) ) {
            continue;
        }
        $term->count = aco_count_published_products_in_term( $term->term_id, 'product_cat' );
        $out[]       = $term;
    }

    return $out;
}

function aco_count_published_products_in_term( $term_id, $taxonomy ) {
    $q = new WP_Query( [
        'post_type'              => 'product',
        'post_status'            => 'publish',
        'posts_per_page'         => 1,
        'fields'                 => 'ids',
        'no_found_rows'          => false,
        'update_post_meta_cache' => false,
        'update_post_term_cache' => false,
        'tax_query'              => [[
            'taxonomy' => $taxonomy,
            'field'    => 'term_id',
            'terms'    => (int) $term_id,
        ]],
    ] );

    return (int) $q->found_posts;
}

function aco_published_catalog_count() {
    $counts = wp_count_posts( 'product' );
    return (int) ( $counts->publish ?? 0 );
}

function aco_courses_word( $count ) {
    $n      = abs( (int) $count );
    $mod10  = $n % 10;
    $mod100 = $n % 100;

    if ( $mod10 === 1 && $mod100 !== 11 ) {
        return 'курс';
    }
    if ( $mod10 >= 2 && $mod10 <= 4 && ( $mod100 < 12 || $mod100 > 14 ) ) {
        return 'курса';
    }
    return 'курсов';
}

function aco_courses_count_label( $count ) {
    $n = abs( (int) $count );
    return $n . ' ' . aco_courses_word( $n );
}

function aco_format_price_display( $amount ) {
    if ( $amount === '' || $amount === null ) {
        return '';
    }
    $n        = (float) $amount;
    $decimals = abs( $n - round( $n ) ) < 0.009 ? 0 : 2;
    return number_format( $n, $decimals, ',', ' ' ) . ' ₽';
}

function aco_direction_icon_svg( $term ) {
    $haystack = function_exists( 'mb_strtolower' )
        ? mb_strtolower( $term->slug . ' ' . $term->name )
        : strtolower( $term->slug . ' ' . $term->name );

    if ( str_contains( $haystack, 'безопас' ) || str_contains( $haystack, 'secur' ) ) {
        return '<svg width="32" height="34" viewBox="0 0 32 34" xmlns="http://www.w3.org/2000/svg"><path d="M16 2L2 8v9.333C2 25.333 8.222 32.8 16 34c7.778-1.2 14-8.667 14-16.667V8L16 2z"/></svg>';
    }
    if ( str_contains( $haystack, 'аналит' ) || str_contains( $haystack, 'data' ) || str_contains( $haystack, 'analytic' ) ) {
        return '<svg width="34" height="34" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><rect x="3" y="12" width="4" height="9"/><rect x="9" y="7" width="4" height="14"/><rect x="15" y="3" width="4" height="18"/></svg>';
    }
    if ( str_contains( $haystack, 'баз' ) || str_contains( $haystack, 'database' ) ) {
        return '<svg width="30" height="34" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M3 5v4c0 1.66 4.03 3 9 3s9-1.34 9-3V5"/><path d="M3 9v4c0 1.66 4.03 3 9 3s9-1.34 9-3V9"/><path d="M3 13v4c0 1.66 4.03 3 9 3s9-1.34 9-3V13"/></svg>';
    }
    if ( str_contains( $haystack, 'дизайн' ) || str_contains( $haystack, 'design' ) ) {
        return '<svg width="32" height="32" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18zm-4.5 9a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3zm3-4.5a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3zm5 0a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3zm3 4.5a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3z"/></svg>';
    }

    return '<svg width="38" height="34" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg>';
}

function aco_get_course_cta_url( $product ) {
    if ( ! $product ) {
        return '#';
    }

    if ( aco_user_owns_current_product( $product ) ) {
        return aco_get_course_learn_url( $product );
    }

    $checkout_url = aco_get_course_checkout_url( $product );

    if ( is_user_logged_in() ) {
        return $checkout_url;
    }

    return aco_get_purchase_redirect_url( $checkout_url );
}

function aco_get_course_cta_label( $product, $buy_label = 'Записаться' ) {
    if ( aco_user_owns_current_product( $product ) ) {
        return 'Перейти к обучению';
    }
    return $buy_label;
}

add_filter( 'woocommerce_is_purchasable', function( $purchasable, $product ) {
    if ( ! $purchasable || ! $product ) {
        return $purchasable;
    }
    if ( is_user_logged_in() && aco_user_owns_product( $product->get_id() ) ) {
        return false;
    }
    return $purchasable;
}, 10, 2 );

add_action( 'woocommerce_product_query', function( $q ) {
    if ( is_admin() || ! is_user_logged_in() ) {
        return;
    }
    $owned = aco_get_owned_product_ids();
    if ( ! $owned ) {
        return;
    }
    $existing = (array) $q->get( 'post__not_in' );
    $q->set( 'post__not_in', array_values( array_unique( array_merge( $existing, $owned ) ) ) );
} );

add_filter( 'woocommerce_add_to_cart_validation', function( $passed, $product_id, $quantity ) {
    if ( is_user_logged_in() && aco_user_owns_product( $product_id ) ) {
        wp_safe_redirect( home_url( '/account/#courses' ) );
        exit;
    }

    if ( ! is_user_logged_in() ) {
        $checkout_url = function_exists( 'wc_get_checkout_url' )
            ? add_query_arg( 'add-to-cart', $product_id, wc_get_checkout_url() )
            : get_permalink( $product_id );
        wp_safe_redirect( aco_get_purchase_redirect_url( $checkout_url ) );
        exit;
    }

    if ( $passed && function_exists( 'WC' ) && WC()->cart ) {
        WC()->cart->empty_cart();
    }

    return $passed;
}, 10, 3 );

add_filter( 'woocommerce_add_to_cart_redirect', function( $url ) {
    return function_exists( 'wc_get_checkout_url' ) ? wc_get_checkout_url() : $url;
} );

add_action( 'template_redirect', function() {
    if ( function_exists( 'is_cart' ) && is_cart() ) {
        if ( WC()->cart && ! WC()->cart->is_empty() ) {
            wp_safe_redirect( wc_get_checkout_url() );
            exit;
        }
        wp_safe_redirect( home_url( '/catalog/' ) );
        exit;
    }

    if ( ! function_exists( 'is_checkout' ) || ! is_checkout() || is_user_logged_in() ) {
        return;
    }

    if ( is_wc_endpoint_url( 'order-received' ) || is_wc_endpoint_url( 'order-pay' ) ) {
        return;
    }

    wp_safe_redirect( aco_get_purchase_redirect_url( wc_get_checkout_url() ) );
    exit;
} );

add_action( 'template_redirect', function() {
    if ( ! function_exists( 'is_checkout' ) || ! is_checkout() || ! is_user_logged_in() ) {
        return;
    }
    if ( is_wc_endpoint_url( 'order-received' ) || is_wc_endpoint_url( 'order-pay' ) ) {
        return;
    }
    if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
        return;
    }

    $owned   = aco_get_owned_product_ids();
    $removed = false;
    foreach ( WC()->cart->get_cart() as $key => $item ) {
        $pid = isset( $item['product_id'] ) ? (int) $item['product_id'] : 0;
        if ( $pid && in_array( $pid, $owned, true ) ) {
            WC()->cart->remove_cart_item( $key );
            $removed = true;
        }
    }

    if ( $removed && WC()->cart->is_empty() ) {
        wp_safe_redirect( home_url( '/account/#courses' ) );
        exit;
    }
}, 20 );

add_action( 'woocommerce_admin_process_product_object', function( $product ) {
    $product->set_virtual( true );
    $product->set_sold_individually( true );
} );

add_filter( 'woocommerce_checkout_redirect_empty_cart', '__return_false' );
add_filter( 'woocommerce_enable_order_notes_field', '__return_false' );
add_filter( 'woocommerce_cart_needs_shipping_address', '__return_false' );
add_filter( 'default_checkout_billing_country', function() {
    return 'RU';
} );

add_action( 'init', 'aco_enable_placeholder_cod_gateway', 20 );
function aco_enable_placeholder_cod_gateway() {
    if ( ! class_exists( 'WooCommerce' ) ) {
        return;
    }

    $env = getenv( 'ACO_ENABLE_COD_PLACEHOLDER' );
    if ( $env === '0' || strtolower( (string) $env ) === 'false' ) {
        return;
    }
    $wp_env = getenv( 'WP_ENVIRONMENT_TYPE' ) ?: '';
    if ( $wp_env === 'production' && $env !== '1' ) {
        return;
    }
    if ( get_option( 'yookassa_shop_id' ) ) {
        return;
    }
    if ( get_option( 'aco_cod_placeholder_enabled' ) ) {
        return;
    }

    $cod = get_option( 'woocommerce_cod_settings', [] );
    if ( ! is_array( $cod ) ) {
        $cod = [];
    }

    $cod['enabled']            = 'yes';
    $cod['enable_for_virtual'] = 'yes';
    $cod['title']              = 'Онлайн-оплата';
    $cod['description']        = '';
    update_option( 'woocommerce_cod_settings', $cod );
    update_option( 'aco_cod_placeholder_enabled', 1 );
}

add_action( 'wp_loaded', 'aco_mark_existing_courses_virtual', 25 );
function aco_mark_existing_courses_virtual() {
    if ( ! class_exists( 'WooCommerce' ) || get_option( 'aco_courses_virtual_enabled' ) === '2' ) {
        return;
    }

    $ids = get_posts( [
        'post_type'      => 'product',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'no_found_rows'  => true,
    ] );

    if ( empty( $ids ) ) {
        return;
    }

    foreach ( $ids as $id ) {
        $product = wc_get_product( $id );
        if ( ! $product ) {
            continue;
        }
        $product->set_virtual( true );
        $product->set_sold_individually( true );
        $product->save();
    }

    update_option( 'aco_courses_virtual_enabled', '2' );
}

add_action( 'init', function() {
    remove_action( 'woocommerce_before_checkout_form', 'woocommerce_checkout_login_form', 10 );
    remove_action( 'woocommerce_before_checkout_form', 'woocommerce_checkout_coupon_form', 10 );
} );

add_filter( 'woocommerce_checkout_fields', 'aco_simplify_checkout_fields', 99 );
add_filter( 'woocommerce_billing_fields', 'aco_simplify_billing_fields', 99 );

function aco_simplify_billing_fields( $fields ) {
    $keep = [ 'billing_first_name', 'billing_last_name', 'billing_email' ];
    foreach ( array_keys( $fields ) as $key ) {
        if ( ! in_array( $key, $keep, true ) ) {
            unset( $fields[ $key ] );
        }
    }

    if ( isset( $fields['billing_first_name'] ) ) {
        $fields['billing_first_name']['label']       = 'Имя';
        $fields['billing_first_name']['placeholder'] = 'Иван';
        $fields['billing_first_name']['class']       = [ 'form-row-first' ];
        $fields['billing_first_name']['priority']    = 10;
        $fields['billing_first_name']['required']    = true;
        $fields['billing_first_name']['autocomplete'] = 'given-name';
    }

    if ( isset( $fields['billing_last_name'] ) ) {
        $fields['billing_last_name']['label']       = 'Фамилия';
        $fields['billing_last_name']['placeholder'] = 'Иванов';
        $fields['billing_last_name']['class']       = [ 'form-row-last' ];
        $fields['billing_last_name']['priority']    = 20;
        $fields['billing_last_name']['required']    = true;
        $fields['billing_last_name']['autocomplete'] = 'family-name';
    }

    if ( isset( $fields['billing_email'] ) ) {
        $fields['billing_email']['label']       = 'Email';
        $fields['billing_email']['placeholder'] = 'ivan@mail.ru';
        $fields['billing_email']['class']       = [ 'form-row-wide' ];
        $fields['billing_email']['priority']    = 30;
        $fields['billing_email']['required']    = true;
        $fields['billing_email']['autocomplete'] = 'email';
    }

    return $fields;
}

function aco_simplify_checkout_fields( $fields ) {
    if ( isset( $fields['billing'] ) ) {
        $fields['billing'] = aco_simplify_billing_fields( $fields['billing'] );
        $fields['billing']['billing_country'] = [
            'type'     => 'hidden',
            'default'  => 'RU',
            'required' => false,
            'class'    => [ 'aco-checkout-country' ],
        ];
    }

    unset( $fields['shipping'], $fields['order'] );
    unset( $fields['account'] );

    return $fields;
}

add_filter( 'woocommerce_order_button_text', function( $text ) {
    if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
        return 'Оплатить';
    }

    $total = html_entity_decode( wp_strip_all_tags( WC()->cart->get_total() ), ENT_QUOTES, 'UTF-8' );
    return $total ? 'Оплатить ' . $total : 'Оплатить';
} );

add_filter( 'woocommerce_pay_order_button_text', function() {
    $order_id = absint( get_query_var( 'order-pay' ) );
    $order    = $order_id && function_exists( 'wc_get_order' ) ? wc_get_order( $order_id ) : null;
    if ( $order ) {
        $total = html_entity_decode( wp_strip_all_tags( $order->get_formatted_order_total() ), ENT_QUOTES, 'UTF-8' );
        if ( $total ) {
            return 'Оплатить ' . $total;
        }
    }

    return 'Перейти к оплате';
} );

add_action( 'woocommerce_checkout_process', 'aco_require_oferta_accept' );
add_action( 'woocommerce_before_pay_action', 'aco_require_oferta_accept' );

function aco_require_oferta_accept( $order = null ) {
    if ( empty( $_POST['aco_oferta_accept'] ) ) {
        wc_add_notice( 'Подтвердите, что принимаете публичную оферту.', 'error' );
        return;
    }
    if ( $order instanceof WC_Order ) {
        $updated = function_exists( 'aco_legal_context' ) ? aco_legal_context()['updated'] : '';
        $order->update_meta_data( '_aco_oferta_version', $updated );
        $order->update_meta_data( '_aco_oferta_accepted', current_time( 'mysql' ) );
        $order->save();
    }
}

add_action( 'woocommerce_checkout_create_order', function( $order ) {
    if ( ! $order->get_billing_country() ) {
        $order->set_billing_country( 'RU' );
    }
    $updated = function_exists( 'aco_legal_context' ) ? aco_legal_context()['updated'] : '';
    $order->update_meta_data( '_aco_oferta_version', $updated );
    $order->update_meta_data( '_aco_oferta_accepted', current_time( 'mysql' ) );
} );

add_action( 'woocommerce_before_checkout_billing_form', function() {
    if ( ! is_user_logged_in() || ! function_exists( 'aco_user_real_email' ) ) {
        return;
    }
    $user = wp_get_current_user();
    if ( aco_user_real_email( $user ) !== '' ) {
        return;
    }
    echo '<p class="aco-checkout-card__lead">Аккаунт создан по телефону. Укажите почту: на неё уйдут чек и доступ, после этого откроется Moodle.</p>';
} );

/* ── Auth AJAX: Login ───────────────────────────────────────────────── */
add_action( 'wp_ajax_nopriv_aco_ajax_login', 'aco_ajax_login' );
add_action( 'wp_ajax_aco_ajax_login',        'aco_ajax_login' );

function aco_ajax_login() {
    check_ajax_referer( 'aco_login', 'aco_login_nonce' );

    $login    = trim( (string) wp_unslash( $_POST['login'] ?? $_POST['email'] ?? '' ) );
    $password = $_POST['password'] ?? '';
    $remember = ! empty( $_POST['rememberme'] );

    if ( ! $login || ! $password ) {
        wp_send_json_error( 'Заполните почту или телефон и пароль' );
    }

    $user = function_exists( 'aco_user_by_login' ) ? aco_user_by_login( $login ) : get_user_by( 'email', sanitize_email( $login ) );
    if ( ! $user ) {
        wp_send_json_error( 'Аккаунт не найден. Проверьте почту или номер' );
    }

    $result = wp_signon( [
        'user_login'    => $user->user_login,
        'user_password' => $password,
        'remember'      => $remember,
    ] );

    if ( is_wp_error( $result ) ) {
        wp_send_json_error( 'Неверный пароль' );
    }

    $redirect = aco_safe_requested_redirect( aco_post_login_redirect( $result ) );

    wp_send_json_success( [ 'redirect' => $redirect ] );
}

/* ── Rate limiting helper (anti-spam for email sending) ─────────────── */
function aco_check_rate_limit( $email, $seconds = 60 ) {
    $key = 'aco_throttle_' . md5( $email );
    if ( get_transient( $key ) ) {
        return false;
    }
    set_transient( $key, 1, $seconds );
    return true;
}

/* ── Auth AJAX: Register — step 1, send confirmation code ───────────── */
add_action( 'wp_ajax_nopriv_aco_ajax_register', 'aco_ajax_register' );
add_action( 'wp_ajax_aco_ajax_register',        'aco_ajax_register' );

function aco_ajax_register() {
    check_ajax_referer( 'aco_register', 'aco_register_nonce' );

    $first_name = sanitize_text_field( $_POST['first_name'] ?? '' );
    $email      = sanitize_email( $_POST['email'] ?? '' );
    $password   = $_POST['password'] ?? '';

    if ( ! $first_name || ! $email || ! $password ) {
        wp_send_json_error( 'Заполните все поля' );
    }
    if ( empty( $_POST['agree_terms'] ) ) {
        wp_send_json_error( 'Необходимо принять публичную оферту и дать согласие на обработку персональных данных' );
    }
    if ( strlen( $password ) < 8 ) {
        wp_send_json_error( 'Пароль должен содержать минимум 8 символов' );
    }
    if ( email_exists( $email ) ) {
        wp_send_json_error( 'Пользователь с таким email уже зарегистрирован' );
    }
    if ( ! aco_is_public_email( $email ) ) {
        wp_send_json_error( aco_public_email_error() );
    }
    if ( ! aco_check_rate_limit( $email ) ) {
        wp_send_json_error( 'Код уже отправлен. Подождите минуту перед повторной отправкой' );
    }

    // Registration is finalised only after the code is confirmed
    // (see aco_ajax_verify_register) — nothing is written to wp_users yet.
    $code = wp_rand( 100000, 999999 );
    set_transient( 'aco_reg_data_' . md5( $email ), [
        'first_name' => $first_name,
        'password'   => $password,
        'code'       => $code,
    ], 15 * MINUTE_IN_SECONDS );

    if ( ! aco_send_otp_mail( $email, $code, 'register', $first_name ) ) {
        delete_transient( 'aco_reg_data_' . md5( $email ) );
        aco_clear_mail_throttle( $email );
        wp_send_json_error( 'Не удалось отправить письмо. Проверьте адрес или попробуйте позже' );
    }

    wp_send_json_success( [] );
}

/* ── Auth AJAX: Register — step 2, verify code & create account ─────── */
add_action( 'wp_ajax_nopriv_aco_ajax_verify_register', 'aco_ajax_verify_register' );
add_action( 'wp_ajax_aco_ajax_verify_register',        'aco_ajax_verify_register' );

/**
 * Skip Edwiser's synchronous Moodle user create during our register AJAX.
 * That HTTP call can wait 50s+ per request; the browser then never gets
 * Set-Cookie / JSON, so the spinner never stops and auto-login fails.
 */
add_filter( 'eb_disable_checkout_user_creation', 'aco_skip_edwiser_moodle_on_register_ajax', 20 );

function aco_skip_edwiser_moodle_on_register_ajax( $disable ) {
    if ( wp_doing_ajax() && isset( $_POST['action'] ) && 'aco_ajax_verify_register' === $_POST['action'] ) {
        return true;
    }
    return $disable;
}

function aco_unhook_edwiser_register_sync() {
    if ( ! function_exists( 'app\wisdmlabs\edwiserBridge\edwiser_bridge_instance' ) ) {
        return;
    }
    $manager = \app\wisdmlabs\edwiserBridge\edwiser_bridge_instance()->user_manager();
    remove_action( 'user_register', [ $manager, 'eb_moodle_user_register' ], 10 );
}

/**
 * Send the AJAX JSON (and auth cookies) first, then run slow follow-up work.
 */
function aco_ajax_json_then( $success, $data, $after = null ) {
    $payload = [
        'success' => (bool) $success,
        'data'    => $data,
    ];

    if ( ! headers_sent() ) {
        header( 'Content-Type: application/json; charset=UTF-8' );
        nocache_headers();
    }

    echo wp_json_encode( $payload );

    if ( function_exists( 'fastcgi_finish_request' ) ) {
        fastcgi_finish_request();
    } else {
        ignore_user_abort( true );
        if ( function_exists( 'session_write_close' ) ) {
            session_write_close();
        }
        while ( ob_get_level() > 0 ) {
            ob_end_flush();
        }
        flush();
    }

    if ( is_callable( $after ) ) {
        try {
            $after();
        } catch ( \Throwable $e ) {
            error_log( 'ACO after-ajax: ' . $e->getMessage() );
        }
    }
    exit;
}

function aco_ajax_verify_register() {
    check_ajax_referer( 'aco_verify_code', 'aco_verify_nonce' );

    $email = sanitize_email( $_POST['email'] ?? '' );
    $code  = preg_replace( '/\D+/', '', (string) ( $_POST['code'] ?? '' ) );

    if ( ! $email || ! $code ) {
        wp_send_json_error( 'Введите код' );
    }

    $data = get_transient( 'aco_reg_data_' . md5( $email ) );

    if ( false === $data || (string) $data['code'] !== (string) $code ) {
        wp_send_json_error( 'Неверный или устаревший код' );
    }

    if ( email_exists( $email ) ) {
        delete_transient( 'aco_reg_data_' . md5( $email ) );
        wp_send_json_error( 'Пользователь с таким email уже зарегистрирован' );
    }

    aco_unhook_edwiser_register_sync();

    $username = sanitize_user( strtolower( $data['first_name'] ) . '_' . wp_generate_password( 4, false ) );
    $user_id  = wp_create_user( $username, $data['password'], $email );

    if ( is_wp_error( $user_id ) ) {
        wp_send_json_error( $user_id->get_error_message() );
    }

    wp_update_user( [
        'ID'           => $user_id,
        'first_name'   => $data['first_name'],
        'display_name' => $data['first_name'],
    ] );

    delete_transient( 'aco_reg_data_' . md5( $email ) );

    $user = get_user_by( 'id', $user_id );
    wp_set_current_user( $user_id, $user->user_login );
    wp_set_auth_cookie( $user_id, true, is_ssl() );
    do_action( 'wp_login', $user->user_login, $user );

    $redirect = home_url( '/account/' );
    if ( ! empty( $_POST['redirect_to'] ) ) {
        $candidate = esc_url_raw( wp_unslash( $_POST['redirect_to'] ) );
        if ( wp_validate_redirect( $candidate, '' ) ) {
            $redirect = $candidate;
        }
    }

    $first_name = $data['first_name'];
    aco_ajax_json_then( true, [ 'redirect' => $redirect ], static function () use ( $user_id, $email, $first_name ) {
        if ( function_exists( 'aco_eb_available' ) && aco_eb_available() ) {
            aco_moodle_ensure_user( $user_id );
        }

        aco_send_mail(
            $email,
            'Добро пожаловать в АЦО!',
            aco_mail_wrap(
                'Добро пожаловать в АЦО',
                '<p style="margin:0 0 12px">Привет, ' . esc_html( $first_name ) . '!</p>'
                . '<p style="margin:0">Вы успешно зарегистрировались. Ваш email: ' . esc_html( $email ) . '</p>'
            )
        );
    } );
}

/* ── Auth AJAX: Send recovery code ─────────────────────────────────── */
add_action( 'wp_ajax_nopriv_aco_ajax_send_code', 'aco_ajax_send_code' );
add_action( 'wp_ajax_aco_ajax_send_code',        'aco_ajax_send_code' );

function aco_ajax_send_code() {
    check_ajax_referer( 'aco_recovery', 'aco_recovery_nonce' );

    $email = sanitize_email( $_POST['email'] ?? '' );

    if ( ! $email ) {
        wp_send_json_error( 'Введите email' );
    }
    if ( ! email_exists( $email ) ) {
        // Don't reveal if email exists — show generic success
        wp_send_json_success( [] );
        return;
    }
    if ( ! aco_check_rate_limit( $email ) ) {
        wp_send_json_error( 'Код уже отправлен. Подождите минуту перед повторной отправкой' );
    }

    $code = wp_rand( 100000, 999999 );
    set_transient( 'aco_reset_code_' . md5( $email ), $code, 15 * MINUTE_IN_SECONDS );

    if ( ! aco_send_otp_mail( $email, $code, 'recovery' ) ) {
        delete_transient( 'aco_reset_code_' . md5( $email ) );
        aco_clear_mail_throttle( $email );
        wp_send_json_error( 'Не удалось отправить письмо. Проверьте адрес или попробуйте позже' );
    }

    wp_send_json_success( [] );
}

/* ── Auth AJAX: Verify code ─────────────────────────────────────────── */
add_action( 'wp_ajax_nopriv_aco_ajax_verify_code', 'aco_ajax_verify_code' );
add_action( 'wp_ajax_aco_ajax_verify_code',        'aco_ajax_verify_code' );

function aco_ajax_verify_code() {
    check_ajax_referer( 'aco_verify_code', 'aco_verify_nonce' );

    $email = sanitize_email( $_POST['email'] ?? '' );
    $code  = sanitize_text_field( $_POST['code'] ?? '' );

    if ( ! $email || ! $code ) {
        wp_send_json_error( 'Введите код' );
    }

    $stored = get_transient( 'aco_reset_code_' . md5( $email ) );

    if ( false === $stored || (string) $stored !== (string) $code ) {
        wp_send_json_error( 'Неверный или устаревший код' );
    }

    // Code is correct — generate one-time token
    delete_transient( 'aco_reset_code_' . md5( $email ) );
    $token = wp_generate_password( 32, false );
    set_transient( 'aco_reset_token_' . md5( $email ), $token, 15 * MINUTE_IN_SECONDS );

    wp_send_json_success( [ 'token' => $token ] );
}

/* ── Auth AJAX: Set new password ────────────────────────────────────── */
add_action( 'wp_ajax_nopriv_aco_ajax_new_password', 'aco_ajax_new_password' );
add_action( 'wp_ajax_aco_ajax_new_password',        'aco_ajax_new_password' );

function aco_ajax_new_password() {
    check_ajax_referer( 'aco_new_password', 'aco_newpass_nonce' );

    $email    = sanitize_email( $_POST['email'] ?? '' );
    $phone    = function_exists( 'aco_normalize_phone' ) ? aco_normalize_phone( wp_unslash( $_POST['phone'] ?? '' ) ) : '';
    $token    = sanitize_text_field( $_POST['token'] ?? '' );
    $password = $_POST['password'] ?? '';
    $key      = $phone !== '' ? $phone : $email;

    if ( ! $key || ! $token || ! $password ) {
        wp_send_json_error( 'Недостаточно данных' );
    }
    if ( strlen( $password ) < 8 ) {
        wp_send_json_error( 'Пароль должен содержать минимум 8 символов' );
    }

    $stored_token = get_transient( 'aco_reset_token_' . md5( $key ) );
    if ( false === $stored_token || ! hash_equals( $stored_token, $token ) ) {
        wp_send_json_error( 'Сессия сброса истекла. Начните заново.' );
    }

    $user = $phone !== '' && function_exists( 'aco_user_by_phone' ) ? aco_user_by_phone( $phone ) : get_user_by( 'email', $email );
    if ( ! $user ) {
        wp_send_json_error( 'Пользователь не найден' );
    }

    wp_set_password( $password, $user->ID );
    delete_transient( 'aco_reset_token_' . md5( $key ) );

    // Auto-login
    wp_set_current_user( $user->ID, $user->user_login );
    wp_set_auth_cookie( $user->ID );

    $redirect = aco_safe_requested_redirect( aco_post_login_redirect( $user ) );

    wp_send_json_success( [ 'redirect' => $redirect ] );
}
