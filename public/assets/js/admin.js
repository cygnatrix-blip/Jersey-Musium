/*! Jersey Musium admin – small enhancements */
(function () {
    'use strict';
    var $ = function (s, r) { return (r || document).querySelector(s); };
    var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };

    // Mobile sidebar
    var side = $('#side'), scrim = $('#scrim'), mb = $('#menu-btn');
    if (mb) mb.addEventListener('click', function () { side.classList.add('is-open'); scrim.classList.add('is-open'); });
    if (scrim) scrim.addEventListener('click', function () { side.classList.remove('is-open'); scrim.classList.remove('is-open'); });

    // Confirm dangerous actions
    document.addEventListener('submit', function (e) {
        var f = e.target;
        if (f.dataset.confirm && !window.confirm(f.dataset.confirm)) { e.preventDefault(); return; }
        var b = f.querySelector('button[type=submit]:not([data-no-lock])');
        if (b) setTimeout(function () { b.disabled = true; }, 0); // prevent double submit
    });

    // Auto-slug from name
    var nameIn = $('[data-slug-source]'), slugIn = $('[data-slug-target]');
    if (nameIn && slugIn) {
        var touched = slugIn.value !== '';
        slugIn.addEventListener('input', function () { touched = true; });
        nameIn.addEventListener('input', function () {
            if (!touched) slugIn.value = nameIn.value.toLowerCase().normalize('NFKD').replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
        });
    }

    // Image previews for file inputs
    $$('input[type=file][data-preview]').forEach(function (inp) {
        var box = document.getElementById(inp.dataset.preview);
        inp.addEventListener('change', function () {
            box.innerHTML = '';
            Array.prototype.forEach.call(inp.files, function (f) {
                var fig = document.createElement('figure'), img = document.createElement('img');
                img.src = URL.createObjectURL(f); img.alt = '';
                fig.appendChild(img); box.appendChild(fig);
            });
        });
    });

    // Variant rows (product form)
    var addVar = $('[data-add-variant]');
    if (addVar) addVar.addEventListener('click', function () {
        var tpl = $('#variant-template');
        $('#variants').insertAdjacentHTML('beforeend', tpl.innerHTML.replace(/__i__/g, Date.now()));
    });
    document.addEventListener('click', function (e) {
        var rm = e.target.closest('[data-remove-row]');
        if (rm) rm.closest('.variant-row').remove();
    });

    // Bar chart tooltips (dashboard)
    $$('.chart').forEach(function (chart) {
        var tip = $('.chart-tip', chart);
        $$('.hit', chart).forEach(function (h) {
            function show() {
                var r = chart.getBoundingClientRect(), b = h.getBoundingClientRect(), bar = h.nextElementSibling;
                tip.innerHTML = '<strong>' + h.dataset.value + '</strong>' + h.dataset.label + ' · ' + h.dataset.orders + ' orders';
                var barRect = bar.getBoundingClientRect();
                tip.style.left = (b.left - r.left + b.width / 2) + 'px';
                tip.style.top = (barRect.top - r.top - 4) + 'px';
                tip.classList.add('is-on'); bar.classList.add('is-hover');
            }
            function hide() { tip.classList.remove('is-on'); h.nextElementSibling.classList.remove('is-hover'); }
            h.addEventListener('mouseenter', show); h.addEventListener('focus', show);
            h.addEventListener('mouseleave', hide); h.addEventListener('blur', hide);
        });
    });

    // Order status form: show shipment fields when relevant
    var refundMode = $('[data-refund-mode]');
    if (refundMode) refundMode.addEventListener('change', function () { $('[data-manual-ref]').hidden = refundMode.value !== 'manual'; });
})();

// Confirm before buttons that submit to a different action (formaction)
document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-confirm-click]');
    if (b && !window.confirm(b.dataset.confirmClick)) e.preventDefault();
});
