/* Header account dropdown toggle */
(function () {
    'use strict';

    var account = document.querySelector('.lh-account');
    if (!account) return;

    var trigger = account.querySelector('.lh-account__trigger');

    function close() {
        account.classList.remove('is-open');
        trigger.setAttribute('aria-expanded', 'false');
    }

    trigger.addEventListener('click', function (e) {
        e.stopPropagation();
        var isOpen = account.classList.toggle('is-open');
        trigger.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    });

    document.addEventListener('click', function (e) {
        if (!account.contains(e.target)) close();
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') close();
    });
}());
