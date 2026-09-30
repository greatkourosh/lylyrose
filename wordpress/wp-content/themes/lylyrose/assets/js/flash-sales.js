(function () {
    'use strict';

    // Countdown. No product here has a scheduled sale end, so this is normally
    // inert — it must not gate the carousel wiring below.
    var cards = document.querySelectorAll('.dk-flash-card[data-end]');
    if (cards.length) {
        function tick() {
            cards.forEach(function (card) {
                var timer = card.querySelector('.dk-flash-timer');
                var remaining = Math.max(0, Number(card.dataset.end) - Math.floor(Date.now() / 1000));
                if (!timer) return;
                if (!remaining) {
                    timer.textContent = 'پیشنهاد پایان یافت';
                    var prices = card.querySelector('.dk-offer-prices');
                    if (prices) prices.style.display = 'none';
                    return;
                }
                timer.textContent = [Math.floor(remaining / 3600), Math.floor(remaining % 3600 / 60), remaining % 60]
                    .map(function (part) { return String(part).padStart(2, '0'); }).join(':')
                    .replace(/\d/g, function (digit) { return '۰۱۲۳۴۵۶۷۸۹'[digit]; });
            });
        }
        tick();
        setInterval(tick, 1000);
    }

    // ---- Row carousels ----
    // RTL: the document reads right-to-left, so "forward" scrolls toward zero
    // scrollLeft. scrollLeft is measured from the left edge in both directions
    // (negative in RTL on Chromium), so the sign has to be read off the
    // computed direction rather than assumed — the same trap hero.js documents.
    var rtl = false;
    try {
        rtl = getComputedStyle(document.documentElement).direction === 'rtl';
    } catch (e) { /* default to LTR */ }

    document.querySelectorAll('[data-dk-row-track]').forEach(function (track) {
        var row = track.closest('.dk-flash-row');
        var prev = row && row.querySelector('[data-dk-row-prev]');
        var next = row && row.querySelector('[data-dk-row-next]');

        function step() {
            var card = track.querySelector('.dk-flash-card');
            var gap = parseFloat(getComputedStyle(track).columnGap || 0) || 0;
            return card ? card.getBoundingClientRect().width + gap : 200;
        }

        // The two ends of the scrollable range, and how far apart they are.
        // Neither number can be worked out: the track rests at its inline-start
        // padding rather than at 0, and the trailing padding inflates
        // scrollWidth past the distance the browser will actually let it travel,
        // so scrollWidth - clientWidth overstates the range by one padding. The
        // only trustworthy source is the browser's own clamp, so this asks for it
        // and puts the value back.
        //
        // Assigning scrollLeft aborts any smooth scroll still running, so this is
        // measured at setup and on resize and never from the scroll handler —
        // doing it there made the nav buttons twitch in place and never arrive.
        var limits = { start: 0, end: 0, max: 0 };

        function measure() {
            var was = track.scrollLeft;
            track.scrollLeft = 1e6;          // clamps to the forward end
            var fwd = track.scrollLeft;
            track.scrollLeft = -1e6;         // clamps to the back end
            var back = track.scrollLeft;
            track.scrollLeft = was;
            // RTL: the reading start is the LARGER scrollLeft, because the track
            // rests just short of 0 and travelling forward decreases it.
            limits = { start: rtl ? fwd : back, end: rtl ? back : fwd, max: Math.abs(fwd - back) };
        }

        // Normalised 0..1 distance from the reading start, so the buttons can be
        // disabled at whichever physical end that is. Measured as a real distance
        // from the known start rather than as a bare ratio of scrollLeft, which in
        // RTL would call the resting position negative progress and leave the
        // "prev" button permanently dead.
        function position() {
            if (!limits.max) { return 0; }
            var at = rtl ? limits.start - track.scrollLeft : track.scrollLeft - limits.start;
            return Math.min(1, Math.max(0, at / limits.max));
        }

        function sync() {
            if (!prev || !next) { return; }
            // A row whose cards all fit has no ends to travel to, so both
            // buttons go dead. position() reports 0 for it, which would
            // otherwise leave "next" live on a button that can do nothing.
            if (!limits.max) {
                prev.disabled = true;
                next.disabled = true;
                return;
            }
            var at = position();
            prev.disabled = at <= 0.01;
            next.disabled = at >= 0.99;
        }

        // scrollBy()'s `left` is in the same signed space as scrollLeft: negative
        // in RTL, which is also the reading direction. `sign` is expressed in
        // reading terms (+1 = toward the end of the list), so it has to be
        // flipped here or the button scrolls the row the wrong way — and in
        // RTL a wrong-way scroll saturates at the current end and appears to
        // do nothing at all.
        function scrollByCards(sign) {
            track.scrollBy({ left: (rtl ? -sign : sign) * step() * 2, behavior: 'smooth' });
        }

        if (prev) { prev.addEventListener('click', function () { scrollByCards(-1); }); }
        if (next) { next.addEventListener('click', function () { scrollByCards(1); }); }
        track.addEventListener('scroll', sync, { passive: true });
        window.addEventListener('resize', function () { measure(); sync(); });
        measure();
        sync();

        // Drag. Mirrors hero.js: a mostly-vertical gesture belongs to the page
        // scroll, and a drag that ends on a card link must not navigate.
        var dragging = false, moved = 0, startX = 0, startY = 0, startScroll = 0, pid = null;

        track.addEventListener('pointerdown', function (e) {
            if (e.button !== 0) { return; }
            dragging = true; moved = 0;
            startX = e.clientX; startY = e.clientY; pid = e.pointerId;
            startScroll = track.scrollLeft;
            track.classList.add('is-dragging');
        });

        track.addEventListener('pointermove', function (e) {
            if (!dragging || e.pointerId !== pid) { return; }
            var dx = e.clientX - startX, dy = e.clientY - startY;
            if (!moved && Math.abs(dy) > Math.abs(dx)) { dragging = false; track.classList.remove('is-dragging'); return; }
            moved = Math.max(moved, Math.abs(dx));
            // In RTL scrollLeft runs negative, so the delta is inverted.
            track.scrollLeft = startScroll + (rtl ? dx : -dx);
        });

        function endDrag() {
            if (!dragging) { return; }
            dragging = false;
            track.classList.remove('is-dragging');
        }
        track.addEventListener('pointerup', endDrag);
        track.addEventListener('pointercancel', endDrag);
        track.addEventListener('pointerleave', endDrag);

        // Suppress the click that follows a real drag, so releasing over a
        // product link does not navigate away from the carousel.
        track.addEventListener('click', function (e) {
            if (moved > 5) { e.preventDefault(); e.stopPropagation(); moved = 0; }
        }, true);
    });
})();
