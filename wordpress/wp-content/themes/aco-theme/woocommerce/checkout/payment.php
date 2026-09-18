<?php
/**
 * Checkout Payment Section
 * Theme override: aco-theme/woocommerce/checkout/payment.php
 */

defined( 'ABSPATH' ) || exit;

if ( ! wp_doing_ajax() ) {
	do_action( 'woocommerce_review_order_before_payment' );
}
?>
<div id="payment" class="woocommerce-checkout-payment aco-checkout-payment">
	<?php if ( WC()->cart && WC()->cart->needs_payment() ) : ?>
		<ul class="wc_payment_methods payment_methods methods aco-checkout-gateways">
			<?php
			if ( ! empty( $available_gateways ) ) {
				foreach ( $available_gateways as $gateway ) {
					wc_get_template( 'checkout/payment-method.php', array( 'gateway' => $gateway ) );
				}
			} else {
				echo '<li>';
				wc_print_notice( apply_filters( 'woocommerce_no_available_payment_methods_message', esc_html__( 'Онлайн-оплата сейчас недоступна. Напишите на inbox@acoedu.ru.', 'aco-theme' ) ), 'notice' );
				echo '</li>';
			}
			?>
		</ul>
	<?php endif; ?>

	<div class="form-row place-order">
		<noscript>
			<?php
			printf( esc_html__( 'Since your browser does not support JavaScript, or it is disabled, please ensure you click the %1$sUpdate Totals%2$s button before placing your order. You may be charged more than the amount stated above if you fail to do so.', 'woocommerce' ), '<em>', '</em>' );
			?>
			<br/><button type="submit" class="button alt<?php echo esc_attr( wc_wp_theme_get_element_class_name( 'button' ) ? ' ' . wc_wp_theme_get_element_class_name( 'button' ) : '' ); ?>" name="woocommerce_checkout_update_totals" value="<?php esc_attr_e( 'Update totals', 'woocommerce' ); ?>"><?php esc_html_e( 'Update totals', 'woocommerce' ); ?></button>
		</noscript>

		<?php wc_get_template( 'checkout/terms.php' ); ?>

		<?php do_action( 'woocommerce_review_order_before_submit' ); ?>

		<?php echo apply_filters( 'woocommerce_order_button_html', '<button type="submit" class="button alt lh-btn lh-btn--primary aco-checkout-pay' . esc_attr( wc_wp_theme_get_element_class_name( 'button' ) ? ' ' . wc_wp_theme_get_element_class_name( 'button' ) : '' ) . '" name="woocommerce_checkout_place_order" id="place_order" value="' . esc_attr( $order_button_text ) . '" data-value="' . esc_attr( $order_button_text ) . '">' . esc_html( $order_button_text ) . '</button>' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

		<p class="aco-checkout-legal">
			Нажимая кнопку, вы принимаете
			<a href="<?php echo esc_url( aco_legal_url( 'oferta' ) ); ?>" target="_blank" rel="noopener">публичную оферту</a>,
			даёте согласие на
			<a href="<?php echo esc_url( aco_legal_url( 'personal-data' ) ); ?>" target="_blank" rel="noopener">обработку персональных данных</a>
			и подтверждаете, что ознакомились с
			<a href="<?php echo esc_url( aco_legal_url( 'privacy' ) ); ?>" target="_blank" rel="noopener">политикой конфиденциальности</a>.
			Курс — цифровая услуга:
			<a href="<?php echo esc_url( home_url( '/how/' ) ); ?>" target="_blank" rel="noopener">как получить доступ после оплаты</a>.
		</p>

		<?php do_action( 'woocommerce_review_order_after_submit' ); ?>

		<?php wp_nonce_field( 'woocommerce-process_checkout', 'woocommerce-process-checkout-nonce' ); ?>
	</div>
</div>
<?php
if ( ! wp_doing_ajax() ) {
	do_action( 'woocommerce_review_order_after_payment' );
}
