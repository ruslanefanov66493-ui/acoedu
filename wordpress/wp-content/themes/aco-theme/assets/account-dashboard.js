/* Account dashboard renderer (Skillbox-like structure, LearnHub style) */
(function () {
    'use strict';

    var root = document.querySelector('[data-component="account-dashboard"]');
    if (!root) return;

    var endpoint = root.dataset.coursesEndpoint;
    var fallbackCourseUrl = root.dataset.fallbackCourseUrl || '/eb-my-courses/';
    var catalogUrl = root.dataset.catalogUrl || '/catalog/';
    var moodleUrl = root.dataset.moodleUrl || '#';
    var ssoEndpoint = root.dataset.ssoEndpoint || '';
    var progressEndpoint = root.dataset.progressEndpoint || '';
    var restNonce = root.dataset.restNonce || '';
    var moodleLinked = root.dataset.moodleLinked === '1';
    var progressByCourse = {};

    // WP REST cookie auth requires the wp_rest nonce, otherwise requests are anonymous.
    function apiFetch(url) {
        return fetch(url, {
            credentials: 'same-origin',
            headers: restNonce ? { 'X-WP-Nonce': restNonce } : {}
        }).then(function (r) { return r.json(); });
    }

    // Route any Moodle URL through the SSO endpoint so the user is logged in transparently.
    function ssoUrl(target) {
        if (!ssoEndpoint || !moodleLinked) return target;
        return ssoEndpoint + '&redirect=' + encodeURIComponent(target);
    }

    function mdlIdFromUrl(url) {
        var m = /[?&]id=(\d+)/.exec(url || '');
        return m ? m[1] : '';
    }

    var TYPE_LABELS = {
        page: 'Материал', quiz: 'Тест', assign: 'Задание', resource: 'Файл',
        url: 'Ссылка', lesson: 'Урок', book: 'Книга', h5pactivity: 'Интерактив'
    };

    function typeLabel(modname) {
        return TYPE_LABELS[modname] || 'Элемент';
    }

    function moduleRow(mod, quiz) {
        var done = mod.state === 'done';
        var failed = mod.state === 'failed';
        var icon = done ? '✓' : (failed ? '✕' : '○');
        var cls = done ? ' is-done' : (failed ? ' is-failed' : '');
        var badge = '';
        if (mod.type === 'quiz') {
            badge = (quiz && quiz.hasgrade)
                ? '<span class="lh-mod__grade">' + esc(quiz.grade) + ' / ' + esc(quiz.grademax) + '</span>'
                : '<span class="lh-mod__grade lh-mod__grade--none">не пройден</span>';
        }
        return [
            '<li class="lh-mod' + cls + '">',
            '<span class="lh-mod__icon" aria-hidden="true">' + icon + '</span>',
            '<span class="lh-mod__type">' + esc(typeLabel(mod.type)) + '</span>',
            '<a class="lh-mod__name" href="' + esc(ssoUrl(mod.url)) + '" target="_blank" rel="noopener">' + esc(mod.name) + '</a>',
            badge,
            '</li>'
        ].join('');
    }

    function courseDetailsHtml(mdlId) {
        var detail = progressByCourse[mdlId];
        if (!detail || !detail.sections || !detail.sections.length) {
            return '<p class="lh-dash-course__details-empty">Структура курса пока не загружена из Moodle.</p>';
        }
        var quizByCmid = {};
        (detail.quizzes || []).forEach(function (q) { quizByCmid[q.cmid] = q; });

        var html = detail.sections.map(function (section) {
            var doneCount = section.modules.filter(function (m) { return m.state === 'done'; }).length;
            return [
                '<div class="lh-section">',
                '<p class="lh-section__title">' + esc(section.name) + '<span>' + doneCount + '/' + section.modules.length + '</span></p>',
                '<ul class="lh-section__mods">',
                section.modules.map(function (m) { return moduleRow(m, quizByCmid[m.cmid]); }).join(''),
                '</ul>',
                '</div>'
            ].join('');
        }).join('');

        return html;
    }

    var continueTrack = root.querySelector('[data-slot="continue-track"]');
    var gridSlot = root.querySelector('[data-slot="courses-grid"]');

    function esc(text) {
        var div = document.createElement('div');
        div.textContent = String(text || '');
        return div.innerHTML;
    }

    function clampPercent(v) {
        var n = Number(v);
        if (Number.isNaN(n)) return 0;
        return Math.max(0, Math.min(100, Math.round(n)));
    }

    function actionLabel(percent) {
        if (percent >= 100) return 'Пересмотреть';
        if (percent > 0) return 'Продолжить';
        return 'Начать';
    }

    function resolveCourseUrl(course) {
        if (course && course.progress && course.progress.course_url) return ssoUrl(course.progress.course_url);
        if (moodleLinked && moodleUrl && moodleUrl !== '#') return ssoUrl(moodleUrl);
        return fallbackCourseUrl;
    }

    function mapCourse(raw) {
        var moodleCourseUrl = raw && raw.progress && raw.progress.course_url ? raw.progress.course_url : '';
        var mdlId = mdlIdFromUrl(moodleCourseUrl);
        var detail = mdlId ? progressByCourse[mdlId] : null;
        var percent = detail ? clampPercent(detail.progress) : clampPercent(raw && raw.progress ? raw.progress.percentage : 0);
        return {
            title: raw && raw.title ? raw.title : 'Курс',
            excerpt: raw && raw.excerpt ? raw.excerpt : '',
            thumb: raw && raw.thumbnail ? raw.thumbnail : '',
            category: raw && raw.category ? raw.category : 'Курс',
            percent: percent,
            url: resolveCourseUrl(raw),
            action: actionLabel(percent),
            mdlId: mdlId,
            hasDetails: !!detail
        };
    }

    function renderContinue(courses) {
        if (!continueTrack) return;
        if (!courses.length) {
            continueTrack.innerHTML = [
                '<div class="lh-dash__empty-card">',
                '<p>Когда появятся курсы, здесь будут быстрые карточки продолжения.</p>',
                '</div>'
            ].join('');
            return;
        }

        continueTrack.innerHTML = courses.slice(0, 8).map(function (course) {
            return [
                '<article class="lh-dash-mini">',
                '<div class="lh-dash-mini__thumb"' + (course.thumb ? ' style="background-image:url(\'' + esc(course.thumb) + '\')"' : '') + '></div>',
                '<p class="lh-dash-mini__meta">' + esc(course.category) + '</p>',
                '<h3 class="lh-dash-mini__title">' + esc(course.title) + '</h3>',
                '<div class="lh-dash-mini__bar"><span style="width:' + course.percent + '%"></span></div>',
                '<div class="lh-dash-mini__foot">',
                '<span class="lh-dash-mini__progress">' + course.percent + '%</span>',
                '<a href="' + esc(course.url) + '" class="lh-dash-mini__link" target="_blank" rel="noopener">Открыть</a>',
                '</div>',
                '</article>'
            ].join('');
        }).join('');
    }

    function renderGrid(courses) {
        if (!gridSlot) return;
        if (!courses.length) {
            gridSlot.innerHTML = [
                '<div class="lh-dash__empty">',
                '<p class="lh-dash__empty-title">У вас пока нет купленных курсов</p>',
                '<p class="lh-dash__empty-text">Загляните в каталог, чтобы выбрать подходящее обучение.</p>',
                '<a class="lh-btn lh-btn--primary lh-btn--sm" href="' + esc(catalogUrl) + '">Перейти в каталог</a>',
                '</div>'
            ].join('');
            return;
        }

        gridSlot.innerHTML = courses.map(function (course, idx) {
            var detailsBtn = course.hasDetails
                ? '<button type="button" class="lh-dash-course__toggle" data-details-toggle="' + idx + '" aria-expanded="false">Модули и тесты<span aria-hidden="true">▾</span></button>'
                : '';
            var detailsPanel = course.hasDetails
                ? '<div class="lh-dash-course__details" data-details="' + idx + '" hidden>' + courseDetailsHtml(course.mdlId) + '</div>'
                : '';
            return [
                '<article class="lh-dash-course">',
                '<div class="lh-dash-course__thumb"' + (course.thumb ? ' style="background-image:url(\'' + esc(course.thumb) + '\')"' : '') + '></div>',
                '<div class="lh-dash-course__body">',
                '<h3 class="lh-dash-course__title">' + esc(course.title) + '</h3>',
                '<div class="lh-dash-course__row">',
                '<span class="lh-dash-course__cat">' + esc(course.category) + '</span>',
                '<span class="lh-dash-course__percent">' + course.percent + '%</span>',
                '</div>',
                '<div class="lh-dash-course__bar"><span style="width:' + course.percent + '%"></span></div>',
                '<div class="lh-dash-course__actions">',
                '<a class="lh-btn lh-btn--primary lh-btn--sm lh-dash-course__btn" href="' + esc(course.url) + '" target="_blank" rel="noopener">' + esc(course.action) + '</a>',
                detailsBtn,
                '</div>',
                detailsPanel,
                '</div>',
                '</article>'
            ].join('');
        }).join('');
    }

    function bindDetailsToggles() {
        if (!gridSlot) return;
        gridSlot.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-details-toggle]');
            if (!btn) return;
            var panel = gridSlot.querySelector('[data-details="' + btn.dataset.detailsToggle + '"]');
            if (!panel) return;
            var open = panel.hasAttribute('hidden');
            if (open) {
                panel.removeAttribute('hidden');
            } else {
                panel.setAttribute('hidden', '');
            }
            btn.setAttribute('aria-expanded', open ? 'true' : 'false');
            btn.classList.toggle('is-open', open);
        });
    }

    function cardStep(track, selector) {
        var card = track.querySelector(selector);
        if (!card) return Math.max(track.clientWidth, 240);
        var gap = parseFloat(getComputedStyle(track).gap) || 14;
        return card.getBoundingClientRect().width + gap;
    }

    function updateCarouselNav(track, prev, next, nav, selector, minCount) {
        if (!track) return;
        var count = track.querySelectorAll(selector).length;
        var overflow = track.scrollWidth > track.clientWidth + 8;
        var need = count >= minCount && overflow;
        if (nav) nav.hidden = !need;
        if (!need) return;
        var max = Math.max(0, track.scrollWidth - track.clientWidth - 4);
        if (prev) prev.disabled = track.scrollLeft <= 4;
        if (next) next.disabled = track.scrollLeft >= max;
    }

    function bindCarousel(track, prevSel, nextSel, navSel, selector, minCount) {
        if (!track) return { refresh: function () {} };
        var prev = root.querySelector(prevSel);
        var next = root.querySelector(nextSel);
        var nav = root.querySelector(navSel);
        if (!prev || !next) return { refresh: function () {} };

        var refresh = function () {
            updateCarouselNav(track, prev, next, nav, selector, minCount);
        };

        prev.addEventListener('click', function () {
            track.scrollBy({ left: -cardStep(track, selector), behavior: 'smooth' });
        });
        next.addEventListener('click', function () {
            track.scrollBy({ left: cardStep(track, selector), behavior: 'smooth' });
        });
        track.addEventListener('scroll', refresh, { passive: true });
        window.addEventListener('resize', refresh);

        return { refresh: refresh };
    }

    function bindScrollButtons() {
        return {
            continueNav: bindCarousel(continueTrack, '[data-scroll="prev"]', '[data-scroll="next"]', '[data-nav="continue"]', '.lh-dash-mini', 2),
            coursesNav: bindCarousel(gridSlot, '[data-scroll-courses="prev"]', '[data-scroll-courses="next"]', '[data-nav="courses"]', '.lh-dash-course', 2)
        };
    }

    function loadDashboard() {
        if (!endpoint) return;

        var coursesReq = apiFetch(endpoint + '?number_of_recommended_courses=8');

        var progressReq = (progressEndpoint && moodleLinked)
            ? apiFetch(progressEndpoint).catch(function () { return null; })
            : Promise.resolve(null);

        Promise.all([coursesReq, progressReq])
            .then(function (results) {
                var payload = results[0];
                var progress = results[1];
                if (progress && progress.courses) {
                    progress.courses.forEach(function (c) {
                        progressByCourse[String(c.moodle_course_id)] = c;
                    });
                }
                var rawCourses = (payload && payload.enrolled_courses) ? payload.enrolled_courses : [];
                var mapped = rawCourses.map(mapCourse);
                // prioritize in-progress cards in "continue" line
                mapped.sort(function (a, b) { return b.percent - a.percent; });
                renderContinue(mapped);
                renderGrid(mapped);
                requestAnimationFrame(function () {
                    carousels.continueNav.refresh();
                    carousels.coursesNav.refresh();
                });
            })
            .catch(function () {
                if (continueTrack) continueTrack.innerHTML = '<div class="lh-dash__empty-card"><p>Не удалось загрузить прогресс. Обновите страницу.</p></div>';
                if (gridSlot) gridSlot.innerHTML = '<div class="lh-dash__empty"><p class="lh-dash__empty-title">Ошибка загрузки</p><p class="lh-dash__empty-text">Попробуйте обновить страницу через пару секунд.</p></div>';
                carousels.continueNav.refresh();
                carousels.coursesNav.refresh();
            });
    }

    var carousels = bindScrollButtons();
    bindDetailsToggles();
    loadDashboard();
}());
