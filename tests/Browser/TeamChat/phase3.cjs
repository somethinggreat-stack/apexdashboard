// Phase 3 browser checks — unread, seen & notification state.
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

// Record every OS notification the page tries to show, and (for the desktop-app checks)
// every native command it invokes.
const INSTRUMENT = ({ tauri }) => {
  window.__notifs = []; window.__invokes = [];
  const N = function (title, opts) { window.__notifs.push({ title, body: opts && opts.body }); this.close = function () {}; };
  N.permission = 'granted'; N.requestPermission = () => Promise.resolve('granted');
  Object.defineProperty(window, 'Notification', { value: N, writable: true, configurable: true });
  if (tauri) {
    window.__TAURI__ = {
      core: { invoke: function (cmd, args) { window.__invokes.push([cmd, args]); return Promise.resolve(); } },
      notification: {
        isPermissionGranted: () => Promise.resolve(true),
        sendNotification: function (o) { window.__notifs.push({ title: o.title, body: o.body, native: true }); },
      },
      window: { getCurrentWindow: () => ({ show() {}, unminimize() {}, setFocus() {} }) },
    };
  }
};

async function login(browser, email, opts = {}) {
  const ctx = await browser.newContext({ userAgent: UA, viewport: { width: 1280, height: 820 } });
  await ctx.grantPermissions(['notifications'], { origin: B });
  await ctx.addInitScript(INSTRUMENT, { tauri: !!opts.tauri });
  const p = await ctx.newPage(); p.errs = []; p.on('pageerror', e => p.errs.push(e.message));
  await p.goto(B + '/admin/chat-login', { waitUntil: 'domcontentloaded' });
  await p.fill('#email', email); await p.fill('#password', 'password123');
  await Promise.all([p.waitForURL(/team-messages/, { timeout: 30000 }), p.click('.submit')]);
  await p.waitForSelector('#tcContacts'); p.ctx = ctx; return p;
}
const row = n => `a.tc-contact:has(.tc-c-name:text-is("${n}"))`;
const open = (p, n) => p.click(row(n));
const head = (p, n, t = 15000) => p.waitForFunction(x => document.querySelector('.tc-thread-head .tc-th-name')?.textContent.trim() === x, n, { timeout: t });
const notifs = p => p.evaluate(() => window.__notifs || []);
const invokes = p => p.evaluate(() => window.__invokes || []);
const badgeOf = (p, name) => p.evaluate(n => {
  const r = [...document.querySelectorAll('a.tc-contact')].find(a => a.querySelector('.tc-c-name')?.textContent.trim() === n);
  return r ? (r.querySelector('[data-badge]')?.textContent || null) : 'no-row';
}, name);
const sendApi = (p, f) => p.evaluate(async f => { const fd = new FormData(); fd.append('_token', document.querySelector('meta[name="csrf-token"]').content); for (const k in f) fd.append(k, f[k]); const r = await fetch('/admin/team-messages', { method: 'POST', body: fd, headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } }); const j = await r.json(); if (!j.ok) throw new Error('send failed'); return j; }, f);
const readUpTo = (p, c) => p.evaluate(async c => (await (await fetch('/admin/team-messages/thread?c=' + c + '&after=999999&read=0', { headers: { Accept: 'application/json' } })).json()).readUpTo, c);
const ticksBlue = p => p.evaluate(() => [...document.querySelectorAll('#tcMessages .tc-msg.mine .tc-btick')].map(e => e.classList.contains('read')));

(async () => {
  const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || chromium.executablePath() });
  const abid = await login(browser, 'va0@apex.test');
  const SUPER_ID = await abid.evaluate(() => [...document.querySelectorAll('a.tc-contact')].find(a => a.querySelector('.tc-c-name')?.textContent.trim() === 'Umair Arshad')?.dataset.peer);
  const ABID_CONV = 1;

  // ---- N. Refresh / first launch must not replay the backlog ---------------------------
  if (want('N')) {
    // 40 older messages exist from the seed + these.
    for (let i = 0; i < 35; i++) await sendApi(abid, { recipient_id: SUPER_ID, body: 'history-' + i });

    const sup = await login(browser, 'super@apex.test');
    await sup.goto(CHAT, { waitUntil: 'domcontentloaded' }); await sup.waitForSelector('#tcContacts');
    await sleep(14000);   // several poll cycles
    const firstLaunch = await notifs(sup);
    check('N1. first launch shows no notifications for existing history', firstLaunch.length === 0, { notifications: firstLaunch.length, sample: firstLaunch.slice(0, 2) });

    // A message that arrives AFTER priming is still delivered.
    await sendApi(abid, { recipient_id: SUPER_ID, body: 'after-priming' });
    await sup.waitForFunction(() => (window.__notifs || []).length > 0, null, { timeout: 20000 }).catch(() => {});
    const afterPrime = await notifs(sup);
    check('N2. a message sent after priming still notifies', afterPrime.length === 1 && /after-priming/.test(afterPrime[0].body || ''), afterPrime);

    // Refresh button wipes local state → must NOT replay the history.
    await sup.evaluate(() => { window.__notifs = []; });
    await sup.click('#tcRefresh');
    await sup.waitForFunction(() => document.readyState === 'complete' && !!document.getElementById('tcContacts'), null, { timeout: 30000 }).catch(() => {});
    await sleep(14000);
    const afterRefresh = await notifs(sup);
    check('N3. Refresh does not replay old messages', afterRefresh.length === 0, { notifications: afterRefresh.length, sample: afterRefresh.slice(0, 3) });

    // …and delivery still works after a Refresh.
    await sendApi(abid, { recipient_id: SUPER_ID, body: 'after-refresh' });
    await sup.waitForFunction(() => (window.__notifs || []).length > 0, null, { timeout: 20000 }).catch(() => {});
    const afterRefreshNew = await notifs(sup);
    check('N4. new messages still notify after a Refresh', afterRefreshNew.length === 1 && /after-refresh/.test(afterRefreshNew[0].body || ''), afterRefreshNew);
    await sup.ctx.close();
  }

  // ---- S. Seen / read only when the window is really in front ---------------------------
  if (want('S')) {
    const sup = await login(browser, 'super@apex.test');
    await sup.goto(CHAT, { waitUntil: 'domcontentloaded' }); await sup.waitForSelector('#tcContacts');
    await open(sup, 'Abid Hussain'); await head(sup, 'Abid Hussain'); await sleep(1500);

    // The app goes to the tray / behind another window: the page is no longer focused.
    await sup.evaluate(() => {
      Object.defineProperty(document, 'hasFocus', { value: () => false, configurable: true });
      window.dispatchEvent(new Event('blur'));
    });
    const focusState = await sup.evaluate(() => ({ hidden: document.hidden, focus: document.hasFocus() }));

    const sent = (await sendApi(abid, { recipient_id: SUPER_ID, body: 'sent-while-you-were-away' })).message.id;
    await sleep(9000);

    const away = {
      readUpTo: await readUpTo(abid, ABID_CONV),          // what the SENDER sees (blue ticks)
      senderTicks: await (async () => { await open(abid, 'Umair Arshad'); await head(abid, 'Umair Arshad'); await sleep(1200); return ticksBlue(abid); })(),
      badge: await badgeOf(sup, 'Abid Hussain'),
      shown: await sup.evaluate(() => [...document.querySelectorAll('#tcMessages .tc-text')].some(e => e.textContent.trim() === 'sent-while-you-were-away')),
      notified: (await notifs(sup)).length, focusState,
    };
    check('S1. away: not marked read, no blue tick, badge shows, message still arrives',
      away.readUpTo < sent && !away.senderTicks.slice(-1)[0] && away.badge === '1' && away.shown && away.notified >= 1, Object.assign({ sent }, away));

    // Coming back to the window marks it read and clears the badge.
    await sup.evaluate(() => {
      Object.defineProperty(document, 'hasFocus', { value: () => true, configurable: true });
      window.dispatchEvent(new Event('focus'));
    });
    await sleep(9000);
    const back = {
      readUpTo: await readUpTo(abid, ABID_CONV),
      badge: await badgeOf(sup, 'Abid Hussain'),
      senderTicks: await (async () => { await sleep(1500); return ticksBlue(abid); })(),
    };
    check('S2. back at the window: marked read, badge clears, sender sees the blue tick',
      back.readUpTo >= sent && back.badge === null && back.senderTicks.slice(-1)[0] === true, Object.assign({ sent }, back));
    await sup.ctx.close();
  }

  // ---- B. Taskbar unread dot (desktop app bridge) ---------------------------------------
  if (want('B')) {
    const sup = await login(browser, 'super@apex.test', { tauri: true });
    await sup.goto(CHAT, { waitUntil: 'domcontentloaded' }); await sup.waitForSelector('#tcContacts');
    await open(sup, 'Mujeeb Rahman'); await head(sup, 'Mujeeb Rahman'); await sleep(1500);
    await sup.evaluate(() => { window.__invokes = []; });
    await sendApi(abid, { recipient_id: SUPER_ID, body: 'dot-please' });
    await sup.waitForFunction(() => (window.__invokes || []).some(i => i[0] === 'set_unread' && i[1] && i[1].count > 0), null, { timeout: 20000 }).catch(() => {});
    const set = (await invokes(sup)).filter(i => i[0] === 'set_unread');
    const lit = set.filter(i => i[1].count > 0);
    check('B1. the app is asked to light the taskbar dot with the unread count', lit.length > 0, { calls: set.slice(-3) });

    // Reading the chat lowers it; reading everything clears it.
    const peak = (await invokes(sup)).filter(i => i[0] === 'set_unread').slice(-1)[0][1].count;
    for (const n of ['Abid Hussain', 'Mujeeb Rahman', 'Raja Khuram', 'Ubaid Dogar', 'Umair Sajid', 'CFPB Screenshots', 'Credit Repair CFPB']) {
      await open(sup, n).catch(() => {}); await sleep(900);
    }
    await sup.waitForFunction(() => (window.__invokes || []).slice(-1)[0]?.[1]?.count === 0, null, { timeout: 20000 }).catch(() => {});
    const last = (await invokes(sup)).filter(i => i[0] === 'set_unread').slice(-1)[0];
    check('B2. the dot follows the real unread total and clears when caught up', last && last[1].count === 0 && peak > 0, { peak, last });
    check('B3. native toast used in the app (not a browser notification)', (await notifs(sup)).every(n => n.native), await notifs(sup));
    await sup.ctx.close();
  }

  check('Z. no page errors', !abid.errs.length, abid.errs);
  await browser.close();
  const failed = results.filter(r => !r.ok);
  console.log(`\n${results.length - failed.length}/${results.length} passed`);
  process.exit(failed.length ? 1 : 0);
})().catch(e => { console.error('SCRIPT ERROR', e); process.exit(2); });
