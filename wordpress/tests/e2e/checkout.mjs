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
// Einheitlicher Wortlaut aus den Germanized-Einstellungen (setup-wp.sh). Der alte Germanized-Standardtext zählt nicht.
const NOTICE = /Umsatzsteuerbefreit\s+gemäß\s+§\s*19\s+UStG\./i;
const OLD_NOTICE = /Kein Mehrwertsteuerausweis|keine Umsatzsteuer berechnet/i;
const TAX_SHOWN = /(inkl\.|inklusive|zzgl\.|zuzüglich)\s*(\d+([,.]\d+)?\s*%\s*)?(MwSt|USt|Mehrwertsteuer|Umsatzsteuer)|\b\d+([,.]\d+)?\s*%\s*(MwSt|USt)\b/i;

// Prüft, ob der § 19-Hinweis DIREKT beim Preis bzw. Gesamtbetrag steht (Abstand in Pixeln, gleiche Spalte).
// Treffer im Kopf- oder Fußbereich der Seite zählen nie.
// anchor: CSS-Selektor des Preises/Summenelements; rowText: optional Regex-Quelle für die Summenzeile;
// scope: optional Element-Selektor/Text, auf den Anker und Hinweis beschränkt werden.
async function checkNoticeAtPrice(area, page, { anchor, label, maxGap = 48, rowText = null, scopeText = null, pattern = NOTICE, title = '§ 19-Hinweis' }) {
  const r = await page.evaluate(({ anchor, maxGap, rowText, scopeText, noticeSrc }) => {
    const NOTICE = new RegExp(noticeSrc, 'i');
    const inChrome = (el) => !!el.closest('header, footer, .wp-block-template-part');
    const visible = (el) => { const b = el.getBoundingClientRect(); return b.width > 0 && b.height > 0 && getComputedStyle(el).visibility !== 'hidden'; };
    let root = document;
    if (scopeText) {
      const card = [...document.querySelectorAll('li, .wc-block-product, .wp-block-post, .product')]
        .filter((e) => e.textContent.includes(scopeText) && !inChrome(e))
        .sort((a, b) => a.textContent.length - b.textContent.length)[0];
      if (!card) return { error: `Produktkarte „${scopeText}“ nicht gefunden` };
      root = card;
    }
    let anchors = [...root.querySelectorAll(anchor)].filter((e) => visible(e) && !inChrome(e));
    if (rowText) {
      const re = new RegExp(rowText, 'i');
      anchors = anchors.filter((e) => re.test(e.textContent));
    }
    const a = anchors[0];
    if (!a) return { error: `Preis-/Summenelement nicht gefunden (${anchor}${rowText ? ' mit „' + rowText + '“' : ''})` };
    const ab = a.getBoundingClientRect();
    // Kleinste sichtbare Elemente mit dem Hinweistext (nicht deren Eltern).
    const leaves = [...root.querySelectorAll('*')]
      .filter((e) => NOTICE.test(e.textContent) && ![...e.children].some((c) => NOTICE.test(c.textContent)) && visible(e));
    const all = leaves.map((e) => {
      const b = e.getBoundingClientRect();
      return { text: e.textContent.replace(/\s+/g, ' ').trim().slice(0, 100), chrome: inChrome(e), gap: Math.round(b.top - ab.bottom), overlapX: b.left < ab.right + 150 && b.right > ab.left - 150, sameRow: b.top < ab.bottom && b.bottom > ab.top };
    });
    const near = all.filter((n) => !n.chrome && n.overlapX && (n.sameRow || (n.gap >= -4 && n.gap <= maxGap)));
    return { anchorText: a.textContent.replace(/\s+/g, ' ').trim().slice(0, 60), near: near[0] || null, others: all.slice(0, 4) };
  }, { anchor, maxGap, rowText, scopeText, noticeSrc: pattern.source });

  if (r.error) return record(area, `${title} ${label}`, false, r.error);
  if (r.near) return record(area, `${title} ${label}`, true, `„${r.near.text}“ (${r.near.sameRow ? 'gleiche Zeile' : r.near.gap + ' px darunter'}; Bezug: „${r.anchorText}“)`);
  const where = r.others.length ? r.others.map((o) => `${o.chrome ? 'Kopf-/Fußbereich' : (o.overlapX ? o.gap + ' px Abstand' : 'andere Spalte')}: „${o.text.slice(0, 50)}“`).join(' | ') : 'kein Hinweis auf der Seite';
  record(area, `${title} ${label}`, false, `nicht beim Preis (max. ${maxGap} px). Gefunden: ${where}`);
}
async function checkNoTaxShown(area, page) {
  const all = await page.innerText('body');
  const tax = all.match(TAX_SHOWN);
  record(area, 'Kein Umsatzsteuerausweis', !tax, tax ? `gefunden: „${context(all, tax.index)}“` : '');
  const old = all.match(OLD_NOTICE);
  record(area, 'Kein abweichender alter Hinweistext', !old, old ? `gefunden: „${context(all, old.index)}“` : '');
}
function checkTaxText(area, text) {
  const notice = text.match(NOTICE);
  record(area, 'Kleinunternehmerhinweis sichtbar', !!notice, notice ? `„${context(text, notice.index)}“` : 'kein „§ 19 UStG“/„Kleinunternehmer“');
  const tax = text.match(TAX_SHOWN);
  record(area, 'Kein Umsatzsteuerausweis', !tax, tax ? `gefunden: „${context(text, tax.index)}“` : '');
  const old = text.match(OLD_NOTICE);
  record(area, 'Kein abweichender alter Hinweistext', !old, old ? `gefunden: „${context(text, old.index)}“` : '');
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
  // 0. Produktübersicht (Shop) – dort kann direkt in den Warenkorb gelegt werden.
  const sresp = await page.goto(urls.shop, { waitUntil: 'networkidle' });
  record('Produktübersicht', 'Shopseite lädt', sresp?.ok() ?? false, `${urls.shop} → HTTP ${sresp?.status()}`);
  const sText = await page.innerText('body');
  record('Produktübersicht', 'Demoprodukt mit 19,90 € gelistet', sText.includes('[DEMO] Einfaches Produkt') && /19,90\s*€/.test(sText));
  const listButton = await page.evaluate(() => {
    const card = [...document.querySelectorAll('li, .wc-block-product, .wp-block-post')]
      .filter((e) => e.textContent.includes('[DEMO] Einfaches Produkt')).sort((a, b) => a.textContent.length - b.textContent.length)[0];
    const btn = card?.querySelector('.wp-block-button__link, .add_to_cart_button, button');
    return btn ? btn.textContent.trim() : '';
  });
  record('Produktübersicht', 'Warenkorb-Schaltfläche direkt in der Übersicht', !!listButton, listButton ? `„${listButton}“` : 'keine', true);
  await checkNoticeAtPrice('Produktübersicht', page, { anchor: '.wc-block-components-product-price, .wp-block-woocommerce-product-price, .price', label: 'direkt unter dem Preis der Produktkarte', scopeText: '[DEMO] Einfaches Produkt' });
  await checkNoticeAtPrice('Produktübersicht', page, { anchor: '.wc-block-components-product-price, .wp-block-woocommerce-product-price, .price', label: 'beim Preis der Produktkarte', scopeText: '[DEMO] Einfaches Produkt', maxGap: 90, pattern: /Versandkosten/i, title: '„zzgl. Versandkosten“' });
  const dup = await page.evaluate((src) => {
    const re = new RegExp(src, 'i');
    const card = [...document.querySelectorAll('li, .wc-block-product, .wp-block-post')]
      .filter((e) => e.textContent.includes('[DEMO] Einfaches Produkt')).sort((a, b) => a.textContent.length - b.textContent.length)[0];
    if (!card) return -1;
    return [...card.querySelectorAll('*')].filter((e) => re.test(e.textContent) && ![...e.children].some((c) => re.test(c.textContent)) && e.getBoundingClientRect().height > 0).length;
  }, NOTICE.source);
  record('Produktübersicht', '§ 19-Hinweis genau einmal je Produktkarte', dup === 1, `${dup}× gefunden`);
  await checkNoTaxShown('Produktübersicht', page);
  await shot('0-uebersicht');

  // 1. Produktseite
  const resp = await page.goto(urls.product, { waitUntil: 'networkidle' });
  record('Produktseite', 'Demoprodukt lädt', resp?.ok() ?? false, `${urls.product} → HTTP ${resp?.status()}`);
  const pText = await page.innerText('body');
  record('Produktseite', 'Titel „[DEMO] Einfaches Produkt“', pText.includes('[DEMO] Einfaches Produkt'));
  record('Produktseite', 'Preis 19,90 €', /19,90\s*€/.test(pText));
  await checkNoticeAtPrice('Produktseite', page, { anchor: 'main .wp-block-woocommerce-product-price', label: 'direkt unter dem Preis' });
  await checkNoticeAtPrice('Produktseite', page, { anchor: 'main .wp-block-woocommerce-product-price', label: 'beim Preis', maxGap: 90, pattern: /zzgl\.?\s*Versandkosten|Versandkosten/i, title: '„zzgl. Versandkosten“' });
  await checkNoTaxShown('Produktseite', page);
  await shot('1-produkt');

  // 2. Warenkorb
  const add = await firstVisible(page, ['button.single_add_to_cart_button', 'button[name="add-to-cart"]', '.wc-block-components-product-button__button']);
  record('Warenkorb', '„In den Warenkorb“-Schaltfläche vorhanden', !!add);
  if (add) {
    await Promise.all([page.waitForLoadState('networkidle'), add.click()]);
    await page.waitForTimeout(1500);
  }
  await page.goto(urls.cart, { waitUntil: 'networkidle' });
  // Der Warenkorb-Block lädt die Positionen per JavaScript nach.
  // Zuerst erscheinen leere Platzhalterzeilen; erst der Produktname zeigt, dass die Daten da sind.
  await page.waitForSelector('.wc-block-cart-items__row .wc-block-components-product-name, .woocommerce-cart-form .cart_item .product-name a', { state: 'visible', timeout: 30000 }).catch(() => {});
  await page.waitForTimeout(500);
  const cText = await page.innerText('body');
  const cartOk = cText.includes('[DEMO] Einfaches Produkt') && /19,90\s*€/.test(cText);
  if (!cartOk) {
    const diag = await page.evaluate(() => ({
      rows: document.querySelectorAll('.wc-block-cart-items__row, .cart_item').length,
      names: [...document.querySelectorAll('.wc-block-components-product-name, .product-name')].map((e) => e.textContent.trim()),
      html: (document.querySelector('.wp-block-woocommerce-cart, .woocommerce-cart-form, main')?.outerHTML || '').replace(/\s+/g, ' ').slice(0, 1500),
    }));
    console.log('Diagnose Warenkorb:', JSON.stringify(diag, null, 1));
    console.log('Seitentext Warenkorb:', cText.replace(/\s+/g, ' ').slice(0, 1200));
  }
  record('Warenkorb', 'Demoprodukt im Warenkorb', cText.includes('[DEMO] Einfaches Produkt'));
  record('Warenkorb', 'Summe 19,90 €', /19,90\s*€/.test(cText));
  await checkNoticeAtPrice('Warenkorb', page, { anchor: '.wc-block-components-totals-footer-item, .order-total', label: 'beim Gesamtbetrag', maxGap: 60 });
  await checkNoTaxShown('Warenkorb', page);
  await shot('2-warenkorb');

  // 3. Kasse
  await page.goto(urls.checkout, { waitUntil: 'networkidle' });
  await page.waitForSelector('.wc-block-checkout, form.checkout', { timeout: 30000 }).catch(() => {});
  const isBlock = (await page.locator('.wc-block-checkout').count()) > 0;
  record('Kasse', 'Kasse lädt', isBlock || (await page.locator('form.checkout').count()) > 0, isBlock ? 'Block-Kasse' : 'klassische Kasse');
  await checkNoticeAtPrice('Kasse', page, { anchor: '.wc-block-components-totals-footer-item, .order-total', label: 'beim Gesamtbetrag', maxGap: 60 });
  await checkNoTaxShown('Kasse', page);

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

  let placeBtn = await firstVisible(page, ['.wc-block-components-checkout-place-order-button', '#place_order', 'button.wc-gzd-place-order', 'button[type=submit].wc-block-components-button']);
  if (!placeBtn) {
    const byRole = page.getByRole('button', { name: /jetzt kaufen|zahlungspflichtig|bestellung aufgeben|kaufen|place order/i }).first();
    if ((await byRole.count()) && (await byRole.isVisible().catch(() => false))) placeBtn = byRole;
  }
  if (!placeBtn) {
    const buttons = await page.evaluate(() => [...document.querySelectorAll('button, input[type=submit], a.button')]
      .filter((b) => b.offsetParent !== null)
      .map((b) => `${b.tagName.toLowerCase()}.${(b.className || '').toString().trim().replace(/\s+/g, '.')} „${(b.innerText || b.value || '').trim().slice(0, 40)}“`));
    console.log('Sichtbare Schaltflächen in der Kasse:\n  ' + buttons.join('\n  '));
  }
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
    await checkNoticeAtPrice('Bestellbestätigung', page, { anchor: '.wp-block-woocommerce-order-confirmation-totals tr, .woocommerce-order-overview__total, .woocommerce-table--order-details tfoot tr', rowText: '^\\s*(Gesamt|Total)', label: 'beim Gesamtbetrag', maxGap: 80 });
    await checkNoTaxShown('Bestellbestätigung', page);
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
    checkTaxText('Bestell-E-Mail', text);
    const atts = (full.Attachments || []).map((a) => a.FileName);
    record('Bestell-E-Mail', 'Rechnung als Anhang', atts.some((n) => /\.pdf$/i.test(n)), atts.length ? atts.join(', ') : 'kein Anhang (erwartet ohne Germanized Pro)', true);
  }
}

writeFileSync(`${OUT}/checkout.json`, JSON.stringify(results, null, 2));
const failed = results.filter((r) => r.status === 'FAIL').length;
console.log(`\nErgebnis: ${results.filter((r) => r.status === 'ok').length} ok, ${results.filter((r) => r.status === 'WARN').length} Warnungen, ${failed} Fehler`);
process.exit(failed ? 1 : 0);
