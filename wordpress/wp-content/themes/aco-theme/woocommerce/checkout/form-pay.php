<?php
/**
 * Pay for order — one-card retry / waiting screen.
 *
 * @package aco-theme
 * @version 8.2.0
 */

defined( 'ABSPATH' ) || exit;

$needs_payment = $order->needs_payment();
?>
<form id="order_review" method="post" class="aco-pay-form">
	<section class="aco-checkout-card aco-pay-card">
		<p class="aco-pay-kicker"><?php echo $needs_payment ? 'Остался один шаг' : 'Заказ'; ?></p>
		<p class="aco-pay-lead">
			<?php if ( $needs_payment ) : ?>
				Сейчас откроется защищённая страница ЮKassa. Курс — цифровая услуга: после оплаты доступ откроется в личном кабинете, доставки товара нет.
			<?php else : ?>
				Этот заказ уже оплачен.
			<?php endif; ?>
		</p>

		<div class="aco-checkout-lines aco-pay-lines">
			<?php
			foreach ( $order->get_items() as $item_id => $item ) {
				if ( ! apply_filters( 'woocommerce_order_item_visible', true, $item ) ) {
					continue;
				}

				$product    = $item->get_product();
				$product_id = $product ? $product->get_id() : $item->get_product_id();
				$terms      = $product_id ? get_the_terms( $product_id, 'product_cat' ) : [];
				$cat_name   = ( ! is_wp_error( $terms ) && ! empty( $terms ) ) ? $terms[0]->name : 'Курс';
				$duration   = $product_id ? get_post_meta( $product_id, '_aco_course_duration', true ) : '';
				$thumb      = $product ? $product->get_image( 'woocommerce_thumbnail', [
					'class' => 'aco-checkout-line__img',
					'alt'   => $item->get_name(),
				] ) : '';
				?>
				<article class="<?php echo esc_attr( apply_filters( 'woocommerce_order_item_class', 'aco-checkout-line', $item, $order ) ); ?>">
					<div class="aco-checkout-line__thumb" aria-hidden="true">
						<?php echo $thumb ? $thumb : '<span class="aco-checkout-line__placeholder">📘</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</div>
					<div class="aco-checkout-line__body">
						<p class="aco-checkout-line__kicker"><?php echo esc_html( $cat_name ); ?></p>
						<h3 class="aco-checkout-line__name">
							<?php
							echo wp_kses_post( apply_filters( 'woocommerce_order_item_name', $item->get_name(), $item, false ) );
							do_action( 'woocommerce_order_item_meta_start', $item_id, $item, $order, false );
							wc_display_item_meta( $item );
							do_action( 'woocommerce_order_item_meta_end', $item_id, $item, $order, false );
							?>
						</h3>
						<?php if ( $duration ) : ?>
							<p class="aco-checkout-line__meta"><?php echo esc_html( $duration ); ?></p>
						<?php endif; ?>
					</div>
					<div class="aco-checkout-line__price product-total">
						<?php echo $order->get_formatted_line_subtotal( $item ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</div>
				</article>
				<?php
			}
			?>
		</div>

		<div class="aco-checkout-totals">
			<div class="aco-checkout-totals__row aco-checkout-totals__row--total">
				<span>Итого</span>
				<span class="aco-checkout-totals__amount"><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></span>
			</div>
		</div>

		<?php if ( $needs_payment ) : ?>
			<ol class="aco-pay-steps">
				<li>Нажмите «Оплатить»</li>
				<li>Подтвердите платёж в ЮKassa</li>
				<li>Курс откроется автоматически</li>
			</ol>
		<?php endif; ?>

		<?php do_action( 'woocommerce_pay_order_before_payment' ); ?>

		<div id="payment" class="woocommerce-checkout-payment aco-checkout-payment aco-pay-payment">
			<?php if ( $needs_payment ) : ?>
				<?php if ( ! empty( $available_gateways ) ) : ?>
					<ul class="wc_payment_methods payment_methods methods aco-checkout-gateways">
						<?php
						foreach ( $available_gateways as $gateway ) {
							wc_get_template( 'checkout/payment-method.php', [ 'gateway' => $gateway ] );
						}
						?>
					</ul>
					<p class="aco-pay-method">Банковской картой или СБП на защищённой странице ЮKassa</p>
				<?php else : ?>
					<?php wc_print_notice( esc_html__( 'Способ оплаты недоступен. Напишите в поддержку.', 'aco-theme' ), 'notice' ); ?>
				<?php endif; ?>
			<?php endif; ?>

			<?php if ( $needs_payment ) : ?>
				<div class="form-row place-order">
					<input type="hidden" name="woocommerce_pay" value="1" />
					<?php wc_get_template( 'checkout/terms.php' ); ?>
					<?php do_action( 'woocommerce_pay_order_before_submit' ); ?>
					<?php
					echo apply_filters(
						'woocommerce_pay_order_button_html',
						'<button type="submit" class="button alt lh-btn lh-btn--primary aco-checkout-pay" id="place_order" value="' . esc_attr( $order_button_text ) . '" data-value="' . esc_attr( $order_button_text ) . '">' . esc_html( $order_button_text ) . '</button>'
					);
					?>
					<?php do_action( 'woocommerce_pay_order_after_submit' ); ?>
					<?php wp_nonce_field( 'woocommerce-pay', 'woocommerce-pay-nonce' ); ?>
					<p class="aco-checkout-legal">
						Нажимая кнопку, вы принимаете
						<a href="<?php echo esc_url( aco_legal_url( 'oferta' ) ); ?>" target="_blank" rel="noopener">публичную оферту</a>,
						даёте согласие на
						<a href="<?php echo esc_url( aco_legal_url( 'personal-data' ) ); ?>" target="_blank" rel="noopener">обработку персональных данных</a>
						и подтверждаете, что ознакомились с
						<a href="<?php echo esc_url( aco_legal_url( 'privacy' ) ); ?>" target="_blank" rel="noopener">политикой конфиденциальности</a>
					</p>
				</div>
			<?php endif; ?>
		</div>

		<p class="aco-pay-meta">Заказ №<?php echo esc_html( $order->get_order_number() ); ?></p>
		<p class="aco-pay-back">
			<a href="<?php echo esc_url( home_url( '/catalog/' ) ); ?>">Вернуться в каталог</a>
			<span aria-hidden="true">·</span>
			<a href="<?php echo esc_url( home_url( '/contacts/' ) ); ?>">Написать в поддержку</a>
		</p>
	</section>
</form>
