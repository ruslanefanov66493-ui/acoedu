<?php
/**
 * Inner pages: About / Catalog / Contacts + generic fallback.
 *
 * @package aco-theme
 */
get_header();

$slug = get_post_field( 'post_name', get_the_ID() );
?>
<main id="main">

    <!-- Page head / breadcrumbs -->
    <section class="lh-page-head">
        <div class="lh-container">
            <nav class="lh-breadcrumbs" aria-label="breadcrumb">
                <a href="<?php echo esc_url( home_url( '/' ) ); ?>">Главная</a>
                <span class="lh-breadcrumbs__sep">›</span>
                <span class="lh-breadcrumbs__cur"><?php the_title(); ?></span>
            </nav>
            <h1 class="lh-section__title"><?php the_title(); ?></h1>
        </div>
    </section>

    <?php if ( in_array( $slug, [ 'about', 'o-nas', 'о-нас' ], true ) ) : ?>
    <!-- ── О НАС ─────────────────────────────────────────────────── -->
    <section class="lh-section">
        <div class="lh-container">
            <div class="lh-about-split">
                <div class="lh-about-text">
                    <h2>Мы делаем образование доступным и практичным</h2>
                    <p>Мы верим, что качественное образование должно быть доступно каждому, независимо от места жительства, возраста или финансового положения.</p>
                    <p>Наша цель — помочь людям получить практические навыки, которые можно сразу применить в работе, и уверенно стартовать в новой профессии.</p>
                    <div class="lh-about-values">
                        <div class="lh-value-item">
                            <span class="lh-value-icon">📚</span>
                            <div class="lh-value-text">
                                <p class="lh-value-title">Практика, а не теория</p>
                                <p class="lh-value-desc">Реальные проекты и кейсы</p>
                            </div>
                        </div>
                        <div class="lh-value-item">
                            <span class="lh-value-icon">👩‍💼</span>
                            <div class="lh-value-text">
                                <p class="lh-value-title">Эксперты-практики</p>
                                <p class="lh-value-desc">Преподаватели из индустрии</p>
                            </div>
                        </div>
                        <div class="lh-value-item">
                            <span class="lh-value-icon">🎧</span>
                            <div class="lh-value-text">
                                <p class="lh-value-title">Поддержка ученика</p>
                                <p class="lh-value-desc">Форма «Контакты» и почта inbox@acoedu.ru</p>
                            </div>
                        </div>
                        <div class="lh-value-item">
                            <span class="lh-value-icon">🎓</span>
                            <div class="lh-value-text">
                                <p class="lh-value-title">Проверка работ</p>
                                <p class="lh-value-desc">Домашние задания смотрит преподаватель</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div>
                    <?php $aco_catalog_n = function_exists( 'aco_published_catalog_count' ) ? aco_published_catalog_count() : 0; ?>
                    <div class="lh-stats-strip">
                        <div class="lh-stat-card">
                            <span class="lh-stat-card__num">2026</span>
                            <span class="lh-stat-card__label">год запуска</span>
                            <span class="lh-stat-card__sub">ООО «АЦО»</span>
                        </div>
                        <div class="lh-stat-card">
                            <span class="lh-stat-card__num"><?php echo esc_html( (string) $aco_catalog_n ); ?></span>
                            <span class="lh-stat-card__label"><?php echo esc_html( aco_courses_word( $aco_catalog_n ) ); ?></span>
                            <span class="lh-stat-card__sub">В открытом каталоге</span>
                        </div>
                        <div class="lh-stat-card">
                            <span class="lh-stat-card__num">Онлайн</span>
                            <span class="lh-stat-card__label">формат</span>
                            <span class="lh-stat-card__sub">Доступ после оплаты</span>
                        </div>
                        <div class="lh-stat-card">
                            <span class="lh-stat-card__num">Саратов</span>
                            <span class="lh-stat-card__label">юр. адрес</span>
                            <span class="lh-stat-card__sub">Обучение дистанционное</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <?php elseif ( in_array( $slug, [ 'catalog', 'katalog', 'каталог' ], true ) ) : ?>
    <section class="lh-section">
        <div class="lh-container">
            <p><a href="<?php echo esc_url( home_url( '/catalog/' ) ); ?>">Перейти в каталог курсов</a></p>
            <div class="lh-catalog-grid">
                <?php
                $fallback_q = new WP_Query( [
                    'post_type'      => 'product',
                    'post_status'    => 'publish',
                    'posts_per_page' => 12,
                ] );
                if ( $fallback_q->have_posts() ) :
                    while ( $fallback_q->have_posts() ) :
                        $fallback_q->the_post();
                        get_template_part( 'template-parts/course-card' );
                    endwhile;
                    wp_reset_postdata();
                endif;
                ?>
            </div>
        </div>
    </section>

    <?php elseif ( in_array( $slug, [ 'contacts', 'kontakty', 'контакты' ], true ) ) : ?>
    <!-- ── КОНТАКТЫ ──────────────────────────────────────────────── -->
    <section class="lh-section">
        <div class="lh-container">
            <div class="lh-contact-grid">
                <div class="lh-contact-info">
                    <h3>Свяжитесь с нами</h3>
                    <ul class="lh-contact-list">
                        <?php aco_the_public_contacts( 'list' ); ?>
                    </ul>
                    <?php if ( function_exists( 'aco_the_legal_requisites' ) ) { aco_the_legal_requisites(); } ?>
                </div>

                <form class="lh-contact-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <input type="hidden" name="action" value="aco_contact_form">
                    <?php wp_nonce_field( 'aco_contact', 'aco_nonce' ); ?>
                    <div class="lh-form-group">
                        <label for="cf-name">Ваше имя</label>
                        <input id="cf-name" type="text" name="cf_name" placeholder="Иван Иванов" required>
                    </div>
                    <div class="lh-form-group">
                        <label for="cf-email">Email</label>
                        <input id="cf-email" type="email" name="cf_email" placeholder="mail@example.com" required>
                    </div>
                    <div class="lh-form-group">
                        <label for="cf-msg">Сообщение</label>
                        <textarea id="cf-msg" name="cf_message" placeholder="Хочу подобрать курс для смены профессии" required></textarea>
                    </div>
                    <button class="lh-btn lh-btn--primary" type="submit" style="width:100%">Отправить</button>
                </form>
            </div>
        </div>
    </section>

    <?php else : ?>
    <!-- ── Generic page content ──────────────────────────────────── -->
    <section class="lh-section">
        <div class="lh-container">
            <?php while ( have_posts() ) : the_post(); ?>
                <article class="lh-single-article">
                    <?php the_content(); ?>
                </article>
            <?php endwhile; ?>
        </div>
    </section>
    <?php endif; ?>

</main>

<?php get_footer(); ?>
