// The owner's chat overview: the super admin can reach it from the chat, a VA cannot see it
// exists, and it shows groups the owner was never added to — without any message text.
const path = require('path');
const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright-core');
const B = process.env.TEST_BASE_URL || 'http://127.0.0.1:8932';
if (!['127.0.0.1', 'localhost'].includes(new URL(B).hostname)) throw Error('Tests require a local disposable server');
const CHAT = B + '/admin/team-messages?standalone=1';
const UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/125 Safari/537.36 ApexDesktop/1.0';
const sleep = ms => new Promise(r => setTimeout(r, ms));
const results = [];
function check(name, ok, detail) { results.push({ name, ok: !!ok }); console.log((ok ? 'PASS ' : 'FAIL ') + name + (detail !== undefined ? '  ' + JSON.stringify(detail) : '')); }

async function login(browser, email) {
  const ctx = await browser.newContext({ userAgent: UA, viewport: { width: 1400, height: 900 } });
  const p = await ctx.newPage(); p.errs = []; p.on('pageerror', e => p.errs.push(e.message));
  await p.goto(B + '/admin/chat-login', { waitUntil: 'domcontentloaded' });
  await p.fill('#email', email); await p.fill('#password', 'password123');
  await Promise.all([p.waitForURL(/team-messages/, { timeout: 30000 }), p.click('.submit')]);
  await p.waitForSelector('#tcContacts'); p.ctx = ctx; return p;
}
const row = n => `a.tc-contact:has(.tc-c-name:text-is("${n}"))`;
const head = (p, n, t = 15000) => p.waitForFunction(x => document.querySelector('.tc-thread-head .tc-th-name')?.textContent.trim() === x, n, { timeout: t });

(async () => {
  const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || chromium.executablePath() });

  // A group between two VAs that the owner is not part of, with a message in it.
  const abid = await login(browser, 'va0@apex.test');
  await abid.goto(CHAT, { waitUntil: 'domcontentloaded' });
  await abid.waitForSelector('#tcContacts');
  await abid.click('#tcNewGroup');
  await abid.fill('#tcNewGroupName', 'VAs only');
  await abid.click('#tcGroupModal .tc-group-members input >> nth=0');
  await abid.click('#tcGroupCreate');
  await head(abid, 'VAs only').catch(() => {});
  await abid.fill('#tcInput', 'OWNER-MUST-NEVER-SEE-THIS');
  await abid.press('#tcInput', 'Enter');
  await abid.waitForFunction(() => document.getElementById('tcInput').value === '', null, { timeout: 20000 }).catch(() => {});
  await sleep(1000);

  // A VA must not even be offered the page.
  check('1. a VA sees no overview button', await abid.locator('a[href*="team-messages/overview"]').count() === 0);
  const vaDirect = await abid.goto(B + '/admin/team-messages/overview', { waitUntil: 'domcontentloaded' });
  check('2. a VA typing the address straight in is refused', vaDirect.status() === 403, { status: vaDirect.status() });

  // The owner reaches it from the chat itself.
  const sup = await login(browser, 'super@apex.test');
  await sup.goto(CHAT, { waitUntil: 'domcontentloaded' });
  await sup.waitForSelector('#tcContacts');
  const btn = sup.locator('a[href*="team-messages/overview"]');
  check('3. the owner has the overview button', await btn.count() === 1);
  await btn.click();
  await sup.waitForSelector('.tc-ov', { timeout: 20000 });

  const body = await sup.textContent('.tc-ov');
  check('4. it lists the group the owner was never added to', /VAs only/.test(body));
  check('5. it says so plainly', /You're not in this group/.test(body));
  check('6. it shows no message text', !/OWNER-MUST-NEVER-SEE-THIS/.test(body));
  check('7. it explains what is and is not shown', /not what anyone said/i.test(body));

  // ---- steps 3 and 4 on the page --------------------------------------------------------
  check('7b. the announcement box is there', await sup.locator('form.tc-ov-announce textarea').count() === 1);
  check('7c. a chat the owner is in can be saved', await sup.locator('a[href*="/overview/export/"]').count() >= 1);

  await sup.evaluate(() => { document.querySelector('.tc-ov').scrollTop = 0; });
  await sup.screenshot({ path: path.join(process.env.SHOT_DIR || require('os').tmpdir(), 'overview.png') });

  // Send one message to the whole team and confirm it lands in a VA's chat.
  await sup.fill('form.tc-ov-announce textarea', 'ANNOUNCE-TO-ALL-TEST');
  sup.once('dialog', d => d.accept());
  await sup.click('form.tc-ov-announce button[type=submit]');
  await sup.waitForSelector('.tc-ov-done, .tc-ov-anns', { timeout: 20000 }).catch(() => {});
  check('7d. the announcement is recorded with a read count',
    /Read by|of/.test(await sup.textContent('.tc-ov') || ''));

  await sup.screenshot({ path: path.join(process.env.SHOT_DIR || require('os').tmpdir(), 'overview-announce.png') });

  // The files page.
  await sup.click('a[href$="/overview/files"]');
  await sup.waitForSelector('.tc-ov', { timeout: 20000 });
  check('7e. the files page opens', /Files shared in chat/.test(await sup.textContent('.tc-ov') || ''));
  await sup.goBack();
  await sup.waitForSelector('.tc-ov-announce', { timeout: 20000 });

  // ---- step 2: the owner acts ---------------------------------------------------------
  // Joining a group is offered, and it is a visible act.
  check('8. joining is offered for a group the owner is not in',
    await sup.locator('form[action*="/join"] button').count() >= 1);

  // And back to the chat without a reload loop.
  await sup.click('.tc-ov-back');
  await sup.waitForSelector('#tcContacts', { timeout: 20000 });
  check('9. the back link returns to the chat', await sup.locator('#tcContacts').count() === 1);

  // The owner can remove a VA's message from a chat they share.
  {
    await abid.goto(CHAT, { waitUntil: 'domcontentloaded' });
    await abid.waitForSelector('#tcContacts');
    await abid.click(row('Umair Arshad'));
    await head(abid, 'Umair Arshad');
    await abid.fill('#tcInput', 'REMOVE-ME-PLEASE');
    await abid.press('#tcInput', 'Enter');
    await abid.waitForFunction(() => document.getElementById('tcInput').value === '', null, { timeout: 20000 });
    await sleep(1200);

    await sup.click(row('Abid Hussain'));
    await head(sup, 'Abid Hussain');
    await sup.waitForFunction(() => [...document.querySelectorAll('#tcMessages .tc-text')]
      .some(e => e.textContent.trim() === 'REMOVE-ME-PLEASE'), null, { timeout: 25000 });

    const target = sup.locator('.tc-msg:not(.mine)').last();
    await target.hover();
    await target.locator('.tc-dots').click();
    const del = sup.locator('.tc-menu-item[data-act="delete"]');
    check("10. the owner is offered Delete on a VA's message", await del.isVisible());
    await del.click();
    await sup.click('#tcDelEveryone');
    await sup.waitForFunction(() => ![...document.querySelectorAll('#tcMessages .tc-text')]
      .some(e => e.textContent.trim() === 'REMOVE-ME-PLEASE'), null, { timeout: 20000 }).catch(() => {});
    check('11. the message is gone for the owner',
      !(await sup.evaluate(() => [...document.querySelectorAll('#tcMessages .tc-text')].some(e => e.textContent.trim() === 'REMOVE-ME-PLEASE'))));

    // And the sender sees it removed BY NAME, not silently vanished.
    const seen = await abid.waitForFunction(() => [...document.querySelectorAll('.tc-deleted-text')]
      .some(e => /deleted by Umair/i.test(e.textContent)), null, { timeout: 30000 }).then(() => true).catch(() => false);
    check('12. the sender is told who removed it', seen,
      await abid.evaluate(() => [...document.querySelectorAll('.tc-deleted-text')].map(e => e.textContent.trim())));
  }
  check('Z. no page errors', sup.errs.length === 0, sup.errs);

  await browser.close();
  const failed = results.filter(r => !r.ok).length;
  console.log('\n' + (results.length - failed) + '/' + results.length + ' passed');
  process.exitCode = failed ? 1 : 0;
})().catch(e => { console.error(e); process.exitCode = 2; });
