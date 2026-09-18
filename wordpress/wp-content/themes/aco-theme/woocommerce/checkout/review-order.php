<?php
/**
 * Review order — course lines + totals.
 *
 * Root class `woocommerce-checkout-review-order-table` is required:
 * WooCommerce AJAX replaces this node on update_order_review.
 *
 * @package aco-theme
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="shop_table woocommerce-checkout-review-order-table aco-checkout-summary">
	<div class="aco-checkout-lines">
		<?php
		do_action( 'woocommerce_review_order_before_cart_contents' );

		foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
			$_product = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );

			if ( ! $_product || ! $_product->exists() || $cart_item['quantity'] <= 0 || ! apply_filters( 'woocommerce_checkout_cart_item_visible', true, $cart_item, $cart_item_key ) ) {
				continue;
			}

			$product_id = $_product->get_id();
			$terms      = get_the_terms( $product_id, 'product_cat' );
			$cat_name   = ( ! is_wp_error( $terms ) && ! empty( $terms ) ) ? $terms[0]->name : 'Курс';
			$duration   = get_post_meta( $product_id, '_aco_course_duration', true );
			$thumb      = $_product->get_image( 'woocommerce_thumbnail', [
				'class' => 'aco-checkout-line__img',
				'alt'   => $_product->get_name(),
			] );
			?>
			<article class="<?php echo esc_attr( apply_filters( 'woocommerce_cart_item_class', 'aco-checkout-line cart_item', $cart_item, $cart_item_key ) ); ?>">
				<div class="aco-checkout-line__thumb" aria-hidden="true">
					<?php echo $thumb ? $thumb : '<span class="aco-checkout-line__placeholder">📘</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WC product image HTML ?>
				</div>
				<div class="aco-checkout-line__body">
					<p class="aco-checkout-line__kicker"><?php echo esc_html( $cat_name ); ?></p>
					<h3 class="aco-checkout-line__name">
						<?php echo wp_kses_post( apply_filters( 'woocommerce_cart_item_name', $_product->get_name(), $cart_item, $cart_item_key ) ); ?>
						<?php echo wc_get_formatted_cart_item_data( $cart_item ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</h3>
					<?php if ( $duration ) : ?>
						<p class="aco-checkout-line__meta"><?php echo esc_html( $duration ); ?></p>
					<?php endif; ?>
				</div>
				<div class="aco-checkout-line__price product-total">
					<?php echo apply_filters( 'woocommerce_cart_item_subtotal', WC()->cart->get_product_subtotal( $_product, $cart_item['quantity'] ), $cart_item, $cart_item_key ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</div>
			</article>
			<?php
		}

		do_action( 'woocommerce_review_order_after_cart_contents' );
		?>
	</div>

	<div class="aco-checkout-totals">
		<?php foreach ( WC()->cart->get_coupons() as $code => $coupon ) : ?>
			<div class="aco-checkout-totals__row cart-discount coupon-<?php echo esc_attr( sanitize_title( $code ) ); ?>">
				<span><?php wc_cart_totals_coupon_label( $coupon ); ?></span>
				<span><?php wc_cart_totals_coupon_html( $coupon ); ?></span>
			</div>
		<?php endforeach; ?>

		<?php foreach ( WC()->cart->get_fees() as $fee ) : ?>
			<div class="aco-checkout-totals__row fee">
				<span><?php echo esc_html( $fee->name ); ?></span>
				<span><?php wc_cart_totals_fee_html( $fee ); ?></span>
			</div>
		<?php endforeach; ?>

		<?php do_action( 'woocommerce_review_order_before_order_total' ); ?>

		<div class="aco-checkout-totals__row aco-checkout-totals__row--total order-total">
			<span><?php esc_html_e( 'Итого', 'woocommerce' ); ?></span>
			<span class="aco-checkout-totals__amount"><?php wc_cart_totals_order_total_html(); ?></span>
		</div>

		<?php do_action( 'woocommerce_review_order_after_order_total' ); ?>
	</div>
</div>
