# Einrichtung auf dem Windows-PC

Ziel: lokale Testumgebung von Pawline auf deinem PC, bearbeitbar mit Claude Code. Der Live-Shop wird dabei **nicht** verändert.

## 1. Voraussetzungen installieren (einmalig)

| Werkzeug | Wozu | Installation |
|---|---|---|
| **Docker Desktop** | Läuft WordPress, Datenbank und Mailpit in Containern | https://www.docker.com/products/docker-desktop/ – bei der Installation „WSL 2“ verwenden, danach PC neu starten und Docker Desktop einmal öffnen |
| **Claude Code** | Entwicklung und Tests | PowerShell: `winget install Anthropic.ClaudeCode`, danach neue PowerShell öffnen und `claude --version` |
| Git (empfohlen) | Versionsverwaltung | `winget install Git.Git` |
| Python (optional) | Statische Block-Prüfung | `winget install Python.Python.3.12` |

Prüfen, ob die Virtualisierung aktiv ist: Docker Desktop startet ohne Fehlermeldung und zeigt „Engine running“.

## 2. Projekt ablegen

Das Projekt liegt im GitHub-Repository `Pawline123/pawline` im Ordner `wordpress/` (der Next.js-Shop in der Wurzel bleibt unberührt):

```powershell
cd $env:USERPROFILE\Documents
git clone https://github.com/Pawline123/pawline.git
cd pawline\wordpress
```

Alle folgenden Befehle laufen im Ordner `Documents\pawline\wordpress`. Optional, falls die Lizenz gekauft ist: Germanized-Pro-ZIP nach `private-plugins\` kopieren.

## 3. Testumgebung starten

PowerShell im Projektordner öffnen (Explorer: Ordner öffnen › Adressleiste `powershell` eingeben › Enter):

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\pawline.ps1 pruefen
powershell -ExecutionPolicy Bypass -File .\scripts\pawline.ps1 start
powershell -ExecutionPolicy Bypass -File .\scripts\pawline.ps1 setup
powershell -ExecutionPolicy Bypass -File .\scripts\pawline.ps1 demo
powershell -ExecutionPolicy Bypass -File .\scripts\pawline.ps1 test
```

Der erste Start lädt mehrere hundert MB (Container-Images, WordPress, Plugins) und dauert einige Minuten. Danach:

- Shop: http://localhost:8080
- Verwaltung: http://localhost:8080/wp-admin (Benutzer `pawline-admin`, Passwort in der Datei `.env` unter `WP_ADMIN_PASSWORD`)
- E-Mails: http://localhost:8025

`-ExecutionPolicy Bypass` gilt nur für diesen einen Aufruf und ändert keine Systemeinstellung.

## 4. Mit Claude Code weiterarbeiten

```powershell
cd $env:USERPROFILE\Documents\pawline\wordpress
claude
```
Vorschlag für den ersten Auftrag:

> Lies CLAUDE.md und docs/TESTSTATUS.md. Führe die „Offenen Aufgaben für die erste Sitzung“ aus, behebe gefundene Fehler und aktualisiere docs/TESTSTATUS.md mit dem, was tatsächlich geprüft wurde.

## 5. Benötigte Internetadressen

Falls eine Firewall, ein Virenscanner oder ein Firmennetz Downloads blockiert, müssen diese Domains erreichbar sein:

| Domain | Wofür |
|---|---|
| `registry-1.docker.io`, `auth.docker.io`, `production.cloudflare.docker.com` | Container-Images (mariadb, wordpress, wordpress:cli, mailpit) |
| `wordpress.org`, `api.wordpress.org`, `downloads.wordpress.org` | WordPress-Kern, Sprachpakete, Plugins (WooCommerce, Germanized, ATUM, Stripe) |
| `vendidero.de` | Download von Germanized Pro nach dem Kauf (manuell im Browser) |
| `api.stripe.com`, `js.stripe.com` | Stripe-Testzahlungen |
| `claude.ai`, `api.anthropic.com` | Claude Code |

## 6. Häufige Probleme

- **„docker“ wird nicht erkannt:** Docker Desktop installieren bzw. PowerShell neu öffnen.
- **Port 8080 belegt:** in `.env` `WP_PORT=8081` setzen, `stop`, dann `start`.
- **„Docker Desktop läuft nicht“:** Docker Desktop öffnen und warten, bis „Engine running“ angezeigt wird.
- **Setup bricht bei Plugin-Download ab:** Internetverbindung bzw. Domains aus Abschnitt 5 prüfen, `setup` erneut ausführen (wiederholbar).
- **Alles neu beginnen:** `.\scripts\pawline.ps1 zuruecksetzen` (löscht nur die lokale Testumgebung).
