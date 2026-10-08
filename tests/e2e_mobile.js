// End-to-end test of the mobile web app in a phone-sized Chromium.
// Usage: PLAYWRIGHT_PATH=/path/to/playwright node tests/e2e_mobile.js [base_url] [screenshot_dir]
const { chromium, devices } = require(process.env.PLAYWRIGHT_PATH || 'playwright');
const base = process.argv[2] || 'http://127.0.0.1:8000';
const shots = process.argv[3];
const root = new URL(base).pathname.replace(/\/$/, ''); // '' at web root, '/ecozhin' under XAMPP
let fail = 0;
const check = (name, ok, extra) => { console.log((ok ? 'PASS' : 'FAIL') + '  ' + name); if (!ok) { fail++; if (extra !== undefined) console.log('      ' + JSON.stringify(extra)); } };
const sfx = String(Math.floor(Math.random() * 9e7 + 1e7));
const PNG = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==', 'base64');

(async () => {
  const browser = await chromium.launch();
  const mk = async () => {
    const ctx = await browser.newContext({ ...devices['Pixel 5'], locale: 'ckb-IQ', geolocation: { latitude: 35.3, longitude: 46.0 }, permissions: ['geolocation'], serviceWorkers: 'allow' });
    const page = await ctx.newPage();
    const errors = [];
    page.on('pageerror', e => errors.push(String(e)));
    page.on('console', m => { if (m.type() === 'error') errors.push(m.text()); });
    return { ctx, page, errors };
  };
  const shot = async (page, n) => { if (shots) await page.screenshot({ path: `${shots}/${n}.png` }); };
  const overflow = page => page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth + 1);
  const rawKeys = page => page.evaluate(() => [...document.body.innerText.matchAll(/\b[a-z]+\.[a-z_]+\b/g)].map(m => m[0]).filter(k => window.I18N && !(k in window.I18N) === false));

  // ---------- Farmer ----------
  const f = await mk(); const p = f.page;
  await p.goto(base + '/'); await shot(p, '01-landing');
  check('landing is RTL Kurdish', await p.evaluate(() => document.documentElement.dir === 'rtl' && document.documentElement.lang === 'ku'));
  check('landing has no horizontal overflow', !(await overflow(p)));
  check('manifest linked + valid', await p.evaluate(async () => { const l = document.querySelector('link[rel=manifest]'); const m = await (await fetch(l.href)).json(); return m.display === 'standalone' && m.icons.length >= 2 && m.start_url.endsWith('/dashboard/'); }));
  await p.click('a[href$="/register.php"]');
  await p.fill('[name=full_name]', 'جوتیاری تێست'); await p.fill('[name=phone]', '+96475' + sfx); await p.fill('[name=password]', 'secret123');
  await shot(p, '02-register');
  await p.click('button[type=submit]');
  await p.waitForURL('**/dashboard/');
  await p.waitForSelector('.bottom-nav');
  await p.waitForSelector('.hero');
  check('farmer lands on app shell with bottom nav', (await p.locator('.bottom-nav a').count()) === 5);
  check('home greets user', (await p.locator('.hero').innerText()).includes('جوتیاری تێست'));
  await p.waitForSelector('.stats .stat');
  await shot(p, '03-home');
  check('home no overflow', !(await overflow(p)));

  // add supply wizard
  await p.click('.bottom-nav a.fab');
  await p.waitForSelector('.tiles .tile');
  check('wizard shows waste tiles', (await p.locator('.tiles .tile').count()) >= 10);
  await p.click('button:has-text("دواتر")'); // next without selecting
  check('cannot skip step 1 w/o material (toast)', await p.locator('.toast.error').count() > 0);
  await p.locator('.tiles .tile', { hasText: 'توێکڵی هەنار' }).click();
  await shot(p, '04-wizard-1');
  await p.click('button:has-text("دواتر")');
  await p.fill('[name=quantity]', '5');
  await p.click('.stepper button:last-child'); // +1 => 6
  check('stepper increments', (await p.inputValue('[name=quantity]')) === '6');
  await p.click('.chip:has-text("پلەی A")');
  await shot(p, '05-wizard-2');
  await p.click('button:has-text("دواتر")');
  await p.click('button:has-text("شوێنی ئێستام")');
  await p.waitForSelector('button:has-text("شوێن پاشەکەوت کرا")');
  await p.fill('[name=address]', 'هەڵەبجە - خورماڵ');
  await shot(p, '06-wizard-3');
  await p.click('button:has-text("دواتر")');
  await p.click('button:has-text("دواتر")'); // date has default
  await p.locator('.tile', { hasText: 'نرخی دیاریکراو' }).click();
  await p.fill('[name=price_per_unit]', '20000');
  await shot(p, '07-wizard-5');
  await p.click('button:has-text("دواتر")');
  await p.setInputFiles('.photos input[type=file]', { name: 'a.png', mimeType: 'image/png', buffer: PNG });
  await p.waitForSelector('.photos .ph');
  await shot(p, '08-wizard-6');
  await p.click('button:has-text("دواتر")');
  await shot(p, '09-wizard-review');
  await p.click('button:has-text("بڵاوکردنەوە")');
  await p.waitForURL('**#/supply/*');
  await p.waitForSelector('.gallery img');
  check('published and redirected to detail with photo', (await p.locator('.gallery img').count()) === 1);
  check('detail shows fixed price', (await p.locator('.price').first().innerText()).includes('٢٠') || (await p.locator('.price').first().innerText()).includes('20'));
  check('detail no overflow', !(await overflow(p)));
  await shot(p, '10-detail');
  check('owner sees cancel action', await p.locator('button:has-text("هەڵوەشاندنەوە")').count() === 1);
  const supplyUrl = p.url();

  // market + filter
  await p.click('.bottom-nav a[data-key=market]');
  await p.waitForSelector('.listing');
  check('market lists the new item', (await p.locator('.listing .title', { hasText: 'توێکڵی هەنار' }).count()) >= 1);
  await p.fill('input[name=q]', 'zzzznomatch'); await p.waitForSelector('.empty');
  check('empty state on no match', true);
  await p.fill('input[name=q]', ''); await p.waitForSelector('.listing');
  await p.click('button[aria-label="فلتەر"]'); await p.waitForSelector('.sheet');
  await shot(p, '11-filters');
  await p.click('.sheet button:has-text("جێبەجێکردن")');
  await shot(p, '12-market');
  check('market no overflow', !(await overflow(p)));

  // my listings
  await p.click('.bottom-nav a[data-key=my]'); await p.waitForSelector('.tabs .chip');
  await p.waitForSelector('.listing');
  check('my listings shows item', (await p.locator('.listing').count()) >= 1);

  // farms
  await p.click('.bottom-nav a[data-key=me]'); await p.waitForSelector('a[href="#/farms"]');
  await shot(p, '13-profile');
  await p.click('a[href="#/farms"]'); await p.waitForSelector('.empty');
  await p.click('button:has-text("کێڵگەی نوێ")'); await p.fill('.sheet [name=name]', 'کێڵگەی خورماڵ'); await p.click('.sheet button[type=submit]');
  await p.waitForSelector('.card h3:has-text("کێڵگەی خورماڵ")');
  check('farm added', true);

  // language switch -> English LTR
  await p.goto(base + '/dashboard/?lang=en'); await p.waitForSelector('.hero');
  check('English switches to LTR', await p.evaluate(() => document.documentElement.dir === 'ltr' && document.querySelector('.bottom-nav').innerText.includes('Market')));
  await shot(p, '14-english');
  const raw = await p.evaluate(() => [...document.querySelectorAll('.bottom-nav a, .hero, .actions a')].map(e => e.innerText).join(' ').match(/\b(nav|common|home|stat)\.[a-z_]+/g));
  check('no raw i18n keys visible (en)', !raw, raw);

  // PWA: service worker active and the offline fallback + app shell are precached.
  // (Playwright's setOffline does not stop requests made by the service worker itself, so the fallback path is checked structurally.)
  const pwa = await p.evaluate(async root => { const r = await navigator.serviceWorker.ready; await new Promise(s => setTimeout(s, 500));
    return { active: !!r.active, offline: !!(await caches.match(root + '/offline.html')), css: !!(await caches.match(root + '/assets/css/app.css')), js: !!(await caches.match(root + '/assets/js/app.js')) }; }, root);
  check('service worker active, shell + offline page precached', pwa.active && pwa.offline && pwa.css && pwa.js, pwa);
  check('authenticated pages are not cached', await p.evaluate(async root => !(await caches.match(root + '/dashboard/')) && !(await caches.match(root + '/api/auth/me')), root));
  check('farmer: no JS errors', f.errors.length === 0, f.errors);

  // ---------- Buyer ----------
  const b = await mk(); const q = b.page;
  await q.goto(base + '/register.php');
  await q.selectOption('[name=role]', 'buyer');
  check('org field appears for buyer', await q.locator('#org-group').isVisible());
  await q.fill('[name=full_name]', 'کارگەی تێست'); await q.fill('[name=phone]', '+96477' + sfx); await q.fill('[name=password]', 'secret123'); await q.fill('[name=organization_name]', 'Test Factory');
  await q.click('button[type=submit]'); await q.waitForURL('**/dashboard/'); await q.waitForSelector('.bottom-nav');
  check('buyer nav has 4 tabs, no add FAB', (await q.locator('.bottom-nav a').count()) === 4 && (await q.locator('.bottom-nav a.fab').count()) === 0);
  await q.goto(base + '/dashboard/#/add'); await q.waitForSelector('.hero');
  check('buyer blocked from supplier-only route', q.url().endsWith('#/'), q.url());
  await q.click('.bottom-nav a[data-key=demands]'); await q.waitForSelector('a:has-text("داواکاری نوێ")');
  await q.click('a:has-text("داواکاری نوێ")'); await q.waitForSelector('form');
  await q.click('button[type=submit]');
  await q.waitForSelector('.field-error');
  check('demand form shows field error', (await q.locator('.field-error').count()) >= 1);
  await shot(q, '15-demand-form');
  await q.fill('[name=required_quantity]', '4'); await q.fill('[name=max_price_per_unit]', '25000');
  await q.click('button[type=submit]'); await q.waitForURL('**#/demands'); await q.waitForSelector('.card.listing, .card a, .card .listing');
  check('demand created and listed', (await q.locator('.card').count()) >= 1);
  await shot(q, '16-demands');
  await q.goto(base + '/dashboard/#/market'); await q.waitForSelector('.listing');
  await q.locator('.listing').first().click(); await q.waitForSelector('.gallery');
  check('buyer sees detail without owner actions', (await q.locator('button:has-text("هەڵوەشاندنەوە")').count()) === 0);
  check('buyer: no JS errors (422 from the deliberate empty submit ignored)', b.errors.filter(e => !/status of 422/.test(e)).length === 0, b.errors);

  // unauth redirect
  const anon = await mk();
  await anon.page.goto(base + '/dashboard/');
  check('unauthenticated /dashboard redirects to login', anon.page.url().includes('/login.php'));

  await browser.close();
  console.log(fail ? `\n${fail} FAILED` : '\nAll passed');
  process.exit(fail ? 1 : 0);
})().catch(e => { console.error(e); process.exit(2); });
