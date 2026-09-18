<?php
/**
 * Single post template — dark article design.
 *
 * @package aco-theme
 */
get_header();

$blog_page_id = get_option( 'page_for_posts' );
?>
<main id="main" class="lh-blog-page">
    <?php while ( have_posts() ) : the_post(); ?>

        <section class="lh-page-hero">
            <div class="lh-container">
                <nav class="lh-breadcrumbs" aria-label="breadcrumb">
                    <a href="<?php echo esc_url( home_url( '/' ) ); ?>">Главная</a>
                    <span class="lh-breadcrumbs__sep">›</span>
                    <a href="<?php echo esc_url( get_permalink( $blog_page_id ) ); ?>">Блог</a>
                    <span class="lh-breadcrumbs__sep">›</span>
                    <span class="lh-breadcrumbs__cur">Страница статьи</span>
                </nav>
            </div>
        </section>

        <section class="lh-section lh-article-section">
            <div class="lh-container">
                <article <?php post_class( 'lh-article-card' ); ?>>
                    <header class="lh-article-header">
                        <?php
                        $cats     = get_the_category();
                        $category = ! empty( $cats ) ? $cats[0]->name : 'Блог';
                        ?>
                        <div class="lh-article-meta">
                            <span class="lh-article-category"><?php echo esc_html( $category ); ?></span>
                            <span class="lh-article-meta-sep">•</span>
                            <time><?php echo esc_html( get_the_date( 'j F Y' ) ); ?></time>
                        </div>

                        <h1 class="lh-article-title"><?php the_title(); ?></h1>

                        <div class="lh-article-cover">
                            <?php if ( has_post_thumbnail() ) : ?>
                                <?php the_post_thumbnail( 'large', [ 'class' => 'lh-article-cover__img' ] ); ?>
                            <?php else : ?>
                                <div class="lh-article-cover__placeholder">📰</div>
                            <?php endif; ?>
                        </div>
                    </header>

                    <hr class="lh-article-divider">

                    <div class="lh-article-content">
                        <?php the_content(); ?>
                    </div>

                    <?php
                    $tags = get_the_tags();
                    if ( ! empty( $tags ) ) : ?>
                        <div class="lh-article-tags">
                            <?php foreach ( $tags as $tag ) : ?>
                                <span class="lh-article-tag">#<?php echo esc_html( $tag->name ); ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </article>

                <div class="lh-article-back">
                    <a class="lh-btn lh-btn--outline" href="<?php echo esc_url( get_permalink( $blog_page_id ) ); ?>">← Назад к блогу</a>
                </div>
            </div>
        </section>

    <?php endwhile; ?>
</main>
<?php get_footer(); ?>
