<?php
/**
 * Footer — component 175:553
 * Pixel-perfect: node #175:605 / template EL-4a3746fe
 *
 * layout: row, padding 30px 0px, justifyContent center, alignItems center, 1920px
 * fills: header token (#111827)
 *
 * Inner #I175:605;121:245 (EL-4ede96ee):
 *   column, padding 10px, 1280px, gap 30px
 *
 * Columns:
 *   Brand (319px): logo 182×70 + desc small-text white
 *   Контакты: phone, email, address, Telegram (icon), Whatsapp (icon)
 *   Платформа: Каталог курсов, О нас, Блог
 *   Поддержка: Помощь, Контакты, FAQ, Партнёрам
 *   Правовая информация: Публичная оферта, Политика конфиденциальности, Обработка данных, Возврат средств
 *
 * Bottom row: © 2026 EduPro. Все права защищены. | Cookie Policy | Sitemap
 *
 * @package aco-theme
 */
?>

<?php if ( is_user_logged_in() ) : ?>
<footer class="lh-footer lh-footer--compact" role="contentinfo">
    <div class="lh-footer__inner lh-footer__inner--compact">
        <div class="lh-footer__bottom lh-footer__bottom--compact">
            <p class="lh-footer__copy">
                &copy; <?php echo esc_html( gmdate( 'Y' ) ); ?> АЦО. Все права защищены.
            </p>
            <div class="lh-footer__legal">
                <a class="lh-footer__legal-link" href="<?php echo esc_url( aco_legal_url( 'legal' ) ); ?>">Документы</a>
                <a class="lh-footer__legal-link" href="<?php echo esc_url( aco_legal_url( 'oferta' ) ); ?>">Оферта</a>
                <a class="lh-footer__legal-link" href="<?php echo esc_url( aco_legal_url( 'privacy' ) ); ?>">Конфиденциальность</a>
                <a class="lh-footer__legal-link" href="<?php echo esc_url( aco_legal_url( 'refund' ) ); ?>">Возврат</a>
                <a class="lh-footer__legal-link" href="<?php echo esc_url( aco_legal_url( 'personal-data' ) ); ?>">Персональные данные</a>
                <a class="lh-footer__legal-link" href="<?php echo esc_url( aco_legal_url( 'cookies' ) ); ?>">Cookies</a>
                <a class="lh-footer__legal-link" href="<?php echo esc_url( home_url( '/contacts/' ) ); ?>">Контакты</a>
            </div>
        </div>
    </div>
</footer>
<?php else : ?>
<footer class="lh-footer" role="contentinfo">
    <div class="lh-footer__inner">

        <!-- Top row (EL-ffc1b4d2): row, stretch, justifyContent space-between -->
        <div class="lh-footer__top">

            <!-- Brand column (EL-8be79202): column, stretch, gap 20px, width 319px -->
            <div class="lh-footer__brand">
                <?php if ( has_custom_logo() ) : ?>
                    <?php the_custom_logo(); ?>
                <?php else : ?>
                    <!-- Logo rectangle: 182×70 (EL-54428f2e: fixed 182×70, imageRef 5046d...) -->
                    <span class="lh-footer__logo-text">АЦО - Академия Цифрового Образования </span>
                <?php endif; ?>
                <!-- Desc: small-text 14px, white (fill_658ab2fa) -->
                <p class="lh-footer__desc">
                    ООО «Академия цифрового образования» — онлайн-курсы цифровых профессий. Доступ после оплаты, без доставки товара.
                </p>
            </div>

            <div class="lh-footer__cols">
            <!-- Contacts column (EL-afba9f32): column, stretch, gap 10px -->
            <div class="lh-footer__col lh-footer__col--contacts">
                <!-- Title: style_cd9f3e0c Bold/14px, white -->
                <p class="lh-footer__col-title">Контакты</p>
                <!-- Links: small-text, placeholder color (#6B7280) -->
                <?php aco_the_public_contacts( 'footer' ); ?>
                <a class="lh-footer__link" href="<?php echo esc_url( home_url( '/how/' ) ); ?>">Как получить курс</a>
            </div>

            <!-- Platform column -->
            <div class="lh-footer__col">
                <p class="lh-footer__col-title">Платформа</p>
                <a class="lh-footer__link" href="<?php echo esc_url( home_url( '/catalog/' ) ); ?>">Каталог курсов</a>
                <a class="lh-footer__link" href="<?php echo esc_url( home_url( '/about/' ) ); ?>">О нас</a>
                <a class="lh-footer__link" href="<?php echo esc_url( home_url( '/blog/' ) ); ?>">Блог</a>
            </div>

            <!-- Support column -->
            <div class="lh-footer__col">
                <p class="lh-footer__col-title">Поддержка</p>
                <a class="lh-footer__link" href="<?php echo esc_url( home_url( '/help/' ) ); ?>">Помощь</a>
                <a class="lh-footer__link" href="<?php echo esc_url( home_url( '/contacts/' ) ); ?>">Контакты</a>
                <a class="lh-footer__link" href="<?php echo esc_url( home_url( '/help/' ) ); ?>">FAQ</a>
                <a class="lh-footer__link" href="<?php echo esc_url( home_url( '/partners/' ) ); ?>">Партнёрам</a>
            </div>

            <!-- Legal column (EL-5374532d) -->
            <div class="lh-footer__col lh-footer__col--legal">
                <p class="lh-footer__col-title">Правовая информация</p>
                <a class="lh-footer__link" href="<?php echo esc_url( aco_legal_url( 'legal' ) ); ?>">Все документы</a>
                <a class="lh-footer__link" href="<?php echo esc_url( aco_legal_url( 'oferta' ) ); ?>">Публичная оферта</a>
                <a class="lh-footer__link" href="<?php echo esc_url( aco_legal_url( 'privacy' ) ); ?>">Политика конфиденциальности</a>
                <a class="lh-footer__link" href="<?php echo esc_url( aco_legal_url( 'personal-data' ) ); ?>">Обработка данных</a>
                <a class="lh-footer__link" href="<?php echo esc_url( aco_legal_url( 'refund' ) ); ?>">Возврат средств</a>
                <a class="lh-footer__link" href="<?php echo esc_url( aco_legal_url( 'cookies' ) ); ?>">Cookies</a>
                <a class="lh-footer__link" href="<?php echo esc_url( home_url( '/how/' ) ); ?>">Как получить курс</a>
            </div>
            </div>

        </div>

        <!-- Divider (EL-d8bad5fb): fill, placeholder color (#6B7280), 1px height -->
        <div class="lh-footer__divider" role="separator" aria-hidden="true"></div>

        <!-- Bottom row (EL-de1d2336): row, stretch, alignItems center
             "© 2026 EduPro. Все права защищены." + Cookie Policy | Sitemap
             All: small-text 14px, placeholder (#6B7280) -->
        <div class="lh-footer__bottom">
            <!-- EL-932a8055: small-text, placeholder -->
            <p class="lh-footer__copy">
                &copy; <?php echo esc_html( gmdate( 'Y' ) ); ?> АЦО. Все права защищены.
            </p>
            <!-- EL-db29abf4: row, gap 20px -->
            <div class="lh-footer__legal">
                <!-- ts4 fills: placeholder (#6B7280) -->
                <a class="lh-footer__legal-link" href="<?php echo esc_url( aco_legal_url( 'cookies' ) ); ?>">Политика использования файлов cookies</a>
                
            </div>
        </div>

    </div>
</footer>
<?php endif; ?>

<?php wp_footer(); ?>
</body>
</html>
