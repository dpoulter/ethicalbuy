// Loads every page type in Chromium and reports Content-Security-Policy
// violations and console errors. Development only.
//
//   DB_PASSWORD=ethicalbuy_dev php -S 127.0.0.1:8000 -t public &
//   node dev/csp-check.js http://127.0.0.1:8000
const { chromium } = require('playwright');

const base = process.argv[2] || 'http://127.0.0.1:8000';
const pages = [
  '/', '/search.php', '/search.php?q=milk', '/search.php?q=oat&submitted=1',
  '/brand.php?brand=Valley+Fresh',
  '/priorities.php', '/about.php', '/contact.php', '/contact.php?about=brand&item=Valley+Fresh',
  '/privacy.php', '/terms.php', '/corrections.php', '/sources.php', '/nope', '/brand.php?brand=No+Such+Brand',
];

(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage();
  let problems = 0;
  let status = 200;
  page.on('response', (r) => { if (r.request().isNavigationRequest() && r.frame() === page.mainFrame()) status = r.status(); });
  page.on('console', (m) => {
    // an intentional 404 page logs its own status as an error; that is not a problem
    if (m.type() !== 'error' || (status >= 400 && /status of \d{3}/.test(m.text()))) return;
    // a photo that could not be fetched was still allowed by the CSP
    if (/net::ERR_/.test(m.text())) { console.log(`  (network, not CSP: ${m.text()})`); return; }
    problems++; console.log(`  console error: ${m.text()}`);
  });
  await page.exposeFunction('reportViolation', (v) => { problems++; console.log(`  CSP violation: ${v}`); });
  await page.addInitScript(() => {
    document.addEventListener('securitypolicyviolation', (e) =>
      window.reportViolation(`${e.violatedDirective} blocked ${e.blockedURI || '(inline)'} at ${e.sourceFile}:${e.lineNumber}`));
  });
  // a real product and brand from the data, whatever is loaded
  await page.goto(base + '/search.php?q=a&submitted=1');
  for (const sel of ['a[href*="product.php?code="]', 'a[href*="brand.php?brand="]']) {
    const href = await page.$eval(sel, (a) => a.getAttribute('href')).catch(() => null);
    if (href) pages.push('/' + href.replace(/^\//, ''));
  }
  for (const p of pages) {
    const before = problems;
    const res = await page.goto(base + p, { waitUntil: 'load' });
    // exercise the scripts: change category, move a slider, open the menu
    if (p === '/') { const opts = await page.$$eval('#categorySelect option', (o) => o.map((x) => x.value)); if (opts[1]) await page.selectOption('#categorySelect', opts[1]); }
    if (p === '/priorities.php') { await page.$eval('input[type=range]', (el) => { el.value = 5; el.dispatchEvent(new Event('input')); }); }
    await page.setViewportSize({ width: 360, height: 800 });
    const toggler = await page.$('.navbar-toggler');
    if (toggler) { await toggler.click(); await page.waitForTimeout(400); }
    await page.setViewportSize({ width: 1200, height: 800 });
    console.log(`${res.status()} ${p}${problems === before ? '  clean' : ''}`);
  }
  await browser.close();
  console.log(problems === 0 ? 'No CSP violations or console errors.' : `${problems} problem(s).`);
  process.exit(problems === 0 ? 0 : 1);
})();
