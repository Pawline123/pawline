# Pawline – Shop, Rechnungen und Warenwirtschaft

WordPress + WooCommerce mit eigenem Block-Theme und einem kleinen Plugin für manuelles Dropshipping und geschützte Eingangsbelege. Ersetzt den bisherigen Shopify-Shop (Migration folgt in Phase 3).

> **Stand:** erste Projektdateien, **noch nicht in WordPress getestet**. Was bereits geprüft ist und was nicht, steht in [docs/TESTSTATUS.md](docs/TESTSTATUS.md).

## Schnellstart (Windows)

Ausführlich mit Installation von Docker Desktop und Claude Code: [docs/EINRICHTUNG-WINDOWS.md](docs/EINRICHTUNG-WINDOWS.md).

```powershell
cd $env:USERPROFILE\Documents\Pawline
powershell -ExecutionPolicy Bypass -File .\scripts\pawline.ps1 start
powershell -ExecutionPolicy Bypass -File .\scripts\pawline.ps1 setup
powershell -ExecutionPolicy Bypass -File .\scripts\pawline.ps1 demo     # optional: [DEMO]-Produkte
powershell -ExecutionPolicy Bypass -File .\scripts\pawline.ps1 test
```

| Adresse | Zweck |
|---|---|
| http://localhost:8080 | Shop (Testumgebung) |
| http://localhost:8080/wp-admin | Verwaltung – Benutzer/Passwort in `.env` |
| http://localhost:8025 | Mailpit: alle Shop-E-Mails (Bestellung, Rechnung) landen hier |

Alle Befehle: `.\scripts\pawline.ps1 hilfe`

## Aufbau

```
wp-content/themes/pawline/          Eigenes Block-Theme (Gestaltung, Vorlagen, Abschnitte)
wp-content/plugins/pawline-dropship/ Eigenes Plugin (Lieferantenaufträge, Belege, Versand, Auswertung)
dev/mu-plugins/                     Nur lokal: E-Mails an Mailpit umleiten
scripts/                            Steuerung (PowerShell) und Einrichtung (WP-CLI)
tests/                              Logiktests, Block-Prüfung, WordPress-Integrationsprüfung
docs/                               Anleitungen, Testplan, Teststatus, Erweiterungs-Konfiguration
private-plugins/                    Germanized-Pro-ZIP hier ablegen (nicht im Repository)
```

Laufzeitdaten (Datenbank, WordPress-Kern, fremde Plugins, Uploads, Belege) liegen in Docker-Volumes, **nicht** im Projektordner. Fremde Plugins und WordPress-Kerndateien werden nie bearbeitet.

## Inhalte und Gestaltung bearbeiten

Im Backend unter **Design › Editor** (Website-Editor):

| Was | Wo bearbeiten | Wo gespeichert |
|---|---|---|
| Farben, Schriften, Schriftgrößen, Abstände | Editor › Stile | Datenbank (überschreibt `theme.json`) |
| Kopf- und Fußbereich, Navigation | Editor › Muster › Template-Teile / Navigation | Datenbank |
| Startseite, Produktseiten, Kategorieseiten | Editor › Templates | Datenbank, sobald gespeichert; vorher aus dem Theme |
| Normale Seiten (Kontakt, Rechtstexte) | Seiten | Datenbank |
| Produkte, Preise, Bilder, Kategorien | Produkte | Datenbank |
| Grundgestaltung und Ausgangsvorlagen | `theme.json`, `templates/`, `parts/`, `patterns/` | Theme-Dateien |

**Wichtig:** Ein Theme-Update überschreibt keine im Editor gespeicherten Anpassungen. Die Datenbankversion hat Vorrang. Neue Theme-Vorlagen erscheinen erst, wenn die Anpassung im Editor über „Anpassungen zurücksetzen“ entfernt wird. Wer im Editor geänderte Vorlagen ins Theme übernehmen möchte, exportiert sie (Editor › ⋮ › Exportieren) und lässt Claude Code die Dateien einpflegen.

Schriften kommen bewusst vom System (keine Google-Fonts-Einbindung, Datenschutz). Eigene Schriftdateien lassen sich später lokal im Theme ablegen.

## Täglicher Ablauf im Backend (Menü „Pawline“)

1. **Überblick** zeigt bezahlte Bestellungen ohne Lieferantenauftrag („Beim Lieferanten bestellen“).
2. Im Lieferantenportal **manuell** bestellen.
3. „Lieferantenauftrag erfassen“: Auftragsnummer, gezahlten Betrag (brutto) und Status eintragen.
4. Lieferantenrechnung unter **Eingangsbelege** erfassen: Werte wie auf dem Beleg, Original-PDF/XML hochladen, Lieferantenauftrag zuordnen.
5. In der Bestellung unter **Versand** die Trackingnummer(n) eintragen und den Bestellstatus setzen.
6. Ausgangsrechnung und Storno erstellt Germanized Pro (siehe [docs/ERWEITERUNGEN.md](docs/ERWEITERUNGEN.md)).

Das Plugin bestellt, bezahlt und benachrichtigt nichts automatisch und verändert keinen Lagerbestand. Die **Auswertung** zeigt eine Rohdifferenz mit benannter Grundlage und markierten Lücken. Sie ist keine Gewinnermittlung und keine Buchhaltung.

## Tests

`.\scripts\pawline.ps1 test` führt aus:
- `tests/run.php` – Logiktests (Beträge, Rohdifferenz, Dateiprüfung, Ablage, CSV), laufen auch ohne WordPress.
- `tests/wp-smoke.php` – Prüfung in WordPress (Plugins, Einstellungen, Rechte, Templates, Blöcke, Download-Schutz).
- `tests/check_blocks.py` – statische Prüfung des Block-Markups (wenn Python installiert ist).

Die Abnahme mit echten Testbestellungen folgt [docs/TESTPLAN.md](docs/TESTPLAN.md).

## Sicherung und Wiederherstellung

```powershell
.\scripts\pawline.ps1 sicherung                         # -> backups\db-*.sql, uploads-*.tar.gz, belege-*.tar.gz
.\scripts\pawline.ps1 wiederherstellen backups\db-....sql
```
Uploads/Belege zurückspielen: `docker compose --profile tools run --rm wpcli tar -xzf /project/backups/uploads-<zeit>.tar.gz -C /var/www/html/wp-content` (für Belege `-C /var`). Die Wiederherstellung einmal vor dem Live-Gang erproben.

## Veröffentlichung (später, nicht Teil dieses Schritts)

Noch nichts ist gebucht oder umgestellt. Der bestehende Shopify-Shop und die Domain bleiben unverändert.

1. Webhosting buchen (Vorschlag in der Spezifikation), dort WordPress auf einer **Testadresse** (Subdomain) installieren.
2. `.\scripts\pawline.ps1 paket` erzeugt `dist\pawline-theme.zip` und `dist\pawline-dropship.zip` zum Hochladen.
3. WooCommerce, Germanized (+ Pro), ATUM und Stripe auf dem Server installieren und nach [docs/ERWEITERUNGEN.md](docs/ERWEITERUNGEN.md) konfigurieren.
4. In der `wp-config.php` des Servers die Belegablage **außerhalb des Web-Verzeichnisses** festlegen:
   ```php
   define( 'PAWLINE_PRIVATE_DIR', '/absoluter/pfad/ausserhalb/htdocs/pawline-belege' );
   ```
   Ohne diese Angabe liegen Belege unter `wp-content/uploads/pawline-private/` und sind nur per `.htaccess` gesperrt. Das wirkt nicht bei nginx-Servern.
5. Testplan auf der Testadresse vollständig durchlaufen, dann Migration (Phase 3) und Domainumstellung planen.

Zugangsdaten (Hosting, Stripe, Lizenzschlüssel) werden nur im jeweiligen Backend oder in der `wp-config.php` des Servers hinterlegt, nie im Projektordner.
