/**
 * Perfume landing page behaviour: AJAX add-to-cart, auto-applying filters,
 * collapsible facet groups, brand search/show-more, the mobile filter drawer,
 * the rail arrows, and the search shortcut.
 *
 * Everything degrades to a working page without JS: the filters are a plain GET
 * form with a <noscript> apply button, the family tiles are links, and a
 * non-simple product renders a link to its page.
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
            var on = wish.classList.toggle('is-on');
            wish.setAttribute('aria-pressed', on ? 'true' : 'false');
        }
    });

    /* ---------------------------------------------------------------------
       Filters: apply on change, collapse groups, search / expand brands.
       --------------------------------------------------------------------- */
    var filterForm = document.getElementById('pf-filter-form');
    if (filterForm) {
        filterForm.addEventListener('change', function (event) {
            var target = event.target;
            if (!target || !target.matches) { return; }
            if (target.matches('input[type="checkbox"], input[type="radio"]')) {
                filterForm.submit();
            }
        });
    }

    Array.prototype.forEach.call(document.querySelectorAll('.pf-filter-group-title'), function (button) {
        var panel = document.getElementById(button.getAttribute('aria-controls'));
        if (!panel) { return; }
        button.addEventListener('click', function () {
            var open = button.getAttribute('aria-expanded') === 'true';
            button.setAttribute('aria-expanded', open ? 'false' : 'true');
            panel.hidden = open;
        });
    });

    var brandSearch = document.getElementById('pf-brand-search');
    var brandList = document.getElementById('pf-brand-list');
    var brandMore = document.querySelector('[data-pf-brand-more]');

    if (brandSearch && brandList) {
        brandSearch.addEventListener('keydown', function (event) {
            if (event.key === 'Enter') { event.preventDefault(); }
        });
        brandSearch.addEventListener('input', function () {
            var needle = brandSearch.value.trim().toLowerCase();
            Array.prototype.forEach.call(brandList.querySelectorAll('.pf-brand-row'), function (row) {
                var name = (row.getAttribute('data-pf-brand-name') || '').toLowerCase();
                if (!needle) {
                    row.style.display = '';
                } else {
                    row.style.display = name.indexOf(needle) !== -1 ? 'flex' : 'none';
                }
            });
        });
    }

    if (brandMore && brandList) {
        brandMore.addEventListener('click', function () {
            var expanded = brandList.classList.toggle('is-expanded');
            brandMore.setAttribute('aria-expanded', expanded ? 'true' : 'false');
            if (brandMore.firstChild && brandMore.firstChild.nodeType === 3) {
                brandMore.firstChild.nodeValue = expanded ? 'نمایش کمتر ' : 'نمایش همه برندها ';
            }
        });
    }

    /* ---------------------------------------------------------------------
       Mobile filter drawer.
       --------------------------------------------------------------------- */
    var filters = document.getElementById('pf-filters');
    var overlay = document.querySelector('.pf-filter-overlay');
    var trigger = document.querySelector('.pf-filter-trigger');
    var drawerReturnFocus = null;

    function isMobileFilters() {
        return window.matchMedia('(max-width: 900px)').matches;
    }

    function openFilters() {
        if (!filters || !isMobileFilters()) { return; }
        drawerReturnFocus = document.activeElement;
        filters.classList.add('is-open');
        if (overlay) { overlay.hidden = false; }
        document.body.classList.add('pf-lock');
        if (trigger) { trigger.setAttribute('aria-expanded', 'true'); }
        var close = filters.querySelector('.pf-filter-close');
        if (close) { window.setTimeout(function () { close.focus(); }, 40); }
    }

    function closeFilters() {
        if (!filters || !filters.classList.contains('is-open')) { return; }
        filters.classList.remove('is-open');
        if (overlay) { overlay.hidden = true; }
        document.body.classList.remove('pf-lock');
        if (trigger) { trigger.setAttribute('aria-expanded', 'false'); }
        if (drawerReturnFocus && drawerReturnFocus.focus) { drawerReturnFocus.focus(); }
    }

    Array.prototype.forEach.call(document.querySelectorAll('[data-pf-open-filters]'), function (opener) {
        opener.addEventListener('click', function (event) {
            if (!isMobileFilters()) { return; }
            event.preventDefault();
            openFilters();
        });
    });

    Array.prototype.forEach.call(document.querySelectorAll('[data-pf-close-filters]'), function (closer) {
        closer.addEventListener('click', function (event) {
            event.preventDefault();
            closeFilters();
        });
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') { closeFilters(); }
    });

    if (filters) {
        filters.addEventListener('keydown', function (event) {
            if (event.key !== 'Tab' || !filters.classList.contains('is-open')) { return; }
            var focusable = filters.querySelectorAll('a[href], button:not([disabled]), input:not([disabled]), select, [tabindex]:not([tabindex="-1"])');
            var visible = Array.prototype.filter.call(focusable, function (el) { return el.offsetParent !== null; });
            if (!visible.length) { return; }
            var first = visible[0];
            var last = visible[visible.length - 1];
            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        });
    }

    window.addEventListener('resize', function () {
        if (!isMobileFilters()) { closeFilters(); }
    });

    /* ---------------------------------------------------------------------
       Sort, search shortcut, best-seller rail.
       --------------------------------------------------------------------- */
    var sortSelect = document.getElementById('pf-sort-select');
    if (sortSelect) {
        sortSelect.addEventListener('change', function () {
            if (sortSelect.value) { window.location.href = sortSelect.value; }
        });
    }

    var searchShortcut = document.querySelector('[data-pf-focus-search]');
    if (searchShortcut) {
        searchShortcut.addEventListener('click', function (event) {
            var input = document.querySelector('.dk-search-input');
            if (!input) { return; }
            event.preventDefault();
            input.focus();
            input.scrollIntoView({ behavior: 'smooth', block: 'center' });
        });
    }

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
