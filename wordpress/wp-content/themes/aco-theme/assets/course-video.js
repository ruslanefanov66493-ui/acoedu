(function () {
    'use strict';

    document.querySelectorAll('.lh-course-video--has-player').forEach(function (wrap) {
        var video = wrap.querySelector('video');
        var btn = wrap.querySelector('.lh-course-video__play');
        if (!video || !btn) return;

        btn.addEventListener('click', function () {
            wrap.classList.add('is-playing');
            video.setAttribute('controls', 'controls');
            video.play();
        });

        video.addEventListener('ended', function () {
            wrap.classList.remove('is-playing');
            video.removeAttribute('controls');
        });
    });
})();
