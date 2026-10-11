// Responsive audit: every key page × 5 viewports, true mobile emulation, horizontal-overflow detection.
const puppeteer = require('puppeteer-core');
const fs = require('fs');
const BASE = process.env.BASE_URL || 'http://localhost:8000';
const OUT = __dirname + '/../../storage/cache/screens/responsive/';
fs.mkdirSync(OUT, { recursive: true });
const [, , ADMIN_EMAIL, ADMIN_PASS, ORDER_NO, ORDER_ID, CUSTOMER_ID] = process.argv;
const MAIL_LOG = __dirname + '/../../storage/logs/mail-' + new Date().toLocaleDateString('sv-SE') + '.log';
const sleep = ms => new Promise(r => setTimeout(r, ms));

const VIEWPORTS = [
  { name: '360', width: 360, height: 740, isMobile: true, hasTouch: true, deviceScaleFactor: 2 },
  { name: '390', width: 390, height: 844, isMobile: true, hasTouch: true, deviceScaleFactor: 2 },
  { name: '768', width: 768, height: 1024, isMobile: true, hasTouch: true, deviceScaleFactor: 2 },
  { name: '1024', width: 1024, height: 768 },
  { name: '1440', width: 1440, height: 900 },
];
const STORE = ['/', '/shop', '/c/national-teams', '/p/argentina-2026-home-jersey', '/search?q=brazil', '/cart', '/checkout', '/login', '/register',
  '/account', '/account/orders', `/account/orders/${ORDER_NO}`, '/account/addresses', '/wishlist', '/track-order', '/page/refund-policy', `/account/orders/${ORDER_NO}/invoice`];
const ADMIN = ['/admin', '/admin/orders', `/admin/orders/${ORDER_ID}`, '/admin/products', '/admin/products/1/edit', `/admin/customers/${CUSTOMER_ID}`, '/admin/settings', '/admin/team', '/admin/login'];
const SHOOT = new Set(['/', '/shop', '/p/argentina-2026-home-jersey', '/cart', '/checkout', `/account/orders/${ORDER_NO}`, '/admin', `/admin/orders/${ORDER_ID}`, '/admin/products/1/edit']);

async function overflow(page) {
  return page.evaluate(() => {
    const vw = document.documentElement.clientWidth;
    const sw = document.documentElement.scrollWidth;
    const offenders = [];
    if (sw > vw + 1) {
      for (const el of document.querySelectorAll('body *')) {
        const r = el.getBoundingClientRect();
        if (r.width === 0 || r.right <= vw + 1) continue;
        let p = el.parentElement, clipped = false;
        while (p && p !== document.body) {
          const s = getComputedStyle(p);
          if (/(auto|scroll|hidden|clip)/.test(s.overflowX)) { clipped = true; break; }
          p = p.parentElement;
        }
        if (!clipped && getComputedStyle(el).position !== 'fixed') offenders.push(`${el.tagName.toLowerCase()}.${String(el.className).split(' ')[0]} →${Math.round(r.right)}px`);
      }
    }
    return { vw, sw, offenders: offenders.slice(0, 5) };
  });
}

(async () => {
  const browser = await puppeteer.launch({ executablePath: process.env.BROWSER || 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe', headless: 'new' });
  const page = await browser.newPage();
  await page.setUserAgent('Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 Chrome/130 Mobile Safari/537.36');
  const errors = [];
  page.on('pageerror', e => errors.push(page.url() + ' :: ' + e.message));
  page.on('console', m => { if (m.type() === 'error' && !/favicon|ERR_|status of 4/.test(m.text())) errors.push(page.url() + ' :: ' + m.text().slice(0, 160)); });

  // customer session (the smoke-test customer) and a bag item for /cart & /checkout
  await page.goto(BASE + '/login', { waitUntil: 'networkidle2' });
  await page.type('input[name=email]', process.env.CUST_EMAIL);
  await page.type('input[name=password]', 'Football123');
  await Promise.all([page.waitForNavigation(), page.click('form[action="/login"] button[type=submit]')]);
  await page.goto(BASE + '/p/brazil-2026-home-jersey', { waitUntil: 'networkidle2' });
  await (await page.$$('.size input:not([disabled])'))[0].click();
  await page.click('#buy-form button[type=submit]');
  await sleep(1200);

  const results = [];
  async function run(paths, label) {
    for (const vp of VIEWPORTS) {
      await page.setViewport(vp);
      for (const p of paths) {
        let res;
        for (let t = 0; t < 3; t++) {
          try { res = await page.goto(BASE + p, { waitUntil: 'networkidle2', timeout: 30000 }); break; }
          catch (e) { console.log('retry', vp.name, p, e.message.slice(0, 60)); await sleep(800); }
        }
        if (!res) { results.push({ label, vp: vp.name, path: p, status: 0, ok: false, sw: 0, vw: 0, offenders: ['navigation failed'] }); continue; }
        await sleep(150);
        const o = await overflow(page);
        results.push({ label, vp: vp.name, path: p, status: res.status(), ok: o.sw <= o.vw + 1, sw: o.sw, vw: o.vw, offenders: o.offenders });
        if (SHOOT.has(p) && (vp.name === '390' || vp.name === '768')) {
          await page.screenshot({ path: `${OUT}${vp.name}${p.replace(/[^a-z0-9]+/gi, '_')}.png`, fullPage: vp.name === '390' });
        }
      }
    }
  }
  await run(STORE, 'store');

  // admin with 2FA
  await page.setViewport(VIEWPORTS[4]);
  await page.goto(BASE + '/admin/login', { waitUntil: 'networkidle2' });
  await page.type('input[name=email]', ADMIN_EMAIL);
  await page.type('input[name=password]', ADMIN_PASS);
  await Promise.all([page.waitForNavigation({ timeout: 60000 }), page.$eval('main form, body form', f => f.requestSubmit())]);
  let code = null;
  for (let i = 0; i < 20 && !code; i++) {
    await sleep(300);
    const m = [...fs.readFileSync(MAIL_LOG, 'utf8').matchAll(new RegExp('To: ' + ADMIN_EMAIL.replace(/[.+]/g, '\\$&') + ' \\| Your admin sign-in code: (\\d{6})', 'g'))];
    if (m.length) code = m[m.length - 1][1];
  }
  await page.type('input[name=code]', code);
  await Promise.all([page.waitForNavigation({ timeout: 60000 }), page.$eval('main form, body form', f => f.requestSubmit())]);
  await run(ADMIN.filter(p => p !== '/admin/login'), 'admin');

  const bad = results.filter(r => !r.ok || r.status >= 400);
  console.log(`checked ${results.length} page×viewport combinations`);
  console.log(`horizontal overflow / errors: ${bad.length}`);
  for (const b of bad) console.log(`  ✗ [${b.vp}] ${b.path} status=${b.status} scrollWidth=${b.sw} > ${b.vw}  ${b.offenders.join(', ')}`);
  console.log(`JS errors: ${errors.length}`);
  errors.slice(0, 10).forEach(e => console.log('  ' + e));
  await browser.close();
})().catch(e => { console.error(e); process.exit(1); });
