/**
 * Mobile header panel: burger, overlay, Escape, click-outside, resize.
 */
(function () {
    'use strict';

    var header = document.querySelector('.lh-header');
    if (!header) {
        return;
    }

    if (!document.body.classList.contains('page-template-page-account')) {
        var onScroll = function () {
            header.classList.toggle('is-scrolled', window.scrollY > 12);
        };
        onScroll();
        window.addEventListener('scroll', onScroll, { passive: true });
    }

    var toggle = header.querySelector('.lh-nav-toggle');
    var closer = header.querySelector('.lh-nav-close');
    var panel = header.querySelector('#lh-header-panel');
    var overlay = document.querySelector('.lh-nav-overlay');
    if (!toggle || !panel) {
        return;
    }

    var mq = window.matchMedia('(max-width: 1100px)');

    function setOpen(open) {
        header.classList.toggle('is-nav-open', open);
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        toggle.setAttribute('aria-label', open ? 'Закрыть меню' : 'Открыть меню');
        if (overlay) {
            overlay.hidden = !open;
        }
        document.body.classList.toggle('lh-nav-locked', open);
    }

    function close() {
        setOpen(false);
    }

    toggle.addEventListener('click', function (e) {
        e.stopPropagation();
        setOpen(!header.classList.contains('is-nav-open'));
    });

    if (closer) {
        closer.addEventListener('click', function (e) {
            e.stopPropagation();
            close();
        });
    }

    if (overlay) {
        overlay.addEventListener('click', close);
    }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            close();
        }
    });

    document.addEventListener('click', function (e) {
        if (!header.classList.contains('is-nav-open')) {
            return;
        }
        if (!header.contains(e.target)) {
            close();
        }
    });

    panel.addEventListener('click', function (e) {
        if (e.target.closest('a')) {
            close();
        }
    });

    function onBreakpoint() {
        if (!mq.matches) {
            close();
        }
    }

    if (mq.addEventListener) {
        mq.addEventListener('change', onBreakpoint);
    } else {
        mq.addListener(onBreakpoint);
    }
}());
