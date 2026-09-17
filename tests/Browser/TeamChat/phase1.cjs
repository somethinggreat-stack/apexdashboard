// Phase 1 browser checks — message integrity & wrong-chat bugs.
const path = require('path');
const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright-core');
const B = process.env.TEST_BASE_URL || 'http://127.0.0.1:8932';
if (!['127.0.0.1', 'localhost'].includes(new URL(B).hostname)) throw Error('Tests require a local disposable server');
const CHAT = B + '/admin/team-messages?standalone=1';
const UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/125 Safari/537.36 ApexDesktop/1.0';
const sleep = ms => new Promise(r => setTimeout(r, ms));
const results = [];
function check(name, ok, detail) { results.push({ name, ok: !!ok, detail }); console.log((ok ? 'PASS ' : 'FAIL ') + name + (detail ? '  ' + JSON.stringify(detail) : '')); }

async function login(browser, email) {
  const ctx = await browser.newContext({ userAgent: UA, viewport: { width: 1300, height: 820 } });
  const p = await ctx.newPage();
  p.errs = [];
  p.on('pageerror', e => p.errs.push(e.message));
  await p.goto(B + '/admin/chat-login', { waitUntil: 'domcontentloaded' });
  await p.fill('#email', email); await p.fill('#password', 'password123');
  await Promise.all([p.waitForURL(/team-messages/, { timeout: 30000 }), p.click('.submit')]);
  await p.waitForSelector('#tcContacts', { timeout: 30000 });
  return p;
}
const rowByName = (name) => `a.tc-contact:has(.tc-c-name:text-is("${name}"))`;
async function openRow(p, name) { await p.click(rowByName(name)); }
async function waitHeader(p, name, t = 15000) {
  await p.waitForFunction(n => { const h = document.querySelector('.tc-thread-head .tc-th-name'); return h && h.textContent.trim() === n; }, name, { timeout: t });
}
async function headerName(p) { return p.evaluate(() => (document.querySelector('.tc-thread-head .tc-th-name') || {}).textContent?.trim()); }
async function msgTexts(p) { return p.evaluate(() => [...document.querySelectorAll('#tcMessages .tc-msg .tc-text')].map(e => e.textContent.trim())); }
async function msgIds(p) { return p.evaluate(() => [...document.querySelectorAll('#tcMessages [data-id]')].map(e => +e.dataset.id)); }
// Post a message as the page's user through the real endpoint.
async function apiSend(p, fields) {
  return p.evaluate(async (f) => {
    const fd = new FormData();
    fd.append('_token', document.querySelector('meta[name="csrf-token"]').content);
    for (const k in f) fd.append(k, f[k]);
    const r = await fetch('/admin/team-messages', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } });
    return r.json();
  }, fields);
}
async function apiThread(p, conv) {
  return p.evaluate(async (c) => (await (await fetch('/admin/team-messages/thread?c=' + c + '&after=0', { headers: { 'Accept': 'application/json' } })).json()).messages, conv);
}
async function type(p, text) { await p.click('#tcInput'); await p.fill('#tcInput', text); }

(async () => {
  const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || chromium.executablePath() });
  const sup = await login(browser, 'super@apex.test');
  const abid = await login(browser, 'va0@apex.test');
  const SUPER_ID = await abid.evaluate(() => [...document.querySelectorAll('a.tc-contact')].find(a => a.querySelector('.tc-c-name')?.textContent.trim() === 'Umair Arshad')?.dataset.peer);
  if (!SUPER_ID) throw new Error('super id not found');
  const sent = async (p, f) => { const r = await apiSend(p, f); if (!r || !r.ok) throw new Error('api send failed ' + JSON.stringify(r)); return r; };
  await sup.goto(CHAT, { waitUntil: 'domcontentloaded' });
  await sup.waitForSelector('#tcContacts');

  // ---- 1. Double Enter sends once ------------------------------------------------------
  {
    await openRow(sup, 'Abid Hussain'); await waitHeader(sup, 'Abid Hussain');
    const bodies = [];
    await sup.route('**/admin/team-messages', async (route) => {
      if (route.request().method() !== 'POST') return route.continue().catch(()=>{});
      bodies.push(route.request().postData() || '');
      await sleep(1500); return route.continue().catch(()=>{});
    });
    await type(sup, 'double-enter-test');
    await sup.press('#tcInput', 'Enter'); await sup.press('#tcInput', 'Enter'); await sup.press('#tcInput', 'Enter');
    const ro = await sup.evaluate(() => document.getElementById('tcInput').readOnly);
    await sup.waitForFunction(() => !document.getElementById('tcInput').readOnly, null, { timeout: 15000 }).catch(()=>{});
    await sup.unroute('**/admin/team-messages');
    await sleep(800);
    const conv = await sup.evaluate(() => document.getElementById('tcMessages').dataset.conversation);
    const server = (await apiThread(sup, conv)).filter(m => m.body === 'double-enter-test').length;
    const shown = (await msgTexts(sup)).filter(t => t === 'double-enter-test').length;
    check('1. triple Enter = one request, one message', bodies.length === 1 && server === 1 && shown === 1, { requests: bodies.length, server, shown, readOnlyWhileSending: ro });
    check('1b. composer cleared after send', (await sup.inputValue('#tcInput')) === '');
  }

  // ---- 2. Retry after a failed send re-uses the id → no duplicate -------------------------
  {
    let n = 0; const uuids = [];
    await sup.route('**/admin/team-messages', async (route) => {
      if (route.request().method() !== 'POST') return route.continue().catch(()=>{});
      n++;
      const m = (route.request().postData() || '').match(/name="client_uuid"\r\n\r\n([^\r]+)/); uuids.push(m && m[1]);
      if (n === 1) { await route.fetch(); return route.abort('connectionreset').catch(()=>{}); }   // server saved it, reply lost
      return route.continue().catch(()=>{});
    });
    await type(sup, 'retry-test');
    await sup.press('#tcInput', 'Enter');
    await sup.waitForFunction(() => !document.getElementById('tcInput').readOnly, null, { timeout: 15000 }).catch(()=>{});
    await sleep(300);
    const kept = await sup.inputValue('#tcInput');
    await sup.press('#tcInput', 'Enter');
    await sup.waitForFunction(() => document.getElementById('tcInput').value === '', null, { timeout: 15000 }).catch(()=>{});
    await sup.unroute('**/admin/team-messages');
    await sleep(3500);
    const conv = await sup.evaluate(() => document.getElementById('tcMessages').dataset.conversation);
    const server = (await apiThread(sup, conv)).filter(m => m.body === 'retry-test').length;
    const shown = (await msgTexts(sup)).filter(t => t === 'retry-test').length;
    check('2. lost reply + retry = one message (same client id)', server === 1 && shown === 1 && uuids[0] && uuids[0] === uuids[1] && kept === 'retry-test',
      { requests: n, sameId: uuids[0] === uuids[1], server, shown, textKeptAfterFailure: kept });
  }

  // ---- 3. Slow send, switch chat: nothing lands in the new chat ---------------------------
  {
    await sup.route('**/admin/team-messages', async (route) => {
      if (route.request().method() !== 'POST') return route.continue().catch(()=>{});
      await sleep(2500); return route.continue().catch(()=>{});
    });
    await type(sup, 'slow-send-to-abid');
    await sup.press('#tcInput', 'Enter');
    await sleep(200);
    await openRow(sup, 'Mujeeb Rahman'); await waitHeader(sup, 'Mujeeb Rahman');
    const roInB = await sup.evaluate(() => document.getElementById('tcInput').readOnly);
    await type(sup, 'typing in mujeeb');
    await sleep(4000);
    await sup.unroute('**/admin/team-messages');
    const inB = (await msgTexts(sup)).includes('slow-send-to-abid');
    const draftB = await sup.inputValue('#tcInput');
    const abidPreview = await sup.evaluate(n => { const r = [...document.querySelectorAll('a.tc-contact')].find(a => a.querySelector('.tc-c-name')?.textContent.trim() === n); return r && r.querySelector('[data-preview-text]')?.textContent; }, 'Abid Hussain');
    check('3. slow send then switch: not shown in other chat, other chat composer untouched', !inB && draftB === 'typing in mujeeb' && !roInB,
      { shownInMujeeb: inB, mujeebDraft: draftB, mujeebReadOnly: roInB, abidPreview });
    await openRow(sup, 'Abid Hussain'); await waitHeader(sup, 'Abid Hussain'); await sleep(1500);
    check('3b. the message is in the right chat', (await msgTexts(sup)).filter(t => t === 'slow-send-to-abid').length === 1);
    await sup.fill('#tcInput', '');
  }

  // ---- 4. Slow open of A, then B: B stays on screen ---------------------------------------
  {
    await sup.evaluate(async () => { try { indexedDB.deleteDatabase('apexChat'); } catch (e) {} });
    await sup.goto(CHAT, { waitUntil: 'domcontentloaded' }); await sup.waitForSelector('#tcContacts');
    await openRow(sup, 'Ubaid Dogar'); await waitHeader(sup, 'Ubaid Dogar');
    await sleep(2500);   // let idle prefetch settle
    await sup.route('**/admin/team-messages/open?**', async (route) => {
      const u = route.request().url();
      if (u.includes('prefetch=1')) return route.abort().catch(()=>{});
      if (u.includes('c=3')) { await sleep(2500); }   // Raja's DM (conv 3) is slow
      return route.continue().catch(()=>{});
    });
    await sup.evaluate(async () => { try { indexedDB.deleteDatabase('apexChat'); } catch (e) {} });
    await openRow(sup, 'Raja Khuram');
    await sleep(150);
    await openRow(sup, 'Umair Sajid');
    await sleep(5000);
    const h = await headerName(sup);
    const active = await sup.evaluate(() => document.querySelector('a.tc-contact.active .tc-c-name')?.textContent.trim());
    check('4. click A (slow) then B: ends on B', h === 'Umair Sajid' && active === 'Umair Sajid', { header: h, activeRow: active, url: sup.url() });
    await sup.unroute('**/admin/team-messages/open?**');
  }

  // ---- 5. A late thread poll for A never lands in B -------------------------------------
  {
    await openRow(sup, 'Abid Hussain'); await waitHeader(sup, 'Abid Hussain'); await sleep(1200);
    const convA = await sup.evaluate(() => document.getElementById('tcMessages').dataset.conversation);
    await sup.route('**/admin/team-messages/thread?**', async (route) => {
      if (route.request().url().includes('c=' + convA + '&')) await sleep(3000);
      return route.continue().catch(()=>{});
    });
    await sleep(3500);   // a poll for A is now in flight (held)
    await sent(abid, { recipient_id: SUPER_ID, body: 'late-poll-from-abid' });
    await sleep(300);
    await openRow(sup, 'Mujeeb Rahman'); await waitHeader(sup, 'Mujeeb Rahman');
    await sleep(5000);
    await sup.unroute('**/admin/team-messages/thread?**');
    const inB = (await msgTexts(sup)).includes('late-poll-from-abid');
    check('5. late poll for A does not add A\'s message to B', !inB, { leakedIntoMujeeb: inB });
  }

  // ---- 6. Opening a never-messaged teammate: previous chat is no longer "open" -------------
  {
    await openRow(sup, 'Abid Hussain'); await waitHeader(sup, 'Abid Hussain'); await sleep(800);
    await openRow(sup, 'Sanwal Khan'); await waitHeader(sup, 'Sanwal Khan'); await sleep(800);
    await sent(abid, { recipient_id: SUPER_ID, body: 'while-you-are-on-sanwal' });
    await sup.waitForFunction(n => {
      const r = [...document.querySelectorAll('a.tc-contact')].find(a => a.querySelector('.tc-c-name')?.textContent.trim() === n);
      return r && r.querySelector('[data-badge]');
    }, 'Abid Hussain', { timeout: 20000 }).catch(() => {});
    const st = await sup.evaluate(n => {
      const r = [...document.querySelectorAll('a.tc-contact')].find(a => a.querySelector('.tc-c-name')?.textContent.trim() === n);
      return { badge: r?.querySelector('[data-badge]')?.textContent, preview: r?.querySelector('[data-preview-text]')?.textContent };
    }, 'Abid Hussain');
    check('6. previous chat keeps badge + preview while on a never-messaged teammate', st.badge && st.preview === 'while-you-are-on-sanwal', st);
  }

  // ---- 7. First message in a new group doesn't take over the creator's DM row -------------
  {
    const san = await login(browser, 'va5@apex.test');
    await san.goto(CHAT, { waitUntil: 'domcontentloaded' }); await san.waitForSelector('#tcContacts');
    await sleep(6000);   // let Sanwal's notifier take its first (baseline) poll
    const sanId = await san.evaluate(() => fetch('/admin/team-messages/presence').then(() => null));
    const sanAdminId = await sup.evaluate(n => [...document.querySelectorAll('a.tc-contact')].find(a => a.querySelector('.tc-c-name')?.textContent.trim() === n)?.dataset.peer, 'Sanwal Khan');
    const gid = await sup.evaluate(async (id) => {
      const r = await fetch('/admin/team-messages/group', { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }, body: JSON.stringify({ name: 'Brand New Group', members: [+id] }) });
      return (await r.json()).conversation_id;
    }, sanAdminId);
    await sent(sup, { conversation_id: gid, body: 'first-group-message' });
    await san.waitForFunction(() => [...document.querySelectorAll('a.tc-contact')].some(a => a.querySelector('.tc-c-name')?.textContent.trim() === 'Brand New Group'), null, { timeout: 20000 }).catch(() => {});
    const st = await san.evaluate(() => {
      const rows = [...document.querySelectorAll('a.tc-contact')];
      const dm = rows.find(a => a.querySelector('.tc-c-name')?.textContent.trim() === 'Umair Arshad');
      const g = rows.find(a => a.querySelector('.tc-c-name')?.textContent.trim() === 'Brand New Group');
      return { dmConv: dm?.dataset.conversation || null, dmPreview: dm?.querySelector('[data-preview-text]')?.textContent || null,
               groupRow: !!g, groupConv: g?.dataset.conversation, groupPreview: g?.querySelector('[data-preview-text]')?.textContent, groupBadge: g?.querySelector('[data-badge]')?.textContent };
    });
    check('7. new group gets its own row; creator DM row untouched', !st.dmConv && st.groupRow && String(st.groupConv) === String(gid) && /first-group-message/.test(st.groupPreview || ''), st);
    // Clicking the new row opens the group.
    await openRow(san, 'Brand New Group').catch(()=>{}); await waitHeader(san, 'Brand New Group').catch(()=>{});
    check('7b. the new group row opens the group', (await msgTexts(san)).includes('first-group-message'));
    san.errsCopy = san.errs;
    results.sanErrs = san.errs;
  }

  // ---- 8. Teammate's message sent just before mine isn't skipped, and order is kept --------
  {
    await openRow(sup, 'Abid Hussain'); await waitHeader(sup, 'Abid Hussain'); await sleep(1500);
    await sup.route('**/admin/team-messages/thread?**', route => route.abort().catch(()=>{}));   // polls blocked
    await sleep(3200);
    const t = await sent(abid, { recipient_id: SUPER_ID, body: 'teammate-before-mine' });
    await type(sup, 'mine-after-teammate');
    await sup.press('#tcInput', 'Enter');
    await sup.waitForFunction(() => document.getElementById('tcInput').value === '', null, { timeout: 15000 }).catch(()=>{});
    const shownEarly = (await msgTexts(sup)).includes('teammate-before-mine');
    await sup.unroute('**/admin/team-messages/thread?**');
    await sup.waitForFunction(() => [...document.querySelectorAll('#tcMessages .tc-text')].some(e => e.textContent.trim() === 'teammate-before-mine'), null, { timeout: 15000 }).catch(() => {});
    const texts = await msgTexts(sup);
    const ids = await msgIds(sup);
    const sorted = [...ids].sort((a, b) => a - b);
    const iT = texts.indexOf('teammate-before-mine'), iM = texts.indexOf('mine-after-teammate');
    check('8. teammate message just before my send still appears, in order', iT >= 0 && iM > iT && JSON.stringify(ids) === JSON.stringify(sorted),
      { teammateShownBeforeUnblock: shownEarly, teammateIndex: iT, mineIndex: iM, idsSorted: JSON.stringify(ids) === JSON.stringify(sorted) });
  }

  // ---- 9. No JS errors ----------------------------------------------------------------------
  check('9. no page errors (super, abid, sanwal)', !sup.errs.length && !abid.errs.length && !(results.sanErrs || []).length, { sup: sup.errs, abid: abid.errs, san: results.sanErrs });

  await browser.close();
  const failed = results.filter(r => !r.ok);
  console.log(`\n${results.length - failed.length}/${results.length} passed`);
  process.exit(failed.length ? 1 : 0);
})().catch(e => { console.error('SCRIPT ERROR', e); process.exit(2); });
