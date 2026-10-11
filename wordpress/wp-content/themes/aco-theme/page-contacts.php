<?php
/**
 * Contacts page template.
 *
 * Single-column contact form and details.
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

    <!-- Contacts section -->
    <section class="lh-section lh-contacts-section" aria-label="Контактная информация">
        <div class="lh-container">
            <div class="lh-contacts-grid">
                <div class="lh-contacts-form">
                    <h2 class="lh-contacts-form__title">Свяжитесь с нами</h2>
                    <p class="lh-contacts-form__desc">Заполните форму, и мы ответим вам в ближайшее время.</p>
                    <?php aco_form_status_notice( 'contact' ); ?>

                    <form class="lh-contact-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                        <?php wp_nonce_field( 'aco_contact', 'aco_nonce' ); ?>
                        <input type="hidden" name="action" value="aco_contact_form">

                        <div class="lh-form-group">
                            <label for="cf-name">Ваше имя</label>
                            <input id="cf-name" type="text" name="cf_name" placeholder="Иван" required>
                        </div>

                        <div class="lh-form-group">
                            <label for="cf-email">Email</label>
                            <input id="cf-email" type="email" name="cf_email" placeholder="ivan@mail.ru" required>
                            <p class="aco-field-hint"><?php echo esc_html( aco_email_field_hint() ); ?></p>
                        </div>

                        <div class="lh-form-group">
                            <label for="cf-msg">Сообщение</label>
                            <textarea id="cf-msg" name="cf_message" placeholder="Ваше сообщение..." required></textarea>
                        </div>

                        <button class="lh-btn lh-btn--contact" type="submit">Отправить сообщение</button>
                    </form>
                </div>

            </div>
            <?php aco_the_legal_requisites(); ?>
        </div>
    </section>

</main>

<?php get_footer(); ?>
