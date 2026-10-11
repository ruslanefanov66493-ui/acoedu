<?php
/**
 * Template Name: Панель АЦО
 *
 * @package aco-theme
 */

defined( 'ABSPATH' ) || exit;

$view      = aco_admin_current_view();
$course_id = absint( $_GET['id'] ?? 0 );
$dir_edit  = absint( $_GET['dir'] ?? $_GET['edit'] ?? 0 );
$dir_rows  = aco_admin_serialize_directions();
$dir_flash = aco_admin_dir_take_flash();
$teacher_flash = function_exists( 'aco_admin_teacher_take_flash' ) ? aco_admin_teacher_take_flash() : null;
$teacher_edit  = absint( $_GET['tid'] ?? 0 );
$teacher_users = function_exists( 'aco_list_teachers' ) ? aco_list_teachers() : [];
$teacher_form  = [
	'id'      => 0,
	'first'   => '',
	'last'    => '',
	'email'   => '',
	'courses' => [],
];
foreach ( $teacher_users as $tuser ) {
	if ( (int) $tuser->ID === $teacher_edit ) {
		$teacher_form = [
			'id'      => (int) $tuser->ID,
			'first'   => $tuser->first_name,
			'last'    => $tuser->last_name,
			'email'   => $tuser->user_email,
			'courses' => array_map( 'absint', (array) get_user_meta( $tuser->ID, '_aco_teacher_courses', true ) ),
		];
		break;
	}
}
$moodle_opts = function_exists( 'aco_moodle_course_options' ) ? aco_moodle_course_options() : [];
$panel_flash = $teacher_flash ?: $dir_flash;
$dir_form  = [
	'id'   => 0,
	'name' => '',
	'slug' => '',
];
foreach ( $dir_rows as $row ) {
	if ( (int) $row['id'] === $dir_edit ) {
		$dir_form = $row;
		break;
	}
}

get_header();
?>

<main class="lh-studio" id="lh-studio"
	data-view="<?php echo esc_attr( $view ); ?>"
	data-course="<?php echo esc_attr( (string) $course_id ); ?>">
	<div class="lh-studio__container">
		<header class="lh-studio__head">
			<div>
				<p class="lh-studio__eyebrow">АЦО</p>
				<h1 class="lh-studio__title">Панель курсов</h1>
				<p class="lh-studio__lead">Те же товары WooCommerce, что в wp-admin. Публичная витрина обновляется сразу.</p>
			</div>
			<div class="lh-studio__head-actions">
				<a class="lh-btn lh-btn--outline lh-btn--sm" href="<?php echo esc_url( home_url( '/' ) ); ?>">Сайт</a>
				<a class="lh-btn lh-btn--outline lh-btn--sm" href="<?php echo esc_url( admin_url( 'edit.php?post_type=product' ) ); ?>">WP-admin</a>
			</div>
		</header>

		<?php if ( function_exists( 'aco_sms_is_stub' ) && aco_sms_is_stub() ) : ?>
		<section class="lh-studio-sms">
			<h2>SMS-коды (тестовый режим)</h2>
			<p class="lh-studio__lead">Провайдер не подключён. Коды не уходят на телефон и видны только здесь.</p>
			<?php $aco_sms_log = aco_sms_stub_log(); ?>
			<?php if ( ! $aco_sms_log ) : ?>
				<p>Пока пусто.</p>
			<?php else : ?>
				<ul class="lh-studio-sms__list">
					<?php foreach ( $aco_sms_log as $row ) : ?>
					<li>
						<strong><?php echo esc_html( $row['phone'] ?? '' ); ?></strong>
						<span><?php echo esc_html( $row['code'] ?? '' ); ?></span>
						<span><?php echo esc_html( $row['purpose'] ?? '' ); ?></span>
						<time><?php echo esc_html( wp_date( 'd.m H:i', (int) ( $row['at'] ?? 0 ) ) ); ?></time>
					</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</section>
		<?php endif; ?>

		<nav class="lh-studio__tabs" aria-label="Разделы панели">
			<a class="lh-studio__tab<?php echo $view === 'courses' ? ' is-active' : ''; ?>" href="<?php echo esc_url( aco_admin_home_url() ); ?>">Курсы</a>
			<a class="lh-studio__tab<?php echo $view === 'directions' ? ' is-active' : ''; ?>" href="<?php echo esc_url( aco_admin_directions_url() ); ?>">Направления</a>
			<a class="lh-studio__tab<?php echo $view === 'teachers' ? ' is-active' : ''; ?>" href="<?php echo esc_url( aco_admin_teachers_url() ); ?>">Учителя</a>
			<button type="button" class="lh-studio__tab<?php echo $view === 'edit' ? ' is-active' : ''; ?>" data-nav="edit" data-new="1">Новый курс</button>
		</nav>

		<?php if ( $panel_flash ) : ?>
		<div class="lh-studio-toast<?php echo ! empty( $panel_flash['error'] ) ? ' is-error' : ''; ?>" data-toast>
			<?php echo esc_html( $panel_flash['text'] ); ?>
		</div>
		<?php else : ?>
		<div class="lh-studio-toast" data-toast hidden></div>
		<?php endif; ?>

		<section class="lh-studio-screen<?php echo $view === 'courses' ? ' is-active' : ''; ?>" data-screen="courses">
			<div class="lh-studio-toolbar">
				<h2 class="lh-studio-screen__title">Карточки курсов</h2>
				<button type="button" class="lh-btn lh-btn--primary lh-btn--sm" data-nav="edit" data-new="1">Создать курс</button>
			</div>
			<div class="lh-studio-table-wrap">
				<table class="lh-studio-table">
					<thead>
						<tr>
							<th>Обложка</th>
							<th>Название</th>
							<th>Направление</th>
							<th>Цена</th>
							<th>Опубликован</th>
							<th>На главной</th>
							<th></th>
						</tr>
					</thead>
					<tbody data-list="courses">
						<tr><td colspan="7" class="lh-studio-muted">Загрузка…</td></tr>
					</tbody>
				</table>
			</div>
		</section>

		<section class="lh-studio-screen<?php echo $view === 'directions' ? ' is-active' : ''; ?>" data-screen="directions">
			<div class="lh-studio-grid lh-studio-grid--split">
				<div>
					<h2 class="lh-studio-screen__title">Направления</h2>
					<p class="lh-studio-muted">Категории WooCommerce. Карточка появляется на главной, фильтр — в каталоге, счётчик считает курсы.</p>
					<div class="lh-studio-table-wrap">
						<table class="lh-studio-table">
							<thead>
								<tr>
									<th>Название</th>
									<th>Slug</th>
									<th>Курсов</th>
									<th></th>
								</tr>
							</thead>
							<tbody>
								<?php if ( ! $dir_rows ) : ?>
								<tr><td colspan="4" class="lh-studio-muted">Направлений пока нет.</td></tr>
								<?php else : ?>
									<?php foreach ( $dir_rows as $row ) : ?>
								<tr>
									<td><?php echo esc_html( $row['name'] ); ?></td>
									<td class="lh-studio-table__slug" title="<?php echo esc_attr( $row['slug'] ); ?>"><?php echo esc_html( $row['slug'] ); ?></td>
									<td><?php echo esc_html( (string) $row['count'] ); ?></td>
									<td class="lh-studio-table__actions">
										<a class="lh-studio-link" href="<?php echo esc_url( aco_admin_directions_url( [ 'dir' => $row['id'] ] ) ); ?>">Изменить</a>
										<?php if ( empty( $row['is_default'] ) ) : ?>
										<form class="lh-studio-inline-form" method="post" action="<?php echo esc_url( aco_admin_directions_url() ); ?>" onsubmit="return confirm('Удалить направление «<?php echo esc_js( $row['name'] ); ?>»?');">
											<?php wp_nonce_field( 'aco_admin_dir', 'aco_dir_nonce' ); ?>
											<input type="hidden" name="aco_dir_delete" value="1">
											<input type="hidden" name="dir_term_id" value="<?php echo esc_attr( (string) $row['id'] ); ?>">
											<button type="submit" class="lh-studio-link lh-studio-link--danger">Удалить</button>
										</form>
										<?php endif; ?>
									</td>
								</tr>
									<?php endforeach; ?>
								<?php endif; ?>
							</tbody>
						</table>
					</div>
				</div>
				<form class="lh-studio-card" method="post" action="<?php echo esc_url( aco_admin_directions_url() ); ?>" data-form="direction">
					<?php wp_nonce_field( 'aco_admin_dir', 'aco_dir_nonce' ); ?>
					<input type="hidden" name="aco_dir_save" value="1">
					<input type="hidden" name="dir_term_id" value="<?php echo esc_attr( (string) $dir_form['id'] ); ?>">
					<h3 class="lh-studio-card__title"><?php echo $dir_form['id'] ? 'Редактировать направление' : 'Новое направление'; ?></h3>
					<label class="lh-studio-field">
						<span>Название</span>
						<input type="text" name="dir_name" value="<?php echo esc_attr( $dir_form['name'] ); ?>" placeholder="Например, Маркетинг" autocomplete="off">
					</label>
					<label class="lh-studio-field">
						<span>Slug <small class="lh-studio-muted">если пусто — латиницей из названия</small></span>
						<input type="text" name="dir_slug" value="<?php echo esc_attr( $dir_form['slug'] ); ?>" placeholder="testirovka" autocomplete="off">
					</label>
					<div class="lh-studio-actions">
						<button type="submit" class="lh-btn lh-btn--primary lh-btn--sm"><?php echo $dir_form['id'] ? 'Сохранить изменения' : 'Создать направление'; ?></button>
						<?php if ( $dir_form['id'] ) : ?>
						<a class="lh-btn lh-btn--outline lh-btn--sm" href="<?php echo esc_url( aco_admin_directions_url() ); ?>">Сбросить</a>
						<?php endif; ?>
					</div>
				</form>
			</div>
		</section>

		<section class="lh-studio-screen<?php echo $view === 'teachers' ? ' is-active' : ''; ?>" data-screen="teachers">
			<div class="lh-studio-grid lh-studio-grid--split">
				<div>
					<h2 class="lh-studio-screen__title">Учителя</h2>
					<p class="lh-studio-muted">Аккаунт WordPress + преподаватель в Moodle. После входа учитель попадает в кабинет Moodle.</p>
					<div class="lh-studio-table-wrap">
						<table class="lh-studio-table">
							<thead>
								<tr>
									<th>Имя</th>
									<th>Email</th>
									<th>Moodle</th>
									<th></th>
								</tr>
							</thead>
							<tbody>
								<?php if ( ! $teacher_users ) : ?>
								<tr><td colspan="4" class="lh-studio-muted">Учителей пока нет.</td></tr>
								<?php else : ?>
									<?php foreach ( $teacher_users as $tuser ) : ?>
								<tr>
									<td><?php echo esc_html( $tuser->display_name ); ?></td>
									<td><?php echo esc_html( $tuser->user_email ); ?></td>
									<td><?php echo esc_html( (string) get_user_meta( $tuser->ID, 'moodle_user_id', true ) ?: '—' ); ?></td>
									<td class="lh-studio-table__actions">
										<a class="lh-studio-link" href="<?php echo esc_url( aco_admin_teachers_url( [ 'tid' => $tuser->ID ] ) ); ?>">Изменить</a>
										<?php if ( (int) $tuser->ID !== (int) get_current_user_id() && ! user_can( $tuser, 'manage_options' ) ) : ?>
										<form class="lh-studio-inline-form" method="post" action="<?php echo esc_url( aco_admin_teachers_url() ); ?>" onsubmit="return confirm('Удалить учителя «<?php echo esc_js( $tuser->display_name ); ?>» из WordPress и Moodle?');">
											<?php wp_nonce_field( 'aco_admin_teacher', 'aco_teacher_nonce' ); ?>
											<input type="hidden" name="aco_teacher_delete" value="1">
											<input type="hidden" name="teacher_user_id" value="<?php echo esc_attr( (string) $tuser->ID ); ?>">
											<button type="submit" class="lh-studio-link lh-studio-link--danger">Удалить</button>
										</form>
										<?php endif; ?>
									</td>
								</tr>
									<?php endforeach; ?>
								<?php endif; ?>
							</tbody>
						</table>
					</div>
				</div>
				<form class="lh-studio-card" method="post" action="<?php echo esc_url( aco_admin_teachers_url() ); ?>">
					<?php wp_nonce_field( 'aco_admin_teacher', 'aco_teacher_nonce' ); ?>
					<input type="hidden" name="aco_teacher_save" value="1">
					<input type="hidden" name="teacher_user_id" value="<?php echo esc_attr( (string) $teacher_form['id'] ); ?>">
					<h3 class="lh-studio-card__title"><?php echo $teacher_form['id'] ? 'Редактировать учителя' : 'Новый учитель'; ?></h3>
					<label class="lh-studio-field">
						<span>Имя</span>
						<input type="text" name="teacher_first" value="<?php echo esc_attr( $teacher_form['first'] ); ?>" placeholder="Анна" autocomplete="off">
					</label>
					<label class="lh-studio-field">
						<span>Фамилия</span>
						<input type="text" name="teacher_last" value="<?php echo esc_attr( $teacher_form['last'] ); ?>" placeholder="Иванова" autocomplete="off">
					</label>
					<label class="lh-studio-field">
						<span>Email</span>
						<input type="email" name="teacher_email" value="<?php echo esc_attr( $teacher_form['email'] ); ?>" placeholder="teacher@aco.ru" autocomplete="off">
					</label>
					<label class="lh-studio-field">
						<span>Пароль <?php echo $teacher_form['id'] ? '(оставьте пустым, чтобы не менять)' : ''; ?></span>
						<input type="text" name="teacher_password" value="" autocomplete="new-password">
					</label>
					<?php if ( $moodle_opts ) : ?>
					<fieldset class="lh-studio-field">
						<span>Курсы Moodle (роль преподавателя)</span>
						<?php foreach ( $moodle_opts as $opt ) : ?>
						<label class="lh-studio-check">
							<input type="checkbox" name="teacher_courses[]" value="<?php echo esc_attr( $opt['id'] ); ?>" <?php checked( in_array( (int) $opt['id'], $teacher_form['courses'], true ) ); ?>>
							<?php echo esc_html( $opt['label'] ); ?>
						</label>
						<?php endforeach; ?>
					</fieldset>
					<?php endif; ?>
					<div class="lh-studio-actions">
						<button type="submit" class="lh-btn lh-btn--primary lh-btn--sm"><?php echo $teacher_form['id'] ? 'Сохранить' : 'Создать учителя'; ?></button>
						<?php if ( $teacher_form['id'] ) : ?>
						<a class="lh-btn lh-btn--outline lh-btn--sm" href="<?php echo esc_url( aco_admin_teachers_url() ); ?>">Сбросить</a>
						<?php endif; ?>
					</div>
				</form>
			</div>
		</section>

		<section class="lh-studio-screen<?php echo $view === 'edit' ? ' is-active' : ''; ?>" data-screen="edit">
			<form class="lh-studio-editor" data-form="course">
				<input type="hidden" name="id" value="<?php echo esc_attr( (string) $course_id ); ?>">
				<input type="hidden" name="image_id" value="">
				<input type="hidden" name="video_id" value="">

				<div class="lh-studio-editor__main">
					<div class="lh-studio-card">
						<h2 class="lh-studio-card__title" data-course-form-title>Новый курс</h2>
						<label class="lh-studio-field">
							<span>Название</span>
							<input type="text" name="title" required placeholder="Название курса">
						</label>
						<label class="lh-studio-field">
							<span>Краткое описание (карточка и герой)</span>
							<textarea name="excerpt" rows="3" placeholder="2–3 предложения"></textarea>
						</label>
						<label class="lh-studio-field">
							<span>О курсе</span>
							<textarea name="content" rows="8" placeholder="Полный текст страницы. Пустой блок на витрине скрывается."></textarea>
						</label>
					</div>

					<div class="lh-studio-card">
						<div class="lh-studio-toolbar">
							<h3 class="lh-studio-card__title">Программа</h3>
							<button type="button" class="lh-btn lh-btn--outline lh-btn--sm" data-add="program">Добавить модуль</button>
						</div>
						<div class="lh-studio-repeater" data-repeater="program"></div>
					</div>

					<div class="lh-studio-card">
						<div class="lh-studio-toolbar">
							<h3 class="lh-studio-card__title">Что получите</h3>
							<button type="button" class="lh-btn lh-btn--outline lh-btn--sm" data-add="benefits">Добавить пункт</button>
						</div>
						<div class="lh-studio-repeater" data-repeater="benefits"></div>
					</div>

					<div class="lh-studio-card">
						<div class="lh-studio-toolbar">
							<h3 class="lh-studio-card__title">FAQ</h3>
							<button type="button" class="lh-btn lh-btn--outline lh-btn--sm" data-add="faq">Добавить вопрос</button>
						</div>
						<div class="lh-studio-repeater" data-repeater="faq"></div>
					</div>
				</div>

				<aside class="lh-studio-editor__side">
					<div class="lh-studio-card">
						<h3 class="lh-studio-card__title">Публикация</h3>
						<label class="lh-studio-check">
							<input type="checkbox" name="status_publish" value="1">
							<span>Опубликован</span>
						</label>
						<label class="lh-studio-check">
							<input type="checkbox" name="featured" value="1">
							<span>На главной</span>
						</label>
						<label class="lh-studio-field">
							<span>Направление</span>
							<select name="category_id">
								<option value="0">— не выбрано —</option>
							</select>
						</label>
						<div class="lh-studio-actions">
							<button type="submit" class="lh-btn lh-btn--primary lh-btn--sm">Сохранить</button>
							<a class="lh-btn lh-btn--outline lh-btn--sm" data-preview-link hidden target="_blank" rel="noopener">Открыть</a>
						</div>
					</div>

					<div class="lh-studio-card">
						<h3 class="lh-studio-card__title">Цена</h3>
						<label class="lh-studio-field">
							<span>Обычная</span>
							<input type="text" name="regular_price" inputmode="decimal" placeholder="0">
						</label>
						<label class="lh-studio-field">
							<span>Акционная</span>
							<input type="text" name="sale_price" inputmode="decimal" placeholder="необязательно">
						</label>
					</div>

					<div class="lh-studio-card">
						<h3 class="lh-studio-card__title">Параметры</h3>
						<label class="lh-studio-field">
							<span>Длительность</span>
							<input type="text" name="duration" placeholder="3 месяца">
						</label>
						<label class="lh-studio-field">
							<span>Уровень</span>
							<input type="text" name="level" placeholder="Junior">
						</label>
						<label class="lh-studio-field">
							<span>Куратор</span>
							<select name="teacher_user_id">
								<option value="0">Команда АЦО</option>
							</select>
						</label>
						<label class="lh-studio-field">
							<span>Курс в Moodle</span>
							<select name="moodle_course_id">
								<option value="">— не привязан —</option>
							</select>
							<span class="lh-studio-hint">Курсы подтягиваются из Moodle, в том числе созданные в конструкторе. Сохраните карточку после выбора.</span>
							<a class="lh-btn lh-btn--outline lh-btn--sm" data-moodle-builder hidden target="_blank" rel="noopener">Открыть конструктор программы</a>
						</label>
					</div>

					<div class="lh-studio-card">
						<h3 class="lh-studio-card__title">Обложка</h3>
						<div class="lh-studio-media" data-preview="image"></div>
						<div class="lh-studio-actions">
							<button type="button" class="lh-btn lh-btn--outline lh-btn--sm" data-pick="image">Выбрать</button>
							<button type="button" class="lh-btn lh-btn--outline lh-btn--sm" data-clear="image">Убрать</button>
						</div>
					</div>

					<div class="lh-studio-card">
						<h3 class="lh-studio-card__title">Превью-видео</h3>
						<div class="lh-studio-media" data-preview="video"></div>
						<div class="lh-studio-actions">
							<button type="button" class="lh-btn lh-btn--outline lh-btn--sm" data-pick="video">Загрузить</button>
							<button type="button" class="lh-btn lh-btn--outline lh-btn--sm" data-clear="video">Убрать</button>
						</div>
					</div>
				</aside>
			</form>
		</section>
	</div>
</main>

<template id="tpl-program-row">
	<div class="lh-studio-row" data-row>
		<label class="lh-studio-field">
			<span>Модуль</span>
			<input type="text" data-f="title" placeholder="Название модуля">
		</label>
		<label class="lh-studio-field">
			<span>Пункты (по одному в строке)</span>
			<textarea data-f="items" rows="4"></textarea>
		</label>
		<button type="button" class="lh-studio-row__remove" data-remove>Удалить</button>
	</div>
</template>
<template id="tpl-benefits-row">
	<div class="lh-studio-row" data-row>
		<label class="lh-studio-field">
			<span>Заголовок</span>
			<input type="text" data-f="title">
		</label>
		<label class="lh-studio-field">
			<span>Текст</span>
			<textarea data-f="text" rows="3"></textarea>
		</label>
		<button type="button" class="lh-studio-row__remove" data-remove>Удалить</button>
	</div>
</template>
<template id="tpl-faq-row">
	<div class="lh-studio-row" data-row>
		<label class="lh-studio-field">
			<span>Вопрос</span>
			<input type="text" data-f="q">
		</label>
		<label class="lh-studio-field">
			<span>Ответ</span>
			<textarea data-f="a" rows="3"></textarea>
		</label>
		<button type="button" class="lh-studio-row__remove" data-remove>Удалить</button>
	</div>
</template>

<?php get_footer(); ?>
