# Teststatus

Bitte nach jeder Prüfung aktualisieren. Nur eintragen, was tatsächlich ausgeführt wurde.

## Stand 16.11.2026 – GitHub Actions (Workflow „WordPress-Prüfung“)

**Umgebung:** GitHub-Runner `ubuntu-24.04`, Docker-Testumgebung aus `docker-compose.yml` (Apache, PHP 8.3, MariaDB 11.4, Mailpit), Chromium über Playwright 1.49.1.
**Installiert durch `setup-wp.sh`:** WordPress (aktuell), WooCommerce 11.3.0, Germanized 4.1.7, ATUM 2.0.7, WooCommerce Stripe Gateway 11.2.0, Theme `pawline` 0.1.0, Plugin `pawline-dropship` 0.1.0. **Nicht installiert:** Germanized Pro (Lizenz fehlt).
**Erster vollständig grüner Lauf:** Lauf 6, https://github.com/Pawline123/pawline/actions/runs/43839938399 (alle Schritte erfolgreich).
**Alle Läufe:** https://github.com/Pawline123/pawline/actions/workflows/wordpress.yml – Bericht je Lauf in der Job-Zusammenfassung, Screenshots/Protokolle im Artefakt `wordpress-testergebnisse`.

### ✅ In WordPress geprüft (automatisch)

| Prüfung | Ergebnis |
|---|---|
| Container-Start, `setup-wp.sh` vollständig | ok |
| Theme `pawline` aktiv; Plugins pawline-dropship, WooCommerce, Germanized, ATUM, Stripe aktiv | ok |
| Germanized-Optionsname `woocommerce_gzd_small_enterprise` | **bestätigt** (`yes`); Germanized-Text: „Kein Mehrwertsteuerausweis, da Kleinunternehmer nach §19 (1) UStG.“ |
| `tests/run.php` (92 Logiktests, davon 16 neu zum Ablageort) | 92/92 |
| `tests/wp-smoke.php` | 0 Fehler, 1 Warnung (Germanized Pro fehlt). Enthält: Einstellungen (DE, EUR, Steuerberechnung aus, Gastbestellung), Shopseiten, Post-Typen nicht öffentlich/nicht in REST, Rechte, Templates aus dem Theme, WooCommerce-Blöcke registriert, alle 7 Muster, Download ohne Anmeldung → HTTP 400 |
| Belegablage in WordPress | `/var/pawline-private` außerhalb von ABSPATH; Testbeleg gespeichert + Prüfsumme ok; ohne `PAWLINE_PRIVATE_DIR` bzw. mit Pfad im Uploadordner **gesperrt**, kein Ordner im Uploadverzeichnis angelegt |
| Demodaten anlegen / entfernen (T-27) | ok, danach 0 Demo-Produkte und keine Demo-Kategorie |
| `tests/check_blocks.py` | bestanden |

### ✅ Kaufablauf im Browser (`tests/e2e/checkout.mjs`)

Gastbestellung „[DEMO] Einfaches Produkt“ mit der **nur in der CI** aktivierten Offline-Zahlart „Überweisung (nur CI-Test)“ und „Versand (nur CI-Test)“ ohne Kosten.

| Schritt | Ergebnis |
|---|---|
| Produktseite | lädt, Preis 19,90 €, kein USt-Ausweis |
| Warenkorb | Produkt + 19,90 €; Germanized-Hinweis „Kein Mehrwertsteuerausweis, da Kleinunternehmer nach §19 (1) UStG.“ |
| Kasse (Block-Kasse) | Germanized-Hinweis, Pflichtfelder, Zahlart, AGB/Widerruf-Bestätigung, Summe 19,90 €, Schaltfläche „Jetzt kaufen“ |
| Bestellabschluss | Weiterleitung zur Bestellbestätigung mit Produkt und Summe |
| Bestell-E-Mail (Mailpit) | „Bestätigung deiner Bestellung …“, mit Germanized-Hinweis, kein USt-Ausweis |
| Bestellung in der DB (`tests/ci/verify-order.php`) | Status „In Wartestellung“, 19,90 EUR, Steuerbetrag 0, keine Steuerposten |
| JavaScript-Fehler im Browser | keine |

### ⚠️ Befunde

| Befund | Bewertung / nächster Schritt |
|---|---|
| Auf **Produktseite** und **Bestellbestätigung** zeigt Germanized im Block-Theme keinen eigenen § 19-Hinweis; sichtbar ist dort nur der feste Text im Fußbereich des Themes („Gemäß § 19 UStG wird keine Umsatzsteuer berechnet und ausgewiesen.“) | Hinweis ist auf jeder Seite vorhanden, aber nicht am Preis. Mit Tim klären, ob das genügt; sonst Germanized-Einstellung/-Block für Preisangaben im Website-Editor ergänzen (keine eigene Steuerlogik). Offene Aufgabe 5 in `CLAUDE.md`. |
| Seitentitel der WooCommerce-Seiten auf Englisch („Cart“, „Checkout“, „My account“), Beispielseite „Sample Page“ vorhanden | Kosmetik der Testinstallation; bei der Einrichtung auf dem Hosting deutsche Titel setzen, Beispielseite löschen. |
| Fehler der ersten Läufe | `wp wc product_cat create` scheitert unter WooCommerce 11.3 („Fehlende(r) Parameter: name“) → Demodaten nutzen jetzt die WooCommerce-PHP-API. Doppelte `WP_DEBUG`-Definition → behoben. Übrige Fehler lagen im Testskript (Warten auf geladene Blöcke). |

### ⏸️ Weiterhin NICHT geprüft

| Bereich | Grund |
|---|---|
| Kundenrechnung, Nummernkreis, § 19-Hinweis auf dem Beleg, Rechnung als E-Mail-Anhang (T-08 bis T-10) | Germanized Pro fehlt (Lizenz) |
| Storno-/Korrekturbelege (T-11) | Germanized Pro fehlt |
| Stripe-Zahlungen, abgelehnte Karte, Webhooks (T-06, T-12) | Keine Stripe-Testschlüssel; im Test nur Offline-Zahlart |
| Versandkosten | Keine echten Versandregeln festgelegt |
| Website-Editor (ungültige Blöcke), Handy-Ansicht, Gestaltung (T-01 bis T-03) | Manuelle Prüfung |
| Plugin-Abläufe im Backend: Lieferantenauftrag, Belegupload über das Formular, Tracking, Auswertung, CSV (T-13 bis T-25) | Noch nicht automatisiert; manuelle Prüfung |
| PowerShell-Skript `pawline.ps1` | Nur unter Windows prüfbar |
| Hosting-Server (nginx/Apache, echte `wp-config.php`) | Nur Docker-Testumgebung |


## Stand 16.11.2026 (früher) – Erstellung in einer Cloud-Umgebung ohne WordPress

**Umgebung:** Linux, PHP 8.3.6, Python 3, Docker Compose CLI (ohne laufenden Docker-Dienst).
**Gesperrt:** Downloads von `wordpress.org`, `downloads.wordpress.org`, `repo.packagist.org`, `registry-1.docker.io` (HTTP 403 über den Proxy). Kein Docker-Dienst, kein PowerShell.
**Folge:** WordPress, WooCommerce, Germanized, ATUM und Stripe konnten nicht installiert werden. **Keine Funktion wurde innerhalb von WordPress getestet.**

### ✅ Hier geprüft

| Prüfung | Ergebnis |
|---|---|
| `php -l` aller 24 PHP-Dateien (Theme, Plugin, Tests, mu-plugin) | keine Syntaxfehler |
| `tests/run.php` – 76 Logiktests: Betragseingabe (deutsch/englisch), Status/Währung/Datum/ID-Listen, Rohdifferenz in Cent inkl. Erstattung, stornierte/fehlende/fremdwährige Einkäufe, Vollständigkeitskennzeichen, Dateityp nach Inhalt (PDF/XML, getarnte PHP-Datei abgelehnt), geschützte Ablage (Zufallsname, .htaccess/index.php, Rechte 0640, SHA-256, Manipulationserkennung, Pfad-Traversal abgewiesen), CSV (BOM, Semikolon, Formelschutz), Laden des Plugins mit/ohne WooCommerce, Registrierung aller Hooks, Rechte-Liste | 76/76 bestanden |
| `tests/check_blocks.py` – 22 Theme-Dateien, 349 Block-Kommentare: JSON gültig, Verschachtelung korrekt, Template-Parts und Muster-Slugs vorhanden, Pattern-Header, Pflicht-Templates vorhanden | bestanden |
| Muster-PHP mit Stub-Funktionen gerendert | ohne Fehler |
| `theme.json` gültiges JSON (Schema v3) | ok |
| `docker compose --profile tools config` | gültig; 4 Dienste (db, wordpress, wpcli, mailpit) |
| `sh -n` für `setup-wp.sh`, `demo-data.sh`, `demo-remove.sh` | Syntax ok |

### ❌ Noch NICHT geprüft (erfordert WordPress auf dem PC)

| Bereich | Was offen ist |
|---|---|
| PowerShell | `scripts/pawline.ps1` wurde nicht ausgeführt (kein PowerShell verfügbar). |
| Container | Images laden, Start, Healthcheck, Rechte der Belegablage (`chown 33:33`). |
| Setup-Skript | Gesamter Ablauf; insbesondere `wp wc tool run install_pages` und der **Germanized-Optionsname** `woocommerce_gzd_small_enterprise` (nicht gegen die Plugin-Version verifiziert). |
| Block-Theme | Ob der Block-Editor alle Vorlagen/Muster als gültig akzeptiert; Darstellung; WooCommerce-Blockattribute (`product-collection`, `product-price`, `post-excerpt`-Namespace) mit der installierten WC-Version. Produktfilter-Block noch nicht eingebaut. |
| § 19 UStG | Anzeige des Kleinunternehmerhinweises durch Germanized im Block-Theme (Produkt, Warenkorb, Kasse, E-Mail). |
| Plugin in WordPress | Speichern der Meta-Boxen, Datei-Upload über das Formular, Download-Schutz in der Praxis, HPOS-Meta-Box und Spalten, Bestellnotizen, Überblick, Auswertung mit echten Bestellungen, CSV-Download, Löschschutz. |
| Germanized Pro | Alle Rechnungs-/Storno-Tests (Lizenz fehlt). |
| ATUM | Tatsächlicher Funktionsumfang mit der WC-Version; kein Lagerzugang bei Dropshipping. |
| Stripe | Testzahlungen, Webhooks, Doppelbenachrichtigung. |
| `tests/wp-smoke.php` | Geschrieben, nicht ausgeführt. |

Nächster Schritt: auf dem PC `pruefen → start → setup → demo → test`, dann `docs/TESTPLAN.md` T-01 bis T-27.
