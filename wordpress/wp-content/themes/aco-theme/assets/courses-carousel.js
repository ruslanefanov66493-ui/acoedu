(function () {
    var root = document.querySelector("[data-courses-carousel]");
    if (!root) {
        return;
    }

    var viewport = root.querySelector(".lh-courses__viewport");
    var track = root.querySelector(".lh-courses__track");
    var prev = root.querySelector(".lh-courses__nav--prev");
    var next = root.querySelector(".lh-courses__nav--next");
    var dotsWrap = root.querySelector(".lh-courses__dots");
    var cards = track ? Array.prototype.slice.call(track.children) : [];

    if (!viewport || !track || cards.length === 0) {
        return;
    }

    var GAP = 22;
    var INTERVAL = 5500;
    var index = 0;
    var timer = null;
    var startX = 0;

    function visibleCount() {
        var width = viewport.clientWidth;
        if (width <= 768) {
            return 1;
        }
        if (width <= 1100) {
            return 2;
        }
        return 3;
    }

    function maxIndex() {
        return Math.max(0, cards.length - visibleCount());
    }

    function cardStep() {
        var rect = cards[0].getBoundingClientRect();
        return rect.width + GAP;
    }

    function updateDots() {
        if (!dotsWrap) {
            return;
        }
        var pages = maxIndex() + 1;
        dotsWrap.innerHTML = "";
        if (pages <= 1) {
            dotsWrap.hidden = true;
            return;
        }
        dotsWrap.hidden = false;
        for (var i = 0; i < pages; i++) {
            var btn = document.createElement("button");
            btn.type = "button";
            btn.className = "lh-courses__dot" + (i === index ? " is-active" : "");
            btn.setAttribute("aria-label", "Слайд " + (i + 1));
            btn.setAttribute("aria-current", i === index ? "true" : "false");
            btn.addEventListener("click", function (page) {
                return function () {
                    go(page);
                    restart();
                };
            }(i));
            dotsWrap.appendChild(btn);
        }
    }

    function updateNav() {
        var last = maxIndex();
        var canSlide = last > 0;
        if (prev) {
            prev.disabled = !canSlide;
            prev.setAttribute("aria-disabled", canSlide ? "false" : "true");
        }
        if (next) {
            next.disabled = !canSlide;
            next.setAttribute("aria-disabled", canSlide ? "false" : "true");
        }
        root.classList.toggle("is-static", !canSlide);
    }

    function go(nextIndex) {
        var last = maxIndex();
        if (last === 0) {
            index = 0;
        } else {
            index = ((nextIndex % (last + 1)) + (last + 1)) % (last + 1);
        }
        track.style.transform = "translateX(" + (-index * cardStep()) + "px)";
        updateNav();
        updateDots();
    }

    function stop() {
        if (timer) {
            clearInterval(timer);
            timer = null;
        }
    }

    function start() {
        stop();
        if (maxIndex() === 0) {
            return;
        }
        timer = setInterval(function () {
            go(index + 1);
        }, INTERVAL);
    }

    function restart() {
        stop();
        start();
    }

    if (prev) {
        prev.addEventListener("click", function () {
            go(index - 1);
            restart();
        });
    }
    if (next) {
        next.addEventListener("click", function () {
            go(index + 1);
            restart();
        });
    }

    root.addEventListener("mouseenter", stop);
    root.addEventListener("mouseleave", start);
    root.addEventListener("focusin", stop);
    root.addEventListener("focusout", function (event) {
        if (!root.contains(event.relatedTarget)) {
            start();
        }
    });

    viewport.addEventListener("touchstart", function (event) {
        startX = event.changedTouches[0].clientX;
        stop();
    }, { passive: true });

    viewport.addEventListener("touchend", function (event) {
        var dx = event.changedTouches[0].clientX - startX;
        if (Math.abs(dx) > 40) {
            go(index + (dx < 0 ? 1 : -1));
        }
        start();
    }, { passive: true });

    window.addEventListener("resize", function () {
        go(Math.min(index, maxIndex()));
    });

    go(0);
    start();
})();
