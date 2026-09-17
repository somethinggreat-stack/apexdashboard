// Server load, session lifetime, and the "file sent but only the name/text shows" repair.
const path = require('path');
const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright-core');
const B = process.env.TEST_BASE_URL || 'http://127.0.0.1:8932';
if (!['127.0.0.1', 'localhost'].includes(new URL(B).hostname)) throw Error('Tests require a local disposable server');
const CHAT = B + '/admin/team-messages?standalone=1';
const UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/125 Safari/537.36 ApexDesktop/1.0';
const sleep = ms => new Promise(r => setTimeout(r, ms));
const results = [];
const only = process.argv[2] ? process.argv[2].split(',') : null;
const want = id => !only || only.includes(id);
function check(name, ok, detail) { results.push({ name, ok: !!ok }); console.log((ok ? 'PASS ' : 'FAIL ') + name + (detail !== undefined ? '  ' + JSON.stringify(detail) : '')); }

async function login(browser, email) {
  const ctx = await browser.newContext({ userAgent: UA, viewport: { width: 1300, height: 860 } });
  const p = await ctx.newPage(); p.errs = []; p.on('pageerror', e => p.errs.push(e.message));
  await p.goto(B + '/admin/chat-login', { waitUntil: 'domcontentloaded' });
  await p.fill('#email', email); await p.fill('#password', 'password123');
  await Promise.all([p.waitForURL(/team-messages/, { timeout: 30000 }), p.click('.submit')]);
  await p.waitForSelector('#tcContacts'); p.ctx = ctx; return p;
}
const row = n => `a.tc-contact:has(.tc-c-name:text-is("${n}"))`;
const open = (p, n) => p.click(row(n));
const head = (p, n, t = 15000) => p.waitForFunction(x => document.querySelector('.tc-thread-head .tc-th-name')?.textContent.trim() === x, n, { timeout: t });

(async () => {
  const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || chromium.executablePath() });
  const sup = await login(browser, 'super@apex.test');
  const abid = await login(browser, 'va0@apex.test');
  await sup.goto(CHAT, { waitUntil: 'domcontentloaded' }); await sup.waitForSelector('#tcContacts');

  // ---- A. A message shown without its file repairs itself ---------------------------------
  if (want('A')) {
    await open(sup, 'Abid Hussain'); await head(sup, 'Abid Hussain'); await sleep(1500);
    // Simulate what a half-saved delivery looked like: the message arrives with no files.
    await sup.route('**/admin/team-messages/thread?**', async (route) => {
      const res = await route.fetch();
      let body;
      try { body = await res.json(); } catch (e) { return route.fulfill({ response: res }).catch(() => {}); }
      let stripped = false;
      (body.messages || []).forEach(m => {
        if (m.body === 'CLINECEA' && m.attachments && m.attachments.length) { m.attachments = []; stripped = true; }
      });
      if (!stripped) return route.fulfill({ response: res }).catch(() => {});
      return route.fulfill({ response: res, body: JSON.stringify(body) }).catch(() => {});
    });

    await abid.goto(CHAT, { waitUntil: 'domcontentloaded' });
    await open(abid, 'Umair Arshad'); await head(abid, 'Umair Arshad');
    await abid.setInputFiles('#tcFile', [{ name: 'Tyeshia Singleton.zip', mimeType: 'application/zip', buffer: Buffer.alloc(2048, 9) }]);
    await abid.fill('#tcInput', 'CLINECEA');
    await abid.press('#tcInput', 'Enter');
    await abid.waitForFunction(() => [...document.querySelectorAll('#tcMessages .tc-att-name')].some(e => e.textContent.trim() === 'Tyeshia Singleton.zip'), null, { timeout: 20000 });

    // The receiving side first shows it text-only (as reported)…
    await sup.waitForFunction(() => [...document.querySelectorAll('#tcMessages .tc-text')].some(e => e.textContent.trim() === 'CLINECEA'), null, { timeout: 20000 });
    const textOnly = await sup.evaluate(() => {
      const el = [...document.querySelectorAll('#tcMessages .tc-msg')].find(m => (m.querySelector('.tc-text')?.textContent || '').trim() === 'CLINECEA');
      return { shown: !!el, hasFile: !!el?.querySelector('.tc-atts') };
    });
    // …and repairs itself from the next states sync, with no reload.
    await sup.unroute('**/admin/team-messages/thread?**');
    await sup.waitForFunction(() => {
      const el = [...document.querySelectorAll('#tcMessages .tc-msg')].find(m => (m.querySelector('.tc-text')?.textContent || '').trim() === 'CLINECEA');
      return !!el && !!el.querySelector('.tc-att-name');
    }, null, { timeout: 30000 }).catch(() => {});
    const healed = await sup.evaluate(() => {
      const el = [...document.querySelectorAll('#tcMessages .tc-msg')].find(m => (m.querySelector('.tc-text')?.textContent || '').trim() === 'CLINECEA');
      return { file: el?.querySelector('.tc-att-name')?.textContent.trim() || null, stillText: (el?.querySelector('.tc-text')?.textContent || '').trim() };
    });
    check('A1. a message that arrived without its file repairs itself (no reload)',
      textOnly.shown && healed.file === 'Tyeshia Singleton.zip' && healed.stillText === 'CLINECEA', { textOnly, healed });
  }

  // ---- B. Idle windows stop hammering the server ------------------------------------------
  if (want('B')) {
    await open(sup, 'Mujeeb Rahman'); await head(sup, 'Mujeeb Rahman');
    const count = async (seconds, viewing) => {
      await sup.evaluate(v => {
        window.__reqs = 0;
        Object.defineProperty(document, 'hasFocus', { value: () => v, configurable: true });
        window.dispatchEvent(new Event(v ? 'focus' : 'blur'));
      }, viewing);
      const onReq = r => { if (/\/admin\/team-messages\/(thread|notifications|presence)/.test(r.url())) sup.__n++; };
      sup.__n = 0; sup.on('request', onReq);
      await sleep(seconds * 1000);
      sup.off('request', onReq);
      return sup.__n;
    };
    const busy = await count(12, true);
    const idle = await count(12, false);
    check('B1. an idle window polls far less than one being used', idle < busy / 2, { requestsWhileViewing: busy, requestsWhileAway: idle });
    await sup.evaluate(() => { Object.defineProperty(document, 'hasFocus', { value: () => true, configurable: true }); window.dispatchEvent(new Event('focus')); });
  }

  // ---- C. Chat images are cacheable; avatar URLs stay put ----------------------------------
  if (want('C')) {
    await open(sup, 'Abid Hussain'); await head(sup, 'Abid Hussain');
    const png = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAIAAAACCAIAAAD91JpzAAAAFklEQVR4nGP8z8DwnwEJMDEgAwYGAHtUAvtXt9cMAAAAAElFTkSuQmCC', 'base64');
    await sup.setInputFiles('#tcFile', [{ name: 'shot.png', mimeType: 'image/png', buffer: png }]);
    await sup.press('#tcInput', 'Enter');
    await sup.waitForSelector('#tcMessages .tc-att-img img', { timeout: 20000 });
    const headers = await sup.evaluate(async () => {
      const img = document.querySelector('#tcMessages .tc-att-img');
      const r = await fetch(img.getAttribute('href'), { credentials: 'same-origin' });
      return { cc: r.headers.get('Cache-Control'), type: r.headers.get('Content-Type') };
    });
    check('C1. a shared image may be kept by the browser', /max-age=31536000/.test(headers.cc || '') && !/no-store/.test(headers.cc || ''), headers);

    const before = await sup.evaluate(() => document.querySelector('.tc-self-av img, #tcMeAvatar img')?.getAttribute('src') || null);
    await sleep(25000);   // longer than the presence write window
    const after = await sup.evaluate(() => document.querySelector('.tc-self-av img, #tcMeAvatar img')?.getAttribute('src') || null);
    check('C2. presence does not keep changing avatar URLs', before === after, { before, after });
  }

  check('Z. no page errors', !sup.errs.length && !abid.errs.length, { sup: sup.errs, abid: abid.errs });
  await browser.close();
  const failed = results.filter(r => !r.ok);
  console.log(`\n${results.length - failed.length}/${results.length} passed`);
  process.exit(failed.length ? 1 : 0);
})().catch(e => { console.error('SCRIPT ERROR', e); process.exit(2); });
