<?php
/**
 * Blog listing template — dark cards design.
 *
 * @package aco-theme
 */
get_header();

$blog_page_id = get_option( 'page_for_posts' );
?>
<main id="main" class="lh-blog-page">
    <section class="lh-page-hero">
        <div class="lh-container">
            <nav class="lh-breadcrumbs" aria-label="breadcrumb">
                <a href="<?php echo esc_url( home_url( '/' ) ); ?>">Главная</a>
                <span class="lh-breadcrumbs__sep">›</span>
                <span class="lh-breadcrumbs__cur">Блог</span>
            </nav>
            <h1 class="lh-page-hero__title">Блог АЦО</h1>
            <p class="lh-page-hero__sub">Статьи о карьере, обучении и практике в профессии.</p>
        </div>
    </section>

    <section class="lh-section lh-blog-section">
        <div class="lh-container">
            <?php if ( have_posts() ) : ?>
                <div class="lh-blog-grid">
                    <?php while ( have_posts() ) : the_post(); ?>
                        <?php
                            $cats        = get_the_category();
                            $category    = ! empty( $cats ) ? $cats[0]->name : 'Блог';
                            $word_count  = str_word_count( strip_tags( get_the_content() ) );
                            $read_time   = max( 1, ceil( $word_count / 200 ) );
                        ?>
                        <article <?php post_class( 'lh-blog-card' ); ?>>
                            <a class="lh-blog-card__link" href="<?php the_permalink(); ?>" aria-label="<?php echo esc_attr( get_the_title() ); ?>">
                                <div class="lh-blog-card__media">
                                    <?php if ( has_post_thumbnail() ) : ?>
                                        <?php the_post_thumbnail( 'large', [ 'class' => 'lh-blog-card__img' ] ); ?>
                                    <?php else : ?>
                                        <div class="lh-blog-card__img lh-blog-card__img--placeholder">
                                            <span>📰</span>
                                        </div>
                                    <?php endif; ?>
                                    <span class="lh-blog-card__media-badge"><?php echo esc_html( $category ); ?></span>
                                </div>

                                <div class="lh-blog-card__body">
                                    <h2 class="lh-blog-card__title"><?php the_title(); ?></h2>
                                    <p class="lh-blog-card__excerpt"><?php echo esc_html( wp_trim_words( get_the_excerpt(), 18 ) ); ?></p>

                                    <div class="lh-blog-card__footer">
                                        <time class="lh-blog-card__date"><?php echo esc_html( get_the_date( 'j F Y' ) ); ?></time>
                                        <span class="lh-blog-card__read-time"><?php echo esc_html( $read_time ); ?> мин чтения</span>
                                    </div>
                                </div>
                            </a>
                        </article>
                    <?php endwhile; ?>
                </div>

                <div class="lh-blog-pagination">
                    <?php the_posts_pagination( [
                        'prev_text' => '← Назад',
                        'next_text' => 'Вперёд →',
                        'mid_size'  => 2,
                    ] ); ?>
                </div>
            <?php else : ?>
                <p class="lh-blog-empty">Пока нет публикаций.</p>
            <?php endif; ?>
        </div>
    </section>
</main>
<?php get_footer(); ?>
