/**
 * Perfume landing page behaviour: AJAX add-to-cart, brand search, the rail
 * arrows, and the search shortcut. Everything degrades to a working page
 * without it — the buy button's fallback is the product page link the
 * non-simple branch renders.
 */
(function () {
    'use strict';

    var toastEl = document.getElementById('pf-toast');
    var toastTimer = null;

    function toast(message) {
        if (!toastEl) { return; }
        toastEl.textContent = message;
        toastEl.classList.add('is-on');
        window.clearTimeout(toastTimer);
        toastTimer = window.setTimeout(function () {
            toastEl.classList.remove('is-on');
        }, 2600);
    }

    // Persian digits, both directions, without a lookup table.
    var FA = '۰۱۲۳۴۵۶۷۸۹';
    function toFa(n) {
        return String(n).replace(/\d/g, function (d) { return FA[+d]; });
    }
    function toEn(s) {
        return String(s).replace(/[۰-۹]/g, function (d) { return String(FA.indexOf(d)); });
    }

    function bumpCartBadge() {
        var badge = document.querySelector('.dk-cart-count');
        if (badge) {
            badge.textContent = toFa((parseInt(toEn(badge.textContent), 10) || 0) + 1);
            return;
        }
        var link = document.querySelector('.dk-cart-wrap .dk-header-action');
        if (!link) { return; }
        var fresh = document.createElement('i');
        fresh.className = 'dk-cart-count';
        fresh.textContent = toFa(1);
        link.appendChild(fresh);
    }

    function addToCart(button) {
        var cfg = window.lylyrosePerfume || {};
        var id = button.getAttribute('data-product-id');
        if (!id || !cfg.ajaxUrl) { return; }

        button.classList.add('is-loading');

        window.fetch(cfg.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            body: new URLSearchParams({ product_id: id, quantity: '1' }).toString()
        })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                button.classList.remove('is-loading');
                if (data && !data.error) {
                    button.classList.add('is-done');
                    toast(cfg.added || 'به سبد خرید اضافه شد.');
                    bumpCartBadge();
                    window.setTimeout(function () { button.classList.remove('is-done'); }, 1800);
                } else {
                    // WooCommerce sends product_url for a rejected add; following
                    // it shows the real reason instead of a generic failure.
                    if (data && data.product_url) { window.location.href = data.product_url; return; }
                    toast(cfg.failed || 'افزودن به سبد خرید ممکن نشد.');
                }
            })
            .catch(function () {
                button.classList.remove('is-loading');
                toast(cfg.failed || 'خطای شبکه. دوباره تلاش کنید.');
            });
    }

    document.addEventListener('click', function (event) {
        var add = event.target.closest && event.target.closest('[data-pf-add]');
        if (add) {
            event.preventDefault();
            addToCart(add);
            return;
        }
        var wish = event.target.closest && event.target.closest('.pf-wish');
        if (wish) {
            wish.classList.toggle('is-on');
        }
    });

    // Brand search narrows the rendered list rather than re-querying, so the
    // checkbox state the form will submit is never touched.
    var brandSearch = document.getElementById('pf-brand-search');
    var brandList = document.getElementById('pf-brand-list');
    if (brandSearch && brandList) {
        brandSearch.addEventListener('input', function () {
            var needle = brandSearch.value.trim().toLowerCase();
            Array.prototype.forEach.call(brandList.children, function (item) {
                var name = (item.getAttribute('data-pf-brand-name') || '').toLowerCase();
                item.style.display = (!needle || name.indexOf(needle) !== -1) ? '' : 'none';
            });
        });
    }

    var searchShortcut = document.querySelector('[data-pf-focus-search]');
    if (searchShortcut) {
        searchShortcut.addEventListener('click', function () {
            var input = document.querySelector('.dk-search-input');
            if (input) { window.setTimeout(function () { input.focus(); }, 60); }
        });
    }

    var sortSelect = document.getElementById('pf-sort-select');
    if (sortSelect) {
        sortSelect.addEventListener('change', function () {
            if (sortSelect.value) { window.location.href = sortSelect.value; }
        });
    }

    // Rail arrows step by two cards, measured from the first card so the step
    // matches whatever the breakpoint resolved the columns to.
    var track = document.querySelector('[data-pf-track]');
    if (track) {
        var step = function () {
            var card = track.querySelector('.pf-best-card');
            var gap = parseFloat(window.getComputedStyle(track).columnGap) || 0;
            return card ? (card.getBoundingClientRect().width + gap) * 2 : 240;
        };
        var prev = document.querySelector('.pf-rail-prev');
        var next = document.querySelector('.pf-rail-next');
        // In RTL, scrollLeft runs negative from 0, so the directions flip.
        var rtl = window.getComputedStyle(document.documentElement).direction === 'rtl';
        if (prev) { prev.addEventListener('click', function () { track.scrollLeft += (rtl ? 1 : -1) * step(); }); }
        if (next) { next.addEventListener('click', function () { track.scrollLeft -= (rtl ? 1 : -1) * step(); }); }
    }
})();
