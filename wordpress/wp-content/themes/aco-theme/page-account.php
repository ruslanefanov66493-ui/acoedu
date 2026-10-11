<?php
/**
 * Template Name: Личный кабинет
 * Skillbox-like dashboard layout with LearnHub styling.
 * Data is loaded from Edwiser Bridge REST API for real Moodle links/progress.
 *
 * @package aco-theme
 */

defined( 'ABSPATH' ) || exit;

if ( ! is_user_logged_in() ) {
	wp_safe_redirect( home_url( '/login/' ) );
	exit;
}

if ( function_exists( 'aco_is_teacher' ) && aco_is_teacher() ) {
	wp_safe_redirect( home_url( '/' ) );
	exit;
}

$aco_user      = wp_get_current_user();
$aco_name      = $aco_user->display_name ?: $aco_user->user_login;
$aco_firstname = get_user_meta( $aco_user->ID, 'first_name', true );
$aco_greeting  = $aco_firstname ? $aco_firstname : $aco_name;
$aco_since     = date_i18n( 'd.m.Y', strtotime( $aco_user->user_registered ) );
$aco_phone     = (string) get_user_meta( $aco_user->ID, 'aco_phone', true );
$aco_real_mail = function_exists( 'aco_user_real_email' ) ? aco_user_real_email( $aco_user ) : $aco_user->user_email;
$aco_last      = (string) get_user_meta( $aco_user->ID, 'last_name', true );
$aco_tab       = isset( $_GET['tab'] ) && $_GET['tab'] === 'profile' ? 'profile' : 'learn';
$aco_moodle_id = get_user_meta( $aco_user->ID, 'moodle_user_id', true );
$aco_moodle_ok = ! empty( $aco_moodle_id );
$aco_moodle_base = function_exists( 'aco_moodle_url' ) ? aco_moodle_url() : '';
$aco_moodle_my   = $aco_moodle_base ? $aco_moodle_base . '/my/' : home_url( '/account/' );
$aco_sso_base    = home_url( '/?aco_moodle_sso=1' );
$aco_sso_my      = $aco_sso_base . '&redirect=' . rawurlencode( $aco_moodle_my );

get_header();
?>

<main class="lh-dash">
	<div class="lh-dash__container"
		data-component="account-dashboard"
		data-courses-endpoint="<?php echo esc_url( rest_url( 'eb/api/v1/my-courses' ) ); ?>"
		data-fallback-course-url="<?php echo esc_url( home_url( '/eb-my-courses/' ) ); ?>"
		data-catalog-url="<?php echo esc_url( home_url( '/catalog/' ) ); ?>"
		data-moodle-url="<?php echo esc_url( $aco_moodle_my ); ?>"
		data-sso-endpoint="<?php echo esc_url( $aco_sso_base ); ?>"
		data-progress-endpoint="<?php echo esc_url( rest_url( 'aco/v1/course-progress' ) ); ?>"
		data-rest-nonce="<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ); ?>"
		data-moodle-linked="<?php echo $aco_moodle_ok ? '1' : '0'; ?>">

		<nav class="lh-dash__tabs" aria-label="Разделы кабинета">
			<button type="button" class="lh-dash__tab<?php echo $aco_tab === 'learn' ? ' is-active' : ''; ?>" data-dash-tab="learn">Обучение</button>
			<button type="button" class="lh-dash__tab<?php echo $aco_tab === 'profile' ? ' is-active' : ''; ?>" data-dash-tab="profile">Личные данные</button>
		</nav>

		<div class="lh-dash__pane<?php echo $aco_tab === 'learn' ? ' is-active' : ''; ?>" data-dash-pane="learn">
		<section class="lh-dash__top">
			<div class="lh-dash__welcome">
				<h1 class="lh-dash__title"><?php echo esc_html( $aco_greeting ); ?>, добро пожаловать!</h1>
				<p class="lh-dash__subtitle">Здесь ваши курсы, прогресс и быстрый переход к обучению в Moodle.</p>
				<div class="lh-dash__profile">
					<?php echo get_avatar( $aco_user->ID, 56, '', '', [ 'class' => 'lh-dash__avatar' ] ); ?>
					<div>
						<p class="lh-dash__email"><?php echo esc_html( $aco_real_mail ? $aco_real_mail : ( $aco_phone ? $aco_phone : 'Почта не указана' ) ); ?></p>
						<p class="lh-dash__since">На платформе с <?php echo esc_html( $aco_since ); ?></p>
					</div>
				</div>
				<div class="lh-dash__quicklinks">
					<a href="<?php echo esc_url( home_url( '/catalog/' ) ); ?>">Каталог</a>
					<a href="<?php echo esc_url( home_url( '/blog/' ) ); ?>">Блог</a>
					<a href="<?php echo esc_url( home_url( '/about/' ) ); ?>">О нас</a>
					<a href="<?php echo esc_url( home_url( '/contacts/' ) ); ?>">Контакты</a>
				</div>
				<?php if ( $aco_moodle_ok ) : ?>
					<a class="lh-btn lh-btn--primary lh-dash__moodle-btn" href="<?php echo esc_url( $aco_sso_my ); ?>" target="_blank" rel="noopener">
						Перейти в Moodle
					</a>
				<?php else : ?>
					<p class="lh-dash__moodle-note">Moodle ещё не привязан. После покупки курса связь создастся автоматически.</p>
				<?php endif; ?>
			</div>

			<div class="lh-dash__continue">
				<div class="lh-dash__continue-head">
					<h2 class="lh-dash__section-title">Продолжить обучение</h2>
					<div class="lh-dash__continue-nav" data-nav="continue" hidden>
						<button type="button" class="lh-dash__nav-btn" data-scroll="prev" aria-label="Прокрутить влево">‹</button>
						<button type="button" class="lh-dash__nav-btn" data-scroll="next" aria-label="Прокрутить вправо">›</button>
					</div>
				</div>
				<div class="lh-dash__continue-track" data-slot="continue-track">
					<div class="lh-dash__skeleton"></div>
					<div class="lh-dash__skeleton"></div>
					<div class="lh-dash__skeleton"></div>
				</div>
			</div>
		</section>

		<section class="lh-dash__section" id="courses">
			<div class="lh-dash__continue-head">
				<h2 class="lh-dash__section-title">Ваши курсы</h2>
				<div class="lh-dash__continue-nav" data-nav="courses" hidden>
					<button type="button" class="lh-dash__nav-btn" data-scroll-courses="prev" aria-label="Прокрутить влево">‹</button>
					<button type="button" class="lh-dash__nav-btn" data-scroll-courses="next" aria-label="Прокрутить вправо">›</button>
				</div>
			</div>
			<div class="lh-dash__grid" data-slot="courses-grid">
				<div class="lh-dash__skeleton lh-dash__skeleton--card"></div>
				<div class="lh-dash__skeleton lh-dash__skeleton--card"></div>
				<div class="lh-dash__skeleton lh-dash__skeleton--card"></div>
			</div>
		</section>
		</div>

		<div class="lh-dash__pane<?php echo $aco_tab === 'profile' ? ' is-active' : ''; ?>" data-dash-pane="profile"
			data-profile
			data-ajax="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>">
			<?php wp_nonce_field( 'aco_profile', 'aco_profile_nonce' ); ?>
			<div class="lh-profile-layout">
				<div class="lh-profile-col">
					<section class="lh-profile-card">
						<h2 class="lh-dash__section-title">Личные данные</h2>
						<p class="lh-profile-lead">Имя, которое видно в кабинете и в письмах.</p>
						<p class="lh-profile-note" data-form-note hidden></p>
						<form class="lh-profile-form" data-profile-form="name">
							<div class="lh-profile-row">
								<label>Имя
									<input type="text" name="first_name" value="<?php echo esc_attr( $aco_firstname ); ?>" autocomplete="given-name" required>
								</label>
								<label>Фамилия
									<input type="text" name="last_name" value="<?php echo esc_attr( $aco_last ); ?>" autocomplete="family-name">
								</label>
							</div>
							<button class="lh-btn lh-btn--primary lh-profile-submit" type="submit">Сохранить</button>
						</form>
					</section>

					<section class="lh-profile-card">
						<h2 class="lh-dash__section-title">Пароль</h2>
						<p class="lh-profile-lead">Новый пароль — не короче 8 символов.</p>
						<p class="lh-profile-note" data-form-note hidden></p>
						<form class="lh-profile-form" data-profile-form="password">
							<div class="lh-profile-row">
								<label>Текущий пароль
									<input type="password" name="old_password" autocomplete="current-password" required>
								</label>
								<label>Новый пароль
									<input type="password" name="password" minlength="8" autocomplete="new-password" required>
								</label>
							</div>
							<button class="lh-btn lh-btn--primary lh-profile-submit" type="submit">Сменить пароль</button>
						</form>
					</section>
				</div>

				<section class="lh-profile-card lh-profile-card--contacts">
					<h2 class="lh-dash__section-title">Контакты</h2>
					<p class="lh-profile-lead">Почта нужна для чеков и доступа к курсу. Телефон можно добавить к аккаунту.</p>

					<div class="lh-profile-block">
						<?php if ( $aco_phone ) : ?>
						<div class="lh-profile-fact" data-profile-phone-fact>
							<div>
								<p class="lh-profile-kicker">Телефон</p>
								<p class="lh-profile-value"><?php echo esc_html( $aco_phone ); ?></p>
							</div>
							<span class="lh-profile-badge">Привязан</span>
						</div>
						<button type="button" class="lh-profile-textbtn" data-profile-reveal="phone">Изменить номер</button>
						<?php endif; ?>
						<form class="lh-profile-form" data-profile-form="phone"<?php echo $aco_phone ? ' hidden' : ''; ?>>
							<p class="lh-profile-note" data-form-note hidden></p>
							<label>Мобильный номер
								<input type="tel" name="phone" value="" placeholder="+7 900 000-00-00" inputmode="tel" autocomplete="tel">
							</label>
							<label data-profile-code="phone" hidden>Код из SMS
								<input type="text" name="code" inputmode="numeric" maxlength="6" autocomplete="one-time-code" placeholder="6 цифр">
							</label>
							<button class="lh-btn lh-btn--primary lh-profile-submit" type="submit">Получить код</button>
						</form>
					</div>

					<div class="lh-profile-block">
						<?php if ( $aco_real_mail ) : ?>
						<div class="lh-profile-fact">
							<div>
								<p class="lh-profile-kicker">Почта</p>
								<p class="lh-profile-value"><?php echo esc_html( $aco_real_mail ); ?></p>
							</div>
							<span class="lh-profile-badge">Подтверждена</span>
						</div>
						<button type="button" class="lh-profile-textbtn" data-profile-reveal="email">Изменить почту</button>
						<?php else : ?>
						<p class="lh-profile-kicker">Почта</p>
						<p class="lh-profile-lead">Укажите почту — на неё придут чек и доступ к курсу.</p>
						<?php endif; ?>
						<form class="lh-profile-form" data-profile-form="email" data-current-email="<?php echo esc_attr( $aco_real_mail ); ?>"<?php echo $aco_real_mail ? ' hidden' : ''; ?>>
							<p class="lh-profile-note" data-form-note hidden></p>
							<label>Новая почта
								<input type="email" name="email" value="" placeholder="ivan@mail.ru" autocomplete="email">
							</label>
							<p class="lh-profile-hint"><?php echo esc_html( aco_email_field_hint() ); ?></p>
							<label data-profile-code="email" hidden>Код из письма
								<input type="text" name="code" inputmode="numeric" maxlength="6" autocomplete="one-time-code" placeholder="6 цифр">
							</label>
							<button class="lh-btn lh-btn--primary lh-profile-submit" type="submit"><?php echo $aco_real_mail ? 'Отправить код' : 'Получить код'; ?></button>
						</form>
					</div>
				</section>
			</div>
		</div>

	</div>
</main>

<?php get_footer(); ?>
