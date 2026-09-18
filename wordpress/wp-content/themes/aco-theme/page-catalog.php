<?php
/**
 * Template Name: Каталог курсов
 * Modern catalog inspired by Skillbox structure, styled for ACO.
 */

defined( 'ABSPATH' ) || exit;
get_header();

$current_cat = isset( $_GET['cat'] ) ? sanitize_text_field( wp_unslash( $_GET['cat'] ) ) : '';
$current_tag = isset( $_GET['tag'] ) ? sanitize_text_field( wp_unslash( $_GET['tag'] ) ) : '';

$args = [
    'post_type'      => 'product',
    'post_status'    => 'publish',
    'posts_per_page' => 12,
];

$owned = aco_get_owned_product_ids();
if ( $owned ) {
    $args['post__not_in'] = $owned;
}

$tax_query = [];
if ( $current_cat ) {
    $tax_query[] = [
        'taxonomy' => 'product_cat',
        'field'    => 'slug',
        'terms'    => $current_cat,
    ];
}
if ( $current_tag ) {
    $tax_query[] = [
        'taxonomy' => 'product_tag',
        'field'    => 'slug',
        'terms'    => $current_tag,
    ];
}
if ( $tax_query ) {
    $args['tax_query'] = $tax_query;
}

$q = new WP_Query( $args );
$cats = function_exists( 'aco_get_course_directions' ) ? aco_get_course_directions() : [];
?>
<main id="main" class="lh-catalog-page">
    <section class="lh-catalog-hero">
        <div class="lh-container">
            <nav class="lh-breadcrumbs" aria-label="breadcrumb">
                <a href="<?php echo esc_url( home_url( '/' ) ); ?>">Главная</a>
                <span class="lh-breadcrumbs__sep">›</span>
                <span class="lh-breadcrumbs__cur">Каталог курсов</span>
            </nav>
            <h1 class="lh-catalog-hero__title">Все программы</h1>
            <p class="lh-catalog-hero__sub">Онлайн-курсы с ценой на карточке. После оплаты доступ открывается в личном кабинете — физической доставки нет. <a href="<?php echo esc_url( home_url( '/how/' ) ); ?>">Как получить курс</a></p>
        </div>
    </section>

    <section class="lh-catalog-main">
        <div class="lh-container">
            <div class="lh-catalog-filters">
                <a class="lh-catalog-filter<?php echo $current_cat === '' ? ' is-active' : ''; ?>" href="<?php echo esc_url( home_url( '/catalog/' ) ); ?>">Все</a>
				<?php if ( ! empty( $cats ) ) : ?>
                    <?php foreach ( $cats as $cat ) : ?>
                        <a class="lh-catalog-filter<?php echo $current_cat === $cat->slug ? ' is-active' : ''; ?>"
                           href="<?php echo esc_url( add_query_arg( 'cat', $cat->slug, home_url( '/catalog/' ) ) ); ?>">
                            <?php echo esc_html( $cat->name ); ?>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <?php if ( $q->have_posts() ) : ?>
                <div class="lh-catalog-grid">
                    <?php while ( $q->have_posts() ) : $q->the_post(); ?>
                        <?php get_template_part( 'template-parts/course-card' ); ?>
                    <?php endwhile; ?>
                </div>
                <?php wp_reset_postdata(); ?>
            <?php else : ?>
                <p class="lh-blog-empty"><?php echo ( is_user_logged_in() && $owned )
                    ? 'Вы уже записаны на все курсы из этого раздела.'
                    : 'Пока нет опубликованных курсов.'; ?></p>
            <?php endif; ?>
        </div>
    </section>
</main>
<?php get_footer(); ?>
