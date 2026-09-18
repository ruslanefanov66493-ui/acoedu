<?php
/**
 * Reliable YooKassa return: query-string URL, thank-you page, complete order without webhook.
 *
 * @package aco
 */

defined( 'ABSPATH' ) || exit;

add_filter( 'query_vars', static function ( $vars ) {
    $vars[] = 'yookassa';
    $vars[] = 'yookassa-order-id';
    return $vars;
} );

add_filter( 'woocommerce_yookassa_create_payment_request', static function ( $request ) {
    if ( ! is_object( $request ) || ! method_exists( $request, 'getConfirmation' ) ) {
        return $request;
    }
    $confirmation = $request->getConfirmation();
    if ( ! $confirmation || ! method_exists( $confirmation, 'getReturnUrl' ) || ! method_exists( $confirmation, 'setReturnUrl' ) ) {
        return $request;
    }
    $old = (string) $confirmation->getReturnUrl();
    if ( ! preg_match( '/yookassa-order-id=([^&]+)/', $old, $m ) ) {
        return $request;
    }
    $confirmation->setReturnUrl(
        home_url( '/?yookassa=returnUrl&yookassa-order-id=' . rawurlencode( rawurldecode( $m[1] ) ) )
    );
    return $request;
} );

add_action( 'template_redirect', 'aco_yookassa_handle_return', 0 );

function aco_yookassa_handle_return() {
    $flag = isset( $_GET['yookassa'] ) ? sanitize_text_field( wp_unslash( $_GET['yookassa'] ) ) : '';
    $key  = isset( $_GET['yookassa-order-id'] ) ? wc_clean( wp_unslash( $_GET['yookassa-order-id'] ) ) : '';
    if ( ! $key && function_exists( 'get_query_var' ) ) {
        $key = wc_clean( (string) get_query_var( 'yookassa-order-id' ) );
    }
    if ( $flag !== 'returnUrl' && $key === '' ) {
        return;
    }
    if ( $key === '' ) {
        return;
    }

    $order_id = wc_get_order_id_by_order_key( $key );
    $order    = $order_id ? wc_get_order( $order_id ) : false;
    if ( ! $order ) {
        wp_safe_redirect( home_url( '/checkout/' ) );
        exit;
    }

    $paid = aco_yookassa_sync_order( $order );
    if ( function_exists( 'WC' ) && WC()->cart ) {
        WC()->cart->empty_cart();
    }

    $url = $paid
        ? $order->get_checkout_order_received_url()
        : $order->get_checkout_payment_url();
    wp_safe_redirect( $url );
    exit;
}

function aco_yookassa_sync_order( WC_Order $order ) {
    if ( $order->is_paid() ) {
        return true;
    }
    $payment_id = $order->get_transaction_id();
    if ( ! $payment_id || ! class_exists( 'YooKassaClientFactory' ) ) {
        return false;
    }
    try {
        $client  = YooKassaClientFactory::getYooKassaClient();
        $payment = $client->getPaymentInfo( $payment_id );
        $status  = $payment->getStatus();
        $ok      = in_array( $status, [ 'succeeded', 'waiting_for_capture' ], true )
            || ( $status === 'pending' && $payment->getPaid() );
        if ( ! $ok ) {
            return false;
        }
        if ( class_exists( 'YooKassaHandler' ) ) {
            YooKassaHandler::completeOrder( $order, $payment );
        } else {
            $order->payment_complete( $payment_id );
        }
        return $order->is_paid();
    } catch ( Throwable $e ) {
        error_log( 'ACO YooKassa return: ' . $e->getMessage() );
        return $order->is_paid();
    }
}
