# CLAUDE.md – Arbeitsanweisungen für Claude Code im Pawline-Projekt

Projektspezifikation: `Pawline_Claude_Code_Startauftrag.txt` (gleicher Ordner; vor größeren Änderungen lesen).
Teststatus: `docs/TESTSTATUS.md` – **nach jeder Prüfung aktualisieren** (was wirklich geprüft wurde).

## Festgelegte Entscheidungen

- WordPress + WooCommerce, selbst gehostet; eigenes **Block-Theme** `pawline`, Bearbeitung über den Website-Editor.
- **Kleinunternehmer nach § 19 UStG**: WooCommerce-Steuerberechnung aus (`woocommerce_calc_taxes = no`), Germanized-Kleinunternehmer-Kennzeichnung an. Keine „inkl. MwSt.“-Angaben, kein 0-%-Steuersatz als Ersatz. Rechnungshinweis: „Für diesen Umsatz gilt die Steuerbefreiung für Kleinunternehmer gemäß § 19 UStG.“
- Rechnungen/Stornos/Lieferscheine: **Germanized Pro** (keine eigene Rechnungsnummernlogik bauen).
- Warenwirtschaft: **ATUM Free** (Lieferanten, Einkaufspreise, Lieferanten-SKU). ATUM-Einkaufsbestellungen **nicht** für Dropshipping verwenden bzw. nie als „erhalten“ buchen (würde Lagerzugang erzeugen).
- Eigenes Plugin `pawline-dropship`: nur die Lücken – Lieferantenauftrag ↔ Kundenbestellung, geschützte Eingangsbelege, manuelle Versandinfos, Überblick, Auswertung, CSV-Export.
- Zahlungen: offizielle Erweiterungen (Stripe zuerst, PayPal bei Bedarf), Testmodus. **Keine eigene Kartenverarbeitung.**
- Lieferantenbestellungen bleiben **manuell**. Nichts automatisch bestellen, bezahlen, Bestände oder Versandstatus „aktuell halten“.
- Einkaufsbeträge brutto inkl. nicht abziehbarer USt; Originalbeträge/Steuerangaben der Lieferanten unverändert speichern.
- Keine externen Schriften/CDNs (DSGVO).

## Regeln

- Keine Änderungen an WordPress-Kern, WooCommerce oder fremden Plugins. Anpassungen nur über Theme, eigenes Plugin, Hooks.
- Keine Zugangsdaten, Lizenzschlüssel, Kundendaten im Repository (`.env` ist ignoriert).
- Keine erfundenen Produktdaten, Preise, Versandkosten, Lieferzeiten, Rechts- oder Firmenangaben. Fehlende Werte als Platzhalter kennzeichnen.
- Demodaten nur lokal, immer `[DEMO]` im Namen + Meta `_pawline_demo=1`; nie in einen Live-Import übernehmen.
- Bestehenden Shopify-Shop und Domain `pawline-shop.de` nicht verändern. Keine Dienste buchen.
- Funktionen erst als „geprüft“ bezeichnen, wenn sie in WordPress tatsächlich getestet wurden.

## Befehle (Windows PowerShell, im Projektordner)

```powershell
.\scripts\pawline.ps1 pruefen | start | setup | demo | demo-entfernen | test | sicherung | logs | paket | stop
# Vor dem ersten Aufruf ggf.: powershell -ExecutionPolicy Bypass -File .\scripts\pawline.ps1 <befehl>
docker compose --profile tools run --rm wpcli wp <befehl>     # beliebiger WP-CLI-Befehl
docker compose --profile tools run --rm wpcli php /project/tests/run.php
```

## Struktur

- `wp-content/themes/pawline/` – `theme.json` (Farben/Schriften), `templates/`, `parts/`, `patterns/` (PHP-Muster mit Header), `style.css` (nur Details), `functions.php` (minimal).
- `wp-content/plugins/pawline-dropship/includes/` – `class-util.php` und `class-margin.php` sind WordPress-frei und unit-getestet; übrige Klassen nutzen WP/WC-APIs.
- Meta-Schlüssel: `_pawline_order_id` (LA→Bestellung, mehrfach), `_pawline_po_id` (Beleg→LA, mehrfach), `_pawline_files` (Beleg-Dateien mit SHA-256), `_pawline_shipments` (Bestellung), `_pawline_dropship` (Produkt).
- Rechte: `manage_pawline`, `manage_pawline_documents` + Post-Typ-Rechte `*_pawline_pos`, `*_pawline_invoices` (Admin, Shop-Manager).

## Offene Aufgaben für die erste Sitzung auf dem PC

1. `pruefen`, `start`, `setup`, `demo`, `test` auf dem PC ausführen (in GitHub Actions bereits grün, siehe `docs/TESTSTATUS.md`); Ergebnisse in `docs/TESTSTATUS.md` eintragen.
2. Website-Editor öffnen: Jede Vorlage und jedes Muster auf „Dieser Block enthält unerwarteten oder ungültigen Inhalt“ prüfen; ggf. Markup an die installierte WooCommerce-Version anpassen.
3. **Produktfilter** in `archive-product.html` ergänzen (Block „Produktfilter“ der installierten WooCommerce-Version im Editor einfügen, exportieren, Datei übernehmen). Bewusst noch nicht enthalten, weil sich das Markup zwischen WC-Versionen ändert.
4. ~~Germanized-Optionsname bestätigen~~ – erledigt: `woocommerce_gzd_small_enterprise` wirkt in Germanized 4.1.7 (GitHub Actions, 16.11.2026). Im Backend trotzdem einmal ansehen.
5. ~~§ 19-Hinweis am Preis~~ – erledigt (Lauf 9): Germanized-Block „Steuerhinweis“ unter jedem Preis (Produktseite, alle Listen), Germanized-Vorlage unter den Summen der Bestellbestätigung (`functions.php`). Neue Preis-/Summenblöcke immer mit diesem Block ergänzen; `checkout.mjs` prüft den Abstand.
6. Festlegen, ob Versand/Tracking über Germanized-Sendungen oder das Pawline-Feld geführt wird (Doppelpflege vermeiden).
7. Testplan `docs/TESTPLAN.md` abarbeiten (Germanized-Pro-Tests erst mit Lizenz).

## Spätere Phasen (nur vorbereiten, nicht ohne Zugänge umsetzen)

- Phase 3: Shopify-Produktexport (CSV) → wiederholbarer Import mit Protokoll, URL-Zuordnung, Weiterleitungen. Eingabedateien nach `import/` (ignoriert).
- Phase 4: ChatGPT-Connector über WordPress MCP Adapter + OAuth; zuerst nur lesend testen. Nicht als „verbunden“ melden, bevor Anmeldung und Datenabruf im echten Konto geprüft sind.
