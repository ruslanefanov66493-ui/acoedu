<?php
/**
 * Thankyou page
 *
 * @package aco-theme
 */

defined( 'ABSPATH' ) || exit;
?>

<div class="woocommerce-order aco-checkout-thanks">
	<?php if ( $order ) : ?>
		<?php do_action( 'woocommerce_before_thankyou', $order->get_id() ); ?>

		<?php if ( $order->has_status( 'failed' ) ) : ?>
			<div class="aco-checkout-card">
				<h2>Не удалось обработать заказ</h2>
				<p>Попробуйте оплатить ещё раз или напишите в поддержку.</p>
				<a class="lh-btn lh-btn--primary" href="<?php echo esc_url( $order->get_checkout_payment_url() ); ?>">Повторить оплату</a>
			</div>
		<?php else : ?>
			<div class="aco-checkout-card">
				<h2>Спасибо, заказ принят</h2>
				<p>Мы отправим доступ к курсу на <?php echo esc_html( $order->get_billing_email() ); ?> после подтверждения оплаты.</p>
				<ul class="aco-checkout-thanks__meta">
					<li><span>Номер заказа</span><strong><?php echo esc_html( $order->get_order_number() ); ?></strong></li>
					<li><span>Сумма</span><strong><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></strong></li>
				</ul>
				<a class="lh-btn lh-btn--primary" href="<?php echo esc_url( home_url( '/account/' ) ); ?>">Перейти в кабинет</a>
			</div>
		<?php endif; ?>
	<?php else : ?>
		<div class="aco-checkout-card">
			<p>Заказ не найден.</p>
			<a class="lh-btn lh-btn--primary" href="<?php echo esc_url( home_url( '/catalog/' ) ); ?>">В каталог</a>
		</div>
	<?php endif; ?>
</div>
