<?php
/**
 * Phone as a second login/register channel. SMS is stubbed until SMS_PROVIDER is set.
 *
 * @package aco-theme
 */

defined( 'ABSPATH' ) || exit;

const ACO_PHONE_EMAIL_DOMAIN = 'phone.acoedu.invalid';

function aco_sms_provider() {
	$provider = '';
	if ( defined( 'SMS_PROVIDER' ) ) {
		$provider = (string) SMS_PROVIDER;
	} elseif ( getenv( 'SMS_PROVIDER' ) ) {
		$provider = (string) getenv( 'SMS_PROVIDER' );
	}
	$provider = strtolower( trim( $provider ) );
	return $provider !== '' ? $provider : 'stub';
}

function aco_sms_is_stub() {
	return aco_sms_provider() === 'stub';
}

/**
 * +7 and 10 digits, Russian mobile (9xx).
 *
 * @return string E.164 or empty.
 */
function aco_normalize_phone( $raw ) {
	$digits = preg_replace( '/\D+/', '', (string) $raw );
	if ( strlen( $digits ) === 11 && ( $digits[0] === '7' || $digits[0] === '8' ) ) {
		$digits = substr( $digits, 1 );
	}
	if ( strlen( $digits ) !== 10 || $digits[0] !== '9' ) {
		return '';
	}
	return '+7' . $digits;
}

function aco_phone_placeholder_email( $phone ) {
	$digits = preg_replace( '/\D+/', '', (string) $phone );
	return 'u' . $digits . '@' . ACO_PHONE_EMAIL_DOMAIN;
}

function aco_is_placeholder_email( $email ) {
	$email = strtolower( trim( (string) $email ) );
	return $email !== '' && str_ends_with( $email, '@' . ACO_PHONE_EMAIL_DOMAIN );
}

function aco_user_real_email( $user ) {
	if ( ! $user instanceof WP_User ) {
		return '';
	}
	$email = (string) $user->user_email;
	if ( $email === '' || aco_is_placeholder_email( $email ) || ! is_email( $email ) ) {
		return '';
	}
	return $email;
}

function aco_user_by_phone( $phone ) {
	$phone = aco_normalize_phone( $phone );
	if ( $phone === '' ) {
		return null;
	}
	$users = get_users( [
		'meta_key'   => 'aco_phone',
		'meta_value' => $phone,
		'number'     => 1,
	] );
	return $users ? $users[0] : null;
}

function aco_user_by_login( $raw ) {
	$raw = trim( (string) $raw );
	if ( $raw === '' ) {
		return null;
	}
	if ( str_contains( $raw, '@' ) ) {
		$user = get_user_by( 'email', sanitize_email( $raw ) );
		return $user instanceof WP_User ? $user : null;
	}
	return aco_user_by_phone( $raw );
}

function aco_phone_taken( $phone, $except_user_id = 0 ) {
	$user = aco_user_by_phone( $phone );
	if ( ! $user ) {
		return false;
	}
	return (int) $user->ID !== (int) $except_user_id;
}

function aco_check_phone_rate_limit( $phone, $seconds = 60 ) {
	$phone = aco_normalize_phone( $phone );
	$key   = 'aco_sms_' . md5( $phone );
	if ( get_transient( $key ) ) {
		return false;
	}
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : '';
	$ip_key = 'aco_sms_ip_' . md5( $ip );
	$hits   = (int) get_transient( $ip_key );
	if ( $hits >= 8 ) {
		return false;
	}
	set_transient( $key, 1, $seconds );
	set_transient( $ip_key, $hits + 1, HOUR_IN_SECONDS );
	return true;
}

function aco_clear_phone_throttle( $phone ) {
	delete_transient( 'aco_sms_' . md5( aco_normalize_phone( $phone ) ) );
}

function aco_sms_log_code( $phone, $code, $purpose ) {
	$log   = get_option( 'aco_sms_stub_log', [] );
	$log   = is_array( $log ) ? $log : [];
	$log[] = [
		'phone'   => $phone,
		'code'    => (string) $code,
		'purpose' => (string) $purpose,
		'at'      => time(),
	];
	$log = array_slice( $log, -20 );
	update_option( 'aco_sms_stub_log', $log, false );
}

function aco_sms_stub_log() {
	$log = get_option( 'aco_sms_stub_log', [] );
	return is_array( $log ) ? array_reverse( $log ) : [];
}

/**
 * @return bool True when the code was accepted for delivery (stub always succeeds).
 */
function aco_send_sms( $phone, $text, $code = '', $purpose = '' ) {
	$phone = aco_normalize_phone( $phone );
	if ( $phone === '' ) {
		return false;
	}
	if ( aco_sms_is_stub() ) {
		aco_sms_log_code( $phone, $code !== '' ? $code : $text, $purpose );
		return true;
	}
	// Real providers plug in here via SMS_PROVIDER without changing callers.
	return (bool) apply_filters( 'aco_send_sms', false, $phone, $text, aco_sms_provider() );
}

function aco_issue_phone_code( $phone, $purpose, $extra = [] ) {
	$phone = aco_normalize_phone( $phone );
	$code  = (string) wp_rand( 100000, 999999 );
	set_transient( 'aco_phone_code_' . md5( $phone . '|' . $purpose ), [
		'code'  => $code,
		'extra' => $extra,
	], 15 * MINUTE_IN_SECONDS );
	$sent = aco_send_sms( $phone, 'Код АЦО: ' . $code, $code, $purpose );
	if ( ! $sent ) {
		delete_transient( 'aco_phone_code_' . md5( $phone . '|' . $purpose ) );
		aco_clear_phone_throttle( $phone );
		return false;
	}
	return true;
}

function aco_take_phone_code( $phone, $purpose, $code ) {
	$phone = aco_normalize_phone( $phone );
	$key   = 'aco_phone_code_' . md5( $phone . '|' . $purpose );
	$data  = get_transient( $key );
	$code  = preg_replace( '/\D+/', '', (string) $code );
	if ( ! is_array( $data ) || (string) $data['code'] !== (string) $code ) {
		return null;
	}
	delete_transient( $key );
	return $data;
}

/* ── Register by phone ──────────────────────────────────────────────── */

add_action( 'wp_ajax_nopriv_aco_ajax_phone_register', 'aco_ajax_phone_register' );
add_action( 'wp_ajax_aco_ajax_phone_register', 'aco_ajax_phone_register' );

function aco_ajax_phone_register() {
	check_ajax_referer( 'aco_register', 'aco_register_nonce' );

	$first_name = sanitize_text_field( wp_unslash( $_POST['first_name'] ?? '' ) );
	$phone      = aco_normalize_phone( wp_unslash( $_POST['phone'] ?? '' ) );
	$password   = (string) ( $_POST['password'] ?? '' );

	if ( ! $first_name || ! $phone || ! $password ) {
		wp_send_json_error( 'Укажите имя, мобильный номер +7 и пароль' );
	}
	if ( empty( $_POST['agree_terms'] ) ) {
		wp_send_json_error( 'Необходимо принять публичную оферту и дать согласие на обработку персональных данных' );
	}
	if ( strlen( $password ) < 8 ) {
		wp_send_json_error( 'Пароль должен содержать минимум 8 символов' );
	}
	if ( aco_phone_taken( $phone ) ) {
		wp_send_json_error( 'Этот номер уже привязан к аккаунту. Войдите или восстановите пароль' );
	}
	if ( ! aco_check_phone_rate_limit( $phone ) ) {
		wp_send_json_error( 'Код уже отправлен. Подождите минуту перед повторной отправкой' );
	}

	$ok = aco_issue_phone_code( $phone, 'register', [
		'first_name' => $first_name,
		'password'   => $password,
	] );
	if ( ! $ok ) {
		wp_send_json_error( 'Не удалось отправить код. Попробуйте позже' );
	}
	wp_send_json_success( [ 'phone' => $phone ] );
}

add_filter( 'eb_disable_checkout_user_creation', 'aco_skip_edwiser_on_phone_register', 20 );

function aco_skip_edwiser_on_phone_register( $disable ) {
	if ( wp_doing_ajax() && isset( $_POST['action'] ) && 'aco_ajax_phone_verify_register' === $_POST['action'] ) {
		return true;
	}
	return $disable;
}

add_action( 'wp_ajax_nopriv_aco_ajax_phone_verify_register', 'aco_ajax_phone_verify_register' );
add_action( 'wp_ajax_aco_ajax_phone_verify_register', 'aco_ajax_phone_verify_register' );

function aco_ajax_phone_verify_register() {
	check_ajax_referer( 'aco_verify_code', 'aco_verify_nonce' );

	$phone = aco_normalize_phone( wp_unslash( $_POST['phone'] ?? '' ) );
	$code  = (string) ( $_POST['code'] ?? '' );
	$data  = aco_take_phone_code( $phone, 'register', $code );
	if ( ! $data ) {
		wp_send_json_error( 'Неверный или устаревший код' );
	}
	if ( aco_phone_taken( $phone ) ) {
		wp_send_json_error( 'Этот номер уже зарегистрирован' );
	}

	if ( function_exists( 'aco_unhook_edwiser_register_sync' ) ) {
		aco_unhook_edwiser_register_sync();
	}

	$extra    = (array) ( $data['extra'] ?? [] );
	$username = 'p' . preg_replace( '/\D+/', '', $phone );
	if ( username_exists( $username ) ) {
		$username .= wp_generate_password( 3, false );
	}
	$user_id = wp_insert_user( [
		'user_login'   => $username,
		'user_pass'    => (string) ( $extra['password'] ?? '' ),
		'user_email'   => aco_phone_placeholder_email( $phone ),
		'first_name'   => (string) ( $extra['first_name'] ?? '' ),
		'display_name' => (string) ( $extra['first_name'] ?? '' ),
		'role'         => 'customer',
	] );
	if ( is_wp_error( $user_id ) ) {
		wp_send_json_error( $user_id->get_error_message() );
	}

	update_user_meta( $user_id, 'aco_phone', $phone );
	update_user_meta( $user_id, 'billing_phone', $phone );

	$user = get_user_by( 'id', $user_id );
	wp_set_current_user( $user_id, $user->user_login );
	wp_set_auth_cookie( $user_id, true, is_ssl() );
	do_action( 'wp_login', $user->user_login, $user );

	$redirect = home_url( '/account/?tab=profile' );
	if ( function_exists( 'aco_safe_requested_redirect' ) ) {
		$redirect = aco_safe_requested_redirect( $redirect );
	}
	wp_send_json_success( [ 'redirect' => $redirect ] );
}

/* ── Password recovery by phone ─────────────────────────────────────── */

add_action( 'wp_ajax_nopriv_aco_ajax_phone_send_code', 'aco_ajax_phone_send_code' );
add_action( 'wp_ajax_aco_ajax_phone_send_code', 'aco_ajax_phone_send_code' );

function aco_ajax_phone_send_code() {
	check_ajax_referer( 'aco_recovery', 'aco_recovery_nonce' );
	$phone = aco_normalize_phone( wp_unslash( $_POST['phone'] ?? '' ) );
	if ( $phone === '' ) {
		wp_send_json_error( 'Укажите мобильный номер в формате +7' );
	}
	// Same reply whether the number exists, so we don't leak accounts.
	if ( ! aco_user_by_phone( $phone ) ) {
		wp_send_json_success( [ 'phone' => $phone ] );
	}
	if ( ! aco_check_phone_rate_limit( $phone ) ) {
		wp_send_json_error( 'Код уже отправлен. Подождите минуту перед повторной отправкой' );
	}
	if ( ! aco_issue_phone_code( $phone, 'recovery' ) ) {
		wp_send_json_error( 'Не удалось отправить код. Попробуйте позже' );
	}
	wp_send_json_success( [ 'phone' => $phone ] );
}

add_action( 'wp_ajax_nopriv_aco_ajax_phone_verify_code', 'aco_ajax_phone_verify_code' );
add_action( 'wp_ajax_aco_ajax_phone_verify_code', 'aco_ajax_phone_verify_code' );

function aco_ajax_phone_verify_code() {
	check_ajax_referer( 'aco_verify_code', 'aco_verify_nonce' );
	$phone = aco_normalize_phone( wp_unslash( $_POST['phone'] ?? '' ) );
	$data  = get_transient( 'aco_phone_code_' . md5( $phone . '|recovery' ) );
	$code  = preg_replace( '/\D+/', '', (string) ( $_POST['code'] ?? '' ) );
	if ( ! is_array( $data ) || (string) $data['code'] !== (string) $code || ! aco_user_by_phone( $phone ) ) {
		wp_send_json_error( 'Неверный или устаревший код' );
	}
	delete_transient( 'aco_phone_code_' . md5( $phone . '|recovery' ) );
	$token = wp_generate_password( 32, false );
	set_transient( 'aco_reset_token_' . md5( $phone ), $token, 15 * MINUTE_IN_SECONDS );
	wp_send_json_success( [ 'token' => $token, 'phone' => $phone ] );
}

/* ── Profile tab ────────────────────────────────────────────────────── */

function aco_require_profile_user() {
	if ( ! is_user_logged_in() ) {
		wp_send_json_error( 'Войдите в кабинет' );
	}
	return wp_get_current_user();
}

add_action( 'wp_ajax_aco_ajax_profile_save', 'aco_ajax_profile_save' );

function aco_ajax_profile_save() {
	check_ajax_referer( 'aco_profile', 'aco_profile_nonce' );
	$user  = aco_require_profile_user();
	$first = sanitize_text_field( wp_unslash( $_POST['first_name'] ?? '' ) );
	$last  = sanitize_text_field( wp_unslash( $_POST['last_name'] ?? '' ) );
	if ( $first === '' ) {
		wp_send_json_error( 'Укажите имя' );
	}
	wp_update_user( [
		'ID'           => $user->ID,
		'first_name'   => $first,
		'last_name'    => $last,
		'display_name' => trim( $first . ' ' . $last ),
	] );
	update_user_meta( $user->ID, 'billing_first_name', $first );
	update_user_meta( $user->ID, 'billing_last_name', $last );
	wp_send_json_success( [ 'message' => 'Данные сохранены' ] );
}

add_action( 'wp_ajax_aco_ajax_profile_password', 'aco_ajax_profile_password' );

function aco_ajax_profile_password() {
	check_ajax_referer( 'aco_profile', 'aco_profile_nonce' );
	$user = aco_require_profile_user();
	$old  = (string) ( $_POST['old_password'] ?? '' );
	$new  = (string) ( $_POST['password'] ?? '' );
	if ( strlen( $new ) < 8 ) {
		wp_send_json_error( 'Новый пароль — минимум 8 символов' );
	}
	if ( ! wp_check_password( $old, $user->user_pass, $user->ID ) ) {
		wp_send_json_error( 'Текущий пароль неверный' );
	}
	wp_set_password( $new, $user->ID );
	wp_set_auth_cookie( $user->ID, true, is_ssl() );
	wp_send_json_success( [ 'message' => 'Пароль изменён' ] );
}

add_action( 'wp_ajax_aco_ajax_profile_phone_send', 'aco_ajax_profile_phone_send' );

function aco_ajax_profile_phone_send() {
	check_ajax_referer( 'aco_profile', 'aco_profile_nonce' );
	$user  = aco_require_profile_user();
	$phone = aco_normalize_phone( wp_unslash( $_POST['phone'] ?? '' ) );
	if ( $phone === '' ) {
		wp_send_json_error( 'Укажите мобильный номер в формате +7' );
	}
	if ( aco_phone_taken( $phone, $user->ID ) ) {
		wp_send_json_error( 'Этот номер уже привязан к другому аккаунту' );
	}
	if ( ! aco_check_phone_rate_limit( $phone ) ) {
		wp_send_json_error( 'Код уже отправлен. Подождите минуту' );
	}
	if ( ! aco_issue_phone_code( $phone, 'bind_' . $user->ID ) ) {
		wp_send_json_error( 'Не удалось отправить код' );
	}
	wp_send_json_success( [ 'phone' => $phone ] );
}

add_action( 'wp_ajax_aco_ajax_profile_phone_confirm', 'aco_ajax_profile_phone_confirm' );

function aco_ajax_profile_phone_confirm() {
	check_ajax_referer( 'aco_profile', 'aco_profile_nonce' );
	$user  = aco_require_profile_user();
	$phone = aco_normalize_phone( wp_unslash( $_POST['phone'] ?? '' ) );
	$data  = aco_take_phone_code( $phone, 'bind_' . $user->ID, (string) ( $_POST['code'] ?? '' ) );
	if ( ! $data ) {
		wp_send_json_error( 'Неверный или устаревший код' );
	}
	if ( aco_phone_taken( $phone, $user->ID ) ) {
		wp_send_json_error( 'Этот номер уже занят' );
	}
	update_user_meta( $user->ID, 'aco_phone', $phone );
	update_user_meta( $user->ID, 'billing_phone', $phone );
	wp_send_json_success( [ 'message' => 'Номер привязан', 'phone' => $phone ] );
}

add_action( 'wp_ajax_aco_ajax_profile_email_send', 'aco_ajax_profile_email_send' );

function aco_ajax_profile_email_send() {
	check_ajax_referer( 'aco_profile', 'aco_profile_nonce' );
	$user  = aco_require_profile_user();
	$email = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
	$current = strtolower( aco_user_real_email( $user ) );
	if ( $current !== '' && strtolower( $email ) === $current ) {
		wp_send_json_success( [ 'message' => 'Эта почта уже подтверждена', 'unchanged' => true ] );
	}
	if ( ! is_email( $email ) || ( function_exists( 'aco_is_public_email' ) && ! aco_is_public_email( $email ) ) ) {
		wp_send_json_error( function_exists( 'aco_public_email_error' ) ? aco_public_email_error() : 'Укажите почту' );
	}
	$owner = email_exists( $email );
	if ( $owner && (int) $owner !== (int) $user->ID ) {
		wp_send_json_error( 'Эта почта уже используется' );
	}
	if ( function_exists( 'aco_check_rate_limit' ) && ! aco_check_rate_limit( $email ) ) {
		wp_send_json_error( 'Код уже отправлен. Подождите минуту' );
	}
	$code = (string) wp_rand( 100000, 999999 );
	set_transient( 'aco_mail_bind_' . $user->ID, [ 'email' => $email, 'code' => $code ], 15 * MINUTE_IN_SECONDS );
	if ( ! function_exists( 'aco_send_otp_mail' ) || ! aco_send_otp_mail( $email, $code, 'register', $user->first_name ) ) {
		delete_transient( 'aco_mail_bind_' . $user->ID );
		wp_send_json_error( 'Не удалось отправить письмо' );
	}
	wp_send_json_success( [] );
}

add_action( 'wp_ajax_aco_ajax_profile_email_confirm', 'aco_ajax_profile_email_confirm' );

function aco_ajax_profile_email_confirm() {
	check_ajax_referer( 'aco_profile', 'aco_profile_nonce' );
	$user = aco_require_profile_user();
	$data = get_transient( 'aco_mail_bind_' . $user->ID );
	$code = preg_replace( '/\D+/', '', (string) ( $_POST['code'] ?? '' ) );
	if ( ! is_array( $data ) || (string) $data['code'] !== (string) $code ) {
		wp_send_json_error( 'Неверный или устаревший код' );
	}
	$email = sanitize_email( $data['email'] );
	$owner = email_exists( $email );
	if ( $owner && (int) $owner !== (int) $user->ID ) {
		wp_send_json_error( 'Эта почта уже используется' );
	}
	wp_update_user( [ 'ID' => $user->ID, 'user_email' => $email ] );
	update_user_meta( $user->ID, 'billing_email', $email );
	delete_transient( 'aco_mail_bind_' . $user->ID );
	if ( function_exists( 'aco_eb_available' ) && aco_eb_available() && function_exists( 'aco_moodle_ensure_user' ) ) {
		aco_moodle_ensure_user( $user->ID );
	}
	wp_send_json_success( [ 'message' => 'Почта сохранена', 'email' => $email ] );
}

/* ── Checkout copies the receipt email onto a phone-only account ───── */

add_filter( 'woocommerce_checkout_get_value', 'aco_checkout_hide_placeholder_email', 10, 2 );

function aco_checkout_hide_placeholder_email( $value, $input ) {
	if ( $input !== 'billing_email' || ! is_user_logged_in() ) {
		return $value;
	}
	$user = wp_get_current_user();
	if ( aco_user_real_email( $user ) === '' ) {
		return '';
	}
	return $value;
}

add_action( 'woocommerce_checkout_order_processed', 'aco_checkout_attach_email', 20, 1 );

function aco_checkout_attach_email( $order_id ) {
	if ( ! function_exists( 'wc_get_order' ) ) {
		return;
	}
	$order = wc_get_order( $order_id );
	if ( ! $order ) {
		return;
	}
	$user_id = (int) $order->get_user_id();
	if ( $user_id < 1 ) {
		return;
	}
	$user = get_user_by( 'id', $user_id );
	if ( ! $user || aco_user_real_email( $user ) !== '' ) {
		return;
	}
	$email = sanitize_email( $order->get_billing_email() );
	if ( ! is_email( $email ) ) {
		return;
	}
	$owner = email_exists( $email );
	if ( $owner && (int) $owner !== $user_id ) {
		$order->add_order_note( 'Почта заказа уже занята другим аккаунтом, в профиль не записана.' );
		return;
	}
	wp_update_user( [ 'ID' => $user_id, 'user_email' => $email ] );
	update_user_meta( $user_id, 'billing_email', $email );
	if ( function_exists( 'aco_eb_available' ) && aco_eb_available() && function_exists( 'aco_moodle_ensure_user' ) ) {
		aco_moodle_ensure_user( $user_id );
	}
}
