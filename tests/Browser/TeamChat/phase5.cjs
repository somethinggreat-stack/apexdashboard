// Phase 5 browser checks — groups, mentions, pins & membership.
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
const texts = p => p.evaluate(() => [...document.querySelectorAll('#tcMessages .tc-msg .tc-text')].map(e => e.textContent.trim()));
const rowNames = p => p.evaluate(() => [...document.querySelectorAll('a.tc-contact .tc-c-name')].map(e => e.textContent.trim()));
const captureToasts = p => p.evaluate(() => { window.__toasts = []; const o = window.apexToast; window.apexToast = function (t) { window.__toasts.push(String(t)); if (o) return o.apply(this, arguments); }; });
const toasts = p => p.evaluate(() => window.__toasts || []);
const sendApi = (p, f) => p.evaluate(async f => { const fd = new FormData(); fd.append('_token', document.querySelector('meta[name="csrf-token"]').content); for (const k in f) fd.append(k, f[k]); const r = await fetch('/admin/team-messages', { method: 'POST', body: fd, headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } }); const j = await r.json(); if (!j.ok) throw new Error(JSON.stringify(j)); return j; }, f);
// Open the 3-dots menu of the message whose text matches, then click an action.
async function msgAction(p, text, act) {
  await p.evaluate(t => {
    const el = [...document.querySelectorAll('#tcMessages .tc-msg')].filter(m => (m.querySelector('.tc-text')?.textContent || '').trim() === t).pop();
    el.querySelector('.tc-dots').click();
  }, text);
  await p.click(`#tcMenu [data-act="${act}"]`);
}

(async () => {
  const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || chromium.executablePath() });
  const sup = await login(browser, 'super@apex.test');
  const abid = await login(browser, 'va0@apex.test');
  await sup.goto(CHAT, { waitUntil: 'domcontentloaded' }); await sup.waitForSelector('#tcContacts');
  await captureToasts(sup);

  // ---- C. Create group while a group chat is open ---------------------------------------
  if (want('C')) {
    await open(sup, 'CFPB Screenshots'); await head(sup, 'CFPB Screenshots');   // a GROUP is open
    await sup.click('#tcNewGroup');
    await sup.fill('#tcNewGroupName', 'Made While A Group Was Open');
    await sup.click('#tcGroupModal .tc-group-members input >> nth=0');
    await sup.click('#tcGroupCreate');
    await head(sup, 'Made While A Group Was Open', 20000).catch(() => {});
    const st = await sup.evaluate(() => ({ header: document.querySelector('.tc-thread-head .tc-th-name')?.textContent.trim(), url: location.search }));
    check('C1. "Create group" works while another group chat is open', st.header === 'Made While A Group Was Open' && /c=\d+/.test(st.url), st);
    check('C2. the new group is in the sidebar', (await rowNames(sup)).includes('Made While A Group Was Open'));
    // The open group's header name is untouched by the modal's field.
    await open(sup, 'CFPB Screenshots'); await head(sup, 'CFPB Screenshots');
    await sup.click('#tcNewGroup');
    const boxes = await sup.evaluate(() => ({ modalInput: document.querySelector('#tcGroupModal #tcNewGroupName')?.value, header: document.querySelector('.tc-thread-head .tc-th-name')?.textContent.trim() }));
    check('C3. the modal field is separate from the open group\'s name', boxes.modalInput === '' && boxes.header === 'CFPB Screenshots', boxes);
    await sup.click('#tcGroupClose');
  }

  // ---- M. Editing keeps mentions ---------------------------------------------------------
  if (want('M')) {
    await open(sup, 'CFPB Screenshots'); await head(sup, 'CFPB Screenshots');
    await sup.fill('#tcInput', 'ping @Abid Hussain');
    // pick the mention from the autocomplete so it is sent as an id
    await sup.evaluate(() => { const i = document.getElementById('tcInput'); i.value = 'ping @Abid'; i.dispatchEvent(new Event('input')); });
    await sup.waitForSelector('#tcMentionPop:not([hidden])', { timeout: 8000 }).catch(() => {});
    await sup.click('#tcMentionPop .tc-mention-opt >> nth=0').catch(() => {});
    await sup.press('#tcInput', 'Enter');
    await sup.waitForFunction(() => [...document.querySelectorAll('#tcMessages .tc-mention')].length > 0, null, { timeout: 15000 });
    const before = await sup.evaluate(() => [...document.querySelectorAll('#tcMessages .tc-msg')].pop().querySelector('.tc-text').textContent.trim());

    await msgAction(sup, before, 'edit');
    await sup.evaluate(() => { const i = document.getElementById('tcInput'); i.value = i.value + ' today'; });
    await sup.press('#tcInput', 'Enter');
    await sleep(1500);
    const after = await sup.evaluate(() => {
      const el = [...document.querySelectorAll('#tcMessages .tc-msg')].pop();
      return { text: el.querySelector('.tc-text').textContent.trim(), mentions: [...el.querySelectorAll('.tc-mention')].map(m => m.textContent.trim()) };
    });
    check('M1. an edit keeps the @mention highlighted and recorded', /today$/.test(after.text) && after.mentions.length === 1, after);

    // Abid still has the mention badge on that group.
    await abid.goto(CHAT, { waitUntil: 'domcontentloaded' }); await abid.waitForSelector('#tcContacts');
    const badge = await abid.evaluate(() => {
      const r = [...document.querySelectorAll('a.tc-contact')].find(a => a.querySelector('.tc-c-name')?.textContent.trim() === 'CFPB Screenshots');
      return !!r?.querySelector('.tc-mention-badge');
    });
    check('M2. the mention still counts for the mentioned VA', badge);
  }

  // ---- P. Pins: deleting a pinned message clears the pin -----------------------------------
  if (want('P')) {
    await open(sup, 'Mujeeb Rahman'); await head(sup, 'Mujeeb Rahman');
    await sup.fill('#tcInput', 'pin-then-delete'); await sup.press('#tcInput', 'Enter');
    await sup.waitForFunction(() => [...document.querySelectorAll('#tcMessages .tc-text')].some(e => e.textContent.trim() === 'pin-then-delete'), null, { timeout: 15000 });
    await msgAction(sup, 'pin-then-delete', 'pin');
    await sup.waitForSelector('#tcPinned', { timeout: 15000 });
    await msgAction(sup, 'pin-then-delete', 'delete').catch(async () => {
      await sup.evaluate(() => { const el = [...document.querySelectorAll('#tcMessages .tc-msg')].pop(); el.querySelector('.tc-dots').click(); });
      await sup.click('#tcMenu [data-act="delete"]');
    });
    await sup.click('#tcDeleteModal button:has-text("Delete for everyone")').catch(async () => { await sup.click('#tcDeleteModal .tc-confirm-row button >> nth=-1'); });
    await sleep(2500);
    const st = await sup.evaluate(() => ({ bar: !!document.getElementById('tcPinned'), icons: document.querySelectorAll('#tcMessages .tc-pin-ic').length, attachmentGhost: (document.getElementById('tcPinned')?.textContent || '').includes('Attachment') }));
    check('P1. deleting a pinned message removes the pin (no "📎 Attachment" ghost)', !st.bar && st.icons === 0 && !st.attachmentGhost, st);
    // Reload: the pin stays gone and nothing is left to fail on unpin.
    await sup.reload({ waitUntil: 'domcontentloaded' }); await sup.waitForSelector('#tcMessages'); await sleep(600);
    check('P2. still gone after a reload', !(await sup.$('#tcPinned')));
  }

  // ---- R. Removed from a group: a clear state, not silence ---------------------------------
  if (want('R')) {
    const gid = await sup.evaluate(async () => {
      const abidRow = [...document.querySelectorAll('a.tc-contact')].find(a => a.querySelector('.tc-c-name')?.textContent.trim() === 'Abid Hussain');
      const r = await fetch('/admin/team-messages/group', { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }, body: JSON.stringify({ name: 'Kick Test', members: [Number(abidRow.dataset.peer)] }) });
      return (await r.json()).conversation_id;
    });
    const abidId = await sup.evaluate(() => Number([...document.querySelectorAll('a.tc-contact')].find(a => a.querySelector('.tc-c-name')?.textContent.trim() === 'Abid Hussain').dataset.peer));
    await sendApi(sup, { conversation_id: gid, body: 'members only' });

    await abid.goto(CHAT + '&c=' + gid, { waitUntil: 'domcontentloaded' }).catch(() => {});
    await abid.goto(B + '/admin/team-messages?c=' + gid + '&standalone=1', { waitUntil: 'domcontentloaded' });
    await abid.waitForSelector('#tcMessages'); await captureToasts(abid); await sleep(1000);
    const sawIt = (await texts(abid)).includes('members only');

    // Super removes Abid while his chat is open.
    await sup.evaluate(async ([gid, abidId]) => {
      const fd = new FormData(); fd.append('_token', document.querySelector('meta[name="csrf-token"]').content); fd.append('_method', 'DELETE');
      await fetch('/admin/team-messages/group/' + gid + '/members/' + abidId, { method: 'POST', body: fd, headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
    }, [gid, abidId]);

    await abid.waitForFunction(() => (window.__toasts || []).some(t => /no longer/i.test(t)), null, { timeout: 20000 }).catch(() => {});
    await sleep(1200);
    const st = await abid.evaluate(() => ({
      toasts: window.__toasts || [],
      notice: document.querySelector('#tcMessages .tc-thread-empty')?.textContent.trim() || '',
      rowGone: ![...document.querySelectorAll('a.tc-contact .tc-c-name')].some(e => e.textContent.trim() === 'Kick Test'),
      composerOff: document.getElementById('tcInput').disabled,
      oldMessagesGone: ![...document.querySelectorAll('#tcMessages .tc-text')].some(e => e.textContent.trim() === 'members only'),
    }));
    check('R1. removal shows a plain notice, clears the chat and the row', sawIt && /no longer/i.test(st.notice) && st.rowGone && st.composerOff && st.oldMessagesGone && st.toasts.some(t => /no longer/i.test(t)), st);
    check('R2. no raw model error text anywhere', !JSON.stringify(st).includes('No query results'), st.toasts);

    // Reopening that group from a stale cache/URL doesn't show its content.
    await abid.goto(B + '/admin/team-messages?c=' + gid + '&standalone=1', { waitUntil: 'domcontentloaded' });
    await abid.waitForSelector('#tcContacts'); await sleep(800);
    const after = await abid.evaluate(() => ({ shown: [...document.querySelectorAll('#tcMessages .tc-text')].map(e => e.textContent.trim()), header: document.querySelector('.tc-thread-head .tc-th-name')?.textContent.trim() || null }));
    check('R3. the removed group cannot be reopened from its URL', !after.shown.includes('members only'), after);
  }

  check('Z. no page errors', !sup.errs.length && !abid.errs.length, { sup: sup.errs, abid: abid.errs });
  await browser.close();
  const failed = results.filter(r => !r.ok);
  console.log(`\n${results.length - failed.length}/${results.length} passed`);
  process.exit(failed.length ? 1 : 0);
})().catch(e => { console.error('SCRIPT ERROR', e); process.exit(2); });
