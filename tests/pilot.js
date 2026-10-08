// Pilot run: the plan's Halabja scenario with demo data (php database/demo-seed.php), driven through the real mobile UI.
// Usage: PLAYWRIGHT_PATH=/path/to/playwright node tests/pilot.js [base_url] [screenshot_dir]
const { chromium, devices } = require(process.env.PLAYWRIGHT_PATH || 'playwright');
const base = process.argv[2] || 'http://127.0.0.1:8000';
const shots = process.argv[3];
let fail = 0;
const check = (name, ok, extra) => { console.log((ok ? 'PASS' : 'FAIL') + '  ' + name); if (!ok) { fail++; if (extra !== undefined) console.log('      ' + JSON.stringify(extra)); } };
const digits = s => Number(String(s).replace(/[٠-٩]/g, d => '٠١٢٣٤٥٦٧٨٩'.indexOf(d)).replace(/[٬,\s]/g, '').replace('٫', '.'));

(async () => {
  const browser = await chromium.launch();
  const session = async (phone, geo) => {
    const ctx = await browser.newContext({ ...devices['Pixel 5'], geolocation: geo, permissions: ['geolocation'] });
    const page = await ctx.newPage(); const errors = [];
    page.on('pageerror', e => errors.push(String(e)));
    page.on('console', m => { if (m.type() === 'error' && !/status of (404|422)/.test(m.text())) errors.push(m.text()); });
    await page.goto(base + '/login.php');
    await page.fill('[name=login]', phone); await page.fill('[name=password]', 'demo12345');
    await page.click('button[type=submit]'); await page.waitForURL('**/dashboard/'); await page.waitForSelector('.bottom-nav');
    return { ctx, page, errors };
  };
  const shot = (p, n) => shots ? p.screenshot({ path: `${shots}/pilot-${n}.png` }) : null;
  const items = async p => p.$$eval('.listing', els => els.map(e => ({ title: e.querySelector('.title')?.innerText, price: e.querySelector('.price')?.innerText, meta: e.querySelector('.meta')?.innerText, href: e.getAttribute('href') })));

  // 1 — farmer Aram (Halabja) logs in; demo data is visible
  const farmer = await session('+9647501000001', { latitude: 35.1778, longitude: 45.9861 });
  const fp = farmer.page;
  await fp.waitForSelector('.stats .stat');
  const stats = (await fp.$$eval('.stats .stat b', els => els.map(e => e.innerText))).map(digits);
  check('farmer home shows his demo listings as active (>= 2)', stats[0] >= 2, stats);
  await shot(fp, '1-farmer-home');

  // 2 — farmer lists 5 tons of pomegranate peel in Halabja through the wizard (voice comes in a later phase)
  await fp.click('.bottom-nav a.fab'); await fp.waitForSelector('.tiles .tile');
  await fp.locator('.tiles .tile', { hasText: 'توێکڵی هەنار' }).click(); await fp.click('button:has-text("دواتر")');
  await fp.fill('[name=quantity]', '5'); await fp.click('button:has-text("دواتر")');
  await fp.click('button:has-text("شوێنی ئێستام")'); await fp.waitForSelector('button:has-text("شوێن پاشەکەوت کرا")');
  await fp.fill('[name=address]', 'هەڵەبجە'); await fp.click('button:has-text("دواتر")');
  await fp.click('button:has-text("دواتر")');
  await fp.locator('.tile', { hasText: 'نرخی دیاریکراو' }).click(); await fp.fill('[name=price_per_unit]', '21000'); await fp.click('button:has-text("دواتر")');
  await fp.click('button:has-text("دواتر")'); await fp.click('button:has-text("بڵاوکردنەوە")');
  await fp.waitForURL('**#/supply/*'); await fp.waitForSelector('.price');
  const qtyTxt = await fp.locator('.kv div').first().innerText(); const priceTxt = await fp.locator('.price').first().innerText();
  check('new Halabja listing published (5 ton @ 21,000)', /[5٥]/.test(qtyTxt) && digits(priceTxt.split(' ')[0]) === 21000, [qtyTxt, priceTxt]);
  await shot(fp, '2-farmer-new-listing');

  // 3 — farmer sees what factories need
  await fp.goto(base + '/dashboard/#/demands'); await fp.waitForSelector('.card');
  const dem = await fp.$$eval('.card .title', els => els.map(e => e.innerText));
  check('farmer sees the 5 open factory demands', dem.length >= 5, dem);
  check('demand for 4 ton pomegranate peel is visible', await fp.locator('.card', { hasText: 'توێکڵی هەنار' }).count() >= 1);
  await shot(fp, '3-farmer-demands');

  // 4 — factory "Asman" (Halabja industrial zone) searches near itself
  const factory = await session('+9647502000001', { latitude: 35.2000, longitude: 45.9800 });
  const p = factory.page;
  await p.goto(base + '/dashboard/#/market'); await p.waitForSelector('.listing');
  const all = await items(p);
  check('market shows demo + new listings', all.length >= 10, all.length);
  await p.click('button[aria-label="فلتەر"]'); await p.waitForSelector('.sheet');
  await p.click('.sheet button:has-text("نزیک من")'); await p.waitForSelector('.sheet button:has-text("شوێنەکەم دیاریکرا")');
  await p.click('.sheet button:has-text("جێبەجێکردن")'); await p.waitForSelector('.listing .meta');
  await p.waitForTimeout(400);
  const near = await items(p);
  const kms = near.map(i => (i.meta.match(/[\d٠-٩][\d٠-٩٬.٫]*\s*کم/) || [''])[0]).map(x => digits(x.replace('کم', '')));
  check('nearest-first: first result is within 10 km (Halabja)', kms[0] < 10, near.slice(0, 3));
  check('results are sorted by distance', kms.every((k, i) => i === 0 || k >= kms[i - 1]), kms);
  check('radius 50 km excludes Sulaymaniyah/Darbandikhan listings', kms.every(k => k <= 50), kms);
  await shot(p, '4-factory-near');

  // 5 — narrow to pomegranate peel, cheapest first
  await p.click('.chips.scroll .chip:has-text("توێکڵی هەنار")'); await p.waitForTimeout(500);
  const peel = await items(p);
  check('pomegranate-peel results only', peel.length >= 2 && peel.every(i => i.title.includes('توێکڵی هەنار')), peel.map(i => i.title));
  await p.click('button[aria-label="فلتەر"]'); await p.waitForSelector('.sheet');
  await p.selectOption('.sheet select >> nth=0', 'price_asc');
  await p.click('.sheet button:has-text("نزیک من")').catch(() => {});
  await p.click('.sheet button:has-text("جێبەجێکردن")'); await p.waitForTimeout(500);
  await shot(p, '5-factory-peel');

  // 6 — open the listing, check it is complete and contactable only through the platform
  await p.locator('.listing').first().click(); await p.waitForSelector('.kv');
  const detail = await p.locator('.card').first().innerText();
  check('detail shows supplier name but no phone number', !/\+?964\d{8,}/.test(detail) && /ئارام|شیلان|کاوە|هێمن|ڕێژنە/.test(detail), detail.slice(0, 200));
  check('buyer has no cancel/publish controls on someone else\'s listing', (await p.locator('button:has-text("هەڵوەشاندنەوە"), button:has-text("بڵاوکردنەوە")').count()) === 0);
  await shot(p, '6-factory-detail');

  // 7 — factory's own demands
  await p.goto(base + '/dashboard/#/demands'); await p.waitForSelector('.card');
  const mine = await p.$$eval('.card .title', els => els.length);
  check('factory sees exactly its own 2 demands', mine === 2, mine);
  await shot(p, '7-factory-demands');

  check('no JS errors (farmer)', farmer.errors.length === 0, farmer.errors);
  check('no JS errors (factory)', factory.errors.length === 0, factory.errors);
  await browser.close();
  console.log(fail ? `\n${fail} FAILED` : '\nPilot passed');
  process.exit(fail ? 1 : 0);
})().catch(e => { console.error(e); process.exit(2); });
