// UI actions phase — downloads, paste, search jump, image arrows, pinned bar.
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
  const ctx = await browser.newContext({ userAgent: UA, viewport: { width: 1300, height: 860 }, acceptDownloads: true });
  const p = await ctx.newPage(); p.errs = []; p.on('pageerror', e => p.errs.push(e.message));
  await p.goto(B + '/admin/chat-login', { waitUntil: 'domcontentloaded' });
  await p.fill('#email', email); await p.fill('#password', 'password123');
  await Promise.all([p.waitForURL(/team-messages/, { timeout: 30000 }), p.click('.submit')]);
  await p.waitForSelector('#tcContacts'); p.ctx = ctx; return p;
}
const row = n => `a.tc-contact:has(.tc-c-name:text-is("${n}"))`;
const open = (p, n) => p.click(row(n));
const head = (p, n, t = 15000) => p.waitForFunction(x => document.querySelector('.tc-thread-head .tc-th-name')?.textContent.trim() === x, n, { timeout: t });
const chips = p => p.evaluate(() => [...document.querySelectorAll('#tcPending .tc-chip-name')].map(e => e.textContent.trim()));
const cards = p => p.evaluate(() => [...document.querySelectorAll('#tcDlDock .tc-dl-card')].map(c => ({ name: c.querySelector('.tc-dl-name')?.textContent, sub: c.querySelector('.tc-dl-sub')?.textContent, fail: c.classList.contains('fail'), done: c.classList.contains('done') })));
const sendFiles = async (p, files) => { await p.setInputFiles('#tcFile', files); await p.press('#tcInput', 'Enter'); };

(async () => {
  const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || chromium.executablePath() });
  const sup = await login(browser, 'super@apex.test');
  await sup.goto(CHAT, { waitUntil: 'domcontentloaded' }); await sup.waitForSelector('#tcContacts');

  // ---- D. Downloads -----------------------------------------------------------------------
  if (want('D')) {
    await open(sup, 'Abid Hussain'); await head(sup, 'Abid Hussain');
    await sendFiles(sup, [
      { name: 'one.zip', mimeType: 'application/zip', buffer: Buffer.alloc(400 * 1024, 1) },
      { name: 'two.zip', mimeType: 'application/zip', buffer: Buffer.alloc(400 * 1024, 2) },
      { name: 'three.zip', mimeType: 'application/zip', buffer: Buffer.alloc(400 * 1024, 3) },
    ]);
    await sup.waitForFunction(() => [...document.querySelectorAll('#tcMessages .tc-att-name')].some(e => e.textContent.trim() === 'three.zip'), null, { timeout: 30000 });

    // D1: an expired session must not be saved as "<the zip>.zip".
    await sup.route('**/admin/team-messages/attachment/**', r =>
      r.fulfill({ status: 200, contentType: 'text/html; charset=UTF-8', body: '<html><body>Sign in to Apex Team Chat</body></html>' }).catch(() => {}));
    const dl1 = sup.waitForEvent('download', { timeout: 4000 }).catch(() => null);
    await sup.click('#tcMessages .tc-att-file >> nth=0');
    const gotFile = await dl1;
    await sup.waitForFunction(() => [...document.querySelectorAll('#tcDlDock .tc-dl-card')].some(c => c.classList.contains('fail')), null, { timeout: 15000 }).catch(() => {});
    const c1 = await cards(sup);
    check('D1. an expired session is not saved as the file', !gotFile && c1.some(c => c.fail && /session ended/i.test(c.sub || '')), { downloaded: !!gotFile, cards: c1 });
    await sup.unroute('**/admin/team-messages/attachment/**');
    await sup.evaluate(() => { document.querySelectorAll('#tcDlDock .tc-dl-x').forEach(b => b.click()); });

    // D2 + D3: "Download selected" runs one at a time, with honest wording.
    let inFlight = 0, maxInFlight = 0;
    await sup.route('**/admin/team-messages/attachment/**', async r => {
      inFlight++; maxInFlight = Math.max(maxInFlight, inFlight);
      await sleep(1200);
      inFlight--;
      return r.continue().catch(() => {});
    });
    await sup.click('.tc-tab[data-tab="files"]');
    await sup.waitForSelector('#tcFilesScroll tr[data-url]', { timeout: 15000 });
    await sup.evaluate(() => { document.querySelectorAll('#tcFilesScroll .tc-fcheck input:not(#tcFilesAll)').forEach(c => { if (!c.checked) c.click(); }); });
    const downloads = [];
    sup.on('download', d => downloads.push(d.suggestedFilename()));
    await sup.click('#tcFilesDownload').catch(async () => { await sup.click('[id*="Download"]'); });
    await sup.waitForFunction(() => [...document.querySelectorAll('#tcDlDock .tc-dl-card')].filter(c => c.classList.contains('done')).length >= 3, null, { timeout: 60000 }).catch(() => {});
    const c2 = await cards(sup);
    await sup.unroute('**/admin/team-messages/attachment/**');
    check('D3. selected files download one at a time (not all into memory at once)', maxInFlight === 1, { maxInFlight, downloads });
    check('D2. the card does not claim "Saved to your Downloads" before it is', c2.length > 0 && c2.every(c => !/^Saved to your Downloads$/.test(c.sub || '')), c2.map(c => c.sub));
    await sup.click('.tc-tab[data-tab="chat"]');
  }

  // ---- E. Paste ---------------------------------------------------------------------------
  if (want('E')) {
    await open(sup, 'Mujeeb Rahman'); await head(sup, 'Mujeeb Rahman');
    const paste = (kind) => sup.evaluate(async (kind) => {
      const dt = new DataTransfer();
      const png = Uint8Array.from(atob('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='), c => c.charCodeAt(0));
      if (kind === 'excel') { dt.setData('text/plain', 'Client\tRound\nJane\t3'); dt.setData('text/html', '<table><tr><td>Jane</td></tr></table>'); dt.items.add(new File([png], '', { type: 'image/png' })); }
      if (kind === 'screenshot') { dt.items.add(new File([png], '', { type: 'image/png' })); }
      if (kind === 'explorer') { dt.setData('text/plain', 'C:\\files\\report.zip'); dt.items.add(new File([new Uint8Array([1, 2, 3])], 'report.zip', { type: 'application/zip' })); }
      const el = document.getElementById('tcInput'); el.focus();
      const ev = new ClipboardEvent('paste', { clipboardData: dt, bubbles: true, cancelable: true });
      el.dispatchEvent(ev);
      return ev.defaultPrevented;
    }, kind);

    await sup.fill('#tcInput', '');
    const excelPrevented = await paste('excel'); await sleep(300);
    const afterExcel = await chips(sup);
    check('E1. pasting from Excel pastes the text, not a picture', !excelPrevented && afterExcel.length === 0, { chips: afterExcel, preventedDefault: excelPrevented });

    const shotPrevented = await paste('screenshot'); await sleep(300);
    const afterShot = await chips(sup);
    check('E2. pasting a screenshot still attaches it', shotPrevented && afterShot.length === 1, { chips: afterShot });
    await sup.evaluate(() => { document.querySelectorAll('#tcPending .tc-chip-x').forEach(b => b.click()); });

    const fileP = await paste('explorer'); await sleep(300);
    const afterFile = await chips(sup);
    check('E3. a file copied from Explorer still attaches', fileP && afterFile.includes('report.zip'), { chips: afterFile });
    await sup.evaluate(() => { document.querySelectorAll('#tcPending .tc-chip-x').forEach(b => b.click()); });
    await sup.fill('#tcInput', '');
  }

  // ---- S. Header search jumps to the message ----------------------------------------------
  if (want('S')) {
    await open(sup, 'Ubaid Dogar'); await head(sup, 'Ubaid Dogar');
    await sup.fill('#tcInput', 'needle-message-to-find'); await sup.press('#tcInput', 'Enter');
    await sup.waitForFunction(() => [...document.querySelectorAll('#tcMessages .tc-text')].some(e => e.textContent.trim() === 'needle-message-to-find'), null, { timeout: 15000 });
    // Bury it under more than a page of newer messages.
    await sup.evaluate(async () => {
      const token = document.querySelector('meta[name="csrf-token"]').content;
      const conv = document.getElementById('tcMessages').dataset.conversation;
      for (let i = 0; i < 60; i++) {
        const fd = new FormData(); fd.append('_token', token); fd.append('conversation_id', conv); fd.append('body', 'filler ' + i);
        await fetch('/admin/team-messages', { method: 'POST', body: fd, headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
      }
    });
    await sup.goto(CHAT, { waitUntil: 'domcontentloaded' }); await sup.waitForSelector('#tcContacts');
    await open(sup, 'Abid Hussain'); await head(sup, 'Abid Hussain');   // start somewhere else

    await sup.fill('#tcHeaderSearch', 'needle-message');
    await sup.waitForSelector('#tcHeaderSearchResults .tc-hs-item', { timeout: 15000 });
    const href = await sup.getAttribute('#tcHeaderSearchResults .tc-hs-item', 'href');
    await sup.click('#tcHeaderSearchResults .tc-hs-item');
    await head(sup, 'Ubaid Dogar', 20000).catch(() => {});
    await sup.waitForFunction(() => [...document.querySelectorAll('#tcMessages .tc-text')].some(e => e.textContent.trim() === 'needle-message-to-find'), null, { timeout: 20000 }).catch(() => {});
    await sleep(900);
    const st = await sup.evaluate(() => {
      const el = [...document.querySelectorAll('#tcMessages .tc-msg')].find(m => (m.querySelector('.tc-text')?.textContent || '').trim() === 'needle-message-to-find');
      if (!el) return { found: false };
      const box = document.getElementById('tcMessages').getBoundingClientRect();
      const r = el.getBoundingClientRect();
      return { found: true, inView: r.top >= box.top - 5 && r.bottom <= box.bottom + 5, flashed: el.classList.contains('tc-flash') || el.dataset.flashed === '1' };
    });
    check('S1. a search result opens the chat at that message, in view', st.found && st.inView, { href, ...st });
  }

  // ---- I. Image arrows from Files / Photos --------------------------------------------------
  if (want('I')) {
    await open(sup, 'Raja Khuram'); await head(sup, 'Raja Khuram');
    const png = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAIAAAACCAIAAAD91JpzAAAAFklEQVR4nGP8z8DwnwEJMDEgAwYGAHtUAvtXt9cMAAAAAElFTkSuQmCC', 'base64');
    for (const n of ['pic-a.png', 'pic-b.png', 'pic-c.png']) {
      await sendFiles(sup, [{ name: n, mimeType: 'image/png', buffer: png }]);
      await sup.waitForFunction(x => [...document.querySelectorAll('#tcMessages .tc-att-img img')].length >= x, ['pic-a.png', 'pic-b.png', 'pic-c.png'].indexOf(n) + 1, { timeout: 20000 }).catch(() => {});
    }
    // Files tab
    await sup.click('.tc-tab[data-tab="files"]');
    await sup.waitForSelector('#tcFilesScroll a[data-lightbox]', { timeout: 15000 });
    await sup.click('#tcFilesScroll a[data-lightbox] >> nth=0');
    await sup.waitForSelector('#tcLightbox:not([hidden])', { timeout: 10000 });
    const first = await sup.getAttribute('#tcLbImg', 'src');
    const arrows = await sup.evaluate(() => ({ prev: !document.getElementById('tcLbPrev').hidden, next: !document.getElementById('tcLbNext').hidden }));
    await sup.click('#tcLbNext');
    const second = await sup.getAttribute('#tcLbImg', 'src');
    check('I1. arrows walk the Files tab images', arrows.next && arrows.prev && first && second && first !== second, { arrows, changed: first !== second });
    await sup.click('#tcLbClose');

    // Photos tab
    await sup.click('.tc-tab[data-tab="photos"]');
    await sup.waitForSelector('#tcPhotosScroll a.tc-photo', { timeout: 15000 });
    const urlBefore = sup.url();
    await sup.click('#tcPhotosScroll a.tc-photo >> nth=0');
    await sup.waitForSelector('#tcLightbox:not([hidden])', { timeout: 10000 }).catch(() => {});
    const openedViewer = await sup.evaluate(() => !document.getElementById('tcLightbox').hidden);
    const p1 = await sup.getAttribute('#tcLbImg', 'src');
    await sup.click('#tcLbNext').catch(() => {});
    const p2 = await sup.getAttribute('#tcLbImg', 'src');
    check('I2. a photo opens the viewer (not the raw image) and arrows work', openedViewer && sup.url() === urlBefore && p1 !== p2, { openedViewer, sameUrl: sup.url() === urlBefore });
    await sup.click('#tcLbClose').catch(() => {});
    await sup.click('.tc-tab[data-tab="chat"]');
  }

  // ---- P. Pinned bar on a normal page load -------------------------------------------------
  if (want('P')) {
    await open(sup, 'Abid Hussain'); await head(sup, 'Abid Hussain');
    await sup.fill('#tcInput', 'pin-me-for-the-bar'); await sup.press('#tcInput', 'Enter');
    await sup.waitForFunction(() => [...document.querySelectorAll('#tcMessages .tc-text')].some(e => e.textContent.trim() === 'pin-me-for-the-bar'), null, { timeout: 15000 });
    await sup.evaluate(() => { const m = [...document.querySelectorAll('#tcMessages .tc-msg')].filter(e => (e.querySelector('.tc-text')||{}).textContent === 'pin-me-for-the-bar').pop(); m.querySelector('.tc-dots').click(); });
    await sup.click('#tcMenu [data-act="pin"]');
    await sup.waitForSelector('#tcPinned', { timeout: 15000 });
    await sup.reload({ waitUntil: 'domcontentloaded' }); await sup.waitForSelector('#tcPinnedHead');
    await sup.click('#tcPinnedHead'); await sleep(250);
    const openState = await sup.evaluate(() => !document.getElementById('tcPinnedDrop').hidden);
    await sup.click('#tcPinnedHead'); await sleep(250);
    const closedState = await sup.evaluate(() => document.getElementById('tcPinnedDrop').hidden);
    check('P1. the pinned bar opens and closes on a normal page load', openState && closedState, { openState, closedState });
  }

  check('Z. no page errors', !sup.errs.length, sup.errs);
  await browser.close();
  const failed = results.filter(r => !r.ok);
  console.log(`\n${results.length - failed.length}/${results.length} passed`);
  process.exit(failed.length ? 1 : 0);
})().catch(e => { console.error('SCRIPT ERROR', e); process.exit(2); });
