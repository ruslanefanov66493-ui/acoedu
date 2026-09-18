<?php
/**
 * Local-only HTTP allowlist for containerized integration tests.
 *
 * This lets wp_safe_remote_post() call local Moodle/WordPress hosts used by
 * Edwiser Bridge inside Docker. Remove this on production with public domains.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$aco_env = getenv( 'WP_ENVIRONMENT_TYPE' );
if ( $aco_env === false || $aco_env === '' ) {
	$aco_env = 'local';
}
if ( ! in_array( $aco_env, [ 'local', 'development' ], true ) ) {
	return;
}

add_filter(
	'http_request_host_is_external',
	static function ( $is_external, $host ) {
		$allowlisted_hosts = array(
			'moodle.localtest.me',
			'wp.localtest.me',
			'host.docker.internal',
			'moodle',
			'wordpress',
		);

		if ( in_array( strtolower( (string) $host ), $allowlisted_hosts, true ) ) {
			return true;
		}

		return $is_external;
	},
	10,
	2
);

add_filter(
	'http_request_args',
	static function ( $args, $url ) {
		$host = wp_parse_url( $url, PHP_URL_HOST );
		if ( ! is_string( $host ) || '' === $host ) {
			return $args;
		}

		$allowlisted_hosts = array(
			'moodle.localtest.me',
			'wp.localtest.me',
			'host.docker.internal',
			'moodle',
			'wordpress',
		);

		if ( in_array( strtolower( $host ), $allowlisted_hosts, true ) ) {
			$args['reject_unsafe_urls'] = false;
		}

		return $args;
	},
	10,
	2
);
