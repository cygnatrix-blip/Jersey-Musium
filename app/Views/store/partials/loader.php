<?php
/**
 * "Kick-off" intro loader.
 * The still photo is split into two layers at the ball. Each player slides in from his
 * side; they meet on the ball, which sets off an impact (shockwave, turf burst, camera shake).
 * Then the ball is "kicked" at the camera to reveal the site.
 * Interactive: pointer/touch parallax, tap the ball to kick again, Skip / Esc to exit.
 * SEO-safe: decorative (aria-hidden), the page content is already in the DOM beneath it.
 */
$word1 = 'JERSEY';
$word2 = 'MUSEUM';
$i = 0;
?>
<div id="jm-loader" class="jm-loader" aria-hidden="true" data-frequency="<?= e(setting('loader_frequency')) ?>"
     style="--jm-img-sm:url('<?= e(asset('img/loader/kickoff-768.jpg')) ?>');--jm-img-lg:url('<?= e(asset('img/loader/kickoff.jpg')) ?>')">
    <div class="jm-backdrop"></div>
    <div class="jm-lights"><i></i><i></i><i></i><i></i></div>

    <div class="jm-copy">
        <p class="jm-kicker"><span class="jm-dot"></span> Kick-off in progress</p>
        <div class="jm-word" aria-label="Jersey Museum">
            <span class="jm-w"><?php foreach (str_split($word1) as $ch): ?><b style="--i:<?= $i++ ?>"><?= $ch ?></b><?php endforeach; ?></span>
            <span class="jm-w jm-w--accent"><?php foreach (str_split($word2) as $ch): ?><b style="--i:<?= $i++ ?>"><?= $ch ?></b><?php endforeach; ?></span>
        </div>
        <p class="jm-tag">Wear the legend.</p>
    </div>

    <div class="jm-frame" id="jm-frame">
        <div class="jm-tilt" id="jm-tilt">
            <div class="jm-half jm-half--l"></div>
            <div class="jm-half jm-half--r"></div>
            <div class="jm-seam"></div>
            <div class="jm-vignette"></div>
            <div class="jm-impact" id="jm-impact"><i></i><i></i><i></i></div>
            <canvas class="jm-fx" id="jm-fx"></canvas>
            <button type="button" class="jm-ballhit" id="jm-ballhit" tabindex="-1" aria-hidden="true"></button>
        </div>
    </div>

    <div class="jm-flash"></div>

    <div class="jm-hud">
        <div class="jm-progress"><span id="jm-bar"></span></div>
        <div class="jm-meta">
            <span id="jm-pct">0%</span>
            <span class="jm-hint">Tap the ball <span aria-hidden="true">⚽</span> to kick</span>
            <span id="jm-kicks" class="jm-kicks"></span>
        </div>
    </div>

    <button type="button" class="jm-skip" id="jm-skip" tabindex="0">Skip intro
        <svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
    </button>

    <svg class="jm-kickball" id="jm-kickball" viewBox="-50 -50 100 100" aria-hidden="true">
        <defs>
            <radialGradient id="jmBallShade" cx="35%" cy="30%" r="80%">
                <stop offset="0" stop-color="#ffffff"/><stop offset=".65" stop-color="#e8ecf1"/><stop offset="1" stop-color="#9aa3b2"/>
            </radialGradient>
            <clipPath id="jmBallClip"><circle r="48"/></clipPath>
        </defs>
        <circle r="48" fill="url(#jmBallShade)"/>
        <g clip-path="url(#jmBallClip)" fill="#0f1115" stroke="#0f1115" stroke-width="1.6" stroke-linejoin="round">
            <polygon points="0,-15 14.3,-4.6 8.8,12.1 -8.8,12.1 -14.3,-4.6"/>
            <polygon points="16.5,-22.7 11.4,-38.3 24.7,-48 38,-38.3 32.9,-22.7"/>
            <polygon points="26.6,8.7 39.9,-1 53.2,8.7 48.1,24.3 31.7,24.3"/>
            <polygon points="0,28 13.3,37.7 8.2,53.3 -8.2,53.3 -13.3,37.7"/>
            <polygon points="-26.6,8.7 -31.7,24.3 -48.1,24.3 -53.2,8.7 -39.9,-1"/>
            <polygon points="-16.5,-22.7 -32.9,-22.7 -38,-38.3 -24.7,-48 -11.4,-38.3"/>
            <g fill="none" stroke-width="1.4">
                <line x1="0" y1="-15" x2="0" y2="-31"/><line x1="14.3" y1="-4.6" x2="29.5" y2="-9.6"/>
                <line x1="8.8" y1="12.1" x2="18.2" y2="25.1"/><line x1="-8.8" y1="12.1" x2="-18.2" y2="25.1"/>
                <line x1="-14.3" y1="-4.6" x2="-29.5" y2="-9.6"/>
            </g>
        </g>
        <circle r="48" fill="none" stroke="#0f1115" stroke-opacity=".35" stroke-width="1"/>
    </svg>
</div>
