/**
 * ACO programme builder: add question rows and toggle quiz/homework fields.
 */
(function () {
    'use strict';

    document.addEventListener('click', function (e) {
        var add = e.target.closest('[data-add-q]');
        if (!add) {
            return;
        }
        var list = document.querySelector('[data-q-list]');
        var row = document.querySelector('[data-q-row]');
        if (!list || !row) {
            return;
        }
        var clone = row.cloneNode(true);
        clone.querySelectorAll('textarea, input[type="text"]').forEach(function (el) {
            el.value = '';
        });
        clone.querySelectorAll('select').forEach(function (el) {
            el.selectedIndex = 0;
        });
        list.appendChild(clone);
        toggleRow(clone);
    });

    document.addEventListener('change', function (e) {
        var type = e.target.closest('[data-q-type]');
        if (type) {
            toggleRow(type.closest('[data-q-row]'));
        }
    });

    document.querySelectorAll('[data-q-row]').forEach(toggleRow);

    function toggleRow(row) {
        if (!row) {
            return;
        }
        var sel = row.querySelector('[data-q-type]');
        var isMc = sel && sel.value === 'multichoice';
        var tf = row.querySelector('[data-q-tf]');
        var mc = row.querySelector('[data-q-mc]');
        if (tf) {
            tf.classList.toggle('is-hidden', isMc);
        }
        if (mc) {
            mc.classList.toggle('is-hidden', !isMc);
        }
    }
})();
