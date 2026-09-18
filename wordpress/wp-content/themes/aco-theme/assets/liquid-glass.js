/**
 * Liquid Glass motion for public pages only.
 * Adds scroll reveals without touching forms or account logic.
 */
(function () {
    'use strict';

    var body = document.body;
    if (!body || body.classList.contains('page-template-page-account')) {
        return;
    }

    body.classList.add('lh-glass');

    // Auth form must stay visible — do not run scroll-reveal on it.
    if (body.classList.contains('aco-auth-page')) {
        return;
    }

    if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        return;
    }

    var selector = [
        '.lh-dir-card',
        '.lh-course-card',
        '.lh-course-card2',
        '.lh-blog-card',
        '.lh-faq-item',
        '.lh-accordion__item',
        '.lh-course-module',
        '.lh-contacts-form',
        '.lh-stat',
        '.lh-section-head',
        '.lh-cta__inner',
        '.lh-about-media',
        '.lh-catalog-filters',
        '.lh-course-hero__content',
        '.lh-course-hero__media'
    ].join(',');

    var nodes = document.querySelectorAll(selector);
    if (!nodes.length || !('IntersectionObserver' in window)) {
        return;
    }

    var observer = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            if (!entry.isIntersecting) {
                return;
            }
            entry.target.classList.add('is-in');
            observer.unobserve(entry.target);
        });
    }, { threshold: 0.12, rootMargin: '0px 0px -8% 0px' });

    nodes.forEach(function (node) {
        node.classList.add('lg-reveal');
        observer.observe(node);
    });

    window.setTimeout(function () {
        nodes.forEach(function (node) {
            node.classList.add('is-in');
        });
    }, 1200);
}());
