// Bedienbarkeit des WordPress-Editors (Block-Theme „pawline“), gegen die Docker-Testumgebung.
// Aufruf: WP_ADMIN_USER=… WP_ADMIN_PASSWORD=… node tests/e2e/editor.mjs
//
// 1. Website-Editor: jede Theme-Vorlage und jeden Template-Teil öffnen → kein Block ungültig („unerwarteter oder
//    ungültiger Inhalt“) und kein fehlender Blocktyp.
// 2. Alle Pawline-Muster in eine Entwurfsseite einfügen, speichern, neu laden → alle Blöcke gültig.
// 3. Wie ein Nutzer ändern: Text tippen, Bild hochladen und einfügen, Spalten-Layout einfügen, Block verschieben,
//    speichern, neu laden → gültig und im Frontend sichtbar.
// 4. Website-Editor: Text der Startseiten-Vorlage ändern, speichern, neu laden → gültig und im Frontend sichtbar.

import { chromium } from 'playwright';
import { readFileSync, writeFileSync, mkdirSync } from 'node:fs';

const OUT = process.env.RESULTS_DIR || 'test-results';
mkdirSync(`${OUT}/screenshots`, { recursive: true });
const ids = JSON.parse(readFileSync(`${OUT}/admin-urls.json`, 'utf8'));
const ADMIN = ids.admin.replace(/\/$/, '');
const BASE = ADMIN.replace(/\/wp-admin$/, '');

const results = [];
const record = (area, name, ok, detail = '', warnOnly = false) => {
  const status = ok ? 'ok' : warnOnly ? 'WARN' : 'FAIL';
  results.push({ area, name, status, detail });
  console.log(`  ${status.padEnd(5)} [${area}] ${name}${detail ? ' – ' + detail : ''}`);
};

const TEMPLATES = ['front-page', 'index', 'page', 'page-wide', 'page-cart', 'page-checkout', 'single-product', 'archive-product', 'taxonomy-product_cat', 'taxonomy-product_tag', 'product-search-results', 'search', '404'];
const PARTS = ['header', 'footer'];

const browser = await chromium.launch();
const context = await browser.newContext({ locale: 'de-DE', viewport: { width: 1440, height: 1000 } });
const page = await context.newPage();
const validationLogs = [];
page.on('console', (m) => { if (/Block validation|invalid content|unexpected or invalid/i.test(m.text())) validationLogs.push(m.text().slice(0, 200)); });
const shot = (name) => page.screenshot({ path: `${OUT}/screenshots/editor-${name}.png` }).catch(() => {});

// Alle Blöcke im Editor prüfen (inkl. Template-Teile und verschachtelter Blöcke).
async function blockReport() {
  return page.evaluate(() => {
    const be = wp.data.select('core/block-editor');
    const all = be.getClientIdsWithDescendants().map((id) => be.getBlock(id)).filter(Boolean);
    return {
      count: all.length,
      invalid: all.filter((b) => b.isValid === false).map((b) => b.name),
      missing: all.filter((b) => b.name === 'core/missing').map((b) => b.attributes?.originalName || '?'),
    };
  });
}
async function waitForBlocks(min = 1) {
  await page.waitForFunction((min) => {
    try { return wp.data.select('core/block-editor').getClientIdsWithDescendants().length >= min; } catch { return false; }
  }, min, { timeout: 60000 });
  await page.waitForTimeout(2500); // Template-Teile und Woo-Blöcke laden nach
  await page.keyboard.press('Escape').catch(() => {}); // Willkommens-Dialog schließen
}
async function disableWelcomeGuides() {
  await page.evaluate(() => {
    try {
      const p = wp.data.dispatch('core/preferences');
      p.set('core/edit-site', 'welcomeGuide', false);
      p.set('core/edit-site', 'welcomeGuideStyles', false);
      p.set('core/edit-site', 'welcomeGuidePage', false);
      p.set('core/edit-site', 'welcomeGuideTemplate', false);
      p.set('core/edit-post', 'welcomeGuide', false);
      p.set('core', 'welcomeGuide', false);
    } catch { /* ältere WP-Version */ }
  });
}

try {
  // Anmelden
  await page.goto(`${BASE}/wp-login.php`, { waitUntil: 'networkidle' });
  await page.fill('#user_login', process.env.WP_ADMIN_USER);
  await page.fill('#user_pass', process.env.WP_ADMIN_PASSWORD);
  await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle' }), page.click('#wp-submit')]);

  // 1. Vorlagen und Template-Teile im Website-Editor
  let first = true;
  for (const [type, slugs] of [['wp_template', TEMPLATES], ['wp_template_part', PARTS]]) {
    for (const slug of slugs) {
      const before = validationLogs.length;
      await page.goto(`${ADMIN}/site-editor.php?postType=${type}&postId=${encodeURIComponent('pawline//' + slug)}&canvas=edit`, { waitUntil: 'domcontentloaded' });
      try {
        await page.waitForFunction(() => !!window.wp?.data?.select('core/block-editor'), null, { timeout: 60000 });
        if (first) { await disableWelcomeGuides(); first = false; }
        await waitForBlocks(1);
        const r = await blockReport();
        const ok = r.invalid.length === 0 && r.missing.length === 0;
        record('Website-Editor', `${type === 'wp_template' ? 'Vorlage' : 'Template-Teil'} ${slug}: alle Blöcke gültig`, ok,
          `${r.count} Blöcke${r.invalid.length ? '; ungültig: ' + r.invalid.join(', ') : ''}${r.missing.length ? '; fehlender Blocktyp: ' + r.missing.join(', ') : ''}${validationLogs.length > before ? '; Konsole: ' + validationLogs.slice(before, before + 1).join('') : ''}`);
        if (['front-page', 'single-product', 'header'].includes(slug)) await shot(`vorlage-${slug}`);
      } catch (e) {
        record('Website-Editor', `${slug}: öffnen`, false, e.message.split('\n')[0]);
        await shot(`fehler-${slug}`);
      }
    }
  }

  // 2. Alle Pawline-Muster in eine Entwurfsseite
  await page.goto(`${ADMIN}/post-new.php?post_type=page`, { waitUntil: 'domcontentloaded' });
  await page.waitForFunction(() => !!window.wp?.data?.select('core/editor')?.getCurrentPostId(), null, { timeout: 60000 });
  await disableWelcomeGuides();
  await page.keyboard.press('Escape').catch(() => {});
  const patterns = await page.evaluate(async () => {
    const reg = await wp.data.resolveSelect('core').getBlockPatterns();
    const ours = (reg || []).filter((p) => p.name.startsWith('pawline/'));
    const be = wp.data.dispatch('core/block-editor');
    for (const p of ours) be.insertBlocks(wp.blocks.parse(p.content));
    wp.data.dispatch('core/editor').editPost({ title: '[DEMO] Alle Pawline-Muster', status: 'draft' });
    await wp.data.dispatch('core/editor').savePost();
    return ours.map((p) => p.name);
  });
  record('Muster', 'Pawline-Muster gefunden', patterns.length === 7, patterns.join(', '));
  const patternPostId = await page.evaluate(() => wp.data.select('core/editor').getCurrentPostId());
  await page.goto(`${ADMIN}/post.php?post=${patternPostId}&action=edit`, { waitUntil: 'domcontentloaded' });
  await page.waitForFunction(() => !!window.wp?.data?.select('core/block-editor'), null, { timeout: 60000 });
  await waitForBlocks(5);
  let r = await blockReport();
  record('Muster', 'Nach Speichern und Neuladen alle Blöcke gültig', r.invalid.length === 0 && r.missing.length === 0, `${r.count} Blöcke${r.invalid.length ? '; ungültig: ' + r.invalid.join(', ') : ''}`);
  await shot('muster');

  // 3. Seite wie ein Nutzer bearbeiten: Text tippen, Bild, Spalten, verschieben
  await page.goto(`${ADMIN}/post-new.php?post_type=page`, { waitUntil: 'domcontentloaded' });
  await page.waitForFunction(() => !!window.wp?.data?.select('core/editor')?.getCurrentPostId(), null, { timeout: 60000 });
  await page.keyboard.press('Escape').catch(() => {});
  await page.waitForTimeout(1500);
  // Titel und Absatz über die Oberfläche tippen
  const canvas = page.frameLocator('iframe[name="editor-canvas"]');
  const hasIframe = (await page.locator('iframe[name="editor-canvas"]').count()) > 0;
  const root = hasIframe ? canvas : page;
  await root.locator('.editor-post-title__input, h1.wp-block-post-title').first().click();
  await page.keyboard.type('[DEMO] Bearbeitungstest');
  await page.keyboard.press('Enter');
  await page.keyboard.type('Dieser Absatz wurde im Test über die Tastatur eingegeben.');
  record('Bearbeiten', 'Titel und Absatz über die Tastatur eingegeben', true, hasIframe ? 'Editor-Inhalt im iframe' : 'Editor ohne iframe');
  // Bild hochladen (Mediathek-Schnittstelle des Editors) und einfügen; Spalten-Layout; verschieben
  const edit = await page.evaluate(async () => {
    const c = document.createElement('canvas'); c.width = 600; c.height = 300;
    const g = c.getContext('2d'); g.fillStyle = '#5e7152'; g.fillRect(0, 0, 600, 300); g.fillStyle = '#fff'; g.font = '40px sans-serif'; g.fillText('DEMO-Bild', 180, 160);
    const blob = await new Promise((res) => c.toBlob(res, 'image/png'));
    const fd = new FormData(); fd.append('file', blob, 'demo-bild.png'); fd.append('title', '[DEMO] Testbild');
    const media = await wp.apiFetch({ path: '/wp/v2/media', method: 'POST', body: fd });
    const { createBlock } = wp.blocks;
    const be = wp.data.dispatch('core/block-editor');
    const img = createBlock('core/image', { id: media.id, url: media.source_url, alt: 'DEMO-Testbild', sizeSlug: 'large' });
    const cols = createBlock('core/columns', {}, [
      createBlock('core/column', {}, [createBlock('core/heading', { level: 3, content: 'Linke Spalte (DEMO)' }), createBlock('core/paragraph', { content: 'Text links.' })]),
      createBlock('core/column', {}, [createBlock('core/heading', { level: 3, content: 'Rechte Spalte (DEMO)' }), createBlock('core/paragraph', { content: 'Text rechts.' })]),
    ]);
    be.insertBlocks([cols, img]);
    be.moveBlocksUp([img.clientId], ''); // Bild über die Spalten verschieben
    const order = wp.data.select('core/block-editor').getBlockOrder().map((id) => wp.data.select('core/block-editor').getBlock(id).name);
    await wp.data.dispatch('core/editor').editPost({ status: 'publish' });
    await wp.data.dispatch('core/editor').savePost();
    const id = wp.data.select('core/editor').getCurrentPostId();
    return { mediaId: media.id, order, id, link: wp.data.select('core/editor').getPermalink() };
  });
  record('Bearbeiten', 'Bild in die Mediathek hochgeladen und eingefügt', edit.mediaId > 0, `Medien-ID ${edit.mediaId}`);
  record('Bearbeiten', 'Block verschoben (Reihenfolge Absatz → Bild → Spalten)', edit.order.join(',') === 'core/paragraph,core/image,core/columns', edit.order.join(' → '));
  await page.goto(`${ADMIN}/post.php?post=${edit.id}&action=edit`, { waitUntil: 'domcontentloaded' });
  await page.waitForFunction(() => !!window.wp?.data?.select('core/block-editor'), null, { timeout: 60000 });
  await waitForBlocks(4);
  r = await blockReport();
  record('Bearbeiten', 'Nach Speichern und Neuladen alle Blöcke gültig', r.invalid.length === 0 && r.missing.length === 0, `${r.count} Blöcke${r.invalid.length ? '; ungültig: ' + r.invalid.join(', ') : ''}`);
  await shot('bearbeitet');
  await page.goto(edit.link, { waitUntil: 'networkidle' });
  const front = await page.innerText('main').catch(() => '');
  const imgOk = (await page.locator('main img[alt="DEMO-Testbild"]').count()) > 0;
  record('Bearbeiten', 'Änderungen im Frontend sichtbar (Text, Bild, Spalten)', front.includes('über die Tastatur eingegeben') && front.includes('Rechte Spalte (DEMO)') && imgOk);
  await page.screenshot({ path: `${OUT}/screenshots/editor-frontend-seite.png`, fullPage: true }).catch(() => {});

  // 4. Startseiten-Vorlage im Website-Editor ändern
  await page.goto(`${ADMIN}/site-editor.php?postType=wp_template&postId=${encodeURIComponent('pawline//front-page')}&canvas=edit`, { waitUntil: 'domcontentloaded' });
  await page.waitForFunction(() => !!window.wp?.data?.select('core/block-editor'), null, { timeout: 60000 });
  await waitForBlocks(3);
  const tpl = await page.evaluate(async () => {
    const be = wp.data.select('core/block-editor');
    const heading = be.getClientIdsWithDescendants().map((id) => be.getBlock(id)).find((b) => (b.name === 'core/heading' || b.name === 'core/paragraph') && be.getBlockParentsByBlockName(b.clientId, 'core/template-part').length === 0);
    if (!heading) return { ok: false, reason: 'kein Text-Block' };
    const text = 'DEMO-Änderung im Website-Editor';
    wp.data.dispatch('core/block-editor').updateBlockAttributes(heading.clientId, { content: text });
    await wp.data.dispatch('core').saveEditedEntityRecord('postType', 'wp_template', 'pawline//front-page');
    return { ok: true, name: heading.name, text };
  });
  record('Website-Editor', 'Startseiten-Text geändert und gespeichert', tpl.ok, tpl.ok ? tpl.name : tpl.reason);
  await page.reload({ waitUntil: 'domcontentloaded' });
  await page.waitForFunction(() => !!window.wp?.data?.select('core/block-editor'), null, { timeout: 60000 });
  await waitForBlocks(3);
  r = await blockReport();
  record('Website-Editor', 'Startseiten-Vorlage nach Speichern gültig', r.invalid.length === 0 && r.missing.length === 0, `${r.count} Blöcke${r.invalid.length ? '; ungültig: ' + r.invalid.join(', ') : ''}`);
  await page.goto(`${BASE}/`, { waitUntil: 'networkidle' });
  record('Website-Editor', 'Änderung auf der Startseite sichtbar', (await page.innerText('body')).includes('DEMO-Änderung im Website-Editor'));
} catch (e) {
  record('Ablauf', 'Unerwarteter Fehler', false, e.message.split('\n')[0]);
  await shot('fehler');
}
record('Konsole', 'Keine Meldungen zur Blockvalidierung', validationLogs.length === 0, validationLogs.slice(0, 2).join(' | '), true);
await browser.close();

writeFileSync(`${OUT}/editor.json`, JSON.stringify(results, null, 2));
const failed = results.filter((r) => r.status === 'FAIL').length;
console.log(`\nErgebnis: ${results.filter((r) => r.status === 'ok').length} ok, ${results.filter((r) => r.status === 'WARN').length} Warnungen, ${failed} Fehler`);
process.exit(failed ? 1 : 0);
