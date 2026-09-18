<?php
/**
 * Structured course landing content (program, benefits, FAQ, preview video).
 * Stored as product post meta so wp-admin and the custom panel share one source.
 *
 * @package aco-theme
 */

defined( 'ABSPATH' ) || exit;

function aco_is_platform_admin( $user = null ) {
    if ( $user instanceof WP_User ) {
        return user_can( $user, 'manage_options' );
    }
    return is_user_logged_in() && current_user_can( 'manage_options' );
}

function aco_admin_home_url( $args = [] ) {
    $url = home_url( '/aco-admin/' );
    if ( $args ) {
        $url = add_query_arg( $args, $url );
    }
    return $url;
}

function aco_safe_requested_redirect( $fallback = '' ) {
    if ( empty( $_REQUEST['redirect_to'] ) ) {
        return $fallback;
    }
    $candidate = wp_validate_redirect( esc_url_raw( wp_unslash( $_REQUEST['redirect_to'] ) ), '' );
    return $candidate ?: $fallback;
}

function aco_ensure_admin_auth_cookie() {
    if ( ! is_user_logged_in() ) {
        return;
    }
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    if ( ! is_string( $uri ) || ! str_contains( $uri, '/wp-admin' ) ) {
        return;
    }
    if ( ( defined( 'DOING_AJAX' ) && DOING_AJAX ) || ( defined( 'DOING_CRON' ) && DOING_CRON ) ) {
        return;
    }
    $scheme = is_ssl() ? 'secure_auth' : 'auth';
    if ( wp_validate_auth_cookie( '', $scheme ) ) {
        return;
    }
    wp_set_auth_cookie( get_current_user_id(), true, is_ssl() );
}
add_action( 'init', 'aco_ensure_admin_auth_cookie', 0 );

function aco_post_login_redirect( $user = null ) {
    if ( ! $user instanceof WP_User ) {
        $user = wp_get_current_user();
    }
    if ( aco_is_platform_admin( $user ) ) {
        return aco_admin_home_url();
    }
    if ( function_exists( 'aco_is_teacher' ) && aco_is_teacher( $user ) ) {
        $target = aco_teacher_cabinet_url();
        $sso    = function_exists( 'aco_moodle_sso_build_url' ) ? aco_moodle_sso_build_url( $user->ID, $target ) : '';
        return $sso ?: $target;
    }
    return home_url( '/account/' );
}

function aco_sanitize_program( $raw ) {
    $out = [];
    if ( is_string( $raw ) ) {
        $raw = json_decode( wp_unslash( $raw ), true );
    }
    if ( ! is_array( $raw ) ) {
        return [];
    }
    foreach ( $raw as $mod ) {
        if ( ! is_array( $mod ) ) {
            continue;
        }
        $title = sanitize_text_field( $mod['title'] ?? '' );
        $items = [];
        foreach ( (array) ( $mod['items'] ?? [] ) as $item ) {
            $item = sanitize_text_field( $item );
            if ( $item !== '' ) {
                $items[] = $item;
            }
        }
        if ( $title === '' && ! $items ) {
            continue;
        }
        $out[] = [
            'title' => $title !== '' ? $title : 'Модуль',
            'items' => $items,
        ];
    }
    return $out;
}

function aco_sanitize_benefits( $raw ) {
    $out = [];
    if ( is_string( $raw ) ) {
        $raw = json_decode( wp_unslash( $raw ), true );
    }
    if ( ! is_array( $raw ) ) {
        return [];
    }
    foreach ( $raw as $row ) {
        if ( ! is_array( $row ) ) {
            continue;
        }
        $title = sanitize_text_field( $row['title'] ?? '' );
        $text  = sanitize_textarea_field( $row['text'] ?? '' );
        if ( $title === '' && $text === '' ) {
            continue;
        }
        $out[] = [
            'title' => $title !== '' ? $title : 'Пункт',
            'text'  => $text,
        ];
    }
    return $out;
}

function aco_sanitize_faq( $raw ) {
    $out = [];
    if ( is_string( $raw ) ) {
        $raw = json_decode( wp_unslash( $raw ), true );
    }
    if ( ! is_array( $raw ) ) {
        return [];
    }
    foreach ( $raw as $row ) {
        if ( ! is_array( $row ) ) {
            continue;
        }
        $q = sanitize_text_field( $row['q'] ?? '' );
        $a = sanitize_textarea_field( $row['a'] ?? '' );
        if ( $q === '' && $a === '' ) {
            continue;
        }
        $out[] = [
            'q' => $q !== '' ? $q : 'Вопрос',
            'a' => $a,
        ];
    }
    return $out;
}

function aco_get_course_program( $post_id ) {
    $val = get_post_meta( $post_id, '_aco_program', true );
    return is_array( $val ) ? $val : [];
}

function aco_get_course_benefits( $post_id ) {
    $val = get_post_meta( $post_id, '_aco_benefits', true );
    return is_array( $val ) ? $val : [];
}

function aco_get_course_faq( $post_id ) {
    $val = get_post_meta( $post_id, '_aco_faq', true );
    return is_array( $val ) ? $val : [];
}

function aco_get_course_preview_video_id( $post_id ) {
    return (int) get_post_meta( $post_id, '_aco_preview_video_id', true );
}

function aco_get_course_preview_video_url( $post_id ) {
    $id = aco_get_course_preview_video_id( $post_id );
    if ( $id < 1 ) {
        return '';
    }
    $url = wp_get_attachment_url( $id );
    return $url ? $url : '';
}

function aco_program_to_textarea( $program ) {
    $blocks = [];
    foreach ( (array) $program as $mod ) {
        $lines   = [ $mod['title'] ?? '' ];
        foreach ( (array) ( $mod['items'] ?? [] ) as $item ) {
            $lines[] = $item;
        }
        $blocks[] = implode( "\n", $lines );
    }
    return implode( "\n\n", $blocks );
}

function aco_program_from_textarea( $text ) {
    $out    = [];
    $chunks = preg_split( "/\n\s*\n/", (string) $text );
    foreach ( $chunks as $chunk ) {
        $lines = preg_split( "/\r\n|\n|\r/", trim( $chunk ) );
        $lines = array_values( array_filter( array_map( 'trim', $lines ), 'strlen' ) );
        if ( ! $lines ) {
            continue;
        }
        $title = array_shift( $lines );
        $out[] = [
            'title' => $title,
            'items' => $lines,
        ];
    }
    return aco_sanitize_program( $out );
}

function aco_benefits_to_textarea( $benefits ) {
    $lines = [];
    foreach ( (array) $benefits as $row ) {
        $lines[] = ( $row['title'] ?? '' ) . ' | ' . ( $row['text'] ?? '' );
    }
    return implode( "\n", $lines );
}

function aco_benefits_from_textarea( $text ) {
    $out = [];
    foreach ( preg_split( "/\r\n|\n|\r/", (string) $text ) as $line ) {
        $line = trim( $line );
        if ( $line === '' ) {
            continue;
        }
        $parts = array_map( 'trim', explode( '|', $line, 2 ) );
        $out[] = [
            'title' => $parts[0] ?? '',
            'text'  => $parts[1] ?? '',
        ];
    }
    return aco_sanitize_benefits( $out );
}

function aco_faq_to_textarea( $faq ) {
    $lines = [];
    foreach ( (array) $faq as $row ) {
        $lines[] = ( $row['q'] ?? '' ) . ' || ' . ( $row['a'] ?? '' );
    }
    return implode( "\n", $lines );
}

function aco_faq_from_textarea( $text ) {
    $out = [];
    foreach ( preg_split( "/\r\n|\n|\r/", (string) $text ) as $line ) {
        $line = trim( $line );
        if ( $line === '' ) {
            continue;
        }
        $parts = array_map( 'trim', explode( '||', $line, 2 ) );
        $out[] = [
            'q' => $parts[0] ?? '',
            'a' => $parts[1] ?? '',
        ];
    }
    return aco_sanitize_faq( $out );
}

function aco_moodle_fetch_live_courses() {
    if ( ! function_exists( 'aco_moodle_api_call' ) ) {
        return [];
    }

    $raw = aco_moodle_api_call( 'core_course_get_courses' );
    if ( is_array( $raw ) && isset( $raw['courses'] ) && is_array( $raw['courses'] ) ) {
        $raw = $raw['courses'];
    }
    if ( ! is_array( $raw ) || isset( $raw['exception'] ) ) {
        $raw = aco_moodle_api_call( 'core_course_get_courses_by_field', [ 'field' => '', 'value' => '' ] );
        if ( is_array( $raw ) && isset( $raw['courses'] ) ) {
            $raw = $raw['courses'];
        }
    }
    if ( ! is_array( $raw ) || isset( $raw['exception'] ) ) {
        return [];
    }

    $out = [];
    foreach ( $raw as $row ) {
        if ( ! is_array( $row ) ) {
            continue;
        }
        $id     = (int) ( $row['id'] ?? 0 );
        $format = (string) ( $row['format'] ?? '' );
        if ( $id < 2 || $format === 'site' ) {
            continue;
        }
        $name  = trim( (string) ( $row['fullname'] ?? $row['displayname'] ?? '' ) );
        $short = trim( (string) ( $row['shortname'] ?? '' ) );
        $label = $name !== '' ? $name : ( 'Курс #' . $id );
        if ( $short !== '' && strcasecmp( $short, $name ) !== 0 ) {
            $label .= ' · ' . $short;
        }
        $out[] = [
            'id'    => (string) $id,
            'label' => $label,
        ];
    }
    usort(
        $out,
        static function ( $a, $b ) {
            return strcasecmp( $a['label'], $b['label'] );
        }
    );
    return $out;
}

function aco_moodle_course_options() {
    $opts = aco_moodle_fetch_live_courses();
    if ( $opts ) {
        return $opts;
    }

    $opts       = [];
    $eb_courses = get_posts( [
        'post_type'      => 'eb_course',
        'post_status'    => [ 'publish', 'draft' ],
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
    ] );
    foreach ( $eb_courses as $eb_course ) {
        $mdl_id = get_post_meta( $eb_course->ID, 'moodle_course_id', true );
        if ( ! $mdl_id ) {
            continue;
        }
        $opts[] = [
            'id'    => (string) $mdl_id,
            'label' => $eb_course->post_title . ' (Moodle ID ' . $mdl_id . ')',
        ];
    }
    return $opts;
}

/**
 * Keep an Edwiser eb_course stub so paid enrolment still finds the Moodle course.
 *
 * @param int    $moodle_id
 * @param string $title
 * @return int WP post id or 0
 */
function aco_moodle_ensure_eb_course( $moodle_id, $title = '' ) {
    $moodle_id = absint( $moodle_id );
    if ( $moodle_id < 2 || ! post_type_exists( 'eb_course' ) ) {
        return 0;
    }
    if ( function_exists( 'app\wisdmlabs\edwiserBridge\wdm_eb_get_wp_course_id_from_moodle_course_id' ) ) {
        $existing = (int) \app\wisdmlabs\edwiserBridge\wdm_eb_get_wp_course_id_from_moodle_course_id( $moodle_id );
        if ( $existing ) {
            return $existing;
        }
    }
    if ( $title === '' ) {
        foreach ( aco_moodle_course_options() as $opt ) {
            if ( (int) $opt['id'] === $moodle_id ) {
                $title = $opt['label'];
                break;
            }
        }
    }
    if ( $title === '' ) {
        $title = 'Moodle #' . $moodle_id;
    }
    $post_id = wp_insert_post( [
        'post_type'   => 'eb_course',
        'post_status' => 'publish',
        'post_title'  => sanitize_text_field( $title ),
    ], true );
    if ( ! $post_id || is_wp_error( $post_id ) ) {
        return 0;
    }
    update_post_meta( $post_id, 'moodle_course_id', $moodle_id );
    return (int) $post_id;
}

function aco_save_course_landing_meta( $post_id, $program, $benefits, $faq, $video_id ) {
    update_post_meta( $post_id, '_aco_program', aco_sanitize_program( $program ) );
    update_post_meta( $post_id, '_aco_benefits', aco_sanitize_benefits( $benefits ) );
    update_post_meta( $post_id, '_aco_faq', aco_sanitize_faq( $faq ) );
    $video_id = absint( $video_id );
    if ( $video_id ) {
        update_post_meta( $post_id, '_aco_preview_video_id', $video_id );
    } else {
        delete_post_meta( $post_id, '_aco_preview_video_id' );
    }
}
