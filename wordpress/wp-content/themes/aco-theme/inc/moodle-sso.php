<?php
/**
 * Moodle SSO (single sign-on) via Edwiser Bridge companion auth plugin.
 *
 * Free Edwiser Bridge does not ship the WP side of SSO (it is a Pro module),
 * but the Moodle companion plugin (auth_edwiserbridge) implements the full
 * receiver: it accepts an encrypted payload, stores a one-time hash in a user
 * preference and logs the user in when the browser follows the login URL.
 * This file implements the WP side of that protocol.
 *
 * Protocol (mirrors auth/edwiserbridge/login.php on the Moodle side):
 *   1. WP POSTs wdm_data = urlsafe_b64( AES-256-ECB( query, sha256(secret) ) )
 *      to {moodle}/auth/edwiserbridge/login.php — Moodle stores it.
 *   2. Browser opens {moodle}/auth/edwiserbridge/login.php?login_id=X&veridy_code=H
 *      — Moodle verifies the one-time hash and completes the login.
 *
 * @package aco-theme
 */

defined( 'ABSPATH' ) || exit;

/**
 * Base Moodle URL from the Edwiser Bridge connection settings.
 *
 * @return string URL without trailing slash, empty string when not configured.
 */
function aco_moodle_url() {
	if ( function_exists( 'app\\wisdmlabs\\edwiserBridge\\wdm_eb_get_moodle_url' ) ) {
		$url = \app\wisdmlabs\edwiserBridge\wdm_eb_get_moodle_url();
		if ( $url && 'MOODLE_URL' !== $url ) {
			return rtrim( $url, '/' );
		}
	}
	return '';
}

/**
 * Encrypt exactly the way auth_edwiserbridge_decrypt_string() expects.
 *
 * Moodle does base64_decode() on the payload and then openssl_decrypt()
 * WITHOUT OPENSSL_RAW_DATA, which base64-decodes its input a second time.
 * The payload must therefore be double base64-encoded:
 * urlsafe_b64( b64( AES-256-ECB( plain, sha256(secret) ) ) ).
 *
 * @param string $plain  Plaintext (http_build_query payload).
 * @param string $secret Shared secret (auth_edwiserbridge/sharedsecret in Moodle).
 * @return string URL-safe base64 ciphertext.
 */
function aco_moodle_sso_encrypt( $plain, $secret ) {
	$key       = hash( 'sha256', $secret, true );
	$encrypted = openssl_encrypt( $plain, 'AES-256-ECB', $key );
	if ( false === $encrypted ) {
		return '';
	}
	return rtrim( strtr( base64_encode( $encrypted ), '+/', '-_' ), '=' );
}

/**
 * Build a one-time Moodle login URL for the given WP user.
 *
 * @param int    $user_id WP user ID.
 * @param string $redirect Absolute Moodle URL to land on after login (e.g. course page).
 * @return string One-time login URL, empty string on failure.
 */
function aco_moodle_sso_build_url( $user_id, $redirect = '' ) {
	$moodle_uid = (int) get_user_meta( $user_id, 'moodle_user_id', true );
	$secret     = get_option( 'aco_eb_sso_secret' );
	$moodle_url = aco_moodle_url();

	if ( ! $moodle_uid || ! $secret || ! $moodle_url ) {
		return '';
	}

	$hash     = md5( wp_rand( 10, 1000 ) . microtime() );
	$wdm_data = aco_moodle_sso_encrypt(
		http_build_query(
			array(
				'moodle_user_id'   => $moodle_uid,
				'login_redirect'   => $redirect,
				'wp_one_time_hash' => $hash,
			)
		),
		$secret
	);

	if ( ! $wdm_data ) {
		return '';
	}

	$response = wp_remote_post(
		$moodle_url . '/auth/edwiserbridge/login.php',
		array(
			'body'    => array( 'wdm_data' => $wdm_data ),
			'timeout' => 15,
		)
	);

	if ( is_wp_error( $response ) ) {
		return '';
	}

	return $moodle_url . '/auth/edwiserbridge/login.php?login_id=' . $moodle_uid . '&veridy_code=' . $hash;
}

/**
 * Browser endpoint: /?aco_moodle_sso=1[&redirect=<moodle url>]
 * Logs the current WP user into Moodle and lands them on the requested page.
 */
function aco_moodle_sso_endpoint() {
	if ( empty( $_GET['aco_moodle_sso'] ) ) {
		return;
	}

	if ( ! is_user_logged_in() ) {
		wp_safe_redirect( home_url( '/login/' ) );
		exit;
	}

	$moodle_url = aco_moodle_url();
	$redirect   = $moodle_url ? $moodle_url . '/my/' : home_url( '/' );

	// Only allow redirects back to the Moodle host (open-redirect guard).
	if ( ! empty( $_GET['redirect'] ) && $moodle_url ) {
		$candidate   = esc_url_raw( wp_unslash( $_GET['redirect'] ) );
		$moodle_host = wp_parse_url( $moodle_url, PHP_URL_HOST );
		if ( $moodle_host && wp_parse_url( $candidate, PHP_URL_HOST ) === $moodle_host ) {
			$redirect = $candidate;
		}
	}

	$sso_url = aco_moodle_sso_build_url( get_current_user_id(), $redirect );

	// Fallback: plain Moodle URL (user will see the Moodle login form).
	wp_redirect( $sso_url ? $sso_url : $redirect );
	exit;
}
add_action( 'template_redirect', 'aco_moodle_sso_endpoint' );

/**
 * Moodle Edwiser logout lands on ?wdmaction=logout&mdl_uid=X.
 * Free Edwiser has no WP receiver, so the teacher stays logged in here
 * and /login/ immediately SSO-s them back to the Moodle cabinet.
 */
function aco_edwiser_logout_bridge() {
	if ( empty( $_GET['wdmaction'] ) || 'logout' !== sanitize_key( wp_unslash( $_GET['wdmaction'] ) ) ) {
		return;
	}

	$mdl_uid = isset( $_GET['mdl_uid'] ) ? absint( $_GET['mdl_uid'] ) : 0;
	if ( is_user_logged_in() && $mdl_uid > 0 ) {
		$linked = (int) get_user_meta( get_current_user_id(), 'moodle_user_id', true );
		if ( $linked === $mdl_uid ) {
			wp_logout();
		}
	}

	wp_safe_redirect( home_url( '/login/' ) );
	exit;
}
add_action( 'init', 'aco_edwiser_logout_bridge', 1 );

/**
 * Let wp_safe_redirect() send users to the Moodle host.
 */
function aco_moodle_allowed_redirect_host( $hosts ) {
	$moodle_url  = aco_moodle_url();
	$moodle_host = $moodle_url ? wp_parse_url( $moodle_url, PHP_URL_HOST ) : '';
	if ( $moodle_host ) {
		$hosts[] = $moodle_host;
	}
	return $hosts;
}
add_filter( 'allowed_redirect_hosts', 'aco_moodle_allowed_redirect_host' );
