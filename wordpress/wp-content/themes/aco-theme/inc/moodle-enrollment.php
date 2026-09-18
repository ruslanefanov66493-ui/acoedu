<?php
/**
 * WooCommerce → Moodle enrolment glue (Edwiser Bridge API).
 *
 * Flow: paid order → ensure Moodle account exists for the buyer →
 * enrol to the Moodle course linked to the purchased product →
 * email access details to the buyer.
 *
 * Product ↔ Moodle link: product meta `_aco_moodle_course_id`
 * (editable in the "Параметры курса" product metabox).
 *
 * @package aco-theme
 */

defined( 'ABSPATH' ) || exit;

/**
 * Edwiser Bridge main instance (the plugin defines it namespaced).
 */
function aco_eb() {
    return \app\wisdmlabs\edwiserBridge\edwiser_bridge_instance();
}

function aco_eb_available() {
    return function_exists( 'app\wisdmlabs\edwiserBridge\edwiser_bridge_instance' );
}

/**
 * Product metabox field: linked Moodle course (dropdown of synced courses).
 */
function aco_render_moodle_course_field() {
    $post_id = get_the_ID();
    $current = (string) get_post_meta( $post_id, '_aco_moodle_course_id', true );
    $opts    = function_exists( 'aco_moodle_course_options' ) ? aco_moodle_course_options() : [];
    ?>
    <p>
        <label for="aco_moodle_course_id">Курс в Moodle (для автозачисления после оплаты)</label><br>
        <select id="aco_moodle_course_id" name="aco_moodle_course_id" style="width:100%;max-width:420px;">
            <option value="">— не привязан —</option>
            <?php foreach ( $opts as $opt ) : ?>
                <option value="<?php echo esc_attr( $opt['id'] ); ?>" <?php selected( $current, (string) $opt['id'] ); ?>>
                    <?php echo esc_html( $opt['label'] ); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <span class="description">Список берётся из Moodle, в том числе курсы из конструктора программы.</span>
    </p>
    <?php
}

function aco_save_moodle_course_field( $post_id ) {
    if ( isset( $_POST['aco_moodle_course_id'] ) ) {
        $mdl_id = absint( $_POST['aco_moodle_course_id'] );
        update_post_meta( $post_id, '_aco_moodle_course_id', $mdl_id );
        if ( $mdl_id && function_exists( 'aco_moodle_ensure_eb_course' ) ) {
            aco_moodle_ensure_eb_course( $mdl_id, get_the_title( $post_id ) );
        }
    }
}

/**
 * Ensure a Moodle account exists for the WP user.
 *
 * @param int $user_id WP user ID.
 * @return array { moodle_user_id: int, created: bool, password: string|null }
 */
function aco_moodle_ensure_user( $user_id ) {
    $result = [ 'moodle_user_id' => 0, 'created' => false, 'password' => null ];

    $wp_user = get_user_by( 'id', $user_id );
    if ( ! $wp_user ) {
        return $result;
    }

    $existing = get_user_meta( $user_id, 'moodle_user_id', true );
    if ( is_numeric( $existing ) && (int) $existing > 0 ) {
        $result['moodle_user_id'] = (int) $existing;
        return $result;
    }

    $user_manager = aco_eb()->user_manager();

    // Account may already exist in Moodle (e.g. manual enrolment earlier).
    $found = $user_manager->get_moodle_user( $wp_user->user_email );
    if ( ! empty( $found['user_exists'] ) && ! empty( $found['user_data'] ) ) {
        $mdl    = is_object( $found['user_data'] ) ? $found['user_data']->id : ( $found['user_data']['id'] ?? 0 );
        $mdl_id = (int) $mdl;
        if ( $mdl_id > 0 ) {
            update_user_meta( $user_id, 'moodle_user_id', $mdl_id );
            $result['moodle_user_id'] = $mdl_id;
            return $result;
        }
    }

    $firstname = $wp_user->first_name ? $wp_user->first_name : $wp_user->display_name;
    $lastname  = $wp_user->last_name ? $wp_user->last_name : '—';
    $password  = wp_generate_password( 12, true, false );

    $created = $user_manager->create_moodle_user( [
        'username'  => sanitize_user( $wp_user->user_login, true ),
        'firstname' => $firstname,
        'lastname'  => $lastname,
        'email'     => $wp_user->user_email,
        'password'  => $password,
        'auth'      => 'manual',
    ] );

    if ( ! empty( $created['user_created'] ) && ! empty( $created['user_data'] ) ) {
        $mdl    = is_object( $created['user_data'] ) ? ( $created['user_data']->id ?? 0 ) : ( $created['user_data']['id'] ?? 0 );
        $mdl_id = (int) $mdl;
        if ( $mdl_id > 0 ) {
            update_user_meta( $user_id, 'moodle_user_id', $mdl_id );
            $result['moodle_user_id'] = $mdl_id;
            $result['created']        = true;
            $result['password']       = $password;
        }
    }

    return $result;
}

/**
 * Enrol the buyer to all Moodle courses linked to order products.
 */
function aco_moodle_enrol_order( $order_id ) {
    if ( ! class_exists( 'WooCommerce' ) || ! aco_eb_available() ) {
        return;
    }

    $order = wc_get_order( $order_id );
    if ( ! $order || $order->get_meta( '_aco_moodle_enrolled' ) ) {
        return;
    }

    $user_id = $order->get_user_id();
    if ( ! $user_id ) {
        $user    = get_user_by( 'email', $order->get_billing_email() );
        $user_id = $user ? $user->ID : 0;
    }
    if ( ! $user_id ) {
        $order->add_order_note( 'Moodle: покупатель не найден в WordPress, зачисление пропущено.' );
        return;
    }

    $mdl_course_ids = [];
    foreach ( $order->get_items() as $item ) {
        $mdl_id = (int) get_post_meta( $item->get_product_id(), '_aco_moodle_course_id', true );
        if ( $mdl_id > 0 ) {
            $mdl_course_ids[] = $mdl_id;
        }
    }
    if ( empty( $mdl_course_ids ) ) {
        return;
    }

    $account = aco_moodle_ensure_user( $user_id );
    if ( ! $account['moodle_user_id'] ) {
        $order->add_order_note( 'Moodle: не удалось создать/найти аккаунт студента.' );
        return;
    }

    $wp_course_ids = [];
    foreach ( $mdl_course_ids as $mdl_id ) {
        if ( function_exists( 'aco_moodle_ensure_eb_course' ) ) {
            aco_moodle_ensure_eb_course( $mdl_id );
        }
        $wp_course_id = \app\wisdmlabs\edwiserBridge\wdm_eb_get_wp_course_id_from_moodle_course_id( $mdl_id );
        if ( $wp_course_id ) {
            $wp_course_ids[] = (int) $wp_course_id;
        }
    }
    if ( empty( $wp_course_ids ) ) {
        $order->add_order_note( 'Moodle: курсы не синхронизированы с WordPress (нет eb_course).' );
        return;
    }

    $enrolled = aco_eb()->enrollment_manager()->update_user_course_enrollment( [
        'user_id' => $user_id,
        'courses' => $wp_course_ids,
    ] );

    if ( false === $enrolled ) {
        $order->add_order_note( 'Moodle: ошибка зачисления на курс (см. лог Edwiser Bridge).' );
        return;
    }

    $order->update_meta_data( '_aco_moodle_enrolled', 1 );
    $order->add_order_note( 'Moodle: студент зачислен на курс (user ID ' . $account['moodle_user_id'] . ').' );
    $order->save();

    aco_moodle_send_access_email( $user_id, $account, $mdl_course_ids );
}

add_action( 'woocommerce_order_status_processing', 'aco_moodle_enrol_order' );
add_action( 'woocommerce_order_status_completed', 'aco_moodle_enrol_order' );

/**
 * Access email: Moodle credentials on first purchase, plain access notice later.
 */
function aco_moodle_send_access_email( $user_id, $account, $mdl_course_ids ) {
    $wp_user = get_user_by( 'id', $user_id );
    if ( ! $wp_user ) {
        return;
    }

    $moodle_url  = function_exists( 'aco_moodle_url' ) ? aco_moodle_url() : '';
    if ( $moodle_url === '' ) {
        $moodle_url = home_url( '/' );
    }
    $course_list = '';
    foreach ( $mdl_course_ids as $mdl_id ) {
        $wp_course_id = \app\wisdmlabs\edwiserBridge\wdm_eb_get_wp_course_id_from_moodle_course_id( $mdl_id );
        $title        = $wp_course_id ? get_the_title( $wp_course_id ) : 'Курс #' . $mdl_id;
        $course_list  .= '— ' . $title . "\n" . $moodle_url . '/course/view.php?id=' . $mdl_id . "\n";
    }

    if ( $account['created'] && $account['password'] ) {
        $subject = 'Ваш доступ к обучению — АЦО';
        $message = sprintf(
            "Здравствуйте, %s!\n\nОплата прошла успешно. Для вас создан аккаунт в учебной платформе АЦО (Moodle).\n\nЛогин: %s\nПароль: %s\nВход: %s/login/\n\nВаши курсы:\n%s\nРекомендуем сменить пароль после первого входа.",
            $wp_user->first_name ? $wp_user->first_name : $wp_user->display_name,
            $wp_user->user_login,
            $account['password'],
            $moodle_url,
            $course_list
        );
    } else {
        $subject = 'Вам открыт доступ к курсу — АЦО';
        $message = sprintf(
            "Здравствуйте, %s!\n\nОплата прошла успешно. Вам открыт доступ к курсу в учебной платформе АЦО.\n\nВаши курсы:\n%s\nВход в платформу: %s/login/ (используйте свои данные Moodle).",
            $wp_user->first_name ? $wp_user->first_name : $wp_user->display_name,
            $course_list,
            $moodle_url
        );
    }

    wp_mail( $wp_user->user_email, $subject, $message );
}

/**
 * Revoke access on refund.
 */
function aco_moodle_unenrol_order( $order_id ) {
    if ( ! aco_eb_available() ) {
        return;
    }

    $order = wc_get_order( $order_id );
    if ( ! $order || ! $order->get_meta( '_aco_moodle_enrolled' ) ) {
        return;
    }

    $user_id = $order->get_user_id();
    if ( ! $user_id ) {
        return;
    }

    $wp_course_ids = [];
    foreach ( $order->get_items() as $item ) {
        $mdl_id = (int) get_post_meta( $item->get_product_id(), '_aco_moodle_course_id', true );
        if ( $mdl_id > 0 ) {
            $wp_course_id = \app\wisdmlabs\edwiserBridge\wdm_eb_get_wp_course_id_from_moodle_course_id( $mdl_id );
            if ( $wp_course_id ) {
                $wp_course_ids[] = (int) $wp_course_id;
            }
        }
    }
    if ( empty( $wp_course_ids ) ) {
        return;
    }

    aco_eb()->enrollment_manager()->update_user_course_enrollment( [
        'user_id'  => $user_id,
        'courses'  => $wp_course_ids,
        'unenroll' => 1,
    ] );

    $order->delete_meta_data( '_aco_moodle_enrolled' );
    $order->add_order_note( 'Moodle: доступ к курсу отозван (возврат).' );
    $order->save();
}

add_action( 'woocommerce_order_status_refunded', 'aco_moodle_unenrol_order' );
