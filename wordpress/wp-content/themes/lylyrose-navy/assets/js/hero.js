(function () {
    var hero = document.querySelector('.dk-hero');
    if (!hero) { return; }
    var slides = hero.querySelectorAll('.dk-hero-slide');
    var dots = hero.querySelectorAll('.dk-hero-dots button, .dk-hero-dots i');
    var prevBtn = hero.querySelector('.dk-hero-prev');
    var nextBtn = hero.querySelector('.dk-hero-next');
    if (slides.length < 1) { return; }

    var index = 0;
    var timer = null;
    var INTERVAL = 5000;

    // Drag state. `active` is the element currently following the pointer (the
    // outgoing slide); the incoming one is simply the hidden one.
    var dragging = false;
    var moved = 0;
    var startX = 0;
    var startY = 0;
    var pointerId = null;
    var activeSlide = null;
    var width = 0;
    var direction = 1;
    var THRESHOLD = 40;

    function show(n) {
        index = (n + slides.length) % slides.length;
        for (var i = 0; i < slides.length; i++) {
            slides[i].classList.toggle('is-active', i === index);
        }
        for (var j = 0; j < dots.length; j++) {
            dots[j].classList.toggle('on', j === index);
        }
    }

    function play() {
        if (slides.length < 2 || timer) { return; }
        timer = setInterval(function () { show(index + 1); }, INTERVAL);
    }

    function stop() {
        clearInterval(timer);
        timer = null;
    }

    function go(delta) {
        stop();
        show(index + delta);
        play();
    }

    for (var d = 0; d < dots.length; d++) {
        (function (n) {
            dots[n].addEventListener('click', function () {
                stop();
                show(n);
                play();
            });
        })(d);
    }

    if (nextBtn) {
        nextBtn.addEventListener('click', function () { go(1); });
    }
    if (prevBtn) {
        prevBtn.addEventListener('click', function () { go(-1); });
    }

    // RTL: the document reads right-to-left, so a drag to the *left* moves
    // forward. getBoundingClientRect().left is unaffected by direction, so
    // compare against the page's computed direction rather than assuming.
    var rtl = false;
    try {
        rtl = getComputedStyle(document.documentElement).direction === 'rtl';
    } catch (e) { /* default to LTR */ }
    var forwardSign = rtl ? -1 : 1;

    function onDown(e) {
        // Let the nav buttons and the hero's own links handle their own clicks.
        if (e.target.closest && e.target.closest('a, button')) { return; }
        dragging = true;
        moved = 0;
        startX = e.clientX;
        startY = e.clientY;
        pointerId = e.pointerId;
        activeSlide = slides[index];
        width = hero.getBoundingClientRect().width || 1;
        hero.classList.add('is-dragging');
        if (activeSlide) { activeSlide.classList.add('is-dragging'); }
        stop();
    }

    function onMove(e) {
        if (!dragging || e.pointerId !== pointerId) { return; }
        var dx = e.clientX - startX;
        var dy = e.clientY - startY;
        // A mostly-vertical gesture belongs to the page scroll, not the carousel.
        if (!moved && Math.abs(dy) > Math.abs(dx)) {
            onUp(e);
            return;
        }
        moved = dx;
        if (activeSlide) {
            activeSlide.style.transform = 'translateX(' + dx + 'px)';
        }
    }

    function onUp(e) {
        if (!dragging || (e && e.pointerId !== pointerId)) { return; }
        dragging = false;
        hero.classList.remove('is-dragging');
        if (activeSlide) {
            activeSlide.classList.remove('is-dragging');
            activeSlide.style.transform = '';
        }
        if (Math.abs(moved) > THRESHOLD) {
            go(moved * forwardSign > 0 ? 1 : -1);
        } else {
            // Not far enough — settle back and resume autoplay.
            play();
        }
        activeSlide = null;
        moved = 0;
    }

    hero.addEventListener('pointerdown', onDown);
    hero.addEventListener('pointermove', onMove);
    hero.addEventListener('pointerup', onUp);
    hero.addEventListener('pointercancel', onUp);

    // A drag that ends on the hero's own link must not follow it.
    hero.addEventListener('click', function (e) {
        if (Math.abs(moved) > 4 && e.target.closest && e.target.closest('a')) {
            e.preventDefault();
        }
    }, true);

    // Never drag-select the hero's text.
    hero.addEventListener('selectstart', function (e) { e.preventDefault(); });

    document.addEventListener('keydown', function (e) {
        if (e.key !== 'ArrowLeft' && e.key !== 'ArrowRight') { return; }
        // Let the nav buttons and dots keep their native button-key behaviour.
        if (e.target && e.target.closest && e.target.closest('button, input, select, textarea')) { return; }
        go(e.key === 'ArrowRight' ? 1 : -1);
    });

    hero.addEventListener('mouseenter', stop);
    hero.addEventListener('mouseleave', play);

    document.addEventListener('visibilitychange', function () {
        if (document.hidden) { stop(); } else { play(); }
    });

    show(0);
    play();
})();
