// Files/Photos paging: the tabs must not stop dead at the newest page of shared files.
// Needs seed-files.php (205 attachments in "Credit Repair CFPB") — run.cjs does that for this suite.
const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright-core');
const B = process.env.TEST_BASE_URL || 'http://127.0.0.1:8932';
if (!['127.0.0.1', 'localhost'].includes(new URL(B).hostname)) throw Error('Tests require a local disposable server');
const CHAT = B + '/admin/team-messages?standalone=1';
const UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/125 Safari/537.36 ApexDesktop/1.0';
const sleep = ms => new Promise(r => setTimeout(r, ms));
const results = [];
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
const head = (p, n, t = 15000) => p.waitForFunction(x => document.querySelector('.tc-thread-head .tc-th-name')?.textContent.trim() === x, n, { timeout: t });
const fileRows = p => p.locator('#tcFilesScroll .tc-ftable tbody tr').count();

(async () => {
  const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || chromium.executablePath() });
  const sup = await login(browser, 'super@apex.test');
  await sup.goto(CHAT, { waitUntil: 'domcontentloaded' });
  await sup.waitForSelector('#tcContacts');

  await sup.click(row('Credit Repair CFPB'));
  await head(sup, 'Credit Repair CFPB');
  await sup.click('#tcTabs [data-tab="files"]');
  await sup.waitForSelector('#tcFilesScroll .tc-ftable tbody tr', { timeout: 20000 });

  const first = await fileRows(sup);
  check('1. the tab loads one page, not the whole history', first === 200, { rows: first });
  check('2. "Load older files" is offered when there is more', await sup.locator('#tcFilesScroll [data-older-files]').count() === 1);

  await sup.click('#tcFilesScroll [data-older-files]');
  await sup.waitForFunction(() => document.querySelectorAll('#tcFilesScroll .tc-ftable tbody tr').length > 200,
    null, { timeout: 20000 }).catch(() => {});
  const after = await fileRows(sup);
  check('3. the older files are appended, not swapped in', after > first, { before: first, after });
  check('4. the button goes once there is nothing older',
    await sup.locator('#tcFilesScroll [data-older-files]').count() === 0);

  // Paging position belongs to the chat you were in. Without the reset, the next chat asks
  // for files older than THIS chat's oldest and shows none of its own.
  await sup.click('#tcTabs [data-tab="chat"]');
  await sup.click(row('CFPB Screenshots'));
  await head(sup, 'CFPB Screenshots');
  await sup.click('#tcTabs [data-tab="files"]');
  await sleep(1500);
  const otherEmpty = await sup.locator('#tcFilesScroll .tc-panel-empty').count();
  const otherRows = await fileRows(sup);
  check('5. switching chats resets the paging position', otherEmpty === 1 || otherRows > 0, { otherEmpty, otherRows });

  await sup.click('#tcTabs [data-tab="chat"]');
  await sup.click(row('Credit Repair CFPB'));
  await head(sup, 'Credit Repair CFPB');
  await sup.click('#tcTabs [data-tab="files"]');
  await sup.waitForSelector('#tcFilesScroll .tc-ftable tbody tr', { timeout: 20000 });
  const back = await fileRows(sup);
  check('6. coming back starts from the newest page again', back === 200, { rows: back });

  // ---- M. A mention added by EDITING an older message still reaches the person ----------
  // The notification poll walks forward by message id and an edit keeps its id, so this can
  // only work through the second, time-based watermark.
  {
    await sup.goto(CHAT, { waitUntil: 'domcontentloaded' });
    await sup.waitForSelector('#tcContacts');
    await sup.click(row('CFPB Screenshots'));
    await head(sup, 'CFPB Screenshots');
    await sup.fill('#tcInput', 'someone please look at this');
    await sup.press('#tcInput', 'Enter');
    await sup.waitForFunction(() => document.getElementById('tcInput').value === '', null, { timeout: 20000 });
    await sleep(800);
    const msgId = await sup.evaluate(() => {
      const all = document.querySelectorAll('.tc-msg[data-id]');
      return all.length ? Number(all[all.length - 1].dataset.id) : 0;
    });

    // Abid is caught up: his poll has already walked past that message's id.
    const abid = await login(browser, 'va0@apex.test');
    const polls = [];
    const delivered = [];
    abid.on('request', r => { if (/team-messages\/notifications/.test(r.url())) polls.push(r.url()); });
    abid.on('response', async r => {
      if (!/team-messages\/notifications/.test(r.url())) return;
      try { (((await r.json()) || {}).messages || []).forEach(m => delivered.push(m)); } catch (e) {}
    });
    await abid.goto(CHAT, { waitUntil: 'domcontentloaded' });
    await abid.waitForSelector('#tcContacts');
    await abid.waitForFunction(() => {
      const k = Object.keys(localStorage).find(x => x.indexOf('apex-team-last-mention:u') === 0);
      return !!(k && localStorage.getItem(k));
    }, null, { timeout: 30000 }).catch(() => {});
    await sleep(6000);
    check('M1. the poll carries the mention watermark', polls.some(u => /mAfter=/.test(u)),
      { polls: polls.slice(-2) });
    delivered.length = 0;

    // Now super edits that same, older message to add the mention.
    await sup.evaluate(async (id) => {
      await fetch('/admin/team-messages/' + id, {
        method: 'POST', credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
        body: JSON.stringify({ _method: 'PUT', body: 'someone please look at this @Abid Hussain', mentions: [] }),
      });
    }, msgId);

    const got = await abid.waitForFunction(() => true, null, { timeout: 100 }).then(async () => {
      for (let i = 0; i < 40; i++) {
        if (delivered.some(m => Number(m.id) === msgId && m.editedMention && m.mention)) return true;
        await sleep(1000);
      }
      return false;
    });
    check('M2. the mention is delivered although the message is older than their watermark', got,
      { msgId, delivered: delivered.map(m => ({ id: m.id, mention: m.mention, editedMention: m.editedMention })) });
    check('M3. no page errors for the person being mentioned', abid.errs.length === 0, abid.errs);
  }

  check('Z. no page errors', sup.errs.length === 0, sup.errs);

  await browser.close();
  const failed = results.filter(r => !r.ok).length;
  console.log('\n' + (results.length - failed) + '/' + results.length + ' passed');
  process.exitCode = failed ? 1 : 0;
})().catch(e => { console.error(e); process.exitCode = 2; });
