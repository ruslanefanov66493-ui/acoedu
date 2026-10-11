<?php
/**
 * About page template.
 *
 * Based on Twenty Twenty-Two pattern: page-about-media-left.
 * Adapted for ACO LearnHub: education platform content + brand styling.
 *
 * @package aco-theme
 */
get_header();
?>

<main id="main">

    <!-- Page hero / breadcrumbs -->
    <section class="lh-page-hero">
        <div class="lh-container">
            <nav class="lh-breadcrumbs" aria-label="breadcrumb">
                <a href="<?php echo esc_url( home_url( '/' ) ); ?>">Главная</a>
                <span class="lh-breadcrumbs__sep">›</span>
                <span class="lh-breadcrumbs__cur"><?php the_title(); ?></span>
            </nav>
            <h1 class="lh-page-hero__title"><?php the_title(); ?></h1>
        </div>
    </section>

    <!-- Media-text section (converted from TT2 page-about-media-left) -->
    <section class="lh-section lh-about-media-section" aria-label="О платформе">
        <div class="lh-container">
            <div class="lh-about-media">

                <!-- Media side: educational illustration/hero image -->
                <div class="lh-about-media__media" aria-hidden="true">
                    <div class="lh-about-media__media-inner">
                        <img src="<?php echo esc_url( get_template_directory_uri() . '/assets/about-team.jpg' ); ?>"
                             alt="Студенты Академии цифрового образования">
                    </div>
                </div>

                <!-- Content side: heading, text, social -->
                <div class="lh-about-media__content">
                    <span class="lh-about-media__kicker">АЦО — Академия Цифрового Образования</span>

                    <h2 class="lh-about-media__title">Обучение, которое<br><em>меняет карьеру</em></h2>

                    <div class="lh-about-media__text">
                        <p>ООО «Академия цифрового образования» (ООО «АЦО») — онлайн-школа цифровых профессий. Мы запускаем практические курсы, которые можно пройти дистанционно: с компьютера или телефона, в своём темпе.</p>
                        <p>После оплаты доступ открывается в личном кабинете и на учебной платформе. Физической доставки нет — это цифровая услуга. Реквизиты и договор — в разделе «Контакты» и в публичной оферте.</p>
                    </div>

                    <div class="lh-about-media__actions">
                        <a class="lh-btn lh-btn--primary" href="<?php echo esc_url( home_url( '/catalog/' ) ); ?>">
                            Смотреть каталог
                        </a>
                        <a class="lh-btn lh-btn--outline" href="<?php echo esc_url( home_url( '/contacts/' ) ); ?>">
                            Связаться с нами
                        </a>
                    </div>
                </div>

            </div>
        </div>
    </section>

    <!-- Values + stats (kept from previous page.php implementation) -->
    <section class="lh-section">
        <div class="lh-container">
            <div class="lh-about-grid">
                <div class="lh-about-text">
                    <h2>Наши ценности</h2>
                    <p>Курсы рассчитаны на старт в профессии: практика, задания и доступ к материалам онлайн. Срок и программа — на странице каждого курса.</p>

                    <div class="lh-accordion">

                        <div class="lh-accordion__item">
                            <button class="lh-accordion__trigger" type="button" aria-expanded="false">
                                <span class="lh-accordion__icon" aria-hidden="true">📚</span>
                                <span class="lh-accordion__title">Практика, а не теория</span>
                                <span class="lh-accordion__toggle" aria-hidden="true">+</span>
                            </button>
                            <div class="lh-accordion__panel">
                                <p>Каждый курс построен вокруг реальных кейсов и рабочих задач. Студенты работают с проектами, которые можно положить в портфолио: разрабатывают приложения, анализируют данные, проектируют интерфейсы. В конце обучения вы получаете не только знания, но и готовые решения, которые можно показать работодателю.</p>
                            </div>
                        </div>

                        <div class="lh-accordion__item">
                            <button class="lh-accordion__trigger" type="button" aria-expanded="false">
                                <span class="lh-accordion__icon" aria-hidden="true">👩‍💼</span>
                                <span class="lh-accordion__title">Эксперты-практики</span>
                                <span class="lh-accordion__toggle" aria-hidden="true">+</span>
                            </button>
                            <div class="lh-accordion__panel">
                                <p>Программы собраны под реальные junior-задачи: код, макет, таблица, отчёт. Если у курса указан преподаватель — его имя на странице программы. Вопросы по учёбе можно задать через «Контакты».</p>
                            </div>
                        </div>

                        <div class="lh-accordion__item">
                            <button class="lh-accordion__trigger" type="button" aria-expanded="false">
                                <span class="lh-accordion__icon" aria-hidden="true">🎧</span>
                                <span class="lh-accordion__title">Поддержка ученика</span>
                                <span class="lh-accordion__toggle" aria-hidden="true">+</span>
                            </button>
                            <div class="lh-accordion__panel">
                                <p>Вопросы по доступу, оплате и программе можно задать через форму «Контакты» или на почту inbox@acoedu.ru. Отвечаем в рабочие дни.</p>
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

</main>

<?php get_footer(); ?>
