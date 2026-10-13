/*!
 * Jersey Museum storefront – progressive enhancement (site works without JS).
 */
(function () {
    'use strict';

    var $ = function (s, r) { return (r || document).querySelector(s); };
    var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };
    var JM = window.JM || {};

    function api(url, opts) {
        opts = opts || {};
        opts.headers = Object.assign({ 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': JM.csrf }, opts.headers || {});
        opts.credentials = 'same-origin';
        return fetch(url, opts).then(function (r) {
            return r.json().catch(function () { return {}; }).then(function (d) { d._status = r.status; return d; });
        });
    }

    /* ---------- toasts ---------- */
    var toastBox = $('#toasts');
    function dismiss(t) { t.classList.add('is-hiding'); setTimeout(function () { t.remove(); }, 260); }
    function toast(msg, type, linkText, linkHref) {
        var t = document.createElement('div');
        t.className = 'toast toast--' + (type || 'info');
        t.textContent = msg;
        if (linkText) {
            var a = document.createElement('a');
            a.href = linkHref; a.textContent = linkText;
            a.style.cssText = 'margin-left:.5rem;font-weight:800;text-decoration:underline;color:inherit';
            t.appendChild(a);
        }
        var b = document.createElement('button'); b.type = 'button'; b.setAttribute('aria-label', 'Dismiss'); b.innerHTML = '&times;';
        t.appendChild(b);
        toastBox.appendChild(t);
        setTimeout(function () { dismiss(t); }, 4500);
    }
    JM.toast = toast;
    $$('.toast[data-autohide]').forEach(function (t) { setTimeout(function () { dismiss(t); }, 5000); });
    document.addEventListener('click', function (e) {
        var b = e.target.closest('.toast button');
        if (b) dismiss(b.parentElement);
    });

    /* ---------- drawers / mobile search ---------- */
    function openDrawer(d) { d.classList.add('is-open'); d.setAttribute('aria-hidden', 'false'); document.documentElement.style.overflow = 'hidden'; }
    function closeDrawer(d) { d.classList.remove('is-open'); d.setAttribute('aria-hidden', 'true'); document.documentElement.style.overflow = ''; }
    document.addEventListener('click', function (e) {
        var o = e.target.closest('[data-drawer-open]');
        if (o) { openDrawer(document.getElementById(o.dataset.drawerOpen)); return; }
        var c = e.target.closest('[data-drawer-close]');
        if (c) { closeDrawer(c.closest('.drawer')); return; }
        var t = e.target.closest('[data-toggle]');
        if (t) {
            var el = document.getElementById(t.dataset.toggle);
            el.classList.toggle('is-open');
            var inp = $('input', el); if (inp && el.classList.contains('is-open')) inp.focus();
        }
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') $$('.drawer.is-open').forEach(closeDrawer);
    });

    /* ---------- search suggestions ---------- */
    $$('form[data-suggest]').forEach(function (form) {
        var input = $('input[name=q]', form), box = $('.suggest', form), timer, active = -1, last = '';
        function render(d) {
            var html = '';
            if (d.teams && d.teams.length) {
                html += '<div class="suggest__label">Teams</div>';
                d.teams.forEach(function (t) { html += '<a href="' + t.url + '">' + esc(t.name) + '</a>'; });
            }
            if (d.products && d.products.length) {
                html += '<div class="suggest__label">Jerseys</div>';
                d.products.forEach(function (p) {
                    html += '<a href="' + p.url + '"><img src="' + p.image + '" alt="" width="36" height="45" loading="lazy"><span>' + esc(p.name) + '<small>' + esc(p.price) + '</small></span></a>';
                });
            }
            if (!html) html = '<div class="suggest__label">No matches – press Enter to search</div>';
            box.innerHTML = html; box.classList.add('is-open'); active = -1;
        }
        input.addEventListener('input', function () {
            clearTimeout(timer);
            var q = input.value.trim();
            if (q.length < 2) { box.classList.remove('is-open'); return; }
            timer = setTimeout(function () {
                if (q === last) return; last = q;
                api('/api/search-suggest?q=' + encodeURIComponent(q)).then(render).catch(function () {});
            }, 180);
        });
        input.addEventListener('keydown', function (e) {
            var links = $$('a', box);
            if (!box.classList.contains('is-open') || !links.length) return;
            if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                e.preventDefault();
                active = (active + (e.key === 'ArrowDown' ? 1 : -1) + links.length) % links.length;
                links.forEach(function (l, i) { l.classList.toggle('is-active', i === active); });
            } else if (e.key === 'Enter' && active >= 0) {
                e.preventDefault(); location.href = links[active].href;
            } else if (e.key === 'Escape') {
                box.classList.remove('is-open');
            }
        });
        document.addEventListener('click', function (e) { if (!form.contains(e.target)) box.classList.remove('is-open'); });
    });
    function esc(s) { var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }

    /* ---------- cart count ---------- */
    function setCount(n) {
        $$('[data-cart-count]').forEach(function (el) { el.dataset.cartCount = n; el.textContent = n > 0 ? n : ''; });
    }
    window.addEventListener('pageshow', function (e) {
        if (e.persisted) api('/api/cart/count').then(function (d) { setCount(d.count || 0); });
    });

    /* ---------- wishlist ---------- */
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-wishlist]');
        if (!btn) return;
        e.preventDefault();
        if (!JM.loggedIn) { location.href = '/login'; return; }
        btn.disabled = true;
        api('/wishlist/toggle', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ product_id: btn.dataset.wishlist }) })
            .then(function (d) {
                btn.disabled = false;
                if (d._status === 401) { location.href = '/login'; return; }
                $$('[data-wishlist="' + btn.dataset.wishlist + '"]').forEach(function (b) { b.classList.toggle('is-on', !!d.added); });
                if (btn.dataset.moveToWishlist) {
                    var f = document.createElement('form');
                    f.method = 'post'; f.action = '/cart/remove';
                    f.innerHTML = '<input type="hidden" name="_token" value="' + JM.csrf + '"><input type="hidden" name="item_id" value="' + btn.dataset.moveToWishlist + '">';
                    document.body.appendChild(f); f.submit();
                    return;
                }
                if (!d.added && btn.closest('[data-wishlist-page]')) { btn.closest('.pcard').remove(); }
                toast(d.message || 'Updated', 'success', d.added ? 'View' : null, '/wishlist');
            })
            .catch(function () { btn.disabled = false; toast('Something went wrong. Please try again.', 'error'); });
    });

    /* ---------- add to cart (PDP) ---------- */
    var buyForm = $('form[data-add-to-cart]');
    if (buyForm) {
        var sizeErr = $('#size-error');
        buyForm.addEventListener('change', function (e) {
            if (e.target.name === 'variant_id') sizeErr.classList.remove('is-visible');
        });
        buyForm.addEventListener('submit', function (e) {
            e.preventDefault();
            if (!$('input[name=variant_id]:checked', buyForm)) {
                sizeErr.classList.remove('is-visible'); void sizeErr.offsetWidth; sizeErr.classList.add('is-visible');
                $('.sizes', buyForm).scrollIntoView({ behavior: 'smooth', block: 'center' });
                return;
            }
            var btn = $('button[type=submit]', buyForm);
            btn.classList.add('is-loading');
            api('/cart/add', { method: 'POST', body: new FormData(buyForm) }).then(function (d) {
                btn.classList.remove('is-loading');
                if (d.ok) {
                    setCount(d.count);
                    btn.innerHTML = 'Go to bag &rarr;';
                    btn.type = 'button';
                    btn.onclick = function () { location.href = '/cart'; };
                    toast(d.message || 'Added to bag', 'success', 'View bag', '/cart');
                } else {
                    toast(d.message || 'Could not add to bag', 'error');
                }
            }).catch(function () { btn.classList.remove('is-loading'); buyForm.submit(); });
        });

        // live personalisation preview
        var nIn = $('[data-preview-name]', buyForm), numIn = $('[data-preview-num]', buyForm);
        if (nIn) {
            nIn.addEventListener('input', function () { $('[data-out-name]').textContent = (nIn.value || 'YOUR NAME').toUpperCase().slice(0, 12); });
            numIn.addEventListener('input', function () { numIn.value = numIn.value.replace(/\D/g, '').slice(0, 2); $('[data-out-num]').textContent = numIn.value || '10'; });
        }
    }

    /* ---------- PDP gallery + zoom ---------- */
    var gallery = $('[data-gallery]');
    if (gallery) {
        var main = $('[data-main]', gallery), zoom = $('[data-zoom]', gallery);
        $$('.gallery__thumbs button', gallery).forEach(function (b) {
            b.addEventListener('click', function () {
                main.src = b.dataset.src;
                $$('.gallery__thumbs button', gallery).forEach(function (x) { x.classList.toggle('is-active', x === b); });
            });
        });
        if (window.matchMedia('(hover: hover)').matches) {
            zoom.addEventListener('mousemove', function (e) {
                var r = zoom.getBoundingClientRect();
                zoom.style.setProperty('--zx', ((e.clientX - r.left) / r.width * 100) + '%');
                zoom.style.setProperty('--zy', ((e.clientY - r.top) / r.height * 100) + '%');
            });
            zoom.addEventListener('click', function () { zoom.classList.toggle('is-zoom'); });
            zoom.addEventListener('mouseleave', function () { zoom.classList.remove('is-zoom'); });
        }
    }
    var sc = $('[data-open-sizechart]');
    if (sc) sc.addEventListener('click', function () { var d = $('#sizechart'); d.open = true; d.scrollIntoView({ behavior: 'smooth', block: 'center' }); });

    /* ---------- pincode ---------- */
    var pin = $('form[data-pincode]');
    if (pin) pin.addEventListener('submit', function (e) {
        e.preventDefault();
        var out = $('[data-pincode-result]'), v = $('input', pin).value.trim();
        api('/api/pincode?pincode=' + encodeURIComponent(v)).then(function (d) {
            out.className = 'pin-result ' + (d.ok ? 'ok' : 'err');
            out.textContent = d.message + (d.ok && d.cod ? ' · COD available' : '');
        });
    });

    /* ---------- filters & sort ---------- */
    $$('form[data-autosubmit]').forEach(function (f) {
        f.addEventListener('change', function () {
            if (window.matchMedia('(min-width: 1024px)').matches) f.submit();
        });
    });
    document.addEventListener('change', function (e) {
        if (e.target.matches('[data-autosubmit-select]')) e.target.form.submit();
    });
    var ft = $('[data-filters-toggle]'), filters = $('#filters');
    if (ft && filters) {
        var bar = document.createElement('div');
        bar.className = 'filters__mbar';
        bar.innerHTML = '<button type="button" class="btn btn--outline" data-close-filters>Close</button><button type="submit" class="btn">Apply</button>';
        filters.appendChild(bar);
        ft.addEventListener('click', function () { filters.classList.add('is-open'); document.documentElement.style.overflow = 'hidden'; });
        bar.firstChild.addEventListener('click', function () { filters.classList.remove('is-open'); document.documentElement.style.overflow = ''; });
    }

    /* ---------- hero slider ---------- */
    $$('[data-slider]').forEach(function (s) {
        var track = $('.hero__track', s), slides = track.children, dots = $$('.hero__dots button', s), i = 0, timer, x0 = null;
        if (slides.length < 2) return;
        function go(n) {
            i = (n + slides.length) % slides.length;
            track.style.transform = 'translateX(' + (-100 * i) + '%)';
            dots.forEach(function (d, k) { d.classList.toggle('is-active', k === i); });
        }
        function play() { stop(); timer = setInterval(function () { go(i + 1); }, 5000); }
        function stop() { clearInterval(timer); }
        dots.forEach(function (d, k) { d.addEventListener('click', function () { go(k); play(); }); });
        $$('[data-slide]', s).forEach(function (b) { b.addEventListener('click', function () { go(i + parseInt(b.dataset.slide, 10)); play(); }); });
        s.addEventListener('mouseenter', stop); s.addEventListener('mouseleave', play);
        s.addEventListener('touchstart', function (e) { x0 = e.touches[0].clientX; stop(); }, { passive: true });
        s.addEventListener('touchend', function (e) {
            if (x0 === null) return;
            var dx = e.changedTouches[0].clientX - x0;
            if (Math.abs(dx) > 40) go(i + (dx < 0 ? 1 : -1));
            x0 = null; play();
        });
        document.addEventListener('visibilitychange', function () { document.hidden ? stop() : play(); });
        go(0); play();
    });

    /* ---------- rails ---------- */
    document.addEventListener('click', function (e) {
        var b = e.target.closest('[data-rail]');
        if (!b) return;
        var t = $('.rail__track', b.parentElement);
        t.scrollBy({ left: t.clientWidth * 0.85 * parseInt(b.dataset.rail, 10), behavior: 'smooth' });
    });

    /* ---------- confirmations & double-submit guard ---------- */
    document.addEventListener('submit', function (e) {
        var f = e.target;
        if (f.dataset.confirm && !window.confirm(f.dataset.confirm)) { e.preventDefault(); return; }
        if (f.id === 'checkout-form') {
            var b = $('[data-place-order]');
            if (b) { b.classList.add('is-loading'); b.textContent = 'Placing order…'; }
        }
    });

    /* ---------- checkout ---------- */
    var co = $('form[data-checkout]');
    if (co) {
        var newAddr = $('#new-address');
        co.addEventListener('change', function (e) {
            if (e.target.name === 'address_id') newAddr.hidden = e.target.value !== '0';
            if (e.target.name === 'payment_method') $('[data-cod-note]').hidden = e.target.value !== 'cod';
        });
        var pm = $('input[name=payment_method]:checked', co);
        if (pm) $('[data-cod-note]').hidden = pm.value !== 'cod';
    }

    /* ---------- return form ---------- */
    var rt = $('[data-return-type]');
    if (rt) rt.addEventListener('change', function () { $('[data-exchange-size]').hidden = rt.value !== 'exchange'; });


    /* ---------- OTP: digits only, auto-submit, resend countdown ---------- */
    $$('.otp-input').forEach(function (inp) {
        inp.addEventListener('input', function () {
            inp.value = inp.value.replace(/\D/g, '').slice(0, 6);
            if (inp.value.length === 6) inp.form.requestSubmit();
        });
    });
    $$('[data-countdown]').forEach(function (b) {
        var left = parseInt(b.dataset.countdown, 10) || 0, label = b.textContent;
        if (left <= 0) return;
        b.disabled = true;
        var t = setInterval(function () {
            left--;
            b.textContent = left > 0 ? label + ' in ' + left + 's' : label;
            if (left <= 0) { clearInterval(t); b.disabled = false; }
        }, 1000);
        b.textContent = label + ' in ' + left + 's';
    });
    var lo = $('[data-logout]');
    if (lo) lo.addEventListener('click', function (e) { e.preventDefault(); $('#logout-form').submit(); });

    /* ---------- reveal on scroll ---------- */
    var rev = $$('[data-reveal]');
    if ('IntersectionObserver' in window) {
        var io = new IntersectionObserver(function (entries) {
            entries.forEach(function (en) { if (en.isIntersecting) { en.target.classList.add('is-in'); io.unobserve(en.target); } });
        }, { rootMargin: '0px 0px -8% 0px' });
        rev.forEach(function (el) { io.observe(el); });
    } else {
        rev.forEach(function (el) { el.classList.add('is-in'); });
    }
})();
