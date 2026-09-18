<?php
/**
 * Template Name: Moodle Dashboard
 * Description: Mockup page for Moodle student dashboard UI.
 *
 * @package aco-theme
 */
get_header();
?>

<main id="main" class="md-page">
    <section class="md-topbar" aria-label="Moodle header">
        <div class="md-topbar__inner">
            <a class="md-topbar__brand" href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="АЦО">
                <img src="<?php echo esc_url( get_template_directory_uri() . '/assets/aco-logo.png' ); ?>" alt="Логотип АЦО">
            </a>

            <div class="md-topbar__user">
                <span class="md-topbar__avatar" aria-hidden="true"></span>
                <span class="md-topbar__name">Иван Иванов</span>
            </div>
        </div>
    </section>

    <section class="md-hero">
        <div class="md-hero__inner">
            <aside class="md-sidebar" aria-label="Навигация по кабинету">
                <a class="md-sidebar__item md-sidebar__item--active" href="#">Панель</a>
                <a class="md-sidebar__item" href="#">Страница курса</a>
                <a class="md-sidebar__item" href="#">Страница теста</a>
                <a class="md-sidebar__item" href="#">Урок</a>
                <a class="md-sidebar__item" href="#">Сертификат</a>
            </aside>

            <div class="md-content">
                <nav class="md-breadcrumbs" aria-label="breadcrumb">
                    <a href="<?php echo esc_url( home_url( '/' ) ); ?>">Главная</a>
                    <span>›</span>
                    <span>Панель</span>
                </nav>

                <div class="md-courses">
                    <article class="md-course-card">
                        <div class="md-course-card__thumb md-course-card__thumb--left"></div>
                        <h2 class="md-course-card__title">Fullstack JavaScript Developer</h2>
                        <p class="md-course-card__meta">Автор: Алексей Иванов • 42 часа • 68 уроков</p>

                        <div class="md-course-card__progress-row">
                            <div class="md-progress" role="progressbar" aria-valuenow="65" aria-valuemin="0" aria-valuemax="100">
                                <span style="width:65%"></span>
                            </div>
                            <span class="md-course-card__percent">65%</span>
                        </div>

                        <a class="md-course-card__btn" href="#">Продолжить обучение</a>
                    </article>

                    <article class="md-course-card">
                        <div class="md-course-card__thumb md-course-card__thumb--right"></div>
                        <h2 class="md-course-card__title">Основы маркетинга 2026</h2>
                        <p class="md-course-card__meta">Автор: Елена Смирнова • 16 часов • 24 урока</p>

                        <div class="md-course-card__progress-row">
                            <div class="md-progress" role="progressbar" aria-valuenow="100" aria-valuemin="0" aria-valuemax="100">
                                <span style="width:100%"></span>
                            </div>
                            <span class="md-course-card__percent">100%</span>
                        </div>

                        <a class="md-course-card__btn md-course-card__btn--success" href="#">Скачать сертификат</a>
                    </article>
                </div>
            </div>
        </div>
    </section>
</main>

<?php get_footer(); ?>
