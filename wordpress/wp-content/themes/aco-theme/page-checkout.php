<?php
/**
 * Checkout page — two-card Skillbox-like layout.
 *
 * @package aco-theme
 */

defined( 'ABSPATH' ) || exit;
get_header();

$is_received = function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'order-received' );
$is_pay      = function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'order-pay' );
?>
<main id="main" class="lh-checkout-page">
    <section class="lh-checkout-hero">
        <div class="lh-container">
            <nav class="lh-breadcrumbs" aria-label="breadcrumb">
                <a href="<?php echo esc_url( home_url( '/' ) ); ?>">Главная</a>
                <span class="lh-breadcrumbs__sep">›</span>
                <a href="<?php echo esc_url( home_url( '/catalog/' ) ); ?>">Каталог</a>
                <span class="lh-breadcrumbs__sep">›</span>
                <span class="lh-breadcrumbs__cur"><?php
                    if ( $is_received ) {
                        echo 'Заказ оформлен';
                    } elseif ( $is_pay ) {
                        echo 'Оплата';
                    } else {
                        echo 'Оформление заказа';
                    }
                ?></span>
            </nav>
            <h1 class="lh-checkout-hero__title"><?php
                if ( $is_received ) {
                    echo 'Заказ оформлен';
                } elseif ( $is_pay ) {
                    echo 'Оплата курса';
                } else {
                    echo 'Оформление заказа';
                }
            ?></h1>
            <?php if ( $is_pay ) : ?>
                <p class="lh-checkout-hero__sub">Перейдите на защищённую страницу ЮKassa — курс откроется сразу после платежа.</p>
            <?php elseif ( ! $is_received ) : ?>
                <p class="lh-checkout-hero__sub">Проверьте данные и подтвердите оплату курса.</p>
            <?php endif; ?>
        </div>
    </section>

    <section class="lh-checkout-main">
        <div class="lh-container">
            <?php
            if ( function_exists( 'woocommerce_output_all_notices' ) ) {
                woocommerce_output_all_notices();
            }
            while ( have_posts() ) :
                the_post();
                the_content();
            endwhile;
            ?>
        </div>
    </section>
</main>
<?php
get_footer();
