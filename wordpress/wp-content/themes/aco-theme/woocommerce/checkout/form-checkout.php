<?php
/**
 * Checkout Form
 * Theme override: aco-theme/woocommerce/checkout/form-checkout.php
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( WC()->cart && WC()->cart->is_empty() ) {
	?>
	<div class="aco-checkout-empty aco-checkout-card">
		<h2>Корзина пуста</h2>
		<p>Выберите курс в каталоге, чтобы перейти к оплате.</p>
		<a class="lh-btn lh-btn--primary" href="<?php echo esc_url( home_url( '/catalog/' ) ); ?>">Перейти в каталог</a>
	</div>
	<?php
	return;
}

do_action( 'woocommerce_before_checkout_form', $checkout );

if ( ! $checkout->is_registration_enabled() && $checkout->is_registration_required() && ! is_user_logged_in() ) {
	echo esc_html( apply_filters( 'woocommerce_checkout_must_be_logged_in_message', __( 'You must be logged in to checkout.', 'woocommerce' ) ) );
	return;
}
?>

<form name="checkout" method="post" class="checkout woocommerce-checkout aco-checkout" action="<?php echo esc_url( wc_get_checkout_url() ); ?>" enctype="multipart/form-data" aria-label="<?php echo esc_attr__( 'Checkout', 'woocommerce' ); ?>">

	<div class="aco-checkout__grid">
		<div class="aco-checkout__left">
			<?php if ( $checkout->get_checkout_fields() ) : ?>
				<?php do_action( 'woocommerce_checkout_before_customer_details' ); ?>

				<section class="aco-checkout-card aco-checkout-card--buyer">
					<h2 class="aco-checkout-card__title">Данные покупателя</h2>
					<p class="aco-checkout-card__lead">Нужны для чека и письма с доступом к курсу.</p>
					<div id="customer_details">
						<?php do_action( 'woocommerce_checkout_billing' ); ?>
					</div>
				</section>

				<div class="aco-checkout-shipping-hidden" hidden>
					<?php do_action( 'woocommerce_checkout_shipping' ); ?>
				</div>

				<?php do_action( 'woocommerce_checkout_after_customer_details' ); ?>
			<?php endif; ?>
		</div>

		<aside class="aco-checkout__right">
			<section class="aco-checkout-card aco-checkout-card--summary">
				<h2 class="aco-checkout-card__title"><?php esc_html_e( 'Ваш заказ', 'woocommerce' ); ?></h2>

				<?php do_action( 'woocommerce_checkout_before_order_review' ); ?>

				<div id="order_review" class="woocommerce-checkout-review-order">
					<?php do_action( 'woocommerce_checkout_order_review' ); ?>
				</div>

				<?php do_action( 'woocommerce_checkout_after_order_review' ); ?>
			</section>
		</aside>
	</div>

</form>

<?php do_action( 'woocommerce_after_checkout_form', $checkout ); ?>
