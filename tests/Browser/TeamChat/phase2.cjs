// Phase 2 browser checks — uploads, attachments & file sending.
// Server must run with TEAM_CHAT_MAX_REQUEST_MB=6 and PHP upload_max_filesize=20M.
const path = require('path');
const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright-core');
const B = process.env.TEST_BASE_URL || 'http://127.0.0.1:8932';
if (!['127.0.0.1', 'localhost'].includes(new URL(B).hostname)) throw Error('Tests require a local disposable server');
const CHAT = B + '/admin/team-messages?standalone=1';
const UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/125 Safari/537.36 ApexDesktop/1.0';
const MB = 1024 * 1024;
const sleep = ms => new Promise(r => setTimeout(r, ms));
const results = [];
const only = process.argv[2] ? process.argv[2].split(',') : null;
function check(name, ok, detail) { results.push({ name, ok: !!ok }); console.log((ok ? 'PASS ' : 'FAIL ') + name + (detail !== undefined ? '  ' + JSON.stringify(detail) : '')); }
const want = id => !only || only.includes(id);

async function login(browser, email) {
  const ctx = await browser.newContext({ userAgent: UA, viewport: { width: 1300, height: 860 } });
  const p = await ctx.newPage(); p.errs = []; p.on('pageerror', e => p.errs.push(e.message));
  await p.goto(B + '/admin/chat-login', { waitUntil: 'domcontentloaded' });
  await p.fill('#email', email); await p.fill('#password', 'password123');
  await Promise.all([p.waitForURL(/team-messages/, { timeout: 30000 }), p.click('.submit')]);
  await p.waitForSelector('#tcContacts'); return p;
}
const row = n => `a.tc-contact:has(.tc-c-name:text-is("${n}"))`;
const open = (p, n) => p.click(row(n));
const head = (p, n, t = 15000) => p.waitForFunction(x => document.querySelector('.tc-thread-head .tc-th-name')?.textContent.trim() === x, n, { timeout: t });
const texts = p => p.evaluate(() => [...document.querySelectorAll('#tcMessages .tc-msg .tc-text')].map(e => e.textContent.trim()));
const attNames = p => p.evaluate(() => [...document.querySelectorAll('#tcMessages .tc-att-name')].map(e => e.textContent.trim()));
const chips = p => p.evaluate(() => [...document.querySelectorAll('#tcPending .tc-chip-name')].map(e => e.textContent.trim()));
const lastToast = p => p.evaluate(() => { const t = [...document.querySelectorAll('.apex-toast, .toast, [class*="toast"]')].map(e => e.textContent.trim()).filter(Boolean); return t[t.length - 1] || ''; });
const file = (name, bytes) => ({ name, mimeType: 'application/zip', buffer: Buffer.alloc(bytes, 7) });
const markNoReload = p => p.evaluate(() => { window.__noReload = 1; });
const stillNoReload = p => p.evaluate(() => window.__noReload === 1);
const convOf = p => p.evaluate(() => document.getElementById('tcMessages').dataset.conversation);
const thread = (p, c) => p.evaluate(async c => (await (await fetch('/admin/team-messages/thread?c=' + c + '&after=0', { headers: { Accept: 'application/json' } })).json()).messages, c);
const sendApi = (p, f) => p.evaluate(async f => { const fd = new FormData(); fd.append('_token', document.querySelector('meta[name="csrf-token"]').content); for (const k in f) fd.append(k, f[k]); return (await fetch('/admin/team-messages', { method: 'POST', body: fd, headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })).json(); }, f);
async function throttleUpload(p, bytesPerSec) {
  const cdp = await p.context().newCDPSession(p);
  await cdp.send('Network.enable');
  await cdp.send('Network.emulateNetworkConditions', { offline: false, latency: 0, downloadThroughput: -1, uploadThroughput: bytesPerSec });
  return async () => { await cdp.send('Network.emulateNetworkConditions', { offline: false, latency: 0, downloadThroughput: -1, uploadThroughput: -1 }); await cdp.detach(); };
}
// Toasts: capture every toast text shown.
async function captureToasts(p) {
  await p.evaluate(() => {
    window.__toasts = [];
    const orig = window.apexToast;
    window.apexToast = function (t) { window.__toasts.push(String(t)); if (orig) return orig.apply(this, arguments); };
  });
}
const toasts = p => p.evaluate(() => window.__toasts || []);

(async () => {
  const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || chromium.executablePath() });
  const sup = await login(browser, 'super@apex.test');
  const abid = await login(browser, 'va0@apex.test');
  const SUPER_ID = await abid.evaluate(() => [...document.querySelectorAll('a.tc-contact')].find(a => a.querySelector('.tc-c-name')?.textContent.trim() === 'Umair Arshad')?.dataset.peer);
  await sup.goto(CHAT, { waitUntil: 'domcontentloaded' }); await sup.waitForSelector('#tcContacts');
  await captureToasts(sup);

  // ---- L. Limits enforced before upload -------------------------------------------------
  if (want('L')) {
    await open(sup, 'Abid Hussain'); await head(sup, 'Abid Hussain'); await captureToasts(sup);
    const limits = await sup.evaluate(() => document.getElementById('tcAttach').title);
    await sup.setInputFiles('#tcFile', [file('too-big.zip', 7 * MB)]);
    await sleep(200);
    const c1 = await chips(sup); const t1 = (await toasts(sup)).slice(-1)[0] || '';
    await sup.setInputFiles('#tcFile', [file('four-a.zip', 4 * MB)]);
    await sup.setInputFiles('#tcFile', [file('four-b.zip', 4 * MB)]);
    await sleep(200);
    const c2 = await chips(sup); const t2 = (await toasts(sup)).slice(-1)[0] || '';
    check('L1. a file over the per-file limit is refused, named, not staged', c1.length === 0 && /too-big\.zip/.test(t1) && /MB/.test(t1), { chips: c1, toast: t1 });
    check('L2. a file that would push the message over the total is refused', JSON.stringify(c2) === '["four-a.zip"]' && /four-b\.zip/.test(t2) && /separate message/.test(t2), { chips: c2, toast: t2 });
    check('L3. attach button shows the limits', /up to .* MB each, .* MB per message/.test(limits) && /server: upload_max_filesize 20M/.test(limits), limits);
    // 413 from the edge (Cloudflare's HTML page): clear message, files kept, no reload.
    await markNoReload(sup);
    await sup.route('**/admin/team-messages', r => r.request().method() === 'POST'
      ? r.fulfill({ status: 413, contentType: 'text/html', body: '<html><head><title>413 Payload Too Large</title></head><body><center>cloudflare</center></body></html>' }).catch(() => {})
      : r.continue().catch(() => {}));
    await sup.fill('#tcInput', 'edge says no');
    await sup.press('#tcInput', 'Enter');
    await sup.waitForFunction(() => !document.getElementById('tcInput').readOnly, null, { timeout: 15000 });
    await sup.unroute('**/admin/team-messages');
    const t3 = (await toasts(sup)).slice(-1)[0] || '';
    check('L4. a 413 shows "too large", keeps text + files, no reload', /Too large for the server/.test(t3) && (await stillNoReload(sup)) && (await sup.inputValue('#tcInput')) === 'edge says no' && (await chips(sup)).length === 1, { toast: t3 });
    // Now it goes through (same content).
    await sup.press('#tcInput', 'Enter');
    await sup.waitForFunction(() => document.getElementById('tcInput').value === '', null, { timeout: 30000 });
    await sleep(800);
    const conv = await convOf(sup);
    const n = (await thread(sup, conv)).filter(m => m.body === 'edge says no').length;
    check('L5. retry after the 413 sends exactly once, with its file', n === 1 && (await attNames(sup)).includes('four-a.zip'), { serverCount: n });
  }

  // ---- T. Slow upload (> 60 s) completes; progress text shown --------------------------
  if (want('T')) {
    await open(sup, 'Mujeeb Rahman'); await head(sup, 'Mujeeb Rahman'); await captureToasts(sup);
    await sup.setInputFiles('#tcFile', [file('slow-upload.zip', 2 * MB)]);
    const unthrottle = await throttleUpload(sup, 26 * 1024);   // ~80 s for 2 MB
    const t0 = Date.now();
    await sup.fill('#tcInput', 'slow-upload-msg');
    await sup.press('#tcInput', 'Enter');
    await sleep(8000);
    const label = await sup.evaluate(() => { const l = document.getElementById('tcProgressLabel'); return l && !l.hidden ? l.textContent : null; });
    await sup.waitForFunction(() => [...document.querySelectorAll('#tcMessages .tc-att-name')].some(e => e.textContent.trim() === 'slow-upload.zip'), null, { timeout: 200000 }).catch(() => {});
    const secs = Math.round((Date.now() - t0) / 1000);
    await unthrottle();
    const shown = (await attNames(sup)).includes('slow-upload.zip');
    const tt = await toasts(sup);
    check('T1. an upload taking over 60 s completes (no "timed out")', shown && secs > 60 && !tt.some(x => /timed out|stalled/i.test(x)), { seconds: secs, toasts: tt });
    check('T2. progress text shows percent and MB', /Uploading… \d+%.*MB of .*MB/.test(label || ''), label);
  }

  // ---- R. Session token expired → refresh + automatic retry, files kept, no reload ------
  if (want('R')) {
    await open(sup, 'Raja Khuram'); await head(sup, 'Raja Khuram'); await markNoReload(sup);
    let n419 = 0, posts = 0;
    await sup.route('**/admin/team-messages', r => {
      if (r.request().method() !== 'POST') return r.continue().catch(() => {});
      posts++;
      if (n419++ === 0) return r.fulfill({ status: 419, contentType: 'application/json', body: '{"message":"CSRF token mismatch."}' }).catch(() => {});
      return r.continue().catch(() => {});
    });
    await sup.setInputFiles('#tcFile', [file('after-419.zip', 300 * 1024)]);
    await sup.fill('#tcInput', 'after-419-msg');
    await sup.press('#tcInput', 'Enter');
    await sup.waitForFunction(() => document.getElementById('tcInput').value === '', null, { timeout: 20000 }).catch(() => {});
    await sup.unroute('**/admin/team-messages');
    await sleep(800);
    const conv = await convOf(sup);
    const n = (await thread(sup, conv)).filter(m => m.body === 'after-419-msg').length;
    check('R1. expired token: new token fetched, sent once with its file, no reload', n === 1 && posts === 2 && (await stillNoReload(sup)) && (await attNames(sup)).includes('after-419.zip'), { posts, serverCount: n });
  }

  // ---- D. Drafts per chat --------------------------------------------------------------
  if (want('D')) {
    await open(sup, 'Ubaid Dogar'); await head(sup, 'Ubaid Dogar');
    await sup.fill('#tcInput', 'draft for ubaid');
    await sup.setInputFiles('#tcFile', [file('ubaid-file.zip', 1000)]);
    // quote a message
    await sup.evaluate(() => { const m = [...document.querySelectorAll('#tcMessages .tc-msg')].pop(); m.querySelector('.tc-dots').click(); });
    await sup.click('#tcMenu [data-act="reply"]').catch(() => {});
    const replyShown = await sup.evaluate(() => !document.getElementById('tcReply').hidden);
    await open(sup, 'Umair Sajid'); await head(sup, 'Umair Sajid');
    const sajidBox = await sup.inputValue('#tcInput'); const sajidChips = await chips(sup);
    await sup.fill('#tcInput', 'draft for sajid');
    await open(sup, 'Ubaid Dogar'); await head(sup, 'Ubaid Dogar'); await sleep(300);
    const ub = { text: await sup.inputValue('#tcInput'), chips: await chips(sup), reply: await sup.evaluate(() => !document.getElementById('tcReply').hidden) };
    await open(sup, 'Umair Sajid'); await head(sup, 'Umair Sajid'); await sleep(300);
    const sj = await sup.inputValue('#tcInput');
    check('D1. switching chats keeps each chat\'s text, files and quote', sajidBox === '' && sajidChips.length === 0 && ub.text === 'draft for ubaid' && JSON.stringify(ub.chips) === '["ubaid-file.zip"]' && (ub.reply === replyShown) && sj === 'draft for sajid', { sajidBox, sajidChips, ubaid: ub, replyShown, sajid: sj });
    // Reload keeps the text of both.
    await sup.reload({ waitUntil: 'domcontentloaded' }); await sup.waitForSelector('#tcInput'); await sleep(500);
    const afterReload = await sup.inputValue('#tcInput');
    await open(sup, 'Ubaid Dogar'); await head(sup, 'Ubaid Dogar'); await sleep(300);
    const ubAfter = await sup.inputValue('#tcInput');
    check('D2. drafts\' text survives a reload', afterReload === 'draft for sajid' && ubAfter === 'draft for ubaid', { afterReload, ubAfter });
    // Sending clears that chat's draft for good.
    await sup.press('#tcInput', 'Enter');
    await sup.waitForFunction(() => document.getElementById('tcInput').value === '', null, { timeout: 15000 });
    await open(sup, 'Umair Sajid'); await head(sup, 'Umair Sajid');
    await open(sup, 'Ubaid Dogar'); await head(sup, 'Ubaid Dogar'); await sleep(300);
    check('D3. a sent draft does not come back', (await sup.inputValue('#tcInput')) === '');
    await open(sup, 'Umair Sajid'); await head(sup, 'Umair Sajid'); await sup.fill('#tcInput', ''); await sup.dispatchEvent('#tcInput', 'input');
  }

  // ---- C. Typing right after opening survives the background refresh ---------------------
  if (want('C')) {
    await open(sup, 'Raja Khuram'); await head(sup, 'Raja Khuram'); await sleep(600);   // snapshot stored
    await open(sup, 'Umair Sajid'); await head(sup, 'Umair Sajid'); await sleep(600);
    const rajaConv = 3;
    const rajaAdmin = await sup.evaluate(() => [...document.querySelectorAll('a.tc-contact')].find(a => a.querySelector('.tc-c-name')?.textContent.trim() === 'Raja Khuram')?.dataset.peer);
    const raja = await login(browser, 'va2@apex.test');
    await sendApi(raja, { recipient_id: SUPER_ID, body: 'new-while-away' });   // cached copy is now stale
    await sup.route('**/admin/team-messages/open?**', async r => { if (!r.request().url().includes('prefetch=1')) await sleep(1500); return r.continue().catch(() => {}); });
    await open(sup, 'Raja Khuram');
    await sleep(150);
    await sup.fill('#tcInput', 'typed-immediately');
    await sleep(3500);
    await sup.unroute('**/admin/team-messages/open?**');
    const kept = await sup.inputValue('#tcInput');
    const got = (await texts(sup)).includes('new-while-away');
    check('C1. text typed while the chat refreshes after opening is kept', kept === 'typed-immediately' && got, { kept, freshMessageShown: got });
    await sup.fill('#tcInput', ''); await sup.dispatchEvent('#tcInput', 'input');
    await raja.context().close();
  }

  // ---- P. Pin / unpin during an upload: no reload, upload finishes, bar works -------------
  if (want('P')) {
    await open(sup, 'Abid Hussain'); await head(sup, 'Abid Hussain'); await markNoReload(sup); await captureToasts(sup);
    await sup.setInputFiles('#tcFile', [file('pin-upload.zip', 600 * 1024)]);
    const unthrottle = await throttleUpload(sup, 40 * 1024);   // ~15 s
    await sup.fill('#tcInput', 'uploading-while-pinning');
    await sup.press('#tcInput', 'Enter');
    await sleep(1500);
    // pin the latest text message via its menu
    await sup.evaluate(() => { const m = [...document.querySelectorAll('#tcMessages .tc-msg:not(.mine), #tcMessages .tc-msg')].filter(e => e.querySelector('.tc-dots')).shift(); m.querySelector('.tc-dots').click(); });
    await sup.click('#tcMenu [data-act="pin"]');
    await sup.waitForSelector('#tcPinned', { timeout: 15000 }).catch(() => {});
    const pinnedBar = !!(await sup.$('#tcPinned'));
    const pinIcons = await sup.evaluate(() => document.querySelectorAll('#tcMessages .tc-pin-ic').length);
    await sup.waitForFunction(() => [...document.querySelectorAll('#tcMessages .tc-att-name')].some(e => e.textContent.trim() === 'pin-upload.zip'), null, { timeout: 60000 }).catch(() => {});
    await unthrottle();
    const uploaded = (await attNames(sup)).includes('pin-upload.zip');
    check('P1. pinning during an upload: no reload, bar + 📌 appear, upload completes', pinnedBar && pinIcons === 1 && uploaded && (await stillNoReload(sup)), { pinnedBar, pinIcons, uploaded });
    // Full page load → the pinned bar opens on click (double-toggle fixed), then unpin in place.
    await sup.reload({ waitUntil: 'domcontentloaded' }); await sup.waitForSelector('#tcPinnedHead'); await markNoReload(sup);
    await sup.click('#tcPinnedHead'); await sleep(200);
    const opened = await sup.evaluate(() => !document.getElementById('tcPinnedDrop').hidden);
    await sup.click('#tcPinnedDrop [data-unpin]');
    await sup.waitForFunction(() => !document.getElementById('tcPinned'), null, { timeout: 15000 }).catch(() => {});
    const gone = !(await sup.$('#tcPinned')) && (await sup.evaluate(() => document.querySelectorAll('#tcMessages .tc-pin-ic').length)) === 0;
    check('P2. pinned bar opens on click after a full load; unpin updates in place', opened && gone && (await stillNoReload(sup)), { opened, gone });
  }

  // ---- G. Group rename / add / remove during an upload: no reload ------------------------
  if (want('G')) {
    await open(sup, 'CFPB Screenshots'); await head(sup, 'CFPB Screenshots'); await markNoReload(sup);
    await sup.setInputFiles('#tcFile', [file('group-upload.zip', 500 * 1024)]);
    const unthrottle = await throttleUpload(sup, 40 * 1024);
    await sup.fill('#tcInput', 'group-upload-msg'); await sup.press('#tcInput', 'Enter');
    await sleep(1000);
    await sup.click('#tcMembersBtn'); await sleep(300);
    await sup.fill('#tcRenameName', 'CFPB Screens (renamed)'); await sup.click('#tcRenameBtn');
    await head(sup, 'CFPB Screens (renamed)').catch(() => {});
    const rowName = await sup.evaluate(() => [...document.querySelectorAll('a.tc-contact .tc-c-name')].some(e => e.textContent.trim() === 'CFPB Screens (renamed)'));
    const before = await sup.evaluate(() => document.querySelectorAll('#tcMembers .tc-member-row').length);
    await sup.check('#tcMembers .tc-member-add-list input >> nth=0'); await sup.click('#tcAddMembersBtn');
    await sup.waitForFunction(n => document.querySelectorAll('#tcMembers .tc-member-row').length === n + 1, before, { timeout: 15000 }).catch(() => {});
    const after = await sup.evaluate(() => document.querySelectorAll('#tcMembers .tc-member-row').length);
    const modalOpen = await sup.evaluate(() => !document.getElementById('tcMembers').hidden);
    const headCount = await sup.evaluate(() => document.getElementById('tcMembersBtn')?.textContent.trim());
    await sup.waitForFunction(() => [...document.querySelectorAll('#tcMessages .tc-att-name')].some(e => e.textContent.trim() === 'group-upload.zip'), null, { timeout: 60000 }).catch(() => {});
    await unthrottle();
    const uploaded = (await attNames(sup)).includes('group-upload.zip');
    const sysShown = await sup.evaluate(() => [...document.querySelectorAll('#tcMessages .tc-sys')].some(e => /added/.test(e.textContent)));
    check('G1. rename + add member in place during an upload (no reload)', rowName && after === before + 1 && modalOpen && String(after) === headCount && uploaded && sysShown && (await stillNoReload(sup)),
      { rowName, before, after, headCount, modalOpen, uploaded, sysShown });
    // remove the member just added
    await sup.click('#tcMembers .tc-member-remove >> nth=-1');
    await sup.click('#tcConfirmModal button:has-text("Remove")').catch(async () => { await sup.click('#tcConfirmModal .tc-confirm-row button >> nth=-1'); });
    await sup.waitForFunction(n => document.querySelectorAll('#tcMembers .tc-member-row').length === n, before, { timeout: 15000 }).catch(() => {});
    check('G2. removing a member updates in place', (await sup.evaluate(() => document.querySelectorAll('#tcMembers .tc-member-row').length)) === before && (await stillNoReload(sup)));
    await sup.click('#tcMembersClose');
  }

  // ---- A. Profile photo change / remove without a reload ---------------------------------
  if (want('A')) {
    await open(sup, 'Abid Hussain'); await head(sup, 'Abid Hussain'); await markNoReload(sup);
    const png0 = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAIAAAACCAIAAAD91JpzAAAAFklEQVR4nGP8z8DwnwEJMDEgAwYGAHtUAvtXt9cMAAAAAElFTkSuQmCC', 'base64');
    await sup.setInputFiles('#tcFile', [{ name: 'thumb.png', mimeType: 'image/png', buffer: png0 }]);
    await sleep(400);
    const png = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAIAAAACCAIAAAD91JpzAAAAFklEQVR4nGP8z8DwnwEJMDEgAwYGAHtUAvtXt9cMAAAAAElFTkSuQmCC', 'base64');
    // (clicking "Change photo" opens the OS file dialog; setting the input fires the same handler)
    await sup.setInputFiles('#tcAvFile', { name: 'me.png', mimeType: 'image/png', buffer: png });
    await sup.waitForSelector('#tcCropMask:not([hidden])', { timeout: 10000 });
    await sup.click('#tcCropSave');
    await sup.waitForFunction(() => !!document.querySelector('#tcMeAvatar .tc-avatar.has-img img'), null, { timeout: 15000 }).catch(() => {});
    const hasImg = await sup.evaluate(() => ({ me: !!document.querySelector('#tcMeAvatar .tc-avatar.has-img img'), self: !!document.querySelector('.tc-self-av .tc-avatar.has-img img') }));
    await sup.click('#tcMeAvatar'); await sup.click('#tcAvRemove');
    await sup.waitForFunction(() => !document.querySelector('#tcMeAvatar .tc-avatar.has-img'), null, { timeout: 15000 }).catch(() => {});
    const mono = await sup.evaluate(() => document.querySelector('#tcMeAvatar .tc-avatar')?.textContent.trim());
    const thumbOk = await sup.evaluate(async () => { const i = document.querySelector('#tcPending img'); return i ? (i.complete ? i.naturalWidth > 0 : await new Promise(r => { i.onload = () => r(true); i.onerror = () => r(false); })) : null; });
    check('A0. staged image thumbnail renders (blob preview allowed)', thumbOk !== false, { thumbOk });
    check('A1. photo change + remove update in place, no reload', hasImg.me && hasImg.self && mono === 'UA' && (await stillNoReload(sup)), { hasImg, mono });
  }

  // ---- F. Forward a file message → recipient gets the file --------------------------------
  if (want('F')) {
    await open(sup, 'Abid Hussain'); await head(sup, 'Abid Hussain');
    await sup.setInputFiles('#tcFile', [file('forward-me.zip', 2000)]);
    await sup.press('#tcInput', 'Enter');
    await sup.waitForFunction(() => [...document.querySelectorAll('#tcMessages .tc-att-name')].some(e => e.textContent.trim() === 'forward-me.zip'), null, { timeout: 15000 });
    await sup.evaluate(() => { const els = [...document.querySelectorAll('#tcMessages .tc-msg')].filter(e => [...e.querySelectorAll('.tc-att-name')].some(n => n.textContent.trim() === 'forward-me.zip')); els.pop().querySelector('.tc-dots').click(); });
    await sup.click('#tcMenu [data-act="forward"]');
    await sup.click('#tcFwdList .tc-fwd-row:has-text("Mujeeb Rahman")');
    await sleep(1200);
    const mujeeb = await login(browser, 'va1@apex.test');
    await mujeeb.goto(CHAT, { waitUntil: 'domcontentloaded' });
    await open(mujeeb, 'Umair Arshad'); await head(mujeeb, 'Umair Arshad'); await sleep(800);
    const got = await mujeeb.evaluate(() => [...document.querySelectorAll('#tcMessages .tc-msg')].some(m => m.querySelector('.tc-fwd') && [...m.querySelectorAll('.tc-att-name')].some(n => n.textContent.trim() === 'forward-me.zip')));
    check('F1. forwarding a file-only message delivers the file', got);
    results.mujeebErrs = mujeeb.errs;
  }

  // ---- K. A batch of new messages advances the refresh cursor (no re-fetch loop) ----------
  if (want('K')) {
    await open(sup, 'Abid Hussain'); await head(sup, 'Abid Hussain'); await sleep(1500);
    await sup.route('**/admin/team-messages/thread?**', r => r.abort().catch(() => {}));
    await sleep(3200);
    for (let i = 0; i < 3; i++) await sendApi(abid, { recipient_id: SUPER_ID, body: 'batch-' + i });
    await sup.unroute('**/admin/team-messages/thread?**');
    await sup.waitForFunction(() => [...document.querySelectorAll('#tcMessages .tc-text')].some(e => e.textContent.trim() === 'batch-2'), null, { timeout: 15000 });
    await sleep(500);
    const resp = await sup.waitForResponse(r => r.url().includes('/admin/team-messages/thread?'), { timeout: 15000 });
    const again = (await resp.json()).messages.length;
    const dupes = (await texts(sup)).filter(t => /^batch-/.test(t)).length;
    check('K1. after a batch arrives, the next refresh fetches nothing again', again === 0 && dupes === 3, { refetched: again, shown: dupes });
  }

  // ---- V. Leave group → chat list, desktop layout kept ----------------------------------
  if (want('V')) {
    await open(sup, 'Credit Repair CFPB'); await head(sup, 'Credit Repair CFPB');
    await sup.click('#tcMembersBtn'); await sleep(300);
    await sup.click('#tcLeaveBtn');
    await sup.click('#tcConfirmModal .tc-confirm-row button >> nth=-1');
    await sup.waitForFunction(() => !/c=7/.test(location.search), null, { timeout: 15000 }).catch(() => {});
    await sleep(1200);
    const st = await sup.evaluate(() => ({ url: location.href, standalone: /standalone=1/.test(location.search), wrap: !!document.querySelector('.tc-wrap'),
      rowGone: ![...document.querySelectorAll('a.tc-contact .tc-c-name')].some(e => e.textContent.trim() === 'Credit Repair CFPB'), dashboardNav: !!document.querySelector('.pro-sidebar, .pro-nav') }));
    check('V1. leaving a group returns to the chat list in the desktop layout', st.standalone && st.wrap && st.rowGone && !st.dashboardNav, st);
  }

  check('Z. no page errors', !sup.errs.length && !abid.errs.length && !(results.mujeebErrs || []).length, { sup: sup.errs, abid: abid.errs, mujeeb: results.mujeebErrs });
  await browser.close();
  const failed = results.filter(r => !r.ok);
  console.log(`\n${results.length - failed.length}/${results.length} passed`);
  process.exit(failed.length ? 1 : 0);
})().catch(e => { console.error('SCRIPT ERROR', e); process.exit(2); });
