/* Auth page: tabs + AJAX handlers */
(function () {
    'use strict';

    var ajaxUrl = (typeof acoAuth !== 'undefined') ? acoAuth.ajax_url : '/wp-admin/admin-ajax.php';
    var recoveryEmail = '';
    var pendingRegistration = null; // { first_name, email, password } — used to resend code
    var redirectTo = new URLSearchParams(window.location.search).get('redirect_to') || '';

    /* ── helpers ─────────────────────────────── */
    var urlTabs = { login: true, register: true, recovery: true };

    function showPanel(name, opts) {
        opts = opts || {};
        document.querySelectorAll('.aco-auth-panel').forEach(function (p) {
            p.classList.toggle('is-active', p.dataset.panel === name);
        });

        if (urlTabs[name] && history.replaceState) {
            var url = new URL(window.location.href);
            if (name === 'login') {
                url.searchParams.delete('tab');
            } else {
                url.searchParams.set('tab', name);
            }
            history.replaceState(null, '', url.pathname + url.search + url.hash);
        }

        if (opts.skipFocus) {
            return;
        }
        var panel = document.querySelector('.aco-auth-panel.is-active');
        if (!panel) {
            return;
        }
        var field = panel.querySelector('input:not([type="hidden"]):not([type="checkbox"])');
        if (field && window.matchMedia && window.matchMedia('(min-width: 768px)').matches) {
            window.setTimeout(function () { field.focus(); }, 30);
        }
    }

    function setLoading(form, loading) {
        var btn = form.querySelector('.aco-auth-submit');
        if (!btn) return;
        btn.disabled = loading;
        btn.querySelector('span').hidden = loading;
        btn.querySelector('.aco-spinner').hidden = !loading;
    }

    function showError(id, msg) {
        var el = document.getElementById(id);
        if (!el) return;
        el.textContent = msg;
        el.hidden = !msg;
    }

    function post(action, data, nonce_field_name, nonce_value) {
        var fd = new FormData();
        fd.append('action', action);
        fd.append(nonce_field_name, nonce_value);
        Object.keys(data).forEach(function (k) {
            if (data[k] !== undefined && data[k] !== null) {
                fd.append(k, data[k]);
            }
        });
        var ctrl = typeof AbortController !== 'undefined' ? new AbortController() : null;
        var timer = ctrl ? setTimeout(function () { ctrl.abort(); }, 20000) : null;
        function clearTimer() {
            if (timer) clearTimeout(timer);
        }
        return fetch(ajaxUrl, {
            method: 'POST',
            body: fd,
            credentials: 'same-origin',
            signal: ctrl ? ctrl.signal : undefined
        }).then(function (r) {
            clearTimer();
            return r.text().then(function (text) {
                var start = text.indexOf('{');
                if (start === -1) {
                    throw new Error('bad-json');
                }
                try {
                    return JSON.parse(text.slice(start));
                } catch (err) {
                    throw new Error('bad-json');
                }
            });
        }, function (err) {
            clearTimer();
            throw err;
        });
    }

    function formChannel(form) {
        return (form && form.getAttribute('data-channel')) || 'email';
    }

    function setChannel(formId, channel) {
        var form = document.getElementById(formId);
        if (!form) return;
        form.setAttribute('data-channel', channel);
        form.querySelectorAll('[data-for]').forEach(function (field) {
            var on = field.getAttribute('data-for') === channel;
            field.hidden = !on;
            field.querySelectorAll('input').forEach(function (input) {
                if (input.type === 'hidden') return;
                input.required = on;
            });
        });
        document.querySelectorAll('[data-channel-for="' + formId + '"] .aco-channel__btn').forEach(function (btn) {
            btn.classList.toggle('is-active', btn.getAttribute('data-channel') === channel);
        });
        if (formId === 'aco-recovery-form') {
            var hint = document.getElementById('recovery-hint');
            if (hint) {
                hint.textContent = channel === 'phone'
                    ? 'Укажите номер — отправим код для сброса пароля.'
                    : 'Укажите почту — отправим код для сброса пароля.';
            }
        }
    }

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.aco-channel__btn');
        if (!btn) return;
        var bar = btn.closest('[data-channel-for]');
        if (!bar) return;
        setChannel(bar.getAttribute('data-channel-for'), btn.getAttribute('data-channel'));
    });

    function phoneDigits(value) {
        var digits = String(value || '').replace(/\D/g, '');
        if (digits.charAt(0) === '8' || digits.charAt(0) === '7') digits = digits.slice(1);
        return digits.slice(0, 10);
    }

    function phoneOk(value) {
        var digits = phoneDigits(value);
        return digits.length === 10 && digits.charAt(0) === '9';
    }

    function openVerify(opts) {
        document.getElementById('verify-email').value = opts.email || '';
        document.getElementById('verify-phone').value = opts.phone || '';
        document.getElementById('verify-channel').value = opts.channel || 'email';
        document.getElementById('verify-context').value = opts.context;
        var hint = document.getElementById('verify-hint');
        var label = document.getElementById('verify-code-label');
        if (opts.context === 'recovery') {
            if (hint) {
                hint.textContent = opts.channel === 'phone'
                    ? 'Если номер есть в аккаунте, код уже отправлен. Если SMS нет — проверьте номер.'
                    : 'Если почта есть в аккаунте, код уже отправлен. Если письма нет — проверьте адрес и папку «Спам».';
            }
            if (label) label.textContent = opts.channel === 'phone' ? 'Код из SMS' : 'Код из письма';
        } else if (opts.channel === 'phone') {
            if (hint) hint.textContent = 'Мы отправили 6-значный код по SMS на ' + (opts.phone || 'ваш номер') + '.';
            if (label) label.textContent = 'Код из SMS';
        } else {
            if (hint) hint.textContent = 'Мы отправили 6-значный код на вашу почту.';
            if (label) label.textContent = 'Код из письма';
        }
        showPanel('verify');
    }

    /* ── goto triggers (data-goto) ───────────── */
    document.addEventListener('click', function (e) {
        var el = e.target.closest('[data-goto]');
        if (!el) return;
        showPanel(el.dataset.goto);
    });

    /* ── Toggle password visibility ──────────── */
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.aco-toggle-pw');
        if (!btn) return;
        var wrap = btn.closest('.aco-field__pw-wrap');
        if (!wrap) return;
        var input = wrap.querySelector('input');
        if (!input) return;
        var show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        btn.setAttribute('aria-pressed', show ? 'true' : 'false');
        btn.setAttribute('aria-label', show ? 'Скрыть пароль' : 'Показать пароль');
        btn.classList.toggle('is-on', show);
    });

    /* ── LOGIN ───────────────────────────────── */
    var loginForm = document.getElementById('aco-login-form');
    if (loginForm) {
        loginForm.addEventListener('submit', function (e) {
            e.preventDefault();
            showError('login-error', '');
            setLoading(this, true);
            var nonce = this.querySelector('[name="aco_login_nonce"]').value;
            var channel = formChannel(this);
            if (channel === 'phone' && !phoneOk(this.phone.value)) {
                setLoading(this, false);
                showError('login-error', 'Укажите мобильный номер в формате +7 9XX');
                return;
            }
            post('aco_ajax_login', {
                login: channel === 'phone' ? this.phone.value : this.email.value,
                password: this.password.value,
                rememberme: this.rememberme ? this.rememberme.checked ? '1' : '' : '',
                redirect_to: redirectTo
            }, 'aco_login_nonce', nonce).then(function (res) {
                setLoading(loginForm, false);
                if (res.success) {
                    window.location.href = res.data.redirect || '/account/';
                } else {
                    showError('login-error', res.data || 'Ошибка входа');
                }
            }).catch(function () {
                setLoading(loginForm, false);
                showError('login-error', 'Ошибка сети, попробуйте ещё раз');
            });
        });
    }

    function emailAllowed(value) {
        var domains = (typeof acoAuth !== 'undefined' && acoAuth.email_domains) ? acoAuth.email_domains : [];
        if (!domains.length) return true;
        var at = String(value || '').toLowerCase().lastIndexOf('@');
        if (at < 1) return false;
        return domains.indexOf(value.slice(at + 1).toLowerCase()) !== -1;
    }

    function publicEmailError() {
        return (typeof acoAuth !== 'undefined' && acoAuth.email_error) ? acoAuth.email_error : 'Укажите почту популярного сервиса';
    }

    /* ── REGISTER ────────────────────────────── */
    var regForm = document.getElementById('aco-register-form');

    function sendRegisterCode(errorElId, onDone) {
        var nonce = regForm.querySelector('[name="aco_register_nonce"]').value;
        var action = pendingRegistration.channel === 'phone' ? 'aco_ajax_phone_register' : 'aco_ajax_register';
        return post(action, pendingRegistration, 'aco_register_nonce', nonce)
            .then(function (res) {
                if (onDone) onDone();
                if (res.success) {
                    openVerify({
                        context: 'register',
                        channel: pendingRegistration.channel,
                        email: pendingRegistration.email,
                        phone: pendingRegistration.phone
                    });
                } else {
                    showError(errorElId, res.data || 'Ошибка регистрации');
                }
            }).catch(function () {
                if (onDone) onDone();
                showError(errorElId, 'Ошибка сети, попробуйте ещё раз');
            });
    }

    if (regForm) {
        regForm.addEventListener('submit', function (e) {
            e.preventDefault();
            showError('register-error', '');
            if (!this.agree_terms.checked) {
                showError('register-error', 'Необходимо принять публичную оферту и дать согласие на обработку персональных данных');
                return;
            }
            if (this.password.value.length < 8) {
                showError('register-error', 'Пароль должен содержать минимум 8 символов');
                return;
            }
            var channel = formChannel(this);
            if (channel === 'phone') {
                if (!phoneOk(this.phone.value)) {
                    showError('register-error', 'Укажите мобильный номер в формате +7 9XX');
                    return;
                }
            } else if (!emailAllowed(this.email.value)) {
                showError('register-error', publicEmailError());
                return;
            }
            setLoading(this, true);
            pendingRegistration = {
                channel: channel,
                first_name: this.first_name.value,
                email: channel === 'email' ? this.email.value : '',
                phone: channel === 'phone' ? this.phone.value : '',
                password: this.password.value,
                agree_terms: '1',
                redirect_to: redirectTo
            };
            sendRegisterCode('register-error', function () { setLoading(regForm, false); });
        });
    }

    /* ── RECOVERY: send code ─────────────────── */
    var recoveryForm = document.getElementById('aco-recovery-form');
    if (recoveryForm) {
        recoveryForm.addEventListener('submit', function (e) {
            e.preventDefault();
            showError('recovery-error', '');
            var channel = formChannel(this);
            if (channel === 'phone' && !phoneOk(this.phone.value)) {
                showError('recovery-error', 'Укажите мобильный номер в формате +7 9XX');
                return;
            }
            setLoading(this, true);
            recoveryEmail = channel === 'phone' ? this.phone.value : this.email.value;
            var nonce = this.querySelector('[name="aco_recovery_nonce"]').value;
            var action = channel === 'phone' ? 'aco_ajax_phone_send_code' : 'aco_ajax_send_code';
            var payload = channel === 'phone' ? { phone: this.phone.value } : { email: this.email.value };
            post(action, payload, 'aco_recovery_nonce', nonce)
                .then(function (res) {
                    setLoading(recoveryForm, false);
                    if (res.success) {
                        openVerify({
                            context: 'recovery',
                            channel: channel,
                            email: channel === 'email' ? recoveryEmail : '',
                            phone: channel === 'phone' ? recoveryEmail : ''
                        });
                    } else {
                        showError('recovery-error', res.data || 'Ошибка. Проверьте данные');
                    }
                }).catch(function () {
                    setLoading(recoveryForm, false);
                    showError('recovery-error', 'Ошибка сети');
                });
        });
    }

    /* ── VERIFY code (recovery OR registration) ─── */
    var verifyForm = document.getElementById('aco-verify-form');
    if (verifyForm) {
        verifyForm.addEventListener('submit', function (e) {
            e.preventDefault();
            showError('verify-error', '');
            setLoading(this, true);
            var nonce   = this.querySelector('[name="aco_verify_nonce"]').value;
            var context = this.context.value; // 'register' | 'recovery'
            var channel = document.getElementById('verify-channel').value || 'email';
            var action;
            if (channel === 'phone') {
                action = context === 'register' ? 'aco_ajax_phone_verify_register' : 'aco_ajax_phone_verify_code';
            } else {
                action = context === 'register' ? 'aco_ajax_verify_register' : 'aco_ajax_verify_code';
            }

            post(action, {
                email: this.email.value,
                phone: document.getElementById('verify-phone').value,
                code: (this.code.value || '').replace(/\D/g, ''),
                redirect_to: redirectTo
            }, 'aco_verify_nonce', nonce).then(function (res) {
                setLoading(verifyForm, false);
                if (!res.success) {
                    showError('verify-error', res.data || 'Неверный или устаревший код');
                    return;
                }
                if (context === 'register') {
                    document.getElementById('success-msg').textContent = 'Регистрация завершена! Входим...';
                    showPanel('success');
                    window.location.href = (res.data && res.data.redirect) || '/account/';
                } else {
                    document.getElementById('newpass-email').value = verifyForm.email.value;
                    document.getElementById('newpass-phone').value = document.getElementById('verify-phone').value;
                    document.getElementById('newpass-token').value = res.data.token;
                    showPanel('newpass');
                }
            }).catch(function () {
                setLoading(verifyForm, false);
                if (context === 'register') {
                    showError('verify-error', 'Не удалось войти автоматически. Если аккаунт уже создан — войдите с email и паролем.');
                    return;
                }
                showError('verify-error', 'Ошибка сети');
            });
        });
    }

    /* ── RESEND code ─────────────────────────── */
    var resendBtn = document.getElementById('resend-code');
    if (resendBtn) {
        resendBtn.addEventListener('click', function () {
            var context = document.getElementById('verify-context').value;
            if (context === 'register' && pendingRegistration) {
                showError('verify-error', '');
                resendBtn.disabled = true;
                sendRegisterCode('verify-error', function () { resendBtn.disabled = false; });
            } else {
                showPanel('recovery');
            }
        });
    }

    /* ── NEW PASSWORD ────────────────────────── */
    var newpassForm = document.getElementById('aco-newpass-form');
    if (newpassForm) {
        newpassForm.addEventListener('submit', function (e) {
            e.preventDefault();
            showError('newpass-error', '');
            if (this.password.value !== this.password2.value) {
                showError('newpass-error', 'Пароли не совпадают');
                return;
            }
            if (this.password.value.length < 8) {
                showError('newpass-error', 'Пароль должен содержать минимум 8 символов');
                return;
            }
            setLoading(this, true);
            var nonce = this.querySelector('[name="aco_newpass_nonce"]').value;
            post('aco_ajax_new_password', {
                email: this.email.value,
                phone: document.getElementById('newpass-phone').value,
                token: this.token.value,
                password: this.password.value,
                redirect_to: redirectTo
            }, 'aco_newpass_nonce', nonce).then(function (res) {
                setLoading(newpassForm, false);
                if (res.success) {
                    document.getElementById('success-msg').textContent = 'Пароль изменён! Входим...';
                    showPanel('success');
                    setTimeout(function () {
                        window.location.href = res.data.redirect || '/account/';
                    }, 1500);
                } else {
                    showError('newpass-error', res.data || 'Ошибка сброса пароля');
                }
            }).catch(function () {
                setLoading(newpassForm, false);
                showError('newpass-error', 'Ошибка сети');
            });
        });
    }
}());
