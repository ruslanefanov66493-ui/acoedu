<?php
/**
 * Shared course card — catalog and homepage carousel (Skillbox-style).
 *
 * @package aco-theme
 *
 * @var array $args {
 *     @type int $post_id Optional. Product ID. Defaults to current post.
 * }
 */

defined( 'ABSPATH' ) || exit;

$post_id  = isset( $args['post_id'] ) ? (int) $args['post_id'] : get_the_ID();
$product  = function_exists( 'wc_get_product' ) ? wc_get_product( $post_id ) : null;
$duration = get_post_meta( $post_id, '_aco_course_duration', true );
$terms    = get_the_terms( $post_id, 'product_cat' );
$cat_name = ( ! is_wp_error( $terms ) && ! empty( $terms ) ) ? $terms[0]->name : 'Курс';
$permalink = get_permalink( $post_id );

$current_price = '';
$old_price     = '';
$discount      = 0;
$enroll_url    = $permalink;
$enroll_label  = 'Записаться';
$owns_course   = false;

if ( $product ) {
    $owns_course   = aco_user_owns_current_product( $product );
    $enroll_url    = aco_get_course_cta_url( $product );
    $enroll_label  = aco_get_course_cta_label( $product, 'Записаться' );
    $current_price = aco_format_price_display( $product->get_price() );
    if ( $product->is_on_sale() && $product->get_regular_price() !== '' ) {
        $regular   = (float) $product->get_regular_price();
        $sale      = (float) $product->get_sale_price();
        $old_price = aco_format_price_display( $regular );
        if ( $regular > 0 && $sale < $regular ) {
            $discount = (int) round( ( ( $regular - $sale ) / $regular ) * 100 );
        }
    }
}
?>
<article <?php post_class( 'lh-course-card2', $post_id ); ?>>
    <div class="lh-course-card2__top">
        <div class="lh-course-card2__intro">
            <p class="lh-course-card2__label"><?php echo esc_html( $cat_name ); ?></p>
            <h2 class="lh-course-card2__title">
                <a href="<?php echo esc_url( $permalink ); ?>"><?php echo esc_html( get_the_title( $post_id ) ); ?></a>
            </h2>
        </div>
        <a class="lh-course-card2__thumb" href="<?php echo esc_url( $permalink ); ?>" tabindex="-1" aria-hidden="true">
            <?php if ( has_post_thumbnail( $post_id ) ) : ?>
                <?php echo get_the_post_thumbnail( $post_id, 'medium', [ 'class' => 'lh-course-card2__img' ] ); ?>
            <?php else : ?>
                <span class="lh-course-card2__img lh-course-card2__img--placeholder">📘</span>
            <?php endif; ?>
        </a>
    </div>

    <div class="lh-course-card2__pricebox">
        <div class="lh-course-card2__prices">
            <?php if ( $old_price ) : ?>
                <span class="lh-course-card2__price-old"><?php echo esc_html( $old_price ); ?></span>
            <?php endif; ?>
            <span class="lh-course-card2__price-now"><?php echo $current_price ? esc_html( $current_price ) : 'По запросу'; ?></span>
            <?php if ( $duration ) : ?>
                <span class="lh-course-card2__price-note"><?php echo esc_html( $duration ); ?></span>
            <?php endif; ?>
        </div>
        <?php if ( $discount > 0 ) : ?>
            <span class="lh-course-card2__sale">−<?php echo esc_html( (string) $discount ); ?>%</span>
        <?php endif; ?>
    </div>

    <div class="lh-course-card2__actions">
        <a class="lh-course-card2__btn lh-course-card2__btn--ghost" href="<?php echo esc_url( $permalink ); ?>">Подробнее</a>
        <a class="lh-course-card2__btn lh-course-card2__btn--solid" href="<?php echo esc_url( $enroll_url ); ?>"<?php echo $owns_course ? ' target="_blank" rel="noopener"' : ''; ?>><?php echo esc_html( $enroll_label ); ?></a>
    </div>
</article>
