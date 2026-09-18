<?php
/**
 * Template Name: Auth Page
 * Страница авторизации / регистрации / восстановления пароля.
 * Визуально в языке публичного сайта (белый хедер, hero-градиент, ACO indigo).
 * Хедер — логотип и ссылка на главную, футер скрыт.
 *
 * @package aco-theme
 */

defined( 'ABSPATH' ) || exit;

if ( is_user_logged_in() ) {
    $requested = function_exists( 'aco_safe_requested_redirect' ) ? aco_safe_requested_redirect( '' ) : '';
    $to_wp_admin = $requested && str_contains( $requested, '/wp-admin' );

    // Teachers must be able to reach WP after leaving Moodle. Do not SSO them back.
    if ( function_exists( 'aco_is_teacher' ) && aco_is_teacher() && ! $to_wp_admin ) {
        wp_safe_redirect( home_url( '/' ) );
        exit;
    }

    wp_safe_redirect( $requested ?: aco_post_login_redirect() );
    exit;
}

$logo_src = get_template_directory_uri() . '/assets/aco-logo.png';

$tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'login';
if ( ! in_array( $tab, [ 'login', 'register', 'recovery' ], true ) ) {
    $tab = 'login';
}

$titles = [
    'login'    => 'Вход в кабинет',
    'register' => 'Регистрация',
    'recovery' => 'Не помню пароль',
];
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <?php wp_head(); ?>
</head>
<body class="aco-auth-page">
<?php wp_body_open(); ?>

<header class="aco-auth-header">
    <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="aco-auth-logo">
        <img src="<?php echo esc_url( $logo_src ); ?>" alt="<?php bloginfo( 'name' ); ?>" width="175" height="70">
    </a>
</header>

<main class="aco-auth-main">
    <div class="aco-auth-card">

        <!-- ── LOGIN FORM ──────────────────────────────── -->
        <div class="aco-auth-panel<?php echo $tab === 'login' ? ' is-active' : ''; ?>" data-panel="login">
            <h1 class="aco-auth-title"><?php echo esc_html( $titles['login'] ); ?></h1>
            <form class="aco-auth-form" id="aco-login-form" novalidate>
                <?php wp_nonce_field( 'aco_login', 'aco_login_nonce' ); ?>
                <div class="aco-field">
                    <label for="login-email">Электронная почта</label>
                    <input type="email" id="login-email" name="email"
                           placeholder="ivan@mail.ru"
                           autocomplete="username"
                           inputmode="email"
                           enterkeyhint="next"
                           spellcheck="false"
                           required>
                </div>
                <div class="aco-field">
                    <label for="login-pass">Пароль</label>
                    <div class="aco-field__pw-wrap">
                        <input type="password" id="login-pass" name="password"
                               placeholder="Введите пароль"
                               autocomplete="current-password"
                               enterkeyhint="go"
                               required>
                        <button type="button" class="aco-toggle-pw" aria-label="Показать пароль" aria-pressed="false">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path d="M1 12S5 4 12 4s11 8 11 8-4 8-11 8S1 12 1 12z"/>
                                <circle cx="12" cy="12" r="3"/>
                            </svg>
                        </button>
                    </div>
                </div>
                <div class="aco-field aco-field--meta">
                    <label class="aco-checkbox">
                        <input type="checkbox" name="rememberme" value="1">
                        <span>Запомнить меня</span>
                    </label>
                    <button type="button" class="aco-link" data-goto="recovery">Не помню пароль</button>
                </div>
                <div class="aco-auth-error" id="login-error" role="alert" hidden></div>
                <button type="submit" class="aco-auth-submit" id="login-submit">
                    <span>Войти</span>
                    <span class="aco-spinner" hidden></span>
                </button>
            </form>
            <p class="aco-auth-switch">Нет аккаунта? <button type="button" class="aco-link" data-goto="register">Зарегистрироваться</button></p>
        </div>

        <!-- ── REGISTER FORM ───────────────────────────── -->
        <div class="aco-auth-panel<?php echo $tab === 'register' ? ' is-active' : ''; ?>" data-panel="register">
            <h1 class="aco-auth-title"><?php echo esc_html( $titles['register'] ); ?></h1>
            <form class="aco-auth-form" id="aco-register-form" novalidate>
                <?php wp_nonce_field( 'aco_register', 'aco_register_nonce' ); ?>
                <div class="aco-field">
                    <label for="reg-name">Имя</label>
                    <input type="text" id="reg-name" name="first_name"
                           placeholder="Иван"
                           autocomplete="given-name"
                           enterkeyhint="next"
                           required>
                </div>
                <div class="aco-field">
                    <label for="reg-email">Электронная почта</label>
                    <input type="email" id="reg-email" name="email"
                           placeholder="ivan@mail.ru"
                           autocomplete="email"
                           inputmode="email"
                           enterkeyhint="next"
                           spellcheck="false"
                           required>
                    <p class="aco-field-hint"><?php echo esc_html( aco_email_field_hint() ); ?></p>
                </div>
                <div class="aco-field">
                    <label for="reg-pass">Пароль</label>
                    <div class="aco-field__pw-wrap">
                        <input type="password" id="reg-pass" name="password"
                               placeholder="Минимум 8 символов"
                               autocomplete="new-password"
                               minlength="8"
                               enterkeyhint="go"
                               required>
                        <button type="button" class="aco-toggle-pw" aria-label="Показать пароль" aria-pressed="false">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path d="M1 12S5 4 12 4s11 8 11 8-4 8-11 8S1 12 1 12z"/>
                                <circle cx="12" cy="12" r="3"/>
                            </svg>
                        </button>
                    </div>
                </div>
                <div class="aco-field">
                    <label class="aco-checkbox">
                        <input type="checkbox" name="agree_terms" required>
                        <span>Нажимая «Зарегистрироваться», я принимаю <a href="<?php echo esc_url( aco_legal_url( 'oferta' ) ); ?>" class="aco-link-inline" target="_blank" rel="noopener">публичную оферту</a>, даю согласие на <a href="<?php echo esc_url( aco_legal_url( 'personal-data' ) ); ?>" class="aco-link-inline" target="_blank" rel="noopener">обработку персональных данных</a> и подтверждаю, что ознакомлен с <a href="<?php echo esc_url( aco_legal_url( 'privacy' ) ); ?>" class="aco-link-inline" target="_blank" rel="noopener">политикой конфиденциальности</a></span>
                    </label>
                </div>
                <div class="aco-auth-error" id="register-error" role="alert" hidden></div>
                <button type="submit" class="aco-auth-submit" id="register-submit">
                    <span>Зарегистрироваться</span>
                    <span class="aco-spinner" hidden></span>
                </button>
            </form>
            <p class="aco-auth-switch">Уже есть аккаунт? <button type="button" class="aco-link" data-goto="login">Войти</button></p>
        </div>

        <!-- ── RECOVERY: enter email ───────────────────── -->
        <div class="aco-auth-panel<?php echo $tab === 'recovery' ? ' is-active' : ''; ?>" data-panel="recovery">
            <h1 class="aco-auth-title"><?php echo esc_html( $titles['recovery'] ); ?></h1>
            <p class="aco-auth-hint">Укажите почту — отправим код для сброса пароля.</p>
            <form class="aco-auth-form" id="aco-recovery-form" novalidate>
                <?php wp_nonce_field( 'aco_recovery', 'aco_recovery_nonce' ); ?>
                <div class="aco-field">
                    <label for="rec-email">Электронная почта</label>
                    <input type="email" id="rec-email" name="email"
                           placeholder="ivan@mail.ru"
                           autocomplete="email"
                           inputmode="email"
                           enterkeyhint="send"
                           spellcheck="false"
                           required>
                    <p class="aco-field-hint"><?php echo esc_html( aco_email_field_hint() ); ?></p>
                </div>
                <div class="aco-auth-error" id="recovery-error" role="alert" hidden></div>
                <button type="submit" class="aco-auth-submit">
                    <span>Отправить код</span>
                    <span class="aco-spinner" hidden></span>
                </button>
            </form>
            <p class="aco-auth-switch"><button type="button" class="aco-link" data-goto="login">Вернуться ко входу</button></p>
        </div>

        <!-- ── RECOVERY: enter code ────────────────────── -->
        <div class="aco-auth-panel" data-panel="verify">
            <h1 class="aco-auth-title">Введите код</h1>
            <p class="aco-auth-hint">Мы отправили 6-значный код на вашу почту.</p>
            <form class="aco-auth-form" id="aco-verify-form" novalidate>
                <?php wp_nonce_field( 'aco_verify_code', 'aco_verify_nonce' ); ?>
                <input type="hidden" id="verify-email" name="email" value="">
                <input type="hidden" id="verify-context" name="context" value="recovery">
                <div class="aco-field">
                    <label for="verify-code">Код из письма</label>
                    <input type="text" id="verify-code" name="code"
                           placeholder="123456"
                           maxlength="6"
                           inputmode="numeric"
                           autocomplete="one-time-code"
                           pattern="\d{6}"
                           enterkeyhint="done"
                           required>
                </div>
                <div class="aco-auth-error" id="verify-error" role="alert" hidden></div>
                <button type="submit" class="aco-auth-submit">
                    <span>Подтвердить</span>
                    <span class="aco-spinner" hidden></span>
                </button>
            </form>
            <p class="aco-auth-switch"><button type="button" class="aco-link" id="resend-code">Отправить код ещё раз</button></p>
        </div>

        <!-- ── RECOVERY: set new password ─────────────── -->
        <div class="aco-auth-panel" data-panel="newpass">
            <h1 class="aco-auth-title">Новый пароль</h1>
            <form class="aco-auth-form" id="aco-newpass-form" novalidate>
                <?php wp_nonce_field( 'aco_new_password', 'aco_newpass_nonce' ); ?>
                <input type="hidden" id="newpass-email" name="email" value="">
                <input type="hidden" id="newpass-token" name="token" value="">
                <div class="aco-field">
                    <label for="new-pass">Новый пароль</label>
                    <div class="aco-field__pw-wrap">
                        <input type="password" id="new-pass" name="password"
                               placeholder="Минимум 8 символов"
                               autocomplete="new-password"
                               minlength="8"
                               required>
                        <button type="button" class="aco-toggle-pw" aria-label="Показать пароль" aria-pressed="false">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path d="M1 12S5 4 12 4s11 8 11 8-4 8-11 8S1 12 1 12z"/>
                                <circle cx="12" cy="12" r="3"/>
                            </svg>
                        </button>
                    </div>
                </div>
                <div class="aco-field">
                    <label for="new-pass2">Повторите пароль</label>
                    <input type="password" id="new-pass2" name="password2"
                           placeholder="Повторите пароль"
                           autocomplete="new-password"
                           minlength="8"
                           required>
                </div>
                <div class="aco-auth-error" id="newpass-error" role="alert" hidden></div>
                <button type="submit" class="aco-auth-submit">
                    <span>Сохранить пароль</span>
                    <span class="aco-spinner" hidden></span>
                </button>
            </form>
        </div>

        <!-- SUCCESS placeholder -->
        <div class="aco-auth-panel" data-panel="success">
            <div class="aco-auth-success">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#4F46E5" stroke-width="2" aria-hidden="true">
                    <circle cx="12" cy="12" r="10"/>
                    <polyline points="9 12 11 14 15 10"/>
                </svg>
                <p id="success-msg">Готово!</p>
            </div>
        </div>

    </div>
</main>

<?php wp_footer(); ?>
</body>
</html>
