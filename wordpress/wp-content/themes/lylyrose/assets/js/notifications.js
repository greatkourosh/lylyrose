/**
 * Notifications bell (theme lylyrose).
 * Progressive enhancement: without JS the badge still renders (server-side);
 * with JS the dropdown toggles, unread count refreshes, and mark-read works.
 */
(function () {
    'use strict';

    var wrap = document.querySelector('.dk-bell-wrap');
    if (!wrap) {
        return;
    }

    var btn = wrap.querySelector('.dk-bell-btn');
    var dropdown = wrap.querySelector('.dk-bell-dropdown');
    var badge = wrap.querySelector('.dk-bell-count');
    var list = wrap.querySelector('.dk-bell-list');
    var markAll = wrap.querySelector('.dk-bell-mark-all');
    var cfg = window.asc_notifications || {};
    var nonce = cfg.nonce || (wrap.getAttribute('data-nonce') || '');
    var ajaxUrl = cfg.ajaxUrl || '/wp-admin/admin-ajax.php';

    if (!btn || !dropdown) {
        return;
    }

    function setHidden(hidden) {
        dropdown.hidden = hidden;
        btn.setAttribute('aria-expanded', hidden ? 'false' : 'true');
    }

    function updateBadge(count) {
        if (!badge) {
            return;
        }
        if (count > 0 && parseInt(count, 10) > 0) {
            badge.textContent = faNum(count);
            badge.hidden = false;
        } else {
            badge.hidden = true;
        }
    }

    function faNum(n) {
        var digits = '۰۱۲۳۴۵۶۷۸۹';
        return String(n).replace(/\d/g, function (d) { return digits[Number(d)]; });
    }

    function post(action, data, cb, err) {
        var body = new URLSearchParams();
        body.append('action', action);
        body.append('nonce', nonce);
        if (data) {
            Object.keys(data).forEach(function (k) { body.append(k, data[k]); });
        }
        fetch(ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body
        }).then(function (r) { return r.json(); }).then(function (json) {
            if (json && json.success) { cb && cb(json.data || {}); }
            else { err && err(json); }
        }).catch(function () { err && err({}); });
    }

    function refreshCount() {
        post('asc_notifications_unread', null, function (data) {
            updateBadge(data.count || 0);
        });
    }

    btn.addEventListener('click', function (e) {
        e.stopPropagation();
        var opening = dropdown.hidden;
        setHidden(!opening);
        if (opening) {
            refreshCount();
        }
    });

    document.addEventListener('click', function (e) {
        if (!dropdown.hidden && !wrap.contains(e.target)) {
            setHidden(true);
        }
    });

    function markAll() {
        post('asc_notifications_mark_read', {}, function () {
            updateBadge(0);
            if (list) {
                var items = list.querySelectorAll('.dk-bell-item');
                for (var i = 0; i < items.length; i++) {
                    items[i].classList.remove('is-unread');
                }
            }
        });
    }

    if (markAll) {
        markAll.addEventListener('click', markAll);
    }

    if (list) {
        list.addEventListener('click', function (e) {
            var link = e.target.closest ? e.target.closest('a') : null;
            if (!link) {
                return;
            }
            var item = link.closest ? link.closest('.dk-bell-item') : null;
            if (!item || !item.classList.contains('is-unread')) {
                return;
            }
            // optimistic: mark read locally, fire-and-forget
            item.classList.remove('is-unread');
            var id = link.getAttribute('data-id');
            if (id) {
                post('asc_notifications_mark_read', { id: id });
            }
            refreshCount();
        });
    }

    // initial refresh so badge is accurate on page load
    refreshCount();
})();