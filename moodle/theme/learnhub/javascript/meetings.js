/**
 * Meeting planner: course switch and select-all.
 */
(function () {
    'use strict';

    var course = document.getElementById('lh-meet-course');
    if (course) {
        course.addEventListener('change', function () {
            var url = new URL(window.location.href);
            url.searchParams.set('id', course.value);
            url.searchParams.delete('cmid');
            window.location = url.toString();
        });
    }

    var all = document.getElementById('lh-meet-all');
    var boxes = function () {
        return document.querySelectorAll('.lh-meet-student');
    };
    if (all) {
        all.addEventListener('change', function () {
            boxes().forEach(function (el) {
                el.checked = all.checked;
            });
        });
        document.addEventListener('change', function (event) {
            if (!event.target.classList || !event.target.classList.contains('lh-meet-student')) {
                return;
            }
            var list = boxes();
            var checked = 0;
            list.forEach(function (el) {
                if (el.checked) {
                    checked++;
                }
            });
            all.checked = list.length > 0 && checked === list.length;
        });
    }

    document.addEventListener('submit', function (event) {
        var btn = event.submitter;
        if (btn && btn.dataset.lhConfirm && !window.confirm(btn.dataset.lhConfirm)) {
            event.preventDefault();
        }
    });
}());
