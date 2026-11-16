// Fasst die Testergebnisse aus test-results/ zu einem Markdown-Bericht zusammen
// (GitHub-Job-Zusammenfassung und Artefakt). Aufruf: node tests/ci/report.mjs
import { readFileSync, writeFileSync, existsSync } from 'node:fs';

const OUT = process.env.RESULTS_DIR || 'test-results';
const read = (f) => (existsSync(`${OUT}/${f}`) ? readFileSync(`${OUT}/${f}`, 'utf8') : null);
const icon = { ok: '✅', WARN: '⚠️', FAIL: '❌', OFFEN: '⏸️' };
const esc = (s) => String(s ?? '').replace(/\|/g, '\\|').replace(/\n/g, ' ');
const lines = [];
const steps = JSON.parse(process.env.STEP_OUTCOMES || '{}');

lines.push('# Pawline – WordPress-Prüfung (GitHub Actions)', '');
lines.push(`Commit \`${(process.env.GITHUB_SHA || '').slice(0, 7)}\` · Lauf ${process.env.GITHUB_RUN_ID || '–'}`, '');
const versions = read('versionen.md');
if (versions) lines.push('<details><summary>Installierte Versionen</summary>', '', versions.replace(/^# .*\n/, ''), '</details>', '');

lines.push('## Schritte', '', '| Schritt | Ergebnis |', '|---|---|');
for (const [name, outcome] of Object.entries(steps)) lines.push(`| ${name} | ${outcome === 'success' ? '✅' : outcome === 'skipped' ? '⏭️ übersprungen' : '❌ ' + outcome} |`);
lines.push('');

// wp-smoke.php (Textausgabe)
const smoke = read('wp-smoke.log');
if (smoke) {
  lines.push('## WordPress-Integrationsprüfung (`tests/wp-smoke.php`)', '', '| | Prüfung |', '|---|---|');
  for (const l of smoke.split('\n')) {
    const m = l.match(/^\s+(ok|WARN|FAIL)\s+(.*)$/);
    if (m && m[1] !== 'ok') lines.push(`| ${icon[m[1]]} | ${esc(m[2])} |`);
  }
  const sum = smoke.match(/Ergebnis:.*$/m);
  lines.push('', sum ? `**${sum[0]}** (nur Warnungen und Fehler aufgelistet)` : '**Keine Ergebniszeile – Prüfung abgebrochen, siehe Protokoll.**', '');
}

for (const [file, title] of [['checkout.json', 'Kaufablauf im Browser (`tests/e2e/checkout.mjs`)'], ['order.json', 'Bestellung in der Datenbank (`tests/ci/verify-order.php`)'], ['mobile.json', 'Handy-Ansicht (`tests/e2e/mobile.mjs`)'], ['admin.json', 'Verwaltung im Browser (`tests/e2e/admin.mjs`)'], ['admin-db.json', 'Verwaltung in der Datenbank (`tests/ci/verify-admin.php`)'], ['editor.json', 'WordPress-Editor (`tests/e2e/editor.mjs`)']]) {
  const raw = read(file);
  lines.push(`## ${title}`, '');
  if (!raw) { lines.push('_Nicht gelaufen._', ''); continue; }
  lines.push('| | Bereich | Prüfung | Detail |', '|---|---|---|---|');
  for (const r of JSON.parse(raw)) lines.push(`| ${icon[r.status] || r.status} | ${esc(r.area)} | ${esc(r.name)} | ${esc(r.detail)} |`);
  lines.push('');
}

lines.push('## ⏸️ Ausdrücklich NICHT geprüft', '',
  '| Bereich | Grund |', '|---|---|',
  '| Kundenrechnung (PDF, Nummernkreis, § 19-Hinweis auf dem Beleg) | Germanized Pro nicht installiert (Lizenz fehlt) |',
  '| Storno-/Korrekturbelege bei Erstattung | Germanized Pro nicht installiert |',
  '| Rechnung als E-Mail-Anhang | Germanized Pro nicht installiert |',
  '| Zahlung mit Stripe (Testkarte erfolgreich/abgelehnt) | Keine Stripe-Testschlüssel; im Kaufablauf wurde nur die Offline-Zahlart „Überweisung (nur CI-Test)“ verwendet |',
  '| Stripe-Webhooks, doppelte Zahlungsbenachrichtigung | Keine Stripe-Testschlüssel |',
  '| Versandkosten | Noch keine echten Versandregeln; im Test nur „Versand (nur CI-Test)“ ohne Kosten |',
  '| Gestalterische Beurteilung (Farben, Abstände) | Nur Screenshots; Beurteilung durch Menschen |',
  '| Hosting-Server (nginx/Apache, wp-config.php) | Nur Docker-Testumgebung (Apache) |', '');

writeFileSync(`${OUT}/bericht.md`, lines.join('\n'));
console.log(lines.join('\n'));
