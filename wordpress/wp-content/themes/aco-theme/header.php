<?php
/**
 * Header — component 155:425
 * Pixel-perfect: node #I155:2862
 *
 * layout: row, padding 10px 0px, justifyContent center, alignItems center, gap 10px, 1920px
 * fills: #FFFFFF (header token)
 *
 * Children inside 1280px frame:
 *   [RECT]     Logo 175×70
 *   [INSTANCE] Nav tabs (Главная, Каталог, О нас, Блог, Контакты)
 *   [INSTANCE] Search bar 233×56, neutral-100, br 28px
 *   [FRAME]    Actions: Вход (outline) + Регистрация (primary)
 *
 * @package aco-theme
 */
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<?php $aco_logged_in = is_user_logged_in(); ?>
<!-- Header: full-width, bg #FFFFFF, padding 10px 0 -->
<header class="lh-header<?php echo $aco_logged_in ? ' lh-header--dashboard' : ''; ?>" role="banner">
    <div class="lh-header__inner">

        <!-- Logo: АЦО — Академия Цифрового Образования -->
        <a class="lh-header__logo"
           href="<?php echo esc_url( home_url( '/' ) ); ?>"
           aria-label="АЦО — Академия Цифрового Образования">
            <img class="lh-header__logo-img"
                 src="<?php echo esc_url( get_template_directory_uri() . '/assets/aco-logo.png' ); ?>"
                 width="175"
                 height="70"
                 alt="Логотип АЦО — Академия Цифрового Образования">
        </a>

        <div class="lh-header__panel" id="lh-header-panel">
            <button class="lh-nav-close" type="button" aria-label="<?php esc_attr_e( 'Закрыть меню', 'aco-theme' ); ?>">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <line x1="6" y1="6" x2="18" y2="18"/>
                    <line x1="18" y1="6" x2="6" y2="18"/>
                </svg>
            </button>
            <?php if ( ! $aco_logged_in ) : ?>
                <nav class="lh-nav"
                     aria-label="<?php esc_attr_e( 'Основное меню', 'aco-theme' ); ?>">
                    <?php if ( has_nav_menu( 'primary' ) ) : ?>
                        <?php wp_nav_menu( [
                            'theme_location' => 'primary',
                            'container'      => false,
                            'items_wrap'     => '%3$s',
                            'walker'         => new ACO_Nav_Walker(),
                        ] ); ?>
                    <?php else :
                        $nav_items = [
                            'Главная'  => home_url( '/' ),
                            'Каталог'  => home_url( '/catalog/' ),
                            'О нас'    => home_url( '/about/' ),
                            'Блог'     => home_url( '/blog/' ),
                            'Контакты' => home_url( '/contacts/' ),
                        ];
                        $current = esc_url( ( is_ssl() ? 'https' : 'http' ) . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] );
                        foreach ( $nav_items as $label => $url ) :
                            $is_active = ( rtrim( $current, '/' ) === rtrim( esc_url( $url ), '/' ) );
                    ?>
                    <div class="lh-nav__item<?php echo $is_active ? ' lh-nav__item--active' : ''; ?>">
                        <a class="lh-nav__link"
                           href="<?php echo esc_url( $url ); ?>"
                           <?php echo $is_active ? 'aria-current="page"' : ''; ?>>
                            <?php echo esc_html( $label ); ?>
                        </a>
                        <span class="lh-nav__indicator" aria-hidden="true"></span>
                    </div>
                    <?php endforeach; endif; ?>
                </nav>

                <div class="lh-header__panel-actions">
                    <a class="lh-btn lh-btn--outline lh-btn--sm"
                       href="<?php echo esc_url( wp_login_url() ); ?>">
                        Вход
                    </a>
                    <a class="lh-btn lh-btn--primary lh-btn--sm"
                       href="<?php echo esc_url( wp_registration_url() ); ?>">
                        Регистрация
                    </a>
                </div>
            <?php else : ?>
                <nav class="lh-header-quicknav" aria-label="<?php esc_attr_e( 'Быстрые разделы кабинета', 'aco-theme' ); ?>">
                    <?php if ( aco_is_platform_admin() ) : ?>
                    <a href="<?php echo esc_url( aco_admin_home_url() ); ?>" class="lh-header-quicknav__item">Панель</a>
                    <?php endif; ?>
                    <a href="<?php echo esc_url( home_url( '/catalog/' ) ); ?>" class="lh-header-quicknav__item">Каталог</a>
                    <a href="<?php echo esc_url( home_url( '/blog/' ) ); ?>" class="lh-header-quicknav__item">Блог</a>
                    <a href="<?php echo esc_url( home_url( '/contacts/' ) ); ?>" class="lh-header-quicknav__item">Контакты</a>
                </nav>
            <?php endif; ?>
        </div>

        <!-- Actions: layout_c686e931 — row, center, gap 10px
             Logged out: Вход (outline) + Регистрация (primary)
             Logged in:  account trigger with avatar/name + dropdown menu -->
        <div class="lh-header__actions">
            <?php if ( is_user_logged_in() ) :
                $aco_user = wp_get_current_user();
                $aco_name = $aco_user->display_name ?: $aco_user->user_login;
            ?>
                <div class="lh-account">
                    <button class="lh-account__trigger" type="button" aria-haspopup="true" aria-expanded="false">
                        <?php echo get_avatar( $aco_user->ID, 36, '', '', [ 'class' => 'lh-account__avatar' ] ); ?>
                        <span class="lh-account__name"><?php echo esc_html( $aco_name ); ?></span>
                        <svg class="lh-account__chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <polyline points="6 9 12 15 18 9"/>
                        </svg>
                    </button>
                    <div class="lh-account__menu" role="menu">
                        <?php if ( aco_is_platform_admin() ) : ?>
                        <a class="lh-account__menu-item" href="<?php echo esc_url( aco_admin_home_url() ); ?>" role="menuitem">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                            Панель
                        </a>
                        <a class="lh-account__menu-item" href="<?php echo esc_url( admin_url() ); ?>" role="menuitem">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                            WP-admin
                        </a>
                        <?php elseif ( function_exists( 'aco_is_teacher' ) && aco_is_teacher() ) : ?>
                        <a class="lh-account__menu-item" href="<?php echo esc_url( home_url( '/?aco_moodle_sso=1&redirect=' . rawurlencode( aco_teacher_cabinet_url() ) ) ); ?>" role="menuitem">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                            Кабинет преподавателя
                        </a>
                        <?php else : ?>
                        <a class="lh-account__menu-item" href="<?php echo esc_url( home_url( '/account/' ) ); ?>" role="menuitem">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                            Личный кабинет
                        </a>
                        <?php endif; ?>
                        <a class="lh-account__menu-item lh-account__menu-item--danger" href="<?php echo esc_url( wp_logout_url( home_url( '/' ) ) ); ?>" role="menuitem">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                            Выйти
                        </a>
                    </div>
                </div>
            <?php else : ?>
                <a class="lh-btn lh-btn--outline lh-btn--sm"
                   href="<?php echo esc_url( wp_login_url() ); ?>">
                    Вход
                </a>
                <a class="lh-btn lh-btn--primary lh-btn--sm"
                   href="<?php echo esc_url( wp_registration_url() ); ?>">
                    Регистрация
                </a>
            <?php endif; ?>
        </div>

        <?php if ( ! $aco_logged_in ) : ?>
        <button class="lh-nav-toggle"
                type="button"
                aria-label="<?php esc_attr_e( 'Открыть меню', 'aco-theme' ); ?>"
                aria-expanded="false"
                aria-controls="lh-header-panel">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2" aria-hidden="true">
                <line x1="3" y1="6"  x2="21" y2="6"/>
                <line x1="3" y1="12" x2="21" y2="12"/>
                <line x1="3" y1="18" x2="21" y2="18"/>
            </svg>
        </button>
        <?php endif; ?>
    </div>
</header>
<div class="lh-nav-overlay" hidden></div>
