<?php
/**
 * Single course (WooCommerce product) custom template.
 * Skillbox-inspired structure, ACO styling.
 */

defined( 'ABSPATH' ) || exit;
get_header();
?>
<main id="main" class="lh-course-page">
<?php while ( have_posts() ) : the_post(); ?>
    <?php
    $product     = function_exists( 'wc_get_product' ) ? wc_get_product( get_the_ID() ) : null;
    $duration    = get_post_meta( get_the_ID(), '_aco_course_duration', true );
    $level       = get_post_meta( get_the_ID(), '_aco_course_level', true );
    $teacher     = function_exists( 'aco_teacher_display_name' ) ? aco_teacher_display_name( get_the_ID() ) : get_post_meta( get_the_ID(), '_aco_course_teacher', true );
    $terms       = get_the_terms( get_the_ID(), 'product_cat' );
    $cat_name    = ( ! is_wp_error( $terms ) && ! empty( $terms ) ) ? $terms[0]->name : 'Курс';
    $owns        = aco_user_owns_current_product( $product );
    $cta_url     = $product ? aco_get_course_cta_url( $product ) : '#';
    $cta_rel     = $owns ? ' target="_blank" rel="noopener"' : '';
    $program     = aco_get_course_program( get_the_ID() );
    $benefits    = aco_get_course_benefits( get_the_ID() );
    $faq         = aco_get_course_faq( get_the_ID() );
    $video_id    = aco_get_course_preview_video_id( get_the_ID() );
    $video_url   = aco_get_course_preview_video_url( get_the_ID() );
    $video_mime  = $video_id ? get_post_mime_type( $video_id ) : '';
    $excerpt     = get_the_excerpt();
    $has_about   = trim( (string) get_post()->post_content ) !== '';
    $poster      = get_the_post_thumbnail_url( get_the_ID(), 'large' );
    ?>
    <section class="lh-course-hero">
        <div class="lh-container lh-course-hero__grid">
            <div class="lh-course-hero__content">
                <nav class="lh-breadcrumbs" aria-label="breadcrumb">
                    <a href="<?php echo esc_url( home_url( '/' ) ); ?>">Главная</a>
                    <span class="lh-breadcrumbs__sep">›</span>
                    <a href="<?php echo esc_url( home_url( '/catalog/' ) ); ?>">Каталог</a>
                    <span class="lh-breadcrumbs__sep">›</span>
                    <span class="lh-breadcrumbs__cur"><?php the_title(); ?></span>
                </nav>

                <div class="lh-course-hero__badge"><?php echo esc_html( $cat_name ); ?></div>
                <h1 class="lh-course-hero__title"><?php the_title(); ?></h1>
                <?php if ( $excerpt ) : ?>
                <p class="lh-course-hero__desc"><?php echo esc_html( wp_trim_words( $excerpt, 28 ) ); ?></p>
                <?php endif; ?>

                <ul class="lh-course-hero__facts">
                    <li><strong><?php echo esc_html( $duration ?: 'Гибкий срок' ); ?></strong><span>длительность</span></li>
                    <li><strong><?php echo esc_html( $level ?: 'Все уровни' ); ?></strong><span>уровень</span></li>
                    <li><strong><?php echo esc_html( $teacher ?: 'Команда АЦО' ); ?></strong><span>куратор</span></li>
                </ul>

                <div class="lh-course-hero__actions">
                    <?php if ( $product ) : ?>
                        <a class="lh-btn lh-btn--primary" href="<?php echo esc_url( $cta_url ); ?>"<?php echo $cta_rel; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php echo esc_html( aco_get_course_cta_label( $product, 'Записаться на курс' ) ); ?></a>
                    <?php endif; ?>
                    <?php if ( $program ) : ?>
                    <a class="lh-btn lh-btn--outline" href="#course-program">Смотреть программу</a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="lh-course-hero__media">
                <div class="lh-course-video<?php echo $video_url ? ' lh-course-video--has-player' : ''; ?>">
                    <?php if ( $video_url ) : ?>
                        <video class="lh-course-video__player"<?php echo $poster ? ' poster="' . esc_url( $poster ) . '"' : ''; ?> preload="metadata" playsinline>
                            <source src="<?php echo esc_url( $video_url ); ?>"<?php echo $video_mime ? ' type="' . esc_attr( $video_mime ) . '"' : ''; ?>>
                        </video>
                        <button class="lh-course-video__play" type="button" aria-label="Смотреть видео о курсе">▶</button>
                    <?php elseif ( has_post_thumbnail() ) : ?>
                        <?php the_post_thumbnail( 'large', [ 'class' => 'lh-course-video__img' ] ); ?>
                    <?php else : ?>
                        <div class="lh-course-video__placeholder">▶</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <section class="lh-course-section lh-course-delivery" id="how-to-get">
        <div class="lh-container">
            <h2 class="lh-course-section__title">Как получить курс после оплаты</h2>
            <div class="lh-course-section__content">
                <p>Это цифровая услуга: почтой, курьером и самовывозом курс не отправляется. После подтверждения платежа ЮKassa доступ открывается в <a href="<?php echo esc_url( home_url( '/account/' ) ); ?>">личном кабинете</a> и в учебной платформе Moodle. Подробности — на странице <a href="<?php echo esc_url( home_url( '/how/' ) ); ?>">«Как получить курс»</a>.</p>
            </div>
        </div>
    </section>

    <?php if ( $has_about ) : ?>
    <section class="lh-course-section">
        <div class="lh-container">
            <h2 class="lh-course-section__title">О курсе</h2>
            <div class="lh-course-section__content">
                <?php the_content(); ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <?php if ( $program ) : ?>
    <section class="lh-course-section" id="course-program">
        <div class="lh-container">
            <h2 class="lh-course-section__title">Программа курса</h2>
            <div class="lh-course-modules">
                <?php foreach ( $program as $i => $mod ) : ?>
                <details class="lh-course-module"<?php echo 0 === $i ? ' open' : ''; ?>>
                    <summary><?php echo esc_html( $mod['title'] ); ?></summary>
                    <?php if ( ! empty( $mod['items'] ) ) : ?>
                    <ul>
                        <?php foreach ( $mod['items'] as $item ) : ?>
                        <li><?php echo esc_html( $item ); ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>
                </details>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <?php if ( $benefits ) : ?>
    <section class="lh-course-section">
        <div class="lh-container">
            <h2 class="lh-course-section__title">Что вы получите</h2>
            <div class="lh-course-benefits">
                <?php foreach ( $benefits as $row ) : ?>
                <div class="lh-course-benefit">
                    <h3><?php echo esc_html( $row['title'] ); ?></h3>
                    <?php if ( ! empty( $row['text'] ) ) : ?>
                    <p><?php echo esc_html( $row['text'] ); ?></p>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <?php if ( $faq ) : ?>
    <section class="lh-course-section lh-course-section--faq">
        <div class="lh-container">
            <h2 class="lh-course-section__title">Частые вопросы</h2>
            <div class="lh-course-faq">
                <?php foreach ( $faq as $row ) : ?>
                <details>
                    <summary><?php echo esc_html( $row['q'] ); ?></summary>
                    <?php if ( ! empty( $row['a'] ) ) : ?>
                    <p><?php echo esc_html( $row['a'] ); ?></p>
                    <?php endif; ?>
                </details>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <section class="lh-course-section lh-course-cta">
        <div class="lh-container lh-course-cta__inner">
            <div>
                <h2><?php echo esc_html( $owns ? 'Продолжить обучение?' : 'Готовы начать обучение?' ); ?></h2>
                <p><?php echo esc_html( $owns
                    ? 'Курс уже в вашем кабинете — откройте его в Moodle.'
                    : 'После оплаты доступ открывается сразу. Физической доставки нет.' ); ?></p>
            </div>
            <?php if ( $product ) : ?>
                <a class="lh-btn lh-btn--primary" href="<?php echo esc_url( $cta_url ); ?>"<?php echo $cta_rel; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php echo esc_html( aco_get_course_cta_label( $product, 'Купить курс' ) ); ?></a>
            <?php endif; ?>
        </div>
    </section>
<?php endwhile; ?>
</main>
<?php get_footer(); ?>
