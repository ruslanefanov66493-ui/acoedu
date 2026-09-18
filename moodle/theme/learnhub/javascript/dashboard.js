/**
 * LearnHub dashboard course grid.
 *
 * Renders Skillbox-style cards next to the (hidden) My overview block.
 */
(function () {
    'use strict';

    function esc(text) {
        var div = document.createElement('div');
        div.textContent = String(text || '');
        return div.innerHTML;
    }

    function courseCard(c) {
        var thumbStyle = c.image ? ' style="background-image:url(\'' + esc(c.image) + '\')"' : '';
        var isDone = !!c.completed || c.progress >= 100;
        var btnClass = isDone ? 'lh-course-card__btn--success' : 'lh-course-card__btn--primary';
        var btnLabel = isDone ? 'Скачать сертификат' : (c.progress > 0 ? 'Продолжить обучение' : 'Начать обучение');
        var btnHref = isDone ? c.certificate : (c.continueurl || c.url);
        var openHref = c.continueurl || c.url;
        var barClass = isDone ? ' is-done' : '';
        var meta = [];
        if (c.teacher) {
            meta.push('Автор: ' + esc(c.teacher));
        }
        if (c.hourslabel) {
            meta.push(esc(c.hourslabel));
        }
        if (c.lessonslabel) {
            meta.push(esc(c.lessonslabel));
        }

        return [
            '<article class="lh-course-card">',
            '<a class="lh-course-card__thumb"' + thumbStyle + ' href="' + esc(openHref) + '">',
            '<span class="lh-course-card__play" aria-hidden="true">▶</span>',
            '</a>',
            '<div class="lh-course-card__body">',
            '<h3 class="lh-course-card__title"><a href="' + esc(openHref) + '">' + esc(c.fullname) + '</a></h3>',
            '<p class="lh-course-card__meta">' + meta.join(' • ') + '</p>',
            '<div class="lh-course-card__progress-row">',
            '<div class="lh-course-card__progress' + barClass + '"><span style="width:' + c.progress + '%"></span></div>',
            '<strong>' + c.progress + '%</strong>',
            '</div>',
            '<a class="lh-course-card__btn ' + btnClass + '" href="' + esc(btnHref) + '">' + btnLabel + '</a>',
            '</div>',
            '</article>'
        ].join('');
    }

    function init() {
        var overview = document.querySelector('.block_myoverview');
        var main = document.getElementById('region-main');
        var mount = overview || main;
        if (!mount || !window.LH_COURSES_ENDPOINT) {
            return;
        }

        var container = document.createElement('div');
        container.className = 'lh-dash';
        container.setAttribute('data-region', 'lh-dashboard-courses');
        container.innerHTML = [
            '<nav class="lh-crumb" aria-label="Навигация">Главная <span aria-hidden="true">›</span> Панель</nav>',
            '<p class="lh-dash-courses__loading">Загрузка курсов…</p>'
        ].join('');

        if (overview && overview.parentNode) {
            overview.parentNode.insertBefore(container, overview);
            overview.classList.add('lh-hidden-source');
        } else {
            mount.insertBefore(container, mount.firstChild);
        }

        fetch(window.LH_COURSES_ENDPOINT, { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                var courses = (data && data.courses) || [];
                var crumb = '<nav class="lh-crumb" aria-label="Навигация">Главная <span aria-hidden="true">›</span> Панель</nav>';
                if (!courses.length) {
                    container.innerHTML = crumb + [
                        '<div class="lh-dash-empty">',
                        '<p class="lh-dash-empty__title">У вас пока нет курсов</p>',
                        '<p class="lh-dash-empty__text">После покупки курса он появится здесь.</p>',
                        '</div>'
                    ].join('');
                    return;
                }
                container.innerHTML = crumb + '<div class="lh-course-grid">' + courses.map(courseCard).join('') + '</div>';
            })
            .catch(function () {
                container.innerHTML = '<div class="lh-dash-empty"><p>Не удалось загрузить курсы. Обновите страницу.</p></div>';
            });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
}());
