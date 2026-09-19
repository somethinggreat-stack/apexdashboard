// Team Chat runs on Pakistan time whatever the PC's clock says.
// The browser is deliberately run in a timezone that is on a DIFFERENT CALENDAR DAY from
// Pakistan right now, so anything still reading the machine's own clock shows up immediately.
const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright-core');
const B = process.env.TEST_BASE_URL || 'http://127.0.0.1:8932';
if (!['127.0.0.1', 'localhost'].includes(new URL(B).hostname)) throw Error('Tests require a local disposable server');
const CHAT = B + '/admin/team-messages?standalone=1';
const UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/125 Safari/537.36 ApexDesktop/1.0';
const PK = 'Asia/Karachi';
const sleep = ms => new Promise(r => setTimeout(r, ms));
const results = [];
function check(name, ok, detail) { results.push({ name, ok: !!ok }); console.log((ok ? 'PASS ' : 'FAIL ') + name + (detail !== undefined ? '  ' + JSON.stringify(detail) : '')); }

const dayIn = (tz, d) => d.toLocaleDateString('en-CA', { timeZone: tz });
const clockIn = (tz, d) => d.toLocaleTimeString('en-US', { timeZone: tz, hour: 'numeric', minute: '2-digit' });

// A timezone that is on a different DATE from Pakistan at this moment. Kiritimati (UTC+14) and
// Midway (UTC-11) are 25 hours apart, so one of them always is.
function foreignZone() {
  const now = new Date(), pk = dayIn(PK, now);
  for (const tz of ['Pacific/Kiritimati', 'Pacific/Midway']) if (dayIn(tz, now) !== pk) return tz;
  throw Error('no zone on a different date than Pakistan — impossible, check the clock');
}

async function login(browser, email, tz) {
  const ctx = await browser.newContext({ userAgent: UA, viewport: { width: 1300, height: 860 }, timezoneId: tz });
  const p = await ctx.newPage(); p.errs = []; p.on('pageerror', e => p.errs.push(e.message));
  await p.goto(B + '/admin/chat-login', { waitUntil: 'domcontentloaded' });
  await p.fill('#email', email); await p.fill('#password', 'password123');
  await Promise.all([p.waitForURL(/team-messages/, { timeout: 30000 }), p.click('.submit')]);
  await p.waitForSelector('#tcContacts'); p.ctx = ctx; return p;
}
const row = n => `a.tc-contact:has(.tc-c-name:text-is("${n}"))`;
const head = (p, n, t = 15000) => p.waitForFunction(x => document.querySelector('.tc-thread-head .tc-th-name')?.textContent.trim() === x, n, { timeout: t });

(async () => {
  const TZ = foreignZone();
  const now = new Date();
  console.log('browser timezone: ' + TZ + ' (date there: ' + dayIn(TZ, now) + ', in Pakistan: ' + dayIn(PK, now) + ')');

  const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || chromium.executablePath() });
  const sup = await login(browser, 'super@apex.test', TZ);

  // The PC really is in the other zone, so a failure below is the app, not the harness.
  const pcZone = await sup.evaluate(() => Intl.DateTimeFormat().resolvedOptions().timeZone);
  check('0. the browser really is in the foreign timezone', pcZone === TZ, { pcZone, TZ });
  // The chat script is an IIFE, so its TZ isn't reachable from here — read it off the page
  // source instead. Checks 4 and 5 below are what actually prove the browser honours it.
  const declared = (await sup.content()).match(/var TZ = '([^']+)'/);
  check('0b. the page is told to use Pakistan time', declared && declared[1].replace('\\/', '/') === PK,
    { declared: declared && declared[1] });

  await sup.goto(CHAT, { waitUntil: 'domcontentloaded' });
  await sup.waitForSelector('#tcContacts');
  await sup.click(row('Abid Hussain'));
  await head(sup, 'Abid Hussain');

  // ---- the message stamp -------------------------------------------------------------
  await sup.fill('#tcInput', 'what time is it');
  await sup.press('#tcInput', 'Enter');
  await sup.waitForFunction(() => document.getElementById('tcInput').value === '', null, { timeout: 20000 });
  await sleep(1200);

  const stamp = await sup.evaluate(() => {
    const all = document.querySelectorAll('.tc-msg .tc-time');
    return all.length ? all[all.length - 1].textContent.trim() : '';
  });
  const pkNow = clockIn(PK, new Date()), pcNow = clockIn(TZ, new Date());
  check('1. the message is stamped in Pakistan time', stamp.includes(pkNow), { stamp, expected: pkNow });
  check('2. it is NOT the PC\'s time', !stamp.includes(pcNow), { stamp, pcTime: pcNow });

  // ---- the sidebar row time ----------------------------------------------------------
  const rowTime = (await sup.locator(row('Abid Hussain') + ' [data-time]').textContent()).trim();
  check('3. the sidebar row time is Pakistan time', rowTime === pkNow || rowTime === 'now',
    { rowTime, expected: pkNow });

  // ---- the day separator -------------------------------------------------------------
  const daysep = await sup.evaluate(() => {
    const all = document.querySelectorAll('.tc-daysep');
    return all.length ? { label: all[all.length - 1].textContent.trim(), key: all[all.length - 1].dataset.daykey } : null;
  });
  check('4. the day separator is on the Pakistan calendar',
    daysep && daysep.label === 'Today' && daysep.key === dayIn(PK, new Date()),
    { daysep, pakistanDay: dayIn(PK, new Date()), pcDay: dayIn(TZ, new Date()) });

  // ---- the Photos tab's date headings -------------------------------------------------
  // Shared seconds ago, so in Pakistan it is "Today". On the PC's calendar it is another day,
  // which is exactly what the old code grouped by.
  await sup.setInputFiles('#tcFile', [{ name: 'shot.png', mimeType: 'image/png',
    buffer: Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==', 'base64') }]);
  await sup.press('#tcInput', 'Enter');
  await sup.waitForFunction(() => !document.getElementById('tcInput').readOnly, null, { timeout: 30000 });
  await sleep(1500);
  await sup.click('#tcTabs [data-tab="photos"]');
  await sup.waitForSelector('#tcPhotosScroll .tc-photos-group', { timeout: 20000 }).catch(() => {});
  const heading = (await sup.locator('#tcPhotosScroll .tc-photos-group').first().textContent().catch(() => '') || '').trim();
  check('5. Photos groups by the Pakistan day, not the PC\'s', heading === 'Today', { heading });

  check('Z. no page errors', sup.errs.length === 0, sup.errs);

  await browser.close();
  const failed = results.filter(r => !r.ok).length;
  console.log('\n' + (results.length - failed) + '/' + results.length + ' passed');
  process.exitCode = failed ? 1 : 0;
})().catch(e => { console.error(e); process.exitCode = 2; });
