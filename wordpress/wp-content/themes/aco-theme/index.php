<?php
/**
 * Fallback index template.
 *
 * @package aco-theme
 */
get_header();
?>
<main id="main">
    <section class="lh-section">
        <div class="lh-container">
            <?php if ( have_posts() ) : ?>
                <div class="lh-blog-grid">
                    <?php while ( have_posts() ) : the_post(); ?>
                        <article <?php post_class( 'lh-blog-card' ); ?>>
                            <div class="lh-blog-card__body">
                                <h2 class="lh-blog-card__title">
                                    <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
                                </h2>
                                <p class="lh-blog-card__excerpt"><?php echo esc_html( wp_trim_words( get_the_excerpt(), 20 ) ); ?></p>
                            </div>
                        </article>
                    <?php endwhile; ?>
                </div>
            <?php else : ?>
                <p>Контент не найден.</p>
            <?php endif; ?>
        </div>
    </section>
</main>
<?php get_footer(); ?>
