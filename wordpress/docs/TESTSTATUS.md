# Teststatus

Bitte nach jeder Prüfung aktualisieren. Nur eintragen, was tatsächlich ausgeführt wurde.

## Stand 16.11.2026 – Erstellung in einer Cloud-Umgebung ohne WordPress

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
