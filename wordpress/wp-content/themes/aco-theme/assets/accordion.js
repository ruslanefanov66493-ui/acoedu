(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        const items = document.querySelectorAll('.lh-accordion__item');

        items.forEach(function (item) {
            const trigger = item.querySelector('.lh-accordion__trigger');

            trigger.addEventListener('click', function () {
                const isOpen = item.classList.contains('is-open');

                // Close all other items (optional — remove this block to allow multiple open)
                items.forEach(function (other) {
                    if (other !== item) {
                        other.classList.remove('is-open');
                        other.querySelector('.lh-accordion__trigger').setAttribute('aria-expanded', 'false');
                    }
                });

                // Toggle current item
                item.classList.toggle('is-open', !isOpen);
                trigger.setAttribute('aria-expanded', String(!isOpen));
            });
        });
    });
})();
