// Handy-Ansicht (iPhone 13, 390 × 844) gegen die Docker-Testumgebung.
// Aufruf: node tests/e2e/mobile.mjs   (nach tests/ci/prepare-checkout.php; Demodaten müssen vorhanden sein)
//
// Seiten: Startseite, Produktübersicht, Produktseite, Warenkorb, Kasse.
// Prüft je Seite: kein seitliches Scrollen, Schaltflächen ≥ 44 px hoch, § 19-Hinweis sichtbar (nicht nur im
// Kopf-/Fußbereich), Menü auf dem Handy erreichbar. Screenshots: test-results/screenshots/mobil-*.png

import { chromium, devices } from 'playwright';
import { readFileSync, writeFileSync, mkdirSync } from 'node:fs';

const OUT = process.env.RESULTS_DIR || 'test-results';
mkdirSync(`${OUT}/screenshots`, { recursive: true });
const urls = JSON.parse(readFileSync(`${OUT}/urls.json`, 'utf8'));
const home = new URL(urls.shop).origin + '/';

const results = [];
const record = (area, name, ok, detail = '', warnOnly = false) => {
  const status = ok ? 'ok' : warnOnly ? 'WARN' : 'FAIL';
  results.push({ area, name, status, detail });
  console.log(`  ${status.padEnd(5)} [${area}] ${name}${detail ? ' – ' + detail : ''}`);
};
// Einheitlicher Wortlaut aus den Germanized-Einstellungen (setup-wp.sh). Der alte Germanized-Standardtext zählt nicht.
const NOTICE = /Umsatzsteuerbefreit\s+gemäß\s+§\s*19\s+UStG\./i;
const OLD_NOTICE = /Kein Mehrwertsteuerausweis|keine Umsatzsteuer berechnet/i;

const browser = await chromium.launch();
const context = await browser.newContext({ ...devices['iPhone 13'], locale: 'de-DE' });
const page = await context.newPage();
const jsErrors = [];
page.on('pageerror', (e) => jsErrors.push(e.message));

async function checkPage(area, { notice = true, buttons = [] } = {}) {
  const r = await page.evaluate(({ src, buttons }) => {
    const re = new RegExp(src, 'i');
    const inChrome = (el) => !!el.closest('header, footer, .wp-block-template-part');
    const vw = window.innerWidth;
    const overflow = document.documentElement.scrollWidth - vw;
    // Elemente, die über den rechten Rand hinausragen (für die Fehlersuche).
    const wide = [...document.querySelectorAll('body *')]
      .filter((e) => { const b = e.getBoundingClientRect(); return b.width > 0 && b.right > vw + 1 && getComputedStyle(e).position !== 'fixed'; })
      .slice(0, 3).map((e) => `${e.tagName.toLowerCase()}.${String(e.className).trim().split(/\s+/).slice(0, 2).join('.')} (${Math.round(e.getBoundingClientRect().right)} px)`);
    const notices = [...document.querySelectorAll('body *')]
      .filter((e) => re.test(e.textContent) && ![...e.children].some((c) => re.test(c.textContent)) && e.getBoundingClientRect().height > 0);
    const btn = buttons.map((sel) => {
      const el = [...document.querySelectorAll(sel)].find((e) => e.getBoundingClientRect().height > 0);
      if (!el) return { sel, missing: true };
      const b = el.getBoundingClientRect();
      return { sel, h: Math.round(b.height), w: Math.round(b.width), text: (el.innerText || el.value || el.getAttribute('aria-label') || '').trim().slice(0, 30) };
    });
    const fontSize = parseFloat(getComputedStyle(document.body).fontSize);
    return { overflow, wide, inMain: notices.some((n) => !inChrome(n)), noticeText: (notices.find((n) => !inChrome(n)) || notices[0])?.textContent.trim().slice(0, 80), btn, fontSize };
  }, { src: NOTICE.source, buttons });
  record(area, 'Kein seitliches Scrollen', r.overflow <= 1, r.overflow > 1 ? `${r.overflow} px zu breit: ${r.wide.join(', ')}` : '');
  record(area, 'Grundschrift ≥ 16 px', r.fontSize >= 16, `${r.fontSize} px`, true);
  if (notice) record(area, '§ 19-Hinweis im Inhalt sichtbar', r.inMain, r.noticeText ? `„${r.noticeText}“` : 'nicht gefunden');
  for (const b of r.btn) {
    if (b.missing) record(area, `Schaltfläche ${b.sel}`, false, 'nicht gefunden');
    else record(area, `Schaltfläche „${b.text}“ gut antippbar (≥ 44 px)`, b.h >= 44, `${b.w} × ${b.h} px`, b.h >= 36);
  }
}
// Kopfbereich: eine Zeile, Reihenfolge Menü → Konto → Warenkorb, Tippflächen ≥ 44 px, Abstand zum Inhalt 12–28 px.
async function checkHeader(area, { cart = true } = {}) {
  const h = await page.evaluate(() => {
    const box = (sel) => { const e = [...document.querySelectorAll(sel)].find((x) => x.getBoundingClientRect().width > 0); if (!e) return null; const b = e.getBoundingClientRect(); return { x: Math.round(b.left), y: Math.round(b.top + b.height / 2), w: Math.round(b.width), h: Math.round(b.height) }; };
    const header = document.querySelector('.pawline-header')?.getBoundingClientRect();
    const first = document.querySelector('main .wp-block-woocommerce-breadcrumbs, main .woocommerce-breadcrumb, main h1');
    return {
      menu: box('.pawline-header .wp-block-navigation__responsive-container-open'),
      account: box('.pawline-header .wp-block-woocommerce-customer-account a'),
      cart: box('.pawline-header .wc-block-mini-cart__button'),
      headerH: header ? Math.round(header.height) : null,
      gap: header && first ? Math.round(first.getBoundingClientRect().top - header.bottom) : null,
      firstEl: first ? (first.matches('h1') ? 'Seitentitel' : 'Brotkrumen') : '–',
    };
  });
  const items = [['Menü', h.menu], ['Konto', h.account]].concat(cart ? [['Warenkorb', h.cart]] : []);
  const missing = items.filter(([, b]) => !b).map(([n]) => n);
  if (missing.length) { record(area, 'Kopfbereich: Symbole vorhanden', false, `fehlt: ${missing.join(', ')}`); return; }
  const oneRow = items.every(([, b]) => Math.abs(b.y - items[0][1].y) <= 8);
  const order = items.every(([, b], i) => i === 0 || b.x > items[i - 1][1].x);
  record(area, `Kopfbereich: ${items.map(([n]) => n).join(' → ')} in einer Zeile`, oneRow && order, items.map(([n, b]) => `${n} x=${b.x} y=${b.y}`).join(', ') + `; Kopfhöhe ${h.headerH} px`);
  const small = items.filter(([, b]) => b.w < 44 || b.h < 44);
  record(area, 'Kopfbereich: Tippflächen ≥ 44 × 44 px', small.length === 0, items.map(([n, b]) => `${n} ${b.w}×${b.h}`).join(', '));
  record(area, `Abstand Kopfbereich → ${h.firstEl} 12–28 px`, h.gap !== null && h.gap >= 12 && h.gap <= 28, h.gap === null ? 'nicht messbar' : `${h.gap} px`);
}
const shot = (name) => page.screenshot({ path: `${OUT}/screenshots/mobil-${name}.png`, fullPage: true }).catch(() => {});

try {
  // Startseite
  let resp = await page.goto(home, { waitUntil: 'networkidle' });
  record('Startseite', 'lädt', resp?.ok() ?? false, `HTTP ${resp?.status()}`);
  await checkPage('Startseite', { notice: false });
  const menuBtn = page.locator('.wp-block-navigation__responsive-container-open').first();
  const hasMenu = (await menuBtn.count()) > 0 && (await menuBtn.isVisible());
  record('Startseite', 'Menü-Schaltfläche (☰) auf dem Handy', hasMenu, '', true);
  await shot('1-startseite');
  if (hasMenu) {
    await menuBtn.click();
    await page.waitForTimeout(500);
    const items = await page.locator('.wp-block-navigation__responsive-container.is-menu-open a').allInnerTexts();
    record('Startseite', 'Menü öffnet sich', items.length > 0, items.map((t) => t.trim()).join(' · '));
    await page.screenshot({ path: `${OUT}/screenshots/mobil-1b-menue.png` }).catch(() => {});
    await page.locator('.wp-block-navigation__responsive-container-close').first().click().catch(() => {});
  }

  // Produktübersicht
  resp = await page.goto(urls.shop, { waitUntil: 'networkidle' });
  record('Produktübersicht', 'lädt', resp?.ok() ?? false, `HTTP ${resp?.status()}`);
  await checkPage('Produktübersicht', { buttons: ['.wc-block-components-product-button__button, .add_to_cart_button, .wp-block-button__link'] });
  await checkHeader('Produktübersicht', {});
  await shot('2-uebersicht');

  // Produktseite
  resp = await page.goto(urls.product, { waitUntil: 'networkidle' });
  record('Produktseite', 'lädt', resp?.ok() ?? false, `HTTP ${resp?.status()}`);
  await checkPage('Produktseite', { buttons: ['button.single_add_to_cart_button'] });
  await checkHeader('Produktseite', {});
  await shot('3-produkt');
  await page.locator('button.single_add_to_cart_button').first().click();
  await page.waitForLoadState('networkidle');

  // Warenkorb
  await page.goto(urls.cart, { waitUntil: 'networkidle' });
  await page.waitForSelector('.wc-block-cart-items__row .wc-block-components-product-name', { state: 'visible', timeout: 30000 }).catch(() => {});
  await page.waitForTimeout(800);
  record('Warenkorb', 'Produkt im Warenkorb', (await page.innerText('body')).includes('[DEMO] Einfaches Produkt'));
  await checkPage('Warenkorb', { buttons: ['.wc-block-cart__submit-button, .checkout-button'] });
  await checkHeader('Warenkorb', { cart: false });
  await shot('4-warenkorb');

  // Kasse
  await page.goto(urls.checkout, { waitUntil: 'networkidle' });
  await page.waitForSelector('.wc-block-checkout', { timeout: 30000 }).catch(() => {});
  await page.waitForTimeout(2500); // Summen laden asynchron
  await checkPage('Kasse', { buttons: ['.wc-block-components-checkout-place-order-button, #place_order', '#email'] });
  // Hinweis muss direkt unter dem (zugeklappten) Gesamtbetrag stehen – nicht nur im Fußbereich.
  const near = await page.evaluate((src) => {
    const re = new RegExp(src, 'i');
    const vis = (e) => { const b = e.getBoundingClientRect(); return b.width > 0 && b.height > 0; };
    const anchors = [...document.querySelectorAll('.wc-block-components-checkout-order-summary__title, .wc-block-components-totals-footer-item')].filter(vis);
    const notices = [...document.querySelectorAll('main *')].filter((e) => re.test(e.textContent) && ![...e.children].some((c) => re.test(c.textContent)) && vis(e) && !e.closest('header, footer, .wp-block-template-part'));
    for (const a of anchors) {
      const ab = a.getBoundingClientRect();
      for (const n of notices) {
        const gap = Math.round(n.getBoundingClientRect().top - ab.bottom);
        if (gap >= -4 && gap <= 80) return { ok: true, gap, anchor: a.textContent.replace(/\s+/g, ' ').trim().slice(0, 40) };
      }
    }
    return { ok: false, anchors: anchors.map((a) => a.textContent.replace(/\s+/g, ' ').trim().slice(0, 40)), notices: notices.length };
  }, NOTICE.source);
  record('Kasse', '§ 19-Hinweis direkt beim Gesamtbetrag (≤ 80 px)', near.ok, near.ok ? `${near.gap} px unter „${near.anchor}“` : `Gesamtbetrag: ${near.anchors.join(' | ') || 'nicht gefunden'}; Hinweise im Inhalt: ${near.notices}`);
  await checkHeader('Kasse', { cart: false });
  await shot('5-kasse');
} catch (e) {
  record('Ablauf', 'Unerwarteter Fehler', false, e.message.split('\n')[0]);
  await shot('fehler');
}
record('Browser', 'Keine JavaScript-Fehler', jsErrors.length === 0, jsErrors.slice(0, 3).join(' | '), true);
await browser.close();

writeFileSync(`${OUT}/mobile.json`, JSON.stringify(results, null, 2));
const failed = results.filter((r) => r.status === 'FAIL').length;
console.log(`\nErgebnis: ${results.filter((r) => r.status === 'ok').length} ok, ${results.filter((r) => r.status === 'WARN').length} Warnungen, ${failed} Fehler`);
process.exit(failed ? 1 : 0);
