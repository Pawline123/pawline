// Verwaltung im Browser durchspielen (Pawline-Plugin), gegen die Docker-Testumgebung.
// Aufruf: WP_ADMIN_USER=… WP_ADMIN_PASSWORD=… node tests/e2e/admin.mjs
// Voraussetzung: Kaufablauf-Test gelaufen, tests/ci/prepare-admin.php hat die Testbestellung auf „In Bearbeitung“ gesetzt.
//
// Ablauf: Anmelden → Pawline › Überblick → „Lieferantenauftrag erfassen“ (Bestellnummer vorbefüllt) → speichern →
// Eingangsbeleg mit PDF + XML hochladen und dem Auftrag zuordnen → Download prüfen → getarnte PHP-Datei wird
// abgelehnt → Tracking in der Bestellung eintragen → Auswertung → beide CSV-Exporte herunterladen und prüfen.
// Alle angelegten Daten tragen „[DEMO]“ bzw. „DEMO-“ im Namen.

import { chromium } from 'playwright';
import { readFileSync, writeFileSync, mkdirSync } from 'node:fs';

const OUT = process.env.RESULTS_DIR || 'test-results';
mkdirSync(`${OUT}/screenshots`, { recursive: true });
mkdirSync(`${OUT}/files`, { recursive: true });
const ids = JSON.parse(readFileSync(`${OUT}/admin-urls.json`, 'utf8'));
const ADMIN = ids.admin.replace(/\/$/, '');
const BASE = ADMIN.replace(/\/wp-admin$/, '');
const USER = process.env.WP_ADMIN_USER;
const PASS = process.env.WP_ADMIN_PASSWORD;

const results = [];
const record = (area, name, ok, detail = '', warnOnly = false) => {
  const status = ok ? 'ok' : warnOnly ? 'WARN' : 'FAIL';
  results.push({ area, name, status, detail });
  console.log(`  ${status.padEnd(5)} [${area}] ${name}${detail ? ' – ' + detail : ''}`);
};
const created = {};

// Testdateien (gekennzeichnet).
const pdf = `${OUT}/files/DEMO-Rechnung.pdf`;
const xml = `${OUT}/files/DEMO-E-Rechnung.xml`;
const fake = `${OUT}/files/DEMO-getarnt.pdf`;
writeFileSync(pdf, '%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n% DEMO – keine echte Rechnung\n');
writeFileSync(xml, '<?xml version="1.0" encoding="UTF-8"?>\n<rsm:CrossIndustryInvoice xmlns:rsm="urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100"><!-- DEMO --></rsm:CrossIndustryInvoice>\n');
writeFileSync(fake, "<?php echo 'DEMO'; ?>\n");

const today = new Date();
const iso = (d) => d.toISOString().slice(0, 10);
const day = (n) => iso(new Date(today.getTime() + n * 86400000));

const browser = await chromium.launch();
const context = await browser.newContext({ locale: 'de-DE', viewport: { width: 1366, height: 900 }, acceptDownloads: true });
const page = await context.newPage();
const shot = (name) => page.screenshot({ path: `${OUT}/screenshots/admin-${name}.png`, fullPage: true }).catch(() => {});
const notices = async () => (await page.locator('.notice, .error, .updated').allInnerTexts().catch(() => [])).map((t) => t.replace(/\s+/g, ' ').trim()).filter(Boolean);

try {
  // Anmelden
  await page.goto(`${BASE}/wp-login.php`, { waitUntil: 'networkidle' });
  await page.fill('#user_login', USER);
  await page.fill('#user_pass', PASS);
  await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle' }), page.click('#wp-submit')]);
  record('Anmeldung', 'Verwaltung erreichbar', page.url().includes('/wp-admin'), page.url().replace(BASE, ''));

  // Überblick
  await page.goto(`${ADMIN}/admin.php?page=pawline`, { waitUntil: 'networkidle' });
  const overview = await page.innerText('#wpbody-content');
  record('Überblick', `Bestellung #${ids.order_id} unter „Beim Lieferanten bestellen“`, overview.includes(`#${ids.order_id}`) || overview.includes(String(ids.order_id)));
  await shot('1-ueberblick');
  const btn = page.locator(`a.button:has-text("Lieferantenauftrag erfassen")[href*="wc_order=${ids.order_id}"]`).first();
  record('Überblick', '„Lieferantenauftrag erfassen“ vorhanden', (await btn.count()) > 0);

  // Lieferantenauftrag
  if (await btn.count()) await Promise.all([page.waitForLoadState('networkidle'), btn.click()]);
  else await page.goto(`${ADMIN}/post-new.php?post_type=pawline_po&wc_order=${ids.order_id}`, { waitUntil: 'networkidle' });
  record('Lieferantenauftrag', 'Bestellnummer vorbefüllt', (await page.inputValue('#pawline_order_ids')).includes(String(ids.order_id)), await page.inputValue('#pawline_order_ids'));
  await page.fill('#pawline_supplier_name', '[DEMO] Lieferant');
  await page.fill('#pawline_external_no', 'DEMO-LA-0001');
  await page.fill('#pawline_amount', '8,50');
  await page.selectOption('#pawline_status', 'bestellt');
  await page.fill('#pawline_ordered_at', iso(today));
  await Promise.all([page.waitForURL(/post\.php\?post=\d+&action=edit/, { timeout: 30000 }), page.click('#publish')]);
  created.po_id = Number(new URL(page.url()).searchParams.get('post'));
  const poText = await page.innerText('#wpbody-content');
  record('Lieferantenauftrag', 'gespeichert', created.po_id > 0, `LA-${created.po_id}; Titel: ${(await page.inputValue('#title').catch(() => '')) || '–'}`);
  record('Lieferantenauftrag', 'Betrag 8,50 und Status übernommen', (await page.inputValue('#pawline_amount')) === '8,50' && (await page.inputValue('#pawline_status')) === 'bestellt');
  record('Lieferantenauftrag', 'Änderungsprotokoll-Eintrag', /Protokoll|geändert|angelegt/i.test(poText), '', true);
  await shot('2-lieferantenauftrag');

  // Eingangsbeleg mit PDF + XML
  await page.goto(`${ADMIN}/post-new.php?post_type=pawline_invoice`, { waitUntil: 'networkidle' });
  await page.fill('#pi_supplier', '[DEMO] Lieferant');
  await page.fill('#pi_no', 'DEMO-RE-0001');
  await page.fill('#pi_date', iso(today));
  await page.fill('input[name="pawline_inv[net]"]', '7,14');
  await page.fill('input[name="pawline_inv[tax]"]', '1,36');
  await page.fill('input[name="pawline_inv[gross]"]', '8,50');
  await page.fill('#pi_taxnote', '19 % USt (DEMO)');
  await page.fill('#pi_pos', String(created.po_id));
  const fileInput = page.locator('#pawline_files');
  record('Eingangsbeleg', 'Upload-Feld vorhanden (Ablage sicher konfiguriert)', (await fileInput.count()) > 0, (await notices()).join(' | '));
  if (await fileInput.count()) await fileInput.setInputFiles([pdf, xml]);
  await Promise.all([page.waitForURL(/post\.php\?post=\d+&action=edit/, { timeout: 30000 }), page.click('#publish')]);
  created.invoice_id = Number(new URL(page.url()).searchParams.get('post'));
  const filesBox = await page.innerText('#pawline_inv_files').catch(() => '');
  const okCount = (filesBox.match(/unverändert/g) || []).length;
  record('Eingangsbeleg', 'gespeichert', created.invoice_id > 0, `ER-${created.invoice_id}`);
  record('Eingangsbeleg', 'PDF und XML abgelegt, Prüfsumme „unverändert“', okCount === 2, `${okCount}× „unverändert“; Hinweise: ${(await notices()).join(' | ') || '–'}`);
  await shot('3-eingangsbeleg');

  // Download (angemeldet)
  const link = page.locator('#pawline_inv_files a[href*="pawline_download"]').first();
  if (await link.count()) {
    const [dl] = await Promise.all([page.waitForEvent('download', { timeout: 15000 }), link.click()]);
    const p = `${OUT}/files/download-${dl.suggestedFilename()}`;
    await dl.saveAs(p);
    const head = readFileSync(p).subarray(0, 5).toString();
    record('Eingangsbeleg', 'Download als Admin liefert die Originaldatei', head === '%PDF-', `${dl.suggestedFilename()}, beginnt mit „${head}“`);
  } else record('Eingangsbeleg', 'Download-Link vorhanden', false);

  // Getarnte PHP-Datei (T-20)
  await page.goto(`${ADMIN}/post-new.php?post_type=pawline_invoice`, { waitUntil: 'networkidle' });
  await page.fill('#pi_supplier', '[DEMO] Lieferant');
  await page.fill('#pi_no', 'DEMO-RE-0002-getarnt');
  if (await page.locator('#pawline_files').count()) await page.setInputFiles('#pawline_files', fake);
  await Promise.all([page.waitForURL(/post\.php\?post=\d+&action=edit/, { timeout: 30000 }), page.click('#publish')]);
  const fakeBox = await page.innerText('#pawline_inv_files').catch(() => '');
  const fakeNotes = (await notices()).join(' | ');
  record('Eingangsbeleg', 'Getarnte PHP-Datei abgelehnt (T-20)', /Noch keine Datei/.test(fakeBox) && /Nur PDF, XML/i.test(fakeNotes), fakeNotes.slice(0, 160));
  await shot('4-abgelehnt');

  // Tracking in der Bestellung
  await page.goto(ids.order_edit, { waitUntil: 'networkidle' });
  record('Bestellung', 'Pawline-Box „Dropshipping“ sichtbar', (await page.locator('#pawline_dropship').count()) > 0);
  const boxText = await page.innerText('#pawline_dropship').catch(() => '');
  record('Bestellung', `Lieferantenauftrag LA-${created.po_id} in der Bestellung angezeigt`, boxText.includes(`LA-${created.po_id}`) || boxText.includes('DEMO-LA-0001'));
  await page.fill('input[name="pawline_ship[0][carrier]"]', 'DHL (DEMO)');
  await page.fill('input[name="pawline_ship[0][tracking]"]', 'DEMO00012345');
  await page.fill('input[name="pawline_ship[0][date]"]', iso(today));
  await Promise.all([page.waitForLoadState('networkidle'), page.click('button.save_order, #publish, button[name="save"]')]);
  await page.waitForTimeout(1000);
  record('Bestellung', 'Tracking gespeichert', (await page.inputValue('input[name="pawline_ship[0][tracking]"]').catch(() => '')) === 'DEMO00012345');
  const notesText = await page.innerText('#woocommerce-order-notes').catch(() => '');
  record('Bestellung', 'Bestellnotiz „Versandinfo erfasst“', /Versandinfo erfasst/.test(notesText));
  await shot('5-bestellung');

  // Auswertung + CSV
  await page.goto(`${ADMIN}/admin.php?page=pawline-reports&from=${day(-1)}&to=${day(1)}`, { waitUntil: 'networkidle' });
  const rep = await page.innerText('#wpbody-content');
  record('Auswertung', 'lädt mit Rohdifferenz und Grundlage', /Rohdifferenz/.test(rep) && /§ 19/.test(rep), '');
  record('Auswertung', 'Wort „Gewinn“ nicht als Ergebnis verwendet', !/\bGewinn:/.test(rep), '', true);
  await shot('6-auswertung');
  for (const [label, type, expect] of [['Lieferantenaufträge als CSV', 'purchases', 'DEMO-LA-0001'], ['Eingangsbelege als CSV', 'invoices', 'DEMO-RE-0001']]) {
    const a = page.locator(`a:has-text("${label}")`).first();
    if (!(await a.count())) { record('CSV-Export', `${label}: Link vorhanden`, false); continue; }
    const [dl] = await Promise.all([page.waitForEvent('download', { timeout: 15000 }), a.click()]);
    const p = `${OUT}/files/${dl.suggestedFilename()}`;
    await dl.saveAs(p);
    const buf = readFileSync(p);
    const txt = buf.toString('utf8');
    const lines = txt.replace(/^﻿/, '').trim().split(/\r?\n/);
    record('CSV-Export', `${label}: Datei ${dl.suggestedFilename()}`, buf[0] === 0xef && buf[1] === 0xbb && buf[2] === 0xbf && lines[0].includes(';'), 'UTF-8 mit BOM, Semikolon');
    record('CSV-Export', `${label}: enthält ${expect} mit Betrag 8,50`, txt.includes(expect) && txt.includes('8,50'), lines.slice(0, 2).join(' ⏎ ').slice(0, 220));
    if (type === 'invoices') record('CSV-Export', 'Eingangsbelege: SHA-256 enthalten', /[a-f0-9]{64}/.test(txt));
  }
} catch (e) {
  record('Ablauf', 'Unerwarteter Fehler', false, e.message.split('\n')[0]);
  await shot('fehler');
}
await browser.close();

writeFileSync(`${OUT}/admin-created.json`, JSON.stringify(created));
writeFileSync(`${OUT}/admin.json`, JSON.stringify(results, null, 2));
const failed = results.filter((r) => r.status === 'FAIL').length;
console.log(`\nErgebnis: ${results.filter((r) => r.status === 'ok').length} ok, ${results.filter((r) => r.status === 'WARN').length} Warnungen, ${failed} Fehler`);
process.exit(failed ? 1 : 0);
