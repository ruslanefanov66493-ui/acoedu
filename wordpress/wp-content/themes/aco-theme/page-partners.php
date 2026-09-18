<?php
/**
 * Partners page template.
 *
 * @package aco-theme
 */
get_header();
?>

<main id="main">

    <!-- Partners Hero -->
    <section class="lh-partners-hero">
        <div class="lh-container lh-partners-hero__inner">
            <div class="lh-partners-hero__content">
                <nav class="lh-breadcrumbs" aria-label="breadcrumb">
                    <a href="<?php echo esc_url( home_url( '/' ) ); ?>">Главная</a>
                    <span class="lh-breadcrumbs__sep">›</span>
                    <span class="lh-breadcrumbs__cur"><?php the_title(); ?></span>
                </nav>
                
                <h1 class="lh-partners-hero__title">Сотрудничество <span>с АЦО</span></h1>
                <p class="lh-partners-hero__desc">Если вы представляете компанию, школу или сервис и хотите обсудить совместные программы — напишите нам. Условия согласовываем индивидуально, готового прайса комиссии на сайте нет.</p>
            </div>
            <div class="lh-partners-hero__image-wrap">
                <img class="lh-partners-hero__image"
                     src="<?php echo esc_url( get_template_directory_uri() . '/assets/partners-photo.jpg' ); ?>"
                     alt="Команда Академии цифрового образования">
            </div>
        </div>
    </section>

    <!-- Benefits Grid -->
    <section class="lh-section lh-partners-benefits">
        <div class="lh-container">
            <div class="lh-benefits-grid">
                <div class="lh-benefit-card">
                    <div class="lh-benefit-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><path d="M12 8v8M8 12h8"></path></svg>
                    </div>
                    <div class="lh-benefit-title">Заявка</div>
                    <p class="lh-benefit-text">Форма ниже или письмо на partners@acoedu.ru</p>
                </div>
                <div class="lh-benefit-card">
                    <div class="lh-benefit-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path></svg>
                    </div>
                    <div class="lh-benefit-title">Ответ</div>
                    <p class="lh-benefit-text">Свяжемся в рабочие дни и уточним задачу</p>
                </div>
                <div class="lh-benefit-card">
                    <div class="lh-benefit-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                    </div>
                    <div class="lh-benefit-title">Формат</div>
                    <p class="lh-benefit-text">Корпоративное обучение, контент, совместные проекты</p>
                </div>
                <div class="lh-benefit-card">
                    <div class="lh-benefit-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"></path></svg>
                    </div>
                    <div class="lh-benefit-title">Договор</div>
                    <p class="lh-benefit-text">Условия фиксируем отдельно, не через витрину курсов</p>
                </div>
            </div>
        </div>
    </section>

    <!-- How it works -->
    <section class="lh-section lh-partners-steps">
        <div class="lh-container">
            <h2 class="lh-partners-section-title">Как это работает?</h2>
            
            <div class="lh-steps-wrapper">
                <div class="lh-step">
                    <div class="lh-step-number">1</div>
                    <div class="lh-step-text">Оставьте заявку</div>
                </div>
                <div class="lh-step-line"></div>
                <div class="lh-step">
                    <div class="lh-step-number">2</div>
                    <div class="lh-step-text">Обсудим условия</div>
                </div>
                <div class="lh-step-line"></div>
                <div class="lh-step">
                    <div class="lh-step-number">3</div>
                    <div class="lh-step-text">Запустим<br>сотрудничество</div>
                </div>
            </div>
        </div>
    </section>

    <!-- Form Section -->
    <section class="lh-section lh-partners-form-section">
        <div class="lh-container">
            <div class="lh-partners-form-card">
                <h3 class="lh-partners-form-title">Оставить заявку</h3>
                <?php aco_form_status_notice( 'partner' ); ?>
                <form class="lh-partner-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <?php wp_nonce_field( 'aco_partner', 'aco_partner_nonce' ); ?>
                    <input type="hidden" name="action" value="aco_partner_form">

                    <div class="lh-form-row">
                        <div class="lh-form-group">
                            <label for="pf-name">Имя</label>
                            <input id="pf-name" type="text" name="pf_name" placeholder="Ваше имя" required>
                        </div>
                        <div class="lh-form-group">
                            <label for="pf-email">Email</label>
                            <input id="pf-email" type="email" name="pf_email" placeholder="ivan@mail.ru" required>
                            <p class="aco-field-hint"><?php echo esc_html( aco_email_field_hint() ); ?></p>
                        </div>
                    </div>

                    <div class="lh-form-row">
                        <div class="lh-form-group">
                            <label for="pf-phone">Телефон</label>
                            <input id="pf-phone" type="tel" name="pf_phone" placeholder="Ваш телефон" required>
                        </div>
                        <div class="lh-form-group">
                            <label for="pf-company">Компания</label>
                            <input id="pf-company" type="text" name="pf_company" placeholder="Как называется ваша компания?" required>
                        </div>
                    </div>

                    <div class="lh-form-group">
                        <label for="pf-msg">Расскажите о себе</label>
                        <textarea id="pf-msg" name="pf_message" placeholder="Напишите о вашей компании и чем она занимается..." required></textarea>
                    </div>

                    <div class="lh-form-submit">
                        <button class="lh-btn lh-btn--primary" type="submit">Отправить заявку</button>
                    </div>
                </form>
            </div>
        </div>
    </section>

</main>

<?php get_footer(); ?>
