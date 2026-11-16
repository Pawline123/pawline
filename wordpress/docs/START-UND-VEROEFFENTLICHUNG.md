# WordPress starten und später veröffentlichen

Diese Anleitung gilt für den Ordner `wordpress/` im Repository `Pawline123/pawline`.
Der bestehende Next.js-Shop (Repository-Wurzel) und der Shopify-Shop unter `pawline-shop.de` bleiben während der Entwicklung unverändert.

> **Stand:** Nichts davon wurde bisher in einer echten WordPress-Installation ausgeführt. Was geprüft ist, steht in [TESTSTATUS.md](TESTSTATUS.md).

---

## Teil A – Lokal starten (Windows-PC)

### A1. Einmalig: Werkzeuge installieren

Docker Desktop (mit WSL 2), Git und Claude Code; Python ist optional. Einzelheiten und Links: [EINRICHTUNG-WINDOWS.md](EINRICHTUNG-WINDOWS.md), Abschnitt 1.

### A2. Einmalig: Repository holen

```powershell
cd $env:USERPROFILE\Documents
git clone https://github.com/Pawline123/pawline.git
cd pawline
git checkout claude/epic-planck-kp1z8a    # nach dem Zusammenführen: main
cd wordpress
```

Alle weiteren Befehle laufen **im Ordner `wordpress`**, da dort `docker-compose.yml` und `scripts\` liegen.
Die Germanized-Pro-ZIP (falls gekauft) nach `wordpress\private-plugins\` kopieren – sie wird nicht eingecheckt.

### A3. Starten

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\pawline.ps1 pruefen   # Docker, Ports, .env
powershell -ExecutionPolicy Bypass -File .\scripts\pawline.ps1 start     # Container starten; legt .env mit Zufallspasswörtern an
powershell -ExecutionPolicy Bypass -File .\scripts\pawline.ps1 setup     # nur beim ersten Mal: WordPress, WooCommerce, Germanized, ATUM, Stripe, Theme, Plugin
powershell -ExecutionPolicy Bypass -File .\scripts\pawline.ps1 demo      # optional: [DEMO]-Produkte
powershell -ExecutionPolicy Bypass -File .\scripts\pawline.ps1 test      # Logiktests, WordPress-Prüfung, Block-Prüfung
```

| Adresse | Zweck |
|---|---|
| http://localhost:8080 | Shop |
| http://localhost:8080/wp-admin | Verwaltung – Benutzer `pawline-admin`, Passwort in `wordpress\.env` (`WP_ADMIN_PASSWORD`) |
| http://localhost:8025 | Mailpit – alle Shop-E-Mails landen hier, nichts geht nach außen |

Der erste Start lädt mehrere hundert MB und dauert einige Minuten.

### A4. Alltag

| Befehl | Wirkung |
|---|---|
| `start` / `stop` | Testumgebung starten / anhalten (Daten bleiben erhalten) |
| `logs` | WordPress-Protokoll anzeigen |
| `sicherung` | Datenbank, Uploads und Belege nach `backups\` |
| `wiederherstellen backups\db-….sql` | Datenbank zurückspielen |
| `demo-entfernen` | alle `[DEMO]`-Daten löschen |
| `zuruecksetzen` | lokale Testumgebung komplett löschen |

Änderungen an Theme und Plugin wirken sofort, weil die Ordner `wp-content\themes\pawline` und `wp-content\plugins\pawline-dropship` direkt in den Container eingebunden sind. Im Website-Editor gespeicherte Änderungen liegen dagegen in der Datenbank (siehe [README](../README.md#inhalte-und-gestaltung-bearbeiten)).

Probleme beim Start: [EINRICHTUNG-WINDOWS.md](EINRICHTUNG-WINDOWS.md), Abschnitt 6.

---

## Teil B – Veröffentlichung (später)

Die lokale Docker-Umgebung ist **nur für den PC** gedacht und wird nicht auf den Server kopiert. Auf dem Webhosting wird WordPress neu installiert; übertragen werden nur das Pawline-Theme und das Pawline-Plugin. Produkte kommen über den Shopify-Import (Phase 3) hinzu, nicht aus der lokalen Datenbank mit Demodaten.

Noch ist nichts gebucht oder umgestellt. Jeder Schritt, der Kosten verursacht oder die Domain betrifft, erfolgt erst nach Tims Entscheidung.

### B1. Voraussetzungen

- [ ] Lokaler Testplan ([TESTPLAN.md](TESTPLAN.md)) durchlaufen, Ergebnisse in [TESTSTATUS.md](TESTSTATUS.md)
- [ ] Webhosting gebucht (Vorschlag der Spezifikation: ALL-INKL Premium, 9,95 €/Monat; Leistung im Testbetrieb prüfen) mit PHP ≥ 8.1, MySQL/MariaDB, HTTPS (Let's Encrypt), möglichst SSH
- [ ] Zugang zur Domainverwaltung (DNS) von `pawline-shop.de` geklärt
- [ ] Germanized-Pro-Lizenz, Stripe-Konto (zunächst Testschlüssel)
- [ ] Echte Firmen-, Rechnungs-, Versand- und Rechtsangaben von Tim (Impressum, Datenschutz, AGB, Widerruf – bisher nur Platzhalter)
- [ ] Shopify-Produktexport (CSV) und Bilder für Phase 3

### B2. Testadresse auf dem Hosting einrichten

1. Im Hosting eine Subdomain anlegen, z. B. `neu.pawline-shop.de` (nur ein zusätzlicher DNS-Eintrag; der Shopify-Shop bleibt unter der Hauptadresse unverändert). HTTPS-Zertifikat aktivieren.
2. Auf der Subdomain WordPress über das Installationswerkzeug des Hosters oder manuell installieren. Sprache Deutsch.
3. Den Zugriff bis zum Livegang sperren (Passwortschutz des Hosters oder ein Wartungsmodus) und unter *Einstellungen › Lesen* „Suchmaschinen davon abhalten …“ aktivieren.
4. In der `wp-config.php` des Servers ergänzen:
   ```php
   define( 'DISALLOW_FILE_EDIT', true );
   define( 'WP_ENVIRONMENT_TYPE', 'staging' );   // beim Livegang: 'production'
   define( 'PAWLINE_PRIVATE_DIR', '/absoluter/pfad/ausserhalb/des/webverzeichnisses/pawline-belege' );
   ```
   Den Ordner für die Belege anlegen und für PHP beschreibbar machen. Ohne `PAWLINE_PRIVATE_DIR` liegen Belege unter `wp-content/uploads/pawline-private/` und sind nur per `.htaccess` geschützt – das wirkt nicht auf nginx-Servern.

### B3. Theme und Plugin übertragen

Auf dem PC im Ordner `wordpress`:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\pawline.ps1 paket
```

Ergebnis: `dist\pawline-theme.zip` und `dist\pawline-dropship.zip`. Im Server-Backend hochladen:
- *Design › Themes › Theme hinzufügen › Theme hochladen* → `pawline-theme.zip`, aktivieren
- *Plugins › Installieren › Plugin hochladen* → `pawline-dropship.zip`, aktivieren

Bei späteren Updates dieselben ZIP-Dateien erneut hochladen (WordPress bietet „Ersetzen“ an). Im Website-Editor gespeicherte Anpassungen bleiben dabei erhalten.

Das Mailpit-Plugin aus `dev\mu-plugins` wird **nicht** übertragen.

### B4. Erweiterungen auf dem Server

WooCommerce, Germanized (+ Pro), ATUM und WooCommerce Stripe Gateway installieren und **genau wie in [ERWEITERUNGEN.md](ERWEITERUNGEN.md)** einstellen. Wichtig:
- Steuerberechnung aus, Germanized-Kleinunternehmerregelung (§ 19 UStG) an
- Stripe zunächst im **Testmodus**, Webhook auf die Testadresse
- Bisherige Rechnungsnummern aus Shopify berücksichtigen (Startnummer in Germanized Pro)
- E-Mail-Versand über das Postfach/SMTP des Hosters prüfen

Zugangsdaten, Lizenz- und Stripe-Schlüssel nur im jeweiligen Backend bzw. in der `wp-config.php` des Servers hinterlegen – nie im Repository.

### B5. Inhalte und Abnahme auf der Testadresse

1. Rechtstexte und Firmenangaben einsetzen, Platzhalterseiten veröffentlichen.
2. Produkte per Shopify-Import übernehmen (Phase 3), URL-Zuordnung und Weiterleitungen anlegen.
3. Testplan erneut auf der Testadresse: Testzahlung mit Stripe, Bestell- und Rechnungs-E-Mails, Storno, ein vollständiger manueller Lieferantenablauf, Belegupload und -download.
4. Sicherung über den Hoster (Dateien + Datenbank + Belegordner) einrichten und **eine Wiederherstellung erproben**.

### B6. Umstellung der Domain (Livegang)

1. Einige Tage vorher die TTL der betroffenen DNS-Einträge auf z. B. 300 Sekunden senken.
2. Shopify-Bestellungen bis zum Umstellungszeitpunkt abarbeiten; letzten Datenabgleich (neue Produkte, Preise, Bestand) durchführen.
3. Im Hosting `pawline-shop.de` (und `www`) auf die WordPress-Installation zeigen lassen; Adresse unter *Einstellungen › Allgemein* auf `https://pawline-shop.de` ändern und alte Subdomain-Links in der Datenbank ersetzen (z. B. per `wp search-replace` über SSH, vorher sichern).
4. Nur die **Website-Einträge** (A/AAAA bzw. CNAME für `@` und `www`) ändern. **MX-, SPF-, DKIM- und sonstige E-Mail-Einträge nicht anfassen.**
5. Stripe von Test- auf Live-Schlüssel umstellen, Webhook auf die Hauptdomain; eine echte Kleinbestellung durchführen und erstatten.
6. Suchmaschinensperre und Zugriffsschutz entfernen, `WP_ENVIRONMENT_TYPE` auf `production`.
7. Shopify noch nicht kündigen, sondern einige Wochen als Rückfallebene behalten. Rückweg: DNS-Einträge wieder auf Shopify zeigen lassen.

### B7. Auswirkung auf den Next.js-Shop

Der Next.js-Shop (Repository-Wurzel, Vercel) lädt Produkte von `https://pawline-shop.de/products.json` und leitet in den Shopify-Warenkorb unter `pawline-shop.de/cart` weiter. **Sobald die Domain auf WordPress zeigt, funktionieren beide Funktionen nicht mehr.** Vor der Domainumstellung also entscheiden:

- Vercel-Projekt abschalten bzw. auf WordPress weiterleiten (naheliegend, da WordPress den Shop vollständig ersetzt), **oder**
- den Next.js-Shop auf die `*.myshopify.com`-Adresse des Shops umstellen, solange Shopify weiterläuft.

Bis zur Domainumstellung bleibt der Next.js-Shop unverändert in Betrieb.
