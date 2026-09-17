// Phase 1 regression checks — features the changes touch.
const path = require('path');
const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright-core');
const B = process.env.TEST_BASE_URL || 'http://127.0.0.1:8932';
if (!['127.0.0.1', 'localhost'].includes(new URL(B).hostname)) throw Error('Tests require a local disposable server');
const CHAT = B + '/admin/team-messages?standalone=1';
const UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/125 Safari/537.36 ApexDesktop/1.0';
const sleep = ms => new Promise(r => setTimeout(r, ms));
const results = [];
function check(name, ok, detail) { results.push({ name, ok: !!ok }); console.log((ok ? 'PASS ' : 'FAIL ') + name + (detail ? '  ' + JSON.stringify(detail) : '')); }
async function login(browser, email) {
  const ctx = await browser.newContext({ userAgent: UA, viewport: { width: 1300, height: 820 } });
  const p = await ctx.newPage(); p.errs = []; p.on('pageerror', e => p.errs.push(e.message));
  await p.goto(B + '/admin/chat-login', { waitUntil: 'domcontentloaded' });
  await p.fill('#email', email); await p.fill('#password', 'password123');
  await Promise.all([p.waitForURL(/team-messages/, { timeout: 30000 }), p.click('.submit')]);
  await p.waitForSelector('#tcContacts'); return p;
}
const row = n => `a.tc-contact:has(.tc-c-name:text-is("${n}"))`;
const open = async (p, n) => p.click(row(n));
const head = async (p, n, t = 15000) => p.waitForFunction(x => document.querySelector('.tc-thread-head .tc-th-name')?.textContent.trim() === x, n, { timeout: t });
const texts = p => p.evaluate(() => [...document.querySelectorAll('#tcMessages .tc-msg .tc-text')].map(e => e.textContent.trim()));
const convOf = p => p.evaluate(() => document.getElementById('tcMessages').dataset.conversation);
const serverCount = (p, c, body) => p.evaluate(async ([c, b]) => (await (await fetch('/admin/team-messages/thread?c=' + c + '&after=0', { headers: { Accept: 'application/json' } })).json()).messages.filter(m => m.body === b).length, [c, body]);

(async () => {
  const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || chromium.executablePath() });
  const p = await login(browser, 'super@apex.test');
  await p.goto(CHAT, { waitUntil: 'domcontentloaded' }); await p.waitForSelector('#tcContacts');

  // a. DM → group → DM swaps, members button present for the group.
  await open(p, 'Abid Hussain'); await head(p, 'Abid Hussain');
  await open(p, 'CFPB Screenshots'); await head(p, 'CFPB Screenshots');
  const hasMembers = await p.$('#tcMembersBtn');
  const gText = (await texts(p)).includes('Group hello');
  await open(p, 'Mujeeb Rahman'); await head(p, 'Mujeeb Rahman');
  const seenHidden = await p.evaluate(() => document.getElementById('tcSeen').hidden);
  check('a. DM → group → DM swap works (members btn, group msgs, Seen-by hidden on DM)', hasMembers && gText && seenHidden);

  // b. Back button returns to the previous chat.
  await p.goBack(); await head(p, 'CFPB Screenshots').catch(() => {});
  const back1 = await p.evaluate(() => document.querySelector('.tc-thread-head .tc-th-name')?.textContent.trim());
  check('b. browser Back goes to the previous chat', back1 === 'CFPB Screenshots', { header: back1 });

  // c. A send that fails after switching away is put back when the VA returns — and resends once.
  await open(p, 'Raja Khuram'); await head(p, 'Raja Khuram');
  const rajaConv = await convOf(p);
  let first = true;
  await p.route('**/admin/team-messages', async route => {
    if (route.request().method() !== 'POST') return route.continue().catch(() => {});
    if (first) { first = false; await sleep(1500); return route.abort('failed').catch(() => {}); }
    return route.continue().catch(() => {});
  });
  await p.fill('#tcInput', 'come-back-to-me');
  await p.press('#tcInput', 'Enter');
  await sleep(200);
  await open(p, 'Ubaid Dogar'); await head(p, 'Ubaid Dogar');
  await sleep(2500);
  const ubaidBox = await p.inputValue('#tcInput');
  await open(p, 'Raja Khuram'); await head(p, 'Raja Khuram'); await sleep(600);
  const restored = await p.inputValue('#tcInput');
  await p.press('#tcInput', 'Enter');
  await p.waitForFunction(() => document.getElementById('tcInput').value === '', null, { timeout: 15000 }).catch(() => {});
  await p.unroute('**/admin/team-messages');
  await sleep(1500);
  const cnt = await serverCount(p, rajaConv, 'come-back-to-me');
  check('c. failed send after switching is restored in its own chat and resends once', ubaidBox === '' && restored === 'come-back-to-me' && cnt === 1, { ubaidBox, restored, serverCount: cnt });

  // d. Session expired on send → reload keeps the draft in the SAME chat only.
  await p.route('**/admin/team-messages', async route => {
    if (route.request().method() !== 'POST') return route.continue().catch(() => {});
    return route.fulfill({ status: 419, contentType: 'application/json', body: '{"message":"CSRF token mismatch."}' }).catch(() => {});
  });
  await p.fill('#tcInput', 'draft-after-419');
  await Promise.all([p.waitForEvent('load', { timeout: 20000 }).catch(() => {}), p.press('#tcInput', 'Enter')]);
  await p.unroute('**/admin/team-messages');
  await p.waitForSelector('#tcInput', { timeout: 20000 }); await sleep(800);
  const afterReload = await p.inputValue('#tcInput');
  const hdr = await p.evaluate(() => document.querySelector('.tc-thread-head .tc-th-name')?.textContent.trim());
  await open(p, 'Ubaid Dogar'); await head(p, 'Ubaid Dogar');
  const otherBox = await p.inputValue('#tcInput');
  check('d. 419 reconnect restores the draft into the same chat only', afterReload === 'draft-after-419' && hdr === 'Raja Khuram' && otherBox === '', { afterReload, hdr, otherBox });

  // e. Old-format (plain string) draft from the previous app version still restores.
  // (the stash key is per signed-in user since the shared-PC work — a plain string under it
  //  is still restored, which is what the old app version wrote)
  await p.evaluate(() => {
    const m = document.documentElement.innerHTML.match(/RECONNECT_DRAFT_KEY = '([^']+)' \+ "(\d+)"/);
    if (!m) throw Error('Per-user reconnect draft key missing');
    sessionStorage.setItem(m[1] + m[2], 'legacy draft text');
    sessionStorage.setItem('tc-draft', 'unowned legacy draft must never restore');
  });
  await p.reload({ waitUntil: 'domcontentloaded' }); await p.waitForSelector('#tcInput'); await sleep(500);
  check('e. legacy plain-text draft restores after update', (await p.inputValue('#tcInput')) === 'legacy draft text');
  await p.fill('#tcInput', '');

  // f. Normal sends + a file still work; message yourself works.
  await p.setInputFiles('#tcFile', { name: 'proof.zip', mimeType: 'application/zip', buffer: Buffer.from('PK zip bytes') });
  await p.fill('#tcInput', 'file-with-text');
  await p.press('#tcInput', 'Enter');
  await p.waitForFunction(() => [...document.querySelectorAll('#tcMessages .tc-att-name')].some(e => e.textContent.trim() === 'proof.zip'), null, { timeout: 15000 }).catch(() => {});
  const fileShown = await p.evaluate(() => [...document.querySelectorAll('#tcMessages .tc-att-name')].some(e => e.textContent.trim() === 'proof.zip'));
  await p.click('a.tc-self-row'); await head(p, 'Notes (You)').catch(() => {});
  await p.fill('#tcInput', 'note-to-self'); await p.press('#tcInput', 'Enter');
  await p.waitForFunction(() => [...document.querySelectorAll('#tcMessages .tc-text')].some(e => e.textContent.trim() === 'note-to-self'), null, { timeout: 15000 }).catch(() => {});
  const noteShown = (await texts(p)).includes('note-to-self');
  check('f. file send + notes-to-self still work', fileShown && noteShown, { fileShown, noteShown });

  check('g. no page errors', !p.errs.length, p.errs);
  await browser.close();
  const failed = results.filter(r => !r.ok);
  console.log(`\n${results.length - failed.length}/${results.length} passed`);
  process.exit(failed.length ? 1 : 0);
})().catch(e => { console.error('SCRIPT ERROR', e); process.exit(2); });
