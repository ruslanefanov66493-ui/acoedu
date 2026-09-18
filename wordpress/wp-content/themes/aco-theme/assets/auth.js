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
            post('aco_ajax_login', {
                email: this.email.value,
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
        return post('aco_ajax_register', pendingRegistration, 'aco_register_nonce', nonce)
            .then(function (res) {
                if (onDone) onDone();
                if (res.success) {
                    document.getElementById('verify-email').value = pendingRegistration.email;
                    document.getElementById('verify-context').value = 'register';
                    showPanel('verify');
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
            if (!emailAllowed(this.email.value)) {
                showError('register-error', publicEmailError());
                return;
            }
            setLoading(this, true);
            pendingRegistration = {
                first_name: this.first_name.value,
                email: this.email.value,
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
            setLoading(this, true);
            recoveryEmail = this.email.value;
            var nonce = this.querySelector('[name="aco_recovery_nonce"]').value;
            post('aco_ajax_send_code', { email: recoveryEmail }, 'aco_recovery_nonce', nonce)
                .then(function (res) {
                    setLoading(recoveryForm, false);
                    if (res.success) {
                        document.getElementById('verify-email').value = recoveryEmail;
                        document.getElementById('verify-context').value = 'recovery';
                        showPanel('verify');
                    } else {
                        showError('recovery-error', res.data || 'Ошибка. Проверьте email');
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
            var action  = context === 'register' ? 'aco_ajax_verify_register' : 'aco_ajax_verify_code';

            post(action, {
                email: this.email.value,
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
