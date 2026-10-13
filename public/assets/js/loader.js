/*!
 * Jersey Museum – "Kick-off" intro loader controller.
 * - Real load progress (min 2.6s so the impact moment always plays, max 6s)
 * - Turf/spark particle burst at the ball on impact & on every tap
 * - Pointer / touch parallax
 * - Skip button, Esc / Enter / Space, reduced-motion support
 * - Shows once per browser session (configurable from Admin → Settings)
 */
(function () {
    'use strict';

    var root = document.getElementById('jm-loader');
    if (!root) return;

    var html = document.documentElement;
    var reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var KEY = 'jm_intro_seen';

    // Already played this session (e.g. page served from cache) → remove instantly.
    try {
        if (root.dataset.frequency !== 'always' && sessionStorage.getItem(KEY)) {
            root.remove();
            return;
        }
        sessionStorage.setItem(KEY, '1');
    } catch (e) { /* storage blocked – just play */ }

    html.classList.add('jm-lock');

    var frame = document.getElementById('jm-frame');
    var canvas = document.getElementById('jm-fx');
    var bar = document.getElementById('jm-bar');
    var pct = document.getElementById('jm-pct');
    var kicksEl = document.getElementById('jm-kicks');
    var impact = document.getElementById('jm-impact');
    var ball = document.getElementById('jm-kickball');
    var ctx = canvas && canvas.getContext ? canvas.getContext('2d') : null;

    var BALL_X = 0.486, BALL_Y = 0.858;
    var IMPACT_AT = 1250, MIN_TIME = reduced ? 900 : 2600, MAX_TIME = 6000;
    var start = performance.now();
    var loaded = document.readyState === 'complete';
    var leaving = false, kicks = 0, particles = [], rafFx = 0;

    window.addEventListener('load', function () { loaded = true; });

    /* ---------------- progress ---------------- */
    var shown = 0;
    function tick(now) {
        if (leaving) return;
        var t = now - start;
        // Ease toward 90% while loading; snap to 100% once loaded and min time passed.
        var target = loaded ? Math.min(100, (t / MIN_TIME) * 100) : Math.min(90, (t / MAX_TIME) * 140);
        shown += (target - shown) * 0.12;
        var v = Math.max(0, Math.min(100, Math.round(shown)));
        bar.style.width = v + '%';
        pct.textContent = v + '%';
        if ((loaded && t >= MIN_TIME && v >= 99) || t >= MAX_TIME) {
            bar.style.width = '100%';
            pct.textContent = '100%';
            leave();
            return;
        }
        requestAnimationFrame(tick);
    }
    requestAnimationFrame(tick);

    /* ---------------- particles ---------------- */
    var dpr = Math.min(window.devicePixelRatio || 1, 2);
    function sizeCanvas() {
        if (!ctx) return;
        var r = canvas.getBoundingClientRect();
        canvas.width = Math.round(r.width * dpr);
        canvas.height = Math.round(r.height * dpr);
    }
    sizeCanvas();
    window.addEventListener('resize', sizeCanvas);

    var TURF = ['#3d7a2a', '#2e5f20', '#4f8f35', '#6b4a2b', '#4a3220'];
    var SPARK = ['#ffffff', '#ffe08a', '#ff2d55'];

    function burst(power) {
        if (!ctx || reduced) return;
        var w = canvas.width, h = canvas.height;
        var ox = w * BALL_X, oy = h * BALL_Y, s = w / 1000;
        var n = Math.round(70 * power);
        for (var i = 0; i < n; i++) {
            var spark = i % 3 === 0;
            var ang = -Math.PI / 2 + (Math.random() - 0.5) * Math.PI * (spark ? 1.9 : 1.3);
            var spd = (spark ? 9 : 5) * (0.4 + Math.random()) * s * power;
            particles.push({
                x: ox + (Math.random() - 0.5) * 30 * s,
                y: oy + (Math.random() - 0.5) * 10 * s,
                vx: Math.cos(ang) * spd,
                vy: Math.sin(ang) * spd,
                g: (spark ? 0.12 : 0.32) * s,
                r: (spark ? 1.2 + Math.random() * 1.6 : 2 + Math.random() * 4.5) * s,
                c: spark ? SPARK[i % SPARK.length] : TURF[i % TURF.length],
                life: 1,
                decay: spark ? 0.018 + Math.random() * 0.02 : 0.008 + Math.random() * 0.012,
                spark: spark,
                rot: Math.random() * Math.PI
            });
        }
        if (!rafFx) rafFx = requestAnimationFrame(drawFx);
    }

    function drawFx() {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        for (var i = particles.length - 1; i >= 0; i--) {
            var p = particles[i];
            p.vy += p.g; p.vx *= 0.985; p.x += p.vx; p.y += p.vy; p.life -= p.decay; p.rot += 0.1;
            if (p.life <= 0 || p.y > canvas.height + 20) { particles.splice(i, 1); continue; }
            ctx.globalAlpha = Math.max(0, p.life);
            if (p.spark) {
                ctx.globalCompositeOperation = 'lighter';
                ctx.strokeStyle = p.c; ctx.lineWidth = p.r; ctx.lineCap = 'round';
                ctx.beginPath(); ctx.moveTo(p.x, p.y); ctx.lineTo(p.x - p.vx * 2.2, p.y - p.vy * 2.2); ctx.stroke();
            } else {
                ctx.globalCompositeOperation = 'source-over';
                ctx.fillStyle = p.c;
                ctx.save(); ctx.translate(p.x, p.y); ctx.rotate(p.rot);
                ctx.fillRect(-p.r, -p.r * 0.6, p.r * 2, p.r * 1.2);
                ctx.restore();
            }
        }
        ctx.globalAlpha = 1; ctx.globalCompositeOperation = 'source-over';
        rafFx = particles.length ? requestAnimationFrame(drawFx) : 0;
    }

    // The scripted impact, synced with the CSS timeline.
    setTimeout(function () {
        burst(1.4); // (no vibration here: browsers only allow it after a user tap)
    }, Math.max(0, IMPACT_AT - (performance.now() - start)));

    /* ---------------- interaction ---------------- */
    function kick(e) {
        if (leaving) return;
        if (e) e.preventDefault();
        kicks++;
        kicksEl.textContent = kicks === 1 ? '1 kick' : kicks + ' kicks';
        burst(1);
        impact.classList.remove('is-kick');
        void impact.offsetWidth; // restart CSS animation
        impact.classList.add('is-kick');
        frame.style.animation = 'none';
        void frame.offsetWidth;
        frame.style.animation = 'jmShake .4s cubic-bezier(.36,.07,.19,.97)';
        if (navigator.vibrate) { try { navigator.vibrate(12); } catch (e2) {} }
    }
    // Tapping anywhere on the photo kicks (the pulsing ring over the ball is the visual cue).
    frame.addEventListener('pointerdown', kick);

    // Parallax (rAF-throttled, eased)
    var tx = 0, ty = 0, cx = 0, cy = 0, rafTilt = 0;
    function onMove(e) {
        var pt = e.touches ? e.touches[0] : e;
        tx = (pt.clientX / window.innerWidth - 0.5) * 2;
        ty = (pt.clientY / window.innerHeight - 0.5) * 2;
        if (!rafTilt) rafTilt = requestAnimationFrame(applyTilt);
    }
    function applyTilt() {
        cx += (tx - cx) * 0.18; cy += (ty - cy) * 0.18;
        root.style.setProperty('--mx', cx.toFixed(3));
        root.style.setProperty('--my', cy.toFixed(3));
        rafTilt = Math.abs(tx - cx) + Math.abs(ty - cy) > 0.002 ? requestAnimationFrame(applyTilt) : 0;
    }
    if (!reduced) {
        root.addEventListener('pointermove', onMove, { passive: true });
        root.addEventListener('touchmove', onMove, { passive: true });
    }

    document.getElementById('jm-skip').addEventListener('click', function (e) { e.stopPropagation(); leave(); });
    function onKey(e) {
        if (e.key === 'Escape' || e.key === 'Enter' || e.key === ' ') { e.preventDefault(); leave(); }
    }
    document.addEventListener('keydown', onKey);

    /* ---------------- exit ---------------- */
    function leave() {
        if (leaving) return;
        leaving = true;
        document.removeEventListener('keydown', onKey);

        // Put the vector ball exactly over the photo's ball, then kick it at the camera.
        var r = frame.getBoundingClientRect();
        root.style.setProperty('--kx', (r.left + r.width * BALL_X) + 'px');
        root.style.setProperty('--ky', (r.top + r.height * BALL_Y) + 'px');
        root.style.setProperty('--ks', Math.max(40, r.width * 0.105) + 'px');
        if (!reduced) burst(0.8);

        root.classList.add('is-leaving');
        setTimeout(function () {
            html.classList.remove('jm-lock');
            root.remove();
            document.dispatchEvent(new CustomEvent('jm:intro-done'));
        }, reduced ? 320 : 1120);
    }
})();
