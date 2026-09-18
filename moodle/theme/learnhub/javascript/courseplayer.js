(function () {
    'use strict';

    var root = document.querySelector('.aco-player');

    function promoteQuizStart() {
        var start = document.querySelector('.aco-player__moodle .quizstartbuttondiv [type=submit]');
        var actions = document.querySelector('.aco-toolbar__actions');
        if (!start || !actions || actions.querySelector('[data-aco-quiz-start]')) {
            return;
        }
        var clone = document.createElement('button');
        clone.type = 'button';
        clone.className = 'aco-btn aco-btn--primary';
        clone.setAttribute('data-aco-quiz-start', '1');
        clone.textContent = (start.value || start.textContent || 'Начать тест').trim();
        clone.addEventListener('click', function () {
            start.click();
        });
        actions.insertBefore(clone, actions.firstChild);
    }
    promoteQuizStart();

    function assignFileAddButton(fm) {
        return fm.querySelector('.fp-btn-add a, .fp-btn-add .btn, .fp-btn-add button, .fp-btn-add [role="button"]');
    }

    function assignFileHasItems(fm) {
        return !!(fm.querySelector('.fp-file, .fp-filename-icon, .fp-filename'));
    }

    function markAssignFileState(fm) {
        fm.classList.toggle('is-empty', !assignFileHasItems(fm));
        fm.classList.toggle('has-files', assignFileHasItems(fm));
    }

    function bindAssignFileDrop() {
        document.querySelectorAll('.aco-player__moodle .filemanager').forEach(function (fm) {
            markAssignFileState(fm);
        });
    }
    bindAssignFileDrop();
    if (window.MutationObserver) {
        var moodle = document.querySelector('.aco-player__moodle');
        if (moodle) {
            new MutationObserver(function () {
                bindAssignFileDrop();
            }).observe(moodle, { childList: true, subtree: true });
        }
    }

    function hideAssignNoise() {
        document.querySelectorAll('.submissionstatustable tr, .feedbacktable tr, .attempthistory tr').forEach(function (tr) {
            if (tr.querySelector('.comment-link, .comment-area, [id^="comment-ctrl"], .commentinline')) {
                tr.hidden = true;
            }
        });
    }
    hideAssignNoise();

    function fixAssignActions() {
        document.querySelectorAll('#fitem_id_submitbutton .felement, form.mform .fitem_actionbuttons .felement').forEach(function (box) {
            box.classList.add('aco-assign-actions');
        });
        document.querySelectorAll('a.mod-assign-history-link').forEach(function (link) {
            link.style.overflow = 'visible';
        });
    }
    fixAssignActions();
    if (window.MutationObserver) {
        var moodleBox = document.querySelector('.aco-player__moodle');
        if (moodleBox) {
            new MutationObserver(function () {
                fixAssignActions();
            }).observe(moodleBox, { childList: true, subtree: true });
        }
    }

    document.addEventListener('click', function (e) {
        var row = e.target.closest('.que .answer .r0, .que .answer .r1, .que .answer .answer-item');
        if (!row || e.target.closest('input, label, a, button, textarea')) {
            return;
        }
        var input = row.querySelector('input[type="radio"], input[type="checkbox"]');
        if (input && !input.disabled) {
            input.click();
        }
    });

    function setNavOpen(open) {
        if (!root) {
            return;
        }
        root.classList.toggle('nav-open', open);
        document.body.classList.toggle('aco-nav-lock', open);
        var btn = root.querySelector('.aco-player__menu-btn');
        if (btn) {
            btn.setAttribute('aria-expanded', open ? 'true' : 'false');
        }
        if (open) {
            var cur = root.querySelector('.aco-lesson.is-current, .aco-mod.has-current');
            if (cur && typeof cur.scrollIntoView === 'function') {
                setTimeout(function () {
                    cur.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
                }, 50);
            }
        }
    }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && root && root.classList.contains('nav-open')) {
            setNavOpen(false);
        }
    });

    document.addEventListener('click', function (e) {
        var acc = e.target.closest('[data-aco-acc]');
        if (acc) {
            var mod = acc.closest('.aco-mod');
            if (mod) {
                mod.classList.toggle('is-open');
                acc.setAttribute('aria-expanded', mod.classList.contains('is-open') ? 'true' : 'false');
            }
            return;
        }

        var menu = e.target.closest('[data-aco-toggle]');
        if (menu) {
            if (root) {
                setNavOpen(!root.classList.contains('nav-open'));
            }
            return;
        }

        if (root && root.classList.contains('nav-open') && !e.target.closest('.aco-player__nav')) {
            setNavOpen(false);
        }

        var lesson = e.target.closest('.aco-lesson a, .aco-lesson__row');
        if (lesson && root) {
            setNavOpen(false);
        }

        var zone = e.target.closest('.filemanager-container, .filemanager .fp-content, .filemanager .fm-empty-container, .filemanager .dndupload-message');
        if (zone && !e.target.closest('a, button, input, .fp-file, .fp-filename, .fp-filename-icon, .fp-reficons3')) {
            var fm = zone.closest('.filemanager');
            var add = fm ? assignFileAddButton(fm) : null;
            if (add && (!assignFileHasItems(fm) || e.target.closest('.fm-empty-container, .dndupload-message, .dndupload-arrow'))) {
                e.preventDefault();
                add.click();
                return;
            }
        }

        var btn = e.target.closest('[data-aco-complete]');
        if (!btn) {
            return;
        }
        e.preventDefault();
        e.stopPropagation();
        if (!root) {
            return;
        }
        btn.disabled = true;
        btn.textContent = 'Сохраняем…';
        var body = new URLSearchParams();
        body.set('cmid', btn.getAttribute('data-aco-complete'));
        var sesskey = root.getAttribute('data-sesskey');
        if (!sesskey && window.M && M.cfg && M.cfg.sesskey) {
            sesskey = M.cfg.sesskey;
        }
        body.set('sesskey', sesskey || '');
        fetch(root.getAttribute('data-complete-url'), {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString()
        }).then(function (r) {
            return r.json().then(function (data) {
                if (!r.ok || !data || !data.ok) {
                    throw new Error((data && (data.error || data.message)) || 'fail');
                }
            }, function () {
                throw new Error('fail');
            });
        }).then(function () {
            window.location.reload();
        }).catch(function () {
            btn.disabled = false;
            btn.textContent = 'Отметить как пройденное';
            alert('Не удалось отметить урок. Попробуйте ещё раз.');
        });
    });
})();
