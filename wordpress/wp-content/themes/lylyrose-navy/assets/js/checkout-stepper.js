/**
 * 2-step checkout stepper (theme lylyrose).
 * Progressive enhancement: without JS both step cards render stacked.
 * With JS, step 2 is hidden on load and revealed on "ادامه به پرداخت".
 */
(function () {
    'use strict';

    var cs2 = document.querySelector('.dk-cs2');
    if (!cs2) {
        return;
    }

    var step1 = cs2.querySelector('.dk-checkout-step--1');
    var step2 = cs2.querySelector('.dk-checkout-step--2');
    var nextBtn = cs2.querySelector('.dk-step-next-btn');
    var backLink = cs2.querySelector('.dk-step-back-link');

    // JS present -> interactive mode; hide step 2 (no-JS fallback is raw HTML).
    cs2.classList.add('step2-ready');
    if (step2) {
        step2.classList.add('is-hidden');
    }

    function goToStep(n) {
        var hide = n === 2 ? step1 : step2;
        var show = n === 2 ? step2 : step1;
        if (hide) {
            hide.classList.add('is-hidden');
        }
        if (show) {
            show.classList.remove('is-hidden');
        }
        window.scrollTo(0, 0);
    }

    if (nextBtn) {
        nextBtn.addEventListener('click', function () {
            goToStep(2);
        });
    }
    if (backLink) {
        backLink.addEventListener('click', function (e) {
            e.preventDefault();
            goToStep(1);
        });
    }
})();
