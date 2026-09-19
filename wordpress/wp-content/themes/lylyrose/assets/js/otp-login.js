/**
 * OTP login flow for the My Account page (theme lylyrose).
 * Talks to lylyrose-core AJAX endpoints asc_otp_request / asc_otp_verify.
 */
(function () {
    'use strict';

    var i18n = window.dk_otp_i18n || {};

    var card = document.querySelector('.dk-account-login-card');
    if (!card) {
        return;
    }

    var form = card.querySelector('.dk-otp-form');
    if (!form) {
        return;
    }

    var stepMobile = form.querySelector('.dk-otp-step--mobile');
    var stepCode = form.querySelector('.dk-otp-step--code');
    var msg = form.querySelector('.dk-otp-msg');
    var resendBtn = form.querySelector('.dk-otp-resend');
    var mobileInput = form.querySelector('.dk-otp-mobile');
    var codeInput = form.querySelector('.dk-otp-code');

    var tabs = card.querySelectorAll('.dk-otp-tab');
    var panels = card.querySelectorAll('.dk-otp-panel');
    var nonce = card.getAttribute('data-otp-nonce') || '';
    var timer = null;
    var mobile = '';

    function showMsg(text, isError) {
        msg.textContent = text;
        msg.hidden = false;
        msg.classList.toggle('is-error', !!isError);
        msg.classList.toggle('is-ok', !isError);
    }

    function startResendCountdown() {
        var left = 60;
        resendBtn.hidden = false;
        resendBtn.disabled = true;
        clearInterval(timer);
        timer = setInterval(function () {
            left -= 1;
            if (left <= 0) {
                clearInterval(timer);
                resendBtn.disabled = false;
                resendBtn.textContent = i18n.resend;
            } else {
                resendBtn.textContent = i18n.resend + ' (' + left + ')';
            }
        }, 1000);
    }

    tabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            tabs.forEach(function (t) { t.classList.remove('is-active'); });
            tab.classList.add('is-active');
            panels.forEach(function (p) {
                p.hidden = p.getAttribute('data-panel') !== tab.getAttribute('data-tab');
            });
        });
    });

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        if (stepMobile.hidden) {
            verify();
        } else {
            request();
        }
    });

    resendBtn.addEventListener('click', function () {
        request(true);
    });

    form.querySelector('.dk-otp-back').addEventListener('click', function (e) {
        e.preventDefault();
        clearInterval(timer);
        stepCode.hidden = true;
        stepMobile.hidden = false;
        resendBtn.hidden = true;
        msg.hidden = true;
        mobileInput.focus();
    });

    function request(isResend) {
        mobile = mobileInput.value.trim();
        if (!/^09\d{9}$/.test(mobile)) {
            showMsg(i18n.invalidMobile, true);
            return;
        }
        msg.hidden = true;
        setLoading(true, isResend ? resendBtn : null);

        ajaxPost('asc_otp_request', { mobile: mobile }, function (ok, data) {
            setLoading(false, isResend ? resendBtn : null);
            if (!ok) {
                showMsg(data && data.message ? data.message : i18n.sendFailed, true);
                return;
            }
            card.querySelector('.dk-otp-masked').textContent = data.mobile || mobile;
            stepMobile.hidden = true;
            stepCode.hidden = false;
            msg.hidden = true;
            codeInput.focus();
            startResendCountdown();
        });
    }

    function verify() {
        var code = codeInput.value.trim();
        if (!/^\d{6}$/.test(code)) {
            showMsg(i18n.invalidCode, true);
            return;
        }
        msg.hidden = true;
        setLoading(true, null);

        ajaxPost('asc_otp_verify', { mobile: mobile, code: code }, function (ok, data) {
            if (!ok) {
                setLoading(false, null);
                showMsg(data && data.message ? data.message : i18n.verifyFailed, true);
                return;
            }
            showMsg(i18n.welcome, false);
            window.location.href = (data && data.redirect) || '/my-account/';
        });
    }

    function setLoading(state, btn) {
        var buttons = [form.querySelector('.dk-otp-send'), form.querySelector('.dk-otp-verify')];
        if (btn) { buttons.push(btn); }
        buttons.forEach(function (b) {
            if (b) {
                b.disabled = state;
            }
        });
    }

    function ajaxPost(action, data, cb) {
        var body = new URLSearchParams();
        body.append('action', action);
        body.append('nonce', nonce);
        Object.keys(data).forEach(function (k) { body.append(k, data[k]); });

        fetch('/wp-admin/admin-ajax.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            credentials: 'same-origin',
            body: body.toString()
        })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                cb(res.success === true, res.data || {});
            })
            .catch(function () {
                cb(false, { message: i18n.networkError });
            });
    }
})();
