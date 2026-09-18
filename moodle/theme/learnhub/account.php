<?php
/**
 * Simplified account settings for students and teachers.
 *
 * @package theme_learnhub
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->libdir . '/gdlib.php');
require_once($CFG->libdir . '/authlib.php');
require_once($CFG->libdir . '/filelib.php');

require_login();
if (isguestuser()) {
    redirect(new moodle_url('/login/index.php'));
}

$kind = \theme_learnhub\roles::kind();
if ($kind === 'admin') {
    redirect(new moodle_url('/user/preferences.php'));
}

$PAGE->set_url(new moodle_url('/theme/learnhub/account.php'));
$PAGE->set_context(context_user::instance($USER->id));
$PAGE->set_pagelayout('standard');
$PAGE->set_title('Настройки учётной записи');
$PAGE->set_heading('Настройки учётной записи');

$notice = '';
$error = '';
$wp = rtrim((string) get_config('auth_edwiserbridge', 'wpsiteurl'), '/');
$recoverurl = $wp ? $wp . '/login/?tab=recovery' : (new moodle_url('/login/forgot_password.php'))->out(false);

if (data_submitted() && confirm_sesskey()) {
    $firstname = trim(required_param('firstname', PARAM_NOTAGS));
    $lastname = trim(required_param('lastname', PARAM_NOTAGS));
    $password = optional_param('password', '', PARAM_RAW);
    $password2 = optional_param('password2', '', PARAM_RAW);

    if ($firstname === '' || $lastname === '') {
        $error = 'Укажите имя и фамилию';
    } else if ($password !== '' && $password !== $password2) {
        $error = 'Пароли не совпадают';
    } else if ($password !== '') {
        $errmsg = '';
        if (!check_password_policy($password, $errmsg)) {
            $error = $errmsg ?: 'Пароль не подходит под требования';
        }
    }

    if ($error === '') {
        $update = (object) [
            'id' => $USER->id,
            'firstname' => $firstname,
            'lastname' => $lastname,
        ];
        if ($password !== '') {
            $update->password = $password;
        }
        user_update_user($update, $password !== '', false);
        $USER->firstname = $firstname;
        $USER->lastname = $lastname;

        if (!empty($_FILES['image']['tmp_name']) && is_uploaded_file($_FILES['image']['tmp_name'])) {
            $context = context_user::instance($USER->id);
            $icon = process_new_icon($context, 'user', 'icon', 0, $_FILES['image']['tmp_name']);
            if ($icon) {
                $DB->set_field('user', 'picture', $icon, ['id' => $USER->id]);
                $USER->picture = $icon;
            }
        }

        \theme_learnhub\roles::sync_wp_account($USER, [
            'firstname' => $firstname,
            'lastname' => $lastname,
            'password' => $password,
        ]);
        $notice = 'Настройки сохранены';
    }
}

$userpicture = $OUTPUT->user_picture($USER, ['size' => 80, 'link' => false]);

echo $OUTPUT->header();
echo html_writer::start_div('lh-account-page');
echo html_writer::tag('h1', 'Настройки учётной записи');
echo html_writer::tag('p', 'Имя, фото и пароль. Если аккаунт связан с сайтом АЦО, пароль обновится и там.', ['class' => 'lh-studio-muted']);

if ($notice) {
    echo html_writer::div(s($notice), 'lh-account-page__notice');
}
if ($error) {
    echo html_writer::div(s($error), 'lh-account-page__error');
}

echo html_writer::start_tag('form', [
    'method' => 'post',
    'action' => $PAGE->url->out(false),
    'enctype' => 'multipart/form-data',
    'class' => 'lh-account-page__form',
]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);

echo html_writer::start_div('lh-account-page__photo');
echo $userpicture;
echo html_writer::tag('label', 'Фото профиля');
echo html_writer::empty_tag('input', ['type' => 'file', 'name' => 'image', 'accept' => 'image/*']);
echo html_writer::end_div();

echo html_writer::tag('label', 'Имя' . html_writer::empty_tag('input', [
    'type' => 'text',
    'name' => 'firstname',
    'required' => 'required',
    'value' => $USER->firstname,
    'autocomplete' => 'given-name',
]));
echo html_writer::tag('label', 'Фамилия' . html_writer::empty_tag('input', [
    'type' => 'text',
    'name' => 'lastname',
    'required' => 'required',
    'value' => $USER->lastname,
    'autocomplete' => 'family-name',
]));
echo html_writer::tag('label', 'Новый пароль' . html_writer::empty_tag('input', [
    'type' => 'password',
    'name' => 'password',
    'autocomplete' => 'new-password',
    'placeholder' => 'оставьте пустым, чтобы не менять',
]));
echo html_writer::tag('label', 'Повторите пароль' . html_writer::empty_tag('input', [
    'type' => 'password',
    'name' => 'password2',
    'autocomplete' => 'new-password',
]));

echo html_writer::tag('button', 'Сохранить', ['type' => 'submit', 'class' => 'lh-course-card__btn lh-course-card__btn--primary']);
echo html_writer::end_tag('form');

echo html_writer::tag('p', html_writer::link($recoverurl, 'Восстановить пароль по email', ['class' => 'lh-account-page__recover']));
echo html_writer::end_div();
echo $OUTPUT->footer();
