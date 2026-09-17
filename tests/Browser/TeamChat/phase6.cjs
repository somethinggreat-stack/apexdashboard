// Shared-PC privacy checks — one browser profile, two VAs one after the other.
const path = require('path');
const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright-core');
const B = process.env.TEST_BASE_URL || 'http://127.0.0.1:8932';
if (!['127.0.0.1', 'localhost'].includes(new URL(B).hostname)) throw Error('Tests require a local disposable server');
const CHAT = B + '/admin/team-messages?standalone=1';
const UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/125 Safari/537.36 ApexDesktop/1.0';
const sleep = ms => new Promise(r => setTimeout(r, ms));
const results = [];
function check(name, ok, detail) { results.push({ name, ok: !!ok }); console.log((ok ? 'PASS ' : 'FAIL ') + name + (detail !== undefined ? '  ' + JSON.stringify(detail) : '')); }

const row = n => `a.tc-contact:has(.tc-c-name:text-is("${n}"))`;
const open = (p, n) => p.click(row(n));
const head = (p, n, t = 15000) => p.waitForFunction(x => document.querySelector('.tc-thread-head .tc-th-name')?.textContent.trim() === x, n, { timeout: t });
const texts = p => p.evaluate(() => [...document.querySelectorAll('#tcMessages .tc-msg .tc-text')].map(e => e.textContent.trim()));
// Everything this browser profile has stored for the chat.
const localState = p => p.evaluate(async () => {
  const ls = Object.keys(localStorage).map(k => k + ' = ' + String(localStorage.getItem(k)).slice(0, 120));
  const ss = Object.keys(sessionStorage).map(k => k + ' = ' + String(sessionStorage.getItem(k)).slice(0, 120));
  let idb = [];
  try {
    const db = await new Promise(res => { const r = indexedDB.open('apexChat', 1); r.onsuccess = () => res(r.result); r.onerror = () => res(null); r.onupgradeneeded = () => { try { r.result.createObjectStore('threads'); } catch (e) {} }; });
    if (db) {
      idb = await new Promise(res => {
        const out = []; const st = db.transaction('threads', 'readonly').objectStore('threads'); const c = st.openCursor();
        c.onsuccess = () => { const cur = c.result; if (!cur) return res(out); out.push({ key: String(cur.key), uid: cur.value && cur.value.uid, ts: cur.value && cur.value.ts, body: JSON.stringify(cur.value && cur.value.data).slice(0, 400) }); cur.continue(); };
        c.onerror = () => res(out);
      });
      db.close();
    }
  } catch (e) {}
  return { ls, ss, idb };
});

async function signIn(page, email) {
  await page.goto(B + '/admin/chat-login', { waitUntil: 'domcontentloaded' });
  await page.fill('#email', email); await page.fill('#password', 'password123');
  await Promise.all([page.waitForURL(/team-messages/, { timeout: 30000 }), page.click('.submit')]);
  await page.waitForSelector('#tcContacts');
}
async function signOut(page) {
  await page.click('.tc-logout');
  await page.waitForURL(/chat-login/, { timeout: 30000 });
  await page.waitForSelector('#email');
}

(async () => {
  const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || chromium.executablePath() });
  // ONE browser profile, as on a shared PC: same context for both VAs.
  const ctx = await browser.newContext({ userAgent: UA, viewport: { width: 1300, height: 860 } });
  const page = await ctx.newPage(); const errs = []; page.on('pageerror', e => errs.push(e.message));

  // ---- VA #1 uses the chat: opens chats, writes a private note and a draft ----------------
  await signIn(page, 'va0@apex.test');           // Abid
  await open(page, 'Umair Arshad'); await head(page, 'Umair Arshad'); await sleep(1200);
  await page.fill('#tcInput', 'abid-private-draft');
  await page.dispatchEvent('#tcInput', 'input');
  await page.click('a.tc-self-row'); await head(page, 'Notes (You)', 20000).catch(() => {});
  await page.fill('#tcInput', 'abid-secret-note'); await page.press('#tcInput', 'Enter');
  await page.waitForFunction(() => [...document.querySelectorAll('#tcMessages .tc-text')].some(e => e.textContent.trim() === 'abid-secret-note'), null, { timeout: 15000 });
  // Leave and re-open the notes so the SAVED copy really contains the private note.
  await open(page, 'Umair Arshad'); await head(page, 'Umair Arshad'); await sleep(800);
  await page.click('a.tc-self-row'); await head(page, 'Notes (You)', 20000).catch(() => {});
  await page.waitForFunction(() => [...document.querySelectorAll('#tcMessages .tc-text')].some(e => e.textContent.trim() === 'abid-secret-note'), null, { timeout: 15000 }).catch(() => {});
  await sleep(1200);
  await open(page, 'Umair Arshad'); await head(page, 'Umair Arshad'); await sleep(1500);   // snapshots saved
  const before = await localState(page);
  const abidStored = JSON.stringify(before);
  check('P0. VA #1 does have local state while signed in', before.idb.length > 0 && before.ls.length > 0, { idb: before.idb.length, ls: before.ls.length });

  // ---- VA #1 signs out --------------------------------------------------------------------
  await signOut(page);
  await sleep(800);
  const cleared = await localState(page);
  check('P1. signing out leaves no saved chats, drafts or notification data',
    cleared.idb.length === 0 && !cleared.ls.some(k => /^(apex-team-|tc-)/.test(k)) && cleared.ss.length === 0, cleared);
  check('P2. nothing of VA #1\'s content remains on the PC',
    !JSON.stringify(cleared).includes('abid-secret-note') && !JSON.stringify(cleared).includes('abid-private-draft'), cleared);

  // ---- VA #2 signs in on the same PC ------------------------------------------------------
  await signIn(page, 'va1@apex.test');           // Mujeeb
  const seen = [];
  page.on('response', () => {});
  await open(page, 'Umair Arshad');
  // Watch the thread continuously for any flash of the other VA's content.
  for (let i = 0; i < 20; i++) { seen.push(...(await texts(page))); await sleep(150); }
  await head(page, 'Umair Arshad');
  await page.click('a.tc-self-row'); await head(page, 'Notes (You)', 20000).catch(() => {});
  for (let i = 0; i < 20; i++) { seen.push(...(await texts(page))); await sleep(150); }
  const notes = await texts(page);
  const draftBox = await page.inputValue('#tcInput');

  check('P3. VA #2 never sees VA #1\'s private notes (not even for a moment)', !seen.includes('abid-secret-note') && !notes.includes('abid-secret-note'), { notes });
  check('P4. VA #2 never sees VA #1\'s messages or draft', !seen.some(t => /abid-private-draft/.test(t)) && draftBox === '', { draftBox });

  // ---- A snapshot from another user / an old one is never painted --------------------------
  // Plant one of each directly in the browser's store, the way a previous version left them.
  const convId = await page.evaluate(() => document.getElementById('tcMessages')?.dataset.conversation || '');
  await page.evaluate(async ({ me, convId }) => {
    const db = await new Promise(res => { const r = indexedDB.open('apexChat', 1); r.onsuccess = () => res(r.result); r.onerror = () => res(null); });
    const put = (key, val) => new Promise(res => { const t = db.transaction('threads', 'readwrite').objectStore('threads').put(val, key); t.onsuccess = t.onerror = () => res(); });
    const fake = { conversation_id: convId, title: 'Umair Arshad', messages: [{ id: 999999, body: 'LEAKED-FROM-OTHER-USER', mine: false, at: '9:00 AM', sender: { name: 'X', mono: 'X', color: '#000' } }], pinned: [], readUpTo: 0 };
    await put('u999|c=' + convId, { data: fake, ts: Date.now(), uid: '999' });                    // another user's
    await put('u' + me + '|with=999', { data: fake, ts: Date.now() - (3 * 24 * 60 * 60 * 1000), uid: String(me) });  // mine, 3 days old
    await put('legacy|c=' + convId, { data: fake, ts: Date.now() });                               // pre-update, unstamped
    db.close();
  }, { me: await page.evaluate(() => window.__ME_ID_FOR_TEST || document.querySelector('meta[name="csrf-token"]') ? null : null) || (await page.evaluate(() => { const m = /var ME_ID = "(\d+)"/.exec(document.documentElement.innerHTML); return m ? m[1] : ''; })), convId });

  await page.goto(CHAT, { waitUntil: 'domcontentloaded' }); await page.waitForSelector('#tcContacts');
  await open(page, 'Umair Arshad');
  const flashes = [];
  for (let i = 0; i < 20; i++) { flashes.push(...(await texts(page))); await sleep(150); }
  check('P5. a saved chat belonging to another user is never shown', !flashes.includes('LEAKED-FROM-OTHER-USER'), { sample: flashes.slice(0, 3) });

  await sleep(1500);
  const after = await localState(page);
  check('P6. foreign and stale saved chats are swept off the PC',
    after.idb.every(r => String(r.uid) === String(after.idb[0] && after.idb[0].uid)) && !JSON.stringify(after.idb).includes('LEAKED-FROM-OTHER-USER'), after.idb.map(r => ({ key: r.key, uid: r.uid })));

  check('P7. every stored key is tied to a user', after.ls.concat(after.ss).every(k => /:u\d+|apex-known-new-clients/.test(k)), after.ls.concat(after.ss));
  check('Z. no page errors', !errs.length, errs);

  await browser.close();
  const failed = results.filter(r => !r.ok);
  console.log(`\n${results.length - failed.length}/${results.length} passed`);
  process.exit(failed.length ? 1 : 0);
})().catch(e => { console.error('SCRIPT ERROR', e); process.exit(2); });
