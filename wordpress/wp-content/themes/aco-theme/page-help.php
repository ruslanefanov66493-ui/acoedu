<?php
/**
 * Help / FAQ page template.
 *
 * @package aco-theme
 */
get_header();
?>

<main id="main">

    <!-- Page hero -->
    <section class="lh-page-hero">
        <div class="lh-container">
            <nav class="lh-breadcrumbs" aria-label="breadcrumb">
                <a href="<?php echo esc_url( home_url( '/' ) ); ?>">Главная</a>
                <span class="lh-breadcrumbs__sep">›</span>
                <span class="lh-breadcrumbs__cur"><?php the_title(); ?></span>
            </nav>
            <h1 class="lh-page-hero__title">Частые вопросы</h1>
            <p class="lh-page-hero__sub">Отвечаем на самые популярные вопросы об обучении, оплате и сертификатах.</p>
        </div>
    </section>

    <!-- FAQ section -->
    <section class="lh-section lh-section--faq" id="faq" aria-label="Вопросы и ответы">
        <div class="lh-container">

            <!-- Tabs -->
            <div class="lh-faq-tabs" role="tablist" aria-label="Категории вопросов">
                <button class="lh-faq-tab is-active" data-filter="all" type="button" role="tab" aria-selected="true">Все вопросы</button>
                <button class="lh-faq-tab" data-filter="payment" type="button" role="tab" aria-selected="false">Оплата</button>
                <button class="lh-faq-tab" data-filter="learning" type="button" role="tab" aria-selected="false">Обучение</button>
                <button class="lh-faq-tab" data-filter="tech" type="button" role="tab" aria-selected="false">Технические вопросы</button>
                <button class="lh-faq-tab" data-filter="certificates" type="button" role="tab" aria-selected="false">Сертификаты</button>
            </div>

            <!-- FAQ list -->
            <div class="lh-faq-list">

                <?php
                $faq = [
                    [
                        'category' => 'payment',
                        'question' => 'Какие способы оплаты доступны?',
                        'answer'   => 'Оплата банковской картой или через СБП на защищённой странице ЮKassa. Данные карты на сайте АЦО не вводятся и не хранятся.',
                    ],
                    [
                        'category' => 'payment',
                        'question' => 'Как я получу курс после оплаты?',
                        'answer'   => 'Это цифровая услуга, доставки нет. После подтверждения платежа доступ открывается в личном кабинете и в учебной платформе Moodle. Подробнее — на странице «Как получить курс».',
                    ],
                    [
                        'category' => 'payment',
                        'question' => 'Как получить чек и договор?',
                        'answer'   => 'Договор — публичная оферта на сайте. Чек направляется на email из заказа после подключения онлайн-кассы / передачи чеков ЮKassa.',
                    ],
                    [
                        'category' => 'learning',
                        'question' => 'Сколько длится курс?',
                        'answer'   => 'Срок указан на странице каждого курса. Материалы доступны онлайн в течение оплаченного периода доступа.',
                    ],
                    [
                        'category' => 'learning',
                        'question' => 'Есть ли практические задания?',
                        'answer'   => 'Да. В программах есть задания и тесты. Объём практики указан в описании конкретного курса.',
                    ],
                    [
                        'category' => 'learning',
                        'question' => 'Как связаться с поддержкой?',
                        'answer'   => 'Напишите через форму «Контакты» или на inbox@acoedu.ru. Отвечаем в рабочие дни.',
                    ],
                    [
                        'category' => 'tech',
                        'question' => 'Как войти и начать обучение?',
                        'answer'   => 'После оплаты войдите в личный кабинет по email, который указали при регистрации, и откройте купленный курс. Обучение проходит на платформе Moodle.',
                    ],
                    [
                        'category' => 'tech',
                        'question' => 'Какие браузеры поддерживаются?',
                        'answer'   => 'Актуальные версии Chrome, Safari, Firefox, Edge и Яндекс.Браузера. Нужен стабильный интернет.',
                    ],
                    [
                        'category' => 'tech',
                        'question' => 'Не открывается урок. Что делать?',
                        'answer'   => 'Проверьте интернет, отключите блокировщик рекламы или попробуйте другой браузер. Если не поможет — напишите в «Контакты».',
                    ],
                    [
                        'category' => 'certificates',
                        'question' => 'Как получить сертификат?',
                        'answer'   => 'Электронный сертификат выдаётся, если это предусмотрено программой и выполнены условия на странице курса. Он появляется в учебном кабинете.',
                    ],
                    [
                        'category' => 'certificates',
                        'question' => 'Это государственная лицензия на образование?',
                        'answer'   => 'Сейчас АЦО оказывает информационно-консультационные услуги по обучению. Это не лицензируемая образовательная деятельность, пока у организации нет соответствующей лицензии. Это прямо указано в оферте.',
                    ],
                ];

                foreach ( $faq as $index => $item ) :
                    $id = 'faq-' . $index;
                    ?>
                    <div class="lh-faq-item" data-category="<?php echo esc_attr( $item['category'] ); ?>">
                        <button class="lh-faq-trigger" type="button" aria-expanded="false" aria-controls="<?php echo esc_attr( $id ); ?>">
                            <span class="lh-faq-question"><?php echo esc_html( $item['question'] ); ?></span>
                            <span class="lh-faq-icon" aria-hidden="true">+</span>
                        </button>
                        <div class="lh-faq-panel" id="<?php echo esc_attr( $id ); ?>">
                            <p><?php echo esc_html( $item['answer'] ); ?></p>
                        </div>
                    </div>
                    <?php
                endforeach;
                ?>

            </div>

        </div>
    </section>

</main>

<?php get_footer(); ?>
