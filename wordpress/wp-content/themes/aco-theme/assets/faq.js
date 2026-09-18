(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        const tabs = document.querySelectorAll('.lh-faq-tab');
        const items = document.querySelectorAll('.lh-faq-item');

        if (!tabs.length || !items.length) {
            return;
        }

        // Filter FAQ items by category
        tabs.forEach(function (tab) {
            tab.addEventListener('click', function () {
                const filter = tab.getAttribute('data-filter');

                tabs.forEach(function (t) {
                    t.classList.remove('is-active');
                    t.setAttribute('aria-selected', 'false');
                });
                tab.classList.add('is-active');
                tab.setAttribute('aria-selected', 'true');

                items.forEach(function (item) {
                    const category = item.getAttribute('data-category');
                    const match = filter === 'all' || category === filter;
                    item.classList.toggle('is-hidden', !match);

                    // Close opened items when switching tabs
                    if (!match) {
                        item.classList.remove('is-open');
                        const trigger = item.querySelector('.lh-faq-trigger');
                        if (trigger) {
                            trigger.setAttribute('aria-expanded', 'false');
                        }
                    }
                });
            });
        });

        // Accordion toggle
        items.forEach(function (item) {
            const trigger = item.querySelector('.lh-faq-trigger');
            if (!trigger) {
                return;
            }

            trigger.addEventListener('click', function () {
                const isOpen = item.classList.contains('is-open');

                item.classList.toggle('is-open', !isOpen);
                trigger.setAttribute('aria-expanded', String(!isOpen));
            });
        });
    });
})();
