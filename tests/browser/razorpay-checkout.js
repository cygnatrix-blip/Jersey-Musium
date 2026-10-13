// Full Razorpay TEST-mode payment through the real Checkout UI (test card → mock bank "Success").
const puppeteer = require('puppeteer-core');
const BASE = process.env.BASE_URL || 'http://localhost:8000';
const OUT = __dirname + '/../../storage/cache/screens/';
require('fs').mkdirSync(OUT, { recursive: true });
const sleep = ms => new Promise(r => setTimeout(r, ms));

async function rzFrame(page) {
  for (let i = 0; i < 40; i++) {
    const f = page.frames().find(x => x.url().includes('api.razorpay.com/v1/checkout'));
    if (f) return f;
    await sleep(500);
  }
  throw new Error('Razorpay frame not found');
}
// Click the element whose visible text matches (walks text nodes; works with fixed-position UIs).
async function clickText(frame, re) {
  return frame.evaluate((src) => {
    const rx = new RegExp(src, 'i');
    const w = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
    let n;
    while ((n = w.nextNode())) {
      const el = n.parentElement;
      if (rx.test(n.textContent.trim()) && el.getClientRects().length) {
        (el.closest('button,[role=button],label,li,[tabindex]') || el).click();
        return true;
      }
    }
    return false;
  }, re.source);
}
async function clickSuccess(ctx) {
  return ctx.evaluate(() => {
    const b = [...document.querySelectorAll('button, input[type=submit], a')].find(e => /^\s*success\s*$/i.test(e.innerText || e.value || ''));
    if (b) { b.click(); return true; }
    return false;
  });
}

(async () => {
  const browser = await puppeteer.launch({ executablePath: process.env.BROWSER || 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe', headless: 'new', args: ['--window-size=1280,900'] });
  const page = await browser.newPage();
  await page.setViewport({ width: 1280, height: 900 });
  await page.setUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/130 Safari/537.36');
  page.on('pageerror', e => console.log('PAGEERROR', e.message));
  page.on('console', m => { if (m.type() === 'error' && !/favicon|Failed to load resource/.test(m.text())) console.log('CONSOLE', m.text().slice(0, 200)); });
  const pops = [];
  browser.on('targetcreated', async t => { try { const p = await t.page(); if (p) pops.push(p); } catch (e) {} });

  const email = `rzp${Date.now()}@example.com`;
  await page.goto(BASE + '/register', { waitUntil: 'networkidle2' });
  await page.type('input[name=name]', 'Razorpay Tester');
  await page.type('input[name=email]', email);
  await page.type('input[name=phone]', '9830012345');
  await page.type('input[name=password]', 'Football123');
  await page.type('input[name=password_confirmation]', 'Football123');
  await Promise.all([page.waitForNavigation(), page.click('form[action="/register"] button[type=submit]')]);
  // Email OTP verification (log mail driver → read the code from storage/logs/mail-YYYY-MM-DD.log)
  if (page.url().includes('/verify-email')) {
    const log = __dirname + '/../../storage/logs/mail-' + new Date().toLocaleDateString('sv-SE') + '.log';
    let code = null;
    for (let i = 0; i < 20 && !code; i++) {
      await sleep(300);
      const m = [...require('fs').readFileSync(log, 'utf8').matchAll(new RegExp(email.replace(/[.+]/g, '\\$&') + ' \\| Your Jersey Museum verification code: (\\d{6})', 'g'))];
      if (m.length) code = m[m.length - 1][1];
    }
    await page.screenshot({ path: OUT + 'verify-email.png' });
    await Promise.all([page.waitForNavigation(), page.type('input[name=code]', code)]); // auto-submits on 6th digit
    console.log('email verified:', !page.url().includes('/verify-email'));
  }
  await page.goto(BASE + '/p/brazil-2026-home-jersey', { waitUntil: 'networkidle2' });
  await (await page.$$('.size input:not([disabled])'))[0].click();
  await page.click('#buy-form button[type=submit]');
  await page.waitForFunction(() => document.querySelector('[data-cart-count]').textContent.trim() !== '');
  await page.goto(BASE + '/checkout', { waitUntil: 'networkidle2' });
  await page.type('input[name=line1]', '12 Stadium Road');
  await page.type('input[name=pincode]', '700001');
  await page.type('input[name=city]', 'Kolkata');
  await page.select('select[name=state]', 'West Bengal');
  await page.click('input[name=payment_method][value=razorpay]');
  await Promise.all([page.waitForNavigation(), page.click('[data-place-order]')]);
  const orderNo = page.url().split('/').pop();
  console.log('ORDER', orderNo);

  const f = await rzFrame(page);
  await sleep(3500);
  if (await f.$('input[name=contact]')) {
    const v = await f.$eval('input[name=contact]', el => el.value);
    if (!v) await f.type('input[name=contact]', '9830012345');
    console.log('contact continue:', await clickText(f, /^Continue$/));
    await sleep(2500);
  }
  await clickText(f, /^Cards$/);
  await sleep(1500);
  try { await f.waitForSelector('input[placeholder="Card Number"]', { timeout: 20000 }); }
  catch (e) { await page.screenshot({ path: OUT + "rzp-nocard.png" }); console.log("NOCARD:", (await f.evaluate(() => document.body.innerText)).slice(-400).replace(/\n+/g, " | ")); throw e; }
  for (let t = 0; t < 4; t++) {
    const el = await f.$('input[placeholder="Card Number"]');
    await el.click({ clickCount: 3 }); await el.press("Backspace");
    await el.type("5267318187975449", { delay: 90 });
    await sleep(600);
    const digits = (await el.evaluate(e => e.value)).replace(/D/g, "");
    console.log("card digits:", digits.length);
    if (digits.length === 16) break;
  }
  await f.type('input[placeholder="MM / YY"]', '1230', { delay: 25 });
  await f.type('input[placeholder="CVV"]', '123', { delay: 25 });
  await sleep(800);
  await page.screenshot({ path: OUT + 'rzp-3-card.png' });
  {
    let done = false;
    for (const b of await f.$$("button")) {
      const t = (await b.evaluate(e => e.innerText.trim()));
      const box = await b.boundingBox();
      if (/^(Continue|Pay)/.test(t) && box && box.width > 100 && box.y > 400) { await b.click(); done = true; console.log("clicked button:", t, Math.round(box.y)); break; }
    }
    if (!done) console.log("no visible continue button");
  }
  await sleep(3500);
  await page.screenshot({ path: OUT + 'rzp-4-after-card.png' });
  console.log('maybe later:', await clickText(f, /^(Maybe later|Skip|Continue without saving)$/));

  // Native OTP screen (test mode accepts any OTP)
  await sleep(1500);
  try { await f.waitForFunction(() => /Enter OTP/.test(document.body.innerText), { timeout: 20000 }); console.log('OTP screen up'); } catch (e) { console.log('no OTP screen'); }
  await sleep(1500);
  await page.screenshot({ path: OUT + 'rzp-otp.png' });
  let typedOtp = false;
  for (const fr of page.frames()) {
    try {
      const ins = await fr.evaluate(() => [...document.querySelectorAll('input')].filter(e => e.getClientRects().length).map(e => [e.type, e.name, e.id, e.placeholder, e.maxLength]));
      console.log('FRAME', fr.url().slice(0, 80), JSON.stringify(ins).slice(0, 300));
      const target = await fr.$('input[name*=otp i], input[id*=otp i], input[placeholder*=OTP i], input[autocomplete=one-time-code]');
      if (target) {
        await target.type('123456', { delay: 30 });
        typedOtp = true; console.log('typed OTP in', fr.url().slice(0, 60));
        let best = null, bestY = -1;
        for (const bt of await fr.$$('button')) {
          const t = await bt.evaluate(e => e.innerText.trim());
          const box = await bt.boundingBox();
          if (/^Continue$/i.test(t) && box && box.width > 60 && box.y > bestY) { best = bt; bestY = box.y; }
        }
        if (best) { await best.click(); console.log('otp submit at y=' + Math.round(bestY)); }
      }
    } catch (e) {}
  }
  const inputs = await f.evaluate(() => [...document.querySelectorAll('input')].filter(e => e.getClientRects().length).map(e => [e.type, e.name, e.id, e.placeholder, e.maxLength, e.autocomplete]));
  console.log('visible inputs:', JSON.stringify(inputs));
  let otp = null;
  for (const el of await f.$$('input')) {
    const info = await el.evaluate(e => ({ v: e.getClientRects().length > 0, ph: e.placeholder || '', name: e.name || '', type: e.type }));
    if (info.v && !/card|mm|cvv|contact|email/i.test(info.ph + info.name) && info.type !== 'checkbox' && info.type !== 'radio') { otp = el; break; }
  }
  if (otp && !typedOtp) {
    await otp.click();
    await otp.type('123456', { delay: 30 });
    await sleep(500);
    for (const b of await f.$$('button')) {
      const t = await b.evaluate(e => e.innerText.trim());
      const box = await b.boundingBox();
      if (/^(Continue|Submit|Pay)/.test(t) && box && box.width > 100) { await b.click(); console.log('otp submitted via', t); break; }
    }
  } else {
    if (!typedOtp) console.log('no otp input; trying bank page:', await clickText(f, /^Pay on bank/));
  }

  let clicked = false;
  for (let i = 0; i < 45 && !clicked; i++) {
    if (page.url().includes('/order/success')) { clicked = true; break; }
    await sleep(1000);
    for (const c of [...pops, ...page.frames()]) {
      try { if (await clickSuccess(c)) { clicked = true; console.log('clicked Success on', c.url().slice(0, 90)); break; } } catch (e) {}
    }
  }
  if (!clicked) {
    await page.screenshot({ path: OUT + 'rzp-5-stuck.png' });
    console.log('STUCK:', (await f.evaluate(() => document.body.innerText)).slice(-500).replace(/\n+/g, ' | '));
  }
  try { await page.waitForFunction(() => location.pathname.startsWith('/order/success'), { timeout: 60000 }); } catch (e) { console.log('no redirect', page.url()); }
  await sleep(1500);
  await page.screenshot({ path: OUT + 'rzp-6-final.png' });
  console.log('FINAL URL', page.url());
  console.log('FINAL TEXT', (await page.evaluate(() => document.querySelector('main').innerText)).slice(0, 260).replace(/\n+/g, ' | '));
  console.log('ORDER_NUMBER=' + orderNo);
  await browser.close();
})().catch(e => { console.error(e); process.exit(1); });
