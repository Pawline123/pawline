# Teststatus

## Zusatz 16.11.2026 – Vorbereitung einer privaten Codespaces-Vorschau

- Neue Konfiguration: `.devcontainer/devcontainer.json`, Compose-Override und `scripts/codespaces-preview.sh`; Bedienung: `docs/VORSCHAU-HANDY.md`.
- Lokal geprüft: JSON/YAML-Struktur, Bash-Syntax und **8/8 isolierte Lifecycle-Tests** mit simuliertem Docker (`tests/test_codespaces_preview.py`). Darunter Start außerhalb Codespaces abgewiesen, ungültige URL-Bestandteile abgewiesen, getrennte zufällige Zugangsdaten ohne Ausgabe im Protokoll, Berechtigung 0600, Wiederaufnahme ohne Neuinstallation, erneuter Versuch nach teilweise fehlgeschlagenem Setup sowie Stoppen ohne Datenlöschung. Die lokale `.env` bleibt unverändert.
- **Nicht geprüft:** echter Codespaces-/Docker-Start, privater Zugriff über die erzeugte URL, HTTPS-/Login-Ablauf. In der aktuellen Bearbeitungsumgebung sind weder Docker noch PHP installiert. Es wurde kein Codespace gestartet und keine neue laufende Vorschau behauptet.
- Der neue Workflow `.github/workflows/codespaces-preview.yml` prüft diese Vorbereitung zusätzlich; er startet keine Vorschau. Sein tatsächlicher Laufstatus ist separat auf GitHub zu prüfen.
- Persönliches Nutzungskontingent/Abrechnung vor dem Codespaces-Start prüfen. Ports 8080 und 8025 müssen Private bleiben. Kein DNS-Wechsel, keine Veröffentlichung des Shops.


Bitte nach jeder Prüfung aktualisieren. Nur eintragen, was tatsächlich ausgeführt wurde.

## Stand 16.11.2026 – GitHub Actions (Workflow „WordPress-Prüfung“)

**Umgebung:** GitHub-Runner `ubuntu-24.04`, Docker-Testumgebung aus `docker-compose.yml` (Apache, PHP 8.3, MariaDB 11.4, Mailpit), Chromium über Playwright 1.49.1.
**Installiert durch `setup-wp.sh`:** WordPress (aktuell), WooCommerce 11.3.0, Germanized 4.1.7, ATUM 2.0.7, WooCommerce Stripe Gateway 11.2.0, Theme `pawline` 0.1.0, Plugin `pawline-dropship` 0.1.0. **Nicht installiert:** Germanized Pro (Lizenz fehlt).
**Aktueller Stand:** Lauf 16 (Commit ba704e8) – alle Schritte grün, einschließlich Handy-Ansicht, Verwaltung und Editor; einheitlicher § 19-Hinweis und neuer Handy-Kopfbereich. Screenshots jedes Laufs: Branch `ci-screenshots` (https://github.com/Pawline123/pawline/tree/ci-screenshots). Erster vollständig grüner Lauf: Lauf 6.
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

**§ 19-Prüfung (seit Lauf 9 streng):** Der Hinweis muss geometrisch direkt beim Preis bzw. Gesamtbetrag stehen (gleiche Spalte, max. 48 px unter dem Preis, 60–80 px unter der Gesamtsumme). Treffer im Kopf- oder Fußbereich zählen **nie**; steht der Hinweis nur dort, schlägt der Test fehl. Text überall aus Germanized, seit Lauf 14 einheitlich: **„Umsatzsteuerbefreit gemäß § 19 UStG.“** (Option `woocommerce_gzd_small_enterprise_text` in `setup-wp.sh`; Fußbereich gleichlautend). Die Tests akzeptieren nur diesen Wortlaut und melden den alten Germanized-Standardtext als Fehler.

| Schritt | Ergebnis (Lauf 9) |
|---|---|
| Produktübersicht (Shop) | Demoprodukt mit 19,90 € und Schaltfläche „In den Warenkorb“ in der Karte; **§ 19-Hinweis 12 px unter dem Preis** der Karte; kein USt-Ausweis |
| Produktseite | Preis 19,90 €; **§ 19-Hinweis 20 px unter dem Preis**; kein USt-Ausweis |
| Warenkorb | Produkt + 19,90 €; **§ 19-Hinweis 46 px unter „Veranschlagte Gesamtsumme 19,90 €“** |
| Kasse (Block-Kasse) | **§ 19-Hinweis 32 px unter „Gesamtsumme“**; Pflichtfelder, Zahlart, AGB/Widerruf-Bestätigung, Summe 19,90 €, Schaltfläche „Jetzt kaufen“ |
| Bestellbestätigung | Produkt + Summe; **§ 19-Hinweis 18 px unter „Gesamt: 19,90 €“** |
| Bestell-E-Mail (Mailpit) | „Bestätigung deiner Bestellung …“, mit Germanized-Hinweis, kein USt-Ausweis |
| Bestellung in der DB (`tests/ci/verify-order.php`) | Status „In Wartestellung“, 19,90 EUR, Steuerbetrag 0, keine Steuerposten |
| JavaScript-Fehler im Browser | keine |

**Umsetzung:** Produktseite und alle Produktlisten (Shop, Kategorie, Schlagwort, Suche, Muster „Neue Produkte“) enthalten den Germanized-Block „Steuerhinweis“ (`woocommerce-germanized/product-tax-info`) direkt unter dem Preisblock. In der Bestellbestätigung gibt Germanized im Block-Theme selbst nichts aus; das Theme hängt dort Germanizeds Vorlage `global/small-business-info.php` an den Block „Bestellbestätigung – Summen“ (`functions.php`, nur wenn `wc_gzd_is_small_business()`). Keine eigene Steuerlogik. Warenkorb, Kasse und E-Mail: Germanized selbst.

### ✅ Handy-Ansicht (`tests/e2e/mobile.mjs`, iPhone 13, 390 × 844) – Lauf 12

| Seite | Ergebnis |
|---|---|
| Startseite | kein seitliches Scrollen, Grundschrift 17 px, Menü (☰) öffnet: Kasse · Kontakt · Mein Konto · Shop · Warenkorb |
| Produktübersicht | kein seitliches Scrollen; „In den Warenkorb“ 160 × 78 px; § 19-Hinweis und „zzgl. Versandkosten“ unter dem Preis |
| Produktseite | kein seitliches Scrollen; „In den Warenkorb“ 191 × 51 px; § 19-Hinweis und „zzgl. Versandkosten“ unter dem Preis |
| Warenkorb | „Weiter zur Kasse“ 342 × 51 px; § 19-Hinweis unter der Gesamtsumme |
| Kasse | „Jetzt kaufen“ 342 × 48 px; § 19-Hinweis 46 px unter „Bestellübersicht 19,90 €“ (Lauf 11 noch Fehler: WooCommerce klappt die Übersicht auf dem Handy zu → behoben, siehe Befunde) |

**Kopfbereich (seit Lauf 16):** eine Zeile Titel | ☰ | Konto | Warenkorb (Kopfhöhe 45 px), Tippflächen je 44 × 44 px, Abstand Kopfbereich → Brotkrumen/Seitentitel auf allen geprüften Seiten 20 px (vorher ca. 70 px). `mobile.mjs` prüft Zeile, Reihenfolge, Tippflächen und Abstand (12–28 px).

**Gestalterische Beobachtungen (offen):** Auf der Startseite fehlen bei „Neu im Shop“ die Bildflächen, solange Produkte kein Bild haben. Der Testtitel „Pawline (TESTUMGEBUNG)“ bricht im Kopfbereich zweizeilig um (mit „Pawline“ nicht).

### ✅ Verwaltung (`tests/e2e/admin.mjs` + `tests/ci/verify-admin.php`) – Lauf 11/12

| Schritt | Ergebnis |
|---|---|
| Pawline › Überblick | bezahlte Testbestellung unter „Beim Lieferanten bestellen“; „Lieferantenauftrag erfassen“ füllt die Bestellnummer vor |
| Lieferantenauftrag | gespeichert (Lieferant, Auftragsnr., 8,50, Status „bestellt“, Datum), mit Bestellung verknüpft, Bestellnotiz, Änderungsprotokoll |
| Eingangsbeleg | PDF + XML hochgeladen, dem Auftrag zugeordnet, beide „✔ unverändert“, Dateien in `/var/pawline-private` (außerhalb Webroot), Download als Admin liefert die Originaldatei |
| Getarnte PHP-Datei (T-20) | abgelehnt mit Hinweis „Nur PDF, XML (E-Rechnung), JPG oder PNG …“ |
| Tracking in der Bestellung | gespeichert, Bestellnotiz „Versandinfo erfasst“, **keine** Kundenbenachrichtigung |
| Auswertung | Rohdifferenz mit Grundlage (§ 19), keine Bezeichnung „Gewinn“ |
| CSV-Export | Lieferantenaufträge und Eingangsbelege: UTF-8 mit BOM, Semikolon, Betrag „8,50“, SHA-256 |

Hinweis: Nach der ersten Anmeldung leitet Germanized auf seinen Einrichtungsassistenten weiter (`wc-gzd-setup`); harmlos, beim echten Setup einmal durchklicken.

### ✅ WordPress-Editor (`tests/e2e/editor.mjs`) – Lauf 11/12

| Prüfung | Ergebnis |
|---|---|
| Website-Editor: alle 13 Vorlagen + Template-Teile header/footer | **0 ungültige Blöcke**, kein fehlender Blocktyp, keine Validierungsmeldung in der Konsole |
| Alle 7 Pawline-Muster in eine Seite eingefügt, gespeichert, neu geladen | alle Blöcke gültig |
| Seite bearbeiten: Titel/Absatz getippt, Bild in die Mediathek hochgeladen und eingefügt, Spalten-Layout, Block verschoben | gespeichert, neu geladen: alle gültig; im Frontend sichtbar |
| Startseiten-Vorlage im Website-Editor geändert | gespeichert, gültig, auf der Startseite sichtbar |

### ✅ Seitentitel

Warenkorb (`/warenkorb/`), Kasse (`/kasse/`), Mein Konto (`/mein-konto/`), Shop; Beispielseite gelöscht (`setup-wp.sh`, geprüft in `wp-smoke.php`).

### ⚠️ Befunde

| Befund | Bewertung / nächster Schritt |
|---|---|
| ~~Produktübersicht: § 19-Hinweis doppelt (Theme-Block + Germanized-Listenausgabe unter der Schaltfläche)~~ | Behoben: Germanized-Listenausgabe für Steuer- und Versandhinweis aus (`woocommerce_gzd_display_product_loop_tax/_shipping_costs = no`), stattdessen Germanized-Blöcke direkt unter dem Preis; Test prüft „genau einmal je Karte“. |
| ~~Produktseite ohne „zzgl. Versandkosten“~~ | Behoben: Germanized-Block „Versandkostenhinweis“ unter dem Preis; Test prüft den Abstand. |
| ~~Kasse auf dem Handy: § 19-Hinweis nur im Fußbereich~~ | Behoben (Lauf 12): Germanized-Vorlage zwischen zugeklappter Bestellübersicht und „Jetzt kaufen“, nur bei schmaler Ansicht (`functions.php`, `style.css`). |
| ~~§ 19-Hinweis auf Produktseite und Bestellbestätigung nur im Fußbereich~~ | **Behoben in Lauf 9**: jetzt direkt am Preis bzw. Gesamtbetrag (siehe oben). Wortlaut stammt aus Germanized (Einstellungen › Germanized › Allgemein › Kleinunternehmerregelung) und kann dort angepasst werden, z. B. „Umsatzsteuerbefreit gemäß § 19 UStG.“ |
| ~~Seitentitel auf Englisch, Beispielseite vorhanden~~ | Behoben in `setup-wp.sh`. |
| Fehler der ersten Läufe | `wp wc product_cat create` scheitert unter WooCommerce 11.3 („Fehlende(r) Parameter: name“) → Demodaten nutzen jetzt die WooCommerce-PHP-API. Doppelte `WP_DEBUG`-Definition → behoben. Übrige Fehler lagen im Testskript (Warten auf geladene Blöcke). |

### ⏸️ Weiterhin NICHT geprüft

| Bereich | Grund |
|---|---|
| Kundenrechnung, Nummernkreis, § 19-Hinweis auf dem Beleg, Rechnung als E-Mail-Anhang (T-08 bis T-10) | Germanized Pro fehlt (Lizenz) |
| Storno-/Korrekturbelege (T-11) | Germanized Pro fehlt |
| Stripe-Zahlungen, abgelehnte Karte, Webhooks (T-06, T-12) | Keine Stripe-Testschlüssel; im Test nur Offline-Zahlart |
| Versandkosten | Keine echten Versandregeln festgelegt |
| Gestalterische Feinheiten (Farben, Abstände) | Screenshots liegen vor; Entscheidung durch Tim |
| Plugin-Sonderfälle: ein Auftrag für mehrere Bestellungen, ungültige Eingaben, Löschschutz, Bestandswarnung (T-15, T-16, T-21, T-23) | Noch nicht automatisiert |
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

