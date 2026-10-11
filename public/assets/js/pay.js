/*!
 * Payment page: opens Razorpay Checkout or Cashfree Checkout with server-provided config.
 * The server – not this script – decides whether an order is paid (signature + API verification).
 */
(function () {
    'use strict';
    var box = document.getElementById('pay-box');
    if (!box || !box.dataset.checkoutConfig) return;

    var cfg = JSON.parse(box.dataset.checkoutConfig);
    var status = box.querySelector('[data-pay-status]');
    var errEl = box.querySelector('[data-pay-error]');
    var btn = box.querySelector('[data-pay-now]');
    var csrf = (window.JM || {}).csrf;

    function say(msg) { status.textContent = msg; }
    function fail(msg) { errEl.textContent = msg; errEl.hidden = false; say('You can retry the payment below.'); btn.disabled = false; }

    function openRazorpay() {
        if (typeof window.Razorpay !== 'function') { fail('Payment window could not load. Check your connection and retry.'); return; }
        var opts = cfg.options;
        opts.handler = function (resp) {
            say('Verifying payment… please do not close this page.');
            btn.disabled = true;
            fetch(cfg.verify_url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                body: JSON.stringify(resp)
            }).then(function (r) { return r.json(); })
              .then(function (d) { if (d.redirect) location.href = d.redirect; else fail(d.message || 'Verification failed'); })
              .catch(function () { fail('Network error while verifying. If money was debited, your order will update automatically.'); });
        };
        opts.modal = { ondismiss: function () { say('Payment window closed.'); btn.disabled = false; }, confirm_close: true };
        var rzp = new window.Razorpay(opts);
        rzp.on('payment.failed', function (r) { fail((r.error && r.error.description) || 'Payment failed. Please try another method.'); });
        btn.disabled = true;
        say('Complete the payment in the secure window.');
        rzp.open();
    }

    function openCashfree() {
        if (typeof window.Cashfree !== 'function') { fail('Payment window could not load. Check your connection and retry.'); return; }
        var cf = window.Cashfree({ mode: cfg.options.mode });
        btn.disabled = true;
        say('Redirecting to secure payment…');
        cf.checkout({ paymentSessionId: cfg.options.paymentSessionId, redirectTarget: '_self' });
    }

    function open() { cfg.gateway === 'razorpay' ? openRazorpay() : openCashfree(); }
    btn.addEventListener('click', open);
    if (document.readyState === 'complete') open(); else window.addEventListener('load', open);
})();
