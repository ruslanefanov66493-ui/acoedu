<?php
/**
 * Front-page — "Главная" #71:27
 * Pixel-perfect by Figma node 1:2 → 🏠 WordPress — Public
 *
 * Structure:
 *   [INSTANCE] Header  #155:2862  (component 155:425)
 *   [FRAME]    Body    #121:332
 *     [FRAME]  Hero    #175:913   — gradient, h1 + desc + buttons + stats + image
 *     [FRAME]  Hero    #121:333   — Directions grid
 *     [FRAME]  Hero    #175:928   — Popular courses
 *     [FRAME]  form    #175:943   — CTA
 *   [INSTANCE] Footer  #175:605   (component 175:553)
 *
 * @package aco-theme
 */
if ( is_user_logged_in() && ! aco_is_platform_admin() && ! ( function_exists( 'aco_is_teacher' ) && aco_is_teacher() ) ) {
	wp_safe_redirect( home_url( '/account/' ) );
	exit;
}
get_header();
?>

<main id="main" role="main">

<!-- ──────────────────────────────────────────────────────────────────
     HERO  #175:913
     Figma: EL-cdb7ec5f
     layout_8aabe066: row, stretch, padding 60px 10px, justifyContent center, gap 10px
     fills: linear-gradient(-45deg, #EEF2FF 0%, #ECFEFF 100%)
─────────────────────────────────────────────────────────────────── -->
<section class="lh-hero" aria-label="Главный баннер">
    <div class="lh-hero__wallpaper" aria-hidden="true">
        <span class="lh-hero__blob lh-hero__blob--a"></span>
        <span class="lh-hero__blob lh-hero__blob--b"></span>
        <span class="lh-hero__blob lh-hero__blob--c"></span>
        <span class="lh-hero__blob lh-hero__blob--d"></span>
        <span class="lh-hero__blob lh-hero__blob--e"></span>
        <span class="lh-hero__blob lh-hero__blob--f"></span>
        <span class="lh-hero__blob lh-hero__blob--g"></span>
        <span class="lh-hero__blob lh-hero__blob--h"></span>
        <span class="lh-hero__blob lh-hero__blob--i"></span>
        <span class="lh-hero__blob lh-hero__blob--j"></span>
    </div>
    <div class="lh-hero__row">

        <!-- Left: column, padding 30px 20px, gap 40px  (#217:1517 / EL-dc427225) -->
        <div class="lh-hero__content">

            <!-- h1: "Обучение — {ts1}твой путь{/ts1}\nк будущему"
                 Inter Bold 48px, color #000000, ts1 → Primary-700 #4338CA -->
            <h1 class="lh-hero__title">
                Обучение — <span class="ts1">твой путь</span><br>к будущему
            </h1>

            <!-- h4 desc: "Профессиональные онлайн-курсы..."
                 Semi-Bold 600 / 24px, color secondary-text-600 #4B5563
                 Figma layout_930113fc: fixed width 751px -->
            <p class="lh-hero__desc">
                Практические онлайн-курсы по цифровым профессиям.<br>
                После оплаты доступ открывается в личном кабинете.
            </p>

            <!-- Buttons row (EL-f73b6cbc): layout_437f573a — row, center, gap 20px
                 Primary (25:3): bg Primary-600, br 12px, padding 16px 24px, text h4 white
                 Outline (26:68): white bg, stroke Primary-200 2px, br 12px, padding 16px 24px, text Primary-600 -->
            <div class="lh-hero__actions">
                <a class="lh-btn lh-btn--primary"
                   href="<?php echo esc_url( home_url( '/catalog/' ) ); ?>">
                    Начать обучение
                </a>
                <a class="lh-btn lh-btn--outline"
                   href="<?php echo esc_url( home_url( '/catalog/' ) ); ?>">
                    Смотреть каталог
                </a>
            </div>

            <!-- Stats row (#217:1522): row, stretch, alignItems center, gap 30px
                 Numbers: h2 style (36px/700), Primary-700
                 Labels: text style (16px/400), #000000 -->
            <div class="lh-hero__stats">
                <?php $aco_catalog_n = function_exists( 'aco_published_catalog_count' ) ? aco_published_catalog_count() : 0; ?>
                <div class="lh-stat">
                    <span class="lh-stat__num"><?php echo esc_html( (string) $aco_catalog_n ); ?></span>
                    <span class="lh-stat__label"><?php echo esc_html( aco_courses_word( $aco_catalog_n ) ); ?> в каталоге</span>
                </div>
                <div class="lh-stat">
                    <span class="lh-stat__num">Онлайн</span>
                    <span class="lh-stat__label">формат обучения</span>
                </div>
                <div class="lh-stat">
                    <span class="lh-stat__num">Сразу</span>
                    <span class="lh-stat__label">доступ после оплаты</span>
                </div>
            </div>
        </div>

    </div>
</section>


<!-- ──────────────────────────────────────────────────────────────────
     DIRECTIONS  #121:333
     Figma: column, stretch, padding 60px 10px 30px, alignItems center, gap 10px
     Background: white (fill_658ab2fa)
─────────────────────────────────────────────────────────────────── -->
<section class="lh-directions" aria-labelledby="directions-heading">
    <div class="lh-directions__inner">

        <!-- Section head (EL-4ffdb2f5): column, stretch, alignItems center, gap 20px -->
        <div class="lh-section-head">
            <!-- h2 (17:571): Inter Bold 36px, LEFT, #000000 -->
            <h2 class="lh-section-title" id="directions-heading">Выберите направление</h2>
            <!-- h4: Inter Semi-Bold 24px, secondary-text-600 -->
            <p class="lh-section-sub">Изучайте актуальные навыки в самых востребованных сферах</p>
        </div>

        <!-- Directions: same product_cat as /aco-admin/, centered row -->
        <div class="lh-dirs-grid">
            <?php
            $directions = function_exists( 'aco_get_course_directions' ) ? aco_get_course_directions() : [];
            if ( ! empty( $directions ) ) :
                foreach ( $directions as $dir ) :
                    $dir_url = add_query_arg( 'cat', $dir->slug, home_url( '/catalog/' ) );
            ?>
            <a class="lh-dir-card" href="<?php echo esc_url( $dir_url ); ?>">
                <span class="lh-dir-card__icon">
                    <?php echo aco_direction_icon_svg( $dir ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- hardcoded SVG ?>
                </span>
                <p class="lh-dir-card__name"><?php echo esc_html( $dir->name ); ?></p>
                <p class="lh-dir-card__count"><?php echo esc_html( aco_courses_count_label( $dir->count ) ); ?></p>
            </a>
            <?php
                endforeach;
            else :
            ?>
            <p class="lh-dirs-empty">Направления появятся, когда их создадут в панели.</p>
            <?php endif; ?>
        </div>
    </div>
</section>


<!-- ──────────────────────────────────────────────────────────────────
     POPULAR COURSES  #175:928
     Figma: EL-39730b44 (layout_55f801bd)
     column, stretch, padding 60px 10px, alignItems center, gap 10px
─────────────────────────────────────────────────────────────────── -->
<section class="lh-courses" aria-labelledby="courses-heading">
    <div class="lh-courses__inner">

        <div class="lh-section-head lh-courses__head">
            <h2 class="lh-section-title" id="courses-heading">Популярные курсы</h2>
            <a class="lh-courses__all" href="<?php echo esc_url( home_url( '/catalog/' ) ); ?>">Все курсы</a>
        </div>

        <?php
        $homepage_courses = function_exists( 'aco_get_homepage_courses' ) ? aco_get_homepage_courses( 12 ) : [];
        ?>

        <?php if ( ! empty( $homepage_courses ) ) : ?>
        <div class="lh-courses__carousel" data-courses-carousel>
            <div class="lh-courses__stage">
                <button type="button" class="lh-courses__nav lh-courses__nav--prev" aria-label="Предыдущие курсы">
                    <span aria-hidden="true">‹</span>
                </button>

                <div class="lh-courses__viewport">
                    <div class="lh-courses__track">
                        <?php foreach ( $homepage_courses as $product ) : ?>
                            <?php get_template_part( 'template-parts/course-card', null, [ 'post_id' => $product->get_id() ] ); ?>
                        <?php endforeach; ?>
                    </div>
                </div>

                <button type="button" class="lh-courses__nav lh-courses__nav--next" aria-label="Следующие курсы">
                    <span aria-hidden="true">›</span>
                </button>
            </div>

            <div class="lh-courses__dots" aria-label="Слайды курсов"></div>
        </div>
        <?php else : ?>
            <p class="lh-courses__empty">Курсы скоро появятся в <a href="<?php echo esc_url( home_url( '/catalog/' ) ); ?>">каталоге</a>.</p>
        <?php endif; ?>
    </div>
</section>


<!-- ──────────────────────────────────────────────────────────────────
     CTA / FORM  #175:943
     Figma: EL-d43b16b6 (layout_55f801bd)
     column, stretch, padding 60px 10px, alignItems center
     fills: Primary-700 (#4338CA)
─────────────────────────────────────────────────────────────────── -->
<section class="lh-cta" aria-label="Призыв к действию">
    <div class="lh-cta__inner">

        <!-- h2 (17:571): Bold 36px, white (fill_658ab2fa) -->
        <h2 class="lh-cta__title">Готовы начать обучение?</h2>

        <!-- text style: Regular 16px, Backgrounds (#F9FAFB) -->
        <p class="lh-cta__text">
            Присоединяйтесь к тысячам студентов, которые уже изменили свою карьеру с Академией цифрового образования.
        </p>

        <!-- Button (EL-dd80d2f9):
             row, padding 16px 32px, center, gap 10px, hug
             fills white, strokes Primary-200 2px, border-radius 32px
             text style_7001806a Bold/20px, fills Primary-600 -->
        <a class="lh-btn lh-btn--cta"
           href="<?php echo esc_url( wp_registration_url() ); ?>">
            Зарегистрироваться бесплатно
        </a>

    </div>
</section>

</main>

<?php get_footer(); ?>
