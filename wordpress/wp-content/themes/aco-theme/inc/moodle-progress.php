<?php
/**
 * Detailed Moodle progress for the account dashboard.
 *
 * Aggregates per-course sections, activity completion and quiz grades via the
 * Moodle REST API (Edwiser Bridge token) and exposes them as a WP REST endpoint
 * consumed by the account dashboard JS.
 *
 * @package aco-theme
 */

defined( 'ABSPATH' ) || exit;

/**
 * Call a Moodle webservice function with the Edwiser Bridge token.
 *
 * @param string $function Webservice function name.
 * @param array  $params   Function parameters (nested arrays allowed).
 * @return array|null Decoded response, null on transport/API error.
 */
function aco_moodle_api_call( $function, $params = array() ) {
	$connection = get_option( 'eb_connection' );
	$moodle_url = isset( $connection['eb_url'] ) ? rtrim( $connection['eb_url'], '/' ) : '';
	$token      = isset( $connection['eb_access_token'] ) ? $connection['eb_access_token'] : '';

	if ( ! $moodle_url || ! $token ) {
		return null;
	}

	$response = wp_remote_post(
		$moodle_url . '/webservice/rest/server.php',
		array(
			'timeout' => 20,
			'body'    => array_merge(
				array(
					'wstoken'            => $token,
					'wsfunction'         => $function,
					'moodlewsrestformat' => 'json',
				),
				function_exists( 'aco_moodle_flatten_params' ) ? aco_moodle_flatten_params( $params ) : $params
			),
		)
	);

	if ( is_wp_error( $response ) ) {
		return null;
	}

	$raw = wp_remote_retrieve_body( $response );
	if ( trim( (string) $raw ) === 'null' ) {
		return array();
	}

	$data = json_decode( $raw, true );
	if ( ! is_array( $data ) || isset( $data['exception'] ) ) {
		return null;
	}

	return $data;
}

/**
 * Map Moodle completion state to a simple status.
 *
 * @param array $statuses Statuses from core_completion_get_activities_completion_status.
 * @return array cmid => 'done'|'failed'|'pending'|'none'
 */
function aco_moodle_map_completion( $statuses ) {
	$map = array();
	foreach ( (array) $statuses as $status ) {
		$cmid = isset( $status['cmid'] ) ? (int) $status['cmid'] : 0;
		if ( ! $cmid ) {
			continue;
		}
		$state = isset( $status['state'] ) ? (int) $status['state'] : 0;
		if ( COMPLETION_COMPLETE === $state || COMPLETION_COMPLETE_PASS === $state ) {
			$map[ $cmid ] = 'done';
		} elseif ( defined( 'COMPLETION_COMPLETE_FAIL' ) && COMPLETION_COMPLETE_FAIL === $state ) {
			$map[ $cmid ] = 'failed';
		} else {
			$map[ $cmid ] = 'pending';
		}
	}
	return $map;
}

/**
 * Build the detailed progress payload for a Moodle user.
 *
 * @param int $moodle_uid Moodle user ID.
 * @return array Courses with sections, modules and quizzes.
 */
function aco_moodle_course_progress( $moodle_uid ) {
	$moodle_url = aco_moodle_url();
	$courses    = aco_moodle_api_call( 'core_enrol_get_users_courses', array( 'userid' => $moodle_uid ) );

	if ( ! is_array( $courses ) ) {
		return array();
	}

	$result = array();
	foreach ( array_slice( $courses, 0, 8 ) as $course ) {
		$course_id = isset( $course['id'] ) ? (int) $course['id'] : 0;
		if ( ! $course_id ) {
			continue;
		}

		$contents   = aco_moodle_api_call( 'core_course_get_contents', array( 'courseid' => $course_id ) );
		$completion = aco_moodle_api_call(
			'core_completion_get_activities_completion_status',
			array( 'courseid' => $course_id, 'userid' => $moodle_uid )
		);
		$status_map = array();
		if ( is_array( $completion ) && ! empty( $completion['statuses'] ) ) {
			if ( ! defined( 'COMPLETION_COMPLETE' ) ) {
				define( 'COMPLETION_COMPLETE', 1 );
			}
			if ( ! defined( 'COMPLETION_COMPLETE_PASS' ) ) {
				define( 'COMPLETION_COMPLETE_PASS', 2 );
			}
			if ( ! defined( 'COMPLETION_COMPLETE_FAIL' ) ) {
				define( 'COMPLETION_COMPLETE_FAIL', 3 );
			}
			$status_map = aco_moodle_map_completion( $completion['statuses'] );
		}

		// Quiz max grades come from mod_quiz_get_quizzes_by_courses (get_user_best_grade
		// returns only the achieved grade).
		$quiz_max  = array();
		$quiz_list = aco_moodle_api_call( 'mod_quiz_get_quizzes_by_courses', array( 'courseids' => array( $course_id ) ) );
		if ( is_array( $quiz_list ) && ! empty( $quiz_list['quizzes'] ) ) {
			foreach ( $quiz_list['quizzes'] as $quiz_def ) {
				$quiz_max[ (int) $quiz_def['id'] ] = isset( $quiz_def['grade'] ) ? (float) $quiz_def['grade'] : 100;
			}
		}

		$sections = array();
		$quizzes  = array();
		foreach ( (array) $contents as $section ) {
			if ( isset( $section['visible'] ) && ! $section['visible'] ) {
				continue;
			}
			$modules = array();
			foreach ( (array) ( $section['modules'] ?? array() ) as $module ) {
				if ( isset( $module['visible'] ) && ! $module['visible'] ) {
					continue;
				}
				$cmid    = isset( $module['id'] ) ? (int) $module['id'] : 0;
				$modname = isset( $module['modname'] ) ? $module['modname'] : '';
				// Labels and the announcements forum add noise to the progress view.
				if ( in_array( $modname, array( 'label', 'forum' ), true ) ) {
					continue;
				}
				$item = array(
					'cmid'  => $cmid,
					'name'  => isset( $module['name'] ) ? $module['name'] : '',
					'type'  => $modname,
					'state' => isset( $status_map[ $cmid ] ) ? $status_map[ $cmid ] : 'none',
					'url'   => $moodle_url . '/mod/' . $modname . '/view.php?id=' . $cmid,
				);
				$modules[] = $item;

				if ( 'quiz' === $modname && ! empty( $module['instance'] ) ) {
					$instance   = (int) $module['instance'];
					$grade_data = aco_moodle_api_call(
						'mod_quiz_get_user_best_grade',
						array( 'quizid' => $instance, 'userid' => $moodle_uid )
					);
					$hasgrade   = is_array( $grade_data ) && ! empty( $grade_data['hasgrade'] ) && isset( $grade_data['grade'] );
					$quizzes[]  = array(
						'cmid'     => $cmid,
						'name'     => $item['name'],
						'state'    => $item['state'],
						'url'      => $item['url'],
						'hasgrade' => $hasgrade,
						'grade'    => $hasgrade ? round( (float) $grade_data['grade'], 1 ) : null,
						'grademax' => isset( $quiz_max[ $instance ] ) ? round( $quiz_max[ $instance ], 1 ) : 100,
					);
				}
			}
			if ( $modules ) {
				$sections[] = array(
					'name'    => isset( $section['name'] ) && '' !== $section['name'] ? $section['name'] : 'Раздел',
					'modules' => $modules,
				);
			}
		}

		$result[] = array(
			'moodle_course_id' => $course_id,
			'title'            => isset( $course['fullname'] ) ? $course['fullname'] : 'Курс',
			'progress'         => isset( $course['progress'] ) ? round( (float) $course['progress'] ) : 0,
			'completed'        => ! empty( $course['completed'] ),
			'course_url'       => $moodle_url . '/course/view.php?id=' . $course_id,
			'sections'         => $sections,
			'quizzes'          => $quizzes,
		);
	}

	return $result;
}

/**
 * REST callback: detailed progress for the current user.
 */
function aco_rest_course_progress( WP_REST_Request $request ) {
	$user_id    = get_current_user_id();
	$moodle_uid = (int) get_user_meta( $user_id, 'moodle_user_id', true );

	if ( ! $moodle_uid ) {
		return rest_ensure_response( array( 'linked' => false, 'courses' => array() ) );
	}

	$cache_key = 'aco_mdl_progress_' . $user_id;
	$cached    = get_transient( $cache_key );
	if ( false !== $cached ) {
		return rest_ensure_response( $cached );
	}

	$payload = array(
		'linked'  => true,
		'courses' => aco_moodle_course_progress( $moodle_uid ),
	);

	set_transient( $cache_key, $payload, 5 * MINUTE_IN_SECONDS );

	return rest_ensure_response( $payload );
}

add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'aco/v1',
			'/course-progress',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => 'aco_rest_course_progress',
				'permission_callback' => 'is_user_logged_in',
			)
		);
	}
);

/**
 * Drop the progress cache when enrollment changes for an order.
 */
function aco_moodle_progress_flush( $order_id ) {
	if ( ! function_exists( 'wc_get_order' ) ) {
		return;
	}
	$order = wc_get_order( $order_id );
	if ( $order && $order->get_user_id() ) {
		delete_transient( 'aco_mdl_progress_' . $order->get_user_id() );
	}
}
add_action( 'woocommerce_order_status_completed', 'aco_moodle_progress_flush', 20 );
add_action( 'woocommerce_order_status_processing', 'aco_moodle_progress_flush', 20 );
