<?php
/**
 * WP teachers: role aco_teacher, Moodle editingteacher, SSO.
 *
 * @package aco-theme
 */

defined( 'ABSPATH' ) || exit;

const ACO_MOODLE_EDITINGTEACHER_ROLE_ID = 3;

function aco_register_teacher_role() {
	if ( get_role( 'aco_teacher' ) ) {
		return;
	}
	add_role( 'aco_teacher', 'Учитель АЦО', [ 'read' => true ] );
}
add_action( 'after_setup_theme', 'aco_register_teacher_role' );

function aco_is_teacher( $user = null ) {
	if ( ! $user instanceof WP_User ) {
		if ( ! is_user_logged_in() ) {
			return false;
		}
		$user = wp_get_current_user();
	}
	return in_array( 'aco_teacher', (array) $user->roles, true );
}

function aco_teacher_cabinet_url() {
	$moodle = aco_moodle_url();
	return $moodle ? $moodle . '/theme/learnhub/teacher/index.php' : home_url( '/' );
}

function aco_admin_teachers_url( $args = [] ) {
	return aco_admin_home_url( array_merge( [ 'view' => 'teachers' ], $args ) );
}

function aco_list_teachers() {
	return get_users( [
		'role'    => 'aco_teacher',
		'orderby' => 'display_name',
		'order'   => 'ASC',
	] );
}

function aco_teacher_options() {
	$out = [];
	foreach ( aco_list_teachers() as $user ) {
		$out[] = [
			'id'    => (string) $user->ID,
			'label' => $user->display_name . ' (' . $user->user_email . ')',
			'name'  => $user->display_name,
		];
	}
	return $out;
}

function aco_teacher_display_name( $product_id ) {
	$tid = (int) get_post_meta( $product_id, '_aco_teacher_user_id', true );
	if ( $tid ) {
		$user = get_user_by( 'id', $tid );
		if ( $user ) {
			return $user->display_name;
		}
	}
	$legacy = (string) get_post_meta( $product_id, '_aco_course_teacher', true );
	return $legacy !== '' ? $legacy : 'Команда АЦО';
}

function aco_moodle_flatten_params( $params, $prefix = '' ) {
	$out = [];
	foreach ( (array) $params as $key => $value ) {
		$name = $prefix === '' ? (string) $key : $prefix . '[' . $key . ']';
		if ( is_array( $value ) ) {
			$out += aco_moodle_flatten_params( $value, $name );
		} else {
			$out[ $name ] = $value;
		}
	}
	return $out;
}

function aco_moodle_course_labels( $course_ids ) {
	$ids  = array_values( array_filter( array_map( 'absint', (array) $course_ids ) ) );
	$map  = [];
	if ( function_exists( 'aco_moodle_course_options' ) ) {
		foreach ( aco_moodle_course_options() as $opt ) {
			$map[ (int) $opt['id'] ] = $opt['label'];
		}
	}
	$labels = [];
	foreach ( $ids as $id ) {
		$labels[] = $map[ $id ] ?? ( 'Курс Moodle #' . $id );
	}
	return $labels;
}

function aco_teacher_mail_greeting( $user ) {
	if ( ! $user instanceof WP_User ) {
		return 'Здравствуйте';
	}
	$first = trim( (string) $user->first_name );
	return $first !== '' ? $first : $user->display_name;
}

function aco_teacher_send_assigned_mail( $user, $course_ids, $password = '' ) {
	if ( ! $user instanceof WP_User || ! is_email( $user->user_email ) || ! function_exists( 'aco_send_mail' ) ) {
		return false;
	}

	$labels = aco_moodle_course_labels( $course_ids );
	$login  = home_url( '/login/' );
	$moodle = function_exists( 'aco_moodle_url' ) ? aco_moodle_url() : '';
	$inbox  = function_exists( 'aco_mail_inbox' ) ? aco_mail_inbox() : get_option( 'admin_email' );

	$courses_html = '';
	if ( $labels ) {
		$courses_html = '<p style="margin:0 0 8px">Вам назначены курсы:</p><ul style="margin:0 0 16px;padding-left:18px">';
		foreach ( $labels as $label ) {
			$courses_html .= '<li>' . esc_html( $label ) . '</li>';
		}
		$courses_html .= '</ul>';
	} else {
		$courses_html = '<p style="margin:0 0 16px">Курсы можно назначить позже в панели АЦО.</p>';
	}

	$access = '';
	if ( $password !== '' ) {
		$access = '<p style="margin:0 0 8px">Данные для входа:</p>'
			. '<p style="margin:0 0 16px">Логин: <strong>' . esc_html( $user->user_login ) . '</strong><br>'
			. 'Пароль: <strong>' . esc_html( $password ) . '</strong></p>';
	}

	$html = aco_mail_wrap(
		'Вас назначили преподавателем',
		'<p style="margin:0 0 16px">Здравствуйте, ' . esc_html( aco_teacher_mail_greeting( $user ) ) . '!</p>'
		. '<p style="margin:0 0 16px">Вас назначили преподавателем в Академии цифрового образования. После входа откроется кабинет преподавателя в Moodle.</p>'
		. $courses_html
		. $access
		. '<p style="margin:0 0 16px"><a href="' . esc_url( $login ) . '" style="color:#3011dd;font-weight:700">Войти на сайт</a>'
		. ( $moodle ? ' · <a href="' . esc_url( $moodle . '/login/' ) . '" style="color:#3011dd;font-weight:700">Учебная платформа</a>' : '' )
		. '</p>'
		. '<p style="margin:0;color:#4B5563">Если письмо пришло по ошибке, напишите на ' . esc_html( $inbox ) . '.</p>'
	);

	return aco_send_mail( $user->user_email, 'Вас назначили преподавателем — АЦО', $html );
}

function aco_teacher_send_deleted_mail( $user, $course_ids = [] ) {
	if ( ! $user instanceof WP_User || ! is_email( $user->user_email ) || ! function_exists( 'aco_send_mail' ) ) {
		return false;
	}

	$labels = aco_moodle_course_labels( $course_ids );
	$inbox  = function_exists( 'aco_mail_inbox' ) ? aco_mail_inbox() : get_option( 'admin_email' );
	$courses_html = '';
	if ( $labels ) {
		$courses_html = '<p style="margin:0 0 8px">Закрыт доступ к курсам:</p><ul style="margin:0 0 16px;padding-left:18px">';
		foreach ( $labels as $label ) {
			$courses_html .= '<li>' . esc_html( $label ) . '</li>';
		}
		$courses_html .= '</ul>';
	}

	$html = aco_mail_wrap(
		'Доступ преподавателя закрыт',
		'<p style="margin:0 0 16px">Здравствуйте, ' . esc_html( aco_teacher_mail_greeting( $user ) ) . '!</p>'
		. '<p style="margin:0 0 16px">Ваш аккаунт преподавателя в АЦО удалён. Вход в кабинет и учебную платформу Moodle больше недоступен.</p>'
		. $courses_html
		. '<p style="margin:0;color:#4B5563">Если это ошибка, напишите в поддержку: ' . esc_html( $inbox ) . '.</p>'
	);

	return aco_send_mail( $user->user_email, 'Доступ преподавателя закрыт — АЦО', $html );
}

function aco_moodle_delete_user( $moodle_user_id ) {
	$moodle_user_id = (int) $moodle_user_id;
	if ( $moodle_user_id < 3 || ! function_exists( 'aco_moodle_api_call' ) ) {
		return false;
	}

	$result = aco_moodle_api_call(
		'core_user_delete_users',
		[ 'userids' => [ $moodle_user_id ] ]
	);

	return $result !== null;
}

function aco_detach_teacher_from_products( $user_id ) {
	$user_id = (int) $user_id;
	if ( $user_id < 1 ) {
		return;
	}
	$products = get_posts( [
		'post_type'      => 'product',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'meta_key'       => '_aco_teacher_user_id',
		'meta_value'     => $user_id,
	] );
	foreach ( $products as $product_id ) {
		delete_post_meta( $product_id, '_aco_teacher_user_id' );
	}
}

function aco_moodle_enrol_editingteacher( $moodle_user_id, $course_id ) {
	$moodle_user_id = (int) $moodle_user_id;
	$course_id      = (int) $course_id;
	if ( $moodle_user_id < 1 || $course_id < 1 ) {
		return false;
	}
	$result = aco_moodle_api_call(
		'enrol_manual_enrol_users',
		[
			'enrolments' => [
				[
					'roleid'   => ACO_MOODLE_EDITINGTEACHER_ROLE_ID,
					'userid'   => $moodle_user_id,
					'courseid' => $course_id,
				],
			],
		]
	);
	return $result !== null;
}

function aco_admin_teacher_flash( $text, $error = false ) {
	set_transient(
		'aco_admin_teacher_flash_' . get_current_user_id(),
		[ 'text' => $text, 'error' => (bool) $error ],
		2 * MINUTE_IN_SECONDS
	);
}

function aco_admin_teacher_take_flash() {
	$key = 'aco_admin_teacher_flash_' . get_current_user_id();
	$val = get_transient( $key );
	delete_transient( $key );
	return is_array( $val ) ? $val : null;
}

function aco_admin_handle_teacher_delete() {
	if ( empty( $_POST['aco_teacher_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['aco_teacher_nonce'] ) ), 'aco_admin_teacher' ) ) {
		return;
	}

	$user_id = absint( $_POST['teacher_user_id'] ?? 0 );
	$user    = $user_id ? get_user_by( 'id', $user_id ) : null;
	if ( ! $user || ! aco_is_teacher( $user ) ) {
		aco_admin_teacher_flash( 'Учитель не найден', true );
		wp_safe_redirect( aco_admin_teachers_url() );
		exit;
	}
	if ( (int) $user->ID === (int) get_current_user_id() || user_can( $user, 'manage_options' ) ) {
		aco_admin_teacher_flash( 'Этого пользователя нельзя удалить из панели', true );
		wp_safe_redirect( aco_admin_teachers_url() );
		exit;
	}

	$courses  = array_map( 'absint', (array) get_user_meta( $user->ID, '_aco_teacher_courses', true ) );
	$mdl_id   = (int) get_user_meta( $user->ID, 'moodle_user_id', true );
	$mailed   = aco_teacher_send_deleted_mail( $user, $courses );
	$mdl_ok   = $mdl_id ? aco_moodle_delete_user( $mdl_id ) : true;

	aco_detach_teacher_from_products( $user->ID );

	require_once ABSPATH . 'wp-admin/includes/user.php';
	$deleted = wp_delete_user( $user->ID );
	if ( ! $deleted ) {
		aco_admin_teacher_flash( 'Не удалось удалить аккаунт WordPress', true );
		wp_safe_redirect( aco_admin_teachers_url() );
		exit;
	}

	if ( $mdl_id && ! $mdl_ok ) {
		aco_admin_teacher_flash( 'Учитель удалён из WordPress, но Moodle не удалил пользователя. Проверьте webservice.', true );
	} elseif ( ! $mailed ) {
		aco_admin_teacher_flash( 'Учитель удалён. Письмо отправить не удалось.', true );
	} else {
		aco_admin_teacher_flash( 'Учитель удалён из WordPress и Moodle. Письмо отправлено.' );
	}
	wp_safe_redirect( aco_admin_teachers_url() );
	exit;
}

function aco_admin_handle_teachers() {
	if ( ! is_page_template( 'page-aco-admin.php' ) || ! aco_is_platform_admin() ) {
		return;
	}
	if ( ( $_SERVER['REQUEST_METHOD'] ?? '' ) !== 'POST' ) {
		return;
	}
	if ( ! empty( $_POST['aco_teacher_delete'] ) ) {
		aco_admin_handle_teacher_delete();
		return;
	}
	if ( empty( $_POST['aco_teacher_save'] ) ) {
		return;
	}
	if ( empty( $_POST['aco_teacher_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['aco_teacher_nonce'] ) ), 'aco_admin_teacher' ) ) {
		return;
	}

	$edit_id   = absint( $_POST['teacher_user_id'] ?? 0 );
	$firstname = sanitize_text_field( wp_unslash( $_POST['teacher_first'] ?? '' ) );
	$lastname  = sanitize_text_field( wp_unslash( $_POST['teacher_last'] ?? '' ) );
	$email     = sanitize_email( wp_unslash( $_POST['teacher_email'] ?? '' ) );
	$password  = (string) wp_unslash( $_POST['teacher_password'] ?? '' );
	$courses   = array_map( 'absint', (array) ( $_POST['teacher_courses'] ?? [] ) );
	$courses   = array_values( array_filter( $courses ) );

	if ( $firstname === '' || $email === '' ) {
		aco_admin_teacher_flash( 'Укажите имя и email', true );
		wp_safe_redirect( aco_admin_teachers_url( $edit_id ? [ 'tid' => $edit_id ] : [] ) );
		exit;
	}

	if ( $edit_id ) {
		$user = get_user_by( 'id', $edit_id );
		if ( ! $user || ! aco_is_teacher( $user ) ) {
			aco_admin_teacher_flash( 'Учитель не найден', true );
			wp_safe_redirect( aco_admin_teachers_url() );
			exit;
		}
		$update = [
			'ID'           => $edit_id,
			'first_name'   => $firstname,
			'last_name'    => $lastname,
			'display_name' => trim( $firstname . ' ' . $lastname ),
			'user_email'   => $email,
		];
		if ( $password !== '' ) {
			$update['user_pass'] = $password;
		}
		$saved = wp_update_user( $update );
		if ( is_wp_error( $saved ) ) {
			aco_admin_teacher_flash( $saved->get_error_message(), true );
			wp_safe_redirect( aco_admin_teachers_url( [ 'tid' => $edit_id ] ) );
			exit;
		}
		$user_id = $edit_id;
	} else {
		if ( $password === '' ) {
			$password = wp_generate_password( 12, true, false );
		}
		$login = sanitize_user( current( explode( '@', $email ) ), true );
		if ( $login === '' || username_exists( $login ) ) {
			$login = sanitize_user( 't_' . wp_generate_password( 8, false, false ), true );
		}
		$saved = wp_insert_user( [
			'user_login'   => $login,
			'user_email'   => $email,
			'user_pass'    => $password,
			'first_name'   => $firstname,
			'last_name'    => $lastname,
			'display_name' => trim( $firstname . ' ' . $lastname ),
		] );
		if ( is_wp_error( $saved ) ) {
			aco_admin_teacher_flash( $saved->get_error_message(), true );
			wp_safe_redirect( aco_admin_teachers_url() );
			exit;
		}
		$user_id = (int) $saved;
		$user    = new WP_User( $user_id );
		$user->set_role( 'aco_teacher' );
	}

	$moodle = aco_eb_available() ? aco_moodle_ensure_user( $user_id ) : [ 'moodle_user_id' => 0 ];
	$mdl_id = (int) ( $moodle['moodle_user_id'] ?? 0 );
	$linked = [];
	if ( $mdl_id ) {
		foreach ( $courses as $course_id ) {
			if ( aco_moodle_enrol_editingteacher( $mdl_id, $course_id ) ) {
				$linked[] = $course_id;
			}
		}
	}
	$existing = array_map( 'absint', (array) get_user_meta( $user_id, '_aco_teacher_courses', true ) );
	$merged   = array_values( array_unique( array_merge( $existing, $linked, $courses ) ) );
	$added    = array_values( array_diff( $merged, $existing ) );
	update_user_meta( $user_id, '_aco_teacher_courses', $merged );

	$notify_password = ( ! $edit_id || $password !== '' ) ? $password : '';
	$should_mail     = ! $edit_id || $added || $notify_password !== '';
	$mailed          = false;
	if ( $should_mail ) {
		$mail_user = get_user_by( 'id', $user_id );
		$mailed    = $mail_user ? aco_teacher_send_assigned_mail( $mail_user, $added ?: $merged, $notify_password ) : false;
	}

	if ( ! $mdl_id ) {
		aco_admin_teacher_flash( 'Учитель сохранён в WordPress, но связать Moodle не удалось. Проверьте Edwiser.', true );
	} else {
		$msg = $edit_id ? 'Учитель обновлён' : 'Учитель создан и связан с Moodle';
		if ( $should_mail ) {
			$msg .= $mailed ? '. Письмо отправлено' : '. Письмо отправить не удалось';
		}
		aco_admin_teacher_flash( $msg, $should_mail && ! $mailed );
	}
	wp_safe_redirect( aco_admin_teachers_url() );
	exit;
}
add_action( 'template_redirect', 'aco_admin_handle_teachers', 11 );

function aco_rest_moodle_account( WP_REST_Request $request ) {
	$secret   = (string) $request->get_param( 'secret' );
	$expected = (string) get_option( 'aco_eb_sso_secret' );
	if ( $expected === '' || ! hash_equals( $expected, $secret ) ) {
		return new WP_Error( 'forbidden', 'Forbidden', [ 'status' => 403 ] );
	}

	$moodle_id = absint( $request->get_param( 'moodle_user_id' ) );
	$email     = sanitize_email( (string) $request->get_param( 'email' ) );
	$users     = $moodle_id ? get_users( [
		'meta_key'   => 'moodle_user_id',
		'meta_value' => $moodle_id,
		'number'     => 1,
	] ) : [];
	$user      = $users ? $users[0] : ( $email ? get_user_by( 'email', $email ) : null );
	if ( ! $user ) {
		return new WP_Error( 'not_found', 'User not found', [ 'status' => 404 ] );
	}

	$update = [ 'ID' => $user->ID ];
	$first  = sanitize_text_field( (string) $request->get_param( 'firstname' ) );
	$last   = sanitize_text_field( (string) $request->get_param( 'lastname' ) );
	if ( $first !== '' ) {
		$update['first_name'] = $first;
	}
	if ( $last !== '' ) {
		$update['last_name'] = $last;
	}
	if ( $first !== '' || $last !== '' ) {
		$update['display_name'] = trim( ( $first !== '' ? $first : $user->first_name ) . ' ' . ( $last !== '' ? $last : $user->last_name ) );
	}
	$pass = (string) $request->get_param( 'password' );
	if ( $pass !== '' ) {
		$update['user_pass'] = $pass;
	}
	if ( count( $update ) > 1 ) {
		wp_update_user( $update );
	}

	return [ 'ok' => true ];
}

add_action( 'rest_api_init', function () {
	register_rest_route( 'aco/v1', '/moodle-account', [
		'methods'             => 'POST',
		'permission_callback' => '__return_true',
		'callback'            => 'aco_rest_moodle_account',
	] );
} );
