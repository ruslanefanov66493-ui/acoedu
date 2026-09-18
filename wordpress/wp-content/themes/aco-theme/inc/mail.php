<?php
/**
 * Transactional mail: SMTP, public-mailbox allowlist, OTP and inbound leads.
 *
 * @package aco-theme
 */

defined( 'ABSPATH' ) || exit;

/**
 * Constant first, then container env (SMTP_HOST, MAIL_INBOX, …).
 */
function aco_mail_setting( $constant, $env_key, $default = '' ) {
    if ( defined( $constant ) ) {
        $val = constant( $constant );
        if ( false !== $val && null !== $val && '' !== $val ) {
            return $val;
        }
    }
    $from_env = getenv( $env_key );
    if ( false !== $from_env && '' !== $from_env ) {
        return $from_env;
    }
    return $default;
}

function aco_mail_setting_bool( $constant, $env_key, $default = false ) {
    if ( defined( $constant ) ) {
        return (bool) constant( $constant );
    }
    $from_env = getenv( $env_key );
    if ( false === $from_env || '' === $from_env ) {
        return $default;
    }
    return filter_var( $from_env, FILTER_VALIDATE_BOOLEAN );
}

function aco_mail_setting_int( $constant, $env_key, $default = 0 ) {
    if ( defined( $constant ) && constant( $constant ) ) {
        return (int) constant( $constant );
    }
    $from_env = getenv( $env_key );
    if ( false !== $from_env && '' !== $from_env ) {
        return (int) $from_env;
    }
    return $default;
}

/* ── SMTP ───────────────────────────────────────────────────────────── */
add_action( 'phpmailer_init', 'aco_configure_smtp' );

function aco_configure_smtp( $phpmailer ) {
    $host = aco_mail_setting( 'ACO_SMTP_HOST', 'SMTP_HOST' );
    if ( ! $host ) {
        return;
    }

    $phpmailer->isSMTP();
    $phpmailer->Host        = $host;
    $phpmailer->Port        = aco_mail_setting_int( 'ACO_SMTP_PORT', 'SMTP_PORT', 587 );
    $phpmailer->SMTPAuth    = aco_mail_setting_bool( 'ACO_SMTP_AUTH', 'SMTP_AUTH', false );
    $phpmailer->SMTPSecure  = aco_mail_setting( 'ACO_SMTP_SECURE', 'SMTP_SECURE', '' );
    $phpmailer->SMTPAutoTLS = (bool) $phpmailer->SMTPSecure;
    $phpmailer->CharSet     = 'UTF-8';
    $phpmailer->Encoding    = 'base64';
    $phpmailer->Timeout     = 15;

    if ( $phpmailer->SMTPAuth ) {
        $phpmailer->Username = (string) aco_mail_setting( 'ACO_SMTP_USER', 'SMTP_USER' );
        $phpmailer->Password = (string) aco_mail_setting( 'ACO_SMTP_PASS', 'SMTP_PASS' );
    }
}

add_filter( 'wp_mail_from', function( $email ) {
    $from = aco_mail_setting( 'ACO_MAIL_FROM', 'MAIL_FROM' );
    return $from ? $from : $email;
} );

add_filter( 'wp_mail_from_name', function( $name ) {
    $from_name = aco_mail_setting( 'ACO_MAIL_FROM_NAME', 'MAIL_FROM_NAME' );
    return $from_name ? $from_name : $name;
} );

add_action( 'wp_mail_failed', function( $error ) {
    error_log( 'ACO mail failed: ' . $error->get_error_message() );
} );

/* ── Public mailbox allowlist ───────────────────────────────────────── */
function aco_allowed_email_domains() {
    $domains = [
        // Mail.ru
        'mail.ru', 'inbox.ru', 'list.ru', 'bk.ru', 'internet.ru',
        // Яндекс
        'yandex.ru', 'yandex.com', 'yandex.by', 'yandex.kz', 'yandex.ua', 'ya.ru',
        // Google
        'gmail.com', 'googlemail.com',
        // Microsoft
        'outlook.com', 'outlook.ru', 'hotmail.com', 'live.com', 'msn.com',
        // Apple
        'icloud.com', 'me.com', 'mac.com',
        // Rambler
        'rambler.ru', 'lenta.ru', 'autorambler.ru', 'myrambler.ru', 'ro.ru',
        // Yahoo / others
        'yahoo.com', 'ymail.com', 'proton.me', 'protonmail.com', 'mail.com',
        'gmx.com', 'ukr.net', 'i.ua',
    ];

    $from = aco_mail_setting( 'ACO_MAIL_FROM', 'MAIL_FROM' );
    if ( $from && is_email( $from ) ) {
        $domains[] = strtolower( substr( strrchr( $from, '@' ), 1 ) );
    }

    $domains = array_values( array_unique( array_filter( $domains ) ) );

    return apply_filters( 'aco_allowed_email_domains', $domains );
}

function aco_email_domain( $email ) {
    $email = strtolower( trim( (string) $email ) );
    $pos   = strrpos( $email, '@' );
    if ( false === $pos ) {
        return '';
    }
    return substr( $email, $pos + 1 );
}

function aco_is_public_email( $email ) {
    if ( ! is_email( $email ) ) {
        return false;
    }
    return in_array( aco_email_domain( $email ), aco_allowed_email_domains(), true );
}

function aco_public_email_error() {
    return 'Укажите почту популярного сервиса: Mail.ru, Яндекс, Gmail, Outlook, iCloud, Yahoo или Rambler';
}

function aco_email_field_hint() {
    return 'Mail.ru, Яндекс, Gmail, Outlook, iCloud и другие популярные сервисы';
}

/* ── Send helpers ───────────────────────────────────────────────────── */
function aco_mail_inbox( $kind = 'general' ) {
    if ( 'partners' === $kind ) {
        $partners = aco_mail_setting( 'ACO_MAIL_PARTNERS', 'MAIL_PARTNERS' );
        if ( $partners && is_email( $partners ) ) {
            return $partners;
        }
    }
    $inbox = aco_mail_setting( 'ACO_MAIL_INBOX', 'MAIL_INBOX' );
    if ( $inbox && is_email( $inbox ) ) {
        return $inbox;
    }
    return (string) get_option( 'admin_email' );
}

function aco_mail_wrap( $title, $inner_html ) {
    $title_esc = esc_html( $title );
    return '<div style="font-family:Inter,Segoe UI,Arial,sans-serif;font-size:16px;line-height:1.5;color:#111827;max-width:560px;margin:0 auto;padding:24px">'
        . '<p style="margin:0 0 8px;font-size:12px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#4F46E5">АЦО</p>'
        . '<h1 style="margin:0 0 20px;font-size:22px;line-height:1.3;color:#111827">' . $title_esc . '</h1>'
        . $inner_html
        . '<p style="margin:28px 0 0;font-size:13px;color:#6B7280">Письмо отправлено автоматически, отвечать на него не нужно.</p>'
        . '</div>';
}

function aco_send_mail( $to, $subject, $html, $reply_to = '' ) {
    $headers = [ 'Content-Type: text/html; charset=UTF-8' ];
    if ( $reply_to && is_email( $reply_to ) ) {
        $headers[] = 'Reply-To: ' . $reply_to;
    }

    $sent = wp_mail( $to, $subject, $html, $headers );
    if ( ! $sent ) {
        error_log( 'ACO mail not accepted by transport, to=' . $to . ' subject=' . $subject );
    }
    return (bool) $sent;
}

function aco_send_otp_mail( $email, $code, $type = 'register', $first_name = '' ) {
    $code = preg_replace( '/\D+/', '', (string) $code );
    $name = $first_name ? esc_html( $first_name ) : 'Здравствуйте';

    if ( 'recovery' === $type ) {
        $subject = 'Код для сброса пароля — АЦО';
        $intro   = '<p style="margin:0 0 16px">Ваш код для сброса пароля:</p>';
    } else {
        $subject = 'Код подтверждения регистрации — АЦО';
        $intro   = '<p style="margin:0 0 16px">' . $name . '!</p><p style="margin:0 0 16px">Ваш код для подтверждения регистрации:</p>';
    }

    $html = aco_mail_wrap(
        'Код подтверждения',
        $intro
        . '<p style="margin:0 0 16px;font-size:32px;letter-spacing:10px;font-weight:800;color:#3011dd">' . esc_html( $code ) . '</p>'
        . '<p style="margin:0;color:#4B5563">Код действует 15 минут. Если вы не запрашивали его — просто проигнорируйте письмо.</p>'
    );

    return aco_send_mail( $email, $subject, $html );
}

function aco_clear_mail_throttle( $email ) {
    delete_transient( 'aco_throttle_' . md5( $email ) );
}

/* ── Inbound leads (partners / contacts) ────────────────────────────── */
add_action( 'init', 'aco_register_lead_post_type' );

function aco_register_lead_post_type() {
    register_post_type( 'aco_lead', [
        'labels'              => [
            'name'          => 'Заявки',
            'singular_name' => 'Заявка',
            'add_new_item'  => 'Новая заявка',
            'edit_item'     => 'Заявка',
            'search_items'  => 'Искать заявки',
        ],
        'public'              => false,
        'show_ui'             => true,
        'show_in_menu'        => true,
        'menu_icon'           => 'dashicons-email-alt',
        'menu_position'       => 26,
        'supports'            => [ 'title', 'editor' ],
        'capability_type'     => 'page',
        'exclude_from_search' => true,
        'rewrite'             => false,
    ] );
}

add_filter( 'manage_aco_lead_posts_columns', function( $columns ) {
    $date = $columns['date'] ?? '';
    unset( $columns['date'] );
    $columns['aco_lead_type']  = 'Тип';
    $columns['aco_lead_email'] = 'Email';
    $columns['aco_lead_phone'] = 'Телефон';
    if ( $date ) {
        $columns['date'] = $date;
    }
    return $columns;
} );

add_action( 'manage_aco_lead_posts_custom_column', function( $column, $post_id ) {
    if ( 'aco_lead_type' === $column ) {
        echo 'partners' === get_post_meta( $post_id, '_aco_lead_type', true ) ? 'Партнёры' : 'Контакты';
    }
    if ( 'aco_lead_email' === $column ) {
        echo esc_html( (string) get_post_meta( $post_id, '_aco_lead_email', true ) );
    }
    if ( 'aco_lead_phone' === $column ) {
        echo esc_html( (string) get_post_meta( $post_id, '_aco_lead_phone', true ) );
    }
}, 10, 2 );

function aco_store_lead( $type, $data ) {
    $title = trim( ( $data['company'] ?? '' ) . ' — ' . ( $data['name'] ?? '' ), ' —' );
    if ( ! $title ) {
        $title = $data['email'] ?? 'Заявка';
    }

    $body = sprintf(
        "Имя: %s\nEmail: %s\nТелефон: %s\nКомпания: %s\n\nСообщение:\n%s",
        $data['name'] ?? '',
        $data['email'] ?? '',
        $data['phone'] ?? '—',
        $data['company'] ?? '—',
        $data['message'] ?? ''
    );

    $post_id = wp_insert_post( [
        'post_type'    => 'aco_lead',
        'post_status'  => 'private',
        'post_title'   => wp_strip_all_tags( $title ),
        'post_content' => $body,
    ], true );

    if ( is_wp_error( $post_id ) || ! $post_id ) {
        return 0;
    }

    update_post_meta( $post_id, '_aco_lead_type', $type );
    update_post_meta( $post_id, '_aco_lead_email', $data['email'] ?? '' );
    update_post_meta( $post_id, '_aco_lead_phone', $data['phone'] ?? '' );
    update_post_meta( $post_id, '_aco_lead_company', $data['company'] ?? '' );

    return (int) $post_id;
}

function aco_notify_lead( $type, $data ) {
    $kind    = 'partners' === $type ? 'partners' : 'general';
    $to      = aco_mail_inbox( $kind );
    $is_part = 'partners' === $type;
    $subject = $is_part
        ? sprintf( 'Заявка на партнёрство: %s (%s)', $data['name'], $data['company'] )
        : sprintf( 'Сообщение с сайта АЦО от %s', $data['name'] );

    $rows = '<p style="margin:0 0 12px"><strong>Имя:</strong> ' . esc_html( $data['name'] ) . '</p>'
        . '<p style="margin:0 0 12px"><strong>Email:</strong> ' . esc_html( $data['email'] ) . '</p>';
    if ( ! empty( $data['phone'] ) ) {
        $rows .= '<p style="margin:0 0 12px"><strong>Телефон:</strong> ' . esc_html( $data['phone'] ) . '</p>';
    }
    if ( ! empty( $data['company'] ) ) {
        $rows .= '<p style="margin:0 0 12px"><strong>Компания:</strong> ' . esc_html( $data['company'] ) . '</p>';
    }
    $rows .= '<p style="margin:16px 0 0;white-space:pre-wrap">' . esc_html( $data['message'] ) . '</p>';

    $html = aco_mail_wrap( $is_part ? 'Заявка на партнёрство' : 'Сообщение с сайта', $rows );

    return aco_send_mail( $to, $subject, $html, $data['email'] );
}

function aco_form_status_notice( $query_key ) {
    $status = isset( $_GET[ $query_key ] ) ? sanitize_key( wp_unslash( $_GET[ $query_key ] ) ) : '';
    if ( ! $status ) {
        return;
    }

    $map = [
        'sent'    => [ 'ok', 'Заявка отправлена. Мы свяжемся с вами по указанной почте.' ],
        'missing' => [ 'err', 'Заполните все поля формы.' ],
        'email'   => [ 'err', aco_public_email_error() ],
        'error'   => [ 'err', 'Не удалось отправить заявку. Попробуйте ещё раз.' ],
    ];
    if ( empty( $map[ $status ] ) ) {
        return;
    }

    printf(
        '<div class="lh-form-notice lh-form-notice--%s" role="status">%s</div>',
        esc_attr( $map[ $status ][0] ),
        esc_html( $map[ $status ][1] )
    );
}

add_action( 'admin_post_aco_contact_form', 'aco_handle_contact_form' );
add_action( 'admin_post_nopriv_aco_contact_form', 'aco_handle_contact_form' );

function aco_handle_contact_form() {
    if ( ! isset( $_POST['aco_nonce'] ) || ! wp_verify_nonce( $_POST['aco_nonce'], 'aco_contact' ) ) {
        wp_safe_redirect( add_query_arg( 'contact', 'error', wp_get_referer() ) );
        exit;
    }

    $name    = sanitize_text_field( wp_unslash( $_POST['cf_name'] ?? '' ) );
    $email   = sanitize_email( wp_unslash( $_POST['cf_email'] ?? '' ) );
    $message = sanitize_textarea_field( wp_unslash( $_POST['cf_message'] ?? '' ) );

    if ( ! $name || ! $email || ! $message ) {
        wp_safe_redirect( add_query_arg( 'contact', 'missing', wp_get_referer() ) );
        exit;
    }
    if ( ! aco_is_public_email( $email ) ) {
        wp_safe_redirect( add_query_arg( 'contact', 'email', wp_get_referer() ) );
        exit;
    }

    $data = [
        'name'    => $name,
        'email'   => $email,
        'message' => $message,
    ];

    if ( ! aco_store_lead( 'contact', $data ) ) {
        wp_safe_redirect( add_query_arg( 'contact', 'error', wp_get_referer() ) );
        exit;
    }

    aco_notify_lead( 'contact', $data );

    wp_safe_redirect( add_query_arg( 'contact', 'sent', wp_get_referer() ) );
    exit;
}

add_action( 'admin_post_aco_partner_form', 'aco_handle_partner_form' );
add_action( 'admin_post_nopriv_aco_partner_form', 'aco_handle_partner_form' );

function aco_handle_partner_form() {
    if ( ! isset( $_POST['aco_partner_nonce'] ) || ! wp_verify_nonce( $_POST['aco_partner_nonce'], 'aco_partner' ) ) {
        wp_safe_redirect( add_query_arg( 'partner', 'error', wp_get_referer() ) );
        exit;
    }

    $name    = sanitize_text_field( wp_unslash( $_POST['pf_name'] ?? '' ) );
    $email   = sanitize_email( wp_unslash( $_POST['pf_email'] ?? '' ) );
    $phone   = sanitize_text_field( wp_unslash( $_POST['pf_phone'] ?? '' ) );
    $company = sanitize_text_field( wp_unslash( $_POST['pf_company'] ?? '' ) );
    $message = sanitize_textarea_field( wp_unslash( $_POST['pf_message'] ?? '' ) );

    if ( ! $name || ! $email || ! $phone || ! $company || ! $message ) {
        wp_safe_redirect( add_query_arg( 'partner', 'missing', wp_get_referer() ) );
        exit;
    }
    if ( ! aco_is_public_email( $email ) ) {
        wp_safe_redirect( add_query_arg( 'partner', 'email', wp_get_referer() ) );
        exit;
    }

    $data = [
        'name'    => $name,
        'email'   => $email,
        'phone'   => $phone,
        'company' => $company,
        'message' => $message,
    ];

    if ( ! aco_store_lead( 'partners', $data ) ) {
        wp_safe_redirect( add_query_arg( 'partner', 'error', wp_get_referer() ) );
        exit;
    }

    aco_notify_lead( 'partners', $data );

    wp_safe_redirect( add_query_arg( 'partner', 'sent', wp_get_referer() ) );
    exit;
}
