(function () {
    'use strict';
    var cards = document.querySelectorAll('.dk-flash-card[data-end]');
    if (!cards.length) return;

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
})();
