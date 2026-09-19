/**
 * Back-in-Stock Notifier (theme lylyrose).
 * Talks to lylyrose-core AJAX endpoint `asc_stock_subscribe`.
 */
(function () {
    'use strict';

    var i18n = window.dk_stock_i18n || {};

    var wrap = document.getElementById('dk-stock-notify');
    if (!wrap) { return; }

    var form = wrap.querySelector('.dk-stock-notify-form');
    if (!form) { return; }

    var msg     = wrap.querySelector('.dk-stock-notify-msg');
    var input   = wrap.querySelector('.dk-stock-notify-input');
    var btn     = wrap.querySelector('.dk-stock-notify-btn');
    var nonce   = wrap.getAttribute('data-nonce') || '';
    var product = wrap.getAttribute('data-product-id') || '';

    form.addEventListener('submit', function (e) {
        e.preventDefault();

        var phone = input ? input.value.trim() : '';
        if (input && !/^09\d{9}$/.test(phone)) {
            showMsg(i18n.invalidMobile, true);
            return;
        }

        btn.disabled = true;

        var body = new URLSearchParams();
        body.append('action', 'asc_stock_subscribe');
        body.append('nonce', nonce);
        body.append('product_id', product);
        if (phone) { body.append('phone', phone); }

        fetch('/wp-admin/admin-ajax.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            credentials: 'same-origin',
            body: body.toString()
        })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            btn.disabled = false;
            if (res.success) {
                form.hidden = true;
                showMsg(res.data.message || i18n.subscribed, false);
            } else {
                showMsg(res.data.message || i18n.subscribeFailed, true);
            }
        })
        .catch(function () {
            btn.disabled = false;
            showMsg(i18n.networkError, true);
        });
    });

    function showMsg(text, isError) {
        msg.textContent = text;
        msg.hidden = false;
        msg.classList.toggle('is-error', !!isError);
        msg.classList.toggle('is-ok', !isError);
    }
})();
