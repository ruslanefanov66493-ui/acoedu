(function () {
    'use strict';

    var root = document.querySelector('[data-profile]');
    if (!root) return;

    var ajax = root.getAttribute('data-ajax');
    var nonceEl = document.getElementById('aco_profile_nonce');

    document.querySelectorAll('[data-dash-tab]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var name = btn.getAttribute('data-dash-tab');
            document.querySelectorAll('[data-dash-tab]').forEach(function (b) {
                b.classList.toggle('is-active', b === btn);
            });
            document.querySelectorAll('[data-dash-pane]').forEach(function (pane) {
                pane.classList.toggle('is-active', pane.getAttribute('data-dash-pane') === name);
            });
            if (history.replaceState) {
                var url = new URL(window.location.href);
                if (name === 'profile') {
                    url.searchParams.set('tab', 'profile');
                } else {
                    url.searchParams.delete('tab');
                }
                history.replaceState(null, '', url.pathname + url.search);
            }
        });
    });

    root.querySelectorAll('[data-profile-reveal]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var kind = btn.getAttribute('data-profile-reveal');
            var form = root.querySelector('[data-profile-form="' + kind + '"]');
            if (!form) return;
            form.hidden = false;
            btn.hidden = true;
            var field = form.querySelector('input');
            if (field) field.focus();
        });
    });

    function noteOf(form) {
        var card = form.closest('.lh-profile-card, .lh-profile-block') || form.parentElement;
        return card ? card.querySelector('[data-form-note]') : null;
    }

    function say(form, msg, isError) {
        var note = noteOf(form);
        if (!note) return;
        note.hidden = !msg;
        note.textContent = msg || '';
        note.classList.toggle('is-error', !!isError);
    }

    function post(action, data) {
        var fd = new FormData();
        fd.append('action', action);
        fd.append('aco_profile_nonce', nonceEl ? nonceEl.value : '');
        Object.keys(data).forEach(function (key) {
            fd.append(key, data[key]);
        });
        return fetch(ajax, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (r) { return r.json(); });
    }

    root.querySelectorAll('[data-profile-form]').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            say(form, '', false);
            var kind = form.getAttribute('data-profile-form');
            var btn = form.querySelector('button[type="submit"]');
            btn.disabled = true;

            var done = function (res, okText) {
                btn.disabled = false;
                if (!res || !res.success) {
                    say(form, (res && res.data) || 'Не получилось сохранить', true);
                    return;
                }
                say(form, (res.data && res.data.message) || okText, false);
            };

            if (kind === 'name') {
                post('aco_ajax_profile_save', {
                    first_name: form.first_name.value,
                    last_name: form.last_name.value
                }).then(function (res) { done(res, 'Сохранено'); }).catch(function () {
                    btn.disabled = false;
                    say(form, 'Ошибка сети', true);
                });
                return;
            }

            if (kind === 'password') {
                post('aco_ajax_profile_password', {
                    old_password: form.old_password.value,
                    password: form.password.value
                }).then(function (res) {
                    done(res, 'Пароль изменён');
                    if (res && res.success) form.reset();
                }).catch(function () {
                    btn.disabled = false;
                    say(form, 'Ошибка сети', true);
                });
                return;
            }

            var codeWrap = form.querySelector('[data-profile-code]');
            var waiting = codeWrap && !codeWrap.hidden;

            if (kind === 'email' && !waiting) {
                var current = (form.getAttribute('data-current-email') || '').trim().toLowerCase();
                var next = (form.email.value || '').trim().toLowerCase();
                if (current && next === current) {
                    btn.disabled = false;
                    form.hidden = true;
                    var reveal = root.querySelector('[data-profile-reveal="email"]');
                    if (reveal) reveal.hidden = false;
                    return;
                }
            }

            if (kind === 'phone') {
                var action = waiting ? 'aco_ajax_profile_phone_confirm' : 'aco_ajax_profile_phone_send';
                post(action, { phone: form.phone.value, code: form.code ? form.code.value : '' })
                    .then(function (res) {
                        btn.disabled = false;
                        if (!res || !res.success) {
                            say(form, (res && res.data) || 'Не получилось', true);
                            return;
                        }
                        if (!waiting) {
                            codeWrap.hidden = false;
                            btn.textContent = 'Подтвердить номер';
                            say(form, 'Код отправлен. В тестовом режиме его видно в панели АЦО.', false);
                            return;
                        }
                        say(form, res.data.message || 'Номер привязан', false);
                        window.setTimeout(function () { window.location.reload(); }, 700);
                    }).catch(function () {
                        btn.disabled = false;
                        say(form, 'Ошибка сети', true);
                    });
                return;
            }

            if (kind === 'email') {
                var mailAction = waiting ? 'aco_ajax_profile_email_confirm' : 'aco_ajax_profile_email_send';
                post(mailAction, { email: form.email.value, code: form.code ? form.code.value : '' })
                    .then(function (res) {
                        btn.disabled = false;
                        if (!res || !res.success) {
                            say(form, (res && res.data) || 'Не получилось', true);
                            return;
                        }
                        if (res.data && res.data.unchanged) {
                            form.hidden = true;
                            var edit = root.querySelector('[data-profile-reveal="email"]');
                            if (edit) edit.hidden = false;
                            return;
                        }
                        if (!waiting) {
                            codeWrap.hidden = false;
                            btn.textContent = 'Подтвердить почту';
                            say(form, 'Код отправлен на новую почту.', false);
                            return;
                        }
                        say(form, res.data.message || 'Почта сохранена', false);
                        window.setTimeout(function () { window.location.reload(); }, 700);
                    }).catch(function () {
                        btn.disabled = false;
                        say(form, 'Ошибка сети', true);
                    });
            }
        });
    });
}());
