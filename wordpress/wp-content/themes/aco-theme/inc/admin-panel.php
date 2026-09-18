<?php
/**
 * Custom ACO admin at /aco-admin/ — writes the same Woo products and categories as wp-admin.
 *
 * @package aco-theme
 */

defined( 'ABSPATH' ) || exit;

function aco_ensure_admin_page() {
    $page = get_page_by_path( 'aco-admin' );

    if ( ! $page ) {
        $page_id = wp_insert_post( [
            'post_type'      => 'page',
            'post_status'    => 'publish',
            'post_title'     => 'Панель АЦО',
            'post_name'      => 'aco-admin',
            'post_content'   => '',
            'comment_status' => 'closed',
            'ping_status'    => 'closed',
        ], true );

        if ( is_wp_error( $page_id ) || ! $page_id ) {
            return;
        }

        $page = get_post( $page_id );
        if ( ! get_option( 'aco_admin_page_v1' ) ) {
            flush_rewrite_rules( false );
            update_option( 'aco_admin_page_v1', 1 );
        }
    }

    if ( $page && get_page_template_slug( $page->ID ) !== 'page-aco-admin.php' ) {
        update_post_meta( $page->ID, '_wp_page_template', 'page-aco-admin.php' );
    }
}
add_action( 'init', 'aco_ensure_admin_page' );

add_action( 'wp_head', function() {
    if ( is_page_template( 'page-aco-admin.php' ) ) {
        echo '<meta name="robots" content="noindex,nofollow">' . "\n";
    }
}, 1 );

function aco_admin_panel_gate() {
    if ( ! is_page_template( 'page-aco-admin.php' ) ) {
        return;
    }

    if ( ! is_user_logged_in() ) {
        wp_safe_redirect( add_query_arg( 'redirect_to', aco_admin_home_url(), home_url( '/login/' ) ) );
        exit;
    }

    if ( ! aco_is_platform_admin() ) {
        wp_safe_redirect( home_url( '/account/' ) );
        exit;
    }
}
add_action( 'template_redirect', 'aco_admin_panel_gate' );

function aco_admin_directions_url( $args = [] ) {
    return aco_admin_home_url( array_merge( [ 'view' => 'directions' ], $args ) );
}

function aco_admin_dir_flash( $text, $error = false ) {
    set_transient(
        'aco_admin_dir_flash_' . get_current_user_id(),
        [ 'text' => $text, 'error' => (bool) $error ],
        2 * MINUTE_IN_SECONDS
    );
}

function aco_admin_dir_take_flash() {
    $key  = 'aco_admin_dir_flash_' . get_current_user_id();
    $val  = get_transient( $key );
    delete_transient( $key );
    return is_array( $val ) ? $val : null;
}

function aco_admin_transliterate( $text ) {
    $map = [
        'А' => 'A', 'Б' => 'B', 'В' => 'V', 'Г' => 'G', 'Д' => 'D', 'Е' => 'E', 'Ё' => 'Yo', 'Ж' => 'Zh',
        'З' => 'Z', 'И' => 'I', 'Й' => 'Y', 'К' => 'K', 'Л' => 'L', 'М' => 'M', 'Н' => 'N', 'О' => 'O',
        'П' => 'P', 'Р' => 'R', 'С' => 'S', 'Т' => 'T', 'У' => 'U', 'Ф' => 'F', 'Х' => 'H', 'Ц' => 'Ts',
        'Ч' => 'Ch', 'Ш' => 'Sh', 'Щ' => 'Sch', 'Ъ' => '', 'Ы' => 'Y', 'Ь' => '', 'Э' => 'E', 'Ю' => 'Yu',
        'Я' => 'Ya',
        'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'yo', 'ж' => 'zh',
        'з' => 'z', 'и' => 'i', 'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n', 'о' => 'o',
        'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u', 'ф' => 'f', 'х' => 'h', 'ц' => 'ts',
        'ч' => 'ch', 'ш' => 'sh', 'щ' => 'sch', 'ъ' => '', 'ы' => 'y', 'ь' => '', 'э' => 'e', 'ю' => 'yu',
        'я' => 'ya',
        'І' => 'I', 'і' => 'i', 'Ї' => 'Yi', 'ї' => 'yi', 'Є' => 'Ye', 'є' => 'ye', 'Ґ' => 'G', 'ґ' => 'g',
    ];
    return strtr( (string) $text, $map );
}

function aco_admin_slug_is_broken( $slug ) {
    $slug = (string) $slug;
    if ( $slug === '' ) {
        return true;
    }
    if ( strpos( $slug, '%' ) !== false ) {
        return true;
    }
    return (bool) preg_match( '/[^\x20-\x7E]/', $slug );
}

function aco_admin_unique_term_slug( $slug, $exclude_id = 0 ) {
    $base = $slug;
    $i    = 2;
    while ( $i < 80 ) {
        $existing = get_term_by( 'slug', $slug, 'product_cat' );
        if ( ! $existing || is_wp_error( $existing ) || (int) $existing->term_id === (int) $exclude_id ) {
            return $slug;
        }
        $slug = $base . '-' . $i;
        $i++;
    }
    return $base . '-' . strtolower( wp_generate_password( 4, false, false ) );
}

function aco_admin_make_slug( $name, $slug = '', $exclude_id = 0 ) {
    $source = trim( (string) $slug );
    if ( $source === '' || aco_admin_slug_is_broken( $source ) ) {
        $source = (string) $name;
    }
    $out = sanitize_title( aco_admin_transliterate( $source ) );
    if ( $out === '' || aco_admin_slug_is_broken( $out ) ) {
        $out = 'direction-' . substr( md5( (string) $name . '|' . (string) $exclude_id ), 0, 8 );
    }
    return aco_admin_unique_term_slug( $out, $exclude_id );
}

function aco_admin_repair_direction_slugs() {
    if ( ! taxonomy_exists( 'product_cat' ) ) {
        return;
    }

    $terms = get_terms( [
        'taxonomy'   => 'product_cat',
        'hide_empty' => false,
    ] );
    if ( is_wp_error( $terms ) || ! $terms ) {
        return;
    }

    foreach ( $terms as $term ) {
        if ( ! $term instanceof WP_Term || ! aco_admin_slug_is_broken( $term->slug ) ) {
            continue;
        }
        wp_update_term(
            (int) $term->term_id,
            'product_cat',
            [ 'slug' => aco_admin_make_slug( $term->name, '', (int) $term->term_id ) ]
        );
    }
}

function aco_admin_purge_placeholder_direction() {
    static $done = false;
    if ( $done || ! taxonomy_exists( 'product_cat' ) ) {
        return;
    }
    $done = true;

    $mist = get_term_by( 'slug', 'mist', 'product_cat' );
    if ( ! $mist instanceof WP_Term ) {
        return;
    }

    $default_id = (int) get_option( 'default_product_cat' );
    if ( (int) $mist->term_id === $default_id ) {
        $fallback = get_term_by( 'slug', 'uncategorized', 'product_cat' );
        if ( ! $fallback instanceof WP_Term ) {
            $created = wp_insert_term( 'Uncategorized', 'product_cat', [ 'slug' => 'uncategorized' ] );
            if ( is_wp_error( $created ) ) {
                return;
            }
            $fallback_id = (int) $created['term_id'];
        } else {
            $fallback_id = (int) $fallback->term_id;
        }
        update_option( 'default_product_cat', $fallback_id );
    }

    wp_delete_term( (int) $mist->term_id, 'product_cat' );
}

function aco_admin_handle_directions() {
    if ( ! is_page_template( 'page-aco-admin.php' ) || ! aco_is_platform_admin() ) {
        return;
    }
    if ( ( $_SERVER['REQUEST_METHOD'] ?? '' ) !== 'POST' ) {
        return;
    }
    if ( empty( $_POST['aco_dir_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['aco_dir_nonce'] ) ), 'aco_admin_dir' ) ) {
        return;
    }

    if ( ! empty( $_POST['aco_dir_delete'] ) ) {
        $term_id = absint( $_POST['dir_term_id'] ?? 0 );
        if ( ! $term_id ) {
            aco_admin_dir_flash( 'Направление не найдено', true );
        } elseif ( $term_id === (int) get_option( 'default_product_cat' ) ) {
            aco_admin_dir_flash( 'Системную категорию удалить нельзя', true );
        } else {
            $deleted = wp_delete_term( $term_id, 'product_cat' );
            if ( $deleted && ! is_wp_error( $deleted ) ) {
                aco_admin_dir_flash( 'Направление удалено' );
            } else {
                aco_admin_dir_flash( 'Не удалось удалить направление', true );
            }
        }
        wp_safe_redirect( aco_admin_directions_url() );
        exit;
    }

    if ( empty( $_POST['aco_dir_save'] ) ) {
        return;
    }

    $term_id = absint( $_POST['dir_term_id'] ?? 0 );
    $name    = sanitize_text_field( wp_unslash( $_POST['dir_name'] ?? '' ) );
    $slug    = aco_admin_make_slug( $name, wp_unslash( $_POST['dir_slug'] ?? '' ), $term_id );

    if ( $name === '' ) {
        aco_admin_dir_flash( 'Укажите название направления — одно slug недостаточно', true );
        wp_safe_redirect( aco_admin_directions_url( $term_id ? [ 'dir' => $term_id ] : [] ) );
        exit;
    }

    $args = [
        'name' => $name,
        'slug' => $slug,
    ];

    if ( $term_id ) {
        $result = wp_update_term( $term_id, 'product_cat', $args );
    } else {
        $result = wp_insert_term( $name, 'product_cat', $args );
    }

    if ( is_wp_error( $result ) ) {
        aco_admin_dir_flash( $result->get_error_message(), true );
        wp_safe_redirect( aco_admin_directions_url( $term_id ? [ 'dir' => $term_id ] : [] ) );
        exit;
    }

    aco_admin_dir_flash( $term_id ? 'Направление обновлено' : 'Направление создано' );
    wp_safe_redirect( aco_admin_directions_url() );
    exit;
}
add_action( 'template_redirect', 'aco_admin_handle_directions', 11 );

function aco_admin_current_view() {
    $view = sanitize_key( wp_unslash( $_GET['view'] ?? 'courses' ) );
    return in_array( $view, [ 'courses', 'directions', 'teachers', 'edit' ], true ) ? $view : 'courses';
}

function aco_admin_ajax_guard() {
    check_ajax_referer( 'aco_admin_panel', 'nonce' );
    if ( ! aco_is_platform_admin() ) {
        wp_send_json_error( 'Недостаточно прав', 403 );
    }
}

function aco_admin_serialize_directions() {
    if ( ! taxonomy_exists( 'product_cat' ) ) {
        return [];
    }

    aco_admin_repair_direction_slugs();
    aco_admin_purge_placeholder_direction();

    $terms = get_terms( [
        'taxonomy'   => 'product_cat',
        'hide_empty' => false,
        'orderby'    => 'name',
        'order'      => 'ASC',
    ] );

    if ( is_wp_error( $terms ) ) {
        return [];
    }

    $out = [];

    foreach ( $terms as $term ) {
        if ( aco_is_hidden_direction( $term ) ) {
            continue;
        }
        $out[] = [
            'id'         => (int) $term->term_id,
            'name'       => $term->name,
            'slug'       => $term->slug,
            'count'      => (int) $term->count,
            'is_default' => false,
        ];
    }

    return $out;
}

function aco_admin_serialize_course( $product ) {
    if ( ! $product instanceof WC_Product ) {
        return null;
    }

    $id       = $product->get_id();
    $cat_ids  = $product->get_category_ids();
    $cat_id   = $cat_ids ? (int) $cat_ids[0] : 0;
    $cat_name = '';
    if ( $cat_id ) {
        $term = get_term( $cat_id, 'product_cat' );
        if ( $term && ! is_wp_error( $term ) ) {
            $cat_name = $term->name;
        }
    }

    $image_id  = (int) $product->get_image_id();
    $video_id  = aco_get_course_preview_video_id( $id );
    $image_url = $image_id ? wp_get_attachment_image_url( $image_id, 'medium' ) : '';
    $video_url = $video_id ? wp_get_attachment_url( $video_id ) : '';

    return [
        'id'               => $id,
        'title'            => $product->get_name(),
        'excerpt'          => $product->get_short_description(),
        'content'          => $product->get_description(),
        'status'           => $product->get_status(),
        'featured'         => $product->is_featured(),
        'regular_price'    => (string) $product->get_regular_price(),
        'sale_price'       => (string) $product->get_sale_price(),
        'price_label'      => wp_strip_all_tags( html_entity_decode( $product->get_price_html() ) ),
        'image_id'         => $image_id,
        'image_url'        => $image_url ? $image_url : '',
        'video_id'         => $video_id,
        'video_url'        => $video_url ? $video_url : '',
        'category_id'      => $cat_id,
        'category_name'    => $cat_name,
        'duration'         => (string) get_post_meta( $id, '_aco_course_duration', true ),
        'level'            => (string) get_post_meta( $id, '_aco_course_level', true ),
        'teacher'          => aco_teacher_display_name( $id ),
        'teacher_user_id'  => (string) (int) get_post_meta( $id, '_aco_teacher_user_id', true ),
        'moodle_course_id' => (string) get_post_meta( $id, '_aco_moodle_course_id', true ),
        'program'          => aco_get_course_program( $id ),
        'benefits'         => aco_get_course_benefits( $id ),
        'faq'              => aco_get_course_faq( $id ),
        'permalink'        => get_permalink( $id ),
    ];
}

function aco_admin_serialize_courses() {
    if ( ! function_exists( 'wc_get_products' ) ) {
        return [];
    }

    $products = wc_get_products( [
        'status'  => [ 'publish', 'draft', 'pending', 'private' ],
        'limit'   => -1,
        'orderby' => 'date',
        'order'   => 'DESC',
        'return'  => 'objects',
    ] );

    $out = [];
    foreach ( $products as $product ) {
        $row = aco_admin_serialize_course( $product );
        if ( $row ) {
            $out[] = $row;
        }
    }
    return $out;
}

function aco_admin_valid_attachment( $id, $type_prefix ) {
    $id = absint( $id );
    if ( $id < 1 ) {
        return 0;
    }
    $mime = get_post_mime_type( $id );
    if ( ! $mime || strpos( $mime, $type_prefix ) !== 0 ) {
        return 0;
    }
    return $id;
}

add_action( 'wp_ajax_aco_admin_bootstrap', 'aco_admin_ajax_bootstrap' );
function aco_admin_ajax_bootstrap() {
    aco_admin_ajax_guard();
    wp_send_json_success( [
        'directions' => aco_admin_serialize_directions(),
        'courses'    => aco_admin_serialize_courses(),
        'moodle'     => aco_moodle_course_options(),
        'teachers'   => aco_teacher_options(),
        'woo'        => function_exists( 'wc_get_products' ),
    ] );
}

add_action( 'wp_ajax_aco_admin_save_direction', 'aco_admin_ajax_save_direction' );
function aco_admin_ajax_save_direction() {
    aco_admin_ajax_guard();

    $term_id = absint( $_POST['term_id'] ?? 0 );
    $name    = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
    $slug    = aco_admin_make_slug( $name, wp_unslash( $_POST['slug'] ?? '' ), $term_id );

    if ( $name === '' ) {
        wp_send_json_error( 'Укажите название направления' );
    }

    $args = [
        'name' => $name,
        'slug' => $slug,
    ];

    if ( $term_id ) {
        $result = wp_update_term( $term_id, 'product_cat', $args );
    } else {
        $result = wp_insert_term( $name, 'product_cat', $args );
    }

    if ( is_wp_error( $result ) ) {
        wp_send_json_error( $result->get_error_message() );
    }

    wp_send_json_success( [ 'directions' => aco_admin_serialize_directions() ] );
}

add_action( 'wp_ajax_aco_admin_delete_direction', 'aco_admin_ajax_delete_direction' );
function aco_admin_ajax_delete_direction() {
    aco_admin_ajax_guard();

    $term_id = absint( $_POST['term_id'] ?? 0 );
    if ( ! $term_id ) {
        wp_send_json_error( 'Направление не найдено' );
    }
    if ( $term_id === (int) get_option( 'default_product_cat' ) ) {
        wp_send_json_error( 'Системную категорию удалить нельзя' );
    }

    $deleted = wp_delete_term( $term_id, 'product_cat' );
    if ( ! $deleted || is_wp_error( $deleted ) ) {
        wp_send_json_error( 'Не удалось удалить направление' );
    }

    wp_send_json_success( [ 'directions' => aco_admin_serialize_directions() ] );
}

add_action( 'wp_ajax_aco_admin_get_course', 'aco_admin_ajax_get_course' );
function aco_admin_ajax_get_course() {
    aco_admin_ajax_guard();

    $id      = absint( $_POST['id'] ?? 0 );
    $product = $id && function_exists( 'wc_get_product' ) ? wc_get_product( $id ) : null;
    if ( ! $product ) {
        wp_send_json_error( 'Курс не найден' );
    }

    wp_send_json_success( [ 'course' => aco_admin_serialize_course( $product ) ] );
}

add_action( 'wp_ajax_aco_admin_save_course', 'aco_admin_ajax_save_course' );
function aco_admin_ajax_save_course() {
    aco_admin_ajax_guard();

    if ( ! function_exists( 'wc_get_product' ) ) {
        wp_send_json_error( 'WooCommerce не активен' );
    }

    $id    = absint( $_POST['id'] ?? 0 );
    $title = sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) );
    if ( $title === '' ) {
        wp_send_json_error( 'Укажите название курса' );
    }

    if ( $id ) {
        $product = wc_get_product( $id );
        if ( ! $product ) {
            wp_send_json_error( 'Курс не найден' );
        }
    } else {
        $product = new WC_Product_Simple();
        $product->set_virtual( true );
        $product->set_sold_individually( true );
        $product->set_catalog_visibility( 'visible' );
    }

    $status = sanitize_key( wp_unslash( $_POST['status'] ?? 'draft' ) );
    if ( ! in_array( $status, [ 'publish', 'draft' ], true ) ) {
        $status = 'draft';
    }

    $product->set_name( $title );
    $product->set_short_description( wp_kses_post( wp_unslash( $_POST['excerpt'] ?? '' ) ) );
    $product->set_description( wp_kses_post( wp_unslash( $_POST['content'] ?? '' ) ) );
    $product->set_status( $status );
    $product->set_featured( ! empty( $_POST['featured'] ) );
    $product->set_regular_price( wc_format_decimal( sanitize_text_field( wp_unslash( $_POST['regular_price'] ?? '' ) ) ) );

    $sale = sanitize_text_field( wp_unslash( $_POST['sale_price'] ?? '' ) );
    $product->set_sale_price( $sale === '' ? '' : wc_format_decimal( $sale ) );

    $image_id = aco_admin_valid_attachment( absint( $_POST['image_id'] ?? 0 ), 'image/' );
    $product->set_image_id( $image_id );

    $cat_id = absint( $_POST['category_id'] ?? 0 );
    $product->set_category_ids( $cat_id ? [ $cat_id ] : [] );

    if ( $product->is_virtual() !== true ) {
        $product->set_virtual( true );
    }
    $product->set_sold_individually( true );

    $saved_id = $product->save();
    if ( ! $saved_id ) {
        wp_send_json_error( 'Не удалось сохранить курс' );
    }

    update_post_meta( $saved_id, '_aco_course_duration', sanitize_text_field( wp_unslash( $_POST['duration'] ?? '' ) ) );
    update_post_meta( $saved_id, '_aco_course_level', sanitize_text_field( wp_unslash( $_POST['level'] ?? '' ) ) );
    $teacher_id = absint( $_POST['teacher_user_id'] ?? 0 );
    update_post_meta( $saved_id, '_aco_teacher_user_id', $teacher_id );
    update_post_meta( $saved_id, '_aco_course_teacher', $teacher_id ? aco_teacher_display_name( $saved_id ) : sanitize_text_field( wp_unslash( $_POST['teacher'] ?? '' ) ) );
    update_post_meta( $saved_id, '_aco_moodle_course_id', absint( $_POST['moodle_course_id'] ?? 0 ) );
    $linked_moodle = absint( $_POST['moodle_course_id'] ?? 0 );
    if ( $linked_moodle && function_exists( 'aco_moodle_ensure_eb_course' ) ) {
        aco_moodle_ensure_eb_course( $linked_moodle, (string) $product->get_name() );
    }

    aco_save_course_landing_meta(
        $saved_id,
        wp_unslash( $_POST['program'] ?? '[]' ),
        wp_unslash( $_POST['benefits'] ?? '[]' ),
        wp_unslash( $_POST['faq'] ?? '[]' ),
        aco_admin_valid_attachment( absint( $_POST['video_id'] ?? 0 ), 'video/' )
    );

    $fresh = wc_get_product( $saved_id );
    wp_send_json_success( [
        'course'  => aco_admin_serialize_course( $fresh ),
        'courses' => aco_admin_serialize_courses(),
    ] );
}

add_action( 'wp_ajax_aco_admin_trash_course', 'aco_admin_ajax_trash_course' );
function aco_admin_ajax_trash_course() {
    aco_admin_ajax_guard();

    $id = absint( $_POST['id'] ?? 0 );
    if ( ! $id || get_post_type( $id ) !== 'product' ) {
        wp_send_json_error( 'Курс не найден' );
    }
    if ( ! current_user_can( 'delete_post', $id ) ) {
        wp_send_json_error( 'Недостаточно прав', 403 );
    }

    $trashed = wp_trash_post( $id );
    if ( ! $trashed ) {
        wp_send_json_error( 'Не удалось удалить курс' );
    }

    wp_send_json_success( [ 'courses' => aco_admin_serialize_courses() ] );
}
