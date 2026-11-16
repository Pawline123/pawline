// Browser-Test des Kaufablaufs gegen die Docker-Testumgebung (GitHub Actions oder lokal).
// Aufruf: node tests/e2e/checkout.mjs   (vorher tests/ci/prepare-checkout.php ausführen)
//
// Prüft: Demoprodukt aufrufen, in den Warenkorb legen, Bestellabschluss als Gast mit der
// Offline-Zahlart „Überweisung (nur CI-Test)“, Kleinunternehmerhinweise (§ 19 UStG) auf
// Produktseite, Warenkorb, Kasse, Bestellbestätigung und in der Bestell-E-Mail (Mailpit).
// Prüft NICHT: Stripe-Zahlungen, Rechnungen, Stornos (siehe Abschnitt „Offen“ im Bericht).

import { chromium } from 'playwright';
import { readFileSync, writeFileSync, mkdirSync } from 'node:fs';

const OUT = process.env.RESULTS_DIR || 'test-results';
const MAILPIT = process.env.MAILPIT_URL || 'http://localhost:8025';
mkdirSync(`${OUT}/screenshots`, { recursive: true });
const urls = JSON.parse(readFileSync(`${OUT}/urls.json`, 'utf8'));

const results = [];
const record = (area, name, ok, detail = '', warnOnly = false) => {
  const status = ok ? 'ok' : warnOnly ? 'WARN' : 'FAIL';
  results.push({ area, name, status, detail });
  console.log(`  ${status.padEnd(5)} [${area}] ${name}${detail ? ' – ' + detail : ''}`);
};

// § 19 UStG: Hinweis muss erscheinen, Steuerausweis darf nicht erscheinen.
const NOTICE = /§\s*19\s*UStG|Kleinunternehmer/i;
const TAX_SHOWN = /(inkl\.|inklusive|zzgl\.|zuzüglich)\s*(\d+([,.]\d+)?\s*%\s*)?(MwSt|USt|Mehrwertsteuer|Umsatzsteuer)|\b\d+([,.]\d+)?\s*%\s*(MwSt|USt)\b/i;

function checkTaxTexts(area, text) {
  const notice = text.match(NOTICE);
  record(area, 'Kleinunternehmerhinweis sichtbar', !!notice, notice ? `„${context(text, notice.index)}“` : 'kein „§ 19 UStG“/„Kleinunternehmer“ im Seitentext');
  const tax = text.match(TAX_SHOWN);
  record(area, 'Kein Umsatzsteuerausweis', !tax, tax ? `gefunden: „${context(text, tax.index)}“` : '');
}
function context(text, i) {
  return text.slice(Math.max(0, i - 40), i + 80).replace(/\s+/g, ' ').trim();
}

async function firstVisible(page, selectors) {
  for (const s of selectors) {
    const l = page.locator(s).first();
    if ((await l.count()) && (await l.isVisible().catch(() => false))) return l;
  }
  return null;
}

async function fillAny(page, label, selectors, value) {
  const l = await firstVisible(page, selectors);
  if (!l) return false;
  await l.fill(value);
  return true;
}

const browser = await chromium.launch();
const page = await browser.newPage({ locale: 'de-DE', viewport: { width: 1280, height: 900 } });
const jsErrors = [];
page.on('pageerror', (e) => jsErrors.push(e.message));
const shot = (name) => page.screenshot({ path: `${OUT}/screenshots/${name}.png`, fullPage: true }).catch(() => {});

let orderPlaced = false;
try {
  // 1. Produktseite
  const resp = await page.goto(urls.product, { waitUntil: 'networkidle' });
  record('Produktseite', 'Demoprodukt lädt', resp?.ok() ?? false, `${urls.product} → HTTP ${resp?.status()}`);
  const pText = await page.innerText('body');
  record('Produktseite', 'Titel „[DEMO] Einfaches Produkt“', pText.includes('[DEMO] Einfaches Produkt'));
  record('Produktseite', 'Preis 19,90 €', /19,90\s*€/.test(pText));
  checkTaxTexts('Produktseite', pText);
  await shot('1-produkt');

  // 2. Warenkorb
  const add = await firstVisible(page, ['button.single_add_to_cart_button', 'button[name="add-to-cart"]', '.wc-block-components-product-button__button']);
  record('Warenkorb', '„In den Warenkorb“-Schaltfläche vorhanden', !!add);
  if (add) {
    await Promise.all([page.waitForLoadState('networkidle'), add.click()]);
    await page.waitForTimeout(1500);
  }
  await page.goto(urls.cart, { waitUntil: 'networkidle' });
  await page.waitForSelector('.wc-block-cart-items, .woocommerce-cart-form, .wc-block-cart__empty-cart__title, .cart-empty', { timeout: 20000 }).catch(() => {});
  const cText = await page.innerText('body');
  record('Warenkorb', 'Demoprodukt im Warenkorb', cText.includes('[DEMO] Einfaches Produkt'));
  record('Warenkorb', 'Summe 19,90 €', /19,90\s*€/.test(cText));
  checkTaxTexts('Warenkorb', cText);
  await shot('2-warenkorb');

  // 3. Kasse
  await page.goto(urls.checkout, { waitUntil: 'networkidle' });
  await page.waitForSelector('.wc-block-checkout, form.checkout', { timeout: 30000 }).catch(() => {});
  const isBlock = (await page.locator('.wc-block-checkout').count()) > 0;
  record('Kasse', 'Kasse lädt', isBlock || (await page.locator('form.checkout').count()) > 0, isBlock ? 'Block-Kasse' : 'klassische Kasse');
  checkTaxTexts('Kasse', await page.innerText('body'));

  const data = { email: 'ci-test@pawline.test', first: 'CI', last: 'Testkunde', street: 'Teststraße 1', zip: '10115', city: 'Berlin', phone: '030000000' };
  const missing = [];
  const f = async (name, sels, v) => { if (!(await fillAny(page, name, sels, v))) missing.push(name); };
  await f('E-Mail', ['#email', '#billing_email', 'input[type=email]'], data.email);
  await f('Vorname', ['#shipping-first_name', '#billing-first_name', '#billing_first_name'], data.first);
  await f('Nachname', ['#shipping-last_name', '#billing-last_name', '#billing_last_name'], data.last);
  await f('Straße', ['#shipping-address_1', '#billing-address_1', '#billing_address_1'], data.street);
  await f('PLZ', ['#shipping-postcode', '#billing-postcode', '#billing_postcode'], data.zip);
  await f('Ort', ['#shipping-city', '#billing-city', '#billing_city'], data.city);
  await fillAny(page, 'Telefon', ['#shipping-phone', '#billing-phone', '#billing_phone'], data.phone);
  record('Kasse', 'Pflichtfelder ausgefüllt', missing.length === 0, missing.length ? `nicht gefunden: ${missing.join(', ')}` : '');
  await page.keyboard.press('Tab');
  await page.waitForTimeout(2500); // Block-Kasse aktualisiert Versand/Summen asynchron

  const pay = await firstVisible(page, ['#radio-control-wc-payment-method-options-bacs', 'input[value="bacs"]', '#payment_method_bacs']);
  if (pay) await pay.check({ force: true }).catch(() => pay.click({ force: true }));
  const payText = await page.innerText('body');
  record('Kasse', 'Zahlart „Überweisung (nur CI-Test)“ angeboten', payText.includes('Überweisung (nur CI-Test)'));
  record('Kasse', 'Versand „nur CI-Test“ angeboten', payText.includes('Versand (nur CI-Test)'), '', true);

  // Rechtliche Checkboxen (Germanized) anhaken – nur solche mit passendem Text.
  const legal = await page.evaluate(() => {
    const re = /AGB|Geschäftsbedingungen|Widerruf|Datenschutz|gelesen|zur Kenntnis|akzeptier|zustimm|einverstanden|terms/i;
    const found = [];
    document.querySelectorAll('input[type=checkbox]').forEach((cb, i) => {
      const label = (cb.closest('label')?.innerText || (cb.id && document.querySelector(`label[for="${cb.id}"]`)?.innerText) || cb.parentElement?.innerText || '').trim();
      if (re.test(label) && !cb.checked && cb.offsetParent !== null) {
        cb.setAttribute('data-pw-legal', String(i));
        found.push(label.slice(0, 120));
      }
    });
    return found;
  });
  for (const el of await page.locator('[data-pw-legal]').all()) await el.check({ force: true }).catch(() => el.click({ force: true }));
  record('Kasse', 'Rechtliche Bestätigungen', true, legal.length ? `angehakt: ${legal.join(' | ')}` : 'keine Checkbox gefunden');
  const kText = await page.innerText('body');
  record('Kasse', 'Summe 19,90 €', /19,90\s*€/.test(kText));
  await shot('3-kasse');

  const placeBtn = await firstVisible(page, ['.wc-block-components-checkout-place-order-button', '#place_order']);
  record('Bestellabschluss', 'Bestell-Schaltfläche vorhanden', !!placeBtn, placeBtn ? `„${(await placeBtn.innerText()).trim()}“` : '');
  if (placeBtn) {
    await placeBtn.click();
    try {
      await page.waitForURL(/order-received|bestellung-erhalten|key=wc_order_/, { timeout: 60000 });
      orderPlaced = true;
    } catch {
      const errs = await page.locator('.wc-block-components-notice-banner.is-error, .woocommerce-error, .wc-block-components-validation-error').allInnerTexts().catch(() => []);
      record('Bestellabschluss', 'Weiterleitung zur Bestellbestätigung', false, errs.length ? `Fehlermeldungen: ${errs.join(' | ').replace(/\s+/g, ' ')}` : `URL: ${page.url()}`);
      await shot('4-fehler-kasse');
    }
  }
  if (orderPlaced) {
    await page.waitForLoadState('networkidle');
    const oText = await page.innerText('body');
    record('Bestellabschluss', 'Bestellbestätigung angezeigt', true, page.url().replace(/key=[^&]+/, 'key=…'));
    record('Bestellabschluss', 'Bestellbestätigung nennt Produkt und Summe', oText.includes('[DEMO] Einfaches Produkt') && /19,90\s*€/.test(oText));
    checkTaxTexts('Bestellbestätigung', oText);
    await shot('4-bestaetigung');
  }
} catch (e) {
  record('Ablauf', 'Unerwarteter Fehler', false, e.message.split('\n')[0]);
  await shot('fehler');
}
record('Browser', 'Keine JavaScript-Fehler', jsErrors.length === 0, jsErrors.slice(0, 3).join(' | '), true);
await browser.close();

// 4. Bestell-E-Mail in Mailpit
if (orderPlaced) {
  let msg = null;
  for (let i = 0; i < 15 && !msg; i++) {
    try {
      const list = await (await fetch(`${MAILPIT}/api/v1/messages?limit=50`)).json();
      msg = (list.messages || []).find((m) => (m.To || []).some((t) => t.Address === 'ci-test@pawline.test'));
    } catch { /* Mailpit noch nicht bereit */ }
    if (!msg) await new Promise((r) => setTimeout(r, 2000));
  }
  record('Bestell-E-Mail', 'E-Mail an Kunden versendet (Mailpit)', !!msg, msg ? `Betreff: „${msg.Subject}“` : 'keine E-Mail an ci-test@pawline.test');
  if (msg) {
    const full = await (await fetch(`${MAILPIT}/api/v1/message/${msg.ID}`)).json();
    const text = (full.Text || '') + '\n' + (full.HTML || '').replace(/<[^>]+>/g, ' ');
    checkTaxTexts('Bestell-E-Mail', text);
    const atts = (full.Attachments || []).map((a) => a.FileName);
    record('Bestell-E-Mail', 'Rechnung als Anhang', atts.some((n) => /\.pdf$/i.test(n)), atts.length ? atts.join(', ') : 'kein Anhang (erwartet ohne Germanized Pro)', true);
  }
}

writeFileSync(`${OUT}/checkout.json`, JSON.stringify(results, null, 2));
const failed = results.filter((r) => r.status === 'FAIL').length;
console.log(`\nErgebnis: ${results.filter((r) => r.status === 'ok').length} ok, ${results.filter((r) => r.status === 'WARN').length} Warnungen, ${failed} Fehler`);
process.exit(failed ? 1 : 0);
