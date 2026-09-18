<?php
/**
 * Template Name: Как получить курс
 *
 * @package aco-theme
 */

defined( 'ABSPATH' ) || exit;
get_header();
$ctx = function_exists( 'aco_legal_context' ) ? aco_legal_context() : [];
?>
<main id="main" class="lh-legal-page">
	<section class="lh-page-hero">
		<div class="lh-container">
			<nav class="lh-breadcrumbs" aria-label="breadcrumb">
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>">Главная</a>
				<span class="lh-breadcrumbs__sep">›</span>
				<span class="lh-breadcrumbs__cur">Как получить курс</span>
			</nav>
			<h1 class="lh-page-hero__title">Как получить курс</h1>
			<p class="lh-page-hero__sub">Цифровая услуга: физической доставки нет. Доступ открывается онлайн после оплаты.</p>
		</div>
	</section>
	<section class="lh-section">
		<div class="lh-container">
			<article class="lh-legal">
				<h2>Что вы покупаете</h2>
				<p>Онлайн-курс на платформе «<?php echo esc_html( $ctx['brand'] ?? 'АЦО' ); ?>»: уроки, задания и тесты в личном кабинете и в учебной системе Moodle. Это не физический товар. Почтой, курьером и самовывозом курс не отправляется.</p>

				<h2>Как получить доступ после оплаты</h2>
				<ol>
					<li>Выберите курс в <a href="<?php echo esc_url( home_url( '/catalog/' ) ); ?>">каталоге</a> и оформите заказ.</li>
					<li>Оплатите банковской картой или СБП на защищённой странице ЮKassa. Данные карты на нашем сайте не вводятся.</li>
					<li>После подтверждения платежа доступ открывается в <a href="<?php echo esc_url( home_url( '/account/' ) ); ?>">личном кабинете</a> на сайте и в учебной платформе.</li>
					<li>На email из заказа приходит письмо с доступом. Вход — по той же почте, которую вы указали при регистрации.</li>
				</ol>

				<h2>Когда начинается обучение</h2>
				<p>Обычно сразу после успешной оплаты. Если письмо задерживается, проверьте папку «Спам» или напишите на <?php echo esc_html( $ctx['email'] ?? 'inbox@acoedu.ru' ); ?> — восстановим доступ.</p>

				<h2>Оплата, оферта и возврат</h2>
				<p>Условия договора — в <a href="<?php echo esc_url( aco_legal_url( 'oferta' ) ); ?>">публичной оферте</a>. Возврат — на странице <a href="<?php echo esc_url( aco_legal_url( 'refund' ) ); ?>">«Возврат средств»</a>. Реквизиты компании — в <a href="<?php echo esc_url( home_url( '/contacts/' ) ); ?>">контактах</a>.</p>
			</article>
		</div>
	</section>
</main>
<?php
get_footer();
