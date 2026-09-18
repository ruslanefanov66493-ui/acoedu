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

		<section class="lh-dash__top">
			<div class="lh-dash__welcome">
				<h1 class="lh-dash__title"><?php echo esc_html( $aco_greeting ); ?>, добро пожаловать!</h1>
				<p class="lh-dash__subtitle">Здесь ваши курсы, прогресс и быстрый переход к обучению в Moodle.</p>
				<div class="lh-dash__profile">
					<?php echo get_avatar( $aco_user->ID, 56, '', '', [ 'class' => 'lh-dash__avatar' ] ); ?>
					<div>
						<p class="lh-dash__email"><?php echo esc_html( $aco_user->user_email ); ?></p>
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
</main>

<?php get_footer(); ?>
