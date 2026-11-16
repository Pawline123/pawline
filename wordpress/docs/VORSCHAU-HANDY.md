# Private Vorschau vom Handy mit GitHub Codespaces

Damit lässt sich das vorhandene WordPress-Projekt ohne eigenen PC im Browser ansehen und bearbeiten. Es wird kein Hostingvertrag abgeschlossen und die Domain `pawline-shop.de` nicht umgestellt.

## 1. Vorschau starten

1. In Safari bei GitHub als `Pawline123` anmelden.
2. [Codespace für den Vorschau-Branch erstellen oder fortsetzen](https://codespaces.new/Pawline123/pawline/tree/codex/private-codespaces-preview?quickstart=1).
3. Auf der GitHub-Seite vor dem Erstellen Konto, Nutzungskontingent und Abrechnung prüfen. Die kleinste passende Maschine wählen (2 Kerne, 8 GB RAM). Für persönliche Konten gibt es ein monatliches Kontingent; verfügbare Restnutzung ist hier nicht bekannt. Über das Kontingent hinaus können Kosten entstehen. Es wurde kein Codespace für dich gestartet.
4. Als Konfiguration `.devcontainer/devcontainer.json` verwenden. Nach **Create codespace** werden Docker und danach WordPress eingerichtet. Der erste Start lädt Images und Plugins und kann mehrere Minuten dauern.
5. Im Bereich **PORTS** muss bei **8080** und **8025** die Sichtbarkeit **Private** stehen. Nicht auf Public umstellen. Port 8080 über **Open in Browser** öffnen. Der Zugriff verlangt deinen GitHub-Login.

Die echte Vorschau-URL wird erst beim Start erzeugt. Sie hat die Form `https://<codespace-name>-8080.app.github.dev`. Dies ist ein Formatbeispiel und kein bereits aktiver Link.

## 2. Verwaltung und Änderungen

An die Shopadresse `/wp-admin/` anhängen. Benutzer ist `pawline-admin`. Das zufällig erzeugte Passwort steht im Codespace in `wordpress/.codespaces/preview.env` bei `WP_ADMIN_PASSWORD`. Diese Datei ist ignoriert und wird nicht veröffentlicht. Passwörter nicht in Chats oder GitHub einfügen.

Theme- und Plugin-Dateien sind eingebunden; Änderungen wirken unmittelbar. Im Website-Editor gespeicherte Inhalte und Layouts bleiben in der Datenbank dieses Codespaces. **Sie werden durch einen Git-Push nicht gesichert.** Vor dem Löschen oder Neuaufsetzen eines Codespaces deshalb Datenbank, Uploads und Belege exportieren, wenn du die Änderungen behalten möchtest. Später erfolgt der Umzug auf den eigenen Server mit Dateien und Datenbank.

Die Umgebung hat gekennzeichnete Demoprodukte. Es gibt keine echten Zahlungszugänge, keine freigegebenen Versandregeln und keine Germanized-Pro-Lizenz. Ohne passende Versand-/Zahlart kann der Kaufabschluss nicht verfügbar sein; das verhindert die Beurteilung des Designs und der Verwaltung nicht. E-Mails werden durch das vorhandene lokale Mail-Plugin in Mailpit aufgefangen (Port 8025).

## 3. Stoppen und wieder öffnen

Nach dem Anschauen auf [github.com/codespaces](https://github.com/codespaces) den Codespace über das Menü **Stop codespace** stoppen. Nur den Browsertab zu schließen ist kein verlässlicher sofortiger Stopp. Nach einem Stopp ist die Vorschau bis zum nächsten Start nicht erreichbar. Das ist eine vorübergehende Vorschau, kein dauerhaftes Hosting.

Beim Fortsetzen startet das Skript die Container wieder und behält vorhandene Passwörter und Daten. Die komplette Installation wird nur beim ersten erfolgreichen Setup ausgeführt. Speicher kann auch bei gestopptem Codespace zum Kontingent zählen; nach Abschluss zuerst eigene Änderungen sichern und danach den Codespace löschen.

Falls das automatische Starten scheitert, im Codespace-Terminal aus der Repository-Wurzel ausführen:

```bash
bash wordpress/scripts/codespaces-preview.sh start
bash wordpress/scripts/codespaces-preview.sh status
```

`zugang` zeigt den Ort der Zugangsdaten, aber nicht das Passwort. `stop` stoppt die WordPress-Container und löscht keine Daten; zum Beenden der Codespaces-Rechenzeit trotzdem den Codespace selbst stoppen.

## Technische Prüfung und Grenzen

Die Codespaces-Konfiguration verwendet das offizielle Docker-in-Docker-Feature. Die vorhandene Docker-Compose-Datei bleibt unverändert; ein kleines Override setzt die Codespaces-Adresse für die Installation. Die offizielle WordPress-Docker-Konfiguration unterstützt HTTPS hinter einem Reverse Proxy.

Hier geprüft: JSON/YAML-Struktur, Bash-Syntax und isolierte Skripttests für Start, Wiederaufnahme, Passwörter, Fehlerfälle und das Nicht-Verändern der lokalen `.env`. **Hier nicht geprüft:** echter Codespaces-Start, Docker-/WordPress-Laufzeit, privater Browserzugriff und HTTPS-/Login-Ablauf. Die Vorschau darf erst nach diesem ersten echten Aufruf als funktionsfähig bezeichnet werden. Die bisherigen WordPress-Tests aus PR #1 ersetzen diese Prüfung nicht.

GitHub-Dokumentation:

- https://docs.github.com/en/codespaces/developing-in-a-codespace/forwarding-ports-in-your-codespace
- https://docs.github.com/en/codespaces/reference/security-in-github-codespaces
- https://docs.github.com/en/codespaces/troubleshooting/troubleshooting-included-usage
- https://docs.github.com/en/codespaces/setting-up-your-project-for-codespaces/setting-up-your-repository/facilitating-quick-creation-and-resumption-of-codespaces
